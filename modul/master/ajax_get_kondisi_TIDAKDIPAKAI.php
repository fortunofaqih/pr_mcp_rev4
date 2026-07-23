<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

if (!isset($_POST['id_kondisi']) || empty($_POST['id_kondisi'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID kondisi tidak valid']);
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_POST['id_kondisi']);
$query = "SELECT k.*, m.driver_tetap, m.jenis_kendaraan, m.kategori_kendaraan, m.merk_tipe, m.status_aktif 
          FROM kondisi_kendaraan k 
          JOIN master_mobil m ON k.id_mobil = m.id_mobil 
          WHERE k.id_kondisi = '$id'";
$result = mysqli_query($koneksi, $query);

if (mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);
    echo json_encode([
        'status' => 'success',
        'data' => $data
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Data kondisi tidak ditemukan']);
}
?>