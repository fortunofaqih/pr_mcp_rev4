<?php
/**
 * AJAX: Menghapus satu baris riwayat kondisi (dipakai untuk koreksi data
 * yang salah input, bukan alur normal).
 */
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

header('Content-Type: application/json');

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid, silakan login ulang.']);
    exit;
}

$id_kondisi = $_POST['id_kondisi'] ?? '';
if (empty($id_kondisi)) {
    echo json_encode(['status' => 'error', 'message' => 'ID data tidak valid.']);
    exit;
}

$stmt = mysqli_prepare($koneksi, "DELETE FROM kondisi_kendaraan WHERE id_kondisi = ?");
mysqli_stmt_bind_param($stmt, "i", $id_kondisi);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success', 'message' => 'Data riwayat berhasil dihapus.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus: ' . mysqli_error($koneksi)]);
}