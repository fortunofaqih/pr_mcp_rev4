<?php
// ============================================================
// proses_revert_pimpinan.php
// Revert / undo approval terakhir pada PR Besar & IT
// Mengembalikan PR ke tahap approval sebelumnya
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
$catatan_raw = $_GET['catatan']       ?? '';

$username_saya = $_SESSION['username'] ?? '';
$now           = date('Y-m-d H:i:s');

// ========== VALIDASI PARAMETER ==========
if (!$id) {
    header("location:list_approval_pimpinan.php?pesan=parameter_invalid");
    exit;
}

$catatan = mysqli_real_escape_string($koneksi, $catatan_raw);

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

// Tidak bisa revert jika sudah BATAL/DITOLAK
if (in_array($status_req, ['BATAL', 'SELESAI']) 
    || in_array($status_app, ['BATAL', 'DITOLAK'])) {
    header("location:list_approval_pimpinan.php?pesan=sudah_diproses");
    exit;
}

// Hanya bisa revert pada status yang sudah ada approval
if (!in_array($status_app, ['APPROVED 1', 'APPROVED 2', 'APPROVED'])) {
    header("location:list_approval_pimpinan.php?pesan=belum_di_approve");
    exit;
}

// ========== VALIDASI USER ==========
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

// ========== PROSES TRANSACTION ==========
mysqli_begin_transaction($koneksi);

try {
    
    $catatan_full = "REVERT oleh $username_saya: " . $catatan;
    $catatan_full_esc = mysqli_real_escape_string($koneksi, $catatan_full);
    
    // =========================================================
    // CASE 1: APPROVED (final) → REVERT ke APPROVED 2
    // Menghapus approval M3
    // =========================================================
    if ($status_app === 'APPROVED') {
        
        // Cek apakah PR ini punya M3
        if ($pr['need_approve3'] != 1) {
            // Tidak ada M3, revert langsung ke APPROVED 1
            // (hapus approval M2)
            $sql = "UPDATE tr_request SET
                        status_approval   = 'APPROVED 1',
                        status_request    = 'PROSES',
                        approve2_by       = NULL,
                        approve2_at       = NULL,
                        catatan_approve2  = NULL,
                        approve_by        = approve1_by,
                        tgl_approval      = NULL,
                        revert_by         = '$username_saya',
                        revert_at         = '$now',
                        catatan_revert    = '$catatan_full_esc',
                        revert_count      = revert_count + 1,
                        updated_by        = '$username_saya',
                        updated_at        = '$now'
                    WHERE id_request = '$id'";
            
            if (!mysqli_query($koneksi, $sql)) {
                throw new Exception("Gagal revert ke APPROVED 1: " . mysqli_error($koneksi));
            }
            
            // Update PO kembali ke DRAFT
            mysqli_query($koneksi, "UPDATE tr_purchase_order 
                                    SET status_po = 'DRAFT',
                                        approved_by = NULL,
                                        tgl_approve = NULL
                                    WHERE id_request = '$id'");
            
            mysqli_commit($koneksi);
            header("location:list_approval_pimpinan.php?pesan=revert_ke_approved1");
            exit;
        }
        
        // Ada M3, revert ke APPROVED 2 (hapus approval M3)
        $sql = "UPDATE tr_request SET
                    status_approval   = 'APPROVED 2',
                    status_request    = 'PROSES',
                    approve3_by       = NULL,
                    approve3_at       = NULL,
                    catatan_approve3  = NULL,
                    approve_by        = CONCAT(approve1_by, ' & ', approve2_by),
                    tgl_approval      = NULL,
                    revert_by         = '$username_saya',
                    revert_at         = '$now',
                    catatan_revert    = '$catatan_full_esc',
                    revert_count      = revert_count + 1,
                    updated_by        = '$username_saya',
                    updated_at        = '$now'
                WHERE id_request = '$id'";
        
        if (!mysqli_query($koneksi, $sql)) {
            throw new Exception("Gagal revert ke APPROVED 2: " . mysqli_error($koneksi));
        }
        
        // Update PO kembali ke DRAFT
        mysqli_query($koneksi, "UPDATE tr_purchase_order 
                                SET status_po = 'DRAFT',
                                    approved_by = NULL,
                                    tgl_approve = NULL
                                WHERE id_request = '$id'");
        
        mysqli_commit($koneksi);
        header("location:list_approval_pimpinan.php?pesan=revert_ke_approved2");
        exit;
    }
    
    // =========================================================
    // CASE 2: APPROVED 2 → REVERT ke APPROVED 1
    // Menghapus approval M2
    // =========================================================
    if ($status_app === 'APPROVED 2') {
        
        $sql = "UPDATE tr_request SET
                    status_approval   = 'APPROVED 1',
                    approve2_by       = NULL,
                    approve2_at       = NULL,
                    catatan_approve2  = NULL,
                    need_approve3     = 0,
                    approve3_target   = NULL,
                    approve_by        = approve1_by,
                    revert_by         = '$username_saya',
                    revert_at         = '$now',
                    catatan_revert    = '$catatan_full_esc',
                    revert_count      = revert_count + 1,
                    updated_by        = '$username_saya',
                    updated_at        = '$now'
                WHERE id_request = '$id'";
        
        if (!mysqli_query($koneksi, $sql)) {
            throw new Exception("Gagal revert ke APPROVED 1: " . mysqli_error($koneksi));
        }
        
        mysqli_commit($koneksi);
        header("location:list_approval_pimpinan.php?pesan=revert_ke_approved1");
        exit;
    }
    
    // =========================================================
    // CASE 3: APPROVED 1 → REVERT ke MENUNGGU APPROVAL
    // Menghapus approval M1
    // =========================================================
    if ($status_app === 'APPROVED 1') {
        
        $sql = "UPDATE tr_request SET
                    status_approval   = 'MENUNGGU APPROVAL',
                    approve1_by       = NULL,
                    approve1_at       = NULL,
                    catatan_approve1  = NULL,
                    approve_by        = NULL,
                    revert_by         = '$username_saya',
                    revert_at         = '$now',
                    catatan_revert    = '$catatan_full_esc',
                    revert_count      = revert_count + 1,
                    updated_by        = '$username_saya',
                    updated_at        = '$now'
                WHERE id_request = '$id'";
        
        if (!mysqli_query($koneksi, $sql)) {
            throw new Exception("Gagal revert ke MENUNGGU APPROVAL: " . mysqli_error($koneksi));
        }
        
        mysqli_commit($koneksi);
        header("location:list_approval_pimpinan.php?pesan=revert_ke_menunggu");
        exit;
    }
    
    throw new Exception("Status tidak valid untuk revert");
    
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $error_message = urlencode($e->getMessage());
    header("location:list_approval_pimpinan.php?pesan=gagal&error=$error_message");
    exit;
}
?>