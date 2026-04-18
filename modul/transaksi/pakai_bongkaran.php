<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if(isset($_POST['proses_ambil'])){
    $id_bongkaran = $_POST['id_bongkaran'];
    $qty_ambil    = $_POST['qty_ambil'];
    $penerima     = $_POST['penerima'];
    $tgl          = date('Y-m-d');

    // 1. Cek stok sisa dulu
    $cek = mysqli_fetch_array(mysqli_query($koneksi, "SELECT qty_sisa FROM tr_bongkaran WHERE id_bongkaran='$id_bongkaran'"));
    
    if($qty_ambil > $cek['qty_sisa']){
        echo "<script>alert('Stok tidak cukup!'); window.location='bongkaran.php';</script>";
    } else {
        // 2. Kurangi stok di tabel utama
        mysqli_query($koneksi, "UPDATE tr_bongkaran SET qty_sisa = qty_sisa - $qty_ambil WHERE id_bongkaran='$id_bongkaran'");
        
        // 3. Catat di tabel histori keluar
        mysqli_query($koneksi, "INSERT INTO tr_bongkaran_keluar (id_bongkaran, tgl_keluar, qty_keluar, penerima) VALUES ('$id_bongkaran', '$tgl', '$qty_ambil', '$penerima')");
        
        echo "<script>alert('Barang berhasil diambil'); window.location='bongkaran.php';</script>";
    }
}
?>