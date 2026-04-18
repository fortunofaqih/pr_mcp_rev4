<?php
include "../../config/koneksi.php";
include '../../auth/check_session.php';
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    if (mysqli_query($koneksi, "DELETE FROM perbandingan_harga WHERE id_perbandingan = '$id'")) {
        echo "<script>alert('Dihapus!'); window.location='data_perbandingan.php';</script>";
    }
}
?>