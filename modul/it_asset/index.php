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
                                    OR a.serial_number LIKE '%" . mysqli_real_escape_string($koneksi, $filter_keyword) . "%'
                                    OR a.pengguna    LIKE '%" . mysqli_real_escape_string($koneksi, $filter_keyword) . "%')";

$query = "SELECT a.* FROM master_it_asset a $where ORDER BY a.created_at DESC";
$result = mysqli_query($koneksi, $query);

// Summary cards
$q_total    = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE status_asset='AKTIF'");
$q_bagus    = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE kondisi='BAGUS' AND status_asset='AKTIF'");
$q_rusak    = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE kondisi='RUSAK' AND status_asset='AKTIF'");
$q_service  = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE kondisi='DI-SERVICE' AND status_asset='AKTIF'");
$total   = mysqli_fetch_assoc($q_total)['c'];
$bagus   = mysqli_fetch_assoc($q_bagus)['c'];
$rusak   = mysqli_fetch_assoc($q_rusak)['c'];
$service = mysqli_fetch_assoc($q_service)['c'];

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
$pending_sync = mysqli_fetch_assoc($q_pending)['c'];

$additional_css = '
<style>
    .card-stat { border-left: 4px solid; border-radius: 8px; }
    .card-stat-total   { border-left-color: #0d6efd; }
    .card-stat-bagus   { border-left-color: #198754; }
    .card-stat-rusak   { border-left-color: #dc3545; }
    .card-stat-service { border-left-color: #fd7e14; }
    .table-hover tbody tr:hover { background-color: #f0f4ff; }
    .garansi-expire { color: #dc3545; font-weight: bold; }
    .garansi-ok     { color: #198754; }
    .garansi-warn   { color: #fd7e14; }
    .sync-alert { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
</style>';

// ✅ INCLUDE HEADER SETELAH $additional_css siap
require_once __DIR__ . '/../../header_it.php';


?>

<!-- ===== SUMMARY CARDS ===== -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card card-stat card-stat-total h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold">TOTAL ASET AKTIF</div>
                        <div class="fs-3 fw-bold text-primary"><?= $total ?></div>
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
                <a href="sinkron_pembelian.php" class="btn btn-purple btn-sm fw-bold" style="background:#764ba2;color:white;">
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
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Cari nama, kode, serial, pengguna..." value="<?= htmlspecialchars($filter_keyword) ?>">
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
                    <?php while($l = mysqli_fetch_assoc($q_lok)): ?>
                    <option value="<?= $l['lokasi'] ?>" <?= $filter_lokasi==$l['lokasi']?'selected':'' ?>><?= $l['lokasi'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
                <a href="index.php" class="btn btn-secondary btn-sm flex-fill">
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
                        <th class="d-none d-md-table-cell">Serial Number</th>
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
                <?php else: while($row = mysqli_fetch_assoc($result)): ?>
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
                            <?= $row['serial_number'] ? htmlspecialchars($row['serial_number']) : '-' ?>
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
    <div class="card-footer text-muted small">
        Total: <strong><?= mysqli_num_rows(mysqli_query($koneksi, $query)) ?></strong> aset ditemukan
    </div>
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
