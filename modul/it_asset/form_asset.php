<?php
$page_title = "Form Aset IT";
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
require_once __DIR__ . '/../../header_it.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

if (!in_array($role, ['administrator', 'it'])) {
    header("Location: " . $base_url . "index.php");
    exit;
}

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($id > 0);
$data   = [];

if ($is_edit) {
    $q = mysqli_query($koneksi, "SELECT * FROM master_it_asset WHERE id_asset = $id");
    if (!$q || mysqli_num_rows($q) == 0) {
        header("Location: index.php");
        exit;
    }
    $data = mysqli_fetch_assoc($q);
}

// Generate kode asset otomatis
function generateKodeAsset($koneksi) {
    $tahun = date('Y');
    // Ambil / insert counter
    $q = mysqli_query($koneksi, "SELECT last_number FROM master_it_asset_counter WHERE tahun = '$tahun'");
    if (mysqli_num_rows($q) == 0) {
        mysqli_query($koneksi, "INSERT INTO master_it_asset_counter (tahun, last_number) VALUES ('$tahun', 0)");
        $last = 0;
    } else {
        $row  = mysqli_fetch_assoc($q);
        $last = $row['last_number'];
    }
    $next = $last + 1;
    return 'IT-' . $tahun . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
}

$kode_baru = $is_edit ? $data['kode_asset'] : generateKodeAsset($koneksi);


// Daftar lokasi
$q_lokasi = mysqli_query($koneksi, "SELECT * FROM master_it_lokasi ORDER BY nama_lokasi");
// Tambahkan ini di bagian atas setelah query lokasi
$q_barang = mysqli_query($koneksi, "SELECT id_barang, nama_barang, merk, kategori FROM master_barang WHERE status_aktif = 'AKTIF' ORDER BY nama_barang ASC");

$additional_css = '
<style>
    .form-section { background: #f8f9ff; border-left: 4px solid #0d6efd; border-radius: 4px; padding: 15px 20px; margin-bottom: 20px; }
    .form-section-title { font-weight: 700; color: #0d6efd; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 12px; }
    .required-star { color: #dc3545; }
    .kode-preview { font-family: monospace; font-size: 1.2rem; font-weight: bold; color: #0d6efd; background: #e8f0fe; padding: 8px 16px; border-radius: 6px; display: inline-block; }
</style>';


?>

<div class="d-flex align-items-center mb-4 gap-3">
    
    <h5 class="fw-bold mb-0 text-primary">
        <i class="fas fa-<?= $is_edit ? 'edit' : 'plus-circle' ?> me-2"></i>
        <?= $is_edit ? 'Edit Aset IT' : 'Tambah Aset IT (Manual)' ?>
    </h5>
</div>

<form method="POST" action="proses_asset.php" enctype="multipart/form-data">
    <input type="hidden" name="aksi"     value="<?= $is_edit ? 'update' : 'simpan' ?>">
    <input type="hidden" name="id_asset" value="<?= $id ?>">

    <div class="row g-4">
        <!-- ===== KOLOM KIRI ===== -->
        <div class="col-12 col-lg-8">

            <!-- Identitas Aset -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white fw-bold py-2">
                    <i class="fas fa-tag me-2"></i> Identitas Aset
                </div>
                <div class="card-body">
                    <div class="mb-3 text-center">
                        <div class="text-muted small mb-1">Kode Aset (Auto-Generated)</div>
                        <div class="kode-preview"><?= htmlspecialchars($kode_baru) ?></div>
                        <input type="hidden" name="kode_asset" value="<?= htmlspecialchars($kode_baru) ?>">
                    </div>
                    <hr>
                    <div class="row g-3">
                       <div class="col-12">
                                    <label class="form-label fw-bold small">Pilih Barang dari Master <span class="required-star">*</span></label>
                                    <select name="id_barang" id="select_barang" class="form-select select2-barang" required>
                                                <option value="">-- Ketik Nama Barang / Merk --</option>
                                                <?php 
                                                $q_barang = mysqli_query($koneksi, "SELECT id_barang, nama_barang, merk FROM master_barang WHERE status_aktif = 'AKTIF' ORDER BY nama_barang ASC");
                                                while($brg = mysqli_fetch_assoc($q_barang)): 
                                                ?>
                                                    <option value="<?= $brg['id_barang'] ?>" 
                                                        data-nama="<?= htmlspecialchars($brg['nama_barang']) ?>"
                                                        data-merk="<?= htmlspecialchars($brg['merk']) ?>"
                                                        <?= ($data['id_barang'] ?? '') == $brg['id_barang'] ? 'selected' : '' ?>>
                                                        
                                                        <!-- HANYA TAMPILKAN NAMA BARANG SAJA -->
                                                        <?= htmlspecialchars($brg['nama_barang']) ?>
                                                        
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                    <!-- Hidden input untuk menyimpan nama asset ke proses_asset.php -->
                                    <input type="hidden" name="nama_asset" id="nama_asset_hidden" value="<?= htmlspecialchars($data['nama_asset'] ?? '') ?>">
                                </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Merk</label>
                                <input type="text" name="merk" id="merk_asset" class="form-control"
                                    value="<?= htmlspecialchars($data['merk'] ?? '') ?>"
                                    placeholder="Terisi otomatis...">
                            </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Model / Tipe</label>
                            <input type="text" name="model" class="form-control"
                                   value="<?= htmlspecialchars($data['model'] ?? '') ?>"
                                   placeholder="VivoBook 14, LaserJet Pro...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Serial Number / No. Seri</label>
                            <input type="text" name="serial_number" class="form-control"
                                   value="<?= htmlspecialchars($data['serial_number'] ?? '') ?>"
                                   placeholder="SN12345678">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">No. IMEI <small class="text-muted">(khusus HP/tablet)</small></label>
                            <input type="text" name="no_imei" class="form-control"
                                   value="<?= htmlspecialchars($data['no_imei'] ?? '') ?>"
                                   placeholder="352656100000000">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Spesifikasi Teknis</label>
                            <textarea name="spesifikasi" class="form-control" rows="3"
                                      placeholder="RAM: 8GB, CPU: Intel Core i5, Storage: 512GB SSD, OS: Windows 11..."><?= htmlspecialchars($data['spesifikasi'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Info Perolehan -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white fw-bold py-2">
                    <i class="fas fa-shopping-bag me-2"></i> Informasi Perolehan
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Sumber Perolehan <span class="required-star">*</span></label>
                            <select name="sumber_perolehan" class="form-select" required id="sumber_select">
                                <option value="MANUAL"    <?= ($data['sumber_perolehan'] ?? 'MANUAL') == 'MANUAL'    ? 'selected' : '' ?>>Manual (Input Langsung)</option>
                                <option value="PEMBELIAN" <?= ($data['sumber_perolehan'] ?? '')        == 'PEMBELIAN' ? 'selected' : '' ?>>Dari Transaksi Pembelian</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Tanggal Perolehan <span class="required-star">*</span></label>
                            <input type="date" name="tgl_perolehan" class="form-control" required
                                   value="<?= $data['tgl_perolehan'] ?? date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Harga Perolehan (Rp)</label>
                            <input type="text" name="harga_perolehan" id="harga_perolehan" class="form-control"
                            value="<?= number_format($data['harga_perolehan'] ?? 0, 0, ',', '.') ?>"
                            placeholder="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Nama Supplier / Toko</label>
                            <input type="text" name="supplier" class="form-control"
                                   value="<?= htmlspecialchars($data['supplier'] ?? '') ?>"
                                   placeholder="Nama supplier atau toko pembelian">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">No. PR / Request <small class="text-muted">(jika ada)</small></label>
                            <input type="text" name="no_request" class="form-control"
                                   value="<?= htmlspecialchars($data['no_request'] ?? '') ?>"
                                   placeholder="PR/2024/001">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Garansi -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark fw-bold py-2">
                    <i class="fas fa-shield-alt me-2"></i> Informasi Garansi
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Mulai Garansi</label>
                            <input type="date" name="tgl_garansi_mulai" class="form-control"
                                   value="<?= $data['tgl_garansi_mulai'] ?? '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Garansi Sampai</label>
                            <input type="date" name="tgl_garansi_selesai" class="form-control"
                                   value="<?= $data['tgl_garansi_selesai'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i>
                        Sistem akan memberi peringatan otomatis jika garansi tersisa &lt; 30 hari.</small>
                    </div>
                </div>
            </div>

            <!-- Keterangan -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white fw-bold py-2">
                    <i class="fas fa-sticky-note me-2"></i> Keterangan Tambahan
                </div>
                <div class="card-body">
                    <textarea name="keterangan" class="form-control" rows="3"
                              placeholder="Catatan atau informasi tambahan tentang aset ini..."><?= htmlspecialchars($data['keterangan'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ===== KOLOM KANAN ===== -->
        <div class="col-12 col-lg-4">

            <!-- Status & Kondisi -->
            <div class="card mb-4">
                <div class="card-header bg-danger text-white fw-bold py-2">
                    <i class="fas fa-heartbeat me-2"></i> Status & Kondisi
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Kondisi Saat Ini <span class="required-star">*</span></label>
                        <select name="kondisi" class="form-select" required>
                            <?php foreach(['BAGUS','RUSAK','DI-SERVICE','TIDAK AKTIF','HILANG'] as $k): ?>
                            <option value="<?= $k ?>" <?= ($data['kondisi'] ?? 'BAGUS') == $k ? 'selected' : '' ?>><?= $k ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Status Aset <span class="required-star">*</span></label>
                        <select name="status_asset" class="form-select" required>
                            <option value="AKTIF"       <?= ($data['status_asset'] ?? 'AKTIF') == 'AKTIF'       ? 'selected' : '' ?>>AKTIF</option>
                            <option value="TIDAK AKTIF" <?= ($data['status_asset'] ?? '')      == 'TIDAK AKTIF' ? 'selected' : '' ?>>TIDAK AKTIF</option>
                            <option value="DISPOSE"     <?= ($data['status_asset'] ?? '')      == 'DISPOSE'     ? 'selected' : '' ?>>DISPOSE</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Penempatan -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white fw-bold py-2">
                    <i class="fas fa-map-marker-alt me-2"></i> Penempatan
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Lokasi / Ruangan</label>
                        <select name="lokasi" class="form-select">
                            <option value="">-- Pilih Lokasi --</option>
                            <?php while($lok = mysqli_fetch_assoc($q_lokasi)): ?>
                            <option value="<?= $lok['nama_lokasi'] ?>"
                                <?= ($data['lokasi'] ?? '') == $lok['nama_lokasi'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lok['nama_lokasi']) ?>
                            </option>
                            <?php endwhile; ?>
                            <option value="_manual_">-- Isi Manual --</option>
                        </select>
                        <input type="text" id="lokasi_manual" class="form-control mt-2 d-none"
                               name="lokasi_manual" placeholder="Ketik nama lokasi...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Pengguna / PIC</label>
                        <input type="text" name="pengguna" class="form-control"
                               value="<?= htmlspecialchars($data['pengguna'] ?? '') ?>"
                               placeholder="Nama pengguna/penanggung jawab">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Departemen</label>
                        <input type="text" name="departemen" class="form-control"
                               value="<?= htmlspecialchars($data['departemen'] ?? '') ?>"
                               placeholder="Finance, Operasional, IT...">
                    </div>
                </div>
            </div>

            <!-- Foto -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white fw-bold py-2">
                    <i class="fas fa-camera me-2"></i> Foto Aset
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($data['foto'])): ?>
                    <img src="<?= $base_url ?>uploads/it_asset/<?= $data['foto'] ?>"
                         class="img-fluid rounded mb-2" style="max-height:150px;" id="preview_foto">
                    <?php else: ?>
                    <img src="" class="img-fluid rounded mb-2 d-none" style="max-height:150px;" id="preview_foto">
                    <div class="text-muted small mb-2" id="no_foto_text"><i class="fas fa-image fa-2x mb-1 d-block"></i>Belum ada foto</div>
                    <?php endif; ?>
                    <input type="file" name="foto" id="input_foto" class="form-control form-control-sm" accept="image/*">
                    <small class="text-muted">Max 2MB, format JPG/PNG</small>
                    <?php if (!empty($data['foto'])): ?>
                    <input type="hidden" name="foto_lama" value="<?= $data['foto'] ?>">
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tombol Submit -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg fw-bold">
                    <i class="fas fa-save me-2"></i>
                    <?= $is_edit ? 'UPDATE ASET' : 'SIMPAN ASET' ?>
                </button>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Batal
                </a>
            </div>
        </div>
    </div>
</form>

<script>
// Preview foto
document.getElementById("input_foto").addEventListener("change", function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            const img = document.getElementById("preview_foto");
            const noText = document.getElementById("no_foto_text");
            img.src = ev.target.result;
            img.classList.remove("d-none");
            if (noText) noText.classList.add("d-none");
        };
        reader.readAsDataURL(file);
    }
});

// Lokasi manual
document.querySelector("[name=lokasi]").addEventListener("change", function() {
    const manual = document.getElementById("lokasi_manual");
    if (this.value === "_manual_") {
        manual.classList.remove("d-none");
        manual.required = true;
    } else {
        manual.classList.add("d-none");
        manual.required = false;
    }
});

</script>';
<script>
$(document).ready(function() {
    // 1. Inisialisasi Select2 untuk fitur pencarian
    $('.select2-barang').select2({
        theme: 'bootstrap-5', // Agar tampilan serasi dengan Bootstrap
        placeholder: "-- Ketik untuk mencari barang --",
        allowClear: true,
        width: '100%'
    });

    // 2. Fungsi Auto-fill ketika barang dipilih
$('#select_barang').on('change', function() {
    const idBarang = $(this).val();
    const selectedOption = $(this).find('option:selected');
    const namaBarang = selectedOption.data('nama');
    const merkMaster = selectedOption.data('merk');

    if (idBarang) {
        $('#nama_asset_hidden').val(namaBarang);
        $('[name="merk"]').val(merkMaster);

        // --- MULAI PROSES CEK PEMBELIAN ---
        $.ajax({
            url: 'get_pembelian_info.php',
            type: 'GET',
            data: { id_barang: idBarang },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'found') {
                    // Jika ditemukan di tabel pembelian, isi otomatis field perolehan
                    $('[name="sumber_perolehan"]').val('PEMBELIAN').trigger('change');
                    $('[name="supplier"]').val(response.supplier);
                    $('[name="no_request"]').val(response.no_request);
                    $('[name="tgl_perolehan"]').val(response.tgl_perolehan);
                    $('[name="harga_perolehan"]').val(response.harga);
                    $('#harga_perolehan').val(formatRupiah(response.harga));
                    
                    // Jika di pembelian ada merk spesifik, timpa merk master
                    if(response.merk) $('[name="merk"]').val(response.merk);
                    
                    alert('Data pembelian ditemukan! Form perolehan telah terisi otomatis.');
                } else {
                    // Jika tidak ditemukan, set ke Manual dan kosongkan
                    $('[name="sumber_perolehan"]').val('MANUAL').trigger('change');
                    $('#harga_perolehan').val('0');
                    $('[name="supplier"]').val('');
                    $('[name="no_request"]').val('');
                    $('[name="tgl_perolehan"]').val('<?= date('Y-m-d') ?>');
                    $('[name="harga_perolehan"]').val(0);
                }
            }
        });
    }
});
// Auto format saat user mengetik manual
$('#harga_perolehan').on('keyup', function() {
    let val = $(this).val().replace(/[^0-9]/g, ''); // Ambil angka saja
    $(this).val(formatRupiah(val));
});
    // --- Script lama Anda tetap dipertahankan di bawah ini ---
    
    // Preview foto
    document.getElementById("input_foto").addEventListener("change", function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                const img = document.getElementById("preview_foto");
                const noText = document.getElementById("no_foto_text");
                img.src = ev.target.result;
                img.classList.remove("d-none");
                if (noText) noText.classList.add("d-none");
            };
            reader.readAsDataURL(file);
        }
    });

    // Lokasi manual
    $("[name=lokasi]").on('change', function() {
        const manual = document.getElementById("lokasi_manual");
        if (this.value === "_manual_") {
            manual.classList.remove("d-none");
            manual.required = true;
        } else {
            manual.classList.add("d-none");
            manual.required = false;
        }
    });
});

// Fungsi untuk memformat angka ke Ribuan (titik)
function formatRupiah(angka) {
    if (angka == null || angka == '') return '0';
    // Hilangkan desimal .00 jika ada
    let val = Math.round(angka).toString();
    // Tambahkan titik setiap 3 digit
    return val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}
</script>



