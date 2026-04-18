<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Pastikan data dikirim melalui metode POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Menangkap ID Primer untuk update
    $id_pembelian   = $_POST['id_pembelian'];
    
    // Menangkap data & Mengubah ke KAPITAL (strtoupper)
    $tgl_beli       = $_POST['tgl_beli'];
    $supplier       = strtoupper(mysqli_real_escape_string($koneksi, $_POST['supplier']));
    $nama_pemesan   = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_pemesan']));
    $nama_barang    = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_barang']));
    $qty_beli       = $_POST['qty_beli'];
    $harga_satuan   = $_POST['harga_satuan'];
    $total_harga    = $_POST['total_harga']; // Nilai ini sudah dihitung JS di form
    $driver         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['driver']));
    $plat_nomor     = strtoupper(mysqli_real_escape_string($koneksi, $_POST['plat_nomor']));
    $keterangan     = strtoupper(mysqli_real_escape_string($koneksi, $_POST['keterangan']));

    // Query Update Data
    $query_update = mysqli_query($koneksi, "UPDATE pembelian SET 
        tgl_beli         = '$tgl_beli',
        supplier         = '$supplier',
        nama_barang_beli = '$nama_barang',
        qty_beli         = '$qty_beli',
        harga_satuan     = '$harga_satuan',
        total_harga      = '$total_harga',
        driver           = '$driver',
        plat_nomor       = '$plat_nomor',
        nama_pemesan     = '$nama_pemesan',
        keterangan       = '$keterangan'
        WHERE id_pembelian = '$id_pembelian'");

    if($query_update) {
        // Redirect kembali ke halaman index dengan pesan sukses
        header("location:index.php?pesan=update_berhasil");
    } else {
        // Jika gagal, tampilkan pesan error
        echo "Gagal memperbarui data: " . mysqli_error($koneksi);
    }
} else {
    // Jika akses langsung ke file ini tanpa POST
    header("location:index.php");
}
?>