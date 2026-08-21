<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/kawat_helper.php';

if (($_SESSION['status'] ?? '') !== 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$user = $_SESSION['username'] ?? 'system';
kawat_sync_master_barang($koneksi, $user);
kawat_sync_pembelian($koneksi, $user);

$result = $koneksi->query("
    SELECT
        k.id_kawat,
        k.ukuran,
        k.created_by,
        k.created_at,
        k.updated_by,
        k.updated_at,
        GROUP_CONCAT(DISTINCT CONCAT(mb.id_barang,' - ',mb.nama_barang)
                     ORDER BY mb.id_barang SEPARATOR ' | ') AS barang_terkait,

        COALESCE(SUM(CASE WHEN t.jenis_mutasi='PEMBELIAN' THEN t.qty_kg ELSE 0 END),0) AS pembelian,
        COALESCE(SUM(CASE WHEN t.jenis_mutasi='PEMAKAIAN' THEN t.qty_kg ELSE 0 END),0) AS pemakaian,
        COALESCE(SUM(CASE WHEN t.jenis_mutasi='RETURN_BEKAS' THEN t.qty_kg ELSE 0 END),0) AS return_bekas,
        COALESCE(SUM(CASE WHEN t.jenis_mutasi='ROMBENG' THEN t.qty_kg ELSE 0 END),0) AS rombeng,

        COALESCE(SUM(CASE WHEN t.kondisi='BARU' AND t.arah='IN' THEN t.qty_kg
                          WHEN t.kondisi='BARU' AND t.arah='OUT' THEN -t.qty_kg ELSE 0 END),0) AS stok_baru,
        COALESCE(SUM(CASE WHEN t.kondisi='BEKAS' AND t.arah='IN' THEN t.qty_kg
                          WHEN t.kondisi='BEKAS' AND t.arah='OUT' THEN -t.qty_kg ELSE 0 END),0) AS stok_bekas
    FROM master_kawat k
    LEFT JOIN master_kawat_barang mkb ON mkb.id_kawat=k.id_kawat
    LEFT JOIN master_barang mb ON mb.id_barang=mkb.id_barang
    LEFT JOIN transaksi_kawat t ON t.id_kawat=k.id_kawat
    GROUP BY k.id_kawat,k.ukuran,k.created_by,k.created_at,k.updated_by,k.updated_at
    ORDER BY k.ukuran
");

function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rekap Master Kawat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{background:#f4f7fb}.wrap{max-width:1500px;margin:25px auto}.head{background:linear-gradient(135deg,#198754,#0b5d3b);color:white;padding:18px 22px;border-radius:14px 14px 0 0}.bodyx{background:#fff;padding:22px;border-radius:0 0 14px 14px;box-shadow:0 5px 18px rgba(0,0,0,.08)}.table thead th{background:#26364a;color:#fff;font-size:11px;text-transform:uppercase;white-space:nowrap}.table td{vertical-align:middle}.barang{font-size:11px;max-width:350px}.total{font-weight:700;color:#0d6efd}
</style>
</head>
<body>
<div class="container-fluid wrap">
<div class="head d-flex justify-content-between align-items-center flex-wrap gap-2">
<div><h5 class="mb-1"><i class="fas fa-list-ul me-2"></i>Rekap Stok Kawat</h5><small class="opacity-75">Master berasal dari master_barang; stok berasal dari ledger transaksi.</small></div>
<a href="master_kawat.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
</div>
<div class="bodyx">
<div class="alert alert-info">
    <b>Catatan:</b> kolom <i>stok_awal</i> tidak lagi dipakai untuk saldo operasional. Saldo dihitung dari seluruh transaksi masuk dan keluar.
</div>
<div class="table-responsive">
<table class="table table-bordered table-hover">
<thead><tr>
<th>No</th><th>Ukuran</th><th>Master Barang Terkait</th><th>Pembelian</th><th>Dipakai Pabrik</th><th>Kembali Bekas</th><th>Rombeng</th><th>Stok Baru</th><th>Stok Bekas</th><th>Total Stok</th>
</tr></thead>
<tbody>
<?php $no=1; while($r=$result->fetch_assoc()): $total=(float)$r['stok_baru']+(float)$r['stok_bekas']; ?>
<tr>
<td><?= $no++ ?></td>
<td><span class="badge bg-danger"><?= number_format((float)$r['ukuran'],2) ?> mm</span></td>
<td class="barang"><?= h($r['barang_terkait'] ?: '-') ?></td>
<td><?= number_format((float)$r['pembelian'],2) ?></td>
<td><?= number_format((float)$r['pemakaian'],2) ?></td>
<td><?= number_format((float)$r['return_bekas'],2) ?></td>
<td><?= number_format((float)$r['rombeng'],2) ?></td>
<td><b><?= number_format((float)$r['stok_baru'],2) ?></b></td>
<td><b><?= number_format((float)$r['stok_bekas'],2) ?></b></td>
<td class="total"><?= number_format($total,2) ?> KG</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>
</body>
</html>
