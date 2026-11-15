<?php
// cetak_progress_pr_pdf.php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// ================= PARAMETER =================
$tipe   = $_GET['tipe']   ?? 'ALL';
$status = $_GET['status'] ?? 'PENDING';

$status_valid = ['PENDING', 'PROSES', 'SELESAI'];
if (!in_array($status, $status_valid, true)) $status = 'PENDING';

$tipe_valid = ['PR KECIL', 'PR BESAR', 'PR IT', 'ALL'];
if (!in_array($tipe, $tipe_valid, true)) $tipe = 'ALL';

// ================= QUERY UTAMA =================
$where_tipe = '';
if ($tipe !== 'ALL') {
    if ($tipe === 'PR BESAR')      $where_tipe = " AND r.no_request LIKE 'PRB%' ";
    elseif ($tipe === 'PR IT')     $where_tipe = " AND r.no_request LIKE 'PRI%' ";
    elseif ($tipe === 'PR KECIL')  $where_tipe = " AND r.no_request NOT LIKE 'PRB%' AND r.no_request NOT LIKE 'PRI%' ";
}

$sql = "
    SELECT
        r.id_request,
        r.no_request,
        r.tgl_request,
        r.nama_pembeli,
        r.status_request,
        r.kategori_pr,
        (
            SELECT COUNT(*) FROM tr_request_detail d WHERE d.id_request = r.id_request
        ) AS jml_item
    FROM tr_request r
    WHERE r.status_request = ? {$where_tipe}
    ORDER BY r.tgl_request ASC, r.no_request ASC
";

$stmt = mysqli_prepare($koneksi, $sql);
if (!$stmt) {
    die('Prepare query utama gagal: ' . mysqli_error($koneksi));
}
mysqli_stmt_bind_param($stmt, "s", $status);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];
while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
}
mysqli_stmt_close($stmt);

// Kolom supplier hanya ditampilkan jika hasil memuat PRB.
$ada_prb = false;
foreach ($rows as $r) {
    if (strtoupper(substr($r['no_request'], 0, 3)) === 'PRB') {
        $ada_prb = true;
        break;
    }
}
// Saat filter PR BESAR kosong, struktur kolom tetap konsisten.
if ($tipe === 'PR BESAR') $ada_prb = true;

// Ambil supplier PRB dari PO tanpa menggandakan baris PR.
$supplier_by_pr = [];
if ($ada_prb) {
    $sql_supplier = "
        SELECT po.id_request,
               GROUP_CONCAT(DISTINCT NULLIF(TRIM(ms.nama_supplier), '')
                            ORDER BY ms.nama_supplier SEPARATOR ', ') AS nama_supplier
        FROM tr_purchase_order po
        LEFT JOIN master_supplier ms ON ms.id_supplier = po.id_supplier
        INNER JOIN tr_request r ON r.id_request = po.id_request
        WHERE r.no_request LIKE 'PRB%'
        GROUP BY po.id_request
    ";
    $res_supplier = mysqli_query($koneksi, $sql_supplier);
    if (!$res_supplier) {
        die('Query supplier PRB gagal: ' . mysqli_error($koneksi));
    }
    while ($sup = mysqli_fetch_assoc($res_supplier)) {
        $supplier_by_pr[$sup['id_request']] = $sup['nama_supplier'] ?: '-';
    }
    mysqli_free_result($res_supplier);
}

// ================= AMBIL ISI ITEM PER PR (satu query, JOIN ke master_barang) =================
// Khusus PR biasa: hanya tampilkan item yang belum dibeli (is_dibeli = 0).
// PRB dan PRI tetap menampilkan semua item, sesuai logika existing.
$pr_biasa_by_id = [];
foreach ($rows as $row_pr) {
    $no_pr = strtoupper($row_pr['no_request']);
    $pr_biasa_by_id[$row_pr['id_request']] =
        substr($no_pr, 0, 3) !== 'PRB' && substr($no_pr, 0, 3) !== 'PRI';
}

// Struktur: $items_by_pr[id_request] = ['Baut M8 (10 PCS)', ...]
$items_by_pr = [];

if (!empty($rows)) {
    $id_list = array_column($rows, 'id_request');
    $placeholders = implode(',', array_fill(0, count($id_list), '?'));

    $sql_item = "
        SELECT
            d.id_request,
            d.nama_barang_manual,
            b.nama_barang AS nama_master,
            d.jumlah,
            d.satuan,
            d.status_item,
            d.is_dibeli
        FROM tr_request_detail d
        LEFT JOIN master_barang b ON d.id_barang = b.id_barang
        WHERE d.id_request IN ($placeholders)
        ORDER BY d.id_request ASC, d.id_detail ASC
    ";

    $stmt_item = mysqli_prepare($koneksi, $sql_item);
    if (!$stmt_item) {
        die('Prepare query item gagal: ' . mysqli_error($koneksi));
    }

    // bind_param dengan referensi (mysqli butuh referensi, bukan value)
    $types = str_repeat('i', count($id_list));
    mysqli_stmt_bind_param($stmt_item, $types, ...$id_list);
    mysqli_stmt_execute($stmt_item);
    $res_item = mysqli_stmt_get_result($stmt_item);

    while ($it = mysqli_fetch_assoc($res_item)) {
        // Filter tampilan Isi Item khusus PR biasa; tidak mengubah jumlah item maupun data.
        if (($pr_biasa_by_id[$it['id_request']] ?? false) && (int)$it['is_dibeli'] === 1) {
            continue;
        }

        // Prioritas nama: manual dulu, kalau kosong pakai master
        $nama = trim($it['nama_barang_manual'] ?? '');
        if ($nama === '') {
            $nama = trim($it['nama_master'] ?? '');
        }
        if ($nama === '') {
            $nama = '(tanpa nama)';
        }

        // Format qty: hilangkan trailing .0000
        $qty_raw = (float)($it['jumlah'] ?? 0);
        $qty = ($qty_raw == (int)$qty_raw)
             ? (string)(int)$qty_raw
             : rtrim(rtrim(number_format($qty_raw, 4, '.', ''), '0'), '.');

        $sat = trim($it['satuan'] ?? '');

        $label = $nama . ' (' . $qty . ($sat ? ' ' . $sat : '') . ')';

        $items_by_pr[$it['id_request']][] = $label;
    }
    mysqli_stmt_close($stmt_item);
}

// ================= LABEL =================
$status_label = [
    'PENDING' => 'PENDING (Menunggu Approval)',
    'PROSES'  => 'PROSES (On Progress)',
    'SELESAI' => 'SELESAI (Sudah Dibelikan)',
][$status];

$judul_tipe = ($tipe === 'ALL') ? 'SEMUA TIPE PR' : $tipe;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Progress PR - <?= htmlspecialchars($status_label) ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #000;
            margin: 0;
            padding: 15px;
            background: #f0f0f0;
        }
        .page {
            background: #fff;
            max-width: 950px;
            margin: 0 auto;
            padding: 25px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .toolbar {
            max-width: 950px;
            margin: 0 auto 12px auto;
            text-align: right;
        }
        .btn-print {
            background: #4a90d9; color: #fff; border: none;
            padding: 9px 20px; font-size: 13px; font-weight: bold;
            border-radius: 4px; cursor: pointer; margin-left: 6px;
        }
        .btn-print:hover { background: #357ab8; }
        .btn-close {
            background: #6c757d; color: #fff; border: none;
            padding: 9px 20px; font-size: 13px; font-weight: bold;
            border-radius: 4px; cursor: pointer;
        }
        .btn-close:hover { background: #565e64; }

        .title-report { font-size: 16px; font-weight: bold; text-align: center; }
        .report-title { font-size: 14px; font-weight: bold; text-align: center; margin-top: 2px; }
        .sub-title { font-size: 12px; text-align: center; font-style: italic; }
        .header-meta { font-size: 11px; text-align: center; color: #666; margin-bottom: 15px; }

        .kpi-table { border-collapse: collapse; margin-bottom: 18px; font-size: 12px; width: 100%; }
        .kpi-table td { border: 1px solid #000; padding: 6px 10px; }
        .kpi-table .header-kpi { background-color: #e8e8e8; font-weight: bold; }

        .section-title { font-weight: bold; margin-bottom: 5px; font-size: 13px; }

        .table-data {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            font-size: 12px;
        }
        .table-data th,
        .table-data td {
            border: 1px solid #000;
            padding: 4px 4px;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: top;
        }
        .table-data thead th {
            background: #e8e8e8;
            font-weight: bold;
            text-align: center;
            font-size: 12px;
        }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .no-wrap { white-space: nowrap; }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 8mm 8mm 8mm;
            }
            html, body {
                background: #fff;
                padding: 0;
                margin: 0;
                font-size: 12px;
                visibility: visible !important;
                display: block !important;
            }
            .page {
                box-shadow: none;
                max-width: 100%;
                padding: 0;
                margin: 0;
                display: block !important;
            }
            .toolbar { display: none !important; }

            .table-data { width: 100%; max-width: 100%; font-size: 12px; display: table !important; }
            .table-data thead { display: table-header-group !important; }
            .table-data tbody { display: table-row-group !important; }
            .table-data tr    { display: table-row !important; }
            .table-data th,
            .table-data td {
                display: table-cell !important;
                padding: 3px 3px;
                font-size: 12px;
                line-height: 1.25;
            }

            .title-report { font-size: 14px; }
            .report-title { font-size: 12.5px; }
            .sub-title { font-size: 12px; }
            .header-meta { font-size: 11px; margin-bottom: 8px; }
            .kpi-table { font-size: 12px; margin-bottom: 10px; }
            .kpi-table td { padding: 4px 6px; }
            .section-title { font-size: 12.5px; }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
        <button class="btn-close" onclick="window.close()">✖ Tutup</button>
    </div>

    <div class="page">

        <!-- JUDUL -->
        <div class="title-report">LAPORAN PROGRESS PURCHASE REQUEST</div>
        
        <div class="sub-title">Status: <?= htmlspecialchars($status_label) ?> &mdash; <?= htmlspecialchars($judul_tipe) ?></div>
        

        <!-- RINGKASAN -->
        <table class="kpi-table">
            <tr>
                <td colspan="2" class="header-kpi text-center">
                    RINGKASAN PROGRESS <?= strtoupper(htmlspecialchars($judul_tipe)) ?> - <?= htmlspecialchars($status) ?>
                </td>
            </tr>
            <tr>
                <td style="width: 50%; font-weight: bold;">Total PR dengan status ini</td>
                <td style="width: 50%;"><?= count($rows) ?> PR</td>
            </tr>
        </table>

        <div class="section-title">DAFTAR PR STATUS <?= htmlspecialchars($status) ?> - <?= htmlspecialchars($judul_tipe) ?></div>

        <!-- TABEL -->
        <table class="table-data">
            <colgroup>
                <?php if ($ada_prb): ?>
                <col style="width: 5%;">   <!-- No -->
                <col style="width: 17%;">  <!-- No. Request -->
                <col style="width: 12%;">  <!-- Tanggal -->
                <col style="width: 15%;">  <!-- Admin -->
                <col style="width: 8%;">   <!-- Jml Item -->
                <col style="width: 27%;">  <!-- Isi Item -->
                <col style="width: 16%;">  <!-- Nama Supplier (PRB) -->
                <?php else: ?>
                <col style="width: 5%;">   <!-- No -->
                <col style="width: 20%;">  <!-- No. Request -->
                <col style="width: 12%;">  <!-- Tanggal -->
                <col style="width: 18%;">  <!-- Admin -->
                <col style="width: 8%;">   <!-- Jml Item -->
                <col style="width: 37%;">  <!-- Isi Item -->
                <?php endif; ?>
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>No. Request</th>
                    <th>Tanggal PR</th>
                    <th>Pembeli</th>
                    <th>Jml Item Req.</th>
                    <th>Item Blm Terbeli</th>
                    <?php if ($ada_prb): ?><th>Nama Supplier</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="<?= $ada_prb ? 7 : 6 ?>" class="text-center" style="font-style: italic; color: #777; padding: 20px;">
                            Tidak ada PR dengan status <?= htmlspecialchars($status) ?> untuk tipe <?= htmlspecialchars($judul_tipe) ?>.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $no = 1;
                    foreach ($rows as $r):
                        $items    = $items_by_pr[$r['id_request']] ?? [];
                        $isi_item = empty($items) ? '-' : implode(', ', $items);
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="text-left no-wrap"><b><?= htmlspecialchars($r['no_request']) ?></b></td>
                        <td class="text-center no-wrap"><?= date('d-M-Y', strtotime($r['tgl_request'])) ?></td>
                        <td class="text-left"><?= htmlspecialchars($r['nama_pembeli']) ?></td>
                        <td class="text-center"><?= (int)$r['jml_item'] ?> Item</td>
                        <td class="text-left" style="font-size: 11px;"><?= htmlspecialchars($isi_item) ?></td>
                        <?php if ($ada_prb): ?>
                        <td class="text-left" style="font-size: 11px;">
                            <?= strtoupper(substr($r['no_request'], 0, 3)) === 'PRB'
                                ? htmlspecialchars($supplier_by_pr[$r['id_request']] ?? '-')
                                : '-' ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Footer -->
        <div style="margin-top: 20px; text-align: right; font-size: 11px; color: #666;">
            Dicetak oleh: <?= htmlspecialchars($_SESSION['username'] ?? 'System') ?><br>
            <?= date('d F Y H:i:s') ?>
        </div>

    </div>

</body>
</html>