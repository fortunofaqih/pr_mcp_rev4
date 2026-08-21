<?php
session_start();

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/kawat_helper.php';

if (($_SESSION['status'] ?? '') !== 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$user = $_SESSION['username'] ?? 'system';

/* =========================================================
   HELPER LOKAL
   ========================================================= */

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function labelJenis($jenis)
{
    return [
        'PEMBELIAN'          => 'Pembelian',
        'PEMAKAIAN'          => 'Dipakai Pabrik',
        'RETURN_BEKAS'       => 'Kembali dari Pabrik',
        'ROMBENG'            => 'Rombeng / Jual Bekas',
        'ADJUSTMENT_MASUK'   => 'Adjustment Masuk',
        'ADJUSTMENT_KELUAR'  => 'Adjustment Keluar',
    ][$jenis] ?? $jenis;
}

function badgeJenis($jenis)
{
    return [
        'PEMBELIAN'          => 'success',
        'PEMAKAIAN'          => 'primary',
        'RETURN_BEKAS'       => 'warning',
        'ROMBENG'            => 'danger',
        'ADJUSTMENT_MASUK'   => 'info',
        'ADJUSTMENT_KELUAR'  => 'secondary',
    ][$jenis] ?? 'secondary';
}

function qtyToKg(float $qty, string $satuan): ?float
{
    $satuan = strtoupper(trim($satuan));

    return match ($satuan) {
        'KG'  => $qty,
        'ONS' => $qty * 0.1,
        default => null
    };
}

function getIdSatuan(mysqli $db, string $namaSatuan): ?int
{
    $stmt = $db->prepare("
        SELECT id_satuan
        FROM master_satuan
        WHERE UPPER(TRIM(nama_satuan)) = UPPER(TRIM(?))
        LIMIT 1
    ");
    $stmt->bind_param("s", $namaSatuan);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row['id_satuan'] : null;
}

/**
 * Saldo aktif suatu kondisi, dengan opsi mengecualikan satu transaksi.
 * Digunakan saat edit agar transaksi lama tidak dihitung dua kali.
 */
function getSaldoAktifExclude(
    mysqli $db,
    int $idKawat,
    string $kondisi,
    int $excludeId = 0
): float {
    if ($excludeId > 0) {
        $stmt = $db->prepare("
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN arah = 'IN'  THEN qty_kg
                        WHEN arah = 'OUT' THEN -qty_kg
                        ELSE 0
                    END
                ),
                0
            ) AS saldo
            FROM transaksi_kawat
            WHERE id_kawat = ?
              AND kondisi = ?
              AND status_transaksi = 'AKTIF'
              AND id_transaksi <> ?
        ");
        $stmt->bind_param("isi", $idKawat, $kondisi, $excludeId);
    } else {
        $stmt = $db->prepare("
            SELECT COALESCE(
                SUM(
                    CASE
                        WHEN arah = 'IN'  THEN qty_kg
                        WHEN arah = 'OUT' THEN -qty_kg
                        ELSE 0
                    END
                ),
                0
            ) AS saldo
            FROM transaksi_kawat
            WHERE id_kawat = ?
              AND kondisi = ?
              AND status_transaksi = 'AKTIF'
        ");
        $stmt->bind_param("is", $idKawat, $kondisi);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (float)($row['saldo'] ?? 0);
}

/* =========================================================
   SINKRON MASTER & PEMBELIAN
   ========================================================= */

$syncMaster = kawat_sync_master_barang($koneksi, $user);
$syncBeli   = kawat_sync_pembelian($koneksi, $user);

/* =========================================================
   UPDATE / EDIT TRANSAKSI MANUAL
   ========================================================= */

if (isset($_POST['update_transaksi'])) {

    $idTransaksi = (int)($_POST['id_transaksi'] ?? 0);
    $tanggal      = $_POST['tanggal'] ?? date('Y-m-d');
    $idKawat      = (int)($_POST['id_kawat'] ?? 0);
    $jenisForm    = $_POST['jenis_mutasi'] ?? '';
    $qtyAsal      = (float)str_replace(',', '.', $_POST['qty_asal'] ?? 0);
    $satuan       = strtoupper(trim($_POST['satuan_input'] ?? 'KG'));
    $noRef        = trim($_POST['no_referensi'] ?? '');
    $ket          = trim($_POST['keterangan'] ?? '');

    $stmt = $koneksi->prepare("
        SELECT *
        FROM transaksi_kawat
        WHERE id_transaksi = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $idTransaksi);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        $_SESSION['error'] = 'Transaksi tidak ditemukan.';
        header("Location: master_kawat.php");
        exit;
    }

    if (($existing['source_type'] ?? '') === 'PEMBELIAN') {
        $_SESSION['error'] =
            'Transaksi pembelian otomatis tidak boleh diedit dari modul kawat. ' .
            'Perbaiki transaksi pada modul pembelian.';
        header("Location: master_kawat.php");
        exit;
    }

    if (($existing['status_transaksi'] ?? 'AKTIF') !== 'AKTIF') {
        $_SESSION['error'] = 'Transaksi VOID tidak dapat diedit.';
        header("Location: master_kawat.php");
        exit;
    }

    $map = [
        'PEMAKAIAN' => [
            'jenis_db' => 'PEMAKAIAN',
            'kondisi'  => 'BARU',
            'arah'     => 'OUT'
        ],
        'RETURN_BEKAS' => [
            'jenis_db' => 'RETURN_BEKAS',
            'kondisi'  => 'BEKAS',
            'arah'     => 'IN'
        ],
        'ROMBENG' => [
            'jenis_db' => 'ROMBENG',
            'kondisi'  => 'BEKAS',
            'arah'     => 'OUT'
        ],
        'ADJUSTMENT_MASUK_BARU' => [
            'jenis_db' => 'ADJUSTMENT_MASUK',
            'kondisi'  => 'BARU',
            'arah'     => 'IN'
        ],
        'ADJUSTMENT_MASUK_BEKAS' => [
            'jenis_db' => 'ADJUSTMENT_MASUK',
            'kondisi'  => 'BEKAS',
            'arah'     => 'IN'
        ],
        'ADJUSTMENT_KELUAR_BARU' => [
            'jenis_db' => 'ADJUSTMENT_KELUAR',
            'kondisi'  => 'BARU',
            'arah'     => 'OUT'
        ],
        'ADJUSTMENT_KELUAR_BEKAS' => [
            'jenis_db' => 'ADJUSTMENT_KELUAR',
            'kondisi'  => 'BEKAS',
            'arah'     => 'OUT'
        ],
    ];

    if (!$idKawat || !isset($map[$jenisForm]) || $qtyAsal <= 0) {
        $_SESSION['error'] = 'Data edit transaksi belum lengkap.';
        header("Location: master_kawat.php");
        exit;
    }

    if (!in_array($satuan, ['KG', 'ONS'], true)) {
        $_SESSION['error'] = 'Satuan hanya boleh KG atau ONS.';
        header("Location: master_kawat.php");
        exit;
    }

    $qtyKg = qtyToKg($qtyAsal, $satuan);

    if ($qtyKg === null || $qtyKg <= 0) {
        $_SESSION['error'] = 'Qty tidak valid.';
        header("Location: master_kawat.php");
        exit;
    }

    $jenisDb = $map[$jenisForm]['jenis_db'];
    $kondisi = $map[$jenisForm]['kondisi'];
    $arah    = $map[$jenisForm]['arah'];

    /*
     * Validasi saldo setelah transaksi lama dikeluarkan dari perhitungan.
     * Jika transaksi hasil edit adalah OUT, stok harus cukup.
     */
    if ($arah === 'OUT') {
        $saldoTersedia = getSaldoAktifExclude(
            $koneksi,
            $idKawat,
            $kondisi,
            $idTransaksi
        );

        if ($qtyKg > $saldoTersedia + 0.000001) {
            $_SESSION['error'] =
                "Perubahan tidak dapat disimpan. Stok {$kondisi} tersedia " .
                number_format($saldoTersedia, 2) . " KG / " .
                number_format($saldoTersedia * 10, 2) . " ONS.";
            header("Location: master_kawat.php");
            exit;
        }
    }

    $idSatuan = getIdSatuan($koneksi, $satuan);

    if (!$idSatuan) {
        $_SESSION['error'] = "Satuan {$satuan} belum tersedia di master_satuan.";
        header("Location: master_kawat.php");
        exit;
    }

    $stmt = $koneksi->prepare("
        UPDATE transaksi_kawat
        SET
            tanggal = ?,
            id_kawat = ?,
            jenis_mutasi = ?,
            kondisi = ?,
            arah = ?,
            qty_asal = ?,
            id_satuan_asal = ?,
            qty_kg = ?,
            no_referensi = ?,
            keterangan = ?,
            updated_by = ?,
            updated_at = NOW()
        WHERE id_transaksi = ?
          AND status_transaksi = 'AKTIF'
          AND COALESCE(source_type, '') <> 'PEMBELIAN'
    ");

    $stmt->bind_param(
        "sisssdidsssi",
        $tanggal,
        $idKawat,
        $jenisDb,
        $kondisi,
        $arah,
        $qtyAsal,
        $idSatuan,
        $qtyKg,
        $noRef,
        $ket,
        $user,
        $idTransaksi
    );

    if ($stmt->execute() && $stmt->affected_rows >= 0) {
        $_SESSION['success'] =
            'Transaksi berhasil diperbaiki. Nilai akhir: ' .
            number_format($qtyAsal, 2) . " {$satuan} = " .
            number_format($qtyKg, 2) . ' KG.';
    } else {
        $_SESSION['error'] = 'Gagal mengupdate transaksi: ' . $stmt->error;
    }

    $stmt->close();

    header("Location: master_kawat.php");
    exit;
}

/* =========================================================
   FILTER
   ========================================================= */

$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== ''
    ? $_GET['start_date']
    : date('Y-m-01');

$endDate = isset($_GET['end_date']) && $_GET['end_date'] !== ''
    ? $_GET['end_date']
    : date('Y-m-t');

$ukuranFilter = isset($_GET['ukuran']) ? trim($_GET['ukuran']) : '';
$jenisFilter  = isset($_GET['jenis']) ? trim($_GET['jenis']) : '';

$where  = [];
$params = [];
$types  = '';

$where[] = "t.tanggal BETWEEN ? AND ?";
$params[] = $startDate;
$params[] = $endDate;
$types .= 'ss';

if ($ukuranFilter !== '') {
    $where[] = "CAST(k.ukuran AS CHAR) LIKE ?";
    $params[] = '%' . str_replace(',', '.', $ukuranFilter) . '%';
    $types .= 's';
}

if ($jenisFilter !== '') {
    $where[] = "t.jenis_mutasi = ?";
    $params[] = $jenisFilter;
    $types .= 's';
}


if ($startDate > $endDate) {
    $tmp = $startDate;
    $startDate = $endDate;
    $endDate = $tmp;

    // Update parameter tanggal yang sudah dimasukkan di awal.
    $params[0] = $startDate;
    $params[1] = $endDate;
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* =========================================================
   DATA TRANSAKSI
   ========================================================= */

$sql = "
    SELECT
        t.*,
        k.ukuran,
        s.nama_satuan
    FROM transaksi_kawat t
    JOIN master_kawat k
        ON k.id_kawat = t.id_kawat
    LEFT JOIN master_satuan s
        ON s.id_satuan = t.id_satuan_asal
    $whereSql
    ORDER BY
        t.tanggal DESC,
        t.id_transaksi DESC
    LIMIT 500
";

$stmt = $koneksi->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================================================
   MASTER KAWAT UNTUK MODAL EDIT
   ========================================================= */

$masterKawat = [];
$resMaster = $koneksi->query("
    SELECT id_kawat, ukuran
    FROM master_kawat
    ORDER BY ukuran ASC
");

while ($row = $resMaster->fetch_assoc()) {
    $masterKawat[] = $row;
}

/* =========================================================
   RINGKASAN SALDO - HANYA AKTIF
   ========================================================= */

$rekap = $koneksi->query("
    SELECT
        k.id_kawat,
        k.ukuran,

        COALESCE(
            SUM(
                CASE
                    WHEN t.status_transaksi = 'AKTIF'
                     AND t.kondisi = 'BARU'
                     AND t.arah = 'IN'
                    THEN t.qty_kg

                    WHEN t.status_transaksi = 'AKTIF'
                     AND t.kondisi = 'BARU'
                     AND t.arah = 'OUT'
                    THEN -t.qty_kg

                    ELSE 0
                END
            ),
            0
        ) AS stok_baru,

        COALESCE(
            SUM(
                CASE
                    WHEN t.status_transaksi = 'AKTIF'
                     AND t.kondisi = 'BEKAS'
                     AND t.arah = 'IN'
                    THEN t.qty_kg

                    WHEN t.status_transaksi = 'AKTIF'
                     AND t.kondisi = 'BEKAS'
                     AND t.arah = 'OUT'
                    THEN -t.qty_kg

                    ELSE 0
                END
            ),
            0
        ) AS stok_bekas

    FROM master_kawat k

    LEFT JOIN transaksi_kawat t
        ON t.id_kawat = k.id_kawat

    GROUP BY
        k.id_kawat,
        k.ukuran

    ORDER BY
        k.ukuran ASC
");

$tot = $koneksi->query("
    SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN status_transaksi = 'AKTIF'
                     AND kondisi = 'BARU'
                     AND arah = 'IN'
                    THEN qty_kg

                    WHEN status_transaksi = 'AKTIF'
                     AND kondisi = 'BARU'
                     AND arah = 'OUT'
                    THEN -qty_kg

                    ELSE 0
                END
            ),
            0
        ) AS stok_baru,

        COALESCE(
            SUM(
                CASE
                    WHEN status_transaksi = 'AKTIF'
                     AND kondisi = 'BEKAS'
                     AND arah = 'IN'
                    THEN qty_kg

                    WHEN status_transaksi = 'AKTIF'
                     AND kondisi = 'BEKAS'
                     AND arah = 'OUT'
                    THEN -qty_kg

                    ELSE 0
                END
            ),
            0
        ) AS stok_bekas,

        SUM(CASE WHEN status_transaksi = 'AKTIF' THEN 1 ELSE 0 END) AS transaksi_aktif

    FROM transaksi_kawat
")->fetch_assoc();

?>
<!doctype html>
<html lang="id">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Stok Kawat - MCP System</title>

<link
    rel="icon"
    type="image/png"
    href="<?= h($base_url ?? '') ?>assets/img/logo_mcp.png"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>

<style>

body {
    background: #f4f7fb;
    font-family: 'Segoe UI', Tahoma, sans-serif;
}

.wrap {
    max-width: 1550px;
    margin: 22px auto;
}

.hero {
    background: linear-gradient(135deg, #0d6efd, #172b74);
    color: #fff;
    padding: 18px 22px;
    border-radius: 14px 14px 0 0;
}

.body-card {
    background: #fff;
    padding: 22px;
    border-radius: 0 0 14px 14px;
    box-shadow: 0 5px 18px rgba(0,0,0,.08);
}

.stat {
    border: 1px solid #e7ebf2;
    border-radius: 12px;
    padding: 16px;
    background: #fff;
    height: 100%;
}

.stat .n {
    font-size: 24px;
    font-weight: 700;
}

.stat .l {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
}

.table thead th {
    font-size: 11px;
    text-transform: uppercase;
    white-space: nowrap;
    background: #26364a;
    color: #fff;
}

.table td {
    vertical-align: middle;
    font-size: 13px;
}

.qty-in {
    color: #198754;
    font-weight: 700;
}

.qty-out {
    color: #dc3545;
    font-weight: 700;
}

.ukuran {
    font-weight: 700;
    white-space: nowrap;
}

.small-muted {
    font-size: 11px;
    color: #6c757d;
}

.saldo-box {
    background: #f8fafc;
    border: 1px solid #e6ebf2;
    border-radius: 12px;
    padding: 14px;
}

.table-responsive {
    border-radius: 10px;
}


.btn-action {
    white-space: nowrap;
}

.audit-box {
    background: #f8f9fa;
    border-left: 4px solid #6c757d;
    padding: 10px 12px;
    border-radius: 7px;
    font-size: 12px;
}

.convert-preview {
    background: #eef6ff;
    border: 1px solid #b9d7ff;
    border-radius: 8px;
    padding: 10px 12px;
}

</style>

</head>

<body>

<div class="container-fluid wrap">

    <div class="hero">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">

            <div>

                <h4 class="mb-1">
                    <i class="fas fa-boxes-stacked me-2"></i>
                    Stok Kawat Tembaga
                </h4>

                <div class="opacity-75">
                    Pembelian → Pemakaian Pabrik → Kembali Bekas → Rombeng
                </div>

            </div>


            <div class="d-flex gap-2 flex-wrap">

                <a
                    class="btn btn-light btn-sm"
                    href="master_kawat_list.php"
                >
                    <i class="fas fa-list me-1"></i>
                    Rekap Stok
                </a>

                <a
                    class="btn btn-warning btn-sm"
                    href="tambah_riwayat.php"
                >
                    <i class="fas fa-plus me-1"></i>
                    Input Mutasi
                </a>

                <a
                    class="btn btn-success btn-sm"
                    href="laporan_kawat.php?bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>"
                >
                    <i class="fas fa-file-excel me-1"></i>
                    Excel
                </a>

                <a
                    class="btn btn-outline-light btn-sm"
                    href="../../index.php"
                >
                    Home
                </a>

            </div>

        </div>

    </div>


    <div class="body-card">

        <?php if (($syncBeli['inserted'] ?? 0) > 0): ?>

            <div class="alert alert-success py-2">

                <i class="fas fa-rotate me-2"></i>

                <?= (int)$syncBeli['inserted'] ?>
                pembelian kawat baru otomatis masuk ke stok.

            </div>

        <?php endif; ?>


        <?php if (isset($_SESSION['success'])): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <?= h($_SESSION['success']) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

            <?php unset($_SESSION['success']); ?>

        <?php endif; ?>


        <?php if (isset($_SESSION['error'])): ?>

            <div class="alert alert-danger alert-dismissible fade show">

                <?= h($_SESSION['error']) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>

            </div>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>


        <!-- KPI -->
        <div class="row g-3 mb-4">

            <div class="col-md-3">

                <div class="stat">

                    <div class="n">
                        <?= number_format((float)($tot['stok_baru'] ?? 0), 2) ?> KG
                    </div>

                    <div class="l">
                        Stok Kawat Baru
                    </div>

                    <div class="small-muted">
                        <?= number_format((float)($tot['stok_baru'] ?? 0) * 10, 2) ?> ONS
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat">

                    <div class="n">
                        <?= number_format((float)($tot['stok_bekas'] ?? 0), 2) ?> KG
                    </div>

                    <div class="l">
                        Stok Kawat Bekas
                    </div>

                    <div class="small-muted">
                        <?= number_format((float)($tot['stok_bekas'] ?? 0) * 10, 2) ?> ONS
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat">

                    <?php
                    $totalFisik =
                        (float)($tot['stok_baru'] ?? 0) +
                        (float)($tot['stok_bekas'] ?? 0);
                    ?>

                    <div class="n">
                        <?= number_format($totalFisik, 2) ?> KG
                    </div>

                    <div class="l">
                        Total Fisik Kawat
                    </div>

                    <div class="small-muted">
                        <?= number_format($totalFisik * 10, 2) ?> ONS
                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stat">

                    <div class="n">
                        <?= number_format((int)($tot['transaksi_aktif'] ?? 0)) ?>
                    </div>

                    <div class="l">
                        Transaksi Aktif
                    </div>


                </div>

            </div>

        </div>


        <!-- SALDO PER UKURAN -->
        <div class="saldo-box mb-4">

            <div class="fw-bold mb-2">
                <i class="fas fa-chart-simple me-2"></i>
                Saldo per Ukuran
            </div>

            <div class="row g-2">

                <?php while ($r = $rekap->fetch_assoc()): ?>

                    <?php
                    $stokBaru  = (float)$r['stok_baru'];
                    $stokBekas = (float)$r['stok_bekas'];
                    $stokTotal = $stokBaru + $stokBekas;
                    ?>

                    <div class="col-xl-3 col-md-4 col-sm-6">

                        <div class="border rounded p-2 h-100">

                            <div class="ukuran">
                                <?= number_format((float)$r['ukuran'], 2) ?> mm
                            </div>

                            <div class="small">
                                Baru:
                                <b><?= number_format($stokBaru, 2) ?> KG</b>
                                /
                                <?= number_format($stokBaru * 10, 2) ?> ONS
                            </div>

                            <div class="small">
                                Bekas:
                                <b><?= number_format($stokBekas, 2) ?> KG</b>
                                /
                                <?= number_format($stokBekas * 10, 2) ?> ONS
                            </div>

                            <div class="small text-primary">
                                Total:
                                <b><?= number_format($stokTotal, 2) ?> KG</b>
                            </div>

                        </div>

                    </div>

                <?php endwhile; ?>

            </div>

        </div>


        <!-- FILTER -->
        <form class="row g-2 mb-3" method="get">

            <div class="col-lg-2 col-md-6">

                <label class="form-label small fw-bold mb-1">
                    Start Date
                </label>

                <input
                    type="date"
                    class="form-control"
                    name="start_date"
                    value="<?= h($startDate) ?>"
                >

            </div>


            <div class="col-lg-2 col-md-6">

                <label class="form-label small fw-bold mb-1">
                    End Date
                </label>

                <input
                    type="date"
                    class="form-control"
                    name="end_date"
                    value="<?= h($endDate) ?>"
                >

            </div>


            <div class="col-lg-2 col-md-6">

                <label class="form-label small fw-bold mb-1">
                    Ukuran
                </label>

                <input
                    class="form-control"
                    name="ukuran"
                    value="<?= h($ukuranFilter) ?>"
                    placeholder="Contoh 0.50"
                >

            </div>


            <div class="col-lg-3 col-md-6">

                <label class="form-label small fw-bold mb-1">
                    Jenis Transaksi
                </label>

                <select
                    class="form-select"
                    name="jenis"
                >

                    <option value="">
                        Semua jenis transaksi
                    </option>

                    <?php
                    foreach (
                        [
                            'PEMBELIAN',
                            'PEMAKAIAN',
                            'RETURN_BEKAS',
                            'ROMBENG',
                            'ADJUSTMENT_MASUK',
                            'ADJUSTMENT_KELUAR'
                        ] as $j
                    ):
                    ?>

                        <option
                            value="<?= h($j) ?>"
                            <?= $jenisFilter === $j ? 'selected' : '' ?>
                        >
                            <?= h(labelJenis($j)) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-lg-3 col-md-12 d-flex align-items-end gap-2">

                <button class="btn btn-primary">
                    <i class="fas fa-search me-1"></i>
                    Tampilkan
                </button>

                <a
                    class="btn btn-outline-secondary"
                    href="master_kawat.php"
                >
                    Bulan Ini
                </a>

            </div>

        </form>

        <div class="small text-muted mb-3">
            <i class="fas fa-calendar-days me-1"></i>
            Menampilkan transaksi periode
            <strong><?= date('d/m/Y', strtotime($startDate)) ?></strong>
            s/d
            <strong><?= date('d/m/Y', strtotime($endDate)) ?></strong>.
            Saldo stok di bagian atas tetap menunjukkan saldo keseluruhan saat ini.
        </div>


        <!-- TABEL TRANSAKSI -->
        <div class="table-responsive">

            <table class="table table-hover table-bordered align-middle">

                <thead>

                <tr>

                    <th>Tanggal</th>
                    <th>Ukuran</th>
                    <th>Transaksi</th>
                    <th>Kondisi</th>
                    <th>Qty Asal</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Referensi</th>
                    <th>Keterangan</th>
                    <th>User</th>
                    <th>Aksi</th>

                </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows): ?>

                    <?php while ($r = $result->fetch_assoc()): ?>

                        <?php
                        $isPembelian =
                            ($r['source_type'] ?? '') === 'PEMBELIAN';

                        $unitAsal =
                            strtoupper(trim($r['nama_satuan'] ?? 'KG'));

                        // Tentukan value dropdown edit untuk adjustment.
                        if ($r['jenis_mutasi'] === 'ADJUSTMENT_MASUK') {
                            $editJenis =
                                $r['kondisi'] === 'BARU'
                                ? 'ADJUSTMENT_MASUK_BARU'
                                : 'ADJUSTMENT_MASUK_BEKAS';
                        } elseif ($r['jenis_mutasi'] === 'ADJUSTMENT_KELUAR') {
                            $editJenis =
                                $r['kondisi'] === 'BARU'
                                ? 'ADJUSTMENT_KELUAR_BARU'
                                : 'ADJUSTMENT_KELUAR_BEKAS';
                        } else {
                            $editJenis = $r['jenis_mutasi'];
                        }
                        ?>

                        <tr>

                            <td>
                                <?= date('d/m/Y', strtotime($r['tanggal'])) ?>
                            </td>


                            <td class="ukuran">
                                <?= number_format((float)$r['ukuran'], 2) ?> mm
                            </td>


                            <td>

                                <span class="badge bg-<?= h(badgeJenis($r['jenis_mutasi'])) ?>">
                                    <?= h(labelJenis($r['jenis_mutasi'])) ?>
                                </span>

                                <?php if ($isPembelian): ?>

                                    <div class="small-muted mt-1">
                                        <i class="fas fa-link me-1"></i>
                                        Otomatis dari Pembelian
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span class="badge <?= $r['kondisi'] === 'BARU' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                    <?= h($r['kondisi']) ?>
                                </span>

                            </td>


                            <td>

                                <b>
                                    <?= number_format((float)$r['qty_asal'], 2) ?>
                                    <?= h($unitAsal) ?>
                                </b>

                                <?php if ($unitAsal === 'ONS'): ?>

                                    <div class="small-muted">
                                        = <?= number_format((float)$r['qty_kg'], 2) ?> KG
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td class="qty-in">

                                <?= $r['arah'] === 'IN'
                                    ? '+ ' . number_format((float)$r['qty_kg'], 2) . ' KG'
                                    : '-'
                                ?>

                            </td>


                            <td class="qty-out">

                                <?= $r['arah'] === 'OUT'
                                    ? '- ' . number_format((float)$r['qty_kg'], 2) . ' KG'
                                    : '-'
                                ?>

                            </td>


                            <td>

                                <?= h($r['no_referensi'] ?: '-') ?>

                                <?php if ($isPembelian): ?>

                                    <div class="small-muted">
                                        Pembelian #<?= (int)$r['source_id'] ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= h($r['keterangan'] ?: '-') ?>


                            </td>




                            <td>

                                <?= h($r['created_by'] ?: '-') ?>

                                <div class="small-muted">
                                    <?= date(
                                        'd/m/Y H:i',
                                        strtotime($r['created_at'])
                                    ) ?>
                                </div>

                                <?php if ($r['updated_by']): ?>

                                    <div class="small-muted mt-1">
                                        Edit:
                                        <?= h($r['updated_by']) ?>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if ($isPembelian): ?>

                                    <button
                                        type="button"
                                        class="btn btn-outline-secondary btn-sm btn-action"
                                        disabled
                                        title="Perbaiki dari modul pembelian"
                                    >
                                        <i class="fas fa-lock me-1"></i>
                                        Pembelian
                                    </button>

                                <?php else: ?>

                                    <button
                                        type="button"
                                        class="btn btn-warning btn-sm btn-action"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= (int)$r['id_transaksi'] ?>"
                                    >
                                        <i class="fas fa-pen me-1"></i>
                                        Edit
                                    </button>

                                <?php endif; ?>

                            </td>

                        </tr>


                        <?php if (!$isPembelian): ?>

                        <!-- =============================================
                             MODAL EDIT
                             ============================================= -->
                        <div
                            class="modal fade"
                            id="editModal<?= (int)$r['id_transaksi'] ?>"
                            tabindex="-1"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <form method="post">

                                        <div class="modal-header bg-warning">

                                            <h5 class="modal-title">
                                                <i class="fas fa-pen me-2"></i>
                                                Edit Transaksi Kawat
                                            </h5>

                                            <button
                                                type="button"
                                                class="btn-close"
                                                data-bs-dismiss="modal"
                                            ></button>

                                        </div>


                                        <div class="modal-body">

                                            <input
                                                type="hidden"
                                                name="id_transaksi"
                                                value="<?= (int)$r['id_transaksi'] ?>"
                                            >


                                            <div class="alert alert-warning py-2">

                                                <i class="fas fa-circle-info me-1"></i>

                                                Edit digunakan untuk memperbaiki
                                                <strong>salah input</strong>.
                                                Adjustment tidak diperlukan jika transaksi asli masih dapat diperbaiki.

                                            </div>


                                            <div class="row g-3">

                                                <div class="col-md-6">

                                                    <label class="form-label fw-bold">
                                                        Tanggal
                                                    </label>

                                                    <input
                                                        type="date"
                                                        name="tanggal"
                                                        class="form-control"
                                                        value="<?= h($r['tanggal']) ?>"
                                                        required
                                                    >

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label fw-bold">
                                                        Ukuran Kawat
                                                    </label>

                                                    <select
                                                        name="id_kawat"
                                                        class="form-select"
                                                        required
                                                    >

                                                        <?php foreach ($masterKawat as $mk): ?>

                                                            <option
                                                                value="<?= (int)$mk['id_kawat'] ?>"
                                                                <?= (int)$mk['id_kawat'] === (int)$r['id_kawat']
                                                                    ? 'selected'
                                                                    : ''
                                                                ?>
                                                            >
                                                                <?= number_format((float)$mk['ukuran'], 2) ?> mm
                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>

                                                </div>


                                                <div class="col-12">

                                                    <label class="form-label fw-bold">
                                                        Jenis Transaksi
                                                    </label>

                                                    <select
                                                        name="jenis_mutasi"
                                                        class="form-select"
                                                        required
                                                    >

                                                        <option
                                                            value="PEMAKAIAN"
                                                            <?= $editJenis === 'PEMAKAIAN'
                                                                ? 'selected'
                                                                : ''
                                                            ?>
                                                        >
                                                            Dipakai Pabrik
                                                        </option>

                                                        <option
                                                            value="RETURN_BEKAS"
                                                            <?= $editJenis === 'RETURN_BEKAS'
                                                                ? 'selected'
                                                                : ''
                                                            ?>
                                                        >
                                                            Kembali dari Pabrik
                                                        </option>

                                                        <option
                                                            value="ROMBENG"
                                                            <?= $editJenis === 'ROMBENG'
                                                                ? 'selected'
                                                                : ''
                                                            ?>
                                                        >
                                                            Rombeng / Jual Kawat Bekas
                                                        </option>

                                                        <optgroup label="Adjustment Stok">

                                                            <option
                                                                value="ADJUSTMENT_MASUK_BARU"
                                                                <?= $editJenis === 'ADJUSTMENT_MASUK_BARU'
                                                                    ? 'selected'
                                                                    : ''
                                                                ?>
                                                            >
                                                                Adjustment Masuk - Baru
                                                            </option>

                                                            <option
                                                                value="ADJUSTMENT_MASUK_BEKAS"
                                                                <?= $editJenis === 'ADJUSTMENT_MASUK_BEKAS'
                                                                    ? 'selected'
                                                                    : ''
                                                                ?>
                                                            >
                                                                Adjustment Masuk - Bekas
                                                            </option>

                                                            <option
                                                                value="ADJUSTMENT_KELUAR_BARU"
                                                                <?= $editJenis === 'ADJUSTMENT_KELUAR_BARU'
                                                                    ? 'selected'
                                                                    : ''
                                                                ?>
                                                            >
                                                                Adjustment Keluar - Baru
                                                            </option>

                                                            <option
                                                                value="ADJUSTMENT_KELUAR_BEKAS"
                                                                <?= $editJenis === 'ADJUSTMENT_KELUAR_BEKAS'
                                                                    ? 'selected'
                                                                    : ''
                                                                ?>
                                                            >
                                                                Adjustment Keluar - Bekas
                                                            </option>

                                                        </optgroup>

                                                    </select>

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label fw-bold">
                                                        Jumlah
                                                    </label>

                                                    <input
                                                        type="number"
                                                        step="0.0001"
                                                        min="0.0001"
                                                        name="qty_asal"
                                                        class="form-control edit-qty"
                                                        value="<?= h($r['qty_asal']) ?>"
                                                        required
                                                    >

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label fw-bold">
                                                        Satuan
                                                    </label>

                                                    <select
                                                        name="satuan_input"
                                                        class="form-select edit-unit"
                                                        required
                                                    >

                                                        <option
                                                            value="KG"
                                                            <?= $unitAsal === 'KG'
                                                                ? 'selected'
                                                                : ''
                                                            ?>
                                                        >
                                                            KG
                                                        </option>

                                                        <option
                                                            value="ONS"
                                                            <?= $unitAsal === 'ONS'
                                                                ? 'selected'
                                                                : ''
                                                            ?>
                                                        >
                                                            ONS
                                                        </option>

                                                    </select>

                                                </div>


                                                <div class="col-12">

                                                    <div class="convert-preview">

                                                        <div class="small text-muted">
                                                            Konversi otomatis
                                                        </div>

                                                        <div class="fw-bold edit-conversion">
                                                            <?= number_format((float)$r['qty_kg'], 2) ?> KG
                                                        </div>

                                                    </div>

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label fw-bold">
                                                        No. Referensi
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="no_referensi"
                                                        class="form-control"
                                                        value="<?= h($r['no_referensi']) ?>"
                                                    >

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="form-label fw-bold">
                                                        Keterangan
                                                    </label>

                                                    <input
                                                        type="text"
                                                        name="keterangan"
                                                        class="form-control"
                                                        value="<?= h($r['keterangan']) ?>"
                                                    >

                                                </div>

                                            </div>


                                            <div class="audit-box mt-3">

                                                <b>Histori:</b><br>

                                                Dibuat:
                                                <?= h($r['created_by'] ?: '-') ?>
                                                |
                                                <?= date(
                                                    'd/m/Y H:i',
                                                    strtotime($r['created_at'])
                                                ) ?>

                                                <?php if ($r['updated_by']): ?>

                                                    <br>

                                                    Terakhir diedit:
                                                    <?= h($r['updated_by']) ?>
                                                    |
                                                    <?= $r['updated_at']
                                                        ? date(
                                                            'd/m/Y H:i',
                                                            strtotime($r['updated_at'])
                                                        )
                                                        : '-'
                                                    ?>

                                                <?php endif; ?>

                                            </div>

                                        </div>


                                        <div class="modal-footer">

                                            <button
                                                type="button"
                                                class="btn btn-secondary"
                                                data-bs-dismiss="modal"
                                            >
                                                Batal
                                            </button>

                                            <button
                                                type="submit"
                                                name="update_transaksi"
                                                class="btn btn-warning"
                                                onclick="return confirm('Simpan perubahan transaksi ini?')"
                                            >
                                                <i class="fas fa-save me-1"></i>
                                                Simpan Perubahan
                                            </button>

                                        </div>

                                    </form>

                                </div>

                            </div>

                        </div>


                        <?php endif; ?>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="11"
                            class="text-center text-muted py-4"
                        >
                            Belum ada transaksi kawat.
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

<script>

/**
 * Live conversion pada setiap modal edit.
 */
document.querySelectorAll('.modal').forEach(function(modal) {

    const qty = modal.querySelector('.edit-qty');
    const unit = modal.querySelector('.edit-unit');
    const preview = modal.querySelector('.edit-conversion');

    if (!qty || !unit || !preview) {
        return;
    }

    function refreshConversion() {

        const q = parseFloat(qty.value || 0);
        const u = unit.value;

        let kg = q;

        if (u === 'ONS') {
            kg = q * 0.1;
        }

        preview.textContent =
            new Intl.NumberFormat(
                'id-ID',
                {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }
            ).format(kg) + ' KG';

    }

    qty.addEventListener('input', refreshConversion);
    unit.addEventListener('change', refreshConversion);

});

</script>

</body>
</html>