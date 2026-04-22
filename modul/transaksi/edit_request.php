<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';


if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// ── Ambil header request ─────────────────────────────────────
$query_h = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '$id'");
$h = mysqli_fetch_array($query_h);

if (!$h) {
    echo "<script>alert('Data tidak ditemukan!'); window.location='pr.php';</script>";
    exit;
}
if (in_array($h['status_request'], ['SELESAI', 'BATAL'])) {
    echo "<script>alert('Data sudah selesai/dibatalkan, tidak bisa diedit!'); window.location='pr.php';</script>";
    exit;
}
if ($h['status_request'] == 'PROSES') {
    $cek_pending = mysqli_num_rows(mysqli_query($koneksi,
        "SELECT id_detail FROM tr_request_detail 
         WHERE id_request = '$id' AND status_item = 'PENDING'"
    ));
    if ($cek_pending == 0) {
        echo "<script>alert('Semua item sudah diproses, tidak ada yang bisa diedit!'); window.location='pr.php';</script>";
        exit;
    }
}

$nama_user_login = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : "USER";

// ════════════════════════════════════════════════════════════════
// Ambil semua data master SEKALI, simpan ke array
// Jangan query di dalam loop
// ════════════════════════════════════════════════════════════════

// 1. Master Barang → array
$list_barang = [];
$res_barang  = mysqli_query($koneksi, "SELECT * FROM master_barang WHERE status_aktif='AKTIF' ORDER BY nama_barang ASC");
while ($b = mysqli_fetch_assoc($res_barang)) {
    $list_barang[] = $b;
}
mysqli_free_result($res_barang);

// 2. Master Mobil → array
$list_mobil = [];
$res_mobil  = mysqli_query($koneksi, "SELECT id_mobil, plat_nomor FROM master_mobil WHERE status_aktif='AKTIF' ORDER BY plat_nomor ASC");
while ($m = mysqli_fetch_assoc($res_mobil)) {
    $list_mobil[] = $m;
}
mysqli_free_result($res_mobil);

// 3. Daftar Petugas Pembeli
$list_pembeli = [];
$res_pembeli  = mysqli_query($koneksi, "SELECT nama_lengkap FROM users WHERE status_aktif='AKTIF' AND (role='bagian_pembelian' OR bagian='Pembelian') ORDER BY nama_lengkap ASC");
while ($u = mysqli_fetch_assoc($res_pembeli)) {
    $list_pembeli[] = strtoupper($u['nama_lengkap']);
}
mysqli_free_result($res_pembeli);

// 4. Satuan (statis)
$sats = ["PCS","DUS","KG","ONS","LITER","ML","METER","CM","LONJOR","SET","ROLL",
         "PACK","UNIT","DRUM","SAK","PAIL","CAN","BOTOL","TUBE","GALON",
         "IKAT","LEMBAR","TABUNG","KALENG","BATANG","KOTAK","COLT","JURIGEN","RIM"];

// 5. Build HTML opsi sekali pakai (di-reuse di setiap baris & JS addRow)
// ── Opsi Barang ──
$html_opsi_barang = '<option value="">-- PILIH BARANG --</option>';
foreach ($list_barang as $b) {
    $html_opsi_barang .=
        '<option value="' . $b['id_barang'] . '"'
        . ' data-nama="'     . htmlspecialchars(strtoupper($b['nama_barang']), ENT_QUOTES) . '"'
        . ' data-satuan="'   . htmlspecialchars(strtoupper($b['satuan']),      ENT_QUOTES) . '"'
        . ' data-merk="'     . htmlspecialchars(strtoupper($b['merk']),        ENT_QUOTES) . '"'
        . ' data-kategori="' . htmlspecialchars(strtoupper($b['kategori']),    ENT_QUOTES) . '"'
        . ' data-harga="'    . $b['harga_barang_stok'] . '">'
        . htmlspecialchars($b['nama_barang'])
        . '</option>';
}

// ── Opsi Mobil ──
$html_opsi_mobil = '<option value="0">NON MOBIL</option>';
foreach ($list_mobil as $m) {
    $html_opsi_mobil .= '<option value="' . $m['id_mobil'] . '">' . htmlspecialchars($m['plat_nomor']) . '</option>';
}

// ── Opsi Satuan ──
$html_opsi_satuan = '<option value="">- PILIH -</option>';
foreach ($sats as $s) {
    $html_opsi_satuan .= '<option value="' . $s . '">' . $s . '</option>';
}

// 6. Buat lookup plat nomor berdasarkan id_mobil untuk tampilan locked row
$map_mobil = [];
foreach ($list_mobil as $m) {
    $map_mobil[$m['id_mobil']] = $m['plat_nomor'];
}

// 7. Detail request
$list_detail = [];
$res_detail  = mysqli_query($koneksi,
    "SELECT * FROM tr_request_detail 
     WHERE id_request = '$id' 
     ORDER BY FIELD(status_item,'PENDING','APPROVED','REJECTED','MENUNGGU VERIFIKASI','TERBELI') ASC"
);
while ($d = mysqli_fetch_assoc($res_detail)) {
    $list_detail[] = $d;
}
mysqli_free_result($res_detail);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Request - <?= $h['no_request'] ?></title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f4f7f6; font-size: 0.85rem; }
        .card-header { background: white; border-bottom: 2px solid #eee; }
        .table-input thead { background: var(--mcp-blue); color: white; font-size: 0.75rem; text-transform: uppercase; }
        .table-responsive { border-radius: 8px; overflow-x: auto; }
        .table-input { min-width: 1000px; table-layout: fixed; }
        .col-brg { width: 220px; }
        .col-kat { width: 140px; }
        .col-mbl { width: 130px; }
        .col-tip { width: 100px; }
        .col-qty { width: 80px; }
        .col-sat { width: 110px; }
        .col-ket { width: 350px; }
        .col-aks { width: 80px; }
        input, select, textarea { text-transform: uppercase; font-size: 0.8rem !important; }
        .info-audit { font-size: 0.75rem; color: #6c757d; background: #eee; padding: 5px 10px; border-radius: 5px; }
        .select2-container--bootstrap-5 .select2-selection { min-height: 31px !important; padding: 2px 5px !important; }
        .row-locked { background-color: #f8f9fa; }
        .locked-text { 
            font-size: 0.8rem; 
            color: #495057; 
            padding: 3px 5px; 
            display: block; 
        }
    </style>
</head>
<body class="py-4">
<div class="container-fluid">
    <form action="proses_edit_request.php" method="POST">
        <input type="hidden" name="id_request" value="<?= $h['id_request'] ?>">

        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card shadow-sm border-0 mb-4">

                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold m-0 text-primary">
                            <i class="fas fa-edit me-2"></i> EDIT PURCHASE REQUEST
                        </h5>
                        <div class="info-audit">
                            <i class="fas fa-user me-1"></i> Dibuat oleh: <strong><?= $h['created_by'] ?></strong>
                        </div>
                    </div>

                    <div class="card-body">

                        <!-- Header Form -->
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <label class="small fw-bold text-muted">NOMOR REQUEST</label>
                                <input type="text" class="form-control bg-light fw-bold" value="<?= $h['no_request'] ?>" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-muted">TANGGAL REQUEST</label>
                                <input type="date" name="tgl_request" class="form-control" value="<?= $h['tgl_request'] ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-muted">ADMIN BAUT (PEMBUAT)</label>
                                <input type="text" name="nama_pemesan" class="form-control bg-light"
                                       value="<?= $nama_user_login ?>" readonly required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-primary">PETUGAS PEMBELIAN</label>
                                <select name="nama_pembeli" class="form-select select-pembeli" required>
                                    <option value="">-- PILIH PEMBELI --</option>
                                    <?php foreach ($list_pembeli as $val_u): ?>
                                        <option value="<?= $val_u ?>" <?= ($h['nama_pembeli'] == $val_u) ? 'selected' : '' ?>>
                                            <?= $val_u ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <hr>

                        <!-- Tabel Item -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-input align-middle" id="tableItem">
                                <thead>
                                    <tr class="text-center">
                                        <th class="col-brg">Nama Barang</th>
                                        <th class="col-kat">Kategori</th>
                                        <th class="col-mbl">Unit/Mobil</th>
                                        <th class="col-tip">Tipe</th>
                                        <th class="col-qty">Qty</th>
                                        <th class="col-sat">Satuan</th>
                                        <th class="col-ket">Keperluan / Ket. nama driver jika beda</th>
                                        <th class="col-aks">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($list_detail as $d):
                                    $is_locked    = in_array($d['status_item'], ['TERBELI', 'MENUNGGU VERIFIKASI']);
                                    $status_label = $d['status_item'];
                                    $row_class    = $is_locked ? 'item-row row-locked' : 'item-row';
                                    $nama_mobil   = ($d['id_mobil'] == 0) ? 'NON MOBIL' : ($map_mobil[$d['id_mobil']] ?? 'NON MOBIL');
                                ?>
                                <tr class="<?= $row_class ?>">

                                    <!-- ══════════════════════════════════════════════════════
                                         KOLOM 1: Nama Barang
                                         PERBAIKAN UTAMA: Baris locked pakai hidden input biasa
                                         (bukan disabled select) agar ikut tersubmit ke POST.
                                         Semua hidden input baris locked dikumpulkan di sini
                                         agar index array POST tetap sinkron.
                                    ═══════════════════════════════════════════════════════ -->
                                    <td>
                                        <!-- Hidden input yang selalu ada di semua baris -->
                                        <input type="hidden" name="id_detail[]"          value="<?= $d['id_detail'] ?>">
                                        <input type="hidden" name="nama_barang_manual[]" value="<?= htmlspecialchars($d['nama_barang_manual']) ?>">
                                        <input type="hidden" name="kwalifikasi[]"        value="<?= htmlspecialchars($d['kwalifikasi']) ?>">
                                        <input type="hidden" name="harga[]"              value="<?= $d['harga_satuan_estimasi'] ?>">

                                        <?php if ($is_locked): ?>
                                            <!-- LOCKED: semua field pakai hidden input agar ikut POST -->
                                            <input type="hidden" name="id_barang[]"         value="<?= $d['id_barang'] ?>">
                                            <input type="hidden" name="kategori_request[]"  value="<?= htmlspecialchars($d['kategori_barang']) ?>">
                                            <input type="hidden" name="id_mobil[]"          value="<?= $d['id_mobil'] ?>">
                                            <input type="hidden" name="tipe_request[]"      value="<?= htmlspecialchars($d['tipe_request']) ?>">
                                            <input type="hidden" name="jumlah[]"            value="<?= $d['jumlah'] ?>">
                                            <input type="hidden" name="satuan[]"            value="<?= htmlspecialchars($d['satuan']) ?>">
                                            <input type="hidden" name="keterangan[]"        value="<?= htmlspecialchars($d['keterangan']) ?>">
                                            <!-- Tampilkan nama barang sebagai teks -->
                                            <span class="locked-text fw-semibold">
                                                <?= htmlspecialchars(strtoupper($d['nama_barang_manual'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <!-- EDITABLE: pakai select biasa -->
                                            <select name="id_barang[]" class="form-select form-select-sm select-barang" required>
                                                <?php foreach ($list_barang as $b): ?>
                                                    <option value="<?= $b['id_barang'] ?>"
                                                        data-nama="<?=     htmlspecialchars(strtoupper($b['nama_barang']), ENT_QUOTES) ?>"
                                                        data-satuan="<?=   htmlspecialchars(strtoupper($b['satuan']),      ENT_QUOTES) ?>"
                                                        data-merk="<?=     htmlspecialchars(strtoupper($b['merk']),        ENT_QUOTES) ?>"
                                                        data-kategori="<?= htmlspecialchars(strtoupper($b['kategori']),    ENT_QUOTES) ?>"
                                                        data-harga="<?= $b['harga_barang_stok'] ?>"
                                                        <?= ($b['id_barang'] == $d['id_barang']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($b['nama_barang']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kolom 2: Kategori -->
                                    <td>
                                        <?php if ($is_locked): ?>
                                            <span class="locked-text"><?= htmlspecialchars($d['kategori_barang']) ?></span>
                                        <?php else: ?>
                                            <select name="kategori_request[]" class="form-select form-select-sm select-kategori" required>
                                                <option value="">- PILIH -</option>
                                                <optgroup label="BENGKEL">
                                                    <?php foreach (['BENGKEL MOBIL','BENGKEL LISTRIK','BENGKEL DINAMO','BENGKEL BUBUT','MESIN','LAS'] as $kat): ?>
                                                        <option value="<?= $kat ?>" <?= $d['kategori_barang'] == $kat ? 'selected' : '' ?>><?= $kat ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <optgroup label="UMUM">
                                                    <?php foreach (['KANTOR','BANGUNAN','UMUM'] as $kat): ?>
                                                        <option value="<?= $kat ?>" <?= $d['kategori_barang'] == $kat ? 'selected' : '' ?>><?= $kat ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            </select>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kolom 3: Unit/Mobil -->
                                    <td>
                                        <?php if ($is_locked): ?>
                                            <span class="locked-text"><?= htmlspecialchars($nama_mobil) ?></span>
                                        <?php else: ?>
                                            <select name="id_mobil[]" class="form-select form-select-sm select-mobil">
                                                <option value="0">NON MOBIL</option>
                                                <?php foreach ($list_mobil as $m): ?>
                                                    <option value="<?= $m['id_mobil'] ?>" <?= $m['id_mobil'] == $d['id_mobil'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($m['plat_nomor']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kolom 4: Tipe -->
                                    <td>
                                        <?php if ($is_locked): ?>
                                            <span class="locked-text"><?= htmlspecialchars($d['tipe_request']) ?></span>
                                        <?php else: ?>
                                            <select name="tipe_request[]" class="form-select form-select-sm select-tipe">
                                                <option value="STOK"     <?= $d['tipe_request'] == 'STOK'     ? 'selected' : '' ?>>STOK</option>
                                                <option value="LANGSUNG" <?= $d['tipe_request'] == 'LANGSUNG' ? 'selected' : '' ?>>LANGSUNG</option>
                                            </select>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kolom 5: QTY -->
                                    <td>
                                        <?php if ($is_locked): ?>
                                            <span class="locked-text text-center d-block"><?= (float)$d['jumlah'] ?></span>
                                        <?php else: ?>
                                            <input type="number" name="jumlah[]"
                                                   class="form-control form-control-sm input-qty text-center"
                                                   step="any" value="<?= (float)$d['jumlah'] ?>" required>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kolom 6: Satuan -->
                                    <td>
                                        <?php if ($is_locked): ?>
                                            <span class="locked-text"><?= htmlspecialchars($d['satuan']) ?></span>
                                        <?php else: ?>
                                            <select name="satuan[]" class="form-select form-select-sm select-satuan" required>
                                                <option value="">- PILIH -</option>
                                                <?php foreach ($sats as $s): ?>
                                                    <option value="<?= $s ?>" <?= $d['satuan'] == $s ? 'selected' : '' ?>><?= $s ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kolom 7: Keterangan -->
                                    <td>
                                        <?php if ($is_locked): ?>
                                            <span class="locked-text"><?= htmlspecialchars($d['keterangan']) ?></span>
                                        <?php else: ?>
                                            <textarea name="keterangan[]" class="form-control form-control-sm" rows="1"><?= htmlspecialchars($d['keterangan']) ?></textarea>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Kolom 8: Aksi / Status -->
                                    <td class="text-center">
                                        <?php if (!$is_locked): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row border-0">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php elseif ($status_label == 'TERBELI'): ?>
                                            <span class="badge bg-success" style="font-size:0.65rem;">TERBELI</span>
                                        <?php elseif ($status_label == 'MENUNGGU VERIFIKASI'): ?>
                                            <span class="badge bg-warning text-dark" style="font-size:0.65rem; white-space:normal;">
                                                MENUNGGU<br>VERIFIKASI
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" id="addRow" class="btn btn-sm btn-success fw-bold px-3 mt-2 shadow-sm">
                            <i class="fas fa-plus me-1"></i> Tambah Baris
                        </button>

                    </div><!-- /.card-body -->

                    <div class="card-footer bg-white py-3">
                        <button type="submit" class="btn btn-primary fw-bold px-5 shadow-sm">
                            <i class="fas fa-save me-1"></i> SIMPAN PERUBAHAN
                        </button>
                        <a href="pr.php" class="btn btn-danger fw-bold px-4">BATAL</a>
                    </div>

                </div><!-- /.card -->
            </div>
        </div>
    </form>
</div>

<!-- Simpan opsi HTML ke variabel JS agar addRow tidak perlu DOM query -->
<script>
var OPSI_BARANG  = <?php echo json_encode($html_opsi_barang); ?>;
var OPSI_MOBIL   = <?php echo json_encode($html_opsi_mobil);  ?>;
var OPSI_SATUAN  = <?php echo json_encode($html_opsi_satuan); ?>;
var OPSI_KATEGORI =
    '<option value="">- PILIH -</option>' +
    '<optgroup label="BENGKEL">' +
        '<option value="BENGKEL MOBIL">BENGKEL MOBIL</option>'     +
        '<option value="BENGKEL LISTRIK">BENGKEL LISTRIK</option>' +
        '<option value="BENGKEL DINAMO">BENGKEL DINAMO</option>'   +
        '<option value="BENGKEL BUBUT">BENGKEL BUBUT</option>'     +
        '<option value="MESIN">MESIN</option>'                     +
        '<option value="LAS">LAS</option>'                         +
    '</optgroup>' +
    '<optgroup label="UMUM">' +
        '<option value="KANTOR">KANTOR</option>'     +
        '<option value="BANGUNAN">BANGUNAN</option>' +
        '<option value="UMUM">UMUM</option>'         +
    '</optgroup>';
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function () {

    // ── Init Select2 ─────────────────────────────────────────────
    function initSelect2(context) {
        $(context).find('.select-barang, .select-kategori, .select-mobil, .select-tipe, .select-satuan, .select-pembeli').each(function () {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: '-- PILIH --'
                });
            }
        });
    }

    initSelect2('body');

    // ── Auto-fill saat pilih barang ──────────────────────────────
    $(document).on('change', '.select-barang', function () {
        var row      = $(this).closest('tr');
        var selected = $(this).find(':selected');
        row.find('.input-nama-barang').val(selected.data('nama')  || '');
        row.find('.input-kwalifikasi').val(selected.data('merk')  || '');
        row.find('.input-harga').val(selected.data('harga')       || 0);
        if (selected.data('kategori')) row.find('.select-kategori').val(selected.data('kategori')).trigger('change');
        if (selected.data('satuan'))   row.find('.select-satuan').val(selected.data('satuan')).trigger('change');
    });

    // ── Hapus baris ──────────────────────────────────────────────
	 // ── Hapus baris ──────────────────────────────────────────────
	$(document).on('click', '.remove-row', function () {
		// Menghitung SEMUA baris yang ada di tabel (termasuk yang locked)
		var totalBaris = $('#tableItem tbody tr').length;

		if (totalBaris > 1) {
			$(this).closest('tr').remove();
		} else {
			Swal.fire({
				icon: 'error',
				title: 'Gagal Menghapus',
				text: 'Request harus memiliki minimal 1 item barang.',
				confirmButtonColor: '#0000FF'
			});
		}
	});

    // ── Tambah baris baru ─────────────────────────────────────────
    // Menggunakan variabel JS (OPSI_*) bukan DOM query,
    // sehingga aman di semua environment termasuk Windows Server.
    // Baris baru TIDAK memiliki hidden input locked — hanya pakai select/input biasa.
    $('#addRow').on('click', function () {

        var newRow = $('<tr class="item-row"></tr>');

        // 1. Nama Barang + hidden inputs (id_detail kosong = baris baru)
        newRow.append(
            '<td>' +
                '<input type="hidden" name="id_detail[]"          value="">' +
                '<input type="hidden" name="nama_barang_manual[]" class="input-nama-barang" value="">' +
                '<input type="hidden" name="kwalifikasi[]"        class="input-kwalifikasi" value="">' +
                '<input type="hidden" name="harga[]"              class="input-harga"       value="0">' +
                '<select name="id_barang[]" class="form-select form-select-sm select-barang" required>' +
                    OPSI_BARANG +
                '</select>' +
            '</td>'
        );

        // 2. Kategori
        newRow.append(
            '<td>' +
                '<select name="kategori_request[]" class="form-select form-select-sm select-kategori" required>' +
                    OPSI_KATEGORI +
                '</select>' +
            '</td>'
        );

        // 3. Unit/Mobil
        newRow.append(
            '<td>' +
                '<select name="id_mobil[]" class="form-select form-select-sm select-mobil">' +
                    OPSI_MOBIL +
                '</select>' +
            '</td>'
        );

        // 4. Tipe
        newRow.append(
            '<td>' +
                '<select name="tipe_request[]" class="form-select form-select-sm select-tipe">' +
                    '<option value="STOK" selected>STOK</option>'  +
                    '<option value="LANGSUNG">LANGSUNG</option>'   +
                '</select>' +
            '</td>'
        );

        // 5. QTY
        newRow.append(
            '<td>' +
                '<input type="number" name="jumlah[]" class="form-control form-control-sm input-qty text-center" step="any" value="1" required>' +
            '</td>'
        );

        // 6. Satuan
        newRow.append(
            '<td>' +
                '<select name="satuan[]" class="form-select form-select-sm select-satuan" required>' +
                    OPSI_SATUAN +
                '</select>' +
            '</td>'
        );

        // 7. Keterangan
        newRow.append(
            '<td><textarea name="keterangan[]" class="form-control form-control-sm" rows="1"></textarea></td>'
        );

        // 8. Aksi
        newRow.append(
            '<td class="text-center">' +
                '<button type="button" class="btn btn-sm btn-outline-danger remove-row border-0">' +
                    '<i class="fas fa-times"></i>' +
                '</button>' +
            '</td>'
        );

        $('#tableItem tbody').append(newRow);
        initSelect2(newRow);

        // Set default setelah Select2 init
        newRow.find('.select-barang').val('').trigger('change');
        newRow.find('.select-mobil').val('0').trigger('change');
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
            fetch('/pr_mcp_rev4/auth/keep_alive.php')
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
        window.location.href = "/pr_mcp_rev4/auth/logout.php?pesan=timeout";
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
        fetch('/pr_mcp_rev4/auth/keep_alive.php')
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
</body>
</html>