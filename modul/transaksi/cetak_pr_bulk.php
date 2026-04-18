<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';

$tgl_dari   = mysqli_real_escape_string($koneksi, $_GET['tgl_dari']   ?? $_GET['tgl'] ?? date('Y-m-d'));
$tgl_sampai = mysqli_real_escape_string($koneksi, $_GET['tgl_sampai'] ?? $_GET['tgl'] ?? date('Y-m-d'));
$status     = mysqli_real_escape_string($koneksi, $_GET['status']     ?? 'PENDING');

$sql   = "SELECT * FROM tr_request
          WHERE tgl_request BETWEEN '$tgl_dari' AND '$tgl_sampai'
            AND status_request = '$status'
          ORDER BY tgl_request ASC, no_request ASC";
$query = mysqli_query($koneksi, $sql);

if (mysqli_num_rows($query) == 0) {
    die("<script>alert('Tidak ada data PR pada periode tersebut.'); window.close();</script>");
}

// Tampung semua PR + detail
$all_pr = [];
while ($row = mysqli_fetch_assoc($query)) {
    $id_req = (int)$row['id_request'];
    $sql_d  = "SELECT d.*, b.nama_barang AS master, m.plat_nomor
               FROM tr_request_detail d
               LEFT JOIN master_barang b ON d.id_barang = b.id_barang
               LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
               WHERE d.id_request = $id_req
               ORDER BY d.id_detail ASC";
    $res_d = mysqli_query($koneksi, $sql_d);
    $items = [];
    while ($d = mysqli_fetch_assoc($res_d)) { $items[] = $d; }
    $row['items'] = $items;
    $all_pr[] = $row;
}

// ── Generate no_form untuk PR yang belum punya (bulk, aman dari race condition) ──
$bulan = date('m');
$tahun = date('Y');

// Ambil nomor urut terakhir bulan ini dari DB dulu (1 kali query)
$prefix_like = "PR-MCP-%-$bulan/$tahun";
$q_last = mysqli_query($koneksi,
    "SELECT no_form FROM tr_request
     WHERE no_form LIKE '$prefix_like'
     ORDER BY no_form DESC LIMIT 1"
);
$last_row = mysqli_fetch_assoc($q_last);
$last_urut = 0;
if ($last_row) {
    // Ambil angka urut dari format PR-MCP-001/01/2026
    preg_match('/PR-MCP-(\d+)\//', $last_row['no_form'], $m);
    $last_urut = isset($m[1]) ? (int)$m[1] : 0;
}

// Loop, generate hanya yang belum punya no_form
foreach ($all_pr as &$pr) {
    if (empty($pr['no_form'])) {
        $bulan = date('m');
        $tahun = date('Y');

        // Ambil 3 digit terakhir dari no_request
        $suffix = substr($pr['no_request'], -3);

        $no_form = "PR-MCP-$suffix/$bulan/$tahun";

        mysqli_query($koneksi,
            "UPDATE tr_request SET no_form = '$no_form'
             WHERE id_request = " . (int)$pr['id_request']
        );
        $pr['no_form'] = $no_form;
    }
}
unset($pr);

$total   = count($all_pr);
$periode = ($tgl_dari === $tgl_sampai)
    ? date('d/m/Y', strtotime($tgl_dari))
    : date('d/m/Y', strtotime($tgl_dari)).' s/d '.date('d/m/Y', strtotime($tgl_sampai));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Bulk PR - MCP</title>
    <style>
        @page { size: 21.5cm 33cm portrait; margin: 0.8cm 1cm; }
        * { box-sizing: border-box; }
        body { font-family:Arial,sans-serif; font-size:8pt; margin:0; padding:0; color:#000; background:#fff; }

        /* 1 PR = 1 halaman */
        .pr-page { width:100%; page-break-after:always; }
        .pr-page:last-child { page-break-after:auto; }

        /* Header halaman */
        .page-header {
            display:flex; justify-content:space-between; align-items:flex-end;
            border-bottom:2px solid #000; padding-bottom:4px; margin-bottom:7px;
        }
        .header-left .title    { font-size:11pt; font-weight:bold; }
        .header-left .subtitle { font-size:7.5pt; color:#444; }
        .header-right          { font-size:7pt; text-align:right; color:#555; line-height:1.7; }

        /* Infobar PR */
        .pr-infobar {
            background:#e9e9e9 !important;
            -webkit-print-color-adjust:exact;
            print-color-adjust:exact;
            display:flex; justify-content:space-between; flex-wrap:wrap;
            font-weight:bold; font-size:7.5pt;
            padding:4px 7px;
            border:0.5px solid #000; border-bottom:none;
        }

        /* Tabel item */
        table.data-table { width:100%; border-collapse:collapse; font-size:7.5pt; table-layout:fixed; }
        table.data-table th,
        table.data-table td {
            border:0.5px solid #000;
            padding:3px 4px;
            vertical-align:middle;
            word-wrap:break-word;
        }
        table.data-table th {
            background:#f2f2f2 !important;
            -webkit-print-color-adjust:exact;
            print-color-adjust:exact;
            text-align:center; font-size:7pt;
        }

        .c-no   { width:22px;  text-align:center; }
        .c-mob  { width:55px;  text-align:center; }
        .c-tipe { width:36px;  text-align:center; }
        .c-qty  { width:52px;  text-align:center; }
        .c-ket  { width:95px; }
        /* TTD per baris */
        .c-ttd  { width:48px; text-align:center; height:36px; font-size:6.5pt; color:#777; }

        /* Catatan TTD & footer */
        .ttd-note {
            margin-top:4px; font-size:6.5pt; color:#444;
            border-top:0.5px dashed #aaa; padding-top:3px;
        }
        .page-footer {
            margin-top:6px; font-size:6.5pt; color:#666; font-style:italic;
            display:flex; justify-content:space-between;
        }

        @media print {
            .no-print { display:none !important; }
            body { -webkit-print-color-adjust:exact; }
        }
    </style>
</head>
<body onload="window.print()">

<!-- Tombol print -->
<div class="no-print" style="background:#fff3cd;padding:8px;margin-bottom:14px;border:1px solid #ffc107;border-radius:4px;display:flex;align-items:center;gap:12px;">
    <button onclick="window.print()" style="padding:7px 18px;background:#007bff;color:#fff;border:none;border-radius:4px;font-weight:bold;cursor:pointer;font-size:12px;">
        🖨️ PRINT BULK PR
    </button>
    <span style="font-size:11px;color:#555;">
        Legal Portrait &nbsp;|&nbsp;
        Periode: <strong><?= $periode ?></strong> &nbsp;|&nbsp;
        Total PR: <strong><?= $total ?></strong>
    </span>
</div>

<?php foreach ($all_pr as $idx => $pr): ?>
<div class="pr-page">

    <!-- Header halaman -->
    <div class="page-header">
        <div class="header-left">
            <div class="title">PURCHASE REQUEST FORM</div>
            <div class="subtitle">PT. Mutiara Cahaya Plastindo</div>
        </div>
        <div class="header-right">
            Periode: <strong><?= $periode ?></strong><br>
            <?= ($idx + 1) ?>/<?= $total ?> &nbsp;|&nbsp; Cetak: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <!-- Info bar PR -->
    <div class="pr-infobar">
        <span>NO: <?= $pr['no_request'] ?></span>
        <span>TGL: <?= date('d/m/Y', strtotime($pr['tgl_request'])) ?></span>
        <span>ADMIN: <?= strtoupper($pr['nama_pemesan']) ?></span>
        <span>PEMBELI: <?= strtoupper($pr['nama_pembeli'] ?: '-') ?></span>
        <span>KATEGORI: <?= $pr['kategori_pr'] ?></span>
    </div>

    <!-- Tabel item -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="c-no">NO</th>
                <th>NAMA BARANG / ITEM</th>
                <th class="c-mob">UNIT/MOBIL</th>
                <th class="c-tipe">TIPE</th>
                <th class="c-qty">QTY</th>
                <th class="c-ket">KETERANGAN</th>
                <th class="c-ttd">TTD 1</th>
                <th class="c-ttd">TTD 2</th>
                <th class="c-ttd">TTD 3</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pr['items'] as $i => $d):
                $nama = !empty($d['nama_barang_manual']) ? $d['nama_barang_manual'] : $d['master'];
            ?>
            <tr>
                <td style="text-align:center;"><?= $i + 1 ?></td>
                <td style="font-weight:bold;"><?= strtoupper($nama) ?></td>
                <td style="text-align:center;font-size:7pt;">
                    <?= ($d['id_mobil'] != 0 && !empty($d['plat_nomor'])) ? $d['plat_nomor'] : '-' ?>
                </td>
                <td style="text-align:center;font-size:6.5pt;font-weight:bold;"><?= $d['tipe_request'] ?></td>
                <td style="text-align:center;"><b><?= (float)$d['jumlah'] ?></b> <?= $d['satuan'] ?></td>
                <td style="font-size:7pt;color:#333;"><?= $d['keterangan'] ?: '-' ?></td>
                <td class="c-ttd"></td>
                <td class="c-ttd"></td>
                <td class="c-ttd"></td>
            </tr>
            <?php endforeach; ?>

            <?php for ($x = count($pr['items']); $x < 3; $x++): ?>
            <tr>
                <td style="text-align:center;color:#ccc;"><?= $x + 1 ?></td>
                <td>&nbsp;</td><td></td><td></td><td></td><td></td>
                <td class="c-ttd"></td><td class="c-ttd"></td><td class="c-ttd"></td>
            </tr>
            <?php endfor; ?>
        </tbody>
    </table>

    <!-- Keterangan TTD -->
    <div class="ttd-note">
        TTD 1 = Pemesan &nbsp;|&nbsp; TTD 2 = Pembeli &nbsp;|&nbsp; TTD 3 = Mengetahui
    </div>

    <!-- Footer halaman 
    <div class="page-footer">
        <span>TTD 1 = Pemesan | TTD 2 = Pembeli | TTD 3 = Mengetahui</span>
        <span><?= $pr['no_request'] ?> — <?= ($idx + 1) ?>/<?= $total ?></span>
    </div>-->

</div><!-- /pr-page -->
<?php endforeach; ?>

</body>
</html>