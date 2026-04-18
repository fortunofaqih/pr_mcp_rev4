<?php
// ================================================================
// auth/check_session.php
// Include file ini di AWAL setiap halaman yang butuh login.
//
// Pastikan session_start() dan include koneksi sudah dipanggil
// SEBELUM include file ini.
//
// Contoh penggunaan:
//   session_start();
//   include '../../config/koneksi.php';
//   include '../../auth/check_session.php';
// ================================================================

// 1. Cek apakah sudah login
if (!isset($_SESSION['status']) || $_SESSION['status'] !== 'login') {
    $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2);
    header("location:" . str_repeat('../', $depth) . "login.php?pesan=belum_login");
    exit();
}

// 2. Cek validitas token (anti login ganda)
if (isset($_SESSION['id_user'], $_SESSION['session_token'], $koneksi)) {
    $id_user       = (int) $_SESSION['id_user'];
    $session_token = mysqli_real_escape_string($koneksi, $_SESSION['session_token']);

    $cek = mysqli_query($koneksi,
        "SELECT session_token FROM users WHERE id_user='$id_user' LIMIT 1"
    );

    if ($cek && mysqli_num_rows($cek) === 1) {
        $db_data = mysqli_fetch_assoc($cek);

        // Token berbeda → ada login baru dari perangkat lain → paksa logout
        if ($db_data['session_token'] !== $session_token) {
            session_unset();
            session_destroy();
            $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2);
            header("location:" . str_repeat('../', $depth) . "login.php?pesan=sesi_ganda");
            exit();
        }
    }
}