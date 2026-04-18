<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if (!isset($_POST['id_barang']) || !is_numeric($_POST['id_barang'])) {
    header("location:data_barang.php?pesan=error");
    exit;
}

$id           = (int)$_POST['id_barang'];
$nama         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_barang']));
$merk         = strtoupper(mysqli_real_escape_string($koneksi, $_POST['merk']));
$lokasi       = strtoupper(mysqli_real_escape_string($koneksi, $_POST['lokasi_rak']));
$satuan       = $_POST['satuan']; 
$stok_input   = (float)$_POST['stok_akhir']; 
$status_aktif = $_POST['status_aktif'];
$kategori     = mysqli_real_escape_string($koneksi, $_POST['kategori']);
$harga_barang = (float)($_POST['harga_barang_stok'] ?? 0);
$user_login   = $_SESSION['nama']; 

// 1. Ambil data stok lama
$query_lama = mysqli_query($koneksi, "SELECT stok_akhir FROM master_barang WHERE id_barang='$id'");
$lama = mysqli_fetch_array($query_lama);
$stok_lama = (float)$lama['stok_akhir'];

mysqli_begin_transaction($koneksi);

try {
    // 2. Selalu update data Master (Nama, Merk, Lokasi, Stok, dll)
    $sql = "UPDATE master_barang SET 
            nama_barang = '$nama', 
            merk = '$merk', 
            kategori = '$kategori', 
            lokasi_rak = '$lokasi', 
            satuan = '$satuan', 
            stok_akhir = '$stok_input', 
            harga_barang_stok = '$harga_barang',
            status_aktif = '$status_aktif'
            WHERE id_barang = '$id'";
    
    if(!mysqli_query($koneksi, $sql)) throw new Exception("Gagal update Master Barang");

    // 3. Catat ke log HANYA jika stok berubah
    if($stok_input != $stok_lama) {
        $selisih = $stok_input - $stok_lama;
        $tipe = ($selisih > 0) ? 'MASUK' : 'KELUAR';
        $qty_log = abs($selisih);
        $keterangan = "KOREKSI STOK MANUAL (DARI $stok_lama KE $stok_input) BY $user_login";
        
        $sql_log = "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan) 
                    VALUES ('$id', NOW(), '$tipe', '$qty_log', '$keterangan')";
        
        if(!mysqli_query($koneksi, $sql_log)) throw new Exception("Gagal catat Log Stok");
    }
	if(!mysqli_query($koneksi, $sql)) throw new Exception("Gagal update Master Barang");

	// --- START SINKRONISASI NAMA BARANG KE TABEL LAIN ---

	// 1. Update tabel pembelian
	$upd_pembelian = "UPDATE pembelian SET nama_barang_beli = '$nama' WHERE id_request_detail IN (SELECT id_detail FROM tr_request_detail WHERE id_barang = '$id')";
	mysqli_query($koneksi, $upd_pembelian);

	// 2. Update tabel pembelian_staging
	$upd_staging = "UPDATE pembelian_staging SET nama_barang_beli = '$nama' WHERE id_barang = '$id'";
	mysqli_query($koneksi, $upd_staging);

	// 3. Update tabel perbandingan_harga (Berdasarkan Nama Barang Lama sebelum diupdate)
	// Kita ambil nama lama dulu jika ingin akurat, atau gunakan ID jika ada. 
	// Karena di tabel ini tidak ada id_barang, kita gunakan nama lama dari master_barang
	$query_nama_lama = mysqli_query($koneksi, "SELECT nama_barang FROM master_barang WHERE id_barang='$id'");
	$data_nama_lama = mysqli_fetch_array($query_nama_lama);
	$nama_lama = $data_nama_lama['nama_barang'];

	$upd_perbandingan = "UPDATE perbandingan_harga SET nama_barang = '$nama' WHERE nama_barang = '$nama_lama'";
	mysqli_query($koneksi, $upd_perbandingan);

	// 4. Update tabel tr_bongkaran (Berdasarkan Nama Barang Lama)
	$upd_bongkaran = "UPDATE tr_bongkaran SET nama_barang = '$nama' WHERE nama_barang = '$nama_lama'";
	mysqli_query($koneksi, $upd_bongkaran);

	// 5. Update tabel tr_request_detail
	$upd_req_detail = "UPDATE tr_request_detail SET nama_barang_manual = '$nama' WHERE id_barang = '$id'";
	mysqli_query($koneksi, $upd_req_detail);

    mysqli_commit($koneksi);
    header("location:data_barang.php?pesan=berhasil_update");

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    echo "<script>alert('Error: " . $e->getMessage() . "'); window.location='data_barang.php';</script>";
}
?>