<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
if (!isset($koneksi) || !$koneksi) {
    die("Koneksi database tidak tersedia.");
}
$id = $_GET['id'];
$nama_kategori = mysqli_real_escape_string($koneksi, strtoupper($_GET['nama_kategori']));

$query = mysqli_query($koneksi, "UPDATE master_kategori SET nama_kategori='$nama_kategori' WHERE id_kategori='$id'");

if($query){
    header("location:data_kategori.php?pesan=update");
} else {
    header("location:data_kategori.php?pesan=gagal");
}
?>