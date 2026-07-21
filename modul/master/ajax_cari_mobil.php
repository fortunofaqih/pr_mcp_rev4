<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

if (!isset($_POST['plat_nomor']) || empty($_POST['plat_nomor'])) {
    echo json_encode(['status' => 'error', 'message' => 'Plat nomor tidak boleh kosong']);
    exit;
}

$plat = mysqli_real_escape_string($koneksi, $_POST['plat_nomor']);
$query = "SELECT * FROM master_mobil WHERE plat_nomor = '$plat'";
$result = mysqli_query($koneksi, $query);

if (mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);
    echo json_encode([
        'status' => 'success',
        'data' => [
            'id_mobil' => $data['id_mobil'],
            'plat_nomor' => $data['plat_nomor'],
            'driver_tetap' => $data['driver_tetap'],
            'jenis_kendaraan' => $data['jenis_kendaraan'],
            'kategori_kendaraan' => $data['kategori_kendaraan'],
            'merk_tipe' => $data['merk_tipe'],
            'status_aktif' => $data['status_aktif'] ?? 'AKTIF'
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Mobil tidak ditemukan']);
}
?>