<?php
//laporan_kondisi_kendaraaan_excel.php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Filter rentang tanggal
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

if (empty($start_date) || empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
}

$start_date_sql = date('Y-m-d', strtotime($start_date));
$end_date_sql = date('Y-m-d', strtotime($end_date));

$periode_label = date('d-M-Y', strtotime($start_date)) . ' s/d ' . date('d-M-Y', strtotime($end_date));

// ================= PERSIAPAN HEADER DOWNLOAD EXCEL =================
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Laporan_Mutasi_Service_Kendaraan_" . date('d-m-Y', strtotime($start_date)) . "_sampai_" . date('d-m-Y', strtotime($end_date)) . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// ================= QUERY KPI KINERJA =================
$total_mobil = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM master_mobil"))['total'];

$total_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan WHERE end_date IS NULL"
))['total'];

$stmt = mysqli_prepare($koneksi,
    "SELECT COUNT(*) as total FROM kondisi_kendaraan
     WHERE end_date IS NOT NULL AND end_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "ss", $start_date_sql, $end_date_sql);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_selesai);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$row_durasi = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT AVG(DATEDIFF(end_date, start_date) + 1) as rata2
     FROM kondisi_kendaraan WHERE end_date IS NOT NULL AND start_date IS NOT NULL"
));
$rata2_durasi = $row_durasi['rata2'] !== null ? round($row_durasi['rata2'], 1) : 0;

// ================= QUERY UNTUK REKAP MUTASI =================
$query_rekap = "
    SELECT 
        k.id_kondisi,
        k.plat_nomor,
        k.kondisi,
        k.bengkel,
        k.keterangan,
        k.start_date,
        k.end_date,
        k.created_at,
        m.driver_tetap,
        m.merk_tipe,
        m.tahun_kendaraan,
        DATEDIFF(COALESCE(k.end_date, NOW()), k.start_date) as durasi_hari
    FROM kondisi_kendaraan k
    JOIN master_mobil m ON k.id_mobil = m.id_mobil
    WHERE (k.start_date BETWEEN ? AND ?) OR (k.end_date BETWEEN ? AND ?)
    ORDER BY k.plat_nomor ASC, k.start_date ASC, k.created_at ASC
";

$stmt_rekap = mysqli_prepare($koneksi, $query_rekap);
mysqli_stmt_bind_param($stmt_rekap, "ssss", $start_date_sql, $end_date_sql, $start_date_sql, $end_date_sql);
mysqli_stmt_execute($stmt_rekap);
$result_rekap = mysqli_stmt_get_result($stmt_rekap);
?>

<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:x="urn:schemas-microsoft-com:office:excel" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Mutasi Service</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        body { 
            font-family: 'Calibri', sans-serif; 
            font-size: 11px; 
        }
        .table-data { 
            border-collapse: collapse; 
            width: 100%; 
        }
        .table-data th { 
            background-color: #4a90d9; 
            color: white;
            font-weight: bold; 
            border: 1px solid #000000; 
            padding: 8px 6px; 
            text-align: center;
            vertical-align: middle;
        }
        .table-data td { 
            border: 1px solid #000000; 
            padding: 6px; 
            vertical-align: middle;
        }
        .text-center { 
            text-align: center; 
        }
        .text-bold { 
            font-weight: bold; 
        }
        .text-left { 
            text-align: left; 
        }
        .text-right {
            text-align: right;
        }
        .kpi-table { 
            border-collapse: collapse; 
            margin-bottom: 20px; 
            font-size: 11px; 
            width: 100%;
        }
        .kpi-table td { 
            border: 1px solid #000000; 
            padding: 5px 10px; 
        }
        .kpi-table .header-kpi { 
            background-color: #e8e8e8; 
            font-weight: bold; 
        }
        .status-aktif { 
            color: #ff6600; 
            font-weight: bold; 
        }
        .status-selesai { 
            color: #28a745; 
            font-weight: bold; 
        }
        .plat-group { 
            background-color: #e8f0fe; 
            font-weight: bold; 
            font-size: 13px;
            color: #1a3c6e;
            border-bottom: 2px solid #4a90d9;
        }
        .plat-group td {
            padding: 8px 10px;
            border-bottom: 2px solid #4a90d9;
        }
        .bg-even { 
            background-color: #f9f9f9; 
        }
        .bg-odd { 
            background-color: #ffffff; 
        }
        .kerusakan-text { 
            font-size: 10px; 
            color: #333; 
        }
        .title-report { 
            font-size: 16px; 
            font-weight: bold; 
            text-align: center; 
        }
        .sub-title { 
            font-size: 12px; 
            text-align: center; 
            font-style: italic; 
        }
        .no-wrap {
            white-space: nowrap;
        }
    </style>
</head>
<body>

    <!-- Judul Laporan -->
    <table style="border: none; margin-bottom: 10px; width: 100%;">
        <tr>
            <td colspan="9" class="title-report">PT MUTIARA CAHAYA PLASTINDO</td>
        </tr>
        <tr>
            <td colspan="9" style="font-size: 14px; font-weight: bold; text-align: center;">LAPORAN MUTASI SERVICE KENDARAAN</td>
        </tr>
        <tr>
            <td colspan="9" class="sub-title">Periode: <?= $periode_label ?></td>
        </tr>
        <tr>
            <td colspan="9" style="font-size: 10px; text-align: center; color: #666;">Tanggal Cetak: <?= date('d F Y H:i:s') ?></td>
        </tr>
    </table>

    <br>

    <!-- Ringkasan Statistik -->
    <table class="kpi-table">
        <tr>
            <td colspan="4" class="header-kpi text-center">RINGKASAN STATISTIK SERVICE PERIODE <?= strtoupper($periode_label) ?></td>
        </tr>
        <tr>
            <td class="text-bold" style="width: 20%;">Total Armada</td>
            <td style="width: 30%;"><?= $total_mobil ?> Unit</td>
            <td class="text-bold" style="width: 20%;">Selesai Servis</td>
            <td style="width: 30%;"><?= $total_selesai ?> Unit</td>
        </tr>
        <tr>
            <td class="text-bold">Masih Diservice</td>
            <td><?= $total_aktif ?> Unit</td>
            <td class="text-bold">Rata-rata Durasi Servis</td>
            <td><?= $rata2_durasi ?> Hari</td>
        </tr>
    </table>

    <br>

    <!-- Tabel Utama Mutasi Service -->
    <div style="font-weight: bold; margin-bottom: 5px; font-size: 12px;">
        MUTASI SERVICE KENDARAAN PERIODE <?= strtoupper($periode_label) ?>
    </div>
    
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 15%;">Plat Nomor</th>
                <th style="width: 15%;">Nama Driver</th>
                <th style="width: 12%;">Tgl Masuk</th>
                <th style="width: 12%;">Tgl Selesai</th>
                <th style="width: 25%;">Keterangan</th>
                <th style="width: 7%;">Durasi</th>
                <th style="width: 10%;">Bengkel</th>
                <th style="width: 10%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $hasData = false;
            $current_plat = '';
            $no = 0;

            while ($row = mysqli_fetch_assoc($result_rekap)) {
                $hasData = true;
                $no++;
                $aktif = is_null($row['end_date']);
                
                // Durasi
                $durasi_hari = $row['durasi_hari'] ?? 0;
                if ($durasi_hari < 0) $durasi_hari = 0;
                
                $status_terakhir = $aktif ? 'Masih Diservice' : 'SELESAI';
                
                // Format tanggal
                $tgl_mulai = $row['start_date'] ? date('d-M-Y', strtotime($row['start_date'])) : '-';
                $tgl_selesai = $row['end_date'] ? date('d-M-Y', strtotime($row['end_date'])) : '-';
                
                // Status color
                $status_class = $aktif ? 'status-aktif' : 'status-selesai';
                
                // Cek apakah plat nomor baru (untuk grouping)
                if ($current_plat != $row['plat_nomor']) {
                    $current_plat = $row['plat_nomor'];
                    ?>
                    <tr class="plat-group">
                        <td colspan="9">
                            <span style="font-size: 13px;"><b><?= htmlspecialchars($row['plat_nomor']) ?></b></span>
                        </td>
                    </tr>
                    <?php
                }
                
                // Tampilkan baris data
                $row_class = ($no % 2 == 0) ? 'bg-even' : 'bg-odd';
                
                // Keterangan
                $keterangan = htmlspecialchars($row['keterangan'] ?? '-');
                if (empty(trim($keterangan))) {
                    $keterangan = '-';
                }
            ?>
                <tr class="<?= $row_class ?>">
                    <td class="text-center" style="font-size: 10px;"><?= $no ?></td>
                    <td class="text-left" style="font-size: 10px;">
                        <b><?= htmlspecialchars($row['plat_nomor']) ?></b>
                    </td>
                    <td class="text-left" style="font-size: 10px;">
                        <?= htmlspecialchars($row['driver_tetap'] ?? '-') ?>
                    </td>
                    <td class="text-center no-wrap" style="font-size: 10px;">
                        <?= $tgl_mulai ?>
                    </td>
                    <td class="text-center no-wrap" style="font-size: 10px;">
                        <?= $tgl_selesai ?>
                    </td>
                    <td class="text-left kerusakan-text">
                        <?= $keterangan ?>
                    </td>
                    <td class="text-center" style="font-size: 10px;">
                        <?= $durasi_hari > 0 ? $durasi_hari . ' hari' : '-' ?>
                    </td>
                    <td class="text-center" style="font-size: 10px;">
                        <?= htmlspecialchars($row['bengkel'] ?? '-') ?>
                    </td>
                    <td class="text-center <?= $status_class ?>" style="font-size: 9px;">
                        <?= $status_terakhir ?>
                    </td>
                </tr>
            <?php 
            }
            
            mysqli_stmt_close($stmt_rekap);

            if (!$hasData) {
                echo '<tr><td colspan="9" class="text-center" style="font-style: italic; color: #777; padding: 20px;">Tidak ada data mutasi service untuk periode ini.</td></tr>';
            }
            ?>
        </tbody>
    </table>

</body>
</html>