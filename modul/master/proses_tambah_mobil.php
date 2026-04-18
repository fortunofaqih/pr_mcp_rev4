<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// 1. Tangkap dan Bersihkan Data
$plat_nomor   = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['plat_nomor'])));
$driver_tetap = strtoupper(mysqli_real_escape_string($koneksi, $_POST['driver_tetap']));
$jenis        = strtoupper(mysqli_real_escape_string($koneksi, $_POST['jenis_kendaraan']));
$kategori     = $_POST['kategori_kendaraan'];
$merk         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['merk_tipe']));
$tahun        = $_POST['tahun_kendaraan'];
$status       = isset($_POST['status_aktif']) ? $_POST['status_aktif'] : 'AKTIF';
$user_login   = $_SESSION['nama'];

// 2. Validasi Format Plat Nomor (Server-Side)
// Pattern baru: 
// ^[A-Z]{1,2}      : 1-2 Huruf depan
// \s[0-9]{1,4}     : Spasi diikuti 1-4 angka
// (\s[A-Z]{1,3})?  : (Grup opsional) Spasi diikuti 1-3 huruf belakang
// Simbol \s? berarti spasi bersifat opsional (boleh ada, boleh tidak)
$pattern = "/^[A-Z]{1,2}\s?[0-9]{1,4}(\s?[A-Z]{1,3})?$/";

if (!preg_match($pattern, $plat_nomor)) {
    header("location:mobil.php?pesan=format_plat_salah");
    exit;
}

// 3. Cek apakah Plat Nomor sudah terdaftar (Mencegah Duplikasi)
$cek_duplikat = mysqli_query($koneksi, "SELECT plat_nomor FROM master_mobil WHERE plat_nomor = '$plat_nomor'");
if (mysqli_num_rows($cek_duplikat) > 0) {
    // Kita arahkan ke pesan 'ada' agar muncul peringatan kuning di SweetAlert
    header("location:mobil.php?pesan=ada"); 
    exit;
}

// 4. Proses Insert
$sql = "INSERT INTO master_mobil (plat_nomor, driver_tetap, jenis_kendaraan, kategori_kendaraan, merk_tipe, tahun_kendaraan, status_aktif, created_by) 
        VALUES ('$plat_nomor', '$driver_tetap', '$jenis', '$kategori', '$merk', '$tahun', '$status', '$user_login')";

if (mysqli_query($koneksi, $sql)) {
    // Jika sukses, kembali ke form dengan pesan 'berhasil'
    header("location:mobil.php?pesan=berhasil");
} else {
    error_log(mysqli_error($koneksi));
    header("location:mobil.php?pesan=gagal");
}
?>