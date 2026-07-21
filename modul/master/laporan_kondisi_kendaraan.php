<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Filter bulan dan tahun
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Kondisi Kendaraan - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card.green { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .stat-card.orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .stat-card.red { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .stat-label { font-size: 0.9rem; opacity: 0.9; }
        
        @media (max-width: 768px) {
            .stat-number { font-size: 1.5rem; }
            .stat-label { font-size: 0.8rem; }
        }
        
        @media print {
            .no-print { display: none !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .stat-card { background: #f8f9fa !important; color: #333 !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-4 no-print">
    <div class="container-fluid px-3 px-sm-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-chart-bar me-2"></i> LAPORAN KONDISI KENDARAAN</span>
        <div class="d-flex flex-wrap gap-1">
            <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> KEMBALI</a>
            <a href="kondisi_kendaraan.php" class="btn btn-sm btn-light fw-bold"><i class="fas fa-clipboard-list"></i> KONDISI</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
    <!-- Filter -->
    <div class="card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-6 col-md-3">
                    <label class="form-label fw-bold">Bulan</label>
                    <select name="bulan" class="form-select">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= sprintf('%02d', $i) ?>" <?= $bulan == sprintf('%02d', $i) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-bold">Tahun</label>
                    <select name="tahun" class="form-select">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                </div>
                <div class="col-6 col-md-2">
                    <a href="print_laporan_kondisi.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-success w-100" target="_blank">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistik -->
    <?php
    // Total kendaraan
    $query_total = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM master_mobil");
    $total_mobil = mysqli_fetch_assoc($query_total)['total'];

    // Total dalam service
    $query_service = mysqli_query($koneksi, "SELECT COUNT(DISTINCT k.id_mobil) as total 
                                            FROM kondisi_kendaraan k 
                                            WHERE k.kondisi IN ('DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT')
                                            AND k.created_at <= NOW()");
    $total_service = mysqli_fetch_assoc($query_service)['total'];

    // Sedang service (belum selesai)
    $query_ongoing = mysqli_query($koneksi, "SELECT COUNT(DISTINCT k.id_mobil) as total 
                                            FROM kondisi_kendaraan k 
                                            WHERE k.kondisi IN ('DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT')
                                            AND (k.end_date IS NULL OR k.end_date > NOW())");
    $total_ongoing = mysqli_fetch_assoc($query_ongoing)['total'];

    // Selesai service bulan ini
    $query_selesai = mysqli_query($koneksi, "SELECT COUNT(DISTINCT k.id_mobil) as total 
                                            FROM kondisi_kendaraan k 
                                            WHERE k.kondisi = 'BAIK'
                                            AND MONTH(k.end_date) = '$bulan' 
                                            AND YEAR(k.end_date) = '$tahun'");
    $total_selesai = mysqli_fetch_assoc($query_selesai)['total'];
    ?>

    <div class="row mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-number"><?= $total_mobil ?></div>
                <div class="stat-label">Total Armada</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card blue">
                <div class="stat-number"><?= $total_service ?></div>
                <div class="stat-label">Dalam Service</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card orange">
                <div class="stat-number"><?= $total_ongoing ?></div>
                <div class="stat-label">Service Berjalan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card green">
                <div class="stat-number"><?= $total_selesai ?></div>
                <div class="stat-label">Selesai Service</div>
            </div>
        </div>
    </div>

    <!-- Grafik -->
    <div class="row mb-4">
        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Status Kondisi Kendaraan</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartStatus" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Durasi Service Terlama</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartDurasi" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Kendaraan Dalam Service -->
    <div class="card">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Kendaraan Dalam Service</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Plat Nomor</th>
                            <th>Driver</th>
                            <th>Kondisi</th>
                            <th>Start Service</th>
                            <th>Durasi (Hari)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query_service_detail = mysqli_query($koneksi, "
                            SELECT k.*, m.driver_tetap, m.merk_tipe 
                            FROM kondisi_kendaraan k 
                            JOIN master_mobil m ON k.id_mobil = m.id_mobil 
                            WHERE k.kondisi IN ('DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT')
                            AND (k.end_date IS NULL OR k.end_date > NOW())
                            ORDER BY k.start_date ASC
                        ");
                        while($d = mysqli_fetch_array($query_service_detail)):
                            $durasi = 0;
                            if ($d['start_date']) {
                                $start = new DateTime($d['start_date']);
                                $now = new DateTime();
                                $diff = $start->diff($now);
                                $durasi = $diff->days;
                            }
                            
                            $badge = '';
                            if ($d['kondisi'] == 'DISERVICE') {
                                $badge = 'badge-diservice';
                            } else if ($d['kondisi'] == 'RUSAK RINGAN') {
                                $badge = 'badge-rusak-ringan';
                            } else if ($d['kondisi'] == 'RUSAK BERAT') {
                                $badge = 'badge-rusak-berat';
                            }
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= $d['plat_nomor'] ?></td>
                            <td><?= $d['driver_tetap'] ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $d['kondisi'] ?></span></td>
                            <td><?= date('d-M-Y', strtotime($d['start_date'])) ?></td>
                            <td><strong><?= $durasi ?></strong> hari</td>
                            <td>
                                <?php if ($d['end_date'] && strtotime($d['end_date']) < time()): ?>
                                    <span class="badge bg-success">Selesai</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Proses</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($query_service_detail) == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Tidak ada kendaraan dalam service</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel Riwayat Service Bulanan -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Service Bulan <?= date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Plat Nomor</th>
                            <th>Driver</th>
                            <th>Kondisi</th>
                            <th>Start Service</th>
                            <th>End Service</th>
                            <th>Durasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query_riwayat = mysqli_query($koneksi, "
                            SELECT k.*, m.driver_tetap, m.merk_tipe 
                            FROM kondisi_kendaraan k 
                            JOIN master_mobil m ON k.id_mobil = m.id_mobil 
                            WHERE MONTH(k.created_at) = '$bulan' 
                            AND YEAR(k.created_at) = '$tahun'
                            ORDER BY k.created_at DESC
                        ");
                        while($d = mysqli_fetch_array($query_riwayat)):
                            $durasi = '-';
                            if ($d['start_date'] && $d['end_date']) {
                                $start = new DateTime($d['start_date']);
                                $end = new DateTime($d['end_date']);
                                $diff = $start->diff($end);
                                $durasi = $diff->days + 1 . ' hari';
                            }
                            
                            $badge = '';
                            if ($d['kondisi'] == 'BAIK') {
                                $badge = 'bg-success';
                            } else if ($d['kondisi'] == 'DISERVICE') {
                                $badge = 'bg-warning text-dark';
                            } else if ($d['kondisi'] == 'RUSAK RINGAN') {
                                $badge = 'bg-warning';
                            } else if ($d['kondisi'] == 'RUSAK BERAT') {
                                $badge = 'bg-danger';
                            }
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= $d['plat_nomor'] ?></td>
                            <td><?= $d['driver_tetap'] ?></td>
                            <td><span class="badge <?= $badge ?>"><?= $d['kondisi'] ?></span></td>
                            <td><?= $d['start_date'] ? date('d-M-Y', strtotime($d['start_date'])) : '-' ?></td>
                            <td><?= $d['end_date'] ? date('d-M-Y', strtotime($d['end_date'])) : '-' ?></td>
                            <td><?= $durasi ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($query_riwayat) == 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Tidak ada data service untuk bulan ini</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Data untuk grafik status
<?php
// Ambil data kondisi
$query_status = mysqli_query($koneksi, "
    SELECT k.kondisi, COUNT(DISTINCT k.id_mobil) as total 
    FROM kondisi_kendaraan k 
    WHERE k.created_at <= NOW()
    GROUP BY k.kondisi
");
$status_data = [];
while ($row = mysqli_fetch_assoc($query_status)) {
    $status_data[$row['kondisi']] = (int)$row['total'];
}
// Jika tidak ada data, set default
if (empty($status_data)) {
    $status_data['BAIK'] = 0;
    $status_data['DISERVICE'] = 0;
    $status_data['RUSAK RINGAN'] = 0;
    $status_data['RUSAK BERAT'] = 0;
}

// Data durasi service terlama
$query_durasi = mysqli_query($koneksi, "
    SELECT k.plat_nomor, k.start_date, k.end_date,
           DATEDIFF(COALESCE(k.end_date, NOW()), k.start_date) as durasi
    FROM kondisi_kendaraan k
    WHERE k.start_date IS NOT NULL
    AND k.kondisi IN ('DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT')
    ORDER BY durasi DESC
    LIMIT 10
");
$durasi_labels = [];
$durasi_values = [];
while ($row = mysqli_fetch_assoc($query_durasi)) {
    $durasi_labels[] = $row['plat_nomor'];
    $durasi_values[] = (int)$row['durasi'];
}
?>

// Chart Status
const ctxStatus = document.getElementById('chartStatus').getContext('2d');
new Chart(ctxStatus, {
    type: 'doughnut',
    data: {
        labels: ['BAIK', 'DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT'],
        datasets: [{
            data: [
                <?= $status_data['BAIK'] ?? 0 ?>,
                <?= $status_data['DISERVICE'] ?? 0 ?>,
                <?= $status_data['RUSAK RINGAN'] ?? 0 ?>,
                <?= $status_data['RUSAK BERAT'] ?? 0 ?>
            ],
            backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Chart Durasi
const ctxDurasi = document.getElementById('chartDurasi').getContext('2d');
new Chart(ctxDurasi, {
    type: 'bar',
    data: {
        labels: <?= json_encode($durasi_labels) ?>,
        datasets: [{
            label: 'Durasi Service (Hari)',
            data: <?= json_encode($durasi_values) ?>,
            backgroundColor: [
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(255, 99, 132, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(255, 99, 132, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Hari'
                }
            }
        }
    }
});

// Idle Timer
let idleTime = 0;
const maxIdleMinutes = 15;

function resetTimer() {
    idleTime = 0;
}

window.onload = resetTimer;
document.onmousemove = resetTimer;
document.onkeypress = resetTimer;
document.onmousedown = resetTimer;
document.onclick = resetTimer;
document.onscroll = resetTimer;

setInterval(function() {
    idleTime++;
    if (idleTime >= maxIdleMinutes) {
        window.location.href = "http://192.168.31.200/pr_mcp/auth/logout.php?pesan=timeout";
    }
}, 60000);
</script>
</body>
</html>