<?php
/**
 * AJAX: Menandai mobil SELESAI SERVIS (menutup episode yang sedang berjalan).
 *
 * Cukup mengisi end_date pada episode yang aktif. Tidak perlu input kondisi
 * dari awal lagi -- menutup episode ini SEKALIGUS menjadi bukti riwayat
 * "mobil kembali BAIK", karena status mobil dihitung dari ada/tidaknya
 * episode dengan end_date masih kosong.
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
$end_date   = $_POST['end_date']   ?? '';
$updated_by = $_SESSION['username'] ?? 'system';

if (empty($id_kondisi) || empty($end_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Tanggal selesai servis wajib diisi.']);
    exit;
}

$end_date_sql = date('Y-m-d', strtotime(str_replace('-', ' ', $end_date)));

// 1. Ambil data episode untuk validasi
$cek = mysqli_prepare($koneksi, "SELECT start_date, end_date FROM kondisi_kendaraan WHERE id_kondisi = ?");
mysqli_stmt_bind_param($cek, "i", $id_kondisi);
mysqli_stmt_execute($cek);
$row = mysqli_stmt_get_result($cek)->fetch_assoc();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Data servis tidak ditemukan.']);
    exit;
}

if ($row['end_date'] !== null) {
    echo json_encode(['status' => 'error', 'message' => 'Data servis ini sudah ditandai selesai sebelumnya.']);
    exit;
}

if ($row['start_date'] && $end_date_sql < $row['start_date']) {
    echo json_encode(['status' => 'error', 'message' => 'Tanggal selesai tidak boleh sebelum tanggal masuk servis.']);
    exit;
}

// 2. Tutup episode -> otomatis berarti mobil kembali BAIK
$stmt = mysqli_prepare($koneksi,
    "UPDATE kondisi_kendaraan SET end_date = ?, updated_by = ?, updated_at = NOW() WHERE id_kondisi = ?"
);
mysqli_stmt_bind_param($stmt, "ssi", $end_date_sql, $updated_by, $id_kondisi);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success', 'message' => 'Servis ditandai selesai. Mobil otomatis berstatus BAIK kembali.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . mysqli_error($koneksi)]);
}