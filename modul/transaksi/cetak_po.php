<?php
// ============================================================
// cetak_po.php - Versi Flexible (No Null Error)
// ============================================================
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// Fungsi Tanggal Indonesia
function tgl_indo($tanggal){
    $bulan = array (
        1 =>   'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    );
    $pecahkan = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecahkan[2] . ' ' . $bulan[ (int)$pecahkan[1] ] . ' ' . $pecahkan[0];
}

$id_po      = (int)($_GET['id_po'] ?? 0);
$id_request = (int)($_GET['id_request'] ?? 0);
$baru       = $_GET['baru'] ?? 0;

$where = "1=0"; 
if ($id_po > 0) {
    $where = "p.id_po = '$id_po'";
} elseif ($id_request > 0) {
    $where = "p.id_request = '$id_request'";
}

$sql_po = "SELECT p.*, s.nama_supplier, s.alamat, s.kota, s.telp, s.fax, s.email, 
                  s.contact_person, s.atas_nama, s.no_rekening, s.nama_bank, s.atas_nama_rekening
           FROM tr_purchase_order p
           LEFT JOIN master_supplier s ON p.id_supplier = s.id_supplier
           WHERE $where LIMIT 1";

$query_po = mysqli_query($koneksi, $sql_po);
$po_data = mysqli_fetch_assoc($query_po);

$po = $po_data ?: [
    'no_po' => '-',
    'tgl_po' => date('Y-m-d'),
    'nama_supplier' => '-',
    'alamat' => '-',
    'kota' => '-',
    'telp' => '-',
    'fax' => '-',
    'atas_nama' => '-',
    'contact_person' => '-',
    'id_request' => $id_request,
    'subtotal' => 0,
    'diskon' => 0,
    'ppn_persen' => 0,
    'ppn_nominal' => 0,
    'grand_total' => 0,
    'catatan' => '',
    'nama_bank' => '-',
    'no_rekening' => '-',
    'atas_nama_rekening' => '-'
];

$q_pr = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '".$po['id_request']."'");
$pr_data = mysqli_fetch_assoc($q_pr);
$pr = $pr_data ?: ['no_request' => '-'];

$raw_details = mysqli_query($koneksi, "SELECT d.*, b.nama_barang as nama_master
    FROM tr_request_detail d
    LEFT JOIN master_barang b ON d.id_barang = b.id_barang
    WHERE d.id_request = '".$po['id_request']."'
    ORDER BY d.id_detail ASC");

$grouped_details = [];
while($row = mysqli_fetch_assoc($raw_details)) {
    $key = !empty($row['id_barang']) ? $row['id_barang'] : $row['nama_barang_manual'];
    if (isset($grouped_details[$key])) {
        $grouped_details[$key]['jumlah'] += (float)$row['jumlah'];
        $grouped_details[$key]['subtotal_estimasi'] += (float)$row['subtotal_estimasi'];
        if (!empty($row['keterangan']) && strpos($grouped_details[$key]['keterangan'], $row['keterangan']) === false) {
            $grouped_details[$key]['keterangan'] .= ", " . $row['keterangan'];
        }
    } else {
        $grouped_details[$key] = $row;
    }
}

$tgl_po_fmt = (!empty($po['tgl_po']) && $po['tgl_po'] != '-') ? tgl_indo($po['tgl_po']) : '-';

$grand_total = (float)($po['grand_total'] ?? 0);
$diskon       = (float)($po['diskon'] ?? 0);
$dpp_setelah_diskon = $grand_total / 1.11;
$ppn_tampil         = $grand_total - $dpp_setelah_diskon;
$subtotal_tampil    = $dpp_setelah_diskon + $diskon;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PO_<?= $po['no_po'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; color: #000; }
        .action-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 999;
            background: #2c3e50; padding: 10px 25px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .po-wrapper { margin-top: 70px; margin-bottom: 50px; }
        .po-page {
            background: white; width: 210mm; min-height: 297mm;
            margin: 0 auto; padding: 10mm 15mm;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            position: relative;
        }

        .kop-header {
            text-align: center;
            margin-bottom: 10px;
            /* Border dihapus karena sudah ada di gambar PNG */
        }

        .img-kop {
            width: 100%;
            height: auto;
            display: block;
        }

        .po-title { text-align: center; font-size: 16px; font-weight: 800; text-decoration: underline; margin-bottom: 15px; }
        .box-info { border: 1px solid #000; padding: 8px; border-radius: 4px; height: 100%; min-height: 100px; }
        .label-meta { font-weight: bold; width: 90px; display: inline-block; }
        .table-po { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table-po th { background: #f2f2f2 !important; border: 1px solid #000; padding: 6px; text-align: center; text-transform: uppercase; }
        .table-po td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; line-height: 1.4; }
        .ttd-table { width: 100%; margin-top: 30px; }
        .ttd-table td { width: 50%; text-align: center; vertical-align: bottom; }
        .ttd-space { height: 75px; }
        .ttd-name { font-weight: bold; text-decoration: underline; text-transform: uppercase; }

        @media print {
            body { background: white; }
            .action-bar { display: none !important; }
            .po-wrapper { margin-top: 0; }
            .po-page { box-shadow: none; margin: 0; width: 100%; padding: 5mm 10mm; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <div class="title" style="color:white;"><i class="fas fa-file-contract me-2"></i> PURCHASE ORDER: <?= $po['no_po'] ?></div>
    <div class="d-flex gap-2">
        <button onclick="window.close()" class="btn btn-sm btn-outline-light"> Tutup</button>
        <button onclick="window.print()" class="btn btn-sm btn-light fw-bold"> Cetak / PDF</button>
    </div>
</div>

<div class="po-wrapper">
    <div class="po-page">
        
        <div class="kop-header">
            <img src="../../assets/img/kop_surat.png" class="img-kop" alt="Kop Surat PT MCP">
        </div>

        <div class="po-title">PURCHASE ORDER</div>

        <div class="row g-2">
            <div class="col-7">
                <div class="box-info">
                    <strong style="text-decoration: underline;">KEPADA:</strong><br>
                    <span style="font-size: 13px; font-weight: 800;"><?= strtoupper($po['nama_supplier'] ?? '-') ?></span><br>
                    <?= nl2br($po['alamat'] ?? '-') ?><br>
                    <?= strtoupper($po['kota'] ?? '-') ?><br>
                    Telp: <?= $po['telp'] ?: '-' ?> | Fax: <?= $po['fax'] ?: '-' ?><br>
                    <strong>U/p: <?= strtoupper($po['atas_nama'] ?? '-') ?></strong> (<?= $po['contact_person'] ?: '-' ?>)
                </div>
            </div>
            <div class="col-5">
                <div class="box-info">
                    <span class="label-meta">No. PO</span>: <strong><?= $po['no_po'] ?></strong><br>
                    <span class="label-meta">Tanggal</span>: <?= $tgl_po_fmt ?><br>
                    <span class="label-meta">No. Ref PR</span>: <?= $pr['no_request'] ?? '-' ?><br>
                    <span class="label-meta">Halaman</span>: 1 / 1
                </div>
            </div>
        </div>

        <table class="table-po">
            <thead>
                <tr>
                    <th style="width: 30px;">No</th>
                    <th>BARANG</th>
                    <th style="width: 50px;">QTY</th>
                    <th style="width: 60px;">SATUAN</th>
                    <th style="width: 110px;">HARGA</th>
                    <th style="width: 120px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; 
                if(!empty($grouped_details)):
                    foreach($grouped_details as $d): 
                        $nama = !empty($d['nama_master']) ? $d['nama_master'] : ($d['nama_barang_manual'] ?? '-');
                        $harga_item = (float)$d['harga_satuan_estimasi'];
                        $qty        = (float)$d['jumlah'];
                        $sub_item   = $harga_item * $qty;
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td>
                        <strong><?= strtoupper($nama) ?></strong>
                        
                    </td>
                    <td class="text-center"><?= $qty ?></td>
                    <td class="text-center"><?= $d['satuan'] ?? '-' ?></td>
                    <td class="text-end"><?= number_format($harga_item, 0, ',', '.') ?></td>
                    <td class="text-end"><?= number_format($sub_item, 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6" class="text-center">Tidak ada detail barang</td></tr>
                <?php endif; ?>
                
                <tr>
                    <td colspan="4" rowspan="4">
                        <div style="font-size: 10px;">
                            <strong>Keterangan (Payment Term):</strong><br>
                            <?= nl2br($po['catatan'] ?: "1. Pencantuman nama PT. MCP pada faktur dan surat jalan.\n2. Pembayaran Transfer...") ?>
                        </div>
                    </td>
                    <td class="text-end fw-bold">DPP</td>
                    <td class="text-end"><?= number_format($subtotal_tampil, 0, ',', '.') ?></td>
                </tr>
                <tr>
                    <td class="text-end fw-bold">Discount</td>
                    <td class="text-end"><?= $diskon > 0 ? '- '.number_format($diskon, 0, ',', '.') : '0' ?></td>
                </tr>
                <tr>
                    <td class="text-end fw-bold">PPN 11%</td>
                    <td class="text-end"><?= number_format($ppn_tampil, 0, ',', '.') ?></td>
                </tr>
                <tr style="background: #eee;">
                    <td class="text-end fw-bold" style="font-size: 12px;">GRAND TOTAL</td>
                    <td class="text-end fw-bold" style="font-size: 12px;">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                </tr>
            </tbody>
        </table>

        <table class="ttd-table">
            <tr>
                <td>
                    Menyetujui,<br><br>
                    <small>PEMBELI</small>
                    <div class="ttd-space"></div>
                    <div class="ttd-name">....................................</div>
                </td>
                <td>
                    SURABAYA, <?= tgl_indo(date('Y-m-d')) ?><br><br>
                    <small>PENJUAL</small>
                    <div class="ttd-space"></div>
                    <div class="ttd-name">....................................</div>
                </td>
            </tr>
        </table>

        <div style="position: absolute; bottom: 10mm; left: 15mm; right: 15mm; border-top: 1px solid #ccc; padding-top: 5px; font-size: 8px; color: #777;">
            Dicetak otomatis oleh Sistem MCP-PR | Tanggal Cetak: <?= date('d/m/Y H:i') ?>
        </div>
    </div>
</div>

<?php if ($baru): ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    Swal.fire({ icon: 'success', title: 'PO Berhasil Disimpan!', confirmButtonColor: '#2c3e50' });
</script>
<?php endif; ?>
</body>
</html>