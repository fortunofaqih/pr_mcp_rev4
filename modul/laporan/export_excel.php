<?php
session_start(); 
ob_start(); 

include '../../config/koneksi.php';
include '../../auth/check_session.php';

// 1. Ambil parameter filter
$tgl_mulai    = $_REQUEST['tgl_mulai']   ?? date('Y-m-01');
$tgl_selesai  = $_REQUEST['tgl_selesai'] ?? date('Y-m-d');
$huruf_awal   = $_REQUEST['huruf_awal']  ?? 'A';
$huruf_akhir  = $_REQUEST['huruf_akhir'] ?? 'Z';
$search_nama  = mysqli_real_escape_string($koneksi, $_REQUEST['search_nama'] ?? '');
$rak_terpilih = $_REQUEST['filter_rak']  ?? [];

if (!empty($rak_terpilih) && is_array($rak_terpilih)) {
    $rak_terpilih = array_map(function($val) use ($koneksi) {
        return mysqli_real_escape_string($koneksi, trim($val));
    }, $rak_terpilih);
}

// 2. Build Query dengan Logika RETUR (+)
$sql = "SELECT 
            m.nama_barang, m.satuan, m.lokasi_rak,
            COALESCE(awal.total_awal, 0) as stok_awal,
            COALESCE(mutasi.m_masuk, 0) as masuk,
            COALESCE(mutasi.m_keluar, 0) as keluar,
            (COALESCE(awal.total_awal, 0) + COALESCE(mutasi.m_masuk, 0) - COALESCE(mutasi.m_keluar, 0)) as stok_akhir
        FROM master_barang m
        LEFT JOIN (
            SELECT id_barang, 
            SUM(CASE 
                WHEN tipe_transaksi IN ('MASUK', 'RETUR') THEN qty 
                WHEN tipe_transaksi = 'KELUAR' THEN -qty 
                ELSE 0 END) as total_awal
            FROM tr_stok_log 
            WHERE tgl_log < '$tgl_mulai 00:00:00' 
            GROUP BY id_barang
        ) awal ON m.id_barang = awal.id_barang
        LEFT JOIN (
            SELECT id_barang, 
            SUM(CASE WHEN tipe_transaksi IN ('MASUK', 'RETUR') THEN qty ELSE 0 END) as m_masuk,
            SUM(CASE WHEN tipe_transaksi = 'KELUAR' THEN qty ELSE 0 END) as m_keluar
            FROM tr_stok_log 
            WHERE tgl_log BETWEEN '$tgl_mulai 00:00:00' AND '$tgl_selesai 23:59:59' 
            GROUP BY id_barang
        ) mutasi ON m.id_barang = mutasi.id_barang
        WHERE m.status_aktif = 'AKTIF' AND m.is_active = 1";

// Tambahan Filter
if (!empty($rak_terpilih)) {
    $rak_list_sql = "'" . implode("','", $rak_terpilih) . "'";
    $sql .= " AND m.lokasi_rak IN ($rak_list_sql)";
}
if ($huruf_awal != 'A' || $huruf_akhir != 'Z') {
    $sql .= " AND LEFT(m.nama_barang, 1) BETWEEN '$huruf_awal' AND '$huruf_akhir'";
}
if ($search_nama != '') {
    $sql .= " AND m.nama_barang LIKE '%$search_nama%'";
}

$sql .= " ORDER BY 
    CASE WHEN m.lokasi_rak = '' OR m.lokasi_rak = '-' THEN 0 ELSE 1 END ASC,
    LEFT(m.lokasi_rak, 1) ASC, 
    LENGTH(m.lokasi_rak) ASC, 
    m.lokasi_rak ASC, 
    m.nama_barang ASC";

$query = mysqli_query($koneksi, $sql);

// Membersihkan buffer agar tidak ada spasi/whitespace yang merusak file excel
ob_end_clean(); 

header("Content-type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=SO_Gudang_" . date('d-m-Y') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");
?>
<meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
<table border="1">
    <tr>
        <th colspan="7" style="font-size:16px; text-align:center; height:30px; background-color:#eeeeee;">
            LAPORAN MUTASI & STOK OPNAME GUDANG
        </th>
    </tr>
    <tr>
        <th colspan="7" style="text-align:center;">
            Periode: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_selesai)) ?>
        </th>
    </tr>
    <tr style="background-color:#00008B; color:#FFFFFF; font-weight:bold; text-align:center;">
        <th width="50">NO</th>
        <th width="350">NAMA BARANG</th>
        <th width="100">RAK</th>
        <th width="80">SATUAN</th>
        <th width="120">STOK SISTEM</th>
        <th width="120">STOK FISIK</th>
        <th width="50">CEK</th>
    </tr>
    <?php 
    $no = 1; 
    $last_rak = null;
    while($r = mysqli_fetch_assoc($query)): 
        $curr = $r['lokasi_rak'] ?: '-';
        if ($curr !== $last_rak): 
    ?>
        <tr style="background-color:#f2f2f2; font-weight:bold;">
            <td colspan="7">LOKASI RAK: <?= htmlspecialchars($curr) ?></td>
        </tr>
    <?php 
        $last_rak = $curr;
        endif; 
    ?>
    <tr>
        <td align="center"><?= $no++ ?></td>
        <td><?= strtoupper(htmlspecialchars($r['nama_barang'])) ?></td>
        <td align="center"><?= htmlspecialchars($r['lokasi_rak'] ?: '-') ?></td>
        <td align="center"><?= $r['satuan'] ?></td>
        <td align="right"><?= number_format($r['stok_akhir'], 2, '.', '') ?></td>
        <td style="background-color: #ffffcc;"></td>
        <td></td>
    </tr>
    <?php endwhile; ?>
</table>