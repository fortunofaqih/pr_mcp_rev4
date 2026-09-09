<?php
/**
 * hapus_data.php (proses delete Fund Transfer BCA)
 *
 * PERBAIKAN:
 * 1. Variabel koneksi database yang benar adalah $koneksi (dari koneksi.php),
 *    bukan $conn. Sebelumnya $conn tidak pernah didefinisikan -> null ->
 *    $conn->prepare() memicu fatal error "Call to a member function
 *    prepare() on null", yang tercetak sebagai HTML ke output sebelum
 *    json_encode() dipanggil -> response jadi bukan JSON valid.
 * 2. Ditambahkan output buffering + display_errors dimatikan, supaya
 *    warning/notice apa pun tidak pernah bocor ke response JSON.
 * 3. catch (\Throwable $e) supaya semua jenis error (termasuk fatal Error)
 *    tetap menghasilkan JSON yang valid, bukan halaman error HTML.
 * 4. Validasi hasil prepare() sebelum dipakai.
 */

session_start();

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Bersihkan output apa pun yang mungkin sudah tercetak dari file yang di-require
ob_clean();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
        exit;
    }

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
        exit;
    }

    // Cek apakah data ada
    $checkQuery = "SELECT id FROM fund_transfer_bca WHERE id = ?";
    $checkStmt = $koneksi->prepare($checkQuery);

    if ($checkStmt === false) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query cek data: ' . $koneksi->error]);
        exit;
    }

    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
        exit;
    }
    $checkStmt->close();

    // Hapus data
    $deleteQuery = "DELETE FROM fund_transfer_bca WHERE id = ?";
    $deleteStmt = $koneksi->prepare($deleteQuery);

    if ($deleteStmt === false) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query hapus: ' . $koneksi->error]);
        exit;
    }

    $deleteStmt->bind_param('i', $id);

    if ($deleteStmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Data berhasil dihapus']);
    } else {
        $errMsg = $deleteStmt->error;
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus data: ' . $errMsg]);
    }

    $deleteStmt->close();

} catch (\Throwable $e) {
    error_log('hapus_data.php error: ' . $e->getMessage());
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

if (isset($koneksi)) {
    $koneksi->close();
}