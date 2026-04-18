<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Pastikan hanya role tertentu yang bisa akses (opsional)
if ($_SESSION['role'] != "manager" && $_SESSION['role'] != "administrator") {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard';</script>";
    exit;
}

// Pesan notifikasi setelah Clean Log
$pesan = isset($_GET['pesan']) ? $_GET['pesan'] : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Log Aktivitas Sistem - MCP</title>
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .table-log { font-size: 0.78rem; } /* Font kecil agar muat banyak data */
        .table-log thead th { background-color: #2c3e50; color: white; text-align: center; }
        .badge-aksi { font-size: 0.7rem; padding: 4px 8px; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            
            <?php if($pesan == 'clean_success'): ?>
                <div class="alert alert-success alert-dismissible fade show small">
                    <i class="fas fa-check-circle me-1"></i> Data log lama berhasil dibersihkan!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-history me-2"></i>LOG AKTIVITAS SISTEM</h5>
                    <div>
                       <a href="users.php" class="btn btn-danger btn-sm fw-bold"  role="button">
                            <i class="fas fa-rotate-left me-1"></i> KEMBALI
                        </a>

                        <button class="btn btn-warning btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalClean">
                            <i class="fas fa-eraser me-1"></i> CLEAN LOG
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover table-striped table-log">
                            <thead>
                                <tr>
                                    <th width="150">Waktu (WIB)</th>
                                    <th width="120">User</th>
                                    <th width="150">Aksi</th>
                                    <th>Rincian Aktivitas</th>
                                    <th width="130">IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Menampilkan 200 log terbaru
                                $sql = "SELECT * FROM tr_log_activity ORDER BY created_at DESC LIMIT 200";
                                $query = mysqli_query($koneksi, $sql);
                                
                                if(mysqli_num_rows($query) > 0) {
                                    while($row = mysqli_fetch_assoc($query)) {
                                        // Warna badge berdasarkan aksi
                                        $bg = "bg-secondary";
                                        if(strpos($row['aksi'], 'TAMBAH') !== false) $bg = "bg-success";
                                        if(strpos($row['aksi'], 'HAPUS') !== false) $bg = "bg-danger";
                                        if(strpos($row['aksi'], 'UPDATE') !== false) $bg = "bg-warning text-dark";
                                        if(strpos($row['aksi'], 'LOGIN') !== false) $bg = "bg-primary";
                                ?>
                                <tr>
                                    <td class="text-center"><?= date('d/m/Y H:i:s', strtotime($row['created_at'])) ?></td>
                                    <td class="text-center fw-bold text-uppercase"><?= $row['username'] ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-aksi <?= $bg ?>"><?= $row['aksi'] ?></span>
                                    </td>
                                    <td class="px-3"><?= $row['rincian'] ?></td>
                                    <td class="text-center text-muted small"><?= $row['ip_address'] ?></td>
                                </tr>
                                <?php 
                                    } 
                                } else {
                                    echo "<tr><td colspan='5' class='text-center py-4 text-muted'>Belum ada aktivitas tercatat.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalClean" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title small fw-bold">KONFIRMASI PEMBERSIHAN LOG</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="clean_log.php" method="POST">
                <div class="modal-body">
                    <p class="small">Pilih data log yang ingin dihapus untuk mengoptimalkan database:</p>
                    <select name="clean_type" class="form-select form-select-sm mb-3" required>
                        <option value="1_bulan">Hapus log yang sudah lebih dari 1 bulan</option>
                        <option value="3_bulan">Hapus log yang sudah lebih dari 3 bulan</option>
                        <option value="semua">Kosongkan semua log aktivitas</option>
                    </select>
                    <div class="alert alert-warning p-2 small mb-0">
                        <i class="fas fa-exclamation-triangle"></i> Data yang dihapus tidak dapat dikembalikan!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm">Ya, Hapus Data</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>