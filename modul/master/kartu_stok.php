<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// Fungsi pembantu untuk tampilan angka bersih (Letakkan di atas)
function formatKartuStok($angka) {
    if ($angka == 0) return '-';
    // Format 4 desimal untuk mendukung presisi plastik yang kecil (0.011)
    $fmt = number_format($angka, 4, ',', '.');
    // Buang nol mubazir di belakang koma dan buang koma jika angka bulat
    return rtrim(rtrim($fmt, '0'), ',');
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// 1. Ambil info detail barang
$query_b = mysqli_query($koneksi, "SELECT * FROM master_barang WHERE id_barang='$id'");
$barang = mysqli_fetch_array($query_b);

if(!$barang) { echo "Barang tidak ditemukan"; exit; }

// 2. Setting Filter Tanggal
$tgl_mulai = isset($_GET['tgl_mulai']) ? $_GET['tgl_mulai'] : date('Y-m-01');
$tgl_selesai = isset($_GET['tgl_selesai']) ? $_GET['tgl_selesai'] : date('Y-m-d');

// 3. HITUNG SALDO AWAL (Gunakan tgl_log agar presisi ke jam/menit/detik)
$q_saldo_lalu = mysqli_query($koneksi, "SELECT 
    SUM(CASE WHEN tipe_transaksi IN ('MASUK', 'RETUR', 'ADJUSTMENT_PLUS') THEN qty ELSE 0 END) AS total_masuk,
    SUM(CASE WHEN tipe_transaksi IN ('KELUAR', 'ADJUSTMENT_MINUS') THEN qty ELSE 0 END) AS total_keluar
    FROM tr_stok_log 
    WHERE id_barang = '$id' AND tgl_log < '$tgl_mulai 00:00:00'");
$d_lalu = mysqli_fetch_array($q_saldo_lalu);
$stok_awal = (float)($d_lalu['total_masuk'] ?? 0) - (float)($d_lalu['total_keluar'] ?? 0);

// 4. Ambil data histori
$query_log = mysqli_query($koneksi, "SELECT * FROM tr_stok_log 
    WHERE id_barang='$id' 
    AND tgl_log BETWEEN '$tgl_mulai 00:00:00' AND '$tgl_selesai 23:59:59' 
    ORDER BY tgl_log ASC, id_log ASC");

$total_masuk = 0;
$total_keluar = 0;
$running_saldo = $stok_awal; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Stok - <?= $barang['nama_barang'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-blue: #00008B; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; font-size: 0.9rem; }
        .table-stok thead { background: var(--mcp-blue) !important; color: white !important; vertical-align: middle; }
        .bg-saldo-awal { background-color: #fdfdfe !important; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="py-4">

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h4 class="fw-bold mb-0"><i class="fas fa-box-open me-2 text-primary"></i>KARTU STOK</h4>
        <div class="d-flex gap-2">
             <a href="../laporan/data_stock.php" class="btn btn-sm btn-outline-danger"><i class="fas fa-arrow-left"></i> KEMBALI</a>
             <button onclick="window.print()" class="btn btn-sm btn-primary"><i class="fas fa-print me-2"></i> CETAK</button>
        </div>
    </div>

    <div class="card mb-3 no-print border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="id" value="<?= $id; ?>">
                <div class="col-md-4">
                    <label class="small fw-bold">Dari Tanggal</label>
                    <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= $tgl_mulai ?>">
                </div>
                <div class="col-md-4">
                    <label class="small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= $tgl_selesai ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-sm btn-dark w-100"><i class="fas fa-sync me-1"></i> TAMPILKAN DATA</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="row mb-4 border-bottom pb-3">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td width="100">BARANG</td><td>: <strong><?= strtoupper($barang['nama_barang']) ?></strong></td></tr>
                        <tr><td>RAK</td><td>: <?= strtoupper($barang['lokasi_rak'] ?: '-') ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6 text-md-end">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td>PERIODE: <strong><?= date('d/m/Y', strtotime($tgl_mulai)) ?> - <?= date('d/m/Y', strtotime($tgl_selesai)) ?></strong></td></tr>
                        <tr><td>SATUAN: <?= strtoupper($barang['satuan']) ?></td></tr>
                    </table>
                </div>
            </div>

            <table class="table table-bordered align-middle table-stok">
                <thead class="text-center">
                    <tr>
                        <th>TANGGAL</th>
                        <th>KETERANGAN / USER / REF</th>
                        <th width="12%">MASUK</th>
                        <th width="12%">KELUAR</th>
                        <th width="15%">SALDO</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-saldo-awal">
                        <td class="text-center text-muted small"><?= date('d/m/Y', strtotime($tgl_mulai)) ?></td>
                        <td class="fw-bold italic">SALDO AWAL PERIODE</td>
                        <td colspan="2" class="text-center bg-light">-</td>
                        <td class="text-center fw-bold"><?= formatKartuStok($stok_awal) ?></td>
                    </tr>

                    <?php
                    while($row = mysqli_fetch_array($query_log)) {
                        $masuk = in_array($row['tipe_transaksi'], ['MASUK', 'RETUR', 'ADJUSTMENT_PLUS']) ? (float)$row['qty'] : 0;
                        $keluar = in_array($row['tipe_transaksi'], ['KELUAR', 'ADJUSTMENT_MINUS']) ? (float)$row['qty'] : 0;
                        
                        $total_masuk += $masuk;
                        $total_keluar += $keluar;
                        $running_saldo = $running_saldo + $masuk - $keluar;
                    ?>
                    <tr>
                        <td class="text-center small"><?= date('d/m/y H:i', strtotime($row['tgl_log'])) ?></td>
                        <td>
                            <div class="d-flex justify-content-between">
                                <span><?= $row['keterangan'] ?></span>
                                <?php if($row['tipe_transaksi'] == 'RETUR'): ?>
                                    <span class="badge bg-success text-white" style="font-size:0.6rem;">RETUR</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center text-success fw-bold"><?= ($masuk > 0) ? formatKartuStok($masuk) : '-' ?></td>
                        <td class="text-center text-danger fw-bold"><?= ($keluar > 0) ? formatKartuStok($keluar) : '-' ?></td>
                        <td class="text-center fw-bold bg-light"><?= formatKartuStok($running_saldo) ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr class="fw-bold">
                        <td colspan="2" class="text-end">TOTAL MUTASI :</td>
                        <td class="text-center"><?= formatKartuStok($total_masuk) ?></td>
                        <td class="text-center"><?= formatKartuStok($total_keluar) ?></td>
                        <td class="text-center bg-warning text-dark">SALDO: <?= formatKartuStok($running_saldo) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

</body>
</html>