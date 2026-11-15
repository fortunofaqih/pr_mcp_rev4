<?php
/** finance_prb_pembayaran.php
 * Antrean otorisasi PRB untuk Finance; tidak mengubah stok atau realisasi pembelian.
 */
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
if (($_SESSION['status'] ?? '') !== 'login' || !in_array($_SESSION['role'] ?? '', ['finance','administrator','manager'],true)) {
    header('Location: ../../index.php'); exit;
}
$boleh_bayar = in_array($_SESSION['role'], ['finance','administrator'],true);
if (empty($_SESSION['csrf_prb_finance'])) $_SESSION['csrf_prb_finance']=bin2hex(random_bytes(32));
$esc=static function($v){return htmlspecialchars((string)($v??''),ENT_QUOTES,'UTF-8');};
$sql="SELECT b.*, r.no_request,r.nama_pemesan,r.status_request,r.file_penawaran,
      po.no_po,ms.nama_supplier,
      CASE WHEN UPPER(COALESCE(r.keterangan,'')) LIKE '%USD%'
                OR EXISTS (SELECT 1 FROM tr_request_detail rd_usd
                           WHERE rd_usd.id_request = r.id_request
                             AND UPPER(COALESCE(rd_usd.keterangan,'')) LIKE '%USD%')
           THEN 'USD' ELSE 'Rp' END AS mata_uang_prb
      FROM tr_prb_pembayaran b JOIN tr_request r ON r.id_request=b.id_request
      LEFT JOIN tr_purchase_order po ON po.id_po=(SELECT MAX(id_po) FROM tr_purchase_order WHERE id_request=r.id_request)
      LEFT JOIN master_supplier ms ON ms.id_supplier=po.id_supplier
      WHERE b.status_bayar='MENUNGGU_FINANCE' AND r.status_request <> 'BATAL'
      ORDER BY b.verified_at ASC";
$q=mysqli_query($koneksi,$sql);
$rows=[]; if($q)while($r=mysqli_fetch_assoc($q))$rows[]=$r;
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Antrean Pembayaran PRB - Finance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>body{background:#f4f6f9;font-family:system-ui,sans-serif}.bar{background:#1e3a8a;color:white}.card{border:0;border-radius:12px;box-shadow:0 4px 16px #0001}small{font-size:.8rem}</style></head><body>
<header class="bar p-3 mb-4"><div class="container-fluid d-flex justify-content-between align-items-center"><strong><i class="fas fa-wallet me-2"></i>FINANCE — PEMBAYARAN PRB</strong><a class="btn btn-light btn-sm" href="../../index.php">Kembali</a></div></header>
<main class="container-fluid px-4"><div class="alert alert-info">Yang tampil di sini adalah Purchase Request Besar yang <strong>sudah disetujui Manager</strong> dan dapat dilakukan pembayaran. Finance harus melakukan verifikasi jika sudah dibayarkan.</div>
<?php if (!$rows): ?><div class="card p-5 text-center text-muted">Jika belum di verifikasi pembayaran oleh Finance maka list PR Besar akan tetap muncul di halaman Manager.</div><?php endif; ?>
<div class="row g-3">
<?php foreach($rows as $r): $mata_uang_prb = $r['mata_uang_prb'] === 'USD' ? 'USD' : 'Rp'; ?><div class="col-xl-6"><div class="card"><div class="card-body">
<div class="d-flex justify-content-between flex-wrap gap-2"><h6 class="fw-bold text-primary"><?= $esc($r['no_request']) ?></h6><span class="badge bg-warning text-dark">MENUNGGU FINANCE</span></div>
<div class="row g-2 small mb-3">
<div class="col-6"><b>Supplier:</b> <?= $esc($r['nama_supplier']) ?></div><div class="col-6"><b>No. PO:</b> <?= $esc($r['no_po']) ?></div>
<div class="col-6"><b>Jenis:</b> <?= $esc($r['jenis_disetujui']) ?></div><div class="col-6"><b>Diverifikasi Manager:</b> <?= $esc($r['verified_by']) ?></div>
<div class="col-12"><b>Waktu verifikasi:</b> <?= $r['verified_at'] ? date('d/m/Y H:i',strtotime($r['verified_at'])) : '-' ?></div>
<div class="col-6"><b>Total tagihan:</b> <?= $mata_uang_prb ?> <?=  number_format((float)$r['total_tagihan'],2,',','.') ?></div>
<div class="col-6"><b>Sudah terbayar:</b> <?= $mata_uang_prb ?> <?=  number_format((float)$r['total_terbayar'],2,',','.') ?></div>
<div class="col-12"><b>Nominal siap dibayar:</b> <strong class="text-danger"><?= $mata_uang_prb ?> <?=  number_format((float)$r['nominal_disetujui'],2,',','.') ?></strong></div>
</div>
<?php if($r['file_penawaran']): ?><a target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mb-3" href="../../download_penawaran.php?file=<?= urlencode($r['file_penawaran']) ?>&amp;id_request=<?= (int)$r['id_request'] ?>"><i class="fas fa-file-pdf me-1"></i>Buka Penawaran</a><?php endif; ?>
<?php if($boleh_bayar): ?>
<form method="POST" action="proses_bayar.php" onsubmit="return confirm('Pastikan pembayaran supplier benar-benar telah dilakukan. Catat pembayaran ini?')">
<input type="hidden" name="csrf_prb_finance" value="<?= $esc($_SESSION['csrf_prb_finance']) ?>">
<input type="hidden" name="id_request" value="<?= (int)$r['id_request'] ?>">
<div class="mb-2"><label class="form-label small fw-bold">Cash / Transfer</label><input name="referensi_bayar" class="form-control" maxlength="150" placeholder="wajib diisi"></div>
<div class="mb-3"><label class="form-label small fw-bold">Catatan Finance</label><textarea name="catatan" class="form-control" rows="2"></textarea></div>
<button class="btn btn-success fw-bold">Konfirmasi Sudah Dibayar</button></form>
<?php else: ?><div class="alert alert-secondary small mb-0">Akses baca saja. Pencatatan pembayaran hanya untuk Finance atau Administrator.</div><?php endif; ?>
</div></div></div><?php endforeach; ?>
</div></main>
<?php if(isset($_GET['pesan'])): ?><script>alert(<?= json_encode($_GET['pesan'], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>);</script><?php endif; ?>
</body></html>
