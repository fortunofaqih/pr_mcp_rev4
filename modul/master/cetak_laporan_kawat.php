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

    $bulan = isset($_GET['bulan'])
        ? max(1, min(12, (int)$_GET['bulan']))
        : (int)date('m');

    $tahun = isset($_GET['tahun'])
        ? (int)$_GET['tahun']
        : (int)date('Y');

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
   QUERY DETAIL MUTASI
   (tetap mengikuti filter periode)
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
";

$paramsDetail = [];
$typesDetail  = '';

if ($pakaiRentang) {

    $sqlDetail .= " AND t.tanggal BETWEEN ? AND ? ";
    $paramsDetail[] = $startDate;
    $paramsDetail[] = $endDate;
    $typesDetail   .= 'ss';

} else {

    $sqlDetail .= " AND MONTH(t.tanggal) = ? AND YEAR(t.tanggal) = ? ";
    $paramsDetail[] = $bulan;
    $paramsDetail[] = $tahun;
    $typesDetail   .= 'ii';
}

if ($idKawatFilter > 0) {
    $sqlDetail .= " AND t.id_kawat = ? ";
    $paramsDetail[] = $idKawatFilter;
    $typesDetail   .= 'i';
}

$sqlDetail .= "
    ORDER BY
        k.ukuran ASC,
        t.tanggal ASC,
        t.id_transaksi ASC
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

$rowspanUkuran = [];

foreach ($detailRows as $row) {
    $id = (int)$row['id_kawat'];

    if (!isset($rowspanUkuran[$id])) {
        $rowspanUkuran[$id] = 0;
    }

    $rowspanUkuran[$id]++;
}

$ukuranSudahTampil = [];

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

body {
    font-family: Calibri, Arial, sans-serif;
    font-size: 11px;
    margin: 10px;
    color: #000;
}

.title {
    text-align: center;
    font-size: 14px;
    font-weight: bold;
}

.sub {
    text-align: center;
    font-size: 10px;
}

.sub-note {
    text-align: center;
    font-size: 9px;
    color: #555;
    font-style: italic;
}

.table-data {
    border-collapse: collapse;
    width: 100%;
    margin-top: 4px;
}

.table-data th {
    background: #244a7c;
    color: #ffffff;
    border: 1px solid #000000;
    padding: 3px 4px;
    text-align: center;
    vertical-align: middle;
    font-size: 11px;
}

.table-data td {
    border: 1px solid #000000;
    padding: 2px 4px;
    vertical-align: middle;
    font-size: 11px;
}

.text-center { text-align: center; }
.text-right  { text-align: right; }
.text-left   { text-align: left; }
.bold        { font-weight: bold; }

.in  { color: #008000; font-weight: bold; }
.out { color: #c00000; font-weight: bold; }

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
   ATURAN CETAK / SIMPAN PDF
   ========================================================= */
@media print {

    @page {
        size: A4 landscape;
        margin: 8mm;
    }

    body {
        margin: 0;
        font-size: 11px;
    }

    .action-bar {
        display: none;
    }

    .table-data th {
        background: #244a7c !important;
        color: #ffffff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        font-size: 11px;
    }

    .table-data td {
        font-size: 11px;
        padding: 2px 4px;
    }

    .in  { color: #008000 !important; }
    .out { color: #c00000 !important; }

    .bg-even { background: #f9f9f9 !important; }
    .bg-odd  { background: #ffffff !important; }

    table {
        page-break-inside: auto;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    thead {
        display: table-header-group;
    }

    tfoot {
        display: table-footer-group;
    }
}

</style>

</head>


<body>

<!-- Tombol Cetak (hanya muncul di layar) -->
<div class="action-bar">
    <button type="button" onclick="window.print()">
        🖨️ Cetak / Simpan PDF
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
    Detail Mutasi mengikuti periode filter.
</div>

<br>


<!-- =====================================================
     REKAP PER UKURAN
     ===================================================== -->

<div class="bold">
    REKAP PER UKURAN (KONDISI SAAT INI)
</div>

<table class="table-data">

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

    // Stok bekas = total kembali bekas − total rombeng
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


<br><br>


<!-- =====================================================
     DETAIL MUTASI
     ===================================================== -->

<div class="bold">
    DETAIL MUTASI
</div>

<div class="sub-note" style="text-align:left;margin-bottom:4px;">
    Menampilkan transaksi dalam periode <?= h($periodeLabel) ?> saja.
</div>

<table class="table-data">

<thead>

<tr>

    <th style="width:4%;">No</th>
    <th style="width:8%;">Ukuran</th>
    <th style="width:9%;">Tanggal</th>
    <th style="width:13%;">Jenis</th>
    <th style="width:8%;">Kondisi</th>
    <th style="width:9%;">Masuk KG</th>
    <th style="width:9%;">Keluar KG</th>
    <th style="width:9%;">Qty Asal</th>
    <th style="width:7%;">Satuan</th>
    <th style="width:10%;">Referensi</th>
    <th style="width:14%;">Keterangan</th>
    <th style="width:10%;">User</th>

</tr>

</thead>


<tbody>

<?php

$noDetail = 0;
$lastIdKawat = null;

if (count($detailRows) > 0):

    foreach ($detailRows as $r):

        $idKawat = (int)$r['id_kawat'];
        $tampilkanUkuran = !isset($ukuranSudahTampil[$idKawat]);

        if ($tampilkanUkuran) {
            $ukuranSudahTampil[$idKawat] = true;
        }

        // Reset nomor setiap ganti ukuran
        if ($lastIdKawat !== $idKawat) {
            $noDetail = 1;
            $lastIdKawat = $idKawat;
        } else {
            $noDetail++;
        }

        $rowClass = ($noDetail % 2 === 0) ? 'bg-even' : 'bg-odd';

?>

<tr class="<?= $rowClass ?>">

    <td class="text-center"><?= $noDetail ?></td>

    <?php if ($tampilkanUkuran): ?>

        <td
            class="text-center bold"
            rowspan="<?= (int)$rowspanUkuran[$idKawat] ?>"
            style="vertical-align: top;"
        >
            <?= number_format((float)$r['ukuran'], 2) ?> mm
        </td>

    <?php endif; ?>

    <td class="text-center">
        <?= date('d/m/Y', strtotime($r['tanggal'])) ?>
    </td>

    <td><?= h(labelJenis($r['jenis_mutasi'])) ?></td>

    <td class="text-center"><?= h($r['kondisi']) ?></td>

    <td class="text-right in">
        <?= $r['arah'] === 'IN'
            ? number_format((float)$r['qty_kg'], 2, '.', '')
            : '-'
        ?>
    </td>

    <td class="text-right out">
        <?= $r['arah'] === 'OUT'
            ? number_format((float)$r['qty_kg'], 2, '.', '')
            : '-'
        ?>
    </td>

    <td class="text-right">
        <?= number_format((float)$r['qty_asal'], 4, '.', '') ?>
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

<tr>

    <td
        colspan="12"
        class="text-center"
        style="padding:20px;color:#777;font-style:italic;"
    >
        Tidak ada transaksi untuk filter yang dipilih.
    </td>

</tr>

<?php endif; ?>

</tbody>

</table>

</body>

</html>