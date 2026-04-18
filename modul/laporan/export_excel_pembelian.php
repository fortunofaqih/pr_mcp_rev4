<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Tangkap filter
$abjad   = isset($_GET['abjad']) ? mysqli_real_escape_string($koneksi, $_GET['abjad']) : '';
$tgl_min = isset($_GET['tgl_min']) ? $_GET['tgl_min'] : '';
$tgl_max = isset($_GET['tgl_max']) ? $_GET['tgl_max'] : '';
$keyword = isset($_GET['keyword']) ? mysqli_real_escape_string($koneksi, $_GET['keyword']) : '';

// Header untuk Excel
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Pembelian_MCP_" . date('Ymd') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// SQL Filter
$filter_sql = " WHERE 1=1 ";
if ($abjad != '' && $abjad != 'ALL') { $filter_sql .= " AND p.nama_barang_beli LIKE '$abjad%' "; }
// Gunakan tgl_beli_barang (Tanggal Nota) untuk laporan
if ($tgl_min != '' && $tgl_max != '') { $filter_sql .= " AND p.tgl_beli_barang BETWEEN '$tgl_min' AND '$tgl_max' "; }
if ($keyword != '') { $filter_sql .= " AND (p.nama_barang_beli LIKE '%$keyword%' OR p.supplier LIKE '%$keyword%' OR p.plat_nomor LIKE '%$keyword%') "; }

$sql = "SELECT p.*, m.merk as merk_master 
        FROM pembelian p 
        LEFT JOIN master_barang m ON p.nama_barang_beli = m.nama_barang 
        $filter_sql 
        ORDER BY p.tgl_beli_barang DESC";
$query = mysqli_query($koneksi, $sql);
?>

<meta http-equiv="content-type" content="application/xhtml+xml; charset=UTF-8" />

<style>
    .text{ mso-number-format:"\@"; } /* Force format teks */
    .angka{ mso-number-format:"\#\,\#\#0"; } /* Force format angka ribuan */
    .desimal{ mso-number-format:"\#\,\#\#0\.00"; } /* Force format desimal */
</style>

<center>
    <h3>REKAP DATA PEMBELIAN BARANG</h3>
    <p>Periode: <?= ($tgl_min ?: '-') ?> s/d <?= ($tgl_max ?: '-') ?></p>
</center>

<table border="1">
    <thead>
        <tr style="background-color: #0000FF; color: white; font-weight: bold;">
            <th>TGL NOTA</th>
            <th>SUPPLIER</th>
            <th>NAMA BARANG</th>
            <th>MERK</th>
            <th>QTY</th>
            <th>HARGA SATUAN</th>
            <th>TOTAL BAYAR</th>
            <th>ALOKASI</th>
            <th>UNIT/PLAT</th>
            <th>DRIVER</th>
            <th>KETERANGAN</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $grand_total = 0;
        while($d = mysqli_fetch_array($query)){ 
            $total = $d['qty'] * $d['harga'];
            $grand_total += $total;
            $merk = !empty($d['merk_beli']) ? $d['merk_beli'] : ($d['merk_master'] ?? '-');
        ?>
        <tr>
            <td class="text">
                <?php 
                    if ($d['tgl_beli_barang'] == '0000-00-00' || empty($d['tgl_beli_barang'])) {
                        echo "-";
                    } else {
                        echo date('d/m/Y', strtotime($d['tgl_beli_barang']));
                    }
                ?>
            </td>
            <td><?= strtoupper($d['supplier']) ?></td>
            <td><?= strtoupper($d['nama_barang_beli']) ?></td>
            <td><?= strtoupper($merk) ?></td>
            <td class="desimal"><?= (float)$d['qty'] ?></td>
            <td class="angka"><?= $d['harga'] ?></td>
            <td class="angka" style="background-color: #e2efda; font-weight: bold;"><?= $total ?></td>
            <td><?= $d['alokasi_stok'] ?></td>
            <td class="text"><?= $d['plat_nomor'] ?></td>
            <td><?= strtoupper($d['driver']) ?></td>
            <td><?= strtoupper($d['keterangan']) ?></td>
        </tr>
        <?php } ?>
    </tbody>
    <tfoot>
        <tr style="font-weight: bold; background-color: #f2f2f2;">
            <td colspan="6" align="right">GRAND TOTAL</td>
            <td class="angka" style="background-color: #ffff00;"><?= $grand_total ?></td>
            <td colspan="4"></td>
        </tr>
    </tfoot>
</table>