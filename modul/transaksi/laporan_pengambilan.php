<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

$bln_filter = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$thn_filter = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$nama_bulan = date("F", mktime(0, 0, 0, $bln_filter, 10));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengambilan Bongkaran - Landscape</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        
        /* CSS KHUSUS CETAK */
        @media print {
            @page { 
                size: landscape; /* Mengatur kertas otomatis Landscape */
                margin: 1cm; 
            }
            .no-print { display: none !important; }
            .card { border: none !important; box-shadow: none !important; }
            body { background-color: white; }
            .table { width: 100% !important; border-collapse: collapse; }
            .table th, .table td { border: 1px solid #000 !important; padding: 8px; }
        }
        
        .table thead { background-color: #f2f2f2; }
        .judul-laporan { border-bottom: 3px double #000; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-5">
    <div class="text-center judul-laporan pb-2">
        <h3 class="fw-bold mb-0">LAPORAN PENGAMBILAN BARANG BONGKARAN</h3>
        <h5 class="mb-1 text-primary">PT. Mutiara Cahaya Plastindo</h5>
        <p class="mb-2">Periode: <?= strtoupper($nama_bulan) ?> <?= $thn_filter ?></p>
    </div>

    <div class="card shadow-sm mb-4 no-print border-0">
        <div class="card-body d-flex justify-content-between align-items-center">
            <a href="bongkaran.php" class="btn btn-danger btn-sm"><i class="fas fa-rotate-left me-1"></i> Kembali</a>
            
            <form action="" method="GET" class="d-flex gap-2">
                <select name="bulan" class="form-select form-select-sm">
                    <?php for($m=1;$m<=12;$m++){ $v=sprintf('%02d',$m); $s=($v==$bln_filter)?'selected':''; echo"<option value='$v' $s>".date('F',mktime(0,0,0,$m,1))."</option>"; } ?>
                </select>
                <select name="tahun" class="form-select form-select-sm">
                    <?php for($y=date('Y');$y>=2024;$y--){ $s=($y==$thn_filter)?'selected':''; echo"<option value='$y' $s>$y</option>"; } ?>
                </select>
                <button type="submit" class="btn btn-sm btn-dark">Filter</button>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-primary px-3">
                    <i class="fas fa-file-pdf me-1"></i> CETAK LANDSCAPE
                </button>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="text-center small fw-bold text-uppercase">
                <tr>
                    <th width="3%">No</th>
                    <th width="12%">Tgl Keluar</th>
                    <th width="25%">Nama Barang</th>
                    <th width="10%">Qty Keluar</th>
                    <th width="20%">Penerima / Pemakai</th>
                    <th width="30%">Keperluan / Lokasi Pemasangan</th>
                </tr>
            </thead>
           <tbody class="small">
                <?php
                $no = 1;
                $total_qty = 0; // Tambahkan variabel counter total
                $q = mysqli_query($koneksi, "SELECT tk.*, tb.nama_barang, tb.satuan_bongkar 
                    FROM tr_bongkaran_keluar tk
                    LEFT JOIN tr_bongkaran tb ON tk.id_bongkaran = tb.id_bongkaran
                    WHERE MONTH(tk.tgl_keluar) = '$bln_filter' 
                    AND YEAR(tk.tgl_keluar) = '$thn_filter'
                    ORDER BY tk.tgl_keluar DESC, tk.id_keluar DESC");

                if(mysqli_num_rows($q) > 0) {
                    while($d = mysqli_fetch_array($q)) {
                        $total_qty += $d['qty_keluar']; // Hitung akumulasi
                ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="text-center"><?= date('d/m/Y', strtotime($d['tgl_keluar'])) ?></td>
                        <td class="fw-bold text-uppercase"><?= $d['nama_barang'] ?></td>
                        <td class="text-center fw-bold"><?= $d['qty_keluar'] ?> <?= $d['satuan_bongkar'] ?></td>
                        <td class="text-uppercase"><?= $d['penerima'] ?></td>
                        <td class="text-uppercase"><?= $d['keperluan'] ?></td>
                    </tr>
                <?php 
                    }
                    // Tampilkan baris Total di bawah
                    echo "<tr class='table-light fw-bold'>
                            <td colspan='3' class='text-end'>TOTAL PENGELUARAN :</td>
                            <td class='text-center'>$total_qty UNIT/PCS</td>
                            <td colspan='2'></td>
                        </tr>";
                } else {
                    echo "<tr><td colspan='6' class='text-center py-5'>DATA TIDAK DITEMUKAN PADA PERIODE INI</td></tr>";
                }
                ?>
</tbody>
        </table>
    </div>
    
    <div class="row mt-5 d-none d-print-flex">
        <div class="col-4 text-center small">
            <p>Admin Gudang,</p>
            <br><br><br>
            <p>( ____________________ )</p>
        </div>
        <div class="col-4"></div>
        <div class="col-4 text-center small">
            <p>Surabaya, <?= date('d F Y') ?><br>Mengetahui,</p>
            <br><br><br>
            <p>( ____________________ )<br></p>
        </div>
    </div>
</div>

</body>
</html>