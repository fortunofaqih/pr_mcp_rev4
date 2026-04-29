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
    $nama_rak = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_rak']));

    // 1. Cek apakah nama rak sudah ada di database (agar tidak duplikat)
    $cek_dulu = mysqli_query($koneksi, "SELECT * FROM master_rak WHERE nama_rak = '$nama_rak'");
    
    if (mysqli_num_rows($cek_dulu) > 0) {
        // Jika sudah ada, kembalikan ke halaman rak.php dengan pesan 'ada'
        header("location:rak.php?pesan=ada");
    } else {
        // 2. Jika belum ada, lakukan insert
        $query = mysqli_query($koneksi, "INSERT INTO master_rak (nama_rak) VALUES ('$nama_rak')");

        if ($query) {
            // Jika berhasil
            header("location:rak.php?pesan=berhasil");
        } else {
            // Jika gagal sistem
            header("location:rak.php?pesan=gagal");
        }
    }

} else {
    // Jika mencoba akses langsung tanpa form
    header("location:rak.php");
}
?>