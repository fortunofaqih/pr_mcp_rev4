<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// 1. Tangkap Data Input
$id_pembelian    = (int)$_POST['id_pembelian'];
$qty_baru        = (float)$_POST['qty'];
$harga_satuan    = (float)$_POST['harga'];
$alokasi_stok    = $_POST['alokasi_stok'] ?? 'LANGSUNG PAKAI'; 
$nama_barang     = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_barang'] ?? ''));
$tgl_beli_barang = mysqli_real_escape_string($koneksi, $_POST['tgl_beli_barang'] ?? date('Y-m-d'));

// Proteksi Undefined Key plat_nomor
$plat_raw   = $_POST['plat_nomor'] ?? '';
$plat_nomor = strtoupper(mysqli_real_escape_string($koneksi, $plat_raw));

$id_user_beli = (int)$_POST['id_user_beli']; 
$keterangan   = strtoupper(mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? ''));
$merk_beli    = strtoupper(mysqli_real_escape_string($koneksi, $_POST['merk_beli'] ?? ''));
$supplier     = strtoupper(mysqli_real_escape_string($koneksi, $_POST['supplier'] ?? ''));

// 2. Ambil Data Lama (Penting untuk netralisasi stok)
$sql_old = "SELECT qty, alokasi_stok, nama_barang_beli, id_request_detail 
            FROM pembelian WHERE id_pembelian = $id_pembelian LIMIT 1";
$res_old = mysqli_query($koneksi, $sql_old);
$data_old = mysqli_fetch_assoc($res_old);

if (!$data_old) {
    header("location:data_pembelian.php?pesan=data_tidak_ditemukan");
    exit;
}

$qty_lama         = (float)$data_old['qty'];
$alokasi_lama     = $data_old['alokasi_stok'];
$nama_barang_lama = $data_old['nama_barang_beli'];
$id_req_detail    = (int)$data_old['id_request_detail'];

// Mulai Transaksi Database
mysqli_begin_transaction($koneksi);

try {
    // --- STEP A: NETRALISASI STOK LAMA ---
   
	if ($alokasi_lama == 'MASUK STOK') {
		$q_m_old = mysqli_query($koneksi, "SELECT id_barang FROM master_barang WHERE nama_barang = '$nama_barang_lama' LIMIT 1");
		if ($row_m_old = mysqli_fetch_assoc($q_m_old)) {
			$id_b_old = $row_m_old['id_barang'];
        
        // 1. Kembalikan angka stok di master
			mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir - $qty_lama WHERE id_barang = $id_b_old");
        
        // 2. PERBAIKAN: Hapus log lama dengan kriteria yang lebih luas (mencakup ID: xxx atau ID-BELI: xxx)
        // Kita gunakan wildcard %ID%id_pembelian% agar semua format kena
			$sql_del_log = "DELETE FROM tr_stok_log WHERE id_barang = '$id_b_old' AND keterangan LIKE '%ID% $id_pembelian%'"; 
			mysqli_query($koneksi, $sql_del_log);
		}
	}

    // --- Cari id_mobil berdasarkan plat_nomor terbaru ---
    $q_cari_id = mysqli_query($koneksi, "SELECT id_mobil FROM master_mobil WHERE plat_nomor = '$plat_nomor' LIMIT 1");
    $row_mobil = mysqli_fetch_assoc($q_cari_id);
    $id_mobil_baru = $row_mobil ? (int)$row_mobil['id_mobil'] : 0;

    // --- STEP B: UPDATE DATA PEMBELIAN ---
    $sql_update_beli = "UPDATE pembelian SET
                        tgl_beli_barang  = '$tgl_beli_barang',
                        nama_barang_beli = '$nama_barang',
                        merk_beli        = '$merk_beli',
                        supplier         = '$supplier',
                        plat_nomor       = '$plat_nomor',
                        id_mobil         = $id_mobil_baru,
                        id_user_beli     = $id_user_beli,
                        qty              = $qty_baru,
                        harga            = $harga_satuan,
                        alokasi_stok     = '$alokasi_stok',
                        keterangan       = '$keterangan'
                    WHERE id_pembelian   = $id_pembelian";
    
    if (!mysqli_query($koneksi, $sql_update_beli)) {
        throw new Exception("Gagal update tabel pembelian: " . mysqli_error($koneksi));
    }

    // --- STEP C: UPDATE DATA REQUEST DETAIL ---
    if ($id_req_detail > 0) {
        $subtotal = $qty_baru * $harga_satuan;
        
        // Mapping Alokasi: MASUK STOK -> STOK | LANGSUNG PAKAI -> LANGSUNG
        $tipe_request = ($alokasi_stok == 'MASUK STOK') ? 'STOK' : 'LANGSUNG';
        
        $sql_update_detail = "UPDATE tr_request_detail SET 
                                nama_barang_manual = '$nama_barang',
                                id_mobil = $id_mobil_baru,
                                jumlah = $qty_baru,
                                subtotal_estimasi = $subtotal,
                                tipe_request = '$tipe_request',
                                status_item = 'TERBELI',
                                is_dibeli = 1
                            WHERE id_detail = $id_req_detail";
        
        if (!mysqli_query($koneksi, $sql_update_detail)) {
            throw new Exception("Gagal update detail request: " . mysqli_error($koneksi));
        }
    }

    // --- STEP D: REKAYASA ULANG STOK BARU ---
    // Jika alokasi baru adalah MASUK STOK, maka tambahkan ke master_barang terbaru
	if ($alokasi_stok == 'MASUK STOK') {
		$q_m_new = mysqli_query($koneksi, "SELECT id_barang FROM master_barang WHERE nama_barang = '$nama_barang' LIMIT 1");
		if ($row_m_new = mysqli_fetch_assoc($q_m_new)) {
			$id_b_new = $row_m_new['id_barang'];
        
			mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty_baru WHERE id_barang = $id_b_new");
        
        // Gunakan format keterangan yang konsisten agar mudah dihapus di kemudian hari
        $ket_log_baru = "MASUK DARI PEMBELIAN (EDIT) | ID-BELI: $id_pembelian";
        $sql_ins_log = "INSERT INTO tr_stok_log (id_barang, tgl_log, qty, tipe_transaksi, keterangan) 
                        VALUES ($id_b_new, '$tgl_beli_barang " . date('H:i:s') . "', $qty_baru, 'MASUK', '$ket_log_baru')";
			mysqli_query($koneksi, $sql_ins_log);
		}
	}

    // Jika semua OK, simpan permanen
    mysqli_commit($koneksi);
    header("location:data_pembelian.php?pesan=edit_sukses");

} catch (Exception $e) {
    // Jika ada yang gagal, batalkan semua perubahan di semua tabel
    mysqli_rollback($koneksi);
    header("location:data_pembelian.php?pesan=edit_gagal&error=" . urlencode($e->getMessage()));
}
?>