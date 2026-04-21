<?php
session_start();
include 'config/koneksi.php';

// Cegah user yang sudah login masuk lagi
if (isset($_SESSION['status']) && $_SESSION['status'] == 'login') {
    header("location:index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mutiara Cahaya Plastindo</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --mcp-blue: #0000FF;
            --mcp-dark: #00008B;
        }
        body {
            background-color: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background-color: white;
            border-bottom: none;
            padding-top: 30px;
            text-align: center;
        }
        .logo-img { max-width: 120px; margin-bottom: 15px; }
        .btn-mcp {
            background-color: var(--mcp-blue);
            color: white;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 12px;
            border-radius: 8px;
            transition: 0.3s;
        }
        .btn-mcp:hover {
            background-color: var(--mcp-dark);
            color: white;
            transform: translateY(-2px);
        }
        .form-control, .form-select {
            padding: 12px;
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--mcp-blue);
            box-shadow: 0 0 0 0.25rem rgba(0,0,255,0.1);
        }
        .footer-text {
            font-size: 0.85rem;
            color: #666;
            text-align: center;
            margin-top: 20px;
        }
        .password-container { position: relative; }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            z-index: 10;
        }
    </style>
</head>
<body>

<div class="login-card card">
    <div class="card-header">
        <img src="assets/logo-1.png" alt="MCP Logo" class="logo-img">
        <h4 class="fw-bold" style="color: var(--mcp-blue);">PURCHASE SYSTEM</h4>
        <p class="text-muted small">Mutiaracahaya Plastindo</p>
    </div>

    <div class="card-body px-4 pb-4">
        <form action="auth/cek_login.php" method="POST">

            <!-- PESAN ERROR / INFO -->
            <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'gagal'): ?>
                <div class="alert alert-danger text-center small py-2">
                    <i class="fas fa-times-circle me-1"></i> USERNAME ATAU PASSWORD SALAH!
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'nonaktif'): ?>
                <div class="alert alert-warning text-center small py-2">
                    <i class="fas fa-ban me-1"></i> AKUN ANDA TIDAK AKTIF. HUBUNGI ADMINISTRATOR.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'sesi_ganda'): ?>
                <div class="alert alert-warning text-center small py-2">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    SESI ANDA DIAKHIRI. Akun ini baru saja login di perangkat lain.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'timeout'): ?>
            <div class="alert alert-warning text-center small py-2">
                <i class="fas fa-clock me-1"></i>
                SESI BERAKHIR. Keluar otomatis karena idle selama 15 menit.
            </div>
        <?php endif; ?>

            <?php if (isset($_GET['pesan']) && $_GET['pesan'] == 'akses_ditolak'): ?>
                <div class="alert alert-danger text-center small py-2">
                    <i class="fas fa-lock me-1"></i> ANDA TIDAK MEMILIKI AKSES UNTUK ROLE TERSEBUT.
                </div>
            <?php endif; ?>

            <!-- USERNAME -->
            <div class="mb-3">
                <label class="form-label small fw-bold">USERNAME</label>
                <input type="text" name="username" class="form-control"
                       placeholder="MASUKKAN USERNAME" required autofocus>
            </div>

            <!-- PASSWORD -->
            <div class="mb-3">
                <label class="form-label small fw-bold">PASSWORD</label>
                <div class="password-container">
                    <input type="password" name="password" id="password"
                           class="form-control" placeholder="MASUKKAN PASSWORD" required>
                    <i class="fa-solid fa-eye toggle-password" id="eyeIcon"></i>
                </div>
            </div>

            <!-- DROPDOWN MASUK SEBAGAI -->
            <div class="mb-4">
                <label class="form-label small fw-bold">MASUK SEBAGAI</label>
                <select name="login_sebagai" class="form-select" required>
                    <option value="" disabled selected>-- PILIH ROLE --</option>
                    <option value="administrator">👑 Administrator</option>
                    <option value="manager">🏢 Manager/Approval</option>
                    <option value="admin_gudang">📦 Admin Gudang</option>
                    <option value="bagian_pembelian">🛒 Bagian Pembelian</option>
                    <option value="pemesan_pr_besar">📋 Pemesan PR Besar</option>
                    <option value="finance">💰 Finance</option>
                </select>
                <div class="form-text text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    Pilih sesuai tugas Anda hari ini.
                </div>
            </div>

            <button type="submit" class="btn btn-mcp w-100">
                <i class="fas fa-sign-in-alt me-2"></i> MASUK KE SISTEM
            </button>
        </form>

        <div class="footer-text">
            &copy; <?= date('Y') ?> MUTIARACAHAYA PLASTINDO
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle show/hide password
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    eyeIcon.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Highlight pilihan dropdown sesuai role
    const roleSelect = document.querySelector('select[name="login_sebagai"]');
    roleSelect.addEventListener('change', function() {
        this.style.fontWeight = 'bold';
        const colors = {
            'manager'          : '#0000FF',
            'admin_gudang'     : '#198754',
            'pemesan_pr_besar' : '#fd7e14',
            'finance'          : '#6f42c1',
        };
        this.style.color = colors[this.value] || '#333';
    });
</script>
</body>
</html>