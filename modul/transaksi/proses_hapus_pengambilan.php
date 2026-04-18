<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Pastikan menerima ID Bon dan ID Log (dari link hapus yang baru)
if (isset($_GET['id']) && isset($_GET['id_log'])) {
    $id_bon = mysqli_real_escape_string($koneksi, $_GET['id']);
    $id_log = mysqli_real_escape_string($koneksi, $_GET['id_log']);
    $user   = $_SESSION['nama'];

    // 1. Ambil detail pengambilan sebelum dihapus (untuk tahu barang dan qty)
    $query_ambil = mysqli_query($koneksi, "SELECT id_barang, qty_keluar FROM bon_permintaan WHERE id_bon = '$id_bon'");
    $data = mysqli_fetch_array($query_ambil);

    if ($data) {
        $id_barang = $data['id_barang'];
        $qty_awal  = $data['qty_keluar'];

        mysqli_begin_transaction($koneksi);

        try {
            // 2. KEMBALIKAN STOK KE MASTER_BARANG
            $sql_update = "UPDATE master_barang SET stok_akhir = stok_akhir + $qty_awal WHERE id_barang = '$id_barang'";
            if (!mysqli_query($koneksi, $sql_update)) {
                throw new Exception("Gagal mengembalikan stok ke master.");
            }

            // 3. HAPUS LOG PENGAMBILAN DI tr_stok_log
            // Kita tidak INSERT lagi, tapi DELETE log yang lama agar kartu stok bersih
            $sql_delete_log = "DELETE FROM tr_stok_log WHERE id_log = '$id_log'";
            if (!mysqli_query($koneksi, $sql_delete_log)) {
                throw new Exception("Gagal menghapus riwayat di kartu stok.");
            }

            // 4. HAPUS DATA DARI TABEL bon_permintaan
            $sql_delete_bon = "DELETE FROM bon_permintaan WHERE id_bon = '$id_bon'";
            if (!mysqli_query($koneksi, $sql_delete_bon)) {
                throw new Exception("Gagal menghapus data bon.");
            }

            mysqli_commit($koneksi);
            header("location:pengambilan.php?pesan=hapus_sukses");

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            echo "<script>alert('Error: " . $e->getMessage() . "'); window.location='pengambilan.php';</script>";
        }
    } else {
        header("location:pengambilan.php?pesan=data_tidak_ditemukan");
    }
} else {
    header("location:pengambilan.php");
}
?>