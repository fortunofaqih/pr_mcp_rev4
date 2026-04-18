<?php
include "../../config/koneksi.php";
include '../../auth/check_session.php';

// 1. TANGKAP FILTER (Letakkan di paling atas)
$alokasi_filter = isset($_GET['alokasi_filter']) ? $_GET['alokasi_filter'] : 'SEMUA';
$keyword         = isset($_GET['keyword']) ? mysqli_real_escape_string($koneksi, strtoupper($_GET['keyword'])) : '';
$abjad_filter   = isset($_GET['abjad']) ? mysqli_real_escape_string($koneksi, strtoupper($_GET['abjad'])) : '';

// 2. BANGUN QUERY STRING (Untuk menjaga filter tetap aktif saat pindah halaman/abjad)
$query_string = "&alokasi_filter=$alokasi_filter&keyword=$keyword";

// 3. PENGATURAN PAGINATION
$limit = 50; 
$page  = isset($_GET['halaman']) ? (int)$_GET['halaman'] : 1;
$offset = ($page > 1) ? ($page * $limit) - $limit : 0;

// 4. LOGIKA SQL FILTER
$filter_sql = " WHERE 1=1 ";
if ($keyword != '') { 
    $filter_sql .= " AND p.nama_barang LIKE '%$keyword%' "; 
}
if ($abjad_filter != '' && $abjad_filter != 'ALL') { 
    $filter_sql .= " AND p.nama_barang LIKE '$abjad_filter%' "; 
}
if ($alokasi_filter != 'SEMUA') { 
    $filter_sql .= " AND p.alokasi_stok = '$alokasi_filter' "; 
}

// 5. HITUNG TOTAL DATA UNTUK PAGINATION
$total_query = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM perbandingan_harga p $filter_sql");
$total_data  = mysqli_fetch_assoc($total_query)['total'];
$total_pages = ceil($total_data / $limit);

// 6. AMBIL DATA UTAMA
$sql = "SELECT p.*, m.merk as merk_master 
        FROM perbandingan_harga p 
        LEFT JOIN master_barang m ON p.nama_barang = m.nama_barang 
        $filter_sql 
        ORDER BY p.tgl_data DESC LIMIT $limit OFFSET $offset";

$query = mysqli_query($koneksi, $sql);
$data_tampil = [];
$harga_array = [];
while($row = mysqli_fetch_assoc($query)) {
    $data_tampil[] = $row;
    if($row['harga'] > 0) { $harga_array[] = $row['harga']; }
}
$harga_termurah = (count($harga_array) > 0) ? min($harga_array) : 0;

// Query string khusus untuk pagination (membawa abjad juga)
$pagination_string = $query_string . "&abjad=$abjad_filter";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Perbandingan Harga - MCP System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .search-box, .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 20px; }
        .row-termurah { background-color: #f0fff4 !important; border-left: 5px solid #198754; }
        .badge-termurah { background-color: #198754; color: white; padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .alphabet-nav { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 15px; }
        .btn-abjad { padding: 5px 10px; font-size: 11px; font-weight: bold; border: 1px solid #dee2e6; background: white; color: #333; text-decoration: none; border-radius: 4px; transition: 0.2s; }
        .btn-abjad.active, .btn-abjad:hover { background: var(--mcp-blue); color: white; border-color: var(--mcp-blue); }
        .btn-delete { color: #dc3545; }
        .text-keterangan { font-size: 10px; color: #6c757d; font-style: italic; display: block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body class="pb-5">

<nav class="navbar navbar-mcp mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white small"><i class="fas fa-balance-scale me-2"></i> PERBANDINGAN HARGA</span>
        <div>
            <a href="form_input_historis.php" class="btn btn-success btn-sm fw-bold me-2"><i class="fas fa-plus-circle me-1"></i> INPUT DATA</a>
            <a href="../../index.php" class="btn btn-danger btn-sm px-3 fw-bold"><i class="fas fa-rotate-left me-1"></i> KEMBALI</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="search-box mb-4">
        <form action="" method="GET">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">ALOKASI</label>
                    <select name="alokasi_filter" class="form-select border-primary fw-bold" onchange="this.form.submit()">
                        <option value="SEMUA" <?= $alokasi_filter == 'SEMUA' ? 'selected' : '' ?>>SEMUA DATA</option>
                        <option value="LANGSUNG PAKAI" <?= $alokasi_filter == 'LANGSUNG PAKAI' ? 'selected' : '' ?>>LANGSUNG PAKAI</option>
                        <option value="MASUK STOK" <?= $alokasi_filter == 'MASUK STOK' ? 'selected' : '' ?>>MASUK STOK</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label class="form-label small fw-bold text-muted">CARI NAMA BARANG</label>
                    <div class="input-group">
                        <input type="text" name="keyword" class="form-control" placeholder="KETIK NAMA BARANG..." value="<?= $keyword ?>">
                        <input type="hidden" name="abjad" value="<?= $abjad_filter ?>">
                        <button type="submit" class="btn btn-primary fw-bold px-4">CARI</button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="data_perbandingan.php" class="btn btn-warning w-100 fw-bold">RESET</a>
                </div>
            </div>
            
            <div class="alphabet-nav">
                <span class="small fw-bold text-muted me-2 align-self-center">HURUF DEPAN:</span>
                <a href="?abjad=ALL<?= $query_string ?>" class="btn-abjad <?= ($abjad_filter == '' || $abjad_filter == 'ALL') ? 'active' : '' ?>">ALL</a>
                <?php foreach (range('A', 'Z') as $char): ?>
                    <a href="?abjad=<?= $char . $query_string ?>" class="btn-abjad <?= ($abjad_filter == $char) ? 'active' : '' ?>"><?= $char ?></a>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <div class="table-container shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100">
                <thead>
                    <tr class="small text-uppercase bg-light border-bottom">
                        <th class="text-center" width="5%">No</th>
                        <th class="text-center" width="12%">Tanggal Beli</th>
                        <th width="25%">Nama Barang & Merk</th>
                        <th width="15%">Supplier</th>
                        <th width="18%">Keterangan</th>
                        <th class="text-end" width="15%">Harga Satuan</th>
                        <th class="text-center" width="10%">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small text-uppercase">
                    <?php 
                    $no = $offset + 1;
                    if(!empty($data_tampil)):
                        foreach($data_tampil as $row): 
                            $is_termurah = ($row['harga'] == $harga_termurah && $harga_termurah > 0);
                            $merk_tampil = !empty($row['merk']) ? $row['merk'] : $row['merk_master'];
                    ?>
                    <tr class="<?= $is_termurah ? 'row-termurah' : '' ?>">
                        <td class="text-center text-muted"><?= $no++ ?></td>
                        <td class="text-center">
                            <div class="fw-bold"><?= date('d/m/Y', strtotime($row['tgl_data'])) ?></div>
                            <small class="text-muted" style="font-size: 9px;"><?= $row['no_request'] ?></small>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= $row['nama_barang'] ?></div>
                            <div class="text-primary fw-bold" style="font-size: 10px;"><i class="fas fa-tag me-1"></i> <?= $merk_tampil ?: '-' ?></div>
                        </td>
                        <td class="fw-bold text-muted"><?= $row['supplier'] ?></td>
                        <td>
                            <span class="text-keterangan">
                                <?= !empty($row['keterangan']) ? $row['keterangan'] : '-' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if($is_termurah): ?>
                                <span class="badge-termurah mb-1"><i class="fas fa-check-circle"></i> TERMURAH</span><br>
                            <?php endif; ?>
                            <span class="fw-bold <?= $is_termurah ? 'text-success fs-6' : 'text-danger' ?>">
                                Rp <?= number_format($row['harga'], 0, ',', '.') ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="proses_hapus_perbandingan.php?id=<?= $row['id_perbandingan'] ?>" class="btn-delete" onclick="return confirm('Hapus data ini?')"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" class="text-center p-5 text-muted">Data tidak ditemukan pada filter ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination pagination-sm justify-content-center">
                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?halaman=<?= $page - 1 . $pagination_string ?>">Previous</a>
                </li>
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                        <a class="page-link" href="?halaman=<?= $i . $pagination_string ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                    <a class="page-link" href="?halaman=<?= $page + 1 . $pagination_string ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>