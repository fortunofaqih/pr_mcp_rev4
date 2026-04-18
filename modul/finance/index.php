<?php
// ============================================================
// modul/finance/index.php
// Dashboard Finance — Monitor PO & Status Pembelian
// READ-ONLY: Finance hanya bisa melihat & cetak PO
// + Tambah: Lihat PDF penawaran supplier
// ============================================================
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$nama_login = strtoupper($_SESSION['nama'] ?? $_SESSION['username'] ?? '');

// ── Filter tab & PO ─────────────────────────────────────────
$id_po_filter = (int)($_GET['id_po'] ?? 0);
$tab_aktif    = $_GET['tab'] ?? 'open';
if (!in_array($tab_aktif, ['open','close'])) $tab_aktif = 'open';

// ── Query PO OPEN ────────────────────────────────────────────
$result_po_open = mysqli_query($koneksi,
    "SELECT p.*, r.no_request, r.nama_pemesan, r.keterangan as tujuan,
            r.status_request, r.kategori_pr, r.status_approval,
            s.nama_supplier, p.tgl_approve
     FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request = r.id_request
     LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
     WHERE p.status_po = 'OPEN'
     ORDER BY p.tgl_approve DESC");

// ── Query PO CLOSE ───────────────────────────────────────────
$result_po_close = mysqli_query($koneksi,
    "SELECT p.*, r.no_request, r.nama_pemesan, r.keterangan as tujuan,
            r.status_request, r.kategori_pr, r.status_approval,
            r.updated_at as tgl_close, s.nama_supplier
     FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request = r.id_request
     LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
     WHERE p.status_po = 'CLOSE'
     ORDER BY r.updated_at DESC");

$jml_open  = mysqli_num_rows($result_po_open);
$jml_close = mysqli_num_rows($result_po_close);

// ── Auto-select PO pertama jika belum ada filter ─────────────
if (!$id_po_filter) {
    $result_aktif = ($tab_aktif === 'close') ? $result_po_close : $result_po_open;
    $first = mysqli_fetch_assoc($result_aktif);
    if ($first) {
        $id_po_filter = (int)$first['id_po'];
        mysqli_data_seek($result_aktif, 0);
    }
}

// ── Detail PO terpilih ───────────────────────────────────────
$po_detail      = null;
$detail_items   = null;
$id_request_sel = 0;

if ($id_po_filter) {
    $po_detail = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT p.*, r.no_request, r.nama_pemesan, r.keterangan as tujuan,
                r.status_request, r.kategori_pr, r.status_approval,
                r.updated_at as tgl_close, s.nama_supplier,
                p.tgl_approve,
                r.approve1_by, r.approve1_at,
                r.approve2_by, r.approve2_at,
                r.approve3_by, r.approve3_at,
                r.file_penawaran
         FROM tr_purchase_order p
         JOIN tr_request r ON p.id_request = r.id_request
         LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
         WHERE p.id_po = '$id_po_filter' LIMIT 1"));

    if ($po_detail) {
    $id_request_sel = (int)$po_detail['id_request'];
    $detail_items = mysqli_query($koneksi,
        "SELECT 
            d.*, 
            b.nama_barang as nama_master, 
            m.plat_nomor,
            p.tgl_beli_barang, 
            p.id_user_beli,
            u.nama_lengkap AS nama_petugas_beli
         FROM tr_request_detail d
         LEFT JOIN master_barang b ON d.id_barang = b.id_barang
         LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
         /* JOIN ke tabel pembelian untuk ambil data realisasi */
         LEFT JOIN pembelian p ON d.id_detail = p.id_request_detail
         /* JOIN ke tabel users untuk ambil nama yang beli */
         LEFT JOIN users u ON p.id_user_beli = u.id_user
         WHERE d.id_request = '$id_request_sel'
         ORDER BY d.id_detail ASC");
}
}

// ── Hitung summary item untuk PO terpilih ───────────────────
$total_item = $item_dibeli = $item_verif = $item_pending = 0;
$rows_item  = [];
if ($detail_items) {
    while ($d = mysqli_fetch_assoc($detail_items)) {
        if (($d['status_item'] ?? '') === 'REJECTED') continue;
        $total_item++;
        if ($d['status_item'] === 'TERBELI')                 $item_dibeli++;
        elseif ($d['status_item'] === 'MENUNGGU VERIFIKASI') $item_verif++;
        else                                                  $item_pending++;
        $rows_item[] = $d;
    }
}
$pct_selesai = $total_item > 0 ? round($item_dibeli / $total_item * 100) : 0;

// ── Stat cards ───────────────────────────────────────────────
$bln_awal  = date('Y-m-01');
$bln_akhir = date('Y-m-t');
$bulan_id  = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$periode   = $bulan_id[(int)date('m')].' '.date('Y');

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(grand_total),0) AS total FROM tr_purchase_order WHERE status_po='OPEN'"));
$nilai_open = (float)$r['total'];

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS c FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request=r.id_request
     WHERE p.status_po='CLOSE' AND DATE(r.updated_at) BETWEEN '$bln_awal' AND '$bln_akhir'"));
$po_close_bulan = (int)$r['c'];

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS c FROM tr_request
     WHERE kategori_pr='BESAR'
       AND status_approval NOT IN ('APPROVED','DISETUJUI')
       AND status_request NOT IN ('SELESAI','REJECTED')"));
$pr_tunggu_appr = (int)$r['c'];

function rpFmt(float $n): string {
    if ($n >= 1_000_000_000) return 'Rp '.number_format($n/1_000_000_000,2,',','.').' M';
    if ($n >= 1_000_000)     return 'Rp '.number_format($n/1_000_000,1,',','.').' jt';
    return 'Rp '.number_format($n,0,',','.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finance Dashboard — MCP</title>
<link rel="icon" type="image/png" href="../../assets/img/logo_mcp.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    --text-soft : #475569;
    --radius    : 14px;
    --radius-sm : 8px;
    --shadow    : 0 1px 3px rgba(0,0,0,.07), 0 4px 14px rgba(0,0,0,.06);
    --shadow-lg : 0 4px 8px rgba(0,0,0,.08), 0 12px 28px rgba(0,0,0,.10);
}

*,*::before,*::after{box-sizing:border-box;}
body {
    font-family:'Plus Jakarta Sans',sans-serif;
    font-size:.875rem; color:var(--text);
    background:var(--surface); min-height:100vh;
}

/* ── TOPBAR ──────────────────────────── */
.topbar {
    background:linear-gradient(135deg,#0c2461 0%,var(--navy) 50%,var(--navy-mid) 100%);
    position:sticky; top:0; z-index:1030;
    box-shadow:0 2px 20px rgba(30,58,138,.45);
}
.topbar-inner {
    display:flex; align-items:center;
    justify-content:space-between;
    padding:12px 20px; gap:12px;
    max-width:1600px; margin:0 auto;
}
.topbar-brand { display:flex; align-items:center; gap:10px; }
.topbar-icon {
    width:38px; height:38px;
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.2);
    border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    color:#fff; font-size:1rem; flex-shrink:0;
}
.topbar-title { color:#fff; font-weight:800; font-size:.95rem; line-height:1.1; }
.topbar-title span { color:#7dd3fc; }
.topbar-sub { color:rgba(255,255,255,.55); font-size:.68rem; margin-top:1px; }
.topbar-right { display:flex; align-items:center; gap:8px; }
.pill {
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.2);
    color:rgba(255,255,255,.85);
    border-radius:20px; padding:4px 12px;
    font-size:.7rem; font-weight:700; white-space:nowrap;
}
.btn-topbar {
    display:inline-flex; align-items:center; gap:5px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.2);
    color:rgba(255,255,255,.85);
    border-radius:20px; padding:5px 13px;
    font-size:.73rem; font-weight:700;
    cursor:pointer; text-decoration:none;
    font-family:'Plus Jakarta Sans',sans-serif;
    transition:background .15s;
}
.btn-topbar:hover { background:rgba(255,255,255,.22); color:#fff; }

/* ── LAYOUT ──────────────────────────── */
.main {
    max-width:1600px; margin:0 auto;
    padding:22px 14px 60px;
}
@media(min-width:768px){.main{padding:26px 22px 60px;}}
@media(min-width:1200px){.main{padding:26px 30px 60px;}}

/* ── STAT CARDS ──────────────────────── */
.stat-grid {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:12px; margin-bottom:22px;
}
@media(min-width:768px){.stat-grid{grid-template-columns:repeat(4,1fr);}}

.stat-card {
    background:var(--card);
    border-radius:var(--radius);
    padding:16px 18px;
    box-shadow:var(--shadow);
    border:1px solid var(--border);
    border-top:3px solid transparent;
    position:relative; overflow:hidden;
    transition:transform .18s, box-shadow .18s;
    animation:fadeUp .3s ease both;
}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg);}
.sc-open  {border-top-color:var(--teal);}
.sc-close {border-top-color:var(--slate);}
.sc-appr  {border-top-color:var(--amber);}
.sc-nilai {border-top-color:var(--navy-mid);}

.stat-icon {
    width:38px; height:38px; border-radius:10px;
    display:flex; align-items:center; justify-content:center;
    font-size:.95rem; margin-bottom:10px;
}
.sc-open  .stat-icon {background:var(--teal-lt);  color:var(--teal-dk);}
.sc-close .stat-icon {background:var(--slate-lt); color:var(--slate);}
.sc-appr  .stat-icon {background:var(--amber-lt); color:var(--amber);}
.sc-nilai .stat-icon {background:var(--blue-lt);  color:var(--navy);}

.stat-label {
    font-size:.63rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.6px;
    color:var(--slate); margin-bottom:3px;
}
.stat-value {
    font-family:'JetBrains Mono',monospace;
    font-size:1.8rem; font-weight:700; line-height:1; margin-bottom:3px;
}
.sc-open  .stat-value {color:var(--teal-dk);}
.sc-close .stat-value {color:var(--slate);}
.sc-appr  .stat-value {color:var(--amber);}
.sc-nilai .stat-value {color:var(--navy); font-size:1.2rem;}
.stat-sub {font-size:.68rem; color:var(--slate);}

/* ── MAIN SPLIT ──────────────────────── */
.split-wrap {
    display:grid;
    grid-template-columns:290px 1fr;
    gap:16px; align-items:start;
}
@media(max-width:991px){.split-wrap{grid-template-columns:1fr;}}

/* ── SIDEBAR PO ──────────────────────── */
.sidebar-card {
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    border:1px solid var(--border);
    overflow:hidden;
    animation:fadeUp .35s ease both;
}
.sidebar-head {
    padding:12px 14px;
    background:linear-gradient(to right,#f8fafc,#fff);
    border-bottom:1px solid var(--border);
    font-weight:800; font-size:.82rem;
    color:var(--navy); display:flex;
    align-items:center; gap:6px;
}

.po-tabs { display:flex; border-bottom:2px solid var(--border); }
.po-tab {
    flex:1; padding:9px 6px;
    font-size:.74rem; font-weight:800;
    text-align:center; background:none;
    border:none; cursor:pointer; color:var(--slate);
    border-bottom:3px solid transparent; margin-bottom:-2px;
    font-family:'Plus Jakarta Sans',sans-serif;
    transition:color .15s, border-color .15s;
}
.po-tab.act-open  {color:var(--teal-dk); border-bottom-color:var(--teal);}
.po-tab.act-close {color:var(--slate);   border-bottom-color:var(--slate);}
.po-tab:hover{background:#f8fffe;}

.po-list {
    max-height:calc(100vh - 280px);
    min-height:200px; overflow-y:auto;
}
.po-list::-webkit-scrollbar{width:4px;}
.po-list::-webkit-scrollbar-thumb{background:var(--teal-lt);border-radius:4px;}

.po-item {
    padding:11px 13px;
    border-bottom:1px solid var(--border);
    cursor:pointer; transition:background .12s;
    text-decoration:none; display:block; color:inherit;
    border-left:3px solid transparent;
}
.po-item:hover{background:#f0fdfa; border-left-color:var(--teal);}
.po-item.active-open  {background:#ccfbf1; border-left-color:var(--teal-dk);}
.po-item.active-close {background:var(--slate-lt); border-left-color:var(--slate);}
.po-item-no   {font-size:.8rem; font-weight:800; line-height:1.2;}
.po-item-req  {font-size:.68rem; color:var(--slate);}
.po-item-sup  {font-size:.75rem; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:230px;}
.po-item-amt  {font-family:'JetBrains Mono',monospace; font-size:.72rem; font-weight:700; color:var(--red); margin-top:2px;}
.po-item-date {font-size:.65rem; color:var(--slate);}

.bdg {
    display:inline-flex; align-items:center; gap:3px;
    border-radius:20px; padding:2px 8px;
    font-size:.63rem; font-weight:800; white-space:nowrap;
}
.bdg-open    {background:var(--green-lt); color:#065f46;}
.bdg-close   {background:var(--slate-lt); color:var(--slate); border:1px solid var(--border);}
.bdg-besar   {background:var(--red-lt);   color:#991b1b;}
.bdg-kecil   {background:var(--blue-lt);  color:var(--navy);}
.bdg-appr    {background:var(--teal-lt);  color:var(--teal-dk);}
.bdg-tunggu  {background:var(--amber-lt); color:#92400e;}
.bdg-proses  {background:var(--blue-lt);  color:var(--navy-mid);}
.bdg-selesai {background:var(--green-lt); color:#065f46;}

/* ── DETAIL PANEL ────────────────────── */
.detail-card {
    background:var(--card);
    border-radius:var(--radius);
    box-shadow:var(--shadow);
    border:1px solid var(--border);
    overflow:hidden;
    animation:fadeUp .4s ease both;
}
.detail-head {
    padding:14px 18px;
    background:linear-gradient(to right,#f0fdfa,#fff);
    border-bottom:1px solid var(--border);
}
.detail-head-top {
    display:flex; align-items:center;
    justify-content:space-between; flex-wrap:wrap;
    gap:8px; margin-bottom:12px;
}
.po-number {
    font-family:'JetBrains Mono',monospace;
    font-size:1.05rem; font-weight:700; color:var(--navy);
}
.info-grid {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:8px 14px;
}
@media(min-width:576px){.info-grid{grid-template-columns:repeat(3,1fr);}}
@media(min-width:768px){.info-grid{grid-template-columns:repeat(4,1fr);}}
.info-lbl {
    font-size:.62rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.5px;
    color:var(--slate); margin-bottom:2px;
}
.info-val {font-size:.82rem; font-weight:700; color:var(--text);}
.info-val.danger{color:var(--red); font-family:'JetBrains Mono',monospace;}

.prog-bar-bg {
    height:7px; background:var(--border);
    border-radius:4px; overflow:hidden; flex:1;
}
.prog-bar-fill {
    height:100%; border-radius:4px;
    background:linear-gradient(90deg,var(--teal-dk),var(--teal));
    transition:width .5s ease;
}
.prog-meta {font-size:.68rem; display:flex; gap:12px; flex-wrap:wrap; margin-top:5px;}

/* ── TABEL ITEM ──────────────────────── */
.detail-body{overflow-x:auto;}
.item-table {width:100%; border-collapse:collapse; font-size:.78rem;}
.item-table th {
    background:#1e3a8a; color:#fff;
    padding:9px 12px;
    font-size:.63rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.5px;
    white-space:nowrap;
}
.item-table td {
    padding:10px 12px;
    border-bottom:1px solid var(--border);
    vertical-align:middle;
}
.item-table tbody tr:hover{background:#f0fdfa;}
.item-table tbody tr:last-child td{border-bottom:none;}
.item-table .row-ban{background:#fffbeb;}
.item-table .row-ban:hover{background:#fef3c7;}

.st-dibeli      {background:var(--teal-lt); color:var(--teal-dk);}
.st-verif       {background:var(--amber-lt);color:#92400e;}
.st-pending     {background:var(--blue-lt); color:var(--navy-mid);}
.st-terpasang   {background:var(--green-lt);color:#065f46;}
.st-belum-pasang{background:var(--red-lt);  color:#991b1b;}

/* ── TOMBOL CETAK ────────────────────── */
.btn-cetak {
    display:inline-flex; align-items:center; gap:6px;
    background:var(--navy); color:#fff;
    border:none; border-radius:var(--radius-sm);
    padding:7px 16px; font-size:.75rem; font-weight:700;
    cursor:pointer; text-decoration:none;
    font-family:'Plus Jakarta Sans',sans-serif;
    transition:background .15s, transform .1s;
    box-shadow:0 2px 8px rgba(30,58,138,.3);
}
.btn-cetak:hover{background:#1d4ed8; color:#fff; transform:translateY(-1px);}
.btn-cetak:active{transform:translateY(0);}
.btn-cetak-outline {
    display:inline-flex; align-items:center; gap:6px;
    background:#fff; color:var(--navy);
    border:1.5px solid var(--navy);
    border-radius:var(--radius-sm);
    padding:6px 14px; font-size:.73rem; font-weight:700;
    cursor:pointer; text-decoration:none;
    font-family:'Plus Jakarta Sans',sans-serif;
    transition:background .15s;
}
.btn-cetak-outline:hover{background:var(--blue-lt); color:var(--navy);}

/* ── BANNER PENAWARAN ────────────────── */
.banner-penawaran {
    display:flex; align-items:center; gap:10px;
    background:#f0fdf4;
    border:1px solid #bbf7d0;
    border-left:4px solid #16a34a;
    border-radius:var(--radius-sm);
    padding:10px 14px;
    margin-top:12px;
    font-size:.78rem;
}
.banner-penawaran .icon-pdf {
    width:34px; height:34px;
    background:#dcfce7; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    color:#16a34a; font-size:1rem; flex-shrink:0;
}
.banner-penawaran .label {
    font-size:.63rem; font-weight:800;
    text-transform:uppercase; letter-spacing:.5px;
    color:#15803d; margin-bottom:2px;
}
.banner-penawaran .filename {
    font-size:.73rem; color:#166534;
    font-family:'JetBrains Mono',monospace;
    word-break:break-all;
}

/* ── EMPTY STATE ─────────────────────── */
.empty-state {
    display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    padding:50px 20px; color:var(--slate); text-align:center;
}
.empty-state i{font-size:2.5rem; opacity:.2; margin-bottom:12px;}

/* ── PRINT STYLES ────────────────────── */
@media print {
    body * { visibility: hidden; }
    #print-area, #print-area * { visibility: visible; }
    #print-area {
        position: fixed; top: 0; left: 0;
        width: 100%; background: #fff;
        padding: 20px; font-size: 11pt;
        font-family: Arial, sans-serif;
    }
    .no-print { display: none !important; }
}

/* ── ANIMATIONS ──────────────────────── */
@keyframes fadeUp{
    from{opacity:0;transform:translateY(8px);}
    to  {opacity:1;transform:translateY(0);}
}
.stat-card:nth-child(1){animation-delay:.04s;}
.stat-card:nth-child(2){animation-delay:.08s;}
.stat-card:nth-child(3){animation-delay:.12s;}
.stat-card:nth-child(4){animation-delay:.16s;}

@media(max-width:991px){.po-list{max-height:260px;}}
</style>
</head>
<body>

<!-- ── TOPBAR ──────────────────────────────────────────────── -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-brand">
            <div class="topbar-icon"><i class="fas fa-landmark"></i></div>
            <div>
                <div class="topbar-title">FINANCE <span>MONITOR</span></div>
                <div class="topbar-sub">Purchase Order & Status Pembelian</div>
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

    <!-- ── STAT CARDS ───────────────────────────────────────── -->
    <div class="stat-grid">
        <div class="stat-card sc-open">
            <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-label">PO Open</div>
            <div class="stat-value"><?= $jml_open ?></div>
            <div class="stat-sub">Purchase Order aktif</div>
        </div>
        <div class="stat-card sc-close">
            <div class="stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="stat-label">PO Close Bulan Ini</div>
            <div class="stat-value"><?= $po_close_bulan ?></div>
            <div class="stat-sub">Selesai <?= $periode ?></div>
        </div>
        <div class="stat-card sc-appr">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-label">PR Besar Menunggu</div>
            <div class="stat-value"><?= $pr_tunggu_appr ?></div>
            <div class="stat-sub">Belum disetujui</div>
        </div>
        <div class="stat-card sc-nilai">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-label">Nilai PO Open</div>
            <div class="stat-value"><?= rpFmt($nilai_open) ?></div>
            <div class="stat-sub">Total outstanding</div>
        </div>
    </div>

    <!-- ── SPLIT: SIDEBAR + DETAIL ──────────────────────────── -->
    <div class="split-wrap">

        <!-- SIDEBAR: Daftar PO -->
        <div class="sidebar-card">
            <div class="sidebar-head">
                <i class="fas fa-list-ul" style="color:var(--teal);"></i>
                Daftar Purchase Order
            </div>

            <div class="po-tabs">
                <button class="po-tab <?= $tab_aktif==='open'  ? 'act-open'  : '' ?>"
                        onclick="goTab('open')">
                    <i class="fas fa-circle me-1" style="font-size:.42rem;color:var(--teal);"></i>
                    OPEN
                    <span style="background:var(--teal-lt);color:var(--teal-dk);border-radius:10px;padding:0 6px;font-size:.62rem;margin-left:3px;"><?= $jml_open ?></span>
                </button>
                <button class="po-tab <?= $tab_aktif==='close' ? 'act-close' : '' ?>"
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
            mysqli_data_seek($result_aktif, 0);

            if ($jml_aktif === 0):
            ?>
                <div class="empty-state" style="padding:30px 16px;">
                    <i class="fas fa-inbox" style="font-size:1.8rem;"></i>
                    <p style="font-size:.78rem;">Tidak ada PO <?= strtoupper($tab_aktif) ?>.</p>
                </div>
            <?php else:
                while ($po_row = mysqli_fetch_assoc($result_aktif)):
                    $is_sel = ($id_po_filter === (int)$po_row['id_po']);
                    $cls    = $is_sel ? ($tab_aktif==='open' ? 'active-open' : 'active-close') : '';
            ?>
                <a class="po-item <?= $cls ?>"
                   href="?tab=<?= $tab_aktif ?>&id_po=<?= $po_row['id_po'] ?>">
                    <div class="d-flex justify-content-between align-items-start gap-1">
                        <div class="po-item-no <?= $tab_aktif==='close' ? 'text-secondary' : 'text-primary' ?>">
                            <?= htmlspecialchars($po_row['no_po']) ?>
                        </div>
                        <?php if ($tab_aktif==='open'): ?>
                        <span class="bdg bdg-open" style="flex-shrink:0;">OPEN</span>
                        <?php else: ?>
                        <span class="bdg bdg-close" style="flex-shrink:0;">CLOSE</span>
                        <?php endif; ?>
                    </div>
                    <div class="po-item-req"><?= htmlspecialchars($po_row['no_request']) ?></div>
                    <div class="po-item-sup"><?= htmlspecialchars($po_row['nama_supplier'] ?? '-') ?></div>
                    <div class="po-item-amt">Rp <?= number_format($po_row['grand_total'],0,',','.') ?></div>
                    <?php if ($tab_aktif==='close' && !empty($po_row['tgl_close'])): ?>
                    <div class="po-item-date">Selesai: <?= date('d/m/Y', strtotime($po_row['tgl_close'])) ?></div>
                    <?php elseif (!empty($po_row['tgl_approve'])): ?>
                    <div class="po-item-date">Approve: <?= date('d/m/Y', strtotime($po_row['tgl_approve'])) ?></div>
                    <?php endif; ?>
                </a>
            <?php endwhile; endif; ?>
            </div>
        </div>

        <!-- DETAIL PANEL -->
        <div class="detail-card" id="print-area">

        <?php if ($po_detail): ?>

        <?php $is_close_view = ($po_detail['status_po'] === 'CLOSE'); ?>
        <?php $is_approved   = in_array($po_detail['status_approval'] ?? '', ['APPROVED','DISETUJUI']); ?>

        <div class="detail-head">
            <div class="detail-head-top">
                <div style="flex:1;">
                    <div class="po-number"><?= htmlspecialchars($po_detail['no_po']) ?></div>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:4px;">
                        <?php if ($is_close_view): ?>
                            <span class="bdg bdg-close"><i class="fas fa-check-double me-1"></i>CLOSE</span>
                        <?php else: ?>
                            <span class="bdg bdg-open"><i class="fas fa-circle me-1" style="font-size:.4rem;"></i>OPEN</span>
                        <?php endif; ?>
                        <?php if (($po_detail['kategori_pr']??'')==='BESAR'): ?>
                            <span class="bdg bdg-besar">BESAR</span>
                        <?php else: ?>
                            <span class="bdg bdg-kecil">KECIL</span>
                        <?php endif; ?>
                        <?php if ($is_approved): ?>
                            <span class="bdg bdg-appr"><i class="fas fa-check-circle me-1"></i>APPROVED</span>
                        <?php else: ?>
                            <span class="bdg bdg-tunggu"><i class="fas fa-hourglass-half me-1"></i>MENUNGGU APPROVAL</span>
                        <?php endif; ?>
                    </div>

                    <!-- Info Approval -->
                    <div class="mt-3 p-2" style="background:#f8f9fa;border-radius:5px;border-left:3px solid #2c3e50;font-size:8.5pt;">
                        <div class="row g-0">
                            <div class="col-4">
                                <i class="fas fa-user-check me-1"></i> <strong>Appr 1:</strong><br>
                                <?= !empty($po_detail['approve1_by']) ? strtoupper($po_detail['approve1_by']) : '<span style="color:#ccc;">-</span>' ?>
                                <div style="font-size:7pt;color:#888;"><?= !empty($po_detail['approve1_at']) ? date('d/m/y H:i', strtotime($po_detail['approve1_at'])) : '' ?></div>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-user-check me-1"></i> <strong>Appr 2:</strong><br>
                                <?= !empty($po_detail['approve2_by']) ? strtoupper($po_detail['approve2_by']) : '<span style="color:#ccc;">-</span>' ?>
                                <div style="font-size:7pt;color:#888;"><?= !empty($po_detail['approve2_at']) ? date('d/m/y H:i', strtotime($po_detail['approve2_at'])) : '' ?></div>
                            </div>
                            <div class="col-4">
                                <i class="fas fa-user-check me-1"></i> <strong>Appr 3:</strong><br>
                                <?= !empty($po_detail['approve3_by']) ? strtoupper($po_detail['approve3_by']) : '<span style="color:#ccc;">-</span>' ?>
                                <div style="font-size:7pt;color:#888;"><?= !empty($po_detail['approve3_at']) ? date('d/m/y H:i', strtotime($po_detail['approve3_at'])) : '' ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="d-flex gap-2 flex-wrap no-print align-items-start">
                    <a href="../transaksi/cetak_pr_besar.php?id=<?= $id_request_sel ?>"
                       target="_blank" class="btn-cetak" style="background-color:#17a2b8;">
                        <i class="fas fa-file-invoice"></i> Cetak PR
                    </a>
                    <a href="../transaksi/cetak_po.php?id_request=<?= $id_request_sel ?>"
                       target="_blank" class="btn-cetak">
                        <i class="fas fa-print"></i> Cetak PO
                    </a>
                    <button onclick="window.print()" class="btn-cetak-outline">
                        <i class="fas fa-file-pdf"></i> Print Halaman
                    </button>
                    <?php if (!empty($po_detail['file_penawaran'])): ?>
                   <!-- <a href="../../download_penawaran.php?file=<?= urlencode($po_detail['file_penawaran']) ?>&id_request=<?= $id_request_sel ?>"
                       target="_blank"
                       class="btn-cetak"
                       style="background-color:#16a34a;"
                       title="Lihat scan penawaran supplier">
                        <i class="fas fa-file-pdf"></i> Penawaran Supplier
                    </a>-->
                    <?php endif; ?>
                </div>
            </div>

            <!-- Banner penawaran (jika ada file) -->
            <?php if (!empty($po_detail['file_penawaran'])): ?>
            <div class="banner-penawaran no-print">
                <div class="icon-pdf"><i class="fas fa-file-pdf"></i></div>
                <div>
                    <div class="label"><i class="fas fa-paperclip me-1"></i>Lampiran Penawaran Supplier</div>
                    <div class="filename"><?= htmlspecialchars($po_detail['file_penawaran']) ?></div>
                </div>
                <a href="../../download_penawaran.php?file=<?= urlencode($po_detail['file_penawaran']) ?>&id_request=<?= $id_request_sel ?>"
                   target="_blank"
                   style="margin-left:auto;display:inline-flex;align-items:center;gap:5px;
                          background:#16a34a;color:#fff;border-radius:6px;
                          padding:6px 13px;font-size:.72rem;font-weight:700;
                          text-decoration:none;white-space:nowrap;flex-shrink:0;">
                    <i class="fas fa-eye"></i> Buka PDF
                </a>
            </div>
            <?php endif; ?>

            <!-- Info grid -->
            <div class="info-grid mb-3 mt-3">
                <div>
                    <div class="info-lbl">No. Request</div>
                    <div class="info-val"><?= htmlspecialchars($po_detail['no_request']) ?></div>
                </div>
                <div>
                    <div class="info-lbl">Supplier</div>
                    <div class="info-val"><?= htmlspecialchars($po_detail['nama_supplier'] ?? '-') ?></div>
                </div>
                <div>
                    <div class="info-lbl">Pemesan</div>
                    <div class="info-val"><?= htmlspecialchars(strtoupper($po_detail['nama_pemesan'])) ?></div>
                </div>
                <div>
                    <div class="info-lbl">Grand Total</div>
                    <div class="info-val danger">Rp <?= number_format((float)$po_detail['grand_total'],0,',','.') ?></div>
                </div>
                <div>
                    <div class="info-lbl">Keperluan</div>
                    <div class="info-val" style="font-weight:500;"><?= htmlspecialchars($po_detail['tujuan'] ?? '-') ?></div>
                </div>
                <?php if (!empty($po_detail['tgl_approve'])): ?>
                <div>
                    <div class="info-lbl">Tgl Approve</div>
                    <div class="info-val"><?= date('d/m/Y', strtotime($po_detail['tgl_approve'])) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($is_close_view && !empty($po_detail['tgl_close'])): ?>
                <div>
                    <div class="info-lbl">Tgl Selesai</div>
                    <div class="info-val" style="color:var(--green);">
                        <i class="fas fa-calendar-check me-1"></i>
                        <?= date('d/m/Y', strtotime($po_detail['tgl_close'])) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Progress bar -->
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="prog-bar-bg">
                    <div class="prog-bar-fill" style="width:<?= $pct_selesai ?>%;"></div>
                </div>
                <span style="font-family:'JetBrains Mono',monospace;font-size:.75rem;font-weight:700;color:var(--teal-dk);white-space:nowrap;">
                    <?= $pct_selesai ?>%
                </span>
            </div>
            <div class="prog-meta">
                <span style="color:var(--teal-dk);font-weight:700;">✓ <?= $item_dibeli ?> terbeli</span>
                <?php if ($item_verif > 0): ?>
                <span style="color:var(--amber);font-weight:700;">⏳ <?= $item_verif ?> verifikasi</span>
                <?php endif; ?>
                <?php if ($item_pending > 0): ?>
                <span style="color:var(--navy-mid);font-weight:700;">📦 <?= $item_pending ?> pending</span>
                <?php endif; ?>
                <span style="color:var(--slate);">Total: <?= $total_item ?> item</span>
            </div>
        </div>

        <!-- Tabel item -->
        <div class="detail-body">
            <table class="item-table">
                <thead>
                    <tr>
                        <th width="4%">#</th>
                        <th>Nama Barang</th>
                        <th width="85">Unit/Plat</th>
                        <th width="70">Qty</th>
                        <th width="130">Status Beli</th>
                        <th width="130">Status Ban</th>
                        <th width="115">Dibeli Oleh</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows_item)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:30px;color:var(--slate);">
                            <i class="fas fa-minus" style="opacity:.3;"></i>
                            Tidak ada item.
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($rows_item as $i => $d):
				$nama          = !empty($d['nama_master']) ? $d['nama_master'] : ($d['nama_barang_manual'] ?? '-');
				$unit          = !empty($d['plat_nomor'])  ? $d['plat_nomor']  : '-';
				$is_ban        = (int)($d['is_ban']    ?? 0);
				
				// Pastikan status ini sesuai dengan data di database Anda
				// Biasanya jika sudah ada data di tabel 'pembelian', maka dianggap sudah terbeli
				$is_terbeli    = !empty($d['tgl_beli_barang']); 
				
				$status_pasang = $d['status_pasang'] ?? null;
				$status_item   = $d['status_item']   ?? '-';
			?>
			<tr class="<?= $is_ban ? 'row-ban' : '' ?>">
				<td style="text-align:center;color:var(--slate);font-size:.73rem;"><?= $i+1 ?></td>
				<td>
					<span style="font-weight:700;"><?= htmlspecialchars(strtoupper($nama)) ?></span>
					<?php if ($is_ban): ?>
					<span style="margin-left:4px;font-size:.6rem;background:#fff3cd;border:1px solid #ffc107;color:#7c4a00;padding:1px 5px;border-radius:4px;font-weight:700;">
						<i class="fas fa-circle me-1" style="font-size:.35rem;"></i>BAN
					</span>
					<?php endif; ?>
				</td>
				<td style="text-align:center;">
					<?php if ($unit !== '-'): ?>
						<span style="background:var(--slate-lt);border:1px solid var(--border);border-radius:5px;padding:2px 7px;font-size:.7rem;font-weight:700;"><?= htmlspecialchars($unit) ?></span>
					<?php else: ?>
						<span style="color:var(--slate);">—</span>
					<?php endif; ?>
				</td>
				<td style="text-align:center;font-weight:800;font-size:.8rem;">
					<?= (float)($d['jumlah']??0)+0 ?>
					<span style="font-size:.65rem;color:var(--slate);font-weight:500;"><?= htmlspecialchars($d['satuan']??'') ?></span>
				</td>
				<td style="text-align:center;">
					<?php if ($is_terbeli): ?>
						<span class="bdg st-dibeli"><i class="fas fa-check me-1"></i>TERBELI</span>
					<?php elseif ($status_item === 'MENUNGGU VERIFIKASI'): ?>
						<span class="bdg st-verif"><i class="fas fa-hourglass-half me-1"></i>VERIFIKASI</span>
					<?php else: ?>
						<span class="bdg st-pending"><i class="fas fa-clock me-1"></i><?= htmlspecialchars($status_item) ?></span>
					<?php endif; ?>
				</td>
				<td style="text-align:center;">
					<?php if ($is_ban): ?>
						<?php if ($status_pasang === 'TERPASANG'): ?>
							<span class="bdg st-terpasang"><i class="fas fa-check me-1"></i>TERPASANG</span>
						<?php else: ?>
							<span class="bdg st-belum-pasang"><i class="fas fa-clock me-1"></i>BELUM PASANG</span>
						<?php endif; ?>
					<?php else: ?>
						<span style="color:var(--slate);">—</span>
					<?php endif; ?>
				</td>
				<td style="text-align:left;">
					<?php if ($is_terbeli): ?>
						<div style="font-weight:700; color:var(--teal-dk); font-size:.75rem;">
							<?= htmlspecialchars($d['nama_petugas_beli'] ?? '-') ?>
						</div>
						<div style="font-size:.65rem; color:var(--slate);">
							<i class="far fa-calendar-alt me-1"></i>
							<?= date('d/m/Y', strtotime($d['tgl_beli_barang'])) ?>
						</div>
					<?php else: ?>
						<span style="color:var(--slate);">—</span>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
            <div class="empty-state" style="min-height:400px;">
                <i class="fas fa-file-invoice-dollar"></i>
                <p><strong>Belum ada PO tersedia</strong></p>
                <p style="font-size:.75rem;">Tidak ada Purchase Order yang ditemukan.</p>
            </div>
        <?php endif; ?>

        </div><!-- /detail-card -->
    </div><!-- /split-wrap -->

</div><!-- /main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function goTab(tab) {
    window.location.href = '?tab=' + tab;
}
</script>
</body>
</html>