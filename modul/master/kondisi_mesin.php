<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kondisi Mesin - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .badge-baik { background-color: #28a745; color: white; }
        .badge-diservice { background-color: #ffc107; color: #000; }
        .badge-dibongkar { background-color: #dc3545; color: white; }
        .badge-dipoles { background-color: #17a2b8; color: white; }
        .badge-dipopok { background-color: #6f42c1; color: white; }
        .badge-dibubut { background-color: #fd7e14; color: white; }
        .badge-spei { background-color: #20c997; color: white; }
        .badge-stell { background-color: #e83e8c; color: white; }
        .badge-gulung-dinamo { background-color: #6c757d; color: white; }
        .btn-kondisi {
            background: #ffc107;
            color: #000;
            border: 1px solid #ffc107;
        }
        .btn-kondisi:hover {
            background: #e0a800;
            color: #000;
            border-color: #e0a800;
        }
        .modal-header-success {
            background-color: #28a745;
            color: white;
        }
        .modal-header-success .btn-close {
            filter: brightness(0) invert(1);
        }

        /* ===== FILTER PANEL ===== */
        .filter-panel {
            background: #f1f4f9;
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        .filter-panel label {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 2px;
            color: #444;
        }
        .filter-panel .form-select,
        .filter-panel .form-control {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            height: 32px;
        }
        .filter-panel .btn {
            font-size: 0.8rem;
            padding: 0.25rem 0.7rem;
            height: 32px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-3">
    <div class="container-fluid px-3 px-sm-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-heartbeat me-2"></i> STATUS MESIN</span>
        <div class="d-flex flex-wrap gap-1">
            <a href="mesin.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> KEMBALI</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
    <div class="card">
        <div class="card-body">

            <!-- ================= FILTER PANEL ================= -->
            <div class="filter-panel">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label><i class="fas fa-tools me-1"></i> Kondisi</label>
                        <select id="filterKondisi" class="form-select">
                            <option value="">Semua</option>
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
                    <div class="col-6 col-md-2">
                        <label><i class="fas fa-info-circle me-1"></i> Status</label>
                        <select id="filterStatus" class="form-select">
                            <option value="">Semua</option>
                            <option value="AKTIF">AKTIF</option>
                            
                            <option value="SELESAI SERVICE & BAIK">SELESAI SERVICE & BAIK</option>
                            
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                       
                        <button type="button" id="btnResetFilter" class="btn btn-secondary btn-sm w-100 badge rounded-pill">
                            <i class="fas fa-eraser"></i> Reset Filter
                        </button>
                    </div>
                   
                </div>
            </div>
            <!-- ================= END FILTER PANEL ================= -->

            <div class="table-responsive">
                <table id="tabelKondisi" class="table table-hover table-striped align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th>ID Mesin</th>
                            <th>Nama Mesin</th>
                            <th>Kondisi</th>
                            <th>Keterangan Service</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = mysqli_query($koneksi, "
                            SELECT km.*, mm.id_mesin, mm.name 
                            FROM kondisi_mesin km
                            JOIN master_mesin mm ON km.id_mesin = mm.id
                            INNER JOIN (
                                SELECT id_mesin, MAX(id_kondisi_mesin) AS max_id
                                FROM kondisi_mesin
                                GROUP BY id_mesin
                            ) latest ON km.id_kondisi_mesin = latest.max_id
                            ORDER BY km.start_date DESC
                        ");
                        while ($d = mysqli_fetch_array($query)) {
                            $aktif = is_null($d['end_date']);
                            $kondisi_upper = strtoupper($d['kondisi_mesin']);
                            $kondisi_baik = ($kondisi_upper === 'BAIK');

                            if (!$aktif && $kondisi_baik) {
                                $status_text = 'SELESAI SERVICE & BAIK';
                                $status_class = 'bg-success text-white';
                            } elseif ($aktif && $kondisi_baik) {
                                $status_text = 'BAIK';
                                $status_class = 'bg-success text-white';
                            } elseif ($aktif) {
                                $status_text = 'AKTIF';
                                $status_class = 'bg-warning text-dark';
                            } else {
                                $status_text = 'SELESAI';
                                $status_class = 'bg-success text-white';
                            }

                            $perlu_selesai = $aktif && !$kondisi_baik;

                            $badge_map = [
                                'BAIK' => '<span class="badge badge-baik"><i class="fas fa-check-circle me-1"></i>BAIK</span>',
                                'DISERVICE' => '<span class="badge badge-diservice"><i class="fas fa-tools me-1"></i>DISERVICE</span>',
                                'DIBONGKAR' => '<span class="badge badge-dibongkar"><i class="fas fa-wrench me-1"></i>DIBONGKAR</span>',
                                'DIPOLES' => '<span class="badge badge-dipoles"><i class="fas fa-brush me-1"></i>DIPOLES</span>',
                                'DI POPOK' => '<span class="badge badge-dipopok"><i class="fas fa-baby me-1"></i>DI POPOK</span>',
                                'DI BUBUT' => '<span class="badge badge-dibubut"><i class="fas fa-cog me-1"></i>DI BUBUT</span>',
                                'SPEI' => '<span class="badge badge-spei"><i class="fas fa-gear me-1"></i>SPEI</span>',
                                'STELL' => '<span class="badge badge-dibongkar"><i class="fas fa-circle me-1"></i>STELL</span>',
                                'DI COR' => '<span class="badge badge-dibongkar"><i class="fas fa-wrench me-1"></i>DI COR</span>',
                                'SELEP' => '<span class="badge badge-dibongkar"><i class="fas fa-wrench me-1"></i>SELEP</span>',
                                'DI GANTI' => '<span class="badge badge-dibongkar"><i class="fas fa-wrench me-1"></i>DI GANTI</span>',
                                'GULUNG DINAMO' => '<span class="badge badge-gulung-dinamo"><i class="fas fa-bolt me-1"></i>GULUNG DINAMO</span>'
                            ];

                            $badge_kondisi = isset($badge_map[$kondisi_upper])
                                ? $badge_map[$kondisi_upper]
                                : '<span class="badge bg-secondary">' . htmlspecialchars($d['kondisi_mesin']) . '</span>';

                            // Simpan data untuk filter (hidden sebagai data-attribute)
                            $start_date_val = $d['start_date'] ? date('Y-m-d', strtotime($d['start_date'])) : '';
                            $end_date_val   = $d['end_date']   ? date('Y-m-d', strtotime($d['end_date']))   : '';
                        ?>
                        <tr data-kondisi="<?= htmlspecialchars($kondisi_upper) ?>"
                            data-status="<?= htmlspecialchars($status_text) ?>"
                            data-start-date="<?= $start_date_val ?>"
                            data-end-date="<?= $end_date_val ?>">
                            <td class="fw-bold text-primary"><?= htmlspecialchars($d['id_mesin']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($d['name']) ?></td>
                            <td><?= $badge_kondisi ?></td>
                            <td><?= htmlspecialchars($d['keterangan_service_mesin'] ?? '-') ?></td>
                            <td><?= date('d-M-Y', strtotime($d['start_date'])) ?></td>
                            <td><?= $d['end_date'] ? date('d-M-Y', strtotime($d['end_date'])) : '-' ?></td>
                            <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-warning"
                                            onclick="editKondisi(<?= (int)$d['id_kondisi_mesin'] ?>)"
                                            title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($perlu_selesai) { ?>
                                    <button class="btn btn-sm btn-outline-success"
                                            onclick="openSelesaiModal(<?= (int)$d['id_kondisi_mesin'] ?>, '<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')"
                                            title="Selesaikan Service">
                                        <i class="fas fa-check"></i> Selesaikan
                                    </button>
                                    <?php } ?>
                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="hapusKondisi(<?= (int)$d['id_kondisi_mesin'] ?>, '<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')"
                                            title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Edit Kondisi (versi minimal — hanya untuk edit) -->
<div class="modal fade" id="modalEditKondisi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalEditKondisiTitle"><i class="fas fa-edit me-2"></i> Edit Kondisi Mesin</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditKondisi">
                <input type="hidden" id="id_edit_kondisi" name="id_edit_kondisi" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Mesin</label>
                        <input type="text" class="form-control" id="edit_nama_mesin_display" disabled>
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
                        <textarea class="form-control" id="keterangan_service_mesin" name="keterangan_service_mesin" rows="3"></textarea>
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Selesai Service -->
<div class="modal fade" id="modalSelesaiKondisi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-header-success">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Selesaikan Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSelesaiKondisi">
                <input type="hidden" id="id_kondisi_selesai" name="id_kondisi_selesai" value="0">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Selesaikan service untuk mesin: <strong id="nama_mesin_selesai"></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="end_date_selesai" name="end_date" required>
                        <small class="text-muted">Tanggal service selesai dilakukan</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Update Kondisi</label>
                        <select class="form-select" id="kondisi_selesai" name="kondisi_mesin">
                            <option value="BAIK">BAIK</option>
                            <option value="DISERVICE">DISERVICE</option>
                            <option value="DIBONGKAR">DIBONGKAR</option>
                            <option value="DIPOLES">DIPOLES</option>
                            <option value="DI POPOK">DI POPOK</option>
                            <option value="DI BUBUT">DI BUBUT</option>
                            <option value="SPEI">SPEI</option>
                            <option value="STELL">STELL</option>
                            <option value="GULUNG DINAMO">GULUNG DINAMO</option>
                            <option value="DI COR">DI COR</option>
                            <option value="SELEP">SELEP</option>
                            <option value="DI GANTI">DI GANTI</option>
                        </select>
                        <small class="text-muted">Pilih kondisi setelah service selesai</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Selesaikan</button>
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
/* =====================================================================
   CUSTOM FILTER UNTUK DATATABLES
   Filter membaca data-attribute di setiap <tr>:
   - data-kondisi
   - data-status
   - data-start-date (Y-m-d)
   - data-end-date   (Y-m-d, kosong kalau masih aktif)
   ===================================================================== */
$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
    if (settings.nTable.id !== 'tabelKondisi') return true;

    var row = settings.aoData[dataIndex].nTr;
    if (!row) return true;

    var kondisi    = ($('#filterKondisi').val() || '').toUpperCase();
    var status     = ($('#filterStatus').val() || '').toUpperCase();
    var startFrom  = $('#filterStartFrom').val();
    var startTo    = $('#filterStartTo').val();
    var endFrom    = $('#filterEndFrom').val();
    var endTo      = $('#filterEndTo').val();

    var rowKondisi = (row.getAttribute('data-kondisi') || '').toUpperCase();
    var rowStatus  = (row.getAttribute('data-status') || '').toUpperCase();
    var rowStart   = row.getAttribute('data-start-date') || '';
    var rowEnd     = row.getAttribute('data-end-date') || '';

    // Filter Kondisi (exact match)
    if (kondisi && rowKondisi !== kondisi) return false;

    // Filter Status (exact match)
    if (status && rowStatus !== status) return false;

    // Filter Start Date range
    if (startFrom && (!rowStart || rowStart < startFrom)) return false;
    if (startTo   && (!rowStart || rowStart > startTo))   return false;

    // Filter End Date range
    // Jika user mengisi End Date range, hanya baris yang punya end_date yang lolos
    if (endFrom || endTo) {
        if (!rowEnd) return false;                       // masih aktif → tidak lolos
        if (endFrom && rowEnd < endFrom) return false;
        if (endTo   && rowEnd > endTo)   return false;
    }

    return true;
});

$(document).ready(function () {
    if (!$.fn.DataTable.isDataTable('#tabelKondisi')) {
        var table = $('#tabelKondisi').DataTable({
            pageLength: 10,
            language: { url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
            columnDefs: [{ orderable: false, targets: 7 }],
            responsive: true,
            order: [[4, 'desc']],
            autoWidth: false
        });

        /* Helper: update info jumlah baris setelah filter */
        function updateFilterInfo() {
            var info = table.page.info();
            $('#filterInfo').text(
                'Menampilkan ' + info.recordsDisplay + ' dari ' + info.recordsTotal + ' baris'
            );
        }
        updateFilterInfo();

        /* Event listener filter */
        $('#filterKondisi, #filterStatus, #filterStartFrom, #filterStartTo, #filterEndFrom, #filterEndTo')
            .on('change', function () {
                table.draw();
                updateFilterInfo();
            });

        /* Reset filter */
        $('#btnResetFilter').on('click', function () {
            $('#filterKondisi').val('');
            $('#filterStatus').val('');
            $('#filterStartFrom').val('');
            $('#filterStartTo').val('');
            $('#filterEndFrom').val('');
            $('#filterEndTo').val('');
            table.search('').columns().search('').draw();
            updateFilterInfo();
        });

        /* Update info setiap kali tabel di-draw (termasuk karena search box DataTables) */
        table.on('draw', updateFilterInfo);
    }

    $('#end_date_selesai').val(new Date().toISOString().split('T')[0]);
});

/* ============ EDIT KONDISI ============ */
function editKondisi(id) {
    $('#modalEditKondisi').modal('show');
    document.getElementById('modalEditKondisiTitle').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Memuat data...';

    $.ajax({
        url: 'ajax_get_kondisi_mesin.php',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.status === 'success') {
                const data = response.data;
                document.getElementById('id_edit_kondisi').value = data.id_kondisi_mesin;
                document.getElementById('edit_nama_mesin_display').value = data.id_mesin;
                document.getElementById('kondisi_mesin').value = data.kondisi_mesin;
                document.getElementById('keterangan_service_mesin').value = data.keterangan_service_mesin || '';
                document.getElementById('start_date_kondisi').value = data.start_date;
                if (document.getElementById('end_date_kondisi')) {
                    document.getElementById('end_date_kondisi').value = data.end_date || '';
                }
                document.getElementById('modalEditKondisiTitle').innerHTML = '<i class="fas fa-edit me-2"></i> Edit Kondisi Mesin';
            } else {
                alert('Gagal mengambil data kondisi: ' + response.message);
                $('#modalEditKondisi').modal('hide');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('Error saat mengambil data kondisi! Silahkan cek console untuk detail.');
            $('#modalEditKondisi').modal('hide');
        }
    });
}

/* ============ SELESAI SERVICE ============ */
function openSelesaiModal(id, nama_mesin) {
    document.getElementById('id_kondisi_selesai').value = id;
    document.getElementById('nama_mesin_selesai').textContent = nama_mesin;
    document.getElementById('end_date_selesai').value = new Date().toISOString().split('T')[0];
    document.getElementById('kondisi_selesai').value = 'BAIK';
    $('#modalSelesaiKondisi').modal('show');
}

$('#formSelesaiKondisi').on('submit', function (e) {
    e.preventDefault();

    const end_date = document.getElementById('end_date_selesai').value;
    if (!end_date) {
        alert('Tanggal selesai wajib diisi!');
        return;
    }

    const formData = new FormData(this);
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
    submitBtn.prop('disabled', true);

    $.ajax({
        url: 'ajax_selesai_kondisi_mesin.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                alert(response.message);
                $('#modalSelesaiKondisi').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
                submitBtn.html(originalText);
                submitBtn.prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            alert('Gagal menyelesaikan service! Error: ' + error);
            submitBtn.html(originalText);
            submitBtn.prop('disabled', false);
        }
    });
});

/* ============ SUBMIT EDIT KONDISI ============ */
$('#formEditKondisi').on('submit', function (e) {
    e.preventDefault();

    const id_edit    = document.getElementById('id_edit_kondisi').value;
    const start_date = document.getElementById('start_date_kondisi').value;

    if (!id_edit || id_edit === '0' || !start_date) {
        alert('Data tidak valid!');
        return;
    }

    const formData = new FormData(this);
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
    submitBtn.prop('disabled', true);

    $.ajax({
        url: 'ajax_update_kondisi_mesin.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.status === 'success') {
                alert(response.message);
                $('#modalEditKondisi').modal('hide');
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

/* ============ HAPUS ============ */
function hapusKondisi(id, name) {
    if (!confirm(`Apakah Anda yakin ingin menghapus riwayat kondisi mesin "${name}" ini?`)) return;

    $.ajax({
        url: 'ajax_hapus_kondisi_mesin.php',
        type: 'POST',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            alert(response.message);
            if (response.status === 'success') location.reload();
        },
        error: function() { alert('Gagal menghapus data kondisi!'); }
    });
}
</script>
</body>
</html>