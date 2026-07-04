<?php
/**
 * report_asset.php
 * Export Data Aset IT (banyak aset, hasil filter) ke Excel - versi minimal
 */

session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

if (!in_array($role, ['administrator', 'it'])) {
    header("Location: ../../index.php");
    exit;
}

// ============================================================
// 1. LOGIKA FILTER (tetap dipertahankan sesuai existing)
// ============================================================
$filter_kondisi = isset($_GET['kondisi']) ? $_GET['kondisi'] : '';
$filter_status  = isset($_GET['status'])  ? $_GET['status']  : '';
$filter_lokasi  = isset($_GET['lokasi'])  ? $_GET['lokasi']  : '';
$filter_keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

$where = "WHERE 1=1";
if ($filter_kondisi) $where .= " AND a.kondisi = '"      . mysqli_real_escape_string($koneksi, $filter_kondisi) . "'";
if ($filter_status)  $where .= " AND a.status_asset = '" . mysqli_real_escape_string($koneksi, $filter_status)  . "'";
if ($filter_lokasi)  $where .= " AND a.lokasi = '"       . mysqli_real_escape_string($koneksi, $filter_lokasi)  . "'";
if ($filter_keyword) {
    $kw = mysqli_real_escape_string($koneksi, $filter_keyword);
    $where .= " AND (a.kode_asset LIKE '%$kw%' OR a.nama_asset LIKE '%$kw%'
                  OR a.merk LIKE '%$kw%' OR a.serial_number LIKE '%$kw%'
                  OR a.pengguna LIKE '%$kw%')";
}

// ============================================================
// 2. AMBIL DATA ASET + RIWAYAT (LEFT JOIN supaya aset tanpa riwayat tetap ikut)
// ============================================================
$query = "SELECT
            a.id_asset, a.lokasi, a.keterangan_penempatan, a.nama_asset, a.keterangan, a.pengguna,
            h.id_history, h.kondisi_sebelum, h.kondisi_sesudah, h.tgl_kejadian, h.keterangan AS h_keterangan
          FROM master_it_asset a
          LEFT JOIN tr_it_asset_history h ON h.id_asset = a.id_asset
          $where
          ORDER BY a.id_asset ASC, h.tgl_kejadian ASC, h.id_history ASC";

$result = mysqli_query($koneksi, $query);

// ============================================================
// 3. HEADER DOWNLOAD
// ============================================================
$filename = 'Laporan_Aset_IT_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');
echo "\xEF\xBB\xBF"; // BOM UTF-8
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<style>
body, table, td, th { font-family: Arial, sans-serif; font-size: 9pt; }
table { border-collapse: collapse; width: 100%; }
.th   { font-weight: bold; }
.str  { mso-number-format:"\@"; }
</style>
</head>
<body>

<table>
    <tr>
        <td class="th">No</td>
        <td class="th">Lokasi</td>
        <td class="th">Keterangan Penempatan</td>
        <td class="th">Nama Barang</td>
        <td class="th">Keterangan</td>
        <td class="th">Pengguna</td>
        <td class="th">Riwayat Kondisi</td>
        <td class="th">Riwayat Tanggal</td>
        <td class="th">Riwayat Keterangan</td>
    </tr>

    <?php
    $no = 1;
    while ($d = mysqli_fetch_assoc($result)):

        $riwayat_kondisi = '';
        if ($d['kondisi_sebelum'] || $d['kondisi_sesudah']) {
            $riwayat_kondisi = ($d['kondisi_sebelum'] ?: '-') . ' -> ' . ($d['kondisi_sesudah'] ?: '-');
        }
        $riwayat_tanggal = $d['tgl_kejadian'] ? date('d/m/Y', strtotime($d['tgl_kejadian'])) : '-';
    ?>
    <tr>
        <td class="str"><?= $no++ ?></td>
        <td><?= htmlspecialchars($d['lokasi']                ?: '-') ?></td>
        <td><?= htmlspecialchars($d['keterangan_penempatan'] ?: '-') ?></td>
        <td><?= htmlspecialchars($d['nama_asset']            ?: '-') ?></td>
        <td><?= htmlspecialchars($d['keterangan']            ?: '-') ?></td>
        <td><?= htmlspecialchars($d['pengguna']               ?: '-') ?></td>
        <td><?= htmlspecialchars($riwayat_kondisi              ?: '-') ?></td>
        <td class="str"><?= $riwayat_tanggal ?></td>
        <td><?= htmlspecialchars($d['h_keterangan']           ?: '-') ?></td>
    </tr>
    <?php endwhile; ?>

</table>
</body>
</html>