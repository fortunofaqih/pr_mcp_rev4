<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Ambil Data Header & Sanitasi Dasar
    $id_request   = mysqli_real_escape_string($koneksi, $_POST['id_request']);
    $tgl_request  = mysqli_real_escape_string($koneksi, $_POST['tgl_request']);
    $nama_pemesan = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_pemesan']));
    $nama_pembeli = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_pembeli']));
    $user_login   = $_SESSION['username'];
    $now          = date('Y-m-d H:i:s');

    // 2. Ambil Array dari POST
    $id_detail_array       = $_POST['id_detail']           ?? [];
    $id_barang_array       = $_POST['id_barang']           ?? [];
    $nama_barang_array     = $_POST['nama_barang_manual']  ?? [];
    $kategori_array        = $_POST['kategori_request']    ?? [];
    $kwalifikasi_array     = $_POST['kwalifikasi']         ?? [];
    $id_mobil_array        = $_POST['id_mobil']            ?? [];
    $tipe_array            = $_POST['tipe_request']        ?? [];
    $jumlah_array          = $_POST['jumlah']              ?? [];
    $satuan_array          = $_POST['satuan']              ?? [];
    $harga_array           = $_POST['harga']               ?? [];
    $keterangan_array      = $_POST['keterangan']          ?? [];

    // Mulai Database Transaction
    mysqli_begin_transaction($koneksi);

    try {
        // --- VALIDASI AWAL ---
        if (empty($id_barang_array)) {
            throw new Exception("Request tidak boleh kosong. Minimal harus ada 1 item barang.");
        }

        // --- 1. UPDATE HEADER ---
        $query_h = "UPDATE tr_request SET 
                        tgl_request  = '$tgl_request', 
                        nama_pemesan = '$nama_pemesan',
                        nama_pembeli = '$nama_pembeli',
                        updated_by   = '$user_login',
                        updated_at   = '$now' 
                    WHERE id_request = '$id_request'";
        
        if (!mysqli_query($koneksi, $query_h)) {
            throw new Exception("Gagal update header: " . mysqli_error($koneksi));
        }

        // --- 2. LOGIKA PENGHAPUSAN (DELETE) ---
        // Mencari ID Detail yang masih ada di form (untuk dipertahankan)
        $id_detail_dikirim = array_filter($id_detail_array, function($v) {
            return !empty($v) && intval($v) > 0;
        });

        $query_del_base = "DELETE FROM tr_request_detail 
                           WHERE id_request = '$id_request' 
                           AND status_item IN ('PENDING', 'APPROVED', 'REJECTED')";

        if (!empty($id_detail_dikirim)) {
            $ids_aman = implode(',', array_map('intval', $id_detail_dikirim));
            $query_del = $query_del_base . " AND id_detail NOT IN ($ids_aman)";
        } else {
            // Jika user menghapus semua baris yang bisa diedit di tabel
            $query_del = $query_del_base;
        }

        if (!mysqli_query($koneksi, $query_del)) {
            throw new Exception("Gagal sinkronisasi data (Delete): " . mysqli_error($koneksi));
        }

        // --- 3. LOOP: INSERT BARU ATAU UPDATE EXISTING ---
        foreach ($id_barang_array as $key => $val) {
            // Skip baris jika ID Barang kosong (baris template/kosong)
            if (empty($val)) continue;

            // Sanitasi data per baris
            $id_detail = intval($id_detail_array[$key] ?? 0);
            $id_brg    = intval($val);
            $qty       = floatval($jumlah_array[$key] ?? 0);
            $hrg       = floatval($harga_array[$key] ?? 0);
            $subtotal  = $qty * $hrg;
            
            // Helper sanitasi string
            $nama_m = strtoupper(mysqli_real_escape_string($koneksi, $nama_barang_array[$key] ?? ''));
            $kat    = strtoupper(mysqli_real_escape_string($koneksi, $kategori_array[$key] ?? ''));
            $kwal   = strtoupper(mysqli_real_escape_string($koneksi, $kwalifikasi_array[$key] ?? ''));
            $sat    = strtoupper(mysqli_real_escape_string($koneksi, $satuan_array[$key] ?? ''));
            $ket    = strtoupper(mysqli_real_escape_string($koneksi, $keterangan_array[$key] ?? ''));
            $tipe   = strtoupper(mysqli_real_escape_string($koneksi, $tipe_array[$key] ?? 'STOK'));
            $mobil  = intval($id_mobil_array[$key] ?? 0);

            if ($id_detail > 0) {
                // --- UPDATE DATA LAMA ---
                // Hanya update jika status_item = 'PENDING'
                // Ini mencegah data yang sudah 'TERBELI' berubah lewat POST manual
                $query_d = "UPDATE tr_request_detail SET
                                nama_barang_manual    = '$nama_m',
                                id_barang             = '$id_brg',
                                id_mobil              = '$mobil',
                                jumlah                = '$qty',
                                satuan                = '$sat',
                                harga_satuan_estimasi = '$hrg',
                                subtotal_estimasi     = '$subtotal',
                                kategori_barang       = '$kat',
                                kwalifikasi           = '$kwal',
                                tipe_request          = '$tipe',
                                keterangan            = '$ket'
                            WHERE id_detail  = '$id_detail'
                            AND   id_request = '$id_request'
                            AND   status_item = 'PENDING'";
            } else {
                // --- INSERT DATA BARU ---
                $query_d = "INSERT INTO tr_request_detail 
                                (id_request, nama_barang_manual, id_barang, id_mobil, jumlah, satuan, 
                                 harga_satuan_estimasi, subtotal_estimasi, kategori_barang, kwalifikasi, 
                                 tipe_request, keterangan, status_item) 
                            VALUES 
                                ('$id_request', '$nama_m', '$id_brg', '$mobil', '$qty', '$sat', 
                                 '$hrg', '$subtotal', '$kat', '$kwal', '$tipe', '$ket', 'PENDING')";
            }

            if (!mysqli_query($koneksi, $query_d)) {
                throw new Exception("Gagal simpan item pada baris " . ($key + 1) . ": " . mysqli_error($koneksi));
            }
        }
		// --- LOGIKA TAMBAHAN: Update Otomatis Status Header ---
		// 1. Hitung total item di PR ini
		// 2. Hitung berapa yang statusnya sudah 'TERBELI'
		$check_status = mysqli_query($koneksi, "SELECT 
			COUNT(*) as total, 
			SUM(CASE WHEN status_item = 'TERBELI' THEN 1 ELSE 0 END) as terbeli 
			FROM tr_request_detail WHERE id_request = '$id_request'");
		$data_status = mysqli_fetch_assoc($check_status);

		if ($data_status['total'] > 0 && $data_status['total'] == $data_status['terbeli']) {
			// Jika semua item sudah terbeli, set header jadi SELESAI
			mysqli_query($koneksi, "UPDATE tr_request SET status_request = 'SELESAI' WHERE id_request = '$id_request'");
		} else if ($data_status['terbeli'] > 0) {
			// Jika baru sebagian yang terbeli, set header jadi PROSES
			mysqli_query($koneksi, "UPDATE tr_request SET status_request = 'PROSES' WHERE id_request = '$id_request'");
		}
        // Jika semua berhasil, Commit transaksi
        mysqli_commit($koneksi);
        header("location:pr.php?pesan=update_sukses");
        exit;

    } catch (Exception $e) {
        // Jika ada error, batalkan semua perubahan (Rollback)
        mysqli_rollback($koneksi);
        
        echo "<div style='font-family:sans-serif; background:#fff5f5; border:1px solid #feb2b2; color:#c53030; padding:20px; border-radius:8px; margin:20px;'>";
        echo "<h3 style='margin-top:0;'>⚠️ Terjadi Kesalahan Sistem</h3>";
        echo "<p>Pesan Error: <strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
        echo "<hr style='border:0; border-top:1px solid #feb2b2;'>";
        echo "<a href='javascript:history.back()' style='text-decoration:none; color:#2b6cb0;'>« Kembali ke Form Edit</a>";
        echo "</div>";
    }
} else {
    // Jika diakses tanpa method POST
    header("location:pr.php");
    exit;
}
?>