<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Set header untuk JSON
header('Content-Type: application/json');

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

// Debug: cek apakah ada POST data
if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak ditemukan']);
    exit;
}

$id = (int)$_POST['id'];

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak valid']);
    exit;
}

// Query dengan prepared statement untuk keamanan
$query = "SELECT * FROM kondisi_mesin WHERE id_kondisi_mesin = ?";
$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if ($data) {
    echo json_encode(['status' => 'success', 'data' => $data]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
}

mysqli_stmt_close($stmt);
?>