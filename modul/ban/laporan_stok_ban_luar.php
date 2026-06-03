<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

$user_login = $_SESSION['nama'] ?? 'Unknown';

// ═══════════════════════════════════════════════════════════════
// PROSES: Keluar Stok Ban (dirombeng / dijual)
// ═══════════════════════════════════════════════════════════════
$pesan      = '';
$pesan_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'keluar_stok') {

    $nama_barang   = mysqli_real_escape_string($koneksi, trim($_POST['nama_barang']));
    $qty_keluar    = (float)  $_POST['qty_keluar'];
    $alasan_keluar = mysqli_real_escape_string($koneksi, trim($_POST['alasan_keluar']));
    $tgl_keluar    = mysqli_real_escape_string($koneksi, trim($_POST['tgl_keluar']));
    $keterangan    = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));

    // Validasi stok mencukupi untuk nama barang tsb
    $sql_cek_stok = "
        SELECT 
            COALESCE(SUM(CASE WHEN tipe_transaksi='MASUK'  THEN qty ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN tipe_transaksi='KELUAR' THEN qty ELSE 0 END), 0) AS stok_nama
        FROM stok_ban_luar
        WHERE nama_barang = '$nama_barang'
    ";
    $res_cek   = mysqli_query($koneksi, $sql_cek_stok);
    $row_cek   = mysqli_fetch_assoc($res_cek);
    $stok_nama = $row_cek['stok_nama'] ?? 0;

    if ($qty_keluar <= 0) {
        $pesan      = 'Qty keluar harus lebih dari 0.';
        $pesan_type = 'danger';
    } elseif ($qty_keluar > $stok_nama) {
        $pesan      = "Stok <strong>$nama_barang</strong> tidak mencukupi. Stok tersedia: <strong>$stok_nama pcs</strong>.";
        $pesan_type = 'danger';
    } else {
        $sql_ins = "INSERT INTO stok_ban_luar
                        (no_request, nama_barang, qty, tipe_transaksi, alasan_keluar,
                         tgl_transaksi, input_oleh, keterangan)
                    VALUES
                        (NULL, '$nama_barang', $qty_keluar, 'KELUAR', '$alasan_keluar',
                         '$tgl_keluar', '$user_login', '$keterangan')";

        if (mysqli_query($koneksi, $sql_ins)) {
            $pesan      = "Pengurangan stok <strong>$nama_barang</strong> sejumlah <strong>$qty_keluar pcs</strong> berhasil dicatat.";
            $pesan_type = 'success';
        } else {
            $pesan      = 'Gagal menyimpan: ' . mysqli_error($koneksi);
            $pesan_type = 'danger';
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// FILTER TANGGAL
// ═══════════════════════════════════════════════════════════════
$filter_dari  = $_GET['dari']  ?? date('Y-m-01');
$filter_sampai = $_GET['sampai'] ?? date('Y-m-d');
$filter_tipe   = $_GET['tipe']   ?? '';

$where_tgl  = "WHERE tgl_transaksi BETWEEN '$filter_dari' AND '$filter_sampai'";
$where_tipe = $filter_tipe ? " AND tipe_transaksi = '$filter_tipe'" : '';

// ═══════════════════════════════════════════════════════════════
// QUERY LAPORAN LOG
// ═══════════════════════════════════════════════════════════════
$sql_log = "
    SELECT s.*, m.plat_nomor AS plat_mobil, m.driver_tetap, m.jenis_kendaraan, m.merk_tipe
    FROM stok_ban_luar s
    LEFT JOIN master_mobil m ON s.id_mobil = m.id_mobil
    $where_tgl $where_tipe
    ORDER BY s.tgl_transaksi DESC, s.id_stok DESC
";
$res_log  = mysqli_query($koneksi, $sql_log);
$list_log = [];
while ($row = mysqli_fetch_assoc($res_log)) {
    $list_log[] = $row;
}
mysqli_free_result($res_log);

// ═══════════════════════════════════════════════════════════════
// STATISTIK STOK KESELURUHAN (tidak terpengaruh filter tanggal)
// ═══════════════════════════════════════════════════════════════
$sql_stat = "
    SELECT
        COALESCE(SUM(CASE WHEN tipe_transaksi='MASUK'  THEN qty ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN tipe_transaksi='KELUAR' THEN qty ELSE 0 END), 0) AS total_keluar
    FROM stok_ban_luar
";
$res_stat    = mysqli_query($koneksi, $sql_stat);
$row_stat    = mysqli_fetch_assoc($res_stat);
$total_masuk  = $row_stat['total_masuk']  ?? 0;
$total_keluar = $row_stat['total_keluar'] ?? 0;
$stok_akhir   = $total_masuk - $total_keluar;

// ═══════════════════════════════════════════════════════════════
// REKAP STOK PER NAMA BARANG (untuk dropdown keluar stok)
// ═══════════════════════════════════════════════════════════════
$sql_per_nama = "
    SELECT
        nama_barang,
        COALESCE(SUM(CASE WHEN tipe_transaksi='MASUK'  THEN qty ELSE 0 END), 0) AS s_masuk,
        COALESCE(SUM(CASE WHEN tipe_transaksi='KELUAR' THEN qty ELSE 0 END), 0) AS s_keluar,
        COALESCE(SUM(CASE WHEN tipe_transaksi='MASUK'  THEN qty ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN tipe_transaksi='KELUAR' THEN qty ELSE 0 END), 0) AS s_akhir
    FROM stok_ban_luar
    GROUP BY nama_barang
    ORDER BY nama_barang ASC
";
$res_per_nama  = mysqli_query($koneksi, $sql_per_nama);
$list_per_nama = [];
while ($r = mysqli_fetch_assoc($res_per_nama)) {
    $list_per_nama[] = $r;
}
mysqli_free_result($res_per_nama);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok Ban Luar - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-mcp { background: var(--mcp-blue); }

        .stat-card {
            border: none; border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.09);
            transition: transform 0.25s; overflow: hidden; position: relative;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-icon { font-size: 3rem; opacity: 0.13; position: absolute; right: 18px; bottom: 8px; }

        .main-card { border: none; border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,0.07); }

        table.dataTable thead th {
            vertical-align: middle; text-align: center;
            background-color: #eef1f8;
            font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.03em;
        }
        table.dataTable tbody td { vertical-align: middle; font-size: 0.82rem; }

        /* Badge tipe transaksi */
        .badge-masuk  { background:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:20px; padding:4px 12px; font-size:0.72rem; font-weight:700; }
        .badge-keluar { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:20px; padding:4px 12px; font-size:0.72rem; font-weight:700; }

        .tr-masuk  { background-color: #f6fff8 !important; }
        .tr-keluar { background-color: #fff8f8 !important; }
        .table-hover tbody tr.tr-masuk:hover  { background-color: #e6f9ea !important; }
        .table-hover tbody tr.tr-keluar:hover { background-color: #fde8e8 !important; }

        /* Filter bar */
        .filter-bar { background: #fff; border-radius: 12px; padding: 16px 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-bottom: 1.2rem; }

        /* Rekap per nama */
        .rekap-card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.07); }
        .rekap-card .table thead th { background: #f1f4f9; font-size: 0.75rem; text-transform: uppercase; }
        .stok-ok      { color: #198754; font-weight: 700; }
        .stok-tipis   { color: #856404; font-weight: 700; }
        .stok-kosong  { color: #dc3545; font-weight: 700; }

        /* Modal */
        .modal-header-keluar { background: linear-gradient(135deg,#dc3545,#8b0000); color:#fff; border-radius:12px 12px 0 0; }
        .modal-content { border-radius: 14px; border: none; }
        .form-label { font-size: 0.82rem; font-weight: 600; color: #444; }
        .form-control, .form-select { font-size: 0.83rem; border-radius: 8px; }

        .btn-keluar-stok {
            background: linear-gradient(135deg,#dc3545,#c0392b);
            color: #fff; border: none; border-radius: 8px;
            font-size: 0.75rem; font-weight: 600; padding: 6px 12px;
            transition: opacity 0.2s; white-space: nowrap;
        }
        .btn-keluar-stok:hover { opacity: 0.85; color:#fff; }
    </style>
</head>
<body>

<!-- ═══ NAVBAR ═══ -->
<nav class="navbar navbar-mcp mb-4 py-2">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white fs-6">
            <i class="fas fa-warehouse me-2"></i> LAPORAN STOK BAN LUAR
        </span>
        <div class="d-flex gap-2">
            <a href="cek_status_ban_luar.php" class="btn btn-sm btn-warning fw-bold px-3">
                <i class="fas fa-tire me-1"></i> CEK PEMBELIAN BAN
            </a>
            <a href="../../index.php" class="btn btn-sm btn-danger fw-bold px-3">
                <i class="fas fa-rotate-left me-1"></i> KEMBALI
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">

    <!-- ═══ ALERT ═══ -->
    <?php if ($pesan): ?>
    <div class="alert alert-<?= $pesan_type ?> alert-dismissible fade show shadow-sm rounded-3 mb-3" role="alert">
        <i class="fas fa-<?= $pesan_type === 'success' ? 'check-circle' : 'circle-xmark' ?> me-2"></i>
        <?= $pesan ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ═══ STAT CARDS ═══ -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card card text-white h-100" style="background:linear-gradient(135deg,#198754,#0f5132);">
                <div class="card-body py-3">
                    <div class="small fw-bold text-uppercase opacity-75">Stok Ban di Gudang</div>
                    <h2 class="fw-bold mb-0 mt-1">
                        <?= number_format($stok_akhir, 0, ',', '.') ?>
                        <small class="fs-6 fw-normal">pcs</small>
                    </h2>
                    <i class="fas fa-warehouse stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card text-white h-100" style="background:linear-gradient(135deg,#0000FF,#003399);">
                <div class="card-body py-3">
                    <div class="small fw-bold text-uppercase opacity-75">Total Masuk (Kumulatif)</div>
                    <h2 class="fw-bold mb-0 mt-1">
                        <?= number_format($total_masuk, 0, ',', '.') ?>
                        <small class="fs-6 fw-normal">pcs</small>
                    </h2>
                    <i class="fas fa-arrow-down stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card text-white h-100" style="background:linear-gradient(135deg,#dc3545,#8b0000);">
                <div class="card-body py-3">
                    <div class="small fw-bold text-uppercase opacity-75">Total Keluar (Kumulatif)</div>
                    <h2 class="fw-bold mb-0 mt-1">
                        <?= number_format($total_keluar, 0, ',', '.') ?>
                        <small class="fs-6 fw-normal">pcs</small>
                    </h2>
                    <i class="fas fa-arrow-up stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card card text-dark h-100" style="background:linear-gradient(135deg,#ffc107,#e0a800);">
                <div class="card-body py-3">
                    <div class="small fw-bold text-uppercase opacity-75">Jenis Ban Berbeda</div>
                    <h2 class="fw-bold mb-0 mt-1">
                        <?= count($list_per_nama) ?>
                        <small class="fs-6 fw-normal">item</small>
                    </h2>
                    <i class="fas fa-tire stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">

        <!-- ═══ REKAP STOK PER NAMA BARANG ═══ -->
        <div class="col-md-5">
            <div class="rekap-card card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-primary">
                            <i class="fas fa-boxes-stacked me-2"></i>Rekap Stok per Jenis Ban
                        </h6>
                        <button type="button" class="btn-keluar-stok btn"
                                data-bs-toggle="modal" data-bs-target="#modalKeluarStok">
                            <i class="fas fa-arrow-up me-1"></i> KELUAR STOK
                        </button>
                    </div>
                    <div class="table-responsive" style="max-height:350px; overflow-y:auto;">
                        <table class="table table-bordered table-hover table-sm align-middle">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Nama Ban</th>
                                    <th class="text-center">Masuk</th>
                                    <th class="text-center">Keluar</th>
                                    <th class="text-center">Stok</th>
                                </tr>
                            </thead>
                            <tbody class="small">
                            <?php if (empty($list_per_nama)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>Belum ada data stok ban.
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($list_per_nama as $n): ?>
                                <?php
                                    $cls_stok = 'stok-ok';
                                    if ($n['s_akhir'] <= 0)    $cls_stok = 'stok-kosong';
                                    elseif ($n['s_akhir'] <= 3) $cls_stok = 'stok-tipis';
                                ?>
                                <tr>
                                    <td class="fw-bold text-uppercase" style="font-size:0.75rem;">
                                        <?= htmlspecialchars($n['nama_barang']) ?>
                                    </td>
                                    <td class="text-center text-primary fw-bold">
                                        <?= rtrim(rtrim(number_format($n['s_masuk'], 4, ',', '.'), '0'), ',') ?>
                                    </td>
                                    <td class="text-center text-danger fw-bold">
                                        <?= rtrim(rtrim(number_format($n['s_keluar'], 4, ',', '.'), '0'), ',') ?>
                                    </td>
                                    <td class="text-center <?= $cls_stok ?>">
                                        <?= rtrim(rtrim(number_format($n['s_akhir'], 4, ',', '.'), '0'), ',') ?>
                                        <?php if ($n['s_akhir'] <= 0): ?>
                                            <span class="d-block badge bg-danger" style="font-size:0.6rem;">KOSONG</span>
                                        <?php elseif ($n['s_akhir'] <= 3): ?>
                                            <span class="d-block badge bg-warning text-dark" style="font-size:0.6rem;">TIPIS</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ FILTER LOG ═══ -->
        <div class="col-md-7 d-flex flex-column">
            <div class="filter-bar mb-0 flex-shrink-0">
                <form method="GET" action="laporan_stok_ban_luar.php" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label mb-1 small fw-bold">Dari Tanggal</label>
                        <input type="date" name="dari" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($filter_dari) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                        <input type="date" name="sampai" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($filter_sampai) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1 small fw-bold">Tipe</label>
                        <select name="tipe" class="form-select form-select-sm">
                            <option value="">Semua</option>
                            <option value="MASUK"  <?= $filter_tipe === 'MASUK'  ? 'selected' : '' ?>>MASUK</option>
                            <option value="KELUAR" <?= $filter_tipe === 'KELUAR' ? 'selected' : '' ?>>KELUAR</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Ringkasan periode filter -->
            <?php
                $sum_masuk_filter  = 0;
                $sum_keluar_filter = 0;
                foreach ($list_log as $l) {
                    if ($l['tipe_transaksi'] === 'MASUK')  $sum_masuk_filter  += $l['qty'];
                    if ($l['tipe_transaksi'] === 'KELUAR') $sum_keluar_filter += $l['qty'];
                }
            ?>
            <div class="row g-2 mt-1 flex-grow-1">
                <div class="col-6">
                    <div class="card border-0 shadow-sm h-100 text-center" style="border-radius:10px; background:#f0fff4;">
                        <div class="card-body py-2">
                            <div class="small text-muted text-uppercase fw-bold">Masuk (Periode)</div>
                            <div class="fw-bold text-success fs-5"><?= number_format($sum_masuk_filter, 0, ',', '.') ?> pcs</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm h-100 text-center" style="border-radius:10px; background:#fff5f5;">
                        <div class="card-body py-2">
                            <div class="small text-muted text-uppercase fw-bold">Keluar (Periode)</div>
                            <div class="fw-bold text-danger fs-5"><?= number_format($sum_keluar_filter, 0, ',', '.') ?> pcs</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ TABEL LOG TRANSAKSI ═══ -->
    <div class="main-card card mb-5">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="fas fa-clock-rotate-left me-2"></i>
                    Log Transaksi Stok Ban Luar
                    <span class="badge bg-secondary ms-2"><?= count($list_log) ?> transaksi</span>
                </h6>
                <small class="text-muted">
                    Periode: <?= date('d/m/Y', strtotime($filter_dari)) ?>
                    — <?= date('d/m/Y', strtotime($filter_sampai)) ?>
                </small>
            </div>

            <div class="table-responsive">
                <table id="tabelLog" class="table table-hover table-bordered align-middle w-100">
                    <thead>
                        <tr>
                            <th width="4%">No</th>
                            <th>Tgl Transaksi</th>
                            <th class="text-center">Tipe</th>
                            <th>No. PR</th>
                            <th>Nama Ban</th>
                            <th class="text-center">Qty</th>
                            <th>Plat / Kendaraan</th>
                            <th>Driver</th>
                            <th>Alasan Keluar</th>
                            <th>Keterangan</th>
                            <th>Input Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                    <?php if (empty($list_log)): ?>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="text-center text-muted py-5" colspan="1">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                Tidak ada data transaksi pada periode yang dipilih.
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($list_log as $l):
                            $tr_cls = ($l['tipe_transaksi'] === 'MASUK') ? 'tr-masuk' : 'tr-keluar';
                            $plat_tampil = $l['plat_nomor'] ?: $l['plat_mobil'] ?: '';
                            $driver_tampil = $l['driver'] ?: $l['driver_tetap'] ?: '-';
                        ?>
                        <tr class="<?= $tr_cls ?>">
                            <td class="text-center text-muted"><?= $no++ ?></td>
                            <td><?= date('d/m/Y', strtotime($l['tgl_transaksi'])) ?></td>
                            <td class="text-center">
                                <?php if ($l['tipe_transaksi'] === 'MASUK'): ?>
                                    <span class="badge-masuk"><i class="fas fa-arrow-down me-1"></i>MASUK</span>
                                <?php else: ?>
                                    <span class="badge-keluar"><i class="fas fa-arrow-up me-1"></i>KELUAR</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $l['no_request']
                                    ? '<span class="badge bg-primary rounded-pill px-2">' . htmlspecialchars($l['no_request']) . '</span>'
                                    : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td class="fw-bold text-uppercase"><?= htmlspecialchars($l['nama_barang']) ?></td>
                            <td class="text-center fw-bold <?= $l['tipe_transaksi'] === 'MASUK' ? 'text-success' : 'text-danger' ?>">
                                <?= ($l['tipe_transaksi'] === 'KELUAR' ? '-' : '+') ?>
                                <?= rtrim(rtrim(number_format($l['qty'], 4, ',', '.'), '0'), ',') ?> pcs
                            </td>
                            <td>
                                <?= $plat_tampil
                                    ? '<span class="badge bg-dark rounded-pill px-2">' . htmlspecialchars($plat_tampil) . '</span>'
                                    : '<span class="text-muted">-</span>' ?>
                                <?php if ($l['merk_tipe']): ?>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($l['merk_tipe']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($driver_tampil) ?></td>
                            <td>
                                <?php if ($l['alasan_keluar']): ?>
                                    <span class="badge bg-danger"><?= htmlspecialchars($l['alasan_keluar']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted" style="max-width:180px; white-space:normal;">
                                <?= htmlspecialchars($l['keterangan'] ?: '-') ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <i class="fas fa-user-circle me-1"></i>
                                    <?= htmlspecialchars($l['input_oleh'] ?: '-') ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL: Keluar Stok (Rombeng / Dijual)
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalKeluarStok" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content shadow-lg">
            <div class="modal-header modal-header-keluar">
                <h6 class="modal-title fw-bold">
                    <i class="fas fa-arrow-up me-2"></i> Pengurangan Stok Ban Luar
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="laporan_stok_ban_luar.php<?= http_build_query(['dari' => $filter_dari, 'sampai' => $filter_sampai, 'tipe' => $filter_tipe]) ? '?' . http_build_query(['dari' => $filter_dari, 'sampai' => $filter_sampai, 'tipe' => $filter_tipe]) : '' ?>" id="formKeluarStok">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="aksi" value="keluar_stok">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama Ban / Barang <span class="text-danger">*</span></label>
                            <select name="nama_barang" id="sel_nama_barang" class="form-select" required>
                                <option value="">-- Pilih Nama Ban --</option>
                                <?php foreach ($list_per_nama as $n): ?>
                                    <?php if ($n['s_akhir'] > 0): ?>
                                    <option value="<?= htmlspecialchars($n['nama_barang']) ?>"
                                            data-stok="<?= (float)$n['s_akhir'] ?>">
                                        <?= htmlspecialchars($n['nama_barang']) ?>
                                        (Stok: <?= rtrim(rtrim(number_format($n['s_akhir'], 4, ',', '.'), '0'), ',') ?> pcs)
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="info_stok_nama">
                                Pilih nama ban untuk melihat stok tersedia.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jumlah Keluar <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="qty_keluar" id="inp_qty_keluar"
                                       class="form-control" min="0.01" step="0.01" required>
                                <span class="input-group-text">pcs</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alasan Keluar <span class="text-danger">*</span></label>
                            <select name="alasan_keluar" class="form-select" required>
                                <option value="">-- Pilih --</option>
                                <option value="DIROMBENG">DIROMBENG</option>
                                <option value="DIJUAL">DIJUAL</option>
                                <option value="LAINNYA">LAINNYA</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tanggal Keluar <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_keluar" class="form-control"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Petugas Input</label>
                            <input type="text" class="form-control bg-light"
                                   value="<?= htmlspecialchars($user_login) ?>" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2"
                                      placeholder="Misal: Ban dikarenakan sudah tidak layak pakai..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-danger rounded-3 fw-bold px-4">
                        <i class="fas fa-arrow-up me-1"></i> KURANGI STOK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ SCRIPTS ═══ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {

    // ── DataTable ──────────────────────────────────────────────
    $('#tabelLog').DataTable({
        pageLength : 25,
        language   : { url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json' },
        order      : [[1, 'desc']],
        columnDefs : [{ orderable: false, targets: [0, 2, 8] }]
    });

    // ── Info stok saat pilih nama ban ────────────────────────
    $('#sel_nama_barang').on('change', function () {
        const stok = $(this).find('option:selected').data('stok') || 0;
        const nama = $(this).find('option:selected').text();
        if ($(this).val()) {
            $('#info_stok_nama').html(
                '<i class="fas fa-info-circle text-success me-1"></i>' +
                'Stok tersedia: <strong class="text-success">' + stok + ' pcs</strong>. ' +
                'Masukkan jumlah yang akan dikeluarkan.'
            );
            $('#inp_qty_keluar').attr('max', stok);
        } else {
            $('#info_stok_nama').text('Pilih nama ban untuk melihat stok tersedia.');
        }
    });

    // ── Validasi sebelum submit keluar stok ──────────────────
    $('#formKeluarStok').on('submit', function (e) {
        const stok = parseFloat($('#sel_nama_barang').find('option:selected').data('stok') || 0);
        const qty  = parseFloat($('#inp_qty_keluar').val() || 0);
        const nama = $('#sel_nama_barang').find('option:selected').val();

        if (!nama) {
            e.preventDefault();
            alert('Pilih nama ban terlebih dahulu.');
            return;
        }
        if (qty <= 0) {
            e.preventDefault();
            alert('Jumlah keluar harus lebih dari 0.');
            return;
        }
        if (qty > stok) {
            e.preventDefault();
            alert('Jumlah keluar (' + qty + ') melebihi stok tersedia (' + stok + ' pcs).');
            return;
        }
        if (!confirm('Kurangi stok ban sebanyak ' + qty + ' pcs?\nPastikan data sudah benar.')) {
            e.preventDefault();
        }
    });
});
</script>

<!-- ── Idle Timeout ── -->
<script>
let idleTime = 0;
const maxIdleMinutes = 15;
let lastServerUpdate = Date.now();

function resetTimer() {
    idleTime = 0;
    let now = Date.now();
    if (now - lastServerUpdate > 300000) {
        const depth  = window.location.pathname.split('/').length - 2;
        const prefix = '../'.repeat(Math.max(0, depth - 1));
        fetch(prefix + 'auth/keep_alive.php').catch(() => {});
        lastServerUpdate = now;
    }
}
window.onload = resetTimer;
document.onmousemove = document.onkeypress = document.onmousedown =
document.onclick = document.onscroll = resetTimer;

setInterval(function () {
    idleTime++;
    if (idleTime >= maxIdleMinutes) {
        alert('Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.');
        const depth  = window.location.pathname.split('/').length - 2;
        const prefix = '../'.repeat(Math.max(0, depth - 1));
        window.location.href = prefix + 'login.php?pesan=timeout';
    }
}, 60000);
</script>
</body>
</html>