<?php
// ============================================================
// proses_edit_besar.php
// Simpan revisi PR Besar yang ditolak
// - Hapus detail item lama, simpan yang baru
// - Update PO (supplier, harga, dll)
// - Reset SEMUA approval → kembali PENDING / MENUNGGU APPROVAL
// ============================================================
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") { header("location:../../login.php?pesan=belum_login"); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("location:pr.php"); exit; }

$id_request    = (int)($_POST['id_request'] ?? 0);
$username_login = $_SESSION['username'] ?? 'SYSTEM';

if (!$id_request) { header("location:pr.php"); exit; }

// Ambil data PR — validasi status harus DITOLAK
$pr = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tr_request WHERE id_request='$id_request' AND kategori_pr='BESAR'"));

if (!$pr || $pr['status_approval'] !== 'DITOLAK') {
    header("location:pr.php?pesan=tidak_bisa_edit");
    exit;
}

// Validasi hak akses
$boleh = ($pr['created_by'] === $username_login)
    || in_array($_SESSION['role'] ?? '', ['admin','superadmin']);
if (!$boleh) { header("location:pr.php?pesan=akses_ditolak"); exit; }

// ── SANITASI INPUT ────────────────────────────────────────────
$tgl_request  = mysqli_real_escape_string($koneksi, $_POST['tgl_request']  ?? date('Y-m-d'));
$nama_pemesan = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_pemesan'] ?? ''));
$nama_pembeli = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_pembeli'] ?? ''));
$keterangan   = mysqli_real_escape_string($koneksi, strtoupper($_POST['keterangan']   ?? ''));
$updated_by   = mysqli_real_escape_string($koneksi, $username_login);
$now          = date('Y-m-d H:i:s');

mysqli_begin_transaction($koneksi);
try {

    // ── 1. RESET HEADER PR ───────────────────────────────────
    // Bersihkan semua data approval, reset ke PENDING / MENUNGGU APPROVAL
    $sql_header = "UPDATE tr_request SET
                    tgl_request      = '$tgl_request',
                    nama_pemesan     = '$nama_pemesan',
                    nama_pembeli     = '$nama_pembeli',
                    keterangan       = '$keterangan',
                    status_request   = 'PENDING',
                    status_approval  = 'MENUNGGU APPROVAL',
                    -- Reset semua approval
                    approve1_by      = NULL,
                    approve1_at      = NULL,
                    catatan_approve1 = NULL,
                    approve2_by      = NULL,
                    approve2_at      = NULL,
                    catatan_approve2 = NULL,
                    approve3_by      = NULL,
                    approve3_at      = NULL,
                    catatan_approve3 = NULL,
                    need_approve3    = 0,
                    approve3_target  = NULL,
                    approve_by       = NULL,
                    tgl_approval     = NULL,
                    -- Simpan histori penolakan (tolak_by, tolak_at, catatan_tolak tetap)
                    updated_by       = '$updated_by',
                    updated_at       = '$now'
                WHERE id_request = '$id_request'";

    if (!mysqli_query($koneksi, $sql_header)) {
        throw new Exception("Gagal reset header: " . mysqli_error($koneksi));
    }

    // ── 2. HAPUS DETAIL ITEM LAMA ────────────────────────────
    if (!mysqli_query($koneksi, "DELETE FROM tr_request_detail WHERE id_request='$id_request'")) {
        throw new Exception("Gagal hapus detail lama: " . mysqli_error($koneksi));
    }

    // ── 3. SIMPAN DETAIL ITEM BARU ───────────────────────────
    $id_barang_arr   = $_POST['id_barang']          ?? [];
    $nama_arr        = $_POST['nama_barang_manual'] ?? [];
    $kategori_arr    = $_POST['kategori_request']   ?? [];
    $kwalifikasi_arr = $_POST['kwalifikasi']        ?? [];
    $id_mobil_arr    = $_POST['id_mobil']           ?? [];
    $tipe_arr        = $_POST['tipe_request']       ?? [];
    $jumlah_arr      = $_POST['jumlah']             ?? [];
    $satuan_arr      = $_POST['satuan']             ?? [];
    $harga_arr       = $_POST['harga']              ?? [];
    $ket_item_arr    = $_POST['keterangan_item']    ?? [];
    $is_ban_arr      = $_POST['is_ban_val']         ?? [];

    $subtotal_total = 0;
    for ($i = 0; $i < count($id_barang_arr); $i++) {
        $id_barang   = (int)($id_barang_arr[$i]   ?? 0);
        $nama_manual = mysqli_real_escape_string($koneksi, strtoupper($nama_arr[$i]        ?? ''));
        $kategori    = mysqli_real_escape_string($koneksi, strtoupper($kategori_arr[$i]    ?? ''));
        $kwalifikasi = mysqli_real_escape_string($koneksi, strtoupper($kwalifikasi_arr[$i] ?? ''));
        $id_mobil    = (int)($id_mobil_arr[$i]    ?? 0);
        $tipe        = mysqli_real_escape_string($koneksi, strtoupper($tipe_arr[$i]        ?? 'LANGSUNG'));
        $jumlah      = (float)($jumlah_arr[$i]    ?? 0);
        $satuan      = mysqli_real_escape_string($koneksi, strtoupper($satuan_arr[$i]      ?? ''));
        $harga       = (float)($harga_arr[$i]     ?? 0);
        $subtotal    = $jumlah * $harga;
        $ket_item    = mysqli_real_escape_string($koneksi, strtoupper($ket_item_arr[$i]    ?? ''));
        $is_ban      = (int)($is_ban_arr[$i] ?? 0) === 1 ? 1 : 0;
        $status_pasang_sql = $is_ban ? "'BELUM_TERPASANG'" : "NULL";

        if ($jumlah <= 0) continue;

        if ($id_barang > 0 && empty($nama_manual)) {
            $qn = mysqli_query($koneksi, "SELECT nama_barang FROM master_barang WHERE id_barang=$id_barang");
            if ($rn = mysqli_fetch_array($qn)) {
                $nama_manual = mysqli_real_escape_string($koneksi, strtoupper($rn['nama_barang']));
            }
        }

        $sql_detail = "INSERT INTO tr_request_detail
            (id_request, nama_barang_manual, id_barang, id_mobil,
             jumlah, satuan, harga_satuan_estimasi, subtotal_estimasi,
             kategori_barang, kwalifikasi, tipe_request, keterangan,
             status_item, is_ban, status_pasang, is_dibeli)
            VALUES
            ('$id_request','$nama_manual','$id_barang','$id_mobil',
             '$jumlah','$satuan','$harga','$subtotal',
             '$kategori','$kwalifikasi','$tipe','$ket_item',
             'PENDING', '$is_ban', $status_pasang_sql, 0)";

        if (!mysqli_query($koneksi, $sql_detail)) {
            throw new Exception("Gagal simpan detail baru: " . mysqli_error($koneksi));
        }
        $subtotal_total += $subtotal;
    }

    // ── 4. UPDATE DATA PO ────────────────────────────────────
    $id_supplier = (int)($_POST['id_supplier']  ?? 0);
    $tgl_po      = mysqli_real_escape_string($koneksi, $_POST['tgl_po']     ?? date('Y-m-d'));
    $diskon      = (float)($_POST['diskon']     ?? 0);
    $ppn_persen  = (float)($_POST['ppn_persen'] ?? 0);
    $catatan_po  = mysqli_real_escape_string($koneksi, $_POST['catatan_po'] ?? '');

    $total_po    = $subtotal_total - $diskon;
    $ppn_nominal = $total_po * ($ppn_persen / 100);
    $grand_total = $total_po + $ppn_nominal;

    // PO kembali ke DRAFT, bersihkan approved_by
    $sql_po = "UPDATE tr_purchase_order SET
                    id_supplier  = '$id_supplier',
                    tgl_po       = '$tgl_po',
                    subtotal     = '$subtotal_total',
                    diskon       = '$diskon',
                    total        = '$total_po',
                    ppn_persen   = '$ppn_persen',
                    ppn_nominal  = '$ppn_nominal',
                    grand_total  = '$grand_total',
                    catatan      = '$catatan_po',
                    prepared_by  = '$nama_pembeli',
                    approved_by  = '',
                    tgl_approve  = NULL,
                    status_po    = 'DRAFT'
               WHERE id_request = '$id_request'";

    if (!mysqli_query($koneksi, $sql_po)) {
        throw new Exception("Gagal update PO: " . mysqli_error($koneksi));
    }

    mysqli_commit($koneksi);
    header("location:pr.php?pesan=revisi_berhasil");
    exit;

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    error_log("proses_edit_besar.php ERROR: " . $e->getMessage());
    header("location:edit_request_besar.php?id=$id_request&pesan=gagal");
    exit;
}
