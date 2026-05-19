<?php
// ============================================================
// proses_simpan_it.php
// Simpan PR IT (kategori_pr=IT) tanpa PO
// Mendukung alur 2-3 approval manager
// + AUTO-CREATE DRAFT PO untuk keperluan ttd approval manager
// ============================================================
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") { header("location:../../login.php?pesan=belum_login"); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("location:tambah_request_it.php"); exit; }

// ── 1. GENERATE NOMOR REQUEST ────────────────────────────────
$bulan_romawi = ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
$bln    = (int)date('n');
$thn    = date('y');
$prefix = "PRI/" . $bulan_romawi[$bln] . "/" . $thn;

$cek_no = mysqli_query($koneksi,
    "SELECT no_request FROM tr_request
     WHERE no_request LIKE '$prefix/%' AND kategori_pr='IT'
     ORDER BY id_request DESC LIMIT 1"
);
$urut = 1;
if (mysqli_num_rows($cek_no) > 0) {
    $last  = mysqli_fetch_array($cek_no);
    $parts = explode('/', $last['no_request']);
    $urut  = (int)end($parts) + 1;
}
$no_request = $prefix . '/' . str_pad($urut, 4, '0', STR_PAD_LEFT);

// ── 2. SANITASI HEADER PR ────────────────────────────────────
$tgl_request  = mysqli_real_escape_string($koneksi, $_POST['tgl_request']  ?? date('Y-m-d'));
$nama_pemesan = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_pemesan'] ?? ''));
$nama_pembeli = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_pembeli'] ?? ''));
$keterangan   = mysqli_real_escape_string($koneksi, strtoupper($_POST['keterangan']   ?? ''));
$created_by   = mysqli_real_escape_string($koneksi, $_SESSION['username']  ?? 'SYSTEM');

// Sanitasi grand_total secara defensif
$grand_total_post = (float)($_POST['grand_total'] ?? 0);

// ── 3. MULAI TRANSAKSI ───────────────────────────────────────
mysqli_begin_transaction($koneksi);
try {

    // ── INSERT HEADER tr_request ─────────────────────────────
    $sql_header = "INSERT INTO tr_request
        (no_request, tgl_request, nama_pemesan, nama_pembeli,
         status_request, kategori_pr, status_approval,
         approve1_by, approve1_at, approve2_by, approve2_at,
         approve3_by, approve3_at, need_approve3, approve3_target,
         keterangan, created_by)
        VALUES
        ('$no_request','$tgl_request','$nama_pemesan','$nama_pembeli',
         'PENDING','IT','MENUNGGU APPROVAL',
         NULL, NULL, NULL, NULL,
         NULL, NULL, 0, NULL,
         '$keterangan', '$created_by')";

    if (!mysqli_query($koneksi, $sql_header)) {
        throw new Exception("Gagal simpan header PR: " . mysqli_error($koneksi));
    }
    $id_request = mysqli_insert_id($koneksi);

    // ── 4. AMBIL & VALIDASI ARRAY ITEM ──────────────────────
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

    if (empty($id_barang_arr)) {
        throw new Exception("Tidak ada item barang yang dikirim.");
    }

    $subtotal_total = 0;
    $item_tersimpan = 0;

    // ── 5. SIMPAN DETAIL ITEM tr_request_detail ──────────────
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

        // Skip baris tidak valid
        if ($jumlah <= 0)    continue;
        if ($id_barang <= 0) continue;

        // Auto-fill nama dari master jika kosong
        if (empty($nama_manual)) {
            $qn = mysqli_query($koneksi,
                "SELECT nama_barang FROM master_barang WHERE id_barang=$id_barang LIMIT 1"
            );
            if ($rn = mysqli_fetch_array($qn)) {
                $nama_manual = mysqli_real_escape_string($koneksi, strtoupper($rn['nama_barang']));
            }
        }

        $sql_detail = "INSERT INTO tr_request_detail
            (id_request, nama_barang_manual, id_barang, id_mobil,
             jumlah, satuan, harga_satuan_estimasi, subtotal_estimasi,
             kategori_barang, kwalifikasi, tipe_request, keterangan,
             status_item, is_dibeli)
            VALUES
            ('$id_request','$nama_manual','$id_barang','$id_mobil',
             '$jumlah','$satuan','$harga','$subtotal',
             '$kategori','$kwalifikasi','$tipe','$ket_item',
             'PENDING', 0)";

        if (!mysqli_query($koneksi, $sql_detail)) {
            throw new Exception("Gagal simpan detail baris ke-" . ($i + 1) . ": " . mysqli_error($koneksi));
        }

        $subtotal_total += $subtotal;
        $item_tersimpan++;
    }

    // Pastikan minimal 1 item valid tersimpan
    if ($item_tersimpan === 0) {
        throw new Exception("Tidak ada item valid yang dapat disimpan.");
    }

    // ── 6. AUTO-CREATE DRAFT PO untuk keperluan TTD approval ─
    //
    // Draft PO dibuat otomatis dengan status DRAFT.
    // id_supplier = 0 karena supplier belum ditentukan saat PR,
    // akan diisi oleh bagian pembelian sebelum/setelah approval.
    //
    // Format no_po : DRAFT-PRI/{bulan_romawi}/{tahun}/{nomor}
    // Contoh       : DRAFT-PRI/V/25/0001
    // ─────────────────────────────────────────────────────────
    $prefix_po = "DRAFT-PRI/" . $bulan_romawi[$bln] . "/" . $thn;

    $cek_po = mysqli_query($koneksi,
        "SELECT no_po FROM tr_purchase_order
         WHERE no_po LIKE '$prefix_po/%'
         ORDER BY id_po DESC LIMIT 1"
    );
    $urut_po = 1;
    if (mysqli_num_rows($cek_po) > 0) {
        $last_po  = mysqli_fetch_array($cek_po);
        $parts_po = explode('/', $last_po['no_po']);
        $urut_po  = (int)end($parts_po) + 1;
    }
    $no_po = $prefix_po . '/' . str_pad($urut_po, 4, '0', STR_PAD_LEFT);

    // PPN default 0% — bisa diupdate saat PO resmi dibuat oleh pembelian
    $ppn_persen  = 0.00;
    $ppn_nominal = 0.00;
    $grand_total = $subtotal_total; // Grand total = subtotal (tanpa PPN dulu)

    $catatan_po = mysqli_real_escape_string($koneksi,
        "DRAFT OTOMATIS DARI PR IT NO. $no_request — MENUNGGU APPROVAL MANAGER"
    );

    $sql_po = "INSERT INTO tr_purchase_order
        (no_po, id_request, id_supplier,
         tgl_po, total_halaman,
         subtotal, diskon, total,
         ppn_persen, ppn_nominal, grand_total,
         catatan, prepared_by, approved_by, tgl_approve,
         status_po, created_by)
        VALUES
        ('$no_po', '$id_request', 0,
         '$tgl_request', 1,
         '$subtotal_total', 0.00, '$subtotal_total',
         '$ppn_persen', '$ppn_nominal', '$grand_total',
         '$catatan_po', '$nama_pemesan', NULL, NULL,
         'DRAFT', '$created_by')";

    if (!mysqli_query($koneksi, $sql_po)) {
        throw new Exception("Gagal buat draft PO: " . mysqli_error($koneksi));
    }
    // Jika di masa depan perlu insert detail ke tabel tr_po_detail, gunakan:
    // $id_po = mysqli_insert_id($koneksi);

    // ── 7. COMMIT ────────────────────────────────────────────
    mysqli_commit($koneksi);
    header("location:tambah_request_it.php?pesan=berhasil_kirim");
    exit;

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    error_log("proses_simpan_it.php ERROR: " . $e->getMessage());
    header("location:tambah_request_it.php?pesan=gagal");
    exit;
}