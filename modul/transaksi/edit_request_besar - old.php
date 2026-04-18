<?php
// ============================================================
// edit_request_besar.php
// Edit PR Besar yang berstatus DITOLAK → revisi → kirim ulang
// Semua approval direset dari awal (M1 & M2 approve ulang)
// ============================================================
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$id              = (int)($_GET['id'] ?? 0);
$username_login  = $_SESSION['username'] ?? '';
$nama_user_login = strtoupper($_SESSION['username'] ?? 'USER');

if (!$id) { header("location:pr.php"); exit; }

// Ambil data PR — hanya boleh diedit jika DITOLAK dan milik pembuat sendiri
$pr = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tr_request WHERE id_request='$id' AND kategori_pr='BESAR'"));

if (!$pr) { header("location:pr.php?pesan=tidak_ditemukan"); exit; }

// Validasi: hanya yang membuat PR atau admin yang boleh edit
$boleh_edit = ($pr['created_by'] === $username_login)
    || in_array($_SESSION['role'] ?? '', ['admin','superadmin']);

if (!$boleh_edit) { header("location:pr.php?pesan=akses_ditolak"); exit; }

if ($pr['status_approval'] !== 'DITOLAK') {
    header("location:pr.php?pesan=tidak_bisa_edit");
    exit;
}

// Ambil detail item existing
$details_raw = [];
$res_det = mysqli_query($koneksi,
    "SELECT d.*, b.nama_barang as nama_master, m.plat_nomor
     FROM tr_request_detail d
     LEFT JOIN master_barang b ON d.id_barang = b.id_barang
     LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
     WHERE d.id_request = '$id'
     ORDER BY d.id_detail ASC");
while ($row = mysqli_fetch_assoc($res_det)) { $details_raw[] = $row; }

// Ambil data PO existing
$po = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tr_purchase_order WHERE id_request='$id' LIMIT 1"));

// Master data
$list_barang = [];
$res = mysqli_query($koneksi, "SELECT * FROM master_barang WHERE status_aktif='AKTIF' AND is_active=1 ORDER BY nama_barang ASC");
while ($b = mysqli_fetch_assoc($res)) { $list_barang[] = $b; }

$list_mobil = [];
$res = mysqli_query($koneksi, "SELECT id_mobil, plat_nomor FROM master_mobil WHERE status_aktif='AKTIF' ORDER BY plat_nomor ASC");
while ($m = mysqli_fetch_assoc($res)) { $list_mobil[] = $m; }

$list_pembeli = [];
$res = mysqli_query($koneksi, "SELECT nama_lengkap FROM users WHERE status_aktif='AKTIF' AND (role='bagian_pembelian' OR bagian='Pembelian') ORDER BY nama_lengkap ASC");
while ($u = mysqli_fetch_assoc($res)) { $list_pembeli[] = strtoupper($u['nama_lengkap']); }

$list_supplier = [];
$res = mysqli_query($koneksi, "SELECT * FROM master_supplier WHERE status_aktif='AKTIF' ORDER BY nama_supplier ASC");
while ($s = mysqli_fetch_assoc($res)) { $list_supplier[] = $s; }

// Build opsi HTML
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Revisi PR <?= htmlspecialchars($pr['no_request']) ?> - MCP System</title>
<link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root{--red:#dc3545;--red-dark:#b02a37;--bg:#f4f6f9;}
body{background:var(--bg);font-size:.85rem;}
.page-header{background:linear-gradient(135deg,#7c3aed,#6d28d9);color:white;border-radius:12px 12px 0 0;padding:18px 24px;}
.page-header h5{font-size:1rem;margin:0;}
.tolak-box{background:#fee2e2;border-left:4px solid #dc3545;border-radius:6px;padding:12px 16px;font-size:.82rem;}
.revisi-box{background:#fef9c3;border-left:4px solid #eab308;border-radius:6px;padding:10px 14px;font-size:.8rem;}
.section-label{font-size:.7rem;font-weight:700;text-transform:uppercase;color:#6c757d;letter-spacing:.5px;margin-bottom:4px;}
.table-input thead{background:#6d28d9;color:white;font-size:.72rem;text-transform:uppercase;}
.table-responsive{border-radius:8px;overflow-x:auto;}
.table-input{min-width:1700px;table-layout:fixed;}
.col-no{width:40px;}.col-brg{width:200px;}.col-kat{width:140px;}.col-kwal{width:150px;}
.col-mbl{width:120px;}.col-tip{width:90px;}.col-qty{width:70px;}.col-sat{width:100px;}
.col-hrg{width:130px;}.col-tot{width:130px;}.col-ket{width:240px;}
.col-ban{width:80px;}.col-aks{width:50px;}
input,select,textarea{text-transform:uppercase;font-size:.8rem!important;}
.bg-autonumber{background:#e9ecef;border-style:dashed;color:#00008B;font-weight:700;}
.select2-container--bootstrap-5 .select2-selection{min-height:31px!important;padding:2px 5px!important;}
textarea.input-keterangan{resize:vertical;min-height:34px;}
textarea.input-keterangan:focus{min-height:70px;transition:.2s;}
.total-box{background:white;border-radius:10px;border:1px solid #dee2e6;padding:16px;}
.total-box .grand-label{font-size:.75rem;color:#6c757d;text-transform:uppercase;font-weight:700;}
.total-box .grand-value{font-size:1.4rem;font-weight:800;color:#6d28d9;}
.po-section{background:#f5f3ff;border:1px solid #ddd6fe;border-radius:12px;padding:22px 24px;margin-top:8px;}
.po-section-title{font-size:.92rem;font-weight:700;color:#4c1d95;display:flex;align-items:center;gap:8px;margin-bottom:18px;}
.po-section-title .divider{width:4px;height:22px;background:#7c3aed;border-radius:2px;flex-shrink:0;}
.grand-po-box{background:#4c1d95;color:white;border-radius:10px;padding:14px 18px;height:100%;display:flex;flex-direction:column;justify-content:center;}
.grand-po-box .label{font-size:.7rem;opacity:.8;text-transform:uppercase;letter-spacing:.5px;}
.grand-po-box .value{font-size:1.4rem;font-weight:800;}
.card-footer{background:white;border-top:1px solid #dee2e6;border-radius:0 0 12px 12px;}
.row-number{color:#aaa;font-size:.75rem;}
.ban-check-wrap{display:flex;justify-content:center;align-items:center;height:100%;}
.ban-check-wrap input[type=checkbox]{width:18px;height:18px;cursor:pointer;accent-color:#dc3545;}
.ban-badge{display:inline-block;background:#fff3cd;border:1px solid #ffc107;color:#7c4a00;font-size:.65rem;font-weight:700;padding:2px 6px;border-radius:4px;margin-top:2px;}
</style>
</head>
<body class="py-4">
<div class="container-fluid px-4">
<form action="proses_edit_besar.php" method="POST" id="formEditPRBesar">
<input type="hidden" name="id_request" value="<?= $id ?>">

<div class="card shadow-sm border-0" style="border-radius:12px;">

<!-- HEADER -->
<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5><i class="fas fa-edit me-2"></i>REVISI PR — <?= htmlspecialchars($pr['no_request']) ?></h5>
            <small class="opacity-75">Edit dan kirim ulang PR yang ditolak. Semua approval akan direset dari awal.</small>
        </div>
        <a href="pr.php" class="btn btn-sm btn-light"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="card-body p-4">

    <!-- INFO PENOLAKAN -->
    <div class="tolak-box mb-3">
        <div class="fw-bold text-danger mb-1">
            <i class="fas fa-times-circle me-2"></i>PR Ini Ditolak oleh: <strong><?= htmlspecialchars($pr['tolak_by'] ?? '-') ?></strong>
            <?php if (!empty($pr['tolak_at'])): ?>
                <span class="text-muted fw-normal ms-2" style="font-size:.78rem;">
                    pada <?= date('d/m/Y H:i', strtotime($pr['tolak_at'])) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if (!empty($pr['catatan_tolak'])): ?>
        <div><i class="fas fa-comment me-1"></i>Alasan: <em><?= htmlspecialchars($pr['catatan_tolak']) ?></em></div>
        <?php endif; ?>
    </div>

    <div class="revisi-box mb-4">
        <i class="fas fa-info-circle text-warning me-2"></i>
        <strong>Perhatian:</strong> Setelah Anda simpan revisi ini, PR akan kembali ke status <strong>PENDING</strong> dan
        <strong>semua approval akan direset</strong>. Manager 1 dan Manager 2 harus menyetujui ulang dari awal.
    </div>

    <!-- HEADER FORM -->
    <div class="row g-3 mb-3">
        <div class="col-md-2">
            <div class="section-label">Nomor Request</div>
            <input type="text" class="form-control bg-autonumber" value="<?= htmlspecialchars($pr['no_request']) ?>" readonly>
        </div>
        <div class="col-md-2">
            <div class="section-label">Tanggal Request</div>
            <input type="date" name="tgl_request" class="form-control"
                value="<?= htmlspecialchars($pr['tgl_request']) ?>" required>
        </div>
        <div class="col-md-3">
            <div class="section-label">Dibuat Oleh</div>
            <input type="text" name="nama_pemesan" class="form-control bg-light"
                value="<?= htmlspecialchars($pr['nama_pemesan']) ?>" readonly required>
        </div>
        <div class="col-md-5">
            <div class="section-label">Petugas Pembelian <span class="text-danger">*</span></div>
            <select name="nama_pembeli" class="form-select select-pembeli" required>
                <option value="">-- Pilih Petugas Pembelian --</option>
                <?php foreach ($list_pembeli as $val_u): ?>
                    <option value="<?= $val_u ?>"
                        <?= (strtoupper($pr['nama_pembeli']) === $val_u) ? 'selected' : '' ?>>
                        <?= $val_u ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="section-label">Keperluan / Tujuan Pembelian <span class="text-danger">*</span></div>
            <textarea name="keterangan" class="form-control" rows="2" required><?= htmlspecialchars($pr['keterangan']) ?></textarea>
        </div>
    </div>

    <hr class="my-3">

    <!-- TABEL ITEM -->
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-bold" style="font-size:.85rem;color:#6d28d9;">
            <i class="fas fa-list me-1"></i> Daftar Item Barang
            <small class="text-muted fw-normal ms-2">
                <i class="fas fa-tire text-warning me-1"></i>Centang kolom <strong>BAN</strong> jika item adalah ban kendaraan
            </small>
        </span>
        <button type="button" id="addRow" class="btn btn-sm btn-success fw-bold px-3">
            <i class="fas fa-plus me-1"></i> Tambah Baris
        </button>
    </div>

    <div class="table-responsive mb-4">
    <table class="table table-bordered table-input align-middle" id="tableItem">
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
            <th class="col-ban" title="Centang jika item ini adalah BAN kendaraan">
                <i class="fas fa-circle text-warning me-1" style="font-size:.6rem;"></i>BAN?
            </th>
            <th class="col-aks"></th>
        </tr>
    </thead>
    <tbody id="tbodyItem">
    <?php foreach ($details_raw as $idx => $d):
        $nama_item = !empty($d['nama_master']) ? strtoupper($d['nama_master']) : strtoupper($d['nama_barang_manual']);
        $is_ban = (int)$d['is_ban'];
    ?>
    <tr class="item-row">
        <td class="text-center row-number"><?= $idx + 1 ?></td>
        <td>
            <select name="id_barang[]" class="form-select form-select-sm select-barang" required>
                <?php
                // Render opsi dengan selected
                echo '<option value="">-- Pilih Barang --</option>';
                foreach ($list_barang as $b) {
                    $sel = ((int)$b['id_barang'] === (int)$d['id_barang']) ? ' selected' : '';
                    echo '<option value="'.$b['id_barang'].'"'.$sel
                        .' data-nama="'.htmlspecialchars(strtoupper($b['nama_barang']), ENT_QUOTES).'"'
                        .' data-satuan="'.htmlspecialchars(strtoupper($b['satuan'] ?? ''), ENT_QUOTES).'"'
                        .' data-merk="'.htmlspecialchars(strtoupper($b['merk'] ?? ''), ENT_QUOTES).'"'
                        .' data-kategori="'.htmlspecialchars(strtoupper($b['kategori'] ?? ''), ENT_QUOTES).'"'
                        .' data-harga="'.($b['harga_barang_stok'] ?? 0).'">'
                        .htmlspecialchars(strtoupper($b['nama_barang'])).'</option>';
                }
                ?>
            </select>
            <input type="hidden" name="nama_barang_manual[]" class="input-nama-barang"
                value="<?= htmlspecialchars($d['nama_barang_manual']) ?>">
        </td>
        <td>
            <select name="kategori_request[]" class="form-select form-select-sm select-kategori" required>
                <?= $html_opsi_kategori ?>
            </select>
        </td>
        <td>
            <input type="text" name="kwalifikasi[]" class="form-control form-control-sm input-kwalifikasi"
                value="<?= htmlspecialchars($d['kwalifikasi'] ?? '') ?>" placeholder="Merk / Spesifikasi...">
        </td>
        <td>
            <select name="id_mobil[]" class="form-select form-select-sm select-mobil">
                <option value="0">NON MOBIL</option>
                <?php foreach ($list_mobil as $m):
                    $sel = ((int)$m['id_mobil'] === (int)$d['id_mobil']) ? ' selected' : '';
                ?>
                <option value="<?= $m['id_mobil'] ?>"<?= $sel ?>><?= htmlspecialchars($m['plat_nomor']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <select name="tipe_request[]" class="form-select form-select-sm select-tipe">
                <option value="LANGSUNG" <?= $d['tipe_request']==='LANGSUNG'?'selected':'' ?>>LANGSUNG</option>
                <option value="STOK"     <?= $d['tipe_request']==='STOK'?'selected':'' ?>>STOK</option>
            </select>
        </td>
        <td>
            <input type="number" name="jumlah[]" class="form-control form-control-sm input-qty text-center"
                step="0.01" min="0.01" value="<?= (float)$d['jumlah']+0 ?>" required>
        </td>
        <td>
            <select name="satuan[]" class="form-select form-select-sm select-satuan" required>
                <?php
                echo '<option value="">- Pilih -</option>';
                foreach ($sats as $s) {
                    $sel = ($s === strtoupper($d['satuan'] ?? '')) ? ' selected' : '';
                    echo '<option value="'.$s.'"'.$sel.'>'.$s.'</option>';
                }
                ?>
            </select>
        </td>
        <td>
            <input type="number" name="harga[]" class="form-control form-control-sm input-harga text-end"
                placeholder="0" min="0" step="1" value="<?= (float)$d['harga_satuan_estimasi'] ?>">
        </td>
        <td>
            <?php $subtotal = (float)$d['jumlah'] * (float)$d['harga_satuan_estimasi']; ?>
            <input type="text" class="form-control form-control-sm input-subtotal text-end bg-light fw-bold"
                value="<?= number_format($subtotal, 0, ',', '.') ?>" readonly tabindex="-1">
        </td>
        <td>
            <textarea name="keterangan_item[]" class="form-control form-control-sm input-keterangan"
                rows="1" placeholder="Spesifikasi mendalam..."><?= htmlspecialchars($d['keterangan'] ?? '') ?></textarea>
        </td>
        <td>
            <div class="ban-check-wrap">
                <div class="text-center">
                    <input type="checkbox" name="is_ban[]" value="1" class="chk-ban"
                        <?= $is_ban ? 'checked' : '' ?>
                        title="Centang jika item ini adalah BAN kendaraan">
                    <div class="ban-badge <?= $is_ban ? '' : 'd-none' ?> ban-label">BAN</div>
                </div>
            </div>
            <input type="hidden" name="is_ban_val[]" class="input-is-ban-val" value="<?= $is_ban ?>">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger remove-row border-0">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
    <!-- Set nilai select2 kategori via JS setelah init -->
    <script>
    window._kat_row_<?= $idx ?> = "<?= htmlspecialchars($d['kategori_barang'] ?? '') ?>";
    </script>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>

    <!-- TOTAL -->
    <?php
    $grand_est = array_sum(array_map(function($d){
        return (float)$d['jumlah'] * (float)$d['harga_satuan_estimasi'];
    }, $details_raw));
    ?>
    <div class="row justify-content-end mb-4">
        <div class="col-md-4">
            <div class="total-box">
                <div class="grand-label">Total Estimasi Item</div>
                <div class="grand-value" id="grandTotalDisplay">Rp <?= number_format($grand_est,0,',','.') ?></div>
                <input type="hidden" id="grandTotalValue" name="grand_total" value="<?= $grand_est ?>">
            </div>
        </div>
    </div>

    <hr class="my-2">

    <!-- DATA PO -->
    <div class="po-section">
        <div class="po-section-title">
            <div class="divider"></div>
            <i class="fas fa-file-invoice" style="color:#7c3aed;"></i>
            Data Purchase Order (PO)
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <div class="section-label">Supplier / Vendor <span class="text-danger">*</span></div>
                <select name="id_supplier" id="selectSupplier" class="form-select select-supplier" required>
                    <option value="">-- Pilih Supplier --</option>
                    <?php foreach ($list_supplier as $s): ?>
                        <option value="<?= $s['id_supplier'] ?>"
                            <?= ($po && (int)$s['id_supplier'] === (int)$po['id_supplier']) ? 'selected' : '' ?>
                            data-alamat="<?= htmlspecialchars($s['alamat']    ?? '', ENT_QUOTES) ?>"
                            data-kota="<?=   htmlspecialchars($s['kota']      ?? '', ENT_QUOTES) ?>"
                            data-telp="<?=   htmlspecialchars($s['telp']      ?? '', ENT_QUOTES) ?>"
                            data-cp="<?=     htmlspecialchars($s['atas_nama'] ?? '', ENT_QUOTES) ?>">
                            <?= htmlspecialchars(strtoupper($s['nama_supplier'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <div class="section-label">Tanggal PO <span class="text-danger">*</span></div>
                <input type="date" name="tgl_po" class="form-control"
                    value="<?= $po ? htmlspecialchars($po['tgl_po']) : date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4" id="infoSupplierBox" style="<?= ($po && $po['id_supplier']) ? '' : 'display:none;' ?>">
                <div class="section-label">Info Supplier</div>
                <div class="p-2 bg-white border rounded" style="font-size:.78rem;line-height:2;">
                    <i class="fas fa-map-marker-alt text-danger me-1"></i><span id="info_alamat_pr">-</span><br>
                    <i class="fas fa-phone text-primary me-1"></i><span id="info_telp_pr">-</span><br>
                    <i class="fas fa-user text-success me-1"></i>U/P: <span id="info_cp_pr">-</span>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="section-label">Diskon (Rp)</div>
                <input type="number" name="diskon" id="inputDiskon" class="form-control"
                    value="<?= $po ? (float)$po['diskon'] : 0 ?>" min="0">
            </div>
            <div class="col-md-3">
                <div class="section-label">PPN</div>
                <select name="ppn_persen" id="selectPPN" class="form-select">
                    <option value="0"  <?= ($po && (float)$po['ppn_persen']==0)  ? 'selected' : '' ?>>Tanpa PPN</option>
                    <option value="11" <?= (!$po || (float)$po['ppn_persen']==11) ? 'selected' : '' ?>>PPN 11%</option>
                    <option value="12" <?= ($po && (float)$po['ppn_persen']==12) ? 'selected' : '' ?>>PPN 12%</option>
                </select>
            </div>
            <div class="col-md-6">
                <div class="section-label">Grand Total PO (Estimasi)</div>
                <div class="grand-po-box">
                    <div class="label">Grand Total PO</div>
                    <div class="value" id="displayGrandTotalPO">
                        Rp <?= $po ? number_format($po['grand_total'],0,',','.') : '0' ?>
                    </div>
                    <input type="hidden" name="grand_total_po" id="hiddenGrandTotalPO"
                        value="<?= $po ? (float)$po['grand_total'] : 0 ?>">
                </div>
            </div>
        </div>

        <div>
            <div class="section-label">Catatan / Ketentuan Pembayaran PO</div>
            <textarea name="catatan_po" class="form-control" rows="3"
                placeholder="Contoh: Pembayaran AN. PT. XYZ..."><?= $po ? htmlspecialchars($po['catatan']) : '' ?></textarea>
        </div>
    </div>

</div><!-- end card-body -->

<!-- FOOTER -->
<div class="card-footer px-4 py-3 d-flex justify-content-between align-items-center">
    <div class="text-muted" style="font-size:.78rem;">
        <i class="fas fa-redo text-warning me-1"></i>
        Setelah disimpan, PR kembali ke <strong>PENDING</strong> dan <strong>semua approval direset dari awal</strong>.
    </div>
    <div>
        <a href="pr.php" class="btn btn-outline-secondary me-2"><i class="fas fa-times me-1"></i> Batal</a>
        <button type="submit" class="btn fw-bold px-4" style="background:#6d28d9;color:white;">
            <i class="fas fa-paper-plane me-1"></i> Simpan Revisi & Kirim Ulang
        </button>
    </div>
</div>

</div><!-- end card -->
</form>
</div>

<script>
var OPSI_BARANG   = <?= json_encode($html_opsi_barang) ?>;
var OPSI_MOBIL    = <?= json_encode($html_opsi_mobil) ?>;
var OPSI_SATUAN   = <?= json_encode($html_opsi_satuan) ?>;
var OPSI_KATEGORI = <?= json_encode($html_opsi_kategori) ?>;
// Data kategori existing per baris
var KAT_DATA = <?= json_encode(array_map(fn($d) => strtoupper($d['kategori_barang'] ?? ''), $details_raw)) ?>;
</script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function(){
    function initSelect2(ctx){
        var $c = ctx ? $(ctx) : $(document);
        $c.find('.select-barang,.select-kategori,.select-mobil,.select-tipe,.select-satuan,.select-pembeli,.select-supplier').each(function(){
            if(!$(this).hasClass('select2-hidden-accessible')){
                $(this).select2({theme:'bootstrap-5',width:'100%'});
            }
        });
    }
    initSelect2();

    // Set nilai kategori existing setelah select2 init
    $('#tbodyItem tr.item-row').each(function(i){
        var kat = KAT_DATA[i] || '';
        if(kat) $(this).find('.select-kategori').val(kat).trigger('change');
    });

    function rp(n){ return 'Rp '+parseFloat(n||0).toLocaleString('id-ID'); }

    function hitungSubtotal(row){
        var qty=parseFloat(row.find('.input-qty').val())||0;
        var hrg=parseFloat(row.find('.input-harga').val())||0;
        row.find('.input-subtotal').val((qty*hrg).toLocaleString('id-ID'));
        hitungTotal();
    }

    function hitungTotal(){
        var total=0;
        $('.input-harga').each(function(){
            var row=$(this).closest('tr');
            total+=(parseFloat(row.find('.input-qty').val())||0)*(parseFloat($(this).val())||0);
        });
        $('#grandTotalDisplay').text(rp(total));
        $('#grandTotalValue').val(total);
        var diskon=parseFloat($('#inputDiskon').val())||0;
        var ppn=parseFloat($('#selectPPN').val())||0;
        var t=total-diskon;
        var grand=t+(t*ppn/100);
        $('#displayGrandTotalPO').text(rp(grand));
        $('#hiddenGrandTotalPO').val(grand);
    }

    function updateNo(){
        $('#tbodyItem tr.item-row').each(function(i){ $(this).find('.row-number').text(i+1); });
    }

    $(document).on('change','.chk-ban',function(){
        var row=$(this).closest('tr');
        var checked=$(this).is(':checked');
        row.find('.input-is-ban-val').val(checked?'1':'0');
        row.find('.ban-label').toggleClass('d-none',!checked);
    });

    $(document).on('change','.select-barang',function(){
        var row=$(this).closest('tr'), sel=$(this).find(':selected');
        row.find('.input-nama-barang').val(sel.data('nama')||'');
        row.find('.input-kwalifikasi').val(sel.data('merk')||'');
        row.find('.input-harga').val(sel.data('harga')||'');
        if(sel.data('kategori')) row.find('.select-kategori').val(sel.data('kategori')).trigger('change');
        if(sel.data('satuan'))   row.find('.select-satuan').val(sel.data('satuan')).trigger('change');
        hitungSubtotal(row);
    });

    $(document).on('input','.input-qty,.input-harga',function(){ hitungSubtotal($(this).closest('tr')); });
    $('#inputDiskon,#selectPPN').on('input change',function(){ hitungTotal(); });

    $('#selectSupplier').on('change',function(){
        var sel=$(this).find(':selected');
        if($(this).val()){
            $('#info_alamat_pr').text((sel.data('alamat')||'-')+(sel.data('kota')?', '+sel.data('kota'):''));
            $('#info_telp_pr').text(sel.data('telp')||'-');
            $('#info_cp_pr').text(sel.data('cp')||'-');
            $('#infoSupplierBox').show();
        } else { $('#infoSupplierBox').hide(); }
    });

    $('#addRow').on('click',function(){
        var n=$('#tbodyItem tr.item-row').length+1;
        var r=$('<tr class="item-row"></tr>');
        r.append('<td class="text-center row-number">'+n+'</td>');
        r.append('<td><select name="id_barang[]" class="form-select form-select-sm select-barang" required>'+OPSI_BARANG+'</select><input type="hidden" name="nama_barang_manual[]" class="input-nama-barang" value=""></td>');
        r.append('<td><select name="kategori_request[]" class="form-select form-select-sm select-kategori" required>'+OPSI_KATEGORI+'</select></td>');
        r.append('<td><input type="text" name="kwalifikasi[]" class="form-control form-control-sm input-kwalifikasi" placeholder="Merk / Spesifikasi..."></td>');
        r.append('<td><select name="id_mobil[]" class="form-select form-select-sm select-mobil">'+OPSI_MOBIL+'</select></td>');
        r.append('<td><select name="tipe_request[]" class="form-select form-select-sm select-tipe"><option value="LANGSUNG" selected>LANGSUNG</option><option value="STOK">STOK</option></select></td>');
        r.append('<td><input type="number" name="jumlah[]" class="form-control form-control-sm input-qty text-center" step="0.01" min="0.01" value="1" required></td>');
        r.append('<td><select name="satuan[]" class="form-select form-select-sm select-satuan" required>'+OPSI_SATUAN+'</select></td>');
        r.append('<td><input type="number" name="harga[]" class="form-control form-control-sm input-harga text-end" placeholder="0" min="0" step="1"></td>');
        r.append('<td><input type="text" class="form-control form-control-sm input-subtotal text-end bg-light fw-bold" value="0" readonly tabindex="-1"></td>');
        r.append('<td><textarea name="keterangan_item[]" class="form-control form-control-sm input-keterangan" rows="1" placeholder="Spesifikasi mendalam..."></textarea></td>');
        r.append('<td><div class="ban-check-wrap"><div class="text-center"><input type="checkbox" name="is_ban[]" value="1" class="chk-ban"><div class="ban-badge d-none ban-label">BAN</div></div></div><input type="hidden" name="is_ban_val[]" class="input-is-ban-val" value="0"></td>');
        r.append('<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-row border-0"><i class="fas fa-times"></i></button></td>');
        $('#tbodyItem').append(r);
        initSelect2(r);
        updateNo();
    });

    $(document).on('click','.remove-row',function(){
        if($('.item-row').length>1){
            $(this).closest('tr').remove();
            hitungTotal(); updateNo();
        } else { Swal.fire('Perhatian','Minimal harus ada 1 item barang.','warning'); }
    });

    $('#formEditPRBesar').on('submit',function(e){
        e.preventDefault();
        var form=this, valid=true;
        $('.select-barang').each(function(){ if(!$(this).val()){ valid=false; return false; } });
        if(!valid){ Swal.fire('Perhatian','Pastikan semua baris sudah memilih nama barang.','warning'); return; }
        if(!$('#selectSupplier').val()){ Swal.fire('Perhatian','Pilih supplier/vendor untuk PO terlebih dahulu.','warning'); return; }

        var banError = false;
        $('.chk-ban:checked').each(function(){
            var row=$(this).closest('tr');
            if(!row.find('.select-mobil').val() || row.find('.select-mobil').val()=='0'){
                banError=true; return false;
            }
        });
        if(banError){ Swal.fire('Perhatian','Item BAN harus memiliki plat nomor kendaraan.','warning'); return; }

        Swal.fire({
            title:'Simpan Revisi & Kirim Ulang?',
            html:'PR akan kembali ke status <strong>PENDING</strong>.<br>Semua approval akan <strong>direset dari awal</strong>.',
            icon:'question', showCancelButton:true,
            confirmButtonColor:'#6d28d9',
            confirmButtonText:'<i class="fas fa-paper-plane me-1"></i> Ya, Kirim Ulang!',
            cancelButtonText:'Batal'
        }).then(function(r){
            if(r.isConfirmed){
                Swal.fire({title:'Memproses...',allowOutsideClick:false,showConfirmButton:false,didOpen:function(){Swal.showLoading();}});
                form.submit();
            }
        });
    });
});
</script>
</body>
</html>
