<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Proteksi halaman
if ($_SESSION['status'] != "login") {
    header("location:../../login.php"); exit;
}

// Default filter tanggal (Awal bulan s/d hari ini)
$tgl_awal  = $_POST['tgl_awal'] ?? date('Y-m-01');
$tgl_akhir = $_POST['tgl_akhir'] ?? date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Barang Besar - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print { .no-print { display: none; } }
        .bg-report { background-color: #f8f9fa; }
        .table-header { background-color: #00008B; color: white; }
    </style>
</head>
<body class="bg-report">

<div class="container-fluid py-4">
    <div class="card shadow-sm mb-4 no-print">
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Dari Tanggal</label>
                    <input type="date" name="tgl_awal" class="form-control" value="<?= $tgl_awal ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Sampai Tanggal</label>
                    <input type="date" name="tgl_akhir" class="form-control" value="<?= $tgl_akhir ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <button type="button" onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> Cetak Laporan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white text-center py-3">
            <h4 class="fw-bold m-0">LAPORAN PEMBELIAN BARANG BESAR (> 1 JUTA)</h4>
            <p class="text-muted m-0">Periode: <?= date('d/m/Y', strtotime($tgl_awal)) ?> s/d <?= date('d/m/Y', strtotime($tgl_akhir)) ?></p>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-header text-center">
                        <tr>
                            <th>No</th>
                            <th>No. Request</th>
                            <th>Tanggal PR</th>
                            <th>Nama Barang</th>
                            <th>Unit/Mobil</th>
                            <th>Qty</th>
                            <th>Harga Satuan</th>
                            <th>Total (Rp)</th>
                            <th>Approve By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        $grand_total = 0;
                        
                        // PERBAIKAN: Melakukan kalkulasi manual di SQL karena MySQL 5.6 tidak support Virtual Columns
                        $sql = "SELECT r.no_request, r.tgl_request, r.approve_by, 
                                       rd.nama_barang_manual, rd.jumlah, rd.satuan, 
                                       rd.harga_satuan_estimasi, 
                                       (rd.jumlah * rd.harga_satuan_estimasi) AS subtotal_estimasi,
                                       m.plat_nomor
                                FROM tr_request r
                                JOIN tr_request_detail rd ON r.id_request = rd.id_request
                                LEFT JOIN master_mobil m ON rd.id_mobil = m.id_mobil
                                WHERE r.kategori_pr = 'BESAR' 
                                AND r.status_approval = 'DISETUJUI'
                                AND r.tgl_request BETWEEN '$tgl_awal' AND '$tgl_akhir'
                                ORDER BY r.tgl_request ASC";
                        
                        $query = mysqli_query($koneksi, $sql);
                        
                        if(mysqli_num_rows($query) > 0) {
                            while($row = mysqli_fetch_assoc($query)) {
                                $grand_total += $row['subtotal_estimasi'];
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= $row['no_request'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['tgl_request'])) ?></td>
                                    <td><?= $row['nama_barang_manual'] ?></td>
                                    <td class="text-center"><?= $row['plat_nomor'] ?? '-' ?></td>
                                    <td class="text-center"><?= $row['jumlah'] ?> <?= $row['satuan'] ?></td>
                                    <td class="text-end"><?= number_format($row['harga_satuan_estimasi'], 0, ',', '.') ?></td>
                                    <td class="text-end fw-bold"><?= number_format($row['subtotal_estimasi'], 0, ',', '.') ?></td>
                                    <td class="text-center small"><?= $row['approve_by'] ?></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo "<tr><td colspan='9' class='text-center py-4'>Data tidak ditemukan untuk periode ini.</td></tr>";
                        }
                        ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="7" class="text-end">TOTAL INVESTASI BARANG BESAR</th>
                            <th class="text-end text-primary h5 fw-bold">Rp <?= number_format($grand_total, 0, ',', '.') ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>