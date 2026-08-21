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

oli_sync_master_barang($koneksi, $user);
oli_sync_pembelian($koneksi, $user);

$result = $koneksi->query("
    SELECT
        o.id_oli,
        o.nama_oli,

        (
            SELECT GROUP_CONCAT(
                CONCAT(mb.id_barang, ' - ', mb.nama_barang)
                ORDER BY mb.id_barang
                SEPARATOR ' | '
            )
            FROM master_oli_barang mob
            JOIN master_barang mb
                ON mb.id_barang = mob.id_barang
            WHERE mob.id_oli = o.id_oli
              AND (
                    UPPER(TRIM(o.nama_oli)) <> 'OLI HIDROLIS'
                    OR mb.id_barang = 3169
                  )
        ) AS barang_terkait,

        COALESCE(
            (
                SELECT SUM(r.jumlah)
                FROM riwayat_oli r
                WHERE r.id_oli = o.id_oli
                  AND r.status_transaksi = 'AKTIF'
                  AND r.jenis_mutasi = 'STOK_AWAL'
            ),
            0
        ) AS stok_awal,

        COALESCE(
            (
                SELECT SUM(r.jumlah)
                FROM riwayat_oli r
                WHERE r.id_oli = o.id_oli
                  AND r.status_transaksi = 'AKTIF'
                  AND r.jenis_mutasi = 'PEMBELIAN'
            ),
            0
        ) AS pembelian,

        COALESCE(
            (
                SELECT SUM(r.jumlah)
                FROM riwayat_oli r
                WHERE r.id_oli = o.id_oli
                  AND r.status_transaksi = 'AKTIF'
                  AND r.jenis_mutasi = 'PEMAKAIAN'
            ),
            0
        ) AS pemakaian,

        COALESCE(
            (
                SELECT SUM(
                    CASE
                        WHEN r.jenis_transaksi = 'MASUK'
                        THEN r.jumlah
                        WHEN r.jenis_transaksi = 'KELUAR'
                        THEN -r.jumlah
                        ELSE 0
                    END
                )
                FROM riwayat_oli r
                WHERE r.id_oli = o.id_oli
                  AND r.status_transaksi = 'AKTIF'
            ),
            0
        ) AS saldo

    FROM master_oli o
    ORDER BY o.nama_oli
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

<title>Rekap Stok Oli</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<style>

body {
    background: #f4f7fb;
}

.wrap {
    max-width: 1300px;
    margin: 25px auto;
}

.head {
    background: linear-gradient(135deg, #d97706, #92400e);
    color: white;
    padding: 18px 22px;
    border-radius: 14px 14px 0 0;
}

.bodyx {
    background: white;
    padding: 22px;
    border-radius: 0 0 14px 14px;
    box-shadow: 0 5px 18px rgba(0,0,0,.08);
}

.table thead th {
    background: #3f3f46;
    color: white;
    font-size: 11px;
    text-transform: uppercase;
}

</style>

</head>


<body>

<div class="container-fluid wrap">

    <div class="head d-flex justify-content-between">

        <div>

            <h5 class="mb-1">
                Rekap Stok Oli
            </h5>

            <small>
                Master dari master_barang, saldo dari riwayat transaksi
            </small>

        </div>

        <a
            href="master_oli.php"
            class="btn btn-light btn-sm"
        >
            Kembali
        </a>

    </div>


    <div class="bodyx">

        <div class="table-responsive">

            <table class="table table-bordered table-hover">

                <thead>

                <tr>
                    <th>No</th>
                    <th>Nama Oli</th>
                    <th>Master Barang Terkait</th>
                    <th>Stok Awal</th>
                    <th>Pembelian</th>
                    <th>Pemakaian</th>
                    <th>Stok Saat Ini</th>
                </tr>

                </thead>


                <tbody>

                <?php $no = 1; ?>

                <?php while ($r = $result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= $no++ ?>
                        </td>

                        <td>
                            <b><?= h($r['nama_oli']) ?></b>
                        </td>

                        <td style="font-size:11px;">
                            <?= h($r['barang_terkait'] ?: '-') ?>
                        </td>

                        <td>
                            <?= number_format((float)$r['stok_awal'], 2) ?> L
                        </td>

                        <td>
                            <?= number_format((float)$r['pembelian'], 2) ?> L
                        </td>

                        <td>
                            <?= number_format((float)$r['pemakaian'], 2) ?> L
                        </td>

                        <td class="fw-bold text-primary">
                            <?= number_format((float)$r['saldo'], 2) ?> L
                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>
