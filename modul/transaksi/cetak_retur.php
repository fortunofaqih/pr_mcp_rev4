<?php
include '../../config/koneksi.php';
$id = $_GET['id'];
$query = mysqli_query($koneksi, "SELECT r.*, m.nama_barang, m.satuan, u.nama_lengkap 
                                FROM tr_retur r 
                                JOIN master_barang m ON r.id_barang = m.id_barang 
                                JOIN users u ON r.id_user = u.id_user 
                                WHERE r.id_retur = '$id'");
$d = mysqli_fetch_array($query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Bukti Retur - <?= $d['no_retur'] ?></title>
    <style>
        @page { size: 165mm 105mm; margin: 0; }
        body { font-family: 'Arial', sans-serif; font-size: 9pt; margin: 0; padding: 0; width: 165mm; height: 105mm; color: #000; }
        .bon-wrapper { padding: 5mm; box-sizing: border-box; width: 100%; height: 100%; position: relative; }
        .header-top { display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 3px; margin-bottom: 10px; }
        .header-title { text-align: center; margin-bottom: 15px; }
        .header-title h3 { margin: 0; font-size: 12pt; text-decoration: underline; }
        .info-row { display: flex; margin-bottom: 5px; }
        .info-label { width: 110px; font-weight: bold; }
        .info-value { flex: 1; border-bottom: 1px dotted #888; }
        .table-items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-items th, .table-items td { border: 1px solid #000; padding: 6px; text-align: left; }
        .table-items th { background: #eee; text-align: center; }
        .ttd-section { display: flex; justify-content: space-between; margin-top: 20px; text-align: center; }
        .ttd-box { width: 30%; }
        .ttd-space { height: 40px; }
        .no-print-zone { background: #fdf6e3; padding: 10px; text-align: center; border-bottom: 1px solid #ddd; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print no-print-zone">
    <button onclick="window.print()" style="padding: 8px 20px; background: blue; color: white; border: none; cursor: pointer;">KLIK UNTUK CETAK</button>
    <p style="font-size: 8pt; color: red; margin-top: 5px;">Gunakan kertas Landscape Ukuran Custom (16.5cm x 10.5cm)</p>
</div>

<div class="bon-wrapper">
    <div class="header-top">
        <div style="font-weight: bold;">MCP LOGISTICS</div>
        <div>Tgl Cetak: <?= date('d/m/Y H:i') ?></div>
    </div>

    <div class="header-title">
        <h3>BUKTI PENGEMBALIAN BARANG (RETUR)</h3>
        <div style="font-weight: bold; margin-top: 3px;"><?= $d['no_retur'] ?></div>
    </div>

    <div class="info-row">
        <div class="info-label">TANGGAL RETUR</div>
        <div class="info-value">: <?= date('d F Y', strtotime($d['tgl_retur'])) ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">PENGEMBALI</div>
        <div class="info-value">: <?= $d['pengembali'] ?></div>
    </div>
    <div class="info-row">
        <div class="info-label">JENIS RETUR</div>
        <div class="info-value">: <?= $d['jenis_retur'] ?></div>
    </div>

    <table class="table-items">
        <thead>
            <tr>
                <th>NAMA BARANG</th>
                <th width="20%">JUMLAH</th>
                <th>ALASAN</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="font-weight: bold;"><?= $d['nama_barang'] ?></td>
                <td align="center" style="font-size: 11pt; font-weight: bold;"><?= number_format($d['qty_retur'], 2) ?> <?= $d['satuan'] ?></td>
                <td style="font-style: italic;"><?= $d['alasan_retur'] ?></td>
            </tr>
        </tbody>
    </table>

    <div class="ttd-section">
        <div class="ttd-box">
            <div>Yang Menyerahkan,</div>
            <div class="ttd-space"></div>
            <div style="font-weight: bold;">( <?= $d['pengembali'] ?> )</div>
        </div>
        <div class="ttd-box">
            <div>Penerima Gudang,</div>
            <div class="ttd-space"></div>
            <div style="font-weight: bold;">( <?= $d['nama_lengkap'] ?> )</div>
        </div>
        <div class="ttd-box">
            <div>Mengetahui,</div>
            <div class="ttd-space"></div>
            <div style="font-weight: bold;">( ......................... )</div>
        </div>
    </div>
</div>

<script>
    // Otomatis buka dialog print saat loading
    window.onload = function() {
        // window.print();
    }
</script>
</body>
</html>