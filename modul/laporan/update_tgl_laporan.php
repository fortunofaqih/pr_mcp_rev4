<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($koneksi, $_POST['id']);
    $kat = $_POST['kat'];
    $tgl_baru = mysqli_real_escape_string($koneksi, $_POST['tgl_baru']);

    if ($kat == 'STOK') {
        // Untuk STOK: Update kolom tgl_keluar di tabel bon_permintaan
        $query = "UPDATE bon_permintaan SET tgl_keluar = '$tgl_baru' WHERE id_bon = '$id'";
        $exec = mysqli_query($koneksi, $query);
    } else {
        // Untuk BELI: Update kolom tgl_beli_barang (sesuai nota) di tabel pembelian
        // Cari dulu relasi no_request dan nama barangnya
        $cek = mysqli_query($koneksi, "SELECT rd.nama_barang_manual, r.no_request 
                                      FROM tr_request_detail rd 
                                      JOIN tr_request r ON rd.id_request = r.id_request 
                                      WHERE rd.id_detail = '$id'");
        $data = mysqli_fetch_assoc($cek);
        
        if ($data) {
            $no_req = $data['no_request'];
            $nama_brg = $data['nama_barang_manual'];
            
            // Update tgl_beli_barang (Tanggal Nota)
            $query = "UPDATE pembelian SET tgl_beli_barang = '$tgl_baru' 
                      WHERE no_request = '$no_req' AND nama_barang_beli = '$nama_brg'";
            $exec = mysqli_query($koneksi, $query);
        } else {
            $exec = false;
        }
    }

    if ($exec) {
        header("location:laporan_mobil.php?pesan=update_tgl_sukses");
    } else {
        echo "Gagal Update. Error: " . mysqli_error($koneksi);
    }
}