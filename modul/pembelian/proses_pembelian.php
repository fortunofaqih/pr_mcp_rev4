<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

$id_request   = $_POST['id_request'];
$tgl_beli     = date('Y-m-d');
$supplier     = strtoupper(mysqli_real_escape_string($koneksi, $_POST['supplier']));
$harga_satuan = $_POST['harga_satuan'];
$total_harga  = $_POST['total_harga'];
$driver       = strtoupper(mysqli_real_escape_string($koneksi, $_POST['driver']));
$plat_nomor   = strtoupper(mysqli_real_escape_string($koneksi, $_POST['plat_nomor']));
$id_user_beli = $_SESSION['id_user'];

// 1. Masukkan data ke tabel pembelian (Buku Pembelian)
$query_beli = mysqli_query($koneksi, "INSERT INTO pembelian (id_request, tgl_beli, supplier, harga_satuan, total_harga, driver, plat_nomor, id_user_beli) 
    VALUES ('$id_request', '$tgl_beli', '$supplier', '$harga_satuan', '$total_harga', '$driver', '$plat_nomor', '$id_user_beli')");

if($query_beli){
    // 2. Update status di tabel purchase_request menjadi SELESAI
    mysqli_query($koneksi, "UPDATE purchase_request SET status_request='SELESAI' WHERE id_request='$id_request'");
    
    header("location:index.php?pesan=berhasil");
} else {
    echo "Gagal input: " . mysqli_error($koneksi);
}
?>