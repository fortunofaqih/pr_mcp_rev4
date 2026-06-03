<?php
/**
 * report_asset.php
 * Export Data Aset IT ke Excel
 * Metode: HTML Table dengan MSO Format
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

$query  = "SELECT a.* FROM master_it_asset a $where ORDER BY a.tgl_perolehan ASC";
$result = mysqli_query($koneksi, $query);
$total_rows = mysqli_num_rows($result);

// ============================================================
// 2. SUMMARY DATA
// ============================================================
$s = [];
$s['total']       = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE status_asset='AKTIF'"))['c'];
$s['bagus']       = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE kondisi='BAGUS'      AND status_asset='AKTIF'"))['c'];
$s['rusak']       = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE kondisi='RUSAK'      AND status_asset='AKTIF'"))['c'];
$s['servis']      = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE kondisi='DI-SERVICE' AND status_asset='AKTIF'"))['c'];
$s['nonaktif']    = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE status_asset='TIDAK AKTIF'"))['c'];
$s['dispose']     = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) c FROM master_it_asset WHERE status_asset='DISPOSE'"))['c'];
$s['total_harga'] = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT SUM(harga_perolehan) total FROM master_it_asset WHERE status_asset='AKTIF'"))['total'];

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
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Data Aset IT</x:Name>
    <x:WorksheetOptions>
      <x:DisplayGridlines/>
      <x:FreezePanes/>
      <x:FrozenNoSplit/>
      <x:SplitHorizontal>4</x:SplitHorizontal>
      <x:TopRowBottomPane>4</x:TopRowBottomPane>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
  <o:OfficeDocumentSettings><o:AllowPNG/></o:OfficeDocumentSettings>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>

/* ── Base ───────────────────────────────────────────────── */
body, table, td, th {
    font-family: Arial, sans-serif;
    font-size: 9pt;
}
table { border-collapse: collapse; width: 100%; }

/* ── Format MSO ─────────────────────────────────────────── */
.str     { mso-number-format:"\@"; }
.num-idr { mso-number-format:"\#\.##0"; text-align: right; }
.num     { mso-number-format:"\#\,\#\#0"; }

/* ── Judul Laporan ──────────────────────────────────────── */
.row-judul td {
    background-color: #0D47A1;
    color: #FFFFFF;
    font-size: 14pt;
    font-weight: bold;
    text-align: center;
    vertical-align: middle;
    height: 36pt;
    border: 2px solid #0A3880;
}

/* ── Sub-judul (info cetak) ─────────────────────────────── */
.row-sub td {
    background-color: #E3F2FD;
    color: #1565C0;
    font-size: 8.5pt;
    text-align: center;
    vertical-align: middle;
    height: 16pt;
    border: 1px solid #BBDEFB;
    font-style: italic;
}

/* ── Baris filter aktif ─────────────────────────────────── */
.row-filter td {
    background-color: #FFF8E1;
    color: #E65100;
    font-size: 8pt;
    text-align: center;
    border: 1px solid #FFE082;
    font-style: italic;
    padding: 3px 6px;
}

/* ── Header Kolom ───────────────────────────────────────── */
.th {
    background-color: #1565C0;
    color: #FFFFFF;
    font-weight: bold;
    font-size: 8.5pt;
    border: 1px solid #0D47A1;
    text-align: center;
    vertical-align: middle;
    height: 28pt;
    white-space: nowrap;
    padding: 4px 6px;
}

/* ── Cell data umum ─────────────────────────────────────── */
.td {
    border: 1px solid #CFD8DC;
    padding: 5px 6px;
    vertical-align: middle;
    font-size: 9pt;
}

/* ── Zebra stripe ───────────────────────────────────────── */
.row-odd  td { background-color: #FFFFFF; }
.row-even td { background-color: #F5F9FF; }

/* ── Baris RUSAK ────────────────────────────────────────── */
.row-rusak td {
    background-color: #FFF5F5 !important;
}

/* ── Baris DI-SERVICE ───────────────────────────────────── */
.row-servis td {
    background-color: #FFF8F0 !important;
}

/* ── Badge Kondisi ──────────────────────────────────────── */
.k-bagus    { background-color: #1B5E20; color: #FFFFFF; text-align: center; font-weight: bold; font-size: 8pt; mso-pattern: auto; }
.k-rusak    { background-color: #B71C1C; color: #FFFFFF; text-align: center; font-weight: bold; font-size: 8pt; mso-pattern: auto; }
.k-servis   { background-color: #E65100; color: #FFFFFF; text-align: center; font-weight: bold; font-size: 8pt; mso-pattern: auto; }
.k-nonaktif { background-color: #546E7A; color: #FFFFFF; text-align: center; font-weight: bold; font-size: 8pt; mso-pattern: auto; }
.k-hilang   { background-color: #212121; color: #FFFFFF; text-align: center; font-weight: bold; font-size: 8pt; mso-pattern: auto; }

/* ── Badge Status ───────────────────────────────────────── */
.s-aktif    { background-color: #E8F5E9; color: #1B5E20; text-align: center; font-weight: bold; font-size: 8pt; }
.s-nonaktif { background-color: #ECEFF1; color: #37474F; text-align: center; font-size: 8pt; }
.s-dispose  { background-color: #EFEBE9; color: #4E342E; text-align: center; font-size: 8pt; }

/* ── Grand Total ────────────────────────────────────────── */
.row-total td {
    background-color: #0D47A1;
    color: #FFFFFF;
    font-weight: bold;
    font-size: 10pt;
    border: 2px solid #0A3880;
    vertical-align: middle;
    height: 22pt;
}
.row-total .td-total-label {
    text-align: right;
    padding-right: 10px;
}
.row-total .td-total-val {
    text-align: right;
    font-size: 11pt;
    padding-right: 8px;
    mso-number-format:"\#\.##0";
}

/* ── Section Summary ────────────────────────────────────── */
.row-sec-hdr td {
    background-color: #263238;
    color: #FFFFFF;
    font-weight: bold;
    font-size: 9pt;
    border: 1px solid #37474F;
    padding: 5px 8px;
    height: 20pt;
}
.row-sum-lbl td {
    background-color: #ECEFF1;
    color: #263238;
    font-size: 9pt;
    border: 1px solid #CFD8DC;
    padding: 4px 8px;
}
.row-sum-val td {
    background-color: #FFFFFF;
    color: #212121;
    font-size: 9pt;
    font-weight: bold;
    border: 1px solid #CFD8DC;
    text-align: center;
    padding: 4px 8px;
}
.sum-total-row td {
    background-color: #E8F5E9;
    color: #1B5E20;
    font-weight: bold;
    font-size: 10pt;
    border: 2px solid #A5D6A7;
    padding: 5px 8px;
}

/* ── Garansi hampir habis ───────────────────────────────── */
.garansi-expired { color: #B71C1C; font-weight: bold; text-align: center; }
.garansi-warn    { color: #E65100; font-weight: bold; text-align: center; }
.garansi-ok      { color: #1B5E20; text-align: center; }

/* ── Utility ────────────────────────────────────────────── */
.ctr { text-align: center; }
.rgt { text-align: right; }
.bold { font-weight: bold; }
.small { font-size: 8pt; }
.muted { color: #78909C; }

</style>
</head>
<body>

<table>

    <!-- ══ JUDUL ══════════════════════════════════════════════════════ -->
    <tr class="row-judul">
        <td colspan="19">&#128187;&nbsp; LAPORAN DATA ASET IT &mdash; MCP SYSTEM</td>
    </tr>
    <tr class="row-sub">
        <td colspan="19">
            Dicetak oleh: <strong><?= htmlspecialchars($nama) ?></strong>
            &nbsp;|&nbsp; Tanggal: <strong><?= date('d/m/Y H:i') ?></strong>
            &nbsp;|&nbsp; Jumlah data ditampilkan: <strong><?= $total_rows ?> item</strong>
            <?php if ($filter_kondisi || $filter_status || $filter_lokasi || $filter_keyword): ?>
            &nbsp;|&nbsp; <em>Filter aktif</em>
            <?php endif; ?>
        </td>
    </tr>

    <!-- ── Baris filter (hanya tampil jika ada filter aktif) ── -->
    <?php if ($filter_kondisi || $filter_status || $filter_lokasi || $filter_keyword): ?>
    <tr class="row-filter">
        <td colspan="19">
            Filter:
            <?php if ($filter_kondisi) echo " Kondisi = <strong>$filter_kondisi</strong>"; ?>
            <?php if ($filter_status)  echo " &nbsp;|&nbsp; Status = <strong>$filter_status</strong>"; ?>
            <?php if ($filter_lokasi)  echo " &nbsp;|&nbsp; Lokasi = <strong>$filter_lokasi</strong>"; ?>
            <?php if ($filter_keyword) echo " &nbsp;|&nbsp; Keyword = <strong>&quot;$filter_keyword&quot;</strong>"; ?>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Spasi -->
    <tr><td colspan="19" style="height:6px; border:none;"></td></tr>

    <!-- ══ HEADER KOLOM ═══════════════════════════════════════════════ -->
    <tr>
        <td class="th" style="width:28px;">No</td>
        <td class="th" style="width:80px;">Kode Aset</td>
        <td class="th" style="width:160px;">Nama Barang</td>
        <td class="th" style="width:75px;">Merk</td>
        <td class="th" style="width:75px;">Model</td>
        <td class="th" style="width:100px;">Serial Number</td>
        <td class="th" style="width:180px;">Spesifikasi</td>
        <td class="th" style="width:70px;">Kondisi</td>
        <td class="th" style="width:65px;">Status</td>
        <td class="th" style="width:90px;">Lokasi</td>
        <td class="th" style="width:100px;">Pengguna / PIC</td>
        <td class="th" style="width:90px;">Departemen</td>
        <td class="th" style="width:72px;">Tgl Perolehan</td>
        <td class="th" style="width:100px;">Harga Perolehan</td>
        <td class="th" style="width:100px;">Supplier</td>
        <td class="th" style="width:70px;">No. PR</td>
        <td class="th" style="width:80px;">Garansi Selesai</td>
        <td class="th" style="width:65px;">Sumber</td>
        <td class="th" style="width:150px;">Keterangan</td>
    </tr>

    <!-- ══ DATA ═══════════════════════════════════════════════════════ -->
    <?php
    $no = 1;
    $grand_total = 0;
    $today = new DateTime();

    while ($d = mysqli_fetch_assoc($result)):
        $grand_total += (float)$d['harga_perolehan'];

        // ── Kelas kondisi badge ──
        $k_class = match($d['kondisi']) {
            'BAGUS'       => 'k-bagus',
            'RUSAK'       => 'k-rusak',
            'DI-SERVICE'  => 'k-servis',
            'TIDAK AKTIF' => 'k-nonaktif',
            'HILANG'      => 'k-hilang',
            default       => 'k-nonaktif',
        };

        // ── Kelas status badge ──
        $s_class = match($d['status_asset']) {
            'AKTIF'       => 's-aktif',
            'TIDAK AKTIF' => 's-nonaktif',
            'DISPOSE'     => 's-dispose',
            default       => '',
        };

        // ── Kelas row ──
        $row_class = match($d['kondisi']) {
            'RUSAK'      => 'row-rusak',
            'DI-SERVICE' => 'row-servis',
            default      => ($no % 2 == 0 ? 'row-even' : 'row-odd'),
        };

        // ── Garansi ──
        $garansi_str   = '-';
        $garansi_class = 'muted ctr';
        if (!empty($d['tgl_garansi_selesai']) && $d['tgl_garansi_selesai'] != '0000-00-00') {
            $tgl_g = new DateTime($d['tgl_garansi_selesai']);
            $diff  = $today->diff($tgl_g);
            $sisa  = $tgl_g > $today ? $diff->days : -$diff->days;
            $garansi_str = strtoupper(date('d-M-y', strtotime($d['tgl_garansi_selesai'])));
            if ($sisa < 0)        $garansi_class = 'garansi-expired';
            elseif ($sisa <= 30)  $garansi_class = 'garansi-warn';
            else                  $garansi_class = 'garansi-ok';
        }
    ?>
    <tr class="<?= $row_class ?>">
        <td class="td ctr muted"><?= $no++ ?></td>
        <td class="td str bold" style="letter-spacing:0.5px;"><?= htmlspecialchars($d['kode_asset']) ?></td>
        <td class="td bold"><?= htmlspecialchars($d['nama_asset']) ?></td>
        <td class="td"><?= htmlspecialchars($d['merk'] ?: '-') ?></td>
        <td class="td"><?= htmlspecialchars($d['model'] ?: '-') ?></td>
        <td class="td str small muted"><?= htmlspecialchars($d['serial_number'] ?: '-') ?></td>
        <td class="td small" style="color:#455A64;"><?= htmlspecialchars($d['spesifikasi'] ?: '-') ?></td>
        <td class="td <?= $k_class ?>"><?= htmlspecialchars($d['kondisi']) ?></td>
        <td class="td <?= $s_class ?>"><?= htmlspecialchars($d['status_asset']) ?></td>
        <td class="td"><?= htmlspecialchars($d['lokasi'] ?: '-') ?></td>
        <td class="td"><?= htmlspecialchars($d['pengguna'] ?: '-') ?></td>
        <td class="td"><?= htmlspecialchars($d['departemen'] ?: '-') ?></td>
        <td class="td ctr small">
            <?= (!empty($d['tgl_perolehan']) && $d['tgl_perolehan'] != '0000-00-00')
                ? strtoupper(date('d-M-y', strtotime($d['tgl_perolehan'])))
                : '-' ?>
        </td>
        <td class="td num-idr" x:num="<?= (float)$d['harga_perolehan'] ?>">
            <?= number_format((float)$d['harga_perolehan'], 0, '.', '.') ?>
        </td>
        <td class="td small"><?= htmlspecialchars($d['supplier'] ?: '-') ?></td>
        <td class="td str small ctr"><?= htmlspecialchars($d['no_request'] ?: '-') ?></td>
        <td class="td <?= $garansi_class ?> small"><?= $garansi_str ?></td>
        <td class="td ctr small"><?= htmlspecialchars($d['sumber_perolehan']) ?></td>
        <td class="td small muted"><?= htmlspecialchars($d['keterangan'] ?: '-') ?></td>
    </tr>
    <?php endwhile; ?>

    <!-- ══ GRAND TOTAL ════════════════════════════════════════════════ -->
    <tr class="row-total">
        <td colspan="13" class="td-total-label">&#128181; GRAND TOTAL NILAI ASET (AKTIF) :</td>
        <td class="td-total-val num-idr" x:num="<?= $grand_total ?>">
            Rp <?= number_format($grand_total, 0, ',', '.') ?>
        </td>
        <td colspan="5"></td>
    </tr>

    <!-- Spasi -->
    <tr><td colspan="19" style="height:14px; border:none;"></td></tr>

    <!-- ══ RINGKASAN STATUS ════════════════════════════════════════════ -->
    <tr class="row-sec-hdr">
        <td colspan="5">&#128202; RINGKASAN STATUS ASET</td>
        <td colspan="14" style="border:none; background:transparent;"></td>
    </tr>

    <!-- Header kolom summary -->
    <tr>
        <td colspan="3" class="th" style="text-align:left; padding-left:8px;">Keterangan</td>
        <td class="th" style="width:60px;">Jumlah</td>
        <td class="th" style="width:120px;">Keterangan Tambahan</td>
        <td colspan="14" style="border:none;"></td>
    </tr>

    <?php
    $summary_rows = [
        ['&#9989; Kondisi BAGUS',         $s['bagus'],    'Aset aktif & siap pakai',          'k-bagus'],
        ['&#128295; Sedang DI-SERVICE',   $s['servis'],   'Dalam perbaikan / servis',         'k-servis'],
        ['&#10060; Kondisi RUSAK',        $s['rusak'],    'Perlu tindak lanjut segera',       'k-rusak'],
        ['&#128274; Status TIDAK AKTIF',  $s['nonaktif'], 'Tidak digunakan / dinonaktifkan',  'k-nonaktif'],
        ['&#128465; Status DISPOSE',      $s['dispose'],  'Sudah dihapus dari inventaris',    'k-hilang'],
    ];
    foreach ($summary_rows as $sr):
    ?>
    <tr>
        <td colspan="3" class="td" style="background:#FAFAFA; padding-left:12px;"><?= $sr[0] ?></td>
        <td class="td <?= $sr[3] ?>"><?= $sr[1] ?> unit</td>
        <td class="td small muted"><?= $sr[2] ?></td>
        <td colspan="14" style="border:none;"></td>
    </tr>
    <?php endforeach; ?>

    <!-- Total keseluruhan -->
    <tr class="sum-total-row">
        <td colspan="3" style="padding-left:12px; font-weight:bold;">&#127942; TOTAL ASET AKTIF</td>
        <td style="text-align:center;"><?= $s['total'] ?> unit</td>
        <td class="num-idr" style="text-align:right;" x:num="<?= (float)$s['total_harga'] ?>">
            Rp <?= number_format((float)$s['total_harga'], 0, ',', '.') ?>
        </td>
        <td colspan="14" style="border:none;"></td>
    </tr>

    <!-- Spasi bawah -->
    <tr><td colspan="19" style="height:16px; border:none;"></td></tr>

    <!-- ══ CATATAN / KETERANGAN WARNA ════════════════════════════════ -->
    <tr class="row-sec-hdr">
        <td colspan="5">&#128218; KETERANGAN WARNA & FORMAT</td>
        <td colspan="14" style="border:none; background:transparent;"></td>
    </tr>
    <?php
    $legends = [
        ['k-bagus',    'Hijau Tua',   'Kondisi BAGUS — aset dalam keadaan baik'],
        ['k-rusak',    'Merah Tua',   'Kondisi RUSAK — perlu perbaikan atau penggantian'],
        ['k-servis',   'Oranye',      'DI-SERVICE — aset sedang dalam perbaikan'],
        ['k-nonaktif', 'Abu-abu',     'TIDAK AKTIF — aset tidak sedang digunakan'],
        ['k-hilang',   'Hitam',       'HILANG / DISPOSE — aset tidak lagi dalam inventaris'],
    ];
    foreach ($legends as $lg):
    ?>
    <tr>
        <td class="td <?= $lg[0] ?>" style="width:80px; text-align:center;"><?= $lg[1] ?></td>
        <td colspan="4" class="td small" style="background:#FAFAFA;"><?= $lg[2] ?></td>
        <td colspan="14" style="border:none;"></td>
    </tr>
    <?php endforeach; ?>

    <!-- Keterangan garansi -->
    <tr>
        <td class="td garansi-expired" style="text-align:center; font-size:8pt;">EXPIRED</td>
        <td colspan="4" class="td small" style="background:#FFF5F5;">Garansi sudah habis</td>
        <td colspan="14" style="border:none;"></td>
    </tr>
    <tr>
        <td class="td garansi-warn" style="text-align:center; font-size:8pt;">&le; 30 HARI</td>
        <td colspan="4" class="td small" style="background:#FFF8F0;">Garansi hampir habis (&le; 30 hari lagi)</td>
        <td colspan="14" style="border:none;"></td>
    </tr>

    <!-- Footer -->
    <tr><td colspan="19" style="height:10px; border:none;"></td></tr>
    <tr>
        <td colspan="19" style="border:none; font-size:7.5pt; color:#9E9E9E; font-style:italic; text-align:center;">
            Dokumen ini digenerate otomatis oleh Sistem Manajemen Aset IT &mdash; <?= date('Y') ?>
        </td>
    </tr>

</table>
</body>
</html>