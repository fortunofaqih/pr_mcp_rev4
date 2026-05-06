<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Pastikan tidak ada output (spasi/echo) sebelum header ini
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Ambil data header form
    $id_req_raw   = !empty($_POST['id_request']) ? mysqli_real_escape_string($koneksi, $_POST['id_request']) : "";
    $id_request   = ($id_req_raw != "") ? "'$id_req_raw'" : "NULL"; 
    $nama_pemesan = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_pemesan'] ?? ''));
    $nama_pembeli = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_pembeli'] ?? ''));
    $username     = $_SESSION['username'] ?? 'SYSTEM';
    $id_user_beli = $_SESSION['id_user'] ?? 0;

    // Ambil Data Array dari Form
    $id_req_detail_arr = $_POST['id_request_detail'] ?? [];
    $id_barang_arr     = $_POST['id_barang'] ?? []; // Pastikan dikirim dari get_pr_detail
    $tgl_nota_arr      = $_POST['tgl_beli_barang'] ?? [];
    $nama_barang_arr   = $_POST['nama_barang'] ?? [];
    $plat_nomor_arr    = $_POST['plat_nomor'] ?? [];
    $qty_arr           = $_POST['qty'] ?? [];
    $harga_satuan_arr  = $_POST['harga_satuan'] ?? [];
    $supplier_arr      = $_POST['supplier'] ?? [];
    $kategori_arr      = $_POST['kategori_beli'] ?? [];
    $alokasi_arr       = $_POST['alokasi_stok'] ?? [];
    $ket_arr           = $_POST['keterangan'] ?? [];

    mysqli_begin_transaction($koneksi);

    try {
        foreach ($id_req_detail_arr as $key => $id_det_req) {
            
            // Sanitasi data per baris
            $id_det   = mysqli_real_escape_string($koneksi, $id_det_req);
            $id_brg   = mysqli_real_escape_string($koneksi, $id_barang_arr[$key] ?? '');
            $nama_brg = mysqli_real_escape_string($koneksi, strtoupper($nama_barang_arr[$key] ?? ''));
            $qty      = (float)($qty_arr[$key] ?? 0);
            $harga    = (float)($harga_satuan_arr[$key] ?? 0);
            $supplier = mysqli_real_escape_string($koneksi, strtoupper($supplier_arr[$key] ?? ''));
            $plat     = mysqli_real_escape_string($koneksi, strtoupper($plat_nomor_arr[$key] ?? '-'));
            $alokasi  = $alokasi_arr[$key] ?? 'LANGSUNG PAKAI';
            $tgl_nota = !empty($tgl_nota_arr[$key]) ? date('Y-m-d', strtotime($tgl_nota_arr[$key])) : date('Y-m-d');
            $ket      = mysqli_real_escape_string($koneksi, strtoupper($ket_arr[$key] ?? ''));

            if ($qty <= 0) continue; 

            // 1. Simpan ke Tabel Pembelian (Sesuai Struktur Tabel Anda)
            $q_beli = "INSERT INTO pembelian 
                       (id_request, id_request_detail, tgl_beli, tgl_beli_barang, supplier, 
                        nama_barang_beli, qty, harga, kategori_beli, alokasi_stok, 
                        nama_pemesan, driver, plat_nomor, keterangan, id_user_beli, sumber_data) 
                       VALUES 
                       ($id_request, '$id_det', CURDATE(), '$tgl_nota', '$supplier', 
                        '$nama_brg', '$qty', '$harga', '{$kategori_arr[$key]}', '$alokasi', 
                        '$nama_pemesan', '$nama_pembeli', '$plat', '$ket', '$id_user_beli', 'SISTEM')";
            
            if (!mysqli_query($koneksi, $q_beli)) {
                throw new Exception("Gagal simpan pembelian: " . mysqli_error($koneksi));
            }
            $id_pembelian_baru = mysqli_insert_id($koneksi);

            // 2. Logika Update Stok & Log (Jika Masuk Stok)
            if ($alokasi == 'MASUK STOK' && !empty($id_brg)) {
                $u_stok = mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty WHERE id_barang = '$id_brg'");
                if (!$u_stok) throw new Exception("Gagal update stok di master_barang.");
                
                $ket_log = "PEMBELIAN: $supplier (ID: $id_pembelian_baru)";
                mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                                       VALUES ('$id_brg', NOW(), 'MASUK', $qty, '$ket_log', '$username')");
            }

            // 3. Update Status Item di Detail PR
            mysqli_query($koneksi, "UPDATE tr_request_detail SET status_item = 'TERBELI', is_dibeli = 1 WHERE id_detail = '$id_det'");
        }

        // 4. Update Status Header PR
        if ($id_req_raw != "") {
            $cek_sisa = mysqli_query($koneksi, "SELECT id_detail FROM tr_request_detail 
                                                WHERE id_request = '$id_req_raw' 
                                                AND status_item IN ('PENDING', 'APPROVED')");
                                                
            $status_baru = (mysqli_num_rows($cek_sisa) == 0) ? "SELESAI" : "PROSES";
            mysqli_query($koneksi, "UPDATE tr_request SET status_request = '$status_baru' WHERE id_request = '$id_req_raw'");
        }

        mysqli_commit($koneksi);
        
        // Menggunakan redirect header agar lebih aman dari error sintaks JS
        header("Location: index.php?pesan=sukses");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        // Jika error, baru gunakan alert JS dengan pembersihan karakter
        $error_msg = str_replace(array("\r", "\n", "'"), "", $e->getMessage());
        echo "<script>alert('Gagal Simpan: $error_msg'); window.history.back();</script>";
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}