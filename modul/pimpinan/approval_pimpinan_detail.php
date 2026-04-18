<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login" || $_SESSION['role'] != 'manager') {
    header("location:../../login.php?pesan=bukan_pimpinan");
    exit;
}

$id            = (int)($_GET['id'] ?? 0);
$username_saya = $_SESSION['username'] ?? '';
$nama_manager  = strtoupper($_SESSION['nama'] ?? $username_saya);

if (!$id) { header("location:approval_pimpinan.php"); exit; }

$pr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request='$id'"));
if (!$pr) { header("location:approval_pimpinan.php?pesan=tidak_ditemukan"); exit; }

$sudah_approve_saya = (
    ($pr['approve1_by'] === $username_saya) ||
    ($pr['approve2_by'] === $username_saya) ||
    ($pr['approve3_by'] === $username_saya)
);

$status_app = $pr['status_approval'];
$need_m3    = (int)$pr['need_approve3'];

$giliran_m1 = ($status_app === 'MENUNGGU APPROVAL');
$giliran_m2 = ($status_app === 'APPROVED 1') && ($pr['approve1_by'] !== $username_saya);
$giliran_m3 = ($status_app === 'APPROVED 2') && $need_m3 && ($pr['approve3_target'] === $username_saya) && empty($pr['approve3_by']);

$bisa_diaksi = ($giliran_m1 || $giliran_m2 || $giliran_m3) && !$sudah_approve_saya;

$list_manager_m3 = [];
if ($giliran_m2) {
    $res_m = mysqli_query($koneksi,
        "SELECT username, nama_lengkap FROM users
         WHERE role='manager' AND status_aktif='AKTIF'
         AND username != '$username_saya'
         AND username != '".$pr['approve1_by']."'
         ORDER BY nama_lengkap ASC");
    while ($m = mysqli_fetch_assoc($res_m)) {
        $list_manager_m3[] = $m;
    }
}

$details = mysqli_query($koneksi,
    "SELECT d.*, b.nama_barang as nama_master, m.plat_nomor
     FROM tr_request_detail d
     LEFT JOIN master_barang b ON d.id_barang = b.id_barang
     LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
     WHERE d.id_request = '$id'
     ORDER BY d.id_detail ASC");

$cek_ban = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) as jml FROM tr_request_detail WHERE id_request='$id' AND is_ban=1"));
$ada_ban = (int)($cek_ban['jml'] ?? 0) > 0;

$row_total   = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT SUM(subtotal_estimasi) as total FROM tr_request_detail WHERE id_request='$id'"));
$grand_total = (float)($row_total['total'] ?? 0);

$po = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT p.*, s.nama_supplier, s.alamat, s.kota, s.telp, s.atas_nama
     FROM tr_purchase_order p
     LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
     WHERE p.id_request = '$id' LIMIT 1"));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review PR <?= htmlspecialchars($pr['no_request']) ?> - MCP</title>
<link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');
    body{background:#f1f5f9; font-family:'Inter', sans-serif; font-size:.875rem; color:#334155;}
    .navbar-mcp{background: linear-gradient(135deg, #1e3a8a, #2563eb); padding: 0.8rem 0;}
    
    .info-header{background:white; border-radius:16px; box-shadow:0 4px 6px -1px rgba(0,0,0,.05); padding:20px; margin-bottom:15px; border: 1px solid #e2e8f0;}
    .info-label{font-size:.65rem; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:.05em; margin-bottom:3px;}
    .info-value{font-size:.9rem; font-weight:700; color:#1e293b;}
    
    /* Timeline Responsive */
    .timeline-container { overflow-x: auto; padding: 10px 0; -webkit-overflow-scrolling: touch; }
    .approval-timeline{ display: flex; align-items: flex-start; gap: 0; min-width: 480px; margin-bottom: 0; }
    .apv-step{flex:1; text-align:center; position:relative;}
    .apv-step::after{content:''; position:absolute; top:17px; left:50%; width:100%; height:2px; background:#e2e8f0; z-index:0;}
    .apv-step:last-child::after{display:none;}
    .apv-circle{width:34px; height:34px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:.8rem; position:relative; z-index:1; background:white; border:2px solid #e2e8f0;}
    .apv-done{background:#10b981 !important; color:white; border-color:#10b981 !important;}
    .apv-active{background:#ffc107 !important; color:#000; border-color:#ffc107 !important; animation:pulse 1.2s infinite alternate;}
    .apv-todo{background:#f1f5f9; color:#94a3b8;}
    .apv-optional{background:#ede9fe; color:#5b21b6; border:2px dashed #c4b5fd;}
    @keyframes pulse{ from{box-shadow:0 0 0 0 rgba(255,193,7,.4);} to{box-shadow:0 0 0 8px rgba(255,193,7,0);}}
    .apv-label{font-size:.65rem; margin-top:6px; font-weight:700; line-height: 1.2;}

    /* Table Optimization */
    .table-detail thead { background: #1e293b; color: white; }
    .table-detail th { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; padding: 12px 10px !important; }

    /* Action & Badges */
    .btn-approve{background:#10b981; color:white; border:none; padding:12px 24px; font-size:.85rem; font-weight:700; border-radius:10px; transition: 0.2s;}
    .btn-reject{background:#ef4444; color:white; border:none; padding:12px 24px; font-size:.85rem; font-weight:700; border-radius:10px; transition: 0.2s;}
    .btn-approve:hover, .btn-reject:hover { opacity: 0.9; transform: translateY(-1px); color: white; }
    .action-box{background:white; border-radius:16px; box-shadow:0 10px 15px -3px rgba(0,0,0,.1); padding:24px; border: 1px solid #e2e8f0;}
    
    .ban-badge-item{display:inline-block; background:#fff3cd; border:1px solid #ffc107; color:#7c4a00; font-size:.62rem; font-weight:700; padding:2px 6px; border-radius:4px;}
    .m3-select-box{background:#f0f7ff; border:1px solid #bae6fd; border-radius:12px; padding:16px;}

    @media (max-width: 768px) {
        .info-header { padding: 15px; }
        /* Tabel jadi Card di Mobile */
        .table-detail thead { display: none; }
        .table-detail tbody tr { display: block; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px; padding: 10px; background: white; }
        .table-detail td { display: flex; justify-content: space-between; align-items: center; border: none !important; padding: 6px 5px !important; text-align: right; }
        .table-detail td::before { content: attr(data-label); font-weight: 700; text-transform: uppercase; font-size: .65rem; color: #64748b; text-align: left; }
        .table-detail tfoot tr { display: flex; flex-direction: column; padding: 15px; background: #f8fafc; border-radius: 12px; }
        .table-detail tfoot td { border: none !important; display: flex; justify-content: space-between; }
        .btn-approve, .btn-reject { width: 100%; }
        .navbar-text { display: none; }
    }
	/* Banner Penawaran PDF */
.banner-penawaran {
    display: flex;
    align-items: center;
    gap: 14px;
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1.5px solid #86efac;
    border-left: 5px solid #16a34a;
    border-radius: 12px;
    padding: 14px 18px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(22,163,74,.08);
}
	.banner-penawaran .icon-pdf {
		font-size: 2rem;
		color: #dc2626;
		flex-shrink: 0;
		background: white;
		width: 44px; height: 44px;
		border-radius: 10px;
		display: flex; align-items: center; justify-content: center;
		box-shadow: 0 2px 6px rgba(0,0,0,.1);
	}
	.banner-penawaran .info-wrap {
		flex: 1;
		min-width: 0;
	}
	.banner-penawaran .label {
		font-size: .68rem;
		font-weight: 800;
		color: #166534;
		text-transform: uppercase;
		letter-spacing: .06em;
		margin-bottom: 3px;
	}
	.banner-penawaran .filename {
		font-size: .82rem;
		color: #1e293b;
		font-weight: 700;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: 100%;
	}
	.banner-penawaran .btn-buka-pdf {
		flex-shrink: 0;
		margin-left: auto;
		display: inline-flex;
		align-items: center;
		gap: 6px;
		background: #16a34a;
		color: #fff;
		border-radius: 8px;
		padding: 8px 16px;
		font-size: .75rem;
		font-weight: 700;
		text-decoration: none;
		white-space: nowrap;
		transition: .2s;
		box-shadow: 0 2px 6px rgba(22,163,74,.3);
	}
	.banner-penawaran .btn-buka-pdf:hover {
		background: #15803d;
		color: white;
		transform: translateY(-1px);
	}
	@media (max-width: 576px) {
		.banner-penawaran { flex-wrap: wrap; }
		.banner-penawaran .btn-buka-pdf { width: 100%; justify-content: center; margin-left: 0; }
	}
</style>
</head>
<body>

<nav class="navbar navbar-dark navbar-mcp shadow-sm mb-4">
    <div class="container-fluid px-3 px-md-4">
        <a class="navbar-brand fw-bold" href="approval_pimpinan.php" style="font-size: .9rem;">
            <i class="fas fa-arrow-left me-2"></i> KEMBALI
        </a>
        <span class="navbar-text text-white small">
            <i class="fas fa-user-circle me-1"></i> <?= $nama_manager ?>
        </span>
    </div>
</nav>

<div class="container-fluid px-3 px-md-4">

    <?php if ($giliran_m1): ?>
    <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center gap-3 mb-3 py-3">
        <i class="fas fa-hourglass-half fs-4"></i>
        <div class="small"><strong>Review Dibutuhkan.</strong> Anda adalah Manager pertama untuk PR ini.</div>
    </div>
    <?php elseif ($giliran_m2): ?>
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-3 mb-3 py-3">
        <i class="fas fa-user-check fs-4"></i>
        <div class="small">
            <strong>Approval 1 Selesai</strong> oleh <?= htmlspecialchars($pr['approve1_by']) ?>.
            <br>Dibutuhkan persetujuan Anda sebagai <strong>Manager ke-2</strong>.
        </div>
    </div>
    <?php elseif ($giliran_m3): ?>
    <div class="alert border-0 shadow-sm d-flex align-items-center gap-3 mb-3 py-3" style="background:#f5f3ff; color:#5b21b6;">
        <i class="fas fa-flag-checkered fs-4"></i>
        <div class="small"><strong>Final Review.</strong> Anda ditunjuk sebagai Manager ke-3 oleh <?= htmlspecialchars($pr['approve2_by']) ?>.</div>
    </div>
    <?php endif; ?>
	<?php if (!empty($pr['file_penawaran'])): ?>
		<div class="banner-penawaran">
			<div class="icon-pdf"><i class="fas fa-file-pdf"></i></div>
			<div class="info-wrap">
				<div class="label"><i class="fas fa-paperclip me-1"></i>Lampiran Penawaran Supplier</div>
				<div class="filename" title="<?= htmlspecialchars($pr['file_penawaran']) ?>">
					<?= htmlspecialchars($pr['file_penawaran']) ?>
				</div>
			</div>
			<a href="../../download_penawaran.php?file=<?= urlencode($pr['file_penawaran']) ?>&id_request=<?= $id ?>"
			   target="_blank"
			   class="btn-buka-pdf">
				<i class="fas fa-eye"></i> Buka PDF
			</a>
		</div>
		<?php endif; ?>

    <div class="info-header">
        <div class="row g-3">
            <div class="col-md-8">
                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <div class="info-label">No. Request</div>
                        <div class="info-value text-primary"><?= htmlspecialchars($pr['no_request']) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="info-label">Tanggal</div>
                        <div class="info-value"><?= date('d/m/Y', strtotime($pr['tgl_request'])) ?></div>
                    </div>
                    <div class="col-12 col-md-5">
                        <div class="info-label">Dibuat Oleh</div>
                        <div class="info-value text-uppercase"><?= htmlspecialchars($pr['nama_pemesan']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="info-label">Keperluan / Keterangan</div>
                        <div class="info-value fw-normal"><?= nl2br(htmlspecialchars($pr['keterangan'] ?: '-')) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 border-start d-none d-md-block">
                <div class="info-label mb-3 text-center">Approval Progress</div>
                <div class="timeline-container">
                    <div class="approval-timeline">
                        <div class="apv-step">
                            <div class="apv-circle <?= in_array($status_app, ['APPROVED 1','APPROVED 2','APPROVED']) ? 'apv-done' : ($status_app === 'MENUNGGU APPROVAL' ? 'apv-active' : 'apv-todo') ?>"><?= in_array($status_app, ['APPROVED 1','APPROVED 2','APPROVED']) ? '<i class="fas fa-check"></i>' : '1' ?></div>
                            <div class="apv-label"><?= $pr['approve1_by'] ? '<span class="text-success">'.htmlspecialchars($pr['approve1_by']).'</span>' : 'M1' ?></div>
                        </div>
                        <div class="apv-step">
                            <div class="apv-circle <?= in_array($status_app, ['APPROVED 2','APPROVED']) ? 'apv-done' : ($status_app === 'APPROVED 1' ? 'apv-active' : 'apv-todo') ?>"><?= in_array($status_app, ['APPROVED 2','APPROVED']) ? '<i class="fas fa-check"></i>' : '2' ?></div>
                            <div class="apv-label"><?= $pr['approve2_by'] ? '<span class="text-success">'.htmlspecialchars($pr['approve2_by']).'</span>' : 'M2' ?></div>
                        </div>
                        <div class="apv-step">
                            <div class="apv-circle <?= ($status_app === 'APPROVED' && $need_m3) ? 'apv-done' : ($status_app === 'APPROVED 2' ? 'apv-active' : ($need_m3 ? 'apv-optional' : 'apv-todo')) ?>"><?= ($status_app === 'APPROVED') ? '<i class="fas fa-check"></i>' : '3' ?></div>
                            <div class="apv-label"><?= $pr['approve3_by'] ? htmlspecialchars($pr['approve3_by']) : ($need_m3 ? 'M3' : 'Ops') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4 overflow-hidden" style="border-radius:16px;">
        <div class="card-header bg-white py-3 fw-bold border-bottom d-flex align-items-center justify-content-between">
            <span><i class="fas fa-box me-2 text-primary"></i>Rincian Barang</span>
            <?php if($ada_ban): ?><span class="ban-badge-item"><i class="fas fa-tire me-1"></i>ADA BAN</span><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-detail">
                <thead>
                    <tr class="text-center">
                        <th>Nama Barang</th>
                        <th>Unit/Mobil</th>
                        <th>Tipe</th>
                        <th>Qty</th>
                        <th class="text-end">Harga</th>
                        <th class="text-end">Subtotal</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($d = mysqli_fetch_assoc($details)): 
                        $nama = !empty($d['nama_master']) ? $d['nama_master'] : $d['nama_barang_manual'];
                    ?>
                    <tr>
                        <td data-label="Barang">
                            <div class="fw-bold"><?= htmlspecialchars(strtoupper($nama)) ?></div>
                            <?php if($d['is_ban']): ?><span class="ban-badge-item mt-1">BAN</span><?php endif; ?>
                        </td>
                        <td data-label="Unit" class="text-center"><?= $d['plat_nomor'] ?: '-' ?></td>
                        <td data-label="Tipe" class="text-center small"><?= $d['tipe_request'] ?></td>
                        <td data-label="Qty" class="text-center fw-bold"><?= (float)$d['jumlah']+0 ?> <?= $d['satuan'] ?></td>
                        <td data-label="Harga" class="text-end">Rp <?= number_format($d['harga_satuan_estimasi'],0,',','.') ?></td>
                        <td data-label="Subtotal" class="text-end fw-bold text-primary">Rp <?= number_format($d['subtotal_estimasi'],0,',','.') ?></td>
                        <td data-label="Ket" class="small"><?= htmlspecialchars($d['keterangan'] ?: '-') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-end d-none d-md-table-cell fw-bold">TOTAL ESTIMASI</td>
                        <td class="text-end fw-bold text-danger fs-5" data-label="Grand Total">Rp <?= number_format($grand_total,0,',','.') ?></td>
                        <td class="d-none d-md-table-cell"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php if ($po): ?>
<div class="card shadow-sm border-0 mb-4" style="border-radius:16px; border:1px solid #bae6fd;">
    <div class="card-header fw-bold py-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background:#f0f9ff; color:#0369a1; border-radius:16px 16px 0 0;">
        <div>
            <i class="fas fa-file-invoice me-2"></i> Preview Purchase Order (PO)
            <span class="badge ms-2 fw-normal" style="font-size:.7rem; background:<?= $po['status_po']==='OPEN'?'#dcfce7;color:#166534':($po['status_po']==='CLOSE'?'#f1f5f9;color:#475569':'#fef9c3;color:#854d0e') ?>;">
                <?= $po['status_po'] ?>
            </span>
        </div>
        <span class="fw-normal small">No. PO: <strong><?= htmlspecialchars($po['no_po']) ?></strong></span>
    </div>
    <div class="card-body p-4">
        <div class="row g-4 mb-4">
            <div class="col-6 col-md-4">
                <div class="info-label">Supplier</div>
                <div class="info-value text-primary"><?= strtoupper(htmlspecialchars($po['nama_supplier'] ?? '-')) ?></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="info-label">Tanggal PO</div>
                <div class="info-value"><?= date('d/m/Y', strtotime($po['tgl_po'])) ?></div>
            </div>
            <div class="col-12 col-md-5">
                <div class="info-label">U/P (Atas Nama)</div>
                <div class="info-value"><?= htmlspecialchars($po['atas_nama'] ?: '-') ?></div>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="info-label">Subtotal</div>
                    <div class="fw-bold">Rp <?= number_format($po['subtotal'], 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="info-label">Diskon</div>
                    <div class="fw-bold text-danger"><?= $po['diskon'] > 0 ? '(Rp '.number_format($po['diskon'], 0, ',', '.').')' : '-' ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 bg-light rounded-3 text-center border">
                    <div class="info-label">PPN (<?= (float)$po['ppn_persen'] ?>%)</div>
                    <div class="fw-bold text-dark">Rp <?= number_format($po['ppn_nominal'], 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 rounded-3 text-center text-white shadow-sm" style="background:#1e3a8a;">
                    <div class="info-label text-white-50">Grand Total</div>
                    <div class="fw-bold fs-6">Rp <?= number_format($po['grand_total'], 0, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <?php if ($po['catatan']): ?>
        <div class="mt-3 p-3 bg-light rounded-3 small border-start border-4 border-primary">
            <strong>Catatan PO:</strong><br><?= nl2br(htmlspecialchars($po['catatan'])) ?>
        </div>
        <?php endif; ?>

        <?php if ($status_app === 'APPROVED'): ?>
        <div class="mt-3">
            <a href="cetak_po.php?id_po=<?= $po['id_po'] ?>" target="_blank" class="btn btn-outline-primary btn-sm fw-bold rounded-pill px-3">
                <i class="fas fa-print me-1"></i> Cetak Dokumen PO
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

    <?php if ($bisa_diaksi): ?>
    <div class="action-box mb-5">
        <h6 class="fw-bold mb-3"><i class="fas fa-pen-nib me-2 text-primary"></i>Form Persetujuan</h6>
        
        <?php if ($giliran_m2): ?>
        <div class="m3-select-box mb-4">
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="chkNeedM3" onchange="toggleM3Select()">
                <label class="form-check-label fw-bold small" for="chkNeedM3">TAMBAHKAN MANAGER KE-3 (FINAL APPROVAL)?</label>
            </div>
            <div id="m3SelectWrap" style="display:none;" class="mt-3">
                <select id="selectM3" class="form-select border-primary">
                    <option value="">-- Pilih Manager --</option>
                    <?php foreach ($list_manager_m3 as $m3): ?>
                    <option value="<?= htmlspecialchars($m3['username']) ?>"><?= strtoupper(htmlspecialchars($m3['nama_lengkap'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <button class="btn-approve w-100" onclick="approvePR(<?= $id ?>, '<?= $pr['no_request'] ?>')">
                    <i class="fas fa-check-circle me-2"></i> 
                    <?= $giliran_m3 ? 'APPROVE SEKARANG (FINAL)' : 'SETUJUI & LANJUTKAN' ?>
                </button>
            </div>
            <div class="col-md-6">
                <button class="btn-reject w-100" onclick="rejectPR(<?= $id ?>, '<?= $pr['no_request'] ?>')">
                    <i class="fas fa-times-circle me-2"></i> TOLAK PERMINTAAN
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
var IS_GILIRAN_M2 = <?= $giliran_m2 ? 'true' : 'false' ?>;
var IS_GILIRAN_M3 = <?= $giliran_m3 ? 'true' : 'false' ?>;

function toggleM3Select(){
    var show = document.getElementById('chkNeedM3').checked;
    document.getElementById('m3SelectWrap').style.display = show ? 'block' : 'none';
}

function approvePR(id, no) {
    var needM3 = IS_GILIRAN_M2 && document.getElementById('chkNeedM3').checked;
    var m3Target = needM3 ? document.getElementById('selectM3').value : '';

    if(needM3 && !m3Target){
        Swal.fire('Pilih Manager','Silakan pilih nama Manager ke-3 terlebih dahulu.','warning');
        return;
    }

    Swal.fire({
        title: 'Konfirmasi Approval',
        text: 'Apakah Anda yakin menyetujui PR ' + no + '?',
        icon: 'question',
        input: 'textarea',
        inputPlaceholder: 'Tulis catatan Anda di sini (opsional)...',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({title:'Memproses...', didOpen:()=>{Swal.showLoading()}});
            window.location.href = 'proses_approval_besar.php?action=approve&id=' + id 
                                    + '&catatan=' + encodeURIComponent(result.value || '')
                                    + '&need_m3=' + (needM3 ? '1' : '0')
                                    + '&m3_target=' + encodeURIComponent(m3Target);
        }
    });
}

function rejectPR(id, no) {
    Swal.fire({
        title: 'Tolak Permintaan?',
        text: 'PR ' + no + ' akan dibatalkan dan tim pembelian tidak bisa memprosesnya.',
        icon: 'warning',
        input: 'textarea',
        inputPlaceholder: 'Wajib isi alasan penolakan...',
        inputValidator: (value) => { if (!value) return 'Alasan penolakan wajib diisi!' },
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Ya, Tolak PR'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'proses_approval_besar.php?action=reject&id=' + id + '&catatan=' + encodeURIComponent(result.value);
        }
    });
}
</script>
</body>
</html>