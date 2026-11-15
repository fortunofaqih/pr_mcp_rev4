<?php
// laporan_kondisi_mesin_print.php
// Hanya menampilkan halaman yang siap di-print ke PDF

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
<html>
<head>
    <meta charset="UTF-8">
    <title>Riwayat Kondisi Mesin - <?= htmlspecialchars($mesin['id_mesin']) ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20px; }
            table { page-break-inside: avoid; }
            .status-aktif, .status-selesai, .kondisi-baik, .kondisi-service {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            padding: 20px;
            background: white;
            color: #212529;
        }
        h1 { color: #1e3a8a; text-align: center; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; }
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 8px; border-bottom: 1px solid #dee2e6; }
        .info-label { font-weight: bold; width: 20%; }
        .info-value { width: 80%; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e9ecef; font-weight: bold; padding: 8px; border: 1px solid #dee2e6; }
        td { padding: 6px; border: 1px solid #dee2e6; }
        .status-aktif { background: #ffc107; font-weight: bold; color: #000; }
        .status-selesai { background: #28a745; font-weight: bold; color: white; }
        .kondisi-baik { color: #28a745; font-weight: bold; }
        .kondisi-service { color: #dc3545; font-weight: bold; }
        .footer { margin-top: 30px; font-size: 10px; color: #6c757d; }
        .no-print { margin-bottom: 20px; text-align: center; }
        .btn-print { padding: 10px 30px; background: #1e3a8a; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 0 5px; }
        .btn-print:hover { background: #2563eb; }
        .total-row td { background: #e9ecef; font-weight: bold; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">
            <i class="fas fa-print"></i> Cetak / Save as PDF
        </button>
        <button onclick="window.close()" class="btn-print" style="background:#6c757d;">
            <i class="fas fa-times"></i> Tutup
        </button>
    </div>

    <h1>LAPORAN RIWAYAT KONDISI MESIN</h1>

    <table class="info-table">
        <tr><td class="info-label">ID Mesin</td><td class="info-value"><strong><?= htmlspecialchars($mesin['id_mesin']) ?></strong></td></tr>
        <tr><td class="info-label">Nama Mesin</td><td class="info-value"><?= htmlspecialchars($mesin['name']) ?></td></tr>
        <tr><td class="info-label">Kondisi Saat Ini</td><td class="info-value"><strong style="color:<?= $kondisi_terakhir == 'BAIK' ? '#28a745' : '#dc3545' ?>;"><?= htmlspecialchars($kondisi_terakhir) ?></strong></td></tr>
        <tr><td class="info-label">Total Riwayat</td><td class="info-value"><?= count($riwayat) ?> Record</td></tr>
    </table>

    <h3>RIWAYAT SERVIS</h3>
    <table>
        <thead>
            <tr>
                <th style="width:5%;">No</th>
                <th style="width:13%;">Kondisi</th>
                <th style="width:29%;">Keterangan Service</th>
                <th style="width:13%;">Mulai</th>
                <th style="width:13%;">Selesai</th>
                <th style="width:10%;">Durasi</th>
                <th style="width:10%;">Status</th>
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

            $status_class = $aktif ? 'status-aktif' : 'status-selesai';
            $status_text = $aktif ? 'AKTIF' : 'SELESAI';
            $kondisi_class = $row['kondisi_mesin'] == 'BAIK' ? 'kondisi-baik' : 'kondisi-service';
        ?>
            <tr>
                <td style="text-align:center;"><?= $no++ ?></td>
                <td style="text-align:center;"><span class="<?= $kondisi_class ?>"><?= htmlspecialchars($row['kondisi_mesin']) ?></span></td>
                <td><?= htmlspecialchars($row['keterangan_service_mesin'] ?? '-') ?></td>
                <td style="text-align:center;"><?= formatDateIndo($row['start_date']) ?></td>
                <td style="text-align:center;"><?= $aktif ? '-' : formatDateIndo($row['end_date']) ?></td>
                <td style="text-align:center;"><?= $durasi ?></td>
                <td style="text-align:center;" class="<?= $status_class ?>"><?= $status_text ?></td>
            </tr>
        <?php } ?>
        </tbody>
        <?php if (count($riwayat) > 0) { ?>
        <tfoot>
            <tr class="total-row">
                <td colspan="5" style="text-align:right;">TOTAL DURASI SERVICE</td>
                <td style="text-align:center;"><?= $total_hari ?> hari</td>
                <td></td>
            </tr>
        </tfoot>
        <?php } ?>
    </table>

    <?php if (count($riwayat) === 0) { ?>
        <p style="text-align:center;color:#6c757d;margin-top:20px;">
            Belum ada riwayat kondisi untuk mesin ini.
        </p>
    <?php } ?>

    <div class="footer">
        <table style="width:100%;">
            <tr>
                <td style="width:50%;">Dicetak oleh: <?= $_SESSION['username'] ?? 'System' ?></td>
                <td style="width:50%;text-align:right;">Dicetak: <?= date('d-m-Y H:i:s') ?></td>
            </tr>
        </table>
    </div>
</body>
</html>