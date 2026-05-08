<?php
$page_title = "Sinkronisasi Aset IT dari Pembelian";
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

if (!in_array($role, ['administrator', 'it'])) {
    header("Location: ../../index.php");
    exit;
}

// ============================================================
// PROSES SIMPAN PILIHAN SINKRON
// ============================================================
// ============================================================
// PROSES SIMPAN PILIHAN SINKRON
// ============================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_detail_list'])) {
    $berhasil = 0;
    $gagal    = 0;
    $tahun    = date('Y');

    foreach ($_POST['id_detail_list'] as $id_detail) {
        $id_detail = (int)$id_detail;

        // Cek sudah disinkron belum (pakai id_detail sebagai referensi unik)
        $cek = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM master_it_asset WHERE id_pembelian = $id_detail");
        if (mysqli_fetch_assoc($cek)['c'] > 0) continue;

        // Ambil data dari join tr_request_detail + pembelian + tr_request
        $q = mysqli_query($koneksi, "
            SELECT
                rd.id_detail,
                rd.nama_barang_manual,
                rd.kategori_barang,
                rd.kwalifikasi,
                rd.harga_satuan_estimasi,
                p.id_pembelian,
                p.nama_barang_beli,
                p.merk_beli,
                p.supplier,
                p.harga,
                p.tgl_beli_barang,
                p.tgl_beli,
                p.no_request,
                r.no_request as no_req_resmi
            FROM tr_request_detail rd
            LEFT JOIN pembelian p ON p.id_request_detail = rd.id_detail
            LEFT JOIN tr_request r ON r.id_request = rd.id_request
            WHERE rd.id_detail = $id_detail
              AND rd.kategori_barang = 'INVESTASI IT'
        ");
        if (!$q || mysqli_num_rows($q) == 0) continue;
        $p = mysqli_fetch_assoc($q);

        // Generate kode asset
        $q_cnt = mysqli_query($koneksi, "SELECT last_number FROM master_it_asset_counter WHERE tahun = '$tahun'");
        if (mysqli_num_rows($q_cnt) == 0) {
            mysqli_query($koneksi, "INSERT INTO master_it_asset_counter (tahun, last_number) VALUES ('$tahun', 0)");
            $last = 0;
        } else {
            $last = mysqli_fetch_assoc($q_cnt)['last_number'];
        }
        $next       = $last + 1;
        $kode_asset = 'IT-' . $tahun . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);

        // Nama barang: ambil dari pembelian jika ada, fallback ke nama_barang_manual
        $nama_barang = $p['nama_barang_beli'] ?: $p['nama_barang_manual'];
        $no_req      = $p['no_req_resmi'] ?: $p['no_request'];
        $tgl_beli    = $p['tgl_beli_barang'] ?: $p['tgl_beli'] ?: date('Y-m-d');
        $harga       = (float)($p['harga'] ?: $p['harga_satuan_estimasi'] ?: 0);

        $kode_esc     = mysqli_real_escape_string($koneksi, $kode_asset);
        $nama_esc     = mysqli_real_escape_string($koneksi, $nama_barang);
        $merk_esc     = mysqli_real_escape_string($koneksi, $p['merk_beli'] ?? '');
        $supplier_esc = mysqli_real_escape_string($koneksi, $p['supplier'] ?? '');
        $no_req_esc   = mysqli_real_escape_string($koneksi, $no_req ?? '');
        $spek_esc     = mysqli_real_escape_string($koneksi, $p['kwalifikasi'] ?? '');

        // Gunakan id_detail sebagai referensi (simpan di id_pembelian sementara)
        // Jika ada id_pembelian asli, gunakan itu
        $id_pem_ref = $p['id_pembelian'] ? (int)$p['id_pembelian'] : 0;

        $sql_insert = "INSERT INTO master_it_asset
            (kode_asset, nama_asset, merk, spesifikasi,
             sumber_perolehan, id_pembelian, no_request,
             tgl_perolehan, harga_perolehan, supplier,
             kondisi, status_asset, created_by, created_at)
            VALUES
            ('$kode_esc', '$nama_esc', '$merk_esc', '$spek_esc',
             'PEMBELIAN', " . ($id_pem_ref ?: 'NULL') . ", '$no_req_esc',
             '$tgl_beli', $harga, '$supplier_esc',
             'BAGUS', 'AKTIF', '$nama', NOW())";

        if (mysqli_query($koneksi, $sql_insert)) {
            $id_baru = mysqli_insert_id($koneksi);
            mysqli_query($koneksi, "INSERT INTO master_it_asset_counter (tahun, last_number) VALUES ('$tahun', $next)
                ON DUPLICATE KEY UPDATE last_number = $next");
            $ket = mysqli_real_escape_string($koneksi, "Aset disinkronkan dari PR. No. Request: " . ($no_req ?? '-'));
            mysqli_query($koneksi, "INSERT INTO tr_it_asset_history
                (id_asset, tgl_kejadian, jenis_history, kondisi_sesudah, keterangan, created_by)
                VALUES ($id_baru, '$tgl_beli', 'PENERIMAAN', 'BAGUS', '$ket', '$nama')");
            $berhasil++;
        } else {
            $gagal++;
        }
    }

    if ($berhasil > 0) $_SESSION['flash_success'] = "<strong>$berhasil barang</strong> berhasil disinkronkan sebagai aset IT!";
    if ($gagal > 0)    $_SESSION['flash_error']   = "$gagal barang gagal disinkronkan.";
    header("Location: index.php");
    exit;
}

// ============================================================
// QUERY: tr_request_detail dengan kategori_barang = 'INVESTASI IT'
// yang belum terdaftar sebagai aset IT
// ============================================================
$filter_keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$filter_tahun   = isset($_GET['tahun'])   ? $_GET['tahun']   : date('Y');

$where = "WHERE rd.kategori_barang = 'INVESTASI IT'
          AND rd.is_dibeli = 1
          AND NOT EXISTS (
              SELECT 1 FROM master_it_asset a
              WHERE a.id_pembelian = p.id_pembelian
          )";

if ($filter_keyword) {
    $kw = mysqli_real_escape_string($koneksi, $filter_keyword);
    $where .= " AND (rd.nama_barang_manual LIKE '%$kw%'
                  OR p.nama_barang_beli LIKE '%$kw%'
                  OR p.supplier LIKE '%$kw%'
                  OR r.no_request LIKE '%$kw%')";
}
if ($filter_tahun) {
    $where .= " AND YEAR(COALESCE(p.tgl_beli_barang, rd.tgl_dibeli)) = '$filter_tahun'";
}

$q_pending = mysqli_query($koneksi, "
    SELECT
        rd.id_detail,
        rd.nama_barang_manual,
        rd.kategori_barang,
        rd.kwalifikasi,
        rd.harga_satuan_estimasi,
        rd.tgl_dibeli,
        COALESCE(p.nama_barang_beli, rd.nama_barang_manual) as nama_barang,
        p.id_pembelian,
        p.merk_beli,
        p.supplier,
        p.harga,
        p.tgl_beli_barang,
        r.no_request
    FROM tr_request_detail rd
    LEFT JOIN pembelian p ON p.id_request_detail = rd.id_detail
    LEFT JOIN tr_request r ON r.id_request = rd.id_request
    $where
    ORDER BY COALESCE(p.tgl_beli_barang, rd.tgl_dibeli) DESC
");

// Sudah tersinkron — join ke tr_request_detail via pembelian
$q_synced = mysqli_query($koneksi, "
    SELECT a.id_asset, a.kode_asset, a.nama_asset, a.kondisi, a.created_at,
           p.tgl_beli_barang, p.supplier, p.no_request, p.harga
    FROM master_it_asset a
    JOIN pembelian p ON a.id_pembelian = p.id_pembelian
    JOIN tr_request_detail rd ON p.id_request_detail = rd.id_detail
    WHERE rd.kategori_barang = 'INVESTASI IT'
    ORDER BY a.created_at DESC
    LIMIT 20
");

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$additional_css = '<style>
.table-pending tbody tr:hover { background:#fff9e6; }
.checked-row { background: #e8f5e9 !important; }
</style>';
require_once __DIR__ . '/../../header_it.php';

?>

<div class="d-flex align-items-center gap-2 mb-4">
    
    <h5 class="fw-bold mb-0 text-primary">
        <i class="fas fa-sync me-2"></i> Sinkronisasi Aset IT dari Pembelian
    </h5>
</div>

<?php if ($flash_success): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?= $flash_success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($flash_error): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-times-circle me-2"></i><?= $flash_error ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Penjelasan -->
<div class="alert alert-info shadow-sm mb-4">
    <i class="fas fa-info-circle me-2"></i>
    Halaman ini menampilkan seluruh transaksi pembelian dengan kategori <strong>INVESTASI IT</strong>
    yang belum terdaftar sebagai aset IT. Centang barang yang ingin didaftarkan, lalu klik <strong>Sinkronkan</strong>.
    Kode aset akan digenerate otomatis.
</div>

<!-- Filter -->
<!--<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold">Cari Barang</label>
                <input type="text" name="keyword" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($filter_keyword) ?>"
                       placeholder="Nama barang, merk, no. request, supplier...">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Tahun</label>
                <select name="tahun" class="form-select form-select-sm">
                    <?php for ($y = date('Y'); $y >= date('Y')-5; $y--): ?>
                    <option value="<?= $y ?>" <?= $filter_tahun==$y?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                    <option value="">-- Semua Tahun --</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="fas fa-search me-1"></i> Filter
                </button>
                <a href="sinkron_pembelian.php" class="btn btn-secondary btn-sm flex-fill">
                    <i class="fas fa-times me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>-->

<!-- Tabel Pending Sinkron -->
<form method="POST">
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between bg-warning text-dark py-2">
        <div class="fw-bold small">
            <i class="fas fa-clock me-2"></i>
            Belum Terdaftar sebagai Aset IT
            <span class="badge bg-dark ms-2"><?= mysqli_num_rows($q_pending) ?></span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-dark" onclick="toggleAll(true)">
                <i class="fas fa-check-square me-1"></i> Pilih Semua
            </button>
            <button type="button" class="btn btn-sm btn-outline-dark" onclick="toggleAll(false)">
                <i class="fas fa-square me-1"></i> Batal Semua
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 small table-pending">
                <thead class="table-dark">
                    <tr>
                        <th class="text-center" style="width:40px;">✓</th>
                        <th>Nama Barang</th>
                        <th class="d-none d-md-table-cell">Merk</th>
                        <th class="d-none d-md-table-cell">Tgl Beli</th>
                        <th class="d-none d-md-table-cell">Supplier</th>
                        <th class="text-end">Harga</th>
                        <th class="d-none d-md-table-cell">No. Request</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($q_pending) == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-success">
                            <i class="fas fa-check-circle fa-2x d-block mb-2"></i>
                            Semua pembelian INVESTASI IT sudah terdaftar sebagai aset. 🎉
                        </td>
                    </tr>
                <?php else: while($p = mysqli_fetch_assoc($q_pending)): ?>
                    <tr id="row_<?= $p['id_pembelian'] ?>">
                        <td class="text-center">
                            <input type="checkbox" name="id_detail_list[]"
                                    value="<?= $p['id_detail'] ?>"
                                   class="form-check-input chk-item"
                                   onchange="highlightRow(this)">
                        </td>
                        <td class="fw-semibold"><?= htmlspecialchars($p['nama_barang']) ?></td>
                        <td class="d-none d-md-table-cell text-muted"><?= htmlspecialchars($p['merk_beli'] ?? '-') ?></td>
                        <td class="d-none d-md-table-cell"><?= $p['tgl_beli_barang'] ? date('d/m/Y', strtotime($p['tgl_beli_barang'])) : ($p['tgl_dibeli'] ? date('d/m/Y', strtotime($p['tgl_dibeli'])) : '-') ?></td>
                        <td class="d-none d-md-table-cell"><?= htmlspecialchars($p['supplier'] ?? '-') ?></td>
                        <td class="text-end">Rp <?= number_format($p['harga'] ?: $p['harga_satuan_estimasi'], 0, ',', '.') ?></td>
                        <td class="d-none d-md-table-cell">
                            <?php if ($p['no_request']): ?>
                            <span class="badge bg-info text-dark"><?= $p['no_request'] ?></span>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (mysqli_num_rows($q_pending) > 0): ?>
    <div class="card-footer text-end">
        <button type="submit" class="btn btn-primary fw-bold">
            <i class="fas fa-sync me-2"></i> SINKRONKAN YANG DIPILIH
        </button>
    </div>
    <?php endif; ?>
</div>
</form>

<!-- Sudah Tersinkron -->
<div class="card">
    <div class="card-header bg-success text-white fw-bold py-2 small">
        <i class="fas fa-check-double me-2"></i> Sudah Terdaftar sebagai Aset (20 Terakhir)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Kode Aset</th>
                        <th>Nama Barang</th>
                        <th class="d-none d-md-table-cell">Kondisi</th>
                        <th class="d-none d-md-table-cell">Tgl Beli</th>
                        <th class="d-none d-md-table-cell text-end">Harga</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($q_synced) == 0): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data tersinkron</td></tr>
                <?php else: while($s = mysqli_fetch_assoc($q_synced)): ?>
                    <tr>
                        <td><span class="fw-bold text-primary" style="font-family:monospace;"><?= $s['kode_asset'] ?></span></td>
                        <td><?= htmlspecialchars($s['nama_asset']) ?></td>
                        <td class="d-none d-md-table-cell">
                            <span class="badge bg-<?= ['BAGUS'=>'success','RUSAK'=>'danger','DI-SERVICE'=>'warning'][$s['kondisi']] ?? 'secondary' ?>">
                                <?= $s['kondisi'] ?>
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell"><?= date('d/m/Y', strtotime($s['tgl_beli_barang'])) ?></td>
                        <td class="d-none d-md-table-cell text-end">Rp <?= number_format($s['harga'], 0, ',', '.') ?></td>
                        <td class="text-center">
                            <a href="detail_asset.php?id=<?= $s['id_asset'] ?>" class="btn btn-info btn-sm text-white">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleAll(state) {
    document.querySelectorAll(".chk-item").forEach(cb => {
        cb.checked = state;
        highlightRow(cb);
    });
}
function highlightRow(cb) {
    const row = cb.closest("tr");
    if (cb.checked) row.classList.add("checked-row");
    else row.classList.remove("checked-row");
}
</script>
