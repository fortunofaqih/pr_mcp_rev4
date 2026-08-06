<?php
/**
 * export_master_mesin.php
 * Export data master mesin ke Excel (format .xls) tanpa library tambahan
 */

session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Set header untuk Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Master_Mesin_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Ambil data mesin
$query = mysqli_query($koneksi, "
    SELECT * FROM master_mesin 
    ORDER BY active DESC, name ASC
");

// Fungsi format tanggal Indonesia
function formatDateIndo($date) {
    if (empty($date) || $date == '0000-00-00') return '-';
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
    $timestamp = strtotime($date);
    if ($timestamp === false) return '-';
    return date('d', $timestamp) . '-' . $bulan[date('n', $timestamp) - 1] . '-' . date('Y', $timestamp);
}

// Fungsi format angka ke Rupiah
function formatRupiah($number) {
    if (empty($number) || $number == 0) return '-';
    return 'Rp ' . number_format($number, 0, ',', '.');
}

// Output HTML untuk Excel
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<style>';
echo 'table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }';
echo 'th { background-color: #1e3a8a; color: white; font-weight: bold; padding: 8px; border: 1px solid #000; text-align: center; }';
echo 'td { padding: 6px; border: 1px solid #ccc; }';
echo '.header-title { font-size: 18px; font-weight: bold; text-align: center; color: #1e3a8a; margin-bottom: 10px; }';
echo '.sub-title { font-size: 12px; text-align: center; margin-bottom: 20px; }';
echo '.status-aktif { color: #28a745; font-weight: bold; }';
echo '.status-nonaktif { color: #dc3545; font-weight: bold; }';
echo '.footer { margin-top: 20px; font-size: 10px; color: #6c757d; text-align: right; }';
echo '.bg-aktif { background-color: #d4edda; }';
echo '.bg-nonaktif { background-color: #f8d7da; }';
echo '</style>';
echo '</head>';
echo '<body>';

// Header Laporan
echo '<div class="header-title">LAPORAN DATA MASTER MESIN</div>';
echo '<div class="sub-title">';
echo 'Dicetak: ' . date('d-m-Y H:i:s') . ' | Oleh: ' . ($_SESSION['username'] ?? 'System');
echo '</div>';

// Tabel Data
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th style="width:10%;">ID Mesin</th>';
echo '<th style="width:15%;">Nama Mesin</th>';
echo '<th style="width:20%;">Spesifikasi</th>';
echo '<th style="width:10%;">Kapasitas</th>';
echo '<th style="width:10%;">Supplier</th>';
echo '<th style="width:12%;">Manufactured By</th>';
echo '<th style="width:10%;">Tgl Manufactur</th>';
echo '<th style="width:12%;">Harga Beli</th>';
echo '<th style="width:10%;">Tgl Beli</th>';
echo '<th style="width:10%;">Acc Reff</th>';
echo '<th style="width:8%;">Status</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

if (mysqli_num_rows($query) > 0) {
    while ($d = mysqli_fetch_assoc($query)) {
        $active = (int)$d['active'] === 1;
        $row_class = $active ? 'bg-aktif' : 'bg-nonaktif';
        $status_text = $active ? 'AKTIF' : 'NONAKTIF';
        $status_class = $active ? 'status-aktif' : 'status-nonaktif';
        
        echo '<tr class="' . $row_class . '">';
        echo '<td style="text-align:center;">' . htmlspecialchars($d['id_mesin']) . '</td>';
        echo '<td>' . htmlspecialchars($d['name']) . '</td>';
        echo '<td>' . htmlspecialchars($d['spec'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($d['capacity'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($d['supplier'] ?? '-') . '</td>';
        echo '<td>' . htmlspecialchars($d['manufactured_by'] ?? '-') . '</td>';
        echo '<td style="text-align:center;">' . formatDateIndo($d['manufactured_date']) . '</td>';
        echo '<td style="text-align:right;">' . formatRupiah($d['purchase_price']) . '</td>';
        echo '<td style="text-align:center;">' . formatDateIndo($d['purchase_date']) . '</td>';
        echo '<td>' . htmlspecialchars($d['acc_reff'] ?? '-') . '</td>';
        echo '<td style="text-align:center;" class="' . $status_class . '">' . $status_text . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr>';
    echo '<td colspan="11" style="text-align:center;">Tidak ada data mesin</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// Footer
echo '<div class="footer">';
echo 'Total Data: ' . mysqli_num_rows($query) . ' mesin';
echo '</div>';

echo '</body>';
echo '</html>';
exit;
?>