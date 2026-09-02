<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Semua user yang login bisa akses
if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=harus_login");
    exit;
}

$nama_user  = strtoupper($_SESSION['nama'] ?? $_SESSION['username'] ?? 'USER');
$username_saya = mysqli_real_escape_string($koneksi, $_SESSION['username'] ?? '');

// ===== PERUBAHAN: Hanya tampilkan PR yang approval-nya BELUM SELESAI =====
$sql = "SELECT * FROM tr_request
        WHERE kategori_pr IN ('BESAR', 'IT')
        AND status_request NOT IN ('BATAL','SELESAI')
        AND status_approval NOT IN ('APPROVED', 'DITOLAK')
        ORDER BY tgl_request ASC";

$query = mysqli_query($koneksi, $sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrean Approval PR - MCP System</title>
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

        .table thead { background: #f1f5f9; border-bottom: 2px solid #e2e8f0; }
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            color: #64748b;
            padding: 15px;
            border: none;
        }

        .badge-status { padding: 6px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; border: 1px solid transparent; }
        .badge-waiting   { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .badge-approved1 { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .badge-approved2 { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }

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
            .btn-review { width: 100%; padding: 12px; border-radius: 10px; margin-top: 10px; }
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
            <i class="fas fa-user me-2"></i> <strong><?= $nama_user ?></strong>
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
                <?php if ($pesan === 'approve1_berhasil'): ?>
                    <strong>Approval ke-1 berhasil!</strong> Menunggu Manager ke-2 untuk approve.
                <?php elseif ($pesan === 'approve2_berhasil'): ?>
                    <strong>Approval ke-2 berhasil!</strong> Menunggu Manager ke-3 (atau PR sudah APPROVED jika tanpa M3).
                <?php elseif ($pesan === 'approve3_berhasil' || $pesan === 'approve_final_berhasil'): ?>
                    <strong>PR telah FULLY APPROVED!</strong> Staf Purchasing dapat memproses pembelian.
                <?php elseif ($pesan === 'ditolak'): ?>
                    <strong>PR telah DITOLAK.</strong> Permintaan tidak akan diproses lebih lanjut.
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row align-items-center mb-4 g-3">
        <div class="col-md-7">
            <h4 class="fw-bold m-0 text-dark">Antrean Approval Purchase Request</h4>
            <p class="text-muted small mb-0">Menampilkan PR Barang Besar & IT yang <strong>belum selesai</strong> proses approval.</p>
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

    <div class="card card-main">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">No. Request</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Pemesan</th>
                            <th>Keperluan</th>
                            <th>Progress Approval</th>
                            <th class="text-center">Penawaran</th>
                            <?php if ($_SESSION['role'] == 'manager'): ?>
                            <th class="text-center">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($query) > 0):
                        while ($data = mysqli_fetch_assoc($query)):
                            $status_app  = $data['status_approval'];
                            $need_m3     = (int)$data['need_approve3'];
                            $is_approved1 = ($status_app === 'APPROVED 1');
                            $is_approved2 = ($status_app === 'APPROVED 2');

                            // ===== BADGE STATUS =====
                            if ($status_app === 'MENUNGGU APPROVAL') {
                                $badge = '<span class="badge-status badge-waiting"><i class="fas fa-clock"></i> MENUNGGU</span>';
                            } elseif ($is_approved1) {
                                $badge = '<span class="badge-status badge-approved1"><i class="fas fa-check"></i> APPROVED 1</span>';
                            } elseif ($is_approved2) {
                                $badge = '<span class="badge-status badge-approved2"><i class="fas fa-check"></i> APPROVED 2</span>';
                            } else {
                                $badge = '<span class="badge-status" style="background:#f1f5f9; color:#64748b;">' . htmlspecialchars($status_app) . '</span>';
                            }

                            // ===== BADGE KATEGORI =====
                            $kat = $data['kategori_pr'] ?? '';
                            if ($kat === 'IT') {
                                $badge_kat = '<span class="badge-status badge-cat-it" style="padding:4px 10px;font-size:.65rem;"><i class="fas fa-laptop me-1"></i>IT</span>';
                            } elseif ($kat === 'BESAR') {
                                $badge_kat = '<span class="badge-status badge-cat-besar" style="padding:4px 10px;font-size:.65rem;"><i class="fas fa-boxes me-1"></i>BESAR</span>';
                            } else {
                                $badge_kat = '<span class="badge-status" style="background:#f1f5f9; color:#64748b; padding:4px 10px;font-size:.65rem;">' . htmlspecialchars($kat) . '</span>';
                            }
                    ?>
                        <tr>
                            <td class="ps-4" data-label="No. Request">
                                <span class="fw-bold text-primary"><?= htmlspecialchars($data['no_request']) ?></span><br>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($data['tgl_request'])) ?></small>
                            </td>
                            <td data-label="Kategori"><?= $badge_kat ?></td>
                            <td data-label="Status"><?= $badge ?></td>
                            <td data-label="Pemesan">
                                <div class="text-uppercase small fw-semibold text-dark"><?= htmlspecialchars($data['nama_pemesan']) ?></div>
                            </td>
                            <td data-label="Keperluan">
                                <div class="text-truncate text-muted small" style="max-width:220px;" title="<?= htmlspecialchars($data['keterangan']) ?>">
                                    <?= htmlspecialchars($data['keterangan']) ?>
                                </div>
                            </td>
                            <td data-label="Progress">
                                <div class="step-indicator d-flex align-items-center gap-2">
                                    <!-- Step 1 -->
                                    <div class="text-center">
                                        <span class="step-dot <?= in_array($status_app, ['APPROVED 1','APPROVED 2','APPROVED']) ? 'step-done' : 'step-active' ?>">
                                            <?= in_array($status_app, ['APPROVED 1','APPROVED 2','APPROVED']) ? '<i class="fas fa-check"></i>' : '1' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted"><?= $data['approve1_by'] ? htmlspecialchars($data['approve1_by']) : 'M1' ?></div>
                                    </div>

                                    <div style="width:15px; height:2px; background:#e2e8f0; margin-bottom:12px;"></div>

                                    <!-- Step 2 -->
                                    <div class="text-center">
                                        <span class="step-dot <?= in_array($status_app, ['APPROVED 2','APPROVED']) ? 'step-done' : ($is_approved1 ? 'step-active' : 'step-todo') ?>">
                                            <?= in_array($status_app, ['APPROVED 2','APPROVED']) ? '<i class="fas fa-check"></i>' : '2' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted"><?= $data['approve2_by'] ? htmlspecialchars($data['approve2_by']) : 'M2' ?></div>
                                    </div>

                                    <!-- Step 3 (Opsional) -->
                                    <?php if ($need_m3): ?>
                                    <div style="width:15px; height:2px; background:#e2e8f0; margin-bottom:12px;"></div>
                                    <div class="text-center">
                                        <span class="step-dot <?= ($status_app === 'APPROVED') ? 'step-done' : ($is_approved2 ? 'step-active' : 'step-optional') ?>">
                                            <?= ($status_app === 'APPROVED') ? '<i class="fas fa-check"></i>' : '3' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted"><?= htmlspecialchars($data['approve3_by'] ?: ($data['approve3_target'] ?: 'M3')) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-center" data-label="Penawaran">
                                <?php if (!empty($data['file_penawaran'])): ?>
                                    <a href="../../download_penawaran.php?file=<?= urlencode($data['file_penawaran']) ?>&id_request=<?= $data['id_request'] ?>"
                                       target="_blank"
                                       class="btn btn-sm fw-bold"
                                       style="background:#16a34a; color:#fff; border-radius:20px; padding:4px 12px; font-size:.7rem; white-space:nowrap;">
                                        <i class="fas fa-file-pdf me-1"></i> PDF
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.7rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($_SESSION['role'] == 'manager'): ?>
                            <td class="text-center" data-label="Aksi">
                                <div class="d-flex gap-1 justify-content-center">
                                    <?php if ($status_app === 'MENUNGGU APPROVAL' && empty($data['approve1_by'])): ?>
                                        <a href="approve_1.php?id=<?= $data['id_request'] ?>" 
                                           class="btn btn-success btn-sm" 
                                           style="padding:2px 8px; font-size:.7rem; border-radius:6px;">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php elseif ($status_app === 'APPROVED 1' && $data['approve1_by'] != $username_saya && empty($data['approve2_by'])): ?>
                                        <a href="approve_2.php?id=<?= $data['id_request'] ?>" 
                                           class="btn btn-success btn-sm" 
                                           style="padding:2px 8px; font-size:.7rem; border-radius:6px;">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php elseif ($status_app === 'APPROVED 2' && $need_m3 && $data['approve3_target'] == $username_saya && empty($data['approve3_by'])): ?>
                                        <a href="approve_3.php?id=<?= $data['id_request'] ?>" 
                                           class="btn btn-success btn-sm" 
                                           style="padding:2px 8px; font-size:.7rem; border-radius:6px;">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($status_app, ['MENUNGGU APPROVAL', 'APPROVED 1', 'APPROVED 2'])): ?>
                                        <a href="reject.php?id=<?= $data['id_request'] ?>" 
                                           class="btn btn-danger btn-sm" 
                                           style="padding:2px 8px; font-size:.7rem; border-radius:6px;"
                                           onclick="return confirm('Yakin ingin menolak PR ini?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="<?= ($_SESSION['role'] == 'manager') ? '9' : '8' ?>" class="text-center py-5">
                                <i class="fas fa-clipboard-check fa-4x text-success opacity-25 mb-3 d-block"></i>
                                <h6 class="text-muted fw-normal">Semua PR sudah selesai approval.</h6>
                                <p class="text-muted small">Tidak ada antrean persetujuan saat ini.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 p-3 bg-white border-0 shadow-sm rounded-4 d-flex align-items-center">
        <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
            <i class="fas fa-info-circle text-primary"></i>
        </div>
        <div style="font-size: 0.75rem;" class="text-muted">
            <strong>Info Alur:</strong> M1 &rarr; APPROVED 1 &rarr; M2 &rarr; APPROVED 2 &rarr; (Opsional M3) &rarr; FULLY APPROVED.
            Setelah status <span class="text-success fw-bold">APPROVED</span>, PR dianggap selesai dan tidak muncul di antrean ini.
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
</script>
</body>
</html>