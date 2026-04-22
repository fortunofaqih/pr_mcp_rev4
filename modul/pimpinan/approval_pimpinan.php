<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';


if ($_SESSION['status'] != "login" || $_SESSION['role'] != 'manager') {
    header("location:../../login.php?pesan=bukan_pimpinan");
    exit;
}

$nama_manager  = strtoupper($_SESSION['nama'] ?? $_SESSION['username'] ?? 'MANAGER');
$username_saya = $_SESSION['username'] ?? '';

// Ambil data PR BESAR yang butuh aksi dari manager ini
$sql = "SELECT * FROM tr_request
        WHERE kategori_pr = 'BESAR'
        AND status_request NOT IN ('BATAL','SELESAI')
        AND (
            status_approval = 'MENUNGGU APPROVAL'
            OR (
                status_approval = 'APPROVED 1'
                AND (approve1_by IS NULL OR approve1_by != '$username_saya')
            )
            OR (
                status_approval = 'APPROVED 2'
                AND need_approve3 = 1
                AND approve3_target = '$username_saya'
                AND (approve3_by IS NULL OR approve3_by = '')
            )
        )
        ORDER BY tgl_request ASC";

$query = mysqli_query($koneksi, $sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrean Approval PR Besar - MCP System</title>
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

        /* Navbar Styling */
        .navbar-mcp { 
            background: linear-gradient(135deg, var(--mcp-blue), #2563eb); 
            padding: 1rem 0;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        /* Card & Table Styling */
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

        /* Status Badges */
        .badge-status { padding: 6px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; border: 1px solid transparent; }
        .badge-waiting { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
        .badge-approved1 { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
        .badge-approved2 { background: #ede9fe; color: #5b21b6; border-color: #c4b5fd; }

        /* Step Dots */
        .step-dot { width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; }
        .step-done { background: #10b981; color: white; }
        .step-active { background: #f59e0b; color: white; animation: pulse 2s infinite; }
        .step-todo { background: #e2e8f0; color: #94a3b8; }
        .step-optional { background: #ede9fe; color: #5b21b6; border: 1px dashed #c4b5fd; }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }

        /* Responsive UI */
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
            <i class="fas fa-user-tie me-2"></i> <strong><?= $nama_manager ?></strong>
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
                    <strong>PR telah FULLY APPROVED!</strong> Tim pembelian dapat memproses PO.
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
            <h4 class="fw-bold m-0 text-dark">Antrean Approval PR Barang Besar</h4>
            <p class="text-muted small mb-0">Hanya menampilkan PR yang menunggu tindakan persetujuan Anda.</p>
        </div>
        <div class="col-md-5 text-md-end">
            <span class="badge bg-danger rounded-pill px-3 py-2 shadow-sm" style="font-size: 0.7rem;">
                <i class="fas fa-exclamation-triangle me-1"></i> KATEGORI: BARANG BESAR / INVESTASI
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
                            <th>Status</th>
                            <th>Pemesan</th>
                            <th>Keperluan</th>
                            <th>Progress Approval</th>
							<th class="text-center">Penawaran</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (mysqli_num_rows($query) > 0): 
                        while ($data = mysqli_fetch_assoc($query)): 
                            $status_app  = $data['status_approval'];
                            $need_m3     = (int)$data['need_approve3'];
                            $is_approved1 = ($status_app === 'APPROVED 1');
                            $is_approved2 = ($status_app === 'APPROVED 2');

                            if ($status_app === 'MENUNGGU APPROVAL') {
                                $badge = '<span class="badge-status badge-waiting"><i class="fas fa-clock"></i> MENUNGGU</span>';
                            } elseif ($is_approved1) {
                                $badge = '<span class="badge-status badge-approved1"><i class="fas fa-check"></i> APPROVED 1</span>';
                            } elseif ($is_approved2) {
                                $badge = '<span class="badge-status badge-approved2"><i class="fas fa-check"></i> APPROVED 2</span>';
                            } else {
                                $badge = '';
                            }
                    ?>
                        <tr>
                            <td class="ps-4" data-label="No. Request">
                                <span class="fw-bold text-primary"><?= $data['no_request'] ?></span><br>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($data['tgl_request'])) ?></small>
                            </td>
                            <td data-label="Status"><?= $badge ?></td>
                            <td data-label="Pemesan">
                                <div class="text-uppercase small fw-semibold text-dark"><?= htmlspecialchars($data['nama_pemesan']) ?></div>
                            </td>
                            <td data-label="Keperluan">
                                <div class="text-truncate text-muted small" style="max-width:250px;" title="<?= htmlspecialchars($data['keterangan']) ?>">
                                    <?= htmlspecialchars($data['keterangan']) ?>
                                </div>
                            </td>
                            <td data-label="Progress">
                                <div class="step-indicator d-flex align-items-center gap-2">
                                    <div class="text-center">
                                        <span class="step-dot <?= in_array($status_app, ['APPROVED 1','APPROVED 2','APPROVED']) ? 'step-done' : 'step-active' ?>">
                                            <?= in_array($status_app, ['APPROVED 1','APPROVED 2','APPROVED']) ? '<i class="fas fa-check"></i>' : '1' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted"><?= $data['approve1_by'] ? htmlspecialchars($data['approve1_by']) : 'M1' ?></div>
                                    </div>

                                    <div style="width:15px; height:2px; background:#e2e8f0; margin-bottom:12px;"></div>

                                    <div class="text-center">
                                        <span class="step-dot <?= in_array($status_app, ['APPROVED 2','APPROVED']) ? 'step-done' : ($is_approved1 ? 'step-active' : 'step-todo') ?>">
                                            <?= in_array($status_app, ['APPROVED 2','APPROVED']) ? '<i class="fas fa-check"></i>' : '2' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted"><?= $data['approve2_by'] ? htmlspecialchars($data['approve2_by']) : 'M2' ?></div>
                                    </div>

                                    <?php if ($need_m3): ?>
                                    <div style="width:15px; height:2px; background:#e2e8f0; margin-bottom:12px;"></div>
                                    <div class="text-center">
                                        <span class="step-dot <?= ($status_app === 'APPROVED') ? 'step-done' : ($is_approved2 ? 'step-active' : 'step-optional') ?>">
                                            <?= ($status_app === 'APPROVED') ? '<i class="fas fa-check"></i>' : '3' ?>
                                        </span>
                                        <div style="font-size:0.6rem" class="text-muted text-truncate" style="max-width:40px;"><?= $data['approve3_by'] ?: ($data['approve3_target'] ?: 'M3') ?></div>
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
                            <td class="text-center px-4">
                                <a href="approval_pimpinan_detail.php?id=<?= $data['id_request'] ?>" 
                                   class="btn btn-primary btn-sm px-4 fw-bold shadow-sm btn-review">
                                   REVIEW <i class="fas fa-search ms-1"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fas fa-clipboard-check fa-4x text-muted opacity-25 mb-3 d-block"></i>
                                <h6 class="text-muted fw-normal">Tidak ada antrean persetujuan untuk Anda saat ini.</h6>
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
            Setelah status <span class="text-success fw-bold">APPROVED</span>, tim purchasing baru dapat memproses Purchase Order (PO).
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let idleTime = 0;
    const maxIdleMinutes = 15; // Samakan dengan server
    let lastServerUpdate = Date.now();
    let sessionValid = true;

    // Fungsi reset timer saat ada gerakan
    function resetTimer() {
        idleTime = 0;
        let now = Date.now();

        // Kirim sinyal ke server setiap 5 menit agar session PHP tidak expired
        if (now - lastServerUpdate > 300000) {
            fetch('/pr_mcp_rev4/auth/keep_alive.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        sessionValid = false;
                        forceLogout();
                    }
                })
                .catch(err => {
                    console.error("Koneksi ke server terputus");
                });
            lastServerUpdate = now;
        }
    }

    // Fungsi paksa logout
    function forceLogout() {
        alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
        // Redirect ke logout.php agar session server juga dihancurkan
        window.location.href = "/pr_mcp_rev4/auth/logout.php?pesan=timeout";
    }

    // Pantau aktivitas user
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    // Cek status idle setiap 1 menit
    setInterval(function() {
        idleTime++;
        // Cek session ke server juga
        fetch('/pr_mcp_rev4/auth/keep_alive.php')
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    sessionValid = false;
                    forceLogout();
                }
            })
            .catch(err => {
                // Jika error koneksi, biarkan user tetap di halaman
            });
        if (idleTime >= maxIdleMinutes && sessionValid) {
            forceLogout();
        }
    }, 60000);
</script>
</body>
</html>