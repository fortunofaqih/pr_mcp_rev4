<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Menangkap filter dari URL, jika tidak ada gunakan bulan & tahun berjalan
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$nama_bulan = date("F", mktime(0, 0, 0, $bulan, 10));

/**
 * Fungsi untuk membersihkan tampilan angka desimal
 * Contoh: 2.0000 -> 2 | 0.5000 -> 0,5 | 0.0110 -> 0,011
 */
function formatAngka($angka) {
    if ($angka == 0) return '0';
    $fmt = number_format($angka, 4, ',', '.');
    return rtrim(rtrim($fmt, '0'), ',');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pembelian Detail - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            @page { size: landscape; margin: 1cm; }
            .d-print-none { display: none !important; }
            table tfoot { display: table-row-group; } 
            tr { page-break-inside: avoid; }
            body { background-color: white !important; padding: 0 !important; }
            .container { width: 100% !important; max-width: 100% !important; box-shadow: none !important; margin: 0 !important; }
        }
        
        .tanda-tangan {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            text-align: center;
        }
        .ttd-box { width: 300px; }
        input, select { text-transform: uppercase; }
        .table th { vertical-align: middle; background-color: #212529 !important; color: white !important; }
        .text-italic { font-style: italic; }
    </style>
</head>
<body class="bg-light p-4">

<div class="container bg-white p-4 shadow-sm rounded">
    <div class="d-print-none mb-4 border-bottom pb-3">
        <form action="" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="small fw-bold">PILIH BULAN</label>
                <select name="bulan" class="form-select form-select-sm">
                    <?php
                    for($m=1; $m<=12; $m++){
                        $active = ($m == $bulan) ? 'selected' : '';
                        echo "<option value='$m' $active>".date('F', mktime(0,0,0,$m,10))."</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">TAHUN</label>
                <input type="number" name="tahun" class="form-control form-control-sm" value="<?= $tahun ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-dark w-100"><i class="fas fa-filter"></i> SORTIR</button>
            </div>
            <div class="col-md-5 text-end">
                <a href="../../index.php" class="btn btn-sm btn-danger">KEMBALI</a>
                <button onclick="window.print()" class="btn btn-sm btn-primary">
                    <i class="fas fa-print"></i> CETAK LAPORAN
                </button>
            </div>
        </form>
    </div>

    <div class="text-center mb-4">
        <h4 class="fw-bold mb-0">LAPORAN DETAIL REALISASI PEMBELIAN</h4>
        <p class="text-muted">Periode: <?= strtoupper($nama_bulan)." ".$tahun ?></p>
    </div>
    
    <div class="table-responsive">
        <table class="table table-bordered table-sm" style="font-size: 0.72rem;">
            <thead>
                <tr class="text-center text-uppercase">
                    <th width="8%">No. PR</th>
                    <th width="8%">Tgl Beli</th>
                    <th width="12%">Admin</th>
                    <th width="10%">Unit/Plat</th> 
                    <th>Nama Barang</th>
                    <th width="6%">Qty</th>
                    <th width="10%">Supplier</th>
                    <th width="9%">Total</th>
                    <th width="10%">Alokasi</th> 
                    <th width="12%">Notes</th> 
                </tr>
            </thead>
            <tbody>
                <?php
                $total_akhir = 0;
                $query = "SELECT p.*, r.no_request, r.nama_pemesan as pemesan_asli 
                          FROM pembelian p 
                          LEFT JOIN tr_request r ON p.id_request = r.id_request 
                          WHERE MONTH(p.tgl_beli_barang)='$bulan' AND YEAR(p.tgl_beli_barang)='$tahun'
                          ORDER BY p.tgl_beli_barang ASC";
                
                $q = mysqli_query($koneksi, $query);
                
                if(mysqli_num_rows($q) > 0) {
                    while($d = mysqli_fetch_array($q)){
                        // Gunakan float untuk akurasi perkalian desimal
                        $qty_val = (float)$d['qty'];
                        $harga_val = (float)$d['harga'];
                        $subtotal = $qty_val * $harga_val;
                        $total_akhir += $subtotal;
                        
                        // Logika Nama Pemesan
                        $nama_tampil = (!empty($d['no_request'])) ? $d['pemesan_asli'] : $d['nama_pemesan'];
                        ?>
                        <tr>
                            <td class="text-center"><?= $d['no_request'] ?: '-' ?></td>
                            <td class="text-center"><?= date('d-m-Y', strtotime($d['tgl_beli_barang'])) ?></td>
                            <td><?= strtoupper($nama_tampil) ?></td>
                            <td class="text-center"><?= strtoupper($d['plat_nomor'] ?: '-') ?></td>
                            <td><?= strtoupper($d['nama_barang_beli']) ?></td>
                            
                            <td class="text-center"><?= formatAngka($d['qty']) ?></td>
                            
                            <td><?= strtoupper($d['supplier']) ?></td>
                            
                            <td class="text-end fw-bold"><?= number_format($subtotal, 0, ',', '.') ?></td>
                            
                            <td class="text-center small"><?= strtoupper($d['alokasi_stok'] ?? '-') ?></td>
                            <td class="text-muted text-italic"><?= $d['keterangan'] ?: '-' ?></td>
                        </tr>
                        <?php 
                    } 
                } else {
                    echo "<tr><td colspan='10' class='text-center py-4'>Tidak ada data pada periode ini.</td></tr>";
                }
                ?>
            </tbody>
            <tfoot> 
                <tr class="fw-bold table-secondary">
                    <td colspan="7" class="text-end py-2">TOTAL PENGELUARAN :</td>
                    <td class="text-end text-danger py-2">Rp <?= number_format($total_akhir, 0, ',', '.') ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="d-none d-print-block">
        <div class="tanda-tangan">
            <div class="ttd-box">
                <p class="mb-5">Dibuat Oleh,</p>
                <p class="fw-bold mb-0">( ............................ )</p>
                <p class="small">Admin Pembelian</p>
            </div>
            <div class="ttd-box">
                <p class="mb-5">Disetujui Oleh,</p>
                <p class="fw-bold mb-0">( ............................ )</p>
                <p class="small">Manager / Pimpinan</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>