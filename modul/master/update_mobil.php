<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$id                 = $_POST['id_mobil'];
$plat_nomor         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['plat_nomor']));
$driver_tetap       = strtoupper(mysqli_real_escape_string($koneksi, $_POST['driver_tetap']));
$jenis_kendaraan    = strtoupper(mysqli_real_escape_string($koneksi, $_POST['jenis_kendaraan']));
$kategori_kendaraan = strtoupper(mysqli_real_escape_string($koneksi, $_POST['kategori_kendaraan']));
$merk_tipe          = strtoupper(mysqli_real_escape_string($koneksi, $_POST['merk_tipe']));
$tahun_kendaraan    = strtoupper(mysqli_real_escape_string($koneksi, $_POST['tahun_kendaraan']));
$status             = $_POST['status_aktif'];
$user_login         = $_SESSION['nama']; 

$update = "UPDATE master_mobil SET 
           plat_nomor = '$plat_nomor', 
           driver_tetap = '$driver_tetap', 
           jenis_kendaraan = '$jenis_kendaraan',
           kategori_kendaraan = '$kategori_kendaraan',
           merk_tipe = '$merk_tipe',
           tahun_kendaraan = '$tahun_kendaraan',
           status_aktif = '$status',
           updated_by = '$user_login',
           updated_at = NOW() -- Tambahkan ini agar waktu edit tercatat
           WHERE id_mobil = '$id'";

if (mysqli_query($koneksi, $update)) {
    header("location:data_mobil.php?pesan=update_sukses");
} else {
    echo "Error: " . mysqli_error($koneksi);
}
?>