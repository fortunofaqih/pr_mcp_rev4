<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_bon     = (int)$_POST['id_bon'];
    $id_log     = (int)$_POST['id_log']; 
    $penerima   = strtoupper(mysqli_real_escape_string($koneksi, $_POST['penerima']));
    $qty_baru   = (float)$_POST['qty_baru']; // Misal: 3
    $qty_lama   = (float)$_POST['qty_lama']; // Misal: 4
    $keperluan  = strtoupper(mysqli_real_escape_string($koneksi, $_POST['keperluan']));
    $plat_nomor = strtoupper(mysqli_real_escape_string($koneksi, $_POST['plat_nomor']));
    $tgl_keluar = $_POST['tgl_keluar']; 
    $now        = date('H:i:s');

    // 1. Ambil ID Barang berdasarkan ID Log (lebih akurat)
    $sql_barang = mysqli_query($koneksi, "SELECT id_barang FROM tr_stok_log WHERE id_log = '$id_log'");
    $db_barang  = mysqli_fetch_array($sql_barang);
    $id_barang  = $db_barang['id_barang'];

    if(!$id_barang) {
        echo "<script>alert('Data Barang Tidak Ditemukan!'); window.location='pengambilan.php';</script>";
        exit;
    }

    mysqli_begin_transaction($koneksi);

    try {
        // 2. CEK STOK DI GUDANG SAAT INI
        $res_cek = mysqli_fetch_array(mysqli_query($koneksi, "SELECT stok_akhir FROM master_barang WHERE id_barang='$id_barang' FOR UPDATE"));
        
        // RUMUS KRUSIAL: Sisa di Gudang + Barang yang mau diedit
        $stok_tersedia_real = $res_cek['stok_akhir'] + $qty_lama;

        // Validasi
        if($qty_baru > $stok_tersedia_real){
            throw new Exception("Gagal! Stok tidak cukup. Maksimal yang bisa diambil: " . $stok_tersedia_real);
        }

        // 3. UPDATE MASTER_BARANG (Logika Netralisasi)
        // Kembalikan dulu 4 ke gudang, lalu kurangi 3
        $final_stok = $stok_tersedia_real - $qty_baru;
        $u1 = mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = '$final_stok' WHERE id_barang = '$id_barang'");

        // 4. UPDATE bon_permintaan (Tabel Transaksi)
        $u2 = mysqli_query($koneksi, "UPDATE bon_permintaan SET 
                                qty_keluar  = '$qty_baru', 
                                penerima    = '$penerima', 
                                keperluan   = '$keperluan',
                                plat_nomor  = '$plat_nomor',
                                tgl_keluar  = '$tgl_keluar'
                                WHERE id_bon = '$id_bon'");

        // 5. UPDATE tr_stok_log (Tabel Kartu Stok)
        $info_plat    = ($plat_nomor != "") ? " [UNIT: $plat_nomor]" : "";
        $ket_baru     = "EDIT PENGAMBILAN: $penerima ($keperluan)$info_plat";
        $tgl_log_baru = $tgl_keluar . ' ' . $now;

        $u3 = mysqli_query($koneksi, "UPDATE tr_stok_log SET 
                            qty        = '$qty_baru', 
                            keterangan = '$ket_baru',
                            tgl_log    = '$tgl_log_baru'
                            WHERE id_log = '$id_log'");

        if(!$u1 || !$u2 || !$u3) {
            throw new Exception("Gagal update database. Periksa query.");
        }

        mysqli_commit($koneksi);
        echo "<script>alert('Berhasil Koreksi Data!'); window.location='pengambilan.php';</script>";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('".$e->getMessage()."'); window.location='pengambilan.php';</script>";
    }
}