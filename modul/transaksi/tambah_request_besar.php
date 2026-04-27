<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// AMBIL DARI $_SESSION['nama'] (sesuai yang di-set di cek_login.php)
$nama_user_login = isset($_SESSION['nama']) ? $_SESSION['nama'] : (isset($_SESSION['username']) ? $_SESSION['username'] : "USER");

// Ubah ke Huruf Besar
$nama_user_login_display = strtoupper($nama_user_login);

// Ambil semua master SEKALI ke array (fix Windows Server)
$list_barang = [];
$res = mysqli_query($koneksi, "SELECT * FROM master_barang WHERE status_aktif='AKTIF' AND is_active=1 ORDER BY nama_barang ASC");
while ($b = mysqli_fetch_assoc($res)) { $list_barang[] = $b; }
mysqli_free_result($res);

$list_mobil = [];
$res = mysqli_query($koneksi, "SELECT id_mobil, plat_nomor FROM master_mobil WHERE status_aktif='AKTIF' ORDER BY plat_nomor ASC");
while ($m = mysqli_fetch_assoc($res)) { $list_mobil[] = $m; }
mysqli_free_result($res);

$list_pembeli = [];
$res = mysqli_query($koneksi, "SELECT nama_lengkap FROM users WHERE status_aktif='AKTIF' AND (role='bagian_pembelian' OR bagian='Pembelian') ORDER BY nama_lengkap ASC");
while ($u = mysqli_fetch_assoc($res)) { $list_pembeli[] = strtoupper($u['nama_lengkap']); }
mysqli_free_result($res);

$list_supplier = [];
$res = mysqli_query($koneksi, "SELECT * FROM master_supplier WHERE status_aktif='AKTIF' ORDER BY nama_supplier ASC");
while ($s = mysqli_fetch_assoc($res)) { $list_supplier[] = $s; }
mysqli_free_result($res);

// Build opsi HTML sekali
$html_opsi_barang = '<option value="">-- Pilih Barang --</option>';
foreach ($list_barang as $b) {
    $html_opsi_barang .= '<option value="'.$b['id_barang'].'"'
        .' data-nama="'.htmlspecialchars(strtoupper($b['nama_barang']), ENT_QUOTES).'"'
        .' data-satuan="'.htmlspecialchars(strtoupper($b['satuan'] ?? ''), ENT_QUOTES).'"'
        .' data-merk="'.htmlspecialchars(strtoupper($b['merk'] ?? ''), ENT_QUOTES).'"'
        .' data-kategori="'.htmlspecialchars(strtoupper($b['kategori'] ?? ''), ENT_QUOTES).'"'
        .' data-harga="'.($b['harga_barang_stok'] ?? 0).'">'
        .htmlspecialchars(strtoupper($b['nama_barang'])).'</option>';
}

$html_opsi_mobil = '<option value="0">NON MOBIL</option>';
foreach ($list_mobil as $m) {
    $html_opsi_mobil .= '<option value="'.$m['id_mobil'].'">'.htmlspecialchars($m['plat_nomor']).'</option>';
}

$sats = ["PCS","UNIT","SET","PEKERJAAN","ACARA","DUS","KG","ONS","LITER","METER","SET","UNIT","LEMBAR","BATANG","ROLL","PACK","DRUM","SAK","PAIL","GALON","BOTOL","TUBE","LONJOR","KOTAK","IKAT","JURIGEN"];
$html_opsi_satuan = '<option value="">- Pilih -</option>';
foreach ($sats as $s) { $html_opsi_satuan .= '<option value="'.$s.'">'.$s.'</option>'; }

$html_opsi_kategori = '<option value="">- Pilih -</option>'
    .'<optgroup label="BENGKEL">'
    .'<option value="BENGKEL MOBIL">BENGKEL MOBIL</option>'
    .'<option value="BENGKEL LISTRIK">BENGKEL LISTRIK</option>'
    .'<option value="BENGKEL DINAMO">BENGKEL DINAMO</option>'
    .'<option value="BENGKEL BUBUT">BENGKEL BUBUT</option>'
    .'<option value="MESIN">MESIN</option>'
    .'<option value="LAS">LAS</option>'
    .'</optgroup>'
    .'<optgroup label="UMUM">'
    .'<option value="KANTOR">KANTOR</option>'
    .'<option value="BANGUNAN">BANGUNAN</option>'
    .'<option value="UMUM">UMUM</option>'
    .'</optgroup>'
    .'<optgroup label="INVESTASI">'
    .'<option value="INVESTASI MESIN">INVESTASI MESIN</option>'
    .'<option value="INVESTASI KENDARAAN">INVESTASI KENDARAAN</option>'
    .'<option value="INVESTASI IT">INVESTASI IT</option>'
    .'<option value="INVESTASI LAINNYA">INVESTASI LAINNYA</option>'
    .'</optgroup>';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Form PR Barang Besar - MCP System</title>
<link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ═══════════════════════════════════════════
   ROOT & BASE
═══════════════════════════════════════════ */
:root {
    --red:      #dc3545;
    --red-dark: #b02a37;
    --blue:     #1e3a8a;
    --bg:       #f4f6f9;
    --radius:   12px;
}
*, *::before, *::after { box-sizing: border-box; }
body { background: var(--bg); font-size: .85rem; -webkit-text-size-adjust: 100%; }

/* ═══════════════════════════════════════════
   HEADER
═══════════════════════════════════════════ */
.page-header {
    background: linear-gradient(135deg, var(--red-dark), var(--blue));
    color: white;
    border-radius: var(--radius) var(--radius) 0 0;
    padding: 16px 20px;
}
.page-header h5 { font-size: .95rem; margin: 0; }
.page-header small { font-size: .72rem; opacity: .85; }

/* ═══════════════════════════════════════════
   ALERT ALUR
═══════════════════════════════════════════ */
.info-alert {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: .78rem;
}
.alur-badge-wrap {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 5px;
    margin-top: 4px;
}
.alur-arrow { color: #adb5bd; }

/* ═══════════════════════════════════════════
   LABEL SECTION
═══════════════════════════════════════════ */
.section-label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    color: #6c757d;
    letter-spacing: .5px;
    margin-bottom: 4px;
}

/* ═══════════════════════════════════════════
   TABEL ITEM — scroll horizontal di semua ukuran
═══════════════════════════════════════════ */
.table-scroll-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    /* Hint scroll di mobile */
    background:
        linear-gradient(to right,  white 30%, rgba(255,255,255,0)),
        linear-gradient(to left,   white 30%, rgba(255,255,255,0)) 100% 0,
        radial-gradient(farthest-side at 0 50%,   rgba(0,0,0,.12), rgba(0,0,0,0)),
        radial-gradient(farthest-side at 100% 50%, rgba(0,0,0,.12), rgba(0,0,0,0)) 100% 0;
    background-repeat: no-repeat;
    background-color: white;
    background-size: 40px 100%, 40px 100%, 14px 100%, 14px 100%;
    background-attachment: local, local, scroll, scroll;
}
/* Badge scroll hint — tampil hanya di layar kecil */
.scroll-hint {
    display: none;
    font-size: .7rem;
    color: #6c757d;
    text-align: right;
    margin-bottom: 4px;
}
@media (max-width: 991.98px) {
    .scroll-hint { display: block; }
}

.table-input {
    min-width: 1600px;
    table-layout: fixed;
    margin-bottom: 0;
}
.table-input thead { background: var(--red); color: white; font-size: .7rem; text-transform: uppercase; }
.table-input thead th { white-space: nowrap; padding: 8px 6px; }
.table-input tbody td { padding: 5px 5px; vertical-align: middle; }

/* Lebar kolom */
.col-no   { width: 38px; }
.col-brg  { width: 190px; }
.col-kat  { width: 140px; }
.col-kwal { width: 150px; }
.col-mbl  { width: 120px; }
.col-tip  { width: 90px; }
.col-qty  { width: 68px; }
.col-sat  { width: 95px; }
.col-hrg  { width: 125px; }
.col-tot  { width: 125px; }
.col-ket  { width: 220px; }
.col-ban  { width: 60px; }
.col-aks  { width: 44px; }

input, select, textarea {
    text-transform: uppercase;
    font-size: .8rem !important;
}
.bg-autonumber {
    background: #e9ecef;
    border-style: dashed;
    color: #00008B;
    font-weight: 700;
}
.select2-container--bootstrap-5 .select2-selection {
    min-height: 31px !important;
    padding: 2px 5px !important;
}
textarea.input-keterangan {
    resize: vertical;
    min-height: 34px;
}
textarea.input-keterangan:focus { min-height: 70px; transition: .2s; }
.row-number { color: #aaa; font-size: .72rem; }

/* ═══════════════════════════════════════════
   TOTAL BOX
═══════════════════════════════════════════ */
.total-box {
    background: white;
    border-radius: 10px;
    border: 1px solid #dee2e6;
    padding: 14px 16px;
}
.total-box .grand-label { font-size: .72rem; color: #6c757d; text-transform: uppercase; font-weight: 700; }
.total-box .grand-value { font-size: 1.3rem; font-weight: 800; color: var(--red); }

/* ═══════════════════════════════════════════
   PO SECTION
═══════════════════════════════════════════ */
.po-section {
    background: #f0f4ff;
    border: 1px solid #c7d2fe;
    border-radius: var(--radius);
    padding: 20px;
    margin-top: 8px;
}
.po-section-title {
    font-size: .9rem;
    font-weight: 700;
    color: #1e3a8a;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.po-section-title .divider {
    width: 4px;
    height: 22px;
    background: #3b82f6;
    border-radius: 2px;
    flex-shrink: 0;
}
.grand-po-box {
    background: var(--blue);
    color: white;
    border-radius: 10px;
    padding: 14px 18px;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 70px;
}
.grand-po-box .label  { font-size: .68rem; opacity: .8; text-transform: uppercase; letter-spacing: .5px; }
.grand-po-box .value  { font-size: 1.3rem; font-weight: 800; }

/* ═══════════════════════════════════════════
   CARD FOOTER
═══════════════════════════════════════════ */
.card-footer {
    background: white;
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 var(--radius) var(--radius);
    padding: 14px 20px;
}

/* ═══════════════════════════════════════════
   CHECKBOX BAN
═══════════════════════════════════════════ */
.ban-check-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
}
.ban-check-wrap input[type=checkbox] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #dc3545;
}
.ban-badge {
    display: inline-block;
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #7c4a00;
    font-size: .62rem;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 4px;
    margin-top: 2px;
    text-transform: uppercase;
}

/* ═══════════════════════════════════════════
   RESPONSIVE — TABLET (≤768px)
═══════════════════════════════════════════ */
@media (max-width: 767.98px) {

    .po-section { 
        padding: 15px; 
        margin-top: 15px;
    }
    
    /* Berikan jarak antar kolom input di PO Section agar tidak menumpuk */
    .po-section .row > div {
        margin-bottom: 10px;
    }

    body { font-size: .82rem; }

    /* Padding lebih rapat di mobile */
    .card-body { padding: 14px !important; }
    .po-section { padding: 14px; }
    .card-footer { padding: 12px 14px; }

    /* Header lebih kompak */
    .page-header { padding: 12px 14px; border-radius: 10px 10px 0 0; }
    .page-header h5 { font-size: .88rem; }
    .page-header .btn { font-size: .75rem; padding: 4px 10px; }

    /* Alur badge bisa wrap lebih bebas */
    .alur-badge-wrap { gap: 4px; font-size: .7rem; }

    /* Input lebih besar agar mudah disentuh */
    .form-control, .form-select, .form-control-sm, .form-select-sm {
        min-height: 38px !important;
        font-size: .82rem !important;
    }

    /* Grand total full width di mobile */
   .total-box {
    background: white;
    border-radius: 10px;
    border: 1px solid #dee2e6;
    padding: 14px 16px;
    margin-bottom: 1.5rem; /* Tambahkan jarak bawah agar tidak menutupi elemen lain */
}
    .total-box .grand-value { font-size: 1.15rem; }
    .grand-po-box {
    background: var(--blue);
    color: white;
    border-radius: 10px;
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 80px;
    margin-bottom: 1rem; /* Jarak aman untuk layar mobile */
}

    /* Footer tombol stack */
    .card-footer {
        flex-direction: column;
        gap: 10px;
        align-items: stretch !important;
    }
    .card-footer .text-muted { font-size: .7rem; text-align: center; }
    .card-footer .btn {
        width: 100%;
        justify-content: center;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .card-footer > div:last-child {
        display: flex;
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }

    /* PO section grand total full width */
    .grand-po-box { margin-top: 8px; }
}

/* ═══════════════════════════════════════════
   RESPONSIVE — MOBILE KECIL (≤480px)
═══════════════════════════════════════════ */
@media (max-width: 479.98px) {
    .container-fluid { padding-left: 10px !important; padding-right: 10px !important; }
    body { padding-top: 10px !important; padding-bottom: 10px !important; }
    .info-alert { font-size: .72rem; padding: 8px 10px; }
    .page-header h5 { font-size: .82rem; }
    .page-header small { display: none; } /* Sembunyikan subtitle di HP kecil */
}
</style>
</head>
<body class="py-3 py-md-4">
<div class="container-fluid px-2 px-md-4">
<form action="proses_simpan_besar.php" method="POST" id="formPRBesar" enctype="multipart/form-data">
<div class="card shadow-sm border-0" style="border-radius:var(--radius);">

<!-- ══ HEADER ════════════════════════════════════ -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start align-items-sm-center gap-2">
        <div>
            <h5><i class="fas fa-boxes-stacked me-2"></i>PURCHASE REQUEST — BARANG BESAR / INVESTASI</h5>
            <small class="opacity-75">Pengajuan ini memerlukan persetujuan minimal 2 Manager sebelum PO diterbitkan</small>
        </div>
            <!-- TAMBAHKAN INI -->
             <div class="flex-shrink-0 fw-bold gap-1 fw-bold">
                <a href="../../ganti_password.php" class="btn btn-warning btn-sm ">
                    <i class="fas fa-key me-1"></i> GANTI PASSWORD
                </a>
                <a href="pr.php" class="btn btn-sm btn-danger ">
                     <i class="fas fa-rotate-left me-1"></i><span class="d-none d-sm-inline">KEMBALI</span>
                </a>
             </div>
            
            
    </div>
    
</div>

<div class="card-body p-3 p-md-4">
  
<!-- ══ ALUR ════════════════════════════════════ -->
<div class="info-alert mb-3 mb-md-4">
    <i class="fas fa-info-circle text-warning me-2"></i><strong>Alur:</strong>
    <div class="alur-badge-wrap mt-1">
        <span>Isi PR</span>
        <span class="alur-arrow">→</span><span class="badge bg-secondary">PENDING</span>
        <span class="alur-arrow">→</span><i class="fas fa-user-tie"></i><span>Mgr 1</span>
        <span class="alur-arrow">→</span><span class="badge bg-warning text-dark">APPROVED 1</span>
        <span class="alur-arrow">→</span><i class="fas fa-user-tie"></i><span>Mgr 2</span>
        <span class="alur-arrow">→</span><span class="badge bg-info text-dark">APPROVED 2*</span>
        <span class="alur-arrow">→</span><span class="badge bg-success">APPROVED</span>
        <span class="alur-arrow">→</span><span class="badge bg-primary">PO OPEN</span>
    </div>
    <div class="mt-1 text-muted" style="font-size:.72rem;">* Manager ke-3 opsional, ditentukan Manager ke-1</div>
</div>

<!-- ══ HEADER FORM ════════════════════════════════════ -->
<div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-2">
        <div class="section-label">No. Request</div>
        <input type="text" class="form-control form-control-sm bg-autonumber" value="[ AUTO ]" readonly>
    </div>
    <div class="col-6 col-md-2">
        <div class="section-label">Tanggal</div>
        <input type="date" name="tgl_request" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="col-12 col-md-3">
        <div class="section-label">Dibuat Oleh</div>
        <input type="text" name="nama_pemesan" class="form-control form-control-sm bg-light" value="<?= $nama_user_login_display ?>" readonly required>
    </div>
    <div class="col-12 col-md-5">
        <div class="section-label">Petugas Pembelian <span class="text-danger">*</span></div>
        <select name="nama_pembeli" class="form-select form-select-sm select-pembeli" required>
            <option value="">-- Pilih Petugas Pembelian --</option>
            <?php foreach ($list_pembeli as $val_u): ?>
                <option value="<?= $val_u ?>"><?= $val_u ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="row g-2 g-md-3 mb-3 mb-md-4">
    <div class="col-12">
        <div class="section-label">Keperluan / Tujuan Pembelian <span class="text-danger">*</span></div>
        <textarea name="keterangan" class="form-control form-control-sm" rows="2"
                  placeholder="Jelaskan tujuan dan keperluan pengajuan barang besar ini..." required></textarea>
    </div>
</div>

<hr class="my-2 my-md-3">

<!-- ══ TABEL ITEM ════════════════════════════════════ -->
<div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
    <div>
        <span class="fw-bold text-danger" style="font-size:.85rem;">
            <i class="fas fa-list me-1"></i> Daftar Item Barang
        </span>
        <div class="text-muted mt-1" style="font-size:.72rem;">
            <i class="fas fa-circle text-warning me-1" style="font-size:.55rem;"></i>
            Centang kolom <strong>BAN</strong> jika item adalah ban kendaraan
        </div>
    </div>
    <button type="button" id="addRow" class="btn btn-sm btn-success fw-bold">
        <i class="fas fa-plus me-1"></i> Tambah Baris
    </button>
</div>

<!-- Hint scroll di mobile -->
<div class="scroll-hint">
    <i class="fas fa-arrows-left-right me-1"></i> Geser kanan-kiri untuk melihat semua kolom
</div>

<div class="table-scroll-wrap mb-3 mb-md-4">
<table class="table table-bordered table-input align-middle mb-0" id="tableItem">
<thead>
    <tr class="text-center">
        <th class="col-no">#</th>
        <th class="col-brg">Nama Barang</th>
        <th class="col-kat">Kategori</th>
        <th class="col-kwal">Kwalifikasi / Merk</th>
        <th class="col-mbl">Unit / Mobil</th>
        <th class="col-tip">Tipe</th>
        <th class="col-qty">Qty</th>
        <th class="col-sat">Satuan</th>
        <th class="col-hrg">Harga Est. (Rp)</th>
        <th class="col-tot">Total (Rp)</th>
        <th class="col-ket">Catatan Detail</th>
        <th class="col-ban" title="Centang jika item adalah BAN kendaraan">
            <i class="fas fa-circle text-warning me-1" style="font-size:.55rem;"></i>BAN?
        </th>
        <th class="col-aks"></th>
    </tr>
</thead>
<tbody id="tbodyItem">
<tr class="item-row">
    <td class="text-center row-number">1</td>
    <td>
        <select name="id_barang[]" class="form-select form-select-sm select-barang" required>
            <?= $html_opsi_barang ?>
        </select>
        <input type="hidden" name="nama_barang_manual[]" class="input-nama-barang" value="">
    </td>
    <td><input type="text" name="kategori_request[]" class="form-control form-control-sm input-kategori-readonly bg-light" placeholder="Otomatis..." readonly></td>
    <td><input type="text" name="kwalifikasi[]" class="form-control form-control-sm input-kwalifikasi" placeholder="Merk / Spesifikasi..." readonly></td>
    <td><select name="id_mobil[]" class="form-select form-select-sm select-mobil"><?= $html_opsi_mobil ?></select></td>
    <td>
        <select name="tipe_request[]" class="form-select form-select-sm select-tipe">
            <option value="LANGSUNG" selected>LANGSUNG</option>
            <option value="STOK">STOK</option>
        </select>
    </td>
    <td><input type="number" name="jumlah[]" class="form-control form-control-sm input-qty text-center" step="0.01" min="0.01" value="1" required></td>
    <td><input type="text" name="satuan[]" 
           class="form-control form-control-sm input-satuan-readonly bg-light" 
           placeholder="Otomatis..." readonly>
	</td>
    <td><input type="number" name="harga[]" class="form-control form-control-sm input-harga text-end" placeholder="0" min="0" step="1"></td>
    <td><input type="text" class="form-control form-control-sm input-subtotal text-end bg-light fw-bold" value="0" readonly tabindex="-1"></td>
    <td><textarea name="keterangan_item[]" class="form-control form-control-sm input-keterangan" rows="1" placeholder="Spesifikasi mendalam..."></textarea></td>
    <td>
        <div class="ban-check-wrap">
            <div class="text-center">
                <input type="checkbox" name="is_ban[]" value="1" class="chk-ban" title="Centang jika item ini adalah BAN kendaraan">
                <div class="ban-badge d-none ban-label">BAN</div>
            </div>
        </div>
        <input type="hidden" name="is_ban_val[]" class="input-is-ban-val" value="0">
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger remove-row border-0">
            <i class="fas fa-times"></i>
        </button>
    </td>
</tr>
</tbody>
</table>
</div>

<!-- ══ TOTAL ════════════════════════════════════ -->
<div class="row justify-content-end mb-3 mb-md-4">
    <div class="col-12 col-sm-8 col-md-5 col-lg-4">
        <div class="total-box">
            <div class="grand-label">Total Estimasi Item</div>
            <div class="grand-value" id="grandTotalDisplay">Rp 0</div>
            <input type="hidden" id="grandTotalValue" name="grand_total" value="0">
        </div>
    </div>
</div>

<hr class="my-2 my-md-3">

<!-- ══ DATA PO ════════════════════════════════════ -->
<div class="po-section">
    <div class="po-section-title">
        <div class="divider"></div>
        <i class="fas fa-file-invoice text-primary"></i>
        Data Purchase Order (PO)
        <span class="badge bg-primary fw-normal ms-1" style="font-size:.68rem;text-transform:none;">
            Dilihat Manager saat review
        </span>
    </div>
    <div class="po-section">
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-5">
            <div class="section-label">Supplier / Vendor</div>
            <select name="id_supplier" id="selectSupplier" class="form-select form-select-sm select-supplier">
                <option value="">-- Pilih Supplier --</option>
                <?php foreach ($list_supplier as $s): ?>
                    <option value="<?= $s['id_supplier'] ?>"
                        data-alamat="<?= htmlspecialchars($s['alamat']    ?? '', ENT_QUOTES) ?>"
                        data-kota="<?=   htmlspecialchars($s['kota']      ?? '', ENT_QUOTES) ?>"
                        data-telp="<?=   htmlspecialchars($s['telp']      ?? '', ENT_QUOTES) ?>"
                        data-cp="<?=     htmlspecialchars($s['atas_nama'] ?? '', ENT_QUOTES) ?>">
                        <?= htmlspecialchars(strtoupper($s['nama_supplier'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <!--<small>
                <a href="master_supplier.php" target="_blank" class="text-decoration-none text-muted">
                    <i class="fas fa-plus-circle me-1"></i>Tambah supplier baru
                </a>
            </small>-->
        </div>
        <div class="col-6 col-md-3">
            <div class="section-label">Tanggal PO <span class="text-danger">*</span></div>
            <input type="date" name="tgl_po" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-6 col-md-4" id="infoSupplierBox" style="display:none;">
            <div class="section-label">Info Supplier</div>
            <div class="p-2 bg-white border rounded" style="font-size:.75rem;line-height:1.9;">
                <i class="fas fa-map-marker-alt text-danger me-1"></i><span id="info_alamat_pr">-</span><br>
                <i class="fas fa-phone text-primary me-1"></i><span id="info_telp_pr">-</span><br>
                <i class="fas fa-user text-success me-1"></i>U/P: <span id="info_cp_pr">-</span>
            </div>
        </div>
    </div>

    <div class="row g-3  mb-3">
        <div class="col-6 col-md-3">
            <div class="section-label">Diskon (Rp)</div>
            <input type="number" name="diskon" id="inputDiskon" class="form-control form-control-sm" value="0" min="0">
        </div>
        <div class="col-6 col-md-3">
            <div class="section-label">PPN</div>
            <select name="ppn_persen" id="selectPPN" class="form-select form-select-sm">
                <option value="0">Tanpa PPN</option>
                <option value="11" selected>PPN 11%</option>
                <option value="12">PPN 12%</option>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <div class="section-label">Grand Total PO (Estimasi)</div>
            <div class="grand-po-box">
                <div class="label">Grand Total PO</div>
                <div class="value" id="displayGrandTotalPO">Rp 0</div>
                <input type="hidden" name="grand_total_po" id="hiddenGrandTotalPO" value="0">
            </div>
        </div>
    </div>

    <div>
        <div class="section-label">Catatan / Ketentuan Pembayaran PO</div>
        <textarea name="catatan_po" class="form-control form-control-sm" rows="3"
                  placeholder="Contoh: Pembayaran AN. PT. XYZ, No Rek: 1234, Bank BCA Cab. Surabaya..."></textarea>
                  <div class="mt-3">
                <div class="section-label">
                    Lampiran Penawaran Supplier 
                    <span class="text-muted fw-normal">(Opsional — PDF, maks. 5MB)</span>
                </div>
                <input type="file" 
                    name="file_penawaran" 
                    id="filePenawaran"
                    class="form-control form-control-sm" 
                    accept=".pdf"
                    style="text-transform:none;">
                <small class="text-muted">
                     <i class="fas fa-file-pdf text-danger me-1"></i>
                    Format: PDF saja. Contoh: scan penawaran harga, quotation, dll.
                </small>
            </div>
    </div>
</div>
</div>
</div><!-- end card-body -->

<!-- ══ FOOTER ════════════════════════════════════ -->
<div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="text-muted" style="font-size:.75rem;">
        <i class="fas fa-shield-alt text-warning me-1"></i>
        Status awal: <strong>PENDING</strong> — menunggu minimal <strong>2 Manager</strong>.
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="pr.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-times me-1"></i> Batal
        </a>
        <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">
            <i class="fas fa-paper-plane me-1"></i> Kirim untuk Approval
        </button>
    </div>
</div>

</div><!-- end card -->
</form>
</div><!-- end container -->

<script>
var OPSI_BARANG   = <?= json_encode($html_opsi_barang) ?>;
var OPSI_MOBIL    = <?= json_encode($html_opsi_mobil) ?>;
var OPSI_SATUAN   = <?= json_encode($html_opsi_satuan) ?>;
var OPSI_KATEGORI = <?= json_encode($html_opsi_kategori) ?>;
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function(){

		function initSelect2(ctx){
		var $c = ctx ? $(ctx) : $(document);
		// Pastikan hanya elemen select yang benar-benar butuh pencarian yang ada di sini
		$c.find('.select-barang,.select-mobil,.select-tipe,.select-pembeli,.select-supplier').each(function(){
			if(!$(this).hasClass('select2-hidden-accessible')){
				$(this).select2({ theme:'bootstrap-5', width:'100%' });
			}
		});
	}
    initSelect2();

    function rp(n){ return 'Rp ' + parseFloat(n||0).toLocaleString('id-ID'); }

    function hitungSubtotal(row){
        var qty = parseFloat(row.find('.input-qty').val())   || 0;
        var hrg = parseFloat(row.find('.input-harga').val()) || 0;
        row.find('.input-subtotal').val((qty * hrg).toLocaleString('id-ID'));
        hitungTotal();
    }

    function hitungTotal(){
        var total = 0;
        $('.input-harga').each(function(){
            var row = $(this).closest('tr');
            total += (parseFloat(row.find('.input-qty').val()) || 0) * (parseFloat($(this).val()) || 0);
        });
        $('#grandTotalDisplay').text(rp(total));
        $('#grandTotalValue').val(total);
        var diskon = parseFloat($('#inputDiskon').val()) || 0;
        var ppn    = parseFloat($('#selectPPN').val())   || 0;
        var t      = total - diskon;
        var grand  = t + (t * ppn / 100);
        $('#displayGrandTotalPO').text(rp(grand));
        $('#hiddenGrandTotalPO').val(grand);
    }

    function updateNo(){
        $('#tbodyItem tr.item-row').each(function(i){
            $(this).find('.row-number').text(i + 1);
        });
    }

    // ── Checkbox BAN ────────────────────────────────────────
    $(document).on('change', '.chk-ban', function(){
        var row     = $(this).closest('tr');
        var checked = $(this).is(':checked');
        row.find('.input-is-ban-val').val(checked ? '1' : '0');
        row.find('.ban-label').toggleClass('d-none', !checked);
        if(checked){
            var mobil = row.find('.select-mobil').val();
            if(!mobil || mobil == '0'){
                Swal.fire({
                    icon: 'info', title: 'Perhatian',
                    text: 'Item ini ditandai sebagai BAN. Pastikan kolom Unit/Mobil diisi dengan plat nomor kendaraan.',
                    confirmButtonColor: '#dc3545', timer: 4000
                });
                row.find('.select-mobil').next('.select2-container').find('.select2-selection')
                   .css('border','2px solid #ffc107');
            }
        } else {
            row.find('.select-mobil').next('.select2-container').find('.select2-selection').css('border','');
        }
    });

   // ── Pilih barang → auto-fill ──────────────────────────
		$(document).on('change', '.select-barang', function(){
		var row = $(this).closest('tr'), 
			sel = $(this).find(':selected');
		
		// Ambil data dari atribut data- master_barang
		var nama     = sel.data('nama') || '';
		var merk     = sel.data('merk') || '';
		var kategori = sel.data('kategori') || '';
		var satuan   = sel.data('satuan') || '';
		var harga    = sel.data('harga') || 0;

		// Masukkan ke input masing-masing
		row.find('.input-nama-barang').val(nama);
		row.find('.input-kategori-readonly').val(kategori);
		row.find('.input-kwalifikasi').val(merk);
		row.find('.input-satuan-readonly').val(satuan); // Isi Satuan Otomatis
		row.find('.input-harga').val(harga);

		hitungSubtotal(row);
	});

	// Event input untuk Qty dan Harga
	$(document).on('input', '.input-qty,.input-harga', function(){
		hitungSubtotal($(this).closest('tr'));
	});

	// Update Total saat Diskon atau PPN berubah
	$('#inputDiskon,#selectPPN').on('input change', function(){ 
		hitungTotal(); 
	});

    // ── Info Supplier ─────────────────────────────────────
    $('#selectSupplier').on('change', function(){
        var sel = $(this).find(':selected');
        if($(this).val()){
            $('#info_alamat_pr').text((sel.data('alamat')||'-') + (sel.data('kota') ? ', '+sel.data('kota') : ''));
            $('#info_telp_pr').text(sel.data('telp') || '-');
            $('#info_cp_pr').text(sel.data('cp') || '-');
            $('#infoSupplierBox').show();
        } else {
            $('#infoSupplierBox').hide();
        }
    });

  // ── Tambah baris ─────────────────────────────────────
		$('#addRow').on('click', function(){
		var n = $('#tbodyItem tr.item-row').length + 1;
		var r = $('<tr class="item-row"></tr>');
		r.append('<td class="text-center row-number">' + n + '</td>');
		r.append('<td><select name="id_barang[]" class="form-select form-select-sm select-barang" required>' + OPSI_BARANG + '</select><input type="hidden" name="nama_barang_manual[]" class="input-nama-barang" value=""></td>');
		
		// Kategori Readonly
		r.append('<td><input type="text" name="kategori_request[]" class="form-control form-control-sm input-kategori-readonly bg-light" placeholder="Otomatis..." readonly></td>');
		
		// Merk Readonly
		r.append('<td><input type="text" name="kwalifikasi[]" class="form-control form-control-sm input-kwalifikasi bg-light" placeholder="Otomatis..." readonly></td>');
		
		r.append('<td><select name="id_mobil[]" class="form-select form-select-sm select-mobil">' + OPSI_MOBIL + '</select></td>');
		r.append('<td><select name="tipe_request[]" class="form-select form-select-sm select-tipe"><option value="LANGSUNG" selected>LANGSUNG</option><option value="STOK">STOK</option></select></td>');
		r.append('<td><input type="number" name="jumlah[]" class="form-control form-control-sm input-qty text-center" step="0.01" min="0.01" value="1" required></td>');
		
		// SATUAN SEKARANG JADI INPUT READONLY
		r.append('<td><input type="text" name="satuan[]" class="form-control form-control-sm input-satuan-readonly bg-light" placeholder="Otomatis..." readonly></td>');
		
		r.append('<td><input type="number" name="harga[]" class="form-control form-control-sm input-harga text-end" placeholder="0" min="0" step="1"></td>');
		r.append('<td><input type="text" class="form-control form-control-sm input-subtotal text-end bg-light fw-bold" value="0" readonly tabindex="-1"></td>');
		r.append('<td><textarea name="keterangan_item[]" class="form-control form-control-sm input-keterangan" rows="1" placeholder="Spesifikasi mendalam..."></textarea></td>');
		r.append('<td><div class="ban-check-wrap"><div class="text-center"><input type="checkbox" name="is_ban[]" value="1" class="chk-ban"><div class="ban-badge d-none ban-label">BAN</div></div></div><input type="hidden" name="is_ban_val[]" class="input-is-ban-val" value="0"></td>');
		r.append('<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row border-0"><i class="fas fa-times"></i></button></td>');
		
		$('#tbodyItem').append(r);
		initSelect2(r);
		updateNo();
	});

    // ── Hapus baris ──────────────────────────────────────
    $(document).on('click', '.remove-row', function(){
        if($('.item-row').length > 1){
            $(this).closest('tr').remove();
            hitungTotal(); updateNo();
        } else {
            Swal.fire('Perhatian', 'Minimal harus ada 1 item barang.', 'warning');
        }
    });

    // ── Submit ───────────────────────────────────────────
    $('#formPRBesar').on('submit', function(e){
        e.preventDefault();
        var form = this, valid = true, hasBan = false;

        $('.select-barang').each(function(){
            if(!$(this).val()){ valid = false; return false; }
        });
        if(!valid){
            Swal.fire('Perhatian', 'Pastikan semua baris sudah memilih nama barang.', 'warning');
            return;
        }

        var banError = false;
        $('.chk-ban:checked').each(function(){
            hasBan = true;
            var row   = $(this).closest('tr');
            var mobil = row.find('.select-mobil').val();
            if(!mobil || mobil == '0'){ banError = true; return false; }
        });
        if(banError){
            Swal.fire('Perhatian', 'Item yang ditandai BAN harus memiliki plat nomor kendaraan.', 'warning');
            return;
        }

        var totalPO  = $('#displayGrandTotalPO').text();
        var banInfo  = hasBan ? '<br><small class="text-warning"><i class="fas fa-circle me-1"></i>Terdapat item BAN.</small>' : '';
        var poInfo   = $('#selectSupplier').val() ? '<br><small class="text-muted">Grand Total PO: <strong class="text-danger">' + totalPO + '</strong></small>' : '<br><small class="text-info"><i class="fas fa-info-circle me-1"></i>Data PO belum diisi (opsional).</small>';
        Swal.fire({
            title: 'Kirim PR untuk Approval?',
            html:  'PR akan dikirim ke minimal <strong>2 Manager</strong>.'
                 + poInfo
                 + banInfo,
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Ya, Kirim!',
            cancelButtonText: 'Batal'
        }).then(function(r){
            if(r.isConfirmed){
                Swal.fire({
                    title: 'Memproses...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: function(){ Swal.showLoading(); }
                });
                form.submit();
            }
        });
    });

    // ── Notif setelah redirect ────────────────────────────
    var pesan = new URLSearchParams(window.location.search).get('pesan');
    if(pesan === 'berhasil_kirim'){
        Swal.fire({ icon:'success', title:'PR Berhasil Dikirim!', text:'Menunggu persetujuan Manager.', confirmButtonColor:'#dc3545' });
    } else if(pesan === 'gagal'){
        Swal.fire({ icon:'error', title:'Gagal!', text:'Terjadi kesalahan saat menyimpan data.', confirmButtonColor:'#dc3545' });
    }
    window.history.replaceState({}, document.title, window.location.pathname);
});
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
            fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
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
        window.location.href = "http://192.168.31.200/pr_mcp/auth/logout.php?pesan=timeout";
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
        fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
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
	// Pastikan data yang 'disabled' tetap terkirim ke PHP
	$('#formPRBesar').on('submit', function() {
		$('.select-kategori').prop('disabled', false);
	});
</script>
</body>
</html>