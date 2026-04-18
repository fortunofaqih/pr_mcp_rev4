<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Ambil data riwayat retur
$query = mysqli_query($koneksi, "SELECT * FROM log_retur ORDER BY tgl_retur DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Retur Barang - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-red: #DC3545; --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 20px; }
        .navbar-mcp { background: var(--mcp-red); color: white; }
    </style>
</head>
<body class="pb-5">

<nav class="navbar navbar-mcp mb-4 shadow-sm">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold"><i class="fas fa-history me-2"></i> RIWAYAT RETUR / PENGEMBALIAN BARANG KE TOKO / SUPPLIER</span>
        <a href="../../index.php" class="btn btn-light btn-sm px-3 fw-bold"><i class="fas fa-arrow-left me-1"></i> KEMBALI</a>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="table-container shadow-sm">
        <div class="alert alert-info py-2">
            <i class="fas fa-info-circle me-2"></i> Halaman ini mencatat semua transaksi pembelian yang dibatalkan/dikembalikan ke toko beserta alasan dan pengaruhnya ke stok.
        </div>

        <div class="table-responsive">
            <table id="tabelRetur" class="table table-hover table-bordered align-middle w-100" style="font-size: 0.8rem;">
                <thead class="table-dark">
                    <tr>
                        <th class="text-center">WAKTU RETUR</th>
                        <th>NO. REQUEST</th>
                        <th>NAMA BARANG</th>
                        <th class="text-center">QTY</th>
                        <th>SUPPLIER</th>
                        <th class="text-center">STOK</th>
                        <th>ALASAN RETUR</th>
                        <th>EKSEKUTOR</th>
                    </tr>
                </thead>
                <tbody class="text-uppercase">
                    <?php while($d = mysqli_fetch_array($query)){ ?>
                    <tr>
                        <td class="text-center small">
                            <?= date('d/m/Y', strtotime($d['tgl_retur'])) ?><br>
                            <span class="text-muted" style="font-size: 10px;"><?= date('H:i', strtotime($d['tgl_retur'])) ?> WIB</span>
                        </td>
                        <td class="fw-bold text-primary"><?= $d['no_request'] ?: '-' ?></td>
                        <td class="fw-bold"><?= $d['nama_barang_retur'] ?></td>
                        <td class="text-center fw-bold"><?= (float)$d['qty_retur'] ?></td>
                        <td><?= $d['supplier'] ?></td>
                        <td class="text-center">
                            <?php if($d['alokasi_sebelumnya'] == 'MASUK STOK'): ?>
                                <span class="badge bg-danger" style="font-size: 9px;">DIPOTONG</span>
                            <?php else: ?>
                                <span class="badge bg-secondary" style="font-size: 9px;">TIDAK</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-wrap" style="max-width: 200px;"><?= $d['alasan_retur'] ?></td>
                        <td class="small fw-bold text-muted"><?= $d['eksekutor_retur'] ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelRetur').DataTable({
        "order": [[0, "desc"]],
        "pageLength": 25,
        "language": {
            "search": "<strong>CARI DATA RETUR:</strong>",
            "emptyTable": "Belum ada riwayat pengembalian barang"
        }
    });
});
</script>
</body>
</html>