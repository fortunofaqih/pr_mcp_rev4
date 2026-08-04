<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    $query = "DELETE FROM master_mesin WHERE id = $id";
    
    if (mysqli_query($koneksi, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Data mesin berhasil dihapus!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data: ' . mysqli_error($koneksi)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>