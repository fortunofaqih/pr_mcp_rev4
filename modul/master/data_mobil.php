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
    <title>Database Armada - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- Select2 (Bootstrap 5 theme) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css">

    <!-- Datepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">

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
            .container-fluid { padding-left: 0.5rem; padding-right: 0.5rem; }
        }
        @media (max-width: 576px) {
            .navbar-brand { font-size: 0.75rem; }
            .btn { font-size: 0.7rem; padding: 0.2rem 0.4rem; }
            .table td, .table th { padding: 0.3rem 0.2rem; }
            .badge { font-size: 0.65rem; }
        }

        .badge-baik { background-color: #28a745; color: white; }
        .badge-diservice { background-color: #ffc107; color: black; }
        .badge-rusak-ringan { background-color: #fd7e14; color: white; }
        .badge-rusak-berat { background-color: #dc3545; color: white; }

        .btn-group-vertical-custom {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .btn-group-vertical-custom .btn {
            border-radius: 4px !important;
            padding: 4px 8px;
            font-size: 0.7rem;
            white-space: nowrap;
        }

        @media (min-width: 769px) {
            .btn-group-vertical-custom {
                flex-direction: row;
                gap: 5px;
            }
            .btn-group-vertical-custom .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }

        /* Select2 di dalam modal */
        .select2-container--bootstrap-5 { width: 100% !important; }
        .modal .select2-container { z-index: 1056; }

        /* Datepicker z-index agar tidak tertutup modal */
        .datepicker { z-index: 1151 !important; }
        .datepicker-dropdown { z-index: 1151 !important; }

        /* ===== SEARCH PER KOLOM (tfoot) ===== */
        #tabelMobil tfoot th {
            background-color: #eef2f7;
            padding: 4px 4px;
            vertical-align: middle;
        }
        #tabelMobil tfoot input.col-search,
        #tabelMobil tfoot select.col-search {
            width: 100%;
            font-size: 0.75rem;
            padding: 2px 4px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            background: #fff;
        }
        #tabelMobil tfoot input.col-search:focus,
        #tabelMobil tfoot select.col-search:focus {
            outline: none;
            border-color: #4a90d9;
            box-shadow: 0 0 0 2px rgba(74,144,217,0.15);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-4">
    <div class="container-fluid px-3 px-sm-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-truck me-2"></i> DATABASE ARMADA KENDARAAN</span>
        <div class="d-flex flex-wrap gap-1">
            <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> HOME</a>
            <a href="mobil.php" class="btn btn-sm btn-light fw-bold"><i class="fas fa-plus-circle"></i> ADD DATA MOBIL</a>
            <a href="laporan_kondisi_kendaraan.php" class="btn btn-sm btn-info fw-bold text-white"><i class="fas fa-chart-bar"></i> LAPORAN</a>
            <button type="button" class="btn btn-sm btn-success" id="btnBukaModalMulai">
                <i class="fas fa-plus-circle"></i> MOBIL MASUK SERVIS
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelMobil" class="table table-hover table-striped align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th>Plat Nomor</th>
                            <th>Driver</th>
                            <th>Jenis</th>
                            <th>Kategori</th>
                            <th class="text-center">Tahun</th>
                            <th class="text-center">Kondisi</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <!-- Search per kolom -->
                    <tfoot>
                        <tr>
                            <th><input type="text" class="col-search" placeholder="Cari plat..." data-col="0"></th>
                            <th><input type="text" class="col-search" placeholder="Cari driver..." data-col="1"></th>
                            <th><input type="text" class="col-search" placeholder="Cari jenis..." data-col="2"></th>
                            <th><input type="text" class="col-search" placeholder="Cari kategori..." data-col="3"></th>
                            <th><input type="text" class="col-search" placeholder="Tahun" data-col="4"></th>
                            <th>
                                <select class="col-search" data-col="5">
                                    <option value="">Semua</option>
                                    <option value="BAIK">BAIK</option>
                                    <option value="DISERVICE">DISERVICE</option>
                                    
                                </select>
                            </th>
                            <th></th><!-- Aksi tidak perlu filter -->
                        </tr>
                    </tfoot>
                    <tbody class="small">
                        <?php
                        $query = mysqli_query($koneksi, "SELECT * FROM master_mobil ORDER BY plat_nomor ASC");

                        $stmt_kondisi = mysqli_prepare($koneksi,
                            "SELECT kondisi FROM kondisi_kendaraan
                             WHERE id_mobil = ? AND end_date IS NULL
                             ORDER BY start_date DESC LIMIT 1"
                        );

                        while ($d = mysqli_fetch_array($query)) {
                            mysqli_stmt_bind_param($stmt_kondisi, "i", $d['id_mobil']);
                            mysqli_stmt_execute($stmt_kondisi);
                            $row_kondisi = mysqli_stmt_get_result($stmt_kondisi)->fetch_assoc();
                            $kondisi = $row_kondisi['kondisi'] ?? 'BAIK';

                            $badge_class = [
                                'BAIK'         => 'badge-baik',
                                'DISERVICE'    => 'badge-diservice',
                                'RUSAK RINGAN' => 'badge-rusak-ringan',
                                'RUSAK BERAT'  => 'badge-rusak-berat',
                            ][$kondisi] ?? 'badge-baik';
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($d['plat_nomor']) ?></td>
                            <td class="text-uppercase"><?= htmlspecialchars($d['driver_tetap']) ?></td>
                            <td><?= htmlspecialchars($d['jenis_kendaraan']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($d['kategori_kendaraan']) ?></span></td>
                            <td class="text-center"><?= htmlspecialchars($d['tahun_kendaraan']) ?></td>
                            <td class="text-center"><span class="badge <?= $badge_class ?>"><?= $kondisi ?></span></td>
                            <td class="text-center">
                                <div class="btn-group-vertical-custom">
                                    <a href="edit_mobil.php?id=<?= (int)$d['id_mobil'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="lihatKondisi(<?= (int)$d['id_mobil'] ?>)" title="Lihat Riwayat Servis">
                                        <i class="fas fa-clipboard-list"></i> Riwayat
                                    </button>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Laporan">
                                            <i class="fas fa-file-alt"></i> Cetak
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="laporan_kondisi_mobil_print.php?id_mobil=<?= (int)$d['id_mobil'] ?>&format=pdf" target="_blank">
                                                    <i class="fas fa-file-pdf text-danger"></i> PDF
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                    <a href="proses_status_mobil.php?id=<?= (int)$d['id_mobil'] ?>&status=<?= htmlspecialchars($d['status_aktif']) ?>"
                                       class="btn btn-sm <?= ($d['status_aktif'] == 'AKTIF') ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                       onclick="return confirm('Ubah status kendaraan ini?')" title="Ubah Status Aktif">
                                        <i class="fas fa-power-off"></i> Status
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

<!-- ================= MODAL MOBIL MASUK SERVIS ================= -->
<div class="modal fade" id="modalMulai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="formMulai" autocomplete="off">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Mobil Masuk Servis</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cari Plat Nomor <span class="text-danger">*</span></label>
                        <select id="cari_plat" class="form-select"></select>
                        <input type="hidden" id="id_mobil">
                        <input type="hidden" id="plat_nomor">
                    </div>

                    <div id="infoMobil" class="alert alert-info py-2 mb-3" style="display:none;">
                        <div class="small"><b>Driver:</b> <span id="info_driver">-</span></div>
                        <div class="small"><b>Jenis:</b> <span id="info_jenis">-</span></div>
                        <div class="small"><b>Merk/Tipe:</b> <span id="info_merk">-</span></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tanggal Masuk <span class="text-danger">*</span></label>
                            <input type="text" id="start_date_mulai" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Kondisi</label>
                            <input type="text" id="kondisi_mulai" class="form-control" value="DISERVICE" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Bengkel <span class="text-danger">*</span></label>
                            <select id="bengkel_mulai" class="form-select" required>
                                <option value="">-- Pilih Bengkel --</option>
                                <option value="MISTARI">MISTARI</option>
                                <option value="RUDI H.">RUDI H.</option>
                                <option value="EDI M.">EDI M.</option>
                                <option value="M. ULUM">M. ULUM</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Keterangan / Kerusakan</label>
                            <textarea id="keterangan_mulai" class="form-control" rows="3" placeholder="Contoh: ganti oli, servis rem..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL SELESAI SERVIS ================= -->
<div class="modal fade" id="modalSelesai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formSelesai" autocomplete="off">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Selesaikan Servis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="id_kondisi_selesai">

                    <table class="table table-sm table-borderless mb-3">
                        <tr><th style="width:35%">Plat Nomor</th><td>: <span id="selesai_plat" class="fw-bold"></span></td></tr>
                        <tr><th>Kondisi</th><td>: <span id="selesai_kondisi"></span></td></tr>
                        <tr><th>Mulai Servis</th><td>: <span id="selesai_mulai"></span></td></tr>
                        <tr><th>Bengkel</th><td>: <span id="selesai_bengkel">-</span></td></tr>
                    </table>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal Selesai <span class="text-danger">*</span></label>
                        <input type="text" id="end_date_selesai" class="form-control datepicker" placeholder="dd-mm-yyyy" autocomplete="off">
                    </div>

                    <div class="mb-3" id="bengkel_selesai_wrapper" style="display:none;">
                        <label class="form-label fw-bold">Bengkel <span class="text-danger">*</span></label>
                        <select id="bengkel_selesai" class="form-select">
                            <option value="">-- Pilih Bengkel --</option>
                            <option value="MISTARI">MISTARI</option>
                            <option value="RUDI H.">RUDI H.</option>
                            <option value="EDI M.">EDI M.</option>
                            <option value="M. ULUM">M. ULUM</option>
                        </select>
                        <div id="bengkel_selesai_error" class="text-danger small mt-1" style="display:none;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-check"></i> Selesaikan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================= MODAL RIWAYAT SERVIS ================= -->
<div class="modal fade" id="modalRiwayat" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i> Riwayat Servis Kendaraan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="riwayatBody">
                <p class="text-center text-muted">Memuat data...</p>
            </div>
        </div>
    </div>
</div>

<!-- ================= SCRIPTS (jQuery HANYA SEKALI) ================= -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.id.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
/* ============================================================
   HELPER: inisialisasi komponen di dalam modal
   ============================================================ */
function initDatepicker(selector) {
    $(selector).datepicker({
        format: 'dd-mm-yyyy',
        autoclose: true,
        todayHighlight: true,
        language: 'id',
        orientation: 'bottom',
        container: 'body'
    });
}

function initSelect2Plat() {
    if ($('#cari_plat').hasClass('select2-hidden-accessible')) return;

    $('#cari_plat').select2({
        theme: 'bootstrap-5',
        placeholder: 'Ketik plat nomor...',
        allowClear: true,
        width: '100%',
        dropdownParent: $('#modalMulai'),
        minimumInputLength: 1,
        ajax: {
            url: 'ajax_search_plat.php',
            dataType: 'json',
            delay: 250,
            data: params => ({ search: params.term }),
            processResults: data => ({ results: data }),
            cache: true
        }
    });

    $('#cari_plat').on('select2:select', function (e) {
        if (e.params.data.id) ambilDataMobil(e.params.data.id);
    });
    $('#cari_plat').on('select2:clear', function () {
        $('#infoMobil').hide();
        $('#id_mobil, #plat_nomor').val('');
    });
}

/* ============================================================
   RESET FORM MULAI
   ============================================================ */
function resetFormMulai() {
    var form = document.getElementById('formMulai');
    if (!form) return;
    form.reset();

    $('#infoMobil').hide();
    $('#id_mobil, #plat_nomor').val('');

    if ($('#cari_plat').hasClass('select2-hidden-accessible')) {
        $('#cari_plat').val(null).trigger('change');
    }
    if ($('#start_date_mulai').data('datepicker')) {
        $('#start_date_mulai').datepicker('update', '');
    }
    $('#kondisi_mulai').val('DISERVICE').prop('disabled', true);
    $('#bengkel_mulai').val('');
}

/* ============================================================
   AMBIL DATA MOBIL
   ============================================================ */
function ambilDataMobil(plat) {
    $.ajax({
        url: 'ajax_cari_mobil.php',
        type: 'POST',
        data: { plat_nomor: plat },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                $('#id_mobil').val(response.data.id_mobil);
                $('#plat_nomor').val(response.data.plat_nomor);
                $('#info_driver').text(response.data.driver_tetap || '-');
                $('#info_jenis').text(response.data.jenis_kendaraan || '-');
                $('#info_merk').text(response.data.merk_tipe || '-');
                $('#infoMobil').show();
            } else {
                alert('Mobil tidak ditemukan!');
            }
        },
        error: function () { alert('Error saat mencari data mobil!'); }
    });
}

/* ============================================================
   BUKA MODAL SELESAI
   ============================================================ */
function bukaModalSelesai(id_kondisi, plat, kondisi, start_date, bengkel) {
    $('#id_kondisi_selesai').val(id_kondisi);
    $('#selesai_plat').text(plat);
    $('#selesai_kondisi').text(kondisi);
    $('#selesai_bengkel').text(bengkel || '-');

    if (!bengkel || bengkel === '' || bengkel === '-') {
        $('#bengkel_selesai_wrapper').show();
        $('#bengkel_selesai').val('');
        $('#bengkel_selesai_error').hide();
    } else {
        $('#bengkel_selesai_wrapper').hide();
        $('#bengkel_selesai').val('');
    }

    if (start_date) {
        var dateObj = new Date(start_date);
        if (!isNaN(dateObj.getTime())) {
            var day   = ('0' + dateObj.getDate()).slice(-2);
            var month = getMonthName(dateObj.getMonth());
            var year  = dateObj.getFullYear();
            $('#selesai_mulai').text(day + '-' + month + '-' + year);
        } else {
            $('#selesai_mulai').text(start_date);
        }
    } else {
        $('#selesai_mulai').text('-');
    }

    var today    = new Date();
    var d        = ('0' + today.getDate()).slice(-2);
    var m        = getMonthName(today.getMonth());
    var y        = today.getFullYear();
    var todayStr = d + '-' + m + '-' + y;

    if ($('#end_date_selesai').data('datepicker')) {
        $('#end_date_selesai').datepicker('update', todayStr);
    } else {
        $('#end_date_selesai').val(todayStr);
    }

    var modalRiwayatEl = document.getElementById('modalRiwayat');
    if (modalRiwayatEl) {
        var inst = bootstrap.Modal.getInstance(modalRiwayatEl);
        if (inst) inst.hide();
    }

    var modalSelesaiEl = document.getElementById('modalSelesai');
    bootstrap.Modal.getOrCreateInstance(modalSelesaiEl).show();
}

function getMonthName(monthIndex) {
    var months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    return months[monthIndex];
}

/* ============================================================
   RIWAYAT KONDISI
   ============================================================ */
function lihatKondisi(id_mobil) {
    $('#riwayatBody').html('<p class="text-center text-muted">Memuat data...</p>');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRiwayat')).show();

    $.ajax({
        url: 'ajax_riwayat_kondisi.php',
        type: 'POST',
        data: { id_mobil: id_mobil },
        dataType: 'html',
        success: function (response) {
            $('#riwayatBody').html(response);
        },
        error: function () {
            $('#riwayatBody').html('<p class="text-center text-danger">Error memuat data!</p>');
        }
    });
}

function hapusKondisi(id_kondisi) {
    if (!confirm('Hapus data riwayat ini? Tindakan ini hanya untuk koreksi data yang salah input.')) return;
    $.post('ajax_hapus_kondisi.php', { id_kondisi: id_kondisi }, function (res) {
        alert(res.message);
        if (res.status === 'success') location.reload();
    }, 'json').fail(function () { alert('Gagal menghubungi server.'); });
}

/* ============================================================
   DOCUMENT READY
   ============================================================ */
$(document).ready(function () {

    /* --- DataTables + Search per kolom --- */
    if ($.fn.DataTable && !$.fn.DataTable.isDataTable('#tabelMobil')) {
        var table = $('#tabelMobil').DataTable({
            pageLength: 10,
            language: { url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
            columnDefs: [
                { orderable: false, targets: 6 } // Aksi tidak bisa di-sort
            ],
            responsive: true,
            // tfoot dipakai untuk filter, bukan sorting
            initComplete: function () {
                var api = this.api();

                // Untuk tiap input/select di tfoot
                $('#tabelMobil tfoot .col-search').each(function () {
                    var colIdx = $(this).data('col');

                    // Text input → filter contains
                    if ($(this).is('input[type="text"]')) {
                        $(this).on('keyup change clear', function () {
                            if (api.column(colIdx).search() !== this.value) {
                                api.column(colIdx).search(this.value).draw();
                            }
                        });
                    }

                    // Select (dropdown) → filter exact match
                    if ($(this).is('select')) {
                        $(this).on('change', function () {
                            var val = $.fn.dataTable.util.escapeRegex($(this).val());
                            // Gunakan regex exact match agar "RUSAK RINGAN" tidak ikut "RUSAK BERAT"
                            api.column(colIdx)
                               .search(val ? '^' + val + '$' : '', true, false)
                               .draw();
                        });
                    }
                });
            }
        });
    } else {
        // Jika tabel sudah di-init (misal karena re-render), tetap pasang listener tfoot
        // supaya search per kolom tetap jalan
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#tabelMobil')) {
            var api = $('#tabelMobil').DataTable();
            $('#tabelMobil tfoot .col-search').each(function () {
                var colIdx = $(this).data('col');
                if ($(this).is('input[type="text"]')) {
                    $(this).on('keyup change clear', function () {
                        if (api.column(colIdx).search() !== this.value) {
                            api.column(colIdx).search(this.value).draw();
                        }
                    });
                }
                if ($(this).is('select')) {
                    $(this).on('change', function () {
                        var val = $.fn.dataTable.util.escapeRegex($(this).val());
                        api.column(colIdx)
                           .search(val ? '^' + val + '$' : '', true, false)
                           .draw();
                    });
                }
            });
        }
    }

    /* --- Datepicker global --- */
    initDatepicker('.datepicker');

    /* --- Select2 plat --- */
    initSelect2Plat();

    /* --- Tombol BUKA modal mulai --- */
    $('#btnBukaModalMulai').on('click', function () {
        resetFormMulai();
        if (!$('#start_date_mulai').data('datepicker')) {
            initDatepicker('#start_date_mulai');
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalMulai')).show();
    });

    /* --- Re-init datepicker saat modal selesai dibuka --- */
    $('#modalSelesai').on('shown.bs.modal', function () {
        if (!$('#end_date_selesai').data('datepicker')) {
            initDatepicker('#end_date_selesai');
        }
    });

    /* ============================================================
       SUBMIT FORM MULAI
       ============================================================ */
    $('#formMulai').on('submit', function (e) {
        e.preventDefault();

        var idMobil  = $('#id_mobil').val();
        var tglMulai = $('#start_date_mulai').val();
        var bengkel  = $('#bengkel_mulai').val();

        if (!idMobil) { alert('Pilih plat nomor mobil terlebih dahulu.'); return; }
        if (!tglMulai) { alert('Isi tanggal masuk servis.'); return; }
        if (!bengkel) { alert('Pilih bengkel terlebih dahulu.'); return; }

        var $btn = $(this).find('button[type="submit"]').prop('disabled', true);

        $.post('ajax_mulai_service.php', {
            id_mobil   : idMobil,
            plat_nomor : $('#plat_nomor').val(),
            kondisi    : 'DISERVICE',
            start_date : tglMulai,
            bengkel    : bengkel,
            keterangan : $('#keterangan_mulai').val()
        }, function (res) {
            alert(res.message);
            if (res.status === 'success') location.reload();
        }, 'json').fail(function () {
            alert('Gagal menghubungi server.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    /* ============================================================
       SUBMIT FORM SELESAI
       ============================================================ */
    $('#formSelesai').on('submit', function (e) {
        e.preventDefault();

        var endDate = $('#end_date_selesai').val();
        if (!endDate) { alert('Isi tanggal selesai servis.'); return; }

        var postData = {
            id_kondisi: $('#id_kondisi_selesai').val(),
            end_date  : endDate
        };

        if ($('#bengkel_selesai_wrapper').is(':visible')) {
            var bengkel = $('#bengkel_selesai').val();
            if (!bengkel) {
                $('#bengkel_selesai_error').show().text('Silakan pilih bengkel terlebih dahulu.');
                return;
            }
            $('#bengkel_selesai_error').hide();
            postData.bengkel = bengkel;
        }

        var $btn = $(this).find('button[type="submit"]').prop('disabled', true);

        $.post('ajax_selesai_service.php', postData, function (res) {
            if (res.status === 'error' && res.requires_bengkel) {
                $('#bengkel_selesai_wrapper').show();
                $('#bengkel_selesai_error').show().text('Silakan pilih bengkel terlebih dahulu.');
                return;
            }
            alert(res.message);
            if (res.status === 'success') location.reload();
        }, 'json').fail(function () {
            alert('Gagal menghubungi server.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

});

/* ============================================================
   IDLE TIMER
   ============================================================ */
let idleTime = 0;
const maxIdleMinutes = 15;
let lastServerUpdate = Date.now();
let sessionValid = true;

const KEEP_ALIVE_URL = '../../auth/keep_alive.php';
const LOGOUT_URL     = '../../auth/logout.php?pesan=timeout';

function resetTimer() {
    idleTime = 0;
    let now = Date.now();
    if (now - lastServerUpdate > 300000) {
        fetch(KEEP_ALIVE_URL)
            .then(r => r.json())
            .then(data => { if (data.status !== 'success') { sessionValid = false; forceLogout(); } })
            .catch(() => console.error("Koneksi ke server terputus"));
        lastServerUpdate = now;
    }
}

function forceLogout() {
    alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
    window.location.href = LOGOUT_URL;
}

window.addEventListener('load', resetTimer);
document.addEventListener('mousemove', resetTimer);
document.addEventListener('keypress', resetTimer);
document.addEventListener('mousedown', resetTimer);
document.addEventListener('click', resetTimer);
document.addEventListener('scroll', resetTimer, true);

setInterval(function () {
    idleTime++;
    fetch(KEEP_ALIVE_URL)
        .then(r => r.json())
        .then(data => { if (data.status !== 'success') { sessionValid = false; forceLogout(); } })
        .catch(() => {});
    if (idleTime >= maxIdleMinutes && sessionValid) forceLogout();
}, 60000);
</script>
</body>
</html>