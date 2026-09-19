<?php
// ============================================================
// proses_void_pimpinan.php
// Proses VOID / BATAL PR Besar & IT yang sudah di-approve
// Hanya manager yang pernah approve yang boleh void
// ============================================================

session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// ========== VALIDASI SESSION ==========
if ($_SESSION['status'] != "login" || $_SESSION['role'] != 'manager') {
    header("location:../../login.php?pesan=bukan_pimpinan");
    exit;
}

// ========== AMBIL PARAMETER ==========
$id          = (int)($_GET['id']      ?? 0);
$action      = $_GET['action']        ?? '';
$catatan_raw = $_GET['catatan']       ?? '';

$username_saya = $_SESSION['username'] ?? '';
$now           = date('Y-m-d H:i:s');

// ========== VALIDASI PARAMETER ==========
if (!$id || $action !== 'void') {
    header("location:list_approval_pimpinan.php?pesan=parameter_invalid");
    exit;
}

// Escape string untuk keamanan
$catatan = mysqli_real_escape_string($koneksi, $catatan_raw);

// Validasi: pastikan catatan tidak kosong
if (empty($catatan)) {
    header("location:list_approval_pimpinan.php?pesan=catatan_kosong");
    exit;
}

// ========== AMBIL DATA PR ==========
$query_pr = "SELECT * FROM tr_request 
             WHERE id_request='$id' 
             AND kategori_pr IN ('BESAR','IT')";

$result_pr = mysqli_query($koneksi, $query_pr);
if (!$result_pr) {
    header("location:list_approval_pimpinan.php?pesan=query_error");
    exit;
}

$pr = mysqli_fetch_assoc($result_pr);
if (!$pr) {
    header("location:list_approval_pimpinan.php?pesan=tidak_ditemukan");
    exit;
}

// ========== VALIDASI STATUS ==========
$status_app = $pr['status_approval'];
$status_req = $pr['status_request'];

// Cek apakah PR sudah BATAL / SELESAI / DITOLAK
if (in_array($status_req, ['BATAL', 'SELESAI']) || $status_app === 'DITOLAK') {
    header("location:list_approval_pimpinan.php?pesan=sudah_diproses");
    exit;
}

// Validasi: hanya bisa di-void pada status yang sudah di-approve
if (!in_array($status_app, ['APPROVED 1', 'APPROVED 2', 'APPROVED'])) {
    header("location:list_approval_pimpinan.php?pesan=belum_di_approve");
    exit;
}

// ========== VALIDASI USER ==========
// Cek apakah user adalah salah satu approver PR ini
$is_approver = (
    $pr['approve1_by'] === $username_saya ||
    $pr['approve2_by'] === $username_saya ||
    $pr['approve3_by'] === $username_saya
);

// Jika user BUKAN approver, cek apakah dia manager yang berhak void
// (opsional: bisa juga semua manager boleh void, sesuaikan kebijakan)
if (!$is_approver) {
    // Cek apakah user adalah manager aktif
    $check_mgr = mysqli_query($koneksi,
        "SELECT username FROM users 
         WHERE username = '$username_saya' 
         AND role = 'manager' 
         AND status_aktif = 'AKTIF'");
    
    if (mysqli_num_rows($check_mgr) == 0) {
        header("location:list_approval_pimpinan.php?pesan=bukan_manager");
        exit;
    }
}

// ========== PROSES TRANSACTION ==========
mysqli_begin_transaction($koneksi);

try {
    
    // Gabungkan catatan void
    $catatan_full = "VOID oleh $username_saya: " . $catatan;
    $catatan_full_esc = mysqli_real_escape_string($koneksi, $catatan_full);
    
    // =========================================================
    // UPDATE tr_request → BATAL
    // =========================================================
    $sql = "UPDATE tr_request SET
                status_approval = 'BATAL',
                status_request  = 'BATAL',
                void_by         = '$username_saya',
                void_at         = '$now',
                catatan_void    = '$catatan_full_esc',
                updated_by      = '$username_saya',
                updated_at      = '$now'
            WHERE id_request = '$id'";
    
    if (!mysqli_query($koneksi, $sql)) {
        throw new Exception("Gagal update status PR: " . mysqli_error($koneksi));
    }
    
    // =========================================================
    // UPDATE tr_purchase_order → DRAFT (jika ada)
    // =========================================================
    $sql_po = "UPDATE tr_purchase_order 
               SET status_po = 'DRAFT' 
               WHERE id_request = '$id'";
    
    if (!mysqli_query($koneksi, $sql_po)) {
        throw new Exception("Gagal update status PO: " . mysqli_error($koneksi));
    }
    
    // =========================================================
    // LOG AKTIVITAS (opsional, jika tabel log tersedia)
    // =========================================================
    // $sql_log = "INSERT INTO tr_log_aktivitas 
    //             (id_request, username, aksi, catatan, created_at)
    //             VALUES 
    //             ('$id', '$username_saya', 'VOID', '$catatan_full_esc', '$now')";
    // mysqli_query($koneksi, $sql_log);
    
    mysqli_commit($koneksi);
    header("location:list_approval_pimpinan.php?pesan=void_berhasil");
    exit;
    
} catch (Exception $e) {
    // Rollback transaction jika ada error
    mysqli_rollback($koneksi);
    
    // Redirect dengan pesan error
    $error_message = urlencode($e->getMessage());
    header("location:list_approval_pimpinan.php?pesan=gagal&error=$error_message");
    exit;
}
?>