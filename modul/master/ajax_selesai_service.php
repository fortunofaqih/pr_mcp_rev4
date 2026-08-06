<?php
/**
 * AJAX: Menandai mobil SELESAI SERVIS (menutup episode yang sedang berjalan).
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

// 🔧 FIX: Konversi format tanggal ke yyyy-mm-dd (support多种 format)
function convertDateToYMD($date_str) {
    // Mapping bulan Indonesia ke angka (3 huruf)
    $bulan_map = [
        'Jan' => '01', 'Feb' => '02', 'Mar' => '03', 'Apr' => '04',
        'Mei' => '05', 'Jun' => '06', 'Jul' => '07', 'Ags' => '08',
        'Sep' => '09', 'Okt' => '10', 'Nov' => '11', 'Des' => '12'
    ];
    
    // Hapus spasi berlebih
    $date_str = trim($date_str);
    
    // FORMAT 1: dd-Mmm-yyyy (contoh: 06-Ags-2026)
    if (preg_match('/^(\d{1,2})-([A-Za-z]{3})-(\d{4})$/', $date_str, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month_short = $matches[2];
        $year = $matches[3];
        
        if (isset($bulan_map[$month_short])) {
            return $year . '-' . $bulan_map[$month_short] . '-' . $day;
        }
    }
    
    // FORMAT 2: dd-mm-yyyy (contoh: 06-08-2026)
    if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $date_str, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = $matches[3];
        
        // Validasi bulan (01-12)
        if ($month >= 1 && $month <= 12) {
            return $year . '-' . $month . '-' . $day;
        }
    }
    
    // FORMAT 3: yyyy-mm-dd (sudah dalam format MySQL)
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date_str, $matches)) {
        $year = $matches[1];
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
        
        if ($month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
            return $year . '-' . $month . '-' . $day;
        }
    }
    
    // FORMAT 4: Fallback ke strtotime
    $timestamp = strtotime(str_replace(['/', '-'], ' ', $date_str));
    if ($timestamp !== false && $timestamp > 0) {
        return date('Y-m-d', $timestamp);
    }
    
    // Jika semua gagal
    return null;
}

$end_date_sql = convertDateToYMD($end_date);

if ($end_date_sql === null) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Format tanggal tidak valid: ' . $end_date . '. Gunakan format dd-mm-yyyy atau dd-Mmm-yyyy.'
    ]);
    exit;
}

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

// Validasi: tanggal selesai tidak boleh sebelum tanggal mulai
if ($row['start_date'] && $end_date_sql < $row['start_date']) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Tanggal selesai (' . date('d-m-Y', strtotime($end_date_sql)) . 
                    ') tidak boleh sebelum tanggal masuk servis (' . 
                    date('d-m-Y', strtotime($row['start_date'])) . ').'
    ]);
    exit;
}

// 2. Tutup episode
$stmt = mysqli_prepare($koneksi,
    "UPDATE kondisi_kendaraan SET end_date = ?, updated_by = ?, updated_at = NOW() WHERE id_kondisi = ?"
);
mysqli_stmt_bind_param($stmt, "ssi", $end_date_sql, $updated_by, $id_kondisi);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success', 'message' => 'Servis ditandai selesai. Mobil otomatis berstatus BAIK kembali.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan: ' . mysqli_error($koneksi)]);
}
?>