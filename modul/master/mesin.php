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
    <title>Manajemen Mesin - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .card { border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        table.dataTable thead th { background-color: #f1f4f9; vertical-align: middle; }

        @media (max-width: 768px) {
            .navbar-brand { font-size: 0.9rem; }
            .btn-sm { font-size: 0.7rem; padding: 0.25rem 0.5rem; }
            .table-responsive { font-size: 0.8rem; }
            .card-body { padding: 0.75rem; }
            .form-label { font-size: 0.85rem; }
            .form-control, .form-select { font-size: 0.85rem; }
            .modal-dialog { margin: 0.5rem; }
            .container-fluid { padding-left: 0.5rem; padding-right: 0.5rem; }
        }
        @media (max-width: 576px) {
            .navbar-brand { font-size: 0.75rem; }
            .btn { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
            .table td, .table th { padding: 0.3rem 0.2rem; }
            .badge { font-size: 0.65rem; }
            .modal-header h5 { font-size: 1rem; }
        }

        .badge-active { background-color: #28a745; color: white; }
        .badge-inactive { background-color: #dc3545; color: white; }

        .mesin-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid var(--mcp-blue);
        }
        .mesin-info-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .mesin-info-item:last-child { border-bottom: none; }
        .mesin-info-label { font-weight: 600; color: #495057; }
        .mesin-info-value { color: #212529; }
        .select2-container--bootstrap-5 .select2-selection { min-height: 38px; }
        
        .import-template-btn {
            background: #6c757d;
            color: white;
        }
        .import-template-btn:hover {
            background: #5a6268;
            color: white;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-3">
    <div class="container-fluid px-3 px-sm-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-industry me-2"></i> MANAJEMEN MESIN</span>
        <div class="d-flex flex-wrap gap-1">
            <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> HOME</a>
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahMesin" onclick="resetFormTambah()">
                <i class="fas fa-plus-circle"></i> TAMBAH MESIN
            </button>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalImportCSV">
                <i class="fas fa-file-import"></i> IMPORT CSV
            </button>
            <a href="template_export.php?action=template" class="btn btn-sm btn-secondary import-template-btn">
                <i class="fas fa-download"></i> TEMPLATE
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
    <div class="alert alert-light border small mb-3">
        <i class="fas fa-circle-info text-primary me-1"></i>
        Kelola data mesin yang digunakan di perusahaan. 
        <span class="badge badge-active">AKTIF</span> = mesin masih digunakan,
        <span class="badge badge-inactive">NONAKTIF</span> = mesin sudah tidak digunakan.
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelMesin" class="table table-hover table-striped align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th>ID Mesin</th>
                            <th>Nama Mesin</th>
                            <th>Spesifikasi</th>
                            <th>Kapasitas</th>
                            <th>Supplier</th>
                            <th>Status</th>
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
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($d['id_mesin']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($d['name']) ?></td>
                            <td><?= htmlspecialchars(substr($d['spec'] ?? '', 0, 50)) . (strlen($d['spec'] ?? '') > 50 ? '...' : '') ?></td>
                            <td><?= htmlspecialchars($d['capacity'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($d['supplier'] ?? '-') ?></td>
                            <td>
                                <?= $active
                                    ? '<span class="badge badge-active"><i class="fas fa-check-circle me-1"></i>AKTIF</span>'
                                    : '<span class="badge badge-inactive"><i class="fas fa-times-circle me-1"></i>NONAKTIF</span>' ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="editMesin(<?= (int)$d['id'] ?>)"
                                            title="Edit Mesin">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                            onclick="hapusMesin(<?= (int)$d['id'] ?>, '<?= htmlspecialchars($d['name'], ENT_QUOTES) ?>')"
                                            title="Hapus Mesin">
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
                            <label class="form-label fw-bold">Supplier</label>
                            <input type="text" class="form-control" id="supplier" name="supplier" placeholder="Nama supplier">
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
                            <label class="form-label fw-bold">Harga Beli</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" id="purchase_price" name="purchase_price" placeholder="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Tanggal Beli</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Acc Reff</label>
                            <input type="text" class="form-control" id="acc_reff" name="acc_reff" placeholder="Referensi akuntansi">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="form-label fw-bold">Status</label>
                            <select class="form-select" id="active" name="active">
                                <option value="1">AKTIF</option>
                                <option value="0">NONAKTIF</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keterangan</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Catatan tambahan"></textarea>
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

<!-- Modal: Import CSV -->
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
                        Format CSV harus sesuai dengan template. <br>
                        <small>Kolom: id_mesin, name, spec, manufactured_date, manufactured_by, supplier, purchase_price, purchase_date, acc_reff, remarks, active, capacity</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Pilih File CSV <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file_csv" name="file_csv" accept=".csv" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="skip_first_row" name="skip_first_row" value="1" checked>
                            <label class="form-check-label" for="skip_first_row">
                                Lewati baris pertama (header)
                            </label>
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function () {
    $('#tabelMesin').DataTable({
        pageLength: 10,
        language: { url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
        columnDefs: [{ orderable: false, targets: 6 }],
        responsive: true
    });
});

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
                alert('Gagal mengambil data mesin!');
            }
        },
        error: function() {
            alert('Error saat mengambil data mesin!');
        }
    });
}

$('#formMesin').on('submit', function (e) {
    e.preventDefault();
    
    // Validasi client-side
    const idMesin = document.getElementById('id_mesin').value.trim();
    const name = document.getElementById('name').value.trim();
    
    if (!idMesin || !name) {
        alert('ID Mesin dan Nama Mesin wajib diisi!');
        return;
    }
    
    const formData = new FormData(this);
    const action = document.getElementById('id_edit').value === '0' ? 'ajax_simpan_mesin.php' : 'ajax_update_mesin.php';
    
    // Tampilkan loading
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
            if (response.status === 'success') {
                location.reload();
            }
        },
        error: function() {
            alert('Gagal menghapus data mesin!');
        }
    });
}

$('#formImport').on('submit', function (e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    $.ajax({
        url: 'ajax_import_csv.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            alert(response.message);
            if (response.status === 'success') {
                $('#modalImportCSV').modal('hide');
                location.reload();
            }
        },
        error: function() {
            alert('Gagal mengimport data!');
        }
    });
});

// Idle Timer
let idleTime = 0;
const maxIdleMinutes = 15;
let lastServerUpdate = Date.now();
let sessionValid = true;

function resetTimer() {
    idleTime = 0;
    let now = Date.now();
    if (now - lastServerUpdate > 300000) {
        fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
            .then(r => r.json())
            .then(data => { if (data.status !== 'success') { sessionValid = false; forceLogout(); } })
            .catch(() => console.error("Koneksi ke server terputus"));
        lastServerUpdate = now;
    }
}
function forceLogout() {
    alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
    window.location.href = "http://192.168.31.200/pr_mcp/auth/logout.php?pesan=timeout";
}
window.onload = resetTimer;
document.onmousemove = resetTimer;
document.onkeypress = resetTimer;
document.onmousedown = resetTimer;
document.onclick = resetTimer;
document.onscroll = resetTimer;
setInterval(function () {
    idleTime++;
    fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
        .then(r => r.json())
        .then(data => { if (data.status !== 'success') { sessionValid = false; forceLogout(); } })
        .catch(() => {});
    if (idleTime >= maxIdleMinutes && sessionValid) forceLogout();
}, 60000);
</script>
</body>
</html>