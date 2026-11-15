<?php
/**
 * verifikasi_pembelian.php
 * Halaman verifikasi pembelian — Admin Gudang
 * Tab Menunggu: card per PR, Tab Riwayat: DataTables
 */

session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] !== 'login') {
    header('Location: ../../login.php?pesan=belum_login');
    exit;
}


// Hak verifikasi PR Besar (PRB) berdasarkan role yang tersimpan di session.

$boleh_verifikasi_prb = in_array($_SESSION['role'] ?? '', ['manager', 'administrator'], true);

// ── REVISI USD: Helper cek & format mata uang ───────────────
function is_usd($keterangan) {



    return stripos((string)$keterangan, 'USD') !== false;

}

function format_uang($nilai, $keterangan) {

    $prefix = is_usd($keterangan) ? 'USD ' : 'Rp ';

    return $prefix . number_format($nilai, 0, ',', '.');

}


// ── END REVISI USD ──────────────────────────────────────────







// ── Hitung total menunggu ────────────────────────────────────



$q_count        = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pembelian_staging WHERE status_staging='MENUNGGU'");



$total_menunggu = mysqli_fetch_assoc($q_count)['total'];







// ── Ambil data menunggu, kelompokkan per no_request ─────────



$q_stg = mysqli_query($koneksi, "



    SELECT s.*, r.nama_pemesan AS pemesan_pr



    FROM pembelian_staging s



    LEFT JOIN tr_request r ON r.id_request = s.id_request



    WHERE s.status_staging = 'MENUNGGU'



    ORDER BY s.no_request ASC, s.created_at ASC



");







$groups = []; // [ no_request => [ 'pemesan' => ..., 'items' => [...] ] ]



while ($row = mysqli_fetch_assoc($q_stg)) {



    $key = $row['no_request'] ?? 'TANPA-PR';



    if (!isset($groups[$key])) {



        $groups[$key] = [



            'pemesan' => $row['pemesan_pr'] ?? '-',



            'items'   => [],



        ];



    }



    $groups[$key]['items'][] = $row;



}



// Pengelompokan khusus tampilan; query dan proses verifikasi tidak diubah.

$kategori_tabs = [

    'pr'  => ['label' => 'PR KECIL', 'icon' => 'fa-shopping-basket'],

    'prb' => ['label' => 'PR BESAR', 'icon' => 'fa-boxes'],

    'pri' => ['label' => 'IT', 'icon' => 'fa-laptop'],

];

$groups_per_tab = ['pr' => [], 'prb' => [], 'pri' => []];

$jumlah_per_tab = ['pr' => 0, 'prb' => 0, 'pri' => 0];

$groups_lainnya = [];

$jumlah_lainnya = 0;

foreach ($groups as $no_pr => $grup) {

    $nomor = strtoupper(trim((string)$no_pr));

    // Periksa awalan panjang dahulu supaya PRB/PRI tidak terbaca sebagai PR kecil.

    if (strpos($nomor, 'PRB') === 0) {

        $kategori = 'prb';

    } elseif (strpos($nomor, 'PRI') === 0) {

        $kategori = 'pri';

    } elseif (strpos($nomor, 'PR') === 0) {

        $kategori = 'pr';

    } else {

        $kategori = 'lainnya';

    }

    if ($kategori === 'lainnya') {

        $groups_lainnya[$no_pr] = $grup;

        $jumlah_lainnya += count($grup['items']);

    } else {

        $groups_per_tab[$kategori][$no_pr] = $grup;

        $jumlah_per_tab[$kategori] += count($grup['items']);

    }

}

if ($groups_lainnya) {

    $kategori_tabs['lainnya'] = ['label' => 'LAINNYA', 'icon' => 'fa-folder-open'];

    $groups_per_tab['lainnya'] = $groups_lainnya;

    $jumlah_per_tab['lainnya'] = $jumlah_lainnya;

}

?>



<!DOCTYPE html>



<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>VERIFIKASI PEMBELIAN — ADMIN GUDANG</title>



    <link rel="icon" type="image/png" href="../../assets/img/logo_mcp.png">



    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">



    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">



    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">



    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">



    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">







    <style>



        /* ── Base ─────────────────────────────────────────────── */



        :root {



            --green       : #198754;



            --green-light : #d1e7dd;



            --green-dark  : #0f5132;



            --amber       : #ffc107;



            --amber-bg    : #fff8e1;



            --surface     : #f4f6f9;



            --card-bg     : #ffffff;



            --border      : #e2e8f0;



            --text-main   : #1a202c;



            --text-muted  : #64748b;



            --radius-card : 14px;



            --radius-inner: 10px;



            --shadow-card : 0 2px 12px rgba(0,0,0,.07);



            --shadow-hover: 0 6px 24px rgba(0,0,0,.12);



        }







        *, *::before, *::after { box-sizing: border-box; }







        body {



            background: var(--surface);



            font-family: 'Plus Jakarta Sans', sans-serif;



            font-size: 0.875rem;



            color: var(--text-main);



        }







        input:not([readonly]):not([type=number]),



        textarea { text-transform: uppercase; }







        /* ── Navbar ───────────────────────────────────────────── */



        .navbar-gudang {



            background: linear-gradient(135deg, var(--green-dark) 0%, var(--green) 100%);



            box-shadow: 0 2px 16px rgba(25, 135, 84, .35);



        }







        /* ── Stat card ────────────────────────────────────────── */



        .stat-card {



            border: none;



            border-radius: var(--radius-card);



            border-left: 5px solid var(--amber);



            background: var(--card-bg);



            box-shadow: var(--shadow-card);



            transition: box-shadow .2s;



        }



        .stat-card:hover { box-shadow: var(--shadow-hover); }







        /* ── Nav tabs ─────────────────────────────────────────── */



        .nav-tabs-custom {



            border: none;



            background: var(--card-bg);



            border-radius: var(--radius-card);



            padding: 6px 8px;



            box-shadow: var(--shadow-card);



            gap: 4px;



        }



        .nav-tabs-custom .nav-link {



            border: none;



            border-radius: 8px;



            color: var(--text-muted);



            font-weight: 600;



            padding: 8px 18px;



            transition: background .15s, color .15s;



        }



        .nav-tabs-custom .nav-link:hover { background: var(--surface); color: var(--text-main); }



        .nav-tabs-custom .nav-link.active {



            background: var(--green);



            color: #fff;



            box-shadow: 0 2px 8px rgba(25,135,84,.3);



        }







        /* ── PR Card ──────────────────────────────────────────── */



        .pr-card {



            background: var(--card-bg);



            border-radius: var(--radius-card);



            border: 1.5px solid var(--border);



            box-shadow: var(--shadow-card);



            overflow: hidden;



            transition: box-shadow .2s, transform .2s;



            animation: fadeSlideUp .3s ease both;



        }



        .pr-card:hover {



            box-shadow: var(--shadow-hover);



            transform: translateY(-2px);



        }







        @keyframes fadeSlideUp {



            from { opacity: 0; transform: translateY(10px); }



            to   { opacity: 1; transform: translateY(0); }



        }







        /* Header PR card */



        .pr-card-header {



            display: flex;



            align-items: center;



            justify-content: space-between;



            flex-wrap: wrap;



            gap: 8px;



            background: linear-gradient(90deg, #f0faf5 0%, #ffffff 100%);



            border-bottom: 1.5px solid var(--green-light);



            padding: 12px 18px;



        }







        .pr-badge-no {



            display: inline-flex;



            align-items: center;



            gap: 8px;



            background: var(--green);



            color: #fff;



            border-radius: 8px;



            padding: 5px 14px;



            font-weight: 800;



            font-size: 0.8rem;



            letter-spacing: .5px;



        }



        .pr-badge-no .pr-dot {



            width: 8px; height: 8px;



            background: var(--amber);



            border-radius: 50%;



            animation: pulse 1.5s infinite;



        }



        @keyframes pulse {



            0%, 100% { opacity: 1; transform: scale(1); }



            50%       { opacity: .6; transform: scale(1.4); }



        }







        .pr-pemesan {



            font-size: 0.78rem;



            color: var(--text-muted);



            font-weight: 500;



        }



        .pr-pemesan strong { color: var(--text-main); font-weight: 700; }







        .pr-item-count {



            background: var(--amber-bg);



            color: #92681a;



            border-radius: 20px;



            padding: 3px 12px;



            font-size: 0.75rem;



            font-weight: 700;



            border: 1px solid #f0d070;



        }







        /* Inner table */



        .pr-card .table { margin: 0; font-size: 0.8rem; }



        .pr-card .table thead th {



            background: #f8fafb;



            border-bottom: 2px solid var(--border);



            color: var(--text-muted);



            font-weight: 700;



            font-size: 0.7rem;



            text-transform: uppercase;



            letter-spacing: .5px;



            padding: 9px 12px;



        }



        .pr-card .table tbody tr {



            transition: background .15s;



        }



        .pr-card .table tbody tr:hover { background: #f8fcf9; }



        .pr-card .table tbody td { padding: 10px 12px; vertical-align: middle; border-color: var(--border); }



        .pr-card .table tbody tr:last-child td { border-bottom: none; }







        /* Badge alokasi */



        .badge-stok  { background: #cff4fc; color: #055160; border: 1px solid #9eeaf9; }



        .badge-langsung { background: #e2e3e5; color: #41464b; border: 1px solid #c4c8cb; }







        /* Tombol verifikasi */



        .btn-verif {



            background: var(--green);



            color: #fff;



            border: none;



            border-radius: 8px;



            padding: 5px 14px;



            font-size: 0.75rem;



            font-weight: 700;



            transition: background .15s, box-shadow .15s, transform .1s;



            white-space: nowrap;



        }



        .btn-verif:hover {



            background: var(--green-dark);



            box-shadow: 0 3px 10px rgba(25,135,84,.35);



            transform: translateY(-1px);



            color: #fff;



        }



        .btn-verif:active { transform: translateY(0); }







        /* Empty state */



        .empty-state {



            text-align: center;



            padding: 64px 24px;



            color: var(--text-muted);



        }



        .empty-state .icon-wrap {



            width: 72px; height: 72px;



            background: var(--green-light);



            border-radius: 50%;



            display: inline-flex;



            align-items: center;



            justify-content: center;



            margin-bottom: 16px;



            font-size: 1.8rem;



            color: var(--green);



        }







        /* ── Modal ────────────────────────────────────────────── */



        .modal-xl { max-width: 96%; }



        @media (min-width: 992px) { .modal-body { max-height: 80vh; overflow-y: auto; } }







        /* ── Riwayat ──────────────────────────────────────────── */



        #tabelRiwayat { font-size: 0.75rem; }



    
        /* Loading halaman — hanya visual, tanpa mengubah proses verifikasi */
        #pageLoadingVerifikasi {
            position: fixed;
            inset: 0;
            z-index: 20000;
            background: rgba(244, 246, 249, .97);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
            visibility: visible;
            transition: opacity .25s ease, visibility .25s ease;
        }
        #pageLoadingVerifikasi.loading-selesai {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        .loading-verifikasi-box {
            text-align: center;
            padding: 32px;
            color: #0f5132;
        }
        .loading-verifikasi-box .spinner-border {
            width: 3.25rem;
            height: 3.25rem;
            border-width: .3rem;
            color: #198754;
        }
        .loading-verifikasi-title {
            margin-top: 18px;
            font-weight: 800;
            font-size: 1.05rem;
            letter-spacing: .2px;
        }
        .loading-verifikasi-subtitle {
            color: #64748b;
            font-size: .8rem;
            margin-top: 5px;
        }
    </style>



</head>



<body>
<!-- Loading awal halaman, tidak menghalangi data setelah siap -->
<div id="pageLoadingVerifikasi" role="status" aria-live="polite" aria-label="Memuat halaman verifikasi pembelian">
    <div class="loading-verifikasi-box">
        <div class="spinner-border" aria-hidden="true"></div>
        <div class="loading-verifikasi-title">Loading... Memuat Verifikasi Pembelian</div>
        <div class="loading-verifikasi-subtitle">Sedang menyiapkan data verifikasi, mohon tunggu.</div>
    </div>
</div>








<!-- ── Navbar ──────────────────────────────────────────────── -->



<nav class="navbar navbar-dark mb-4 navbar-gudang">



    <div class="container-fluid px-4">



        <span class="navbar-brand fw-bold fs-6">



            <i class="fas fa-clipboard-check me-2"></i>VERIFIKASI PEMBELIAN — ADMIN GUDANG



        </span>



        <a href="../../index.php" class="btn btn-danger btn-sm">



            <i class="fas fa-arrow-left me-1"></i>Kembali



        </a>



    </div>



</nav>







<div class="container-fluid px-4 pb-5">







    <!-- ── Stat card ──────────────────────────────────────── -->



    <div class="row mb-4">



        <div class="col-md-3">



            <div class="card stat-card">



                <div class="card-body py-3">



                    <div class="d-flex justify-content-between align-items-center">



                        <div>



                            <div class="text-muted small fw-600 text-uppercase" style="font-size:.7rem;letter-spacing:.5px;">



                                Menunggu Verifikasi



                            </div>



                            <div class="fs-2 fw-bold text-warning" id="counterMenunggu"><?= $total_menunggu ?></div>



                        </div>



                        <i class="fas fa-hourglass-half fa-2x text-warning opacity-50"></i>



                    </div>



                </div>



            </div>



        </div>



    </div>







    <!-- Tabs verifikasi berdasarkan awalan nomor request -->

    <ul class="nav nav-tabs-custom mb-4 d-flex flex-wrap" id="tabsVerifikasi" role="tablist">

        <?php $tab_pertama = true; foreach ($kategori_tabs as $kode => $tab_info): ?>

        <li class="nav-item" role="presentation">

            <button type="button" class="nav-link <?= $tab_pertama ? 'active' : '' ?>"

                    id="nav-<?= $kode ?>" data-bs-toggle="tab"

                    data-bs-target="#tab-<?= $kode ?>" role="tab"

                    aria-controls="tab-<?= $kode ?>" aria-selected="<?= $tab_pertama ? 'true' : 'false' ?>">

                <i class="fas <?= htmlspecialchars($tab_info['icon'], ENT_QUOTES, 'UTF-8') ?> me-1"></i>

                <?= htmlspecialchars($tab_info['label'], ENT_QUOTES, 'UTF-8') ?>

                <span class="badge bg-warning text-dark ms-1 badge-kategori" data-kategori="<?= $kode ?>"><?= $jumlah_per_tab[$kode] ?></span>

            </button>

        </li>

        <?php $tab_pertama = false; endforeach; ?>

        <li class="nav-item" role="presentation">

            <button type="button" class="nav-link" id="nav-riwayat" data-bs-toggle="tab"

                    data-bs-target="#tab-riwayat" role="tab" aria-controls="tab-riwayat" aria-selected="false">

                <i class="fas fa-history me-1"></i>RIWAYAT VERIFIKASI

            </button>

        </li>

    </ul>



    <div class="tab-content" id="kontenTabsVerifikasi">

        <?php $tab_pertama = true; foreach ($kategori_tabs as $kode => $tab_info):

            $daftar_pr = $groups_per_tab[$kode]; ?>

        <div class="tab-pane fade <?= $tab_pertama ? 'show active' : '' ?>" id="tab-<?= $kode ?>"

             role="tabpanel" aria-labelledby="nav-<?= $kode ?>">

            <?php if ($kode === 'prb' && !$boleh_verifikasi_prb): ?>

                <div class="alert alert-warning py-2 small mb-3" role="status">

                    <i class="fas fa-lock me-1"></i>

                    Verifikasi PR Besar hanya dapat dilakukan oleh Manager atau Administrator.

                    Data tetap dapat dilihat.

                </div>

            <?php endif; ?>

            <div class="pr-cards-wrapper d-flex flex-column gap-3" data-kategori="<?= $kode ?>">

                <?php if (empty($daftar_pr)): ?>

                    <div class="empty-state">

                        <div class="icon-wrap"><i class="fas fa-check-circle"></i></div>

                        <h5 class="fw-bold mt-2 mb-1">Tidak ada pembelian menunggu verifikasi</h5>

                        <p class="mb-0">Belum ada item untuk kategori <?= htmlspecialchars($tab_info['label'], ENT_QUOTES, 'UTF-8') ?>.</p>

                    </div>

                <?php else: ?>

                <?php foreach ($daftar_pr as $no_pr => $grup) :



                    $item_count = count($grup['items']);



                ?>



                <div class="pr-card" data-pr="<?= htmlspecialchars($no_pr) ?>">







                    <!-- Header card -->



                    <div class="pr-card-header">



                        <div class="d-flex align-items-center gap-3 flex-wrap">



                            <div class="pr-badge-no">



                                <span class="pr-dot"></span>



                                <i class="fas fa-file-invoice me-1"></i>



                                <?= htmlspecialchars($no_pr) ?>



                            </div>



                            <div class="pr-pemesan">



                                <i class="fas fa-user me-1 opacity-50"></i>



                                Pemesan: <strong><?= htmlspecialchars($grup['pemesan']) ?></strong>



                            </div>



                        </div>



                        <span class="pr-item-count">



                            <i class="fas fa-boxes me-1"></i><?= $item_count ?> item



                        </span>



                    </div>







                    <!-- Tabel item -->



                    <div class="table-responsive">



                        <table class="table table-borderless">



                            <thead>



                                <tr>



                                    <th class="ps-4">#</th>



                                    <th>Nama Barang</th>



                                    <th>Toko / Supplier</th>



                                    <th>Tgl Nota</th>



                                    <th class="text-center">Qty</th>



                                    <th class="text-start">Satuan</th>



                                    <th class="text-end">Harga</th>



                                    <th class="text-end">Subtotal</th>



                                    <th class="text-center">Alokasi</th>



                                    <th>Petugas</th>



                                    <th class="text-center pe-4">Aksi</th>



                                </tr>



                            </thead>



                            <tbody>



                            <?php foreach ($grup['items'] as $i => $s) :



                                $subtotal  = $s['qty'] * $s['harga'];



                                $is_stok   = $s['alokasi_stok'] === 'MASUK STOK';



                                $tgl_fmt   = date('d/m/Y', strtotime($s['tgl_beli_barang']));



                                // ── REVISI USD: ambil keterangan item ──



                                $ket_item  = $s['keterangan'] ?? '';



                            ?>



                            <tr>



                                <td class="ps-4 text-muted fw-bold" style="width:36px;"><?= $i + 1 ?></td>



                                <td class="fw-bold"><?= htmlspecialchars($s['nama_barang_beli']) ?></td>



                                <td class="text-muted"><?= htmlspecialchars($s['supplier']) ?></td>



                                <td><?= $tgl_fmt ?></td>



                                <td class="text-center fw-bold"><?= (float) $s['qty'] ?></td>



                                <td class="text-start text-muted small"><?= strtoupper(htmlspecialchars($s['satuan_barang_beli'] ?? '')) ?></td>







                                <?php /* ── REVISI USD: harga & subtotal pakai format_uang ── */ ?>



                                <td class="text-end"><?= format_uang($s['harga'], $ket_item) ?></td>



                                <td class="text-end fw-bold text-success"><?= format_uang($subtotal, $ket_item) ?></td>



                                <td class="text-center">



                                    <span class="badge <?= $is_stok ? 'badge-stok' : 'badge-langsung' ?>">



                                        <?= htmlspecialchars($s['alokasi_stok']) ?>



                                    </span>



                                </td>



                                <td class="text-muted"><?= htmlspecialchars($s['driver']) ?></td>



                                <td class="text-center pe-4">



                                    <?php if ($kode === 'prb' && !$boleh_verifikasi_prb): ?>

                                        <button type="button" class="btn-verif" disabled

                                                title="Verifikasi PR Besar hanya untuk Manager dan Administrator"

                                                style="background:#94a3b8;cursor:not-allowed;box-shadow:none;transform:none;">

                                            <i class="fas fa-lock me-1"></i>Verifikasi

                                        </button>

                                    <?php else: ?>

                                        <button class="btn-verif" onclick="bukaVerifikasi(<?= (int)$s['id_staging'] ?>)">

                                            <i class="fas fa-check-double me-1"></i>Verifikasi

                                        </button>

                                    <?php endif; ?>



                                </td>



                            </tr>



                            <?php endforeach; ?>



                            </tbody>



                        </table>



                    </div>







                </div><!-- /pr-card -->



                <?php endforeach; ?>

                <?php endif; ?>

            </div><!-- /pr-cards-wrapper -->

        </div><!-- /tab kategori -->

        <?php $tab_pertama = false; endforeach; ?>



        <!-- ══════════════════════════════════════════════════



             TAB RIWAYAT — DataTables



        ══════════════════════════════════════════════════ -->



        <div class="tab-pane fade" id="tab-riwayat">



            <div class="card border-0 shadow-sm mt-2">



                <div class="card-body">



                    <table id="tabelRiwayat" class="table table-hover table-bordered w-100">



                        <thead class="table-dark">



                            <tr>



                                <th>Tgl Verifikasi</th>



                                <th>No. PR</th>



                                <th>Barang</th>



                                <th>Toko</th>



                                <th>Qty</th>



                                <th>Harga</th>



                                <th>Total</th>



                                <th>Alokasi</th>



                                <th>Status</th>



                                <th>Catatan</th>



                                <th>Verifikator</th>



                            </tr>



                        </thead>



                        <tbody>



                        <?php



                        // ── REVISI USD: tambah keterangan_final di query ──



                        $q_riwayat = mysqli_query($koneksi, "



                            SELECT



                                s.id_staging, s.no_request, s.status_staging,



                                s.catatan_verifikasi, s.verified_by, s.verified_at,



                                COALESCE(p.tgl_beli,         s.tgl_beli_barang)  AS tgl_final,



                                COALESCE(p.nama_barang_beli, s.nama_barang_beli) AS nama_final,



                                COALESCE(p.supplier,         s.supplier)         AS supplier_final,



                                COALESCE(p.qty,              s.qty)              AS qty_final,



                                COALESCE(p.harga,            s.harga)            AS harga_final,



                                COALESCE(p.alokasi_stok,     s.alokasi_stok)     AS alokasi_final,



                                COALESCE(p.keterangan,       s.keterangan)       AS keterangan_final



                            FROM pembelian_staging s



                            LEFT JOIN pembelian p



                                ON p.id_request_detail = s.id_request_detail



                               AND p.no_request        = s.no_request



                            WHERE s.status_staging IN ('DISETUJUI','DITOLAK')



                            ORDER BY s.verified_at DESC



                            LIMIT 500



                        ");



                        while ($rv = mysqli_fetch_assoc($q_riwayat)):



                            $total_rv = $rv['qty_final'] * $rv['harga_final'];



                            $badge    = $rv['status_staging'] === 'DISETUJUI' ? 'bg-success' : 'bg-danger';



                            $is_stok  = $rv['alokasi_final'] === 'MASUK STOK';



                            // ── REVISI USD: keterangan untuk format uang ──



                            $ket_rv   = $rv['keterangan_final'] ?? '';



                        ?>



                        <tr>



                            <td><?= $rv['verified_at'] ? date('d/m/Y H:i', strtotime($rv['verified_at'])) : '-' ?></td>



                            <td><?= htmlspecialchars($rv['no_request'] ?? '-') ?></td>



                            <td class="fw-bold"><?= htmlspecialchars($rv['nama_final']) ?></td>



                            <td><?= htmlspecialchars($rv['supplier_final']) ?></td>



                            <td class="text-center"><?= (float) $rv['qty_final'] ?></td>



                            <?php /* ── REVISI USD: harga & total pakai format_uang ── */ ?>



                            <td class="text-end"><?= format_uang($rv['harga_final'], $ket_rv) ?></td>



                            <td class="text-end fw-bold"><?= format_uang($total_rv, $ket_rv) ?></td>



                            <td>



                                <span class="badge <?= $is_stok ? 'badge-stok' : 'badge-langsung' ?>">



                                    <?= htmlspecialchars($rv['alokasi_final']) ?>



                                </span>



                            </td>



                            <td><span class="badge <?= $badge ?>"><?= $rv['status_staging'] ?></span></td>



                            <td class="text-muted small"><?= htmlspecialchars($rv['catatan_verifikasi'] ?? '-') ?></td>



                            <td class="small"><?= htmlspecialchars($rv['verified_by'] ?? '-') ?></td>



                        </tr>



                        <?php endwhile; ?>



                        </tbody>



                    </table>



                </div>



            </div>



        </div>







    </div><!-- /tab-content -->



</div><!-- /container -->







<!-- ── Modal Verifikasi ────────────────────────────────────── -->



<div class="modal fade" id="modalVerifikasi" tabindex="-1" data-bs-backdrop="static">



    <div class="modal-dialog modal-xl">



        <div class="modal-content shadow-lg">



            <div class="modal-header py-2" style="background:var(--green);">



                <h5 class="modal-title text-white fw-bold small">



                    <i class="fas fa-clipboard-check me-2"></i>FORM VERIFIKASI PEMBELIAN



                </h5>



                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>



            </div>



            <div class="modal-body p-4" id="kontenVerifikasi">



                <div class="text-center py-5">



                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>



                </div>



            </div>



        </div>



    </div>



</div>







<!-- ── Toast ───────────────────────────────────────────────── -->



<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">



    <div id="toastNotif" class="toast align-items-center text-white border-0" role="alert">



        <div class="d-flex">



            <div class="toast-body fw-bold" id="toastMsg">OK</div>



            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>



        </div>



    </div>



</div>







<!-- ── Scripts ─────────────────────────────────────────────── -->



<script src="https://code.jquery.com/jquery-3.7.0.js"></script>



<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>



<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>



<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>







<script>



// ── Toast helper ─────────────────────────────────────────────



const toastEl = document.getElementById('toastNotif');



function showToast(msg, type = 'success') {



    const map = { success: 'bg-success', error: 'bg-danger', warning: 'bg-warning text-dark' };



    toastEl.className = 'toast align-items-center text-white border-0 ' + (map[type] || 'bg-success');



    document.getElementById('toastMsg').innerText = msg;



    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 3500 }).show();



}







// ── Buka modal verifikasi ────────────────────────────────────



function bukaVerifikasi(id_staging) {



    $('#kontenVerifikasi').html(



        '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i> Memuat data...</div>'



    );



    $('#modalVerifikasi').modal('show');







    $.get('ajax_form_verifikasi.php', { id: id_staging }, function (html) {



        $('#kontenVerifikasi').html(html);







        // ── REVISI USD: jika keterangan mengandung "USD", ganti prefix Rp → USD di modal ──



        const ketModal = ($('#modal_keterangan').val() || '').toUpperCase();



        if (ketModal.includes('USD')) {



            $('#kontenVerifikasi').html(



                $('#kontenVerifikasi').html().replace(/Rp\s?/g, 'USD ')



            );



        }



        // ── END REVISI USD ──







        $('#modal_tgl_nota').datepicker({ dateFormat: 'dd-mm-yy', changeMonth: true, changeYear: true });



        hitungSubtotal();



    }).fail(function () {



        $('#kontenVerifikasi').html('<div class="alert alert-danger">Gagal memuat form verifikasi.</div>');



    });



}







// ── Hitung subtotal live ─────────────────────────────────────



function hitungSubtotal() {



    const qty   = parseFloat($('#modal_qty').val())   || 0;



    const harga = parseFloat($('#modal_harga').val()) || 0;



    // ── REVISI USD: prefix subtotal ikut keterangan ──



    const isUSD = ($('#modal_keterangan').val() || '').toUpperCase().includes('USD');



    const prefix = isUSD ? 'USD ' : 'Rp ';



    $('#modal_subtotal').text(



        prefix + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(qty * harga)



    );



    // ── END REVISI USD ──



}



$(document).on('input', '#modal_qty, #modal_harga, #modal_keterangan', hitungSubtotal);







// ── Approve ──────────────────────────────────────────────────



$(document).on('click', '#btnApprove', function () {



    const id = $(this).data('id');



    if (!confirm('Setujui dan simpan data pembelian ini ke buku realisasi?')) return;







    kirimVerifikasi({



        id_staging : id,



        aksi       : 'APPROVE',



        tgl_nota   : $('#modal_tgl_nota').val(),



        supplier   : $('#modal_supplier').val(),



        nama_barang: $('#modal_nama_barang').val(),



        qty        : $('#modal_qty').val(),



        satuan     : $('#modal_satuan').val(),



        harga      : $('#modal_harga').val(),



        alokasi    : $('#modal_alokasi').val(),



        keterangan : $('#modal_keterangan').val(),



        catatan    : $('#modal_catatan').val(),



        id_mobil   : $('#modal_id_mobil').val(),



    });



});







// ── Tolak ────────────────────────────────────────────────────



$(document).on('click', '#btnTolak', function () {



    const id      = $(this).data('id');



    const catatan = $('#modal_catatan').val().trim();



    if (!catatan) {



        showToast('Isi catatan alasan penolakan terlebih dahulu!', 'warning');



        $('#modal_catatan').focus();



        return;



    }



    if (!confirm('Tolak data ini? Item akan kembali ke antrean PR petugas pembelian.')) return;



    kirimVerifikasi({ id_staging: id, aksi: 'TOLAK', catatan });



});







// ── Kirim verifikasi ke server ───────────────────────────────



function kirimVerifikasi(payload) {



    $.ajax({



        url     : 'proses_verifikasi.php',



        type    : 'POST',



        dataType: 'json',



        data    : payload,



        success : function (res) {



            if (res.status === 'ok') {



                const icon = res.aksi === 'APPROVE' ? '✅ ' : '❌ ';



                const type = res.aksi === 'APPROVE' ? 'success' : 'warning';



                showToast(icon + res.message, type);



                $('#modalVerifikasi').modal('hide');



                hapusBarisDanCekCard(payload.id_staging);



            } else {



                showToast('❌ ' + res.message, 'error');



            }



        },



        error: function () { showToast('❌ Terjadi kesalahan server.', 'error'); }



    });



}







// ── Hapus baris item; jika card PR kosong → hapus card juga ──

function hapusBarisDanCekCard(id_staging) {

    const $btn = $(`button[onclick="bukaVerifikasi(${id_staging})"]`);

    const $row = $btn.closest('tr');

    const $card = $row.closest('.pr-card');

    const $wrapper = $card.closest('.pr-cards-wrapper');



    $row.fadeOut(350, function () {

        $(this).remove();

        const sisaRows = $card.find('tbody tr').length;

        if (sisaRows === 0) {

            $card.fadeOut(400, function () {

                $(this).remove();

                if ($wrapper.find('.pr-card').length === 0) {

                    $wrapper.html(`

                        <div class="empty-state">

                            <div class="icon-wrap"><i class="fas fa-check-circle"></i></div>

                            <h5 class="fw-bold mt-2 mb-1">Semua sudah diverifikasi!</h5>

                            <p class="mb-0">Tidak ada pembelian menunggu verifikasi untuk kategori ini.</p>

                        </div>

                    `);

                }

                updateCounter();

            });

        } else {

            $card.find('.pr-item-count').html(`<i class="fas fa-boxes me-1"></i>${sisaRows} item`);

            updateCounter();

        }

    });

}



// ── Hitung total seluruh kategori dan badge masing-masing ──

function updateCounter() {

    const sisa = $('.pr-cards-wrapper .pr-card tbody tr').length;

    $('#counterMenunggu').text(sisa);

    $('.pr-cards-wrapper').each(function () {

        const kategori = $(this).attr('data-kategori');

        const jumlah = $(this).find('.pr-card tbody tr').length;

        $('.badge-kategori[data-kategori="' + kategori + '"]').text(jumlah);

    });

}



// ── DataTables riwayat ───────────────────────────────────────



$(document).ready(function () {



    $('#tabelRiwayat').DataTable({



        order   : [[0, 'desc']],



        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },



    });



});



</script>



<script>



    let idleTime = 0;



    const maxIdleMinutes = 15; // Samakan dengan server



    let lastServerUpdate = Date.now();



    let sessionValid = true;







    // Fungsi reset timer saat ada gerakan



    function resetTimer() {



        idleTime = 0;



        let now = Date.now();







        // Kirim sinyal ke server setiap 5 menit agar session PHP tidak expired



        if (now - lastServerUpdate > 300000) {



            fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')



                .then(response => response.json())



                .then(data => {



                    if (data.status !== 'success') {



                        sessionValid = false;



                        forceLogout();



                    }



                })



                .catch(err => {



                    console.error("Koneksi ke server terputus");



                });



            lastServerUpdate = now;



        }



    }







    // Fungsi paksa logout



    function forceLogout() {



        alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");



        // Redirect ke logout.php agar session server juga dihancurkan



        window.location.href = "http://192.168.31.200/pr_mcp/auth/logout.php?pesan=timeout";



    }







    // Pantau aktivitas user



    window.onload = resetTimer;



    document.onmousemove = resetTimer;



    document.onkeypress = resetTimer;



    document.onmousedown = resetTimer;



    document.onclick = resetTimer;



    document.onscroll = resetTimer;







    // Cek status idle setiap 1 menit



    setInterval(function() {



        idleTime++;



        // Cek session ke server juga



        fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')



            .then(response => response.json())



            .then(data => {



                if (data.status !== 'success') {



                    sessionValid = false;



                    forceLogout();



                }



            })



            .catch(err => {



                // Jika error koneksi, biarkan user tetap di halaman



            });



        if (idleTime >= maxIdleMinutes && sessionValid) {



            forceLogout();



        }



    }, 60000);



</script>




<script>
// Loading awal halaman; addEventListener tidak mengganggu window.onload = resetTimer.
(function () {
    function tutupLoadingHalaman() {
        var loading = document.getElementById('pageLoadingVerifikasi');
        if (!loading || loading.classList.contains('loading-selesai')) return;
        loading.classList.add('loading-selesai');
        window.setTimeout(function () { if (loading.parentNode) loading.parentNode.removeChild(loading); }, 350);
    }
    // Hilangkan ketika semua resource halaman selesai dimuat.
    if (document.readyState === 'complete') {
        tutupLoadingHalaman();
    } else {
        window.addEventListener('load', tutupLoadingHalaman, { once: true });
    }
    // Cadangan agar loading tidak menutupi halaman selamanya jika CDN gagal merespons.
    window.setTimeout(tutupLoadingHalaman, 12000);
})();
</script>
</body>



</html>