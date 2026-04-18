<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    exit("Akses ditolak");
}

if (isset($_GET['id']) && isset($_GET['kat'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    $kat = $_GET['kat'];

    mysqli_begin_transaction($koneksi);

    try {
        if ($kat == 'STOK') {
            /**
             * UNTUK BARANG DARI STOK GUDANG:
             * Karena ini adalah bon keluar, jika dihapus dari mobil, 
             * maka barang harus balik ke rak (stok bertambah lagi).
             */
            $cek = mysqli_query($koneksi, "SELECT id_barang, qty_keluar FROM bon_permintaan WHERE id_bon = '$id'");
            $data = mysqli_fetch_assoc($cek);

            if ($data) {
                $brg_id = $data['id_barang'];
                $qty = $data['qty_keluar'];

                // 1. Kembalikan stok ke master_barang
                mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty WHERE id_barang = '$brg_id'");

                // 2. Hapus data bon (karena bon ini spesifik untuk plat nomor tersebut)
                mysqli_query($koneksi, "DELETE FROM bon_permintaan WHERE id_bon = '$id'");
                
                // 3. Hapus log stok terkait (agar kartu stok sinkron)
                // Kita asumsikan log terakhir adalah bon ini
                mysqli_query($koneksi, "DELETE FROM tr_stok_log WHERE id_barang = '$brg_id' AND tipe_transaksi = 'KELUAR' ORDER BY id_log DESC LIMIT 1");
            }

        } else if ($kat == 'BELI') {
            /**
             * UNTUK PEMBELIAN LANGSUNG:
             * Data pembelian TIDAK DIHAPUS. 
             * Kita hanya memutuskan hubungan (link) antara barang ini dengan mobil tersebut.
             */
            
            // 1. Update di tr_request_detail: set id_mobil ke 0 agar tidak muncul di laporan mobil
            mysqli_query($koneksi, "UPDATE tr_request_detail SET id_mobil = 0 WHERE id_detail = '$id'");

            // 2. Update di tabel pembelian: kosongkan plat_nomor agar tidak muncul di laporan mobil
            // Kita cari dulu nama barang dan no_request-nya
            $cek_beli = mysqli_query($koneksi, "SELECT rd.nama_barang_manual, r.no_request 
                                               FROM tr_request_detail rd 
                                               JOIN tr_request r ON rd.id_request = r.id_request 
                                               WHERE rd.id_detail = '$id'");
            $d_beli = mysqli_fetch_assoc($cek_beli);
            
            if($d_beli) {
                $no_req = $d_beli['no_request'];
                $nama_brg = $d_beli['nama_barang_manual'];
                
                // Update plat_nomor menjadi string kosong di tabel pembelian
                mysqli_query($koneksi, "UPDATE pembelian SET plat_nomor = '' 
                                       WHERE no_request = '$no_req' AND nama_barang_beli = '$nama_brg'");
            }
        }

        mysqli_commit($koneksi);
        header("location:laporan_mobil.php?pesan=update_berhasil");

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "Gagal memproses data: " . $e->getMessage();
    }
}
?>