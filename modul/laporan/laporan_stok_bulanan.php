<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Default filter: Bulan berjalan
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Stok Bulanan - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .header-report { background: #00008B; color: white; padding: 20px; border-radius: 10px 10px 0 0; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="bg-light py-4">

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="header-report no-print">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="m-0"><i class="fas fa-file-invoice me-2"></i> REKAPITULASI STOK BARANG</h5>
                <div>
                    <a href="../../index.php" class="btn btn-danger btn-sm"><i class="fas fa-rotate-left"></i> Kembali</a>
                    <button onclick="window.print()" class="btn btn-light btn-sm"><i class="fas fa-print"></i> Cetak</button>
                    
                </div>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" class="row g-3 mb-4 no-print border-bottom pb-3">
                <div class="col-md-3">
                    <label class="small fw-bold">Dari Tanggal</label>
                    <input type="date" name="tgl_mulai" class="form-control" value="<?= $tgl_mulai ?>">
                </div>
                <div class="col-md-3">
                    <label class="small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                </div>
            </form>

            <div class="text-center mb-4">
                <h4 class="fw-bold text-uppercase">LAPORAN MUTASI STOK GUDANG</h4>
                <p class="text-muted">Periode: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_selesai)) ?></p>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-dark text-center small">
                        <tr>
                            <th rowspan="2">NO</th>
                            <th rowspan="2">NAMA BARANG</th>
                            <th rowspan="2">MERK</th> <th rowspan="2">KATEGORI</th>
                            <th rowspan="2" class="bg-secondary">STOK AWAL<br>(Sblm <?= date('d/m', strtotime($tgl_mulai)) ?>)</th>
                            <th colspan="2" class="bg-primary">MUTASI PERIODE INI</th>
                            <th rowspan="2" class="bg-success">STOK AKHIR<br>(Per <?= date('d/m', strtotime($tgl_selesai)) ?>)</th>
                            <th rowspan="2">SATUAN</th>
                        </tr>
                        <tr>
                            <th class="bg-primary">MASUK (+)</th>
                            <th class="bg-primary">KELUAR (-)</th>
                        </tr>
                    </thead>
                    <tbody class="small text-uppercase">
                        <?php
                        $no = 1;
                        $res = mysqli_query($koneksi, "SELECT * FROM master_barang WHERE is_active = 1 ORDER BY nama_barang ASC");
                        while($b = mysqli_fetch_array($res)){
                            $id_b = $b['id_barang'];

                            // 1. Hitung Stok Awal (Semua transaksi sebelum tgl_mulai)
                            $q_awal = mysqli_query($koneksi, "SELECT 
                                SUM(CASE WHEN tipe_transaksi='MASUK' THEN qty ELSE 0 END) - 
                                SUM(CASE WHEN tipe_transaksi='KELUAR' THEN qty ELSE 0 END) as awal 
                                FROM tr_stok_log WHERE id_barang='$id_b' AND tgl_log < '$tgl_mulai'");
                            $d_awal = mysqli_fetch_array($q_awal);
                            $stok_awal = $d_awal['awal'] ?? 0;

                            // 2. Hitung Mutasi Masuk/Keluar dalam periode
                            $q_mutasi = mysqli_query($koneksi, "SELECT 
                                SUM(CASE WHEN tipe_transaksi='MASUK' THEN qty ELSE 0 END) as masuk,
                                SUM(CASE WHEN tipe_transaksi='KELUAR' THEN qty ELSE 0 END) as keluar
                                FROM tr_stok_log WHERE id_barang='$id_b' AND tgl_log BETWEEN '$tgl_mulai 00:00:00' AND '$tgl_selesai 23:59:59'");
                            $d_mutasi = mysqli_fetch_array($q_mutasi);
                            $masuk = $d_mutasi['masuk'] ?? 0;
                            $keluar = $d_mutasi['keluar'] ?? 0;

                            $stok_akhir = $stok_awal + $masuk - $keluar;
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="fw-bold"><?= $b['nama_barang'] ?></td>
                            <td class="text-center"><?= $b['merk'] ?: '-' ?></td> <td class="text-center"><?= $b['kategori'] ?></td>
                            <td class="text-center fw-bold bg-light"><?= number_format($stok_awal,0) ?></td>
                            <td class="text-center text-success"><?= ($masuk > 0) ? number_format($masuk,0) : '-' ?></td>
                            <td class="text-center text-danger"><?= ($keluar > 0) ? number_format($keluar,0) : '-' ?></td>
                            <td class="text-center fw-bold bg-success text-white"><?= number_format($stok_akhir,0) ?></td>
                            <td class="text-center small"><?= $b['satuan'] ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>