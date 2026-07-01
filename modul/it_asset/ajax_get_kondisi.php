<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

header('Content-Type: application/json');

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['administrator', 'it'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = [];
$q = mysqli_query($koneksi, "SELECT nama_kondisi FROM master_it_kondisi ORDER BY nama_kondisi");
while ($row = mysqli_fetch_assoc($q)) {
    $data[] = ['nama_kondisi' => $row['nama_kondisi']];
}

echo json_encode(['status' => 'success', 'data' => $data]);
?>