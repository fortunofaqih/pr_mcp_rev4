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

// Sinkronisasi master dan pembelian kawat.
kawat_sync_master_barang($koneksi, $user);
kawat_sync_pembelian($koneksi, $user);

/**
 * Konversi satuan input user menjadi KG.
 */
function qtyToKg(float $qty, string $satuan): ?float
{
    $satuan = strtoupper(trim($satuan));

    return match ($satuan) {
        'KG'  => $qty,
        'ONS' => $qty * 0.1,
        default => null
    };
}

/**
 * Ambil ID satuan dari master_satuan.
 */
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
    $idKawat   = (int)($_POST['id_kawat'] ?? 0);
    $jenis     = $_POST['jenis_mutasi'] ?? '';
    $qtyAsal   = (float)str_replace(',', '.', $_POST['qty_asal'] ?? 0);
    $satuan    = strtoupper(trim($_POST['satuan_input'] ?? 'KG'));
    $noRef     = trim($_POST['no_referensi'] ?? '');
    $ket       = trim($_POST['keterangan'] ?? '');

    // Mapping transaksi operasional.
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

        // Adjustment tetap tersedia, tetapi diletakkan pada kelompok khusus.
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

    if (!$idKawat || !isset($map[$jenis]) || $qtyAsal <= 0) {
        $_SESSION['error'] = 'Data mutasi belum lengkap.';
        header("Location: tambah_riwayat.php");
        exit;
    }

    if (!in_array($satuan, ['KG', 'ONS'], true)) {
        $_SESSION['error'] = 'Satuan hanya boleh KG atau ONS.';
        header("Location: tambah_riwayat.php");
        exit;
    }

    $qtyKg = qtyToKg($qtyAsal, $satuan);

    if ($qtyKg === null || $qtyKg <= 0) {
        $_SESSION['error'] = 'Qty tidak valid.';
        header("Location: tambah_riwayat.php");
        exit;
    }

    $jenisDb = $map[$jenis]['jenis_db'];
    $kondisi = $map[$jenis]['kondisi'];
    $arah    = $map[$jenis]['arah'];

    // Validasi saldo jika transaksi keluar.
    if ($arah === 'OUT') {
        $saldoKg = kawat_get_saldo($koneksi, $idKawat, $kondisi);

        if ($qtyKg > $saldoKg + 0.000001) {
            $saldoOns = $saldoKg * 10;

            $_SESSION['error'] =
                "Qty keluar melebihi stok {$kondisi}. " .
                "Stok tersedia: " .
                number_format($saldoKg, 2) . " KG / " .
                number_format($saldoOns, 2) . " ONS.";

            header("Location: tambah_riwayat.php");
            exit;
        }
    }

    $idSatuan = getIdSatuan($koneksi, $satuan);

    if (!$idSatuan) {
        $_SESSION['error'] = "Satuan {$satuan} belum tersedia di master_satuan.";
        header("Location: tambah_riwayat.php");
        exit;
    }

    // Tambahkan penjelasan otomatis supaya histori lebih mudah dibaca.
    if ($jenisDb === 'ADJUSTMENT_MASUK' || $jenisDb === 'ADJUSTMENT_KELUAR') {
        $ket = trim(
            '[ADJUSTMENT STOK] ' .
            ($ket !== '' ? $ket : 'Koreksi hasil stok fisik')
        );
    }

    $stmt = $koneksi->prepare("
        INSERT INTO transaksi_kawat
        (
            tanggal,
            id_kawat,
            jenis_mutasi,
            kondisi,
            arah,
            qty_asal,
            id_satuan_asal,
            qty_kg,
            source_type,
            source_id,
            no_referensi,
            keterangan,
            created_by
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?,
            'MANUAL', NULL, ?, ?, ?
        )
    ");

    $stmt->bind_param(
        "sisssdidsss",
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
        $user
    );

    if ($stmt->execute()) {
        $_SESSION['success'] =
            "Mutasi kawat berhasil disimpan. " .
            number_format($qtyAsal, 2) . " {$satuan} = " .
            number_format($qtyKg, 2) . " KG.";

        header("Location: master_kawat.php");
        exit;
    }

    $_SESSION['error'] = 'Gagal menyimpan: ' . $stmt->error;
    $stmt->close();

    header("Location: tambah_riwayat.php");
    exit;
}

// Data kawat + saldo BARU/BEKAS.
$kawat = $koneksi->query("
    SELECT
        k.id_kawat,
        k.ukuran,

        COALESCE(
            SUM(
                CASE
                    WHEN t.kondisi = 'BARU' AND t.arah = 'IN'  THEN t.qty_kg
                    WHEN t.kondisi = 'BARU' AND t.arah = 'OUT' THEN -t.qty_kg
                    ELSE 0
                END
            ),
            0
        ) AS stok_baru,

        COALESCE(
            SUM(
                CASE
                    WHEN t.kondisi = 'BEKAS' AND t.arah = 'IN'  THEN t.qty_kg
                    WHEN t.kondisi = 'BEKAS' AND t.arah = 'OUT' THEN -t.qty_kg
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

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Input Mutasi Kawat - MCP System</title>

<link rel="icon" type="image/png" href="<?= h($base_url ?? '') ?>assets/img/logo_mcp.png">

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
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.wrap {
    max-width: 900px;
    margin: 30px auto;
}

.head {
    background: linear-gradient(135deg, #0d6efd, #172b74);
    color: #fff;
    padding: 20px 24px;
    border-radius: 14px 14px 0 0;
}

.cardx {
    background: #fff;
    padding: 26px;
    border-radius: 0 0 14px 14px;
    box-shadow: 0 5px 18px rgba(0,0,0,.08);
}

.form-label {
    font-weight: 600;
    color: #34495e;
}

.balance {
    background: #f8fafc;
    border: 1px solid #e3e8ef;
    border-radius: 10px;
    padding: 14px 16px;
}

.convert-card {
    background: #eef6ff;
    border: 1px solid #b9d7ff;
    border-left: 5px solid #0d6efd;
    border-radius: 10px;
    padding: 14px 16px;
}

.convert-main {
    font-size: 21px;
    font-weight: 700;
    color: #0d6efd;
}

.convert-sub {
    font-size: 12px;
    color: #6c757d;
}

.hint {
    font-size: 12px;
    color: #6c757d;
}

.transaction-info {
    border-radius: 10px;
    padding: 12px 14px;
    display: none;
}

.transaction-info.active {
    display: block;
}

.transaction-primary {
    background: #e8f1ff;
    border-left: 4px solid #0d6efd;
}

.transaction-warning {
    background: #fff5dc;
    border-left: 4px solid #f39c12;
}

.transaction-success {
    background: #eaf8ee;
    border-left: 4px solid #198754;
}

.transaction-danger {
    background: #fdecec;
    border-left: 4px solid #dc3545;
}

.adjustment-box {
    background: #f8f9fa;
    border: 1px dashed #adb5bd;
    border-radius: 10px;
    padding: 12px 14px;
}

.section-title {
    font-size: 12px;
    font-weight: 700;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.quick-unit {
    display: flex;
    gap: 8px;
    margin-top: 7px;
}

.quick-unit button {
    min-width: 75px;
}

.stock-number {
    font-size: 18px;
    font-weight: 700;
}

.stock-baru {
    color: #198754;
}

.stock-bekas {
    color: #f39c12;
}

@media(max-width:768px) {
    .wrap {
        margin: 12px auto;
    }
}
</style>
</head>

<body>

<div class="container wrap">

    <div class="head">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>
                <h5 class="mb-1">
                    <i class="fas fa-right-left me-2"></i>
                    Input Mutasi Kawat
                </h5>

                <div class="opacity-75">
                    Input bisa menggunakan KG atau ONS. Sistem otomatis menyimpan saldo dalam KG.
                </div>
            </div>

            <a href="master_kawat.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left me-1"></i>
                Kembali
            </a>

        </div>
    </div>

    <div class="cardx">

        <?php if (isset($_SESSION['error'])): ?>

            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= h($_SESSION['error']) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>
            </div>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>


        <div class="alert alert-info">
            <i class="fas fa-circle-info me-2"></i>
            Pembelian dengan nama barang
            <strong>KAWAT</strong>
            akan masuk otomatis dari tabel pembelian.
        </div>
        <div class="alert alert-info">
            <i class="fas fa-circle-info me-2"></i>
            Adjustment stok hanya digunakan untuk koreksi hasil stok fisik. <strong>Jangan</strong> digunakan untuk mengganti transaksi normal.
            Contoh Fisik 5 KG, sistem 4,80 KG, gunakan Adjustment Keluar 0,20 KG.
        </div>


        <form method="post" id="formMutasi">

            <div class="row g-3">

                <!-- Tanggal -->
                <div class="col-md-6">

                    <label class="form-label">
                        Tanggal Transaksi
                    </label>

                    <input
                        type="date"
                        class="form-control"
                        name="tanggal"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <!-- Ukuran -->
                <div class="col-md-6">

                    <label class="form-label">
                        Ukuran Kawat
                    </label>

                    <select
                        class="form-select"
                        name="id_kawat"
                        id="idKawat"
                        required
                    >

                        <option value="">
                            -- Pilih ukuran --
                        </option>

                        <?php while ($r = $kawat->fetch_assoc()): ?>

                            <option
                                value="<?= (int)$r['id_kawat'] ?>"
                                data-baru="<?= h($r['stok_baru']) ?>"
                                data-bekas="<?= h($r['stok_bekas']) ?>"
                            >
                                <?= number_format((float)$r['ukuran'], 2) ?> mm
                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <!-- Saldo -->
                <div class="col-12">

                    <div class="balance" id="saldoInfo">

                        <div class="text-muted">
                            Pilih ukuran kawat untuk melihat saldo stok.
                        </div>

                    </div>

                </div>


                <!-- Jenis Mutasi -->
                <div class="col-12">

                    <div class="section-title mb-2">
                        Transaksi Utama
                    </div>

                    <select
                        class="form-select"
                        name="jenis_mutasi"
                        id="jenisMutasi"
                        required
                    >

                        <option value="">
                            -- Pilih jenis transaksi --
                        </option>

                        <option value="PEMAKAIAN">
                            Dipakai Pabrik
                        </option>

                        <option value="RETURN_BEKAS">
                            Kembali dari Pabrik
                        </option>

                        <option value="ROMBENG">
                            Rombeng / Jual Kawat Bekas
                        </option>


                        <optgroup label="Koreksi Stok / Adjustment">

                            <option value="ADJUSTMENT_MASUK_BARU">
                                Adjustment Masuk - Kawat Baru
                            </option>

                            <option value="ADJUSTMENT_MASUK_BEKAS">
                                Adjustment Masuk - Kawat Bekas
                            </option>

                            <option value="ADJUSTMENT_KELUAR_BARU">
                                Adjustment Keluar - Kawat Baru
                            </option>

                            <option value="ADJUSTMENT_KELUAR_BEKAS">
                                Adjustment Keluar - Kawat Bekas
                            </option>

                        </optgroup>

                    </select>

                </div>


                <!-- Info transaksi -->
                <div class="col-12">

                    <div
                        class="transaction-info transaction-primary"
                        id="infoPemakaian"
                    >
                        <strong>
                            <i class="fas fa-industry me-1"></i>
                            Dipakai Pabrik
                        </strong>

                        <div class="small mt-1">
                            Mengurangi stok <strong>KAWAT BARU</strong>.
                            Gunakan ketika kawat diambil dari gudang untuk kebutuhan pabrik.
                        </div>
                    </div>


                    <div
                        class="transaction-info transaction-warning"
                        id="infoReturn"
                    >
                        <strong>
                            <i class="fas fa-rotate-left me-1"></i>
                            Kembali dari Pabrik
                        </strong>

                        <div class="small mt-1">
                            Menambah stok <strong>KAWAT BEKAS</strong>.
                            Gunakan ketika sisa kawat dari pabrik dikembalikan ke gudang.
                        </div>
                    </div>


                    <div
                        class="transaction-info transaction-danger"
                        id="infoRombeng"
                    >
                        <strong>
                            <i class="fas fa-trash-can me-1"></i>
                            Rombeng / Jual Kawat Bekas
                        </strong>

                        <div class="small mt-1">
                            Mengurangi stok <strong>KAWAT BEKAS</strong>.
                            Gunakan ketika kawat bekas dijual atau dikeluarkan sebagai rombeng.
                        </div>
                    </div>


                    <div
                        class="transaction-info adjustment-box"
                        id="infoAdjustment"
                    >
                        <strong>
                            <i class="fas fa-scale-balanced me-1"></i>
                            Adjustment Stok
                        </strong>

                        <div class="small mt-1">
                            Khusus untuk koreksi hasil stok fisik.
                            <strong>Bukan untuk mengganti transaksi normal.</strong>
                        </div>

                        <div class="small text-muted mt-1">
                            Contoh: sistem 5 KG, tetapi hasil timbang fisik 4,80 KG.
                            Gunakan Adjustment Keluar 0,20 KG.
                        </div>
                    </div>

                </div>


                <!-- Qty -->
                <div class="col-md-6">

                    <label class="form-label">
                        Jumlah
                    </label>

                    <input
                        type="number"
                        step="0.0001"
                        min="0.0001"
                        class="form-control"
                        name="qty_asal"
                        id="qtyAsal"
                        placeholder="Contoh: 5"
                        required
                    >

                </div>


                <!-- Satuan -->
                <div class="col-md-6">

                    <label class="form-label">
                        Satuan Input
                    </label>

                    <select
                        name="satuan_input"
                        id="satuanInput"
                        class="form-select"
                        required
                    >

                        <option value="KG">
                            KG
                        </option>

                        <option value="ONS">
                            ONS
                        </option>

                    </select>

                    <div class="quick-unit">

                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            onclick="setUnit('KG')"
                        >
                            KG
                        </button>

                        <button
                            type="button"
                            class="btn btn-outline-warning btn-sm"
                            onclick="setUnit('ONS')"
                        >
                            ONS
                        </button>

                    </div>

                </div>


                <!-- Konversi -->
                <div class="col-12">

                    <div class="convert-card">

                        <div class="convert-sub">
                            KONVERSI OTOMATIS
                        </div>

                        <div class="convert-main" id="hasilKonversi">
                            0,00 KG
                        </div>

                        <div class="convert-sub" id="detailKonversi">
                            Masukkan jumlah untuk melihat hasil konversi.
                        </div>

                    </div>

                </div>


                <!-- Referensi -->
                <div class="col-md-6">

                    <label class="form-label">
                        No. Referensi
                    </label>

                    <input
                        class="form-control"
                        name="no_referensi"
                        placeholder="Contoh: BON-001 / ROMBENG-001"
                    >

                </div>


                <!-- Keterangan -->
                <div class="col-md-6">

                    <label class="form-label">
                        Keterangan
                    </label>

                    <input
                        class="form-control"
                        name="keterangan"
                        placeholder="Contoh: kebutuhan rewinding mesin A"
                    >

                </div>

            </div>


            <div class="d-flex justify-content-between mt-4">

                <a
                    href="master_kawat.php"
                    class="btn btn-secondary"
                >
                    <i class="fas fa-arrow-left me-1"></i>
                    Kembali
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    <i class="fas fa-save me-1"></i>
                    Simpan Mutasi
                </button>

            </div>

        </form>

    </div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

<script>

const idKawat       = document.getElementById('idKawat');
const saldoInfo     = document.getElementById('saldoInfo');
const jenisMutasi   = document.getElementById('jenisMutasi');
const qtyAsal       = document.getElementById('qtyAsal');
const satuanInput   = document.getElementById('satuanInput');
const hasilKonversi = document.getElementById('hasilKonversi');
const detailKonversi = document.getElementById('detailKonversi');


function formatNumber(value, decimal = 2) {

    return new Intl.NumberFormat(
        'id-ID',
        {
            minimumFractionDigits: decimal,
            maximumFractionDigits: decimal
        }
    ).format(value);

}


function updateSaldo() {

    const option = idKawat.options[idKawat.selectedIndex];

    if (!option || !option.value) {

        saldoInfo.innerHTML = `
            <div class="text-muted">
                Pilih ukuran kawat untuk melihat saldo stok.
            </div>
        `;

        return;

    }


    const baru  = parseFloat(option.dataset.baru || 0);
    const bekas = parseFloat(option.dataset.bekas || 0);
    const total = baru + bekas;


    saldoInfo.innerHTML = `

        <div class="row">

            <div class="col-md-4 mb-2 mb-md-0">

                <div class="text-muted small">
                    STOK BARU
                </div>

                <div class="stock-number stock-baru">
                    ${formatNumber(baru)} KG
                </div>

                <div class="small text-muted">
                    ${formatNumber(baru * 10)} ONS
                </div>

            </div>


            <div class="col-md-4 mb-2 mb-md-0">

                <div class="text-muted small">
                    STOK BEKAS
                </div>

                <div class="stock-number stock-bekas">
                    ${formatNumber(bekas)} KG
                </div>

                <div class="small text-muted">
                    ${formatNumber(bekas * 10)} ONS
                </div>

            </div>


            <div class="col-md-4">

                <div class="text-muted small">
                    TOTAL FISIK
                </div>

                <div class="stock-number text-primary">
                    ${formatNumber(total)} KG
                </div>

                <div class="small text-muted">
                    ${formatNumber(total * 10)} ONS
                </div>

            </div>

        </div>
    `;

}


function updateJenisInfo() {

    const value = jenisMutasi.value;


    document
        .querySelectorAll('.transaction-info')
        .forEach(el => el.classList.remove('active'));


    if (value === 'PEMAKAIAN') {

        document
            .getElementById('infoPemakaian')
            .classList.add('active');

    }

    else if (value === 'RETURN_BEKAS') {

        document
            .getElementById('infoReturn')
            .classList.add('active');

    }

    else if (value === 'ROMBENG') {

        document
            .getElementById('infoRombeng')
            .classList.add('active');

    }

    else if (value.startsWith('ADJUSTMENT_')) {

        document
            .getElementById('infoAdjustment')
            .classList.add('active');

    }

}


function updateConversion() {

    const qty = parseFloat(qtyAsal.value || 0);
    const unit = satuanInput.value;

    let kg = 0;


    if (unit === 'KG') {

        kg = qty;

    }

    else if (unit === 'ONS') {

        kg = qty * 0.1;

    }


    hasilKonversi.innerText =
        `${formatNumber(kg)} KG`;


    if (qty <= 0) {

        detailKonversi.innerText =
            'Masukkan jumlah untuk melihat hasil konversi.';

        return;

    }


    if (unit === 'KG') {

        detailKonversi.innerText =
            `${formatNumber(qty)} KG = ${formatNumber(qty * 10)} ONS`;

    }

    else {

        detailKonversi.innerText =
            `${formatNumber(qty)} ONS = ${formatNumber(kg)} KG`;

    }

}


function setUnit(unit) {

    satuanInput.value = unit;

    updateConversion();

}


idKawat.addEventListener(
    'change',
    updateSaldo
);

jenisMutasi.addEventListener(
    'change',
    updateJenisInfo
);

qtyAsal.addEventListener(
    'input',
    updateConversion
);

satuanInput.addEventListener(
    'change',
    updateConversion
);


// Default
updateConversion();

</script>

</body>
</html>