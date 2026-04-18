<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Database Armada - MCP System</title>
   <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        table.dataTable thead th { background-color: #f1f4f9; vertical-align: middle; }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-4">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-truck me-2"></i> DATABASE ARMADA KENDARAAN</span>
        <div>
            <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> KEMBALI</a>
            <a href="mobil.php" class="btn btn-sm btn-light fw-bold"><i class="fas fa-plus-circle"></i> TAMBAH DATA MOBIL</a>
            
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelMobil" class="table table-hover table-striped align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th>Plat Nomor</th>
                            <th>Driver</th>
                            <th>Jenis</th>
                            <th>Kategori</th>
                            <th>Merk/Tipe</th>
                            <th class="text-center">Tahun</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php
                        $query = mysqli_query($koneksi, "SELECT * FROM master_mobil ORDER BY plat_nomor ASC");
                        while($d = mysqli_fetch_array($query)){
                            $badge = ($d['status_aktif'] == 'AKTIF') ? 'bg-success' : 'bg-danger';
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= $d['plat_nomor'] ?></td>
                            <td class="text-uppercase"><?= $d['driver_tetap'] ?></td>
                            <td><?= $d['jenis_kendaraan'] ?></td>
                            <td><span class="badge bg-light text-dark border"><?= $d['kategori_kendaraan'] ?></span></td>
                            <td><?= $d['merk_tipe'] ?></td>
                            <td class="text-center"><?= $d['tahun_kendaraan'] ?></td>
                            <td class="text-center"><span class="badge <?= $badge ?>"><?= $d['status_aktif'] ?></span></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="edit_mobil.php?id=<?= $d['id_mobil'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="proses_status_mobil.php?id=<?= $d['id_mobil'] ?>&status=<?= $d['status_aktif'] ?>" 
                                       class="btn btn-sm <?= ($d['status_aktif'] == 'AKTIF') ? 'btn-outline-danger' : 'btn-outline-success' ?>" 
                                       onclick="return confirm('Ubah status kendaraan ini?')">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#tabelMobil').DataTable({
        "pageLength": 10,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
        "columnDefs": [ { "orderable": false, "targets": 7 } ]
    });
});
</script>
</body>
</html>