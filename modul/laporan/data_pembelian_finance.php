<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
//require_once __DIR__ . '/../../auth/keep_alive.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$nama_user = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'ADMIN';

// 1. TANGKAP FILTER
$abjad_filter   = isset($_GET['abjad'])         ? mysqli_real_escape_string($koneksi, strtoupper($_GET['abjad'])) : '';
// DEFAULT: Hari ini untuk kedua rentang tanggal
$today          = date('Y-m-d');
$tgl_min        = isset($_GET['tgl_min']) && !empty($_GET['tgl_min']) ? $_GET['tgl_min'] : $today;
$tgl_max        = isset($_GET['tgl_max']) && !empty($_GET['tgl_max']) ? $_GET['tgl_max'] : $today;
$keyword        = isset($_GET['keyword'])        ? mysqli_real_escape_string($koneksi, strtoupper($_GET['keyword'])) : '';
$filter_petugas = isset($_GET['id_user_beli'])   ? (int)$_GET['id_user_beli'] : 0;

// 2. QUERY STRING
$query_string = "tgl_min=$tgl_min&tgl_max=$tgl_max&keyword=$keyword&id_user_beli=$filter_petugas";

// 3. AMBIL DAFTAR PETUGAS untuk dropdown
$sql_petugas = "SELECT DISTINCT u.id_user, u.nama_lengkap 
                FROM users u 
                INNER JOIN pembelian p ON p.id_user_beli = u.id_user 
                ORDER BY u.nama_lengkap ASC";
$res_petugas = mysqli_query($koneksi, $sql_petugas);
$list_petugas = [];
while ($r = mysqli_fetch_assoc($res_petugas)) {
    $list_petugas[] = $r;
}

// 4. AMBIL MASTER MOBIL untuk dropdown plat nomor
$sql_mobil = "SELECT id_mobil, plat_nomor, driver_tetap, merk_tipe 
              FROM master_mobil 
              WHERE status_aktif = 'AKTIF' 
              ORDER BY plat_nomor ASC";
$res_mobil   = mysqli_query($koneksi, $sql_mobil);
$list_mobil  = [];
while ($r = mysqli_fetch_assoc($res_mobil)) {
    $list_mobil[] = $r;
}

// 5. BANGUN SQL FILTER
$filter_sql = " WHERE 1=1 ";
if ($abjad_filter != '' && $abjad_filter != 'ALL') {
    $filter_sql .= " AND p.nama_barang_beli LIKE '$abjad_filter%' ";
}
if ($tgl_min != '' && $tgl_max != '') {
    $filter_sql .= " AND p.tgl_beli_barang BETWEEN '$tgl_min' AND '$tgl_max' ";
}
if ($keyword != '') {
    $filter_sql .= " AND (p.nama_barang_beli LIKE '%$keyword%' OR p.supplier LIKE '%$keyword%' OR p.plat_nomor LIKE '%$keyword%') ";
}
if ($filter_petugas > 0) {
    $filter_sql .= " AND p.id_user_beli = $filter_petugas ";
}

// 6. AMBIL DATA
$sql = "SELECT p.*, 
               (SELECT merk FROM master_barang WHERE nama_barang = p.nama_barang_beli AND status_aktif = 'AKTIF' LIMIT 1) as merk_master,
               (SELECT satuan FROM master_barang WHERE nama_barang = p.nama_barang_beli AND status_aktif = 'AKTIF' LIMIT 1) as satuan_master,
               u.nama_lengkap as nama_petugas
        FROM pembelian p 
        LEFT JOIN users u ON p.id_user_beli = u.id_user
        $filter_sql 
        ORDER BY p.tgl_beli_barang DESC, p.id_pembelian DESC LIMIT 500";

$query       = mysqli_query($koneksi, $sql);
$data_tampil = [];
$harga_array = [];

while ($row = mysqli_fetch_assoc($query)) {
    $data_tampil[] = $row;
    if ($row['harga'] > 0) { $harga_array[] = (float)$row['harga']; }
}

$harga_termurah = (count($harga_array) > 0) ? min($harga_array) : 0;

// Nama petugas terpilih (untuk header cetak)
$nama_petugas_filter = 'Semua Petugas';
if ($filter_petugas > 0) {
    foreach ($list_petugas as $p) {
        if ($p['id_user'] == $filter_petugas) { $nama_petugas_filter = $p['nama_lengkap']; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Realisasi Pembelian - MCP</title>
	<link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Flatpickr Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.82rem; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .search-box, .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 20px; }
        .row-termurah { background-color: #f0fff4 !important; border-left: 5px solid #198754; }
        .badge-termurah { background-color: #198754; color: white; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; display: inline-block; margin-bottom: 3px; }
        .alphabet-nav { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 15px; }
        .btn-abjad { padding: 4px 9px; font-size: 10px; font-weight: bold; border: 1px solid #dee2e6; background: white; color: #333; text-decoration: none; border-radius: 4px; }
        .btn-abjad.active, .btn-abjad:hover { background: var(--mcp-blue); color: white; border-color: var(--mcp-blue); }
        .text-plat { background: #333; color: #fff; padding: 2px 5px; border-radius: 3px; font-weight: bold; font-family: monospace; font-size: 10px; }
        .btn-xs { padding: 0.25rem 0.4rem; font-size: 0.75rem; }
        table thead th { vertical-align: middle; background-color: #212529 !important; color: white; }
        .badge-petugas { background: #e8f0fe; color: #1a56db; padding: 2px 7px; border-radius: 20px; font-size: 10px; font-weight: 600; border: 1px solid #c3d3f7; }
        .select-petugas-aktif { border-color: #0000FF !important; box-shadow: 0 0 0 0.2rem rgba(0,0,255,.15) !important; font-weight: bold; color: #0000FF; }

        /* Info mobil terpilih di modal */
        #info_mobil_terpilih { font-size: 10px; color: #555; min-height: 14px; }

        /* Styling Flatpickr */
        .flatpickr-input { font-family: 'Inter', sans-serif; }
        .flatpickr-calendar { box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important; }

        /* CSS untuk Cetak */
        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { 
                position: absolute; left: 0; top: 0; width: 100%; 
                font-size: 9pt !important;
            }
            .navbar-mcp, .search-box, .btn-group, .btn-edit, .btn-info, 
            .btn-danger, .btn-warning, .alphabet-nav, .table-container .btn { display: none !important; }
            
            .print-table { 
                width: 100%; border-collapse: collapse; 
                font-family: Arial, sans-serif;
                font-size: 9pt !important;
                table-layout: fixed;
            }
            .print-table th { 
                background-color: #333 !important; color: white !important; 
                padding: 4px 3px !important; text-align: center; font-weight: bold;
                font-size: 9pt !important;
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .print-table td { 
                border: 1px solid #000; padding: 3px 4px !important;
                vertical-align: top; font-size: 9pt !important; word-wrap: break-word;
            }
            .print-table .row-termurah { 
                background-color: #f0fff4 !important; 
                -webkit-print-color-adjust: exact; print-color-adjust: exact;
            }
            .print-table th:nth-child(1), .print-table td:nth-child(1) { width: 3%; }
            .print-table th:nth-child(2), .print-table td:nth-child(2) { width: 7%; }
            .print-table th:nth-child(3), .print-table td:nth-child(3) { width: 14%; }
            .print-table th:nth-child(4), .print-table td:nth-child(4) { width: 25%; }
            .print-table th:nth-child(5), .print-table td:nth-child(5) { width: 4%; }
            .print-table th:nth-child(6), .print-table td:nth-child(6) { width: 4%; }
            .print-table th:nth-child(7), .print-table td:nth-child(7) { width: 10%; }
            .print-table th:nth-child(8), .print-table td:nth-child(8) { width: 11%; }
            .print-table th:nth-child(9), .print-table td:nth-child(9) { width: 11%; }
            .print-table th:nth-child(10), .print-table td:nth-child(10) { width: 11%; } /* PETUGAS */

            .print-header { text-align: center; margin-bottom: 8px !important; padding: 5px !important; border-bottom: 2px solid #000; }
            .print-header h2 { margin: 0; font-size: 14pt !important; }
            .print-header h4 { margin: 3px 0; font-size: 11pt !important; }
            .print-header p { margin: 2px 0; font-size: 8pt !important; }
            .print-filter-info { font-size: 8pt !important; margin: 5px 0 !important; padding: 4px !important; background: #f5f5f5; border: 1px dashed #999; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-summary { page-break-inside: avoid; margin-top: 15px; padding: 8px; border-top: 2px solid #333; background-color: #fafafa; font-size: 9pt !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-urut { text-align: center; font-weight: bold; }
            tr, td, th { page-break-inside: avoid; }
            .print-summary { page-break-before: avoid; page-break-after: avoid; }
        }
        .print-area { display: none; }
    </style>
</head>
<body class="pb-5">

<nav class="navbar navbar-mcp mb-4 shadow-sm">
    <div class="container-fluid px-4 text-white">
        <span class="navbar-brand fw-bold text-white small"><i class="fas fa-book me-2"></i> BUKU REALISASI PEMBELIAN</span>
        <div>
            <button onclick="cetakLaporan()" class="btn btn-light btn-sm px-3 fw-bold me-2">
                <i class="fas fa-print me-1"></i> CETAK LAPORAN
            </button>
            <a href="export_excel_pembelian.php?abjad=<?= $abjad_filter ?>&<?= $query_string ?>" class="btn btn-success btn-sm px-3 fw-bold me-2">
                <i class="fas fa-file-excel me-1"></i> EXPORT
            </a>
            <a href="../../index.php" class="btn btn-danger btn-sm px-3 fw-bold"><i class="fas fa-arrow-left me-1"></i> KEMBALI</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="search-box mb-4">
        <form action="" method="GET">
            <div class="row g-3">
                <!-- Filter Tanggal (dengan Flatpickr) -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">RENTANG TANGGAL NOTA</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-primary fw-bold d-block mb-1">START DATE</small>
                            <input type="text" id="tgl_min_display" class="form-control form-control-sm date-picker" 
                                   value="<?= date('d-M-Y', strtotime($tgl_min)) ?>" placeholder="DD-Mon-YYYY">
                            <input type="hidden" name="tgl_min" id="tgl_min_hidden" value="<?= $tgl_min ?>">
                        </div>
                        <div class="col-6">
                            <small class="text-success fw-bold d-block mb-1">END DATE</small>
                            <input type="text" id="tgl_max_display" class="form-control form-control-sm date-picker" 
                                   value="<?= date('d-M-Y', strtotime($tgl_max)) ?>" placeholder="DD-Mon-YYYY">
                            <input type="hidden" name="tgl_max" id="tgl_max_hidden" value="<?= $tgl_max ?>">
                        </div>
                    </div>
                </div>

                <!-- Filter Petugas -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">
                        <i class="fas fa-user-tie me-1"></i>PETUGAS PEMBELIAN
                    </label>
                    <select name="id_user_beli" class="form-select form-select-sm <?= $filter_petugas > 0 ? 'select-petugas-aktif' : '' ?>">
                        <option value="0">— Semua Petugas —</option>
                        <?php foreach ($list_petugas as $p): ?>
                            <option value="<?= $p['id_user'] ?>" <?= ($filter_petugas == $p['id_user']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper($p['nama_lengkap'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Keyword -->
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted">PENCARIAN</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="keyword" class="form-control text-uppercase" 
                               placeholder="BARANG / TOKO / PLAT..." value="<?= $keyword ?>">
                        <button type="submit" class="btn btn-primary fw-bold px-4">CARI DATA</button>
                    </div>
                </div>

                <!-- Reset -->
                <div class="col-md-2 d-flex align-items-end">
                    <a href="data_pembelian_finance.php" class="btn btn-warning btn-sm w-100 fw-bold">
                        <i class="fas fa-sync me-1"></i> RESET
                    </a>
                </div>
            </div>

            <!-- Filter Abjad -->
            <div class="alphabet-nav">
                <a href="?abjad=ALL&<?= $query_string ?>" class="btn-abjad <?= ($abjad_filter == '' || $abjad_filter == 'ALL') ? 'active' : '' ?>">ALL</a>
                <?php foreach (range('A', 'Z') as $char): ?>
                    <a href="?abjad=<?= $char ?>&<?= $query_string ?>" 
                       class="btn-abjad <?= ($abjad_filter == $char) ? 'active' : '' ?>"><?= $char ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Info filter aktif -->
            <?php if ($filter_petugas > 0): ?>
            <div class="mt-2 p-2 rounded" style="background:#e8f0fe; border:1px solid #c3d3f7; font-size:11px;">
                <i class="fas fa-filter me-1 text-primary"></i>
                Menampilkan data pembelian oleh: 
                <strong class="text-primary"><?= htmlspecialchars(strtoupper($nama_petugas_filter)) ?></strong>
                — <?= count($data_tampil) ?> transaksi ditemukan.
                <a href="data_pembelian_finance.php" class="ms-2 text-danger fw-bold" style="font-size:11px;">
                    <i class="fas fa-times"></i> Hapus Filter
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabel Data -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100">
                <thead class="table-dark">
                    <tr class="text-nowrap small text-center">
                        <th width="100">TGL NOTA</th>
                        <th width="150">SUPPLIER</th>
                        <th class="text-start">NAMA BARANG</th>
                        <th width="70">QTY</th>
                        <th width="120">HARGA</th>
                        <th width="130">TOTAL</th>
                        <th width="150">ALOKASI / UNIT</th>
                        <th class="text-start">KETERANGAN</th>
                        <th width="120">PETUGAS</th>
                        
                    </tr>
                </thead>
                <tbody class="text-uppercase">
                    <?php 
                    if (!empty($data_tampil)):
                        foreach ($data_tampil as $row): 
                            $total_bayar      = $row['qty'] * $row['harga'];
                            $is_termurah      = ($row['harga'] == $harga_termurah && $harga_termurah > 0);
                            $merk_tampil      = !empty($row['merk_beli']) ? $row['merk_beli'] : ($row['merk_master'] ?? '-');
                            $satuan           = !empty($row['satuan_master']) ? $row['satuan_master'] : 'PCS';
                            $tgl_display      = ($row['tgl_beli_barang'] == '0000-00-00' || empty($row['tgl_beli_barang'])) 
                                                ? '<span class="text-muted small">-</span>' 
                                                : date('d/m/y', strtotime($row['tgl_beli_barang']));
                            $nama_petugas_row = !empty($row['nama_petugas']) ? $row['nama_petugas'] : '-';
                    ?>
                    <tr class="<?= $is_termurah ? 'row-termurah' : '' ?>">
                        <td class="text-center fw-bold text-muted"><?= $tgl_display ?></td>
                        <td class="small"><?= substr($row['supplier'], 0, 25) ?></td>
                        <td>
                            <div class="fw-bold"><?= $row['nama_barang_beli'] ?></div>
                            <small class="text-primary fw-bold" style="font-size: 10px;"><?= $merk_tampil ?></small>
                        </td>
                        <td class="text-center">
                            <div class="fw-bold"><?= (float)$row['qty'] ?></div>
                            <div class="text-muted fw-bold" style="font-size: 9px;"><?= strtoupper($satuan) ?></div>
                        </td>
                        <td class="text-end">
                            <?php if ($is_termurah): ?>
                                <span class="badge-termurah"><i class="fas fa-check-circle"></i> TERMURAH</span><br>
                            <?php endif; ?>
                            <span class="fw-bold <?= $is_termurah ? 'text-success' : '' ?>">
                                <?= number_format($row['harga'], 0, ',', '.') ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-danger"><?= number_format($total_bayar, 0, ',', '.') ?></td>
                        <td>
                            <span class="badge bg-secondary mb-1" style="font-size: 9px;"><?= $row['alokasi_stok'] ?></span><br>
                            <?php if (!empty($row['plat_nomor'])): ?>
                                <span class="text-plat"><?= $row['plat_nomor'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small fw-bold text-start"><?= $row['keterangan'] ?: '-' ?></td>
                        <td class="text-center">
                            <span class="badge-petugas">
                                <i class="fas fa-user me-1"></i><?= htmlspecialchars($nama_petugas_row) ?>
                            </span>
                        </td>
                      
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="9" class="text-center p-4 text-muted">Data tidak ditemukan sesuai filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Siapkan data JSON untuk window cetak
$print_rows        = [];
$grand_total_print = 0;

foreach ($data_tampil as $row) {
    $total_bayar = $row['qty'] * $row['harga'];
    $grand_total_print += $total_bayar;
    $tgl_format = ($row['tgl_beli_barang'] != '0000-00-00' && !empty($row['tgl_beli_barang']))
                  ? date('d/m/y', strtotime($row['tgl_beli_barang'])) : '-';

    // 1. Ambil data dasar
    $plat = !empty($row['plat_nomor']) ? trim($row['plat_nomor']) : '';
    $raw_keterangan = !empty($row['keterangan']) ? $row['keterangan'] : '';

    // 2. Logika potong keterangan (Ambil 2 bagian terakhir saja)
    $tampil_ket = "";
    if (!empty($raw_keterangan) && $raw_keterangan != '-') {
        $parts = explode('|', $raw_keterangan);
        if (count($parts) >= 2) {
            $last_two = array_slice($parts, -2); // Ambil 2 terakhir (L8023 | MISTARI)
            $tampil_ket = trim($last_two[0]) . ' | ' . trim($last_two[1]);
        } else {
            $tampil_ket = trim($raw_keterangan);
        }
    }

    // 3. Gabungkan hasil akhir
    // Jika di dalam keterangan sudah ada plat nomor, jangan tulis plat nomor lagi di depan
    if (!empty($plat) && strpos($tampil_ket, $plat) === false) {
        $ket_final = $plat . ' | ' . $tampil_ket;
    } else {
        $ket_final = $tampil_ket;
    }

    // Jika hasil akhirnya kosong, baru pakai alokasi_stok sebagai fallback
    if (empty($ket_final)) {
        $ket_final = $row['alokasi_stok'] ?? '-';
    }

    $satuan_print = !empty($row['satuan_master']) ? strtoupper($row['satuan_master']) : 'PCS';
    $print_rows[] = [
        'tgl'      => $tgl_format,
        'supplier' => strtoupper(substr($row['supplier'] ?? '-', 0, 20)),
        'barang'   => strtoupper($row['nama_barang_beli']),
        'merk'     => strtoupper($row['merk_beli'] ?? ''),
        'qty'      => number_format((float)$row['qty'], 2, ',', '.'),
        'satuan'   => $satuan_print,
        'harga'    => number_format((float)$row['harga'], 0, ',', '.'),
        'total'    => number_format($total_bayar, 0, ',', '.'),
        'ket'      => strtoupper($ket_final), // <--- Menggunakan hasil potongan yang bersih
        'termurah' => ($row['harga'] == $harga_termurah && $harga_termurah > 0),
    ];
}
?>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// ── Setup Flatpickr Date Pickers ────────────────────────
var tglMinPicker = flatpickr("#tgl_min_display", {
    dateFormat: "d-M-Y",
    defaultDate: "<?= date('d-M-Y', strtotime($tgl_min)) ?>",
    onChange: function(selectedDates, dateStr, instance) {
        // Update hidden input dengan format database (YYYY-MM-DD)
        if (selectedDates.length > 0) {
            var dbFormat = selectedDates[0].toISOString().split('T')[0];
            document.getElementById('tgl_min_hidden').value = dbFormat;
        }
    }
});

var tglMaxPicker = flatpickr("#tgl_max_display", {
    dateFormat: "d-M-Y",
    defaultDate: "<?= date('d-M-Y', strtotime($tgl_max)) ?>",
    onChange: function(selectedDates, dateStr, instance) {
        // Update hidden input dengan format database (YYYY-MM-DD)
        if (selectedDates.length > 0) {
            var dbFormat = selectedDates[0].toISOString().split('T')[0];
            document.getElementById('tgl_max_hidden').value = dbFormat;
        }
    }
});

var PRINT_DATA = {
    rows       : <?php echo json_encode($print_rows, JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    grandTotal : "Rp <?php echo number_format($grand_total_print, 0, ',', '.'); ?>",
    totalItem  : <?php echo count($data_tampil); ?>,
    periode    : "<?php echo ($tgl_min && $tgl_max) ? date('d/m/Y', strtotime($tgl_min)).' s/d '.date('d/m/Y', strtotime($tgl_max)) : 'Semua Data'; ?>",
    petugas    : "<?php echo addslashes(strtoupper($nama_petugas_filter)); ?>",
    tglCetak   : "<?php echo date('d/m/Y H:i'); ?>",
    oleh       : "<?php echo addslashes(strtoupper($_SESSION['nama_user'] ?? $nama_user)); ?>"
};

// ── Helper: tampilkan info kendaraan terpilih ──────────────
function updateInfoMobil(opt) {
    var info = $('#info_mobil_terpilih');
    if (opt.val() && opt.data('merk')) {
        info.html('<i class="fas fa-car me-1 text-primary"></i>'
                  + '<span class="text-primary fw-bold">' + opt.data('merk') + '</span>');
    } else {
        info.html('');
    }
}

$(document).ready(function() {

    // Highlight select petugas
    $('select[name="id_user_beli"]').on('change', function() {
        $(this).toggleClass('select-petugas-aktif', $(this).val() > 0);
    });

});

// ── Fungsi Cetak ─────────────────────────────────────────────
function cetakLaporan() {
    var d = PRINT_DATA;
    if (d.rows.length === 0) { alert('Tidak ada data untuk dicetak.'); return; }

    var ROWS_PER_PAGE = 18;
    var now      = new Date();
    var yymm     = now.getFullYear() + '' + String(now.getMonth()+1).padStart(2,'0');
    var seqNum   = String(Math.floor(Math.random()*900)+100);
    var baseForm = 'PB-' + yymm + '-' + seqNum;
    var ALPHA    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    var pages = [];
    for (var p = 0; p < d.rows.length; p += ROWS_PER_PAGE) {
        pages.push(d.rows.slice(p, p + ROWS_PER_PAGE));
    }
    var totalPages = pages.length;

    var css = ''
    + '@page { size: 21.5cm 16.5cm landscape; margin: 0.5cm 0.7cm; }'
    + '* { box-sizing:border-box; }'
	// Font diperkecil sedikit ke 7.5pt
	+ 'body { font-family:Arial,sans-serif; font-size:7.5pt; margin:0; padding:0; background:#fff; color:#000; }'
	// Padding block dikurangi agar tidak banyak ruang kosong di atas/bawa
    + '.page-block { width:100%; padding:5px 6px; page-break-after:always; position:relative; }'
    + '.header { text-align:center; border-bottom:1.5px solid #000; padding-bottom:3px; margin-bottom:4px; }'
    + '.header h2 { margin:0; font-size:11pt; font-weight:bold; }'
    + '.header h4 { margin:1px 0 0; font-size:8.5pt; font-weight:normal; }'
	// Bar info dipadatkan
	+ '.info-bar { display:flex; justify-content:space-between; align-items:center; background:#eeeeee; border:0.5px solid #aaa; padding:2px 6px; margin-bottom:4px; font-size:7pt; -webkit-print-color-adjust:exact; print-color-adjust:exact; }'
	// Tabel direkatkan (Padding dikurangi dari 3px ke 1px atau 2px)
    + '.info-bar .petugas-name { font-size:9.5pt; font-weight:bold; color:#000; }'
    + 'table.data { width:100%; border-collapse:collapse; font-size:7.5pt; table-layout:fixed; }'
    + 'table.data th { background-color:#333 !important; color:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; text-align:center; font-size:7pt; font-weight:bold; border:0.5px solid #000; padding:3px 3px; }'
    + 'table.data td { border:0.5px solid #000; padding:2px 3px; vertical-align:middle; word-wrap:break-word; overflow:hidden; }'
    + '.c-no  { width:22px; text-align:center; }'
    + '.c-tgl { width:52px; text-align:center; }'
    + '.c-sup { width:88px; }'
    + '.c-brg { }'
    + '.c-qty { width:40px; text-align:center; }'
    + '.c-hrg { width:70px; text-align:right; }'
    + '.c-tot { width:76px; text-align:right; }'
    + '.c-ket { width:93px; }'
	// Footer dipadatkan
	//+ '.page-footer { display:flex; justify-content:space-between; align-items:flex-end; margin-top:2px; padding-top:2px; border-top:1px solid #000; font-size:7pt; }'
	//+ '.ttd-line { border-top:1px solid #000; width:100px; margin:15px auto 2px; }'; // Jarak ttd diperkecil
    + '.page-footer { display:flex; justify-content:space-between; align-items:flex-end; margin-top:4px; padding-top:3px; border-top:1px solid #000; font-size:7.5pt; }'
    + '.total-box { background:#333; color:#fff; padding:3px 8px; font-size:8.5pt; font-weight:bold; -webkit-print-color-adjust:exact; print-color-adjust:exact; }'
    + '.ttd-box { text-align:center; font-size:7.5pt; }'
    + '.ttd-line { border-top:1px solid #000; width:130px; margin:20px auto 2px; }'
    + '.form-no { font-size:7pt; color:#333; font-weight:bold; border:0.5px solid #aaa; padding:2px 5px; background:#f9f9f9; -webkit-print-color-adjust:exact; print-color-adjust:exact; }'
    + '.print-ctrl { text-align:center; padding:8px; margin:8px; background:#f8f9fa; border:1px solid #ddd; border-radius:4px; }'
    + '.print-ctrl button { border:none; padding:6px 16px; margin:0 4px; border-radius:3px; font-size:11px; font-weight:bold; cursor:pointer; color:#fff; }'
    + '.btn-blue{background:#007bff;} .btn-green{background:#28a745;} .btn-gray{background:#6c757d;}'
    + '.page-badge { display:inline-block; background:#555; color:#fff; font-size:7pt; padding:1px 5px; border-radius:2px; margin-left:6px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }'
    + '@media print { .print-ctrl{display:none!important;} body{padding:0;margin:0;} }';
	

    var allPages = '';
    pages.forEach(function(pageRows, pi) {
        var sheetLetter = ALPHA[pi] || String(pi+1);
        var formNo      = baseForm + '/' + sheetLetter;

        var tbody = '';
        pageRows.forEach(function(r, ri) {
            var globalIdx = pi * ROWS_PER_PAGE + ri + 1;
            var bg = r.termurah ? 'background:#e8fff0;-webkit-print-color-adjust:exact;print-color-adjust:exact;' : '';
            tbody += '<tr style="' + bg + '">'
                + '<td style="text-align:center;font-weight:bold;">' + globalIdx + '</td>'
                + '<td style="text-align:center;white-space:nowrap;">' + r.tgl + '</td>'
                + '<td>' + r.supplier + '</td>'
                + '<td><strong>' + r.barang + '</strong>'
                  + (r.merk ? '<br><span style="font-size:6.5pt;color:#555;">' + r.merk + '</span>' : '')
                  + '</td>'
                + '<td style="text-align:center;">' + r.qty + ' ' + r.satuan + '</td>'
                + '<td style="text-align:right;">' + r.harga + '</td>'
                + '<td style="text-align:right;font-weight:bold;">' + r.total + '</td>'
                + '<td style="font-size:6.5pt;">' + r.ket + '</td>'
                + '</tr>';
        });

        var isLast   = (pi === totalPages - 1);
        var leftFoot = isLast
            ? '<div style="margin-bottom:3px;">Total: <strong>' + d.totalItem + ' transaksi</strong></div>'
              + '<div class="total-box">TOTAL PEMBELIAN: ' + d.grandTotal + '</div>'
            : '<div style="color:#888;font-size:7pt;font-style:italic;">Bersambung ke halaman berikutnya...</div>';

        var ttdFoot = isLast
            ? '<div class="ttd-box"><div style="font-size:7pt;color:#555;">Mengetahui,</div>'
              + '<div class="ttd-line"></div><div><strong>MANAGER</strong></div></div>'
            : '';

        allPages +=
            '<div class="page-block">'
            + '<div class="header"><h2>PT. MUTIARA CAHAYA PLASTINDO</h2><h4>LAPORAN REALISASI PEMBELIAN HARIAN</h4></div>'
            + '<div class="info-bar">'
            + '<div>Petugas:&nbsp;<span class="petugas-name">' + d.petugas + '</span>'
            + (totalPages > 1 ? '<span class="page-badge">Hal. ' + (pi+1) + '/' + totalPages + '</span>' : '')
            + '</div>'
            + '<div>Periode: <strong>' + d.periode + '</strong></div>'
            + '<div>Cetak: ' + d.tglCetak + ' | Oleh: ' + d.oleh + '</div>'
            + '</div>'
            + '<table class="data"><thead><tr>'
            + '<th class="c-no">NO</th><th class="c-tgl">TGL</th><th class="c-sup">SUPPLIER</th>'
            + '<th class="c-brg">NAMA BARANG</th><th class="c-qty">QTY</th>'
            + '<th class="c-hrg">HARGA</th><th class="c-tot">TOTAL</th><th class="c-ket">KETERANGAN</th>'
            + '</tr></thead><tbody>' + tbody + '</tbody></table>'
            + '<div class="page-footer">'
            + '<div>' + leftFoot + '</div>'
            + ttdFoot
            + '<div style="text-align:right;align-self:flex-end;"><span class="form-no">Form No. ' + formNo + '</span></div>'
            + '</div>'
            + '</div>';
    });

    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8">'
        + '<title>Laporan Pembelian - ' + d.petugas + '</title>'
        + '<style>' + css + '</style></head><body>'
        + '<div class="print-ctrl">'
        + '<button class="btn-blue" onclick="window.print()">&#128438; CETAK (' + totalPages + ' halaman)</button>'
        + '<button class="btn-green" onclick="window.print();setTimeout(function(){window.close();},600)">CETAK &amp; TUTUP</button>'
        + '<button class="btn-gray" onclick="window.close()">BATAL</button>'
        + '<span style="font-size:8px;color:#888;margin-left:10px;">&#128196; ' + totalPages + ' halaman &nbsp;|&nbsp; Kertas: 21.5&times;16.5 cm Landscape</span>'
        + '</div>'
        + allPages + '</body></html>';

    var w = window.open('', '_blank', 'width=1000,height=720');
    w.document.write(html);
    w.document.close();
}

// Ctrl+P shortcut
$(document).keydown(function(e) {
    if (e.ctrlKey && e.keyCode == 80) { e.preventDefault(); cetakLaporan(); }
});
</script>


<script>
    let idleTime = 0;
    const maxIdleMinutes = 15;
    let lastServerUpdate = Date.now();

    // Fungsi untuk mereset timer idle
    function resetTimer() {
        idleTime = 0;
        
        let now = Date.now();
        // Kirim sinyal "Keep Alive" ke server setiap 5 menit sekali jika user aktif
        // Ini mencegah session PHP mati saat user sedang asyik mengetik/input
        if (now - lastServerUpdate > 300000) { // 300.000 ms = 5 menit
            const depth = window.location.pathname.split('/').length - 2;
            const prefix = "../".repeat(Math.max(0, depth - 1));
            
            fetch(prefix + 'auth/keep_alive.php')
                .then(response => console.log("Sesi diperbarui secara background"))
                .catch(err => console.error("Gagal memperbarui sesi", err));
            
            lastServerUpdate = now;
        }
    }

    // Deteksi interaksi user
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    // Interval cek setiap 1 menit
    setInterval(function() {
        idleTime++;
        if (idleTime >= maxIdleMinutes) {
            alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
            const depth = window.location.pathname.split('/').length - 2;
            const prefix = "../".repeat(Math.max(0, depth - 1));
            window.location.href = prefix + "login.php?pesan=timeout";
        }
    }, 60000); // Cek setiap 60 detik
</script>
</body>
</html>