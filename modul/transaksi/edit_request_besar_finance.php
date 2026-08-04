<?php
// ============================================================
// edit_request_besar_finance.php
// Edit PR Besar - Khusus Finance (Readonly semua field)
// Hanya Catatan PO yang bisa diedit
// ============================================================
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// HARUS ROLE FINANCE
if ($_SESSION['role'] !== 'finance') {
    header("location:pr.php?pesan=akses_ditolak");
    exit;
}

$id              = (int)($_GET['id'] ?? 0);
$username_login  = $_SESSION['username'] ?? '';

if (!$id) { 
    header("location:pr_finance.php"); 
    exit; 
}

// 1. AMBIL DATA PR
$query_pr = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request='$id' AND kategori_pr='BESAR'");
$pr = mysqli_fetch_assoc($query_pr);

if (!$pr) { 
    header("location:pr_finance.php?pesan=tidak_ditemukan"); 
    exit; 
}

// 2. AMBIL DATA PO
$po = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tr_purchase_order WHERE id_request='$id' LIMIT 1"
));

// 3. AMBIL DETAIL ITEM
$details_raw = [];
$res_det = mysqli_query($koneksi,
    "SELECT d.*, b.nama_barang as nama_master, m.plat_nomor
     FROM tr_request_detail d
     LEFT JOIN master_barang b ON d.id_barang = b.id_barang
     LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
     WHERE d.id_request = '$id'
     ORDER BY d.id_detail ASC");
while ($row = mysqli_fetch_assoc($res_det)) { $details_raw[] = $row; }

// 4. AMBIL DATA SUPPLIER
$supplier = null;
if ($po && $po['id_supplier']) {
    $supplier = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT * FROM master_supplier WHERE id_supplier = '{$po['id_supplier']}' LIMIT 1"
    ));
}

// Grand Total
$grand_est = array_sum(array_map(function($d){
    return (float)$d['jumlah'] * (float)$d['harga_satuan_estimasi'];
}, $details_raw));

// Status badge
switch ($pr['status_request']) {
    case 'PENDING' : $badge_color = 'bg-warning text-dark'; break;
    case 'PROSES'  : $badge_color = 'bg-primary';           break;
    case 'SELESAI' : $badge_color = 'bg-success';           break;
    case 'BATAL'   : $badge_color = 'bg-danger';            break;
    case 'DITOLAK' : $badge_color = 'bg-danger';            break;
    default        : $badge_color = 'bg-secondary';
}

// Approval badge
switch ($pr['status_approval']) {
    case 'MENUNGGU APPROVAL': $app_badge = 'bg-warning text-dark'; break;
    case 'APPROVED 1'       : $app_badge = 'bg-info';              break;
    case 'APPROVED 2'       : $app_badge = 'bg-primary';           break;
    case 'APPROVED'         : 
    case 'DISETUJUI'        : $app_badge = 'bg-success';           break;
    case 'DITOLAK'          : $app_badge = 'bg-danger';            break;
    default                 : $app_badge = 'bg-secondary';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit PR Finance - <?= htmlspecialchars($pr['no_request']) ?> - MCP System</title>
<link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--bg:#f4f6f9;}
body{background:var(--bg);font-size:.85rem;}
.page-header{background:linear-gradient(135deg,#0c2461,#1e3a8a);color:white;border-radius:12px 12px 0 0;padding:18px 24px;}
.page-header h5{font-size:1rem;margin:0;}
.section-label{font-size:.7rem;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin-bottom:4px;}
.info-box{background:#dbeafe;border-left:4px solid #3b82f6;border-radius:6px;padding:12px 16px;font-size:.82rem;}
.readonly-box{background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:12px 16px;}
.readonly-box .label{font-size:.65rem;font-weight:700;text-transform:uppercase;color:#6c757d;}
.readonly-box .value{font-size:.85rem;font-weight:600;color:#1a1a2e;}
.total-box{background:white;border-radius:10px;border:1px solid #dee2e6;padding:16px;}
.total-box .grand-label{font-size:.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;}
.total-box .grand-value{font-size:1.4rem;font-weight:800;color:#1e3a8a;}
.po-section{background:#f5f3ff;border:1px solid #ddd6fe;border-radius:12px;padding:22px 24px;margin-top:8px;}
.po-section-title{font-size:.92rem;font-weight:700;color:#4c1d95;display:flex;align-items:center;gap:8px;margin-bottom:18px;}
.po-section-title .divider{width:4px;height:22px;background:#7c3aed;border-radius:2px;flex-shrink:0;}
.grand-po-box{background:#4c1d95;color:white;border-radius:10px;padding:14px 18px;height:100%;display:flex;flex-direction:column;justify-content:center;}
.grand-po-box .label{font-size:.7rem;opacity:.8;text-transform:uppercase;letter-spacing:.5px;}
.grand-po-box .value{font-size:1.4rem;font-weight:800;}
.card-footer{background:white;border-top:1px solid #dee2e6;border-radius:0 0 12px 12px;}
.table-readonly thead{background:#1e3a8a;color:white;font-size:.72rem;text-transform:uppercase;}
.table-readonly td{vertical-align:middle;background:#f8f9fa;}
.table-readonly .bg-light-item{background:#f8f9fa;}
.badge-status{font-size:.75rem;padding:6px 15px;border-radius:20px;}
.finance-badge{background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:2px 14px;font-size:.7rem;font-weight:700;color:#fff;}
.editable-catatan{background:#fff;border:2px solid #7c3aed;border-radius:8px;transition:all .3s;}
.editable-catatan:focus{box-shadow:0 0 0 3px rgba(124,58,237,.3);border-color:#4c1d95;}
.editable-catatan:hover{border-color:#4c1d95;}
</style>
</head>
<body class="py-4">
<div class="container-fluid px-4">

<form action="proses_edit_besar_finance_tanpa_approval.php" method="POST" id="formEditPRBesarFinance">
<input type="hidden" name="id_request" value="<?= $id ?>">

<div class="card shadow-sm border-0" style="border-radius:12px;">

<!-- HEADER -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5>
                <i class="fas fa-edit me-2"></i>
                EDIT PR — <?= htmlspecialchars($pr['no_request']) ?>
                <span class="finance-badge ms-2">
                    <i class="fas fa-landmark me-1"></i>FINANCE
                </span>
            </h5>
            <div class="d-flex align-items-center gap-2 mt-1">
                <small class="opacity-75">
                    <i class="fas fa-info-circle me-1"></i>
                    Hanya <strong>Catatan PO</strong> yang dapat diedit
                </small>
            </div>
        </div>
        <a href="pr_finance.php" class="btn btn-sm btn-light">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>
</div>

<div class="card-body p-4">

<?php if (isset($_GET['pesan']) && $_GET['pesan'] === 'gagal' && !empty($_SESSION['error_edit'])): ?>
    <div class="alert alert-danger">
        <strong>Gagal menyimpan perubahan:</strong><br>
        <?= htmlspecialchars($_SESSION['error_edit']) ?>
        <?php unset($_SESSION['error_edit']); ?>
    </div>
<?php endif; ?>

<!-- INFO STATUS PR -->
<div class="info-box mb-4">
    <div class="row g-2">
        <div class="col-md-6">
            <div class="fw-bold text-primary mb-1">
                <i class="fas fa-info-circle me-2"></i>
                Status PR: 
                <span class="badge <?= $badge_color ?> badge-status"><?= htmlspecialchars($pr['status_request']) ?></span>
            </div>
            <div class="small text-muted">
                <i class="fas fa-user me-1"></i>Dibuat oleh: <?= htmlspecialchars($pr['created_by'] ?? '-') ?>
                | Tanggal: <?= date('d/m/Y H:i', strtotime($pr['created_at'] ?? $pr['tgl_request'])) ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="fw-bold text-primary mb-1">
                <i class="fas fa-check-circle me-2"></i>
                Approval: 
                <span class="badge <?= $app_badge ?> badge-status"><?= htmlspecialchars($pr['status_approval'] ?? 'BELUM') ?></span>
            </div>
            <div class="small text-muted">
                <?php if (!empty($pr['updated_by']) && !empty($pr['updated_at'])): ?>
                    <i class="fas fa-edit me-1"></i>Terakhir edit: <?= htmlspecialchars($pr['updated_by']) ?> 
                    (<?= date('d/m/Y H:i', strtotime($pr['updated_at'])) ?>)
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (!empty($pr['catatan_tolak'])): ?>
    <div class="mt-2 text-danger">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Catatan penolakan: <em><?= htmlspecialchars($pr['catatan_tolak']) ?></em>
    </div>
    <?php endif; ?>
</div>

<!-- HEADER FORM - READONLY -->
<div class="row g-3 mb-3">
    <div class="col-md-2">
        <div class="section-label">Nomor Request</div>
        <div class="readonly-box">
            <div class="value"><?= htmlspecialchars($pr['no_request']) ?></div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="section-label">Tanggal Request</div>
        <div class="readonly-box">
            <div class="value"><?= date('d/m/Y', strtotime($pr['tgl_request'])) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="section-label">Dibuat Oleh</div>
        <div class="readonly-box">
            <div class="value"><?= htmlspecialchars($pr['nama_pemesan']) ?></div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="section-label">Petugas Pembelian</div>
        <div class="readonly-box">
            <div class="value"><?= htmlspecialchars($pr['nama_pembeli'] ?? '-') ?></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="section-label">Keperluan / Tujuan Pembelian</div>
        <div class="readonly-box">
            <div class="value"><?= htmlspecialchars($pr['keterangan']) ?></div>
        </div>
    </div>
</div>

<hr class="my-3">

<!-- TABEL ITEM - READONLY -->
<div class="d-flex justify-content-between align-items-center mb-2">
    <span class="fw-bold" style="font-size:.85rem;color:#1e3a8a;">
        <i class="fas fa-list me-1"></i> Daftar Item Barang
        <span class="text-muted fw-normal ms-2" style="font-size:.7rem;">
            <i class="fas fa-lock me-1"></i>Readonly
        </span>
    </span>
    <span class="badge bg-light text-dark border">
        Total: <?= count($details_raw) ?> Item
    </span>
</div>

<div class="table-responsive mb-4">
<table class="table table-bordered table-readonly align-middle">
<thead>
    <tr class="text-center">
        <th style="width:40px;">#</th>
        <th style="width:200px;">Nama Barang</th>
        <th style="width:140px;">Kategori</th>
        <th style="width:150px;">Kwalifikasi / Merk</th>
        <th style="width:120px;">Unit / Mobil</th>
        <th style="width:90px;">Tipe</th>
        <th style="width:70px;">Qty</th>
        <th style="width:100px;">Satuan</th>
        <th style="width:130px;">Harga Est. (Rp)</th>
        <th style="width:130px;">Total (Rp)</th>
        <th style="width:240px;">Catatan Detail</th>
        <th style="width:80px;">BAN</th>
    </tr>
</thead>
<tbody>
<?php foreach ($details_raw as $idx => $d): 
    $subtotal = (float)$d['jumlah'] * (float)$d['harga_satuan_estimasi'];
    $nama_item = !empty($d['nama_master']) ? strtoupper($d['nama_master']) : strtoupper($d['nama_barang_manual']);
?>
<tr>
    <td class="text-center text-muted"><?= $idx + 1 ?></td>
    <td><strong><?= htmlspecialchars($nama_item) ?></strong></td>
    <td><?= htmlspecialchars($d['kategori_barang'] ?? '-') ?></td>
    <td><?= htmlspecialchars($d['kwalifikasi'] ?? '-') ?></td>
    <td><?= htmlspecialchars($d['plat_nomor'] ?? 'NON MOBIL') ?></td>
    <td><?= htmlspecialchars($d['tipe_request'] ?? 'LANGSUNG') ?></td>
    <td class="text-center"><?= number_format((float)$d['jumlah'], 2, ',', '.') ?></td>
    <td><?= htmlspecialchars($d['satuan'] ?? '-') ?></td>
    <td class="text-end"><?= number_format((float)$d['harga_satuan_estimasi'], 0, ',', '.') ?></td>
    <td class="text-end fw-bold"><?= number_format($subtotal, 0, ',', '.') ?></td>
    <td><?= htmlspecialchars($d['keterangan'] ?? '-') ?></td>
    <td class="text-center">
        <?php if ((int)$d['is_ban'] === 1): ?>
            <span class="badge bg-warning text-dark">BAN</span>
        <?php else: ?>
            <span class="text-muted">-</span>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<?php if (count($details_raw) === 0): ?>
<tr>
    <td colspan="12" class="text-center py-4 text-muted">
        <i class="fas fa-inbox me-2"></i>Tidak ada item
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- TOTAL -->
<div class="row justify-content-end mb-4">
    <div class="col-md-4">
        <div class="total-box">
            <div class="grand-label">Total Estimasi Item</div>
            <div class="grand-value">Rp <?= number_format($grand_est, 0, ',', '.') ?></div>
        </div>
    </div>
</div>

<hr class="my-2">

<!-- DATA PO - Catatan PO BISA DIEDIT -->
<div class="po-section">
    <div class="po-section-title">
        <div class="divider"></div>
        <i class="fas fa-file-invoice" style="color:#7c3aed;"></i>
        Data Purchase Order (PO)
        <span class="badge bg-success ms-2">
            <i class="fas fa-edit me-1"></i>Catatan PO dapat diedit
        </span>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-5">
            <div class="section-label">Supplier / Vendor</div>
            <div class="readonly-box">
                <div class="value">
                    <?php if ($supplier): ?>
                        <?= htmlspecialchars(strtoupper($supplier['nama_supplier'])) ?>
                        <small class="text-muted d-block" style="font-size:.7rem;">
                            <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($supplier['alamat'] ?? '-') ?>
                            <?php if (!empty($supplier['telp'])): ?>
                                | <i class="fas fa-phone me-1"></i><?= htmlspecialchars($supplier['telp']) ?>
                            <?php endif; ?>
                        </small>
                    <?php else: ?>
                        <span class="text-muted">- Belum dipilih -</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="section-label">Tanggal PO</div>
            <div class="readonly-box">
                <div class="value">
                    <?php if ($po): ?>
                        <?= date('d/m/Y', strtotime($po['tgl_po'])) ?>
                    <?php else: ?>
                        <span class="text-muted">- Belum dibuat -</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="section-label">Status PO</div>
            <div class="readonly-box">
                <div class="value">
                    <?php if ($po): ?>
                        <?php if ($po['status_po'] === 'OPEN'): ?>
                            <span class="badge bg-success">OPEN</span>
                        <?php elseif ($po['status_po'] === 'CLOSE'): ?>
                            <span class="badge bg-secondary">CLOSE</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">DRAFT</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">- Belum dibuat -</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="section-label">Diskon (Rp)</div>
            <div class="readonly-box">
                <div class="value">Rp <?= number_format($po ? (float)$po['diskon'] : 0, 0, ',', '.') ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="section-label">PPN</div>
            <div class="readonly-box">
                <div class="value">
                    <?php if ($po && (float)$po['ppn_persen'] > 0): ?>
                        <?= (float)$po['ppn_persen'] ?>%
                    <?php else: ?>
                        Tanpa PPN
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="section-label">Grand Total PO (Estimasi)</div>
            <div class="grand-po-box">
                <div class="label">Grand Total PO</div>
                <div class="value">
                    Rp <?= $po ? number_format($po['grand_total'], 0, ',', '.') : '0' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- CATATAN PO - SATU-SATUNYA FIELD YANG BISA DIEDIT           -->
    <!-- ========================================================== -->
    <div>
        <div class="section-label">
            <i class="fas fa-edit text-success me-1"></i>
            Catatan / Ketentuan Pembayaran PO 
            <span class="badge bg-success">EDITABLE</span>
        </div>
        <textarea name="catatan_po" 
                  class="form-control editable-catatan" 
                  rows="4" 
                  placeholder="Masukkan catatan PO atau ketentuan pembayaran..."><?= $po ? htmlspecialchars($po['catatan']) : '' ?></textarea>
        <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Field ini dapat diedit. Perubahan hanya pada catatan PO.
        </small>
    </div>

    <div class="mt-3">
        <div class="section-label">Lampiran Penawaran Supplier</div>
        <div class="readonly-box">
            <div class="value">
                <?php if ($po && !empty($po['file_penawaran'])): ?>
                    <a href="../../uploads/penawaran/<?= htmlspecialchars($po['file_penawaran']) ?>" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-pdf me-1"></i> Lihat PDF
                    </a>
                <?php else: ?>
                    <span class="text-muted">- Tidak ada lampiran -</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- end card-body -->

<!-- FOOTER -->
<div class="card-footer px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="text-muted" style="font-size:.78rem;">
        <i class="fas fa-lock text-warning me-1"></i>
        <strong>Mode Readonly:</strong> Hanya Catatan PO yang dapat diedit
        <span class="badge bg-success ms-2">
            <i class="fas fa-edit me-1"></i>1 Field Editable
        </span>
    </div>
    <div>
        <a href="pr_finance.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-times me-1"></i> Batal
        </a>
        <button type="submit" class="btn fw-bold px-4" style="background:#4c1d95;color:white;">
            <i class="fas fa-save me-1"></i> Simpan Perubahan
        </button>
    </div>
</div>

</div><!-- end card -->
</form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// SweetAlert untuk konfirmasi submit
$(document).ready(function(){
    $('#formEditPRBesarFinance').on('submit', function(e){
        e.preventDefault();
        var form = this;
        
        Swal.fire({
            title: 'Simpan Perubahan?',
            html: 'Hanya <strong>Catatan PO</strong> yang akan diperbarui.<br>Data lain tetap <strong>readonly</strong>.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#4c1d95',
            confirmButtonText: '<i class="fas fa-save me-1"></i> Ya, Simpan!',
            cancelButtonText: 'Batal'
        }).then(function(result){
            if(result.isConfirmed){
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: function(){
                        Swal.showLoading();
                    }
                });
                form.submit();
            }
        });
    });
});

// Idle timer
let idleTime = 0;
const maxIdleMinutes = 15;
let lastServerUpdate = Date.now();
let sessionValid = true;

function resetTimer() {
    idleTime = 0;
    let now = Date.now();
    if (now - lastServerUpdate > 300000) {
        fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    sessionValid = false;
                    forceLogout();
                }
            })
            .catch(err => { console.error("Koneksi ke server terputus"); });
        lastServerUpdate = now;
    }
}

function forceLogout() {
    alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
    window.location.href = "http://192.168.31.200/pr_mcp/auth/logout.php?pesan=timeout";
}

window.onload = resetTimer;
document.onmousemove = resetTimer;
document.onkeypress = resetTimer;
document.onmousedown = resetTimer;
document.onclick = resetTimer;
document.onscroll = resetTimer;

setInterval(function() {
    idleTime++;
    fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                sessionValid = false;
                forceLogout();
            }
        })
        .catch(err => {});
    if (idleTime >= maxIdleMinutes && sessionValid) {
        forceLogout();
    }
}, 60000);
</script>
</body>
</html>