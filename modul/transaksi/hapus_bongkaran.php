<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") { header("location:../../login.php"); exit; }

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    $query = mysqli_query($koneksi, "DELETE FROM tr_bongkaran WHERE id_bongkaran = '$id'");
    
    $pesan = ($query) ? "Data berhasil dihapus" : "Gagal menghapus data";
    echo "<script>alert('$pesan'); window.location='bongkaran.php';</script>";
}
?>