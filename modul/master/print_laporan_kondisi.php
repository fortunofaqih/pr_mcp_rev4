<?php
// laporan_kondisi_kendaraan_excel.php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// ================= FILTER RENTANG TANGGAL =================
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date']   ?? date('Y-m-d');

if (empty($start_date) || empty($end_date)) {
    $start_date = date('Y-m-01');
    $end_date   = date('Y-m-d');
}

$start_date_sql = date('Y-m-d', strtotime($start_date));
$end_date_sql   = date('Y-m-d', strtotime($end_date));

$periode_label = date('d-M-Y', strtotime($start_date)) . ' s/d ' . date('d-M-Y', strtotime($end_date));

// ================= HEADER DOWNLOAD EXCEL =================
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Laporan_Mutasi_Service_Kendaraan_" 
       . date('d-m-Y', strtotime($start_date)) . "_sampai_" 
       . date('d-m-Y', strtotime($end_date)) . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// ================= KPI =================
// Mobil yang masih aktif diservice (end_date IS NULL) — hitung mobil UNIK
$total_aktif = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan WHERE end_date IS NULL"
))['total'];

// Mobil yang selesai tepat di periode filter — hitung mobil UNIK (bukan jumlah baris mutasi)
$stmt = mysqli_prepare($koneksi,
    "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan
     WHERE end_date IS NOT NULL AND end_date BETWEEN ? AND ?"
);
mysqli_stmt_bind_param($stmt, "ss", $start_date_sql, $end_date_sql);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $total_selesai);
mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

// ================= QUERY UTAMA (REVISI) =================
// Logika:
//   A) Service SELESAI yang end_date-nya masuk dalam periode filter
//   B) Service yang BELUM SELESAI (end_date IS NULL) — semua, meskipun
//      start_date-nya di bulan/tahun sebelumnya
//
// Status: 'SELESAI' (selesai di periode) atau 'MASIH DISERVICE' (pending)
$query_rekap = "
    SELECT
        k.id_kondisi,
        k.id_mobil,
        m.plat_nomor AS plat_nomor,
        k.plat_nomor AS plat_nomor_service,
        hp.plat_lama AS plat_sebelumnya,
        hp.plat_baru AS plat_hasil_perubahan,
        hp.tanggal_perubahan AS tanggal_ganti_plat,
        k.kondisi,
        k.bengkel,
        k.keterangan,
        k.start_date,
        k.end_date,
        k.created_at,
        m.driver_tetap,
        m.merk_tipe,
        m.tahun_kendaraan,
        DATEDIFF(COALESCE(k.end_date, NOW()), k.start_date) AS durasi_hari,
        CASE
            WHEN k.end_date IS NULL THEN 'MASIH DISERVICE'
            ELSE 'SELESAI'
        END AS status_service
    FROM kondisi_kendaraan k
    JOIN master_mobil m ON k.id_mobil = m.id_mobil
    LEFT JOIN histori_plat_mobil hp
        ON hp.id_histori = (
            SELECT h2.id_histori
            FROM histori_plat_mobil h2
            WHERE h2.id_mobil = k.id_mobil
            ORDER BY h2.tanggal_perubahan DESC, h2.id_histori DESC
            LIMIT 1
        )
    WHERE
        (k.end_date IS NOT NULL AND k.end_date BETWEEN ? AND ?)
        OR
        (k.end_date IS NULL)
    ORDER BY
        -- Pertahankan service pending di atas; urutkan menurut plat TERBARU dan identitas mobil
        (k.end_date IS NULL) DESC,
        m.plat_nomor ASC,
        k.id_mobil ASC,
        k.start_date ASC,
        k.created_at ASC
";

$stmt_rekap = mysqli_prepare($koneksi, $query_rekap);
mysqli_stmt_bind_param($stmt_rekap, "ss", $start_date_sql, $end_date_sql);
mysqli_stmt_execute($stmt_rekap);
$result_rekap = mysqli_stmt_get_result($stmt_rekap);

// Hitung jumlah mobil pending (unik) — untuk info di header KPI
$row_pending = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(DISTINCT id_mobil) as total FROM kondisi_kendaraan WHERE end_date IS NULL"
));
$total_pending = $row_pending['total'];
?>

<html xmlns:o="urn:schemas-microsoft-com:office:office" 
      xmlns:x="urn:schemas-microsoft-com:office:excel" 
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Mutasi Service</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        body { font-family: 'Calibri', sans-serif; font-size: 11px; }
        .table-data { border-collapse: collapse; width: 100%; }
        .table-data th {
            background-color: #4a90d9; color: white; font-weight: bold;
            border: 1px solid #000; padding: 8px 6px;
            text-align: center; vertical-align: middle;
        }
        .table-data td {
            border: 1px solid #000; padding: 6px; vertical-align: middle;
        }
        .text-center { text-align: center; }
        .text-left   { text-align: left; }
        .text-bold   { font-weight: bold; }
        .kpi-table { border-collapse: collapse; margin-bottom: 20px; font-size: 11px; width: 100%; }
        .kpi-table td { border: 1px solid #000; padding: 5px 10px; }
        .kpi-table .header-kpi { background-color: #e8e8e8; font-weight: bold; }
        .status-aktif   { color: #ff6600; font-weight: bold; }
        .status-selesai { color: #28a745; font-weight: bold; }
        .plat-group {
            background-color: #e8f0fe; font-weight: bold; font-size: 13px;
            color: #1a3c6e; border-bottom: 2px solid #4a90d9;
        }
        .plat-group td { padding: 8px 10px; border-bottom: 2px solid #4a90d9; }
        .bg-even { background-color: #f9f9f9; }
        .bg-odd  { background-color: #ffffff; }
        .row-pending { background-color: #fff4e6 !important; }
        .row-pending-prev { background-color: #fff9e6 !important; }
        .kerusakan-text { font-size: 10px; color: #333; }
        .title-report { font-size: 16px; font-weight: bold; text-align: center; }
        .sub-title    { font-size: 12px; text-align: center; font-style: italic; }
        .no-wrap      { white-space: nowrap; }
        .badge-pending {
            background-color: #ff9800; color: #fff; padding: 2px 6px;
            border-radius: 3px; font-size: 9px; font-weight: bold;
        }
        .badge-selesai {
            background-color: #28a745; color: #fff; padding: 2px 6px;
            border-radius: 3px; font-size: 9px; font-weight: bold;
        }
        .badge-lanjutan {
            background-color: #dc3545; color: #fff; padding: 2px 6px;
            border-radius: 3px; font-size: 9px; font-weight: bold;
        }
    </style>
</head>
<body>

    <!-- ================= JUDUL ================= -->
    <table style="border: none; margin-bottom: 10px; width: 100%;">
        <tr><td colspan="9" class="title-report">PT MUTIARA CAHAYA PLASTINDO</td></tr>
        <tr><td colspan="9" style="font-size: 14px; font-weight: bold; text-align: center;">LAPORAN MUTASI SERVICE KENDARAAN</td></tr>
        <tr><td colspan="9" class="sub-title">Periode: <?= $periode_label ?></td></tr>
        <tr><td colspan="9" style="font-size: 10px; text-align: center; color: #666;">
            Tanggal Cetak: <?= date('d F Y H:i:s') ?>
        </td></tr>
    </table>

    <br>

    <!-- ================= KPI ================= -->
    <table class="kpi-table">
        <tr>
            <td colspan="2" class="header-kpi text-center">
                RINGKASAN STATISTIK SERVICE PERIODE <?= strtoupper($periode_label) ?>
            </td>
        </tr>
        <tr>
            <td class="text-bold" style="width: 50%;">Mobil yang masih diservice</td>
            <td style="width: 50%;"><?= $total_pending ?> Unit</td>
        </tr>
        <tr>
            <td class="text-bold">Mobil yang selesai diservice</td>
            <td><?= $total_selesai ?> Unit</td>
        </tr>
    </table>

    <br>

    <div style="font-weight: bold; margin-bottom: 5px; font-size: 12px;">
        MUTASI SERVICE KENDARAAN PERIODE <?= strtoupper($periode_label) ?>
    </div>
    <div style="font-size: 10px; color: #666; margin-bottom: 8px; font-style: italic;">
        Catatan: Baris berwarna oranye = service yang masih berjalan (belum selesai), 
        termasuk yang dimulai dari periode sebelumnya.
    </div>

    <!-- ================= TABEL UTAMA ================= -->
    <table class="table-data">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 13%;">Plat Nomor</th>
                <th style="width: 13%;">Nama Driver</th>
                <th style="width: 11%;">Tgl Masuk</th>
                <th style="width: 11%;">Tgl Selesai</th>
                <th style="width: 25%;">Keterangan</th>
                <th style="width: 6%;">Durasi</th>
                <th style="width: 8%;">Bengkel</th>
                <th style="width: 9%;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $hasData = false;
            $current_group = null;
            $no = 0;

            while ($row = mysqli_fetch_assoc($result_rekap)) {
                $hasData = true;

                // Kelompokkan berdasarkan ID kendaraan, bukan plat historis.
                // Status pending/selesai tetap mengikuti prioritas urutan existing.
                $group_key = ($row['end_date'] === null ? 'PENDING:' : 'SELESAI:') . $row['id_mobil'];
                if ($current_group !== $group_key) {
                    $no = 0; // reset counter per kelompok kendaraan
                    $current_group = $group_key;
                    ?>
                    <tr class="plat-group">
                        <td colspan="9">
                            <b><?= htmlspecialchars($row['plat_nomor']) ?></b>
                            <?php if (!empty($row['merk_tipe'])): ?>
                                &mdash; <?= htmlspecialchars($row['merk_tipe']) ?>
                                <?= htmlspecialchars($row['tahun_kendaraan'] ? '(' . $row['tahun_kendaraan'] . ')' : '') ?>
                            <?php endif; ?>
                            <?php if (!empty($row['plat_sebelumnya']) && !empty($row['plat_hasil_perubahan'])): ?>
                                <div style="font-size: 10px; color: #795548; font-weight: normal; margin-top: 3px;">
                                    PERNAH GANTI PLAT — sebelumnya:
                                    <?= htmlspecialchars($row['plat_sebelumnya']) ?>
                                    &rarr; <?= htmlspecialchars($row['plat_hasil_perubahan']) ?>
                                    <?php if (!empty($row['tanggal_ganti_plat'])): ?>
                                        | Tgl ganti: <?= date('d/m/Y', strtotime($row['tanggal_ganti_plat'])) ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php
                }

                $no++; // nomor urut per plat

                $aktif = is_null($row['end_date']);

                // Durasi
                $durasi_hari = $row['durasi_hari'] ?? 0;
                if ($durasi_hari < 0) $durasi_hari = 0;

                // Apakah service ini dimulai sebelum periode filter? (lanjutan bulan lalu)
                $start_ts = strtotime($row['start_date']);
                $periode_ts = strtotime($start_date_sql);
                $is_lanjutan = $aktif && $start_ts < $periode_ts;

                // Format tanggal
                $tgl_mulai   = $row['start_date'] ? date('d-M-Y', strtotime($row['start_date'])) : '-';
                $tgl_selesai = $row['end_date']   ? date('d-M-Y', strtotime($row['end_date']))   : '-';

                // Status
                if ($aktif) {
                    $status_label = $is_lanjutan ? 'MASIH DISERVICE (LANJUTAN)' : 'MASIH DISERVICE';
                    $status_class = 'status-aktif';
                } else {
                    $status_label = 'SELESAI';
                    $status_class = 'status-selesai';
                }

                // Warna baris
                if ($aktif && $is_lanjutan) {
                    $row_class = 'row-pending-prev'; // kuning lebih tua = lanjutan bulan lalu
                } elseif ($aktif) {
                    $row_class = 'row-pending';      // oranye muda = pending periode ini
                } else {
                    $row_class = ($no % 2 == 0) ? 'bg-even' : 'bg-odd';
                }

                $keterangan = htmlspecialchars($row['keterangan'] ?? '-');
                if (empty(trim($keterangan))) $keterangan = '-';
            ?>
                <tr class="<?= $row_class ?>">
                    <td class="text-center" style="font-size: 10px;"><?= $no ?></td>
                    <td class="text-left" style="font-size: 10px;">
                        <b><?= htmlspecialchars($row['plat_nomor']) ?></b>
                    </td>
                    <td class="text-left" style="font-size: 10px;">
                        <?= htmlspecialchars($row['driver_tetap'] ?? '-') ?>
                    </td>
                    <td class="text-center no-wrap" style="font-size: 10px;">
                        <?= $tgl_mulai ?>
                    </td>
                    <td class="text-center no-wrap" style="font-size: 10px;">
                        <?= $tgl_selesai ?>
                    </td>
                    <td class="text-left kerusakan-text">
                        <?= $keterangan ?>
                    </td>
                    <td class="text-center" style="font-size: 10px;">
                        <?= $durasi_hari > 0 ? $durasi_hari . ' hari' : '-' ?>
                    </td>
                    <td class="text-center" style="font-size: 10px;">
                        <?= htmlspecialchars($row['bengkel'] ?? '-') ?>
                    </td>
                    <td class="text-center <?= $status_class ?>" style="font-size: 9px;">
                        <?= $status_label ?>
                    </td>
                </tr>
            <?php
            }

            mysqli_stmt_close($stmt_rekap);

            if (!$hasData) {
                echo '<tr><td colspan="9" class="text-center" style="font-style: italic; color: #777; padding: 20px;">'
                   . 'Tidak ada data mutasi service untuk periode ini.</td></tr>';
            }
            ?>
        </tbody>
    </table>

</body>
</html>