<?php
// ajax_close_po.php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

header('Content-Type: application/json');

// Cek session
if ($_SESSION['status'] !== 'login') {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Ambil parameter
$id_po = (int)($_POST['id_po'] ?? 0);
$id_request = (int)($_POST['id_request'] ?? 0);
$now = date('Y-m-d H:i:s');
$username = $_SESSION['username'] ?? 'system';

if (!$id_po || !$id_request) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']);
    exit;
}

// Mulai transaksi
mysqli_begin_transaction($koneksi);

try {
    // Update status PO menjadi CLOSE
    $query_po = "UPDATE tr_purchase_order 
                 SET status_po = 'CLOSE' 
                 WHERE id_po = $id_po AND status_po = 'OPEN'";
    
    if (!mysqli_query($koneksi, $query_po)) {
        throw new Exception('Gagal update status PO: ' . mysqli_error($koneksi));
    }
    
    // Cek apakah PO berhasil diupdate
    if (mysqli_affected_rows($koneksi) == 0) {
        throw new Exception('PO sudah dalam status CLOSE atau tidak ditemukan');
    }
    
    // Update status request menjadi SELESAI
    $query_request = "UPDATE tr_request 
                      SET status_request = 'SELESAI', 
                          updated_by = '$username', 
                          updated_at = '$now' 
                      WHERE id_request = $id_request AND status_request != 'SELESAI'";
    
    mysqli_query($koneksi, $query_request);
    
    // Commit transaksi
    mysqli_commit($koneksi);
    
    echo json_encode([
        'success' => true,
        'message' => 'PO berhasil ditutup',
        'id_po' => $id_po,
        'id_request' => $id_request
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>