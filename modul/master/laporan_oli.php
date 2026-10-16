<?php
// laporan oli
session_start();

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/oli_helper.php';

if (($_SESSION['status'] ?? '') !== 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$user = $_SESSION['username'] ?? 'system';

oli_sync_master_barang($koneksi, $user);
oli_sync_pembelian($koneksi, $user);

/* =========================================================
   FILTER
   ========================================================= */

$startDate = $_GET['start_date'] ?? '';
$endDate   = $_GET['end_date']   ?? '';

$bulan = isset($_GET['bulan'])
    ? max(1, min(12, (int)$_GET['bulan']))
    : (int)date('m');

$tahun = isset($_GET['tahun'])
    ? (int)$_GET['tahun']
    : (int)date('Y');

if ($startDate === '' || $endDate === '') {
    $startDate = sprintf('%04d-%02d-01', $tahun, $bulan);
    $endDate   = date('Y-m-t', strtotime($startDate));
}

if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$idOliFilter = isset($_GET['id_oli'])
    ? (int)$_GET['id_oli']
    : 0;

$jenisFilter = isset($_GET['jenis'])
    ? trim($_GET['jenis'])
    : '';

/* =========================================================
   LABEL PERIODE & FILTER
   ========================================================= */

$periodeText = date('d/m/Y', strtotime($startDate))
    . ' s/d '
    . date('d/m/Y', strtotime($endDate));

$filterText = 'Semua Oli';

if ($idOliFilter > 0) {

    $stmt = $koneksi->prepare("
        SELECT nama_oli
        FROM master_oli
        WHERE id_oli = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $idOliFilter);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $filterText = $row['nama_oli'];
    }

    $stmt->close();
}

/* =========================================================
   SALDO AKHIR PER OLI
   ========================================================= */

$saldoAkhirList = [];

$sqlSaldo = "
    SELECT
        o.id_oli,
        o.nama_oli,
        COALESCE(SUM(
            CASE
                WHEN r.jenis_transaksi = 'MASUK'  THEN  r.jumlah
                WHEN r.jenis_transaksi = 'KELUAR' THEN -r.jumlah
                ELSE 0
            END
        ), 0) AS saldo_akhir
    FROM master_oli o

    LEFT JOIN riwayat_oli r
        ON r.id_oli = o.id_oli
       AND r.status_transaksi = 'AKTIF'
       AND r.tanggal <= ?

    GROUP BY
        o.id_oli,
        o.nama_oli

    ORDER BY
        o.nama_oli ASC
";

$stmtSaldo = $koneksi->prepare($sqlSaldo);
$stmtSaldo->bind_param("s", $endDate);
$stmtSaldo->execute();

$resSaldo = $stmtSaldo->get_result();

while ($row = $resSaldo->fetch_assoc()) {
    $saldoAkhirList[] = $row;
}

$stmtSaldo->close();

/* =========================================================
   DOWNLOAD EXCEL
   ========================================================= */

$namaFile =
    'Laporan_Oli_'
    . date('Ymd', strtotime($startDate))
    . '_'
    . date('Ymd', strtotime($endDate))
    . '.xls';

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename={$namaFile}");
header("Pragma: no-cache");
header("Expires: 0");

/* =========================================================
   QUERY TRANSAKSI SESUAI FILTER
   ========================================================= */

$where = [
    "r.tanggal BETWEEN ? AND ?",
    "r.status_transaksi = 'AKTIF'"
];

$params = [$startDate, $endDate];
$types  = 'ss';

if ($idOliFilter > 0) {
    $where[]  = "r.id_oli = ?";
    $params[] = $idOliFilter;
    $types   .= 'i';
}

if ($jenisFilter !== '') {
    $where[]  = "r.jenis_mutasi = ?";
    $params[] = $jenisFilter;
    $types   .= 's';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT
        r.*,
        o.nama_oli
    FROM riwayat_oli r

    JOIN master_oli o
        ON o.id_oli = r.id_oli

    $whereSql

    ORDER BY
        o.nama_oli ASC,
        r.tanggal ASC,
        r.id_riwayat ASC
";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();

$rows = [];

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();

/* =========================================================
   HITUNG ROWSPAN PER OLI (untuk kolom "Jenis Oli")
   ========================================================= */

$rowspanOli = [];

foreach ($rows as $r) {
    $id = (int)$r['id_oli'];

    if (!isset($rowspanOli[$id])) {
        $rowspanOli[$id] = 0;
    }

    $rowspanOli[$id]++;
}

$oliSudahTampil = [];

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

function labelOliMutasi($jenis)
{
    return [
        'STOK_AWAL' => 'Stok Awal',
        'PEMBELIAN' => 'Pembelian',
        'PEMAKAIAN' => 'Pemakaian Operasional'
    ][$jenis] ?? $jenis;
}
?>
<html>

<head>

<meta charset="utf-8">

<style>

body {
    font-family: Calibri, Arial;
    font-size: 11px;
}

.title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
}

.sub {
    text-align: center;
}

/* ---------- Info saldo (per kolom) ---------- */
.saldo-table {
    border-collapse: collapse;
    width: 100%;
    margin: 12px 0 16px 0;
}

.saldo-table td {
    border: 1px solid #8a4b08;
    background: #fff8e8;
    padding: 6px 8px;
    font-weight: bold;
    text-align: center;
    font-size: 12px;
}

/* ---------- Tabel detail ---------- */
.t {
    border-collapse: collapse;
    width: 100%;
}

.t th {
    background: #8a4b08;
    color: white;
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
}

.t td {
    border: 1px solid #000;
    padding: 5px;
    vertical-align: middle;
}

.center {
    text-align: center;
}

.right {
    text-align: right;
}

.bold {
    font-weight: bold;
}

.in {
    color: #008000;
    font-weight: bold;
}

.out {
    color: #c00000;
    font-weight: bold;
}

</style>

</head>


<body>

<div class="title">
    PT MUTIARA CAHAYA PLASTINDO
</div>

<div class="title">
    LAPORAN STOK & PEMAKAIAN OLI
</div>

<div class="sub">
    Periode <?= h($periodeText) ?>
</div>

<div class="sub">
    Filter: <?= h($filterText) ?>
    <?= $jenisFilter !== ''
        ? ' | Jenis: ' . h(labelOliMutasi($jenisFilter))
        : ''
    ?>
</div>

<br>


<!-- ===================================================== -->
<!-- INFO SALDO AKHIR — SATU OLI SATU KOLOM                 -->
<!-- ===================================================== -->

<?php if (count($saldoAkhirList)): ?>

<table class="saldo-table">

<tr>

    <?php foreach ($saldoAkhirList as $s): ?>

        <td>
            <?= h($s['nama_oli']) ?>
            =
            <?= number_format((float)$s['saldo_akhir'], 2) ?> L
        </td>

    <?php endforeach; ?>

</tr>

</table>

<?php endif; ?>


<!-- ===================================================== -->
<!-- DETAIL TRANSAKSI                                       -->
<!-- ===================================================== -->

<table class="t">

<thead>

<tr>
    <th style="width:4%;">No</th>
    <th style="width:9%;">Tanggal</th>
    <th style="width:16%;">Jenis Oli</th>
    <th style="width:14%;">Jenis</th>
    <th style="width:10%;">Masuk (L)</th>
    <th style="width:10%;">Keluar (L)</th>
    <th style="width:17%;">Tujuan</th>
    <th style="width:20%;">Keterangan</th>
</tr>

</thead>


<tbody>

<?php

$no = 0;
$lastIdOli = null;

if (count($rows)):

    foreach ($rows as $r):

        $idOli = (int)$r['id_oli'];

        $tampilkanOli = !isset($oliSudahTampil[$idOli]);

        if ($tampilkanOli) {
            $oliSudahTampil[$idOli] = true;
        }

        /*
         * Reset nomor setiap kali ganti jenis oli.
         */
        if ($lastIdOli !== $idOli) {
            $no = 1;
            $lastIdOli = $idOli;
        } else {
            $no++;
        }

        $tujuan = '-';

        if ($r['jenis_mutasi'] === 'PEMAKAIAN') {
            $tujuan = trim(
                ($r['tujuan_tipe'] ?: '')
                . ' - '
                . ($r['tujuan_nama'] ?: ''),
                ' -'
            );

            if ($tujuan === '') {
                $tujuan = '-';
            }
        }

?>

<tr>

    <td class="center">
        <?= $no ?>
    </td>

    <td class="center">
        <?= date('d/m/Y', strtotime($r['tanggal'])) ?>
    </td>

    <?php if ($tampilkanOli): ?>

        <td
            class="bold center"
            rowspan="<?= (int)$rowspanOli[$idOli] ?>"
            style="vertical-align: middle;"
        >
            <?= h($r['nama_oli']) ?>
        </td>

    <?php endif; ?>

    <td>
        <?= h(labelOliMutasi($r['jenis_mutasi'])) ?>
    </td>

    <td class="right in">
        <?= $r['jenis_transaksi'] === 'MASUK'
            ? number_format((float)$r['jumlah'], 2)
            : '-'
        ?>
    </td>

    <td class="right out">
        <?= $r['jenis_transaksi'] === 'KELUAR'
            ? number_format((float)$r['jumlah'], 2)
            : '-'
        ?>
    </td>

    <td>
        <?= h($tujuan) ?>
    </td>

    <td>
        <?= h($r['keterangan'] ?: '-') ?>
    </td>

</tr>

<?php
    endforeach;

else:
?>

<tr>
    <td colspan="8" class="center">
        Tidak ada transaksi untuk filter yang dipilih.
    </td>
</tr>

<?php endif; ?>

</tbody>

</table>

</body>

</html>