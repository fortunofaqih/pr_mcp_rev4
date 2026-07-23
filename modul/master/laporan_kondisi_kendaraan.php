<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Filter bulan dan tahun (di-cast ke integer, lebih aman dari SQL injection)
$bulan = isset($_GET['bulan']) ? (int) $_GET['bulan'] : (int) date('m');
$tahun = isset($_GET['tahun']) ? (int) $_GET['tahun'] : (int) date('Y');
if ($bulan < 1 || $bulan > 12) $bulan = (int) date('m');
$bulan_str = sprintf('%02d', $bulan);
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
        .table thead th { background-color: #f1f4f9; vertical-align: middle; }

        .stat-card {
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            color: #fff;
            background: #495057;
        }
        .stat-card.total   { background: var(--mcp-blue); }
        .stat-card.aktif   { background: #fd7e14; }
        .stat-card.selesai { background: #28a745; }
        .stat-card.durasi  { background: #6c757d; }
        .stat-card i.stat-icon { font-size: 1.5rem; opacity: 0.85; }
        .stat-number { font-size: 2rem; font-weight: bold; line-height: 1.1; }
        .stat-label { font-size: 0.85rem; opacity: 0.95; }

        .badge-baik { background-color: #28a745; color: white; }
        .badge-diservice { background-color: #ffc107; color: black; }
        .badge-rusak-ringan { background-color: #fd7e14; color: white; }
        .badge-rusak-berat { background-color: #dc3545; color: white; }

        .chart-box { position: relative; height: 280px; }

        @media (max-width: 768px) {
            .navbar-brand { font-size: 0.9rem; }
            .btn-sm { font-size: 0.7rem; padding: 0.25rem 0.5rem; }
            .table-responsive { font-size: 0.8rem; }
            .card-body { padding: 0.75rem; }
            .container-fluid { padding-left: 0.5rem; padding-right: 0.5rem; }
            .stat-number { font-size: 1.5rem; }
            .stat-label { font-size: 0.75rem; }
            .chart-box { height: 220px; }
        }
        @media (max-width: 576px) {
            .navbar-brand { font-size: 0.75rem; }
            .btn { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
            .table td, .table th { padding: 0.3rem 0.2rem; }
            .badge { font-size: 0.65rem; }
            .stat-card { padding: 14px; }
        }

        @media print {
            .no-print { display: none !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .stat-card { color: #000 !important; border: 1px solid #ddd !important; }
            .stat-card.total, .stat-card.aktif, .stat-card.selesai, .stat-card.durasi { background: #f8f9fa !important; }
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
                            <option value="<?= sprintf('%02d', $i) ?>" <?= $bulan == $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label fw-bold">Tahun</label>
                    <select name="tahun" class="form-select">
                        <?php for ($i = (int)date('Y'); $i >= (int)date('Y') - 5; $i--): ?>
                            <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
                </div>
                <div class="col-6 col-md-2">
                    <a href="print_laporan_kondisi.php?bulan=<?= $bulan_str ?>&tahun=<?= $tahun ?>" class="btn btn-success w-100" target="_blank">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= STATISTIK ================= -->
    <?php
    $total_mobil = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM master_mobil"))['total'];

    $total_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan WHERE end_date IS NULL"
    ))['total'];

    $stmt = mysqli_prepare($koneksi,
        "SELECT COUNT(*) as total FROM kondisi_kendaraan
         WHERE end_date IS NOT NULL AND MONTH(end_date) = ? AND YEAR(end_date) = ?"
    );
    mysqli_stmt_bind_param($stmt, "ii", $bulan, $tahun);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total_selesai);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    $row_durasi = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT AVG(DATEDIFF(end_date, start_date) + 1) as rata2
         FROM kondisi_kendaraan WHERE end_date IS NOT NULL AND start_date IS NOT NULL"
    ));
    $rata2_durasi = $row_durasi['rata2'] !== null ? round($row_durasi['rata2'], 1) : 0;
    ?>

    <div class="row mb-2">
        <div class="col-6 col-md-3">
            <div class="stat-card total">
                <i class="fas fa-truck stat-icon"></i>
                <div class="stat-number"><?= $total_mobil ?></div>
                <div class="stat-label">Total Armada</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card aktif">
                <i class="fas fa-wrench stat-icon"></i>
                <div class="stat-number"><?= $total_aktif ?></div>
                <div class="stat-label">Sedang Diservis (Aktif Saat Ini)</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card selesai">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-number"><?= $total_selesai ?></div>
                <div class="stat-label">Selesai Servis Bulan Ini</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card durasi">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-number"><?= $rata2_durasi ?> <small style="font-size:1rem;">hari</small></div>
                <div class="stat-label">Rata-rata Durasi Servis</div>
            </div>
        </div>
    </div>

    <!-- ================= GRAFIK ================= -->
    <div class="row mb-4">
        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Distribusi Kondisi Armada Saat Ini</h6>
                </div>
                <div class="card-body">
                    <div class="chart-box"><canvas id="chartStatus"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Durasi Servis Terlama (Top 10)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-box"><canvas id="chartDurasi"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= TABEL: RIWAYAT BULANAN ================= -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Servis Dimulai Bulan <?= date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun)) ?></h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="small text-uppercase">
                        <tr>
                            <th style="width:36px;"></th>
                            <th>Plat Nomor</th>
                            <th>Driver</th>
                            <th class="text-center">Jumlah Servis</th>
                            <th>Status Terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt_riwayat = mysqli_prepare($koneksi, "
                            SELECT k.kondisi, k.plat_nomor, k.start_date, k.end_date, m.driver_tetap
                            FROM kondisi_kendaraan k
                            JOIN master_mobil m ON k.id_mobil = m.id_mobil
                            WHERE MONTH(k.start_date) = ? AND YEAR(k.start_date) = ?
                            ORDER BY k.start_date DESC
                        ");
                        mysqli_stmt_bind_param($stmt_riwayat, "ii", $bulan, $tahun);
                        mysqli_stmt_execute($stmt_riwayat);
                        mysqli_stmt_bind_result($stmt_riwayat, $r_kondisi, $r_plat, $r_start, $r_end, $r_driver);

                        $riwayat_grouped = [];
                        while (mysqli_stmt_fetch($stmt_riwayat)) {
                            $riwayat_grouped[$r_plat]['driver'] = $r_driver;
                            $riwayat_grouped[$r_plat]['episodes'][] = [
                                'kondisi' => $r_kondisi,
                                'start'   => $r_start,
                                'end'     => $r_end,
                            ];
                        }
                        mysqli_stmt_close($stmt_riwayat);

                        $badge_map = [
                            'DISERVICE'    => 'bg-warning text-dark',
                            'RUSAK RINGAN' => 'bg-warning',
                            'RUSAK BERAT'  => 'bg-danger',
                        ];
                        ?>

                        <?php if (empty($riwayat_grouped)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Tidak ada servis yang dimulai pada bulan ini.</td>
                        </tr>
                        <?php else: foreach ($riwayat_grouped as $plat => $data):
                            $episodes = $data['episodes'];
                            $terakhir = $episodes[0];
                            $aktif_terakhir = is_null($terakhir['end']);
                            $collapse_id = 'riwayat_' . preg_replace('/[^A-Za-z0-9]/', '', $plat);
                        ?>
                        <tr class="riwayat-toggle" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#<?= $collapse_id ?>">
                            <td class="text-center text-muted"><i class="fas fa-chevron-right toggle-icon"></i></td>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($plat) ?></td>
                            <td><?= htmlspecialchars($data['driver']) ?></td>
                            <td class="text-center"><span class="badge bg-secondary"><?= count($episodes) ?>x</span></td>
                            <td>
                                <?= $aktif_terakhir
                                    ? '<span class="badge bg-warning text-dark">AKTIF</span>'
                                    : '<span class="badge bg-success">SELESAI</span>' ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="5" class="p-0 border-0">
                                <div class="collapse" id="<?= $collapse_id ?>">
                                    <div class="p-2 ps-4 bg-light">
                                        <table class="table table-sm mb-0 bg-white">
                                            <thead class="small text-uppercase">
                                                <tr>
                                                    <th>Kondisi</th>
                                                    <th>Mulai</th>
                                                    <th>Selesai</th>
                                                    <th>Durasi</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($episodes as $ep):
                                                    $aktif = is_null($ep['end']);
                                                    $durasi = '-';
                                                    if ($ep['start']) {
                                                        $start = new DateTime($ep['start']);
                                                        $sampai = $aktif ? new DateTime() : new DateTime($ep['end']);
                                                        $durasi = ($start->diff($sampai)->days + 1) . ' hari';
                                                    }
                                                    $badge = $badge_map[$ep['kondisi']] ?? 'bg-secondary';
                                                ?>
                                                <tr>
                                                    <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($ep['kondisi']) ?></span></td>
                                                    <td><?= $ep['start'] ? date('d-M-Y', strtotime($ep['start'])) : '-' ?></td>
                                                    <td><?= $ep['end'] ? date('d-M-Y', strtotime($ep['end'])) : '-' ?></td>
                                                    <td><?= $durasi ?></td>
                                                    <td>
                                                        <?= $aktif
                                                            ? '<span class="badge bg-warning text-dark">AKTIF</span>'
                                                            : '<span class="badge bg-success">SELESAI</span>' ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php
$status_data = ['BAIK' => 0, 'DISERVICE' => 0, 'RUSAK RINGAN' => 0, 'RUSAK BERAT' => 0];

$q_mobil = mysqli_query($koneksi, "SELECT id_mobil FROM master_mobil");
$stmt_cek = mysqli_prepare($koneksi,
    "SELECT kondisi FROM kondisi_kendaraan WHERE id_mobil = ? AND end_date IS NULL ORDER BY start_date DESC LIMIT 1"
);
mysqli_stmt_bind_param($stmt_cek, "i", $id_mobil_loop);
mysqli_stmt_bind_result($stmt_cek, $kondisi_loop);

while ($m = mysqli_fetch_assoc($q_mobil)) {
    $id_mobil_loop = $m['id_mobil'];
    mysqli_stmt_execute($stmt_cek);
    $k = mysqli_stmt_fetch($stmt_cek) ? $kondisi_loop : 'BAIK';
    if (!isset($status_data[$k])) $k = 'BAIK';
    $status_data[$k]++;
}
mysqli_stmt_close($stmt_cek);

$query_durasi = mysqli_query($koneksi, "
    SELECT k.plat_nomor, k.start_date, k.end_date,
           DATEDIFF(COALESCE(k.end_date, NOW()), k.start_date) as durasi
    FROM kondisi_kendaraan k
    WHERE k.start_date IS NOT NULL
    ORDER BY durasi DESC
    LIMIT 10
");
$durasi_labels = [];
$durasi_values = [];
while ($row = mysqli_fetch_assoc($query_durasi)) {
    $durasi_labels[] = $row['plat_nomor'];
    $durasi_values[] = (int) $row['durasi'];
}
?>

new Chart(document.getElementById('chartStatus').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['BAIK', 'DISERVICE', 'RUSAK RINGAN', 'RUSAK BERAT'],
        datasets: [{
            data: [
                <?= $status_data['BAIK'] ?>,
                <?= $status_data['DISERVICE'] ?>,
                <?= $status_data['RUSAK RINGAN'] ?>,
                <?= $status_data['RUSAK BERAT'] ?>
            ],
            backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});

new Chart(document.getElementById('chartDurasi').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($durasi_labels) ?>,
        datasets: [{
            label: 'Durasi Servis (Hari)',
            data: <?= json_encode($durasi_values) ?>,
            backgroundColor: 'rgba(0, 0, 255, 0.6)',
            borderColor: 'rgba(0, 0, 255, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Hari' } } }
    }
});

let idleTime = 0;
const maxIdleMinutes = 15;
function resetTimer() { idleTime = 0; }
window.onload = resetTimer;
document.onmousemove = resetTimer;
document.onkeypress = resetTimer;
document.onmousedown = resetTimer;
document.onclick = resetTimer;
document.onscroll = resetTimer;
setInterval(function () {
    idleTime++;
    if (idleTime >= maxIdleMinutes) {
        window.location.href = "http://192.168.31.200/pr_mcp/auth/logout.php?pesan=timeout";
    }
}, 60000);
</script>
</body>
</html>