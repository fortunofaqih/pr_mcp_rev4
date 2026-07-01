<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Set header JSON
header('Content-Type: application/json');

// Cek role
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['administrator', 'it'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized - Anda tidak memiliki akses']);
    exit;
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan']);
    exit;
}

// Ambil data
$id_history = isset($_POST['id_history']) ? (int)$_POST['id_history'] : 0;
$tgl_kejadian = isset($_POST['tgl_kejadian']) ? trim($_POST['tgl_kejadian']) : '';
$keterangan = isset($_POST['keterangan']) ? trim($_POST['keterangan']) : '';

// Validasi
if (!$id_history) {
    echo json_encode(['status' => 'error', 'message' => 'ID riwayat tidak valid']);
    exit;
}

if (!$tgl_kejadian) {
    echo json_encode(['status' => 'error', 'message' => 'Tanggal kejadian harus diisi']);
    exit;
}

// Escape string
$tgl_kejadian = mysqli_real_escape_string($koneksi, $tgl_kejadian);
$keterangan = mysqli_real_escape_string($koneksi, $keterangan);
$nama = mysqli_real_escape_string($koneksi, $_SESSION['nama'] ?? '');

// Cek apakah data ada
$cek = mysqli_query($koneksi, "SELECT id_history FROM tr_it_asset_history WHERE id_history = $id_history");
if (mysqli_num_rows($cek) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Riwayat tidak ditemukan']);
    exit;
}

// Update data
$sql = "UPDATE tr_it_asset_history SET 
        tgl_kejadian = '$tgl_kejadian',
        keterangan = '$keterangan',
        updated_by = '$nama',
        updated_at = NOW()
        WHERE id_history = $id_history";

if (mysqli_query($koneksi, $sql)) {
    echo json_encode([
        'status' => 'success', 
        'message' => 'Riwayat berhasil diperbarui',
        'data' => [
            'id_history' => $id_history,
            'tgl_kejadian' => $tgl_kejadian,
            'keterangan' => $keterangan
        ]
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Gagal update: ' . mysqli_error($koneksi)
    ]);
}
?>