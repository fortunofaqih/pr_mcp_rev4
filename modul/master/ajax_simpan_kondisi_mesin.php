<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

$id_mesin = (int)$_POST['id_mesin'];
$kondisi_mesin = $_POST['kondisi_mesin'];
$keterangan_service_mesin = $_POST['keterangan_service_mesin'] ?? '';
$start_date = $_POST['start_date'];
$created_by = $_SESSION['username'] ?? 'System';

if ($id_mesin <= 0 || empty($kondisi_mesin) || empty($start_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap']);
    exit;
}

// Cek apakah ada kondisi yang masih aktif (end_date NULL)
$cek = mysqli_query($koneksi, "SELECT id_kondisi_mesin FROM kondisi_mesin WHERE id_mesin = $id_mesin AND end_date IS NULL");
if (mysqli_num_rows($cek) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Mesin ini masih memiliki kondisi aktif yang belum selesai!']);
    exit;
}

$query = "INSERT INTO kondisi_mesin (id_mesin, kondisi_mesin, keterangan_service_mesin, start_date, created_by) 
          VALUES ($id_mesin, '$kondisi_mesin', '$keterangan_service_mesin', '$start_date', '$created_by')";

if (mysqli_query($koneksi, $query)) {
    echo json_encode(['status' => 'success', 'message' => 'Data kondisi berhasil disimpan']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . mysqli_error($koneksi)]);
}