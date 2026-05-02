<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Proteksi session
if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Validasi input ID
if (!isset($_POST['id_barang']) || !is_numeric($_POST['id_barang'])) {
    header("location:data_barang.php?pesan=error");
    exit;
}

// 1. Ambil data dari POST
$id           = (int)$_POST['id_barang'];
// Data Nama Barang tetap disimpan dengan spasi asli (setelah trim depan-belakang)
$nama         = strtoupper(mysqli_real_escape_string($koneksi, trim($_POST['nama_barang'])));
$merk         = strtoupper(mysqli_real_escape_string($koneksi, trim($_POST['merk'])));
$lokasi       = strtoupper(mysqli_real_escape_string($koneksi, trim($_POST['lokasi_rak'])));
$satuan       = mysqli_real_escape_string($koneksi, $_POST['satuan']); 
$stok_input   = (float)$_POST['stok_akhir']; 
$status_aktif = mysqli_real_escape_string($koneksi, $_POST['status_aktif']);
$kategori     = mysqli_real_escape_string($koneksi, $_POST['kategori']);
$harga_barang = (float)($_POST['harga_barang_stok'] ?? 0);
$user_login   = $_SESSION['nama']; 

// Ambil data lama untuk keperluan log dan sinkronisasi
$query_lama = mysqli_query($koneksi, "SELECT nama_barang, stok_akhir FROM master_barang WHERE id_barang='$id'");
$lama       = mysqli_fetch_array($query_lama);
$nama_lama  = $lama['nama_barang'];
$stok_lama  = (float)$lama['stok_akhir'];

// Mulai Transaksi
mysqli_begin_transaction($koneksi);

try {
    // --- LOGIKA OPSI 1: CEK DUPLIKAT TANPA MEMPEDULIKAN SPASI ---
    // Kita hapus semua spasi di inputan untuk perbandingan
    $nama_tanpa_spasi = str_replace(' ', '', $nama);

    // Query menggunakan fungsi REPLACE() milik MySQL untuk menghapus spasi di kolom database saat pencarian
    $sql_cek = "SELECT id_barang FROM master_barang 
                WHERE REPLACE(nama_barang, ' ', '') = '$nama_tanpa_spasi' 
                AND id_barang != '$id'";
    
    $cek_duplikat = mysqli_query($koneksi, $sql_cek);
    
    if (mysqli_num_rows($cek_duplikat) > 0) {
        // Jika ditemukan kecocokan (meskipun beda spasi di tengah), lempar error
        throw new Exception("duplikat");
    }

    // 2. Update data Master
    $sql_update = "UPDATE master_barang SET 
            nama_barang = '$nama', 
            merk = '$merk', 
            kategori = '$kategori', 
            lokasi_rak = '$lokasi', 
            satuan = '$satuan', 
            stok_akhir = '$stok_input', 
            harga_barang_stok = '$harga_barang',
            status_aktif = '$status_aktif'
            WHERE id_barang = '$id'";
    
    if(!mysqli_query($koneksi, $sql_update)) throw new Exception("gagal_update");

    // 3. Log Perubahan Stok
    if($stok_input != $stok_lama) {
        $selisih = $stok_input - $stok_lama;
        $tipe = ($selisih > 0) ? 'MASUK' : 'KELUAR';
        $qty_log = abs($selisih);
        $keterangan = "KOREKSI STOK MANUAL (DARI $stok_lama KE $stok_input) BY $user_login";
        
        $sql_log = "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan) 
                    VALUES ('$id', NOW(), '$tipe', '$qty_log', '$keterangan')";
        
        if(!mysqli_query($koneksi, $sql_log)) throw new Exception("gagal_log");
    }

    // 4. Sinkronisasi Nama Barang ke tabel-tabel terkait
    if ($nama != $nama_lama) {
        // Update tabel pembelian (melalui detail request)
        mysqli_query($koneksi, "UPDATE pembelian SET nama_barang_beli = '$nama' WHERE id_request_detail IN (SELECT id_detail FROM tr_request_detail WHERE id_barang = '$id')");

        // Update tabel pembelian_staging
        mysqli_query($koneksi, "UPDATE pembelian_staging SET nama_barang_beli = '$nama' WHERE id_barang = '$id'");

        // Update tabel perbandingan_harga
        mysqli_query($koneksi, "UPDATE perbandingan_harga SET nama_barang = '$nama' WHERE nama_barang = '$nama_lama'");

        // Update tabel tr_bongkaran
        mysqli_query($koneksi, "UPDATE tr_bongkaran SET nama_barang = '$nama' WHERE nama_barang = '$nama_lama'");

        // Update tabel tr_request_detail
        mysqli_query($koneksi, "UPDATE tr_request_detail SET nama_barang_manual = '$nama' WHERE id_barang = '$id'");
    }

    // Commit semua perubahan jika sukses
    mysqli_commit($koneksi);
    header("location:data_barang.php?pesan=berhasil_update");

} catch (Exception $e) {
    // Batalkan semua perubahan jika ada error atau duplikat
    mysqli_rollback($koneksi);
    $error_type = $e->getMessage();
    header("location:edit_barang.php?id=$id&pesan=$error_type");
    exit;
}
?>