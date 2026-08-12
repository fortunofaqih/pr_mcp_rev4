<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$id_mesin = (int)($_GET['id_mesin'] ?? 0);

if ($id_mesin <= 0) {
    die('ID mesin tidak valid');
}

// Ambil data mesin
$query_mesin = "SELECT id_mesin, name FROM master_mesin WHERE id = $id_mesin";
$result_mesin = mysqli_query($koneksi, $query_mesin);
$mesin = mysqli_fetch_assoc($result_mesin);

if (!$mesin) {
    die('Data mesin tidak ditemukan');
}

// Ambil riwayat kondisi - diurutkan dari yang paling awal (start_date ASC)
$query_riwayat = "SELECT id_kondisi_mesin, kondisi_mesin, keterangan_service_mesin, 
                         start_date, end_date, created_at, created_by, updated_at, updated_by
                  FROM kondisi_mesin
                  WHERE id_mesin = $id_mesin
                  ORDER BY start_date ASC, created_at ASC";
$result_riwayat = mysqli_query($koneksi, $query_riwayat);

$riwayat = [];
while ($row = mysqli_fetch_assoc($result_riwayat)) {
    $riwayat[] = $row;
}

// Cek kondisi terakhir (aktif)
$kondisi_terakhir = 'BAIK';
$query_kondisi = "SELECT kondisi_mesin FROM kondisi_mesin 
                  WHERE id_mesin = $id_mesin AND end_date IS NULL 
                  ORDER BY start_date DESC LIMIT 1";
$result_kondisi = mysqli_query($koneksi, $query_kondisi);
$row_kondisi = mysqli_fetch_assoc($result_kondisi);
if ($row_kondisi) {
    $kondisi_terakhir = $row_kondisi['kondisi_mesin'];
}

function formatDateIndo($date) {
    if (empty($date)) return '-';
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
    $timestamp = strtotime($date);
    return date('d', $timestamp) . '-' . $bulan[date('n', $timestamp) - 1] . '-' . date('Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Kondisi Mesin - <?= htmlspecialchars($mesin['id_mesin']) ?></title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .badge-baik { background-color: #28a745; color: white; }
        .badge-diservice { background-color: #ffc107; color: #000; }
        table.dataTable thead th { background-color: #f1f4f9; vertical-align: middle; }
        .btn-print {
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .btn-print:hover {
            background: #2563eb;
            color: white;
        }
        @media print {
            .no-print { display: none !important; }
            .navbar-mcp { display: none !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            body { background: white !important; padding: 20px !important; }
            table { page-break-inside: avoid; }
            .badge { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-left: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -24px;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--mcp-blue);
            border: 2px solid white;
            box-shadow: 0 0 0 2px var(--mcp-blue);
        }
        .timeline-item.active::before {
            background: #ffc107;
            box-shadow: 0 0 0 2px #ffc107;
        }
        .timeline-item.completed::before {
            background: #28a745;
            box-shadow: 0 0 0 2px #28a745;
        }
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box .label {
            font-weight: bold;
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .info-box .value {
            font-size: 1.1rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-3 no-print">
    <div class="container-fluid px-3 px-sm-4">
        <span class="navbar-brand fw-bold text-white">
            <i class="fas fa-history me-2"></i> RIWAYAT KONDISI MESIN
        </span>
        <div class="d-flex flex-wrap gap-1">
            <button onclick="window.close()" class="btn btn-sm btn-danger">
                <i class="fas fa-times"></i> TUTUP
            </button>
            <button onclick="window.print()" class="btn btn-sm btn-print">
                <i class="fas fa-print"></i> CETAK / PDF
            </button>
            <a href="mesin.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> KEMBALI
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
    <!-- Informasi Mesin -->
    <div class="info-box">
        <div class="row">
            <div class="col-md-6">
                <div class="label">ID Mesin</div>
                <div class="value"><?= htmlspecialchars($mesin['id_mesin']) ?></div>
            </div>
            <div class="col-md-6">
                <div class="label">Nama Mesin</div>
                <div class="value"><?= htmlspecialchars($mesin['name']) ?></div>
            </div>
            <div class="col-md-6 mt-2">
                <div class="label">Kondisi Saat Ini</div>
                <div class="value">
                    <?php if ($kondisi_terakhir == 'BAIK') { ?>
                        <span class="badge badge-baik" style="font-size:1rem;">
                            <i class="fas fa-check-circle me-1"></i> BAIK
                        </span>
                    <?php } else { ?>
                        <span class="badge badge-diservice" style="font-size:1rem;">
                            <i class="fas fa-tools me-1"></i> DISERVICE
                        </span>
                    <?php } ?>
                </div>
            </div>
            <div class="col-md-6 mt-2">
                <div class="label">Total Riwayat</div>
                <div class="value"><?= count($riwayat) ?> Record</div>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">
                <i class="fas fa-list me-2 text-primary"></i>
                RIWAYAT KONDISI MESIN
                <small class="text-muted">(Diurutkan dari tanggal pertama)</small>
            </h5>
        </div>
        <div class="card-body">
            <?php if (count($riwayat) > 0) { ?>
            <div class="table-responsive">
                <table id="tabelRiwayat" class="table table-hover table-striped align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th style="width:5%;">No</th>
                            <th style="width:12%;">Kondisi</th>
                            <th style="width:30%;">Keterangan Service</th>
                            <th style="width:15%;">Tanggal Mulai</th>
                            <th style="width:15%;">Tanggal Selesai</th>
                            <th style="width:8%;">Durasi</th>
                            <th style="width:10%;">Status</th>
                            <th style="width:5%;">#</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    $total_hari = 0;
                    foreach ($riwayat as $row) {
                        $aktif = is_null($row['end_date']);
                        $durasi = '-';
                        $durasi_hari = 0;
                        
                        if ($row['start_date']) {
                            $start = new DateTime($row['start_date']);
                            $sampai = $aktif ? new DateTime() : new DateTime($row['end_date']);
                            $diff = $start->diff($sampai);
                            $durasi_hari = $diff->days + 1;
                            $durasi = $durasi_hari . ' hari';
                            if ($aktif) {
                                $durasi .= ' (berjalan)';
                            }
                            $total_hari += $durasi_hari;
                        }
                        
                        $status_class = $aktif ? 'bg-warning text-dark' : 'bg-success text-white';
                        $status_text = $aktif ? 'AKTIF' : 'SELESAI';
                        $badge_kondisi = $row['kondisi_mesin'] == 'BAIK' 
                            ? 'badge-baik' 
                            : 'badge-diservice';
                        $icon_kondisi = $row['kondisi_mesin'] == 'BAIK' 
                            ? 'fa-check-circle' 
                            : 'fa-tools';
                    ?>
                        <tr>
                            <td class="text-center fw-bold"><?= $no++ ?></td>
                            <td>
                                <span class="badge <?= $badge_kondisi ?>">
                                    <i class="fas <?= $icon_kondisi ?> me-1"></i>
                                    <?= $row['kondisi_mesin'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['keterangan_service_mesin'] ?? '-') ?></td>
                            <td><?= formatDateIndo($row['start_date']) ?></td>
                            <td><?= $aktif ? '-' : formatDateIndo($row['end_date']) ?></td>
                            <td class="text-center"><?= $durasi ?></td>
                            <td>
                                <span class="badge <?= $status_class ?>" style="width:100%;">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($aktif) { ?>
                                    <i class="fas fa-circle text-warning" title="Masih Berjalan"></i>
                                <?php } else { ?>
                                    <i class="fas fa-check-circle text-success" title="Selesai"></i>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-info">
                            <td colspan="5" class="text-end fw-bold">TOTAL DURASI SERVICE</td>
                            <td class="text-center fw-bold"><?= $total_hari ?> hari</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php } else { ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Belum ada riwayat kondisi untuk mesin ini</h5>
                    <p class="text-muted">Status: <span class="badge badge-baik">BAIK</span></p>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Informasi Tambahan -->
    <div class="row mt-3 no-print">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-info-circle text-primary me-2"></i> Informasi</h6>
                    <small class="text-muted">
                        <i class="fas fa-circle text-warning me-1"></i> Status AKTIF = Service masih berjalan<br>
                        <i class="fas fa-circle text-success me-1"></i> Status SELESAI = Service sudah selesai<br>
                        <i class="fas fa-check-circle text-success me-1"></i> BAIK = Mesin dalam kondisi baik<br>
                        <i class="fas fa-tools text-warning me-1"></i> DISERVICE = Mesin sedang diperbaiki
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-clock text-primary me-2"></i> Ringkasan</h6>
                    <small class="text-muted">
                        Total riwayat: <?= count($riwayat) ?> record<br>
                        Total hari service: <?= $total_hari ?? 0 ?> hari<br>
                        Kondisi saat ini: <strong><?= $kondisi_terakhir ?></strong>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    $('#tabelRiwayat').DataTable({
        pageLength: 10,
        language: { 
            url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" 
        },
        responsive: true,
        order: [[3, 'asc']], // Urutkan berdasarkan tanggal mulai (ASC - dari yang pertama)
        columnDefs: [
            { orderable: false, targets: [0, 7] } // No dan # tidak bisa diurutkan
        ]
    });
});
</script>
</body>
</html>