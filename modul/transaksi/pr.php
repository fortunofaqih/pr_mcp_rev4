<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// ================= HITUNG PROGRES PER TIPE PR =================
// Query agregat: jumlah PR per tipe × per status
$q_progress = mysqli_query($koneksi, "
    SELECT
        CASE
            WHEN no_request LIKE 'PRB%' THEN 'PR BESAR'
            WHEN no_request LIKE 'PRI%' THEN 'PR IT'
            ELSE 'PR KECIL'
        END AS tipe_pr,
        status_request,
        COUNT(*) AS jumlah
    FROM tr_request
    GROUP BY tipe_pr, status_request
");

$progress = [
    'PR KECIL' => ['PENDING'=>0, 'PROSES'=>0, 'SELESAI'=>0, 'BATAL'=>0, 'DITOLAK'=>0, 'TOTAL'=>0],
    'PR BESAR' => ['PENDING'=>0, 'PROSES'=>0, 'SELESAI'=>0, 'BATAL'=>0, 'DITOLAK'=>0, 'TOTAL'=>0],
    'PR IT'    => ['PENDING'=>0, 'PROSES'=>0, 'SELESAI'=>0, 'BATAL'=>0, 'DITOLAK'=>0, 'TOTAL'=>0],
];

while ($p = mysqli_fetch_assoc($q_progress)) {
    $t = $p['tipe_pr'];
    $s = $p['status_request'];
    if (!isset($progress[$t])) continue;
    if (!isset($progress[$t][$s])) $progress[$t][$s] = 0;
    $progress[$t][$s] = (int) $p['jumlah'];
    $progress[$t]['TOTAL'] += (int) $p['jumlah'];
}

// Grand total semua tipe
$grand = ['PENDING'=>0,'PROSES'=>0,'SELESAI'=>0,'BATAL'=>0,'DITOLAK'=>0,'TOTAL'=>0];
foreach ($progress as $row) {
    foreach ($grand as $k => $v) {
        $grand[$k] += $row[$k] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request (PR) - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --mcp-blue: #0000FF; --mcp-dark: #00008B; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 25px; }
        .btn-mcp { background: var(--mcp-blue); color: white; border-radius: 8px; font-weight: bold; }
        .btn-mcp:hover { background: var(--mcp-dark); color: white; }
        .status-badge { font-size: 0.75rem; font-weight: 700; padding: 6px 15px; border-radius: 20px; text-transform: uppercase; }

        /* ── Filter Dropdown di Header Tabel ── */
        .filter-select {
            font-size: 0.7rem;
            padding: 2px 4px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            background-color: #fff;
            color: #333;
            width: 100%;
            margin-top: 4px;
            cursor: pointer;
        }
        .filter-select:focus {
            outline: none;
            border-color: #0000FF;
            box-shadow: 0 0 0 2px rgba(0,0,255,0.15);
        }

        /* ── KARTU PROGRES PER TIPE PR ── */
        .progress-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            overflow: hidden;
            height: 100%;
        }
        .progress-card .card-header {
            padding: 10px 15px;
            font-weight: 700;
            font-size: 0.85rem;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .progress-card.tipe-kecil .card-header { background: #0d6efd; }
        .progress-card.tipe-besar .card-header { background: #6f42c1; }
        .progress-card.tipe-it    .card-header { background: #0dcaf0; color: #003; }

        .progress-card .card-body { padding: 12px 15px; }

        .mini-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 4px 0;
            font-size: 0.8rem;
            border-bottom: 1px dashed #e5e7eb;
        }
        .mini-stat:last-child { border-bottom: none; }
        .mini-stat .label { color: #6b7280; font-weight: 600; }
        .mini-stat .value {
            font-weight: 700;
            font-size: 0.95rem;
            min-width: 32px;
            text-align: right;
        }
        .value-pending { color: #d97706; }
        .value-proses  { color: #0d6efd; }
        .value-selesai { color: #16a34a; }
        .value-batal   { color: #dc2626; }
        .value-ditolak { color: #7c2d12; }

        .total-row {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 2px solid #e5e7eb;
            font-size: 0.8rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            color: #111;
        }

        .btn-cetak-status {
            font-size: 0.7rem;
            padding: 3px 8px;
            font-weight: 700;
        }

        /* Tab kategori hanya mengatur tampilan; seluruh aksi dan query existing tetap digunakan. */
        .pr-tabs { gap: 8px; border-bottom: 1px solid #dee2e6; }
        .pr-tabs .nav-link {
            border: 1px solid #dee2e6;
            border-bottom: 0;
            border-radius: 10px 10px 0 0;
            color: #475569;
            background: #f8fafc;
            font-weight: 700;
            padding: 11px 18px;
        }
        .pr-tabs .nav-link:hover { color: #0000FF; background: #eff6ff; }
        .pr-tabs .nav-link.active {
            color: #0000FF;
            background: #fff;
            border-color: #dee2e6;
            box-shadow: inset 0 3px 0 #0000FF;
        }
        .pr-tabs .badge { font-size: .7rem; }
        .pr-table-caption { font-size: .85rem; color: #64748b; }
    </style>
</head>
<body class="pb-5">

<nav class="navbar navbar-mcp mb-4">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-file-invoice me-2"></i> PURCHASE REQUEST</span>
        <div>
            <a href="../../index.php" class="btn btn-danger"><i class="fas fa-rotate-left"></i> KEMBALI</a>

            <?php if ($_SESSION['role'] !== 'pemesan_pr_besar'): ?>
                <a href="tambah_request.php" class="btn btn-sm btn-warning fw-bold">
                    <i class="fas fa-plus-circle"></i> BUAT REQUEST BARU (BARANG KECIL)
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-sm btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalCetakTanggal">
                <i class="fas fa-print"></i> CETAK PER TANGGAL
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">

    <!-- ================= KARTU PROGRES PER TIPE PR ================= -->
    <div class="row g-3 mb-3">
        <?php
        $config_tipe = [
            'PR KECIL' => ['css'=>'tipe-kecil', 'icon'=>'fa-file-alt'],
            'PR BESAR' => ['css'=>'tipe-besar', 'icon'=>'fa-file-invoice-dollar'],
            'PR IT'    => ['css'=>'tipe-it',    'icon'=>'fa-laptop-code'],
        ];
        foreach ($config_tipe as $tipe => $cfg):
            $d = $progress[$tipe];
        ?>
        <div class="col-xl-4 col-md-6">
            <div class="progress-card <?= $cfg['css'] ?>">
                <div class="card-header">
                    <i class="fas <?= $cfg['icon'] ?> me-2"></i> <?= $tipe ?>
                </div>
                <div class="card-body">
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-clock me-1"></i> Blm approval</span>
                        <span class="value value-pending"><?= $d['PENDING'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-sync-alt me-1"></i> Proses</span>
                        <span class="value value-proses"><?= $d['PROSES'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-check-circle me-1"></i> Selesai</span>
                        <span class="value value-selesai"><?= $d['SELESAI'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-ban me-1"></i> Batal</span>
                        <span class="value value-batal"><?= $d['BATAL'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-times-circle me-1"></i> Ditolak</span>
                        <span class="value value-ditolak"><?= $d['DITOLAK'] ?></span>
                    </div>

                    <div class="total-row">
                        <span><i class="fas fa-layer-group me-1"></i> TOTAL</span>
                        <span><?= $d['TOTAL'] ?> PR</span>
                    </div>

                    <!-- Tombol cetak per status untuk tipe ini -->
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        <a href="cetak_progress_pr_pdf.php?tipe=<?= urlencode($tipe) ?>&status=PENDING" target="_blank"
                           class="btn btn-outline-warning btn-cetak-status">
                            <i class="fas fa-print"></i> Blm approval
                        </a>
                        <a href="cetak_progress_pr_pdf.php?tipe=<?= urlencode($tipe) ?>&status=PROSES" target="_blank"
                           class="btn btn-outline-primary btn-cetak-status">
                            <i class="fas fa-print"></i> Proses
                        </a>
                        <a href="cetak_progress_pr_pdf.php?tipe=<?= urlencode($tipe) ?>&status=SELESAI" target="_blank"
                           class="btn btn-outline-success btn-cetak-status">
                            <i class="fas fa-print"></i> Selesai
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Grand Total -->
        <div class="col-xl-4 col-md-6">
            <div class="progress-card" style="border: 2px solid #111;">
                <div class="card-header" style="background: #111;">
                    <i class="fas fa-chart-line me-2"></i> GRAND TOTAL (SEMUA TIPE)
                </div>
                <div class="card-body">
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-clock me-1"></i> Blm Approval</span>
                        <span class="value value-pending"><?= $grand['PENDING'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-sync-alt me-1"></i> Proses</span>
                        <span class="value value-proses"><?= $grand['PROSES'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-check-circle me-1"></i> Selesai</span>
                        <span class="value value-selesai"><?= $grand['SELESAI'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-ban me-1"></i> Batal</span>
                        <span class="value value-batal"><?= $grand['BATAL'] ?></span>
                    </div>
                    <div class="mini-stat">
                        <span class="label"><i class="fas fa-times-circle me-1"></i> Ditolak</span>
                        <span class="value value-ditolak"><?= $grand['DITOLAK'] ?></span>
                    </div>
                    <div class="total-row">
                        <span><i class="fas fa-layer-group me-1"></i> TOTAL KESELURUHAN</span>
                        <span><?= $grand['TOTAL'] ?> PR</span>
                    </div>

                    <!-- Cetak semua tipe per status -->
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        <a href="cetak_progress_pr_pdf.php?tipe=ALL&status=PENDING" target="_blank"
                           class="btn btn-outline-warning btn-cetak-status">
                            <i class="fas fa-print"></i> Blm approval
                        </a>
                        <a href="cetak_progress_pr_pdf.php?tipe=ALL&status=PROSES" target="_blank"
                           class="btn btn-outline-primary btn-cetak-status">
                            <i class="fas fa-print"></i> Proses
                        </a>
                        <a href="cetak_progress_pr_pdf.php?tipe=ALL&status=SELESAI" target="_blank"
                           class="btn btn-outline-success btn-cetak-status">
                            <i class="fas fa-print"></i> Selesai
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- ================= END KARTU PROGRES ================= -->

    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'hapus_sukses'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Berhasil!</strong> Data request telah dihapus secara permanen.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Tab kategori PR: hanya memfilter baris tabel pada sisi tampilan. -->
    <div class="mt-4">
        <ul class="nav pr-tabs" id="tabKategoriPR" role="tablist" aria-label="Kategori purchase request">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" data-tipe="PR" role="tab" aria-selected="true">
                    <i class="fas fa-file-alt me-1"></i> PR
                    <span class="badge bg-secondary ms-1"><?= $progress['PR KECIL']['TOTAL'] ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" data-tipe="PRB" role="tab" aria-selected="false">
                    <i class="fas fa-file-invoice-dollar me-1"></i> PRB
                    <span class="badge bg-secondary ms-1"><?= $progress['PR BESAR']['TOTAL'] ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" data-tipe="PRI" role="tab" aria-selected="false">
                    <i class="fas fa-laptop-code me-1"></i> PRI
                    <span class="badge bg-secondary ms-1"><?= $progress['PR IT']['TOTAL'] ?></span>
                </button>
            </li>
        </ul>
    </div>
    <div class="table-container" style="border-radius:0 12px 12px 12px;">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div class="fw-bold" id="judulTabelPR">DAFTAR REQUEST PR</div>
            <div class="pr-table-caption">Pilih kategori melalui tab untuk melihat request terkait.</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100" id="tablePR">
                <thead class="bg-light">
                    <tr class="small text-uppercase">
                        <th class="text-center">No</th>
                        <th>No. Request</th>
                        <th>Tanggal</th>
                        <th>Admin</th>
                        <th class="text-center">Total Item</th>
                        <th class="text-center" id="th-status">
                            Status
                            <select id="filterStatus" class="filter-select">
                                <option value="">-- Semua --</option>
                                <option value="PENDING">⏳ BELUM APPROVE</option>
                                <option value="PROSES">🔄 PROSES</option>
                                <option value="SELESAI">✅ SELESAI</option>
                                <option value="BATAL">❌ BATAL</option>
                                <option value="DITOLAK">🚫 DITOLAK</option>
                            </select>
                        </th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php
                    $no = 1;

                    $sql = "SELECT r.*,
                                p.id_po, p.no_po, p.status_po
                            FROM tr_request r
                            LEFT JOIN tr_purchase_order p ON r.id_request = p.id_request
                            ORDER BY r.id_request DESC";
                    $query = mysqli_query($koneksi, $sql);
                    while ($row = mysqli_fetch_array($query)) {
                        $id_req   = $row['id_request'];
                        $jml_item = mysqli_num_rows(mysqli_query($koneksi,
                            "SELECT id_detail FROM tr_request_detail WHERE id_request='$id_req'"));

                        $status_po   = $row['status_po'] ?? null;
                        $no_po       = $row['no_po']     ?? null;

                        switch ($row['status_request']) {
                            case 'PENDING' : $badge_color = 'bg-warning text-dark'; break;
                            case 'PROSES'  : $badge_color = 'bg-primary';           break;
                            case 'SELESAI' : $badge_color = 'bg-success';           break;
                            case 'BATAL'   : $badge_color = 'bg-danger';            break;
                            case 'DITOLAK' : $badge_color = 'bg-danger';            break;
                            default        : $badge_color = 'bg-secondary';
                        }

                        if ($status_po === 'OPEN') {
                            $po_badge_bg  = '#dcfce7'; $po_badge_col = '#166534';
                            $po_badge_bdr = '#86efac'; $po_badge_ico = 'fa-circle';
                            $po_badge_txt = 'OPEN';
                        } elseif ($status_po === 'CLOSE') {
                            $po_badge_bg  = '#f1f5f9'; $po_badge_col = '#475569';
                            $po_badge_bdr = '#cbd5e1'; $po_badge_ico = 'fa-check-double';
                            $po_badge_txt = 'CLOSE';
                        } else {
                            $status_po = null;
                        }

                        // Tipe PR (untuk label)
                        $tipe_row = 'KECIL';
                        if (strpos($row['no_request'], 'PRB') === 0) $tipe_row = 'BESAR';
                        elseif (strpos($row['no_request'], 'PRI') === 0) $tipe_row = 'IT';
                    ?>
                    <tr data-status="<?= $row['status_request'] ?>" data-tipe="<?= $tipe_row === 'BESAR' ? 'PRB' : ($tipe_row === 'IT' ? 'PRI' : 'PR') ?>">
                        <td class="text-center text-muted"><?= $no++ ?></td>
                        <td class="fw-bold text-primary">
                            <?= htmlspecialchars($row['no_request']) ?>
                            <?php if ($tipe_row === 'BESAR'): ?>
                                <span class="badge bg-secondary ms-1" style="font-size:.6rem;">BESAR</span>
                            <?php elseif ($tipe_row === 'IT'): ?>
                                <span class="badge bg-info text-dark ms-1" style="font-size:.6rem;">IT</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y', strtotime($row['tgl_request'])) ?></td>
                        <td class="text-uppercase"><?= htmlspecialchars($row['nama_pemesan']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-3"><?= $jml_item ?> Item</span>
                        </td>
                        <td class="text-center">
                            <span class="badge status-badge <?= $badge_color ?> d-block mb-1">
                                <?= $row['status_request'] ?>
                            </span>

                            <?php if ($row['kategori_pr'] === 'BESAR' && $status_po): ?>
                            <span style="
                                display:inline-block;
                                background:<?= $po_badge_bg ?>;
                                color:<?= $po_badge_col ?>;
                                border:1px solid <?= $po_badge_bdr ?>;
                                padding:2px 8px;
                                border-radius:50px;
                                font-size:.65rem;
                                font-weight:700;
                                margin-bottom:3px;
                            ">
                                <i class="fas <?= $po_badge_ico ?> me-1" style="font-size:.5rem;"></i>
                                PO: <?= $po_badge_txt ?>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($row['nama_pembeli'])):
                                $p = strtoupper($row['nama_pembeli']);
                                $c = "bg-info";
                                if ($p == "GANG")   $c = "bg-danger";
                                if ($p == "HENDRO") $c = "bg-success text-dark";
                            ?>
                                <span class="badge <?= $c ?>" style="font-size:.65rem;">
                                    <i class="fas fa-user me-1"></i><?= $p ?>
                                </span>
                            <?php else: ?>
                                <div class="small text-danger" style="font-size:.7rem;">
                                    <i class="fas fa-exclamation-circle me-1"></i>BELUM DISET
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-dark text-white btn-view-detail"
                                        data-id="<?= $row['id_request'] ?>"
                                        title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <?php if ($row['kategori_pr'] == 'BESAR' && !in_array($row['status_approval'], ['MENUNGGU APPROVAL', 'APPROVED 1', 'APPROVED 2', 'DITOLAK', ''])): ?>
                                    <a href="cetak_po.php?id_request=<?= $row['id_request'] ?>"
                                    target="_blank"
                                    class="btn btn-sm"
                                    style="background-color:#4f46e5;color:white;"
                                    title="Cetak PO<?= $no_po ? ' · '.$no_po : '' ?> | Status: <?= $po_badge_txt ?? 'DRAFT' ?>">
                                        <i class="fas fa-file-invoice"></i>
                                        <?php if ($status_po === 'OPEN'): ?>
                                            <span style="display:inline-block;width:6px;height:6px;background:#4ade80;border-radius:50%;margin-left:2px;vertical-align:middle;" title="PO OPEN"></span>
                                        <?php elseif ($status_po === 'CLOSE'): ?>
                                            <span style="display:inline-block;width:6px;height:6px;background:#94a3b8;border-radius:50%;margin-left:2px;vertical-align:middle;" title="PO CLOSE"></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $is_pr_besar = (
                                    $row['kategori_pr'] === 'BESAR' ||
                                    strpos($row['no_request'] ?? '', 'PRB') === 0 ||
                                    strpos($row['no_request'] ?? '', 'PRI') === 0
                                );
                                $file_cetak = $is_pr_besar ? 'cetak_pr_besar.php' : 'cetak_pr.php';

                                $is_besar_or_it = in_array($row['kategori_pr'] ?? '', ['BESAR', 'IT'], true);
                                $btn_class   = $is_besar_or_it ? 'btn-danger' : 'btn-info text-white';
                                $btn_title   = $is_besar_or_it ? 'Cetak PR Besar (QR)' : 'Cetak PR Kecil';
                                $show_qr     = $is_besar_or_it;
                                ?>

                                <a href="<?= htmlspecialchars($file_cetak) ?>?id=<?= urlencode($row['id_request'] ?? '') ?>"
                                target="_blank"
                                class="btn btn-sm <?= $btn_class ?>"
                                title="<?= htmlspecialchars($btn_title) ?>">
                                    <i class="fas fa-print"></i>
                                    <?php if ($show_qr): ?>
                                        <i class="fas fa-qrcode ms-1" style="font-size:.6rem;opacity:.8;"></i>
                                    <?php endif; ?>
                                </a>

                                <?php
                                $link_edit = ($row['kategori_pr'] == 'BESAR') ? 'edit_request_besar.php' : 'edit_request.php';
                                $status_bisa_edit = in_array($row['status_request'], ['PENDING', 'PROSES']) || $row['status_approval'] == 'DITOLAK';

                                if ($status_bisa_edit):
                                ?>
                                    <a href="<?= $link_edit ?>?id=<?= $row['id_request'] ?>"
                                       class="btn btn-sm btn-warning"
                                       title="<?= ($row['status_approval'] == 'DITOLAK') ? 'Revisi PR' : 'Edit PR' ?>">
                                        <?php if ($row['status_approval'] == 'DITOLAK'): ?>
                                            <i class="fas fa-redo me-1"></i> Revisi
                                        <?php else: ?>
                                            <i class="fas fa-edit"></i> Edit
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($row['status_request'] == 'PENDING' && $row['kategori_pr'] != 'BESAR'): ?>
                                    <a href="hapus_request.php?id=<?= $row['id_request'] ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Hapus seluruh form request ini?')"
                                    title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detail PR -->
<div class="modal fade" id="modalDetailPR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title small fw-bold"><i class="fas fa-info-circle me-2"></i>DETAIL ITEM PURCHASE REQUEST</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="kontenDetail">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 small text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-sm btn-secondary fw-bold" data-bs-dismiss="modal">TUTUP</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cetak Per Tanggal -->
<div class="modal fade" id="modalCetakTanggal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title small"><i class="fas fa-filter me-2"></i> FILTER CETAK REQUEST</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="cetak_pr_bulk.php" method="GET" target="_blank">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small fw-bold">PILIH TANGGAL REQUEST</label>
                        <input type="date" name="tgl" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">STATUS REQUEST</label>
                        <select name="status" class="form-select">
                            <option value="PENDING">MENUNGGU APPROVAL</option>
                            <option value="SELESAI">SELESAI (Sudah Dibelikan)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100 fw-bold">PROSES & CETAK</button>
                </div>S
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function () {
    const table = $('#tablePR').DataTable({
        destroy     : true,
        pageLength  : 10,
        order       : [[0, 'asc']],
        language    : { url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json' },
        columnDefs  : [ { orderable: false, targets: [4, 5, 6] } ]
    });

    // Tab hanya menyaring baris yang sudah dimuat oleh query existing.
    let tipeAktif = 'PR';
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        // Jangan sampai filter halaman ini memengaruhi DataTable lain.
        if (settings.nTable.id !== 'tablePR') return true;

        const rowNode = table.row(dataIndex).node();
        const tipeRow = $(rowNode).attr('data-tipe') || '';
        const statusRow = $(rowNode).attr('data-status') || '';
        const pilihan = $('#filterStatus').val();

        return tipeRow === tipeAktif && (!pilihan || statusRow === pilihan);
    });

    $('#tabKategoriPR button[data-tipe]').on('click', function () {
        tipeAktif = $(this).attr('data-tipe');
        $('#tabKategoriPR button[data-tipe]')
            .removeClass('active')
            .attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        $('#judulTabelPR').text('DAFTAR REQUEST ' + tipeAktif);
        table.page('first').draw();
    });

    $('#filterStatus').on('click', function (e) { e.stopPropagation(); });
    $('#filterStatus').on('change', function () { table.draw(); });
    table.draw(); // Saat pertama dibuka, tampilkan hanya PR biasa.

    $(document).on('click', '.btn-view-detail', function () {
        const id = $(this).data('id');
        $('#modalDetailPR').modal('show');
        $('#kontenDetail').html(
            '<div class="text-center p-5">' +
            '<div class="spinner-border text-primary"></div>' +
            '<p class="mt-2 small text-muted">Memuat data...</p>' +
            '</div>'
        );
        $.ajax({
            url    : 'get_detail_pr.php',
            type   : 'GET',
            data   : { id: id },
            success: function (response) { $('#kontenDetail').html(response); },
            error  : function () { $('#kontenDetail').html('<div class="alert alert-danger m-3">Gagal mengambil data.</div>'); }
        });
    });
});
</script>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    const pesan     = urlParams.get('pesan');
    const no_pr     = urlParams.get('no');

    if (pesan === 'berhasil') {
        let textTampil = (no_pr && no_pr !== 'null')
            ? `Request <b>${no_pr}</b> telah berhasil dibuat dan disimpan.`
            : `Request telah berhasil dibuat dan disimpan ke sistem.`;
        Swal.fire({ icon: 'success', title: 'BERHASIL!', html: textTampil, confirmButtonColor: '#0000FF' });
    } else if (pesan === 'gagal') {
        Swal.fire({ icon: 'error', title: 'GAGAL!', text: 'Terjadi kesalahan sistem saat menyimpan data.', confirmButtonColor: '#d33' });
    } else if (pesan === 'update_sukses') {
        Swal.fire({ icon: 'success', title: 'BERHASIL!', text: 'Data request berhasil diperbarui.', confirmButtonColor: '#0000FF' });
    } else if (pesan === 'revisi_berhasil') {
        Swal.fire({
            icon: 'success',
            title: 'Revisi Berhasil!',
            html: 'PR telah direvisi dan dikirim ulang.<br><small class="text-muted">Menunggu persetujuan Manager dari awal.</small>',
            confirmButtonColor: '#6d28d9'
        });
    }

    window.history.replaceState({}, document.title, window.location.pathname);
</script>

<script>
    let idleTime = 0;
    const maxIdleMinutes = 15;
    let lastServerUpdate = Date.now();
    let sessionValid = true;

    function resetTimer() {
        idleTime = 0;
        let now = Date.now();
        if (now - lastServerUpdate > 300000) {
            fetch('../../auth/keep_alive.php')
                .then(response => response.json())
                .then(data => { if (data.status !== 'success') { sessionValid = false; forceLogout(); } })
                .catch(err => { console.error("Koneksi ke server terputus"); });
            lastServerUpdate = now;
        }
    }

    function forceLogout() {
        alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
        window.location.href = "../../auth/logout.php?pesan=timeout";
    }

    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    setInterval(function() {
        idleTime++;
        fetch('../../auth/keep_alive.php')
            .then(response => response.json())
            .then(data => { if (data.status !== 'success') { sessionValid = false; forceLogout(); } })
            .catch(err => {});
        if (idleTime >= maxIdleMinutes && sessionValid) { forceLogout(); }
    }, 60000);
</script>
</body>
</html>