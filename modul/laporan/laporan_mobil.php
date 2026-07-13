<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// ============================================================
// GET PARAMETERS & SANITASI
// ============================================================
$tgl_awal    = isset($_GET['tgl_awal'])  ? $_GET['tgl_awal']  : date('Y-m-01');
$tgl_akhir   = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');
$search      = isset($_GET['search'])    ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
// 💡 Cek apakah checkbox "Khusus Tambal Ban" dicentang
$tambal_ban  = isset($_GET['tambal_ban']) ? $_GET['tambal_ban'] : ''; 

$nama_bulan = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];

// Kondisi dinamis untuk filter Tambal Ban
$filter_tambal_beli = ($tambal_ban == '1') ? " AND rd.nama_barang_manual LIKE '%TAMBAL BAN%' " : "";
$filter_tambal_stok = ($tambal_ban == '1') ? " AND mb.nama_barang LIKE '%TAMBAL BAN%' " : "";

// ============================================================
// MEMBANGUN KONDISI SEARCH UNTUK SEMUA FIELD
// ============================================================
$search_condition = "";
if ($search != '') {
    // Escape string untuk keamanan
    $search = mysqli_real_escape_string($koneksi, $search);
    
    // Kondisi pencarian untuk query BELI
    $search_beli = " AND (
        m.driver_tetap LIKE '%$search%' OR 
        m.plat_nomor LIKE '%$search%' OR 
        m.jenis_kendaraan LIKE '%$search%' OR 
        rd.nama_barang_manual LIKE '%$search%' OR 
        rd.satuan LIKE '%$search%' OR 
        CONCAT(rd.jumlah, ' ', rd.satuan) LIKE '%$search%' OR 
        CONCAT(rd.nama_barang_manual, ' (', rd.jumlah, ' ', rd.satuan, ')') LIKE '%$search%' OR 
        p.harga LIKE '%$search%' OR 
        CONCAT('Rp ', FORMAT(p.harga, 0)) LIKE '%$search%'
    ) ";
    
    // Kondisi pencarian untuk query STOK
    $search_stok = " AND (
        m.driver_tetap LIKE '%$search%' OR 
        m.plat_nomor LIKE '%$search%' OR 
        m.jenis_kendaraan LIKE '%$search%' OR 
        mb.nama_barang LIKE '%$search%' OR 
        mb.satuan LIKE '%$search%' OR 
        CONCAT(b.qty_keluar, ' ', mb.satuan) LIKE '%$search%' OR 
        CONCAT('[STOK] ', mb.nama_barang, ' (', b.qty_keluar, ' ', mb.satuan, ')') LIKE '%$search%' OR 
        IFNULL(mb.harga_barang_stok, 0) LIKE '%$search%' OR 
        CONCAT('Rp ', FORMAT(IFNULL(mb.harga_barang_stok, 0), 0)) LIKE '%$search%'
    ) ";
} else {
    $search_beli = "";
    $search_stok = "";
}

// ============================================================
// QUERY GABUNGAN - BELI + STOK
// ============================================================
$query_sql = "SELECT 
                id_transaksi, driver_tetap, plat_nomor, jenis_kendaraan, 
                nama_item, tgl_beli, harga_satuan, total_per_item, 
                kategori, nama_barang_asli
              FROM (
         
                SELECT 
                    rd.id_detail as id_transaksi,
                    m.driver_tetap, 
                    m.plat_nomor, 
                    m.jenis_kendaraan,
                    CONCAT(rd.nama_barang_manual, ' (', rd.jumlah, ' ', rd.satuan, ')') as nama_item,
                    p.tgl_beli_barang as tgl_beli,
                    p.harga as harga_satuan,
                    (rd.jumlah * p.harga) as total_per_item,
                    'BELI' as kategori,
                    rd.nama_barang_manual as nama_barang_asli,
                    rd.jumlah as jumlah,
                    rd.satuan as satuan
                FROM master_mobil m
                    INNER JOIN tr_request_detail rd ON m.id_mobil = rd.id_mobil
                    INNER JOIN tr_request r ON rd.id_request = r.id_request
                    INNER JOIN pembelian p ON p.id_request_detail = rd.id_detail
                        AND REPLACE(p.plat_nomor, ' ', '') = REPLACE(m.plat_nomor, ' ', '')
                WHERE (p.tgl_beli_barang BETWEEN '$tgl_awal' AND '$tgl_akhir')
                $filter_tambal_beli
                $search_beli

                UNION ALL

                SELECT 
                    b.id_bon as id_transaksi,
                    m.driver_tetap, 
                    m.plat_nomor, 
                    m.jenis_kendaraan,
                    CONCAT('[STOK] ', mb.nama_barang, ' (', b.qty_keluar, ' ', mb.satuan, ')') as nama_item,
                    DATE(b.tgl_keluar) as tgl_beli, 
                    IFNULL(mb.harga_barang_stok, 0) as harga_satuan,
                    (b.qty_keluar * IFNULL(mb.harga_barang_stok, 0)) as total_per_item,
                    'STOK' as kategori,
                    mb.nama_barang as nama_barang_asli,
                    b.qty_keluar as jumlah,
                    mb.satuan as satuan
                FROM master_mobil m
                    INNER JOIN bon_permintaan b ON REPLACE(m.plat_nomor, ' ', '') = REPLACE(b.plat_nomor, ' ', '')
                    INNER JOIN master_barang mb ON b.id_barang = mb.id_barang
                WHERE (DATE(b.tgl_keluar) BETWEEN '$tgl_awal' AND '$tgl_akhir')
                $filter_tambal_stok
                $search_stok
              ) AS gabungan
              ORDER BY driver_tetap ASC, plat_nomor ASC, tgl_beli ASC, kategori DESC";

// ============================================================
// EXECUTE QUERY & FETCH DATA
// ============================================================
$result = mysqli_query($koneksi, $query_sql);

if (!$result) {
    die("Query Error: " . mysqli_error($koneksi));
}

// ============================================================
// GROUPING DATA BY DRIVER -> PLAT NOMOR
// ============================================================
$data_by_driver = [];
while ($row = mysqli_fetch_assoc($result)) {
    $driver_key = ($row['driver_tetap'] != '' && $row['driver_tetap'] != null && $row['driver_tetap'] != '-') 
                  ? $row['driver_tetap'] 
                  : 'TANPA DRIVER';
    $plat_key = $row['plat_nomor'];
    
    if (!isset($data_by_driver[$driver_key])) { 
        $data_by_driver[$driver_key] = []; 
    }
    
    if (!isset($data_by_driver[$driver_key][$plat_key])) {
        $data_by_driver[$driver_key][$plat_key] = [
            'driver' => $row['driver_tetap'],
            'jenis'  => $row['jenis_kendaraan'],
            'items'  => []
        ];
    }
    
    $data_by_driver[$driver_key][$plat_key]['items'][] = $row;
}

// Sort: TANPA DRIVER paling belakang, sisanya alphabetical
uksort($data_by_driver, function($a, $b) {
    if ($a === 'TANPA DRIVER') return 1;
    if ($b === 'TANPA DRIVER') return -1;
    return strcmp($a, $b);
});

// ============================================================
// HELPER FUNCTION: CEK ITEM DUPLIKAT
// ============================================================
function isDuplicateItem($koneksi, $nama_barang, $plat_nomor) {
    $nama_barang = mysqli_real_escape_string($koneksi, $nama_barang);
    $plat_nomor  = mysqli_real_escape_string($koneksi, $plat_nomor);
    
    $query = "SELECT COUNT(*) as total FROM (
                SELECT rd.nama_barang_manual as nama 
                FROM tr_request_detail rd
                INNER JOIN master_mobil m ON rd.id_mobil = m.id_mobil
                WHERE rd.nama_barang_manual = '$nama_barang' 
                  AND m.plat_nomor = '$plat_nomor'
                
                UNION ALL
                
                SELECT mb.nama_barang as nama 
                FROM bon_permintaan b
                INNER JOIN master_barang mb ON b.id_barang = mb.id_barang
                WHERE mb.nama_barang = '$nama_barang' 
                  AND b.plat_nomor = '$plat_nomor'
            ) AS gabung";
    
    $res = mysqli_query($koneksi, $query);
    
    if (!$res) {
        error_log("isDuplicateItem Query Error: " . mysqli_error($koneksi));
        return false;
    }
    
    $data = mysqli_fetch_assoc($res);
    return ($data['total'] > 1);
}

// ============================================================
// HELPER FUNCTION: AMBIL TANGGAL PEMBELIAN TERAKHIR
// ============================================================
function getLastPurchaseDate($koneksi, $nama_barang, $tgl_sekarang, $plat_nomor) {
    $nama_barang  = mysqli_real_escape_string($koneksi, $nama_barang);
    $tgl_sekarang = mysqli_real_escape_string($koneksi, $tgl_sekarang);
    $plat_nomor   = mysqli_real_escape_string($koneksi, $plat_nomor);
    
    $query = "SELECT MAX(tgl_beli) as tgl_terakhir FROM (
                SELECT IFNULL(p.tgl_beli_barang, r.tgl_request) as tgl_beli
                FROM tr_request_detail rd
                INNER JOIN tr_request r ON rd.id_request = r.id_request
                LEFT JOIN pembelian p ON rd.id_request = p.id_request 
                    AND rd.nama_barang_manual = p.nama_barang_beli
                WHERE rd.nama_barang_manual = '$nama_barang'
                  AND rd.id_mobil = (SELECT id_mobil FROM master_mobil 
                                     WHERE plat_nomor = '$plat_nomor' LIMIT 1)
                  AND IFNULL(p.tgl_beli_barang, r.tgl_request) < '$tgl_sekarang'
                
                UNION ALL
                
                SELECT DATE(b.tgl_keluar) as tgl_beli
                FROM bon_permintaan b
                INNER JOIN master_barang mb ON b.id_barang = mb.id_barang
                WHERE mb.nama_barang = '$nama_barang'
                  AND b.plat_nomor = '$plat_nomor'
                  AND DATE(b.tgl_keluar) < '$tgl_sekarang'
            ) AS history";
    
    $res = mysqli_query($koneksi, $query);
    
    if (!$res) {
        error_log("getLastPurchaseDate Query Error: " . mysqli_error($koneksi));
        return null;
    }
    
    $row = mysqli_fetch_assoc($res);
    return $row['tgl_terakhir'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <title>Laporan Rincian Mobil - MCP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
        }
        .wrap-laporan {
            width: 100%;
            background: white;
            padding: 15px 20px;
        }
        .table-laporan {
            width: 100%;
            border-collapse: collapse !important;
            background: white;
            table-layout: fixed;
        }
        .table-laporan col.c-no       { width: 4%; }
        .table-laporan col.c-kendaraan { width: 15%; }
        .table-laporan col.c-barang   { width: 31%; }
        .table-laporan col.c-tglbeli  { width: 8%; }
        .table-laporan col.c-tgllast  { width: 10%; }
        .table-laporan col.c-harga    { width: 14%; }
        .table-laporan col.c-subtotal { width: 14%; }
        .table-laporan col.c-aksi     { width: 4%; }

        .table-laporan th,
        .table-laporan td {
            border: 1px solid #000 !important;
            padding: 4px 5px;
            vertical-align: middle;
            color: #000 !important;
            word-wrap: break-word;
            overflow-wrap: break-word;
            overflow: hidden;
        }
        .table-laporan th {
            background-color: #e8e8e8 !important;
            text-align: center;
            font-size: 8.5pt;
            font-weight: bold;
        }
        .table-laporan td {
            font-size: 8.5pt;
            line-height: 1.3;
        }
        .badge-stok {
            color: #198754;
            font-weight: bold;
            border: 1px solid #198754;
            padding: 0px 3px;
            border-radius: 3px;
            font-size: 7pt;
            white-space: nowrap;
        }
        .badge-jenis {
            background-color: #333;
            color: #fff;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 6.5pt;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 2px;
        }
        .baris-total {
            background-color: #343a40 !important;
            color: #fff !important;
        }
        .baris-total td {
            color: #fff !important;
            font-size: 8.5pt;
        }
        .badge-periode {
            background-color: #000;
            color: #fff;
            padding: 0px 4px;
            border-radius: 3px;
            font-size: 6.5pt;
            font-weight: bold;
            display: inline-block;
            margin-top: 1px;
        }
        .driver-header {
            background-color: #d0d7de !important;
            font-weight: bold;
            font-size: 8.5pt;
        }
        .subtotal-row {
            background-color: #f5f5f5 !important;
            font-size: 8.5pt;
        }
        .highlight-pernah {
            background-color: #ffff99 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .judul-laporan {
            font-size: 13pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 3px;
        }
        .periode-laporan {
            font-size: 9pt;
            text-align: center;
            margin-bottom: 8px;
        }
        .info-legenda {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 7.5pt;
            display: inline-block;
            background: #ffff99;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            margin-top: 6px;
        }

        @media print {
            @page {
                size: 210mm 330mm portrait;
                margin-top: 10mm; margin-bottom: 10mm; margin-left: 12mm; margin-right: 10mm;
            }
            html, body {
                width: 210mm;
                background: white !important;
                font-size: 8pt;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .wrap-laporan {
                max-width: 100%; width: 100%; padding: 0; box-shadow: none; margin: 0;
            }
            .no-print { display: none !important; }
            .table-laporan th, .table-laporan td {
                font-size: 7.5pt !important; padding: 3px 4px !important; line-height: 1.25 !important;
            }
            .driver-header td { font-size: 8pt !important; }
            .baris-total td { font-size: 8pt !important; }
            .judul-laporan { font-size: 11pt; }
            .periode-laporan { font-size: 8.5pt; }
            .highlight-pernah { background-color: #ffff99 !important; }
            .driver-header { background-color: #d0d7de !important; }
            .baris-total { background-color: #343a40 !important; }
            .baris-total td { color: #fff !important; }
            .badge-jenis { background-color: #333 !important; color: #fff !important; }
            .info-legenda { background: #ffff99 !important; }
            tr { page-break-inside: avoid; }
            .driver-header { page-break-before: auto; page-break-after: avoid; }
            .ttd-block { display: block !important; }
        }
        .ttd-block { display: none; }
    </style>
</head>
<body>

<div class="container-fluid py-2 no-print">
    <div class="card mb-3 shadow-sm border-0">
        <div class="card-body bg-light p-2">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="small fw-bold">Periode</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="tgl_awal"  class="form-control" value="<?= $tgl_awal ?>">
                        <span class="input-group-text">s/d</span>
                        <input type="date" name="tgl_akhir" class="form-control" value="<?= $tgl_akhir ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Cari (semua field)</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Plat / Driver / Barang / Harga / dll...">
                </div>
                
                <div class="col-md-2 mb-1">
                    <div class="form-check form-switch small">
                        <input class="form-check-input" type="checkbox" name="tambal_ban" id="checkTambal" value="1" <?= ($tambal_ban == '1') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold text-danger" for="checkTambal">Khusus Tambal Ban</label>
                    </div>
                </div>

                <div class="col-md-4 d-flex gap-2 align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-search me-1"></i>Tampilkan
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-dark">
                        <i class="fas fa-print me-1"></i>Cetak Laporan
                    </button>
                    <a href="../../index.php" class="btn btn-sm btn-danger">
                        <i class="fas fa-arrow-left me-1"></i>Kembali
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="wrap-laporan" id="area-cetak">

    <div class="judul-laporan">Laporan Kendaraan <?= ($tambal_ban == '1') ? '(Khusus Tambal Ban)' : '' ?> PT. MCP</div>
    <div class="periode-laporan">
        Periode: <?= date('d/m/Y', strtotime($tgl_awal)) ?> s/d <?= date('d/m/Y', strtotime($tgl_akhir)) ?>
        <?php if ($search != ''): ?>
            <br><span class="text-muted">Pencarian: "<?= htmlspecialchars($search) ?>"</span>
        <?php endif; ?>
    </div>

    <table class="table-laporan">
        <colgroup>
            <col class="c-no">
            <col class="c-kendaraan">
            <col class="c-barang">
            <col class="c-tglbeli">
            <col class="c-tgllast">
            <col class="c-harga">
            <col class="c-subtotal">
        </colgroup>
        <thead>
            <tr>
                <th>NO</th>
                <th>KENDARAAN / DRIVER</th>
                <th>NAMA BARANG / ITEM</th>
                <th>TGL BELI</th>
                <th>TGL TERAKHIR</th>
                <th>HARGA SATUAN</th>
                <th>SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 0; $grand_total = 0;
            if(!empty($data_by_driver)) :
                foreach ($data_by_driver as $driver_name => $data_mobil) :
            ?>
                    <tr class="driver-header">
                        <td colspan="7" class="ps-2">
                            <i class="fas fa-user me-1"></i>
                            DRIVER: <?= ($driver_name === 'TANPA DRIVER') ? '-' : strtoupper($driver_name) ?>
                        </td>
                    </tr>

                    <?php
                    foreach ($data_mobil as $plat => $m) :
                        $sub_total_mobil = 0;
                        $rowspan = count($m['items']);
                        foreach ($m['items'] as $index => $item) :
                            $sub_total_mobil += $item['total_per_item'];
                            $grand_total     += $item['total_per_item'];

                            $is_duplikat     = isDuplicateItem($koneksi, $item['nama_barang_asli'], $plat);
                            $class_highlight = $is_duplikat ? 'highlight-pernah' : '';

                            $tgl_terakhir_db = getLastPurchaseDate($koneksi, $item['nama_barang_asli'], $item['tgl_beli'], $plat);
                            $tgl_tampil  = '-';
                            $badge_periode = '';
                            if ($tgl_terakhir_db) {
                                $tgl_tampil    = date('d/m/y', strtotime($tgl_terakhir_db));
                                $diff          = (new DateTime($item['tgl_beli']))->diff(new DateTime($tgl_terakhir_db))->days;
                                $badge_periode = '<br><span class="badge-periode">' . $diff . ' Hari</span>';
                            }
                    ?>
                        <tr class="<?= $class_highlight ?>">
                            <?php if ($index === 0) : ?>
                                <td rowspan="<?= $rowspan ?>" class="text-center fw-bold align-middle">
                                    <?= ++$no ?>
                                </td>
                                <td rowspan="<?= $rowspan ?>" class="align-top" style="font-size:7.5pt;">
                                    <span class="badge-jenis"><?= htmlspecialchars($m['jenis']) ?></span><br>
                                    <b><?= htmlspecialchars($plat) ?></b><br>
                                    <span class="text-muted" style="font-size:7pt;"><?= htmlspecialchars($m['driver']) ?></span>
                                </td>
                            <?php endif; ?>

                            <td style="font-size:7.5pt;">
                                <?php 
                                    $tampil_item = htmlspecialchars($item['nama_item']);
                                    $tampil_item = str_replace('[STOK]', '<span class="badge-stok">STOK</span>', $tampil_item);
                                    
                                    $tampil_item = preg_replace_callback('/(\d+\.\d+)/', function($m) {
                                        return rtrim(rtrim($m[1], '0'), '.');
                                    }, $tampil_item);

                                    echo $tampil_item;
                                ?>
                            </td>

                            <td class="text-center" style="font-size:7.5pt; white-space:nowrap;">
                                <span class="no-print">
                                    <a href="javascript:void(0)" class="text-decoration-none"
                                       onclick="editTanggal('<?= $item['id_transaksi'] ?>','<?= $item['kategori'] ?>','<?= $item['tgl_beli'] ?>')">
                                        <?= date('d/m/y', strtotime($item['tgl_beli'])) ?>
                                    </a>
                                </span>
                                <span class="d-none d-print-inline">
                                    <?= date('d/m/y', strtotime($item['tgl_beli'])) ?>
                                </span>
                            </td>

                            <td class="text-center" style="font-size:7.5pt;">
                                <?= $tgl_tampil . $badge_periode ?>
                            </td>

                            <td class="text-end" style="font-size:7.5pt; white-space:nowrap;">
                                Rp <?= number_format($item['harga_satuan'], 0, ',', '.') ?>
                            </td>

                            <td class="text-end fw-bold" style="font-size:7.5pt; white-space:nowrap;">
                                Rp <?= number_format($item['total_per_item'], 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="subtotal-row">
                        <td colspan="6" class="text-end small fw-bold" style="font-size:7.5pt;">
                            SUBTOTAL <?= htmlspecialchars($plat) ?> :
                        </td>
                        <td class="text-end fw-bold" style="font-size:7.5pt; white-space:nowrap;">
                            Rp <?= number_format($sub_total_mobil, 0, ',', '.') ?>
                        </td>
                    </tr>

            <?php endforeach; endforeach; 
            else: ?>
                <tr>
                    <td colspan="7" class="text-center py-3 text-muted">Tidak ada data transaksi yang ditemukan.</td>
                </tr>
            <?php endif; ?>

            <tr class="baris-total">
                <td colspan="6" class="text-end fw-bold">TOTAL KESELURUHAN :</td>
                <td class="text-end fw-bold" style="white-space:nowrap;">
                    Rp <?= number_format($grand_total, 0, ',', '.') ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="row mt-2">
        <div class="col-7">
            <div class="info-legenda">
                <strong>INFO:</strong> Baris kuning = barang/jasa yang pernah dibeli sebelumnya (pengulangan).
            </div>
        </div>
        <div class="col-5 text-end ttd-block" style="font-size: 8.5pt; font-family: 'Times New Roman', serif;">
            Surabaya, <?= date('d') ?> <?= $nama_bulan[date('m')] ?> <?= date('Y') ?><br><br><br><br>
            <strong>( ____________________ )</strong><br>Manager
        </div>
    </div>

</div>

<div class="modal fade no-print" id="modalEditTgl" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form action="update_tgl_laporan.php" method="POST" class="modal-content">
            <div class="modal-header p-2">
                <h6 class="mb-0">Edit Tanggal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-2">
                <input type="hidden" name="id"  id="edit_id">
                <input type="hidden" name="kat" id="edit_kat">
                <input type="date"   name="tgl_baru" id="edit_tgl" class="form-control form-control-sm">
            </div>
            <div class="modal-footer p-1">
                <button type="submit" class="btn btn-sm btn-primary w-100">Update</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editTanggal(id, kat, tgl) {
    document.getElementById('edit_id').value  = id;
    document.getElementById('edit_kat').value = kat;
    document.getElementById('edit_tgl').value = tgl;
    new bootstrap.Modal(document.getElementById('modalEditTgl')).show();
}
</script>
</body>
</html>