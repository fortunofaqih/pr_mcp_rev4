<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$nama_user = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'ADMIN';

// 1. TANGKAP FILTER
$abjad_filter   = isset($_GET['abjad'])         ? mysqli_real_escape_string($koneksi, strtoupper($_GET['abjad'])) : '';
$tgl_min        = isset($_GET['tgl_min'])        ? $_GET['tgl_min']  : '';
$tgl_max        = isset($_GET['tgl_max'])        ? $_GET['tgl_max']  : '';
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
                <!-- Filter Tanggal -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">RENTANG TANGGAL NOTA</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="tgl_min" class="form-control" value="<?= $tgl_min ?>">
                        <input type="date" name="tgl_max" class="form-control" value="<?= $tgl_max ?>">
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
                    <a href="data_pembelian.php" class="btn btn-warning btn-sm w-100 fw-bold">
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
                <a href="data_pembelian.php" class="ms-2 text-danger fw-bold" style="font-size:11px;">
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
                        <th width="80">AKSI</th>
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
                        <td class="text-center">
                            <div class="btn-group">
                          
							<button class="btn btn-xs btn-warning btn-edit" 
								data-id="<?= $row['id_pembelian'] ?>"
								data-tgl="<?= $row['tgl_beli_barang'] ?>"
								data-barang="<?= htmlspecialchars($row['nama_barang_beli'] ?? '') ?>"
								data-merk="<?= htmlspecialchars($row['merk_beli'] ?? '') ?>"
								data-supplier="<?= htmlspecialchars($row['supplier'] ?? '') ?>"
								data-qty="<?= $row['qty'] ?>"
								data-harga="<?= $row['harga'] ?>"
								data-alokasi="<?= $row['alokasi_stok'] ?>"
								data-driver="<?= htmlspecialchars($row['driver'] ?? '') ?>"
								data-plat="<?= htmlspecialchars($row['plat_nomor'] ?? '') ?>"
								data-id-mobil="<?= (int)($row['id_mobil'] ?? 0) ?>"
								data-id-user-beli="<?= (int)($row['id_user_beli'] ?? 0) ?>"
								data-ket="<?= htmlspecialchars($row['keterangan'] ?? '') ?>">
								<i class="fas fa-edit"></i>
							</button>
                                 
                                <button class="btn btn-xs btn-info text-white" 
                                    onclick="aksiRetur('<?= $row['id_pembelian'] ?>', '<?= addslashes($row['nama_barang_beli']) ?>', '<?= (float)$row['qty'] ?>', '<?= addslashes($row['supplier']) ?>')">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <a href="hapus_pembelian_double.php?id=<?= $row['id_pembelian'] ?>" 
                                   class="btn btn-xs btn-danger" 
                                   onclick="return confirm('PERINGATAN!\n\nData ini akan dihapus permanen.\n\nYakin ingin menghapus?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="10" class="text-center p-4 text-muted">Data tidak ditemukan sesuai filter.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     MODAL EDIT DATA REALISASI
═══════════════════════════════════════════ -->
<div class="modal fade" id="modalEditBeli" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <form action="proses_edit_pembelian.php" method="POST">
                <div class="modal-header bg-warning">
                    <h6 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>EDIT DATA REALISASI</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_pembelian" id="edit_id">

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="small fw-bold">TANGGAL NOTA</label>
                            <input type="date" name="tgl_beli_barang" id="edit_tgl" class="form-control form-control-sm border-primary" required>
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">NAMA BARANG</label>
                            <input type="text" name="nama_barang" id="edit_barang" class="form-control form-control-sm fw-bold text-uppercase" readonly>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">MERK</label>
                            <input type="text" name="merk_beli" id="edit_merk" class="form-control form-control-sm text-uppercase">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">SUPPLIER / TOKO</label>
                            <input type="text" name="supplier" id="edit_supplier" class="form-control form-control-sm text-uppercase">
                        </div>
                    </div>

                    <div class="row g-2 mb-3 p-2 bg-light border rounded">
                        <div class="col-md-4">
                            <label class="small fw-bold text-primary">QTY</label>
                            <input type="number" name="qty" id="edit_qty" class="form-control form-control-sm hitung" step="any">
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold text-primary">TOTAL BAYAR (GLOBAL)</label>
                            <input type="number" id="edit_total_global" class="form-control form-control-sm hitung">
                            <input type="hidden" name="harga" id="edit_harga">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">ALOKASI STOK</label>
                            <select name="alokasi_stok" id="edit_alokasi" class="form-select form-select-sm fw-bold">
                                <option value="LANGSUNG PAKAI">LANGSUNG PAKAI</option>
                                <option value="MASUK STOK">MASUK STOK</option>
                            </select>
                        </div>
                     <div class="col-md-6">
					<label class="small fw-bold text-primary">PETUGAS PEMBELIAN</label>
					<select name="id_user_beli" id="edit_id_user_beli" class="form-select form-select-sm fw-bold border-primary" required>
						<option value="">-- Pilih Petugas --</option>
						<?php 
						// Filter: Hanya mengambil user yang rolenya 'bagian_pembelian' ATAU bagiannya 'Pembelian'
						$q_u = mysqli_query($koneksi, "SELECT id_user, nama_lengkap FROM users 
													   WHERE status_aktif = 'AKTIF' 
													   AND (role = 'bagian_pembelian' OR bagian = 'Pembelian') 
													   ORDER BY nama_lengkap ASC");
						while($u = mysqli_fetch_array($q_u)): 
						?>
							<option value="<?= $u['id_user'] ?>"><?= htmlspecialchars($u['nama_lengkap']) ?></option>
						<?php endwhile; ?>
					</select>
				</div>
                    </div>

                    <div class="row g-2">
                        <!-- PLAT NOMOR — dropdown dari master_mobil -->
                        <div class="col-md-4">
                            <label class="small fw-bold">
                                <i class="fas fa-car me-1 text-primary"></i>PLAT NOMOR
                            </label>
                            <input type="hidden" name="id_mobil" id="edit_id_mobil" value="0">
                            <select name="plat_nomor" id="edit_plat" class="form-select form-select-sm fw-bold">
                                <option value="">— Pilih Kendaraan —</option>
                                <?php foreach ($list_mobil as $m): ?>
                                    <option value="<?= htmlspecialchars($m['plat_nomor']) ?>"
                                            data-id="<?= $m['id_mobil'] ?>"
                                            data-driver="<?= htmlspecialchars($m['driver_tetap'] ?? '') ?>"
                                            data-merk="<?= htmlspecialchars($m['merk_tipe'] ?? '') ?>">
                                        <?= htmlspecialchars($m['plat_nomor']) ?>
                                        <?= !empty($m['merk_tipe'])    ? ' — ' . $m['merk_tipe']    : '' ?>
                                        <?= !empty($m['driver_tetap']) ? ' (' . $m['driver_tetap'] . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="info_mobil_terpilih" class="mt-1"></div>
                        </div>

                        <div class="col-md-8">
                            <label class="small fw-bold">KETERANGAN / CATATAN</label>
                            <input type="text" name="keterangan" id="edit_ket" class="form-control form-control-sm text-uppercase">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">BATAL</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold shadow-sm">
                        <i class="fas fa-save me-1"></i> SIMPAN PERUBAHAN
                    </button>
                </div>
            </form>
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

    $print_rows[] = [
        'tgl'      => $tgl_format,
        'supplier' => strtoupper(substr($row['supplier'] ?? '-', 0, 20)),
        'barang'   => strtoupper($row['nama_barang_beli']),
        'merk'     => strtoupper($row['merk_beli'] ?? ''),
        'qty'      => number_format((float)$row['qty'], 2, ',', '.'),
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
<script>
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

function aksiRetur(id, barang, qty, supplier) {
    let msg = `RETUR BARANG?\n--------------------------\nBarang : ${barang}\nQty    : ${qty}\nToko   : ${supplier}\n--------------------------\nMasukkan alasan retur:`;
    let alasan = prompt(msg);
    if (alasan) {
        window.location.href = `proses_retur_pembelian.php?id=${id}&alasan=${encodeURIComponent(alasan)}`;
    }
}

$(document).ready(function() {

    // Highlight select petugas
    $('select[name="id_user_beli"]').on('change', function() {
        $(this).toggleClass('select-petugas-aktif', $(this).val() > 0);
    });

  // ── Buka modal edit ──────────────────────────────────────
	$(document).on('click', '.btn-edit', function() {
		var d = $(this).data();

		$('#edit_id').val(d.id);
		$('#edit_tgl').val(d.tgl);
		$('#edit_barang').val(d.barang);
		$('#edit_merk').val(d.merk);
		$('#edit_supplier').val(d.supplier);
		$('#edit_qty').val(d.qty);
		$('#edit_total_global').val(Math.round(parseFloat(d.qty) * parseFloat(d.harga)));
		$('#edit_harga').val(d.harga);
		$('#edit_alokasi').val(d.alokasi);
		$('#edit_ket').val(d.ket);

		// --- TAMBAHAN UNTUK PETUGAS PEMBELIAN ---
		
		$('#edit_id_user_beli').val(d.idUserBeli).trigger('change'); 

		// — Bagian Plat Nomor (Versi Perbaikan) —
			var idMobil = parseInt(d.idMobil) || 0;

			if (idMobil > 0) {
				// Cari option yang punya data-id cocok, lalu pilih
				$('#edit_plat option').filter(function() {
					return $(this).data('id') == idMobil;
				}).prop('selected', true);
			} else {
				$('#edit_plat').val(d.plat);
			}

			// PENTING: Trigger change agar id_mobil hidden ikut terupdate
			$('#edit_plat').trigger('change'); 

			// Pastikan id_mobil terisi dengan benar setelah trigger
			var selectedOpt = $('#edit_plat option:selected');
			$('#edit_id_mobil').val(selectedOpt.data('id') || 0);

		// Bagian Driver bisa dihapus jika kolomnya sudah tidak digunakan
		// Atau jika masih ingin menampilkan driver tetap dari mobil:
		$('#edit_driver').val(selectedOpt.data('driver') || '');

		updateInfoMobil(selectedOpt);
		$('#modalEditBeli').modal('show');
	});

    // — Ganti kendaraan: update hidden id_mobil & driver —
    $('#edit_plat').on('change', function() {
        var opt = $(this).find('option:selected');
        $('#edit_id_mobil').val(opt.data('id') || 0);
        if (opt.val() && opt.data('driver')) {
            $('#edit_driver').val(opt.data('driver'));
        }
        updateInfoMobil(opt);
    });

       // — Hitung Otomatis di Modal Edit —
	$('.hitung').on('input', function() {
		var qty         = parseFloat($('#edit_qty').val()) || 0;
		var totalGlobal = parseFloat($('#edit_total_global').val()) || 0;
		var hargaSatuan = parseFloat($('#edit_harga').val()) || 0;

		// Jika yang sedang diketik adalah Qty (id="edit_qty")
		// Maka kita update Total Global-nya (Qty * Harga Satuan)
		if ($(this).attr('id') === 'edit_qty' && hargaSatuan > 0) {
			var baruTotal = Math.round(qty * hargaSatuan);
			$('#edit_total_global').val(baruTotal);
		} 
		
		// Jika yang sedang diketik adalah Total Global (id="edit_total_global")
		// Maka kita update Harga Satuannya (Total / Qty)
		else if ($(this).attr('id') === 'edit_total_global' && qty > 0) {
			var baruHarga = Math.round(totalGlobal / qty);
			$('#edit_harga').val(baruHarga);
		}
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
                + '<td style="text-align:center;">' + r.qty + '</td>'
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
(function() {
    const params = new URLSearchParams(window.location.search);
    const pesan  = params.get('pesan');
    const errMsg = params.get('error');

    if (pesan === 'retur_sukses') {
        Swal.fire({
            icon: 'success', title: 'Retur Berhasil!',
            html: 'Barang telah diretur ke toko.<br><small class="text-muted">Stok dan status PR sudah dikembalikan.</small>',
            confirmButtonText: 'OK', confirmButtonColor: '#0000FF'
        });
    } else if (pesan === 'retur_gagal') {
        Swal.fire({
            icon: 'error', title: 'Retur Gagal!',
            text: errMsg ? decodeURIComponent(errMsg) : 'Terjadi kesalahan saat proses retur.',
            confirmButtonText: 'Tutup', confirmButtonColor: '#d33'
        });
    } else if (pesan === 'invalid') {
        Swal.fire({
            icon: 'warning', title: 'Permintaan Tidak Valid',
            text: 'Data retur tidak ditemukan atau parameter tidak lengkap.',
            confirmButtonColor: '#f59e0b'
        });
    } else if (pesan === 'edit_sukses') {
        Swal.fire({
            icon: 'success', title: 'Data Berhasil Diupdate!',
            html: 'Data pembelian & kendaraan telah disimpan.',
            confirmButtonText: 'OK', confirmButtonColor: '#0000FF'
        });
    } else if (pesan === 'edit_gagal') {
        Swal.fire({
            icon: 'error', title: 'Update Gagal!',
            text: errMsg ? decodeURIComponent(errMsg) : 'Terjadi kesalahan saat menyimpan data.',
            confirmButtonText: 'Tutup', confirmButtonColor: '#d33'
        });
    }

    if (pesan) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
})();
</script>
</body>
</html>