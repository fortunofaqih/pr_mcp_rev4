<?php
// ============================================================
// modul/transaksi/update_status_ban.php
// Update status pembelian item & pemasangan ban
// PO otomatis CLOSE bila semua item dibeli + semua ban terpasang
// ============================================================
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] !== 'login') {
    header('location:../../login.php?pesan=belum_login');
    exit;
}

$role_ok = in_array($_SESSION['role'] ?? '', [
    'bagian_pembelian', 'admin', 'manager', 'superadmin', 'pemesan_pr_besar'
]);
if (!$role_ok) {
    header('location:../../login.php?pesan=akses_ditolak');
    exit;
}

$username_login = $_SESSION['username'] ?? '';
$nama_login     = strtoupper($_SESSION['nama'] ?? $username_login);

// ── PROSES POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_detail    = (int)($_POST['id_detail']  ?? 0);
    $aksi         = $_POST['aksi']             ?? '';
    $id_po_redir  = (int)($_POST['id_po']      ?? 0);
    $id_req_redir = (int)($_POST['id_request'] ?? 0);

    if (!$id_detail || !in_array($aksi, ['beli', 'pasang'])) {
        header('location:update_status_ban.php?pesan=invalid');
        exit;
    }

    $now      = date('Y-m-d H:i:s');
    $today    = date('Y-m-d');
    $nama_esc = mysqli_real_escape_string($koneksi, $nama_login);
    $user_esc = mysqli_real_escape_string($koneksi, $username_login);

    mysqli_begin_transaction($koneksi);
    try {
        if ($aksi === 'beli') {
            // Ambil data dari tabel pembelian
            $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT harga FROM pembelian WHERE id_request_detail = $id_detail LIMIT 1"));
            if (!$cek) {
                throw new Exception('Barang belum diinput ke database Pembelian. Silakan input nota terlebih dahulu.');
            }
            $harga_aktual = (float)$cek['harga'];

            // Update status beli + sinkronisasi harga nota ke detail PR
            if (!mysqli_query($koneksi,
                "UPDATE tr_request_detail SET
                    is_dibeli              = 1,
                    tgl_dibeli             = '$today',
                    dibeli_oleh            = '$nama_esc',
                    harga_satuan_estimasi  = $harga_aktual,
                    subtotal_estimasi      = (jumlah * $harga_aktual)
                 WHERE id_detail = $id_detail AND is_dibeli = 0")) {
                throw new Exception('Gagal update detail beli: ' . mysqli_error($koneksi));
            }

            // Re-hitung subtotal & grand_total PO
            if ($id_req_redir > 0) {
                mysqli_query($koneksi,
                    "UPDATE tr_purchase_order SET
                        subtotal = (SELECT SUM(subtotal_estimasi) FROM tr_request_detail WHERE id_request = $id_req_redir)
                     WHERE id_request = $id_req_redir");
                mysqli_query($koneksi,
                    "UPDATE tr_purchase_order SET
                        ppn_nominal = subtotal * (ppn_persen / 100),
                        grand_total = subtotal + (subtotal * (ppn_persen / 100)) - diskon
                     WHERE id_request = $id_req_redir");
            }

        } elseif ($aksi === 'pasang') {
            if (!mysqli_query($koneksi,
                "UPDATE tr_request_detail SET
                    status_pasang = 'TERPASANG',
                    tgl_pasang    = '$today',
                    pasang_oleh   = '$nama_esc'
                 WHERE id_detail = $id_detail AND is_ban = 1 AND is_dibeli = 1 AND status_pasang = 'BELUM_TERPASANG'")) {
                throw new Exception('Gagal update pasang: ' . mysqli_error($koneksi));
            }
        }

        // Cek apakah PO bisa otomatis CLOSE
        if ($id_req_redir > 0) {
            $belum_beli = (int)(mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT COUNT(*) AS jml FROM tr_request_detail
                 WHERE id_request = $id_req_redir AND is_dibeli = 0 AND status_item != 'REJECTED'"))['jml'] ?? 1);

            $ban_belum = (int)(mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT COUNT(*) AS jml FROM tr_request_detail
                 WHERE id_request = $id_req_redir AND is_ban = 1 AND status_pasang = 'BELUM_TERPASANG'"))['jml'] ?? 0);

            if ($belum_beli === 0 && $ban_belum === 0) {
                mysqli_query($koneksi,
                    "UPDATE tr_purchase_order SET status_po = 'CLOSE'
                     WHERE id_request = $id_req_redir AND status_po = 'OPEN'");
                mysqli_query($koneksi,
                    "UPDATE tr_request SET status_request = 'SELESAI', updated_by = '$user_esc', updated_at = '$now'
                     WHERE id_request = $id_req_redir AND status_request != 'SELESAI'");
            }
        }

        mysqli_commit($koneksi);
        header("location:update_status_ban.php?id_po=$id_po_redir&tab=open&pesan=berhasil");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        error_log('update_status_ban.php ERROR: ' . $e->getMessage());
        header('location:update_status_ban.php?id_po=' . $id_po_redir . '&pesan=gagal&msg=' . urlencode($e->getMessage()));
        exit;
    }
}

// ── TAMPIL HALAMAN ────────────────────────────────────────────
$id_po_filter = (int)($_GET['id_po'] ?? 0);
$tab_aktif    = in_array($_GET['tab'] ?? '', ['open', 'close']) ? $_GET['tab'] : 'open';
$search_po    = trim($_GET['cari'] ?? '');

// Helper WHERE untuk search sidebar
function searchWhere(string $s, $koneksi): string {
    if ($s === '') return '';
    $e = mysqli_real_escape_string($koneksi, $s);
    return "AND (p.no_po LIKE '%$e%' 
             OR r.no_request LIKE '%$e%' 
             OR s.nama_supplier LIKE '%$e%' 
             OR r.nama_pemesan LIKE '%$e%'
             OR EXISTS (
                SELECT 1 FROM tr_request_detail d
                LEFT JOIN master_barang b ON d.id_barang = b.id_barang
                WHERE d.id_request = r.id_request
                  AND (b.nama_barang LIKE '%$e%' 
                       OR d.keterangan LIKE '%$e%'
                       OR d.nama_barang_manual LIKE '%$e%')
             ))";
}

$sw_open  = searchWhere($search_po, $koneksi);
$sw_close = searchWhere($search_po, $koneksi);

$base_select = "SELECT p.*, r.no_request, r.nama_pemesan, r.keterangan AS tujuan,
                       r.status_request, r.updated_at AS tgl_close,
                       s.nama_supplier,
                       u_admin.nama_lengkap AS nama_admin_pembuat
                FROM tr_purchase_order p
                JOIN tr_request r ON p.id_request = r.id_request
                LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
                LEFT JOIN users u_admin ON r.created_by = u_admin.username";

$result_po_open = mysqli_query($koneksi,
    "$base_select WHERE p.status_po = 'OPEN' $sw_open ORDER BY p.tgl_approve DESC");
$result_po_close = mysqli_query($koneksi,
    "$base_select WHERE p.status_po = 'CLOSE' $sw_close ORDER BY r.updated_at DESC");

$jml_open  = mysqli_num_rows($result_po_open);
$jml_close = mysqli_num_rows($result_po_close);

// Auto-select PO pertama
if (!$id_po_filter) {
    $res_first = ($tab_aktif === 'close') ? $result_po_close : $result_po_open;
    $first = mysqli_fetch_assoc($res_first);
    if ($first) {
        $id_po_filter = (int)$first['id_po'];
        mysqli_data_seek($res_first, 0);
    }
}

// Detail PO terpilih
$po_detail      = null;
$detail_items   = null;
$id_request_sel = 0;

if ($id_po_filter) {
    $po_detail = mysqli_fetch_assoc(mysqli_query($koneksi,
        "$base_select WHERE p.id_po = $id_po_filter LIMIT 1"));

    if ($po_detail) {
        $id_request_sel = (int)$po_detail['id_request'];
        $detail_items = mysqli_query($koneksi,
            "SELECT d.*,
                    b.nama_barang AS nama_master,
                    m.plat_nomor,
                    pb.harga      AS harga_nota,
                    pb.qty        AS qty_nota,
                    pb.id_pembelian
             FROM tr_request_detail d
             LEFT JOIN master_barang b  ON d.id_barang = b.id_barang
             LEFT JOIN master_mobil  m  ON d.id_mobil  = m.id_mobil
             LEFT JOIN pembelian     pb ON d.id_detail  = pb.id_request_detail
             WHERE d.id_request = $id_request_sel
             ORDER BY d.id_detail ASC");
    }
}

// Hitung summary item
$total_item = $item_beli = $item_pending = 0;
$rows_item  = [];
if ($detail_items) {
    while ($d = mysqli_fetch_assoc($detail_items)) {
        if (($d['status_item'] ?? '') === 'REJECTED') continue;
        $total_item++;
        if ((int)($d['is_dibeli'] ?? 0)) $item_beli++;
        else                              $item_pending++;
        $rows_item[] = $d;
    }
}
$pct = $total_item > 0 ? round($item_beli / $total_item * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Status Pembelian — MCP</title>
<link rel="icon" type="image/png" href="../../assets/img/logo_mcp.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* ══════════════════════════════════════════
   TOKENS
══════════════════════════════════════════ */
:root {
    --navy      : #1e3a8a;
    --navy-mid  : #1d4ed8;
    --blue-lt   : #dbeafe;
    --teal      : #0d9488;
    --teal-dk   : #0f766e;
    --teal-lt   : #ccfbf1;
    --amber     : #d97706;
    --amber-lt  : #fef3c7;
    --green     : #059669;
    --green-lt  : #d1fae5;
    --red       : #dc2626;
    --red-lt    : #fee2e2;
    --slate     : #64748b;
    --slate-lt  : #f1f5f9;
    --surface   : #f0f9ff;
    --card      : #ffffff;
    --border    : #e2e8f0;
    --text      : #0f172a;
    --radius    : 14px;
    --radius-sm : 8px;
    --shadow    : 0 1px 3px rgba(0,0,0,.07), 0 4px 14px rgba(0,0,0,.06);
    --shadow-lg : 0 4px 8px rgba(0,0,0,.08), 0 12px 28px rgba(0,0,0,.10);
}

*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: .875rem;
    color: var(--text);
    background: var(--surface);
    min-height: 100vh;
}

/* ── TOPBAR ───────────────────────────── */
.topbar {
    background: linear-gradient(135deg, #0c2461 0%, var(--navy) 50%, var(--navy-mid) 100%);
    position: sticky; top: 0; z-index: 1030;
    box-shadow: 0 2px 20px rgba(30,58,138,.45);
}
.topbar-inner {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 12px 20px; gap: 12px;
    max-width: 1600px; margin: 0 auto;
}
.topbar-brand { display: flex; align-items: center; gap: 10px; }
.topbar-icon {
    width: 38px; height: 38px;
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1rem; flex-shrink: 0;
}
.topbar-title { color: #fff; font-weight: 800; font-size: .95rem; line-height: 1.1; }
.topbar-title span { color: #7dd3fc; }
.topbar-sub { color: rgba(255,255,255,.55); font-size: .68rem; margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 8px; }
.pill {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    color: rgba(255,255,255,.85);
    border-radius: 20px; padding: 4px 12px;
    font-size: .7rem; font-weight: 700; white-space: nowrap;
}
.btn-topbar {
    display: inline-flex; align-items: center; gap: 5px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    color: rgba(255,255,255,.85);
    border-radius: 20px; padding: 5px 13px;
    font-size: .73rem; font-weight: 700;
    cursor: pointer; text-decoration: none;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transition: background .15s;
}
.btn-topbar:hover { background: rgba(255,255,255,.22); color: #fff; }

/* ── LAYOUT ───────────────────────────── */
.main {
    max-width: 1600px; margin: 0 auto;
    padding: 22px 14px 60px;
}
@media (min-width: 768px)  { .main { padding: 26px 22px 60px; } }
@media (min-width: 1200px) { .main { padding: 26px 30px 60px; } }

.split-wrap {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 16px; align-items: start;
}
@media (max-width: 991px) { .split-wrap { grid-template-columns: 1fr; } }

/* ── SIDEBAR ──────────────────────────── */
.sidebar-card {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
    position: sticky; top: 70px;
}
.sidebar-head {
    padding: 12px 14px;
    background: linear-gradient(to right, #f8fafc, #fff);
    border-bottom: 1px solid var(--border);
    font-weight: 800; font-size: .82rem; color: var(--navy);
    display: flex; align-items: center; gap: 6px;
}
.sidebar-search {
    padding: 8px 10px;
    border-bottom: 1px solid var(--border);
    background: #fafbfc;
}
.sidebar-search input {
    width: 100%;
    padding: 6px 10px 6px 30px;
    font-size: .76rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 10px center;
    outline: none; transition: border-color .15s;
}
.sidebar-search input:focus { border-color: var(--teal); }

.po-tabs { display: flex; border-bottom: 2px solid var(--border); }
.po-tab {
    flex: 1; padding: 9px 6px;
    font-size: .74rem; font-weight: 800;
    text-align: center; background: none; border: none;
    cursor: pointer; color: var(--slate);
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    transition: color .15s, border-color .15s;
}
.po-tab.act-open  { color: var(--teal-dk); border-bottom-color: var(--teal); }
.po-tab.act-close { color: var(--slate);   border-bottom-color: var(--slate); }
.po-tab:hover { background: #f8fffe; }

.po-list {
    max-height: calc(100vh - 340px);
    min-height: 200px; overflow-y: auto;
}
.po-list::-webkit-scrollbar { width: 4px; }
.po-list::-webkit-scrollbar-thumb { background: var(--teal-lt); border-radius: 4px; }
@media (max-width: 991px) { .po-list { max-height: 260px; } }

.po-item {
    padding: 11px 13px;
    border-bottom: 1px solid var(--border);
    cursor: pointer; transition: background .12s;
    text-decoration: none; display: block; color: inherit;
    border-left: 3px solid transparent;
}
.po-item:hover { background: #f0fdfa; border-left-color: var(--teal); }
.po-item.active-open  { background: #ccfbf1; border-left-color: var(--teal-dk); }
.po-item.active-close { background: var(--slate-lt); border-left-color: var(--slate); }
.po-item-no   { font-size: .8rem; font-weight: 800; line-height: 1.2; }
.po-item-req  { font-size: .68rem; color: var(--slate); }
.po-item-sup  { font-size: .75rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px; }
.po-item-meta { font-size: .68rem; color: var(--slate); }
.po-item-amt  { font-family: 'JetBrains Mono', monospace; font-size: .72rem; font-weight: 700; color: var(--red); margin-top: 2px; }

.search-empty { padding: 24px 16px; text-align: center; color: var(--slate); font-size: .78rem; }
.search-empty i { display: block; font-size: 1.5rem; opacity: .25; margin-bottom: 8px; }

/* ── BADGES ───────────────────────────── */
.bdg {
    display: inline-flex; align-items: center; gap: 3px;
    border-radius: 20px; padding: 2px 8px;
    font-size: .63rem; font-weight: 800; white-space: nowrap;
}
.bdg-open    { background: var(--green-lt); color: #065f46; }
.bdg-close   { background: var(--slate-lt); color: var(--slate); border: 1px solid var(--border); }
.bdg-beli    { background: var(--blue-lt);  color: var(--navy-mid); }
.bdg-pending { background: var(--amber-lt); color: #92400e; }
.bdg-done    { background: var(--teal-lt);  color: var(--teal-dk); }
.bdg-ban-ok  { background: var(--green-lt); color: #065f46; }
.bdg-ban-no  { background: var(--red-lt);   color: #991b1b; }

/* ── DETAIL CARD ──────────────────────── */
.detail-card {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    overflow: hidden;
}
.detail-head {
    padding: 14px 18px;
    background: linear-gradient(to right, #f0fdfa, #fff);
    border-bottom: 1px solid var(--border);
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px 14px;
}
@media (min-width: 576px) { .info-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 768px) { .info-grid { grid-template-columns: repeat(4, 1fr); } }
.info-lbl {
    font-size: .62rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--slate); margin-bottom: 2px;
}
.info-val { font-size: .82rem; font-weight: 700; color: var(--text); }

.prog-bar-bg {
    height: 7px; background: var(--border);
    border-radius: 4px; overflow: hidden; flex: 1;
}
.prog-bar-fill {
    height: 100%; border-radius: 4px;
    background: linear-gradient(90deg, var(--teal-dk), var(--teal));
    transition: width .5s ease;
}
.prog-meta { font-size: .68rem; display: flex; gap: 12px; flex-wrap: wrap; margin-top: 5px; }

/* ── ITEM SEARCH ──────────────────────── */
.item-search-bar {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    background: #fafbfc;
}
.item-search-bar input {
    flex: 1;
    padding: 6px 10px 6px 30px;
    font-size: .76rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat 10px center;
    outline: none; transition: border-color .15s;
}
.item-search-bar input:focus { border-color: var(--teal); }
.item-count-badge {
    font-size: .65rem; font-weight: 800;
    background: var(--blue-lt); color: var(--navy);
    border-radius: 20px; padding: 2px 8px; white-space: nowrap;
}

/* ── TABEL ITEM ───────────────────────── */
.detail-body { overflow-x: auto; }
.item-table { width: 100%; border-collapse: collapse; font-size: .78rem; }
.item-table thead { position: sticky; top: 0; z-index: 2; }
.item-table th {
    background: #1e3a8a; color: #fff;
    padding: 9px 12px;
    font-size: .63rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .5px;
    white-space: nowrap;
}
.item-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}
.item-table tbody tr:hover { background: #f0fdfa; }
.item-table tbody tr:last-child td { border-bottom: none; }
.item-table .row-ban { background: #fffbeb; }
.item-table .row-ban:hover { background: #fef3c7; }
.item-table tr.row-hidden { display: none; }

/* ── TOMBOL AKSI ──────────────────────── */
.btn-beli {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--teal-dk); color: #fff;
    border: none; border-radius: var(--radius-sm);
    padding: 5px 11px; font-size: .72rem; font-weight: 700;
    cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif;
    transition: background .15s, transform .1s;
}
.btn-beli:hover   { background: var(--teal); transform: translateY(-1px); }
.btn-beli:active  { transform: translateY(0); }

.btn-pasang {
    display: inline-flex; align-items: center; gap: 5px;
    background: var(--amber); color: #fff;
    border: none; border-radius: var(--radius-sm);
    padding: 5px 11px; font-size: .72rem; font-weight: 700;
    cursor: pointer; font-family: 'Plus Jakarta Sans', sans-serif;
    transition: background .15s, transform .1s;
}
.btn-pasang:hover  { background: #b45309; transform: translateY(-1px); }
.btn-pasang:active { transform: translateY(0); }

.nota-warning {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--red-lt); color: #991b1b;
    border: 1px solid #fca5a5;
    border-radius: var(--radius-sm);
    padding: 4px 8px; font-size: .65rem; font-weight: 700;
}

/* ── EMPTY STATE ──────────────────────── */
.empty-state {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    min-height: 380px;
    color: var(--slate); text-align: center; padding: 40px 20px;
}
.empty-state i { font-size: 3rem; opacity: .15; margin-bottom: 14px; }

/* ── ALERT ────────────────────────────── */
.alert-bar {
    border-radius: var(--radius-sm);
    padding: 10px 16px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 8px;
    font-size: .8rem; font-weight: 600;
    animation: fadeUp .3s ease both;
}
.alert-bar.success { background: var(--green-lt); color: #065f46; border: 1px solid #6ee7b7; }
.alert-bar.danger  { background: var(--red-lt);   color: #991b1b; border: 1px solid #fca5a5; }

/* ── ANIMATIONS ───────────────────────── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<!-- ── TOPBAR ───────────────────────────────────────────────── -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-brand">
            <div class="topbar-icon"><i class="fas fa-shopping-bag"></i></div>
            <div>
                <div class="topbar-title">UPDATE STATUS <span>PEMBELIAN BESAR & PO</span></div>
                <div class="topbar-sub">Tandai item terbeli & ban terpasang</div>
            </div>
        </div>
        <div class="topbar-right">
            <span class="pill d-none d-sm-inline">
                <i class="fas fa-user me-1"></i><?= htmlspecialchars($nama_login) ?>
            </span>
            <a href="../../index.php" class="btn-topbar">
                <i class="fas fa-arrow-left"></i>
                <span class="d-none d-sm-inline">Dashboard</span>
            </a>
        </div>
    </div>
</header>

<div class="main">

    <!-- Alert -->
    <?php $pesan = $_GET['pesan'] ?? ''; ?>
    <?php if ($pesan === 'berhasil'): ?>
    <div class="alert-bar success">
        <i class="fas fa-check-circle"></i>
        <strong>Berhasil!</strong> Status berhasil diperbarui.
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:.9rem;">✕</button>
    </div>
    <?php elseif ($pesan === 'gagal'): ?>
    <div class="alert-bar danger">
        <i class="fas fa-times-circle"></i>
        <strong>Gagal!</strong> <?= htmlspecialchars($_GET['msg'] ?? 'Terjadi kesalahan.') ?>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:.9rem;">✕</button>
    </div>
    <?php elseif ($pesan === 'invalid'): ?>
    <div class="alert-bar danger">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Input tidak valid.</strong> Silakan coba lagi.
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;font-size:.9rem;">✕</button>
    </div>
    <?php endif; ?>

    <div class="split-wrap">

        <!-- ── SIDEBAR ──────────────────────────────────────── -->
        <div class="sidebar-card">
            <div class="sidebar-head">
                <i class="fas fa-list-ul" style="color:var(--teal);"></i>
                Daftar Purchase Order
            </div>

            <!-- Search sidebar -->
            <div class="sidebar-search">
                <form method="GET" action="">
                    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab_aktif) ?>">
                    <div style="display: flex; gap: 8px;">
                        <input type="text"
                               name="cari"
                               placeholder="Cari no. PO, supplier, pemesan…"
                               value="<?= htmlspecialchars($search_po) ?>"
                               oninput="if(this.value.trim() === '') { this.form.submit(); }"
                               style="flex: 1;">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="po-tabs">
                <button class="po-tab <?= $tab_aktif === 'open'  ? 'act-open'  : '' ?>"
                        onclick="goTab('open')">
                    <i class="fas fa-circle me-1" style="font-size:.42rem;color:var(--teal);"></i>
                    OPEN
                    <span style="background:var(--teal-lt);color:var(--teal-dk);border-radius:10px;padding:0 6px;font-size:.62rem;margin-left:3px;"><?= $jml_open ?></span>
                </button>
                <button class="po-tab <?= $tab_aktif === 'close' ? 'act-close' : '' ?>"
                        onclick="goTab('close')">
                    <i class="fas fa-check-double me-1"></i>
                    CLOSE
                    <span style="background:var(--slate-lt);color:var(--slate);border-radius:10px;padding:0 6px;font-size:.62rem;margin-left:3px;"><?= $jml_close ?></span>
                </button>
            </div>

            <div class="po-list">
            <?php
            $result_aktif = ($tab_aktif === 'close') ? $result_po_close : $result_po_open;
            $jml_aktif    = ($tab_aktif === 'close') ? $jml_close : $jml_open;
            if ($jml_aktif > 0) mysqli_data_seek($result_aktif, 0);

            if ($jml_aktif === 0):
            ?>
                <div class="search-empty">
                    <i class="fas fa-<?= $search_po ? 'search' : 'inbox' ?>"></i>
                    <?= $search_po
                        ? 'Tidak ada hasil untuk <strong>' . htmlspecialchars($search_po) . '</strong>'
                        : 'Tidak ada PO ' . strtoupper($tab_aktif) . '.' ?>
                </div>
            <?php else:
                while ($po_row = mysqli_fetch_assoc($result_aktif)):
                    $is_sel = ($id_po_filter === (int)$po_row['id_po']);
                    $cls    = $is_sel ? ($tab_aktif === 'open' ? 'active-open' : 'active-close') : '';
            ?>
                <a class="po-item <?= $cls ?>"
                   href="?tab=<?= $tab_aktif ?>&id_po=<?= (int)$po_row['id_po'] ?><?= $search_po ? '&cari=' . urlencode($search_po) : '' ?>">
                    <div class="d-flex justify-content-between align-items-start gap-1">
                        <div class="po-item-no <?= $tab_aktif === 'open' ? 'text-primary' : 'text-secondary' ?>">
                            <?= htmlspecialchars($po_row['no_po']) ?>
                        </div>
                        <span class="bdg <?= $tab_aktif === 'open' ? 'bdg-open' : 'bdg-close' ?>" style="flex-shrink:0;">
                            <?= strtoupper($tab_aktif) ?>
                        </span>
                    </div>
                    <div class="po-item-req"><?= htmlspecialchars($po_row['no_request']) ?></div>
                    <div class="po-item-sup"><?= htmlspecialchars($po_row['nama_supplier'] ?? '-') ?></div>
                    <div class="po-item-meta">
                        <i class="fas fa-user me-1 opacity-50"></i>
                        <?= htmlspecialchars($po_row['nama_admin_pembuat'] ?? $po_row['nama_pemesan'] ?? '-') ?>
                    </div>
                    <div class="po-item-amt">
                        Rp <?= number_format((float)$po_row['grand_total'], 0, ',', '.') ?>
                        <?php if ($tab_aktif === 'close' && !empty($po_row['tgl_close'])): ?>
                            <span style="color:var(--teal-dk);margin-left:6px;">
                                ✓ <?= date('d/m/y', strtotime($po_row['tgl_close'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endwhile; endif; ?>
            </div>
        </div><!-- /sidebar-card -->

        <!-- ── DETAIL PANEL ─────────────────────────────────── -->
        <div>
        <?php if ($po_detail): ?>

        <?php $is_close_view = ($po_detail['status_po'] === 'CLOSE'); ?>

        <!-- Info PO -->
        <div class="detail-card mb-3">
            <div class="detail-head">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <div style="font-family:'JetBrains Mono',monospace;font-size:1.05rem;font-weight:700;color:var(--navy);">
                            <?= htmlspecialchars($po_detail['no_po']) ?>
                        </div>
                        <div style="margin-top:4px;">
                            <span class="bdg <?= $is_close_view ? 'bdg-close' : 'bdg-open' ?>">
                                <i class="fas <?= $is_close_view ? 'fa-check-double' : 'fa-circle' ?> me-1" style="font-size:.4rem;"></i>
                                <?= $is_close_view ? 'CLOSE' : 'OPEN' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Progress ringkas -->
                    <div style="text-align:right;">
                        <div style="font-family:'JetBrains Mono',monospace;font-size:1.4rem;font-weight:700;color:var(--teal-dk);"><?= $pct ?>%</div>
                        <div style="font-size:.65rem;color:var(--slate);"><?= $item_beli ?>/<?= $total_item ?> item selesai</div>
                    </div>
                </div>

                <!-- Progress bar -->
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <div class="prog-bar-bg">
                        <div class="prog-bar-fill" style="width:<?= $pct ?>%;"></div>
                    </div>
                </div>

                <!-- Info grid -->
                <div class="info-grid">
                    <div>
                        <div class="info-lbl">No. Request</div>
                        <div class="info-val"><?= htmlspecialchars($po_detail['no_request']) ?></div>
                    </div>
                    <div>
                        <div class="info-lbl">Supplier</div>
                        <div class="info-val"><?= htmlspecialchars($po_detail['nama_supplier'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="info-lbl">Admin / Pemesan</div>
                        <div class="info-val"><?= htmlspecialchars($po_detail['nama_admin_pembuat'] ?? $po_detail['nama_pemesan'] ?? '-') ?></div>
                    </div>
                    <div>
                        <div class="info-lbl">Grand Total</div>
                        <div class="info-val" style="font-family:'JetBrains Mono',monospace;color:var(--red);">
                            Rp <?= number_format((float)$po_detail['grand_total'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <div style="grid-column:1/-1;">
                        <div class="info-lbl">Keperluan / Tujuan</div>
                        <div class="info-val" style="font-weight:500;"><?= htmlspecialchars($po_detail['tujuan'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabel item -->
        <div class="detail-card">
            <!-- Header tabel -->
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);background:linear-gradient(to right,#f8fafc,#fff);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                <div style="font-weight:800;font-size:.84rem;color:var(--navy);display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-<?= $is_close_view ? 'lock' : 'tasks' ?>" style="color:<?= $is_close_view ? 'var(--slate)' : 'var(--teal)' ?>;"></i>
                    <?= $is_close_view ? 'Histori Item — Readonly' : 'Daftar Item — Update Status' ?>
                </div>
                <?php if ($is_close_view): ?>
                <span class="bdg bdg-close"><i class="fas fa-lock me-1"></i>SELESAI</span>
                <?php endif; ?>
            </div>

            <!-- Search item barang -->
            <div class="item-search-bar">
                <input type="text"
                       id="searchItem"
                       placeholder="Cari nama barang, keterangan, atau plat nomor…"
                       oninput="filterItems(this.value)">
                <span class="item-count-badge" id="itemCount"><?= count($rows_item) ?> item</span>
            </div>

            <div class="detail-body">
                <table class="item-table">
                    <thead>
                        <tr>
                            <th width="4%">#</th>
                            <th>Nama Barang</th>
                            <th width="90">Unit / Plat</th>
                            <th width="75">Qty</th>
                            <th width="140">Status Beli</th>
                            <th width="140">Status Ban</th>
                            <?php if (!$is_close_view): ?>
                            <th width="155">Aksi</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="itemTableBody">
                    <?php if (empty($rows_item)): ?>
                        <tr>
                            <td colspan="<?= $is_close_view ? 6 : 7 ?>" style="text-align:center;padding:30px;color:var(--slate);">
                                <i class="fas fa-minus" style="opacity:.3;"></i> Tidak ada item.
                            </td>
                        </tr>
                    <?php else:
                        foreach ($rows_item as $i => $d):
                            $nama          = $d['nama_master'] ?: ($d['nama_barang_manual'] ?? '-');
                            $unit          = $d['plat_nomor']  ?: '-';
                            $is_ban        = (int)($d['is_ban']    ?? 0);
                            $is_dibeli     = (int)($d['is_dibeli'] ?? 0);
                            $status_pasang = $d['status_pasang'] ?? null;
                            $harga_nota    = $d['harga_nota']    ?? null;
                            $sudah_nota    = !empty($d['id_pembelian']);
                            $keterangan    = $d['keterangan'] ?? '';
                            $kwalifikasi   = $d['kwalifikasi'] ?? '';
                            $harga_est     = $d['harga_satuan_estimasi'] ?? '';
                    ?>
                        <tr class="<?= $is_ban ? 'row-ban' : '' ?>"
                            data-search="<?= htmlspecialchars(strtolower($nama . ' ' . $unit . ' ' . $keterangan . ' ' . $kwalifikasi . ' ' . $harga_est . ' ' . ($harga_nota ?: ''))) ?>">
                            <td style="text-align:center;color:var(--slate);font-size:.73rem;"><?= $i + 1 ?></td>
                            <td>
                                <div style="font-weight:700;"><?= htmlspecialchars(strtoupper($nama)) ?></div>
                                <?php if ($is_ban): ?>
                                <span style="font-size:.6rem;background:#fff3cd;border:1px solid #ffc107;color:#7c4a00;padding:1px 5px;border-radius:4px;font-weight:700;">
                                    <i class="fas fa-circle me-1" style="font-size:.35rem;"></i>BAN
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($d['kwalifikasi'])): ?>
                                <div style="font-size:.68rem;color:var(--slate);margin-top:2px;"><?= htmlspecialchars($d['kwalifikasi']) ?></div>
                                <?php endif; ?>
                                <!-- Harga estimasi vs nota -->
                                <div style="font-size:.68rem;margin-top:3px;display:flex;gap:8px;flex-wrap:wrap;">
                                    <span style="color:var(--slate);">
                                        Est: <span style="font-family:'JetBrains Mono',monospace;">Rp <?= number_format((float)($d['harga_satuan_estimasi'] ?? 0), 0, ',', '.') ?></span>
                                    </span>
                                    <?php if ($sudah_nota && $harga_nota !== null): ?>
                                    <span style="color:<?= ((float)$harga_nota > (float)($d['harga_satuan_estimasi'] ?? 0)) ? 'var(--red)' : 'var(--teal-dk)' ?>;font-weight:700;">
                                        <i class="fas fa-tag me-1"></i>Nota:
                                        <span style="font-family:'JetBrains Mono',monospace;">Rp <?= number_format((float)$harga_nota, 0, ',', '.') ?></span>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($keterangan)): ?>
                                <div style="font-size:.65rem;color:var(--slate);margin-top:2px;font-style:italic;">
                                    <?= htmlspecialchars($keterangan) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($unit !== '-'): ?>
                                <span style="background:var(--slate-lt);border:1px solid var(--border);border-radius:5px;padding:2px 7px;font-size:.7rem;font-weight:700;">
                                    <?= htmlspecialchars($unit) ?>
                                </span>
                                <?php else: ?>
                                <span style="color:var(--slate);">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;font-weight:800;font-size:.8rem;">
                                <?= (float)($d['jumlah'] ?? 0) + 0 ?>
                                <span style="font-size:.65rem;color:var(--slate);font-weight:500;"><?= htmlspecialchars($d['satuan'] ?? '') ?></span>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($is_dibeli): ?>
                                    <span class="bdg bdg-done"><i class="fas fa-check me-1"></i>TERBELI</span>
                                    <div style="font-size:.63rem;color:var(--slate);margin-top:3px;">
                                        <?= !empty($d['tgl_dibeli']) ? date('d/m/Y', strtotime($d['tgl_dibeli'])) : '' ?>
                                        <?= !empty($d['dibeli_oleh']) ? '<br>' . htmlspecialchars($d['dibeli_oleh']) : '' ?>
                                    </div>
                                <?php else: ?>
                                    <span class="bdg bdg-pending"><i class="fas fa-clock me-1"></i>OPEN</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($is_ban): ?>
                                    <?php if ($status_pasang === 'TERPASANG'): ?>
                                        <span class="bdg bdg-ban-ok"><i class="fas fa-check me-1"></i>TERPASANG</span>
                                        <div style="font-size:.63rem;color:var(--slate);margin-top:3px;">
                                            <?= !empty($d['tgl_pasang']) ? date('d/m/Y', strtotime($d['tgl_pasang'])) : '' ?>
                                            <?= !empty($d['pasang_oleh']) ? '<br>' . htmlspecialchars($d['pasang_oleh']) : '' ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="bdg bdg-ban-no"><i class="fas fa-clock me-1"></i>BELUM PASANG</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--slate);">—</span>
                                <?php endif; ?>
                            </td>
                            <?php if (!$is_close_view): ?>
                            <td style="text-align:center;">
                                <div style="display:flex;flex-direction:column;gap:5px;align-items:center;">
                                <?php if (!$is_dibeli): ?>
                                    <?php if ($sudah_nota): ?>
                                        <div style="font-size:.63rem;color:var(--teal-dk);font-weight:700;margin-bottom:2px;">
                                            <i class="fas fa-check-circle me-1"></i>Nota ada
                                        </div>
                                        <form method="POST" onsubmit="return konfirmBeli(event, '<?= htmlspecialchars(strtoupper($nama), ENT_QUOTES) ?>')">
                                            <input type="hidden" name="id_detail"  value="<?= (int)$d['id_detail'] ?>">
                                            <input type="hidden" name="id_po"      value="<?= $id_po_filter ?>">
                                            <input type="hidden" name="id_request" value="<?= $id_request_sel ?>">
                                            <input type="hidden" name="aksi"       value="beli">
                                            <button type="submit" class="btn-beli">
                                                <i class="fas fa-shopping-bag"></i> DONE
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="nota-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Nota belum diinput
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if ($is_ban && $is_dibeli && $status_pasang === 'BELUM_TERPASANG'): ?>
                                    <form method="POST" onsubmit="return konfirmPasang(event, '<?= htmlspecialchars(strtoupper($nama), ENT_QUOTES) ?>', '<?= htmlspecialchars($unit, ENT_QUOTES) ?>')">
                                        <input type="hidden" name="id_detail"  value="<?= (int)$d['id_detail'] ?>">
                                        <input type="hidden" name="id_po"      value="<?= $id_po_filter ?>">
                                        <input type="hidden" name="id_request" value="<?= $id_request_sel ?>">
                                        <input type="hidden" name="aksi"       value="pasang">
                                        <button type="submit" class="btn-pasang">
                                            <i class="fas fa-circle"></i> Tandai Terpasang
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($is_dibeli && (!$is_ban || $status_pasang === 'TERPASANG')): ?>
                                    <span style="font-size:.72rem;color:var(--teal-dk);font-weight:700;">
                                        <i class="fas fa-check-circle me-1"></i>Selesai
                                    </span>
                                <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <!-- No result saat search item -->
                <div id="itemNoResult" style="display:none;padding:24px;text-align:center;color:var(--slate);font-size:.8rem;">
                    <i class="fas fa-search" style="opacity:.2;font-size:1.8rem;display:block;margin-bottom:8px;"></i>
                    Tidak ada item yang cocok.
                </div>
            </div>
        </div><!-- /detail-card tabel -->

        <?php else: ?>
        <div class="detail-card">
            <div class="empty-state">
                <i class="fas fa-hand-point-left"></i>
                <p><strong>Pilih PO dari daftar sebelah kiri</strong></p>
                <p style="font-size:.75rem;color:var(--slate);">untuk melihat dan mengupdate status item</p>
            </div>
        </div>
        <?php endif; ?>
        </div><!-- /detail wrapper -->

    </div><!-- /split-wrap -->
</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Ganti tab ─────────────────────────────────────────────────
function goTab(tab) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    url.searchParams.delete('id_po');
    url.searchParams.delete('cari');
    window.location.href = url.toString();
}

// ── Filter item barang (client-side, realtime) ────────────────
function filterItems(val) {
    const q     = val.trim().toLowerCase();
    const rows  = document.querySelectorAll('#itemTableBody tr[data-search]');
    let visible = 0;

    rows.forEach(function(tr) {
        const text = tr.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
            tr.classList.remove('row-hidden');
            visible++;
        } else {
            tr.classList.add('row-hidden');
        }
    });

    const counter  = document.getElementById('itemCount');
    const noResult = document.getElementById('itemNoResult');
    if (counter)  counter.textContent = visible + ' item';
    if (noResult) noResult.style.display = (visible === 0 && rows.length > 0) ? 'block' : 'none';
}

// ── Konfirmasi beli ───────────────────────────────────────────
function konfirmBeli(e, nama) {
    e.preventDefault();
    var form = e.target;
    Swal.fire({
        title: 'Tandai sudah dibeli?',
        html: 'Item: <strong>' + nama + '</strong><br><small class="text-muted">Tindakan ini tidak bisa dibatalkan.</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0f766e',
        confirmButtonText: '<i class="fas fa-check me-1"></i> Ya, Sudah Dibeli',
        cancelButtonText: 'Batal'
    }).then(function(r) {
        if (r.isConfirmed) {
            Swal.fire({ title: 'Menyimpan…', allowOutsideClick: false, showConfirmButton: false,
                didOpen: function() { Swal.showLoading(); } });
            form.submit();
        }
    });
    return false;
}

// ── Konfirmasi pasang ─────────────────────────────────────────
function konfirmPasang(e, nama, plat) {
    e.preventDefault();
    var form = e.target;
    Swal.fire({
        title: 'Tandai ban sudah terpasang?',
        html: 'Ban: <strong>' + nama + '</strong><br>Kendaraan: <strong>' + plat + '</strong><br><small class="text-muted">Pastikan ban sudah benar-benar terpasang.</small>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d97706',
        confirmButtonText: '<i class="fas fa-circle me-1"></i> Ya, Sudah Terpasang',
        cancelButtonText: 'Batal'
    }).then(function(r) {
        if (r.isConfirmed) {
            Swal.fire({ title: 'Menyimpan…', allowOutsideClick: false, showConfirmButton: false,
                didOpen: function() { Swal.showLoading(); } });
            form.submit();
        }
    });
    return false;
}
</script>
</body>
</html>