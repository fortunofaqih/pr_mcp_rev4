<?php
// print_laporan_kondisi_pdf.php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// ================= FILTER RENTANG TANGGAL =================
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

if (empty($start_date) || empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-d');
}

$start_date_sql = date('Y-m-d', strtotime($start_date));
$end_date_sql   = date('Y-m-d', strtotime($end_date));

$periode_label = date('d-M-Y', strtotime($start_date)) . ' s/d ' . date('d-M-Y', strtotime($end_date));

// ================= KPI =================
$total_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan WHERE end_date IS NULL"
))['total'];

$stmt = mysqli_prepare($koneksi,
    "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan
     WHERE end_date IS NOT NULL AND end_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "ss", $start_date_sql, $end_date_sql);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_selesai);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// ================= QUERY UTAMA =================
$query_rekap = "
    SELECT
        k.id_kondisi,
        k.id_mobil,
        m.plat_nomor AS plat_nomor,
        k.plat_nomor AS plat_nomor_service,
        k.kondisi,
        k.bengkel,
        k.keterangan,
        k.start_date,
        k.end_date,
        k.created_at,
        m.driver_tetap,
        m.merk_tipe,
        m.tahun_kendaraan,
        DATEDIFF(COALESCE(k.end_date, NOW()), k.start_date) AS durasi_hari,
        CASE
            WHEN k.end_date IS NULL THEN 'MASIH DISERVICE'
            ELSE 'SELESAI'
        END AS status_service
    FROM kondisi_kendaraan k
    JOIN master_mobil m ON k.id_mobil = m.id_mobil
    WHERE
        (k.end_date IS NOT NULL AND k.end_date BETWEEN ? AND ?)
        OR
        (k.end_date IS NULL)
    ORDER BY
        (k.end_date IS NULL) DESC,
        m.plat_nomor ASC,
        k.id_mobil ASC,
        k.start_date ASC,
        k.created_at ASC
";

$stmt_rekap = mysqli_prepare($koneksi, $query_rekap);
mysqli_stmt_bind_param($stmt_rekap, "ss", $start_date_sql, $end_date_sql);
mysqli_stmt_execute($stmt_rekap);
$result_rekap = mysqli_stmt_get_result($stmt_rekap);

$row_pending = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan WHERE end_date IS NULL"
));
$total_pending = $row_pending['total'];

// ================= HISTORI PERGANTIAN PLAT =================
// Hanya digunakan sebagai keterangan; tidak memengaruhi KPI, filter, maupun transaksi service.
$histori_plat_per_mobil = [];
$sql_histori = "SELECT id_mobil, plat_lama, plat_baru, tanggal_perubahan
                FROM histori_plat_mobil
                ORDER BY id_mobil ASC, tanggal_perubahan DESC, id_histori DESC";
$hasil_histori = mysqli_query($koneksi, $sql_histori);
if (!$hasil_histori) {
    die('Query histori plat error: ' . htmlspecialchars(mysqli_error($koneksi)));
}
while ($histori = mysqli_fetch_assoc($hasil_histori)) {
    $id_histori_mobil = (int)$histori['id_mobil'];
    if (!isset($histori_plat_per_mobil[$id_histori_mobil])) {
        // Record pertama adalah pergantian paling baru untuk kendaraan tersebut.
        $histori_plat_per_mobil[$id_histori_mobil] = $histori;
    }
}
mysqli_free_result($hasil_histori);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Mutasi Service Kendaraan - <?= htmlspecialchars($periode_label) ?></title>
    <style>
        /* ============ STYLE LAYAR ============ */
        * { box-sizing: border-box; }
        body {
            font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
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
        .header-meta { font-size: 10px; text-align: center; color: #666; margin-bottom: 15px; }

        .kpi-table { border-collapse: collapse; margin-bottom: 18px; font-size: 11px; width: 100%; }
        .kpi-table td { border: 1px solid #000; padding: 6px 10px; }
        .kpi-table .header-kpi { background-color: #e8e8e8; font-weight: bold; }
        .text-bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }

        .section-title { font-weight: bold; margin-bottom: 5px; font-size: 12px; }
        .catatan { font-size: 10px; color: #666; margin-bottom: 8px; font-style: italic; }

        /* ============ TABEL DATA ============ */
        /* border-collapse: separate + spacing 0 supaya garis kanan selalu tampil di print */
        .table-data {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            max-width: 100%;
            table-layout: fixed;
            font-size: 11px;
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
            font-size: 11px;
        }

        .plat-group td {
            padding: 6px 6px;
            border: 1px solid #000;
            border-bottom: 2px solid #4a90d9;
            background: #eef4fb;
            font-size: 11px;
        }
        .bg-even { background-color: #f9f9f9; }
        .bg-odd  { background-color: #ffffff; }
        .row-pending { background-color: #fff4e6 !important; }
        .row-pending-prev { background-color: #fff9e6 !important; }
        .status-aktif   { color: #ff6600; font-weight: bold; }
        .status-selesai { color: #28a745; font-weight: bold; }
        .kerusakan-text { font-size: 10.5px; color: #333; }
        .no-wrap { white-space: nowrap; }

        /* ============ STYLE PRINT (PORTRAIT) ============ */
        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 8mm 8mm 8mm;
            }

            html, body {
                background: #fff;
                padding: 0;
                margin: 0;
                font-size: 11px;
            }
            .page {
                box-shadow: none;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .toolbar { display: none !important; }

            /* Tabel full width, font tetap 11px */
            .table-data {
                width: 100%;
                max-width: 100%;
                font-size: 11px;
            }
            .table-data th,
            .table-data td {
                padding: 3px 3px;
                font-size: 11px;
                line-height: 1.25;
            }
            .table-data thead th { font-size: 11px; }

            .plat-group td { padding: 4px 4px; font-size: 11px; }

            .title-report { font-size: 14px; }
            .report-title { font-size: 12px; }
            .sub-title { font-size: 11px; }
            .header-meta { font-size: 10px; margin-bottom: 8px; }
            .kpi-table { font-size: 11px; margin-bottom: 10px; }
            .kpi-table td { padding: 4px 6px; }
            .section-title { font-size: 11.5px; }
            .catatan { font-size: 10px; margin-bottom: 6px; }
            .kerusakan-text { font-size: 10.5px; }

            /* Paksa semua warna & border tercetak */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .plat-group { page-break-after: avoid; }
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
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
        <div class="report-title">LAPORAN MUTASI SERVICE KENDARAAN</div>
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
                <td class="text-bold" style="width: 50%;">Mobil yang masih diservice</td>
                <td style="width: 50%;"><?= (int)$total_pending ?> Unit</td>
            </tr>
            <tr>
                <td class="text-bold">Mobil yang selesai diservice</td>
                <td><?= (int)$total_selesai ?> Unit</td>
            </tr>
        </table>

        <div class="section-title">DETAIL SERVICE KENDARAAN </div>
    
        <!-- ================= TABEL UTAMA ================= -->
        <!-- Kolom: total = 100% -->
        <table class="table-data">
            <colgroup>
                <col style="width: 4%;">   <!-- No -->
                <col style="width: 11%;">  <!-- Plat -->
                <col style="width: 13%;">  <!-- Driver -->
                <col style="width: 9%;">   <!-- Tgl Masuk -->
                <col style="width: 9%;">   <!-- Tgl Selesai -->
                <col style="width: 28%;">  <!-- Keterangan -->
                <col style="width: 6%;">   <!-- Durasi -->
                <col style="width: 10%;">  <!-- Bengkel -->
                <col style="width: 10%;">  <!-- Status -->
            </colgroup>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Plat Nomor</th>
                    <th>Nama Driver</th>
                    <th>Tgl Masuk</th>
                    <th>Tgl Selesai</th>
                    <th>Keterangan</th>
                    <th>Durasi</th>
                    <th>Bengkel</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hasData = false;
                $current_mobil_id = null;
                $no = 0;

                while ($row = mysqli_fetch_assoc($result_rekap)) {
                    $hasData = true;

                    if ($current_mobil_id !== (int)$row['id_mobil']) {
                        $no = 0;
                        $current_mobil_id = (int)$row['id_mobil'];
                        ?>
                        <tr class="plat-group">
                            <td colspan="9">
                                <b><?= htmlspecialchars($row['plat_nomor']) ?></b>
                                <?php if (!empty($row['merk_tipe'])): ?>
                                    &mdash; <?= htmlspecialchars($row['merk_tipe']) ?>
                                    <?= htmlspecialchars($row['tahun_kendaraan'] ? '(' . $row['tahun_kendaraan'] . ')' : '') ?>
                                <?php endif; ?>
                                <?php if (isset($histori_plat_per_mobil[(int)$row['id_mobil']])):
                                    $perubahan_plat = $histori_plat_per_mobil[(int)$row['id_mobil']];
                                ?>
                                    <br><span style="font-size:10px; color:#7a4b11; font-weight:normal;">
                                        PERNAH GANTI PLAT &mdash;
                                        sebelumnya: <?= htmlspecialchars($perubahan_plat['plat_lama']) ?>;
                                        terbaru: <?= htmlspecialchars($row['plat_nomor']) ?>;
                                        tanggal: <?= date('d/m/Y', strtotime($perubahan_plat['tanggal_perubahan'])) ?>
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

                    $start_ts   = strtotime($row['start_date']);
                    $periode_ts = strtotime($start_date_sql);
                    $is_lanjutan = $aktif && $start_ts < $periode_ts;

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

                    $keterangan = htmlspecialchars($row['keterangan'] ?? '-');
                    if (empty(trim($keterangan))) $keterangan = '-';
                ?>
                    <tr class="<?= $row_class ?>">
                        <td class="text-center"><?= $no ?></td>
                        <td class="text-left"><b><?= htmlspecialchars($row['plat_nomor']) ?></b></td>
                        <td class="text-left"><?= htmlspecialchars($row['driver_tetap'] ?? '-') ?></td>
                        <td class="text-center no-wrap"><?= $tgl_mulai ?></td>
                        <td class="text-center no-wrap"><?= $tgl_selesai ?></td>
                        <td class="text-left kerusakan-text"><?= $keterangan ?></td>
                        <td class="text-center"><?= $durasi_hari > 0 ? $durasi_hari . 'h' : '-' ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['bengkel'] ?? '-') ?></td>
                        <td class="text-center <?= $status_class ?>"><?= $status_label ?></td>
                    </tr>
                <?php
                }

                mysqli_stmt_close($stmt_rekap);

                if (!$hasData) {
                    echo '<tr><td colspan="9" class="text-center" style="font-style: italic; color: #777; padding: 20px;">'
                       . 'Tidak ada data mutasi service untuk periode ini.</td></tr>';
                }
                ?>
            </tbody>
        </table>

    </div>

</body>
</html>