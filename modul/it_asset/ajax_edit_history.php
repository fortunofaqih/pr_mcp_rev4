<?php
// Hapus session_start() karena sudah ada di check_session.php
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
$nama = $_SESSION['nama'] ?? '';

// Validasi
if (!$id_history) {
    echo json_encode(['status' => 'error', 'message' => 'ID riwayat tidak valid']);
    exit;
}

if (!$tgl_kejadian) {
    echo json_encode(['status' => 'error', 'message' => 'Tanggal kejadian harus diisi']);
    exit;
}

// Gunakan prepared statement untuk keamanan
$stmt = mysqli_prepare($koneksi, "SELECT id_history FROM tr_it_asset_history WHERE id_history = ?");
mysqli_stmt_bind_param($stmt, "i", $id_history);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Riwayat tidak ditemukan']);
    mysqli_stmt_close($stmt);
    exit;
}
mysqli_stmt_close($stmt);

// Update dengan prepared statement
$stmt = mysqli_prepare($koneksi, "UPDATE tr_it_asset_history SET 
                                tgl_kejadian = ?,
                                keterangan = ?,
                                updated_by = ?,
                                updated_at = NOW()
                                WHERE id_history = ?");

mysqli_stmt_bind_param($stmt, "sssi", $tgl_kejadian, $keterangan, $nama, $id_history);

if (mysqli_stmt_execute($stmt)) {
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
        'message' => 'Gagal update: ' . mysqli_stmt_error($stmt)
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($koneksi);
?>