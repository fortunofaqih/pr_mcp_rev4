<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('m');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
if ($bulan < 1 || $bulan > 12) $bulan = (int) date('m');

$nama_bulan = date('F', mktime(0, 0, 0, $bulan, 1, $tahun));

// ================= PERSIAPAN HEADER DOWNLOAD EXCEL =================
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Laporan_Riwayat_Service_Kendaraan_MCP_" . $nama_bulan . "_" . $tahun . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// ================= QUERY KPI KINERJA =================
$total_mobil = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM master_mobil"))['total'];

$total_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan WHERE end_date IS NULL"
))['total'];

$stmt = mysqli_prepare($koneksi,
    "SELECT COUNT(*) as total FROM kondisi_kendaraan
     WHERE end_date IS NOT NULL AND MONTH(end_date) = ? AND YEAR(end_date) = ?"
);
mysqli_stmt_bind_param($stmt, "ii", $bulan, $tahun);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_selesai);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

$row_durasi = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT AVG(DATEDIFF(end_date, start_date) + 1) as rata2
     FROM kondisi_kendaraan WHERE end_date IS NOT NULL AND start_date IS NOT NULL"
));
$rata2_durasi = $row_durasi['rata2'] !== null ? round($row_durasi['rata2'], 1) : 0;

// ================= QUERY UNTUK REKAP BULANAN =================
$query_rekap = "
    SELECT 
        k.id_kondisi,
        k.plat_nomor,
        k.kondisi,
        k.keterangan,
        k.start_date,
        k.end_date,
        k.created_at,
        m.driver_tetap,
        m.merk_tipe,
        m.tahun_kendaraan
    FROM kondisi_kendaraan k
    JOIN master_mobil m ON k.id_mobil = m.id_mobil
    WHERE (MONTH(k.start_date) = ? AND YEAR(k.start_date) = ?)
       OR (MONTH(k.end_date) = ? AND YEAR(k.end_date) = ?)
       OR (MONTH(k.created_at) = ? AND YEAR(k.created_at) = ?)
    ORDER BY k.plat_nomor ASC, k.start_date ASC, k.created_at ASC
";

$stmt_rekap = mysqli_prepare($koneksi, $query_rekap);
mysqli_stmt_bind_param($stmt_rekap, "iiiiii", $bulan, $tahun, $bulan, $tahun, $bulan, $tahun);
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
                    <x:Name>Laporan Service</x:Name>
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
            <td colspan="7" class="title-report">PT MUTIARA CAHAYA PLASTINDO</td>
        </tr>
        <tr>
            <td colspan="7" style="font-size: 14px; font-weight: bold; text-align: center;">LAPORAN REKAP BULANAN RIWAYAT SERVICE KENDARAAN</td>
        </tr>
        <tr>
            <td colspan="7" class="sub-title">Periode: <?= $nama_bulan ?> <?= $tahun ?></td>
        </tr>
        <tr>
            <td colspan="7" style="font-size: 10px; text-align: center; color: #666;">Tanggal Cetak: <?= date('d F Y H:i:s') ?></td>
        </tr>
    </table>

    <br>

    <!-- Ringkasan Statistik -->
    <table class="kpi-table">
        <tr>
            <td colspan="4" class="header-kpi text-center">RINGKASAN STATISTIK SERVICE BULAN <?= strtoupper($nama_bulan) ?> <?= $tahun ?></td>
        </tr>
        <tr>
            <td class="text-bold" style="width: 20%;">Total Armada</td>
            <td style="width: 30%;"><?= $total_mobil ?> Unit</td>
            <td class="text-bold" style="width: 20%;">Selesai Servis Bulan Ini</td>
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

    <!-- Tabel Utama Rekap Service -->
    <div style="font-weight: bold; margin-bottom: 5px; font-size: 12px;">
        REKAP SERVICE KENDARAAN BULAN <?= strtoupper($nama_bulan) ?> <?= $tahun ?>
    </div>
    
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 15%;">Nama Driver</th>
                <th style="width: 10%;">Tgl Masuk</th>
                <th style="width: 42%;">Kerusakan</th>
                <th style="width: 10%;">Tgl Selesai</th>
                <th style="width: 8%;">Durasi</th>
                <th style="width: 11%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $hasData = false;
            $total_durasi = 0;
            $jumlah_data = 0;
            $current_plat = '';

            while ($row = mysqli_fetch_assoc($result_rekap)) {
                $hasData = true;
                $aktif = is_null($row['end_date']);
                
                // Hitung durasi
                $durasi_hari = 0;
                if ($row['start_date']) {
                    $start_dt = new DateTime($row['start_date']);
                    $sampai_dt = $aktif ? new DateTime() : new DateTime($row['end_date']);
                    $durasi_hari = $start_dt->diff($sampai_dt)->days + 1;
                    $total_durasi += $durasi_hari;
                    $jumlah_data++;
                }
                
                $status_terakhir = $aktif ? 'Masih Diservice' : 'SELESAI';
                
                // Format tanggal
                $tgl_mulai = $row['start_date'] ? date('d-M-Y', strtotime($row['start_date'])) : '-';
                $tgl_selesai = $row['end_date'] ? date('d-M-Y', strtotime($row['end_date'])) : '-';
                
                // Status color
                $status_class = $aktif ? 'status-aktif' : 'status-selesai';
                
                // Cek apakah plat nomor baru (untuk grouping)
                if ($current_plat != $row['plat_nomor']) {
                    $current_plat = $row['plat_nomor'];
                    
                    // Tampilkan header group plat nomor
                    ?>
                    <tr class="plat-group">
                        <td colspan="7">
                            <span style="font-size: 13px;"><b><?= htmlspecialchars($row['plat_nomor']) ?></b></span>
                        </td>
                    </tr>
                    <?php
                    // Reset counter untuk no urut di dalam group
                    $sub_no = 1;
                }
                
                // Tampilkan baris data
                $row_class = ($sub_no % 2 == 0) ? 'bg-even' : 'bg-odd';
                
                // Keterangan kerusakan
                $keterangan = htmlspecialchars($row['keterangan'] ?? '-');
                if (empty(trim($keterangan))) {
                    $keterangan = '-';
                }
            ?>
                <tr class="<?= $row_class ?>">
                    <td class="text-center" style="font-size: 10px;"><?= $sub_no++ ?></td>
                    <td class="text-left" style="font-size: 10px;">
                        <?= htmlspecialchars($row['driver_tetap'] ?? '-') ?>
                    </td>
                    <td class="text-center no-wrap" style="font-size: 10px;">
                        <?= $tgl_mulai ?>
                    </td>
                    <td class="text-left kerusakan-text">
                        <?= $keterangan ?>
                    </td>
                    <td class="text-center no-wrap" style="font-size: 10px;">
                        <?= $tgl_selesai ?>
                    </td>
                    <td class="text-center" style="font-size: 10px;">
                        <?= $durasi_hari > 0 ? $durasi_hari : '-' ?>
                    </td>
                    <td class="text-center <?= $status_class ?>" style="font-size: 9px;">
                        <?= $status_terakhir ?>
                    </td>
                </tr>
            <?php 
            }
            
            mysqli_stmt_close($stmt_rekap);

            if (!$hasData) {
                echo '<tr><td colspan="7" class="text-center" style="font-style: italic; color: #777; padding: 20px;">Tidak ada data riwayat service untuk periode ini.</td></tr>';
            }
            ?>
        </tbody>
    </table>

</body>
</html>