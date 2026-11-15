<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Ambil data kondisi terakhir untuk setiap mesin
$query_kondisi_terakhir = "
    SELECT km.id_mesin, km.kondisi_mesin, km.start_date, km.end_date
    FROM kondisi_mesin km
    INNER JOIN (
        SELECT id_mesin, MAX(start_date) as max_start_date
        FROM kondisi_mesin
        GROUP BY id_mesin
    ) latest ON km.id_mesin = latest.id_mesin AND km.start_date = latest.max_start_date
";
$result_kondisi = mysqli_query($koneksi, $query_kondisi_terakhir);
$kondisi_terakhir = [];
while ($row = mysqli_fetch_assoc($result_kondisi)) {
    $kondisi_terakhir[$row['id_mesin']] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Mesin - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --mcp-blue: #0000FF;
            --mcp-blue-soft: #eef1ff;
            --mcp-gray: #f4f6fb;
            --mcp-border: #e3e8f0;
            --mcp-text: #2c3345;
        }

        * { font-family: 'Segoe UI', 'Inter', 'Helvetica Neue', Arial, sans-serif; }

        body {
            background-color: var(--mcp-gray);
            color: var(--mcp-text);
            font-size: 0.92rem;
        }

        .navbar-mcp {
            background: linear-gradient(90deg, var(--mcp-blue) 0%, #2a4dff 100%);
            color: #fff;
            box-shadow: 0 3px 12px rgba(0,0,255,0.18);
        }
        .navbar-mcp .navbar-brand {
            letter-spacing: 0.4px;
            font-size: 1.05rem;
        }

        .card {
            border-radius: 14px;
            border: 1px solid var(--mcp-border);
            box-shadow: 0 6px 22px rgba(30, 42, 80, 0.06);
            background: #fff;
        }
        .card-body { padding: 1.15rem 1.25rem; }

        /* ===== Tabel ===== */
        table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
            font-size: 0.9rem;
        }
        table.dataTable thead th {
            background-color: var(--mcp-blue-soft) !important;
            color: #1c2b6e;
            font-weight: 600;
            font-size: 0.78rem;
            letter-spacing: 0.35px;
            text-transform: uppercase;
            border-bottom: 2px solid #d5ddff !important;
            padding: 0.75rem 0.65rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        table.dataTable tbody td {
            padding: 0.65rem 0.65rem;
            vertical-align: middle;
            border-color: var(--mcp-border);
        }
        table.dataTable tbody tr:hover { background-color: #f6f9ff !important; }

        /* ===== Baris filter di tfoot ===== */
        table.dataTable tfoot th {
            background-color: #fafbff !important;
            border-top: 1px solid var(--mcp-border) !important;
            border-bottom: 1px solid var(--mcp-border) !important;
            padding: 0.45rem 0.5rem;
        }
        table.dataTable tfoot th .filter-input,
        table.dataTable tfoot th .filter-select {
            width: 100%;
            font-size: 0.78rem;
            padding: 0.35rem 0.5rem;
            border: 1px solid #cfd6e6;
            border-radius: 7px;
            background: #fff;
            color: var(--mcp-text);
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        table.dataTable tfoot th .filter-input:focus,
        table.dataTable tfoot th .filter-select:focus {
            outline: none;
            border-color: var(--mcp-blue);
            box-shadow: 0 0 0 3px rgba(0,0,255,0.10);
        }
        table.dataTable tfoot th .filter-input::placeholder {
            color: #a4adc0;
            font-size: 0.75rem;
        }
        .filter-hint {
            font-size: 0.68rem;
            color: #8a93a6;
            margin-top: 3px;
            display: block;
            letter-spacing: 0.2px;
        }

        /* ===== Badge kondisi ===== */
        .badge {
            font-weight: 600;
            font-size: 0.72rem;
            letter-spacing: 0.25px;
            padding: 0.42em 0.7em;
            border-radius: 7px;
        }
        .badge-active { background-color: #28a745; color: #fff; }
        .badge-inactive { background-color: #dc3545; color: #fff; }
        .badge-baik { background-color: #28a745; color: #fff; }
        .badge-diservice { background-color: #ffc107; color: #000; }
        .badge-dibongkar { background-color: #dc3545; color: #fff; }
        .badge-dipoles { background-color: #17a2b8; color: #fff; }
        .badge-dipopok { background-color: #6f42c1; color: #fff; }
        .badge-dibubut { background-color: #fd7e14; color: #fff; }
        .badge-spei { background-color: #20c997; color: #fff; }
        .badge-stell { background-color: #e83e8c; color: #fff; }
        .badge-gulung-dinamo { background-color: #6c757d; color: #fff; }

        /* ===== Tombol aksi & toolbar ===== */
        .btn-sm { font-size: 0.75rem; }
        .btn-kondisi {
            background: #5d615d;
            color: #f8f5f5;
            border: 1px solid #c4c0b4;
        }
        .btn-kondisi:hover {
            background: #e0a800;
            color: #000;
            border-color: #e0a800;
        }
        .btn-laporan {
            background: #17a2b8;
            color: #fff;
            border: 1px solid #17a2b8;
        }
        .btn-laporan:hover {
            background: #138496;
            color: #fff;
            border-color: #138496;
        }
        .btn-group .btn { border-radius: 6px; }

        /* ===== DataTables ===== */
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #cfd6e6;
            border-radius: 8px;
            padding: 0.35rem 0.6rem;
            font-size: 0.82rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: var(--mcp-blue);
            box-shadow: 0 0 0 3px rgba(0,0,255,0.10);
        }
        /* ===== DataTables ===== */
        .dataTables_wrapper .dataTables_length select {
            border-radius: 8px;
            border: 1px solid #cfd6e6;
            font-size: 0.82rem;
            padding: 0.25rem 1.75rem 0.25rem 0.5rem; /* kanan diperbesar utk panah */
            min-width: 4.5rem;
            background-position: right 0.4rem center;  /* opsional, jika pakai custom arrow */
            background-repeat: no-repeat;
            background-size: 10px;
        }
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            font-size: 0.82rem;
            color: #5a6478;
        }
        .pagination .page-link {
            border-radius: 7px !important;
            margin: 0 2px;
            font-size: 0.8rem;
            color: var(--mcp-blue);
            border-color: #d7ddf0;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--mcp-blue);
            border-color: var(--mcp-blue);
            color: #fff;
        }

        /* ===== Responsive ===== */
        @media (max-width: 768px) {
            .navbar-brand { font-size: 0.9rem; }
            .btn-sm { font-size: 0.7rem; padding: 0.25rem 0.5rem; }
            .table-responsive { font-size: 0.8rem; }
            .card-body { padding: 0.75rem; }
            table.dataTable thead th { font-size: 0.7rem; padding: 0.55rem 0.4rem; }
            table.dataTable tbody td { padding: 0.5rem 0.4rem; }
            table.dataTable tfoot th { padding: 0.35rem 0.35rem; }
            table.dataTable tfoot th .filter-input,
            table.dataTable tfoot th .filter-select { font-size: 0.7rem; padding: 0.3rem 0.4rem; }
            .filter-hint { display: none; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-3">
    <div class="container-fluid px-3 px-sm-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-industry me-2"></i> MASTER MESIN</span>
        <div class="d-flex flex-wrap gap-1">
            <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> HOME</a>
            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalTambahMesin" onclick="resetFormTambah()">
                <i class="fas fa-plus-circle"></i> TAMBAH MESIN
            </button>
            <!-- TOMBOL TAMBAH KONDISI — dipindahkan dari kondisi_mesin.php -->
            <button class="btn btn-sm btn-kondisi" data-bs-toggle="modal" data-bs-target="#modalTambahKondisi" onclick="resetFormTambahKondisi()">
                <i class="fas fa-plus-circle"></i> TAMBAH KONDISI
            </button>
            <button class="btn btn-sm btn-success" onclick="window.location.href='kondisi_mesin.php'">
                <i class="fas fa-heartbeat"></i> STATUS MESIN
            </button>
            <button class="btn btn-sm btn-laporan" onclick="window.location.href='laporan_kondisi_mesin.php'">
                <i class="fas fa-file-alt"></i> LAPORAN
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelMesin" class="table table-hover table-striped align-middle w-100">
                    <colgroup>
                        <col style="width: 10%;">
                        <col style="width: 18%;">
                        <col style="width: 22%;">
                        <col style="width: 12%;">
                        <col style="width: 10%;">
                        <col style="width: 18%;">
                        <col style="width: 10%;">
                    </colgroup>
                    <thead class="small text-uppercase">
                        <tr>
                            <th>ID Mesin</th>
                            <th>Nama Mesin</th>
                            <th>Spesifikasi</th>
                            <th>Kapasitas</th>
                            <th>Unit</th>
                            <th>Kondisi</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = mysqli_query($koneksi, "
                            SELECT * FROM master_mesin 
                            ORDER BY active DESC, name ASC
                        ");
                        while ($d = mysqli_fetch_array($query)) {
                            $active = (int)$d['active'] === 1;
                            $id_mesin = (int)$d['id'];

                            $kondisi = 'BAIK';
                            if (isset($kondisi_terakhir[$id_mesin])) {
                                $kondisi = $kondisi_terakhir[$id_mesin]['kondisi_mesin'];
                            }

                            $badge_map = [
                                'BAIK' => '<span class="badge badge-baik"><i class="fas fa-check-circle me-1"></i>BAIK</span>',
                                'DISERVICE' => '<span class="badge badge-diservice"><i class="fas fa-tools me-1"></i>DISERVICE</span>',
                                'DIBONGKAR' => '<span class="badge badge-dibongkar"><i class="fas fa-wrench me-1"></i>DIBONGKAR</span>',
                                'DIPOLES' => '<span class="badge badge-dipoles"><i class="fas fa-brush me-1"></i>DIPOLES</span>',
                                'DI POPOK' => '<span class="badge badge-dipopok"><i class="fas fa-baby me-1"></i>DI POPOK</span>',
                                'DI BUBUT' => '<span class="badge badge-dibubut"><i class="fas fa-cog me-1"></i>DI BUBUT</span>',
                                'SPEI' => '<span class="badge badge-spei"><i class="fas fa-gear me-1"></i>SPEI</span>',
                                'STELL' => '<span class="badge badge-stell"><i class="fas fa-circle me-1"></i>STELL</span>',
                                'GULUNG DINAMO' => '<span class="badge badge-gulung-dinamo"><i class="fas fa-bolt me-1"></i>GULUNG DINAMO</span>'
                            ];

                            $badge_kondisi = isset($badge_map[$kondisi]) ? $badge_map[$kondisi] : '<span class="badge bg-secondary">' . htmlspecialchars($kondisi) . '</span>';

                            $spec_raw = $d['spec'] ?? '';
                            $spec_short = mb_strlen($spec_raw, 'UTF-8') > 50
                                ? mb_substr($spec_raw, 0, 50, 'UTF-8') . '...'
                                : $spec_raw;
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($d['id_mesin']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($d['name']) ?></td>
                            <td><?= htmlspecialchars($spec_short) ?></td>
                            <td><?= htmlspecialchars($d['capacity'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['unit'] ?? '-') ?></td>
                            <td><?= $badge_kondisi ?></td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="editMesin(<?= (int)$d['id'] ?>)"
                                            title="Edit Mesin">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="hapusMesin(<?= (int)$d['id'] ?>, '<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')"
                                            title="Hapus Mesin">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                    <a href="laporan_kondisi_mesin_print.php?id_mesin=<?= (int)$d['id'] ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-success"
                                       title="Riwayat Kondisi Mesin">
                                        <i class="fas fa-history"></i> Riwayat
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                    <!-- ===== BARIS FILTER PER KOLOM (di bawah kolom, di atas pagination) ===== -->
                    <tfoot>
                        <tr>
                            <th>
                                <input type="text" class="filter-input" data-col="0" placeholder="Filter ID...">
                                
                            </th>
                            <th>
                                <input type="text" class="filter-input" data-col="1" placeholder="Filter nama...">
                            </th>
                            <th>
                                <input type="text" class="filter-input" data-col="2" placeholder="Filter spesifikasi...">
                            </th>
                            <th>
                                <input type="text" class="filter-input" data-col="3" placeholder="Filter kapasitas...">
                            </th>
                            <th>
                                <input type="text" class="filter-input" data-col="4" placeholder="Filter unit...">
                            </th>
                            <th>
                                <select class="filter-select" data-col="5">
                                    <option value="">Semua Kondisi</option>
                                    <option value="BAIK">BAIK</option>
                                    <option value="DISERVICE">DISERVICE</option>
                                    <option value="DIBONGKAR">DIBONGKAR</option>
                                    <option value="DIPOLES">DIPOLES</option>
                                    <option value="DI POPOK">DI POPOK</option>
                                    <option value="DI BUBUT">DI BUBUT</option>
                                     <option value="DI GANTI">DI GANTI</option>
                                    <option value="SPEI">SPEI</option>
                                    <option value="STELL">STELL</option>
                                    <option value="GULUNG DINAMO">GULUNG DINAMO</option>
                                </select>
                            </th>
                            <th class="text-center">
                                <button type="button" id="btnResetFilter" class="btn btn-sm btn-outline-secondary w-100" title="Reset semua filter">
                                    <i class="fas fa-eraser"></i> Reset
                                </button>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Tambah/Edit Mesin -->
<div class="modal fade" id="modalTambahMesin" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-plus-circle me-2"></i> Tambah Mesin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formMesin">
                <input type="hidden" id="id_edit" name="id_edit" value="0">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">ID Mesin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="id_mesin" name="id_mesin" placeholder="Contoh: M-001" required>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Nama Mesin <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Nama mesin" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Kapasitas</label>
                            <input type="text" class="form-control" id="capacity" name="capacity" placeholder="Contoh: 1000 kg/jam">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit" placeholder="Nama Unit">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Spesifikasi</label>
                        <textarea class="form-control" id="spec" name="spec" rows="3" placeholder="Detail spesifikasi mesin"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Tanggal Manufactur</label>
                            <input type="date" class="form-control" id="manufactured_date" name="manufactured_date">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Manufactured By</label>
                            <input type="text" class="form-control" id="manufactured_by" name="manufactured_by" placeholder="Pabrik pembuat">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Supplier</label>
                            <input type="text" class="form-control" id="supplier" name="supplier" placeholder="Supplier">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Harga Beli</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" id="purchase_price" name="purchase_price" placeholder="0" step="0.01">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Tanggal Beli</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Acc Reff</label>
                            <input type="text" class="form-control" id="acc_reff" name="acc_reff" placeholder="Referensi akuntansi">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" id="active" name="active">
                                <option value="1">AKTIF</option>
                                <option value="0">NONAKTIF</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Keterangan</label>
                            <input type="text" class="form-control" id="remarks" name="remarks" placeholder="Catatan tambahan">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL: TAMBAH/EDIT KONDISI (dipindahkan dari kondisi_mesin.php) ================= -->
<div class="modal fade" id="modalTambahKondisi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTambahKondisiTitle"><i class="fas fa-plus-circle me-2"></i> Tambah Kondisi Mesin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formKondisi">
                <input type="hidden" id="id_edit_kondisi" name="id_edit_kondisi" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih Mesin <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_mesin_kondisi" name="id_mesin" required>
                            <option value="">-- Pilih Mesin --</option>
                            <?php
                            $query_mesin = mysqli_query($koneksi, "SELECT id, id_mesin, name FROM master_mesin WHERE active = 1 ORDER BY name ASC");
                            while ($m = mysqli_fetch_array($query_mesin)) {
                                echo '<option value="' . (int)$m['id'] . '">' . htmlspecialchars($m['id_mesin']) . ' - ' . htmlspecialchars($m['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kondisi <span class="text-danger">*</span></label>
                        <select class="form-select" id="kondisi_mesin" name="kondisi_mesin" required>
                            <option value="BAIK">BAIK</option>
                            <option value="DISERVICE">DISERVICE</option>
                            <option value="DIBONGKAR">DIBONGKAR</option>
                            <option value="DIPOLES">DIPOLES</option>
                            <option value="DI POPOK">DI POPOK</option>
                            <option value="DI BUBUT">DI BUBUT</option>
                            <option value="SPEI">SPEI</option>
                            <option value="STELL">STELL</option>
                            <option value="DI COR">DI COR</option>
                            <option value="SELEP">SELEP</option>
                            <option value="DI GANTI">DI GANTI</option>
                            <option value="GULUNG DINAMO">GULUNG DINAMO</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan Service</label>
                        <textarea class="form-control" id="keterangan_service_mesin" name="keterangan_service_mesin" rows="3" placeholder="Deskripsi kondisi atau service"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Mulai <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="start_date_kondisi" name="start_date" required>
                    </div>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') { ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Selesai</label>
                        <input type="date" class="form-control" id="end_date_kondisi" name="end_date">
                        <small class="text-muted">Kosongkan jika masih aktif</small>
                    </div>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Import CSV (dinonaktifkan) -->
<div class="modal fade" id="modalImportCSV" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-file-import me-2"></i> Import Data Mesin (CSV)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formImport" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Format CSV: id_mesin, name, spec, manufactured_date, manufactured_by, supplier, purchase_price, purchase_date, acc_reff, remarks, active, capacity, unit
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih File CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file_csv" name="file_csv" accept=".csv" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="skip_first_row" name="skip_first_row" value="1" checked>
                            <label class="form-check-label" for="skip_first_row">Lewati baris pertama (header)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    // ===== Inisialisasi DataTables =====
    var table = $('#tabelMesin').DataTable({
        pageLength: 10,
        language: { url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
        columnDefs: [{ orderable: false, targets: 6 }],
        responsive: true,
        autoWidth: false,
        // Urutkan default (tetap sesuai query: active DESC, name ASC)
        order: []
    });

    // ===== Filter per kolom (tfoot) =====
    // Untuk kolom teks (ID, Nama, Spesifikasi, Kapasitas, Unit)
    $('#tabelMesin tfoot').on('keyup change', 'input.filter-input', function () {
        var colIdx = parseInt($(this).data('col'), 10);
        var val = $(this).val();
        // Gunakan regex agar pencarian tidak dianggap regex, dan case-insensitive
        table.column(colIdx).search(val, true, false).draw();
    });

    // Untuk kolom Kondisi (select)
    $('#tabelMesin tfoot').on('change', 'select.filter-select', function () {
        var colIdx = parseInt($(this).data('col'), 10);
        var val = $(this).val();
        table.column(colIdx).search(val, true, false).draw();
    });

    // Tombol reset semua filter
    $('#btnResetFilter').on('click', function () {
        $('#tabelMesin tfoot input.filter-input').val('');
        $('#tabelMesin tfoot select.filter-select').val('');
        table.columns().search('').draw();
        // Kosongkan juga search global agar benar-benar bersih
        table.search('').draw();
    });
});

/* ============ FORM MESIN ============ */
function resetFormTambah() {
    document.getElementById('formMesin').reset();
    document.getElementById('id_edit').value = '0';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Tambah Mesin';
    document.getElementById('active').value = '1';
}

function editMesin(id) {
    $.ajax({
        url: 'ajax_get_mesin.php',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const data = response.data;
                document.getElementById('id_edit').value = data.id;
                document.getElementById('id_mesin').value = data.id_mesin;
                document.getElementById('name').value = data.name;
                document.getElementById('spec').value = data.spec || '';
                document.getElementById('manufactured_date').value = data.manufactured_date || '';
                document.getElementById('manufactured_by').value = data.manufactured_by || '';
                document.getElementById('unit').value = data.unit || '';
                document.getElementById('supplier').value = data.supplier || '';
                document.getElementById('purchase_price').value = data.purchase_price || '';
                document.getElementById('purchase_date').value = data.purchase_date || '';
                document.getElementById('acc_reff').value = data.acc_reff || '';
                document.getElementById('remarks').value = data.remarks || '';
                document.getElementById('active').value = data.active;
                document.getElementById('capacity').value = data.capacity || '';

                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Mesin';
                $('#modalTambahMesin').modal('show');
            } else {
                alert('Gagal mengambil data mesin: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            alert('Error saat mengambil data mesin!');
        }
    });
}

$('#formMesin').on('submit', function (e) {
    e.preventDefault();

    const id_mesin = document.getElementById('id_mesin').value.trim();
    const name = document.getElementById('name').value.trim();

    if (!id_mesin || !name) {
        alert('ID Mesin dan Nama Mesin wajib diisi!');
        return;
    }

    const formData = new FormData(this);
    const action = document.getElementById('id_edit').value === '0' ? 'ajax_simpan_mesin.php' : 'ajax_update_mesin.php';

    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
    submitBtn.prop('disabled', true);

    $.ajax({
        url: action,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert(response.message);
                $('#modalTambahMesin').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.log('Response:', xhr.responseText);
            alert('Gagal menyimpan data mesin! Error: ' + error);
            submitBtn.html(originalText);
            submitBtn.prop('disabled', false);
        }
    });
});

function hapusMesin(id, name) {
    if (!confirm(`Apakah Anda yakin ingin menghapus mesin "${name}"?`)) return;

    $.ajax({
        url: 'ajax_hapus_mesin.php',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            alert(response.message);
            if (response.status === 'success') location.reload();
        },
        error: function() { alert('Gagal menghapus data mesin!'); }
    });
}

/* ============ FORM KONDISI (dipindahkan dari kondisi_mesin.php) ============ */
function resetFormTambahKondisi() {
    document.getElementById('formKondisi').reset();
    document.getElementById('id_edit_kondisi').value = '0';
    document.getElementById('start_date_kondisi').value = new Date().toISOString().split('T')[0];
    if (document.getElementById('end_date_kondisi')) {
        document.getElementById('end_date_kondisi').value = '';
    }
    document.getElementById('modalTambahKondisiTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Tambah Kondisi Mesin';
    document.getElementById('id_mesin_kondisi').disabled = false;
}

$('#formKondisi').on('submit', function (e) {
    e.preventDefault();

    const id_edit    = document.getElementById('id_edit_kondisi').value;
    const id_mesin   = document.getElementById('id_mesin_kondisi').value;
    const start_date = document.getElementById('start_date_kondisi').value;

    if (!id_mesin || !start_date) {
        alert('Pilih mesin dan tanggal mulai wajib diisi!');
        return;
    }

    const formData = new FormData(this);
    const action = id_edit === '0' ? 'ajax_simpan_kondisi_mesin.php' : 'ajax_update_kondisi_mesin.php';

    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
    submitBtn.prop('disabled', true);

    $.ajax({
        url: action,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.status === 'success') {
                alert(response.message);
                $('#modalTambahKondisi').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('Gagal menyimpan data kondisi! Error: ' + error);
            submitBtn.html(originalText);
            submitBtn.prop('disabled', false);
        }
    });
});

/* ============ FORM IMPORT ============ */
$('#formImport').on('submit', function (e) {
    e.preventDefault();
    alert('Fungsi import CSV dinonaktifkan sementara.');
});
</script>
</body>
</html>