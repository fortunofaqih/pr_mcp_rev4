<?php
//proses edit request
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

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

    // Variabel untuk file upload
    $nama_file_baru_safe = '';
    $file_penawaran_sql  = '';
    $has_it_item = false;

    // Mulai Database Transaction
    mysqli_begin_transaction($koneksi);

    try {
        // --- VALIDASI AWAL ---
        if (empty($id_barang_array)) {
            throw new Exception("Request tidak boleh kosong. Minimal harus ada 1 item barang.");
        }

        // --- CEK APAKAH ADA ITEM KATEGORI IT ---
        $cek_it = mysqli_query($koneksi, 
            "SELECT COUNT(*) as total_it FROM tr_request_detail 
             WHERE id_request = '$id_request' AND kategori_barang LIKE '%IT%'"
        );
        $data_it = mysqli_fetch_assoc($cek_it);
        $has_it_item = ($data_it['total_it'] > 0);

        // Jika ada item IT dan ada file yang diupload
        if ($has_it_item && isset($_FILES['file_penawaran']) && $_FILES['file_penawaran']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Ambil data PR lama untuk hapus file lama nanti
            $pr_lama = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT file_penawaran FROM tr_request WHERE id_request = '$id_request'"
            ));

            if ($_FILES['file_penawaran']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload file penawaran gagal.");
            }

            $max_size = 5 * 1024 * 1024; // 5 MB

            if ($_FILES['file_penawaran']['size'] > $max_size) {
                throw new Exception("Ukuran file penawaran maksimal 5MB.");
            }

            $nama_asli = $_FILES['file_penawaran']['name'];
            $ext       = strtolower(pathinfo($nama_asli, PATHINFO_EXTENSION));

            if ($ext !== 'pdf') {
                throw new Exception("File penawaran harus berformat PDF.");
            }

            $upload_dir = 'C:/uploads_pr/penawaran/';

            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Folder upload penawaran gagal dibuat.");
                }
            }

            $nama_file_baru = 'penawaran_pr_' . $id_request . '_' . date('YmdHis') . '.pdf';
            $tujuan_file    = $upload_dir . $nama_file_baru;

            if (!move_uploaded_file($_FILES['file_penawaran']['tmp_name'], $tujuan_file)) {
                throw new Exception("Gagal menyimpan file penawaran.");
            }

            $nama_file_baru_safe = mysqli_real_escape_string($koneksi, $nama_file_baru);
            $file_penawaran_sql  = ", file_penawaran = '$nama_file_baru_safe'";
        }

        // --- 1. UPDATE HEADER ---
        $query_h = "UPDATE tr_request SET 
                        tgl_request  = '$tgl_request', 
                        nama_pemesan = '$nama_pemesan',
                        nama_pembeli = '$nama_pembeli',
                        updated_by   = '$user_login',
                        updated_at   = '$now'
                        $file_penawaran_sql
                    WHERE id_request = '$id_request'";
        
        if (!mysqli_query($koneksi, $query_h)) {
            throw new Exception("Gagal update header: " . mysqli_error($koneksi));
        }

        // --- HAPUS FILE LAMA JIKA UPLOAD BERHASIL ---
        if (!empty($nama_file_baru_safe) && !empty($pr_lama['file_penawaran'])) {
            $file_lama = __DIR__ . '/../../uploads/penawaran/' . $pr_lama['file_penawaran'];
            if (is_file($file_lama)) {
                @unlink($file_lama);
            }
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
        $subtotal_total = 0;
        $item_tersimpan = 0;

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

            $subtotal_total += $subtotal;
            $item_tersimpan++;
        }

        if ($item_tersimpan === 0) {
            throw new Exception("Tidak ada item valid. Pastikan barang sudah diisi.");
        }

        // --- 4. UPDATE / CREATE PO JIKA ADA ITEM IT ---
        if ($has_it_item) {
            // Ambil data supplier dari POST (jika ada)
            $id_supplier = (int)($_POST['id_supplier'] ?? 0);
            $tgl_po = mysqli_real_escape_string($koneksi, $_POST['tgl_po'] ?? date('Y-m-d'));
            $diskon = max(0, (float)($_POST['diskon'] ?? 0));
            $ppn_persen = (float)($_POST['ppn_persen'] ?? 0);
            $cat_po = mysqli_real_escape_string($koneksi, $_POST['catatan_po'] ?? '');

            $total_po = max(0, $subtotal_total - $diskon);
            $ppn_nom  = $total_po * ($ppn_persen / 100);
            $grand_po = $total_po + $ppn_nom;

            $cek_po = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT id_po, no_po
                 FROM tr_purchase_order
                 WHERE id_request = '$id_request'
                 LIMIT 1"
            ));

            if ($cek_po) {
                // Update PO existing
                $sql_po = "UPDATE tr_purchase_order SET
                            id_supplier  = '$id_supplier',
                            tgl_po       = '$tgl_po',
                            subtotal     = '$subtotal_total',
                            diskon       = '$diskon',
                            total        = '$total_po',
                            ppn_persen   = '$ppn_persen',
                            ppn_nominal  = '$ppn_nom',
                            grand_total  = '$grand_po',
                            catatan      = '$cat_po',
                            status_po    = 'DRAFT',
                            approved_by  = NULL,
                            tgl_approve  = NULL
                           WHERE id_request = '$id_request'";
            } else {
                // Insert PO baru
                $no_po_baru = 'PO-' . date('YmdHis') . '-' . $id_request;
                $no_po_baru = mysqli_real_escape_string($koneksi, $no_po_baru);

                $sql_po = "INSERT INTO tr_purchase_order
                            (
                                no_po,
                                id_request,
                                id_supplier,
                                tgl_po,
                                subtotal,
                                diskon,
                                total,
                                ppn_persen,
                                ppn_nominal,
                                grand_total,
                                catatan,
                                status_po,
                                created_by
                            )
                           VALUES
                            (
                                '$no_po_baru',
                                '$id_request',
                                '$id_supplier',
                                '$tgl_po',
                                '$subtotal_total',
                                '$diskon',
                                '$total_po',
                                '$ppn_persen',
                                '$ppn_nom',
                                '$grand_po',
                                '$cat_po',
                                'DRAFT',
                                '$user_login'
                            )";
            }

            if (!mysqli_query($koneksi, $sql_po)) {
                throw new Exception("Gagal simpan PO: " . mysqli_error($koneksi));
            }
        }

        // --- 5. RESET APPROVAL JIKA ADA PERUBAHAN ---
        // Cek apakah ada perubahan pada item (update/insert/delete)
        $ada_perubahan = false;
        
        // Cek apakah ada baris yang di-update atau di-insert
        foreach ($id_barang_array as $key => $val) {
            if (!empty($val)) {
                $ada_perubahan = true;
                break;
            }
        }

        if ($ada_perubahan) {
            // Reset semua approval
            $query_reset = "UPDATE tr_request SET
                                status_approval  = 'MENUNGGU APPROVAL',
                                approve1_by      = NULL,
                                approve1_at      = NULL,
                                catatan_approve1 = NULL,
                                approve2_by      = NULL,
                                approve2_at      = NULL,
                                catatan_approve2 = NULL,
                                approve3_by      = NULL,
                                approve3_at      = NULL,
                                catatan_approve3 = NULL,
                                approve_by       = NULL,
                                tgl_approval     = NULL,
                                tolak_by         = NULL,
                                tolak_at         = NULL,
                                catatan_tolak    = NULL
                            WHERE id_request = '$id_request'
                            AND status_request IN ('PENDING', 'PROSES')";

            if (!mysqli_query($koneksi, $query_reset)) {
                throw new Exception("Gagal reset approval: " . mysqli_error($koneksi));
            }
        }

        // --- 6. UPDATE OTOMATIS STATUS HEADER ---
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
        
        // Jika transaksi gagal tapi file baru sudah terupload,
        // hapus file baru agar tidak menjadi file yatim
        if (!empty($nama_file_baru_safe)) {
            $file_baru_gagal = __DIR__ . '/../../uploads/penawaran/' . $nama_file_baru_safe;
            if (is_file($file_baru_gagal)) {
                @unlink($file_baru_gagal);
            }
        }
        
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