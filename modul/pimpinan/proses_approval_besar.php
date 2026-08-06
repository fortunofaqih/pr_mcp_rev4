<?php
// ============================================================
// proses_approval_besar.php
// Proses approve / reject PR Besar & IT — 2-3 approval manager
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
$id            = (int)($_GET['id']        ?? 0);
$action        = $_GET['action']          ?? '';
$catatan_raw   = $_GET['catatan']         ?? '';
$need_m3_raw   = (int)($_GET['need_m3']   ?? 0);
$m3_target_raw = $_GET['m3_target']       ?? '';

$username_saya = $_SESSION['username'] ?? '';
$now           = date('Y-m-d H:i:s');

// ========== VALIDASI PARAMETER ==========
if (!$id || !in_array($action, ['approve', 'reject'])) {
    header("location:approval_pimpinan.php?pesan=parameter_invalid");
    exit;
}

// Escape string untuk keamanan
$catatan   = mysqli_real_escape_string($koneksi, $catatan_raw);
$m3_target = mysqli_real_escape_string($koneksi, strtolower(trim($m3_target_raw)));

// ========== AMBIL DATA PR ==========
$query_pr = "SELECT * FROM tr_request 
             WHERE id_request='$id' 
             AND kategori_pr IN ('BESAR','IT')";

$result_pr = mysqli_query($koneksi, $query_pr);
if (!$result_pr) {
    header("location:approval_pimpinan.php?pesan=query_error");
    exit;
}

$pr = mysqli_fetch_assoc($result_pr);
if (!$pr) {
    header("location:approval_pimpinan.php?pesan=tidak_ditemukan");
    exit;
}

// ========== VALIDASI STATUS ==========
$status_app  = $pr['status_approval'];
$status_req  = $pr['status_request'];

// Cek apakah PR sudah diproses/ditolak
if ($status_req === 'BATAL' || $status_app === 'DITOLAK') {
    header("location:approval_pimpinan.php?pesan=sudah_diproses");
    exit;
}

// Validasi: hanya bisa diaksi pada status yang relevan
if (!in_array($status_app, ['MENUNGGU APPROVAL', 'APPROVED 1', 'APPROVED 2'])) {
    header("location:approval_pimpinan.php?pesan=sudah_diproses");
    exit;
}

// ========== VALIDASI USER ==========
// Cek apakah user sudah approve sebelumnya
if ($pr['approve1_by'] === $username_saya ||
    $pr['approve2_by'] === $username_saya ||
    $pr['approve3_by'] === $username_saya) {
    header("location:approval_pimpinan.php?pesan=sudah_approve");
    exit;
}

// Validasi khusus M3: hanya approve3_target yang boleh approve saat APPROVED 2
if ($status_app === 'APPROVED 2') {
    if ($pr['approve3_target'] !== $username_saya) {
        header("location:approval_pimpinan.php?pesan=bukan_giliran");
        exit;
    }
}

// ========== PROSES TRANSACTION ==========
mysqli_begin_transaction($koneksi);

try {
    
    // =========================================================
    // TINDAKAN: TOLAK
    // =========================================================
    if ($action === 'reject') {
        
        // Validasi: pastikan catatan tidak kosong
        if (empty($catatan)) {
            throw new Exception("Alasan penolakan wajib diisi");
        }
        
        // Update status PR
        $sql = "UPDATE tr_request SET
                    status_approval = 'DITOLAK',
                    status_request  = 'BATAL',
                    tolak_by        = '$username_saya',
                    tolak_at        = '$now',
                    catatan_tolak   = '$catatan',
                    updated_by      = '$username_saya',
                    updated_at      = '$now'
                WHERE id_request = '$id'";
        
        if (!mysqli_query($koneksi, $sql)) {
            throw new Exception("Gagal update status PR: " . mysqli_error($koneksi));
        }
        
        // Update PO menjadi DRAFT
        $sql_po = "UPDATE tr_purchase_order 
                   SET status_po = 'DRAFT' 
                   WHERE id_request = '$id'";
        
        if (!mysqli_query($koneksi, $sql_po)) {
            throw new Exception("Gagal update status PO: " . mysqli_error($koneksi));
        }
        
        mysqli_commit($koneksi);
        header("location:approval_pimpinan.php?pesan=ditolak");
        exit;
    }
    
    // =========================================================
    // TINDAKAN: APPROVE
    // =========================================================
    if ($action === 'approve') {
        
        // =====================================================
        // APPROVE KE-1 (status: MENUNGGU APPROVAL)
        // =====================================================
        if ($status_app === 'MENUNGGU APPROVAL') {
            
            $sql = "UPDATE tr_request SET
                        status_approval  = 'APPROVED 1',
                        approve1_by      = '$username_saya',
                        approve1_at      = '$now',
                        catatan_approve1 = '$catatan',
                        updated_by       = '$username_saya',
                        updated_at       = '$now'
                    WHERE id_request = '$id'";
            
            if (!mysqli_query($koneksi, $sql)) {
                throw new Exception("Gagal approve ke-1: " . mysqli_error($koneksi));
            }
            
            mysqli_commit($koneksi);
            header("location:approval_pimpinan.php?pesan=approve1_berhasil");
            exit;
        }
        
        // =====================================================
        // APPROVE KE-2 (status: APPROVED 1)
        // =====================================================
        if ($status_app === 'APPROVED 1') {
            
            // CEK OPSI 1: Dengan M3 (belum final)
            if ($need_m3_raw && !empty($m3_target)) {
                
                // Validasi: pastikan M3 yang dipilih tidak sama dengan user atau M1
                $m1_username = $pr['approve1_by'];
                if ($m3_target === $username_saya || $m3_target === $m1_username) {
                    throw new Exception("Manager ke-3 tidak boleh sama dengan Manager sebelumnya");
                }
                
                // Validasi: pastikan M3 yang dipilih adalah manager aktif
                $check_m3 = mysqli_query($koneksi, 
                    "SELECT username FROM users 
                     WHERE username = '$m3_target' 
                     AND role = 'manager' 
                     AND status_aktif = 'AKTIF'");
                
                if (mysqli_num_rows($check_m3) == 0) {
                    throw new Exception("Manager ke-3 tidak valid atau tidak aktif");
                }
                
                // Update dengan M3
                $sql = "UPDATE tr_request SET
                            status_approval  = 'APPROVED 2',
                            approve2_by      = '$username_saya',
                            approve2_at      = '$now',
                            catatan_approve2 = '$catatan',
                            need_approve3    = 1,
                            approve3_target  = '$m3_target',
                            approve_by       = CONCAT(approve1_by, ' & $username_saya'),
                            updated_by       = '$username_saya',
                            updated_at       = '$now'
                        WHERE id_request = '$id'";
                
                if (!mysqli_query($koneksi, $sql)) {
                    throw new Exception("Gagal approve ke-2 (+M3): " . mysqli_error($koneksi));
                }
                
                mysqli_commit($koneksi);
                header("location:approval_pimpinan.php?pesan=approve2_berhasil");
                exit;
            }
            
            // CEK OPSI 2: Tanpa M3 (langsung final)
            else {
                
                // Update PR menjadi APPROVED FINAL
                $sql = "UPDATE tr_request SET
                            status_approval  = 'APPROVED',
                            status_request   = 'PROSES',
                            approve2_by      = '$username_saya',
                            approve2_at      = '$now',
                            catatan_approve2 = '$catatan',
                            need_approve3    = 0,
                            approve_by       = CONCAT(approve1_by, ' & $username_saya'),
                            tgl_approval     = '$now',
                            updated_by       = '$username_saya',
                            updated_at       = '$now'
                        WHERE id_request = '$id'";
                
                if (!mysqli_query($koneksi, $sql)) {
                    throw new Exception("Gagal approve ke-2 (final): " . mysqli_error($koneksi));
                }
                
                // Update PO menjadi OPEN
                $approved_by_esc = mysqli_real_escape_string($koneksi,
                    $pr['approve1_by'] . ' & ' . $username_saya);
                
                $sql_po = "UPDATE tr_purchase_order SET
                                status_po   = 'OPEN',
                                approved_by = '$approved_by_esc',
                                tgl_approve = '$now'
                           WHERE id_request = '$id'";
                
                if (!mysqli_query($koneksi, $sql_po)) {
                    throw new Exception("Gagal update PO ke OPEN: " . mysqli_error($koneksi));
                }
                
                mysqli_commit($koneksi);
                header("location:approval_pimpinan.php?pesan=approve_final_berhasil");
                exit;
            }
        }
        
        // =====================================================
        // APPROVE KE-3 (status: APPROVED 2, final)
        // =====================================================
        if ($status_app === 'APPROVED 2') {
            
            // Cek apakah M3 diperlukan
            if ($pr['need_approve3'] != 1) {
                throw new Exception("PR ini tidak memerlukan Manager ke-3");
            }
            
            // Cek apakah user adalah M3 yang ditunjuk
            if ($pr['approve3_target'] !== $username_saya) {
                throw new Exception("Anda bukan Manager ke-3 yang ditunjuk untuk PR ini");
            }
            
            // Gabungkan semua approver
            $all_approvers = $pr['approve1_by'] . ' & ' . $pr['approve2_by'] . ' & ' . $username_saya;
            $all_approvers_esc = mysqli_real_escape_string($koneksi, $all_approvers);
            
            // Update PR menjadi APPROVED FINAL
            $sql = "UPDATE tr_request SET
                        status_approval  = 'APPROVED',
                        status_request   = 'PROSES',
                        approve3_by      = '$username_saya',
                        approve3_at      = '$now',
                        catatan_approve3 = '$catatan',
                        approve_by       = '$all_approvers_esc',
                        tgl_approval     = '$now',
                        updated_by       = '$username_saya',
                        updated_at       = '$now'
                    WHERE id_request = '$id'";
            
            if (!mysqli_query($koneksi, $sql)) {
                throw new Exception("Gagal approve ke-3: " . mysqli_error($koneksi));
            }
            
            // Update PO menjadi OPEN
            $sql_po = "UPDATE tr_purchase_order SET
                            status_po   = 'OPEN',
                            approved_by = '$all_approvers_esc',
                            tgl_approve = '$now'
                       WHERE id_request = '$id'";
            
            if (!mysqli_query($koneksi, $sql_po)) {
                throw new Exception("Gagal update PO ke OPEN (M3): " . mysqli_error($koneksi));
            }
            
            mysqli_commit($koneksi);
            header("location:approval_pimpinan.php?pesan=approve3_berhasil");
            exit;
        }
    }
    
    // =========================================================
    // Jika tidak ada action yang sesuai
    // =========================================================
    throw new Exception("Action tidak valid atau status tidak sesuai");
    
} catch (Exception $e) {
    // Rollback transaction jika ada error
    mysqli_rollback($koneksi);
    
    // Redirect dengan pesan error
    $error_message = urlencode($e->getMessage());
    header("location:approval_pimpinan.php?pesan=gagal&error=$error_message");
    exit;
}
?>