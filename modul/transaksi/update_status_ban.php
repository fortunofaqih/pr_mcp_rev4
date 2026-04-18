<?php
// ============================================================
// update_status_ban.php
// Halaman untuk Petugas Pembelian / Mekanik update:
//   1. Status pembelian item (is_dibeli)
//   2. Status pemasangan ban (status_pasang)
// PO otomatis CLOSE bila semua item dibeli + semua ban terpasang
// ============================================================
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Hanya role pembelian / admin / manager yang boleh
$role_ok = in_array($_SESSION['role'] ?? '', ['bagian_pembelian','admin','manager','superadmin','pemesan_pr_besar']);
if (!$role_ok) {
    header("location:../../login.php?pesan=akses_ditolak");
    exit;
}

$username_login = $_SESSION['username'] ?? '';
$nama_login     = strtoupper($_SESSION['nama'] ?? $username_login);

// ── PROSES POST (update item) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_detail   = (int)($_POST['id_detail']    ?? 0);
    $aksi        = $_POST['aksi']               ?? ''; // 'beli' atau 'pasang'
    $id_po_redir = (int)($_POST['id_po']        ?? 0);
    $id_req_redir= (int)($_POST['id_request']   ?? 0);

    if (!$id_detail || !in_array($aksi, ['beli','pasang'])) {
        header("location:update_status_ban.php?pesan=invalid");
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $today = date('Y-m-d');
    $user_esc = mysqli_real_escape_string($koneksi, $username_login);
    $nama_esc = mysqli_real_escape_string($koneksi, $nama_login);

    mysqli_begin_transaction($koneksi);
    try {
       if ($aksi === 'beli') {
        // 1. CEK DULU: Apakah item ini sudah ada di tabel pembelian?
        // Kita cek berdasarkan id_detail (id_request_detail di tabel pembelian)
        $cek_pembelian = mysqli_query($koneksi, "SELECT id_pembelian FROM pembelian WHERE id_request_detail = '$id_detail' LIMIT 1");
        
        if (mysqli_num_rows($cek_pembelian) == 0) {
            // Jika tidak ada di tabel pembelian, lempar error agar masuk ke catch block
            throw new Exception("Barang belum diinput ke database Pembelian. Silakan input nota pembelian terlebih dahulu.");
        }

        // 2. Jika lolos pengecekan, baru update status tr_request_detail
        $sql = "UPDATE tr_request_detail SET
                    is_dibeli   = 1,
                    tgl_dibeli  = '$today',
                    dibeli_oleh = '$nama_esc'
                WHERE id_detail = '$id_detail' AND is_dibeli = 0";
                
        if (!mysqli_query($koneksi, $sql)) throw new Exception("Gagal update beli: ".mysqli_error($koneksi));

        } elseif ($aksi === 'pasang') {
            // Tandai ban sudah terpasang (hanya jika is_ban=1 dan sudah dibeli)
            $sql = "UPDATE tr_request_detail SET
                        status_pasang = 'TERPASANG',
                        tgl_pasang    = '$today',
                        pasang_oleh   = '$nama_esc'
                    WHERE id_detail = '$id_detail' AND is_ban = 1 AND is_dibeli = 1 AND status_pasang = 'BELUM_TERPASANG'";
            if (!mysqli_query($koneksi, $sql)) throw new Exception("Gagal update pasang: ".mysqli_error($koneksi));
        }

        // ── Cek apakah PO bisa otomatis CLOSE ───────────────
        // Ambil id_request dari detail
        $row_req = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT id_request FROM tr_request_detail WHERE id_detail='$id_detail'"));
        $id_req = (int)($row_req['id_request'] ?? 0);

        if ($id_req) {
            // Cek: semua item sudah dibeli?
            $cek_belum_beli = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT COUNT(*) as jml FROM tr_request_detail
                 WHERE id_request='$id_req' AND is_dibeli = 0"));
            $belum_beli = (int)($cek_belum_beli['jml'] ?? 1);

            // Cek: semua ban sudah terpasang? (ban yang belum terpasang)
            $cek_ban_belum = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT COUNT(*) as jml FROM tr_request_detail
                 WHERE id_request='$id_req' AND is_ban = 1 AND status_pasang = 'BELUM_TERPASANG'"));
            $ban_belum_pasang = (int)($cek_ban_belum['jml'] ?? 0);

            if ($belum_beli === 0 && $ban_belum_pasang === 0) {
                // Semua item dibeli + semua ban terpasang → PO = CLOSE, PR = SELESAI
                mysqli_query($koneksi,
                    "UPDATE tr_purchase_order SET status_po = 'CLOSE' WHERE id_request='$id_req' AND status_po = 'OPEN'");
                mysqli_query($koneksi,
                    "UPDATE tr_request SET status_request = 'SELESAI', updated_by='$user_esc', updated_at='$now'
                     WHERE id_request='$id_req' AND status_request != 'SELESAI'");
            }
        }

        mysqli_commit($koneksi);
        header("location:update_status_ban.php?id_po=$id_po_redir&pesan=berhasil");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        error_log("update_status_ban.php ERROR: " . $e->getMessage());
        header("location:update_status_ban.php?id_po=$id_po_redir&pesan=gagal");
        exit;
    }
}

// ── TAMPIL HALAMAN ───────────────────────────────────────────
$id_po_filter = (int)($_GET['id_po'] ?? 0);
$tab_aktif    = $_GET['tab'] ?? 'open'; // 'open' atau 'close'
if (!in_array($tab_aktif, ['open','close'])) $tab_aktif = 'open';

// Ambil PO OPEN
$result_po_open = mysqli_query($koneksi,
    "SELECT p.*, r.no_request, r.nama_pemesan, s.nama_supplier, 
            u_admin.nama_lengkap as nama_admin_pembuat,
            u_beli.nama_lengkap as nama_staf_pembeli
     FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request = r.id_request
     LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
     LEFT JOIN users u_admin ON r.created_by = u_admin.username
     LEFT JOIN users u_beli ON p.created_by = u_beli.username AND u_beli.role = 'bagian_pembelian'
     WHERE p.status_po = 'OPEN'
     ORDER BY p.tgl_approve DESC");

// Ambil PO CLOSE (histori)
$result_po_close = mysqli_query($koneksi,
    "SELECT p.*, r.no_request, r.nama_pemesan, s.nama_supplier,
            r.updated_at as tgl_close, 
            u_admin.nama_lengkap as nama_admin_pembuat,
            u_beli.nama_lengkap as nama_staf_pembeli
     FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request = r.id_request
     LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
     LEFT JOIN users u_admin ON r.created_by = u_admin.username
     LEFT JOIN users u_beli ON p.created_by = u_beli.username AND u_beli.role = 'bagian_pembelian'
     WHERE p.status_po = 'CLOSE'
     ORDER BY r.updated_at DESC");

$jml_open  = mysqli_num_rows($result_po_open);
$jml_close = mysqli_num_rows($result_po_close);

// Tentukan result yang ditampilkan sesuai tab
$result_po = ($tab_aktif === 'close') ? $result_po_close : $result_po_open;

// Jika ada filter id_po, tampilkan detailnya
$po_detail   = null;
$detail_items = null;
$id_request_sel = 0;

// Bagian detail PO (saat diklik)
if ($id_po_filter) {
    $po_detail = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT p.*, r.no_request, r.nama_pemesan, r.keterangan as tujuan, r.status_request,
                r.updated_at as tgl_close, s.nama_supplier, 
                u_admin.nama_lengkap as nama_admin_pembuat,
                u_beli.nama_lengkap as nama_staf_pembeli
         FROM tr_purchase_order p
         JOIN tr_request r ON p.id_request = r.id_request
         LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
         LEFT JOIN users u_admin ON r.created_by = u_admin.username
         LEFT JOIN users u_beli ON p.created_by = u_beli.username AND u_beli.role = 'bagian_pembelian'
         WHERE p.id_po = '$id_po_filter' LIMIT 1"));

    if ($po_detail) {
        $id_request_sel = (int)$po_detail['id_request'];
        $detail_items = mysqli_query($koneksi,
            "SELECT d.*, b.nama_barang as nama_master, m.plat_nomor
             FROM tr_request_detail d
             LEFT JOIN master_barang b ON d.id_barang = b.id_barang
             LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
             WHERE d.id_request = '$id_request_sel'
             ORDER BY d.id_detail ASC");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Status Pembelian & Pemasangan Ban - MCP</title>
<link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body{background:#f4f6f9;font-size:.875rem;}
.navbar-mcp{background:#1e3a8a;}
.card{border-radius:12px;}
.table thead{background:#2d3748;color:white;font-size:.78rem;text-transform:uppercase;}
.info-label{font-size:.7rem;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;}
.info-value{font-size:.9rem;font-weight:600;color:#1a1a2e;}
.badge-open{background:#dcfce7;color:#166534;border:1px solid #86efac;padding:4px 10px;border-radius:50px;font-size:.72rem;font-weight:700;}
.badge-close{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1;padding:4px 10px;border-radius:50px;font-size:.72rem;font-weight:700;}
.badge-ban-belum{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;padding:3px 8px;border-radius:4px;font-size:.7rem;font-weight:700;}
.badge-ban-sudah{background:#dcfce7;color:#166534;border:1px solid #86efac;padding:3px 8px;border-radius:4px;font-size:.7rem;font-weight:700;}
.badge-dibeli{background:#dbeafe;color:#1e40af;padding:3px 8px;border-radius:4px;font-size:.7rem;font-weight:700;}
.badge-belum-beli{background:#fef3c7;color:#92400e;padding:3px 8px;border-radius:4px;font-size:.7rem;font-weight:700;}
.po-card{cursor:pointer;transition:.15s;border:2px solid transparent;}
.po-card:hover{border-color:#3b82f6;background:#f0f7ff;}
.po-card.active{border-color:#1e3a8a;background:#eff6ff;}
.po-card.close-card:hover{border-color:#94a3b8;background:#f8fafc;}
.po-card.close-card.active{border-color:#64748b;background:#f1f5f9;}
.row-ban{background:#fffbeb;}
/* Tab style */
.tab-po{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:0;}
.tab-po .tab-btn{flex:1;padding:10px 6px;font-size:.78rem;font-weight:700;text-align:center;background:transparent;border:none;cursor:pointer;color:#64748b;border-bottom:3px solid transparent;margin-bottom:-2px;transition:.15s;}
.tab-po .tab-btn.active-open{color:#166534;border-bottom-color:#22c55e;}
.tab-po .tab-btn.active-close{color:#475569;border-bottom-color:#64748b;}
.tab-po .tab-btn:hover{background:#f8fafc;}
.badge-count{display:inline-block;min-width:18px;height:18px;line-height:18px;border-radius:50px;font-size:.65rem;font-weight:700;text-align:center;padding:0 5px;margin-left:4px;}
.bc-open{background:#dcfce7;color:#166534;}
.bc-close{background:#e2e8f0;color:#475569;}
/* Histori item readonly */
.tbl-histori thead{background:#475569;}
.tbl-histori td{font-size:.78rem;}
#searchPO:focus {
    box-shadow: none;
    background-color: #fff !important;
    border: 1px solid #3b82f6 !important;
}
.input-group-text {
    border-radius: 8px 0 0 8px;
}
#searchPO {
    border-radius: 0 8px 8px 0;
}
</style>
</head>
<body>

<nav class="navbar navbar-dark navbar-mcp shadow-sm mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="../../index.php">
            <i class="fas fa-arrow-left me-2"></i> DASHBOARD
        </a>
        <span class="navbar-text text-white">
            <i class="fas fa-user me-1"></i> <strong><?= $nama_login ?></strong>
        </span>
    </div>
</nav>

<div class="container-fluid px-4">

    <?php $pesan = $_GET['pesan'] ?? ''; ?>
    <?php if ($pesan === 'berhasil'): ?>
    <div class="alert alert-success alert-dismissible fade show mb-3">
        <i class="fas fa-check-circle me-2"></i> <strong>Berhasil!</strong> Status berhasil diperbarui.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php elseif ($pesan === 'gagal'): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-3">
        <i class="fas fa-times-circle me-2"></i> Gagal memperbarui status.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Update Status Pembelian & Pemasangan Ban</h4>
            <p class="text-muted small mb-0">Tandai item yang sudah dibeli dan ban yang sudah terpasang. PO otomatis CLOSE bila semua selesai.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-success px-3 py-2"><i class="fas fa-circle me-1" style="font-size:.5rem;"></i>OPEN: <?= $jml_open ?> PO</span>
            <span class="badge bg-secondary px-3 py-2"><i class="fas fa-check-double me-1"></i>CLOSE: <?= $jml_close ?> PO</span>
        </div>
    </div>

    <div class="row g-4">
        <!-- ── KIRI: DAFTAR PO OPEN / CLOSE ─────────────────── -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white p-0">
                   <div class="p-2 border-bottom">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="searchPO" class="form-control border-0 bg-light" placeholder="Cari PO, Supplier, atau Admin...">
                </div>
            </div>
                    <div class="tab-po">
                        <button class="tab-btn <?= $tab_aktif==='open' ? 'active-open' : '' ?>"
                                onclick="window.location.href='update_status_ban.php?tab=open'">
                            <i class="fas fa-circle me-1" style="font-size:.5rem;color:#22c55e;"></i>OPEN
                            <span class="badge-count bc-open"><?= $jml_open ?></span>
                        </button>
                        <button class="tab-btn <?= $tab_aktif==='close' ? 'active-close' : '' ?>"
                                onclick="window.location.href='update_status_ban.php?tab=close'">
                            <i class="fas fa-check-double me-1"></i>CLOSE
                            <span class="badge-count bc-close"><?= $jml_close ?></span>
                        </button>
                    </div>
                </div>
                <div class="card-body p-2" style="max-height:600px;overflow-y:auto;">

                    <?php if ($tab_aktif === 'open'): ?>
                    <?php /* ── Tab OPEN ── */ ?>
                    <?php if ($jml_open > 0):
                        mysqli_data_seek($result_po_open, 0);
                        while ($po_row = mysqli_fetch_assoc($result_po_open)): ?>
                        <div class="po-card p-3 rounded mb-2 <?= $id_po_filter===(int)$po_row['id_po'] ? 'active' : '' ?>"
                            onclick="window.location.href='update_status_ban.php?tab=<?= $tab_aktif ?>&id_po=<?= $po_row['id_po'] ?>'">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-primary" style="font-size:.82rem;"><?= htmlspecialchars($po_row['no_po'] ?? '-') ?></div>
                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($po_row['no_request']) ?></div>
                                </div>
                                <span class="<?= $tab_aktif==='open' ? 'badge-open' : 'badge-close' ?>">
                                    <i class="fas <?= $tab_aktif==='open' ? 'fa-circle' : 'fa-check-double' ?> me-1" style="font-size:.4rem;"></i>
                                    <?= strtoupper($tab_aktif) ?>
                                </span>
                            </div>
                            
                            
                            <div class="text-dark mt-1 fw-bold" style="font-size:.78rem;">
                                <i class="fas fa-truck me-1 opacity-50"></i> <?= htmlspecialchars($po_row['nama_supplier'] ?? '-') ?>
                            </div>

                            <div class="mt-2 pt-2 border-top">
                                <div class="text-muted" style="font-size:.68rem;">
                                    <i class="fas fa-user-edit me-1"></i>  <span class="text-danger fw-bold"> Admin (Requester):</span> <span class="text-dark fw-bold"><?= htmlspecialchars($po_row['nama_admin_pembuat'] ?? '-') ?></span>
                                </div>
                               <!-- <div class="text-muted" style="font-size:.68rem;">
                                    <i class="fas fa-shopping-cart me-1"></i> <span class="text-danger fw-bold"> Pembeli: </span> <span class="text-dark fw-bold"><?= htmlspecialchars($po_row['nama_pembeli'] ?? '-') ?></span>
                                </div>-->
                            </div>

                            <div class="text-muted mt-2 d-flex justify-content-between align-items-center" style="font-size:.72rem;">
                                <span>Rp <?= number_format($po_row['grand_total'],0,',','.') ?></span>
                                <?php if ($tab_aktif === 'close' && !empty($po_row['tgl_close'])): ?>
                                    <span class="text-success italic">Selesai <?= date('d/m/y', strtotime($po_row['tgl_close'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-check-double fa-2x mb-2 d-block opacity-25"></i>
                            Tidak ada PO dengan status OPEN.
                        </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <?php /* ── Tab CLOSE (Histori) ── */ ?>
                    <?php if ($jml_close > 0):
                        mysqli_data_seek($result_po_close, 0);
                        while ($po_row = mysqli_fetch_assoc($result_po_close)): ?>
                        <div class="po-card p-3 rounded mb-2 <?= $id_po_filter===(int)$po_row['id_po'] ? 'active' : '' ?>"
                            onclick="window.location.href='update_status_ban.php?tab=<?= $tab_aktif ?>&id_po=<?= $po_row['id_po'] ?>'">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-bold text-primary" style="font-size:.82rem;"><?= htmlspecialchars($po_row['no_po'] ?? '-') ?></div>
                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($po_row['no_request']) ?></div>
                                </div>
                                <span class="<?= $tab_aktif==='open' ? 'badge-open' : 'badge-close' ?>">
                                    <i class="fas <?= $tab_aktif==='open' ? 'fa-circle' : 'fa-check-double' ?> me-1" style="font-size:.4rem;"></i>
                                    <?= strtoupper($tab_aktif) ?>
                                </span>
                            </div>
                          
                            <div class="text-dark mt-1 fw-bold" style="font-size:.78rem;">
                                <i class="fas fa-truck me-1 opacity-50"></i> <?= htmlspecialchars($po_row['nama_supplier'] ?? '-') ?>
                            </div>

                            <div class="mt-2 pt-2 border-top">
                                <div class="text-muted" style="font-size:.68rem;">
                                    <i class="fas fa-user-edit me-1"></i><span class="text-danger fw-bold">Admin (Requester):</span>  <span class="text-dark fw-bold"><?= htmlspecialchars($po_row['nama_admin_pembuat'] ?? '-') ?></span>
                                </div>
                               <!-- <div class="text-muted" style="font-size:.68rem;">
                                    <i class="fas fa-shopping-cart me-1"></i><span class="text-danger fw-bold"> Pembeli: </span> <span class="text-dark fw-bold"><?= htmlspecialchars($po_row['nama_staf_pembeli'] ?? '-') ?></span>
                                </div>-->
                            </div>

                            <div class="text-muted mt-2 d-flex justify-content-between align-items-center" style="font-size:.72rem;">
                                <span>Rp <?= number_format($po_row['grand_total'],0,',','.') ?></span>
                                <?php if ($tab_aktif === 'close' && !empty($po_row['tgl_close'])): ?>
                                    <span class="text-success italic">Selesai <?= date('d/m/y', strtotime($po_row['tgl_close'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-25"></i>
                            Belum ada PO yang selesai.
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- ── KANAN: DETAIL ITEM PO TERPILIH ───────────── -->
       <div class="col-md-8">
    <?php if ($po_detail && $detail_items): ?>

    <?php $is_close_view = ($po_detail['status_po'] === 'CLOSE'); ?>
    <div class="card shadow-sm border-0 mb-3" style="border:1px solid <?= $is_close_view ? '#cbd5e1' : '#c7d2fe' ?>!important;">
        <div class="card-body py-3">
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div class="info-label">No. PO</div>
                    <div class="info-value <?= $is_close_view ? 'text-secondary' : 'text-primary' ?>"><?= htmlspecialchars($po_detail['no_po']) ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="info-label">No. Request</div>
                    <div class="info-value"><?= htmlspecialchars($po_detail['no_request']) ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="info-label">Admin (Pembuat PR)</div>
                    <div class="info-value text-dark">
                        <i class="fas fa-user-edit me-1 small opacity-50"></i>
                        <?= htmlspecialchars($po_detail['nama_admin_pembuat'] ?? $po_detail['nama_pemesan']) ?>
                    </div>
                </div>
                <!--<div class="col-md-3 col-6">
                    <div class="info-label">Pembeli (Purchasing)</div>
                    <div class="info-value text-dark">
                        <i class="fas fa-shopping-cart me-1 small opacity-50"></i>
                        <?= htmlspecialchars($po_detail['nama_staf_pembeli'] ?? '-') ?>
                    </div>
                </div>-->

                <div class="col-md-3 col-6">
                    <div class="info-label">Supplier</div>
                    <div class="info-value"><?= htmlspecialchars($po_detail['nama_supplier'] ?? '-') ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="info-label">Grand Total</div>
                    <div class="info-value text-danger font-monospace">Rp <?= number_format($po_detail['grand_total'],0,',','.') ?></div>
                </div>
                <div class="col-md-4 col-12">
                    <div class="info-label">Keperluan / Tujuan</div>
                    <div style="font-size:.82rem;" class="fw-semibold"><?= htmlspecialchars($po_detail['tujuan'] ?? '-') ?></div>
                </div>
                
                <div class="col-md-2 col-12 text-md-end">
                    <div class="info-label">Status PO</div>
                    <div class="mt-1">
                        <?php if ($is_close_view): ?>
                            <span class="badge-close"><i class="fas fa-check-double me-1"></i>CLOSE</span>
                        <?php else: ?>
                            <span class="badge-open"><i class="fas fa-circle me-1" style="font-size:.4rem;"></i>OPEN</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

            <!-- Tabel item -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-list <?= $is_close_view ? 'text-secondary' : 'text-primary' ?> me-2"></i>
                        <?= $is_close_view ? 'Histori Item — Readonly' : 'Daftar Item — Klik tombol untuk update status' ?>
                    </div>
                    <?php if ($is_close_view): ?>
                    <span class="badge bg-secondary" style="font-size:.7rem;"><i class="fas fa-lock me-1"></i>SELESAI</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0 <?= $is_close_view ? 'tbl-histori' : '' ?>">
                            <thead>
                                <tr class="text-center">
                                    <th width="4%">#</th>
                                    <th>Nama Barang</th>
                                    <th width="90">Unit/Plat</th>
                                    <th width="80">Qty</th>
                                    <th width="130">Status Barang</th>
                                    <th width="130">Status Ban</th>
                                    <?php if (!$is_close_view): ?>
                                    <th width="140">Aksi</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $no = 1; while ($d = mysqli_fetch_assoc($detail_items)):
                                $nama = !empty($d['nama_master']) ? $d['nama_master'] : $d['nama_barang_manual'];
                                $unit = !empty($d['plat_nomor']) ? $d['plat_nomor'] : '-';
                                $is_ban    = (int)$d['is_ban'];
                                $is_dibeli = (int)$d['is_dibeli'];
                                $status_pasang = $d['status_pasang'] ?? null;
                                $id_det = $d['id_detail']; // Ambil ID Detail
                                // Cek apakah item ini sudah diinput di nota pembelian
                                $q_cek_nota = mysqli_query($koneksi, "SELECT id_pembelian FROM pembelian WHERE id_request_detail = '$id_det' LIMIT 1");
                                $sudah_ada_nota = (mysqli_num_rows($q_cek_nota) > 0);
                            ?>
                            <tr class="<?= $is_ban ? 'row-ban' : '' ?>">
                                <td class="text-center text-muted"><?= $no++ ?></td>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars(strtoupper($nama)) ?></span>
                                    <?php if ($is_ban): ?>
                                    <span class="ms-1" style="font-size:.65rem;background:#fff3cd;border:1px solid #ffc107;color:#7c4a00;padding:1px 5px;border-radius:4px;font-weight:700;">
                                        <i class="fas fa-tire me-1"></i>BAN
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($d['kwalifikasi']): ?>
                                    <div class="text-muted" style="font-size:.72rem;"><?= htmlspecialchars($d['kwalifikasi']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?= $unit !== '-' ? '<span class="badge bg-light text-dark border" style="font-size:.72rem;">'.$unit.'</span>' : '<span class="text-muted">-</span>' ?>
                                </td>
                                <td class="text-center fw-bold"><?= (float)$d['jumlah']+0 ?> <?= $d['satuan'] ?></td>
                                <td class="text-center">
                                    <?php if ($is_dibeli): ?>
                                        <div><span class="badge-dibeli"><i class="fas fa-check me-1"></i>CLOSE</span></div>
                                        <div class="text-muted mt-1" style="font-size:.68rem;">
                                            <?= $d['tgl_dibeli'] ? date('d/m/Y', strtotime($d['tgl_dibeli'])) : '' ?>
                                            <?= $d['dibeli_oleh'] ? '<br>'.$d['dibeli_oleh'] : '' ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge-belum-beli"><i class="fas fa-clock me-1"></i>OPEN</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($is_ban): ?>
                                        <?php if ($status_pasang === 'TERPASANG'): ?>
                                            <div><span class="badge-ban-sudah"><i class="fas fa-check me-1"></i>TERPASANG</span></div>
                                            <div class="text-muted mt-1" style="font-size:.68rem;">
                                                <?= $d['tgl_pasang'] ? date('d/m/Y', strtotime($d['tgl_pasang'])) : '' ?>
                                                <?= $d['pasang_oleh'] ? '<br>'.$d['pasang_oleh'] : '' ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-ban-belum"><i class="fas fa-clock me-1"></i>BELUM PASANG</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (!$is_close_view): ?>
                                <td class="text-center">
                                    <div class="d-flex flex-column gap-1">
                                    <?php if (!$is_dibeli): ?>
                                    <?php if ($sudah_ada_nota): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return konfirmBeli(event, '<?= htmlspecialchars(strtoupper($nama), ENT_QUOTES) ?>')">
                                            <input type="hidden" name="id_detail"  value="<?= $d['id_detail'] ?>">
                                            <input type="hidden" name="id_po"      value="<?= $id_po_filter ?>">
                                            <input type="hidden" name="id_request" value="<?= $id_request_sel ?>">
                                            <input type="hidden" name="aksi"       value="beli">
                                            <button type="submit" class="btn btn-sm btn-success fw-bold px-2" style="font-size:.72rem;">
                                                <i class="fas fa-shopping-bag me-1"></i>DONE
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <div class="p-1 border rounded bg-light">
                                            <span class="text-danger fw-bold" style="font-size:.65rem;">
                                                <i class="fas fa-exclamation-triangle me-1"></i>NOTA BELUM DIINPUT
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                    <?php if ($is_ban && $is_dibeli && $status_pasang === 'BELUM_TERPASANG'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return konfirmPasang(event, '<?= htmlspecialchars(strtoupper($nama), ENT_QUOTES) ?>', '<?= htmlspecialchars($unit, ENT_QUOTES) ?>')">
                                            <input type="hidden" name="id_detail"  value="<?= $d['id_detail'] ?>">
                                            <input type="hidden" name="id_po"      value="<?= $id_po_filter ?>">
                                            <input type="hidden" name="id_request" value="<?= $id_request_sel ?>">
                                            <input type="hidden" name="aksi"       value="pasang">
                                            <button type="submit" class="btn btn-sm btn-warning fw-bold px-2" style="font-size:.72rem;">
                                                <i class="fas fa-tire me-1"></i>Tandai Terpasang
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($is_dibeli && (!$is_ban || $status_pasang === 'TERPASANG')): ?>
                                        <span class="text-success" style="font-size:.72rem;"><i class="fas fa-check-circle me-1"></i>Selesai</span>
                                    <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <div class="card shadow-sm border-0 d-flex align-items-center justify-content-center" style="min-height:300px;border-radius:12px;">
                <div class="text-center text-muted py-5">
                    <i class="fas fa-hand-point-left fa-3x mb-3 d-block opacity-25"></i>
                    <strong>Pilih PO dari daftar sebelah kiri</strong><br>
                    <small>untuk melihat detail item</small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function konfirmBeli(e, nama) {
    e.preventDefault();
    var form = e.target;
    Swal.fire({
        title: 'Tandai sudah dibeli?',
        html: 'Item: <strong>' + nama + '</strong><br><small class="text-muted">Tindakan ini tidak bisa dibatalkan.</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: '<i class="fas fa-check me-1"></i> Ya, Sudah Dibeli',
        cancelButtonText: 'Batal'
    }).then(function(r){
        if(r.isConfirmed){
            Swal.fire({title:'Menyimpan...',allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
            form.submit();
        }
    });
    return false;
}

function konfirmPasang(e, nama, plat) {
    e.preventDefault();
    var form = e.target;
    Swal.fire({
        title: 'Tandai ban sudah terpasang?',
        html: 'Ban: <strong>' + nama + '</strong><br>Kendaraan: <strong>' + plat + '</strong><br><small class="text-muted">Pastikan ban sudah benar-benar terpasang pada kendaraan.</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: '<i class="fas fa-tire me-1"></i> Ya, Sudah Terpasang',
        cancelButtonText: 'Batal'
    }).then(function(r){
        if(r.isConfirmed){
            Swal.fire({title:'Menyimpan...',allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
            form.submit();
        }
    });
    return false;
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchPO');
    
    searchInput.addEventListener('keyup', function() {
        const filter = searchInput.value.toLowerCase();
        // Mengambil semua card PO yang ada di container (baik di tab open maupun close)
        const cards = document.querySelectorAll('.po-card');

        cards.forEach(card => {
            // Kita ambil semua teks di dalam card agar pencarian fleksibel
            const text = card.textContent.toLowerCase();
            
            if (text.includes(filter)) {
                card.style.display = ""; // Tampilkan
            } else {
                card.style.display = "none"; // Sembunyikan
            }
        });
    });
});
</script>
</body>
</html>