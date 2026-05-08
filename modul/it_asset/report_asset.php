<?php
/**
 * report_asset.php
 * Export Data Aset IT ke Excel (Optimized Version)
 * Metode: HTML Table dengan MSO Format (Teks & Number)
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
// 1. LOGIKA FILTER (Sesuai Filter di UI)
// ============================================================
$filter_kondisi = isset($_GET['kondisi']) ? $_GET['kondisi'] : '';
$filter_status  = isset($_GET['status'])  ? $_GET['status']  : '';
$filter_lokasi  = isset($_GET['lokasi'])  ? $_GET['lokasi']  : '';
$filter_keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';

$where = "WHERE 1=1";
if ($filter_kondisi) $where .= " AND a.kondisi = '" . mysqli_real_escape_string($koneksi, $filter_kondisi) . "'";
if ($filter_status)  $where .= " AND a.status_asset = '" . mysqli_real_escape_string($koneksi, $filter_status)  . "'";
if ($filter_lokasi)  $where .= " AND a.lokasi = '" . mysqli_real_escape_string($koneksi, $filter_lokasi)  . "'";
if ($filter_keyword) {
    $kw = mysqli_real_escape_string($koneksi, $filter_keyword);
    $where .= " AND (a.kode_asset LIKE '%$kw%' OR a.nama_asset LIKE '%$kw%'
                  OR a.merk LIKE '%$kw%' OR a.serial_number LIKE '%$kw%'
                  OR a.pengguna LIKE '%$kw%')";
}

//$query  = "SELECT a.* FROM master_it_asset a $where ORDER BY a.kode_asset ASC";
$query = "SELECT a.* FROM master_it_asset a $where ORDER BY a.tgl_perolehan ASC";
$result = mysqli_query($koneksi, $query);

// ============================================================
// 2. SUMMARY DATA (Untuk bagian bawah laporan)
// ============================================================
$s = [];
$s['total']       = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE status_asset='AKTIF'"))['c'];
$s['bagus']       = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE kondisi='BAGUS' AND status_asset='AKTIF'"))['c'];
$s['rusak']       = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE kondisi='RUSAK' AND status_asset='AKTIF'"))['c'];
$s['total_harga'] = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(harga_perolehan) total FROM master_it_asset WHERE status_asset='AKTIF'"))['total'];

// ============================================================
// 3. HEADER DOWNLOAD (Force Excel)
// ============================================================
$filename = 'Laporan_Aset_IT_' . date('Ymd_His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');
echo "\xEF\xBB\xBF"; // BOM UTF-8 agar karakter spesial terbaca benar
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Data Aset IT</x:Name>
    <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
  <o:OfficeDocumentSettings>
   <o:AllowPNG/>
  </o:OfficeDocumentSettings>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
    /* Format Khusus Excel */
    .str { mso-number-format:"\@"; } 
    
    /* Format Rupiah yang lebih kompatibel dengan Excel Indonesia */
    .num-idr { 
        mso-number-format:"\#\.##0";  /* titik sebagai pemisah ribuan */
        text-align: right;
    }
    
    .num { mso-number-format:"\#\,\#\#0"; }

    body   { font-family: Arial, sans-serif; }
    table  { border-collapse: collapse; width: 100%; }
    
    /* MIDDLE ALIGN: Tambahkan vertical-align middle di semua cell */
    .td { 
    border: 1px solid #D0D0D0; 
    padding: 8px 4px; 
    vertical-align: middle; /* Menjaga teks tetap di tengah secara vertikal */
}
    
    .th { 
        background-color: #1A3E8F; 
        color: #FFFFFF; 
        font-weight: bold; 
        border: 1px solid #000000; 
        text-align: center; 
        vertical-align: middle;
        height: 30pt;
    }

    .judul { font-size: 16pt; font-weight: bold; text-align: center; background-color: #1A3E8F; color: #FFFFFF; vertical-align: middle; }
    
    .ctr { text-align: center; }
    .rgt { text-align: right; }
    
    /* Warna Kondisi */
    .cell-bagus { background-color: #28A745; color: #FFFFFF; text-align: center; font-weight: bold; mso-pattern:auto; }
    .cell-rusak { background-color: #DC3545; color: #FFFFFF; text-align: center; font-weight: bold; mso-pattern:auto; }
    .cell-servis { background-color: #FD7E14; color: #FFFFFF; text-align: center; font-weight: bold; mso-pattern:auto; }
</style>
</head>
<body>

<table>
    <!-- JUDUL -->
    <tr><td colspan="19" class="judul">LAPORAN DATA ASET IT &mdash; MCP SYSTEM</td></tr>
    <tr>
        <td colspan="19" class="sub">
            Dicetak oleh: <?= htmlspecialchars($nama) ?> | 
            Tanggal: <?= strtoupper(date('d-M-y H:i')) ?> | 
            Total: <?= mysqli_num_rows($result) ?> Item
            <?php if($filter_keyword) echo " | Keyword: '$filter_keyword'"; ?>
        </td>
    </tr>
    <tr><td colspan="19"></td></tr>

    <!-- HEADER KOLOM -->
    <tr>
        <td class="th">No</td>
        <td class="th">Kode Aset</td>
        <td class="th">Nama Barang</td>
        <td class="th">Merk</td>
        <td class="th">Model</td>
        <td class="th">Serial Number</td>
        <td class="th">Spesifikasi</td>
        <td class="th">Kondisi</td>
        <td class="th">Status</td>
        <td class="th">Lokasi</td>
        <td class="th">Pengguna</td>
        <td class="th">Departemen</td>
        <td class="th">Tgl Perolehan</td>
        <td class="th">Harga Perolehan</td>
        <td class="th">Supplier</td>
        <td class="th">No. PR</td>
        <td class="th">Garansi Selesai</td>
        <td class="th">Sumber</td>
        <td class="th">Keterangan</td>
    </tr>

    <!-- DATA -->
    <?php
    $no = 1;
    $grand_total = 0;
    while ($d = mysqli_fetch_assoc($result)):
        $warna_kondisi = '';
        if ($d['kondisi'] == 'BAGUS') $warna_kondisi = 'cell-bagus';
        elseif ($d['kondisi'] == 'RUSAK') $warna_kondisi = 'cell-rusak';
        elseif ($d['kondisi'] == 'DI-SERVICE') $warna_kondisi = 'cell-servis';
        else $warna_kondisi = 'cell-nonaktif';

        $grand_total += (float)$d['harga_perolehan'];
        
        // Logika warna baris
        $row_class = '';
        if ($d['kondisi'] == 'RUSAK') $row_class = 'r-rusak';
        if ($d['kondisi'] == 'DI-SERVICE') $row_class = 'r-servis';
    ?>
    <tr class="<?= $row_class ?>">
        <td class="td ctr"><?= $no++ ?></td>
        <td class="td str"><?= $d['kode_asset'] ?></td>
        <td class="td"><strong><?= htmlspecialchars($d['nama_asset']) ?></strong></td>
        <td class="td"><?= htmlspecialchars($d['merk']) ?></td>
        <td class="td"><?= htmlspecialchars($d['model']) ?></td>
        <td class="td str"><?= $d['serial_number'] ?></td>
        <td class="td" style="font-size: 8pt;"><?= htmlspecialchars($d['spesifikasi']) ?></td>
        <td class="td <?= $warna_kondisi ?>"><?= $d['kondisi'] ?></td>
        <td class="td ctr"><?= $d['status_asset'] ?></td>
        <td class="td"><?= htmlspecialchars($d['lokasi']) ?></td>
        <td class="td"><?= htmlspecialchars($d['pengguna']) ?></td>
        <td class="td"><?= htmlspecialchars($d['departemen']) ?></td>
        <td class="td ctr">
            <?= ($d['tgl_perolehan'] && $d['tgl_perolehan'] != '0000-00-00') 
                ? strtoupper(date('d-M-y', strtotime($d['tgl_perolehan']))) 
                : '-' ?>
        </td>
        <!-- Class 'num' agar di excel bisa dijumlahkan -->
        <td class="td num-idr" x:num="<?= (float)$d['harga_perolehan'] ?>">
                <?= number_format((float)$d['harga_perolehan'], 0, '.', '.') ?>
            </td>
        <td class="td"><?= htmlspecialchars($d['supplier']) ?></td>
        <td class="td str"><?= $d['no_request'] ?></td>
        <td class="td ctr">
            <?= ($d['tgl_garansi_selesai'] && $d['tgl_garansi_selesai'] != '0000-00-00') 
                ? strtoupper(date('d-M-y', strtotime($d['tgl_garansi_selesai']))) 
                : '-' ?>
        </td>
        <td class="td ctr"><?= $d['sumber_perolehan'] ?></td>
        <td class="td"><?= htmlspecialchars($d['keterangan']) ?></td>
    </tr>
    <?php endwhile; ?>

    <!-- TOTAL BAWAH -->
    <tr class="tr-total">
        <td colspan="13" class="td rgt">GRAND TOTAL (AKTIF):</td>
        <td class="td num-idr" x:num="<?= $grand_total ?>">
            Rp <?= number_format($grand_total, 0, ',', '.') ?>
        </td>
        <td colspan="5" class="td"></td>
    </tr>

    <tr><td colspan="19" style="height: 20px;"></td></tr>

    <!-- SUMMARY SECTION -->
    <tr><td colspan="5" class="th" style="text-align: left; background: #333;">RINGKASAN STATUS (AKTIF)</td><td colspan="14"></td></tr>
    <tr><td colspan="4" class="td">Total Aset Bagus</td><td class="td num ctr"><?= $s['bagus'] ?></td><td colspan="14"></td></tr>
    <tr><td colspan="4" class="td">Total Aset Rusak</td><td class="td num ctr"><?= $s['rusak'] ?></td><td colspan="14"></td></tr>
    <tr>
        <td colspan="4" class="td" style="font-weight: bold;">Total Nilai Aset</td>
        <td class="td num-idr" style="font-weight: bold;" x:num="<?= $s['total_harga'] ?>">
            Rp <?= number_format((float)$s['total_harga'], 0, ',', '.') ?>
        </td>
        <td colspan="14" class="td"></td>
    </tr>
</table>

</body>
</html>