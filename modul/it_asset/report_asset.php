<?php
/**
 * report_asset.php
 * Export Data Aset IT (banyak aset, hasil filter) ke Excel
 * 
 * Format: Setiap aset ditampilkan sebagai grup dengan header (bold),
 *         diikuti detail penempatan & seluruh riwayat mutasi/kondisi.
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
// 1. LOGIKA FILTER
// ============================================================
$filter_kondisi = $_GET['kondisi'] ?? '';
$filter_status  = $_GET['status']  ?? '';
$filter_lokasi  = $_GET['lokasi']  ?? '';
$filter_keyword = $_GET['keyword'] ?? '';

$where = "WHERE 1=1";
if ($filter_kondisi) {
    $where .= " AND a.kondisi = '" . mysqli_real_escape_string($koneksi, $filter_kondisi) . "'";
}
if ($filter_status) {
    $where .= " AND a.status_asset = '" . mysqli_real_escape_string($koneksi, $filter_status) . "'";
}
if ($filter_lokasi) {
    $where .= " AND a.lokasi = '" . mysqli_real_escape_string($koneksi, $filter_lokasi) . "'";
}
if ($filter_keyword) {
    $kw = mysqli_real_escape_string($koneksi, $filter_keyword);
    $where .= " AND (a.kode_asset LIKE '%$kw%' OR a.nama_asset LIKE '%$kw%'
                  OR a.merk LIKE '%$kw%' OR a.serial_number LIKE '%$kw%'
                  OR a.pengguna LIKE '%$kw%')";
}

// ============================================================
// 2. AMBIL DATA MASTER ASET (tanpa join history dulu)
// ============================================================
$query_asset = "SELECT
                    a.id_asset, a.kode_asset, a.nama_asset, a.merk, a.model,
                    a.lokasi, a.keterangan_penempatan, a.pengguna,
                    a.kondisi, a.status_asset, a.keterangan,
                    a.departemen, a.serial_number
                FROM master_it_asset a
                $where
                ORDER BY a.nama_asset ASC, a.id_asset ASC";

$result_asset = mysqli_query($koneksi, $query_asset);

// Kumpulkan data aset ke array agar bisa diproses lebih dulu
$assets = [];
while ($row = mysqli_fetch_assoc($result_asset)) {
    $assets[] = $row;
}

// ============================================================
// 3. AMBIL SELURUH RIWAYAT UNTUK SEMUA ASET (1x query, hindari N+1)
// ============================================================
$history_map = [];
if (!empty($assets)) {
    $id_list = array_column($assets, 'id_asset');
    $id_list_str = implode(',', array_map('intval', $id_list));

    $query_history = "SELECT
                        h.id_history, h.id_asset, h.kondisi_sesudah,
                        h.tgl_kejadian, h.keterangan,
                        h.lokasi_sesudah, h.pengguna_sesudah,
                        h.keterangan_penempatan_sesudah
                      FROM tr_it_asset_history h
                      WHERE h.id_asset IN ($id_list_str)
                      ORDER BY h.id_asset ASC, h.tgl_kejadian ASC, h.id_history ASC";

    $result_history = mysqli_query($koneksi, $query_history);
    while ($h = mysqli_fetch_assoc($result_history)) {
        $history_map[$h['id_asset']][] = $h;
    }
}

// ============================================================
// 4. HEADER DOWNLOAD EXCEL
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
    body, table, td, th {
        font-family: Arial, sans-serif;
        font-size: 9pt;
    }
    table { border-collapse: collapse; width: 100%; }
    .th   { font-weight: bold; background-color: #D9E1F2; border: 1px solid #999; padding: 4px; text-align: center; }
    .td   { border: 1px solid #CCC; padding: 4px; vertical-align: top; }
    .str  { mso-number-format:"\@"; }
    .asset-header {
        font-weight: bold;
        font-size: 11pt;
        background-color: #FFF2CC;
        border: 1px solid #999;
        padding: 6px;
    }
    .asset-info-label {
        font-weight: bold;
        background-color: #F2F2F2;
        border: 1px solid #CCC;
        padding: 3px;
        width: 120px;
    }
    .asset-info-value {
        border: 1px solid #CCC;
        padding: 3px;
    }
    .section-title {
        font-weight: bold;
        background-color: #E2EFDA;
        border: 1px solid #999;
        padding: 4px;
    }
    .spacer { height: 8px; }
</style>
</head>
<body>

<?php
$no_asset = 1;

foreach ($assets as $asset):
    $id_asset = $asset['id_asset'];
    $riwayat  = $history_map[$id_asset] ?? [];
?>

<!-- ================= HEADER ASET ================= -->
<table>
    <tr>
        <td colspan="6" class="asset-header">
            <?= $no_asset++ ?>. <?= htmlspecialchars($asset['nama_asset'] ?: '-') ?>
            (<?= htmlspecialchars($asset['kode_asset'] ?: '-') ?>)
            <?php if (!empty($asset['merk'])): ?>
                &mdash; <?= htmlspecialchars($asset['merk']) ?>
                <?= htmlspecialchars($asset['model'] ?: '') ?>
            <?php endif; ?>
        </td>
    </tr>
</table>

<!-- ================= INFO UTAMA ASET ================= -->
<table>
    <tr>
        <td class="asset-info-label">Status</td>
        <td class="asset-info-value" colspan="3">
            <?= htmlspecialchars($asset['status_asset'] ?: '-') ?>
        </td>
    </tr>
    <tr>
        <td class="asset-info-label">Lokasi Saat Ini</td>
        <td class="asset-info-value" colspan="3">
            <?= htmlspecialchars($asset['lokasi'] ?: '-') ?>
        </td>
    </tr>
    <tr>
        <td class="asset-info-label">Keterangan Penempatan</td>
        <td class="asset-info-value" colspan="3">
            <?= htmlspecialchars($asset['keterangan_penempatan'] ?: '-') ?>
        </td>
    </tr>
    <tr>
        <td class="asset-info-label">Pengguna</td>
        <td class="asset-info-value">
            <?= htmlspecialchars($asset['pengguna'] ?: '-') ?>
        </td>
        <td class="asset-info-label">Departemen</td>
        <td class="asset-info-value">
            <?= htmlspecialchars($asset['departemen'] ?: '-') ?>
        </td>
    </tr>
    <tr>
        <td class="asset-info-label">Kondisi Saat Ini</td>
        <td class="asset-info-value">
            <?= htmlspecialchars($asset['kondisi'] ?: '-') ?>
        </td>
        <td class="asset-info-label">Serial Number</td>
        <td class="asset-info-value">
            <?= htmlspecialchars($asset['serial_number'] ?: '-') ?>
        </td>
    </tr>
    <tr>
        <td class="asset-info-label">Keterangan</td>
        <td class="asset-info-value" colspan="3">
            <?= htmlspecialchars($asset['keterangan'] ?: '-') ?>
        </td>
    </tr>
</table>

<!-- ================= RIWAYAT MUTASI ================= -->
<table>
    <tr>
        <td colspan="5" class="section-title">
            Riwayat Mutasi / Perubahan Kondisi &amp; Perpindahan
        </td>
    </tr>
    <tr>
        <td class="th">No</td>
        <td class="th">Tanggal</td>
        <td class="th">Kondisi</td>
        <td class="th">Lokasi</td>
        <td class="th">Pengguna / Keterangan</td>
    </tr>

    <?php if (empty($riwayat)): ?>
        <tr>
            <td class="td str" colspan="5" style="text-align:center;">
                Tidak ada riwayat mutasi.
            </td>
        </tr>
    <?php else: ?>
        <?php $no_riwayat = 1; ?>
        <?php foreach ($riwayat as $r): ?>
            <tr>
                <td class="td str" style="text-align:center;"><?= $no_riwayat++ ?></td>
                <td class="td str">
                    <?= $r['tgl_kejadian'] ? date('d/m/Y', strtotime($r['tgl_kejadian'])) : '-' ?>
                </td>
                <td class="td"><?= htmlspecialchars($r['kondisi_sesudah'] ?: '-') ?></td>
                <td class="td"><?= htmlspecialchars($r['lokasi_sesudah'] ?: '-') ?></td>
                <td class="td">
                    <?php
                        $ket = [];
                        if (!empty($r['pengguna_sesudah'])) {
                            $ket[] = 'Pengguna: ' . $r['pengguna_sesudah'];
                        }
                        if (!empty($r['keterangan_penempatan_sesudah'])) {
                            $ket[] = 'Penempatan: ' . $r['keterangan_penempatan_sesudah'];
                        }
                        if (!empty($r['keterangan'])) {
                            $ket[] = $r['keterangan'];
                        }
                        echo htmlspecialchars(implode(' | ', $ket) ?: '-');
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<!-- Spacer antar aset -->
<table><tr><td class="spacer">&nbsp;</td></tr></table>

<?php endforeach; ?>

</body>
</html>