<?php
session_start();

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/oli_helper.php';

if (($_SESSION['status'] ?? '') !== 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$user = $_SESSION['username'] ?? 'system';

function h($v)
{
    return htmlspecialchars(
        (string)$v,
        ENT_QUOTES,
        'UTF-8'
    );
}

function labelMutasi($jenis)
{
    return [
        'STOK_AWAL' => 'Stok Awal',
        'PEMBELIAN' => 'Pembelian',
        'PEMAKAIAN' => 'Pemakaian Operasional'
    ][$jenis] ?? $jenis;
}

$syncMaster = oli_sync_master_barang($koneksi, $user);
$syncBeli   = oli_sync_pembelian($koneksi, $user);

/* =========================================================
   EDIT TRANSAKSI MANUAL
   ========================================================= */

if (isset($_POST['update_transaksi'])) {

    $idRiwayat  = (int)($_POST['id_riwayat'] ?? 0);
    $tanggal     = $_POST['tanggal'] ?? date('Y-m-d');
    $idOli       = (int)($_POST['id_oli'] ?? 0);
    $jenisMutasi = $_POST['jenis_mutasi'] ?? '';
    $jumlah      = (float)str_replace(',', '.', $_POST['jumlah'] ?? 0);
    $tujuanTipe  = $_POST['tujuan_tipe'] ?? null;
    $tujuanNama  = trim($_POST['tujuan_nama'] ?? '');
    $noRef       = trim($_POST['no_referensi'] ?? '');
    $ket         = trim($_POST['keterangan'] ?? '');

    $stmt = $koneksi->prepare("
        SELECT *
        FROM riwayat_oli
        WHERE id_riwayat = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $idRiwayat);
    $stmt->execute();

    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$existing) {
        $_SESSION['error'] = 'Transaksi tidak ditemukan.';
        header("Location: master_oli.php");
        exit;
    }

    if (($existing['source_type'] ?? '') === 'PEMBELIAN') {
        $_SESSION['error'] =
            'Transaksi pembelian otomatis tidak boleh diedit dari modul oli.';
        header("Location: master_oli.php");
        exit;
    }

    if (!in_array(
        $jenisMutasi,
        ['STOK_AWAL', 'PEMAKAIAN'],
        true
    ) || !$idOli || $jumlah <= 0) {
        $_SESSION['error'] = 'Data edit belum lengkap.';
        header("Location: master_oli.php");
        exit;
    }

    if ($jenisMutasi === 'STOK_AWAL') {

        if (oli_has_stok_awal(
            $koneksi,
            $idOli,
            $idRiwayat
        )) {
            $_SESSION['error'] =
                'Stok awal untuk oli tersebut sudah ada.';
            header("Location: master_oli.php");
            exit;
        }

        $jenisTransaksi = 'MASUK';
        $tujuanTipe = null;
        $tujuanNama = '';

    } else {

        $jenisTransaksi = 'KELUAR';

        $saldoTanpaTransaksi =
            oli_get_saldo(
                $koneksi,
                $idOli,
                $idRiwayat
            );

        if ($jumlah > $saldoTanpaTransaksi + 0.000001) {
            $_SESSION['error'] =
                'Jumlah pemakaian melebihi stok tersedia. ' .
                'Stok tersedia: ' .
                number_format($saldoTanpaTransaksi, 2) .
                ' Liter.';
            header("Location: master_oli.php");
            exit;
        }

        if (!in_array(
            $tujuanTipe,
            ['MESIN', 'KENDARAAN', 'LAINNYA'],
            true
        ) || $tujuanNama === '') {
            $_SESSION['error'] =
                'Tujuan pemakaian wajib diisi.';
            header("Location: master_oli.php");
            exit;
        }
    }

    $stmt = $koneksi->prepare("
        UPDATE riwayat_oli
        SET
            id_oli = ?,
            tanggal = ?,
            jenis_mutasi = ?,
            jenis_transaksi = ?,
            jumlah = ?,
            no_referensi = ?,
            tujuan_tipe = ?,
            tujuan_nama = ?,
            keterangan = ?,
            updated_by = ?,
            updated_at = NOW()
        WHERE id_riwayat = ?
          AND COALESCE(source_type, '') <> 'PEMBELIAN'
    ");

    $stmt->bind_param(
        "isssdsssssi",
        $idOli,
        $tanggal,
        $jenisMutasi,
        $jenisTransaksi,
        $jumlah,
        $noRef,
        $tujuanTipe,
        $tujuanNama,
        $ket,
        $user,
        $idRiwayat
    );

    if ($stmt->execute()) {
        $_SESSION['success'] =
            'Transaksi oli berhasil diperbaiki.';
    } else {
        $_SESSION['error'] =
            'Gagal mengupdate: ' .
            $stmt->error;
    }

    $stmt->close();

    header("Location: master_oli.php");
    exit;
}

/* =========================================================
   FILTER DEFAULT BULAN BERJALAN
   ========================================================= */

$startDate =
    isset($_GET['start_date']) &&
    $_GET['start_date'] !== ''
    ? $_GET['start_date']
    : date('Y-m-01');

$endDate =
    isset($_GET['end_date']) &&
    $_GET['end_date'] !== ''
    ? $_GET['end_date']
    : date('Y-m-t');

$idOliFilter =
    isset($_GET['id_oli'])
    ? (int)$_GET['id_oli']
    : 0;

$jenisFilter =
    isset($_GET['jenis'])
    ? trim($_GET['jenis'])
    : '';

if ($startDate > $endDate) {
    [$startDate, $endDate] =
        [$endDate, $startDate];
}

$where = [
    "r.tanggal BETWEEN ? AND ?"
];

$params = [
    $startDate,
    $endDate
];

$types = 'ss';

if ($idOliFilter > 0) {
    $where[] = "r.id_oli = ?";
    $params[] = $idOliFilter;
    $types .= 'i';
}

if ($jenisFilter !== '') {
    $where[] = "r.jenis_mutasi = ?";
    $params[] = $jenisFilter;
    $types .= 's';
}

$whereSql =
    'WHERE ' .
    implode(' AND ', $where);

$sql = "
    SELECT
        r.*,
        o.nama_oli
    FROM riwayat_oli r

    JOIN master_oli o
        ON o.id_oli = r.id_oli

    $whereSql

    ORDER BY
        r.tanggal DESC,
        r.id_riwayat DESC

    LIMIT 500
";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();

/* =========================================================
   MASTER OLI + SALDO
   ========================================================= */

$masterOli = [];

$resMaster = $koneksi->query("
    SELECT
        o.id_oli,
        o.nama_oli,

        COALESCE(
            SUM(
                CASE
                    WHEN r.status_transaksi = 'AKTIF'
                     AND r.jenis_transaksi = 'MASUK'
                    THEN r.jumlah

                    WHEN r.status_transaksi = 'AKTIF'
                     AND r.jenis_transaksi = 'KELUAR'
                    THEN -r.jumlah

                    ELSE 0
                END
            ),
            0
        ) AS saldo

    FROM master_oli o

    LEFT JOIN riwayat_oli r
        ON r.id_oli = o.id_oli

    GROUP BY
        o.id_oli,
        o.nama_oli

    ORDER BY
        o.nama_oli
");

$totalStok = 0;

while ($row = $resMaster->fetch_assoc()) {
    $masterOli[] = $row;
    $totalStok += (float)$row['saldo'];
}

$totalPemakaianBulan = 0;

$stmtPakai = $koneksi->prepare("
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM riwayat_oli
    WHERE jenis_mutasi = 'PEMAKAIAN'
      AND status_transaksi = 'AKTIF'
      AND tanggal BETWEEN ? AND ?
");
$stmtPakai->bind_param(
    "ss",
    $startDate,
    $endDate
);
$stmtPakai->execute();

$totalPemakaianBulan =
    (float)(
        $stmtPakai
            ->get_result()
            ->fetch_assoc()['total']
        ?? 0
    );

$stmtPakai->close();
?>
<!doctype html>
<html lang="id">

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Stok Oli</title>

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
}

.wrap {
    max-width: 1500px;
    margin: 22px auto;
}

.hero {
    background: linear-gradient(135deg, #d97706, #92400e);
    color: white;
    padding: 18px 22px;
    border-radius: 14px 14px 0 0;
}

.body-card {
    background: white;
    padding: 22px;
    border-radius: 0 0 14px 14px;
    box-shadow: 0 5px 18px rgba(0,0,0,.08);
}

.stat {
    border: 1px solid #ececec;
    border-radius: 12px;
    padding: 15px;
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
    background: #3f3f46;
    color: white;
    font-size: 11px;
    text-transform: uppercase;
    white-space: nowrap;
}

.table td {
    vertical-align: middle;
    font-size: 13px;
}

.saldo-card {
    background: #fff8e8;
    border: 1px solid #f5d28b;
    border-radius: 10px;
    padding: 12px;
}

</style>

</head>


<body>

<div class="container-fluid wrap">

    <div class="hero">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>

                <h4 class="mb-1">
                    <i class="fas fa-oil-can me-2"></i>
                    Stok Oli
                </h4>

                <div class="opacity-75">
                    Pembelian → Stok → Pemakaian Mesin / Kendaraan
                </div>

            </div>


            <div class="d-flex gap-2">

                <a
                    href="master_oli_list.php"
                    class="btn btn-light btn-sm"
                >
                    Rekap Stok
                </a>

                <a
                    href="tambah_riwayat_oli.php"
                    class="btn btn-warning btn-sm"
                >
                    Input Mutasi
                </a>

                <a
                    href="laporan_oli.php?bulan=<?= date('m') ?>&tahun=<?= date('Y') ?>"
                    class="btn btn-success btn-sm"
                >
                    Excel
                </a>

                <a
                    href="../../index.php"
                    class="btn btn-outline-light btn-sm"
                >
                    Home
                </a>

            </div>

        </div>

    </div>


    <div class="body-card">

        <?php if (($syncBeli['inserted'] ?? 0) > 0): ?>

            <div class="alert alert-success">
                <?= (int)$syncBeli['inserted'] ?>
                pembelian oli otomatis masuk ke stok.
            </div>

        <?php endif; ?>


        <?php if (isset($_SESSION['success'])): ?>

            <div class="alert alert-success">
                <?= h($_SESSION['success']) ?>
            </div>

            <?php unset($_SESSION['success']); ?>

        <?php endif; ?>


        <?php if (isset($_SESSION['error'])): ?>

            <div class="alert alert-danger">
                <?= h($_SESSION['error']) ?>
            </div>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>


        <div class="row g-3 mb-4">

            <div class="col-md-4">

                <div class="stat">

                    <div class="n">
                        <?= number_format($totalStok, 2) ?> L
                    </div>

                    <div class="l">
                        Total Stok Oli
                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat">

                    <div class="n">
                        <?= count($masterOli) ?>
                    </div>

                    <div class="l">
                        Jenis Oli
                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="stat">

                    <div class="n">
                        <?= number_format($totalPemakaianBulan, 2) ?> L
                    </div>

                    <div class="l">
                        Pemakaian Periode Filter
                    </div>

                </div>

            </div>

        </div>


        <div class="row g-2 mb-4">

            <?php foreach ($masterOli as $o): ?>

                <div class="col-md-4">

                    <div class="saldo-card">

                        <div class="fw-bold">
                            <?= h($o['nama_oli']) ?>
                        </div>

                        <div style="font-size:22px;font-weight:700;">
                            <?= number_format((float)$o['saldo'], 2) ?> Liter
                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <form
            class="row g-2 mb-3"
            method="get"
        >

            <div class="col-lg-2 col-md-6">

                <label class="small fw-bold">
                    Start Date
                </label>

                <input
                    type="date"
                    name="start_date"
                    class="form-control"
                    value="<?= h($startDate) ?>"
                >

            </div>


            <div class="col-lg-2 col-md-6">

                <label class="small fw-bold">
                    End Date
                </label>

                <input
                    type="date"
                    name="end_date"
                    class="form-control"
                    value="<?= h($endDate) ?>"
                >

            </div>


            <div class="col-lg-3 col-md-6">

                <label class="small fw-bold">
                    Jenis Oli
                </label>

                <select
                    name="id_oli"
                    class="form-select"
                >

                    <option value="">
                        Semua Oli
                    </option>

                    <?php foreach ($masterOli as $o): ?>

                        <option
                            value="<?= (int)$o['id_oli'] ?>"
                            <?= $idOliFilter === (int)$o['id_oli']
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= h($o['nama_oli']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-lg-3 col-md-6">

                <label class="small fw-bold">
                    Jenis Transaksi
                </label>

                <select
                    name="jenis"
                    class="form-select"
                >

                    <option value="">
                        Semua
                    </option>

                    <?php
                    foreach (
                        ['STOK_AWAL','PEMBELIAN','PEMAKAIAN']
                        as $j
                    ):
                    ?>

                        <option
                            value="<?= h($j) ?>"
                            <?= $jenisFilter === $j
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= h(labelMutasi($j)) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="col-lg-2 d-flex align-items-end gap-2">

                <button class="btn btn-warning">
                    Tampilkan
                </button>

                <a
                    href="master_oli.php"
                    class="btn btn-outline-secondary"
                >
                    Bulan Ini
                </a>

            </div>

        </form>


        <div class="small text-muted mb-3">
            Menampilkan transaksi
            <?= date('d/m/Y', strtotime($startDate)) ?>
            s/d
            <?= date('d/m/Y', strtotime($endDate)) ?>.
            Saldo bagian atas tetap saldo keseluruhan saat ini.
        </div>


        <div class="table-responsive">

            <table class="table table-bordered table-hover">

                <thead>

                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Oli</th>
                    <th>Transaksi</th>
                    <th>Masuk</th>
                    <th>Keluar</th>
                    <th>Tujuan</th>
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
                        ?>

                        <tr>

                            <td>
                                <?= date('d/m/Y', strtotime($r['tanggal'])) ?>
                            </td>

                            <td>
                                <b><?= h($r['nama_oli']) ?></b>
                            </td>

                            <td>
                                <?= h(labelMutasi($r['jenis_mutasi'])) ?>
                            </td>

                            <td class="text-success fw-bold">
                                <?= $r['jenis_transaksi'] === 'MASUK'
                                    ? '+ ' . number_format((float)$r['jumlah'], 2) . ' L'
                                    : '-'
                                ?>
                            </td>

                            <td class="text-danger fw-bold">
                                <?= $r['jenis_transaksi'] === 'KELUAR'
                                    ? '- ' . number_format((float)$r['jumlah'], 2) . ' L'
                                    : '-'
                                ?>
                            </td>

                            <td>
                                <?php if ($r['jenis_mutasi'] === 'PEMAKAIAN'): ?>
                                    <?= h($r['tujuan_tipe'] ?: '-') ?>
                                    <br>
                                    <small><?= h($r['tujuan_nama'] ?: '-') ?></small>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= h($r['no_referensi'] ?: '-') ?>
                            </td>

                            <td>
                                <?= h($r['keterangan'] ?: '-') ?>
                            </td>

                            <td>
                                <?= h($r['created_by'] ?: '-') ?>
                            </td>

                            <td>

                                <?php if ($isPembelian): ?>

                                    <button
                                        class="btn btn-outline-secondary btn-sm"
                                        disabled
                                    >
                                        Pembelian
                                    </button>

                                <?php else: ?>

                                    <button
                                        class="btn btn-warning btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= (int)$r['id_riwayat'] ?>"
                                    >
                                        Edit
                                    </button>

                                <?php endif; ?>

                            </td>

                        </tr>


                        <?php if (!$isPembelian): ?>

                        <div
                            class="modal fade"
                            id="editModal<?= (int)$r['id_riwayat'] ?>"
                            tabindex="-1"
                        >

                            <div class="modal-dialog modal-lg">

                                <div class="modal-content">

                                    <form method="post">

                                        <div class="modal-header bg-warning">

                                            <h5 class="modal-title">
                                                Edit Transaksi Oli
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
                                                name="id_riwayat"
                                                value="<?= (int)$r['id_riwayat'] ?>"
                                            >

                                            <div class="row g-3">

                                                <div class="col-md-6">

                                                    <label class="fw-bold">
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

                                                    <label class="fw-bold">
                                                        Jenis Oli
                                                    </label>

                                                    <select
                                                        name="id_oli"
                                                        class="form-select"
                                                        required
                                                    >

                                                        <?php foreach ($masterOli as $o): ?>

                                                            <option
                                                                value="<?= (int)$o['id_oli'] ?>"
                                                                <?= (int)$r['id_oli'] === (int)$o['id_oli']
                                                                    ? 'selected'
                                                                    : ''
                                                                ?>
                                                            >
                                                                <?= h($o['nama_oli']) ?>
                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="fw-bold">
                                                        Transaksi
                                                    </label>

                                                    <select
                                                        name="jenis_mutasi"
                                                        class="form-select editJenis"
                                                        required
                                                    >

                                                        <option
                                                            value="STOK_AWAL"
                                                            <?= $r['jenis_mutasi'] === 'STOK_AWAL'
                                                                ? 'selected'
                                                                : ''
                                                            ?>
                                                        >
                                                            Stok Awal
                                                        </option>

                                                        <option
                                                            value="PEMAKAIAN"
                                                            <?= $r['jenis_mutasi'] === 'PEMAKAIAN'
                                                                ? 'selected'
                                                                : ''
                                                            ?>
                                                        >
                                                            Pemakaian Operasional
                                                        </option>

                                                    </select>

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="fw-bold">
                                                        Jumlah (Liter)
                                                    </label>

                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        name="jumlah"
                                                        class="form-control"
                                                        value="<?= h($r['jumlah']) ?>"
                                                        required
                                                    >

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="fw-bold">
                                                        Tujuan
                                                    </label>

                                                    <select
                                                        name="tujuan_tipe"
                                                        class="form-select"
                                                    >

                                                        <option value="">
                                                            -
                                                        </option>

                                                        <?php foreach (['MESIN','KENDARAAN','LAINNYA'] as $t): ?>

                                                            <option
                                                                value="<?= $t ?>"
                                                                <?= $r['tujuan_tipe'] === $t
                                                                    ? 'selected'
                                                                    : ''
                                                                ?>
                                                            >
                                                                <?= $t ?>
                                                            </option>

                                                        <?php endforeach; ?>

                                                    </select>

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="fw-bold">
                                                        Nama Tujuan
                                                    </label>

                                                    <input
                                                        name="tujuan_nama"
                                                        class="form-control"
                                                        value="<?= h($r['tujuan_nama']) ?>"
                                                    >

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="fw-bold">
                                                        Referensi
                                                    </label>

                                                    <input
                                                        name="no_referensi"
                                                        class="form-control"
                                                        value="<?= h($r['no_referensi']) ?>"
                                                    >

                                                </div>


                                                <div class="col-md-6">

                                                    <label class="fw-bold">
                                                        Keterangan
                                                    </label>

                                                    <input
                                                        name="keterangan"
                                                        class="form-control"
                                                        value="<?= h($r['keterangan']) ?>"
                                                    >

                                                </div>

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
                                                name="update_transaksi"
                                                class="btn btn-warning"
                                            >
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
                            colspan="10"
                            class="text-center text-muted py-4"
                        >
                            Tidak ada transaksi pada periode ini.
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

</body>
</html>
