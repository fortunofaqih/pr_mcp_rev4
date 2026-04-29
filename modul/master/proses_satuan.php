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
    $nama_satuan = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_satuan']));

    // 1. Cek apakah nama satuan sudah ada di database (agar tidak duplikat)
    $cek_dulu = mysqli_query($koneksi, "SELECT * FROM master_satuan WHERE nama_satuan = '$nama_satuan'");
    
    if (mysqli_num_rows($cek_dulu) > 0) {
        // Jika sudah ada, kembalikan ke halaman satuan.php dengan pesan 'ada'
        header("location:satuan.php?pesan=ada");
    } else {
        // 2. Jika belum ada, lakukan insert
        $query = mysqli_query($koneksi, "INSERT INTO master_satuan (nama_satuan) VALUES ('$nama_satuan')");

        if ($query) {
            // Jika berhasil
            header("location:satuan.php?pesan=berhasil");
        } else {
            // Jika gagal sistem
            header("location:satuan.php?pesan=gagal");
        }
    }

} else {
    // Jika mencoba akses langsung tanpa form
    header("location:satuan.php");
}
?>