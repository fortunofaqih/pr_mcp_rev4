<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if (isset($_POST['plat_nomor'])) {
    $plat_nomor = mysqli_real_escape_string($koneksi, $_POST['plat_nomor']);
    
    $query = mysqli_query($koneksi, "SELECT id_mobil FROM master_mobil WHERE plat_nomor = '$plat_nomor'");
    
    if (mysqli_num_rows($query) > 0) {
        echo "ada"; // Mengirim respon jika plat sudah terdaftar
    } else {
        echo "aman"; // Mengirim respon jika plat tersedia
    }
}
?>