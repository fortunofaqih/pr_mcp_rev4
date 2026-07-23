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

// ================= PERSIAPAN HEADER DOWLOAD EXCEL =================
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Kondisi_Kendaraan_MCP_" . $nama_bulan . "_" . $tahun . ".xls");
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
?>

<!-- Struktur HTML Table yang akan dibaca otomatis sebagai Excel -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; }
        .table-data { border-collapse: collapse; width: 100%; }
        .table-data th { background-color: #f2f2f2; font-weight: bold; border: 1px solid #000000; padding: 6px; }
        .table-data td { border: 1px solid #000000; padding: 6px; }
        .text-center { text-align: center; }
        .text-bold { font-weight: bold; }
        .kpi-table { border-collapse: collapse; margin-bottom: 20px; }
        .kpi-table td { border: 1px solid #000000; padding: 5px 10px; }
    </style>
</head>
<body>

    <!-- Judul Laporan -->
    <table style="border: none; margin-bottom: 10px;">
        <tr>
            <td colspan="8" style="font-size: 14px; font-weight: bold;">PT MUTIARA CAHAYA PLASTINDO</td>
        </tr>
        <tr>
            <td colspan="8" style="font-size: 14px; font-weight: bold;">LAPORAN KONDISI KENDARAAN (ARMADA)</td>
        </tr>
        <tr>
            <td colspan="8" style="font-size: 11px; font-style: italic;">Periode: <?= $nama_bulan ?> <?= $tahun ?></td>
        </tr>
    </table>

    <br>

    <!-- Ringkasan Statistik -->
    <table class="kpi-table">
        <tr>
            <td colspan="2" class="text-bold" style="background-color: #f2f2f2;">RINGKASAN STATISTIK ARMADA</td>
        </tr>
        <tr>
            <td class="text-bold">Total Armada</td>
            <td><?= $total_mobil ?> Unit</td>
        </tr>
        <tr>
            <td class="text-bold">Sedang Servis Saat Ini (Aktif)</td>
            <td><?= $total_aktif ?> Unit</td>
        </tr>
        <tr>
            <td class="text-bold">Selesai Servis Bulan Ini</td>
            <td><?= $total_selesai ?> Unit</td>
        </tr>
        <tr>
            <td class="text-bold">Rata-rata Durasi Servis</td>
            <td><?= $rata2_durasi ?> Hari</td>
        </tr>
    </table>

    <br>

    <!-- Tabel Utama -->
    <div style="font-weight: bold; margin-bottom: 5px;">RIWAYAT SERVIS KENDARAAN YANG DIMULAI PERIODE INI</div>
    <table class="table-data">
        <thead>
            <tr>
                <th>No</th>
                <th>Plat Nomor</th>
                <th>Nama Driver</th>
                <th>Kondisi Saat Input</th>
                <th>Tanggal Mulai</th>
                <th>Tanggal Selesai</th>
                <th>Durasi Berjalan / Total</th>
                <th>Status Terakhir</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt_riwayat = mysqli_prepare($koneksi, "
                SELECT k.kondisi, k.plat_nomor, k.start_date, k.end_date, m.driver_tetap
                FROM kondisi_kendaraan k
                JOIN master_mobil m ON k.id_mobil = m.id_mobil
                WHERE MONTH(k.start_date) = ? AND YEAR(k.start_date) = ?
                ORDER BY k.start_date DESC
            ");
            mysqli_stmt_bind_param($stmt_riwayat, "ii", $bulan, $tahun);
            mysqli_stmt_execute($stmt_riwayat);
            mysqli_stmt_bind_result($stmt_riwayat, $r_kondisi, $r_plat, $r_start, $r_end, $r_driver);

            $no = 1;
            $hasData = false;

            while (mysqli_stmt_fetch($stmt_riwayat)) {
                $hasData = true;
                $aktif = is_null($r_end);
                
                // Hitung durasi
                $durasi_str = '-';
                if ($r_start) {
                    $start_dt = new DateTime($r_start);
                    $sampai_dt = $aktif ? new DateTime() : new DateTime($r_end);
                    $durasi_str = ($start_dt->diff($sampai_dt)->days + 1) . ' Hari';
                }
                
                $status_terakhir = $aktif ? 'AKTIF' : 'SELESAI';
                $tgl_mulai = $r_start ? date('d-M-Y', strtotime($r_start)) : '-';
                $tgl_selesai = $r_end ? date('d-M-Y', strtotime($r_end)) : '-';
            ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="text-center" style="font-weight: bold;"><?= htmlspecialchars($r_plat) ?></td>
                    <td><?= htmlspecialchars($r_driver) ?></td>
                    <td class="text-center"><?= htmlspecialchars($r_kondisi) ?></td>
                    <td class="text-center"><?= $tgl_mulai ?></td>
                    <td class="text-center"><?= $tgl_selesai ?></td>
                    <td class="text-center"><?= $durasi_str ?></td>
                    <td class="text-center"><?= $status_terakhir ?></td>
                </tr>
            <?php 
            }
            mysqli_stmt_close($stmt_riwayat);

            if (!$hasData) {
                echo '<tr><td colspan="8" class="text-center" style="font-style: italic; color: #777;">Tidak ada data riwayat servis untuk periode ini.</td></tr>';
            }
            ?>
        </tbody>
    </table>

</body>
</html>