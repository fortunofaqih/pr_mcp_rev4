<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['status']) || $_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Bersihkan Input
    $nama_raw     = $_POST['nama_barang'] ?? '';
    $nama_raw     = str_replace(chr(194).chr(160), ' ', $nama_raw); 
    $nama         = strtoupper(trim($nama_raw));
    
    $merk         = strtoupper(trim($_POST['merk'] ?? ''));
    $kategori     = trim($_POST['kategori'] ?? 'UMUM');
    $satuan       = strtoupper(trim($_POST['satuan'] ?? 'PCS'));
    $lokasi_rak   = strtoupper(trim($_POST['lokasi_rak'] ?? ''));
    $status       = $_POST['status_aktif'] ?? 'AKTIF';
    
    $stok_input   = (float)($_POST['stok_awal'] ?? 0);
    $harga_barang = (float)($_POST['harga_barang_stok'] ?? 0);
    $stok_minimal = 3;
    $user_login   = $_SESSION['nama'] ?? 'SYSTEM';

    // 2. Cek Duplikasi
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id_barang FROM master_barang WHERE nama_barang = ?");
    mysqli_stmt_bind_param($stmt_cek, "s", $nama);
    mysqli_stmt_execute($stmt_cek);
    mysqli_stmt_store_result($stmt_cek);

    if (mysqli_stmt_num_rows($stmt_cek) > 0) {
        mysqli_stmt_close($stmt_cek);
        header("location:barang.php?pesan=ada");
        exit;
    }
    mysqli_stmt_close($stmt_cek);

    // 3. Hitung ID Manual (Pastikan benar-benar unik)
    $q_max = mysqli_query($koneksi, "SELECT id_barang FROM master_barang ORDER BY id_barang DESC LIMIT 1");
    $r_max = mysqli_fetch_assoc($q_max);
    $id_baru = ($r_max['id_barang'] ?? 0) + 1;

    $cek_lagi = mysqli_query($koneksi, "SELECT id_barang FROM master_barang WHERE id_barang = '$id_baru'");
    while (mysqli_num_rows($cek_lagi) > 0) {
        $id_baru++;
        $cek_lagi = mysqli_query($koneksi, "SELECT id_barang FROM master_barang WHERE id_barang = '$id_baru'");
    }

    // 4. Simpan ke master_barang
    $sql_master = "INSERT INTO master_barang 
                   (id_barang, nama_barang, merk, kategori, satuan, stok_minimal, stok_akhir, harga_barang_stok, lokasi_rak, status_aktif, created_by, is_active) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

    $stmt_master = mysqli_prepare($koneksi, $sql_master);
   
	$types = "issssiddsss";

    mysqli_stmt_bind_param($stmt_master, $types, 
        $id_baru,       // 1. i - id_barang
        $nama,          // 2. s - nama_barang
        $merk,          // 3. s - merk
        $kategori,      // 4. s - kategori
        $satuan,        // 5. s - satuan
        $stok_minimal,  // 6. i - stok_minimal
        $stok_input,    // 7. d - stok_akhir
        $harga_barang,  // 8. d - harga_barang_stok
        $lokasi_rak,    // 9. s - lokasi_rak
        $status,        // 10. s - status_aktif
        $user_login     // 11. s - created_by
    );

    // 5. Eksekusi SATU KALI
    if (mysqli_stmt_execute($stmt_master)) {
        mysqli_stmt_close($stmt_master);

        // 6. Log Stok
        $q_log = mysqli_query($koneksi, "SELECT MAX(id_log) as max_log FROM tr_stok_log");
        $r_log = mysqli_fetch_assoc($q_log);
        $id_log_baru = ($r_log['max_log'] ?? 0) + 1;

        $keterangan = "SALDO AWAL";
        $tipe       = "MASUK";
        $qty_log    = (float)$stok_input;

        $sql_log = "INSERT INTO tr_stok_log (id_log, id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                    VALUES (?, ?, NOW(), ?, ?, ?, ?)";

        $stmt_log = mysqli_prepare($koneksi, $sql_log);
        mysqli_stmt_bind_param($stmt_log, "iisdss", 
            $id_log_baru,   // 1. i - id_log
            $id_baru,       // 2. i - id_barang
            $tipe,          // 3. s - tipe_transaksi
            $qty_log,       // 4. d - qty
            $keterangan,    // 5. s - keterangan
            $user_login     // 6. s - user_input
        );

        if (mysqli_stmt_execute($stmt_log)) {
            mysqli_stmt_close($stmt_log);
            header("location:barang.php?pesan=berhasil");
            exit;
        } else {
            die("Gagal simpan log stok: " . mysqli_stmt_error($stmt_log));
        }

    } else {
        die("Gagal simpan master: " . mysqli_stmt_error($stmt_master));
    }
}
?>