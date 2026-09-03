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
$kondisi    = $_POST['kondisi']    ?? 'DISERVICE'; // Default DISERVICE
$start_date = $_POST['start_date'] ?? '';
$bengkel    = $_POST['bengkel']    ?? ''; // Tambahan field bengkel
$keterangan = $_POST['keterangan'] ?? '';
$created_by = $_SESSION['username'] ?? 'system';

// Validasi kondisi - hanya DISERVICE yang diizinkan untuk input baru
$kondisi_valid = ['DISERVICE']; // Hanya DISERVICE

// Validasi bengkel
$bengkel_valid = ['MISTARI', 'RUDI H.', 'EDI M.', 'M. ULUM', 'SAIFUL'];

if (empty($id_mobil) || empty($plat_nomor) || !in_array($kondisi, $kondisi_valid, true) || empty($start_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Mobil, kondisi (DISERVICE), dan tanggal masuk servis wajib diisi.']);
    exit;
}

if (empty($bengkel) || !in_array($bengkel, $bengkel_valid, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Pilih bengkel yang valid.']);
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
    
    // FORMAT 4: dd/mm/yyyy (contoh: 06/08/2026)
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_str, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = $matches[3];
        
        if ($month >= 1 && $month <= 12) {
            return $year . '-' . $month . '-' . $day;
        }
    }
    
    // FORMAT 5: Fallback ke strtotime
    $timestamp = strtotime(str_replace(['/', '-'], ' ', $date_str));
    if ($timestamp !== false && $timestamp > 0) {
        return date('Y-m-d', $timestamp);
    }
    
    // Jika semua gagal
    return null;
}

$start_date_sql = convertDateToYMD($start_date);

if ($start_date_sql === null) {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Format tanggal tidak valid: ' . $start_date . '. Gunakan format dd-mm-yyyy atau dd-Mmm-yyyy.'
    ]);
    exit;
}

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
                    . date('d-m-Y', strtotime($row_aktif['start_date']))
                    . ". Selesaikan dulu servis yang sedang berjalan sebelum menambah data baru."
    ]);
    exit;
}

// 2. Simpan episode baru dengan field bengkel
$stmt = mysqli_prepare($koneksi,
    "INSERT INTO kondisi_kendaraan (id_mobil, plat_nomor, kondisi, bengkel, keterangan, start_date, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, "issssss", $id_mobil, $plat_nomor, $kondisi, $bengkel, $keterangan, $start_date_sql, $created_by);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => 'success', 'message' => 'Mobil berhasil dicatat masuk servis di bengkel ' . $bengkel . '.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data: ' . mysqli_error($koneksi)]);
}
?>