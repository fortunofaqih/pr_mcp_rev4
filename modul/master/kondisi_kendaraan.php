<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Proses Simpan Data
if (isset($_POST['simpan'])) {
    $id_mobil = $_POST['id_mobil'];
    $plat_nomor = $_POST['plat_nomor'];
    $kondisi = $_POST['kondisi'];
    $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $start_date = !empty($_POST['start_date']) ? date('Y-m-d', strtotime(str_replace('-', ' ', $_POST['start_date']))) : NULL;
    $end_date = !empty($_POST['end_date']) ? date('Y-m-d', strtotime(str_replace('-', ' ', $_POST['end_date']))) : NULL;
    $created_by = $_SESSION['username'] ?? 'system';

    if (isset($_POST['id_kondisi']) && !empty($_POST['id_kondisi'])) {
        // Update
        $id_kondisi = $_POST['id_kondisi'];
        $query = "UPDATE kondisi_kendaraan SET 
                  id_mobil = '$id_mobil',
                  plat_nomor = '$plat_nomor',
                  kondisi = '$kondisi',
                  keterangan = '$keterangan',
                  start_date = " . ($start_date ? "'$start_date'" : "NULL") . ",
                  end_date = " . ($end_date ? "'$end_date'" : "NULL") . ",
                  updated_by = '$created_by',
                  updated_at = NOW()
                  WHERE id_kondisi = '$id_kondisi'";
        $pesan = "Data kondisi berhasil diupdate!";
    } else {
        // Insert
        $query = "INSERT INTO kondisi_kendaraan (id_mobil, plat_nomor, kondisi, keterangan, start_date, end_date, created_by) 
                  VALUES ('$id_mobil', '$plat_nomor', '$kondisi', '$keterangan', " . 
                  ($start_date ? "'$start_date'" : "NULL") . ", " . 
                  ($end_date ? "'$end_date'" : "NULL") . ", '$created_by')";
        $pesan = "Data kondisi berhasil disimpan!";
    }

    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('$pesan'); window.location.href='kondisi_kendaraan.php';</script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($koneksi) . "');</script>";
    }
}

// Proses Hapus Data
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    $query = "DELETE FROM kondisi_kendaraan WHERE id_kondisi = '$id'";
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Data kondisi berhasil dihapus!'); window.location.href='kondisi_kendaraan.php';</script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($koneksi) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kondisi Kendaraan - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
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
        
        .badge-baik { background-color: #28a745; color: white; }
        .badge-diservice { background-color: #ffc107; color: black; }
        .badge-rusak-ringan { background-color: #fd7e14; color: white; }
        .badge-rusak-berat { background-color: #dc3545; color: white; }
        
        .mobil-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid var(--mcp-blue);
        }
        
        .mobil-info-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .mobil-info-item:last-child {
            border-bottom: none;
        }
        
        .mobil-info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .mobil-info-value {
            color: #212529;
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 38px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-3">
    <div class="container-fluid px-3 px-sm-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-clipboard-list me-2"></i> KONDISI KENDARAAN</span>
        <div class="d-flex flex-wrap gap-1">
            <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> HOME</a>
            <a href="data_mobil.php" class="btn btn-sm btn-light fw-bold"><i class="fas fa-truck"></i> ARMADA</a>
            <a href="laporan_kondisi_kendaraan.php" class="btn btn-sm btn-info fw-bold text-white"><i class="fas fa-chart-bar"></i> LAPORAN</a>
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalKondisi" onclick="resetForm()">
                <i class="fas fa-plus-circle"></i> TAMBAH
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelKondisi" class="table table-hover table-striped align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th>Plat Nomor</th>
                            <th>Kondisi</th>
                            <th>Keterangan</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Durasi (Hari)</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = mysqli_query($koneksi, "SELECT k.*, m.driver_tetap, m.jenis_kendaraan, m.kategori_kendaraan, m.merk_tipe, m.status_aktif
                                                         FROM kondisi_kendaraan k 
                                                         JOIN master_mobil m ON k.id_mobil = m.id_mobil 
                                                         ORDER BY k.created_at DESC");
                        while($d = mysqli_fetch_array($query)){
                            $status_aktif = ($d['status_aktif'] == 'AKTIF') ? 'AKTIF' : 'NONAKTIF';
                            $status_badge = ($d['status_aktif'] == 'AKTIF') ? 'bg-success' : 'bg-danger';
                            
                            $kondisi_badge = '';
                            if ($d['kondisi'] == 'BAIK') {
                                $kondisi_badge = 'bg-success';
                            } else if ($d['kondisi'] == 'DISERVICE') {
                                $kondisi_badge = 'bg-warning text-dark';
                            } else if ($d['kondisi'] == 'RUSAK RINGAN') {
                                $kondisi_badge = 'bg-warning';
                            } else if ($d['kondisi'] == 'RUSAK BERAT') {
                                $kondisi_badge = 'bg-danger';
                            }
                            
                            // Hitung durasi
                            $durasi = '-';
                            if ($d['start_date'] && $d['end_date']) {
                                $start = new DateTime($d['start_date']);
                                $end = new DateTime($d['end_date']);
                                $diff = $start->diff($end);
                                $durasi = $diff->days + 1; // +1 karena termasuk hari mulai
                            }
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= $d['plat_nomor'] ?></td>
                            <td><span class="badge <?= $kondisi_badge ?>"><?= $d['kondisi'] ?></span></td>
                            <td><?= nl2br(substr($d['keterangan'], 0, 50) . (strlen($d['keterangan']) > 50 ? '...' : '')) ?></td>
                            <td><?= $d['start_date'] ? date('d-M-Y', strtotime($d['start_date'])) : '-' ?></td>
                            <td><?= $d['end_date'] ? date('d-M-Y', strtotime($d['end_date'])) : '-' ?></td>
                            <td class="text-center"><strong><?= $durasi ?></strong></td>
                            <td><span class="badge <?= $status_badge ?>"><?= $status_aktif ?></span></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="editKondisi(<?= $d['id_kondisi'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?hapus=<?= $d['id_kondisi'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus data kondisi ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
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

<!-- Modal Tambah/Edit Kondisi -->
<div class="modal fade" id="modalKondisi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i> <span id="modalTitle">Tambah Kondisi Kendaraan</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formKondisi">
                <div class="modal-body">
                    <input type="hidden" name="id_kondisi" id="id_kondisi">
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">Cari Plat Nomor <span class="text-danger">*</span></label>
                            <select class="form-select" id="cari_plat" name="cari_plat" style="width: 100%;">
                                <option value="">Cari plat nomor...</option>
                            </select>
                            <input type="hidden" name="id_mobil" id="id_mobil">
                            <input type="hidden" name="plat_nomor" id="plat_nomor">
                        </div>
                    </div>
                    
                    <div id="infoMobil" style="display:none;" class="mobil-info mb-3">
                        <div class="mobil-info-item">
                            <span class="mobil-info-label">Driver Tetap</span>
                            <span class="mobil-info-value" id="info_driver">-</span>
                        </div>
                        <div class="mobil-info-item">
                            <span class="mobil-info-label">Jenis Kendaraan</span>
                            <span class="mobil-info-value" id="info_jenis">-</span>
                        </div>
                        <div class="mobil-info-item">
                            <span class="mobil-info-label">Kategori</span>
                            <span class="mobil-info-value" id="info_kategori">-</span>
                        </div>
                        <div class="mobil-info-item">
                            <span class="mobil-info-label">Merk / Tipe</span>
                            <span class="mobil-info-value" id="info_merk">-</span>
                        </div>
                        <div class="mobil-info-item">
                            <span class="mobil-info-label">Status Aktif</span>
                            <span class="mobil-info-value" id="info_status">-</span>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Kondisi <span class="text-danger">*</span></label>
                            <select class="form-select" name="kondisi" id="kondisi" required>
                                <option value="">Pilih Kondisi</option>
                                <option value="BAIK">BAIK</option>
                                <option value="DISERVICE">DISERVICE</option>
                                <option value="RUSAK RINGAN">RUSAK RINGAN</option>
                                <option value="RUSAK BERAT">RUSAK BERAT</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Keterangan</label>
                            <textarea class="form-control" name="keterangan" id="keterangan" rows="3" placeholder="Masukkan keterangan kondisi..."></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Start Date (Masuk Service)</label>
                            <div class="input-group date">
                                <input type="text" class="form-control datepicker" name="start_date" id="start_date" placeholder="DD-MMM-YYYY" autocomplete="off">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <small class="text-muted">Isi jika kondisi DISERVICE atau RUSAK</small>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">End Date (Selesai Service)</label>
                            <div class="input-group date">
                                <input type="text" class="form-control datepicker" name="end_date" id="end_date" placeholder="DD-MMM-YYYY" autocomplete="off">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <small class="text-muted">Isi jika service sudah selesai</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.id.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Inisialisasi Select2 untuk pencarian plat
$(document).ready(function() {
    $('#cari_plat').select2({
        theme: 'bootstrap-5',
        placeholder: 'Cari plat nomor...',
        allowClear: true,
        ajax: {
            url: 'ajax_search_plat.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 1,
        dropdownParent: $('#modalKondisi')
    });

    // Event ketika plat dipilih
    $('#cari_plat').on('select2:select', function(e) {
        var data = e.params.data;
        if (data.id) {
            getDataMobil(data.id);
        }
    });

    $('#cari_plat').on('select2:clear', function() {
        document.getElementById('infoMobil').style.display = 'none';
        document.getElementById('id_mobil').value = '';
        document.getElementById('plat_nomor').value = '';
    });
});

// Fungsi untuk mendapatkan data mobil
function getDataMobil(plat) {
    $.ajax({
        url: 'ajax_cari_mobil.php',
        type: 'POST',
        data: { plat_nomor: plat },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                document.getElementById('id_mobil').value = response.data.id_mobil;
                document.getElementById('plat_nomor').value = response.data.plat_nomor;
                document.getElementById('info_driver').textContent = response.data.driver_tetap || '-';
                document.getElementById('info_jenis').textContent = response.data.jenis_kendaraan || '-';
                document.getElementById('info_kategori').textContent = response.data.kategori_kendaraan || '-';
                document.getElementById('info_merk').textContent = response.data.merk_tipe || '-';
                document.getElementById('info_status').innerHTML = response.data.status_aktif == 'AKTIF' ? 
                    '<span class="badge bg-success">AKTIF</span>' : 
                    '<span class="badge bg-danger">NONAKTIF</span>';
                document.getElementById('infoMobil').style.display = 'block';
            } else {
                alert('Mobil tidak ditemukan!');
            }
        },
        error: function() {
            alert('Error saat mencari data mobil!');
        }
    });
}

// Inisialisasi Datepicker
$(document).ready(function() {
    $('.datepicker').datepicker({
        format: 'dd-M-yyyy',
        autoclose: true,
        todayHighlight: true,
        language: 'id',
        orientation: 'bottom'
    });
});

// Inisialisasi DataTable
$(document).ready(function() {
    $('#tabelKondisi').DataTable({
        "pageLength": 10,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
        "columnDefs": [ { "orderable": false, "targets": 7 } ],
        "responsive": true
    });
});

// Reset Form
function resetForm() {
    document.getElementById('formKondisi').reset();
    document.getElementById('id_kondisi').value = '';
    document.getElementById('modalTitle').innerHTML = 'Tambah Kondisi Kendaraan';
    document.getElementById('infoMobil').style.display = 'none';
    document.getElementById('id_mobil').value = '';
    document.getElementById('plat_nomor').value = '';
    $('#cari_plat').val(null).trigger('change');
    $('#start_date').datepicker('update', '');
    $('#end_date').datepicker('update', '');
    document.getElementById('keterangan').value = '';
}

// Edit Kondisi
function editKondisi(id) {
    $.ajax({
        url: 'ajax_get_kondisi.php',
        type: 'POST',
        data: { id_kondisi: id },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                const data = response.data;
                document.getElementById('id_kondisi').value = data.id_kondisi;
                document.getElementById('id_mobil').value = data.id_mobil;
                document.getElementById('plat_nomor').value = data.plat_nomor;
                document.getElementById('kondisi').value = data.kondisi;
                document.getElementById('keterangan').value = data.keterangan || '';
                
                // Set Select2
                var newOption = new Option(data.plat_nomor, data.plat_nomor, true, true);
                $('#cari_plat').append(newOption).trigger('change');
                
                // Set info mobil
                document.getElementById('info_driver').textContent = data.driver_tetap || '-';
                document.getElementById('info_jenis').textContent = data.jenis_kendaraan || '-';
                document.getElementById('info_kategori').textContent = data.kategori_kendaraan || '-';
                document.getElementById('info_merk').textContent = data.merk_tipe || '-';
                document.getElementById('info_status').innerHTML = data.status_aktif == 'AKTIF' ? 
                    '<span class="badge bg-success">AKTIF</span>' : 
                    '<span class="badge bg-danger">NONAKTIF</span>';
                document.getElementById('infoMobil').style.display = 'block';
                
                if (data.start_date) {
                    const d = new Date(data.start_date);
                    $('#start_date').datepicker('update', d);
                }
                if (data.end_date) {
                    const d = new Date(data.end_date);
                    $('#end_date').datepicker('update', d);
                }
                
                document.getElementById('modalTitle').innerHTML = 'Edit Kondisi Kendaraan';
                $('#modalKondisi').modal('show');
            }
        },
        error: function() {
            alert('Error saat mengambil data kondisi!');
        }
    });
}

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

setInterval(function() {
    idleTime++;
    fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                sessionValid = false;
                forceLogout();
            }
        })
        .catch(err => {});
    if (idleTime >= maxIdleMinutes && sessionValid) {
        forceLogout();
    }
}, 60000);
</script>
</body>
</html>