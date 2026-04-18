<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

/* =======================
   PROTEKSI HALAMAN
======================= */
if (!isset($_SESSION['status']) || $_SESSION['status'] != 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if ($_SESSION['role'] != 'administrator') {
    header("location:../../index.php");
    exit;
}

/* =======================
   PROSES TAMBAH USER
======================= */
if (isset($_POST['simpan'])) {
    $username      = mysqli_real_escape_string($koneksi, $_POST['username']);
    $nama_lengkap  = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $password      = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role          = $_POST['role'];
    $bagian        = $_POST['bagian'];

    $q_max   = mysqli_query($koneksi, "SELECT MAX(id_user) as max_id FROM users");
    $r_max   = mysqli_fetch_assoc($q_max);
    $id_baru = ($r_max['max_id'] ?? 0) + 1;

    $query = "INSERT INTO users 
              (id_user, username, password, nama_lengkap, role, bagian, status_aktif)
              VALUES
              ('$id_baru', '$username', '$password', '$nama_lengkap', '$role', '$bagian', 'AKTIF')";

    if (mysqli_query($koneksi, $query)) {
        header("location:users.php?pesan=berhasil");
        exit;
    } else {
        die("Gagal simpan user: " . mysqli_error($koneksi));
    }
}

/* =======================
   PROSES UPDATE USER
======================= */
if (isset($_POST['update'])) {
    $id_user      = (int)$_POST['id_user'];
    $nama_lengkap = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $role         = $_POST['role'];
    $bagian       = $_POST['bagian'];
    $status       = $_POST['status_aktif'];

    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        mysqli_query($koneksi, "UPDATE users SET nama_lengkap='$nama_lengkap', password='$password', role='$role', bagian='$bagian', status_aktif='$status' WHERE id_user='$id_user'");
    } else {
        mysqli_query($koneksi, "UPDATE users SET nama_lengkap='$nama_lengkap', role='$role', bagian='$bagian', status_aktif='$status' WHERE id_user='$id_user'");
    }
    header("location:users.php?pesan=update");
    exit;
}

/* =======================
   PROSES HAPUS USER
======================= */
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($koneksi, "DELETE FROM users WHERE id_user='$id'");
    header("location:users.php?pesan=hapus");
    exit;
}

// Konfigurasi UI
$daftar_role = [
    'admin_gudang'      => '📦 Admin Gudang',
    'bagian_pembelian'  => '🛒 Bagian Pembelian',
    'manager'           => '🏢 Manager',
    'pemesan_pr_besar'  => '📋 Pemesan PR Besar',
    'finance'           => '💰 Finance',
];
$daftar_bagian = ['Gudang', 'Pembelian', 'Manager', 'IT', 'Produksi', 'Finance'];
$badge_role = [
    'administrator'     => 'bg-dark',
    'manager'           => 'bg-primary',
    'admin_gudang'      => 'bg-success',
    'bagian_pembelian'  => 'bg-info',
    'pemesan_pr_besar'  => 'bg-warning text-dark',
    'finance'           => 'bg-purple text-white',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - MCP System</title>
    <link rel="icon" type="image/png" href="../../assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-blue: #0000FF; --mcp-dark: #00008B; }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, sans-serif; overflow: hidden; }
        
        .wrapper { display: flex; width: 100%; height: 100vh; align-items: stretch; }

        /* Sidebar Styling */
        #sidebar {
            min-width: 260px;
            max-width: 260px;
            background: var(--mcp-blue);
            color: #fff;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            z-index: 1040;
        }
        #sidebar.active { margin-left: -260px; }
        .sidebar-header { padding: 20px; background: rgba(0,0,0,0.1); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-scroll { flex: 1; overflow-y: auto; overflow-x: hidden; }
        .nav-link { color: rgba(255,255,255,0.8); font-size: 0.75rem; padding: 12px 20px; font-weight: 500; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link:hover, .nav-link.active { background: var(--mcp-dark); color: #fff; }

        /* Content Styling */
        #content { flex: 1; height: 100vh; overflow-y: auto; display: flex; flex-direction: column; background: #f8f9fa; }
        .topbar { background: #fff; border-bottom: 1px solid #e3e6f0; padding: 10px 20px; position: sticky; top: 0; z-index: 1030; }

        /* Mobile Adjustments */
        @media (max-width: 992px) {
            #sidebar { margin-left: -260px; position: fixed; height: 100%; }
            #sidebar.active { margin-left: 0; }
            .overlay { display: none; position: fixed; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1035; }
            .overlay.active { display: block; }
        }

        .card { border: none; border-radius: 8px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
        .bg-purple { background-color: #6f42c1 !important; }
        .sidebar-scroll::-webkit-scrollbar { width: 5px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="wrapper">
    <nav id="sidebar">
        <div class="sidebar-header text-center">
            <div class="d-flex align-items-center justify-content-center gap-2">
                <img src="../../assets/img/logo_mcp.png" alt="Logo" style="width: 32px;">
                <h6 class="fw-bold m-0 text-white">MCP SYSTEM</h6>
            </div>
            <small class="opacity-50 text-white small">ADMIN PANEL</small>
        </div>

        <div class="sidebar-scroll">
            <ul class="nav flex-column">
                <!--<li class="nav-item"><a href="../../index.php" class="nav-link"><i class="fas fa-arrow-left me-2"></i> Kembali Ke App</a></li>-->
                <li class="nav-item"><a href="users.php" class="nav-link active text-warning"><i class="fas fa-users-cog me-2"></i> Manajemen User</a></li>
                <li class="nav-item"><a href="log_activity.php" class="nav-link"><i class="fas fa-file-alt me-2"></i> Activity Log</a></li>
            </ul>
        </div>

        <div class="p-3 border-top border-white-50 text-center text-white-50 small">
            &copy; <?= date("Y") ?> MCP IT Team
        </div>
    </nav>

    <div id="content">
        <header class="topbar d-flex justify-content-between align-items-center">
            <button type="button" id="sidebarCollapse" class="btn btn-primary d-lg-none">
                <i class="fas fa-bars"></i>
            </button>
            <div class="fw-bold text-primary d-none d-sm-block">
                <i class="fas fa-shield-alt me-2"></i> ADMINISTRATOR AREA
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="small text-muted fw-bold d-none d-md-block"><?= date('d F Y') ?></span>
                <a href="../../auth/logout.php" class="btn btn-danger btn-sm fw-bold">LOGOUT</a>
            </div>
        </header>

        <div class="container-fluid p-3 p-md-4">
            
            <h4 class="fw-bold text-uppercase mb-4">Manajemen Akses Pengguna</h4>

            <?php if (isset($_GET['pesan'])): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php
                    $msg = ['berhasil' => 'User baru berhasil ditambahkan.', 'update' => 'Data user berhasil diperbarui.', 'hapus' => 'User berhasil dihapus.'];
                    echo $msg[$_GET['pesan']] ?? 'Proses selesai.';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white fw-bold text-primary py-3">
                    <i class="fas fa-plus-circle me-2"></i> Registrasi User Baru
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="small fw-bold">Username</label>
                                <input type="text" name="username" class="form-control" placeholder="Contoh: fortuno" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="small fw-bold">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="form-control" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="small fw-bold">Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="small fw-bold">Role</label>
                                <select name="role" id="role_select" class="form-select" required>
                                    <option value="">-- Pilih Hak Akses --</option>
                                    <?php foreach ($daftar_role as $val => $label): ?>
                                        <option value="<?= $val ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="small fw-bold">Bagian</label>
                                <select name="bagian" id="bagian_select" class="form-select" required>
                                    <?php foreach ($daftar_bagian as $b): ?>
                                        <option value="<?= $b ?>"><?= $b ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 d-flex align-items-end">
                                <button name="simpan" class="btn btn-primary w-100 fw-bold py-2">
                                    <i class="fas fa-save me-2"></i> SIMPAN USER
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm mb-5">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 fw-bold text-primary">DAFTAR PENGGUNA TERDAFTAR</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="ps-4">Username</th>
                                    <th>Nama Lengkap</th>
                                    <th>Role</th>
                                    <th>Bagian</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q = mysqli_query($koneksi, "SELECT * FROM users ORDER BY id_user DESC");
                                while ($u = mysqli_fetch_assoc($q)):
                                    $badge_class = $badge_role[$u['role']] ?? 'bg-secondary';
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold"><?= htmlspecialchars($u['username']) ?></td>
                                    <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                                    <td><span class="badge rounded-pill <?= $badge_class ?>"><?= strtoupper($u['role']) ?></span></td>
                                    <td><?= $u['bagian'] ?></td>
                                    <td>
                                        <span class="badge <?= $u['status_aktif'] == 'AKTIF' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $u['status_aktif'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-warning btn-sm btn-edit fw-bold" 
                                            data-id="<?= $u['id_user'] ?>" 
                                            data-nama="<?= htmlspecialchars($u['nama_lengkap']) ?>" 
                                            data-role="<?= $u['role'] ?>" 
                                            data-bagian="<?= $u['bagian'] ?>" 
                                            data-status="<?= $u['status_aktif'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?hapus=<?= $u['id_user'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Hapus user ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST">
                <div class="modal-header bg-warning">
                    <h6 class="modal-title fw-bold">EDIT DATA USER</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_user" id="edit_id_user">
                    <div class="mb-3">
                        <label class="small fw-bold">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Password Baru (Opsional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ganti">
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="small fw-bold">Role</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <?php foreach ($daftar_role as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <label class="small fw-bold">Bagian</label>
                            <select name="bagian" id="edit_bagian" class="form-select" required>
                                <?php foreach ($daftar_bagian as $b): ?><option value="<?= $b ?>"><?= $b ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">Status Keaktifan</label>
                        <select name="status_aktif" id="edit_status" class="form-select">
                            <option value="AKTIF">AKTIF</option>
                            <option value="NONAKTIF">NONAKTIF</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update" class="btn btn-primary px-4 fw-bold">UPDATE DATA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const btn = document.getElementById('sidebarCollapse');

        function toggleSidebar() { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); }
        btn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        // Auto-mapping Role ke Bagian
        const roleMap = { 'admin_gudang':'Gudang', 'bagian_pembelian':'Pembelian', 'manager':'Manager', 'pemesan_pr_besar':'Produksi', 'finance':'Finance' };
        document.getElementById('role_select').addEventListener('change', function() {
            const bagian = roleMap[this.value] || '';
            const bSel = document.getElementById('bagian_select');
            for(let o of bSel.options) { if(o.value === bagian) { o.selected = true; break; } }
        });

        // Trigger Edit Modal
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_id_user').value = this.dataset.id;
                document.getElementById('edit_nama').value = this.dataset.nama;
                document.getElementById('edit_status').value = this.dataset.status;
                document.getElementById('edit_role').value = this.dataset.role;
                document.getElementById('edit_bagian').value = this.dataset.bagian;
                new bootstrap.Modal(document.getElementById('modalEditUser')).show();
            });
        });
    });
</script>
</body>
</html>