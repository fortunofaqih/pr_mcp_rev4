<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// ================= FILTER BULAN & TAHUN =================
$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('m');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
if ($bulan < 1 || $bulan > 12) $bulan = (int) date('m');

$nama_bulan    = date('F', mktime(0, 0, 0, $bulan, 1, $tahun));
$periode_label = $nama_bulan . ' ' . $tahun;
$awal_bulan_ts = mktime(0, 0, 0, $bulan, 1, $tahun);

// ================= KPI =================
$total_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(DISTINCT id_mesin) as total FROM kondisi_mesin WHERE end_date IS NULL"
))['total'];

$stmt = mysqli_prepare($koneksi,
    "SELECT COUNT(*) as total FROM kondisi_mesin
     WHERE end_date IS NOT NULL AND MONTH(end_date) = ? AND YEAR(end_date) = ?"
);
mysqli_stmt_bind_param($stmt, "ii", $bulan, $tahun);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_selesai);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// ================= QUERY UTAMA =================
$query_rekap = "
    SELECT
        km.id_kondisi_mesin,
        km.id_mesin,
        km.kondisi_mesin,
        km.keterangan_service_mesin,
        km.start_date,
        km.end_date,
        km.created_at,
        mm.id_mesin AS kode_mesin,
        mm.name,
        mm.spec,
        mm.capacity,
        DATEDIFF(COALESCE(km.end_date, NOW()), km.start_date) AS durasi_hari,
        CASE
            WHEN km.end_date IS NULL THEN 'MASIH DISERVICE'
            ELSE 'SELESAI'
        END AS status_service
    FROM kondisi_mesin km
    JOIN master_mesin mm ON km.id_mesin = mm.id
    WHERE
        (km.end_date IS NOT NULL AND MONTH(km.end_date) = ? AND YEAR(km.end_date) = ?)
        OR
        (km.end_date IS NULL)
    ORDER BY
        (km.end_date IS NULL) DESC,
        mm.id_mesin ASC,
        km.start_date ASC,
        km.created_at ASC
";

$stmt_rekap = mysqli_prepare($koneksi, $query_rekap);
mysqli_stmt_bind_param($stmt_rekap, "ii", $bulan, $tahun);
mysqli_stmt_execute($stmt_rekap);
$result_rekap = mysqli_stmt_get_result($stmt_rekap);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Mutasi Service Mesin - <?= htmlspecialchars($periode_label) ?></title>
    <style>
        /* ============ STYLE LAYAR ============ */
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
            background: #4a90d9;
            color: #fff;
            border: none;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 6px;
        }
        .btn-print:hover { background: #357ab8; }
        .btn-close {
            background: #6c757d;
            color: #fff;
            border: none;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-close:hover { background: #565e64; }

        /* ============ KONTEN ============ */
        .title-report { font-size: 16px; font-weight: bold; text-align: center; }
        .report-title { font-size: 14px; font-weight: bold; text-align: center; margin-top: 2px; }
        .sub-title { font-size: 12px; text-align: center; font-style: italic; }
        .header-meta { font-size: 11px; text-align: center; color: #666; margin-bottom: 15px; }

        .kpi-table { border-collapse: collapse; margin-bottom: 18px; font-size: 12px; width: 100%; }
        .kpi-table td { border: 1px solid #000; padding: 6px 10px; }
        .kpi-table .header-kpi { background-color: #e8e8e8; font-weight: bold; }
        .text-bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }

        .section-title { font-weight: bold; margin-bottom: 5px; font-size: 13px; }
        .catatan { font-size: 11px; color: #666; margin-bottom: 8px; font-style: italic; }

        /* ============ TABEL DATA ============ */
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

        .mesin-group td {
            padding: 6px 6px;
            border: 1px solid #000;
            border-bottom: 2px solid #4a90d9;
            background: #eef4fb;
            font-size: 12px;
            color: #1a3c6e;
            font-weight: bold;
        }
        .bg-even { background-color: #f9f9f9; }
        .bg-odd  { background-color: #ffffff; }
        .row-pending { background-color: #fff4e6 !important; }
        .row-pending-prev { background-color: #fff9e6 !important; }
        .status-aktif   { color: #ff6600; font-weight: bold; }
        .status-selesai { color: #28a745; font-weight: bold; }
        .kerusakan-text { font-size: 11.5px; color: #333; }
        .no-wrap { white-space: nowrap; }

        /* ============ STYLE PRINT ============ */
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

            .table-data {
                width: 100%;
                max-width: 100%;
                font-size: 12px;
                display: table !important;
            }
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

            .mesin-group td { padding: 4px 4px; font-size: 12px; }
            .title-report { font-size: 14px; }
            .report-title { font-size: 12.5px; }
            .sub-title { font-size: 12px; }
            .header-meta { font-size: 11px; margin-bottom: 8px; }
            .kpi-table { font-size: 12px; margin-bottom: 10px; }
            .kpi-table td { padding: 4px 6px; }
            .section-title { font-size: 12.5px; }
            .catatan { font-size: 11px; margin-bottom: 6px; }
            .kerusakan-text { font-size: 11.5px; }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .mesin-group { page-break-after: avoid; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <!-- Toolbar (tidak ikut tercetak) -->
    <div class="toolbar">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
        <button class="btn-close" onclick="window.close()">✖ Tutup</button>
    </div>

    <div class="page">

        <!-- ================= JUDUL ================= -->
        <div class="title-report">PT MUTIARA CAHAYA PLASTINDO</div>
        <div class="report-title">LAPORAN MUTASI SERVICE MESIN</div>
        <div class="sub-title">Periode: <?= htmlspecialchars($periode_label) ?></div>
        <div class="header-meta">Tanggal Cetak: <?= date('d F Y H:i:s') ?></div>

        <!-- ================= KPI ================= -->
        <table class="kpi-table">
            <tr>
                <td colspan="2" class="header-kpi text-center">
                    RINGKASAN STATISTIK SERVICE PERIODE <?= strtoupper(htmlspecialchars($periode_label)) ?>
                </td>
            </tr>
            <tr>
                <td class="text-bold" style="width: 50%;">Mesin yang masih diservice</td>
                <td style="width: 50%;"><?= (int)$total_aktif ?> Unit</td>
            </tr>
            <tr>
                <td class="text-bold">Mesin yang selesai diservice</td>
                <td><?= (int)$total_selesai ?> Unit</td>
            </tr>
        </table>

        <div class="section-title">MUTASI SERVICE MESIN PERIODE <?= strtoupper(htmlspecialchars($periode_label)) ?></div>
        <div class="catatan">
            Catatan: Baris berwarna oranye = service yang masih berjalan (belum selesai),
            termasuk yang dimulai dari periode sebelumnya.
        </div>

        <!-- ================= TABEL UTAMA ================= -->
        <table class="table-data">
            <colgroup>
                <col style="width: 4%;">
                <col style="width: 11%;">
                <col style="width: 12%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
                <col style="width: 29%;">
                <col style="width: 7%;">
                <col style="width: 9%;">
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>ID Mesin</th>
                    <th>Nama Mesin</th>
                    <th>Tgl Masuk</th>
                    <th>Tgl Selesai</th>
                    <th>Keterangan Service</th>
                    <th>Durasi</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hasData = false;
                $current_mesin = '';
                $no = 0;

                while ($row = mysqli_fetch_assoc($result_rekap)) {
                    $hasData = true;

                    if ($current_mesin != $row['id_mesin']) {
                        $no = 0;
                        $current_mesin = $row['id_mesin'];
                        ?>
                        <tr class="mesin-group">
                            <td colspan="8">
                                <b><?= htmlspecialchars($row['kode_mesin']) ?></b>
                                &mdash; <?= htmlspecialchars($row['name']) ?>
                                <?php if (!empty($row['capacity'])): ?>
                                    <span style="font-weight: normal; color: #444;">
                                        (Kapasitas: <?= htmlspecialchars($row['capacity']) ?>)
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    }

                    $no++;

                    $aktif = is_null($row['end_date']);

                    $durasi_hari = $row['durasi_hari'] ?? 0;
                    if ($durasi_hari < 0) $durasi_hari = 0;

                    $start_ts    = strtotime($row['start_date']);
                    $is_lanjutan = $aktif && $start_ts < $awal_bulan_ts;

                    $tgl_mulai   = $row['start_date'] ? date('d-M-Y', strtotime($row['start_date'])) : '-';
                    $tgl_selesai = $row['end_date']   ? date('d-M-Y', strtotime($row['end_date']))   : '-';

                    if ($aktif) {
                        $status_label = $is_lanjutan ? 'MASIH DISERVICE (LANJUTAN)' : 'MASIH DISERVICE';
                        $status_class = 'status-aktif';
                    } else {
                        $status_label = 'SELESAI';
                        $status_class = 'status-selesai';
                    }

                    if ($aktif && $is_lanjutan) {
                        $row_class = 'row-pending-prev';
                    } elseif ($aktif) {
                        $row_class = 'row-pending';
                    } else {
                        $row_class = ($no % 2 == 0) ? 'bg-even' : 'bg-odd';
                    }

                    $keterangan = htmlspecialchars($row['keterangan_service_mesin'] ?? '-');
                    if (empty(trim($keterangan))) $keterangan = '-';
                ?>
                    <tr class="<?= $row_class ?>">
                        <td class="text-center"><?= $no ?></td>
                        <td class="text-left"><b><?= htmlspecialchars($row['kode_mesin']) ?></b></td>
                        <td class="text-left"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="text-center no-wrap"><?= $tgl_mulai ?></td>
                        <td class="text-center no-wrap"><?= $tgl_selesai ?></td>
                        <td class="text-left kerusakan-text"><?= $keterangan ?></td>
                        <td class="text-center"><?= $durasi_hari > 0 ? $durasi_hari . 'h' : '-' ?></td>
                        <td class="text-center <?= $status_class ?>"><?= $status_label ?></td>
                    </tr>
                <?php
                }

                mysqli_stmt_close($stmt_rekap);

                if (!$hasData) {
                    echo '<tr><td colspan="8" class="text-center" style="font-style: italic; color: #777; padding: 20px;">'
                       . 'Tidak ada data mutasi service mesin untuk periode ini.</td></tr>';
                }
                ?>
            </tbody>
        </table>

    </div>

</body>
</html>