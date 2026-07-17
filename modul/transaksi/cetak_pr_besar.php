<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// ── 1. TERIMA PARAMETER: bisa ?id=123 ATAU ?no=PRB/IV/26/0002 ────────────────
$h = null;

if (!empty($_GET['id'])) {
    $id_raw = (int)$_GET['id'];
    $h = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT * FROM tr_request
         WHERE id_request = '$id_raw'
         AND kategori_pr IN ('BESAR','IT')
         LIMIT 1"));

} elseif (!empty($_GET['no'])) {
    $no_raw = mysqli_real_escape_string($koneksi, trim($_GET['no']));
    $h = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT * FROM tr_request
         WHERE no_request = '$no_raw'
         AND kategori_pr IN ('BESAR','IT')
         LIMIT 1"));
}

if (!$h) {
    die("<div style='font-family:Arial;padding:30px;color:red;'>
            <b>Data tidak ditemukan.</b><br>
            Pastikan parameter <code>?id=</code> atau <code>?no=PRB/...</code> / <code>?no=PRI/...</code> sudah benar,
            dan nomor request dimulai dengan <b>PRB</b> (Besar) atau <b>PRI</b> (IT).
         </div>");
}

$id = (int)$h['id_request'];

// ── 2. GENERATE no_form jika belum ada ───────────────────────────────────────
if (empty($h['no_form'])) {
    $bulan  = date('m');
    $tahun  = date('Y');
    $suffix = substr($h['no_request'], -4);
    $prefix_form = ($h['kategori_pr'] === 'IT') ? 'PRI-MCP' : 'PRB-MCP';
    $no_form = "$prefix_form-$suffix/$bulan/$tahun";
    mysqli_query($koneksi, "UPDATE tr_request SET no_form = '$no_form' WHERE id_request = '$id'");
    $h['no_form'] = $no_form;
}

// ── 3. AMBIL DETAIL BARANG (Disesuaikan dengan struktur tabel pembelian Anda) ──
$items = [];
$q = mysqli_query($koneksi,
    "SELECT 
            d.id_detail,
            d.id_request,
            d.nama_barang_manual,
            d.id_barang,
            d.id_mobil,
            d.jumlah,
            d.satuan,
            d.harga_satuan_estimasi,
            d.status_item,
            d.subtotal_estimasi,
            d.kategori_barang,
            d.kwalifikasi,
            d.tipe_request,
            d.keterangan,
            m.plat_nomor,
            b.nama_barang as nama_barang_master,
            p.tgl_beli_barang,
            p.nama_barang_beli as nama_barang_final,      
            p.qty as qty_final,                            
            (SELECT GROUP_CONCAT(DISTINCT ms.nama_supplier SEPARATOR ', ') 
             FROM tr_purchase_order po 
             JOIN master_supplier ms ON po.id_supplier = ms.id_supplier 
             WHERE po.id_request = d.id_request) as supplier_names
     FROM tr_request_detail d
     LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
     LEFT JOIN master_barang b ON d.id_barang = b.id_barang
     LEFT JOIN pembelian     p ON p.id_request_detail = d.id_detail
     WHERE d.id_request = '$id'
     ORDER BY d.id_detail ASC");

while ($d = mysqli_fetch_assoc($q)) { 
    $items[] = $d; 
}

// ── 4. AMBIL NAMA LENGKAP APPROVER ───────────────────────────────────────────
function getApproverData($koneksi, $username) {
    if (empty($username)) return ['nama_lengkap' => ''];
    $u = mysqli_real_escape_string($koneksi, $username);
    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT nama_lengkap FROM users WHERE username = '$u' LIMIT 1"));
    return ['nama_lengkap' => $r ? strtoupper($r['nama_lengkap']) : strtoupper($username)];
}

$approver = [];
foreach ([1,2,3] as $lv) {
    $by = $h["approve{$lv}_by"] ?? '';
    $at = $h["approve{$lv}_at"] ?? '';
    $approver[$lv] = [
        'by'           => $by,
        'at'           => $at,
        'nama_lengkap' => getApproverData($koneksi, $by)['nama_lengkap'],
    ];
}

// ── 5. PATH GAMBAR TANDA TANGAN ───────────────────────────────────────────────
$ttd_base_dir = $_SERVER['DOCUMENT_ROOT'] . '/pr_mcp/assets/ttd/';
$ttd_base_url = '/pr_mcp/assets/ttd/';

function getTtdUrl($base_dir, $base_url, $username) {
    if (empty($username)) return null;
    $u = strtolower(trim($username));
    foreach (['png', 'jpg', 'jpeg'] as $ext) {
        if (file_exists($base_dir . $u . '.' . $ext)) {
            return $base_url . $u . '.' . $ext;
        }
    }
    return null;
}

$ttd_url = [
    1 => getTtdUrl($ttd_base_dir, $ttd_base_url, $approver[1]['by']),
    2 => getTtdUrl($ttd_base_dir, $ttd_base_url, $approver[2]['by']),
    3 => getTtdUrl($ttd_base_dir, $ttd_base_url, $approver[3]['by']),
];

$fully_approved = ($h['status_approval'] === 'APPROVED');
$need_m3        = (int)($h['need_approve3'] ?? 0);

$is_it         = ($h['kategori_pr'] === 'IT');
$judul_cetak   = $is_it ? 'PURCHASE REQUEST (PR) IT' : 'PURCHASE REQUEST (PR) BESAR';
$warna_badge   = $is_it ? '#1e40af' : '#1e3a8a';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PR <?= $is_it ? 'IT' : 'Besar' ?> - <?= htmlspecialchars($h['no_request']) ?></title>
    <style>
        @page { size: 21.5cm 16.5cm landscape; margin: 0.5cm 0.7cm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 8pt; margin: 0; padding: 8px; background: #fff; color: #000; }

        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
        .header h2 { margin: 0; font-size: 11pt; font-weight: bold; }
        .header h4 { margin: 0; font-size: 8pt; font-weight: normal; }

        .badge-pr {
            display: inline-block;
            background: <?= $warna_badge ?>;
            color: #fff;
            font-size: 6.5pt;
            font-weight: bold;
            padding: 2px 7px;
            border-radius: 3px;
            letter-spacing: .05em;
            vertical-align: middle;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .info-pr { width: 100%; margin-bottom: 5px; font-size: 7.5pt; font-weight: bold; border-collapse: collapse; }

        table.data { width: 100%; border-collapse: collapse; font-size: 7.5pt; table-layout: fixed; }
        table.data th {
            background-color: <?= $warna_badge ?> !important;
            color: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            text-align: center;
            font-size: 7pt;
            border: 0.5px solid #000;
            padding: 3px 4px;
        }
        table.data td {
            border: 0.5px solid #000;
            padding: 3px 4px;
            vertical-align: middle;
            word-wrap: break-word;
            white-space: normal;
            overflow: hidden;
        }

        .c-no       { width: 22px;  text-align: center; }
        .c-barang   { width: 150px; text-align: left; }
        .c-supplier { width: 85px;  text-align: center; }
        .c-tgl      { width: 55px;  text-align: center; }
        .c-unit     { width: 65px;  text-align: center; }
        .c-tipe     { width: 50px;  text-align: center; }
        .c-qty      { width: 65px;  text-align: center; }
        .c-harga    { width: 70px;  text-align: right; }
        .c-sub      { width: 80px;  text-align: right; }
        .c-ket      { width: auto;  text-align: left; }

        .ttd-section { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .ttd-section td { width: 33.33%; text-align: center; vertical-align: top; padding: 0 8px; }
        .ttd-box {
            border: 0.5px solid #cbd5e1; border-radius: 4px; padding: 5px 6px 5px;
            min-height: 85px; display: flex; flex-direction: column; align-items: center;
        }
        .ttd-label {
            font-size: 6.5pt; font-weight: bold; text-transform: uppercase;
            color: <?= $warna_badge ?>; margin-bottom: 4px; letter-spacing: .04em;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .ttd-img-area {
            width: 110px; height: 52px;
            display: flex; align-items: center; justify-content: center; margin: 0 auto 3px;
        }
        .ttd-img { max-width: 110px; max-height: 52px; object-fit: contain; }
        .ttd-empty-box {
            width: 110px; height: 52px; border: 0.5px dashed #94a3b8; border-radius: 3px;
            display: flex; align-items: center; justify-content: center;
        }
        .ttd-empty-text { font-size: 5.5pt; color: #94a3b8; font-style: italic; }
        .ttd-nama { font-size: 7pt; font-weight: bold; color: #1e293b; margin-top: 3px; text-align: center; line-height: 1.3; }
        .ttd-tgl  { font-size: 5.5pt; color: #64748b; margin-top: 1px; }
        .ttd-menunggu { font-size: 6pt; color: #94a3b8; font-style: italic; margin-top: 3px; }

        .wm-pending {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 52pt; font-weight: 900; color: rgba(220,0,0,.06);
            pointer-events: none; z-index: 0; letter-spacing: 4px;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">

<?php if (!$fully_approved): ?>
<div class="wm-pending">BELUM DISETUJUI</div>
<?php endif; ?>

<div class="no-print" style="background:#dbeafe; padding:8px; margin-bottom:10px; border:1px solid #93c5fd; border-radius:4px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
    <button onclick="window.print()" style="padding:7px 18px; background:<?= $warna_badge ?>; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
        🖨️ CETAK PR <?= $is_it ? 'IT' : 'BESAR' ?>
    </button>
    <span style="font-size:11px; color:#555;">Kertas: F4 / Setengah Folio (21.5 × 16.5 cm Landscape)</span>
    <span style="font-size:10px; color:#475569;">
        📁 Folder TTD: <code style="background:#e2e8f0; padding:1px 5px; border-radius:3px;">../../assets/ttd/{username}.png</code>
    </span>
    <span style="font-size:10px; font-weight:bold; margin-left:auto; color:<?= $fully_approved ? '#16a34a' : '#dc2626' ?>;">
        Status: <?= htmlspecialchars($h['status_approval']) ?>
    </span>
</div>

<div class="header">
    <h2><?= $judul_cetak ?></h2>
    <h4>PT. Mutiaracahaya Plastindo</h4>
</div>

<table class="info-pr">
    <tr>
        <td width="40%">
            NO: <b><?= htmlspecialchars($h['no_request']) ?></b>
            &nbsp;|&nbsp;
            FORM: <?= htmlspecialchars($h['no_form']) ?>
            &nbsp;
            <span class="badge-pr"><?= $is_it ? 'IT' : 'BESAR' ?></span>
        </td>
        <td width="30%" style="text-align:center;">ADMIN: <?= strtoupper(htmlspecialchars($h['nama_pemesan'])) ?></td>
        <td width="30%" style="text-align:right;">TGL: <?= date('d/m/Y', strtotime($h['tgl_request'])) ?></td>
    </tr>
    <!-- TAMBAHAN: Baris untuk Nama Pembeli -->
    <tr>
        <td colspan="3" style="text-align:center; font-weight:normal; padding-top:2px; border-top:0.5px dashed #ccc;">
            <span style="font-weight:bold;">PEMBELI:</span> <?= !empty($h['nama_pembeli']) ? strtoupper(htmlspecialchars($h['nama_pembeli'])) : '<span style="color:#999;">(belum diisi)</span>' ?>
        </td>
    </tr>
    <?php if (!empty($h['keterangan'])): ?>
    <tr>
        <td colspan="3" style="font-weight:normal; font-size:7pt; padding-top:2px;">
            Keperluan: <b><?= nl2br(htmlspecialchars($h['keterangan'])) ?></b>
        </td>
    </tr>
    <?php endif; ?>
</table>

<table class="data">
    <thead>
        <tr>
            <th class="c-no">NO</th>
            <th class="c-barang">NAMA BARANG / ITEM</th>
            <th class="c-supplier">SUPPLIER</th>
            <th class="c-tgl">TGL BELI</th>
            <th class="c-unit">UNIT / MOBIL</th>
            <th class="c-tipe">TIPE</th>
            <th class="c-qty">QTY</th>
            <th class="c-harga">EST. HARGA</th>
            <th class="c-sub">SUBTOTAL</th>
            <th class="c-ket">KETERANGAN</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $grand_total = 0;
        foreach ($items as $i => $d):
            // Fallback: Jika data sudah diverifikasi gudang, gunakan nama final & qty final. Satuan tetap ikut tr_request_detail.
            if (!empty($d['nama_barang_final'])) {
                $nama = $d['nama_barang_final'];
                $qty_tampil = (float)$d['qty_final'];
            } else {
                $nama = !empty($d['nama_barang_manual']) ? $d['nama_barang_manual'] : $d['nama_barang_master'];
                $qty_tampil = (float)$d['jumlah'];
            }

            $satuan_tampil = $d['satuan']; // Karena tabel pembelian tidak punya kolom satuan, ikut satuan asal
            $supplier = !empty($d['supplier_names']) ? $d['supplier_names'] : '-';
            $harga    = (float)($d['harga_satuan_estimasi'] ?? 0);
            $subtotal = (float)($d['subtotal_estimasi']    ?? 0);
            $grand_total += $subtotal;
        ?>
        <tr>
            <td style="text-align:center;"><?= $i + 1 ?></td>
            <td style="font-weight:bold;"><?= strtoupper(htmlspecialchars($nama)) ?></td>
            <td style="font-size:6.5pt; text-align:center;"><?= htmlspecialchars($supplier) ?></td>
            <td style="text-align:center;">
                <?php if (!empty($d['tgl_beli_barang']) && $d['tgl_beli_barang'] != '0000-00-00'): ?>
                    <span style="font-weight:bold; color:#166534;"><?= date('d/m/y', strtotime($d['tgl_beli_barang'])) ?></span>
                <?php else: ?>
                    <span style="color:#cbd5e1;">—</span>
                <?php endif; ?>
            </td>
            <td style="text-align:center;">
                <?= ($d['id_mobil'] != 0 && !empty($d['plat_nomor'])) ? htmlspecialchars($d['plat_nomor']) : '-' ?>
            </td>
            <td style="text-align:center; font-size:6.5pt; font-weight:bold;"><?= htmlspecialchars($d['tipe_request']) ?></td>
            <td style="text-align:center;"><b><?= $qty_tampil ?></b> <?= htmlspecialchars($satuan_tampil) ?></td>
            <td style="text-align:right;"><?= number_format($harga, 0, ',', '.') ?></td>
            <td style="text-align:right; font-weight:bold;"><?= number_format($subtotal, 0, ',', '.') ?></td>
            <td style="font-size:7pt;"><?= htmlspecialchars($d['keterangan'] ?: '-') ?></td>
        </tr>
        <?php endforeach; ?>

        <tr>
            <td colspan="8" style="text-align:right; font-weight:bold; background:#f8fafc;">GRAND TOTAL ESTIMASI :</td>
            <td style="text-align:right; font-weight:bold; background:#f8fafc; color:<?= $warna_badge ?>;">
                Rp <?= number_format($grand_total, 0, ',', '.') ?>
            </td>
            <td style="background:#f8fafc;"></td>
        </tr>
    </tbody>
</table>

<?php
$ttd_cols = [
    [
        'label'   => 'Approval 1',
        'nama'    => $approver[1]['nama_lengkap'],
        'at'      => $approver[1]['at'],
        'by'      => $approver[1]['by'],
        'img_url' => $ttd_url[1],
        'mode'    => 'digital',
    ],
    [
        'label'   => 'Approval 2',
        'nama'    => $approver[2]['nama_lengkap'],
        'at'      => $approver[2]['at'],
        'by'      => $approver[2]['by'],
        'img_url' => $ttd_url[2],
        'mode'    => 'digital',
    ],
    [
        'label'   => 'Approval 3 — ' . ($approver[3]['nama_lengkap'] ?: ($need_m3 ? 'MANAGER 3' : 'OPSIONAL')),
        'nama'    => $approver[3]['nama_lengkap'],
        'at'      => $approver[3]['at'],
        'by'      => $approver[3]['by'],
        'img_url' => $ttd_url[3],
        'mode'    => 'digital',
    ],
];
?>
<table class="ttd-section">
    <tr>
    <?php foreach ($ttd_cols as $col): ?>
        <td>
            <div class="ttd-box">
                <div class="ttd-label"><?= htmlspecialchars($col['label']) ?></div>

                <?php if ($col['mode'] === 'manual'): ?>
                    <div class="ttd-img-area">
                        <div class="ttd-empty-box">
                            <span class="ttd-empty-text">tanda tangan</span>
                        </div>
                    </div>
                    <div class="ttd-nama"><?= htmlspecialchars($col['nama']) ?></div>

                <?php elseif (!empty($col['by'])): ?>
                    <div class="ttd-img-area">
                        <?php if ($col['img_url']): ?>
                            <img src="<?= htmlspecialchars($col['img_url']) ?>"
                                 alt="TTD <?= htmlspecialchars($col['nama']) ?>"
                                 class="ttd-img">
                        <?php else: ?>
                            <div class="ttd-empty-box">
                                <span class="ttd-empty-text">ttd tidak tersedia</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="ttd-nama"><?= htmlspecialchars($col['nama']) ?></div>
                    <?php if (!empty($col['at'])): ?>
                        <div class="ttd-tgl"><?= date('d/m/Y H:i', strtotime($col['at'])) ?></div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="ttd-img-area">
                        <div class="ttd-empty-box">
                            <span class="ttd-empty-text">belum disetujui</span>
                        </div>
                    </div>
                    <div class="ttd-menunggu">— Menunggu —</div>
                <?php endif; ?>

            </div>
        </td>
    <?php endforeach; ?>
    </tr>
</table>

</body>
</html>