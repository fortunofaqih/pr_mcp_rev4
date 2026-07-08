<?php
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Set header JSON
header('Content-Type: application/json');

// Cek role
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['administrator', 'it'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Cek method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan']);
    exit;
}

// Ambil data
$id_asset = isset($_POST['id_asset']) ? (int)$_POST['id_asset'] : 0;
$kondisi = isset($_POST['kondisi']) ? trim($_POST['kondisi']) : '';
$keterangan_kondisi = isset($_POST['keterangan_kondisi']) ? trim($_POST['keterangan_kondisi']) : '';
$nama = $_SESSION['nama'] ?? '';

// Validasi
if (!$id_asset) {
    echo json_encode(['status' => 'error', 'message' => 'ID aset tidak valid']);
    exit;
}

if (empty($kondisi)) {
    echo json_encode(['status' => 'error', 'message' => 'Kondisi harus diisi']);
    exit;
}

// Cek asset dengan prepared statement
$stmt = mysqli_prepare($koneksi, "SELECT id_asset, kondisi, keterangan_kondisi FROM master_it_asset WHERE id_asset = ?");
mysqli_stmt_bind_param($stmt, "i", $id_asset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Asset tidak ditemukan']);
    mysqli_stmt_close($stmt);
    exit;
}

$asset_data = mysqli_fetch_assoc($result);
$kondisi_sebelum = $asset_data['kondisi'];
$keterangan_kondisi_sebelum = $asset_data['keterangan_kondisi'] ?? '';
mysqli_stmt_close($stmt);

// Validasi kondisi di master_it_kondisi
$stmt = mysqli_prepare($koneksi, "SELECT id_kondisi FROM master_it_kondisi WHERE nama_kondisi = ?");
mysqli_stmt_bind_param($stmt, "s", $kondisi);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Kondisi tidak valid']);
    mysqli_stmt_close($stmt);
    exit;
}
mysqli_stmt_close($stmt);

// Update kondisi di master_it_asset
$stmt = mysqli_prepare($koneksi, "UPDATE master_it_asset SET 
                                kondisi = ?,
                                keterangan_kondisi = ?,
                                updated_by = ?,
                                updated_at = NOW()
                                WHERE id_asset = ?");

mysqli_stmt_bind_param($stmt, "sssi", $kondisi, $keterangan_kondisi, $nama, $id_asset);

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Gagal update: ' . mysqli_stmt_error($stmt)
    ]);
    mysqli_stmt_close($stmt);
    exit;
}
mysqli_stmt_close($stmt);

// Insert history dengan struktur tabel yang benar
$jenis_history = 'KONDISI UPDATE';
$keterangan_history = "Update kondisi dari '$kondisi_sebelum' menjadi '$kondisi'";

$stmt = mysqli_prepare($koneksi, "INSERT INTO tr_it_asset_history 
                                (id_asset, tgl_kejadian, jenis_history, 
                                 kondisi_sebelum, kondisi_sesudah,
                                 keterangan_kondisi_sebelum, keterangan_kondisi_sesudah,
                                 keterangan, created_by, created_at) 
                                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())");

mysqli_stmt_bind_param($stmt, "isssssss", 
    $id_asset, 
    $jenis_history, 
    $kondisi_sebelum, 
    $kondisi,
    $keterangan_kondisi_sebelum,
    $keterangan_kondisi,
    $keterangan_history,
    $nama
);

if (!mysqli_stmt_execute($stmt)) {
    // Log error tapi tetap response sukses karena update utama berhasil
    error_log("Gagal insert history: " . mysqli_stmt_error($stmt));
}
mysqli_stmt_close($stmt);

// Response sukses
echo json_encode([
    'status' => 'success', 
    'message' => 'Kondisi berhasil diperbarui'
]);

mysqli_close($koneksi);
?>