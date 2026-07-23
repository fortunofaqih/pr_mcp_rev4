<?php
/**
 * AJAX: Mencatat mobil MASUK SERVIS (membuka episode baru).
 *
 * Aturan: satu mobil hanya boleh punya SATU episode yang sedang berjalan
 * (end_date IS NULL) di satu waktu. Kalau masih ada yang aktif, harus
 * diselesaikan dulu lewat ajax_selesai_service.php sebelum bisa buka baru.
 */
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

header('Content-Type: application/json');

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Sesi tidak valid, silakan login ulang.']);
    exit;
}

$id_mobil   = $_POST['id_mobil']   ?? '';
$plat_nomor = $_POST['plat_nomor'] ?? '';
$kondisi    = $_POST['kondisi']    ?? '';
$start_date = $_POST['start_date'] ?? '';
$keterangan = $_POST['keterangan'] ?? '';
$created_by = $_SESSION['username'] ?? 'system';

$kondisi_valid = ['DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT'];

if (empty($id_mobil) || empty($plat_nomor) || !in_array($kondisi, $kondisi_valid, true) || empty($start_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Mobil, kondisi, dan tanggal masuk servis wajib diisi.']);
    exit;
}

$start_date_sql = date('Y-m-d', strtotime(str_replace('-', ' ', $start_date)));

// 1. Cek apakah mobil ini masih punya episode servis yang belum ditutup
$cek = mysqli_prepare($koneksi,
    "SELECT id_kondisi, kondisi, start_date FROM kondisi_kendaraan
     WHERE id_mobil = ? AND end_date IS NULL LIMIT 1"
);
mysqli_stmt_bind_param($cek, "i", $id_mobil);
mysqli_stmt_execute($cek);
$row_aktif = mysqli_stmt_get_result($cek)->fetch_assoc();

if ($row_aktif) {
    echo json_encode([
        'status'  => 'error',
        'message' => "Mobil ini masih berstatus {$row_aktif['kondisi']} sejak "
                    . date('d-M-Y', strtotime($row_aktif['start_date']))
                    . ". Selesaikan dulu servis yang sedang berjalan sebelum menambah data baru."
    ]);
    exit;
}

// 2. Simpan episode baru
$stmt = mysqli_prepare($koneksi,
    "INSERT INTO kondisi_kendaraan (id_mobil, plat_nomor, kondisi, keterangan, start_date, created_by)
     VALUES (?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, "isssss", $id_mobil, $plat_nomor, $kondisi, $keterangan, $start_date_sql, $created_by);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success', 'message' => 'Mobil berhasil dicatat masuk servis.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . mysqli_error($koneksi)]);
}