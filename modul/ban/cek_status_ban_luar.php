<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

$user_login = $_SESSION['nama'] ?? 'Unknown';

// ═══════════════════════════════════════════════════════════════
// PROSES: Masuk Stok Ban (POST)
// ═══════════════════════════════════════════════════════════════
$pesan      = '';
$pesan_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'masuk_stok') {

    $id_pembelian   = (int)   $_POST['id_pembelian'];
    $id_mobil       = (int)   $_POST['id_mobil'];
    $plat_nomor     = mysqli_real_escape_string($koneksi, trim($_POST['plat_nomor']));
    $driver         = mysqli_real_escape_string($koneksi, trim($_POST['driver']));
    $nama_barang    = mysqli_real_escape_string($koneksi, trim($_POST['nama_barang']));
    $no_request     = mysqli_real_escape_string($koneksi, trim($_POST['no_request']));
    $qty            = (float)  $_POST['qty'];
    $tgl_transaksi  = mysqli_real_escape_string($koneksi, trim($_POST['tgl_transaksi']));
    $keterangan     = mysqli_real_escape_string($koneksi, trim($_POST['keterangan'] ?? ''));

    // Cek apakah sudah pernah dimasukkan
    $cek = mysqli_query($koneksi, "SELECT is_masuk_stok_ban FROM pembelian WHERE id_pembelian = $id_pembelian");
    $row_cek = mysqli_fetch_assoc($cek);

    if (!$row_cek) {
        $pesan = 'Data pembelian tidak ditemukan.';
        $pesan_type = 'danger';
    } elseif ($row_cek['is_masuk_stok_ban'] == 1) {
        $pesan = 'Ban ini sudah pernah dimasukkan ke stok.';
        $pesan_type = 'warning';
    } else {
        // Hitung stok saat ini untuk ditampilkan (info saja)
        $sql_total = "SELECT 
                        COALESCE(SUM(CASE WHEN tipe_transaksi='MASUK'  THEN qty ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN tipe_transaksi='KELUAR' THEN qty ELSE 0 END), 0) AS stok_now
                      FROM stok_ban_luar";
        $res_total  = mysqli_query($koneksi, $sql_total);
        $row_total  = mysqli_fetch_assoc($res_total);
        $stok_now   = $row_total['stok_now'] ?? 0;

        // Insert ke stok_ban_luar
        $sql_ins = "INSERT INTO stok_ban_luar 
                        (id_pembelian, no_request, id_mobil, plat_nomor, driver,
                         nama_barang, qty, tipe_transaksi, tgl_transaksi, input_oleh, keterangan)
                    VALUES
                        ($id_pembelian, '$no_request', $id_mobil, '$plat_nomor', '$driver',
                         '$nama_barang', $qty, 'MASUK', '$tgl_transaksi', '$user_login', '$keterangan')";

        if (mysqli_query($koneksi, $sql_ins)) {
            // Update flag di tabel pembelian
            mysqli_query($koneksi, "UPDATE pembelian SET is_masuk_stok_ban = 1 WHERE id_pembelian = $id_pembelian");
            $pesan      = "Ban <strong>$nama_barang</strong> berhasil dimasukkan ke stok ban luar.";
            $pesan_type = 'success';
        } else {
            $pesan      = 'Gagal menyimpan data: ' . mysqli_error($koneksi);
            $pesan_type = 'danger';
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// QUERY: Ambil semua pembelian ban luar yang BELUM masuk stok
// Kondisi: is_ban = 1 dari tr_request_detail ATAU
//          data pembelian ban langsung (kategori_beli LIKE ban)
// ═══════════════════════════════════════════════════════════════
$sql_ban = "
    SELECT 
        p.id_pembelian,
        p.no_request,
        p.tgl_beli_barang,
        p.tgl_beli,
        p.supplier,
        p.nama_barang_beli,
        p.merk_beli,
        p.qty,
        p.harga,
        p.kategori_beli,
        p.plat_nomor,
        p.id_mobil,
        p.keterangan,
        p.id_request_detail,
        p.is_masuk_stok_ban,
        m.driver_tetap,
        m.jenis_kendaraan,
        m.merk_tipe,
        rd.is_ban        AS is_ban_detail,
        rd.status_item   AS status_item
    FROM pembelian p
    INNER JOIN tr_request_detail rd ON p.id_request_detail = rd.id_detail AND rd.is_ban = 1
    LEFT JOIN  master_mobil m       ON (
                                         (p.id_mobil > 0 AND p.id_mobil = m.id_mobil)
                                         OR
                                         (COALESCE(p.id_mobil, 0) = 0 AND rd.id_mobil > 0 AND rd.id_mobil = m.id_mobil)
                                         OR
                                         (COALESCE(p.id_mobil, 0) = 0 AND COALESCE(rd.id_mobil, 0) = 0 AND p.plat_nomor != '' AND p.plat_nomor = m.plat_nomor)
                                       )
    WHERE p.is_masuk_stok_ban = 0
      AND p.no_request LIKE 'PRB%'
    ORDER BY p.tgl_beli_barang DESC
";
$res_ban  = mysqli_query($koneksi, $sql_ban);
$list_ban = [];
while ($row = mysqli_fetch_assoc($res_ban)) {
    $list_ban[] = $row;
}
mysqli_free_result($res_ban);

// Hitung statistik ringkas
$total_menunggu = count($list_ban);
$sql_stok_now   = "SELECT 
    COALESCE(SUM(CASE WHEN tipe_transaksi='MASUK'  THEN qty ELSE 0 END), 0) -
    COALESCE(SUM(CASE WHEN tipe_transaksi='KELUAR' THEN qty ELSE 0 END), 0) AS stok_total
FROM stok_ban_luar";
$res_sn        = mysqli_query($koneksi, $sql_stok_now);
$row_sn        = mysqli_fetch_assoc($res_sn);
$stok_total    = $row_sn['stok_total'] ?? 0;

$sql_sudah     = "SELECT COUNT(*) AS jml FROM pembelian WHERE is_masuk_stok_ban = 1";
$res_sudah     = mysqli_query($koneksi, $sql_sudah);
$row_sudah     = mysqli_fetch_assoc($res_sudah);
$total_sudah   = $row_sudah['jml'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Status Ban Luar - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .navbar-mcp { background: var(--mcp-blue); }

        /* ── Stat Cards ── */
        .stat-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.09);
            transition: transform 0.25s;
            overflow: hidden;
            position: relative;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-icon {
            font-size: 3rem;
            opacity: 0.15;
            position: absolute;
            right: 18px;
            bottom: 10px;
        }

        /* ── Main Card ── */
        .main-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
        }

        /* ── Table ── */
        table.dataTable thead th {
            vertical-align: middle;
            text-align: center;
            background-color: #eef1f8;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        table.dataTable tbody td { vertical-align: middle; font-size: 0.82rem; }

        /* ── Badge ── */
        .badge-ban {
            background: #fff0e6;
            color: #c85a00;
            font-size: 0.7rem;
            padding: 3px 9px;
            border-radius: 20px;
            font-weight: 600;
            border: 1px solid #f9c08a;
        }
        .badge-menunggu {
            background: #fff9e6;
            color: #856404;
            font-size: 0.68rem;
            padding: 3px 9px;
            border-radius: 20px;
            font-weight: 600;
            border: 1px solid #ffc107;
        }

        /* ── Tombol Masuk Stok ── */
        .btn-masuk {
            background: linear-gradient(135deg, #198754, #0f5132);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 12px;
            transition: opacity 0.2s, transform 0.2s;
            white-space: nowrap;
        }
        .btn-masuk:hover { opacity: 0.88; transform: scale(1.03); color: #fff; }

        /* ── Modal ── */
        .modal-header-ban {
            background: linear-gradient(135deg, #0000FF, #003399);
            color: #fff;
            border-radius: 12px 12px 0 0;
        }
        .modal-content { border-radius: 14px; border: none; }
        .form-label { font-size: 0.82rem; font-weight: 600; color: #444; }
        .form-control, .form-select {
            font-size: 0.83rem;
            border-radius: 8px;
        }
        .info-stok-total {
            background: #e8f5e9;
            border-left: 4px solid #198754;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.83rem;
        }
    </style>
</head>
<body>

<!-- ═══ NAVBAR ═══ -->
<nav class="navbar navbar-mcp mb-4 py-2">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white fs-6">
            <i class="fas fa-tire me-2"></i> CEK STATUS BAN LUAR — PEMBELIAN
        </span>
        <div class="d-flex gap-2">
            <a href="laporan_stok_ban_luar.php" class="btn btn-sm btn-warning fw-bold px-3">
                <i class="fas fa-warehouse me-1"></i> LAPORAN STOK
            </a>
            <a href="../../index.php" class="btn btn-sm btn-danger fw-bold px-3">
                <i class="fas fa-rotate-left me-1"></i> KEMBALI
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">

    <!-- ═══ ALERT PESAN ═══ -->
    <?php if ($pesan): ?>
    <div class="alert alert-<?= $pesan_type ?> alert-dismissible fade show shadow-sm rounded-3 mb-3" role="alert">
        <i class="fas fa-<?= $pesan_type === 'success' ? 'check-circle' : ($pesan_type === 'warning' ? 'triangle-exclamation' : 'circle-xmark') ?> me-2"></i>
        <?= $pesan ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ═══ STAT CARDS ═══ -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card card bg-warning text-dark h-100">
                <div class="card-body py-3">
                    <div class="small fw-bold text-uppercase opacity-75">Menunggu Masuk Stok</div>
                    <h2 class="fw-bold mb-0 mt-1"><?= $total_menunggu ?> <small class="fs-6 fw-normal">item ban</small></h2>
                    <i class="fas fa-clock stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card text-white h-100" style="background: linear-gradient(135deg,#198754,#0f5132);">
                <div class="card-body py-3">
                    <div class="small fw-bold text-uppercase opacity-75">Total Stok Ban di Gudang</div>
                    <h2 class="fw-bold mb-0 mt-1"><?= number_format($stok_total, 0, ',', '.') ?> <small class="fs-6 fw-normal">pcs</small></h2>
                    <i class="fas fa-warehouse stat-icon"></i>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card card text-white h-100" style="background: linear-gradient(135deg,#0000FF,#003399);">
                <div class="card-body py-3">
                    <div class="small fw-bold text-uppercase opacity-75">Total Sudah Masuk Stok</div>
                    <h2 class="fw-bold mb-0 mt-1"><?= $total_sudah ?> <small class="fs-6 fw-normal">transaksi</small></h2>
                    <i class="fas fa-check-double stat-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ TABEL UTAMA ═══ -->
    <div class="main-card card mb-5">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold mb-0 text-primary">
                        <i class="fas fa-list-check me-2"></i>Daftar Pembelian Ban Luar — Belum Masuk Stok
                    </h6>
                    <small class="text-muted">Klik tombol <strong>Masuk Stok</strong> untuk memasukkan ban lama ke gudang</small>
                </div>
            </div>

            <div class="table-responsive">
                <table id="tabelBan" class="table table-hover table-bordered align-middle w-100">
                    <thead>
                        <tr>
                            <th width="4%">No</th>
                            <th>No. PR</th>
                            <th>Tgl Beli</th>
                            <th>Nama Ban / Barang</th>
                            <th>Merk</th>
                            <th class="text-center">Qty</th>
                            <th>Plat / Mobil</th>
                            <th>Driver</th>
                            <th>Supplier</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                    <?php if (empty($list_ban)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-5">
                                <i class="fas fa-check-circle fa-2x text-success mb-2 d-block"></i>
                                Semua pembelian ban luar sudah masuk ke stok gudang.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($list_ban as $b): ?>
                        <tr>
                            <td class="text-center text-muted"><?= $no++ ?></td>
                            <td>
                                <?php if ($b['no_request']): ?>
                                    <span class="badge bg-primary rounded-pill px-2"><?= htmlspecialchars($b['no_request']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= date('d/m/Y', strtotime($b['tgl_beli_barang'])) ?></div>
                                <small class="text-muted">Beli: <?= date('d/m/Y', strtotime($b['tgl_beli'])) ?></small>
                            </td>
                            <td>
                                <div class="fw-bold text-uppercase"><?= htmlspecialchars($b['nama_barang_beli']) ?></div>
                                <?php if ($b['kategori_beli']): ?>
                                    <span class="badge-ban"><?= htmlspecialchars($b['kategori_beli']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($b['merk_beli'] ?: '-') ?></td>
                            <td class="text-center fw-bold">
                                <?= rtrim(rtrim(number_format($b['qty'], 4, ',', '.'), '0'), ',') ?> pcs
                            </td>
                            <td>
                                <?php if ($b['plat_nomor']): ?>
                                    <span class="badge bg-dark rounded-pill px-2"><?= htmlspecialchars($b['plat_nomor']) ?></span>
                                <?php endif; ?>
                                <?php if ($b['merk_tipe']): ?>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($b['merk_tipe']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($b['driver_tetap'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($b['supplier'] ?: '-') ?></td>
                            <td class="text-center">
                                <?php
                                $st = $b['status_item'] ?? '';
                                if ($st === 'TERBELI') {
                                    echo '<span class="badge bg-success px-2 py-1"><i class="fas fa-check me-1"></i>TERBELI</span>';
                                } elseif ($st === 'APPROVED') {
                                    echo '<span class="badge bg-primary px-2 py-1"><i class="fas fa-thumbs-up me-1"></i>APPROVED</span>';
                                } elseif ($st === 'MENUNGGU VERIFIKASI') {
                                    echo '<span class="badge bg-info text-dark px-2 py-1"><i class="fas fa-hourglass-half me-1"></i>MENUNGGU VERIFIKASI</span>';
                                } elseif ($st === 'PENDING') {
                                    echo '<span class="badge-menunggu"><i class="fas fa-clock me-1"></i>PENDING</span>';
                                } elseif ($st === 'REJECTED') {
                                    echo '<span class="badge bg-danger px-2 py-1"><i class="fas fa-times me-1"></i>REJECTED</span>';
                                } elseif ($st === 'RETUR') {
                                    echo '<span class="badge bg-warning text-dark px-2 py-1"><i class="fas fa-undo me-1"></i>RETUR</span>';
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <?php if ($b['status_item'] === 'TERBELI'): ?>
                                <button type="button"
                                    class="btn-masuk btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalMasukStok"
                                    data-id="<?= $b['id_pembelian'] ?>"
                                    data-no_request="<?= htmlspecialchars($b['no_request'] ?? '') ?>"
                                    data-nama="<?= htmlspecialchars($b['nama_barang_beli']) ?>"
                                    data-qty="<?= (float)$b['qty'] ?>"
                                    data-plat="<?= htmlspecialchars($b['plat_nomor'] ?? '') ?>"
                                    data-driver="<?= htmlspecialchars($b['driver_tetap'] ?? '') ?>"
                                    data-id_mobil="<?= (int)$b['id_mobil'] ?>">
                                    <i class="fas fa-arrow-down me-1"></i> MASUK STOK
                                </button>
                                <?php else: ?>
                                <button type="button" class="btn btn-sm btn-secondary px-3" disabled
                                    title="Tombol aktif jika status TERBELI">
                                    <i class="fas fa-lock me-1"></i> MASUK STOK
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     MODAL: Konfirmasi Masuk Stok
════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalMasukStok" tabindex="-1" aria-labelledby="modalMasukStokLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content shadow-lg">
            <div class="modal-header modal-header-ban">
                <h6 class="modal-title fw-bold" id="modalMasukStokLabel">
                    <i class="fas fa-arrow-down me-2"></i> Input Ban Luar ke Stok Gudang
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="cek_status_ban_luar.php" id="formMasukStok">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="aksi"         value="masuk_stok">
                    <input type="hidden" name="id_pembelian" id="modal_id_pembelian">
                    <input type="hidden" name="id_mobil"     id="modal_id_mobil">

                    <!-- Info stok total saat ini -->
                    <div class="info-stok-total mb-3">
                        <i class="fas fa-warehouse me-2 text-success"></i>
                        Total stok ban di gudang saat ini:
                        <strong class="text-success fs-6 ms-1"><?= number_format($stok_total, 0, ',', '.') ?> pcs</strong>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">No. Purchase Request</label>
                            <input type="text" name="no_request" id="modal_no_request"
                                   class="form-control bg-light" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Ban / Barang <span class="text-danger">*</span></label>
                            <input type="text" name="nama_barang" id="modal_nama_barang"
                                   class="form-control bg-light text-uppercase fw-bold" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Qty Masuk <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="qty" id="modal_qty"
                                       class="form-control" min="0.01" step="0.01" required>
                                <span class="input-group-text">pcs</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Masuk Stok <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_transaksi" id="modal_tgl"
                                   class="form-control" required
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Plat Nomor</label>
                            <input type="text" name="plat_nomor" id="modal_plat"
                                   class="form-control text-uppercase" placeholder="Contoh: L 1234 AB">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Driver</label>
                            <input type="text" name="driver" id="modal_driver"
                                   class="form-control" placeholder="Nama driver">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Diinput Oleh</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($user_login) ?>" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2"
                                         readonly><?php 
                                        echo "BAN BEKAS";
                                        
                                    ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 px-4 pb-4">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-success rounded-3 fw-bold px-4">
                        <i class="fas fa-arrow-down me-1"></i> MASUKKAN KE STOK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ SCRIPTS ═══ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {

    // ── DataTable ──────────────────────────────────────────────
    $('#tabelBan').DataTable({
        pageLength : 25,
        language   : { url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json' },
        order      : [[2, 'desc']],
        columnDefs : [{ orderable: false, targets: [0, 10] }]
    });

    // ── Isi modal saat tombol MASUK STOK diklik ──────────────
    $('#modalMasukStok').on('show.bs.modal', function (e) {
        const btn = $(e.relatedTarget);
        $('#modal_id_pembelian').val(btn.data('id'));
        $('#modal_id_mobil').val(btn.data('id_mobil'));
        $('#modal_no_request').val(btn.data('no_request') || '-');
        $('#modal_nama_barang').val(btn.data('nama'));
        $('#modal_qty').val(btn.data('qty'));
        $('#modal_plat').val(btn.data('plat'));
        $('#modal_driver').val(btn.data('driver'));
    });

    // ── Konfirmasi sebelum submit ─────────────────────────────
    $('#formMasukStok').on('submit', function (e) {
        const nama = $('#modal_nama_barang').val();
        if (!confirm('Masukkan ban "' + nama + '" ke stok gudang?\n\nPastikan data sudah benar.')) {
            e.preventDefault();
        }
    });
});
</script>

<!-- ── Idle Timeout (sama dengan template) ── -->
<script>
let idleTime = 0;
const maxIdleMinutes = 15;
let lastServerUpdate = Date.now();

function resetTimer() {
    idleTime = 0;
    let now = Date.now();
    if (now - lastServerUpdate > 300000) {
        const depth  = window.location.pathname.split('/').length - 2;
        const prefix = '../'.repeat(Math.max(0, depth - 1));
        fetch(prefix + 'auth/keep_alive.php').catch(() => {});
        lastServerUpdate = now;
    }
}
window.onload = resetTimer;
document.onmousemove = document.onkeypress = document.onmousedown =
document.onclick = document.onscroll = resetTimer;

setInterval(function () {
    idleTime++;
    if (idleTime >= maxIdleMinutes) {
        alert('Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.');
        const depth  = window.location.pathname.split('/').length - 2;
        const prefix = '../'.repeat(Math.max(0, depth - 1));
        window.location.href = prefix + 'login.php?pesan=timeout';
    }
}, 60000);
</script>
</body>
</html>