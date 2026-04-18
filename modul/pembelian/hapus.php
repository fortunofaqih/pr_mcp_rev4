<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") { header("location:../../login.php"); exit; }

$id_pembelian = mysqli_real_escape_string($koneksi, $_GET['id']);
$id_request   = mysqli_real_escape_string($koneksi, $_GET['id_req']);

if (!empty($id_pembelian)) {
    // 1. AMBIL DATA PEMBELIAN SEBELUM DIHAPUS (Untuk koreksi stok)
    $q_data = mysqli_query($koneksi, "SELECT * FROM pembelian WHERE id_pembelian = '$id_pembelian'");
    $d = mysqli_fetch_assoc($q_data);

    if ($d) {
        $qty          = $d['qty'];
        $nama_barang  = $d['nama_barang_beli'];
        $alokasi      = $d['alokasi_stok'];

        // 2. KOREKSI STOK JIKA DULU MASUK STOK
        if ($alokasi == "MASUK STOK") {
            // Kurangi stok di master_barang
            mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir - $qty WHERE nama_barang = '$nama_barang'");
            
            // Catat di log bahwa ada pembatalan/penghapusan
            $keterangan_log = "PEMBATALAN BELI: $nama_barang (Data Dihapus)";
            $tgl_sekarang   = date('Y-m-d H:i:s');
            mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan) 
                                    SELECT id_barang, '$tgl_sekarang', 'KELUAR', '$qty', '$keterangan_log' 
                                    FROM master_barang WHERE nama_barang = '$nama_barang'");
        }

        // 3. KEMBALIKAN STATUS PR KE PENDING
        if (!empty($id_request) && $id_request != '0' && $id_request != 'NULL') {
            mysqli_query($koneksi, "UPDATE tr_request SET status_request = 'PENDING' WHERE id_request = '$id_request'");
        }

        // 4. HAPUS DATA PEMBELIAN
        mysqli_query($koneksi, "DELETE FROM pembelian WHERE id_pembelian = '$id_pembelian'");

        echo "<script>alert('Data pembelian dihapus & stok telah dikoreksi!'); window.location=document.referrer;</script>";
    }
} else {
    header("location:index.php");
}
?>