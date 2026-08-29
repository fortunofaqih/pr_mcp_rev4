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
$nama         = strtoupper(trim($_POST['nama_barang']));
$merk         = strtoupper(trim($_POST['merk'] ?? ''));
$lokasi       = strtoupper(trim($_POST['lokasi_rak'] ?? ''));
$satuan       = trim($_POST['satuan'] ?? '');
$stok_input   = (float)($_POST['stok_akhir'] ?? 0);
$status_aktif = trim($_POST['status_aktif'] ?? 'AKTIF');
$kategori     = trim($_POST['kategori'] ?? '');
$harga_barang = (float)($_POST['harga_barang_stok'] ?? 0);
$user_login   = $_SESSION['nama'] ?? 'SYSTEM';

// Validasi data wajib
if (empty($nama) || empty($satuan) || empty($kategori)) {
    header("location:edit_barang.php?id=$id&pesan=data_tidak_lengkap");
    exit;
}

// Ambil data lama untuk keperluan log dan sinkronisasi
$query_lama  = mysqli_query($koneksi, "SELECT nama_barang, satuan, stok_akhir FROM master_barang WHERE id_barang='$id'");
if (!$query_lama || mysqli_num_rows($query_lama) == 0) {
    header("location:data_barang.php?pesan=tidak_ditemukan");
    exit;
}
$lama        = mysqli_fetch_array($query_lama);
$nama_lama   = $lama['nama_barang'];
$satuan_lama = $lama['satuan'];
$stok_lama   = (float)$lama['stok_akhir'];

// Mulai Transaksi
mysqli_begin_transaction($koneksi);

try {
    // CEK DUPLIKAT (Case-insensitive dan abaikan spasi)
    $nama_tanpa_spasi = str_replace(' ', '', $nama);
    $nama_lama_tanpa_spasi = str_replace(' ', '', $nama_lama);
    
    // Cek duplikat hanya jika nama berubah
    if ($nama_tanpa_spasi != $nama_lama_tanpa_spasi) {
        $sql_cek = "SELECT id_barang FROM master_barang 
                    WHERE REPLACE(nama_barang, ' ', '') = '$nama_tanpa_spasi' 
                    AND id_barang != '$id'";
        
        $cek_duplikat = mysqli_query($koneksi, $sql_cek);
        
        if (mysqli_num_rows($cek_duplikat) > 0) {
            throw new Exception("duplikat");
        }
    }

    // 2. Update data Master - Gunakan Prepared Statement
    $sql_update = "UPDATE master_barang SET 
            nama_barang = ?,
            merk = ?,
            kategori = ?,
            lokasi_rak = ?,
            satuan = ?,
            stok_akhir = ?,
            harga_barang_stok = ?,
            status_aktif = ?
            WHERE id_barang = ?";
    
    $stmt = mysqli_prepare($koneksi, $sql_update);
    mysqli_stmt_bind_param($stmt, "sssssddsi", 
        $nama, 
        $merk, 
        $kategori, 
        $lokasi, 
        $satuan, 
        $stok_input, 
        $harga_barang, 
        $status_aktif,
        $id
    );
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("gagal_update: " . mysqli_stmt_error($stmt));
    }

    // 3. Log Perubahan Stok
    if ($stok_input != $stok_lama) {
        $selisih = $stok_input - $stok_lama;
        $tipe = ($selisih > 0) ? 'MASUK' : 'KELUAR';
        $qty_log = abs($selisih);
        $keterangan = "KOREKSI STOK MANUAL (DARI $stok_lama KE $stok_input) BY $user_login";
        
        $sql_log = "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan) 
                    VALUES (?, NOW(), ?, ?, ?)";
        
        $stmt_log = mysqli_prepare($koneksi, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "isds", $id, $tipe, $qty_log, $keterangan);
        
        if (!mysqli_stmt_execute($stmt_log)) {
            throw new Exception("gagal_log: " . mysqli_stmt_error($stmt_log));
        }
        mysqli_stmt_close($stmt_log);
    }

    // 4. Sinkronisasi Nama Barang & Satuan ke tabel-tabel terkait
    if ($nama != $nama_lama || $satuan != $satuan_lama) {
        
        // Update tabel pembelian (menggunakan subquery)
        $sql_update_pembelian = "UPDATE pembelian SET 
                                nama_barang_beli = ? 
                                WHERE id_request_detail IN (
                                    SELECT id_detail FROM tr_request_detail WHERE id_barang = ?
                                )";
        $stmt = mysqli_prepare($koneksi, $sql_update_pembelian);
        mysqli_stmt_bind_param($stmt, "si", $nama, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Update tabel pembelian_staging
        $sql_update_staging = "UPDATE pembelian_staging SET nama_barang_beli = ? WHERE id_barang = ?";
        $stmt = mysqli_prepare($koneksi, $sql_update_staging);
        mysqli_stmt_bind_param($stmt, "si", $nama, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Update tabel perbandingan_harga
        $sql_update_perbandingan = "UPDATE perbandingan_harga SET nama_barang = ? WHERE nama_barang = ?";
        $stmt = mysqli_prepare($koneksi, $sql_update_perbandingan);
        mysqli_stmt_bind_param($stmt, "ss", $nama, $nama_lama);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Update tabel tr_bongkaran
        $sql_update_bongkaran = "UPDATE tr_bongkaran SET nama_barang = ? WHERE nama_barang = ?";
        $stmt = mysqli_prepare($koneksi, $sql_update_bongkaran);
        mysqli_stmt_bind_param($stmt, "ss", $nama, $nama_lama);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Update tabel tr_request_detail
        $sql_update_request = "UPDATE tr_request_detail SET 
                                nama_barang_manual = ?, 
                                satuan = ? 
                                WHERE id_barang = ?";
        $stmt = mysqli_prepare($koneksi, $sql_update_request);
        mysqli_stmt_bind_param($stmt, "ssi", $nama, $satuan, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Commit semua perubahan
    mysqli_commit($koneksi);
    header("location:data_barang.php?pesan=berhasil_update");

} catch (Exception $e) {
    // Rollback jika ada error
    mysqli_rollback($koneksi);
    $error_type = $e->getMessage();
    header("location:edit_barang.php?id=$id&pesan=" . urlencode($error_type));
    exit;
}
?>