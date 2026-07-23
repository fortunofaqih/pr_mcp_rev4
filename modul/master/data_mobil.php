<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        @media (max-width: 768px) {
            .navbar-brand { font-size: 0.9rem; }
            .btn-sm { font-size: 0.7rem; padding: 0.25rem 0.5rem; }
            .table-responsive { font-size: 0.8rem; }
            .card-body { padding: 0.75rem; }
            .container-fluid { padding-left: 0.5rem; padding-right: 0.5rem; }
        }
        @media (max-width: 576px) {
            .navbar-brand { font-size: 0.75rem; }
            .btn { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
            .table td, .table th { padding: 0.3rem 0.2rem; }
            .badge { font-size: 0.65rem; }
        }

        .badge-baik { background-color: #28a745; color: white; }
        .badge-diservice { background-color: #ffc107; color: black; }
        .badge-rusak-ringan { background-color: #fd7e14; color: white; }
        .badge-rusak-berat { background-color: #dc3545; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-4">
    <div class="container-fluid px-3 px-sm-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-truck me-2"></i> DATABASE ARMADA KENDARAAN</span>
        <div class="d-flex flex-wrap gap-1">
            <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> HOME</a>
            <a href="mobil.php" class="btn btn-sm btn-light fw-bold"><i class="fas fa-plus-circle"></i> ADD DATA MOBIL</a>
            <a href="kondisi_kendaraan.php" class="btn btn-sm btn-warning fw-bold"><i class="fas fa-clipboard-list"></i> KONDISI SERVIS</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
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
                            <th class="text-center">Kondisi</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php
                        $query = mysqli_query($koneksi, "SELECT * FROM master_mobil ORDER BY plat_nomor ASC");

                        // Kondisi "saat ini" dihitung, bukan disimpan manual:
                        // kalau ada episode servis yang belum ditutup (end_date NULL) -> pakai kondisi itu.
                        // Kalau tidak ada -> mobil dianggap BAIK.
                        $stmt_kondisi = mysqli_prepare($koneksi,
                            "SELECT kondisi FROM kondisi_kendaraan
                             WHERE id_mobil = ? AND end_date IS NULL
                             ORDER BY start_date DESC LIMIT 1"
                        );

                        while ($d = mysqli_fetch_array($query)) {
                            mysqli_stmt_bind_param($stmt_kondisi, "i", $d['id_mobil']);
                            mysqli_stmt_execute($stmt_kondisi);
                            $row_kondisi = mysqli_stmt_get_result($stmt_kondisi)->fetch_assoc();
                            $kondisi = $row_kondisi['kondisi'] ?? 'BAIK';

                            $badge_class = [
                                'BAIK'         => 'badge-baik',
                                'DISERVICE'    => 'badge-diservice',
                                'RUSAK RINGAN' => 'badge-rusak-ringan',
                                'RUSAK BERAT'  => 'badge-rusak-berat',
                            ][$kondisi] ?? 'badge-baik';
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($d['plat_nomor']) ?></td>
                            <td class="text-uppercase"><?= htmlspecialchars($d['driver_tetap']) ?></td>
                            <td><?= htmlspecialchars($d['jenis_kendaraan']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($d['kategori_kendaraan']) ?></span></td>
                            <td><?= htmlspecialchars($d['merk_tipe']) ?></td>
                            <td class="text-center"><?= htmlspecialchars($d['tahun_kendaraan']) ?></td>
                            <td class="text-center"><span class="badge <?= $badge_class ?>"><?= $kondisi ?></span></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="edit_mobil.php?id=<?= $d['id_mobil'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-info" onclick="lihatKondisi(<?= $d['id_mobil'] ?>)" title="Lihat Riwayat Servis">
                                        <i class="fas fa-clipboard-list"></i>
                                    </button>
                                    <a href="proses_status_mobil.php?id=<?= $d['id_mobil'] ?>&status=<?= $d['status_aktif'] ?>"
                                       class="btn btn-sm <?= ($d['status_aktif'] == 'AKTIF') ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                       onclick="return confirm('Ubah status kendaraan ini?')" title="Ubah Status Aktif">
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

<!-- Modal Riwayat Servis -->
<div class="modal fade" id="modalRiwayat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i> Riwayat Servis Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="riwayatBody">
                <p class="text-center text-muted">Memuat data...</p>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#tabelMobil').DataTable({
        "pageLength": 10,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
        "columnDefs": [ { "orderable": false, "targets": 7 } ],
        "responsive": true
    });
});

function lihatKondisi(id_mobil) {
    $('#riwayatBody').html('<p class="text-center text-muted">Memuat data...</p>');
    $('#modalRiwayat').modal('show');

    $.ajax({
        url: 'ajax_riwayat_kondisi.php',
        type: 'POST',
        data: { id_mobil: id_mobil },
        dataType: 'html',
        success: function(response) {
            $('#riwayatBody').html(response);
        },
        error: function() {
            $('#riwayatBody').html('<p class="text-center text-danger">Error memuat data!</p>');
        }
    });
}

// Idle Timer
let idleTime = 0;
const maxIdleMinutes = 15;
let lastServerUpdate = Date.now();
let sessionValid = true;

function resetTimer() {
    idleTime = 0;
    let now = Date.now();
    if (now - lastServerUpdate > 300000) {
        fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    sessionValid = false;
                    forceLogout();
                }
            })
            .catch(err => {
                console.error("Koneksi ke server terputus");
            });
        lastServerUpdate = now;
    }
}

function forceLogout() {
    alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
    window.location.href = "http://192.168.31.200/pr_mcp/auth/logout.php?pesan=timeout";
}

window.onload = resetTimer;
document.onmousemove = resetTimer;
document.onkeypress = resetTimer;
document.onmousedown = resetTimer;
document.onclick = resetTimer;
document.onscroll = resetTimer;

setInterval(function() {
    idleTime++;
    fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                sessionValid = false;
                forceLogout();
            }
        })
        .catch(err => {});
    if (idleTime >= maxIdleMinutes && sessionValid) {
        forceLogout();
    }
}, 60000);
</script>
</body>
</html>