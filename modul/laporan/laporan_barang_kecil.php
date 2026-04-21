<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
include '../../auth/keep_alive.php';

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
    <title>LAPORAN BARANG KECIL - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); }
        .table thead { background-color: #f8f9fa; color: #333; }
        
        /* LOGIKA CETAK (PRINT) */
        @media print {
            @page { 
                size: landscape; 
                margin: 0.5cm; 
            }
            
            .no-print, .dt-buttons, .dataTables_filter, .dataTables_info, .dataTables_paginate, .navbar, .btn {
                display: none !important;
            }
            
            body { background-color: white; margin: 0; padding: 0; color: #000; }
            .container-fluid { width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .card { box-shadow: none !important; border: none !important; }
            .card-body { padding: 0 !important; }
            
            .print-header { display: block !important; text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
            
            table { width: 100% !important; border-collapse: collapse !important; table-layout: fixed; }
            th, td { 
                border: 1px solid #000 !important; 
                padding: 4px 2px !important; 
                font-size: 8.5pt !important; 
                word-wrap: break-word; 
            }
            .col-pr { width: 100px; }
            .col-plat { width: 80px; } /* Tambahkan lebar kolom plat nomor */
            .col-barang { width: auto; }

            /* Lebar Kolom saat Print agar No. PR tidak terjepit */
            .col-no { width: 30px; }
            .col-tgl { width: 75px; }
            .col-pr { width: 115px; }
            .col-barang { width: auto; }
            .col-supp { width: 110px; }
            .col-qty { width: 40px; }
            .col-harga { width: 90px; }
            .col-total { width: 100px; }
            .col-pemesan { width: 100px; }

            .text-nowrap { white-space: nowrap !important; }
            .table-responsive { overflow: visible !important; display: block !important; }
        }

        .print-header { display: none; }
    </style>
</head>
<body>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div>
            <h4 class="fw-bold text-success mb-0"><i class="fas fa-box-open me-2"></i>REKAP PEMBELIAN BARANG KECIL</h4>
            <p class="text-muted mb-0 small">Kategori Permintaan: KECIL / RUTIN</p>
        </div>
        <div>
            <a href="../../index.php" class="btn btn-danger btn-sm shadow-sm"><i class="fas fa-rotate-left"></i> Kembali</a>
            <button onclick="cetakLaporan()" class="btn btn-dark btn-sm shadow-sm px-3"><i class="fas fa-print me-1"></i> Cetak Printer</button>
        </div>
    </div>

    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">DARI TANGGAL</label>
                    <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?= $tgl_awal ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">SAMPAI TANGGAL</label>
                    <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= $tgl_akhir ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-search me-1"></i> Filter Data</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="print-header">
                <h2 style="margin:0;">PT. MUTIARACAHAYA PLASTINDO</h2>
                <h4 style="margin:5px 0;">LAPORAN REALISASI PEMBELIAN BARANG KECIL</h4>
                <p style="margin:0;">Periode: <?= date('d/m/Y', strtotime($tgl_awal)) ?> s/d <?= date('d/m/Y', strtotime($tgl_akhir)) ?></p>
            </div>

            <div class="table-responsive">
                <table id="tabelKecil" class="table table-bordered table-striped w-100">
                    <thead class="table-light text-center">
                    <tr>
                        <th class="col-no">NO</th>
                        <th class="col-tgl">TGL. BELI</th>
                        <th class="col-pr">NO. PR</th>
                        <th class="col-plat">PLAT</th> <th class="col-barang">BARANG</th>
                        <th class="col-supp">SUPPLIER</th>
                        <th class="col-qty">QTY</th>
                        <th class="col-harga">HARGA</th>
                        <th class="col-total">TOTAL</th>
                        <th class="col-pemesan">PEMESAN</th>
                    </tr>
                </thead>
                   <tbody>
                        <?php
                        $no = 1;
                        $grand_total = 0;
                        // Query tetap sama karena p.* sudah mengambil semua kolom dari tabel pembelian
                                           $query = "SELECT p.*, r.kategori_pr 
                              FROM pembelian p
                              INNER JOIN tr_request r ON p.id_request = r.id_request
                              WHERE r.kategori_pr = 'KECIL' 
                              /* Gunakan tgl_beli_barang agar sesuai dengan tanggal di NOTA */
                              AND p.tgl_beli_barang BETWEEN '$tgl_awal' AND '$tgl_akhir'
                              ORDER BY p.tgl_beli_barang DESC";
                        
                        $sql = mysqli_query($koneksi, $query);
                        while ($d = mysqli_fetch_array($sql)) {
                            $subtotal = $d['qty'] * $d['harga'];
                            $grand_total += $subtotal;
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                              <td class="text">
                                <?php 
                                    if ($d['tgl_beli_barang'] == '0000-00-00' || empty($d['tgl_beli_barang'])) {
                                        echo "-";
                                    } else {
                                        echo date('d/m/Y', strtotime($d['tgl_beli_barang']));
                                    }
                                ?>
                            </td>
                            <td class="fw-bold text-nowrap" style="font-size: 8pt;"><?= $d['no_request'] ?></td>
                            <td class="text-center text-nowrap"><?= $d['plat_nomor'] ?></td> <td><?= $d['nama_barang_beli'] ?></td>
                            <td><?= $d['supplier'] ?></td>
                            <td class="text-center">
							<?php 
								// Format ke 4 desimal dulu agar angka kecil (0.011) tidak hilang
								$qty_fmt = number_format($d['qty'], 4, ',', '.'); 
								
								// Buang nol di kanan, dan buang koma jika angka bulat
								echo rtrim(rtrim($qty_fmt, '0'), ','); 
							?>
						</td>
                            <td class="text-end"><?= number_format($d['harga']) ?></td>
                            <td class="text-end fw-bold"><?= number_format($subtotal) ?></td>
                            <td><?= $d['nama_pemesan'] ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                   <tfoot class="table-dark fw-bold">
                        <tr>
                            <td colspan="8" class="text-end">TOTAL PENGELUARAN:</td> <td class="text-end text-warning"><?= number_format($grand_total) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="row mt-5 d-none d-print-flex text-center">
                <div class="col-4">
                    <p>Admin Gudang,</p><br><br><br>
                    <p>( ........................ )</p>
                </div>
                <div class="col-4"></div>
                <div class="col-4">
                    <p>Purchasing,</p><br><br><br>
                    <p>( ........................ )</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#tabelKecil').DataTable({
        dom: '<"d-flex justify-content-between no-print"Bf>rtip',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-1"></i> Export Excel',
                className: 'btn btn-success btn-sm shadow-sm',
                footer: true
            }
        ],
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.21/i18n/Indonesian.json" },
        "pageLength": 25
    });
});

// Fungsi Cetak yang Menampilkan Semua Data Terlebih Dahulu
function cetakLaporan() {
    var table = $('#tabelKecil').DataTable();
    
    // Ubah ke mode tampilkan semua agar tidak terpotong pagination
    table.page.len(-1).draw();
    
    // Beri waktu sedikit untuk rendering semua baris
    setTimeout(function(){
        window.print();
        // Kembalikan ke jumlah baris semula setelah jendela print tertutup
        table.page.len(25).draw();
    }, 500);
}
</script>
<script>
    let idleTime = 0;
    const maxIdleMinutes = 15;
    let lastServerUpdate = Date.now();

    // Fungsi untuk mereset timer idle
    function resetTimer() {
        idleTime = 0;
        
        let now = Date.now();
        // Kirim sinyal "Keep Alive" ke server setiap 5 menit sekali jika user aktif
        // Ini mencegah session PHP mati saat user sedang asyik mengetik/input
        if (now - lastServerUpdate > 300000) { // 300.000 ms = 5 menit
            const depth = window.location.pathname.split('/').length - 2;
            const prefix = "../".repeat(Math.max(0, depth - 1));
            
            fetch(prefix + 'auth/keep_alive.php')
                .then(response => console.log("Sesi diperbarui secara background"))
                .catch(err => console.error("Gagal memperbarui sesi", err));
            
            lastServerUpdate = now;
        }
    }

    // Deteksi interaksi user
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    // Interval cek setiap 1 menit
    setInterval(function() {
        idleTime++;
        if (idleTime >= maxIdleMinutes) {
            alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
            const depth = window.location.pathname.split('/').length - 2;
            const prefix = "../".repeat(Math.max(0, depth - 1));
            window.location.href = prefix + "login.php?pesan=timeout";
        }
    }, 60000); // Cek setiap 60 detik
</script>
</body>
</html>