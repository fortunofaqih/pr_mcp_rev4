<?php
/* =========================================================
  tambah riwayat oli
   ========================================================= */
session_start();

require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/oli_helper.php';

if (($_SESSION['status'] ?? '') !== 'login') {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$user = $_SESSION['username'] ?? 'system';

oli_sync_master_barang($koneksi, $user);
oli_sync_pembelian($koneksi, $user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggal    = $_POST['tanggal'] ?? date('Y-m-d');
    $idOli      = (int)($_POST['id_oli'] ?? 0);
    $jenis      = $_POST['jenis_mutasi'] ?? '';
    $jumlah     = (float)str_replace(',', '.', $_POST['jumlah'] ?? 0);
    $tujuanTipe = $_POST['tujuan_tipe'] ?? null;
    $tujuanNama = trim($_POST['tujuan_nama'] ?? '');
    $noRef      = trim($_POST['no_referensi'] ?? '');
    $ket        = trim($_POST['keterangan'] ?? '');

    if (!$idOli || $jumlah <= 0) {
        $_SESSION['error'] = 'Data belum lengkap.';
        header("Location: tambah_riwayat_oli.php");
        exit;
    }

    if (!in_array(
        $jenis,
        ['STOK_AWAL', 'PEMAKAIAN'],
        true
    )) {
        $_SESSION['error'] = 'Jenis transaksi tidak valid.';
        header("Location: tambah_riwayat_oli.php");
        exit;
    }

    if ($jenis === 'STOK_AWAL') {

        if (oli_has_stok_awal($koneksi, $idOli)) {
            $_SESSION['error'] =
                'Stok awal untuk oli ini sudah pernah dicatat. ' .
                'Jika salah, gunakan tombol Edit pada halaman stok oli.';
            header("Location: tambah_riwayat_oli.php");
            exit;
        }

        $jenisTransaksi = 'MASUK';
        $tujuanTipe = null;
        $tujuanNama = '';

        if ($ket === '') {
            $ket = 'Saldo awal berdasarkan catatan / stok fisik';
        }

    } else {

        $jenisTransaksi = 'KELUAR';

        $saldo = oli_get_saldo(
            $koneksi,
            $idOli
        );

        if ($jumlah > $saldo + 0.000001) {
            $_SESSION['error'] =
                'Pemakaian melebihi stok tersedia. ' .
                'Stok saat ini: ' .
                number_format($saldo, 2) .
                ' Liter.';
            header("Location: tambah_riwayat_oli.php");
            exit;
        }

        if (!in_array(
            $tujuanTipe,
            ['MESIN', 'KENDARAAN', 'LAINNYA'],
            true
        )) {
            $_SESSION['error'] =
                'Tujuan pemakaian wajib dipilih.';
            header("Location: tambah_riwayat_oli.php");
            exit;
        }

        if ($tujuanNama === '') {
            $_SESSION['error'] =
                'Nama mesin / kendaraan / tujuan wajib diisi.';
            header("Location: tambah_riwayat_oli.php");
            exit;
        }
    }

    $stmt = $koneksi->prepare("
        INSERT INTO riwayat_oli
        (
            id_oli,
            tanggal,
            jenis_mutasi,
            jenis_transaksi,
            jumlah,
            source_type,
            source_id,
            no_referensi,
            tujuan_tipe,
            tujuan_nama,
            keterangan,
            status_transaksi,
            created_by
        )
        VALUES
        (
            ?, ?, ?, ?, ?,
            'MANUAL',
            NULL,
            ?, ?, ?, ?,
            'AKTIF',
            ?
        )
    ");

    $stmt->bind_param(
        "isssdsssss",
        $idOli,
        $tanggal,
        $jenis,
        $jenisTransaksi,
        $jumlah,
        $noRef,
        $tujuanTipe,
        $tujuanNama,
        $ket,
        $user
    );

    if ($stmt->execute()) {

        $_SESSION['success'] =
            $jenis === 'STOK_AWAL'
            ? 'Stok awal oli berhasil dicatat.'
            : 'Pemakaian oli berhasil disimpan.';

        header("Location: master_oli.php");
        exit;
    }

    $_SESSION['error'] =
        'Gagal menyimpan: ' .
        $stmt->error;

    $stmt->close();

    header("Location: tambah_riwayat_oli.php");
    exit;
}

$oli = $koneksi->query("
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
        ) AS saldo,

        MAX(
            CASE
                WHEN r.status_transaksi = 'AKTIF'
                 AND r.jenis_mutasi = 'STOK_AWAL'
                THEN 1
                ELSE 0
            END
        ) AS punya_stok_awal

    FROM master_oli o

    LEFT JOIN riwayat_oli r
        ON r.id_oli = o.id_oli

    GROUP BY
        o.id_oli,
        o.nama_oli

    ORDER BY
        o.nama_oli
");

function h($v)
{
    return htmlspecialchars(
        (string)$v,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!doctype html>
<html lang="id">

<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Input Mutasi Oli</title>

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
    max-width: 850px;
    margin: 35px auto;
}

.head {
    background: linear-gradient(135deg, #d97706, #92400e);
    color: #fff;
    padding: 18px 22px;
    border-radius: 14px 14px 0 0;
}

.cardx {
    background: #fff;
    padding: 26px;
    border-radius: 0 0 14px 14px;
    box-shadow: 0 5px 18px rgba(0,0,0,.08);
}

.balance {
    background: #fff8e8;
    border: 1px solid #f5d28b;
    border-radius: 10px;
    padding: 14px;
}

.info-box {
    background: #eef6ff;
    border-left: 4px solid #0d6efd;
    padding: 12px 14px;
    border-radius: 8px;
}

</style>

</head>


<body>

<div class="container wrap">

    <div class="head">

        <h5 class="mb-1">
            <i class="fas fa-oil-can me-2"></i>
            Input Mutasi Oli
        </h5>

        <div class="opacity-75">
            Saldo awal atau pemakaian operasional
        </div>

    </div>


    <div class="cardx">

        <?php if (isset($_SESSION['error'])): ?>

            <div class="alert alert-danger">
                <?= h($_SESSION['error']) ?>
            </div>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>


        <div class="alert alert-info">

            <strong>Pembelian oli tidak perlu diinput manual.</strong>

            Jika nanti ada pembelian OLI SAE 40,
            OLI SAE 460, atau OLI Hidrolis,
            sistem akan memasukkannya otomatis.

        </div>


        <form method="post">

            <div class="row g-3">

                <div class="col-md-6">

                    <label class="form-label fw-bold">
                        Tanggal
                    </label>

                    <input
                        type="date"
                        name="tanggal"
                        class="form-control"
                        value="<?= date('Y-m-d') ?>"
                        required
                    >

                </div>


                <div class="col-md-6">

                    <label class="form-label fw-bold">
                        Jenis Oli
                    </label>

                    <select
                        name="id_oli"
                        id="idOli"
                        class="form-select"
                        required
                    >

                        <option value="">
                            -- Pilih Oli --
                        </option>

                        <?php while ($r = $oli->fetch_assoc()): ?>

                            <option
                                value="<?= (int)$r['id_oli'] ?>"
                                data-saldo="<?= h($r['saldo']) ?>"
                                data-stok-awal="<?= (int)$r['punya_stok_awal'] ?>"
                            >
                                <?= h($r['nama_oli']) ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <div class="col-12">

                    <div
                        class="balance"
                        id="saldoInfo"
                    >
                        Pilih oli untuk melihat stok saat ini.
                    </div>

                </div>


                <div class="col-12">

                    <label class="form-label fw-bold">
                        Jenis Transaksi
                    </label>

                    <select
                        name="jenis_mutasi"
                        id="jenisMutasi"
                        class="form-select"
                        required
                    >

                        <option value="">
                            -- Pilih Transaksi --
                        </option>

                        <option value="STOK_AWAL">
                            Input Stok Awal
                        </option>

                        <option value="PEMAKAIAN">
                            Pemakaian Operasional
                        </option>

                    </select>

                </div>


                <div class="col-md-6">

                    <label class="form-label fw-bold">
                        Jumlah (Liter)
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        name="jumlah"
                        class="form-control"
                        placeholder="Contoh: 250"
                        required
                    >

                </div>


                <div class="col-md-6">

                    <label class="form-label fw-bold">
                        No. Referensi
                    </label>

                    <input
                        name="no_referensi"
                        class="form-control"
                        placeholder="Contoh: BON-OLI-001"
                    >

                </div>


                <div
                    class="col-12"
                    id="tujuanSection"
                    style="display:none;"
                >

                    <div class="info-box">

                        <div class="row g-3">

                            <div class="col-md-5">

                                <label class="form-label fw-bold">
                                    Dipakai Untuk
                                </label>

                                <select
                                    name="tujuan_tipe"
                                    id="tujuanTipe"
                                    class="form-select"
                                >

                                    <option value="">
                                        -- Pilih --
                                    </option>

                                    <option value="MESIN">
                                        Mesin Pabrik
                                    </option>

                                    <option value="KENDARAAN">
                                        Kendaraan
                                    </option>

                                    <option value="LAINNYA">
                                        Lainnya
                                    </option>

                                </select>

                            </div>


                            <div class="col-md-7">

                                <label class="form-label fw-bold">
                                    Nama Mesin / Kendaraan / Tujuan
                                </label>

                                <input
                                    name="tujuan_nama"
                                    id="tujuanNama"
                                    class="form-control"
                                    placeholder="Contoh: Mesin Blowing 01 / L300 B 1234 XX"
                                >

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-12">

                    <label class="form-label fw-bold">
                        Keterangan
                    </label>

                    <textarea
                        name="keterangan"
                        class="form-control"
                        rows="3"
                    ></textarea>

                </div>

            </div>


            <div class="d-flex justify-content-between mt-4">

                <a
                    href="master_oli.php"
                    class="btn btn-secondary"
                >
                    Kembali
                </a>

                <button
                    class="btn btn-warning"
                >
                    <i class="fas fa-save me-1"></i>
                    Simpan
                </button>

            </div>

        </form>

    </div>

</div>


<script>

const idOli = document.getElementById('idOli');
const saldoInfo = document.getElementById('saldoInfo');
const jenisMutasi = document.getElementById('jenisMutasi');
const tujuanSection = document.getElementById('tujuanSection');
const tujuanTipe = document.getElementById('tujuanTipe');
const tujuanNama = document.getElementById('tujuanNama');

function refreshSaldo() {

    const opt =
        idOli.options[idOli.selectedIndex];

    if (!opt || !opt.value) {

        saldoInfo.innerHTML =
            'Pilih oli untuk melihat stok saat ini.';

        return;
    }

    const saldo =
        parseFloat(opt.dataset.saldo || 0);

    const punyaStokAwal =
        parseInt(opt.dataset.stokAwal || 0);

    saldoInfo.innerHTML = `
        <div class="small text-muted">STOK SAAT INI</div>
        <div style="font-size:22px;font-weight:700;">
            ${saldo.toLocaleString('id-ID', {
                minimumFractionDigits:2,
                maximumFractionDigits:2
            })} Liter
        </div>
        <div class="small text-muted mt-1">
            ${
                punyaStokAwal
                ? 'Stok awal sudah pernah dicatat.'
                : 'Stok awal belum pernah dicatat.'
            }
        </div>
    `;
}

function refreshJenis() {

    const pemakaian =
        jenisMutasi.value === 'PEMAKAIAN';

    tujuanSection.style.display =
        pemakaian
        ? 'block'
        : 'none';

    tujuanTipe.required = pemakaian;
    tujuanNama.required = pemakaian;
}

idOli.addEventListener(
    'change',
    refreshSaldo
);

jenisMutasi.addEventListener(
    'change',
    refreshJenis
);

</script>

</body>
</html>
