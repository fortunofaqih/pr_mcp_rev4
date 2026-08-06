<?php
// laporan_kondisi_mobil_print.php
// Hanya menampilkan halaman yang siap di-print ke PDF

session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$id_mobil = (int)($_GET['id_mobil'] ?? 0);

if ($id_mobil <= 0) {
    die('ID mobil tidak valid');
}

// Ambil data (sama seperti sebelumnya)
$query_mobil = "SELECT * FROM master_mobil WHERE id_mobil = $id_mobil";
$result_mobil = mysqli_query($koneksi, $query_mobil);
$mobil = mysqli_fetch_assoc($result_mobil);

if (!$mobil) {
    die('Data mobil tidak ditemukan');
}

$query_riwayat = "SELECT id_kondisi, kondisi, keterangan, start_date, end_date, created_at
                  FROM kondisi_kendaraan
                  WHERE id_mobil = $id_mobil
                  ORDER BY (end_date IS NULL) DESC, start_date DESC";
$result_riwayat = mysqli_query($koneksi, $query_riwayat);

$riwayat = [];
while ($row = mysqli_fetch_assoc($result_riwayat)) {
    $riwayat[] = $row;
}

$kondisi_terakhir = 'BAIK';
$query_kondisi = "SELECT kondisi FROM kondisi_kendaraan 
                  WHERE id_mobil = $id_mobil AND end_date IS NULL 
                  ORDER BY start_date DESC LIMIT 1";
$result_kondisi = mysqli_query($koneksi, $query_kondisi);
$row_kondisi = mysqli_fetch_assoc($result_kondisi);
if ($row_kondisi) {
    $kondisi_terakhir = $row_kondisi['kondisi'];
}

function formatDateIndo($date) {
    if (empty($date)) return '-';
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
    $timestamp = strtotime($date);
    return date('d', $timestamp) . '-' . $bulan[date('n', $timestamp) - 1] . '-' . date('Y', $timestamp);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Riwayat Kondisi - <?= $mobil['plat_nomor'] ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20px; }
            table { page-break-inside: avoid; }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            padding: 20px;
            background: white;
        }
        h1 { color: #1e3a8a; text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 8px; border-bottom: 1px solid #dee2e6; }
        .info-label { font-weight: bold; width: 20%; }
        .info-value { width: 80%; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e9ecef; font-weight: bold; padding: 8px; border: 1px solid #dee2e6; }
        td { padding: 6px; border: 1px solid #dee2e6; }
        .status-aktif { background: #ffc107; font-weight: bold; }
        .status-selesai { background: #28a745; font-weight: bold; color: white; }
        .footer { margin-top: 30px; font-size: 10px; color: #6c757d; }
        .no-print { margin-bottom: 20px; text-align: center; }
        .btn-print { padding: 10px 30px; background: #1e3a8a; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-print:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Cetak / Save as PDF
        </button>
        <button onclick="window.close()" class="btn-print" style="background:#6c757d;">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>
    
    <h1>LAPORAN RIWAYAT KONDISI KENDARAAN</h1>
    
    <table class="info-table">
        <tr><td class="info-label">Plat Nomor</td><td class="info-value"><strong><?= $mobil['plat_nomor'] ?></strong></td></tr>
        <tr><td class="info-label">Driver</td><td class="info-value"><?= $mobil['driver_tetap'] ?? '-' ?></td></tr>
        <tr><td class="info-label">Jenis Kendaraan</td><td class="info-value"><?= $mobil['jenis_kendaraan'] ?? '-' ?></td></tr>
        <tr><td class="info-label">Merk / Tipe</td><td class="info-value"><?= $mobil['merk_tipe'] ?? '-' ?></td></tr>
        <tr><td class="info-label">Kondisi Saat Ini</td><td class="info-value"><strong style="color:<?= $kondisi_terakhir == 'BAIK' ? '#28a745' : '#dc3545' ?>;"><?= $kondisi_terakhir ?></strong></td></tr>
    </table>
    
    <h3>RIWAYAT SERVIS</h3>
    <table>
        <thead>
            <tr>
                <th style="width:5%;">No</th>
                <th style="width:15%;">Kondisi</th>
                <th style="width:25%;">Keterangan</th>
                <th style="width:15%;">Mulai</th>
                <th style="width:15%;">Selesai</th>
                <th style="width:10%;">Durasi</th>
                <th style="width:15%;">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $no = 1;
        foreach ($riwayat as $row) {
            $aktif = is_null($row['end_date']);
            $durasi = '-';
            if ($row['start_date']) {
                $start = new DateTime($row['start_date']);
                $sampai = $aktif ? new DateTime() : new DateTime($row['end_date']);
                $durasi = $start->diff($sampai)->days + 1;
            }
            $status_class = $aktif ? 'status-aktif' : 'status-selesai';
            $status_text = $aktif ? 'AKTIF' : 'SELESAI';
        ?>
            <tr>
                <td style="text-align:center;"><?= $no++ ?></td>
                <td style="text-align:center;"><?= $row['kondisi'] ?></td>
                <td><?= $row['keterangan'] ?? '-' ?></td>
                <td style="text-align:center;"><?= formatDateIndo($row['start_date']) ?></td>
                <td style="text-align:center;"><?= $aktif ? '-' : formatDateIndo($row['end_date']) ?></td>
                <td style="text-align:center;"><?= $durasi ?></td>
                <td style="text-align:center;" class="<?= $status_class ?>"><?= $status_text ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    
    <div class="footer">
        <table style="width:100%;">
            <tr>
                <td style="width:50%;">Dicetak oleh: <?= $_SESSION['username'] ?? 'System' ?></td>
                <td style="width:50%;text-align:right;">Dicetak: <?= date('d-m-Y H:i:s') ?></td>
            </tr>
        </table>
    </div>
</body>
</html>