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

/*
 * Default bulan & tahun — selalu di-set di awal,
 * supaya terdefinisi baik di mode bulan maupun mode rentang.
 */
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
        1  => 'Januari',
        2  => 'Februari',
        3  => 'Maret',
        4  => 'April',
        5  => 'Mei',
        6  => 'Juni',
        7  => 'Juli',
        8  => 'Agustus',
        9  => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ][$bulan];

    $periodeLabel = $namaBulan . ' ' . $tahun;
}

$idKawatFilter = isset($_GET['id_kawat'])
    ? (int)$_GET['id_kawat']
    : 0;

/* =========================================================
   NAMA UKURAN FILTER
   ========================================================= */

$ukuranFilterText = 'Semua Ukuran';

if ($idKawatFilter > 0) {

    $stmtUkuran = $koneksi->prepare("
        SELECT ukuran
        FROM master_kawat
        WHERE id_kawat = ?
        LIMIT 1
    ");

    $stmtUkuran->bind_param("i", $idKawatFilter);
    $stmtUkuran->execute();

    $rowUkuran = $stmtUkuran->get_result()->fetch_assoc();

    if ($rowUkuran) {
        $ukuranFilterText =
            number_format((float)$rowUkuran['ukuran'], 2) . ' mm';
    }

    $stmtUkuran->close();
}

/* =========================================================
   HELPER QUERY DETAIL — dipakai untuk PEMBELIAN & BEKAS
   ========================================================= */

function buildFilterPeriodeDetail($pakaiRentang, $startDate, $endDate, $bulan, $tahun)
{
    if ($pakaiRentang) {
        return [
            " AND t.tanggal BETWEEN ? AND ? ",
            [$startDate, $endDate],
            'ss'
        ];
    }

    return [
        " AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ? ",
        [$bulan, $tahun],
        'ii'
    ];
}

/* =========================================================
   QUERY DETAIL MUTASI — PEMBELIAN
   ========================================================= */

$sqlDetail = "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.id_kawat,
        k.ukuran,
        t.jenis_mutasi,
        t.kondisi,
        t.arah,
        t.qty_asal,
        s.nama_satuan,
        t.qty_kg,
        t.no_referensi,
        t.keterangan,
        t.created_by,
        t.source_type,
        t.source_id
    FROM transaksi_kawat t

    JOIN master_kawat k
        ON k.id_kawat = t.id_kawat

    LEFT JOIN master_satuan s
        ON s.id_satuan = t.id_satuan_asal

    WHERE
        t.status_transaksi = 'AKTIF'
        AND t.jenis_mutasi = 'PEMBELIAN'
";

list($filterSql, $filterParams, $filterTypes) =
    buildFilterPeriodeDetail($pakaiRentang, $startDate, $endDate, $bulan, $tahun);

$sqlDetail .= $filterSql;

$paramsDetail = $filterParams;
$typesDetail  = $filterTypes;

if ($idKawatFilter > 0) {
    $sqlDetail .= " AND t.id_kawat = ? ";
    $paramsDetail[] = $idKawatFilter;
    $typesDetail   .= 'i';
}

$sqlDetail .= "
    ORDER BY
        k.ukuran ASC,
        t.tanggal DESC,
        t.id_transaksi DESC
";

$stmtDetail = $koneksi->prepare($sqlDetail);

if ($paramsDetail) {
    $stmtDetail->bind_param($typesDetail, ...$paramsDetail);
}

$stmtDetail->execute();
$resultDetail = $stmtDetail->get_result();

$detailRows = [];

while ($row = $resultDetail->fetch_assoc()) {
    $detailRows[] = $row;
}

/* =========================================================
   QUERY DETAIL MUTASI BEKAS
   Khusus kondisi BEKAS / jenis RETURN_BEKAS (Kembali dari Pabrik)
   ========================================================= */

$sqlDetailBekas = "
    SELECT
        t.id_transaksi,
        t.tanggal,
        t.id_kawat,
        k.ukuran,
        t.jenis_mutasi,
        t.kondisi,
        t.arah,
        t.qty_asal,
        s.nama_satuan,
        t.qty_kg,
        t.no_referensi,
        t.keterangan,
        t.created_by,
        t.source_type,
        t.source_id
    FROM transaksi_kawat t

    JOIN master_kawat k
        ON k.id_kawat = t.id_kawat

    LEFT JOIN master_satuan s
        ON s.id_satuan = t.id_satuan_asal

    WHERE
        t.status_transaksi = 'AKTIF'
        AND (
            t.jenis_mutasi = 'RETURN_BEKAS'
            OR t.kondisi = 'BEKAS'
        )
";

$sqlDetailBekas .= $filterSql;

$paramsDetailBekas = $filterParams;
$typesDetailBekas  = $filterTypes;

if ($idKawatFilter > 0) {
    $sqlDetailBekas .= " AND t.id_kawat = ? ";
    $paramsDetailBekas[] = $idKawatFilter;
    $typesDetailBekas   .= 'i';
}

$sqlDetailBekas .= "
    ORDER BY
        k.ukuran ASC,
        t.tanggal DESC,
        t.id_transaksi DESC
";

$stmtDetailBekas = $koneksi->prepare($sqlDetailBekas);

if ($paramsDetailBekas) {
    $stmtDetailBekas->bind_param($typesDetailBekas, ...$paramsDetailBekas);
}

$stmtDetailBekas->execute();
$resultDetailBekas = $stmtDetailBekas->get_result();

$detailBekasRows = [];

while ($row = $resultDetailBekas->fetch_assoc()) {
    $detailBekasRows[] = $row;
}

/* =========================================================
   HITUNG ROWSPAN PER UKURAN — PEMBELIAN
   ========================================================= */

$rowspanUkuran     = [];
$ukuranSudahTampil = [];

foreach ($detailRows as $row) {
    $id = (int)$row['id_kawat'];

    if (!isset($rowspanUkuran[$id])) {
        $rowspanUkuran[$id] = 0;
    }

    $rowspanUkuran[$id]++;
}

/* =========================================================
   HITUNG ROWSPAN PER UKURAN — BEKAS
   ========================================================= */

$rowspanUkuranBekas     = [];
$ukuranBekasSudahTampil = [];

foreach ($detailBekasRows as $row) {
    $id = (int)$row['id_kawat'];

    if (!isset($rowspanUkuranBekas[$id])) {
        $rowspanUkuranBekas[$id] = 0;
    }

    $rowspanUkuranBekas[$id]++;
}

/* =========================================================
   HITUNG TOTAL MASUK KG — PEMBELIAN & BEKAS
   (hanya yang arahnya IN)
   ========================================================= */

$totalMasukPembelian = 0.0;

foreach ($detailRows as $row) {
    if (($row['arah'] ?? '') === 'IN') {
        $totalMasukPembelian += (float)$row['qty_kg'];
    }
}

$totalMasukBekas = 0.0;

foreach ($detailBekasRows as $row) {
    if (($row['arah'] ?? '') === 'IN') {
        $totalMasukBekas += (float)$row['qty_kg'];
    }
}

/* =========================================================
   QUERY REKAP PER UKURAN
   — LINTAS BULAN (tidak terpengaruh filter periode)
   — Stok Bekas = Total Return Bekas − Total Rombeng
   ========================================================= */

$sqlRekap = "
    SELECT
        k.id_kawat,
        k.ukuran,

        COALESCE(SUM(CASE
            WHEN t.status_transaksi = 'AKTIF'
             AND t.jenis_mutasi = 'PEMBELIAN'
            THEN t.qty_kg ELSE 0 END), 0) AS total_beli,

        COALESCE(SUM(CASE
            WHEN t.status_transaksi = 'AKTIF'
             AND t.jenis_mutasi = 'RETURN_BEKAS'
            THEN t.qty_kg ELSE 0 END), 0) AS total_bekas,

        COALESCE(SUM(CASE
            WHEN t.status_transaksi = 'AKTIF'
             AND t.jenis_mutasi = 'ROMBENG'
            THEN t.qty_kg ELSE 0 END), 0) AS total_rombeng,

        (
            SELECT t2.tanggal
            FROM transaksi_kawat t2
            WHERE t2.id_kawat = k.id_kawat
              AND t2.status_transaksi = 'AKTIF'
              AND t2.jenis_mutasi = 'PEMBELIAN'
            ORDER BY t2.tanggal DESC, t2.id_transaksi DESC
            LIMIT 1
        ) AS tgl_beli_terakhir,

        (
            SELECT t2.qty_kg
            FROM transaksi_kawat t2
            WHERE t2.id_kawat = k.id_kawat
              AND t2.status_transaksi = 'AKTIF'
              AND t2.jenis_mutasi = 'PEMBELIAN'
            ORDER BY t2.tanggal DESC, t2.id_transaksi DESC
            LIMIT 1
        ) AS qty_beli_terakhir,

        (
            SELECT t3.tanggal
            FROM transaksi_kawat t3
            WHERE t3.id_kawat = k.id_kawat
              AND t3.status_transaksi = 'AKTIF'
              AND t3.jenis_mutasi = 'RETURN_BEKAS'
            ORDER BY t3.tanggal DESC, t3.id_transaksi DESC
            LIMIT 1
        ) AS tgl_bekas_terakhir,

        (
            SELECT t3.qty_kg
            FROM transaksi_kawat t3
            WHERE t3.id_kawat = k.id_kawat
              AND t3.status_transaksi = 'AKTIF'
              AND t3.jenis_mutasi = 'RETURN_BEKAS'
            ORDER BY t3.tanggal DESC, t3.id_transaksi DESC
            LIMIT 1
        ) AS qty_bekas_terakhir

    FROM master_kawat k

    LEFT JOIN transaksi_kawat t
        ON t.id_kawat = k.id_kawat
";

if ($idKawatFilter > 0) {
    $sqlRekap .= " WHERE k.id_kawat = ? ";
}

$sqlRekap .= "
    GROUP BY
        k.id_kawat,
        k.ukuran

    ORDER BY
        k.ukuran ASC
";

$paramsRekap = [];
$typesRekap  = '';

if ($idKawatFilter > 0) {
    $paramsRekap[] = $idKawatFilter;
    $typesRekap   .= 'i';
}

$stmtRekap = $koneksi->prepare($sqlRekap);

if ($paramsRekap) {
    $stmtRekap->bind_param($typesRekap, ...$paramsRekap);
}

$stmtRekap->execute();
$resultRekap = $stmtRekap->get_result();

/* =========================================================
   HELPERS
   ========================================================= */

function h($v)
{
    return htmlspecialchars(
        (string)$v,
        ENT_QUOTES,
        'UTF-8'
    );
}

function labelJenis($j)
{
    return [
        'PEMBELIAN'         => 'Pembelian',
        'PEMAKAIAN'         => 'Dipakai Pabrik',
        'RETURN_BEKAS'      => 'Kembali dari Pabrik',
        'ROMBENG'           => 'Rombeng / Jual Bekas',
        'ADJUSTMENT_MASUK'  => 'Adjustment Masuk',
        'ADJUSTMENT_KELUAR' => 'Adjustment Keluar'
    ][$j] ?? $j;
}

/**
 * Format kolom "tanggal = qty KG".
 * Contoh: 02/10/2026 = 1.00 KG
 * Kalau tanggal kosong / qty 0 → tampil 0 KG.
 */
function formatTanggalQty($tanggal, $qtyKg)
{
    $qty = (float)$qtyKg;

    if (!$tanggal || $qty <= 0) {
        return '0 KG';
    }

    return date('d/m/Y', strtotime($tanggal))
         . ' = '
         . number_format($qty, 2, '.', '')
         . ' KG';
}
?>
<html>

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
    font-size: 16px;
    font-weight: bold;
}

.sub {
    text-align: center;
    font-size: 11px;
}

.sub-note {
    text-align: center;
    font-size: 9px;
    color: #555;
    font-style: italic;
}

.section-title {
    font-size: 12px;
    font-weight: bold;
    margin-top: 10px;
    margin-bottom: 2px;
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
    padding: 3px 3px;
    text-align: center;
    vertical-align: middle;
    font-size: 10px;
    line-height: 1.15;
    word-wrap: break-word;
}

.table-data td {
    border: 1px solid #000000;
    padding: 2px 3px;
    vertical-align: middle;
    font-size: 10px;
    line-height: 1.2;
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

/* Sel ukuran (rowspan) */
.ukuran-cell {
    font-weight: bold;
    text-align: center;
    background: #eef3fa;
    vertical-align: middle;
}

/* Baris total */
.total-row td {
    background: #dbe6f3 !important;
    font-weight: bold;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

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
   ATURAN CETAK / SIMPAN PDF — A4 POTRAIT
   ========================================================= */
@media print {

    @page {
        size: A4 portrait;
        /* margin kanan-kiri 10mm supaya border tepi tidak terpotong */
        margin: 10mm 10mm 10mm 10mm;
    }

    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        font-size: 9.5px;
    }

    /* Wadah konten diberi padding tipis agar tabel tidak menyentuh
       batas printable area (mencegah border kanan hilang) */
    body {
        padding: 0 2mm;
        box-sizing: border-box;
    }

    .action-bar {
        display: none !important;
    }

    thead {
        display: table-header-group;
    }

    tfoot {
        display: table-footer-group;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    /* Tabel diberi lebar 100% tapi dengan box-sizing agar border
       tidak "keluar" dari area cetak */
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
        font-size: 8.5px;
        padding: 2px 2px;
        line-height: 1.1;
    }

    .table-data td {
        font-size: 8.5px;
        padding: 1.5px 2px;
        line-height: 1.15;
    }

    .in  { color: #008000 !important; }

    .bg-even { background: #f2f2f2 !important; }
    .bg-odd  { background: #ffffff !important; }

    .ukuran-cell {
        background: #e6eef8 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .total-row td {
        background: #dbe6f3 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .title, .sub, .sub-note, .section-title {
        page-break-after: avoid;
    }

    .block-detail,
    .block-detail-bekas {
        page-break-before: auto;
        page-break-inside: auto;
        margin-top: 10px;
    }

    .empty-row td {
        color: #777 !important;
        font-style: italic;
    }

    .ukuran-cell {
        page-break-inside: avoid;
    }
}

</style>

</head>


<body>

<!-- Tombol Cetak (hanya muncul di layar) -->
<div class="action-bar">
    <button type="button" onclick="window.print()">
        🖨️ Cetak / Simpan PDF (Potrait)
    </button>
</div>


<div class="title">
    LAPORAN MUTASI STOK KAWAT MCP
</div>

<div class="sub">
    Periode <?= h($periodeLabel) ?>
</div>

<div class="sub">
    <?= h($ukuranFilterText) ?>
</div>

<div class="sub-note">
    Rekap Per Ukuran menampilkan kondisi keseluruhan saat ini (lintas bulan).
    Detail Mutasi menampilkan transaksi Pembelian dalam periode filter.
    Detail Mutasi Bekas menampilkan transaksi kondisi Bekas / Kembali dari Pabrik.
</div>

<br>


<!-- =====================================================
     REKAP PER UKURAN
     ===================================================== -->

<div class="section-title">
    REKAP PER UKURAN (KONDISI SAAT INI)
</div>

<table class="table-data">

<colgroup>
    <col style="width:4%;">
    <col style="width:9%;">
    <col style="width:11%;">
    <col style="width:11%;">
    <col style="width:10%;">
    <col style="width:19%;">
    <col style="width:22%;">
    <col style="width:14%;">
</colgroup>

<thead>

<tr>

    <th>No</th>
    <th>Ukuran</th>
    <th>Total Beli</th>
    <th>Total Bekas</th>
    <th>Rombeng</th>
    <th>Tgl Beli Terakhir</th>
    <th>Tgl Bekas Kembali Terakhir</th>
    <th>Stok Bekas Saat Ini</th>

</tr>

</thead>


<tbody>

<?php
$noRekap = 1;

while ($r = $resultRekap->fetch_assoc()):

    $totalBeli    = (float)$r['total_beli'];
    $totalBekas   = (float)$r['total_bekas'];
    $totalRombeng = (float)$r['total_rombeng'];

    $stokBekas = $totalBekas - $totalRombeng;

    $tglBeliTampil = formatTanggalQty(
        $r['tgl_beli_terakhir'],
        $r['qty_beli_terakhir']
    );

    $tglBekasTampil = formatTanggalQty(
        $r['tgl_bekas_terakhir'],
        $r['qty_bekas_terakhir']
    );
?>

<tr>

    <td class="text-center"><?= $noRekap++ ?></td>

    <td class="text-center">
        <?= number_format((float)$r['ukuran'], 2) ?> mm
    </td>

    <td class="text-right">
        <?= number_format($totalBeli, 2, '.', '') ?>
    </td>

    <td class="text-right">
        <?= number_format($totalBekas, 2, '.', '') ?>
    </td>

    <td class="text-right">
        <?= number_format($totalRombeng, 2, '.', '') ?>
    </td>

    <td class="text-center"><?= h($tglBeliTampil) ?></td>

    <td class="text-center"><?= h($tglBekasTampil) ?></td>

    <td class="text-right bold">
        <?= number_format($stokBekas, 2, '.', '') ?>
    </td>

</tr>

<?php endwhile; ?>

</tbody>

</table>


<!-- =====================================================
     DETAIL MUTASI — PEMBELIAN
     ===================================================== -->

<div class="block-detail">

<div class="section-title">
    DETAIL MUTASI
</div>

<div class="sub-note" style="text-align:left;margin-bottom:3px;">
    Menampilkan transaksi <strong>Pembelian</strong> dalam periode
    <?= h($periodeLabel) ?>, dikelompokkan per ukuran dan
    diurutkan dari tanggal pembelian terbaru.
</div>

<table class="table-data">

<colgroup>
    <col style="width:4%;">
    <col style="width:10%;">
    <col style="width:9%;">
    <col style="width:14%;">
    <col style="width:8%;">
    <col style="width:10%;">
    <col style="width:7%;">
    <col style="width:12%;">
    <col style="width:15%;">
    <col style="width:11%;">
</colgroup>

<thead>

<tr>

    <th>No</th>
    <th>Tanggal</th>
    <th>Ukuran</th>
    <th>Jenis</th>
    <th>Kondisi</th>
    <th>Masuk KG</th>
    <th>Satuan</th>
    <th>Referensi</th>
    <th>Keterangan</th>
    <th>User</th>

</tr>

</thead>


<tbody>

<?php

$noDetail    = 0;
$lastIdKawat = null;

if (count($detailRows) > 0):

    foreach ($detailRows as $r):

        $idKawat = (int)$r['id_kawat'];

        $tampilkanUkuran = !isset($ukuranSudahTampil[$idKawat]);

        if ($tampilkanUkuran) {
            $ukuranSudahTampil[$idKawat] = true;
        }

        if ($lastIdKawat !== $idKawat) {
            $noDetail    = 1;
            $lastIdKawat = $idKawat;
        } else {
            $noDetail++;
        }

        $rowClass = ($noDetail % 2 === 0) ? 'bg-even' : 'bg-odd';

?>

<tr class="<?= $rowClass ?>">

    <td class="text-center"><?= $noDetail ?></td>

    <td class="text-center">
        <?= date('d/m/Y', strtotime($r['tanggal'])) ?>
    </td>

    <?php if ($tampilkanUkuran): ?>

        <td
            class="ukuran-cell"
            rowspan="<?= (int)$rowspanUkuran[$idKawat] ?>"
        >
            <?= number_format((float)$r['ukuran'], 2) ?> mm
        </td>

    <?php endif; ?>

    <td><?= h(labelJenis($r['jenis_mutasi'])) ?></td>

    <td class="text-center"><?= h($r['kondisi']) ?></td>

    <td class="text-right in">
        <?= $r['arah'] === 'IN'
            ? number_format((float)$r['qty_kg'], 2, '.', '')
            : '-'
        ?>
    </td>

    <td class="text-center">
        <?= h($r['nama_satuan'] ?: 'KG') ?>
    </td>

    <td><?= h($r['no_referensi'] ?: '-') ?></td>

    <td><?= h($r['keterangan'] ?: '-') ?></td>

    <td><?= h($r['created_by'] ?: '-') ?></td>

</tr>

<?php

    endforeach;

else:

?>

<tr class="empty-row">

    <td
        colspan="10"
        class="text-center"
        style="padding:12px;color:#777;font-style:italic;"
    >
        Tidak ada data pembelian untuk filter yang dipilih.
    </td>

</tr>

<?php endif; ?>

</tbody>

<!-- TOTAL PEMBELIAN: di kolom terakhir "Masuk KG" -->
<tfoot>
<tr class="total-row">
    <td colspan="5" class="text-right">TOTAL MASUK KG</td>
    <td class="text-right">
        <?= number_format($totalMasukPembelian, 2, '.', '') ?>
    </td>
    <td colspan="4"></td>
</tr>
</tfoot>

</table>

</div>


<!-- =====================================================
     DETAIL MUTASI BEKAS
     ===================================================== -->

<div class="block-detail-bekas">

<div class="section-title">
    DETAIL MUTASI BEKAS
</div>

<div class="sub-note" style="text-align:left;margin-bottom:3px;">
    Menampilkan transaksi <strong>kondisi Bekas / Kembali dari Pabrik</strong>
    dalam periode <?= h($periodeLabel) ?>, dikelompokkan per ukuran dan
    diurutkan dari tanggal terbaru.
</div>

<table class="table-data">

<colgroup>
    <col style="width:4%;">
    <col style="width:10%;">
    <col style="width:9%;">
    <col style="width:14%;">
    <col style="width:8%;">
    <col style="width:10%;">
    <col style="width:7%;">
    <col style="width:12%;">
    <col style="width:15%;">
    <col style="width:11%;">
</colgroup>

<thead>

<tr>

    <th>No</th>
    <th>Tanggal</th>
    <th>Ukuran</th>
    <th>Jenis</th>
    <th>Kondisi</th>
    <th>Masuk KG</th>
    <th>Satuan</th>
    <th>Referensi</th>
    <th>Keterangan</th>
    <th>User</th>

</tr>

</thead>


<tbody>

<?php

$noDetailBekas    = 0;
$lastIdKawatBekas = null;

if (count($detailBekasRows) > 0):

    foreach ($detailBekasRows as $r):

        $idKawat = (int)$r['id_kawat'];

        $tampilkanUkuranBekas = !isset($ukuranBekasSudahTampil[$idKawat]);

        if ($tampilkanUkuranBekas) {
            $ukuranBekasSudahTampil[$idKawat] = true;
        }

        if ($lastIdKawatBekas !== $idKawat) {
            $noDetailBekas    = 1;
            $lastIdKawatBekas = $idKawat;
        } else {
            $noDetailBekas++;
        }

        $rowClass = ($noDetailBekas % 2 === 0) ? 'bg-even' : 'bg-odd';

?>

<tr class="<?= $rowClass ?>">

    <td class="text-center"><?= $noDetailBekas ?></td>

    <td class="text-center">
        <?= date('d/m/Y', strtotime($r['tanggal'])) ?>
    </td>

    <?php if ($tampilkanUkuranBekas): ?>

        <td
            class="ukuran-cell"
            rowspan="<?= (int)$rowspanUkuranBekas[$idKawat] ?>"
        >
            <?= number_format((float)$r['ukuran'], 2) ?> mm
        </td>

    <?php endif; ?>

    <td><?= h(labelJenis($r['jenis_mutasi'])) ?></td>

    <td class="text-center"><?= h($r['kondisi']) ?></td>

    <td class="text-right in">
        <?= $r['arah'] === 'IN'
            ? number_format((float)$r['qty_kg'], 2, '.', '')
            : '-'
        ?>
    </td>

    <td class="text-center">
        <?= h($r['nama_satuan'] ?: 'KG') ?>
    </td>

    <td><?= h($r['no_referensi'] ?: '-') ?></td>

    <td><?= h($r['keterangan'] ?: '-') ?></td>

    <td><?= h($r['created_by'] ?: '-') ?></td>

</tr>

<?php

    endforeach;

else:

?>

<tr class="empty-row">

    <td
        colspan="10"
        class="text-center"
        style="padding:12px;color:#777;font-style:italic;"
    >
        Tidak ada data bekas untuk filter yang dipilih.
    </td>

</tr>

<?php endif; ?>

</tbody>

<!-- TOTAL BEKAS: di kolom terakhir "Masuk KG" -->
<tfoot>
<tr class="total-row">
    <td colspan="5" class="text-right">TOTAL MASUK KG</td>
    <td class="text-right">
        <?= number_format($totalMasukBekas, 2, '.', '') ?>
    </td>
    <td colspan="4"></td>
</tr>
</tfoot>

</table>

</div>

</body>

</html>