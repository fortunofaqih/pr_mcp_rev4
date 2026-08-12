<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

$id = (int)$_POST['id_kondisi_selesai'];
$end_date = $_POST['end_date'];
$kondisi_mesin = $_POST['kondisi_mesin'] ?? 'BAIK';
$updated_by = $_SESSION['username'] ?? 'System';

if ($id <= 0 || empty($end_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

// Mulai transaksi
mysqli_begin_transaction($koneksi);

try {
    // 1. Update end_date dan kondisi pada record yang sedang aktif
    $query_update = "UPDATE kondisi_mesin 
                     SET end_date = '$end_date', 
                         kondisi_mesin = '$kondisi_mesin',
                         updated_by = '$updated_by',
                         updated_at = NOW()
                     WHERE id_kondisi_mesin = $id";
    
    if (!mysqli_query($koneksi, $query_update)) {
        throw new Exception('Gagal mengupdate kondisi: ' . mysqli_error($koneksi));
    }
    
    // 2. Ambil id_mesin dari record yang diupdate
    $query_id_mesin = "SELECT id_mesin FROM kondisi_mesin WHERE id_kondisi_mesin = $id";
    $result = mysqli_query($koneksi, $query_id_mesin);
    $row = mysqli_fetch_assoc($result);
    $id_mesin = $row['id_mesin'];
    
    // 3. Jika kondisi diubah menjadi BAIK, buat record baru dengan kondisi BAIK
    if ($kondisi_mesin == 'BAIK') {
        // Cek apakah sudah ada record BAIK setelah tanggal selesai
        $query_cek = "SELECT id_kondisi_mesin FROM kondisi_mesin 
                      WHERE id_mesin = $id_mesin 
                      AND kondisi_mesin = 'BAIK' 
                      AND start_date >= '$end_date'";
        $cek = mysqli_query($koneksi, $query_cek);
        
        if (mysqli_num_rows($cek) == 0) {
            // Buat record baru dengan kondisi BAIK
            $query_insert = "INSERT INTO kondisi_mesin 
                            (id_mesin, kondisi_mesin, keterangan_service_mesin, start_date, created_by) 
                            VALUES 
                            ($id_mesin, 'BAIK', 'Kondisi baik setelah service', '$end_date', '$updated_by')";
            
            if (!mysqli_query($koneksi, $query_insert)) {
                throw new Exception('Gagal menambahkan record kondisi BAIK: ' . mysqli_error($koneksi));
            }
        }
    }
    
    // Commit transaksi
    mysqli_commit($koneksi);
    
    echo json_encode(['status' => 'success', 'message' => 'Service berhasil diselesaikan dan kondisi telah diupdate']);
    
} catch (Exception $e) {
    // Rollback jika ada error
    mysqli_rollback($koneksi);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>