<?php
/**
 * pembelian.php — Dashboard Pembelian MCP
 * Fitur baru:
 * - Responsive (mobile/tablet/desktop)
 * - Stat cards: Belum Diproses, Proses, Menunggu Verifikasi, Selesai
 * - Visualisasi kinerja per staf pembeli (bulan ini)
 * - Antrean PR dengan info status lengkap
 */
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';


if ($_SESSION['status'] !== 'login') {
    header('Location: ../../login.php?pesan=belum_login');
    exit;
}

// ── Periode bulan ini ────────────────────────────────────────
$bln_awal  = date('Y-m-01');
$bln_akhir = date('Y-m-t');

// ── Stat cards ───────────────────────────────────────────────
$stat = [];

// PR Belum Diproses (PENDING, tidak ada item staging)
$r = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(*) AS c FROM tr_request
    WHERE status_request = 'PENDING'
"));
$stat['pending'] = (int)$r['c'];

// PR Sedang Diproses (PROSES, masih ada item pending)
$r = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(DISTINCT r.id_request) AS c
    FROM tr_request r
    WHERE r.status_request = 'PROSES'
      AND EXISTS (
          SELECT 1 FROM tr_request_detail d
          WHERE d.id_request = r.id_request
            AND d.status_item NOT IN ('TERBELI','MENUNGGU VERIFIKASI','REJECTED')
      )
"));
$stat['proses'] = (int)$r['c'];

// PR Menunggu Verifikasi (semua item staging, tidak ada pending)
$r = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(DISTINCT r.id_request) AS c
    FROM tr_request r
    WHERE r.status_request = 'PROSES'
      AND NOT EXISTS (
          SELECT 1 FROM tr_request_detail d
          WHERE d.id_request = r.id_request
            AND d.status_item NOT IN ('TERBELI','MENUNGGU VERIFIKASI','REJECTED')
      )
      AND EXISTS (
          SELECT 1 FROM tr_request_detail d2
          WHERE d2.id_request = r.id_request
            AND d2.status_item = 'MENUNGGU VERIFIKASI'
      )
"));
$stat['verifikasi'] = (int)$r['c'];

// PR Selesai bulan ini
$r = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COUNT(*) AS c FROM tr_request
    WHERE status_request = 'SELESAI'
      AND DATE(updated_at) BETWEEN '$bln_awal' AND '$bln_akhir'
"));
$stat['selesai'] = (int)$r['c'];

// Total nilai pembelian bulan ini
$r = mysqli_fetch_assoc(mysqli_query($koneksi, "
    SELECT COALESCE(SUM(qty * harga), 0) AS total
    FROM pembelian
    WHERE tgl_beli BETWEEN '$bln_awal' AND '$bln_akhir'
"));
$stat['nilai_bulan'] = (float)$r['total'];

// ── Data kinerja per staf pembeli (bulan ini) ────────────────
// Kita JOIN ke tabel user untuk memastikan nama sesuai database
// ── Data kinerja per staf pembeli (bulan ini) ────────────────
$q_staf = mysqli_query($koneksi, "
    SELECT 
        u.nama_lengkap AS nama_staf,
        COUNT(p.id_pembelian) AS jml_transaksi,
        SUM(p.qty * p.harga) AS total_nilai,
        COUNT(DISTINCT DATE(p.tgl_beli)) AS hari_aktif
    FROM users u 
    INNER JOIN pembelian p ON u.username = p.driver 
    WHERE p.tgl_beli BETWEEN '$bln_awal' AND '$bln_akhir'
      AND u.role = 'bagian_pembelian' 
    GROUP BY u.username
    ORDER BY total_nilai DESC
    LIMIT 10
");

// Cek jika query error untuk memudahkan debugging
if (!$q_staf) {
    die("Query Error: " . mysqli_error($koneksi));
}

$data_staf = [];
while ($row = mysqli_fetch_assoc($q_staf)) {
    $data_staf[] = $row;
}

// Nilai max untuk bar width
$max_nilai_staf = !empty($data_staf) ? max(array_column($data_staf, 'total_nilai')) : 1;

// ── Kamus barang ─────────────────────────────────────────────
$daftar_master = mysqli_query($koneksi, "
    SELECT nama_barang FROM master_barang
    WHERE status_aktif='AKTIF' ORDER BY nama_barang ASC
");
$kamus_barang = '';
while ($m = mysqli_fetch_array($daftar_master))
    $kamus_barang .= '<option value="'.strtoupper($m['nama_barang']).'">';

// ── Helper rupiah ─────────────────────────────────────────────
function rp(float $n): string {
    if ($n >= 1_000_000_000) return 'Rp '.number_format($n/1_000_000_000,1,',','.').' M';
    if ($n >= 1_000_000)     return 'Rp '.number_format($n/1_000_000,1,',','.').' jt';
    return 'Rp '.number_format($n,0,',','.');
}

$bulan_id = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$periode_label = $bulan_id[(int)date('m')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Pembelian — MCP</title>
    <link rel="icon" type="image/png" href="../../assets/img/logo_mcp.png">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <style>
    /* ═══════════════════════════════════════════
       TOKENS
    ═══════════════════════════════════════════ */
    :root {
        --blue        : #1a56db;
        --blue-dark   : #1e429f;
        --blue-light  : #e8f0fe;
        --amber       : #f59e0b;
        --amber-light : #fef3c7;
        --green       : #059669;
        --green-light : #d1fae5;
        --red         : #dc2626;
        --red-light   : #fee2e2;
        --slate       : #64748b;
        --surface     : #f1f5f9;
        --card        : #ffffff;
        --border      : #e2e8f0;
        --text        : #0f172a;
        --radius      : 14px;
        --radius-sm   : 8px;
        --shadow      : 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
        --shadow-lg   : 0 4px 6px rgba(0,0,0,.07), 0 10px 30px rgba(0,0,0,.1);
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
        background: var(--surface);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.875rem;
        color: var(--text);
        min-height: 100vh;
    }

    input:not([readonly]):not([type=number]),
    textarea { text-transform: uppercase; }

    /* ── Topbar ─────────────────────────────── */
    .topbar {
        background: linear-gradient(135deg, var(--blue-dark) 0%, var(--blue) 100%);
        padding: 0;
        position: sticky;
        top: 0;
        z-index: 1030;
        box-shadow: 0 2px 20px rgba(26,86,219,.35);
    }
    .topbar-inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px;
        gap: 12px;
    }
    .topbar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        color: #fff;
        font-weight: 700;
        font-size: 0.95rem;
        white-space: nowrap;
    }
    .topbar-brand .icon-wrap {
        width: 34px; height: 34px;
        background: rgba(255,255,255,.15);
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .topbar-period {
        background: rgba(255,255,255,.12);
        color: rgba(255,255,255,.85);
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    /* ── Main layout ────────────────────────── */
    .main-wrap { padding: 20px 16px 60px; max-width: 1400px; margin: 0 auto; }
    @media (min-width: 768px)  { .main-wrap { padding: 24px 24px 60px; } }
    @media (min-width: 1200px) { .main-wrap { padding: 28px 32px 60px; } }

    /* ── Section header ─────────────────────── */
    .section-label {
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: var(--slate);
        margin-bottom: 10px;
    }

    /* ── Stat cards ─────────────────────────── */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    @media (min-width: 576px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 992px) { .stat-grid { grid-template-columns: repeat(5, 1fr); } }

    .stat-card {
        background: var(--card);
        border-radius: var(--radius);
        padding: 16px;
        box-shadow: var(--shadow);
        border-top: 3px solid transparent;
        display: flex;
        flex-direction: column;
        gap: 6px;
        transition: transform .15s, box-shadow .15s;
        animation: fadeUp .35s ease both;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
    .stat-card.c-pending    { border-top-color: var(--slate); }
    .stat-card.c-proses     { border-top-color: var(--blue); }
    .stat-card.c-verifikasi { border-top-color: var(--amber); }
    .stat-card.c-selesai    { border-top-color: var(--green); }
    .stat-card.c-nilai      { border-top-color: #8b5cf6; grid-column: span 2; }
    @media (min-width: 992px) { .stat-card.c-nilai { grid-column: span 1; } }

    .stat-icon {
        width: 36px; height: 36px;
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.95rem;
        flex-shrink: 0;
    }
    .stat-card.c-pending    .stat-icon { background: #f1f5f9; color: var(--slate); }
    .stat-card.c-proses     .stat-icon { background: var(--blue-light); color: var(--blue); }
    .stat-card.c-verifikasi .stat-icon { background: var(--amber-light); color: var(--amber); }
    .stat-card.c-selesai    .stat-icon { background: var(--green-light); color: var(--green); }
    .stat-card.c-nilai      .stat-icon { background: #ede9fe; color: #7c3aed; }

    .stat-label { font-size: 0.68rem; font-weight: 600; color: var(--slate); text-transform: uppercase; letter-spacing: .5px; }
    .stat-value { font-size: 1.6rem; font-weight: 800; line-height: 1; font-family: 'DM Mono', monospace; }
    .stat-card.c-pending    .stat-value { color: var(--slate); }
    .stat-card.c-proses     .stat-value { color: var(--blue); }
    .stat-card.c-verifikasi .stat-value { color: var(--amber); }
    .stat-card.c-selesai    .stat-value { color: var(--green); }
    .stat-card.c-nilai      .stat-value { color: #7c3aed; font-size: 1.2rem; }
    .stat-sub { font-size: 0.7rem; color: var(--slate); }

    /* ── Panel ──────────────────────────────── */
    .panel {
        background: var(--card);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
        margin-bottom: 20px;
        animation: fadeUp .4s ease both;
    }
    .panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--border);
        background: #fafbfc;
    }
    .panel-title {
        font-weight: 700;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .panel-body { padding: 0; }
    .panel-body-pad { padding: 18px; }

    /* ── Nav tabs custom ─────────────────────── */
    .tabs-wrap {
        display: flex;
        gap: 4px;
        background: var(--surface);
        border-radius: 10px;
        padding: 4px;
        overflow-x: auto;
        scrollbar-width: none;
        flex-shrink: 0;
    }
    .tabs-wrap::-webkit-scrollbar { display: none; }
    .tab-btn {
        background: none;
        border: none;
        border-radius: 7px;
        padding: 7px 14px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--slate);
        white-space: nowrap;
        cursor: pointer;
        transition: background .15s, color .15s;
        font-family: 'DM Sans', sans-serif;
    }
    .tab-btn.active {
        background: var(--card);
        color: var(--blue);
        box-shadow: 0 1px 4px rgba(0,0,0,.1);
    }
    .tab-pane-custom { display: none; }
    .tab-pane-custom.active { display: block; }

    /* ── Antrean PR table ────────────────────── */
    .pr-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .pr-table th {
        background: #f8fafc;
        padding: 10px 12px;
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: var(--slate);
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }
    .pr-table td {
        padding: 11px 12px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }
    .pr-table tbody tr:last-child td { border-bottom: none; }
    .pr-table tbody tr:hover { background: #f8fafc; }

    /* Badge status PR */
    .pr-status {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 20px;
        padding: 3px 9px;
        font-size: 0.68rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .ps-pending    { background: #f1f5f9; color: var(--slate); }
    .ps-proses     { background: var(--blue-light); color: var(--blue-dark); }
    .ps-verifikasi { background: var(--amber-light); color: #92400e; }
    .ps-approved   { background: #dcfce7; color: #166534; }

    /* Progress mini untuk item per PR */
    .pr-progress-wrap { display: flex; gap: 3px; align-items: center; flex-wrap: nowrap; }
    .pr-progress-bar {
        height: 6px;
        border-radius: 3px;
        flex: 1;
        min-width: 40px;
        background: var(--border);
        overflow: hidden;
        position: relative;
    }
    .pr-progress-bar .fill {
        position: absolute; left: 0; top: 0; height: 100%;
        border-radius: 3px;
        transition: width .4s ease;
    }
    .fill-terbeli    { background: var(--green); }
    .fill-verifikasi { background: var(--amber); }
    .fill-pending    { background: var(--blue); }

    /* Aksi buttons */
    .btn-aksi {
        display: inline-flex; align-items: center; gap: 5px;
        border-radius: var(--radius-sm);
        padding: 5px 10px;
        font-size: 0.72rem;
        font-weight: 700;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        transition: opacity .15s, transform .1s;
        font-family: 'DM Sans', sans-serif;
        text-decoration: none;
    }
    .btn-aksi:hover { opacity: .85; transform: translateY(-1px); }
    .btn-aksi:active { transform: translateY(0); }
    .btn-view  { background: #e0f2fe; color: #0369a1; }
    .btn-print { background: #f1f5f9; color: var(--slate); }
    .btn-beli  { background: var(--blue); color: #fff; box-shadow: 0 2px 8px rgba(26,86,219,.3); }
    .btn-lock-verif { background: var(--amber-light); color: #92400e; cursor: default; }
    .btn-lock-appr  { background: #f1f5f9; color: var(--slate); cursor: default; }

    /* Aksi wrap responsive */
    .aksi-wrap { display: flex; gap: 5px; flex-wrap: wrap; justify-content: flex-end; }

    /* ── Kinerja staf ───────────────────────── */
    .staf-list { display: flex; flex-direction: column; gap: 0; }
    .staf-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 12px 18px;
        border-bottom: 1px solid var(--border);
        animation: fadeUp .3s ease both;
    }
    .staf-row:last-child { border-bottom: none; }
    .staf-name {
        font-weight: 700;
        font-size: 0.83rem;
        margin-bottom: 4px;
        text-transform: uppercase;
    }
    .staf-bar-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .staf-bar-bg {
        flex: 1;
        height: 8px;
        background: var(--border);
        border-radius: 4px;
        overflow: hidden;
        min-width: 60px;
    }
    .staf-bar-fill {
        height: 100%;
        border-radius: 4px;
        background: linear-gradient(90deg, var(--blue-dark), var(--blue));
        transition: width .6s cubic-bezier(.4,0,.2,1);
    }
    .staf-meta { font-size: 0.7rem; color: var(--slate); margin-top: 2px; }
    .staf-nilai {
        font-family: 'DM Mono', monospace;
        font-weight: 700;
        font-size: 0.8rem;
        color: var(--blue-dark);
        text-align: right;
        white-space: nowrap;
    }
    .staf-rank {
        width: 24px; height: 24px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.65rem;
        font-weight: 800;
        flex-shrink: 0;
        margin-right: 4px;
    }
    .rank-1 { background: #fbbf24; color: #78350f; }
    .rank-2 { background: #94a3b8; color: #1e293b; }
    .rank-3 { background: #d97706; color: #fff; }
    .rank-n { background: var(--surface); color: var(--slate); }

    /* ── Empty state ────────────────────────── */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--slate);
    }
    .empty-state i { font-size: 2rem; opacity: .3; margin-bottom: 10px; display: block; }

    /* ── Responsive table scroll ────────────── */
    .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

    /* ── Modal ──────────────────────────────── */
    .modal-xl { max-width: 98%; }
    @media (min-width: 992px) { .modal-body { max-height: 80vh; overflow-y: auto; } }

    /* ── Animations ─────────────────────────── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .stat-card:nth-child(1) { animation-delay: .05s; }
    .stat-card:nth-child(2) { animation-delay: .10s; }
    .stat-card:nth-child(3) { animation-delay: .15s; }
    .stat-card:nth-child(4) { animation-delay: .20s; }
    .stat-card:nth-child(5) { animation-delay: .25s; }

    /* ── Misc ───────────────────────────────── */
    .fw-mono { font-family: 'DM Mono', monospace; }
    .btn-simpan-baris.loading { pointer-events: none; opacity: .7; }
    @keyframes flashGreen { 0%{background:#d1fae5;} 100%{background:transparent;} }
    tr.saved-flash { animation: flashGreen 1.2s ease; }

    /* DataTables override */
    .dataTables_wrapper { font-size: 0.8rem; }
    </style>
</head>
<body>
<datalist id="list_barang_master"><?= $kamus_barang ?></datalist>

<!-- ── Topbar ──────────────────────────────────────────────── -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="topbar-brand">
            <div class="icon-wrap"><i class="fas fa-shopping-cart"></i></div>
            <span>MODUL PEMBELIAN</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="topbar-period"><i class="fas fa-calendar-alt me-1"></i><?= $periode_label ?></span>
            <a href="../../index.php" class="btn-aksi btn-print" style="border-radius:20px;">
                <i class="fas fa-arrow-left"></i><span class="d-none d-sm-inline">Kembali</span>
            </a>
        </div>
    </div>
</header>

<div class="main-wrap">

    <!-- ── Stat Cards ──────────────────────────────────────── -->
    <p class="section-label"><i class="fas fa-chart-bar me-1"></i>Ringkasan Antrean Kerja</p>
    <div class="stat-grid">

        <div class="stat-card c-pending">
            <div class="stat-icon"><i class="fas fa-inbox"></i></div>
            <div class="stat-label">Belum Diproses</div>
            <div class="stat-value"><?= $stat['pending'] ?></div>
            <div class="stat-sub">PR menunggu pembeli</div>
        </div>

        <div class="stat-card c-proses">
            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-label">Sedang Diproses</div>
            <div class="stat-value"><?= $stat['proses'] ?></div>
            <div class="stat-sub">item belum dibeli</div>
        </div>

        <div class="stat-card c-verifikasi">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-label">Menunggu Verifikasi</div>
            <div class="stat-value"><?= $stat['verifikasi'] ?></div>
            <div class="stat-sub">Semua item di staging</div>
        </div>

        <div class="stat-card c-selesai">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-label">Selesai Bulan Ini</div>
            <div class="stat-value"><?= $stat['selesai'] ?></div>
            <div class="stat-sub">PR sudah terbeli semua</div>
        </div>

      <!--  <div class="stat-card c-nilai">
            <div class="stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="stat-label">Nilai Pembelian</div>
            <div class="stat-value"><?= rp($stat['nilai_bulan']) ?></div>
            <div class="stat-sub">Total realisasi <?= $periode_label ?></div>
        </div>-->

    </div>

    <!-- ── Main grid: Antrean + Kinerja ────────────────────── -->
    <div class="row g-3">

        <!-- Antrean PR -->
        <div class="col-12 col-xl-8">
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">
                        <i class="fas fa-clipboard-list text-primary"></i>
                        Antrean Request (PR)
                    </div>
                    <!-- Tab filter -->
                    <div class="tabs-wrap" role="tablist">
                        <button class="tab-btn active" data-target="tab-menunggu">
                            Belum Diproses
                            <?php if($stat['pending']>0): ?>
                            <span style="background:var(--slate);color:#fff;border-radius:10px;padding:1px 6px;font-size:.6rem;margin-left:3px;"><?=$stat['pending']?></span>
                            <?php endif; ?>
                        </button>
                        <button class="tab-btn" data-target="tab-proses">
                            Sedang Proses
                            <?php if($stat['proses']>0): ?>
                            <span style="background:var(--blue);color:#fff;border-radius:10px;padding:1px 6px;font-size:.6rem;margin-left:3px;"><?=$stat['proses']?></span>
                            <?php endif; ?>
                        </button>
                        <button class="tab-btn" data-target="tab-verif">
                            Verifikasi
                            <?php if($stat['verifikasi']>0): ?>
                            <span style="background:var(--amber);color:#000;border-radius:10px;padding:1px 6px;font-size:.6rem;margin-left:3px;"><?=$stat['verifikasi']?></span>
                            <?php endif; ?>
                        </button>
                        <button class="tab-btn" data-target="tab-realisasi">
                            Buku Realisasi
                        </button>
                    </div>
                </div>
                <div class="panel-body">

                    <?php
                    // Query semua PR aktif dengan stat item
                    $q_all_pr = mysqli_query($koneksi, "
                        SELECT
                            r.*,
                            (SELECT COUNT(*) FROM tr_request_detail d
                             WHERE d.id_request=r.id_request AND d.status_item NOT IN ('REJECTED')
                            ) AS item_total,
                            (SELECT COUNT(*) FROM tr_request_detail d
                             WHERE d.id_request=r.id_request AND d.status_item='TERBELI'
                            ) AS item_terbeli,
                            (SELECT COUNT(*) FROM tr_request_detail d
                             WHERE d.id_request=r.id_request AND d.status_item='MENUNGGU VERIFIKASI'
                            ) AS item_verif,
                            (SELECT COUNT(*) FROM tr_request_detail d
                             WHERE d.id_request=r.id_request
                               AND d.status_item NOT IN ('TERBELI','MENUNGGU VERIFIKASI','REJECTED')
                            ) AS item_pending
                        FROM tr_request r
                        WHERE r.status_request IN ('PENDING','PROSES')
                        ORDER BY r.id_request DESC
                    ");

                    // Pisahkan ke bucket
                    $bucket = ['menunggu'=>[], 'proses'=>[], 'verif'=>[]];
                    while ($pr = mysqli_fetch_assoc($q_all_pr)) {
                        $is_besar    = $pr['kategori_pr'] === 'BESAR';
                        $is_approved = in_array($pr['status_approval'],['APPROVED','DISETUJUI']);
                        $pr['boleh_beli'] = !$is_besar || $is_approved;

                        if ($pr['status_request'] === 'PENDING') {
                            $bucket['menunggu'][] = $pr;
                        } elseif ($pr['item_pending'] == 0 && $pr['item_verif'] > 0) {
                            $bucket['verif'][] = $pr;
                        } else {
                            $bucket['proses'][] = $pr;
                        }
                    }

                    // Render fungsi tabel PR
                    function renderPRTable(array $rows, string $emptyMsg): void {
                        if (empty($rows)) {
                            echo '<div class="empty-state"><i class="fas fa-clipboard-check"></i><p>' . $emptyMsg . '</p></div>';
                            return;
                        }
                        echo '<div class="table-scroll">';
                        echo '<table class="pr-table">';
                        echo '<thead><tr>
                            <th>No. PR</th>
                            <th>Tanggal</th>
                            <th class="d-none d-md-table-cell">Pemesan</th>
                            <th class="d-none d-sm-table-cell">Pembeli</th>
                            <th>Progress Item</th>
                            <th class="text-end">Aksi</th>
                        </tr></thead><tbody>';

                        foreach ($rows as $pr) {
                            $is_besar    = $pr['kategori_pr'] === 'BESAR';
                            $is_approved = in_array($pr['status_approval'],['APPROVED','DISETUJUI']);
                            $item_total  = max((int)$pr['item_total'], 1);
                            $pct_terbeli = round($pr['item_terbeli'] / $item_total * 100);
                            $pct_verif   = round($pr['item_verif']   / $item_total * 100);
                            $pct_pending = round($pr['item_pending']  / $item_total * 100);

                            $pembeli = !empty($pr['nama_pembeli']) ? strtoupper($pr['nama_pembeli']) : '-';

                            // Tentukan badge status row
                            if ($pr['status_request'] === 'PENDING') {
                                $status_badge = '<span class="pr-status ps-pending"><i class="fas fa-inbox"></i> PENDING</span>';
                            } elseif ($pr['item_pending'] == 0 && $pr['item_verif'] > 0) {
                                $status_badge = '<span class="pr-status ps-verifikasi"><i class="fas fa-hourglass-half"></i> VERIFIKASI</span>';
                            } else {
                                $status_badge = '<span class="pr-status ps-proses"><i class="fas fa-shopping-bag"></i> PROSES</span>';
                            }

                            $kat_badge = $is_besar
                                ? '<span class="pr-status" style="background:#fee2e2;color:#991b1b;">BESAR</span>'
                                : '<span class="pr-status" style="background:#dbeafe;color:#1e3a8a;">KECIL</span>';

                            echo '<tr>';
                            echo '<td>
                                <div class="fw-bold text-primary" style="font-size:.8rem;">' . htmlspecialchars($pr['no_request']) . '</div>
                                <div style="margin-top:3px;display:flex;gap:4px;flex-wrap:wrap;">' . $status_badge . $kat_badge . '</div>
                            </td>';
                            echo '<td class="fw-mono" style="font-size:.75rem;color:var(--slate);">' . date('d/m/Y', strtotime($pr['tgl_request'])) . '</td>';
                            echo '<td class="d-none d-md-table-cell" style="font-weight:600;">' . htmlspecialchars(strtoupper($pr['nama_pemesan'])) . '</td>';
                            echo '<td class="d-none d-sm-table-cell">';
                            if ($pembeli !== '-') {
                                echo '<span style="background:var(--blue-light);color:var(--blue-dark);border-radius:6px;padding:2px 8px;font-size:.72rem;font-weight:700;">' . $pembeli . '</span>';
                            } else {
                                echo '<span style="color:var(--slate);font-size:.75rem;">—</span>';
                            }
                            echo '</td>';

                            // Progress bar
                            echo '<td style="min-width:120px;">
                                <div class="pr-progress-wrap">
                                    <div class="pr-progress-bar">
                                        <div class="fill fill-terbeli" style="width:'.$pct_terbeli.'%"></div>
                                    </div>
                                </div>
                                <div style="font-size:.65rem;color:var(--slate);margin-top:3px;display:flex;gap:6px;flex-wrap:wrap;">
                                    <span style="color:var(--green);">✓ '.$pr['item_terbeli'].' terbeli</span>';
                            if ($pr['item_verif'] > 0)   echo '<span style="color:var(--amber);">⏳ '.$pr['item_verif'].' verifikasi</span>';
                            if ($pr['item_pending'] > 0) echo '<span style="color:var(--blue);">📦 '.$pr['item_pending'].' pending</span>';
                            echo '  </div>
                            </td>';

                            // Aksi
                          // Aksi
                                echo '<td><div class="aksi-wrap">';
                                echo '<button onclick="viewPR('.$pr['id_request'].')" class="btn-aksi btn-view" title="Detail"><i class="fas fa-eye"></i></button>';
                                echo '<a href="../transaksi/cetak_pr.php?id='.$pr['id_request'].'" target="_blank" class="btn-aksi btn-print" title="Cetak PR"><i class="fas fa-print"></i></a>';

                                // --- TAMBAHAN TOMBOL CETAK PO ---
                                if ($is_besar && $is_approved) {
                                    // Ambil ID PO agar link akurat (opsional, jika tidak ada cukup pakai id_request)
                                    echo '<a href="../transaksi/cetak_po.php?id_request='.$pr['id_request'].'" target="_blank" class="btn-aksi" style="background:#4f46e5;color:#fff;" title="Cetak Purchase Order (PO)">
                                            <i class="fas fa-file-invoice"></i>
                                        </a>';
                                }
                                // --------------------------------

                                $harus_lock = ($pr['item_pending'] == 0 && $pr['item_verif'] > 0);
                                if ($harus_lock) {
                                    echo '<button class="btn-aksi btn-lock-verif" disabled title="Semua item menunggu verifikasi"><i class="fas fa-hourglass-half"></i><span class="d-none d-md-inline">Verifikasi</span></button>';
                                } elseif (!$pr['boleh_beli']) {
                                    echo '<button class="btn-aksi btn-lock-appr" disabled title="Menunggu approval manager"><i class="fas fa-lock"></i><span class="d-none d-md-inline">Approval</span></button>';
                                } else {
                                    echo '<button onclick="prosesBeli('.$pr['id_request'].')" class="btn-aksi btn-beli"><i class="fas fa-shopping-cart"></i><span class="d-none d-md-inline">Beli</span>';
                                    if ($pr['item_verif'] > 0 && $pr['item_pending'] > 0)
                                        echo '<span style="background:rgba(255,255,255,.25);border-radius:10px;padding:0 5px;font-size:.62rem;">'.$pr['item_pending'].' sisa</span>';
                                    echo '</button>';
                                }
                                echo '</div></td>';
                            echo '</tr>';
                        }

                        echo '</tbody></table></div>';
                    }
                    ?>

                    <!-- Tab: Belum Diproses -->
                    <div class="tab-pane-custom active" id="tab-menunggu">
                        <?php renderPRTable($bucket['menunggu'], 'Tidak ada PR yang menunggu diproses.'); ?>
                    </div>

                    <!-- Tab: Sedang Proses -->
                    <div class="tab-pane-custom" id="tab-proses">
                        <?php renderPRTable($bucket['proses'], 'Tidak ada PR yang sedang diproses.'); ?>
                    </div>

                    <!-- Tab: Menunggu Verifikasi -->
                    <div class="tab-pane-custom" id="tab-verif">
                        <?php renderPRTable($bucket['verif'], 'Tidak ada PR yang menunggu verifikasi.'); ?>
                    </div>

                    <!-- Tab: Buku Realisasi -->
                    <div class="tab-pane-custom" id="tab-realisasi">
                        <div class="panel-body-pad" style="padding:16px;">
                            <table id="tabelRealisasi" class="table table-hover table-bordered w-100" style="font-size:0.75rem;">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Tgl Beli</th><th>No. PR</th><th>Supplier</th>
                                        <th>Nama Barang</th><th>Qty</th><th>Harga</th>
                                        <th>Total</th><th>Alokasi</th><th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php
                                $q = mysqli_query($koneksi, "SELECT * FROM pembelian ORDER BY id_pembelian DESC LIMIT 1000");
                                while ($d = mysqli_fetch_array($q)):
                                    $total  = $d['qty'] * $d['harga'];
                                    $al_cls = $d['alokasi_stok'] === 'MASUK STOK' ? 'bg-info' : 'bg-secondary';
                                ?>
                                <tr>
                                    <td><?= date('d/m/y', strtotime($d['tgl_beli'])) ?></td>
                                    <td><?= $d['no_request'] ?? '-' ?></td>
                                    <td><?= htmlspecialchars($d['supplier']) ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($d['nama_barang_beli']) ?></td>
                                    <td class="text-center"><?= (float)$d['qty'] ?></td>
                                    <td class="text-end"><?= number_format($d['harga']) ?></td>
                                    <td class="text-end fw-bold"><?= number_format($total) ?></td>
                                    <td><span class="badge <?= $al_cls ?>"><?= $d['alokasi_stok'] ?></span></td>
                                    <td><?= htmlspecialchars($d['keterangan']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Kinerja Staf Pembeli -->
       <div class="col-12 col-xl-4">
    <div class="panel">
        <div class="panel-head">
            <div class="panel-title">
                <i class="fas fa-trophy text-warning"></i>
                Kinerja Pembeli (<?= $periode_label ?>)
            </div>
        </div>
        <div class="panel-body">
            <div class="staf-list">
                <?php if (empty($data_staf)): ?>
                    <div class="empty-state">
                        <i class="fas fa-user-clock"></i>
                        <p>Belum ada data aktivitas bulan ini.</p>
                    </div>
                <?php else: 
                    foreach ($data_staf as $index => $s): 
                        $rank_class = ($index == 0) ? 'rank-1' : (($index == 1) ? 'rank-2' : (($index == 2) ? 'rank-3' : 'rank-n'));
                        $persen_bar = ($s['total_nilai'] / $max_nilai_staf) * 100;
                        
                        // Avatar gratis dari internet (UI Faces / DiceBear)
                        $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($s['nama_staf']) . "&background=random&color=fff";
                ?>
                    <div class="staf-row">
                        <div class="d-flex align-items-center gap-3 w-100">
                            <div class="staf-rank <?= $rank_class ?>"><?= $index + 1 ?></div>
                            
                            <img src="<?= $avatar_url ?>" alt="Avatar" style="width:40px; height:40px; border-radius:50%; border:2px solid var(--border);">
                            
                            <div class="flex-grow-1">
                                <div class="staf-name" style="font-size: 0.8rem;"><?= htmlspecialchars($s['nama_staf']) ?></div>
                                <div class="staf-bar-wrap">
                                    <div class="staf-bar-bg" style="height: 10px;">
                                        <div class="staf-bar-fill" style="width: <?= $persen_bar ?>%; background: linear-gradient(90deg, #1e429f, #1a56db);"></div>
                                    </div>
                                </div>
                                <div class="staf-meta" style="margin-top: 5px;">
                                    <span class="badge bg-light text-dark border">
                                        <i class="fas fa-shopping-cart me-1"></i> <?= $s['jml_transaksi'] ?> Transaksi
                                    </span>
                                    <span class="badge bg-light text-dark border ms-1">
                                        <i class="fas fa-calendar-check me-1"></i> <?= $s['hari_aktif'] ?> Hari
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        </div>
</div>

    </div><!-- /row -->
</div><!-- /main-wrap -->

<!-- ══════════════════════════════════════════════════════════
     MODAL REALISASI PEMBELIAN
══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalTambah" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
            <div class="modal-header py-2" style="background:var(--blue);">
                <h5 class="modal-title text-white fw-bold small">
                    <i class="fas fa-shopping-bag me-2"></i>FORM REALISASI PEMBELIAN
                    <span id="labelNoPR" class="ms-2 badge bg-light text-primary"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3 bg-light p-2 rounded small">
                    <div class="col-md-4">
                        <label class="fw-bold text-muted small">USER PEMESAN</label>
                        <input type="text" id="info_nama_pemesan" class="form-control form-control-sm bg-white" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="fw-bold text-primary small">STAF PEMBELI <span class="text-danger">*</span></label>
                        <input type="text" id="input_nama_pembeli"
                               class="form-control form-control-sm border-primary fw-bold"
                               placeholder="NAMA STAF"
                               onkeyup="this.value=this.value.toUpperCase()">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="alert alert-info mb-0 py-1 px-2 small w-100">
                            <i class="fas fa-info-circle me-1"></i>
                            Alokasi otomatis dari tipe PR. Simpan setiap baris.
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle" id="tabelBeli">
                        <thead class="table-dark text-center" style="font-size:.7rem;">
                            <tr>
                                <th>TGL NOTA</th><th>NAMA BARANG</th><th>UNIT/MOBIL</th>
                                <th>TOKO/SUPPLIER</th><th>QTY</th><th>HARGA</th>
                                <th>KAT PR</th><th>ALOKASI</th><th>KAT BARANG</th>
                                <th>SUBTOTAL</th><th>KETERANGAN</th><th>AKSI</th>
                            </tr>
                        </thead>
                        <tbody id="containerBarang">
                            <tr><td colspan="12" class="text-center text-muted py-4">
                                <i class="fas fa-arrow-up me-1"></i>Pilih PR terlebih dahulu
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between bg-light">
                <div>
                    <span class="text-muted small me-2">Total sesi ini:</span>
                    <strong class="text-primary fs-5" id="grandTotalDisplay">Rp 0</strong>
                </div>
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>TUTUP
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL VIEW PR -->
<div class="modal fade" id="modalView" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-search me-2"></i>DETAIL PURCHASE REQUEST</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light" id="kontenView"></div>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="toastNotif" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-bold" id="toastMsg">OK</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<!-- SCRIPTS -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Tab switching ────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane-custom').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(this.dataset.target)?.classList.add('active');

        // Init DataTables saat tab realisasi dibuka
        if (this.dataset.target === 'tab-realisasi' && !$.fn.DataTable.isDataTable('#tabelRealisasi')) {
            $('#tabelRealisasi').DataTable({
                order   : [[0,'desc']],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' }
            });
        }
    });
});

// ── Global ───────────────────────────────────────────────────
let currentIdRequest = 0;
let grandTotal       = 0;
const toastEl        = document.getElementById('toastNotif');

function showToast(msg, type = 'success') {
    const map = { success: 'bg-success', error: 'bg-danger', warning: 'bg-warning text-dark' };
    toastEl.className = 'toast align-items-center text-white border-0 ' + (map[type] ?? 'bg-success');
    document.getElementById('toastMsg').innerText = msg;
    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 }).show();
}

function rupiah(n) {
    return new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', maximumFractionDigits:0 }).format(n);
}

// ── View PR ──────────────────────────────────────────────────
function viewPR(id) {
    $('#kontenView').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#modalView').modal('show');
    $.get('ajax_view_pr.php', { id }).done(res => $('#kontenView').html(res))
     .fail(() => $('#kontenView').html('<div class="alert alert-danger">Gagal memuat detail PR.</div>'));
}

// ── Buka modal beli ──────────────────────────────────────────
function prosesBeli(id) {
    currentIdRequest = id;
    grandTotal = 0;
    $('#containerBarang').html('<tr><td colspan="12" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Memuat...</td></tr>');
    $('#info_nama_pemesan, #input_nama_pembeli').val('');
    $('#grandTotalDisplay').text('Rp 0');
    $('#labelNoPR').text('');
    $('#modalTambah').modal('show');

    $.get('get_pr_data.php', { id }, function(res) {
        if (!res) return;
        if (res.no_request)   $('#labelNoPR').text(res.no_request);
        if (res.nama_pemesan) $('#info_nama_pemesan').val(res.nama_pemesan);
        if (res.nama_pembeli) $('#input_nama_pembeli').val(res.nama_pembeli);
    }, 'json').fail(() => {});

    $.ajax({
        url: 'get_pr_detail.php', type: 'GET', data: { id },
        success: html => { $('#containerBarang').html(html); initDatepicker(); },
        error  : ()   => $('#containerBarang').html('<tr><td colspan="12" class="text-center text-danger">Gagal memuat detail PR.</td></tr>')
    });
}

function initDatepicker() {
    $('.b-tanggal').datepicker({ dateFormat:'dd-mm-yy', changeMonth:true, changeYear:true });
}

// ── Hitung subtotal live ─────────────────────────────────────
$(document).on('input', '.b-qty, .b-harga', function () {
    const $tr = $(this).closest('tr');
    const sub = (parseFloat($tr.find('.b-qty').val())||0) * (parseFloat($tr.find('.b-harga').val())||0);
    $tr.find('.b-total').val(sub.toLocaleString('id-ID'));
});

// ── Simpan per baris ─────────────────────────────────────────
$(document).on('click', '.btn-simpan-baris', function () {
    const $btn = $(this), $tr = $btn.closest('tr');

    const id_detail       = $tr.find('.f-id-detail').val();
    const id_request_v    = $tr.find('.f-id-request').val() || currentIdRequest;
    const id_barang       = $tr.find('.f-id-barang').val();
    const nama_pemesan    = $tr.find('.f-nama-pemesan').val()    || $('#info_nama_pemesan').val();
    const kategori_pr     = $tr.find('.f-kategori-pr').val()     || 'KECIL';
    const kategori_barang = $tr.find('.f-kategori-barang').val() || '-';
    const alokasi_info    = $tr.find('.f-alokasi-otomatis').val() || '-';
    const nama_pembeli    = $('#input_nama_pembeli').val().trim();
    const tgl_nota        = $tr.find('.b-tanggal').val();
    const nama_barang     = $tr.find('input[list]').val() || '';
    const id_mobil        = $tr.find('.b-id-mobil').val();
    const supplier        = $tr.find('.b-supplier').val().trim();
    const qty             = $tr.find('.b-qty').val();
    const harga           = $tr.find('.b-harga').val();
    const keterangan      = $tr.find('.b-keterangan').val();

    if (!nama_pembeli)          { showToast('Isi nama staf pembeli!','warning'); $('#input_nama_pembeli').focus(); return; }
    if (!supplier)              { showToast('Nama toko/supplier wajib diisi!','warning'); $tr.find('.b-supplier').focus(); return; }
    if (parseFloat(qty) <= 0)   { showToast('Qty harus lebih dari 0!','warning'); $tr.find('.b-qty').focus(); return; }
    if (parseFloat(harga) <= 0) { showToast('Harga harus diisi!','warning'); $tr.find('.b-harga').focus(); return; }
    if (!keterangan)            { showToast('Keterangan wajib diisi!','warning'); $tr.find('.b-keterangan').focus(); return; }

    const sub_fmt = (parseFloat(qty)*parseFloat(harga)).toLocaleString('id-ID');
    if (!confirm('Simpan item ini?\n\n'+nama_barang+'\n'+qty+' × Rp '+sub_fmt+'\nDari: '+supplier+'\nAlokasi: '+alokasi_info)) return;

    $btn.addClass('loading').html('<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...');

    $.ajax({
        url: 'proses_simpan_baris.php', type: 'POST', dataType: 'json',
        data: { id_detail, id_request:id_request_v, id_barang, id_mobil,
                nama_pemesan, nama_pembeli, tgl_nota, nama_barang, supplier,
                qty, harga, keterangan, kategori_pr, kategori_barang },
        success: function(res) {
            if (res.status === 'ok') {
                showToast('✅ ' + (res.message||'Berhasil!'), 'success');
                grandTotal += (res.subtotal||0);
                $('#grandTotalDisplay').text(rupiah(grandTotal));
                $tr.addClass('saved-flash');
                buatBarisTerbeli($tr, {
                    tgl_nota, nama_barang,
                    plat_nomor      : res.plat_nomor      || '-',
                    supplier, qty, harga,
                    kategori_pr     : res.kategori_beli   || kategori_pr,
                    alokasi         : res.alokasi,
                    kategori_barang : res.kategori_barang || kategori_barang,
                    subtotal_fmt    : res.subtotal_fmt,
                    keterangan,
                });

                const sisaPending = $('#containerBarang .btn-simpan-baris').length;
                if (res.pr_selesai) {
                    setTimeout(() => {
                        showToast('🎉 Semua item terbeli! PR SELESAI.','success');
                        hapusBarisPR(currentIdRequest);
                    }, 1000);
                } else if (sisaPending === 0 && res.semua_diinput) {
                    setTimeout(() => {
                        showToast('⏳ Semua item diinput. Menunggu verifikasi.','warning');
                        lockTombolPR(currentIdRequest, res.item_menunggu ?? 0);
                    }, 800);
                }
            } else {
                showToast('❌ '+(res.message||'Gagal.'),'error');
                $btn.removeClass('loading').html('<i class="fas fa-save me-1"></i>Simpan');
            }
        },
        error: function(xhr) {
            showToast('❌ Kesalahan server.','error');
            console.error(xhr.responseText);
            $btn.removeClass('loading').html('<i class="fas fa-save me-1"></i>Simpan');
        }
    });
});

function buatBarisTerbeli($tr, d) {
    const alokasiClass = d.alokasi === 'MASUK STOK' ? 'bg-info text-dark' : 'bg-secondary';
    const katPRClass   = d.kategori_pr === 'BESAR'  ? 'bg-danger'         : 'bg-success';
    const platBadge    = (d.plat_nomor && d.plat_nomor !== '-')
        ? `<span class="badge bg-primary small">${d.plat_nomor}</span>`
        : '<span class="text-muted small">-</span>';

    $tr.removeClass('baris-beli').addClass('table-success opacity-75').html(`
        <td class="text-center small">${d.tgl_nota}</td>
        <td><strong>${d.nama_barang}</strong></td>
        <td class="text-center">${platBadge}</td>
        <td class="small">${d.supplier}</td>
        <td class="text-center">${parseFloat(d.qty)}</td>
        <td class="text-end small">${Number(d.harga).toLocaleString('id-ID')}</td>
        <td class="text-center"><span class="badge ${katPRClass} small">${d.kategori_pr}</span></td>
        <td class="text-center"><span class="badge ${alokasiClass} small">${d.alokasi}</span></td>
        <td class="text-center small text-muted">${d.kategori_barang}</td>
        <td class="text-end fw-bold text-success small">${d.subtotal_fmt}</td>
        <td class="text-muted small">${d.keterangan}</td>
        <td class="text-center"><span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i>MENUNGGU VERIFIKASI</span></td>
    `);
}

function hapusBarisPR(idRequest) {
    // Hapus baris dari semua tab
    document.querySelectorAll(`[onclick="prosesBeli(${idRequest})"]`).forEach(btn => {
        btn.closest('tr')?.remove();
    });
}

function lockTombolPR(idRequest, jumlahMenunggu) {
    document.querySelectorAll(`[onclick="prosesBeli(${idRequest})"]`).forEach(btn => {
        btn.outerHTML = `<button class="btn-aksi btn-lock-verif" disabled
            title="Semua item diinput. Menunggu verifikasi (${jumlahMenunggu} item).">
            <i class="fas fa-hourglass-half"></i>
            <span class="d-none d-md-inline">Verifikasi</span>
        </button>`;
    });
}
</script>
<script>
    let idleTime = 0;
    const maxIdleMinutes = 15; // Samakan dengan server
    let lastServerUpdate = Date.now();
    let sessionValid = true;

    // Fungsi reset timer saat ada gerakan
    function resetTimer() {
        idleTime = 0;
        let now = Date.now();

        // Kirim sinyal ke server setiap 5 menit agar session PHP tidak expired
        if (now - lastServerUpdate > 300000) {
            fetch('/pr_mcp_rev4/auth/keep_alive.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        sessionValid = false;
                        forceLogout();
                    }
                })
                .catch(err => {
                    console.error("Koneksi ke server terputus");
                });
            lastServerUpdate = now;
        }
    }

    // Fungsi paksa logout
    function forceLogout() {
        alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
        // Redirect ke logout.php agar session server juga dihancurkan
        window.location.href = "/pr_mcp_rev4/auth/logout.php?pesan=timeout";
    }

    // Pantau aktivitas user
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    // Cek status idle setiap 1 menit
    setInterval(function() {
        idleTime++;
        // Cek session ke server juga
        fetch('/pr_mcp_rev4/auth/keep_alive.php')
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    sessionValid = false;
                    forceLogout();
                }
            })
            .catch(err => {
                // Jika error koneksi, biarkan user tetap di halaman
            });
        if (idleTime >= maxIdleMinutes && sessionValid) {
            forceLogout();
        }
    }, 60000);
</script>
</body>
</html>