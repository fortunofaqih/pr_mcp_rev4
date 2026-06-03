<?php
/**
 * cetak_pdf_aset.php
 * Halaman cetak / PDF riwayat + detail aset IT.
 * 
 * Cara 1 — Print dari browser (Ctrl+P → Save as PDF)  ← default, tanpa library
 * Cara 2 — Pakai mPDF  : aktifkan blok mPDF di bawah
 * Cara 3 — Pakai TCPDF : aktifkan blok TCPDF di bawah
 * 
 * URL: cetak_pdf_aset.php?id=123
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

$q = mysqli_query($koneksi, "SELECT * FROM master_it_asset WHERE id_asset = $id");
if (!$q || mysqli_num_rows($q) == 0) { header("Location: index.php"); exit; }
$asset = mysqli_fetch_assoc($q);

$q_hist = mysqli_query($koneksi,
    "SELECT * FROM tr_it_asset_history
     WHERE id_asset = $id
     ORDER BY tgl_kejadian ASC, id_history ASC"
);

// ── Hitung garansi ────────────────────────────────────────────────────────────
$garansi_info  = '-';
$garansi_color = '#333';
if ($asset['tgl_garansi_selesai']) {
    $tgl_garansi = new DateTime($asset['tgl_garansi_selesai']);
    $today = new DateTime();
    $diff  = $today->diff($tgl_garansi);
    $sisa  = $tgl_garansi > $today ? $diff->days : -$diff->days;
    if ($sisa < 0)        { $garansi_info = 'EXPIRED (' . abs($sisa) . ' hari lalu)'; $garansi_color = '#c62828'; }
    elseif ($sisa <= 30)  { $garansi_info = 'HAMPIR HABIS (sisa ' . $sisa . ' hari)'; $garansi_color = '#e65100'; }
    else                  { $garansi_info = 'AKTIF (sisa ' . $sisa . ' hari)'; $garansi_color = '#1b5e20'; }
}

$kondisi_colors = [
    'BAGUS'       => '#1b5e20',
    'RUSAK'       => '#b71c1c',
    'DI-SERVICE'  => '#e65100',
    'TIDAK AKTIF' => '#37474f',
    'HILANG'      => '#212121',
];
$kondisi_color = $kondisi_colors[$asset['kondisi']] ?? '#333';

// ── mPDF (opsional) ───────────────────────────────────────────────────────────
// Aktifkan jika mPDF sudah di-install via Composer
/*
$mpdfPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($mpdfPath)) {
    require $mpdfPath;
    ob_start();
    // ... render HTML ke buffer ...
    $html = ob_get_clean();
    $mpdf = new \Mpdf\Mpdf(['margin_top'=>15,'margin_bottom'=>15]);
    $mpdf->WriteHTML($html);
    $mpdf->Output('Aset_' . $asset['kode_asset'] . '.pdf', 'D');
    exit;
}
*/

// ── Render HTML untuk cetak browser ──────────────────────────────────────────
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Riwayat Aset — <?= htmlspecialchars($asset['kode_asset']) ?></title>
    <style>
        /* ─── Reset & Base ──────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            color: #212121;
            background: #f5f5f5;
        }

        /* ─── Layout ──────────────────────────────────── */
        .page {
            width: 210mm;
            min-height: 297mm;
            background: #fff;
            margin: 10mm auto;
            padding: 14mm 14mm 18mm;
            box-shadow: 0 2px 12px rgba(0,0,0,.18);
        }

        /* ─── Header dokumen ──────────────────────────── */
        .doc-header {
            display: flex;
            align-items: center;
            border-bottom: 3px solid #0D47A1;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .doc-header .logo-wrap {
            width: 52px;
            height: 52px;
            background: #0D47A1;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22pt;
            flex-shrink: 0;
            margin-right: 12px;
        }
        .doc-title h1 { font-size: 14pt; color: #0D47A1; font-weight: 700; }
        .doc-title p  { font-size: 8pt; color: #666; margin-top: 2px; }
        .doc-meta {
            margin-left: auto;
            text-align: right;
            font-size: 8pt;
            color: #555;
            line-height: 1.5;
        }

        /* ─── Badge kondisi ───────────────────────────── */
        .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 12px;
            font-size: 8pt;
            font-weight: 700;
            color: #fff;
            letter-spacing: .5px;
        }

        /* ─── Info grid (2-kolom) ─────────────────────── */
        .info-section { margin-bottom: 12px; }
        .section-title {
            background: #0D47A1;
            color: #fff;
            font-size: 9pt;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 4px 4px 0 0;
            letter-spacing: .5px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #cfd8dc;
            border-top: none;
            border-radius: 0 0 4px 4px;
            overflow: hidden;
        }
        .info-grid.full { grid-template-columns: 1fr; }
        .info-cell {
            padding: 5px 10px;
            border-bottom: 1px solid #eceff1;
            border-right: 1px solid #eceff1;
        }
        .info-cell:last-child, .info-cell:nth-last-child(-n+2):nth-child(odd) { border-bottom: none; }
        .info-cell:nth-child(even) { border-right: none; }
        .info-cell .lbl { font-size: 7.5pt; text-transform: uppercase; font-weight: 800; color: #78909c; letter-spacing: .8px; }
        .info-cell .val { font-size: 9.5pt; font-weight: 500; margin-top: 1px; }

        /* ─── Timeline riwayat ────────────────────────── */
        .history-title {
            background: #1B5E20;
            color: #fff;
            font-size: 9pt;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: 4px 4px 0 0;
            letter-spacing: .5px;
            margin-top: 14px;
        }
        table.hist-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            border: 1px solid #cfd8dc;
            border-top: none;
        }
        table.hist-table thead tr {
            background: #2E7D32;
            color: #fff;
        }
        table.hist-table thead th {
            padding: 5px 6px;
            text-align: left;
            font-weight: 700;
            font-size: 8pt;
            border-right: 1px solid #43A047;
        }
        table.hist-table thead th:last-child { border-right: none; }
        table.hist-table tbody tr { border-bottom: 1px solid #e0e0e0; }
        table.hist-table tbody tr:nth-child(even) { background: #F1F8E9; }
        table.hist-table tbody td {
            padding: 5px 6px;
            vertical-align: top;
            border-right: 1px solid #e8f5e9;
            line-height: 1.4;
        }
        table.hist-table tbody td:last-child { border-right: none; }
        .jenis-badge {
            display: inline-block;
            padding: 1px 7px;
            border-radius: 8px;
            font-size: 7.5pt;
            font-weight: 700;
            color: #fff;
        }
        .arrow { color: #0D47A1; font-weight: 700; }

        /* ─── Footer ──────────────────────────────────── */
        .doc-footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #cfd8dc;
            display: flex;
            justify-content: space-between;
            font-size: 8pt;
            color: #9e9e9e;
        }
        .sign-box {
            width: 150px;
            text-align: center;
            font-size: 8pt;
        }
        .sign-box .sign-line {
            border-top: 1px solid #333;
            margin: 40px 0 2px;
        }

        /* ─── Tombol cetak (tidak tercetak) ───────────── */
        .no-print {
            text-align: center;
            padding: 10px;
            background: #e3f2fd;
            border-radius: 6px;
            margin: 0 auto 8mm;
            width: 210mm;
        }
        .no-print button {
            background: #0D47A1;
            color: #fff;
            border: none;
            padding: 8px 28px;
            font-size: 11pt;
            border-radius: 5px;
            cursor: pointer;
            margin: 0 6px;
            font-weight: 700;
        }
        .no-print button.btn-back {
            background: #546e7a;
        }
        .no-print button:hover { opacity: .88; }

        /* ─── Print media query ───────────────────────── */
        @media print {
            body { background: #fff; }
            .page { margin: 0; padding: 10mm 12mm 15mm; box-shadow: none; width: 100%; }
            .no-print { display: none !important; }
            table.hist-table { page-break-inside: auto; }
            table.hist-table tr { page-break-inside: avoid; }
            .info-section, .history-title { page-break-inside: avoid; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>

<!-- Toolbar cetak -->
<div class="no-print">
    <button onclick="window.print()">🖨️ Cetak / Save PDF</button>
    <button class="btn-back" onclick="window.history.back()">← Kembali</button>
</div>

<div class="page">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="doc-header">
        <div class="logo-wrap">💻</div>
        <div class="doc-title">
            <h1>LAPORAN RIWAYAT ASET IT</h1>
            <p>Dokumen ini dicetak secara otomatis dari Sistem Manajemen Aset IT</p>
        </div>
        <div class="doc-meta">
            <strong>Dicetak oleh:</strong> <?= htmlspecialchars($_SESSION['nama'] ?? '-') ?><br>
            <strong>Tanggal:</strong> <?= date('d/m/Y H:i') ?><br>
            <strong>Kode Aset:</strong> <?= htmlspecialchars($asset['kode_asset']) ?>
        </div>
    </div>

    <!-- ── Identitas + Kondisi ────────────────────────────────────────────── -->
    <div class="info-section">
        <div class="section-title">🔷 IDENTITAS ASET</div>
        <div class="info-grid">
            <div class="info-cell">
                <div class="lbl">Kode Aset</div>
                <div class="val"><strong><?= htmlspecialchars($asset['kode_asset']) ?></strong></div>
            </div>
            <div class="info-cell">
                <div class="lbl">Kondisi Saat Ini</div>
                <div class="val">
                    <span class="badge" style="background:<?= $kondisi_color ?>"><?= $asset['kondisi'] ?></span>
                </div>
            </div>
            <div class="info-cell">
                <div class="lbl">Nama Aset</div>
                <div class="val"><?= htmlspecialchars($asset['nama_asset']) ?></div>
            </div>
            <div class="info-cell">
                <div class="lbl">Merk / Model</div>
                <div class="val"><?= htmlspecialchars(trim(($asset['merk'] ?? '') . ' ' . ($asset['model'] ?? ''))) ?: '-' ?></div>
            </div>
            <div class="info-cell">
                <div class="lbl">Serial Number</div>
                <div class="val"><?= htmlspecialchars($asset['serial_number'] ?: '-') ?></div>
            </div>
            <div class="info-cell">
                <div class="lbl">No. IMEI</div>
                <div class="val"><?= htmlspecialchars($asset['no_imei'] ?: '-') ?></div>
            </div>
        </div>
        <?php if ($asset['spesifikasi']): ?>
        <div class="info-grid full" style="border-top:none; margin-top:-1px;">
            <div class="info-cell">
                <div class="lbl">Spesifikasi</div>
                <div class="val" style="font-size:8.5pt;"><?= nl2br(htmlspecialchars($asset['spesifikasi'])) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Perolehan + Garansi + Penempatan (3-kolom ringkas) ─────────────── -->
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:12px;">

        <div class="info-section" style="margin-bottom:0;">
            <div class="section-title" style="background:#1565C0;">📦 PEROLEHAN</div>
            <div class="info-grid full">
                <div class="info-cell"><div class="lbl">Sumber</div><div class="val"><?= htmlspecialchars($asset['sumber_perolehan']) ?></div></div>
                <div class="info-cell"><div class="lbl">Tgl Perolehan</div><div class="val"><?= $asset['tgl_perolehan'] ? date('d/m/Y', strtotime($asset['tgl_perolehan'])) : '-' ?></div></div>
                <div class="info-cell"><div class="lbl">Harga</div><div class="val">Rp <?= number_format((float)$asset['harga_perolehan'], 0, ',', '.') ?></div></div>
                <div class="info-cell"><div class="lbl">Supplier</div><div class="val"><?= htmlspecialchars($asset['supplier'] ?: '-') ?></div></div>
                <div class="info-cell" style="border-bottom:none;"><div class="lbl">No. PR/PO</div><div class="val"><?= htmlspecialchars($asset['no_request'] ?: '-') ?></div></div>
            </div>
        </div>

        <div class="info-section" style="margin-bottom:0;">
            <div class="section-title" style="background:#E65100;">🛡️ GARANSI</div>
            <div class="info-grid full">
                <div class="info-cell"><div class="lbl">Mulai</div><div class="val"><?= $asset['tgl_garansi_mulai']   ? date('d/m/Y', strtotime($asset['tgl_garansi_mulai']))   : '-' ?></div></div>
                <div class="info-cell"><div class="lbl">Selesai</div><div class="val"><?= $asset['tgl_garansi_selesai'] ? date('d/m/Y', strtotime($asset['tgl_garansi_selesai'])) : '-' ?></div></div>
                <div class="info-cell" style="border-bottom:none;"><div class="lbl">Status</div><div class="val" style="color:<?= $garansi_color ?>;font-weight:700;"><?= $garansi_info ?></div></div>
            </div>
        </div>

        <div class="info-section" style="margin-bottom:0;">
            <div class="section-title" style="background:#006064;">📍 PENEMPATAN</div>
            <div class="info-grid full">
                <div class="info-cell"><div class="lbl">Lokasi</div><div class="val"><?= htmlspecialchars($asset['lokasi'] ?: '-') ?></div></div>
                <div class="info-cell"><div class="lbl">Pengguna / PIC</div><div class="val"><?= htmlspecialchars($asset['pengguna'] ?: '-') ?></div></div>
                <div class="info-cell" style="border-bottom:none;"><div class="lbl">Departemen</div><div class="val"><?= htmlspecialchars($asset['departemen'] ?: '-') ?></div></div>
            </div>
        </div>

    </div>

    <!-- ── Tabel Riwayat ──────────────────────────────────────────────────── -->
    <div class="history-title">📋 RIWAYAT / HISTORI ASET</div>
    <?php
    $jenis_colors = [
        'PENERIMAAN'      => '#1b5e20',
        'RUSAK'           => '#b71c1c',
        'SERVIS MASUK'    => '#e65100',
        'SERVIS SELESAI'  => '#2e7d32',
        'PINDAH LOKASI'   => '#006064',
        'PINDAH PENGGUNA' => '#01579b',
        'DISPOSE'         => '#37474f',
        'HILANG'          => '#212121',
        'KONDISI UPDATE'  => '#1a237e',
        'CATATAN'         => '#4e342e',
    ];
    $totalRows = mysqli_num_rows($q_hist);
    ?>
    <?php if ($totalRows == 0): ?>
    <div style="border:1px solid #cfd8dc; border-top:none; padding:20px; text-align:center; color:#9e9e9e; font-style:italic;">
        Belum ada riwayat tercatat untuk aset ini.
    </div>
    <?php else: ?>
    <table class="hist-table">
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th style="width:62px;">Tanggal</th>
                <th style="width:88px;">Jenis</th>
                <th>Kondisi</th>
                <th>Lokasi</th>
                <th>Pengguna</th>
                <th>Vendor / Teknisi</th>
                <th style="width:70px;">Biaya</th>
                <th>Keterangan</th>
                <th style="width:62px;">Oleh</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $no = 1;
        // Reset pointer karena sudah di-fetch sebelumnya (tidak)
        // Jika q_hist sudah di-while sebelumnya harus di-reset:
        // mysqli_data_seek($q_hist, 0);
        while ($hist = mysqli_fetch_assoc($q_hist)):
            $jColor   = $jenis_colors[$hist['jenis_history']] ?? '#555';
            $kondisi  = '';
            if ($hist['kondisi_sebelum'] || $hist['kondisi_sesudah']) {
                $kondisi = ($hist['kondisi_sebelum'] ?: '?')
                         . ' <span class="arrow">→</span> '
                         . ($hist['kondisi_sesudah'] ?: '?');
            }
            $lokasi = '';
            if ($hist['lokasi_sebelum'] || $hist['lokasi_sesudah']) {
                $lokasi = htmlspecialchars($hist['lokasi_sebelum'] ?: '-')
                        . ' <span class="arrow">→</span> '
                        . htmlspecialchars($hist['lokasi_sesudah'] ?: '-');
            }
            // Pengguna: tampilkan perubahan jika PINDAH PENGGUNA, fallback ke pengguna aktif aset
            $pengguna = '';
            if ($hist['pengguna_sebelum'] || $hist['pengguna_sesudah']) {
                $pengguna = htmlspecialchars($hist['pengguna_sebelum'] ?: '-')
                          . ' <span class="arrow">→</span> '
                          . htmlspecialchars($hist['pengguna_sesudah'] ?: '-');
            } else {
                $pengguna = htmlspecialchars($asset['pengguna'] ?: '-');
            }
        ?>
        <tr>
            <td style="text-align:center;"><?= $no ?></td>
            <td><?= $hist['tgl_kejadian'] ? date('d/m/Y', strtotime($hist['tgl_kejadian'])) : '-' ?></td>
            <td>
                <span class="jenis-badge" style="background:<?= $jColor ?>">
                    <?= htmlspecialchars($hist['jenis_history']) ?>
                </span>
            </td>
            <td><?= $kondisi ?: '-' ?></td>
            <td><?= $lokasi ?: '-' ?></td>
            <td><?= $pengguna ?: '-' ?></td>
            <td><?= htmlspecialchars($hist['vendor_servis'] ?: '-') ?></td>
            <td style="text-align:right;">
                <?= $hist['biaya_servis'] > 0 ? 'Rp ' . number_format((float)$hist['biaya_servis'], 0, ',', '.') : '-' ?>
            </td>
            <td style="font-style:italic; color:#555;"><?= htmlspecialchars($hist['keterangan'] ?: '-') ?></td>
            <td><?= htmlspecialchars($hist['created_by'] ?? '-') ?></td>
        </tr>
        <?php $no++; endwhile; ?>
        </tbody>
    </table>
    <div style="font-size:8pt; color:#666; text-align:right; margin-top:4px;">
        Total: <?= $totalRows ?> riwayat tercatat
    </div>
    <?php endif; ?>

    <!-- ── Footer / Tanda tangan ─────────────────────────────────────────── -->
    <div class="doc-footer">
        <div style="align-self:flex-end;">
            <small>Dokumen ini digenerate otomatis — Sistem Aset IT &copy; <?= date('Y') ?></small>
        </div>
        <div class="sign-box">
            <div>Mengetahui,</div>
            <div class="sign-line"></div>
            <div><strong>Staff IT / Administrator</strong></div>
            <div style="color:#9e9e9e;">(______________________)</div>
        </div>
    </div>

</div><!-- end .page -->

<script>
// Auto-print jika parameter ?print=1 disisipkan
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 400));
}
</script>

</body>
</html>