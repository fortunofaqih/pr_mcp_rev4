<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
if (!isset($koneksi) || !$koneksi) {
    die("Koneksi database tidak tersedia.");
}
$id = $_GET['id'];
$nama_satuan = mysqli_real_escape_string($koneksi, strtoupper($_GET['nama_satuan']));

$query = mysqli_query($koneksi, "UPDATE master_satuan SET nama_satuan='$nama_satuan' WHERE id_satuan='$id'");

if($query){
    header("location:data_satuan.php?pesan=update");
} else {
    header("location:data_satuan.php?pesan=gagal");
}
?>