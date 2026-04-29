<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if (!isset($koneksi) || !$koneksi) {
    die("Koneksi database tidak tersedia.");
}

// Pastikan data dikirim melalui method POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Ambil data dan ubah ke UPPERCASE agar seragam
    $nama_kategori = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_kategori']));

    // 1. Cek apakah nama kategori sudah ada di database (agar tidak duplikat)
    $cek_dulu = mysqli_query($koneksi, "SELECT * FROM master_kategori WHERE nama_kategori = '$nama_kategori'");
    
    if (mysqli_num_rows($cek_dulu) > 0) {
        // Jika sudah ada, kembalikan ke halaman kategori.php dengan pesan 'ada'
        header("location:kategori.php?pesan=ada");
    } else {
        // 2. Jika belum ada, lakukan insert
        $query = mysqli_query($koneksi, "INSERT INTO master_kategori (nama_kategori) VALUES ('$nama_kategori')");

        if ($query) {
            // Jika berhasil
            header("location:kategori.php?pesan=berhasil");
        } else {
            // Jika gagal sistem
            header("location:kategori.php?pesan=gagal");
        }
    }

} else {
    // Jika mencoba akses langsung tanpa form
    header("location:kategori.php");
}
?>