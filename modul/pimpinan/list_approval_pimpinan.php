<?php
// list_approval_pimpinan.php
// Halaman daftar PR yang sudah di-approve & tombol void/revert
// Dilengkapi: Search, Filter, Pagination
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login" || $_SESSION['role'] != 'manager') {
    header("location:../../login.php?pesan=bukan_pimpinan");
    exit;
}

$nama_manager  = strtoupper($_SESSION['nama'] ?? $_SESSION['username'] ?? 'MANAGER');
$username_saya = mysqli_real_escape_string($koneksi, $_SESSION['username'] ?? '');

// ============================================================
// PARAMETER SEARCH, FILTER, PAGINATION
// ============================================================
$search       = trim($_GET['search']    ?? '');
$filter_kat   = $_GET['kategori']       ?? '';      // '', 'BESAR', 'IT'
$filter_stat  = $_GET['status']         ?? '';      // '', 'APPROVED 1', 'APPROVED 2', 'APPROVED'
$tgl_dari     = $_GET['tgl_dari']       ?? '';
$tgl_sampai   = $_GET['tgl_sampai']     ?? '';
$sort_by      = $_GET['sort']           ?? 'tgl_request';
$sort_order   = strtoupper($_GET['order'] ?? 'DESC');
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = (int)($_GET['per_page'] ?? 10);

// Validasi sort
$allowed_sort = ['tgl_request', 'no_request', 'nama_pemesan', 'status_approval'];
if (!in_array($sort_by, $allowed_sort)) $sort_by = 'tgl_request';
if (!in_array($sort_order, ['ASC', 'DESC'])) $sort_order = 'DESC';

// Validasi per_page
if (!in_array($per_page, [10, 25, 50, 100])) $per_page = 10;

$offset = ($page - 1) * $per_page;

// ============================================================
// BUILD WHERE CLAUSE
// ============================================================
$where = "WHERE kategori_pr IN ('BESAR', 'IT')
          AND status_request NOT IN ('BATAL','SELESAI')
          AND (void_by IS NULL OR void_by = '')
          AND (
              status_approval IN ('APPROVED 1','APPROVED 2','APPROVED')
              OR approve1_by IS NOT NULL
              OR approve2_by IS NOT NULL
              OR approve3_by IS NOT NULL
          )";

// Search: no_request / nama_pemesan / keterangan
if ($search !== '') {
    $search_esc = mysqli_real_escape_string($koneksi, $search);
    $where .= " AND (no_request LIKE '%$search_esc%' 
                  OR nama_pemesan LIKE '%$search_esc%' 
                  OR keterangan LIKE '%$search_esc%')";
}

// Filter kategori
if (in_array($filter_kat, ['BESAR', 'IT'])) {
    $where .= " AND kategori_pr = '$filter_kat'";
}

// Filter status approval
if (in_array($filter_stat, ['APPROVED 1', 'APPROVED 2', 'APPROVED'])) {
    $where .= " AND status_approval = '$filter_stat'";
}

// Filter tanggal
if ($tgl_dari !== '') {
    $tgl_dari_esc = mysqli_real_escape_string($koneksi, $tgl_dari);
    $where .= " AND DATE(tgl_request) >= '$tgl_dari_esc'";
}
if ($tgl_sampai !== '') {
    $tgl_sampai_esc = mysqli_real_escape_string($koneksi, $tgl_sampai);
    $where .= " AND DATE(tgl_request) <= '$tgl_sampai_esc'";
}

// ============================================================
// HITUNG TOTAL DATA
// ============================================================
$count_sql = "SELECT COUNT(*) AS total FROM tr_request $where";
$count_res = mysqli_query($koneksi, $count_sql);
$total_data = (int)(mysqli_fetch_assoc($count_res)['total'] ?? 0);
$total_page = max(1, (int)ceil($total_data / $per_page));

// Pastikan halaman tidak melebihi total
if ($page > $total_page) $page = $total_page;
$offset = ($page - 1) * $per_page;

// ============================================================
// QUERY UTAMA dengan LIMIT
// ============================================================
$sql = "SELECT * FROM tr_request
        $where
        ORDER BY $sort_by $sort_order
        LIMIT $per_page OFFSET $offset";

$query = mysqli_query($koneksi, $sql);

// Simpan hasil ke array agar bisa dipakai berulang
$rows = [];
while ($r = mysqli_fetch_assoc($query)) {
    $rows[] = $r;
}

// ============================================================
// BUILD QUERY STRING untuk pagination (mempertahankan filter)
// ============================================================
$query_params = $_GET;
unset($query_params['page']);
$base_qs = http_build_query($query_params);
$base_url = '?' . ($base_qs ? $base_qs . '&' : '');

// Helper: escape + aman untuk output
function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Approval PR - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        :root {
            --mcp-blue: #1e3a8a;
            --mcp-accent: #3b82f6;
            --bg-light: #f8fafc;
        }

        body {
            background: var(--bg-light);
            font-family: 'Inter', sans-serif;
            color: #334155;
            font-size: 0.875rem;
        }

        .navbar-mcp {
            background: linear-gradient(135deg, var(--mcp-blue), #2563eb);
            padding: 1rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .card-main {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
            overflow: hidden;
            background: white;
        }

        /* ── Filter Bar ───────────────────────── */
        .filter-card {
            background: white;
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
            margin-bottom: 16px;
        }
        .filter-card label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 4px;
        }
        .filter-card .form-control,
        .filter-card .form-select {
            font-size: 0.8rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }
        .filter-card .form-control:focus,
        .filter-card .form-select:focus {
            border-color: var(--mcp-accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }

        /* ── Table ───────────────────────────── */
        .table thead { background: #f1f5f9; border-bottom: 2px solid #e2e8f0; }
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 14px 12px;
            border: none;
            white-space: nowrap;
        }
        .table thead th a {
            color: #64748b;
            text-decoration: none;
        }
        .table thead th a:hover {
            color: var(--mcp-accent);
        }

        .badge-status { padding: 6px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; border: 1px solid transparent; }
        .badge-approved1 { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .badge-approved2 { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }
        .badge-approved  { background: #dcfce7; color: #166534; border-color: #86efac; }

        /* Badge kategori PR */
        .badge-cat-besar { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-cat-it    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }

        .step-dot { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; }
        .step-done     { background: #10b981; color: white; }
        .step-active   { background: #f59e0b; color: white; animation: pulse 2s infinite; }
        .step-todo     { background: #e2e8f0; color: #94a3b8; }
        .step-optional { background: #ede9fe; color: #5b21b6; border: 1px dashed #c4b5fd; }

        @keyframes pulse {
            0%   { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            70%  { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }

        .btn-void {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            transition: 0.2s;
        }
        .btn-void:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239,68,68,0.3);
        }

        .btn-revert {
            background: #f59e0b;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 0.72rem;
            font-weight: 700;
            transition: 0.2s;
        }
        .btn-revert:hover {
            background: #d97706;
            color: white;
            transform: translateY(-1px);
        }

        /* ── Pagination ──────────────────────── */
        .pagination .page-link {
            font-size: 0.8rem;
            color: #334155;
            border-color: #e2e8f0;
            border-radius: 8px !important;
            margin: 0 2px;
            min-width: 34px;
            text-align: center;
        }
        .pagination .page-item.active .page-link {
            background: var(--mcp-blue);
            border-color: var(--mcp-blue);
            color: white;
        }
        .pagination .page-link:hover {
            background: #eff6ff;
            color: var(--mcp-blue);
        }

        @media (max-width: 768px) {
            .table-responsive thead { display: none; }
            .table-responsive tbody tr {
                display: block;
                margin: 15px;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                background: white;
                padding: 10px;
            }
            .table-responsive tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none;
                padding: 8px 10px;
                text-align: right;
            }
            .table-responsive tbody td::before {
                content: attr(data-label);
                font-weight: 700;
                text-align: left;
                font-size: 0.75rem;
                color: #64748b;
                text-transform: uppercase;
            }
            .step-indicator { justify-content: flex-end; }
            .btn-void, .btn-revert { width: 100%; padding: 10px; border-radius: 10px; margin-top: 8px; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-mcp mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="../../index.php">
            <i class="fas fa-arrow-left me-3"></i> <span>KEMBALI KE DASHBOARD</span>
        </a>
        <span class="navbar-text text-white d-none d-md-inline">
            <i class="fas fa-user-tie me-2"></i> <strong><?= h($nama_manager) ?></strong>
        </span>
    </div>
</nav>

<div class="container pb-5">

    <?php
    $pesan = $_GET['pesan'] ?? '';
    if ($pesan):
        $alertClass = (strpos($pesan, 'berhasil') !== false) ? 'alert-success' : 'alert-danger';
        $icon = (strpos($pesan, 'berhasil') !== false) ? 'fa-check-circle' : 'fa-times-circle';
    ?>
    <div class="alert <?= $alertClass ?> alert-dismissible fade show mb-4 shadow-sm border-0" role="alert" style="border-left: 5px solid rgba(0,0,0,0.1);">
        <div class="d-flex align-items-center">
            <i class="fas <?= $icon ?> fa-lg me-3"></i>
            <div>
                <?php if ($pesan === 'void_berhasil'): ?>
                    <strong>PR berhasil di-VOID / dibatalkan!</strong>
                <?php elseif ($pesan === 'revert_ke_menunggu'): ?>
                    <strong>Approval berhasil di-REVERT!</strong> PR kembali ke antrean <strong>MENUNGGU APPROVAL (M1)</strong>.
                <?php elseif ($pesan === 'revert_ke_approved1'): ?>
                    <strong>Approval berhasil di-REVERT!</strong> PR kembali ke <strong>APPROVED 1</strong>, menunggu Manager ke-2.
                <?php elseif ($pesan === 'revert_ke_approved2'): ?>
                    <strong>Approval berhasil di-REVERT!</strong> PR kembali ke <strong>APPROVED 2</strong>, menunggu Manager ke-3.
                <?php elseif ($pesan === 'catatan_kosong'): ?>
                    <strong>Alasan wajib diisi!</strong>
                <?php elseif ($pesan === 'belum_di_approve'): ?>
                    <strong>PR belum di-approve</strong>, tidak bisa di-revert.
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-7">
            <h4 class="fw-bold m-0 text-dark">
                List Approval Purchase Request
                <span class="badge bg-primary ms-2" style="font-size:.7rem;">
                    <?= number_format($total_data, 0, ',', '.') ?> PR
                </span>
            </h4>
            <p class="text-muted small mb-0">Daftar PR yang sudah di-approve. Anda dapat melakukan REVERT atau VOID jika diperlukan.</p>
        </div>
        <div class="col-md-5 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
            <span class="badge rounded-pill px-3 py-2 shadow-sm badge-cat-besar" style="font-size: 0.7rem;">
                <i class="fas fa-boxes me-1"></i> BARANG BESAR
            </span>
            <span class="badge rounded-pill px-3 py-2 shadow-sm badge-cat-it" style="font-size: 0.7rem;">
                <i class="fas fa-laptop me-1"></i> IT
            </span>
        </div>
    </div>

    <!-- ═══════════ FILTER BAR ═══════════ -->
    <form method="GET" action="" id="filterForm">
        <div class="filter-card">
            <div class="row g-2 align-items-end">
                <!-- Search -->
                <div class="col-12 col-md-4">
                    <label><i class="fas fa-search me-1"></i> Cari</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="No. Request / Pemesan / Keperluan..."
                           value="<?= h($search) ?>">
                </div>

                <!-- Kategori -->
                <div class="col-6 col-md-2">
                    <label>Kategori</label>
                    <select name="kategori" class="form-select">
                        <option value="">Semua</option>
                        <option value="BESAR" <?= $filter_kat === 'BESAR' ? 'selected' : '' ?>>BESAR</option>
                        <option value="IT"    <?= $filter_kat === 'IT'    ? 'selected' : '' ?>>IT</option>
                    </select>
                </div>

                <!-- Status -->
                <div class="col-6 col-md-2">
                    <label>Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        <option value="APPROVED 1" <?= $filter_stat === 'APPROVED 1' ? 'selected' : '' ?>>Approved 1</option>
                        <option value="APPROVED 2" <?= $filter_stat === 'APPROVED 2' ? 'selected' : '' ?>>Approved 2</option>
                        <option value="APPROVED"   <?= $filter_stat === 'APPROVED'   ? 'selected' : '' ?>>Fully Approved</option>
                    </select>
                </div>

                <!-- Tgl Dari -->
                <div class="col-6 col-md-2">
                    <label>Dari Tanggal</label>
                    <input type="date" name="tgl_dari" class="form-control" value="<?= h($tgl_dari) ?>">
                </div>

                <!-- Tgl Sampai -->
                <div class="col-6 col-md-2">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="tgl_sampai" class="form-control" value="<?= h($tgl_sampai) ?>">
                </div>

                <!-- Per Page + Sort -->
                <div class="col-6 col-md-2">
                    <label>Tampilkan</label>
                    <select name="per_page" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <?php foreach ([10, 25, 50, 100] as $pp): ?>
                            <option value="<?= $pp ?>" <?= $per_page == $pp ? 'selected' : '' ?>><?= $pp ?> baris</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Sort -->
                <div class="col-6 col-md-2">
                    <label>Urutkan</label>
                    <select name="sort" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="tgl_request"   <?= $sort_by === 'tgl_request'   ? 'selected' : '' ?>>Tanggal Request</option>
                        <option value="no_request"    <?= $sort_by === 'no_request'    ? 'selected' : '' ?>>No. Request</option>
                        <option value="nama_pemesan"  <?= $sort_by === 'nama_pemesan'  ? 'selected' : '' ?>>Pemesan</option>
                        <option value="status_approval" <?= $sort_by === 'status_approval' ? 'selected' : '' ?>>Status</option>
                    </select>
                </div>

                <!-- Order -->
                <div class="col-6 col-md-2">
                    <label>Arah</label>
                    <select name="order" class="form-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Terbaru (DESC)</option>
                        <option value="ASC"  <?= $sort_order === 'ASC'  ? 'selected' : '' ?>>Terlama (ASC)</option>
                    </select>
                </div>

                <!-- Tombol Aksi -->
                <div class="col-12 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm fw-bold flex-fill">
                        <i class="fas fa-filter me-1"></i> TERAPKAN
                    </button>
                    <a href="list_approval_pimpinan.php" class="btn btn-outline-secondary btn-sm fw-bold flex-fill">
                        <i class="fas fa-redo me-1"></i> RESET
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="card card-main">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">No. Request</th>
                            <th>Kategori</th>
                            <th>Status Approval</th>
                            <th>Pemesan</th>
                            <th>Keperluan</th>
                            <th>Progress Approval</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($rows) > 0):
                        foreach ($rows as $data):
                            $status_app   = $data['status_approval'];
                            $need_m3      = (int)$data['need_approve3'];
                            $is_approved1 = ($status_app === 'APPROVED 1');
                            $is_approved2 = ($status_app === 'APPROVED 2');
                            $is_approved  = ($status_app === 'APPROVED');

                            if ($is_approved) {
                                $badge = '<span class="badge-status badge-approved"><i class="fas fa-check-double"></i> APPROVED</span>';
                            } elseif ($is_approved2) {
                                $badge = '<span class="badge-status badge-approved2"><i class="fas fa-check"></i> APPROVED 2</span>';
                            } elseif ($is_approved1) {
                                $badge = '<span class="badge-status badge-approved1"><i class="fas fa-check"></i> APPROVED 1</span>';
                            } else {
                                $badge = '<span class="badge-status badge-approved1"><i class="fas fa-check"></i> PROSES</span>';
                            }

                            // Badge kategori
                            $kat = $data['kategori_pr'] ?? '';
                            if ($kat === 'IT') {
                                $badge_kat = '<span class="badge-status badge-cat-it" style="padding:4px 10px;font-size:.65rem;"><i class="fas fa-laptop me-1"></i>IT</span>';
                            } else {
                                $badge_kat = '<span class="badge-status badge-cat-besar" style="padding:4px 10px;font-size:.65rem;"><i class="fas fa-boxes me-1"></i>BESAR</span>';
                            }

                            // Hitung revert count (jika ada kolomnya)
                            $revert_count = (int)($data['revert_count'] ?? 0);
                    ?>
                        <tr>
                            <td class="ps-4" data-label="No. Request">
                                <span class="fw-bold text-primary"><?= h($data['no_request']) ?></span><br>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($data['tgl_request'])) ?></small>
                                <?php if ($revert_count > 0): ?>
                                    <br><span class="badge bg-warning text-dark mt-1" style="font-size:.6rem;"
                                          title="Pernah di-revert <?= $revert_count ?>x">
                                        <i class="fas fa-undo"></i> REVERTED <?= $revert_count ?>x
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Kategori"><?= $badge_kat ?></td>
                            <td data-label="Status"><?= $badge ?></td>
                            <td data-label="Pemesan">
                                <div class="text-uppercase small fw-semibold text-dark"><?= h($data['nama_pemesan']) ?></div>
                            </td>
                            <td data-label="Keperluan">
                                <div class="text-truncate text-muted small" style="max-width:220px;" title="<?= h($data['keterangan']) ?>">
                                    <?= h($data['keterangan']) ?>
                                </div>
                            </td>
                            <td data-label="Progress">
                                <div class="step-indicator d-flex align-items-center gap-2">
                                    <div class="text-center">
                                        <span class="step-dot <?= in_array($status_app, ['APPROVED 1','APPROVED 2','APPROVED']) ? 'step-done' : 'step-active' ?>">
                                            <?= in_array($status_app, ['APPROVED 1','APPROVED 2','APPROVED']) ? '<i class="fas fa-check"></i>' : '1' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted"><?= $data['approve1_by'] ? h($data['approve1_by']) : 'M1' ?></div>
                                    </div>

                                    <div style="width:15px; height:2px; background:#e2e8f0; margin-bottom:12px;"></div>

                                    <div class="text-center">
                                        <span class="step-dot <?= in_array($status_app, ['APPROVED 2','APPROVED']) ? 'step-done' : ($is_approved1 ? 'step-active' : 'step-todo') ?>">
                                            <?= in_array($status_app, ['APPROVED 2','APPROVED']) ? '<i class="fas fa-check"></i>' : '2' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted"><?= $data['approve2_by'] ? h($data['approve2_by']) : 'M2' ?></div>
                                    </div>

                                    <?php if ($need_m3): ?>
                                    <div style="width:15px; height:2px; background:#e2e8f0; margin-bottom:12px;"></div>
                                    <div class="text-center">
                                        <span class="step-dot <?= ($status_app === 'APPROVED') ? 'step-done' : ($is_approved2 ? 'step-active' : 'step-optional') ?>">
                                            <?= ($status_app === 'APPROVED') ? '<i class="fas fa-check"></i>' : '3' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted"><?= h($data['approve3_by'] ?: ($data['approve3_target'] ?: 'M3')) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center px-4" data-label="Aksi">
                                <div class="d-flex gap-2 justify-content-center flex-wrap">
                                    <?php 
                                    $boleh_revert = in_array($status_app, ['APPROVED 1', 'APPROVED 2', 'APPROVED']) 
                                                    && empty($data['void_by']);
                                    $boleh_void   = empty($data['void_by']);
                                    
                                    if ($boleh_revert): 
                                    ?>
                                    <button type="button"
                                            class="btn-revert"
                                            onclick="revertPR(<?= $data['id_request'] ?>, '<?= h($data['no_request']) ?>', '<?= $status_app ?>')">
                                        <i class="fas fa-undo me-1"></i> REVERT
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($boleh_void): ?>
                                    <button type="button"
                                            class="btn-void"
                                            onclick="voidPR(<?= $data['id_request'] ?>, '<?= h($data['no_request']) ?>')">
                                        <i class="fas fa-ban me-1"></i> VOID
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-clipboard-check fa-4x text-muted opacity-25 mb-3 d-block"></i>
                                <h6 class="text-muted fw-normal">
                                    <?php if ($search || $filter_kat || $filter_stat || $tgl_dari || $tgl_sampai): ?>
                                        Tidak ada PR yang sesuai dengan filter.
                                        <br><a href="list_approval_pimpinan.php" class="btn btn-sm btn-outline-primary mt-2">
                                            <i class="fas fa-redo me-1"></i> Reset Filter
                                        </a>
                                    <?php else: ?>
                                        Belum ada PR yang di-approve.
                                    <?php endif; ?>
                                </h6>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══════════ PAGINATION ═══════════ -->
        <?php if ($total_page > 1): ?>
        <div class="card-footer bg-white border-top py-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted small">
                    Menampilkan 
                    <strong><?= ($total_data > 0) ? ($offset + 1) : 0 ?></strong> 
                    - 
                    <strong><?= min($offset + $per_page, $total_data) ?></strong> 
                    dari 
                    <strong><?= number_format($total_data, 0, ',', '.') ?></strong> PR
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <!-- First -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>page=1">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <!-- Prev -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>page=<?= $page - 1 ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>

                        <?php
                        // Tampilkan maksimal 5 halaman di sekitar halaman aktif
                        $start = max(1, $page - 2);
                        $end   = min($total_page, $page + 2);
                        if ($start > 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;
                        if ($end < $total_page) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        ?>

                        <!-- Next -->
                        <li class="page-item <?= ($page >= $total_page) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>page=<?= $page + 1 ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <!-- Last -->
                        <li class="page-item <?= ($page >= $total_page) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>page=<?= $total_page ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-4 p-3 bg-white border-0 shadow-sm rounded-4 d-flex align-items-center">
        <div class="bg-warning bg-opacity-10 p-2 rounded-circle me-3">
            <i class="fas fa-info-circle text-warning"></i>
        </div>
        <div style="font-size: 0.75rem;" class="text-muted">
            <strong>Info:</strong> 
            <span class="text-warning fw-bold">REVERT</span> mengembalikan PR ke tahap approval sebelumnya (bisa di-approve ulang).
            <span class="text-danger fw-bold">VOID</span> membatalkan PR secara permanen (tidak bisa dikembalikan).
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const KEEP_ALIVE_URL = '/pr_mcp/auth/keep_alive.php';
    const LOGOUT_URL     = '/pr_mcp/auth/logout.php?pesan=timeout';

    let idleTime = 0;
    const maxIdleMinutes = 15;
    let lastServerUpdate = Date.now();
    let sessionValid = true;

    function resetTimer() {
        idleTime = 0;
        let now = Date.now();
        if (now - lastServerUpdate > 300000) {
            fetch(KEEP_ALIVE_URL)
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success') { sessionValid = false; forceLogout(); }
                })
                .catch(() => {});
            lastServerUpdate = now;
        }
    }

    function forceLogout() {
        alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
        window.location.href = LOGOUT_URL;
    }

    window.onload      = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress  = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick     = resetTimer;
    document.onscroll    = resetTimer;

    setInterval(function() {
        idleTime++;
        fetch(KEEP_ALIVE_URL)
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') { sessionValid = false; forceLogout(); }
            })
            .catch(() => {});
        if (idleTime >= maxIdleMinutes && sessionValid) forceLogout();
    }, 60000);

    // ── FUNGSI VOID ──────────────────────────────
    function voidPR(id, no) {
        Swal.fire({
            title: 'VOID / BATALKAN PR?',
            html: 'PR <strong>' + no + '</strong> akan dibatalkan dan tidak bisa diproses lebih lanjut.',
            icon: 'warning',
            input: 'textarea',
            inputPlaceholder: 'Wajib isi alasan void / pembatalan...',
            inputValidator: (value) => {
                if (!value || value.trim() === '') {
                    return 'Alasan void / pembatalan wajib diisi!';
                }
            },
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-ban me-1"></i> Ya, VOID PR',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                window.location.href = 'proses_void_pimpinan.php?action=void&id=' + id
                                     + '&catatan=' + encodeURIComponent(result.value);
            }
        });
    }

    // ── FUNGSI REVERT ────────────────────────────
    function revertPR(id, no, status) {
        let targetLabel = '';
        if (status === 'APPROVED') {
            targetLabel = 'APPROVED 2 (kembali ke tahap M3)';
        } else if (status === 'APPROVED 2') {
            targetLabel = 'APPROVED 1 (kembali ke tahap M2)';
        } else if (status === 'APPROVED 1') {
            targetLabel = 'MENUNGGU APPROVAL (kembali ke tahap M1)';
        }
        
        Swal.fire({
            title: 'REVERT APPROVAL?',
            html: 'PR <strong>' + no + '</strong> akan dikembalikan ke status:<br><strong class="text-warning">' + targetLabel + '</strong><br><br>Approval terakhir akan dihapus dan manager tersebut harus approve ulang.',
            icon: 'warning',
            input: 'textarea',
            inputPlaceholder: 'Wajib isi alasan revert (misal: salah approve)...',
            inputValidator: (value) => {
                if (!value || value.trim() === '') {
                    return 'Alasan revert wajib diisi!';
                }
            },
            showCancelButton: true,
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#64748b',
            confirmButtonText: '<i class="fas fa-undo me-1"></i> Ya, REVERT',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                window.location.href = 'proses_revert_pimpinan.php?id=' + id
                                     + '&catatan=' + encodeURIComponent(result.value);
            }
        });
    }
</script>
</body>
</html>