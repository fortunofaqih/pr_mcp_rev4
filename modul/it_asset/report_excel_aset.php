<?php
/**
 * report_excel_aset.php
 * Export detail aset IT + riwayat ke Excel (XML Spreadsheet .xls)
 * Tanpa library tambahan — langsung jalan di PHP murni.
 *
 * URL: report_excel_aset.php?id=123
 */

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

$role = $_SESSION['role'];
if (!in_array($role, ['administrator', 'it'])) {
    header("Location: ../../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: index.php"); exit; }

// ── Ambil data aset ───────────────────────────────────────────────────────────
$q = mysqli_query($koneksi, "SELECT * FROM master_it_asset WHERE id_asset = $id");
if (!$q || mysqli_num_rows($q) == 0) { header("Location: index.php"); exit; }
$asset = mysqli_fetch_assoc($q);

// ── Ambil riwayat ─────────────────────────────────────────────────────────────
$q_hist = mysqli_query($koneksi,
    "SELECT * FROM tr_it_asset_history
     WHERE id_asset = $id
     ORDER BY tgl_kejadian ASC, id_history ASC"
);

// ── Hitung status garansi ─────────────────────────────────────────────────────
$garansi_info = '-';
if ($asset['tgl_garansi_selesai']) {
    $tgl_garansi = new DateTime($asset['tgl_garansi_selesai']);
    $today = new DateTime();
    $diff  = $today->diff($tgl_garansi);
    $sisa  = $tgl_garansi > $today ? $diff->days : -$diff->days;
    if ($sisa < 0)       $garansi_info = 'Expired ' . abs($sisa) . ' hari lalu';
    elseif ($sisa <= 30) $garansi_info = 'Hampir Habis (sisa ' . $sisa . ' hari)';
    else                 $garansi_info = 'Aktif (sisa ' . $sisa . ' hari)';
}

// ── Helper: escape nilai untuk XML ───────────────────────────────────────────
function xval($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// ── Helper: satu Cell string ──────────────────────────────────────────────────
function cell($val, $styleID, $mergeAcross = 0) {
    $ma = $mergeAcross > 0 ? " ss:MergeAcross=\"{$mergeAcross}\"" : '';
    return "<Cell ss:StyleID=\"{$styleID}\"{$ma}><Data ss:Type=\"String\">" . xval($val) . "</Data></Cell>";
}

// ── Set header download ───────────────────────────────────────────────────────
$namaFile = 'Aset_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $asset['kode_asset'])
          . '_' . date('Ymd_His') . '.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Cache-Control: max-age=0');

// ═════════════════════════════════════════════════════════════════════════════
// OUTPUT XML SPREADSHEET
// ═════════════════════════════════════════════════════════════════════════════
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
          xmlns:x="urn:schemas-microsoft-com:office:excel"
          xmlns:o="urn:schemas-microsoft-com:office:office">

<Styles>

  <!-- Judul utama -->
  <Style ss:ID="s_title">
    <Font ss:Bold="1" ss:Size="13" ss:Color="#FFFFFF" ss:FontName="Arial"/>
    <Interior ss:Color="#0D47A1" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>

  <!-- Sub-info cetak -->
  <Style ss:ID="s_sub">
    <Font ss:Italic="1" ss:Size="8" ss:Color="#555555" ss:FontName="Arial"/>
    <Interior ss:Color="#E3F2FD" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>

  <!-- Header seksi (IDENTITAS, PEROLEHAN, dst) -->
  <Style ss:ID="s_sec">
    <Font ss:Bold="1" ss:Size="9" ss:Color="#FFFFFF" ss:FontName="Arial"/>
    <Interior ss:Color="#1565C0" ss:Pattern="Solid"/>
    <Alignment ss:Vertical="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FFFFFF"/>
    </Borders>
  </Style>

  <!-- Label (kolom kiri info) -->
  <Style ss:ID="s_lbl">
    <Font ss:Bold="1" ss:Size="9" ss:FontName="Arial"/>
    <Interior ss:Color="#E8F4FD" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BDBDBD"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BDBDBD"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BDBDBD"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BDBDBD"/>
    </Borders>
    <Alignment ss:Vertical="Center"/>
  </Style>

  <!-- Value (kolom kanan info) -->
  <Style ss:ID="s_val">
    <Font ss:Size="9" ss:FontName="Arial"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BDBDBD"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BDBDBD"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BDBDBD"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#BDBDBD"/>
    </Borders>
    <Alignment ss:Vertical="Center" ss:WrapText="1"/>
  </Style>

  <!-- Baris kosong pemisah -->
  <Style ss:ID="s_gap">
    <Interior ss:Color="#F5F5F5" ss:Pattern="Solid"/>
  </Style>

  <!-- Header tabel riwayat -->
  <Style ss:ID="s_th">
    <Font ss:Bold="1" ss:Size="9" ss:Color="#FFFFFF" ss:FontName="Arial"/>
    <Interior ss:Color="#2E7D32" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#FFFFFF"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#81C784"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FFFFFF"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#81C784"/>
    </Borders>
  </Style>

  <!-- Baris ganjil tabel -->
  <Style ss:ID="s_td">
    <Font ss:Size="9" ss:FontName="Arial"/>
    <Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
    </Borders>
    <Alignment ss:WrapText="1" ss:Vertical="Top"/>
  </Style>

  <!-- Baris genap tabel (alt) -->
  <Style ss:ID="s_td2">
    <Font ss:Size="9" ss:FontName="Arial"/>
    <Interior ss:Color="#F1F8E9" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
    </Borders>
    <Alignment ss:WrapText="1" ss:Vertical="Top"/>
  </Style>

  <!-- Nomor (center) -->
  <Style ss:ID="s_num">
    <Font ss:Size="9" ss:FontName="Arial"/>
    <Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
    </Borders>
    <Alignment ss:Horizontal="Center" ss:Vertical="Top"/>
  </Style>
  <Style ss:ID="s_num2">
    <Font ss:Size="9" ss:FontName="Arial"/>
    <Interior ss:Color="#F1F8E9" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E0E0E0"/>
    </Borders>
    <Alignment ss:Horizontal="Center" ss:Vertical="Top"/>
  </Style>

  <!-- Judul sheet riwayat -->
  <Style ss:ID="s_title2">
    <Font ss:Bold="1" ss:Size="13" ss:Color="#FFFFFF" ss:FontName="Arial"/>
    <Interior ss:Color="#1B5E20" ss:Pattern="Solid"/>
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
  </Style>

  <!-- Notes: header label (icon + teks bold) -->
  <Style ss:ID="s_note_hdr">
    <Font ss:Bold="1" ss:Size="9" ss:Color="#FFFFFF" ss:FontName="Arial"/>
    <Interior ss:Color="#37474F" ss:Pattern="Solid"/>
    <Alignment ss:Vertical="Center"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#546E7A"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#546E7A"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#37474F"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#37474F"/>
    </Borders>
  </Style>

  <!-- Notes: isi teks keterangan -->
  <Style ss:ID="s_note_val">
    <Font ss:Size="9" ss:Italic="1" ss:Color="#37474F" ss:FontName="Arial"/>
    <Interior ss:Color="#ECEFF1" ss:Pattern="Solid"/>
    <Alignment ss:Vertical="Center" ss:WrapText="1"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B0BEC5"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#37474F"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B0BEC5"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#B0BEC5"/>
    </Borders>
  </Style>

  <!-- Notes: baris pemisah tipis sebelum notes -->
  <Style ss:ID="s_note_gap">
    <Interior ss:Color="#CFD8DC" ss:Pattern="Solid"/>
  </Style>

</Styles>

<?php
// ═════════════════════════════════════════════════════════════════════════════
// SHEET 1 — INFO ASET
// ═════════════════════════════════════════════════════════════════════════════
?>
<Worksheet ss:Name="Info Aset">
<Table ss:DefaultColumnWidth="80">

  <Column ss:Index="1" ss:Width="130"/>
  <Column ss:Index="2" ss:Width="300"/>

  <!-- Judul -->
  <Row ss:Height="26">
    <?= cell('LAPORAN DETAIL ASET IT', 's_title', 1) ?>
  </Row>
  <Row ss:Height="16">
    <?= cell('Dicetak: ' . date('d/m/Y H:i') . '   |   Oleh: ' . ($_SESSION['nama'] ?? '-'), 's_sub', 1) ?>
  </Row>

  <!-- Baris kosong -->
  <Row ss:Height="8">
    <?= cell('', 's_gap', 1) ?>
  </Row>

<?php
// ── Definisi seksi info ───────────────────────────────────────────────────────
$sections = [
    'IDENTITAS ASET' => [
        ['Kode Aset',      $asset['kode_asset']],
        ['Nama Aset',       $asset['nama_asset']],
        ['Merk',            $asset['merk'] ?: '-'],
        ['Model',           $asset['model'] ?: '-'],
        ['Serial Number',   $asset['serial_number'] ?: '-'],
        ['No. IMEI',        $asset['no_imei'] ?: '-'],
        ['Spesifikasi',     $asset['spesifikasi'] ?: '-'],
        ['Kondisi',         $asset['kondisi']],
    ],
    'INFO PEROLEHAN' => [
        ['Sumber Perolehan', $asset['sumber_perolehan']],
        ['Tanggal Perolehan', $asset['tgl_perolehan'] ? date('d/m/Y', strtotime($asset['tgl_perolehan'])) : '-'],
        ['Harga Perolehan',   'Rp ' . number_format((float)$asset['harga_perolehan'], 0, ',', '.')],
        ['Supplier',          $asset['supplier'] ?: '-'],
        ['No. PR / PO',       $asset['no_request'] ?: '-'],
    ],
    'GARANSI' => [
        ['Garansi Mulai',   $asset['tgl_garansi_mulai']   ? date('d/m/Y', strtotime($asset['tgl_garansi_mulai']))   : '-'],
        ['Garansi Selesai', $asset['tgl_garansi_selesai'] ? date('d/m/Y', strtotime($asset['tgl_garansi_selesai'])) : '-'],
        ['Status Garansi',  $garansi_info],
    ],
    'PENEMPATAN' => [
        ['Lokasi',          $asset['lokasi']     ?: '-'],
        ['Pengguna / PIC',  $asset['pengguna']   ?: '-'],
        ['Departemen',      $asset['departemen'] ?: '-'],
    ],
];

foreach ($sections as $sec => $items):
?>
  <!-- Seksi: <?= $sec ?> -->
  <Row ss:Height="18">
    <?= cell($sec, 's_sec', 1) ?>
  </Row>
<?php foreach ($items as $item): ?>
  <Row ss:Height="16">
    <?= cell($item[0], 's_lbl') ?>
    <?= cell($item[1], 's_val') ?>
  </Row>
<?php endforeach; ?>
  <Row ss:Height="6">
    <?= cell('', 's_gap', 1) ?>
  </Row>
<?php endforeach; ?>

</Table>
</Worksheet>

<?php
// ═════════════════════════════════════════════════════════════════════════════
// SHEET 2 — RIWAYAT ASET
// ═════════════════════════════════════════════════════════════════════════════
?>
<Worksheet ss:Name="Riwayat Aset">
<Table>

  <Column ss:Index="1"  ss:Width="30"/>   <!-- No -->
  <Column ss:Index="2"  ss:Width="65"/>   <!-- Tanggal -->
  <Column ss:Index="3"  ss:Width="95"/>   <!-- Jenis -->
  <Column ss:Index="4"  ss:Width="120"/>  <!-- Kondisi -->
  <Column ss:Index="5"  ss:Width="130"/>  <!-- Lokasi -->
  <Column ss:Index="6"  ss:Width="130"/>  <!-- Pengguna -->
  <Column ss:Index="7"  ss:Width="110"/>  <!-- Vendor -->
  <Column ss:Index="8"  ss:Width="85"/>   <!-- Biaya -->
  <Column ss:Index="9"  ss:Width="200"/>  <!-- Keterangan -->
  <Column ss:Index="10" ss:Width="80"/>   <!-- Oleh -->

  <!-- Judul sheet 2 -->
  <Row ss:Height="24">
    <?= cell(
        'RIWAYAT ASET — ' . strtoupper($asset['nama_asset']) . ' (' . $asset['kode_asset'] . ')',
        's_title2', 9
    ) ?>
  </Row>
  <Row ss:Height="14">
    <?= cell('Dicetak: ' . date('d/m/Y H:i') . '   |   Oleh: ' . ($_SESSION['nama'] ?? '-'), 's_sub', 9) ?>
  </Row>

  <!-- Header kolom -->
  <Row ss:Height="30">
    <?= cell('No',             's_th') ?>
    <?= cell('Tanggal',        's_th') ?>
    <?= cell('Jenis',          's_th') ?>
    <?= cell('Kondisi',        's_th') ?>
    <?= cell('Lokasi',         's_th') ?>
    <?= cell('Pengguna',       's_th') ?>
    <?= cell('Vendor/Teknisi', 's_th') ?>
    <?= cell('Biaya Servis',   's_th') ?>
    <?= cell('Keterangan',     's_th') ?>
    <?= cell('Dicatat Oleh',   's_th') ?>
  </Row>

<?php
$no = 1;
while ($hist = mysqli_fetch_assoc($q_hist)):
    $td    = ($no % 2 == 0) ? 's_td2' : 's_td';
    $tdNum = ($no % 2 == 0) ? 's_num2' : 's_num';

    $kondisi = '';
    if ($hist['kondisi_sebelum'] || $hist['kondisi_sesudah'])
        $kondisi = ($hist['kondisi_sebelum'] ?: '-') . ' -> ' . ($hist['kondisi_sesudah'] ?: '-');

    $lokasi = '';
    if ($hist['lokasi_sebelum'] || $hist['lokasi_sesudah'])
        $lokasi = ($hist['lokasi_sebelum'] ?: '-') . ' -> ' . ($hist['lokasi_sesudah'] ?: '-');

    // Pengguna: tampilkan perubahan jika PINDAH PENGGUNA, fallback ke pengguna aktif aset
    $pengguna = '';
    if ($hist['pengguna_sebelum'] || $hist['pengguna_sesudah'])
        $pengguna = ($hist['pengguna_sebelum'] ?: '-') . ' -> ' . ($hist['pengguna_sesudah'] ?: '-');
    else
        $pengguna = $asset['pengguna'] ?: '-';

    $biaya = $hist['biaya_servis'] > 0
           ? 'Rp ' . number_format((float)$hist['biaya_servis'], 0, ',', '.')
           : '-';
?>
  <Row>
    <?= cell($no,                                                                  $tdNum) ?>
    <?= cell($hist['tgl_kejadian'] ? date('d/m/Y', strtotime($hist['tgl_kejadian'])) : '-', $td) ?>
    <?= cell($hist['jenis_history'],                                               $td) ?>
    <?= cell($kondisi  ?: '-',                                                     $td) ?>
    <?= cell($lokasi   ?: '-',                                                     $td) ?>
    <?= cell($pengguna ?: '-',                                                     $td) ?>
    <?= cell($hist['vendor_servis'] ?: '-',                                        $td) ?>
    <?= cell($biaya,                                                               $td) ?>
    <?= cell($hist['keterangan'] ?: '-',                                           $td) ?>
    <?= cell($hist['created_by'] ?? '-',                                           $td) ?>
  </Row>
<?php $no++; endwhile; ?>

  <!-- ── Baris pemisah sebelum notes ── -->
  <Row ss:Height="10">
    <?= cell('', 's_note_gap', 9) ?>
  </Row>

  <!-- ── Blok CATATAN / NOTES ── -->
  <Row ss:Height="18">
    <?= cell('CATATAN', 's_note_hdr', 9) ?>
  </Row>
  <Row ss:Height="30">
    <?= cell('*', 's_note_hdr') ?>
    <?= cell('Kolom Vendor/Teknisi dan Biaya Servis bersifat OPSIONAL — hanya diisi apabila aset sedang atau pernah dalam proses servis (jenis riwayat: SERVIS MASUK atau SERVIS SELESAI).', 's_note_val', 8) ?>
  </Row>
  <Row ss:Height="22">
    <?= cell('*', 's_note_hdr') ?>
    <?= cell('Jika aset tidak pernah diservice, kedua kolom tersebut akan bernilai "-" dan dapat diabaikan.', 's_note_val', 8) ?>
  </Row>
  <Row ss:Height="22">
    <?= cell('*', 's_note_hdr') ?>
    <?= cell('Biaya Servis diisi dalam satuan Rupiah (Rp). Kosongkan atau isi 0 jika servis ditanggung garansi / tanpa biaya.', 's_note_val', 8) ?>
  </Row>

</Table>
</Worksheet>

</Workbook>