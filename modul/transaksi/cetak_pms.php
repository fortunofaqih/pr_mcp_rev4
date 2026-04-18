<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';
$id = $_GET['id'];
$q = mysqli_query($koneksi, "SELECT p.*, m.nama_barang, u.nama_lengkap 
     FROM tr_pemusnahan p 
     JOIN master_barang m ON p.id_barang = m.id_barang 
     JOIN users u ON p.id_user = u.id_user 
     WHERE p.id_pemusnahan = $id");
$d = mysqli_fetch_array($q);
function formatAngka($angka) {
    if ($angka == 0) return '0';
    return rtrim(rtrim(number_format($angka, 4, ',', '.'), '0'), ',');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Bukti Pemusnahan</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .info { margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #000; text-align: left; }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <h2>MUTIARA CAHAYA PLASTINDO</h2>
        <p>BUKTI PENGHAPUSAN / PEMUSNAHAN BARANG</p>
    </div>
    <div class="info">
        No. Transaksi: <b><?= $d['no_pemusnahan'] ?></b><br>
        Tanggal: <?= date('d F Y', strtotime($d['tgl_pemusnahan'])) ?>
    </div>
    <table>
        <tr><th>Nama Barang</th><td><?= $d['nama_barang'] ?></td></tr>
        <tr><th>Jumlah</th><td><?= formatAngka($d['qty_dimusnahkan']) ?> <?= $d['satuan'] ?></td></tr>
        <tr><th>Metode</th><td><?= $d['metode_pemusnahan'] ?></td></tr>
		
        <tr><th>Nilai Scrap</th><td>Rp <?= number_format($d['nilai_jual_scrap'], 0, ',', '.') ?></td></tr>
        <tr><th>Alasan</th><td><?= $d['alasan_pemusnahan'] ?></td></tr>
        <tr><th>Petugas</th><td><?= $d['nama_lengkap'] ?></td></tr>
    </table>
</body>
</html>