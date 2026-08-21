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
   FILTER
   ========================================================= */

$bulan = isset($_GET['bulan'])
    ? max(1, min(12, (int)$_GET['bulan']))
    : (int)date('m');

$tahun = isset($_GET['tahun'])
    ? (int)$_GET['tahun']
    : (int)date('Y');

$idKawatFilter = isset($_GET['id_kawat'])
    ? (int)$_GET['id_kawat']
    : 0;

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
   DOWNLOAD EXCEL
   ========================================================= */

$namaFileUkuran = $idKawatFilter > 0
    ? '_' . str_replace(['.', ',', ' '], ['_', '_', ''], $ukuranFilterText)
    : '';

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header(
    "Content-Disposition: attachment; " .
    "filename=Laporan_Mutasi_Kawat_{$namaBulan}_{$tahun}{$namaFileUkuran}.xls"
);
header("Pragma: no-cache");
header("Expires: 0");

/* =========================================================
   QUERY DETAIL MUTASI
   Hanya transaksi AKTIF
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
        MONTH(t.tanggal) = ?
        AND YEAR(t.tanggal) = ?
        AND t.status_transaksi = 'AKTIF'
";

if ($idKawatFilter > 0) {
    $sqlDetail .= " AND t.id_kawat = ? ";
}

$sqlDetail .= "
    ORDER BY
        k.ukuran ASC,
        t.tanggal ASC,
        t.id_transaksi ASC
";

$stmtDetail = $koneksi->prepare($sqlDetail);

if ($idKawatFilter > 0) {
    $stmtDetail->bind_param(
        "iii",
        $bulan,
        $tahun,
        $idKawatFilter
    );
} else {
    $stmtDetail->bind_param(
        "ii",
        $bulan,
        $tahun
    );
}

$stmtDetail->execute();
$resultDetail = $stmtDetail->get_result();

/* =========================================================
   BUFFER DETAIL UNTUK ROWSPAN PER UKURAN
   ========================================================= */

$detailRows = [];

while ($row = $resultDetail->fetch_assoc()) {
    $detailRows[] = $row;
}

/*
 * Hitung jumlah baris per id_kawat.
 * Nilai ini dipakai untuk rowspan kolom Ukuran.
 */
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
   ========================================================= */

$sqlRekap = "
    SELECT
        k.id_kawat,
        k.ukuran,

        COALESCE(
            SUM(
                CASE
                    WHEN MONTH(t.tanggal) = ?
                     AND YEAR(t.tanggal) = ?
                     AND t.status_transaksi = 'AKTIF'
                     AND t.jenis_mutasi = 'PEMBELIAN'
                    THEN t.qty_kg
                    ELSE 0
                END
            ),
            0
        ) AS pembelian,

        COALESCE(
            SUM(
                CASE
                    WHEN MONTH(t.tanggal) = ?
                     AND YEAR(t.tanggal) = ?
                     AND t.status_transaksi = 'AKTIF'
                     AND t.jenis_mutasi = 'PEMAKAIAN'
                    THEN t.qty_kg
                    ELSE 0
                END
            ),
            0
        ) AS pemakaian,

        COALESCE(
            SUM(
                CASE
                    WHEN MONTH(t.tanggal) = ?
                     AND YEAR(t.tanggal) = ?
                     AND t.status_transaksi = 'AKTIF'
                     AND t.jenis_mutasi = 'RETURN_BEKAS'
                    THEN t.qty_kg
                    ELSE 0
                END
            ),
            0
        ) AS return_bekas,

        COALESCE(
            SUM(
                CASE
                    WHEN MONTH(t.tanggal) = ?
                     AND YEAR(t.tanggal) = ?
                     AND t.status_transaksi = 'AKTIF'
                     AND t.jenis_mutasi = 'ROMBENG'
                    THEN t.qty_kg
                    ELSE 0
                END
            ),
            0
        ) AS rombeng,

        COALESCE(
            SUM(
                CASE
                    WHEN t.status_transaksi = 'AKTIF'
                     AND t.kondisi = 'BARU'
                     AND t.arah = 'IN'
                    THEN t.qty_kg

                    WHEN t.status_transaksi = 'AKTIF'
                     AND t.kondisi = 'BARU'
                     AND t.arah = 'OUT'
                    THEN -t.qty_kg

                    ELSE 0
                END
            ),
            0
        ) AS stok_baru_akhir,

        COALESCE(
            SUM(
                CASE
                    WHEN t.status_transaksi = 'AKTIF'
                     AND t.kondisi = 'BEKAS'
                     AND t.arah = 'IN'
                    THEN t.qty_kg

                    WHEN t.status_transaksi = 'AKTIF'
                     AND t.kondisi = 'BEKAS'
                     AND t.arah = 'OUT'
                    THEN -t.qty_kg

                    ELSE 0
                END
            ),
            0
        ) AS stok_bekas_akhir

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

$stmtRekap = $koneksi->prepare($sqlRekap);

if ($idKawatFilter > 0) {

    $stmtRekap->bind_param(
        "iiiiiiiii",
        $bulan,
        $tahun,
        $bulan,
        $tahun,
        $bulan,
        $tahun,
        $bulan,
        $tahun,
        $idKawatFilter
    );

} else {

    $stmtRekap->bind_param(
        "iiiiiiii",
        $bulan,
        $tahun,
        $bulan,
        $tahun,
        $bulan,
        $tahun,
        $bulan,
        $tahun
    );

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
?>
<html>

<head>

<meta charset="utf-8">

<style>

body {
    font-family: Calibri, Arial, sans-serif;
    font-size: 11px;
}

.title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
}

.sub {
    text-align: center;
    font-size: 12px;
}

.table-data {
    border-collapse: collapse;
    width: 100%;
}

.table-data th {
    background: #244a7c;
    color: #ffffff;
    border: 1px solid #000000;
    padding: 6px;
    text-align: center;
    vertical-align: middle;
}

.table-data td {
    border: 1px solid #000000;
    padding: 5px 6px;
    vertical-align: middle;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.text-left {
    text-align: left;
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


.bg-even {
    background: #f9f9f9;
}

.bg-odd {
    background: #ffffff;
}

.info-table {
    border-collapse: collapse;
    margin-bottom: 14px;
}

.info-table td {
    padding: 3px 8px;
}

</style>

</head>


<body>

<div class="title">
    PT MUTIARA CAHAYA PLASTINDO
</div>

<div class="title">
    LAPORAN MUTASI STOK KAWAT
</div>

<div class="sub">
    Periode <?= h($namaBulan) ?> <?= (int)$tahun ?>
</div>

<div class="sub">
    Ukuran: <?= h($ukuranFilterText) ?>
</div>

<br>


<!-- =====================================================
     REKAP PER UKURAN
     ===================================================== -->

<div class="bold">
    REKAP PER UKURAN
</div>

<table class="table-data">

<thead>

<tr>

    <th>No</th>
    <th>Ukuran</th>
    <th>Pembelian</th>
    <th>Dipakai Pabrik</th>
    <th>Kembali Bekas</th>
    <th>Rombeng</th>
    <th>Stok Baru Saat Ini</th>
    <th>Stok Bekas Saat Ini</th>
    <th>Total Saat Ini</th>

</tr>

</thead>


<tbody>

<?php
$noRekap = 1;

while ($r = $resultRekap->fetch_assoc()):

    $stokBaru  = (float)$r['stok_baru_akhir'];
    $stokBekas = (float)$r['stok_bekas_akhir'];
    $total     = $stokBaru + $stokBekas;
?>

<tr>

    <td class="text-center">
        <?= $noRekap++ ?>
    </td>

    <td class="text-center">
        <?= number_format((float)$r['ukuran'], 2) ?> mm
    </td>

    <td class="text-right">
        <?= number_format((float)$r['pembelian'], 2) ?>
    </td>

    <td class="text-right">
        <?= number_format((float)$r['pemakaian'], 2) ?>
    </td>

    <td class="text-right">
        <?= number_format((float)$r['return_bekas'], 2) ?>
    </td>

    <td class="text-right">
        <?= number_format((float)$r['rombeng'], 2) ?>
    </td>

    <td class="text-right">
        <?= number_format($stokBaru, 2) ?>
    </td>

    <td class="text-right">
        <?= number_format($stokBekas, 2) ?>
    </td>

    <td class="text-right bold">
        <?= number_format($total, 2) ?>
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

$noDetail = 1;

if (count($detailRows) > 0):

    foreach ($detailRows as $r):

        $idKawat = (int)$r['id_kawat'];
        $tampilkanUkuran = !isset($ukuranSudahTampil[$idKawat]);

        if ($tampilkanUkuran) {
            $ukuranSudahTampil[$idKawat] = true;
        }

        $rowClass =
            ($noDetail % 2 === 0)
            ? 'bg-even'
            : 'bg-odd';

?>

<tr class="<?= $rowClass ?>">

    <td class="text-center">
        <?= $noDetail ?>
    </td>

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

    <td>
        <?= h(labelJenis($r['jenis_mutasi'])) ?>
    </td>

    <td class="text-center">
        <?= h($r['kondisi']) ?>
    </td>

    <td class="text-right in">

        <?= $r['arah'] === 'IN'
            ? number_format((float)$r['qty_kg'], 2)
            : '-'
        ?>

    </td>

    <td class="text-right out">

        <?= $r['arah'] === 'OUT'
            ? number_format((float)$r['qty_kg'], 2)
            : '-'
        ?>

    </td>

    <td class="text-right">
        <?= number_format((float)$r['qty_asal'], 4) ?>
    </td>

    <td class="text-center">
        <?= h($r['nama_satuan'] ?: 'KG') ?>
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

        $noDetail++;

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