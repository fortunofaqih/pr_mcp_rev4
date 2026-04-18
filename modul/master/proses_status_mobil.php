<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    $status_sekarang = $_GET['status'];
    $user_login = $_SESSION['nama'];

    $status_baru = ($status_sekarang == 'AKTIF') ? 'NONAKTIF' : 'AKTIF';

    // Update status sekaligus catat updated_by
    $query = "UPDATE master_mobil SET status_aktif = '$status_baru', updated_by = '$user_login' WHERE id_mobil = '$id'";

    if (mysqli_query($koneksi, $query)) {
        header("location:data_mobil.php?pesan=status_berhasil");
    } else {
        echo "Error: " . mysqli_error($koneksi);
    }
} else {
    header("location:data_mobil.php");
}
?>