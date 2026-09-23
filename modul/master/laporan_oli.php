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

$bulan =
    isset($_GET['bulan'])
    ? max(1, min(12, (int)$_GET['bulan']))
    : (int)date('m');

$tahun =
    isset($_GET['tahun'])
    ? (int)$_GET['tahun']
    : (int)date('Y');

$idOliFilter =
    isset($_GET['id_oli'])
    ? (int)$_GET['id_oli']
    : 0;

$namaBulan = [
    1=>'Januari',
    2=>'Februari',
    3=>'Maret',
    4=>'April',
    5=>'Mei',
    6=>'Juni',
    7=>'Juli',
    8=>'Agustus',
    9=>'September',
    10=>'Oktober',
    11=>'November',
    12=>'Desember'
][$bulan];

$filterText = 'Semua Oli';

if ($idOliFilter > 0) {

    $stmt = $koneksi->prepare("
        SELECT nama_oli
        FROM master_oli
        WHERE id_oli = ?
    ");

    $stmt->bind_param(
        "i",
        $idOliFilter
    );

    $stmt->execute();

    $row =
        $stmt
            ->get_result()
            ->fetch_assoc();

    if ($row) {
        $filterText = $row['nama_oli'];
    }

    $stmt->close();
}

// =========================================================
// Tentukan rentang tanggal periode
// =========================================================
$tglAwal  = sprintf('%04d-%02d-01', $tahun, $bulan);
$tglAkhir = date('Y-m-t', strtotime($tglAwal)); // tanggal terakhir bulan

header("Content-Type: application/vnd.ms-excel; charset=utf-8");

header(
    "Content-Disposition: attachment; " .
    "filename=Laporan_Stok_Oli_{$namaBulan}_{$tahun}.xls"
);

header("Pragma: no-cache");
header("Expires: 0");

// =========================================================
// Query transaksi periode terpilih
// =========================================================
$sql = "
    SELECT
        r.*,
        o.nama_oli
    FROM riwayat_oli r

    JOIN master_oli o
        ON o.id_oli = r.id_oli

    WHERE
        MONTH(r.tanggal) = ?
        AND YEAR(r.tanggal) = ?
        AND r.status_transaksi = 'AKTIF'
";

if ($idOliFilter > 0) {
    $sql .= " AND r.id_oli = ? ";
}

$sql .= "
    ORDER BY
        o.nama_oli ASC,
        r.tanggal ASC,
        r.id_riwayat ASC
";

$stmt = $koneksi->prepare($sql);

if ($idOliFilter > 0) {

    $stmt->bind_param(
        "iii",
        $bulan,
        $tahun,
        $idOliFilter
    );

} else {

    $stmt->bind_param(
        "ii",
        $bulan,
        $tahun
    );
}

$stmt->execute();

$result = $stmt->get_result();

$rows = [];

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$stmt->close();

$rowspan = [];

foreach ($rows as $row) {

    $id = (int)$row['id_oli'];

    if (!isset($rowspan[$id])) {
        $rowspan[$id] = 0;
    }

    $rowspan[$id]++;
}

$shown = [];

// =========================================================
// Query SALDO AWAL
// Akumulasi MASUK - KELUAR untuk transaksi AKTIF
// dengan tanggal SEBELUM tanggal awal periode
// =========================================================
$sqlAwal = "
    SELECT
        r.id_oli,
        o.nama_oli,
        COALESCE(SUM(
            CASE
                WHEN r.jenis_transaksi = 'MASUK'  THEN  r.jumlah
                WHEN r.jenis_transaksi = 'KELUAR' THEN -r.jumlah
                ELSE 0
            END
        ), 0) AS saldo_awal
    FROM riwayat_oli r
    JOIN master_oli o ON o.id_oli = r.id_oli
    WHERE
        r.status_transaksi = 'AKTIF'
        AND r.tanggal < ?
";

if ($idOliFilter > 0) {
    $sqlAwal .= " AND r.id_oli = ? ";
}

$sqlAwal .= " GROUP BY r.id_oli, o.nama_oli ";

$stmtAwal = $koneksi->prepare($sqlAwal);

if ($idOliFilter > 0) {
    $stmtAwal->bind_param("si", $tglAwal, $idOliFilter);
} else {
    $stmtAwal->bind_param("s", $tglAwal);
}

$stmtAwal->execute();
$resAwal = $stmtAwal->get_result();

$saldoAwalMap = [];

while ($row = $resAwal->fetch_assoc()) {
    $saldoAwalMap[(int)$row['id_oli']] = [
        'nama_oli'   => $row['nama_oli'],
        'saldo_awal' => (float)$row['saldo_awal'],
    ];
}

$stmtAwal->close();

// =========================================================
// Bangun ringkasan per oli (dari transaksi periode + saldo awal)
// =========================================================
$ringkasan = [];

// Inisialisasi dari saldo awal
foreach ($saldoAwalMap as $idOli => $info) {
    $ringkasan[$idOli] = [
        'nama_oli'    => $info['nama_oli'],
        'saldo_awal'  => $info['saldo_awal'],
        'total_masuk' => 0.0,
        'total_keluar'=> 0.0,
        'saldo_akhir' => 0.0,
    ];
}

// Tambahkan transaksi periode
foreach ($rows as $r) {

    $id = (int)$r['id_oli'];

    if (!isset($ringkasan[$id])) {
        $ringkasan[$id] = [
            'nama_oli'    => $r['nama_oli'],
            'saldo_awal'  => 0.0,
            'total_masuk' => 0.0,
            'total_keluar'=> 0.0,
            'saldo_akhir' => 0.0,
        ];
    }

    if ($r['jenis_transaksi'] === 'MASUK') {
        $ringkasan[$id]['total_masuk'] += (float)$r['jumlah'];
    } elseif ($r['jenis_transaksi'] === 'KELUAR') {
        $ringkasan[$id]['total_keluar'] += (float)$r['jumlah'];
    }
}

// Hitung saldo akhir
foreach ($ringkasan as $id => $info) {
    $ringkasan[$id]['saldo_akhir'] =
        $info['saldo_awal']
        + $info['total_masuk']
        - $info['total_keluar'];
}

// Urutkan ringkasan berdasarkan nama oli
uasort($ringkasan, function ($a, $b) {
    return strcmp($a['nama_oli'], $b['nama_oli']);
});

// Total keseluruhan (untuk baris TOTAL)
$grand = [
    'saldo_awal'   => 0.0,
    'total_masuk'  => 0.0,
    'total_keluar' => 0.0,
    'saldo_akhir'  => 0.0,
];

foreach ($ringkasan as $info) {
    $grand['saldo_awal']   += $info['saldo_awal'];
    $grand['total_masuk']  += $info['total_masuk'];
    $grand['total_keluar'] += $info['total_keluar'];
    $grand['saldo_akhir']  += $info['saldo_akhir'];
}

$tampilkanTotal = count($ringkasan) > 1;

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

.t {
    border-collapse: collapse;
    width: 100%;
}

.t th {
    background: #8a4b08;
    color: white;
    border: 1px solid #000;
    padding: 6px;
}

.t td {
    border: 1px solid #000;
    padding: 5px;
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

/* Ringkasan saldo */
.ringkasan {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 10px;
}

.ringkasan th {
    background: #f0c48a;
    border: 1px solid #000;
    padding: 5px;
}

.ringkasan td {
    border: 1px solid #000;
    padding: 5px;
}

.ringkasan .total-row td {
    background: #fde9c9;
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
    Periode <?= h($namaBulan) ?> <?= (int)$tahun ?>
</div>

<div class="sub">
    Filter: <?= h($filterText) ?>
</div>

<br>

<!-- ===================================================== -->
<!-- RINGKASAN SALDO AWAL / MASUK / KELUAR / AKHIR          -->
<!-- ===================================================== -->
<table class="ringkasan">

<thead>

<tr>
    <th>Jenis Oli</th>
    <th>Saldo Awal (Liter)</th>
    <th>Total Masuk (Liter)</th>
    <th>Total Keluar (Liter)</th>
    <th>Saldo Akhir (Liter)</th>
</tr>

</thead>

<tbody>

<?php if (count($ringkasan)): ?>

    <?php foreach ($ringkasan as $info): ?>

    <tr>
        <td><?= h($info['nama_oli']) ?></td>
        <td class="right"><?= number_format($info['saldo_awal'], 2) ?></td>
        <td class="right"><?= number_format($info['total_masuk'], 2) ?></td>
        <td class="right"><?= number_format($info['total_keluar'], 2) ?></td>
        <td class="right bold"><?= number_format($info['saldo_akhir'], 2) ?></td>
    </tr>

    <?php endforeach; ?>

    <?php if ($tampilkanTotal): ?>

    <tr class="total-row">
        <td class="center">TOTAL</td>
        <td class="right"><?= number_format($grand['saldo_awal'], 2) ?></td>
        <td class="right"><?= number_format($grand['total_masuk'], 2) ?></td>
        <td class="right"><?= number_format($grand['total_keluar'], 2) ?></td>
        <td class="right"><?= number_format($grand['saldo_akhir'], 2) ?></td>
    </tr>

    <?php endif; ?>

<?php else: ?>

    <tr>
        <td colspan="5" class="center">
            Tidak ada data saldo.
        </td>
    </tr>

<?php endif; ?>

</tbody>

</table>

<br>

<!-- ===================================================== -->
<!-- DETAIL TRANSAKSI                                       -->
<!-- ===================================================== -->
<table class="t">

<thead>

<tr>
    <th>No</th>
    <th>Jenis Oli</th>
    <th>Tanggal</th>
    <th>Jenis</th>
    <th>Masuk Liter</th>
    <th>Keluar Liter</th>
    <th>Tujuan</th>
    <th>Referensi</th>
    <th>Keterangan</th>
    <th>User</th>
</tr>

</thead>


<tbody>

<?php

$no = 1;

if (count($rows)):

    foreach ($rows as $r):

        $id =
            (int)$r['id_oli'];

        $first =
            !isset($shown[$id]);

        if ($first) {
            $shown[$id] = true;
        }

?>

<tr>

    <td class="center">
        <?= $no++ ?>
    </td>

    <?php if ($first): ?>

        <td
            rowspan="<?= (int)$rowspan[$id] ?>"
            class="bold"
            style="vertical-align:top;"
        >
            <?= h($r['nama_oli']) ?>
        </td>

    <?php endif; ?>

    <td class="center">
        <?= date('d/m/Y', strtotime($r['tanggal'])) ?>
    </td>

    <td>
        <?= h(labelOliMutasi($r['jenis_mutasi'])) ?>
    </td>

    <td class="right">
        <?= $r['jenis_transaksi'] === 'MASUK'
            ? number_format((float)$r['jumlah'], 2)
            : '-'
        ?>
    </td>

    <td class="right">
        <?= $r['jenis_transaksi'] === 'KELUAR'
            ? number_format((float)$r['jumlah'], 2)
            : '-'
        ?>
    </td>

    <td>
        <?php if ($r['jenis_mutasi'] === 'PEMAKAIAN'): ?>
            <?= h($r['tujuan_tipe'] ?: '-') ?>
            -
            <?= h($r['tujuan_nama'] ?: '-') ?>
        <?php else: ?>
            -
        <?php endif; ?>
    </td>

    <td>
        <?= h($r['no_referensi'] ?: '-') ?>
    </td>

    <td>
        <?= h($r['keterangan'] ?: '-') ?>
    </td>

    <td>
        <?= h($r['created_by'] ?: '-') ?>
    </td>

</tr>

<?php
    endforeach;

else:
?>

<tr>
    <td
        colspan="10"
        class="center"
    >
        Tidak ada transaksi.
    </td>
</tr>

<?php endif; ?>

</tbody>

</table>

</body>

</html>