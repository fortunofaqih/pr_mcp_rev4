<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Proteksi Keamanan: Hanya Manager atau Administrator yang boleh menghapus log
if ($_SESSION['role'] != 'manager' && $_SESSION['role'] != 'administrator') {
    header("location:log_activity.php?pesan=akses_ditolak");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['clean_type'])) {
    $type = $_POST['clean_type'];
    $ip_user = $_SERVER['REMOTE_ADDR'];
    $username = $_SESSION['username'];

    // Menentukan Query berdasarkan pilihan User
    if ($type == "1_bulan") {
        // Hapus data yang usianya lebih dari 30 hari
        $sql = "DELETE FROM tr_log_activity WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        $rincian = "Pembersihan log yang berusia lebih dari 1 bulan";
    } elseif ($type == "3_bulan") {
        // Hapus data yang usianya lebih dari 90 hari
        $sql = "DELETE FROM tr_log_activity WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)";
        $rincian = "Pembersihan log yang berusia lebih dari 3 bulan";
    } elseif ($type == "semua") {
        // Kosongkan seluruh tabel
        $sql = "TRUNCATE TABLE tr_log_activity";
        $rincian = "Pengosongan seluruh data log aktivitas";
    }

    // Eksekusi penghapusan
    if (mysqli_query($koneksi, $sql)) {
        // SETELAH DIHAPUS, catat aksi pembersihan ini sebagai log baru agar tetap ada jejak auditnya
        $log_baru = "INSERT INTO tr_log_activity (username, aksi, rincian, ip_address) 
                     VALUES ('$username', 'CLEAN LOG', '$rincian', '$ip_user')";
        mysqli_query($koneksi, $log_baru);

        header("location:log_activity.php?pesan=clean_success");
    } else {
        header("location:log_activity.php?pesan=clean_failed");
    }
} else {
    header("location:log_activity.php");
}
?>