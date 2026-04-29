<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

$id = $_GET['id'];

$query = mysqli_query($koneksi, "DELETE FROM master_kategori WHERE id_kategori='$id'");

if($query){
    header("location:data_kategori.php?pesan=hapus");
} else {
    header("location:data_kategori.php?pesan=gagal");
}
?>