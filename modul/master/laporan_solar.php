<?php
// laporan solar - khusus SOLAR INDUSTRI
session_start();

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/oli_helper.php';

if (($_SESSION['status'] ?? '') !== 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$user = $_SESSION['username'] ?? 'system';

// Sync master dan pembelian (termasuk solar)
oli_sync_master_barang($koneksi, $user);
oli_sync_pembelian($koneksi, $user);

// Ambil atau buat ID Solar
$idSolar = oli_get_or_create_master($koneksi, 'SOLAR INDUSTRI', $user);

$bulan =
    isset($_GET['bulan'])
    ? max(1, min(12, (int)$_GET['bulan']))
    : (int)date('m');

$tahun =
    isset($_GET['tahun'])
    ? (int)$_GET['tahun']
    : (int)date('Y');

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

header("Content-Type: application/vnd.ms-excel; charset=utf-8");

header(
    "Content-Disposition: attachment; " .
    "filename=Laporan_Solar_{$namaBulan}_{$tahun}.xls"
);

header("Pragma: no-cache");
header("Expires: 0");

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
        AND r.id_oli = ?

    ORDER BY
        r.tanggal ASC,
        r.id_riwayat ASC
";

$stmt = $koneksi->prepare($sql);

$stmt->bind_param(
    "iii",
    $bulan,
    $tahun,
    $idSolar
);

$stmt->execute();

$result = $stmt->get_result();

$rows = [];

while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// Hitung total masuk dan keluar
$totalMasuk = 0;
$totalKeluar = 0;
$saldoAwal = 0;
$saldoAkhir = 0;

foreach ($rows as $r) {
    if ($r['jenis_transaksi'] === 'MASUK') {
        $totalMasuk += (float)$r['jumlah'];
    } elseif ($r['jenis_transaksi'] === 'KELUAR') {
        $totalKeluar += (float)$r['jumlah'];
    }
}

// Ambil saldo awal (sebelum periode)
$stmtSaldo = $koneksi->prepare("
    SELECT COALESCE(
        SUM(
            CASE
                WHEN status_transaksi = 'AKTIF'
                 AND jenis_transaksi = 'MASUK'
                THEN jumlah

                WHEN status_transaksi = 'AKTIF'
                 AND jenis_transaksi = 'KELUAR'
                THEN -jumlah

                ELSE 0
            END
        ),
        0
    ) AS saldo
    FROM riwayat_oli
    WHERE id_oli = ?
      AND status_transaksi = 'AKTIF'
      AND tanggal < ?
");
$tanggalAwal = date('Y-m-01', strtotime("$tahun-$bulan-01"));
$stmtSaldo->bind_param("is", $idSolar, $tanggalAwal);
$stmtSaldo->execute();
$saldoAwal = (float)$stmtSaldo->get_result()->fetch_assoc()['saldo'] ?? 0;
$stmtSaldo->close();

$saldoAkhir = $saldoAwal + $totalMasuk - $totalKeluar;

function h($v)
{
    return htmlspecialchars(
        (string)$v,
        ENT_QUOTES,
        'UTF-8'
    );
}

function labelSolarMutasi($jenis)
{
    return [
        'STOK_AWAL' => 'Stok Awal',
        'PEMBELIAN' => 'Pembelian',
        'PEMAKAIAN' => 'Pemakaian'
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
    background: #0d6efd;
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

.summary {
    margin-top: 15px;
    padding: 10px;
    border: 1px solid #000;
    background: #f0f8ff;
}

</style>

</head>


<body>

<div class="title">
    PT MUTIARA CAHAYA PLASTINDO
</div>

<div class="title">
    LAPORAN PEMAKAIAN SOLAR INDUSTRI
</div>

<div class="sub">
    Periode <?= h($namaBulan) ?> <?= (int)$tahun ?>
</div>

<br>

<div class="summary">
    <table style="width:100%;">
        <tr>
            <td><b>Saldo Awal:</b> <?= number_format($saldoAwal, 2) ?> Liter</td>
            <td><b>Total Masuk:</b> <?= number_format($totalMasuk, 2) ?> Liter</td>
            <td><b>Total Keluar:</b> <?= number_format($totalKeluar, 2) ?> Liter</td>
            <td><b>Saldo Akhir:</b> <?= number_format($saldoAkhir, 2) ?> Liter</td>
        </tr>
    </table>
</div>

<br>

<table class="t">

<thead>

<tr>
    <th>No</th>
    <th>Tanggal</th>
    <th>Jenis</th>
    <th>Masuk (Liter)</th>
    <th>Keluar (Liter)</th>
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

?>

<tr>

    <td class="center">
        <?= $no++ ?>
    </td>

    <td class="center">
        <?= date('d/m/Y', strtotime($r['tanggal'])) ?>
    </td>

    <td>
        <?= h(labelSolarMutasi($r['jenis_mutasi'])) ?>
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
        colspan="9"
        class="center"
    >
        Tidak ada transaksi Solar pada periode ini.
    </td>
</tr>

<?php endif; ?>

</tbody>

</table>

</body>

</html>