<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Ambil parameter filter
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');

// Set header untuk Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Kondisi_Kendaraan_".$bulan."_".$tahun.".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Mulai output
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<style>
    body { font-family: Arial, sans-serif; }
    .title { font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 20px; }
    .subtitle { font-size: 14px; text-align: center; margin-bottom: 20px; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    th { background-color: #4CAF50; color: white; padding: 8px; border: 1px solid #ddd; text-align: left; }
    td { padding: 8px; border: 1px solid #ddd; }
    .header { background-color: #4472C4; color: white; font-weight: bold; text-align: center; }
    .header-orange { background-color: #ED7D31; color: white; font-weight: bold; text-align: center; }
    .header-green { background-color: #70AD47; color: white; font-weight: bold; text-align: center; }
    .header-purple { background-color: #7030A0; color: white; font-weight: bold; text-align: center; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .footer { margin-top: 20px; font-size: 12px; text-align: right; }
    .warning { background-color: #FFC000; }
    .danger { background-color: #FF0000; color: white; }
    .success { background-color: #92D050; }
    .info { background-color: #00B0F0; color: white; }
</style>';
echo '</head>';
echo '<body>';

// Judul Laporan
echo '<div class="title">LAPORAN KONDISI KENDARAAN</div>';
echo '<div class="subtitle">Periode: ' . date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) . '</div>';
echo '<br>';

// 1. STATISTIK
echo '<table>';
echo '<tr><th colspan="4" class="header">STATISTIK KONDISI KENDARAAN</th></tr>';
echo '<tr style="background-color: #D9E1F2;">';
echo '<th style="font-weight: bold;">Total Armada</th>';
echo '<th style="font-weight: bold;">Dalam Service</th>';
echo '<th style="font-weight: bold;">Service Berjalan</th>';
echo '<th style="font-weight: bold;">Selesai Service</th>';
echo '</tr>';

// Hitung statistik
$query_total = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM master_mobil");
$total_mobil = mysqli_fetch_assoc($query_total)['total'];

$query_service = mysqli_query($koneksi, "SELECT COUNT(DISTINCT k.id_mobil) as total 
                                        FROM kondisi_kendaraan k 
                                        WHERE k.kondisi IN ('DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT')
                                        AND k.created_at <= NOW()");
$total_service = mysqli_fetch_assoc($query_service)['total'];

$query_ongoing = mysqli_query($koneksi, "SELECT COUNT(DISTINCT k.id_mobil) as total 
                                        FROM kondisi_kendaraan k 
                                        WHERE k.kondisi IN ('DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT')
                                        AND (k.end_date IS NULL OR k.end_date > NOW())");
$total_ongoing = mysqli_fetch_assoc($query_ongoing)['total'];

$query_selesai = mysqli_query($koneksi, "SELECT COUNT(DISTINCT k.id_mobil) as total 
                                        FROM kondisi_kendaraan k 
                                        WHERE k.kondisi = 'BAIK'
                                        AND MONTH(k.end_date) = '$bulan' 
                                        AND YEAR(k.end_date) = '$tahun'");
$total_selesai = mysqli_fetch_assoc($query_selesai)['total'];

echo '<tr>';
echo '<td class="text-center"><strong>' . $total_mobil . '</strong></td>';
echo '<td class="text-center"><strong>' . $total_service . '</strong></td>';
echo '<td class="text-center"><strong>' . $total_ongoing . '</strong></td>';
echo '<td class="text-center"><strong>' . $total_selesai . '</strong></td>';
echo '</tr>';
echo '</table>';
echo '<br>';

// 2. KENDARAAN DALAM SERVICE
echo '<table>';
echo '<tr><th colspan="7" class="header-orange">KENDARAAN DALAM SERVICE</th></tr>';
echo '<tr style="background-color: #FCE4D6;">';
echo '<th style="font-weight: bold;">Plat Nomor</th>';
echo '<th style="font-weight: bold;">Driver</th>';
echo '<th style="font-weight: bold;">Merk/Tipe</th>';
echo '<th style="font-weight: bold;">Kondisi</th>';
echo '<th style="font-weight: bold;">Keterangan</th>';
echo '<th style="font-weight: bold;">Start Service</th>';
echo '<th style="font-weight: bold;">Durasi (Hari)</th>';
echo '</tr>';

$query_service_detail = mysqli_query($koneksi, "
    SELECT k.*, m.driver_tetap, m.merk_tipe 
    FROM kondisi_kendaraan k 
    JOIN master_mobil m ON k.id_mobil = m.id_mobil 
    WHERE k.kondisi IN ('DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT')
    AND (k.end_date IS NULL OR k.end_date > NOW())
    ORDER BY k.start_date ASC
");

if (mysqli_num_rows($query_service_detail) > 0) {
    while($d = mysqli_fetch_array($query_service_detail)):
        $durasi = 0;
        if ($d['start_date']) {
            $start = new DateTime($d['start_date']);
            $now = new DateTime();
            $diff = $start->diff($now);
            $durasi = $diff->days;
        }
        
        $keterangan = $d['keterangan'] ?? '-';
        $keterangan = str_replace("\n", " ", $keterangan);
        $keterangan = str_replace("\r", " ", $keterangan);
    ?>
    <tr>
        <td><?= $d['plat_nomor'] ?></td>
        <td><?= $d['driver_tetap'] ?></td>
        <td><?= $d['merk_tipe'] ?></td>
        <td><?= $d['kondisi'] ?></td>
        <td><?= $keterangan ?></td>
        <td><?= date('d-M-Y', strtotime($d['start_date'])) ?></td>
        <td class="text-center"><?= $durasi ?></td>
    </tr>
    <?php endwhile;
} else {
    echo '<tr><td colspan="7" class="text-center">Tidak ada kendaraan dalam service</td></tr>';
}
echo '</table>';
echo '<br>';

// 3. RIWAYAT SERVICE BULAN INI
echo '<table>';
echo '<tr><th colspan="8" class="header-green">RIWAYAT SERVICE ' . strtoupper(date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun))) . '</th></tr>';
echo '<tr style="background-color: #E2EFDA;">';
echo '<th style="font-weight: bold;">Plat Nomor</th>';
echo '<th style="font-weight: bold;">Driver</th>';
echo '<th style="font-weight: bold;">Merk/Tipe</th>';
echo '<th style="font-weight: bold;">Kondisi</th>';
echo '<th style="font-weight: bold;">Keterangan</th>';
echo '<th style="font-weight: bold;">Start Service</th>';
echo '<th style="font-weight: bold;">End Service</th>';
echo '<th style="font-weight: bold;">Durasi</th>';
echo '</tr>';

$query_riwayat = mysqli_query($koneksi, "
    SELECT k.*, m.driver_tetap, m.merk_tipe 
    FROM kondisi_kendaraan k 
    JOIN master_mobil m ON k.id_mobil = m.id_mobil 
    WHERE MONTH(k.created_at) = '$bulan' 
    AND YEAR(k.created_at) = '$tahun'
    ORDER BY k.created_at DESC
");

if (mysqli_num_rows($query_riwayat) > 0) {
    while($d = mysqli_fetch_array($query_riwayat)):
        $durasi = '-';
        if ($d['start_date'] && $d['end_date']) {
            $start = new DateTime($d['start_date']);
            $end = new DateTime($d['end_date']);
            $diff = $start->diff($end);
            $durasi = ($diff->days + 1) . ' hari';
        } elseif ($d['start_date']) {
            $durasi = 'Berjalan';
        }
        
        $keterangan = $d['keterangan'] ?? '-';
        $keterangan = str_replace("\n", " ", $keterangan);
        $keterangan = str_replace("\r", " ", $keterangan);
    ?>
    <tr>
        <td><?= $d['plat_nomor'] ?></td>
        <td><?= $d['driver_tetap'] ?></td>
        <td><?= $d['merk_tipe'] ?></td>
        <td><?= $d['kondisi'] ?></td>
        <td><?= $keterangan ?></td>
        <td><?= $d['start_date'] ? date('d-M-Y', strtotime($d['start_date'])) : '-' ?></td>
        <td><?= $d['end_date'] ? date('d-M-Y', strtotime($d['end_date'])) : '-' ?></td>
        <td class="text-center"><?= $durasi ?></td>
    </tr>
    <?php endwhile;
} else {
    echo '<tr><td colspan="8" class="text-center">Tidak ada data service untuk bulan ini</td></tr>';
}
echo '</table>';
echo '<br>';

// 4. REKAP SERVICE PER PLAT NOMOR (Frekuensi Service)
echo '<table>';
echo '<tr><th colspan="6" class="header-purple">REKAP FREKUENSI SERVICE PER KENDARAAN</th></tr>';
echo '<tr style="background-color: #E4DFEC;">';
echo '<th style="font-weight: bold;">Plat Nomor</th>';
echo '<th style="font-weight: bold;">Driver</th>';
echo '<th style="font-weight: bold;">Total Service</th>';
echo '<th style="font-weight: bold;">Total Hari Service</th>';
echo '<th style="font-weight: bold;">Rata-rata Hari Service</th>';
echo '<th style="font-weight: bold;">Keterangan Terakhir</th>';
echo '</tr>';

$query_rekap = mysqli_query($koneksi, "
    SELECT 
        m.plat_nomor,
        m.driver_tetap,
        COUNT(k.id_kondisi) as total_service,
        SUM(CASE 
            WHEN k.start_date IS NOT NULL AND k.end_date IS NOT NULL 
            THEN DATEDIFF(k.end_date, k.start_date) + 1 
            ELSE 0 
        END) as total_hari,
        AVG(CASE 
            WHEN k.start_date IS NOT NULL AND k.end_date IS NOT NULL 
            THEN DATEDIFF(k.end_date, k.start_date) + 1 
            ELSE NULL 
        END) as rata_hari,
        (SELECT keterangan FROM kondisi_kendaraan k2 
         WHERE k2.id_mobil = m.id_mobil 
         ORDER BY k2.created_at DESC LIMIT 1) as keterangan_terakhir
    FROM master_mobil m
    LEFT JOIN kondisi_kendaraan k ON m.id_mobil = k.id_mobil
    WHERE k.kondisi IN ('DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT')
    GROUP BY m.id_mobil
    HAVING total_service > 0
    ORDER BY total_service DESC
");

if (mysqli_num_rows($query_rekap) > 0) {
    while($d = mysqli_fetch_array($query_rekap)):
        $keterangan = $d['keterangan_terakhir'] ?? '-';
        $keterangan = str_replace("\n", " ", $keterangan);
        $keterangan = str_replace("\r", " ", $keterangan);
    ?>
    <tr>
        <td><?= $d['plat_nomor'] ?></td>
        <td><?= $d['driver_tetap'] ?></td>
        <td class="text-center"><?= $d['total_service'] ?>x</td>
        <td class="text-center"><?= $d['total_hari'] ?> hari</td>
        <td class="text-center"><?= number_format($d['rata_hari'], 1) ?> hari</td>
        <td><?= $keterangan ?></td>
    </tr>
    <?php endwhile;
} else {
    echo '<tr><td colspan="6" class="text-center">Tidak ada data service</td></tr>';
}
echo '</table>';
echo '<br>';

// 5. DETAIL SEMUA DATA KONDISI (Lengkap)
echo '<table>';
echo '<tr><th colspan="8" class="header">DETAIL SELURUH DATA KONDISI KENDARAAN</th></tr>';
echo '<tr style="background-color: #D9E1F2;">';
echo '<th style="font-weight: bold;">Plat Nomor</th>';
echo '<th style="font-weight: bold;">Driver</th>';
echo '<th style="font-weight: bold;">Jenis</th>';
echo '<th style="font-weight: bold;">Kategori</th>';
echo '<th style="font-weight: bold;">Merk/Tipe</th>';
echo '<th style="font-weight: bold;">Kondisi</th>';
echo '<th style="font-weight: bold;">Keterangan</th>';
echo '<th style="font-weight: bold;">Tanggal Update</th>';
echo '</tr>';

$query_detail = mysqli_query($koneksi, "
    SELECT k.*, m.driver_tetap, m.jenis_kendaraan, m.kategori_kendaraan, m.merk_tipe
    FROM kondisi_kendaraan k 
    JOIN master_mobil m ON k.id_mobil = m.id_mobil 
    ORDER BY k.created_at DESC
    LIMIT 100
");

if (mysqli_num_rows($query_detail) > 0) {
    while($d = mysqli_fetch_array($query_detail)):
        $keterangan = $d['keterangan'] ?? '-';
        $keterangan = str_replace("\n", " ", $keterangan);
        $keterangan = str_replace("\r", " ", $keterangan);
    ?>
    <tr>
        <td><?= $d['plat_nomor'] ?></td>
        <td><?= $d['driver_tetap'] ?></td>
        <td><?= $d['jenis_kendaraan'] ?></td>
        <td><?= $d['kategori_kendaraan'] ?></td>
        <td><?= $d['merk_tipe'] ?></td>
        <td><?= $d['kondisi'] ?></td>
        <td><?= $keterangan ?></td>
        <td><?= date('d-M-Y H:i', strtotime($d['created_at'])) ?></td>
    </tr>
    <?php endwhile;
} else {
    echo '<tr><td colspan="8" class="text-center">Tidak ada data kondisi</td></tr>';
}
echo '</table>';

// Footer
echo '<div class="footer">';
echo 'Dicetak: ' . date('d-m-Y H:i:s') . '<br>';
echo 'User: ' . ($_SESSION['username'] ?? 'System');
echo '</div>';

echo '</body>';
echo '</html>';
?>