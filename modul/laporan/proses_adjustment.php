<?php
session_start();
include '../../config/koneksi.php'; // Pastikan di sini mendefinisikan $koneksi
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Gunakan variabel koneksi yang konsisten ($koneksi)
$id_barang   = mysqli_real_escape_string($koneksi, $_GET['id_barang']);
$stok_master = (float)$_GET['stok_master']; 

if (isset($id_barang) && $id_barang != '') {
    
    // 1. Hitung total stok dari LOG (Sertakan RETUR agar sinkron dengan Kartu Stok)
    $query_cek = "SELECT 
                    (COALESCE(SUM(CASE WHEN tipe_transaksi IN ('MASUK', 'RETUR') THEN qty ELSE 0 END), 0) - 
                     COALESCE(SUM(CASE WHEN tipe_transaksi = 'KELUAR' THEN qty ELSE 0 END), 0)) AS stok_log_sekarang
                  FROM tr_stok_log 
                  WHERE id_barang = '$id_barang'";
    
    $result = mysqli_query($koneksi, $query_cek);
    $data   = mysqli_fetch_assoc($result);
    $stok_log_sekarang = (float)$data['stok_log_sekarang'];

    // 2. Hitung Selisih
    // Jika log (100) dan master (80), selisih = -20 (Artinya harus KELUAR 20)
    $selisih = $stok_master - $stok_log_sekarang;

    if ($selisih == 0) {
        echo "<script>alert('Stok LOG dan MASTER sudah sinkron!'); window.location='data_stock.php';</script>";
        exit;
    }

    // Tentukan tipe transaksi
    $tipe_transaksi = ($selisih > 0) ? 'MASUK' : 'KELUAR';
    $qty_adjustment = abs($selisih); 

    // 3. Masukkan ke tabel Log sebagai baris penyeimbang
    $keterangan_adj = "KOREKSI SISTEM - PENYIMBANG LOG KE MASTER (SELISIH: $selisih)";
    
    $sql_insert = "INSERT INTO tr_stok_log (id_barang, tipe_transaksi, qty, keterangan, tgl_log) 
                   VALUES ('$id_barang', '$tipe_transaksi', '$qty_adjustment', '$keterangan_adj', NOW())";

    if (mysqli_query($koneksi, $sql_insert)) {
        echo "<script>alert('Adjustment Berhasil! Stok LOG kini sinkron dengan MASTER ($stok_master).'); window.location='data_stok.php';</script>";
    } else {
        echo "Error: " . mysqli_error($koneksi);
    }
} else {
    echo "ID Barang tidak valid.";
}
?>