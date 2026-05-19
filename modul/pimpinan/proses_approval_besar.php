<?php
// ============================================================
// proses_approval_besar.php
// Proses approve / reject PR Besar & IT — 2-3 approval manager
// BUG FIX: hapus filter kategori_pr='BESAR' agar PR IT bisa diapprove
// ============================================================
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login" || $_SESSION['role'] != 'manager') {
    header("location:../../login.php?pesan=bukan_pimpinan");
    exit;
}

$id            = (int)($_GET['id']        ?? 0);
$action        = $_GET['action']          ?? '';
$catatan_raw   = $_GET['catatan']         ?? '';
$need_m3_raw   = (int)($_GET['need_m3']   ?? 0);
$m3_target_raw = $_GET['m3_target']       ?? '';

$username_saya = $_SESSION['username'] ?? '';

if (!$id || !in_array($action, ['approve','reject'])) {
    header("location:approval_pimpinan.php");
    exit;
}

$catatan   = mysqli_real_escape_string($koneksi, $catatan_raw);
$m3_target = mysqli_real_escape_string($koneksi, strtolower(trim($m3_target_raw)));

// ── Ambil data PR ────────────────────────────────────────────
// BUG FIX UTAMA: hapus AND kategori_pr='BESAR'
// Ganti dengan whitelist kategori yang diizinkan lewat halaman ini
$pr = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tr_request
     WHERE id_request='$id'
     AND kategori_pr IN ('BESAR','IT')"));

if (!$pr) { header("location:approval_pimpinan.php?pesan=tidak_ditemukan"); exit; }

$status_app  = $pr['status_approval'];
$approve1_by = $pr['approve1_by'] ?? '';

// Validasi: hanya bisa diaksi pada status yang relevan
if (!in_array($status_app, ['MENUNGGU APPROVAL', 'APPROVED 1', 'APPROVED 2'])) {
    header("location:approval_pimpinan.php?pesan=sudah_diproses");
    exit;
}

// Validasi: manager yang sudah approve tidak boleh approve lagi
if ($pr['approve1_by'] === $username_saya ||
    $pr['approve2_by'] === $username_saya ||
    $pr['approve3_by'] === $username_saya) {
    header("location:approval_pimpinan.php?pesan=sudah_approve");
    exit;
}

// Validasi M3: hanya approve3_target yang boleh approve saat APPROVED 2
if ($status_app === 'APPROVED 2') {
    if ($pr['approve3_target'] !== $username_saya) {
        header("location:approval_pimpinan.php?pesan=bukan_giliran");
        exit;
    }
}

$now = date('Y-m-d H:i:s');

mysqli_begin_transaction($koneksi);
try {

    // ── TOLAK ─────────────────────────────────────────────────
    if ($action === 'reject') {

        $sql = "UPDATE tr_request SET
                    status_approval = 'DITOLAK',
                    status_request  = 'DITOLAK',
                    tolak_by        = '$username_saya',
                    tolak_at        = '$now',
                    catatan_tolak   = '$catatan',
                    updated_by      = '$username_saya',
                    updated_at      = '$now'
                WHERE id_request = '$id'";

        if (!mysqli_query($koneksi, $sql)) throw new Exception("Gagal tolak: " . mysqli_error($koneksi));

        // PO kembali ke DRAFT saat ditolak
        mysqli_query($koneksi,
            "UPDATE tr_purchase_order SET status_po='DRAFT' WHERE id_request='$id'");

        mysqli_commit($koneksi);
        header("location:approval_pimpinan.php?pesan=ditolak");
        exit;

    // ── APPROVE ───────────────────────────────────────────────
    } elseif ($action === 'approve') {

        // ════════════════════════════════════════════════════
        // APPROVE KE-1 (status: MENUNGGU APPROVAL)
        // ════════════════════════════════════════════════════
        if ($status_app === 'MENUNGGU APPROVAL') {

            $sql = "UPDATE tr_request SET
                        status_approval  = 'APPROVED 1',
                        approve1_by      = '$username_saya',
                        approve1_at      = '$now',
                        catatan_approve1 = '$catatan',
                        updated_by       = '$username_saya',
                        updated_at       = '$now'
                    WHERE id_request = '$id'";

            if (!mysqli_query($koneksi, $sql)) throw new Exception("Gagal approve ke-1: " . mysqli_error($koneksi));

            mysqli_commit($koneksi);
            header("location:approval_pimpinan.php?pesan=approve1_berhasil");
            exit;

        // ════════════════════════════════════════════════════
        // APPROVE KE-2 (status: APPROVED 1)
        // ════════════════════════════════════════════════════
        } elseif ($status_app === 'APPROVED 1') {

            if ($need_m3_raw && !empty($m3_target)) {
                // M2 memilih tambah M3 → belum final, status APPROVED 2
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

                if (!mysqli_query($koneksi, $sql)) throw new Exception("Gagal approve ke-2 (+M3): " . mysqli_error($koneksi));

                mysqli_commit($koneksi);
                header("location:approval_pimpinan.php?pesan=approve2_berhasil");
                exit;

            } else {
                // M2 tidak pilih M3 → langsung APPROVED FINAL
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

                if (!mysqli_query($koneksi, $sql)) throw new Exception("Gagal approve ke-2 (final): " . mysqli_error($koneksi));

                // PO → OPEN setelah approval final
                $approved_by_esc = mysqli_real_escape_string($koneksi,
                    $pr['approve1_by'] . ' & ' . $username_saya);
                $sql_po = "UPDATE tr_purchase_order SET
                                status_po   = 'OPEN',
                                approved_by = '$approved_by_esc',
                                tgl_approve = '$now'
                           WHERE id_request = '$id'";
                if (!mysqli_query($koneksi, $sql_po)) throw new Exception("Gagal update PO ke OPEN: " . mysqli_error($koneksi));

                mysqli_commit($koneksi);
                header("location:approval_pimpinan.php?pesan=approve_final_berhasil");
                exit;
            }

        // ════════════════════════════════════════════════════
        // APPROVE KE-3 (status: APPROVED 2, final)
        // ════════════════════════════════════════════════════
        } elseif ($status_app === 'APPROVED 2') {

            $all_approvers     = $pr['approve1_by'] . ' & ' . $pr['approve2_by'] . ' & ' . $username_saya;
            $all_approvers_esc = mysqli_real_escape_string($koneksi, $all_approvers);

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

            if (!mysqli_query($koneksi, $sql)) throw new Exception("Gagal approve ke-3: " . mysqli_error($koneksi));

            // PO → OPEN setelah semua approval selesai
            $sql_po = "UPDATE tr_purchase_order SET
                            status_po   = 'OPEN',
                            approved_by = '$all_approvers_esc',
                            tgl_approve = '$now'
                       WHERE id_request = '$id'";
            if (!mysqli_query($koneksi, $sql_po)) throw new Exception("Gagal update PO ke OPEN (M3): " . mysqli_error($koneksi));

            mysqli_commit($koneksi);
            header("location:approval_pimpinan.php?pesan=approve3_berhasil");
            exit;
        }
    }

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    error_log("proses_approval_besar.php ERROR: " . $e->getMessage());
    header("location:approval_pimpinan.php?pesan=gagal");
    exit;
}