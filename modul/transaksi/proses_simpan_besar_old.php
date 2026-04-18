<?php
// ============================================================
// proses_simpan_besar.php
// Simpan PR Besar (kategori_pr=BESAR) + draft PO
// Mendukung alur 2-3 approval manager + kolom ban
// ============================================================
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") { header("location:../../login.php?pesan=belum_login"); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("location:tambah_request_besar.php"); exit; }

// ── 1. GENERATE NOMOR REQUEST ────────────────────────────────
$bulan_romawi = ['','I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
$bln    = (int)date('n');
$thn    = date('y');
$prefix = "PRB/" . $bulan_romawi[$bln] . "/" . $thn;
$cek_no = mysqli_query($koneksi, "SELECT no_request FROM tr_request WHERE no_request LIKE '$prefix/%' AND kategori_pr='BESAR' ORDER BY id_request DESC LIMIT 1");
$urut   = 1;
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

// ── 3. MULAI TRANSAKSI ───────────────────────────────────────
mysqli_begin_transaction($koneksi);
try {

    // INSERT header tr_request
    // need_approve3 = 0 default, akan diisi Manager ke-1 saat approval
    // approve3_target = NULL default, akan diisi Manager ke-1
    $sql_header = "INSERT INTO tr_request
        (no_request, tgl_request, nama_pemesan, nama_pembeli,
         status_request, kategori_pr, status_approval,
         approve1_by, approve1_at, approve2_by, approve2_at,
         approve3_by, approve3_at, need_approve3, approve3_target,
         keterangan, created_by)
        VALUES
        ('$no_request','$tgl_request','$nama_pemesan','$nama_pembeli',
         'PENDING','BESAR','MENUNGGU APPROVAL',
         NULL, NULL, NULL, NULL,
         NULL, NULL, 0, NULL,
         '$keterangan','$created_by')";

    if (!mysqli_query($koneksi, $sql_header)) {
        throw new Exception("Gagal simpan header: " . mysqli_error($koneksi));
    }
    $id_request = mysqli_insert_id($koneksi);

    // ── 4. SIMPAN DETAIL ITEM ────────────────────────────────
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
    // is_ban_val: hidden field yang selalu ada (0 atau 1), lebih reliable dari checkbox is_ban[]
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
        // is_ban: 1 jika checkbox ban dicentang
        $is_ban      = (int)($is_ban_arr[$i] ?? 0) === 1 ? 1 : 0;
        // status_pasang: jika ban → BELUM_TERPASANG, jika bukan → NULL
        $status_pasang_sql = $is_ban ? "'BELUM_TERPASANG'" : "NULL";

        if ($jumlah <= 0) continue;

        // Auto-fill nama dari master jika kosong
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
            throw new Exception("Gagal simpan detail: " . mysqli_error($koneksi));
        }
        $subtotal_total += $subtotal;
    }

    // ── 5. SIMPAN DRAFT PO ───────────────────────────────────
    $id_supplier = (int)($_POST['id_supplier']  ?? 0);
    $tgl_po      = mysqli_real_escape_string($koneksi, $_POST['tgl_po']     ?? date('Y-m-d'));
    $diskon      = (float)($_POST['diskon']     ?? 0);
    $ppn_persen  = (float)($_POST['ppn_persen'] ?? 0);
    $catatan_po  = mysqli_real_escape_string($koneksi, $_POST['catatan_po'] ?? '');

    $total_po    = $subtotal_total - $diskon;
    $ppn_nominal = $total_po * ($ppn_persen / 100);
    $grand_total = $total_po + $ppn_nominal;

    // Generate nomor PO draft
    $bln_po  = (int)date('n', strtotime($tgl_po));
    $thn_po  = date('y', strtotime($tgl_po));
    $suf_po  = "/" . $bulan_romawi[$bln_po] . "/" . $thn_po;
    $cek_po  = mysqli_fetch_array(mysqli_query($koneksi,
        "SELECT no_po FROM tr_purchase_order WHERE no_po LIKE 'MCP-%' ORDER BY id_po DESC LIMIT 1"
    ));
    $urut_po = 1;
    if ($cek_po) {
        preg_match('/MCP-(\d+)/', $cek_po['no_po'], $match_po);
        $urut_po = (int)($match_po[1] ?? 0) + 1;
    }
    $no_po = "MCP-" . str_pad($urut_po, 4, '0', STR_PAD_LEFT) . $suf_po;

    // PO status DRAFT saat simpan, berubah OPEN saat semua approval selesai
    $sql_po = "INSERT INTO tr_purchase_order
        (no_po, id_request, id_supplier, tgl_po,
         subtotal, diskon, total, ppn_persen, ppn_nominal, grand_total,
         catatan, prepared_by, approved_by, status_po, created_by)
        VALUES
        ('$no_po','$id_request','$id_supplier','$tgl_po',
         '$subtotal_total','$diskon','$total_po','$ppn_persen','$ppn_nominal','$grand_total',
         '$catatan_po','$nama_pembeli','','DRAFT','$created_by')";

    if (!mysqli_query($koneksi, $sql_po)) {
        throw new Exception("Gagal simpan PO: " . mysqli_error($koneksi));
    }

    mysqli_commit($koneksi);
    header("location:tambah_request_besar.php?pesan=berhasil_kirim");
    exit;

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    error_log("proses_simpan_besar.php ERROR: " . $e->getMessage());
    header("location:tambah_request_besar.php?pesan=gagal");
    exit;
}