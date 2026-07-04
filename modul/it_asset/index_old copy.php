<?php
$page_title = "IT Asset Management";
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// ===== FILTER =====
$filter_kondisi  = isset($_GET['kondisi'])  ? $_GET['kondisi']  : '';
$filter_status   = isset($_GET['status'])   ? $_GET['status']   : 'AKTIF';
$filter_lokasi   = isset($_GET['lokasi'])   ? $_GET['lokasi']   : '';
$filter_keyword  = isset($_GET['keyword'])  ? $_GET['keyword']  : '';

$where = "WHERE 1=1";
if ($filter_kondisi)  $where .= " AND a.kondisi    = '" . mysqli_real_escape_string($koneksi, $filter_kondisi) . "'";
if ($filter_status)   $where .= " AND a.status_asset = '" . mysqli_real_escape_string($koneksi, $filter_status) . "'";
if ($filter_lokasi)   $where .= " AND a.lokasi      = '" . mysqli_real_escape_string($koneksi, $filter_lokasi) . "'";
if ($filter_keyword)  $where .= " AND (a.kode_asset LIKE '%" . mysqli_real_escape_string($koneksi, $filter_keyword) . "%'
                                    OR a.nama_asset  LIKE '%" . mysqli_real_escape_string($koneksi, $filter_keyword) . "%'
                                    OR a.merk        LIKE '%" . mysqli_real_escape_string($koneksi, $filter_keyword) . "%'
                                    OR a.keterangan LIKE '%" . mysqli_real_escape_string($koneksi, $filter_keyword) . "%'
                                    OR a.pengguna    LIKE '%" . mysqli_real_escape_string($koneksi, $filter_keyword) . "%')";

// ===== PAGINATION =====
$items_per_page = 15;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

$offset = ($current_page - 1) * $items_per_page;

// Count total items
$count_query = "SELECT COUNT(*) as total FROM master_it_asset a $where";
$count_result = mysqli_query($koneksi, $count_query);
$total_items = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_items / $items_per_page);

// Ensure current page is not beyond total pages
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$query = "SELECT a.* FROM master_it_asset a $where ORDER BY a.created_at DESC LIMIT $items_per_page OFFSET $offset";
$result = mysqli_query($koneksi, $query);

// ===== SUMMARY CARDS DATA =====
$q_total    = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE status_asset='AKTIF'");
$q_bagus    = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE kondisi='BAGUS' AND status_asset='AKTIF'");
$q_rusak    = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE kondisi='RUSAK' AND status_asset='AKTIF'");
$q_service  = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE kondisi='DI-SERVICE' AND status_asset='AKTIF'");
$total   = mysqli_fetch_assoc($q_total)['c'] ?: 0;
$bagus   = mysqli_fetch_assoc($q_bagus)['c'] ?: 0;
$rusak   = mysqli_fetch_assoc($q_rusak)['c'] ?: 0;
$service = mysqli_fetch_assoc($q_service)['c'] ?: 0;

// Hitung persentase
$pct_bagus = $total > 0 ? round(($bagus / $total) * 100, 1) : 0;
$pct_rusak = $total > 0 ? round(($rusak / $total) * 100, 1) : 0;
$pct_service = $total > 0 ? round(($service / $total) * 100, 1) : 0;

// Maintenance needed = rusak + service
$maintenance_needed = $rusak + $service;

// ===== KATEGORI BREAKDOWN =====
$q_kategori = mysqli_query($koneksi, "
  SELECT 
    CASE 
      WHEN nama_asset LIKE '%LAPTOP%' THEN 'Laptop'
      WHEN nama_asset LIKE '%PC%' AND nama_asset NOT LIKE '%LAPTOP%' THEN 'PC'
      WHEN nama_asset LIKE '%PRINTER%' THEN 'Printer'
      WHEN nama_asset LIKE '%SERVER%' THEN 'Server'
      WHEN nama_asset LIKE '%ROUTER%' OR nama_asset LIKE '%MIKROTIK%' OR nama_asset LIKE '%SWITCH%' THEN 'Network'
      ELSE 'Lainnya'
    END as kategori,
    COUNT(*) as count
  FROM master_it_asset
  WHERE status_asset = 'AKTIF'
  GROUP BY kategori
  ORDER BY count DESC
");
$kategori_data = [];
while ($row = mysqli_fetch_assoc($q_kategori)) {
  $kategori_data[] = $row;
}

// ===== LOKASI BREAKDOWN =====
$q_lokasi = mysqli_query($koneksi, "
  SELECT lokasi, COUNT(*) as count
  FROM master_it_asset
  WHERE status_asset = 'AKTIF' AND lokasi IS NOT NULL AND lokasi != ''
  GROUP BY lokasi
  ORDER BY count DESC
  LIMIT 6
");
$lokasi_data = [];
while ($row = mysqli_fetch_assoc($q_lokasi)) {
  $lokasi_data[] = $row;
}

// ===== GARANSI EXPIRING / EXPIRED =====
$q_garansi = mysqli_query($koneksi, "
  SELECT COUNT(*) as expired FROM master_it_asset
  WHERE status_asset = 'AKTIF'
  AND tgl_garansi_selesai IS NOT NULL
  AND tgl_garansi_selesai < CURDATE()
");
$garansi_expired = mysqli_fetch_assoc($q_garansi)['expired'] ?: 0;

$q_garansi_warn = mysqli_query($koneksi, "
  SELECT COUNT(*) as warn FROM master_it_asset
  WHERE status_asset = 'AKTIF'
  AND tgl_garansi_selesai IS NOT NULL
  AND tgl_garansi_selesai >= CURDATE()
  AND DATEDIFF(tgl_garansi_selesai, CURDATE()) <= 30
");
$garansi_warn = mysqli_fetch_assoc($q_garansi_warn)['warn'] ?: 0;
$garansi_action = $garansi_expired + $garansi_warn;

// ===== ASSET VALUE =====
$q_value = mysqli_query($koneksi, "
  SELECT SUM(harga_perolehan) as total_value, COUNT(*) as count
  FROM master_it_asset
  WHERE sumber_perolehan = 'PEMBELIAN' AND status_asset = 'AKTIF'
");
$value_row = mysqli_fetch_assoc($q_value);
$asset_value = $value_row['total_value'] ?: 0;
$asset_count_purchased = $value_row['count'] ?: 0;

// Daftar lokasi untuk filter
$q_lok = mysqli_query($koneksi, "SELECT DISTINCT lokasi FROM master_it_asset WHERE lokasi IS NOT NULL AND lokasi != '' ORDER BY lokasi");

// Cek pending sinkron dari pembelian
$q_pending = mysqli_query($koneksi, "
  SELECT COUNT(*) as c FROM pembelian p
  WHERE p.kategori_beli = 'INVESTASI IT'
  AND NOT EXISTS (
    SELECT 1 FROM master_it_asset a WHERE a.id_pembelian = p.id_pembelian
  )
");
$pending_sync = mysqli_fetch_assoc($q_pending)['c'] ?: 0;

// Detail items dengan kondisi rusak/service untuk quick view
$q_maintenance_items = mysqli_query($koneksi, "
  SELECT id_asset, kode_asset, nama_asset, kondisi, merk, lokasi
  FROM master_it_asset
  WHERE status_asset = 'AKTIF' AND kondisi IN ('RUSAK', 'DI-SERVICE')
  ORDER BY kondisi ASC, created_at DESC
  LIMIT 10
");

// Detail items dengan garansi expired/expiring soon
$q_garansi_items = mysqli_query($koneksi, "
  SELECT id_asset, kode_asset, nama_asset, tgl_garansi_selesai, 
         DATEDIFF(tgl_garansi_selesai, CURDATE()) as sisa_hari
  FROM master_it_asset
  WHERE status_asset = 'AKTIF'
  AND tgl_garansi_selesai IS NOT NULL
  AND (tgl_garansi_selesai < CURDATE() OR DATEDIFF(tgl_garansi_selesai, CURDATE()) <= 30)
  ORDER BY tgl_garansi_selesai ASC
  LIMIT 5
");

$additional_css = '
<style>
  :root {
    --clr-primary: #0d6efd;
    --clr-success: #198754;
    --clr-danger: #dc3545;
    --clr-warning: #fd7e14;
    --clr-info: #0dcaf0;
    --clr-secondary: #6c757d;
    --clr-purple: #6f42c1;
  }

  .card-stat {
    border-left: 4px solid;
    border-radius: 8px;
    background: var(--bs-card-bg);
    border: 0.5px solid #dee2e6;
  }
  .card-stat-total   { border-left-color: var(--clr-primary); }
  .card-stat-bagus   { border-left-color: var(--clr-success); }
  .card-stat-rusak   { border-left-color: var(--clr-danger); }
  .card-stat-service { border-left-color: var(--clr-warning); }
  .card-stat-maintenance { border-left-color: var(--clr-danger); }

  .table-hover tbody tr:hover { background-color: #f0f4ff; }
  
  .garansi-expire { color: var(--clr-danger); font-weight: bold; }
  .garansi-ok     { color: var(--clr-success); }
  .garansi-warn   { color: var(--clr-warning); }
  
  .sync-alert {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
  }

  .dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
  }

  @media (max-width: 1200px) {
    .dashboard-grid {
      grid-template-columns: 1fr;
    }
  }

  .breakdown-card {
    background: var(--bs-card-bg);
    border: 0.5px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
  }

  .breakdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
  }

  .breakdown-label {
    min-width: 80px;
    font-size: 12px;
    color: #666;
    font-weight: 500;
  }

  .breakdown-bar {
    flex: 1;
    background: #e9ecef;
    height: 20px;
    border-radius: 4px;
    overflow: hidden;
  }

  .breakdown-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
  }

  .breakdown-percent {
    min-width: 45px;
    text-align: right;
    font-size: 12px;
    font-weight: 600;
    color: #333;
  }

  .quick-action-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
  }

  .quick-action-banner .banner-label {
    font-size: 12px;
    font-weight: 600;
    opacity: 0.9;
    margin-bottom: 0.5rem;
  }

  .quick-action-banner .banner-count {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 0.75rem;
  }

  .quick-action-banner .banner-text {
    font-size: 13px;
    opacity: 0.9;
    margin-bottom: 1rem;
    line-height: 1.4;
  }

  .quick-action-banner button {
    background: white;
    color: #764ba2;
    border: none;
    padding: 0.6rem 1rem;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    width: 100%;
    transition: all 0.2s;
  }

  .quick-action-banner button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }

  .alert-card {
    background: var(--bs-card-bg);
    border: 0.5px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
  }

  .alert-card h6 {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .alert-item {
    border-bottom: 0.5px solid #dee2e6;
    padding-bottom: 0.75rem;
    margin-bottom: 0.75rem;
  }

  .alert-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
  }

  .alert-badge {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 0.25rem;
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
  }

  .alert-badge-danger { background: rgba(220, 53, 69, 0.1); color: var(--clr-danger); }
  .alert-badge-warning { background: rgba(253, 126, 20, 0.1); color: var(--clr-warning); }
  .alert-badge-success { background: rgba(25, 135, 84, 0.1); color: var(--clr-success); }

  .alert-title {
    font-size: 12px;
    color: #333;
    font-weight: 600;
    margin-bottom: 0.25rem;
  }

  .alert-desc {
    font-size: 11px;
    color: #999;
  }

  .stat-box {
    background: var(--bs-card-bg);
    border: 0.5px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
  }

  .stat-box h6 {
    font-size: 12px;
    font-weight: 600;
    color: #666;
    margin-bottom: 0.75rem;
  }

  .stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--clr-primary);
    margin-bottom: 0.75rem;
  }

  .stat-detail {
    font-size: 11px;
    color: #999;
    line-height: 1.6;
  }

  .stat-detail div {
    margin-bottom: 0.35rem;
  }

  /* Pagination Styling */
  .pagination {
    gap: 0.25rem;
  }

  .page-link {
    color: var(--clr-primary);
    border: 0.5px solid #dee2e6;
    border-radius: var(--radius);
    padding: 0.5rem 0.75rem;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
  }

  .page-link:hover:not(.disabled .page-link) {
    background-color: #f0f4ff;
    border-color: var(--clr-primary);
    color: var(--clr-primary);
  }

  .page-item.active .page-link {
    background-color: var(--clr-primary);
    border-color: var(--clr-primary);
    color: white;
  }

  .page-item.disabled .page-link {
    color: #999;
    cursor: not-allowed;
    opacity: 0.5;
  }

  .page-item.disabled .page-link:hover {
    background-color: transparent;
  }
</style>';

// ✅ INCLUDE HEADER SETELAH $additional_css siap
require_once __DIR__ . '/../../header_it.php';

?>

<!-- ===== ENHANCED SUMMARY CARDS ===== -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card card-stat card-stat-total h-100">
      <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-bold">TOTAL AKTIF</div>
            <div class="fs-3 fw-bold text-primary"><?= $total ?></div>
            <small class="text-muted">semua aset aktif</small>
          </div>
          <i class="fas fa-laptop fa-2x text-primary opacity-25"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card card-stat card-stat-bagus h-100">
      <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-bold">KONDISI BAGUS</div>
            <div class="fs-3 fw-bold text-success"><?= $bagus ?></div>
            <small class="text-muted"><?= $pct_bagus ?>% dari total</small>
          </div>
          <i class="fas fa-check-circle fa-2x text-success opacity-25"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card card-stat card-stat-rusak h-100">
      <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-bold">RUSAK</div>
            <div class="fs-3 fw-bold text-danger"><?= $rusak ?></div>
            <small class="text-muted"><?= $pct_rusak ?>% dari total</small>
          </div>
          <i class="fas fa-times-circle fa-2x text-danger opacity-25"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card card-stat card-stat-service h-100">
      <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="text-muted small fw-bold">DI-SERVICE</div>
            <div class="fs-3 fw-bold text-warning"><?= $service ?></div>
            <small class="text-muted"><?= $pct_service ?>% dari total</small>
          </div>
          <i class="fas fa-tools fa-2x text-warning opacity-25"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ===== NOTIF SINKRON ===== -->
<?php if ($pending_sync > 0): ?>
<div class="alert sync-alert shadow-sm mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <i class="fas fa-sync-alt me-2"></i>
    <strong><?= $pending_sync ?> barang</strong> dari transaksi pembelian kategori <strong>INVESTASI IT</strong> belum terdaftar sebagai aset IT.
  </div>
  <a href="sinkron_pembelian.php" class="btn btn-light btn-sm fw-bold">
    <i class="fas fa-sync me-1"></i> SINKRONKAN SEKARANG
  </a>
</div>
<?php endif; ?>

<!-- ===== DASHBOARD GRID: Breakdown + Quick Actions ===== -->
<div class="dashboard-grid">
  <!-- LEFT COLUMN: Breakdown Charts -->
  <div>
    <!-- Kategori Breakdown -->
    <div class="breakdown-card">
      <h6 class="mb-3" style="font-size: 14px; font-weight: 600; color: #333;">
        <i class="fas fa-chart-bar me-2" style="color: var(--clr-primary);"></i>Kategori
      </h6>
      
      <?php
      $colors = ['var(--clr-primary)', 'var(--clr-info)', 'var(--clr-success)', 'var(--clr-warning)', 'var(--clr-secondary)', 'var(--clr-purple)'];
      $color_idx = 0;
      foreach ($kategori_data as $kat):
        $pct = round(($kat['count'] / $total) * 100, 1);
        $color = $colors[$color_idx % count($colors)];
        $color_idx++;
      ?>
      <div class="breakdown-item">
        <span class="breakdown-label"><?= htmlspecialchars($kat['kategori']) ?> (<?= $kat['count'] ?>)</span>
        <div class="breakdown-bar">
          <div class="breakdown-fill" style="background-color: <?= $color ?>; width: <?= ($kat['count'] / $total) * 100 ?>%;"></div>
        </div>
        <span class="breakdown-percent"><?= $pct ?>%</span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Lokasi Breakdown -->
    <div class="breakdown-card">
      <h6 class="mb-3" style="font-size: 14px; font-weight: 600; color: #333;">
        <i class="fas fa-map-marker-alt me-2" style="color: var(--clr-success);"></i>Lokasi Asset
      </h6>
      
      <?php foreach ($lokasi_data as $lok): 
        $pct_lok = round(($lok['count'] / $total) * 100, 1);
      ?>
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; padding-bottom: 0.75rem; border-bottom: 0.5px solid #dee2e6;">
        <span style="font-size: 12px; color: #666;"><?= htmlspecialchars($lok['lokasi']) ?></span>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
          <span style="font-size: 13px; font-weight: 600; color: #333;"><?= $lok['count'] ?></span>
          <span style="font-size: 11px; color: #999;"><?= $pct_lok ?>%</span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- RIGHT COLUMN: Quick Actions & Alerts -->
  <div>
    <!-- Sync Banner -->
    <?php if ($pending_sync > 0): ?>
    <div class="quick-action-banner">
      <div class="banner-label"><i class="fas fa-sync me-1"></i>SINKRONISASI PEMBELIAN</div>
      <div class="banner-count"><?= $pending_sync ?></div>
      <div class="banner-text">barang belum terdaftar dari kategori INVESTASI IT</div>
      <a href="sinkron_pembelian.php" style="text-decoration: none;">
        <button type="button">
          <i class="fas fa-arrow-right me-1"></i>Sinkronkan Sekarang
        </button>
      </a>
    </div>
    <?php endif; ?>

    <!-- Assets Needing Action -->
    <?php if ($maintenance_needed > 0 || $garansi_action > 0): ?>
    <div class="alert-card">
      <h6 style="color: var(--clr-danger);">
        <i class="fas fa-exclamation-triangle"></i> Perlu Tindakan
      </h6>

      <!-- Rusak -->
      <?php if ($rusak > 0): ?>
      <div class="alert-item">
        <span class="alert-badge alert-badge-danger">RUSAK</span>
        <div class="alert-title"><?= $rusak ?> aset memerlukan perbaikan</div>
        <div class="alert-desc">
          <?php
          $maintenance_items = [];
          $q_temp = mysqli_query($koneksi, "SELECT kode_asset, nama_asset FROM master_it_asset WHERE status_asset='AKTIF' AND kondisi='RUSAK' LIMIT 3");
          while ($m = mysqli_fetch_assoc($q_temp)) {
            $maintenance_items[] = $m['kode_asset'] . ' (' . substr($m['nama_asset'], 0, 15) . ')';
          }
          echo implode(', ', $maintenance_items);
          if ($rusak > 3) echo ', +' . ($rusak - 3) . ' lainnya';
          ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Di-Service -->
      <?php if ($service > 0): ?>
      <div class="alert-item">
        <span class="alert-badge alert-badge-warning">DI-SERVICE</span>
        <div class="alert-title"><?= $service ?> aset dalam perbaikan</div>
        <div class="alert-desc">Estimasi kembali: segera</div>
      </div>
      <?php endif; ?>

      <!-- Garansi -->
      <?php if ($garansi_action > 0): ?>
      <div class="alert-item">
        <span class="alert-badge alert-badge-success">GARANSI</span>
        <div class="alert-title"><?= $garansi_expired ?> expired, <?= $garansi_warn ?> < 30 hari</div>
        <div class="alert-desc">
          <?php
          $garansi_items_list = [];
          $q_temp = mysqli_query($koneksi, "SELECT kode_asset, tgl_garansi_selesai FROM master_it_asset WHERE status_asset='AKTIF' AND tgl_garansi_selesai IS NOT NULL AND (tgl_garansi_selesai < CURDATE() OR DATEDIFF(tgl_garansi_selesai, CURDATE()) <= 30) LIMIT 2");
          while ($g = mysqli_fetch_assoc($q_temp)) {
            $garansi_items_list[] = $g['kode_asset'];
          }
          echo implode(', ', $garansi_items_list);
          if ($garansi_action > 2) echo ', +' . ($garansi_action - 2) . ' lainnya';
          ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Asset Value Info -->
    <div class="stat-box">
      <h6>Asset Value</h6>
      <div class="stat-value">
        Rp <?= number_format($asset_value, 0, ',', '.') ?>
      </div>
      <div class="stat-detail">
        <div>• <?= $asset_count_purchased ?> item dari pembelian</div>
        <div>• <?= ($total - $asset_count_purchased) ?> item manual entry</div>
        <div style="margin-top: 0.5rem; font-style: italic;">*Depreciation calculation pending</div>
      </div>
    </div>
  </div>
</div>

<!-- ===== TOOLBAR ===== -->
<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center mb-3">
      <h5 class="fw-bold mb-0 text-primary">
        <i class="fas fa-laptop me-2"></i> Daftar Aset IT
      </h5>
      <div class="d-flex gap-2 flex-wrap">
        <a href="form_asset.php" class="btn btn-primary btn-sm fw-bold">
          <i class="fas fa-plus me-1"></i> Tambah Barang IT
        </a>
        <a href="sinkron_pembelian.php" class="btn btn-sm fw-bold" style="background:#764ba2;color:white;border:none;">
          <i class="fas fa-sync me-1"></i> Sinkron Dari Pembelian
        </a>
        <a href="report_asset.php?kondisi=<?= $filter_kondisi ?>&status=<?= $filter_status ?>&lokasi=<?= urlencode($filter_lokasi) ?>&keyword=<?= urlencode($filter_keyword) ?>" 
          class="btn btn-success btn-sm fw-bold">
          <i class="fas fa-file-excel me-1"></i> Export Excel
        </a>
      </div>
    </div>

    <!-- Filter -->
    <form method="GET" class="row g-2">
      <div class="col-12 col-md-3">
        <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Cari nama, kode, keterangan, pengguna..." value="<?= htmlspecialchars($filter_keyword) ?>">
      </div>
      <div class="col-6 col-md-2">
        <select name="kondisi" class="form-select form-select-sm">
          <option value="">Semua Kondisi</option>
          <?php foreach(['BAGUS','RUSAK','DI-SERVICE','TIDAK AKTIF','HILANG'] as $k): ?>
          <option value="<?= $k ?>" <?= $filter_kondisi==$k?'selected':'' ?>><?= $k ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="status" class="form-select form-select-sm">
          <option value="">Semua Status</option>
          <option value="AKTIF"      <?= $filter_status=='AKTIF'?'selected':'' ?>>AKTIF</option>
          <option value="TIDAK AKTIF"<?= $filter_status=='TIDAK AKTIF'?'selected':'' ?>>TIDAK AKTIF</option>
          <option value="DISPOSE"    <?= $filter_status=='DISPOSE'?'selected':'' ?>>DISPOSE</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <select name="lokasi" class="form-select form-select-sm">
          <option value="">Semua Lokasi</option>
          <?php 
          mysqli_data_seek($q_lok, 0);
          while($l = mysqli_fetch_assoc($q_lok)): ?>
          <option value="<?= $l['lokasi'] ?>" <?= $filter_lokasi==$l['lokasi']?'selected':'' ?>><?= $l['lokasi'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-6 col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-fill">
          <i class="fas fa-search me-1"></i> Filter
        </button>
        <a href="<?= strtok($_SERVER["REQUEST_URI"],'?') ?>" class="btn btn-secondary btn-sm flex-fill">
          <i class="fas fa-times me-1"></i> Reset
        </a>
      </div>
    </form>
  </div>
</div>

<!-- ===== TABEL ASET ===== -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-bordered mb-0 small">
        <thead class="table-dark">
          <tr>
            <th class="text-center" style="width:40px;">No</th>
            <th>Kode Aset</th>
            <th>Nama Barang / Merk</th>
            <th class="d-none d-md-table-cell">Keterangan</th>
            <th class="d-none d-md-table-cell">Lokasi / Pengguna</th>
            <th class="text-center">Kondisi</th>
            <th class="d-none d-lg-table-cell text-center">Garansi</th>
            <th class="d-none d-md-table-cell">Sumber</th>
            <th class="text-center" style="width:120px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php
        $no = 1;
        if (mysqli_num_rows($result) == 0):
        ?>
          <tr>
            <td colspan="9" class="text-center py-4 text-muted">
              <i class="fas fa-inbox fa-2x d-block mb-2"></i>
              Belum ada data aset IT
            </td>
          </tr>
        <?php else: 
        // Reset result untuk digunakan kembali
        mysqli_data_seek($result, 0);
        while($row = mysqli_fetch_assoc($result)): ?>
          <?php
          // Badge kondisi
          $kondisi_class = [
            'BAGUS'       => 'success',
            'RUSAK'       => 'danger',
            'DI-SERVICE'  => 'warning',
            'TIDAK AKTIF' => 'secondary',
            'HILANG'      => 'dark',
          ][$row['kondisi']] ?? 'secondary';

          // Garansi
          $garansi_info = '-';
          $garansi_class = '';
          if ($row['tgl_garansi_selesai']) {
            $tgl_garansi = new DateTime($row['tgl_garansi_selesai']);
            $today = new DateTime();
            $diff = $today->diff($tgl_garansi);
            $sisa_hari = $tgl_garansi > $today ? $diff->days : -$diff->days;
            if ($sisa_hari < 0) {
              $garansi_info  = 'Expired';
              $garansi_class = 'garansi-expire';
            } elseif ($sisa_hari <= 30) {
              $garansi_info  = $sisa_hari . ' hari';
              $garansi_class = 'garansi-warn';
            } else {
              $garansi_info  = date('d/m/Y', strtotime($row['tgl_garansi_selesai']));
              $garansi_class = 'garansi-ok';
            }
          }
          ?>
          <tr>
            <td class="text-center"><?= $no++ ?></td>
            <td>
              <span class="fw-bold text-primary"><?= htmlspecialchars($row['kode_asset']) ?></span>
              <?php if ($row['status_asset'] != 'AKTIF'): ?>
              <br><span class="badge bg-secondary" style="font-size:0.65rem;"><?= $row['status_asset'] ?></span>
              <?php endif; ?>
            </td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($row['nama_asset']) ?></div>
              <?php if ($row['merk']): ?>
              <small class="text-muted"><?= htmlspecialchars($row['merk']) ?> <?= htmlspecialchars($row['model'] ?? '') ?></small>
              <?php endif; ?>
            </td>
            <td class="d-none d-md-table-cell text-muted">
              <?= $row['keterangan'] ? htmlspecialchars($row['keterangan']) : '-' ?>
            </td>
            <td class="d-none d-md-table-cell">
              <?php if ($row['lokasi']): ?>
              <div><i class="fas fa-map-marker-alt text-muted me-1" style="font-size:0.7rem;"></i><?= htmlspecialchars($row['lokasi']) ?></div>
              <?php endif; ?>
              <?php if ($row['pengguna']): ?>
              <small class="text-muted"><i class="fas fa-user me-1" style="font-size:0.7rem;"></i><?= htmlspecialchars($row['pengguna']) ?></small>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <span class="badge bg-<?= $kondisi_class ?>"><?= $row['kondisi'] ?></span>
            </td>
            <td class="d-none d-lg-table-cell text-center">
              <span class="<?= $garansi_class ?> small"><?= $garansi_info ?></span>
            </td>
            <td class="d-none d-md-table-cell text-center">
              <?php if ($row['sumber_perolehan'] == 'PEMBELIAN'): ?>
              <span class="badge bg-info text-dark" style="font-size:0.65rem;">PEMBELIAN</span>
              <?php else: ?>
              <span class="badge bg-secondary" style="font-size:0.65rem;">MANUAL</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <a href="detail_asset.php?id=<?= $row['id_asset'] ?>" class="btn btn-sm btn-info text-white" title="Detail & History">
                  <i class="fas fa-eye"></i>
                </a>
                <a href="form_asset.php?id=<?= $row['id_asset'] ?>" class="btn btn-sm btn-warning text-dark" title="Edit">
                  <i class="fas fa-pencil"></i>
                </a>
                <button onclick="konfirmasiHapus(<?= $row['id_asset'] ?>, '<?= htmlspecialchars($row['nama_asset'], ENT_QUOTES) ?>')" class="btn btn-sm btn-danger" title="Hapus">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer text-muted small d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      Total: <strong><?= $total_items ?></strong> aset ditemukan | 
      Halaman: <strong><?= $current_page ?> dari <?= $total_pages ?: 1 ?></strong> | 
      Menampilkan: <strong><?= min($items_per_page, $total_items - $offset) ?></strong> per halaman
    </div>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <nav aria-label="Page navigation" class="mt-3">
    <ul class="pagination justify-content-center mb-0">
      <!-- Previous Button -->
      <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $current_page - 1)])) ?>" <?= $current_page <= 1 ? 'tabindex="-1"' : '' ?>>
          <i class="fas fa-chevron-left me-1"></i> Sebelumnya
        </a>
      </li>

      <!-- Page Numbers -->
      <?php
      // Show max 5 page buttons
      $page_range = 5;
      $start_page = max(1, $current_page - floor($page_range / 2));
      $end_page = min($total_pages, $start_page + $page_range - 1);
      $start_page = max(1, $end_page - $page_range + 1);

      if ($start_page > 1):
      ?>
        <li class="page-item">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
        </li>
        <?php if ($start_page > 2): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
        <?php endif; ?>
      <?php endif; ?>

      <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
        <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" aria-label="Halaman <?= $i ?>" <?= $i == $current_page ? 'aria-current="page"' : '' ?>>
            <?= $i ?>
          </a>
        </li>
      <?php endfor; ?>

      <?php if ($end_page < $total_pages): ?>
        <?php if ($end_page < $total_pages - 1): ?>
        <li class="page-item disabled"><span class="page-link">...</span></li>
        <?php endif; ?>
        <li class="page-item">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
        </li>
      <?php endif; ?>

      <!-- Next Button -->
      <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $current_page + 1)])) ?>" <?= $current_page >= $total_pages ? 'tabindex="-1"' : '' ?>>
          Berikutnya <i class="fas fa-chevron-right ms-1"></i>
        </a>
      </li>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<!-- Modal Hapus -->
<div class="modal fade" id="modalHapus" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h6 class="modal-title fw-bold"><i class="fas fa-trash me-2"></i>Konfirmasi Hapus</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center py-4">
        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
        <p class="mb-1">Yakin ingin menghapus aset:</p>
        <strong id="nama_hapus" class="text-danger fs-6"></strong>
        <p class="text-muted small mt-2">Semua riwayat aset ini juga akan terhapus!</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="btn_hapus_confirm" class="btn btn-danger fw-bold">
          <i class="fas fa-trash me-1"></i> Hapus
        </a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
  window.konfirmasiHapus = function(id, nama) {
    document.getElementById("nama_hapus").textContent = nama;
    document.getElementById("btn_hapus_confirm").href = "proses_asset.php?aksi=hapus&id=" + id;
    new bootstrap.Modal(document.getElementById("modalHapus")).show();
  }
});
</script>