<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/kawat_helper.php';

if (($_SESSION['status'] ?? '') !== 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$user = $_SESSION['username'] ?? 'system';
kawat_sync_master_barang($koneksi, $user);
kawat_sync_pembelian($koneksi, $user);



/* =========================================================
   FILTER — MODE BULAN atau MODE RENTANG TANGGAL
   ========================================================= */

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate   = isset($_GET['end_date'])   ? trim($_GET['end_date'])   : '';

$pakaiRentang = ($startDate !== '' && $endDate !== '');

if ($pakaiRentang) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $pakaiRentang = false;
    }
}

$bulan = isset($_GET['bulan'])
    ? max(1, min(12, (int)$_GET['bulan']))
    : (int)date('m');

$tahun = isset($_GET['tahun'])
    ? (int)$_GET['tahun']
    : (int)date('Y');

if ($pakaiRentang) {
    if ($startDate > $endDate) {
        $tmp       = $startDate;
        $startDate = $endDate;
        $endDate   = $tmp;
    }
    $periodeLabel = date('d/m/Y', strtotime($startDate))
                  . ' s/d '
                  . date('d/m/Y', strtotime($endDate));
} else {
    $namaBulan = [
        1  => 'Januari',   2  => 'Februari', 3  => 'Maret',
        4  => 'April',     5  => 'Mei',      6  => 'Juni',
        7  => 'Juli',      8  => 'Agustus',  9  => 'September',
        10 => 'Oktober',   11 => 'November', 12 => 'Desember'
    ][$bulan];

    $periodeLabel = $namaBulan . ' ' . $tahun;
}

$idKawatFilter = isset($_GET['id_kawat']) ? (int)$_GET['id_kawat'] : 0;

/* =========================================================
   NAMA UKURAN FILTER
   ========================================================= */

$ukuranFilterText = 'Semua Ukuran';

if ($idKawatFilter > 0) {
    $stmtUkuran = $koneksi->prepare("
        SELECT ukuran FROM master_kawat WHERE id_kawat = ? LIMIT 1
    ");
    $stmtUkuran->bind_param("i", $idKawatFilter);
    $stmtUkuran->execute();
    $rowUkuran = $stmtUkuran->get_result()->fetch_assoc();
    if ($rowUkuran) {
        $ukuranFilterText = number_format((float)$rowUkuran['ukuran'], 2, ',', '.') . ' mm';
    }
    $stmtUkuran->close();
}

/* =========================================================
   QUERY REKAP PER UKURAN (sama dengan halaman cetak)
   ========================================================= */

if ($pakaiRentang) {
    $periodeSql = 't.tanggal >= ? AND t.tanggal < DATE_ADD(?, INTERVAL 1 DAY)';
    $params = [$startDate, $endDate];
    $types  = 'ss';
} else {
    $periodeSql = 'MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?';
    $params = [$bulan, $tahun];
    $types  = 'ii';
}

$ukuranSql = '';
if ($idKawatFilter > 0) {
    $ukuranSql = ' AND k.id_kawat = ? ';
    $params[]  = $idKawatFilter;
    $types    .= 'i';
}

$sqlRekap = "
    SELECT
        k.ukuran,
        COALESCE(SUM(CASE WHEN t.jenis_mutasi = 'PEMBELIAN' THEN t.qty_kg ELSE 0 END), 0) AS total_beli,
        COALESCE(SUM(CASE WHEN t.jenis_mutasi = 'RETURN_BEKAS' THEN t.qty_kg ELSE 0 END), 0) AS total_bekas,
        COALESCE(SUM(CASE WHEN t.jenis_mutasi = 'ROMBENG' THEN t.qty_kg ELSE 0 END), 0) AS total_rombeng
    FROM transaksi_kawat t
    JOIN master_kawat k ON k.id_kawat = t.id_kawat
    WHERE t.status_transaksi = 'AKTIF'
      AND t.jenis_mutasi IN ('PEMBELIAN', 'RETURN_BEKAS', 'ROMBENG')
      AND ({$periodeSql})
      {$ukuranSql}
    GROUP BY k.ukuran
    HAVING total_beli <> 0 OR total_bekas <> 0
    ORDER BY k.ukuran ASC
";

$stmtRekap = $koneksi->prepare($sqlRekap);
$stmtRekap->bind_param($types, ...$params);
$stmtRekap->execute();
$resultRekap = $stmtRekap->get_result();

$rekap = [];
while ($r = $resultRekap->fetch_assoc()) {
    $key = (string)$r['ukuran'];
    $r['selisih'] = (float)$r['total_beli'] - (float)$r['total_bekas'];
    $rekap[$key] = $r;
}
$stmtRekap->close();

/* =========================================================
   AMBIL KETERANGAN YANG SUDAH TERSIMPAN (read-only)
   ========================================================= */

function kunciUkuranCatatanKawat($ukuran)
{
    $nilai = (string)$ukuran;
    return strpos($nilai, '.') === false ? $nilai : rtrim(rtrim($nilai, '0'), '.');
}

// Periode catatan: hanya jika satu bulan kalender penuh
$periodeCatatan = null;
if (!$pakaiRentang && $tahun >= 1000 && $tahun <= 9999) {
    $periodeCatatan = sprintf('%04d-%02d-01', $tahun, $bulan);
} elseif ($pakaiRentang) {
    $awalBulan = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    if ($awalBulan && $awalBulan->format('Y-m-d') === $startDate &&
        (int)$awalBulan->format('Y') >= 1000 && $awalBulan->format('d') === '01' &&
        $awalBulan->format('Y-m-t') === $endDate) {
        $periodeCatatan = $startDate;
    }
}

$catatanRekap = [];
if ($periodeCatatan !== null) {
    try {
        $stmtCatatan = $koneksi->prepare(
            'SELECT ukuran_mm, keterangan FROM laporan_kawat_keterangan WHERE periode = ?'
        );
        $stmtCatatan->bind_param('s', $periodeCatatan);
        $stmtCatatan->execute();
        $hasilCatatan = $stmtCatatan->get_result();
        while ($catatan = $hasilCatatan->fetch_assoc()) {
            $catatanRekap[kunciUkuranCatatanKawat($catatan['ukuran_mm'])] = $catatan['keterangan'];
        }
        $stmtCatatan->close();
    } catch (Exception $e) {
        error_log('Master kawat list, baca keterangan: ' . $e->getMessage());
    }
}
/* =========================================================
   STOK KAWAT BEKAS SELURUH PERIODE (tidak terpengaruh filter)
   Akumulasi RETURN_BEKAS - ROMBENG sampai saat ini.
   ========================================================= */

$sqlBekasAll = "
    SELECT
        k.ukuran,
        COALESCE(SUM(CASE WHEN t.jenis_mutasi = 'RETURN_BEKAS' THEN t.qty_kg ELSE 0 END), 0) AS total_masuk,
        COALESCE(SUM(CASE WHEN t.jenis_mutasi = 'ROMBENG' THEN t.qty_kg ELSE 0 END), 0) AS total_rombeng
    FROM transaksi_kawat t
    JOIN master_kawat k ON k.id_kawat = t.id_kawat
    WHERE t.status_transaksi = 'AKTIF'
      AND t.jenis_mutasi IN ('RETURN_BEKAS', 'ROMBENG')
    GROUP BY k.ukuran
    HAVING total_masuk <> 0 OR total_rombeng <> 0
    ORDER BY k.ukuran ASC
";

$resultBekasAll = $koneksi->query($sqlBekasAll);
$bekasAll = [];
while ($r = $resultBekasAll->fetch_assoc()) {
    $r['sisa'] = (float)$r['total_masuk'] - (float)$r['total_rombeng'];
    $bekasAll[(string)$r['ukuran']] = $r;
}
/* =========================================================
   HELPER
   ========================================================= */

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatUkuran($ukuran)
{
    return number_format((float)$ukuran, 2, ',', '.') . ' mm';
}

function formatKg($kg)
{
    return number_format((float)$kg, 2, ',', '.');
}

$namaBulan = [
    1  => 'Januari',   2  => 'Februari', 3  => 'Maret',
    4  => 'April',     5  => 'Mei',      6  => 'Juni',
    7  => 'Juli',      8  => 'Agustus',  9  => 'September',
    10 => 'Oktober',   11 => 'November', 12 => 'Desember'
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rekap Master Kawat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{background:#f4f7fb}
.wrap{max-width:1500px;margin:25px auto}
.head{background:linear-gradient(135deg,#198754,#0b5d3b);color:white;padding:18px 22px;border-radius:14px 14px 0 0}
.bodyx{background:#fff;padding:22px;border-radius:0 0 14px 14px;box-shadow:0 5px 18px rgba(0,0,0,.08)}
.table thead th{background:#26364a;color:#fff;font-size:11px;text-transform:uppercase;white-space:nowrap}
.table td{vertical-align:middle}
.total{font-weight:700;color:#0d6efd}
.keterangan-cell{white-space:pre-wrap;font-size:12px;max-width:380px}
.filter-box{background:#f8f9fa;border:1px solid #e3e6ea;border-radius:10px;padding:14px 16px;margin-bottom:16px}
</style>
</head>
<body>
<div class="container-fluid wrap">


    <!-- ===================== TABEL REKAP PERIODE (yang sudah ada) ===================== -->
<div class="head d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h5 class="mb-1"><i class="fas fa-list-ul me-2"></i>Rekap Stok Kawat</h5>
        <small class="opacity-75">Rekap per ukuran berdasarkan periode filter. Keterangan diambil dari catatan yang sudah tersimpan.</small>
    </div>
    <a href="master_kawat.php" class="btn btn-light btn-sm"><i class="fas fa-arrow-left me-1"></i>Kembali</a>
</div>
<div class="bodyx">

    <!-- ===================== FILTER ===================== -->
    <div class="filter-box">
        <form method="get" action="" id="form-filter" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1 small fw-bold">Mode Filter</label>
                <select name="mode" id="mode-filter" class="form-select form-select-sm">
                    <option value="bulan" <?= !$pakaiRentang ? 'selected' : '' ?>>Bulan / Tahun</option>
                    <option value="rentang" <?= $pakaiRentang ? 'selected' : '' ?>>Rentang Tanggal</option>
                </select>
            </div>

            <div class="col-md-2 mode-bulan">
                <label class="form-label mb-1 small fw-bold">Bulan</label>
                <select name="bulan" class="form-select form-select-sm">
                    <?php foreach ($namaBulan as $num => $nm): ?>
                        <option value="<?= $num ?>" <?= $bulan === $num ? 'selected' : '' ?>><?= h($nm) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 mode-bulan">
                <label class="form-label mb-1 small fw-bold">Tahun</label>
                <input type="number" name="tahun" class="form-control form-control-sm" value="<?= h($tahun) ?>" min="2000" max="9999">
            </div>

            <div class="col-md-2 mode-rentang d-none">
                <label class="form-label mb-1 small fw-bold">Start Date</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= h($startDate) ?>">
            </div>
            <div class="col-md-2 mode-rentang d-none">
                <label class="form-label mb-1 small fw-bold">End Date</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= h($endDate) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label mb-1 small fw-bold">Ukuran (opsional)</label>
                <select name="id_kawat" class="form-select form-select-sm">
                    <option value="0">Semua Ukuran</option>
                    <?php
                    $optUkuran = $koneksi->query("SELECT id_kawat, ukuran FROM master_kawat ORDER BY ukuran");
                    while ($u = $optUkuran->fetch_assoc()):
                    ?>
                        <option value="<?= (int)$u['id_kawat'] ?>" <?= $idKawatFilter === (int)$u['id_kawat'] ? 'selected' : '' ?>>
                            <?= h(formatUkuran($u['ukuran'])) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-filter me-1"></i>Terapkan</button>
            </div>
        </form>
    </div>

    <div class="alert alert-info py-2">
        <b>Periode:</b> <?= h($periodeLabel) ?> &nbsp;|&nbsp; <b>Ukuran:</b> <?= h($ukuranFilterText) ?>
    </div>

    <!-- ===================== TABEL REKAP ===================== -->
      
    <?php
    // Hitung total keseluruhan dari semua ukuran yang tampil
    $grandBeli    = 0.0;
    $grandBekas   = 0.0;
    $grandRombeng = 0.0;
    $grandSelisih = 0.0;
    foreach ($rekap as $r) {
        $grandBeli    += (float)$r['total_beli'];
        $grandBekas   += (float)$r['total_bekas'];
        $grandRombeng += (float)$r['total_rombeng'];
        $grandSelisih += (float)$r['selisih'];
    }
    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Ukuran</th>
                    <th class="text-end">Total Beli (KG)</th>
                    <th class="text-end">Total Bekas (KG)</th>
                    <th class="text-end">Rombeng (KG)</th>
                    <th class="text-end">Selisih (KG)</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rekap): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted fst-italic">
                            Tidak ada total pembelian atau pengembalian bekas pada periode ini.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php $no = 0; foreach ($rekap as $r): ?>
                    <?php $kodeUkuran = kunciUkuranCatatanKawat($r['ukuran']); ?>
                    <?php $isiCatatan = $catatanRekap[$kodeUkuran] ?? ''; ?>
                    <tr>
                        <td class="text-center"><?= ++$no ?></td>
                        <td><span class="badge bg-danger"><?= h(formatUkuran($r['ukuran'])) ?></span></td>
                        <td class="text-end"><?= h(formatKg($r['total_beli'])) ?></td>
                        <td class="text-end"><?= h(formatKg($r['total_bekas'])) ?></td>
                        <td class="text-end"><?= h(formatKg($r['total_rombeng'])) ?></td>
                        <td class="text-end total"><?= h(formatKg($r['selisih'])) ?></td>
                        <td class="keterangan-cell">
                            <?php if ($isiCatatan !== ''): ?>
                                <?= nl2br(h($isiCatatan)) ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($rekap): ?>
            <tfoot>
                <tr class="table-success fw-bold">
                    <td colspan="2" class="text-center">TOTAL KESELURUHAN</td>
                    <td class="text-end"><?= h(formatKg($grandBeli)) ?></td>
                    <td class="text-end"><?= h(formatKg($grandBekas)) ?></td>
                    <td class="text-end"><?= h(formatKg($grandRombeng)) ?></td>
                    <td class="text-end total"><?= h(formatKg($grandSelisih)) ?></td>
                    <td class="text-muted fst-italic small">—</td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
        <!-- ===================== STOK KAWAT BEKAS SELURUH PERIODE ===================== -->
    <?php
    $gbMasuk   = 0.0;
    $gbRombeng = 0.0;
    $gbSisa    = 0.0;
    foreach ($bekasAll as $b) {
        $gbMasuk   += (float)$b['total_masuk'];
        $gbRombeng += (float)$b['total_rombeng'];
        $gbSisa    += (float)$b['sisa'];
    }
    ?>
    <h6 class="fw-bold mt-2 mb-2">
        <i class="fas fa-recycle me-1 text-success"></i>
        Stok Kawat Bekas Seluruh Periode (Belum Dirombeng)
    </h6>
    <div class="alert alert-success py-2 small">
        Akumulasi seluruh periode, <b>tidak terpengaruh filter</b> di atas. Sisa bekas = total bekas masuk − total sudah dirombeng.
    </div>
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-sm align-middle">
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Ukuran</th>
                    <th class="text-end">Total Bekas Masuk (KG)</th>
                    <th class="text-end">Sudah Rombeng (KG)</th>
                    <th class="text-end">Sisa Belum Rombeng (KG)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$bekasAll): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted fst-italic">
                            Belum ada transaksi kawat bekas maupun rombeng.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php $nb = 0; foreach ($bekasAll as $b): ?>
                    <tr>
                        <td class="text-center"><?= ++$nb ?></td>
                        <td><span class="badge bg-danger"><?= h(formatUkuran($b['ukuran'])) ?></span></td>
                        <td class="text-end"><?= h(formatKg($b['total_masuk'])) ?></td>
                        <td class="text-end"><?= h(formatKg($b['total_rombeng'])) ?></td>
                        <td class="text-end fw-bold text-success"><?= h(formatKg($b['sisa'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($bekasAll): ?>
            <tfoot>
                <tr class="table-success fw-bold">
                    <td colspan="2" class="text-center">TOTAL KESELURUHAN</td>
                    <td class="text-end"><?= h(formatKg($gbMasuk)) ?></td>
                    <td class="text-end"><?= h(formatKg($gbRombeng)) ?></td>
                    <td class="text-end"><?= h(formatKg($gbSisa)) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <hr>
    <?php if ($periodeCatatan === null): ?>
        <div class="alert alert-warning py-2 small mt-2">
            Keterangan hanya tersimpan untuk periode satu bulan kalender penuh. Untuk rentang tanggal bebas, kolom keterangan tidak dapat ditampilkan.
        </div>
    <?php endif; ?>

</div>
</div>

<script>
(function () {
    var mode = document.getElementById('mode-filter');
    function toggleMode() {
        var isBulan = mode.value === 'bulan';
        document.querySelectorAll('.mode-bulan').forEach(function (el) {
            el.classList.toggle('d-none', !isBulan);
        });
        document.querySelectorAll('.mode-rentang').forEach(function (el) {
            el.classList.toggle('d-none', isBulan);
        });
    }
    mode.addEventListener('change', toggleMode);
    toggleMode();
})();
</script>
</body>
</html>