<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAPORAN BARANG BESAR - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; font-size: 0.9rem; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .table thead { background-color: #f8f9fa; color: #333; }
        
        @media print {
            @page { size: landscape; margin: 0.5cm; }
            .no-print, .btn, .dataTables_filter, .dataTables_info, .dataTables_paginate { display: none !important; }
            body { background-color: white; margin: 0; padding: 0; color: #000; }
            th, td { border: 1px solid #000 !important; font-size: 8.5pt !important; padding: 4px !important; }
            .print-header { display: block !important; text-align: center; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h4 class="fw-bold text-primary mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>REKAP PEMBELIAN BARANG BESAR</h4>
        <div>
            <a href="../../index.php" class="btn btn-danger btn-sm shadow-sm"><i class="fas fa-rotate-left"></i> Kembali</a>
            <button onclick="window.print()" class="btn btn-dark btn-sm shadow-sm"><i class="fas fa-print me-1"></i> Cetak Printer</button>
        </div>
    </div>

    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">DARI TANGGAL</label>
                    <input type="date" name="tgl_awal" class="form-control" value="<?= $tgl_awal ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">SAMPAI TANGGAL</label>
                    <input type="date" name="tgl_akhir" class="form-control" value="<?= $tgl_akhir ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="print-header">
                <h4 class="fw-bold">LAPORAN REALISASI BARANG BESAR</h4>
                <h5 class="fw-bold">PT. MUTIARACAHAYA PLASTINDO</h5>
                <p>Periode: <?= date('d/m/Y', strtotime($tgl_awal)) ?> s/d <?= date('d/m/Y', strtotime($tgl_akhir)) ?></p>
                <hr style="border: 1px solid #000;">
            </div>

            <div class="table-responsive">
                <table id="tabelLaporan" class="table table-bordered table-striped w-100">
                    <thead class="table-light text-center small">
                        <tr>
                            <th>NO</th>
                            <th>TANGGAL</th>
                            <th>NO. PR</th>
                            <th>NAMA BARANG</th>
                            <th>SUPPLIER</th>
                            <th>QTY</th>
                            <th>HARGA</th>
                            <th>TOTAL</th>
                            <th>PEMESAN</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php
                        $no = 1;
                        $grand_total = 0;
                        
                        // GANTI QUERY LAMA DENGAN INI
                        $query = "SELECT p.*, r.kategori_pr 
                                FROM pembelian p
                                INNER JOIN tr_request r ON p.id_request = r.id_request
                                WHERE r.kategori_pr = 'BESAR' 
                                AND p.tgl_beli BETWEEN '$tgl_awal' AND '$tgl_akhir'
                                ORDER BY p.tgl_beli DESC";
                        
                        $sql = mysqli_query($koneksi, $query);
                        while ($d = mysqli_fetch_array($sql)) {
                            $subtotal = $d['qty'] * $d['harga'];
                            $grand_total += $subtotal;
                        ?>
                       <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td><?= date('d/m/y', strtotime($d['tgl_beli'])) ?></td>
                        <td class="fw-bold"><?= $d['no_request'] ?></td>
                        <td><?= $d['nama_barang_beli'] ?></td>
                        <td><?= $d['supplier'] ?></td>
                        <td class="text-center"><?= number_format($d['qty'], 2) ?></td>
                        <td class="text-end"><?= number_format($d['harga']) ?></td>
                        <td class="text-end fw-bold"><?= number_format($subtotal) ?></td>
                        <td>
                            <div class="fw-bold"><?= $d['nama_pemesan'] ?></div>
                            <small class="text-muted"><?= !empty($d['plat_nomor']) ? $d['plat_nomor'] : '-' ?></small>
                        </td>
                    </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="7" class="text-end">GRAND TOTAL:</td>
                            <td class="text-end"><?= number_format($grand_total) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="row mt-5 d-none d-print-flex text-center">
                <div class="col-4">
                    <p>Dibuat Oleh,</p><br><br><br>
                    <p>( ........................ )</p>
                </div>
                <div class="col-4"></div>
                <div class="col-4">
                    <p>Mengetahui,</p><br><br><br>
                    <p>( ........................ )</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelLaporan').DataTable({
        "pageLength": 50,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" },
        "order": [[1, 'desc']]
    });
});
</script>
</body>
</html>