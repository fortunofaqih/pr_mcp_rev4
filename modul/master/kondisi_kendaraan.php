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
        .mobil-info-item:last-child { border-bottom: none; }
        .mobil-info-label { font-weight: 600; color: #495057; }
        .mobil-info-value { color: #212529; }
        .select2-container--bootstrap-5 .select2-selection { min-height: 38px; }
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
            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalMulai" onclick="resetFormMulai()">
                <i class="fas fa-plus-circle"></i> MOBIL MASUK SERVIS
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-sm-4">
    <div class="alert alert-light border small mb-3">
        <i class="fas fa-circle-info text-primary me-1"></i>
        Setiap baris di bawah ini adalah <strong>satu periode servis</strong> (dari mobil masuk sampai selesai).
        Status <span class="badge bg-warning text-dark">AKTIF</span> = masih di bengkel,
        <span class="badge bg-success">SELESAI</span> = sudah kembali BAIK.
        Untuk menutup servis, cukup klik <strong>Selesaikan</strong> — tidak perlu input kondisi ulang.
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelKondisi" class="table table-hover table-striped align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th>Plat Nomor</th>
                            <th>Driver</th>
                            <th>Kondisi</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = mysqli_query($koneksi, "
                            SELECT k.*, m.driver_tetap
                            FROM kondisi_kendaraan k
                            JOIN master_mobil m ON k.id_mobil = m.id_mobil
                            ORDER BY (k.end_date IS NULL) DESC, k.start_date DESC
                        ");
                        while ($d = mysqli_fetch_array($query)) {
                            $aktif = is_null($d['end_date']);

                            $badge_kondisi = [
                                'DISERVICE'    => 'badge-diservice',
                                'RUSAK RINGAN' => 'badge-rusak-ringan',
                                'RUSAK BERAT'  => 'badge-rusak-berat',
                            ][$d['kondisi']] ?? 'bg-secondary';

                            // Durasi: kalau masih aktif dihitung sampai hari ini, kalau selesai dihitung sampai end_date
                            $durasi_teks = '-';
                            if ($d['start_date']) {
                                $start = new DateTime($d['start_date']);
                                $sampai = $aktif ? new DateTime() : new DateTime($d['end_date']);
                                $durasi = $start->diff($sampai)->days + 1;
                                $durasi_teks = $durasi . ' hari' . ($aktif ? ' (berjalan)' : '');
                            }
                        ?>
                        <tr>
                            <td class="fw-bold text-primary"><?= htmlspecialchars($d['plat_nomor']) ?></td>
                            <td class="text-uppercase"><?= htmlspecialchars($d['driver_tetap'] ?? '-') ?></td>
                            <td><span class="badge <?= $badge_kondisi ?>"><?= htmlspecialchars($d['kondisi']) ?></span></td>
                            <td><?= $d['start_date'] ? date('d-M-Y', strtotime($d['start_date'])) : '-' ?></td>
                            <td><?= $d['end_date'] ? date('d-M-Y', strtotime($d['end_date'])) : '-' ?></td>
                            <td><?= $durasi_teks ?></td>
                            <td>
                                <?= $aktif
                                    ? '<span class="badge bg-warning text-dark">AKTIF</span>'
                                    : '<span class="badge bg-success">SELESAI</span>' ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <?php if ($aktif): ?>
                                    <button class="btn btn-sm btn-outline-success"
                                            onclick="bukaModalSelesai(<?= (int)$d['id_kondisi'] ?>, '<?= htmlspecialchars($d['plat_nomor'], ENT_QUOTES) ?>', '<?= htmlspecialchars($d['kondisi'], ENT_QUOTES) ?>', '<?= $d['start_date'] ?>')"
                                            title="Tandai Selesai Servis">
                                        <i class="fas fa-check"></i> Selesaikan
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-danger" onclick="hapusKondisi(<?= (int)$d['id_kondisi'] ?>)" title="Hapus (koreksi data)">
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

<!-- Modal: Mobil Masuk Servis -->
<div class="modal fade" id="modalMulai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-wrench me-2"></i> Mobil Masuk Servis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formMulai">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cari Plat Nomor <span class="text-danger">*</span></label>
                        <select class="form-select" id="cari_plat" style="width: 100%;">
                            <option value="">Cari plat nomor...</option>
                        </select>
                        <input type="hidden" id="id_mobil">
                        <input type="hidden" id="plat_nomor">
                    </div>

                    <div id="infoMobil" style="display:none;" class="mobil-info mb-3">
                        <div class="mobil-info-item"><span class="mobil-info-label">Driver Tetap</span><span class="mobil-info-value" id="info_driver">-</span></div>
                        <div class="mobil-info-item"><span class="mobil-info-label">Jenis Kendaraan</span><span class="mobil-info-value" id="info_jenis">-</span></div>
                        <div class="mobil-info-item"><span class="mobil-info-label">Merk / Tipe</span><span class="mobil-info-value" id="info_merk">-</span></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Kondisi <span class="text-danger">*</span></label>
                            <select class="form-select" id="kondisi_mulai" required>
                                <option value="">Pilih Kondisi</option>
                                <option value="DISERVICE">DISERVICE</option>
                                <option value="RUSAK RINGAN">RUSAK RINGAN</option>
                                <option value="RUSAK BERAT">RUSAK BERAT</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Tanggal Masuk Servis <span class="text-danger">*</span></label>
                            <div class="input-group date">
                                <input type="text" class="form-control datepicker" id="start_date_mulai" placeholder="DD-MMM-YYYY" autocomplete="off" required>
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">Keterangan</label>
                        <textarea class="form-control" id="keterangan_mulai" rows="3" placeholder="Contoh: ganti kampas rem, servis rutin, dll."></textarea>
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

<!-- Modal: Selesaikan Servis -->
<div class="modal fade" id="modalSelesai" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Selesaikan Servis</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formSelesai">
                <div class="modal-body">
                    <input type="hidden" id="id_kondisi_selesai">
                    <div class="mobil-info mb-3">
                        <div class="mobil-info-item"><span class="mobil-info-label">Plat Nomor</span><span class="mobil-info-value" id="selesai_plat">-</span></div>
                        <div class="mobil-info-item"><span class="mobil-info-label">Kondisi</span><span class="mobil-info-value" id="selesai_kondisi">-</span></div>
                        <div class="mobil-info-item"><span class="mobil-info-label">Mulai Servis</span><span class="mobil-info-value" id="selesai_mulai">-</span></div>
                    </div>
                    <label class="form-label fw-bold">Tanggal Selesai Servis <span class="text-danger">*</span></label>
                    <div class="input-group date">
                        <input type="text" class="form-control datepicker" id="end_date_selesai" placeholder="DD-MMM-YYYY" autocomplete="off" required>
                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                    <small class="text-muted">Mobil otomatis tercatat kembali BAIK setelah tanggal ini disimpan.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Tandai Selesai</button>
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
$(document).ready(function () {
    $('#tabelKondisi').DataTable({
        pageLength: 10,
        language: { url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
        columnDefs: [{ orderable: false, targets: 7 }],
        responsive: true
    });

    $('.datepicker').datepicker({
        format: 'dd-M-yyyy',
        autoclose: true,
        todayHighlight: true,
        language: 'id',
        orientation: 'bottom'
    });

    $('#cari_plat').select2({
        theme: 'bootstrap-5',
        placeholder: 'Cari plat nomor...',
        allowClear: true,
        ajax: {
            url: 'ajax_search_plat.php',
            dataType: 'json',
            delay: 250,
            data: params => ({ search: params.term }),
            processResults: data => ({ results: data }),
            cache: true
        },
        minimumInputLength: 1,
        dropdownParent: $('#modalMulai')
    });

    $('#cari_plat').on('select2:select', function (e) {
        if (e.params.data.id) ambilDataMobil(e.params.data.id);
    });
    $('#cari_plat').on('select2:clear', function () {
        $('#infoMobil').hide();
        $('#id_mobil, #plat_nomor').val('');
    });
});

function resetFormMulai() {
    document.getElementById('formMulai').reset();
    $('#infoMobil').hide();
    $('#id_mobil, #plat_nomor').val('');
    $('#cari_plat').val(null).trigger('change');
    $('#start_date_mulai').datepicker('update', '');
}

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

$('#formMulai').on('submit', function (e) {
    e.preventDefault();
    if (!$('#id_mobil').val()) { alert('Pilih plat nomor mobil terlebih dahulu.'); return; }
    if (!$('#kondisi_mulai').val()) { alert('Pilih kondisi terlebih dahulu.'); return; }
    if (!$('#start_date_mulai').val()) { alert('Isi tanggal masuk servis.'); return; }

    $.post('ajax_mulai_service.php', {
        id_mobil: $('#id_mobil').val(),
        plat_nomor: $('#plat_nomor').val(),
        kondisi: $('#kondisi_mulai').val(),
        start_date: $('#start_date_mulai').val(),
        keterangan: $('#keterangan_mulai').val()
    }, function (res) {
        alert(res.message);
        if (res.status === 'success') location.reload();
    }, 'json').fail(function () { alert('Gagal menghubungi server.'); });
});

function bukaModalSelesai(id_kondisi, plat, kondisi, start_date) {
    $('#id_kondisi_selesai').val(id_kondisi);
    $('#selesai_plat').text(plat);
    $('#selesai_kondisi').text(kondisi);
    $('#selesai_mulai').text(start_date ? new Date(start_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-');
    $('#end_date_selesai').datepicker('update', new Date());
    $('#modalSelesai').modal('show');
}

$('#formSelesai').on('submit', function (e) {
    e.preventDefault();
    if (!$('#end_date_selesai').val()) { alert('Isi tanggal selesai servis.'); return; }

    $.post('ajax_selesai_service.php', {
        id_kondisi: $('#id_kondisi_selesai').val(),
        end_date: $('#end_date_selesai').val()
    }, function (res) {
        alert(res.message);
        if (res.status === 'success') location.reload();
    }, 'json').fail(function () { alert('Gagal menghubungi server.'); });
});

function hapusKondisi(id_kondisi) {
    if (!confirm('Hapus data riwayat ini? Tindakan ini hanya untuk koreksi data yang salah input.')) return;
    $.post('ajax_hapus_kondisi.php', { id_kondisi: id_kondisi }, function (res) {
        alert(res.message);
        if (res.status === 'success') location.reload();
    }, 'json').fail(function () { alert('Gagal menghubungi server.'); });
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