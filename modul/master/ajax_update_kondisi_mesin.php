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
if (!isset($_POST['id_edit_kondisi']) || empty($_POST['id_edit_kondisi'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID tidak ditemukan']);
    exit;
}

$id = (int)$_POST['id_edit_kondisi'];
$kondisi_mesin = $_POST['kondisi_mesin'] ?? '';
$keterangan_service_mesin = $_POST['keterangan_service_mesin'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? null;
$updated_by = $_SESSION['username'] ?? 'System';

if ($id <= 0 || empty($kondisi_mesin) || empty($start_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

// Jika end_date kosong, set ke NULL
$end_date_sql = $end_date ? "'$end_date'" : "NULL";

// Gunakan prepared statement untuk keamanan
if ($end_date) {
    $query = "UPDATE kondisi_mesin 
              SET kondisi_mesin = ?,
                  keterangan_service_mesin = ?,
                  start_date = ?,
                  end_date = ?,
                  updated_by = ?,
                  updated_at = NOW()
              WHERE id_kondisi_mesin = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "sssssi", $kondisi_mesin, $keterangan_service_mesin, $start_date, $end_date, $updated_by, $id);
} else {
    $query = "UPDATE kondisi_mesin 
              SET kondisi_mesin = ?,
                  keterangan_service_mesin = ?,
                  start_date = ?,
                  end_date = NULL,
                  updated_by = ?,
                  updated_at = NOW()
              WHERE id_kondisi_mesin = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "ssssi", $kondisi_mesin, $keterangan_service_mesin, $start_date, $updated_by, $id);
}

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success', 'message' => 'Data kondisi berhasil diupdate']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal mengupdate data: ' . mysqli_error($koneksi)]);
}

mysqli_stmt_close($stmt);
?>