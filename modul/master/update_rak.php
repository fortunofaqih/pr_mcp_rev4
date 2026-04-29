<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
if (!isset($koneksi) || !$koneksi) {
    die("Koneksi database tidak tersedia.");
}
$id = $_GET['id'];
$nama_rak = mysqli_real_escape_string($koneksi, strtoupper($_GET['nama_rak']));

$query = mysqli_query($koneksi, "UPDATE master_rak SET nama_rak='$nama_rak' WHERE id_rak='$id'");

if($query){
    header("location:data_rak.php?pesan=update");
} else {
    header("location:data_rak.php?pesan=gagal");
}
?>