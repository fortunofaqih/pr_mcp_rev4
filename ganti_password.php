<?php
session_start();
include 'config/koneksi.php';
include 'auth/check_session.php';


if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$id_user      = $_SESSION['id_user'];
$username     = $_SESSION['username'];
$pesan        = '';
$tipe_pesan   = '';

// ════════════════════════════════════════════════════════════════
// PROSES GANTI PASSWORD (POST)
// ════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pw_lama  = $_POST['password_lama']      ?? '';
    $pw_baru  = $_POST['password_baru']      ?? '';
    $pw_ulang = $_POST['password_konfirmasi'] ?? '';

    // ── Validasi input tidak boleh kosong ──
    if (empty($pw_lama) || empty($pw_baru) || empty($pw_ulang)) {
        $pesan      = 'Semua kolom wajib diisi.';
        $tipe_pesan = 'danger';

    // ── Validasi panjang minimal ──
    } elseif (strlen($pw_baru) < 6) {
        $pesan      = 'Password baru minimal 6 karakter.';
        $tipe_pesan = 'danger';

    // ── Validasi konfirmasi cocok ──
    } elseif ($pw_baru !== $pw_ulang) {
        $pesan      = 'Password baru dan konfirmasi tidak cocok.';
        $tipe_pesan = 'danger';

    } else {
        // ── Ambil password hash dari DB ──
        $id_esc  = intval($id_user);
        $res     = mysqli_query($koneksi, "SELECT password FROM users WHERE id_user = '$id_esc' AND status_aktif = 'AKTIF'");
        $user_db = mysqli_fetch_assoc($res);

        if (!$user_db) {
            $pesan      = 'Akun tidak ditemukan.';
            $tipe_pesan = 'danger';

        // ── Verifikasi password lama dengan password_verify (bcrypt) ──
        } elseif (!password_verify($pw_lama, $user_db['password'])) {
            $pesan      = 'Password lama yang Anda masukkan salah.';
            $tipe_pesan = 'danger';

        // ── Cegah password baru sama dengan password lama ──
        } elseif (password_verify($pw_baru, $user_db['password'])) {
            $pesan      = 'Password baru tidak boleh sama dengan password lama.';
            $tipe_pesan = 'warning';

        } else {
            // ── Hash password baru dengan bcrypt lalu simpan ──
            $hash_baru = password_hash($pw_baru, PASSWORD_BCRYPT);
            $hash_esc  = mysqli_real_escape_string($koneksi, $hash_baru);

            $update = mysqli_query($koneksi,
                "UPDATE users SET password = '$hash_esc' WHERE id_user = '$id_esc'"
            );

            if ($update) {
                $pesan      = 'Password berhasil diubah. Silakan gunakan password baru Anda untuk login berikutnya.';
                $tipe_pesan = 'success';
            } else {
                $pesan      = 'Gagal menyimpan perubahan: ' . mysqli_error($koneksi);
                $tipe_pesan = 'danger';
            }
        }
    }
}

// Ambil nama lengkap untuk ditampilkan
$id_esc   = intval($id_user);
$res_info = mysqli_query($koneksi, "SELECT nama_lengkap, role, bagian FROM users WHERE id_user = '$id_esc'");
$info     = mysqli_fetch_assoc($res_info);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --mcp-blue: #0000FF; --mcp-dark: #00008B; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-mcp { background: var(--mcp-blue); }

        .card-ganti-pw {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.10);
            max-width: 480px;
            width: 100%;
        }
        .card-header-pw {
            background: linear-gradient(135deg, var(--mcp-blue), var(--mcp-dark));
            border-radius: 16px 16px 0 0 !important;
            padding: 24px 28px;
        }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #444; }
        .input-group-text {
            background: #f1f4f9;
            border-right: none;
            color: #555;
        }
        .form-control {
            border-left: none;
            font-size: 0.9rem;
        }
        .form-control:focus {
            border-color: var(--mcp-blue);
            box-shadow: 0 0 0 3px rgba(0,0,255,0.1);
        }
        .btn-toggle-pw {
            background: #f1f4f9;
            border: 1px solid #ced4da;
            border-left: none;
            color: #555;
            cursor: pointer;
            padding: 0 12px;
            border-radius: 0 6px 6px 0;
        }
        .btn-toggle-pw:hover { background: #e2e6ea; }

        /* Indikator kekuatan password */
        .pw-strength-bar {
            height: 5px;
            border-radius: 4px;
            transition: width 0.3s, background 0.3s;
            width: 0%;
        }
        .pw-strength-text { font-size: 0.72rem; }

        .info-user {
            background: #f1f4f9;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.82rem;
        }
        .role-badge {
            font-size: 0.7rem;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 700;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-mcp mb-5 py-3">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white fs-6">
            <i class="fas fa-key me-2"></i> GANTI PASSWORD
        </span>
        <a href="index.php" class="btn btn-sm btn-danger fw-bold">
            <i class="fas fa-rotate-left me-1"></i> KEMBALI
        </a>
    </div>
</nav>

<!-- Form -->
<div class="d-flex justify-content-center px-3">
    <div class="card-ganti-pw card">

        <!-- Header Card -->
        <div class="card-header-pw text-white">
            <div class="d-flex align-items-center gap-3">
                <div style="width:52px; height:52px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:1.4rem;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <div class="fw-bold fs-6"><?= htmlspecialchars(strtoupper($info['nama_lengkap'] ?? $username)) ?></div>
                    <div style="font-size:0.78rem; opacity:0.85;">@<?= htmlspecialchars($username) ?></div>
                </div>
            </div>
        </div>

        <div class="card-body p-4">

            <!-- Info Akun -->
            <div class="info-user mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted mb-1" style="font-size:0.72rem;">ROLE & BAGIAN</div>
                    <span class="role-badge bg-primary text-white me-1"><?= htmlspecialchars($info['role'] ?? '-') ?></span>
                    <span class="role-badge bg-secondary text-white"><?= htmlspecialchars($info['bagian'] ?? '-') ?></span>
                </div>
                <i class="fas fa-id-badge text-primary fa-2x opacity-50"></i>
            </div>

            <?php if ($pesan): ?>
            <!-- Alert PHP (fallback jika JS mati) -->
            <div class="alert alert-<?= $tipe_pesan ?> alert-dismissible fade show small py-2" role="alert">
                <i class="fas fa-<?= $tipe_pesan === 'success' ? 'check-circle' : ($tipe_pesan === 'warning' ? 'exclamation-triangle' : 'times-circle') ?> me-1"></i>
                <?= htmlspecialchars($pesan) ?>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="formGantiPw" autocomplete="off">

                <!-- Password Lama -->
                <div class="mb-3">
                    <label class="form-label">Password Lama</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" name="password_lama"
                               id="pw_lama" placeholder="Masukkan password lama" required>
                        <button type="button" class="btn-toggle-pw" onclick="togglePw('pw_lama', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Password Baru -->
                <div class="mb-1">
                    <label class="form-label">Password Baru</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock-open"></i></span>
                        <input type="password" class="form-control" name="password_baru"
                               id="pw_baru" placeholder="Minimal 6 karakter" required
                               oninput="cekKekuatan(this.value)">
                        <button type="button" class="btn-toggle-pw" onclick="togglePw('pw_baru', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Indikator Kekuatan Password -->
                <div class="mb-3 px-1">
                    <div class="bg-light rounded" style="height:5px;">
                        <div class="pw-strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="pw-strength-text text-muted mt-1" id="strengthText"></div>
                </div>

                <!-- Konfirmasi Password Baru -->
                <div class="mb-4">
                    <label class="form-label">Konfirmasi Password Baru</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-check-double"></i></span>
                        <input type="password" class="form-control" name="password_konfirmasi"
                               id="pw_konfirmasi" placeholder="Ulangi password baru" required
                               oninput="cekKonfirmasi(this.value)">
                        <button type="button" class="btn-toggle-pw" onclick="togglePw('pw_konfirmasi', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="pw-strength-text mt-1" id="konfirmasiInfo"></div>
                </div>

                <!-- Tombol Submit -->
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary fw-bold py-2" id="btnSimpan">
                        <i class="fas fa-save me-2"></i> SIMPAN PASSWORD BARU
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary fw-bold">
                        <i class="fas fa-times me-1"></i> BATAL
                    </a>
                </div>

            </form>
        </div>

        <div class="card-footer bg-transparent text-center py-3">
            <small class="text-muted">
                <i class="fas fa-shield-alt me-1 text-primary"></i>
                Password disimpan dengan enkripsi <strong>bcrypt</strong> — aman & tidak bisa dibaca.
            </small>
        </div>

    </div>
</div>

<br><br>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

// ── Toggle tampil/sembunyikan password ───────────────────────
function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon  = btn.querySelector('i');
    if (input.type === 'password') {
        input.type   = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type   = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// ── Indikator kekuatan password ──────────────────────────────
function cekKekuatan(val) {
    const bar  = document.getElementById('strengthBar');
    const teks = document.getElementById('strengthText');
    let skor   = 0;

    if (val.length >= 6)                          skor++;
    if (val.length >= 10)                         skor++;
    if (/[A-Z]/.test(val))                        skor++;
    if (/[0-9]/.test(val))                        skor++;
    if (/[^A-Za-z0-9]/.test(val))                 skor++;

    const level = [
        { w: '20%',  bg: '#dc3545', label: 'Sangat Lemah' },
        { w: '40%',  bg: '#fd7e14', label: 'Lemah'        },
        { w: '60%',  bg: '#ffc107', label: 'Cukup'        },
        { w: '80%',  bg: '#20c997', label: 'Kuat'         },
        { w: '100%', bg: '#198754', label: 'Sangat Kuat'  },
    ];

    if (val.length === 0) {
        bar.style.width      = '0';
        bar.style.background = '';
        teks.textContent     = '';
        return;
    }

    const idx = Math.min(skor - 1, 4);
    bar.style.width      = level[idx].w;
    bar.style.background = level[idx].bg;
    teks.style.color     = level[idx].bg;
    teks.textContent     = '🔒 Kekuatan: ' + level[idx].label;

    // Update info konfirmasi jika sudah diisi
    cekKonfirmasi(document.getElementById('pw_konfirmasi').value);
}

// ── Cek kesesuaian konfirmasi password ───────────────────────
function cekKonfirmasi(val) {
    const info   = document.getElementById('konfirmasiInfo');
    const pwBaru = document.getElementById('pw_baru').value;

    if (val.length === 0) { info.textContent = ''; return; }

    if (val === pwBaru) {
        info.style.color = '#198754';
        info.textContent = '✅ Password cocok';
    } else {
        info.style.color = '#dc3545';
        info.textContent = '❌ Password tidak cocok';
    }
}

// ── SweetAlert untuk hasil proses dari PHP ───────────────────
<?php if ($pesan && $tipe_pesan === 'success'): ?>
Swal.fire({
    icon             : 'success',
    title            : 'BERHASIL!',
    text             : '<?= addslashes($pesan) ?>',
    confirmButtonColor: '#0000FF',
    confirmButtonText: 'OK'
});
<?php elseif ($pesan && $tipe_pesan === 'danger'): ?>
Swal.fire({
    icon             : 'error',
    title            : 'GAGAL!',
    text             : '<?= addslashes($pesan) ?>',
    confirmButtonColor: '#d33'
});
<?php elseif ($pesan && $tipe_pesan === 'warning'): ?>
Swal.fire({
    icon             : 'warning',
    title            : 'PERHATIAN!',
    text             : '<?= addslashes($pesan) ?>',
    confirmButtonColor: '#ffc107'
});
<?php endif; ?>

// ── Konfirmasi sebelum submit ────────────────────────────────
document.getElementById('formGantiPw').addEventListener('submit', function (e) {
    const pwBaru    = document.getElementById('pw_baru').value;
    const pwKonfirm = document.getElementById('pw_konfirmasi').value;

    // Validasi sisi client sebelum kirim ke server
    if (pwBaru !== pwKonfirm) {
        e.preventDefault();
        Swal.fire({
            icon             : 'error',
            title            : 'Tidak Cocok!',
            text             : 'Password baru dan konfirmasi tidak sama.',
            confirmButtonColor: '#d33'
        });
        return;
    }

    if (pwBaru.length < 6) {
        e.preventDefault();
        Swal.fire({
            icon             : 'error',
            title            : 'Terlalu Pendek!',
            text             : 'Password minimal 6 karakter.',
            confirmButtonColor: '#d33'
        });
        return;
    }
});
</script>
</body>
</html>
