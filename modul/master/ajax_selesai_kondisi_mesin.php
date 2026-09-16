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

mysqli_begin_transaction($koneksi);

try {
    // Update end_date dan kondisi pada record yang sedang aktif
    $query_update = "UPDATE kondisi_mesin 
                     SET end_date = '$end_date', 
                         kondisi_mesin = '$kondisi_mesin',
                         updated_by = '$updated_by',
                         updated_at = NOW()
                     WHERE id_kondisi_mesin = $id";
    
    if (!mysqli_query($koneksi, $query_update)) {
        throw new Exception('Gagal mengupdate kondisi: ' . mysqli_error($koneksi));
    }
    
    mysqli_commit($koneksi);
    echo json_encode(['status' => 'success', 'message' => 'Service berhasil diselesaikan']);
    
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>