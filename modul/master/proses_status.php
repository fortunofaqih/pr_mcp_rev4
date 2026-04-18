<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';
$id     = $_GET['id'];
$status = $_GET['status'];

// Toggle status
$status_baru = ($status == 'AKTIF') ? 'NONAKTIF' : 'AKTIF';

$query = mysqli_query($koneksi, "UPDATE master_barang SET status_aktif = '$status_baru' WHERE id_barang = '$id'");

if($query) {
    header("location:barang.php?pesan=update_status");
} else {
    echo "Gagal: " . mysqli_error($koneksi);
}
?>