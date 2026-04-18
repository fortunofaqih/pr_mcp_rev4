<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// ── 1. TERIMA PARAMETER: bisa ?id=123 ATAU ?no=PRB/IV/26/0002 ────────────────
$h = null;

if (!empty($_GET['id'])) {
    $id_raw = (int)$_GET['id'];
    $h = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT * FROM tr_request WHERE id_request = '$id_raw' AND no_request LIKE 'PRB%' LIMIT 1"));

} elseif (!empty($_GET['no'])) {
    $no_raw = mysqli_real_escape_string($koneksi, trim($_GET['no']));
    $h = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT * FROM tr_request WHERE no_request = '$no_raw' AND no_request LIKE 'PRB%' LIMIT 1"));
}

if (!$h) {
    die("<div style='font-family:Arial;padding:30px;color:red;'>
            <b>Data tidak ditemukan.</b><br>
            Pastikan parameter <code>?id=</code> atau <code>?no=PRB/...</code> sudah benar,
            dan nomor request dimulai dengan <b>PRB</b>.
         </div>");
}

$id = (int)$h['id_request'];

// ── 2. GENERATE no_form jika belum ada ───────────────────────────────────────
if (empty($h['no_form'])) {
    $bulan  = date('m');
    $tahun  = date('Y');
    $suffix = substr($h['no_request'], -4);
    $no_form = "PRB-MCP-$suffix/$bulan/$tahun";
    mysqli_query($koneksi, "UPDATE tr_request SET no_form = '$no_form' WHERE id_request = '$id'");
    $h['no_form'] = $no_form;
}

// ── 3. AMBIL DETAIL BARANG ────────────────────────────────────────────────────
$items = [];
$q = mysqli_query($koneksi,
    "SELECT d.*, 
            m.plat_nomor, 
            b.nama_barang as nama_barang_master,
            p.tgl_beli_barang
     FROM tr_request_detail d
     LEFT JOIN master_mobil  m ON d.id_mobil  = m.id_mobil
     LEFT JOIN master_barang b ON d.id_barang = b.id_barang
     LEFT JOIN pembelian     p ON p.id_request_detail = d.id_detail
     WHERE d.id_request = '$id'
     ORDER BY d.id_detail ASC");
while ($d = mysqli_fetch_assoc($q)) { $items[] = $d; }

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
// Letakkan file TTD di: /pr_mcp/assets/ttd/{username}.png (atau .jpg/.jpeg)
// Contoh: /pr_mcp/assets/ttd/budi.png  → untuk user dengan username "budi"
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak PR Besar - <?= htmlspecialchars($h['no_request']) ?></title>
    <style>
        @page { size: 21.5cm 16.5cm landscape; margin: 0.5cm 0.7cm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 8pt; margin: 0; padding: 8px; background: #fff; color: #000; }

        /* ── Header ── */
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
        .header h2 { margin: 0; font-size: 11pt; font-weight: bold; }
        .header h4 { margin: 0; font-size: 8pt; font-weight: normal; }

        /* ── Badge PR Besar ── */
        .badge-besar {
            display: inline-block;
            background: #1e3a8a;
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

        /* ── Info PR ── */
        .info-pr { width: 100%; margin-bottom: 5px; font-size: 7.5pt; font-weight: bold; border-collapse: collapse; }

        /* ── Tabel Data ── */
        table.data { width: 100%; border-collapse: collapse; font-size: 7.5pt; table-layout: fixed; }
        table.data th {
            background-color: #1e3a8a !important;
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

        /* ── Lebar Kolom ── */
        .c-no   { width: 22px; text-align: center; }
        .c-tgl  { width: 60px; text-align: center; }
        .c-unit { width: 70px; text-align: center; }
        .c-tipe { width: 60px; text-align: center; }
        .c-qty  { width: 60px; text-align: center; }
        .c-ket  { }
        .c-ttd  { width: 50px; text-align: center; }

        /* ── Blok TTD di bawah tabel ── */
        .ttd-section {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .ttd-section td {
            width: 33.33%;
            text-align: center;
            vertical-align: top;
            padding: 0 8px;
        }
        .ttd-box {
            border: 0.5px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px 6px 5px;
            min-height: 85px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .ttd-label {
            font-size: 6.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #1e3a8a;
            margin-bottom: 4px;
            letter-spacing: .04em;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        /* Area gambar TTD — area putih tempat gambar muncul */
        .ttd-img-area {
            width: 110px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 3px;
        }
        .ttd-img {
            max-width: 110px;
            max-height: 52px;
            object-fit: contain;
        }
        /* Kotak kosong — pemesan atau belum ada file TTD */
        .ttd-empty-box {
            width: 110px;
            height: 52px;
            border: 0.5px dashed #94a3b8;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ttd-empty-text { font-size: 5.5pt; color: #94a3b8; font-style: italic; }
        .ttd-nama {
            font-size: 7pt;
            font-weight: bold;
            color: #1e293b;
            margin-top: 3px;
            text-align: center;
            line-height: 1.3;
        }
        .ttd-tgl { font-size: 5.5pt; color: #64748b; margin-top: 1px; }
        .ttd-menunggu { font-size: 6pt; color: #94a3b8; font-style: italic; margin-top: 3px; }

        /* ── Watermark ── */
        .wm-pending {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-35deg);
            font-size: 52pt; font-weight: 900; color: rgba(220,0,0,.06);
            pointer-events: none; z-index: 0; letter-spacing: 4px;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }

        /* ── Catatan bawah ── */
        .ttd-note {
            margin-top: 5px; font-size: 6pt; color: #64748b;
            border-top: 0.5px dashed #aaa; padding-top: 3px;
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

<!-- ── Tombol Cetak (tidak ikut print) ── -->
<div class="no-print" style="background:#dbeafe; padding:8px; margin-bottom:10px; border:1px solid #93c5fd; border-radius:4px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
    <button onclick="window.print()" style="padding:7px 18px; background:#1e3a8a; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
        🖨️ CETAK PR BESAR
    </button>
    <span style="font-size:11px; color:#555;">Kertas: F4 / Setengah Folio (21.5 × 16.5 cm Landscape)</span>
    <span style="font-size:10px; color:#475569;">
        📁 Folder TTD: <code style="background:#e2e8f0; padding:1px 5px; border-radius:3px;">../../assets/ttd/{username}.png</code>
    </span>
    <span style="font-size:10px; font-weight:bold; margin-left:auto; color:<?= $fully_approved ? '#16a34a' : '#dc2626' ?>;">
        Status: <?= htmlspecialchars($h['status_approval']) ?>
    </span>
</div>

<!-- ── Header ── -->
<div class="header">
    <h2>PURCHASE REQUEST (PR) BESAR</h2>
    <h4>PT. Mutiara Cahaya Plastindo</h4>
</div>

<!-- ── Info PR ── -->
<table class="info-pr">
    <tr>
        <td width="40%">NO: <b><?= htmlspecialchars($h['no_request']) ?></b> &nbsp;|&nbsp; FORM: <?= htmlspecialchars($h['no_form']) ?></td>
        <td width="30%" style="text-align:center;">ADMIN: <?= strtoupper(htmlspecialchars($h['nama_pemesan'])) ?></td>
        <td width="30%" style="text-align:right;">TGL: <?= date('d/m/Y', strtotime($h['tgl_request'])) ?></td>
    </tr>
    <?php if (!empty($h['keterangan'])): ?>
    <tr>
        <td colspan="3" style="font-weight:normal; font-size:7pt; padding-top:2px;">
            Keperluan: <b><?= nl2br(htmlspecialchars($h['keterangan'])) ?></b>
        </td>
    </tr>
    <?php endif; ?>
</table>

<!-- ── Tabel Barang ── -->
<table class="data">
    <thead>
        <tr>
            <th class="c-no">NO</th>
            <th>NAMA BARANG / ITEM</th>
            <th class="c-tgl">TGL BELI</th>
            <th class="c-unit">UNIT / MOBIL</th>
            <th class="c-tipe">TIPE</th>
            <th class="c-qty">QTY</th>
            <th class="c-ket">KETERANGAN</th>
            
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $i => $d):
            $nama = !empty($d['nama_barang_manual']) ? $d['nama_barang_manual'] : $d['nama_barang_master'];
        ?>
        <tr>
            <td style="text-align:center;"><?= $i + 1 ?></td>
            <td style="font-weight:bold;"><?= strtoupper(htmlspecialchars($nama)) ?></td>
          
			<td style="text-align:center;">
			<?php if (!empty($d['tgl_beli_barang']) && $d['tgl_beli_barang'] != '0000-00-00'): ?>
				<span style="font-weight:bold; color:#166534;">
					<?= date('d/m/y', strtotime($d['tgl_beli_barang'])) ?>
				</span>
			<?php else: ?>
				<span style="color:#cbd5e1;">—</span>
			<?php endif; ?>
		</td>
            <td style="text-align:center;">
                <?= ($d['id_mobil'] != 0 && !empty($d['plat_nomor'])) ? htmlspecialchars($d['plat_nomor']) : '-' ?>
            </td>
            <td style="text-align:center; font-size:6.5pt; font-weight:bold;"><?= htmlspecialchars($d['tipe_request']) ?></td>
            <td style="text-align:center;"><b><?= (float)$d['jumlah'] ?></b> <?= htmlspecialchars($d['satuan']) ?></td>
            <td style="font-size:7pt;"><?= htmlspecialchars($d['keterangan'] ?: '-') ?></td>
           
        </tr>
        <?php endforeach; ?>

        <?php for ($x = count($items); $x < 3; $x++): ?>
       
        <?php endfor; ?>
    </tbody>
</table>

<!-- ══════════════════════════════════════════════════════════════
     BLOK TANDA TANGAN DI BAWAH TABEL
     Kolom 1 : Manager 1       
     Kolom 2 : Manager 2 
     Kolom 3 : Manager 3 
════════════════════════════════════════════════════════════════ -->
<?php
$ttd_cols = [
    [
        'label'   => 'Approval 1' ,
        'nama'    => $approver[1]['nama_lengkap'],
        'at'      => $approver[1]['at'],
        'by'      => $approver[1]['by'],
        'img_url' => $ttd_url[1],
        'mode'    => 'digital',
    ],
    [
        'label'   => 'Approval 2' ,
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

                <!-- Label kolom (TTD 1, TTD 2, TTD 3) -->
                <div class="ttd-label"><?= htmlspecialchars($col['label']) ?></div>

                <?php if ($col['mode'] === 'manual'): ?>
                    <!-- Pemesan: kotak kosong untuk TTD tangan -->
                    <div class="ttd-img-area">
                        <div class="ttd-empty-box">
                            <span class="ttd-empty-text">tanda tangan</span>
                        </div>
                    </div>
                    <div class="ttd-nama"><?= htmlspecialchars($col['nama']) ?></div>

                <?php elseif (!empty($col['by'])): ?>
                    <!-- Sudah ada approver -->
                    <div class="ttd-img-area">
                        <?php if ($col['img_url']): ?>
                            <!-- Ada file gambar TTD -->
                            <img src="<?= htmlspecialchars($col['img_url']) ?>"
                                 alt="TTD <?= htmlspecialchars($col['nama']) ?>"
                                 class="ttd-img">
                        <?php else: ?>
                            <!-- Approve sudah ada tapi file gambar TTD belum diupload -->
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
                    <!-- Belum ada yang approve pada level ini -->
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