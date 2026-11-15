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
$simpanKeterangan = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$csrfCatatan = $_SESSION['csrf_laporan_kawat_keterangan'] ?? '';

if ($simpanKeterangan && (
    ($_POST['aksi'] ?? '') !== 'simpan_keterangan' ||
    $csrfCatatan === '' ||
    !is_string($_POST['csrf'] ?? null) ||
    !hash_equals($csrfCatatan, $_POST['csrf'])
)) {
    http_response_code(403);
    exit('Permintaan tidak valid. Muat ulang laporan dan coba lagi.');
}

if ($csrfCatatan === '') {
    $csrfCatatan = bin2hex(random_bytes(32));
    $_SESSION['csrf_laporan_kawat_keterangan'] = $csrfCatatan;
}

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
        $ukuranFilterText = formatUkuran($rowUkuran['ukuran']);
    }
    $stmtUkuran->close();
}

// Rekap dan detail menggunakan periode serta filter ukuran yang sama.
if ($pakaiRentang) {
    $periodeSql = 't.tanggal >= ? AND t.tanggal < DATE_ADD(?, INTERVAL 1 DAY)';
    $params = [$startDate, $endDate];
    $types = 'ss';
} else {
    $periodeSql = 'MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ?';
    $params = [$bulan, $tahun];
    $types = 'ii';
}

$ukuranSql = '';
if ($idKawatFilter > 0) {
    $ukuranSql = ' AND k.id_kawat = ? ';
    $params[] = $idKawatFilter;
    $types .= 'i';
}

// Bekas pada rekap adalah pengembalian dari pabrik, bukan penjualan/rombeng.
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
$kelompokPerUkuran = [];
$ukuranBerpasangan = [];
$mutasiPengecualian = ['hanya_beli' => [], 'hanya_bekas' => []];
while ($r = $resultRekap->fetch_assoc()) {
    $key = (string)$r['ukuran'];
    $beli = (float)$r['total_beli'];
    $bekas = (float)$r['total_bekas'];
    $r['selisih'] = $beli - $bekas;
    $r['mutasi'] = [];
    $rekap[$key] = $r;

    $grup = $beli == 0.0 ? 'hanya_bekas' : ($bekas == 0.0 ? 'hanya_beli' : 'berpasangan');
    $kelompokPerUkuran[$key] = $grup;
    if ($grup === 'berpasangan') {
        $ukuranBerpasangan[] = $key;
    }
}
$stmtRekap->close();

// Penyimpanan hanya untuk satu bulan kalender penuh.
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

function kunciUkuranCatatanKawat($ukuran)
{
    // Samakan misalnya 0.2500 dari master dengan 0.25000000 dari tabel catatan.
    $nilai = (string)$ukuran;
    return strpos($nilai, '.') === false ? $nilai : rtrim(rtrim($nilai, '0'), '.');
}

$catatanRekap = [];
$ukuranCatatanValid = [];
foreach ($rekap as $r) {
    $ukuranCatatanValid[kunciUkuranCatatanKawat($r['ukuran'])] = true;
}

$catatanBisaDisimpan = $periodeCatatan !== null;
$pesanCatatan = '';
$catatanGagal = false;
$filterKembali = $pakaiRentang
    ? ['start_date' => $startDate, 'end_date' => $endDate]
    : ['bulan' => $bulan, 'tahun' => $tahun];
if ($idKawatFilter > 0) {
    $filterKembali['id_kawat'] = $idKawatFilter;
}
$urlKembali = '?' . http_build_query($filterKembali);
if (!$simpanKeterangan && isset($_SESSION['flash_catatan_kawat'][$urlKembali])) {
    $pesanCatatan = $_SESSION['flash_catatan_kawat'][$urlKembali];
    unset($_SESSION['flash_catatan_kawat'][$urlKembali]);
}

if ($catatanBisaDisimpan) {
    try {
        $stmtCatatan = $koneksi->prepare(
            'SELECT ukuran_mm, keterangan FROM laporan_kawat_keterangan WHERE periode = ?'
        );
        if (!$stmtCatatan || !$stmtCatatan->bind_param('s', $periodeCatatan) || !$stmtCatatan->execute()) {
            throw new RuntimeException('Query catatan tidak dapat dijalankan.');
        }
        $hasilCatatan = $stmtCatatan->get_result();
        if (!$hasilCatatan) {
            throw new RuntimeException('Hasil catatan tidak tersedia.');
        }
        while ($catatan = $hasilCatatan->fetch_assoc()) {
            $catatanRekap[kunciUkuranCatatanKawat($catatan['ukuran_mm'])] = $catatan['keterangan'];
        }
        $stmtCatatan->close();
    } catch (Exception $e) {
        error_log('Laporan kawat, baca keterangan: ' . $e->getMessage());
        $catatanBisaDisimpan = false;
        $catatanGagal = true;
        $pesanCatatan = 'Catatan belum bisa dimuat. Periksa apakah tabel laporan_kawat_keterangan sudah dibuat.';
    }
}

if ($simpanKeterangan) {
    $masukan = $_POST['keterangan'] ?? [];
    $perubahan = [];
    if (!$catatanBisaDisimpan || ($_POST['periode_catatan'] ?? '') !== $periodeCatatan || !is_array($masukan)) {
        $catatanGagal = true;
        $pesanCatatan = 'Keterangan tidak disimpan. Muat ulang laporan dan pastikan periodenya satu bulan kalender penuh.';
    } else {
        foreach ($masukan as $ukuran => $isi) {
            if (!isset($ukuranCatatanValid[$ukuran]) || !is_string($isi)) {
                $catatanGagal = true;
                $pesanCatatan = 'Data ukuran berubah atau tidak valid. Muat ulang laporan sebelum menyimpan.';
                break;
            }
            $isi = trim(str_replace(["\r\n", "\r"], "\n", $isi));
            if (preg_match('/\A.{0,1000}\z/us', $isi) !== 1) {
                $catatanGagal = true;
                $pesanCatatan = 'Keterangan harus berupa teks maksimal 1.000 karakter per ukuran.';
                break;
            }
            if ($isi !== ($catatanRekap[$ukuran] ?? '')) {
                $perubahan[$ukuran] = $isi;
            }
        }

        if (!$catatanGagal) {
            $dalamTransaksi = false;
            try {
                if ($perubahan) {
                    if (!$koneksi->begin_transaction()) {
                        throw new RuntimeException('Transaksi catatan tidak dapat dimulai.');
                    }
                    $dalamTransaksi = true;
                    $stmtSimpan = $koneksi->prepare(
                        'INSERT INTO laporan_kawat_keterangan (periode, ukuran_mm, keterangan, updated_by)
                         VALUES (?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE keterangan = ?, updated_by = ?'
                    );
                    $stmtHapus = $koneksi->prepare(
                        'DELETE FROM laporan_kawat_keterangan WHERE periode = ? AND ukuran_mm = ?'
                    );
                    if (!$stmtSimpan || !$stmtHapus) {
                        throw new RuntimeException('Query simpan/hapus catatan tidak siap.');
                    }
                    foreach ($perubahan as $ukuran => $isi) {
                        $ukuran = (string)$ukuran;
                        if ($isi === '') {
                            $berhasil = $stmtHapus->bind_param('ss', $periodeCatatan, $ukuran) && $stmtHapus->execute();
                        } else {
                            $berhasil = $stmtSimpan->bind_param(
                                'ssssss', $periodeCatatan, $ukuran, $isi, $user, $isi, $user
                            ) && $stmtSimpan->execute();
                        }
                        if (!$berhasil) {
                            throw new RuntimeException('Perubahan catatan gagal dijalankan.');
                        }
                    }
                    $stmtSimpan->close();
                    $stmtHapus->close();
                    if (!$koneksi->commit()) {
                        throw new RuntimeException('Transaksi catatan gagal disimpan.');
                    }
                    $dalamTransaksi = false;
                }
                $_SESSION['flash_catatan_kawat'] = [
                    $urlKembali => $perubahan ? 'Keterangan berhasil disimpan.' : 'Tidak ada perubahan keterangan.',
                ];
                header('Location: ' . $urlKembali, true, 303);
                exit;
            } catch (Exception $e) {
                if ($dalamTransaksi) {
                    $koneksi->rollback();
                }
                error_log('Laporan kawat, simpan keterangan: ' . $e->getMessage());
                $catatanGagal = true;
                $pesanCatatan = 'Keterangan gagal disimpan. Isian masih tersedia, silakan coba lagi.';
            }
        }
    }
    // Pertahankan draf ketika validasi atau penyimpanan gagal.
    if (is_array($masukan)) {
        foreach ($masukan as $ukuran => $isi) {
            if (isset($ukuranCatatanValid[$ukuran]) && is_string($isi)) {
                $catatanRekap[$ukuran] = $isi;
            }
        }
    }
}

if ($rekap) {
    $sqlDetail = "
        SELECT
            k.ukuran, t.tanggal, t.jenis_mutasi, t.kondisi, t.arah,
            t.qty_kg, t.keterangan, s.nama_satuan
        FROM transaksi_kawat t
        JOIN master_kawat k ON k.id_kawat = t.id_kawat
        LEFT JOIN master_satuan s ON s.id_satuan = t.id_satuan_asal
        WHERE t.status_transaksi = 'AKTIF'
          AND t.jenis_mutasi IN ('PEMBELIAN', 'RETURN_BEKAS')
          AND ({$periodeSql})
          {$ukuranSql}
        ORDER BY DATE(t.tanggal) ASC,
                 CASE WHEN t.jenis_mutasi = 'RETURN_BEKAS' THEN 0 ELSE 1 END,
                 t.tanggal ASC, t.id_transaksi ASC
    ";

    $stmtDetail = $koneksi->prepare($sqlDetail);
    $stmtDetail->bind_param($types, ...$params);
    $stmtDetail->execute();
    $resultDetail = $stmtDetail->get_result();

    while ($r = $resultDetail->fetch_assoc()) {
        $key = (string)$r['ukuran'];
        if (!isset($rekap[$key])) {
            continue;
        }
        $grup = $kelompokPerUkuran[$key];
        if ($grup === 'berpasangan') {
            $rekap[$key]['mutasi'][] = $r;
        } elseif (
            ($grup === 'hanya_beli' && $r['jenis_mutasi'] === 'PEMBELIAN') ||
            ($grup === 'hanya_bekas' && $r['jenis_mutasi'] === 'RETURN_BEKAS')
        ) {
            $mutasiPengecualian[$grup][$key][] = $r;
        }
    }
    $stmtDetail->close();
}

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

function keteranganCetakKawat(array $row)
{
    $catatan = trim((string)($row['keterangan'] ?? ''));
    if ($row['jenis_mutasi'] !== 'PEMBELIAN' ||
        !preg_match('/^\[?auto\s+dari\s+pembelian\b/iu', $catatan)) {
        return $catatan;
    }

    // Format sinkronisasi: "Auto dari pembelian: ... | Supplier: ... | catatan".
    // Batasi pemisahan agar karakter "|" di dalam catatan tidak ikut terpotong.
    $bagian = explode('|', $catatan, 3);
    if (isset($bagian[1]) && preg_match('/^\s*(?:supplier|pemasok)\s*:/iu', $bagian[1])) {
        $manual = trim($bagian[2] ?? '');
        return preg_replace('/^(?:keterangan|catatan)\s*:\s*/iu', '', $manual) ?? $manual;
    }

    // Cadangan untuk data lama yang tidak memakai format pemisah baku.
    if (preg_match('/\b(?:supplier|pemasok)\b\s*[:=]?\s*(.*)\z/isu', $catatan, $supplier)) {
        $sisa = $supplier[1];
        if (preg_match('/(?:^|[\s|;)\]])(?:keterangan|catatan)\s*:\s*(.*)\z/isu', $sisa, $manual)) {
            return trim($manual[1]);
        }
        if (preg_match('~(?:\r\n|\r|\n|\||;|\s+[–—/:-]\s+|\)\s+|\]\s+)(?:[|;–—/:-]\s+)?\s*(.*)\z~su', $sisa, $manual)) {
            return trim($manual[1]);
        }
    } elseif (preg_match('/(?:^|[\s|;)\]])(?:keterangan|catatan)\s*:\s*(.*)\z/isu', $catatan, $manual)) {
        return trim($manual[1]);
    }

    return '';
}

function tabelMutasiKawat(array $baris)
{
    ?>
    <table class="table-data">
        <colgroup>
            <col style="width:14%;">
            <col style="width:12%;">
            <col style="width:14%;">
            <col style="width:9%;">
            <col style="width:51%;">
        </colgroup>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Kondisi</th>
                <th>Masuk (KG)</th>
                <th>Satuan</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($baris as $i => $r):
                $catatan = keteranganCetakKawat($r);
            ?>
                <tr class="<?= $i % 2 ? 'bg-even' : 'bg-odd' ?>">
                    <td class="text-center nowrap"><?= h(date('d/m/Y', strtotime($r['tanggal']))) ?></td>
                    <td class="text-center"><?= h($r['kondisi']) ?></td>
                    <td class="text-right in"><?= $r['arah'] === 'IN' ? h(formatKg($r['qty_kg'])) : '-' ?></td>
                    <td class="text-center"><?= h($r['nama_satuan'] ?: 'KG') ?></td>
                    <td><?= $catatan !== '' ? nl2br(h($catatan)) : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$baris): ?>
                <tr class="empty-row"><td colspan="5" class="text-center">Tidak ada transaksi untuk periode dan ukuran yang dipilih.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Cetak Laporan Mutasi Kawat</title>
<style>
/* =========================================================
   LAYAR (default)
   ========================================================= */
body {
    font-family: Calibri, Arial, sans-serif;
    font-size: 12px;
    margin: 12px;
    color: #000;
    background: #fff;
}

.title {
    text-align: center;
    font-size: 17px;
    font-weight: bold;
}

.sub {
    text-align: center;
    font-size: 12px;
}

.sub-note {
    text-align: center;
    font-size: 11px;
    color: #555;
    font-style: italic;
}

.section-title {
    font-size: 13px;
    font-weight: bold;
    margin-top: 12px;
    margin-bottom: 3px;
}

.table-data {
    border-collapse: collapse;
    width: 100%;
    margin-top: 3px;
    table-layout: fixed;
}

.table-data th {
    background: #244a7c;
    color: #ffffff;
    border: 1px solid #000000;
    padding: 4px 4px;
    text-align: center;
    vertical-align: middle;
    font-size: 11px;
    line-height: 1.2;
    word-wrap: break-word;
}

.table-data td {
    border: 1px solid #000000;
    padding: 3px 4px;
    vertical-align: middle;
    font-size: 11px;
    line-height: 1.25;
    word-wrap: break-word;
    overflow-wrap: anywhere;
}

.text-center { text-align: center; }
.text-right  { text-align: right; }
.text-left   { text-align: left; }
.bold        { font-weight: bold; }

.in  { color: #008000; font-weight: bold; }

.bg-even { background: #f9f9f9; }
.bg-odd  { background: #ffffff; }


/* Tombol aksi (tidak tercetak) */
.action-bar {
    text-align: right;
    margin-bottom: 10px;
}

.action-bar button {
    background: #c00000;
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
}

.action-bar button:hover {
    background: #a00000;
}

/* =========================================================
   ATURAN CETAK / SIMPAN PDF — A4 PORTRAIT
   ========================================================= */
@media print {

    @page {
        size: A4 portrait;
        margin: 8mm 8mm 8mm 8mm;
    }

    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        font-size: 11px;
    }

    body {
        padding: 0 1mm;
        box-sizing: border-box;
    }

    .action-bar {
        display: none !important;
    }

    thead { display: table-header-group; }
    tfoot { display: table-footer-group; }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    .table-data {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        box-sizing: border-box;
        page-break-inside: auto;
    }

    .table-data th,
    .table-data td {
        box-sizing: border-box;
    }

    .table-data th {
        background: #244a7c !important;
        color: #ffffff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        font-size: 10.5px;
        padding: 3px 3px;
        line-height: 1.15;
    }

    .table-data td {
        font-size: 10.5px;
        padding: 2.5px 3px;
        line-height: 1.2;
    }

    .in  { color: #008000 !important; }

    .bg-even { background: #f2f2f2 !important; }
    .bg-odd  { background: #ffffff !important; }


    .title, .sub, .sub-note, .section-title {
        page-break-after: avoid;
    }

    .block-detail {
        page-break-before: auto;
        page-break-inside: auto;
        margin-top: 10px;
    }

    .empty-row td {
        color: #777 !important;
        font-style: italic;
    }
}


.size-title {
    font-size: 13px;
    margin: 10px 0 3px;
    break-after: avoid;
    page-break-after: avoid;
}
.nowrap { white-space: nowrap; }
.note-input {
    display: block;
    box-sizing: border-box;
    width: 100%;
    min-height: 2.5em;
    resize: vertical;
    padding: 2px 3px;
    border: 1px solid #bbb;
    font: inherit;
    color: inherit;
}
.catatan-status { margin: 5px 0; font-weight: bold; }
.catatan-aksi { margin-top: 6px; text-align: right; }
.catatan-simpan {
    background: #244a7c;
    color: #fff;
    border: 0;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font: inherit;
}
.note-print { display: none; }
@media print {
    .screen-only, .note-input { display: none !important; }
    .note-print {
        display: block;
        min-height: 1.5em;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }
}
</style>
</head>
<body>
<div class="action-bar">
    <button type="button" onclick="window.print()">🖨️ Cetak / Simpan PDF (Portrait)</button>
</div>
<div class="title">LAPORAN MUTASI STOK KAWAT MCP</div>
<div class="sub">Periode <?= h($periodeLabel) ?></div>
<div class="sub"><?= h($ukuranFilterText) ?></div>
<br>

<h2 class="section-title">REKAP PER UKURAN (PERIODE FILTER)</h2>
<div class="sub-note text-left screen-only">
    <?php if ($catatanBisaDisimpan): ?>
        Klik Simpan Keterangan untuk membuka kembali catatan ukuran ini pada bulan yang sama.
    <?php elseif ($periodeCatatan === null): ?>
        Rentang ini bukan satu bulan kalender penuh. Keterangan hanya berlaku untuk cetakan ini.
    <?php else: ?>
        Penyimpanan keterangan tidak tersedia. Keterangan hanya berlaku untuk cetakan ini.
    <?php endif; ?>
</div>
<?php if ($pesanCatatan !== ''): ?>
    <p class="screen-only catatan-status" role="<?= $catatanGagal ? 'alert' : 'status' ?>"><?= h($pesanCatatan) ?></p>
<?php endif; ?>
<form method="post" action="<?= h($urlKembali) ?>" id="form-keterangan-kawat">
    <?php if ($catatanBisaDisimpan): ?>
        <input type="hidden" name="aksi" value="simpan_keterangan">
        <input type="hidden" name="csrf" value="<?= h($csrfCatatan) ?>">
        <input type="hidden" name="periode_catatan" value="<?= h($periodeCatatan) ?>">
    <?php endif; ?>
<table class="table-data">
    <colgroup>
        <col style="width:5%;">
        <col style="width:12%;">
        <col style="width:13%;">
        <col style="width:14%;">
        <col style="width:13%;">
        <col style="width:12%;">
        <col style="width:31%;">
    </colgroup>
    <thead>
        <tr>
            <th>No</th>
            <th>Ukuran</th>
            <th>Total Beli (KG)</th>
            <th>Total Bekas (KG)</th>
            <th>Rombeng (KG)</th>
            <th>Selisih (KG)</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$rekap): ?>
            <tr class="empty-row"><td colspan="7" class="text-center">Tidak ada total pembelian atau pengembalian bekas pada periode ini.</td></tr>
        <?php endif; ?>
        <?php $no = 0; foreach ($rekap as $r): ?>
            <tr>
                <td class="text-center"><?= ++$no ?></td>
                <td class="text-center nowrap"><?= h(formatUkuran($r['ukuran'])) ?></td>
                <td class="text-right"><?= h(formatKg($r['total_beli'])) ?></td>
                <td class="text-right"><?= h(formatKg($r['total_bekas'])) ?></td>
                <td class="text-right"><?= h(formatKg($r['total_rombeng'])) ?></td>
                <td class="text-right bold"><?= h(formatKg($r['selisih'])) ?></td>
                <td>
                    <?php $kodeUkuran = kunciUkuranCatatanKawat($r['ukuran']); ?>
                    <?php $isiCatatan = $catatanRekap[$kodeUkuran] ?? ''; ?>
                    <textarea class="note-input" rows="1" maxlength="1000"
                        <?php if ($catatanBisaDisimpan): ?>name="keterangan[<?= h($kodeUkuran) ?>]"<?php endif; ?>
                        aria-label="Alasan selisih untuk ukuran <?= h(formatUkuran($r['ukuran'])) ?>"><?= h($isiCatatan) ?></textarea>
                    <div class="note-print"><?= h($isiCatatan) ?></div>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php if ($catatanBisaDisimpan && $rekap): ?>
    <div class="screen-only catatan-aksi">
        <button type="submit" class="catatan-simpan">Simpan Keterangan</button>
    </div>
<?php endif; ?>
</form>

<section class="block-detail">
    <h2 class="section-title">DETAIL MUTASI PER UKURAN</h2>

    <?php if (!$ukuranBerpasangan): ?>
        <p class="sub-note text-left">Tidak ada ukuran yang memiliki pembelian sekaligus pengembalian bekas pada periode ini.</p>
    <?php endif; ?>
    <?php foreach ($ukuranBerpasangan as $key): ?>
        <h3 class="size-title">UKURAN <?= h(formatUkuran($rekap[$key]['ukuran'])) ?></h3>
        <?php tabelMutasiKawat($rekap[$key]['mutasi']); ?>
    <?php endforeach; ?>
</section>

<?php
$judulPengecualian = [
    'hanya_beli' => 'ADA PEMBELIAN, BELUM ADA BEKAS',
    'hanya_bekas' => 'ADA BEKAS, TIDAK ADA PEMBELIAN',
];
foreach ($judulPengecualian as $grup => $judul):
?>
    <section class="block-detail">
        <h2 class="section-title"><?= h($judul) ?></h2>
        <?php if (!$mutasiPengecualian[$grup]): ?>
            <?php tabelMutasiKawat([]); ?>
        <?php else: ?>
            <?php foreach ($rekap as $key => $ringkasan): ?>
                <?php if (!isset($mutasiPengecualian[$grup][$key])) continue; ?>
                <h3 class="size-title">UKURAN <?= h(formatUkuran($ringkasan['ukuran'])) ?></h3>
                <?php tabelMutasiKawat($mutasiPengecualian[$grup][$key]); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<script>
function sinkronCatatanCetak(input) {
    input.nextElementSibling.textContent = input.value;
}
document.querySelectorAll('.note-input').forEach(function (input) {
    input.addEventListener('input', function () {
        sinkronCatatanCetak(input);
    });
});
document.getElementById('form-keterangan-kawat').addEventListener('formdata', function (event) {
    // Kirim hanya sel yang diubah agar catatan pengguna lain tidak tertimpa.
    this.querySelectorAll('.note-input[name]').forEach(function (input) {
        if (input.value === input.defaultValue) {
            event.formData.delete(input.name);
        }
    });
});
window.addEventListener('beforeprint', function () {
    document.querySelectorAll('.note-input').forEach(sinkronCatatanCetak);
});
</script>
</body>
</html>
