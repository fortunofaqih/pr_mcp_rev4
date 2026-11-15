<?php
// UPDATE MOBIL - ID mobil permanen, histori pergantian plat
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if (!isset($_SESSION['status']) || $_SESSION['status'] != 'login') {
    header('Location: ../../login.php?pesan=belum_login');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: data_mobil.php');
    exit;
}

function mobilInput($key) {
    return strtoupper(trim((string)($_POST[$key] ?? '')));
}
function mobilPlatNormal($value) {
    return strtoupper(preg_replace('/\s+/', '', $value));
}

$id = filter_var($_POST['id_mobil'] ?? null, FILTER_VALIDATE_INT);
$plat_nomor = mobilInput('plat_nomor');
$driver_tetap = mobilInput('driver_tetap');
$jenis_kendaraan = mobilInput('jenis_kendaraan');
$kategori_kendaraan = mobilInput('kategori_kendaraan');
$merk_tipe = mobilInput('merk_tipe');
$tahun_kendaraan = mobilInput('tahun_kendaraan');
$status = mobilInput('status_aktif');
$user_login = (string)($_SESSION['nama'] ?? '');

if (!$id || $plat_nomor === '' || $driver_tetap === '' || $jenis_kendaraan === '' ||
    $merk_tipe === '' || !in_array($kategori_kendaraan, ['PERUSAHAAN','PRIBADI'], true) ||
    !in_array($status, ['AKTIF','NONAKTIF'], true) ||
    !preg_match('/^(19|20)\d{2}$/', $tahun_kendaraan) || strlen($plat_nomor) > 20 ||
    strlen($driver_tetap) > 100 || strlen($jenis_kendaraan) > 50 || strlen($merk_tipe) > 50) {
    http_response_code(400);
    exit('Data mobil tidak valid. Kembali dan periksa kolom input.');
}

mysqli_begin_transaction($koneksi);
try {
    // Lock record agar histori mencatat plat yang benar walau dua user edit bersamaan.
    $stmt = mysqli_prepare($koneksi, 'SELECT plat_nomor FROM master_mobil WHERE id_mobil = ? FOR UPDATE');
    if (!$stmt) throw new Exception(mysqli_error($koneksi));
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    mysqli_stmt_bind_result($stmt, $plat_lama);
    if (!mysqli_stmt_fetch($stmt)) throw new Exception('Mobil tidak ditemukan.');
    mysqli_stmt_close($stmt);

    // Periksa duplikasi menggunakan bentuk normal yang mengabaikan spasi dan kapital.
    $plat_normal = mobilPlatNormal($plat_nomor);
    $stmt = mysqli_prepare($koneksi, "SELECT id_mobil FROM master_mobil WHERE UPPER(REPLACE(plat_nomor, ' ', '')) = ? AND id_mobil <> ? LIMIT 1");
    if (!$stmt) throw new Exception(mysqli_error($koneksi));
    mysqli_stmt_bind_param($stmt, 'si', $plat_normal, $id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt) > 0) throw new Exception('Plat nomor sudah digunakan kendaraan lain.');
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($koneksi, 'UPDATE master_mobil SET plat_nomor=?, driver_tetap=?, jenis_kendaraan=?, kategori_kendaraan=?, merk_tipe=?, tahun_kendaraan=?, status_aktif=?, updated_by=?, updated_at=NOW() WHERE id_mobil=?');
    if (!$stmt) throw new Exception(mysqli_error($koneksi));
    mysqli_stmt_bind_param($stmt, 'ssssssssi', $plat_nomor, $driver_tetap, $jenis_kendaraan, $kategori_kendaraan, $merk_tipe, $tahun_kendaraan, $status, $user_login, $id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    if (mobilPlatNormal($plat_lama) !== $plat_normal) {
        $stmt = mysqli_prepare($koneksi, 'INSERT INTO histori_plat_mobil (id_mobil, plat_lama, plat_baru, tanggal_perubahan, updated_by) VALUES (?, ?, ?, NOW(), ?)');
        if (!$stmt) throw new Exception(mysqli_error($koneksi));
        mysqli_stmt_bind_param($stmt, 'isss', $id, $plat_lama, $plat_nomor, $user_login);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
    }

    mysqli_commit($koneksi);
    header('Location: data_mobil.php?pesan=update_sukses');
    exit;
} catch (Throwable $e) {
    mysqli_rollback($koneksi);
    error_log('Update Mobil gagal ID '.$id.': '.$e->getMessage());
    http_response_code(400);
    echo 'Perubahan mobil tidak tersimpan: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
