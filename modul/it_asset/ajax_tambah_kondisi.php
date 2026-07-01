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

$nama_kondisi = trim($_POST['nama_kondisi'] ?? '');

if (empty($nama_kondisi)) {
    echo json_encode(['status' => 'error', 'message' => 'Nama kondisi tidak boleh kosong']);
    exit;
}

if (strlen($nama_kondisi) > 150) {
    echo json_encode(['status' => 'error', 'message' => 'Nama kondisi maksimal 150 karakter']);
    exit;
}

$nama_kondisi = mysqli_real_escape_string($koneksi, strtoupper($nama_kondisi));

$cek = mysqli_query($koneksi, "SELECT id_kondisi FROM master_it_kondisi WHERE UPPER(nama_kondisi) = UPPER('$nama_kondisi')");
if (mysqli_num_rows($cek) > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Kondisi "' . $nama_kondisi . '" sudah ada']);
    exit;
}

$sql = "INSERT INTO master_it_kondisi (nama_kondisi) VALUES ('$nama_kondisi')";
if (mysqli_query($koneksi, $sql)) {
    echo json_encode([
        'status' => 'success', 
        'nama_kondisi' => $nama_kondisi,
        'message' => 'Kondisi berhasil ditambahkan'
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Gagal menambahkan: ' . mysqli_error($koneksi)
    ]);
}
?>