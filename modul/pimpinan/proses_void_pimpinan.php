<?php
// proses_void_pimpinan.php — Rule B: PR dan PO batal hanya jika belum ada transaksi lanjutan.
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

function voidRedirect($pesan) {
    header('Location: list_approval_pimpinan.php?pesan=' . rawurlencode($pesan));
    exit;
}
function adaBarisVoid($db, $sql, $params, $types) {
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) throw new RuntimeException('Prepare gagal');
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Pemeriksaan data gagal');
    $result = mysqli_stmt_get_result($stmt);
    $ada = mysqli_fetch_assoc($result) !== null;
    mysqli_stmt_close($stmt);
    return $ada;
}

if (($_SESSION['status'] ?? '') !== 'login' || ($_SESSION['role'] ?? '') !== 'manager') {
    header('Location: ../../login.php?pesan=bukan_pimpinan');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') voidRedirect('metode_invalid');
if (empty($_SESSION['csrf_void_pimpinan']) ||
    !hash_equals($_SESSION['csrf_void_pimpinan'], (string)($_POST['csrf_token'] ?? ''))) {
    voidRedirect('token_invalid');
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = (string)($_POST['action'] ?? '');
$catatan = trim((string)($_POST['catatan'] ?? ''));
$username = trim((string)($_SESSION['username'] ?? ''));
if (!$id || $action !== 'void') voidRedirect('parameter_invalid');
if ($catatan === '') voidRedirect('catatan_kosong');
if ($username === '') voidRedirect('bukan_manager');
if (mb_strlen($catatan, 'UTF-8') > 2000) voidRedirect('catatan_terlalu_panjang');

$inTransaction = false;
try {
    mysqli_begin_transaction($koneksi);
    $inTransaction = true;

    // Lock baris PR. Status approval dipertahankan sebagai histori.
    $stmt = mysqli_prepare($koneksi, "SELECT id_request, no_request, kategori_pr, status_request,
        status_approval, approve1_by, approve2_by, approve3_by, void_by
        FROM tr_request WHERE id_request=? AND kategori_pr IN ('BESAR','IT') FOR UPDATE");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $pr = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$pr) throw new DomainException('tidak_ditemukan');
    if (in_array($pr['status_request'], ['BATAL','SELESAI'], true) ||
        $pr['status_approval'] === 'DITOLAK' || !empty($pr['void_by'])) {
        throw new DomainException('sudah_diproses');
    }
    if (!in_array($pr['status_approval'], ['APPROVED 1','APPROVED 2','APPROVED'], true)) {
        throw new DomainException('belum_di_approve');
    }
    // Session sudah diverifikasi sebagai manager. Sesuai rule awal,
    // manager dapat VOID PR approved walaupun bukan approver pada tahap sebelumnya.

    // Kunci seluruh PO terkait sebelum pemeriksaan dan pembatalan.
    $stmt = mysqli_prepare($koneksi, 'SELECT id_po, status_po FROM tr_purchase_order WHERE id_request=? FOR UPDATE');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $po = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($po)) {
        // CLOSE dianggap selesai: jangan VOID tanpa rekonsiliasi manual.
        if ($row['status_po'] === 'CLOSE') throw new DomainException('po_sudah_close');
    }
    mysqli_stmt_close($stmt);

    // Pemeriksaan transaksi nyata, termasuk relasi lewat no_request bagi data lama.
    // Pembelian yang telah tersimpan dianggap transaksi lanjutan walaupun detail PR belum diperbarui.
    $sqlPembelian = "SELECT id_pembelian FROM pembelian
        WHERE id_request=? OR (no_request IS NOT NULL AND no_request <> '' AND no_request=?) LIMIT 1";
    if (adaBarisVoid($koneksi, $sqlPembelian, [$id, $pr['no_request']], 'is')) {
        throw new DomainException('sudah_ada_pembelian');
    }
    // Staging aktif masih memuat proses pembelian yang berjalan.
    $sqlStaging = "SELECT id_staging FROM pembelian_staging
        WHERE (id_request=? OR (no_request IS NOT NULL AND no_request <> '' AND no_request=?))
          AND status_staging IN ('MENUNGGU','DISETUJUI') LIMIT 1";
    if (adaBarisVoid($koneksi, $sqlStaging, [$id, $pr['no_request']], 'is')) {
        throw new DomainException('ada_pembelian_staging');
    }
    // Cek penanda item terbeli agar data migrasi/manual tetap terlindungi.
    $sqlDetail = "SELECT id_detail FROM tr_request_detail WHERE id_request=?
        AND (is_dibeli=1 OR status_item='TERBELI' OR tgl_dibeli IS NOT NULL) LIMIT 1";
    if (adaBarisVoid($koneksi, $sqlDetail, [$id], 'i')) {
        throw new DomainException('item_sudah_dibeli');
    }

    $now = date('Y-m-d H:i:s');
    $catatanFull = 'Dibatalkan oleh ' . $username . ': ' . $catatan;
    // Nilai BATAL TIDAK ada di enum status_approval. Jangan menulis BATAL ke kolom tersebut.
    $stmt = mysqli_prepare($koneksi, "UPDATE tr_request SET
        status_request='BATAL', void_by=?, void_at=?, catatan_void=?,
        updated_by=?, updated_at=?
        WHERE id_request=? AND status_request NOT IN ('BATAL','SELESAI')
          AND (void_by IS NULL OR void_by='')");
    mysqli_stmt_bind_param($stmt, 'sssssi', $username, $now, $catatanFull, $username, $now, $id);
    mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($changed !== 1) throw new DomainException('sudah_diproses');

    // Batalkan SEMUA PO terkait; jangan mengubahnya menjadi DRAFT.
    $stmt = mysqli_prepare($koneksi, "UPDATE tr_purchase_order SET status_po='BATAL'
        WHERE id_request=? AND status_po<>'BATAL'");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($koneksi);
    $inTransaction = false;
    voidRedirect('void_berhasil');
} catch (DomainException $e) {
    if ($inTransaction) mysqli_rollback($koneksi);
    voidRedirect($e->getMessage());
} catch (Throwable $e) {
    if ($inTransaction) mysqli_rollback($koneksi);
    error_log('VOID PR error id=' . (int)$id . ': ' . $e->getMessage());
    voidRedirect('gagal');
}
