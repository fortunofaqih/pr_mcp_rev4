<?php
session_start();
include '../config/koneksi.php';

// Validasi method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("location:../login.php");
    exit();
}

// Ambil input
$username      = mysqli_real_escape_string($koneksi, $_POST['username']);
$password      = $_POST['password'];
$login_sebagai = mysqli_real_escape_string($koneksi, $_POST['login_sebagai']);

// Validasi: pastikan nilai login_sebagai adalah role yang valid
$role_valid = ['administrator', 'manager', 'admin_gudang', 'bagian_pembelian', 'pemesan_pr_besar', 'finance'];
if (!in_array($login_sebagai, $role_valid)) {
    header("location:../login.php?pesan=gagal");
    exit();
}

// Query user
$query = mysqli_query($koneksi,
    "SELECT * FROM users WHERE username='$username' LIMIT 1"
);

if (mysqli_num_rows($query) === 1) {

    $data = mysqli_fetch_assoc($query);

    // Verifikasi password
    if (password_verify($password, $data['password'])) {

        // Cek status akun
        if ($data['status_aktif'] !== 'AKTIF') {
            header("location:../login.php?pesan=nonaktif");
            exit();
        }

        // ============================================================
        // VALIDASI ROLE: Apakah user boleh login dengan role yang dipilih?
        //
        // Aturan:
        // - Role asli user harus cocok DENGAN yang dipilih, KECUALI:
        // - Jika user punya akses_gudang='Y', dia boleh pilih 'admin_gudang'
        //   meski role aslinya bukan admin_gudang
        // ============================================================
        $role_asli    = $data['role'];
        $akses_gudang = $data['akses_gudang']; // 'Y' atau 'N'

        $boleh_login = false;

        if ($login_sebagai === $role_asli) {
            // Kasus normal: role yang dipilih = role asli akun
            $boleh_login = true;

        } elseif ($login_sebagai === 'admin_gudang' && $akses_gudang === 'Y') {
            // Kasus khusus: user punya akses gudang tambahan
            $boleh_login = true;

        } elseif ($role_asli === 'administrator') {
            // Administrator bisa masuk sebagai role apapun
            $boleh_login = true;
        }
      

        if (!$boleh_login) {
            header("location:../login.php?pesan=akses_ditolak");
            exit();
        }

        // ============================================================
        // ANTI LOGIN GANDA: Generate session token unik
        // ============================================================
        $session_token = bin2hex(random_bytes(32));

        mysqli_query($koneksi,
            "UPDATE users SET session_token='$session_token' WHERE id_user='{$data['id_user']}'"
        );

        // ============================================================
        // SET SESSION
        // ============================================================
        $_SESSION['id_user']       = $data['id_user'];
        $_SESSION['username']      = $data['username'];
        $_SESSION['nama']          = $data['nama_lengkap'];
        $_SESSION['role']          = $login_sebagai;
        $_SESSION['role_asli']     = $role_asli;
        $_SESSION['bagian']        = $data['bagian'];
        $_SESSION['akses_gudang']  = $akses_gudang;
        $_SESSION['status']        = 'login';
        $_SESSION['session_token'] = $session_token;

        // ============================================================
        // REDIRECT berdasarkan role yang DIPILIH
        // ============================================================
        if ($login_sebagai === 'administrator') {
            header("location:../modul/master/users.php");

        } elseif ($login_sebagai === 'pemesan_pr_besar') {
            header("location:../index.php");

        } elseif ($login_sebagai === 'finance') {
            header("location:../modul/finance/index.php");

        } else {
            header("location:../index.php");
        }
        exit();

    } else {
        header("location:../login.php?pesan=gagal");
        exit();
    }

} else {
    header("location:../login.php?pesan=gagal");
    exit();
}