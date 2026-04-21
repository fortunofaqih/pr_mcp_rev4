<?php
// ================================================================
// auth/check_session.php
// ================================================================

// 1. Cek apakah sudah login
if (!isset($_SESSION['status']) || $_SESSION['status'] !== 'login') {
    $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2);
    header("location:" . str_repeat('../', $depth) . "login.php?pesan=belum_login");
    exit();
}

// 2. Logika Session Timeout (Server-side)
$timeout_duration = 900; // 15 menit

if (isset($_SESSION['last_activity'])) {
    $elapsed_time = time() - $_SESSION['last_activity'];
    if ($elapsed_time >= $timeout_duration) {
        session_unset();
        session_destroy();
        $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2);
        header("location:" . str_repeat('../', $depth) . "login.php?pesan=timeout");
        exit();
    }
}
// Update waktu terakhir (hanya saat pindah halaman/refresh)
$_SESSION['last_activity'] = time();

// 3. Cek validitas token (anti login ganda)
if (isset($_SESSION['id_user'], $_SESSION['session_token'], $koneksi)) {
    $id_user       = (int) $_SESSION['id_user'];
    $session_token = mysqli_real_escape_string($koneksi, $_SESSION['session_token']);
    $cek = mysqli_query($koneksi, "SELECT session_token FROM users WHERE id_user='$id_user' LIMIT 1");

    if ($cek && mysqli_num_rows($cek) === 1) {
        $db_data = mysqli_fetch_assoc($cek);
        if ($db_data['session_token'] !== $session_token) {
            session_unset();
            session_destroy();
            $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 2);
            header("location:" . str_repeat('../', $depth) . "login.php?pesan=sesi_ganda");
            exit();
        }
    }
}