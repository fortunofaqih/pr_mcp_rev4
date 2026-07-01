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

// Daftar kondisi - HANYA SEKALI QUERY
$q_kondisi = mysqli_query($koneksi, "SELECT * FROM master_it_kondisi ORDER BY nama_kondisi");
$kondisi_list = [];
while($row = mysqli_fetch_assoc($q_kondisi)) {
    $kondisi_list[] = $row['nama_kondisi'];
}

// Reset pointer untuk digunakan lagi
mysqli_data_seek($q_kondisi, 0);

// Daftar barang
$q_barang = mysqli_query($koneksi, "SELECT id_barang, nama_barang, merk, kategori FROM master_barang WHERE status_aktif = 'AKTIF' AND kategori = 'INVESTASI IT' ORDER BY nama_barang ASC");

$additional_css = '
<style>
    .form-section { background: #f8f9ff; border-left: 4px solid #0d6efd; border-radius: 4px; padding: 15px 20px; margin-bottom: 20px; }
    .form-section-title { font-weight: 700; color: #0d6efd; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 12px; }
    .required-star { color: #dc3545; }
    .kode-preview { font-family: monospace; font-size: 1.2rem; font-weight: bold; color: #0d6efd; background: #e8f0fe; padding: 8px 16px; border-radius: 6px; display: inline-block; }
    
    /* Tambahan styling untuk kondisi */
    #kondisi_select {
        border-right: none;
    }
    #kondisi_select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    #kondisi_manual_wrapper {
        transition: all 0.3s ease;
        padding: 8px 0;
    }
    #kondisi_manual_wrapper .input-group {
        border-radius: 6px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    #btn_tambah_kondisi {
        border-top-right-radius: 6px;
        border-bottom-right-radius: 6px;
    }
    #btn_refresh_kondisi {
        border-radius: 0;
        border-left: none;
    }
    #btn_refresh_kondisi:hover {
        background-color: #f8f9fa;
    }
</style>
';
?>

<!-- Include SweetAlert2 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
                                // Reset pointer q_barang
                                mysqli_data_seek($q_barang, 0);
                                while($brg = mysqli_fetch_assoc($q_barang)): 
                                ?>
                                <option value="<?= $brg['id_barang'] ?>" 
                                    data-nama="<?= htmlspecialchars($brg['nama_barang']) ?>"
                                    data-merk="<?= htmlspecialchars($brg['merk']) ?>"
                                    <?= ($data['id_barang'] ?? '') == $brg['id_barang'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brg['nama_barang']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
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
						       <div class="col-12">
								<label class="form-label fw-bold small">Keterangan Barang</label>
								<textarea name="keterangan_barang" class="form-control" rows="2"
										  placeholder="Informasi tambahan tentang barang ini (misal: kondisi fisik, aksesoris yang disertakan, catatan khusus, dll)..."><?= htmlspecialchars($data['keterangan_barang'] ?? '') ?></textarea>
								<small class="text-muted">
									<i class="fas fa-info-circle me-1"></i>
									Isikan informasi tambahan tentang barang ini jika diperlukan.
								</small>
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
						
						<div class="input-group">
							<select name="kondisi" id="kondisi_select" class="form-select" required>
								<option value="">-- Pilih Kondisi --</option>
								<?php 
								mysqli_data_seek($q_kondisi, 0);
								while($row = mysqli_fetch_assoc($q_kondisi)): 
								?>
								<option value="<?= htmlspecialchars($row['nama_kondisi']) ?>"
									<?= ($data['kondisi'] ?? '') == $row['nama_kondisi'] ? 'selected' : '' ?>>
									<?= htmlspecialchars($row['nama_kondisi']) ?>
								</option>
								<?php endwhile; ?>
								<option value="MANUAL" 
									<?= (isset($data['kondisi']) && !in_array($data['kondisi'], $kondisi_list)) ? 'selected' : '' ?>>
									✏️ -- Isi Manual --
								</option>
							</select>
							<button type="button" class="btn btn-outline-secondary" id="btn_refresh_kondisi" title="Refresh daftar kondisi">
								<i class="fas fa-sync-alt"></i>
							</button>
						</div>
						
						<!-- Input Manual -->
						<div id="kondisi_manual_wrapper" class="mt-2 <?= (isset($data['kondisi']) && !in_array($data['kondisi'], $kondisi_list)) ? '' : 'd-none' ?>">
							<div class="input-group">
								<span class="input-group-text"><i class="fas fa-pen"></i></span>
								<input type="text" id="kondisi_manual" class="form-control"
									   name="kondisi_manual" 
									   placeholder="Ketik nama kondisi baru (maks. 150 karakter)..."
									   value="<?= (isset($data['kondisi']) && !in_array($data['kondisi'], $kondisi_list)) ? htmlspecialchars($data['kondisi']) : '' ?>"
									   maxlength="150">
								<button type="button" class="btn btn-success" id="btn_tambah_kondisi">
									<i class="fas fa-plus"></i> Tambah
								</button>
							</div>
							<small class="text-muted">
								<i class="fas fa-info-circle me-1"></i>
								Maksimal 150 karakter. Kondisi baru akan tersimpan di master.
							</small>
						</div>
						
						<!-- Tampilkan kondisi yang dipilih -->
						<div id="selected_kondisi_display" class="mt-2 d-none">
							<span class="badge bg-info text-dark p-2">
								<i class="fas fa-check-circle me-1"></i>
								Kondisi terpilih: <strong id="selected_kondisi_text"></strong>
							</span>
						</div>
					</div>
					
					<!-- ============================================================ -->
					<!-- TAMBAH: KETERANGAN KONDISI -->
					<!-- ============================================================ -->
					<div class="mb-3">
						<label class="form-label fw-bold small">Keterangan Kondisi</label>
						<textarea name="keterangan_kondisi" class="form-control" rows="2"
								  placeholder="Deskripsi detail tentang kondisi aset (misal: ada goresan ringan, tombol power bermasalah, dll)..."><?= htmlspecialchars($data['keterangan_kondisi'] ?? '') ?></textarea>
						<small class="text-muted">
							<i class="fas fa-info-circle me-1"></i>
							Isikan penjelasan detail tentang kondisi aset jika diperlukan.
						</small>
					</div>
					
					<div class="mb-3">
						<label class="form-label fw-bold small">Status Aset <span class="required-star">*</span></label>
						<select name="status_asset" class="form-select" required>
							<option value="AKTIF" <?= ($data['status_asset'] ?? 'AKTIF') == 'AKTIF' ? 'selected' : '' ?>>AKTIF</option>
							<option value="TIDAK AKTIF" <?= ($data['status_asset'] ?? '') == 'TIDAK AKTIF' ? 'selected' : '' ?>>TIDAK AKTIF</option>
							<option value="DISPOSE" <?= ($data['status_asset'] ?? '') == 'DISPOSE' ? 'selected' : '' ?>>DISPOSE</option>
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
						<select name="lokasi" id="lokasi_select" class="form-select">
							<option value="">-- Pilih Lokasi --</option>
							<?php 
							mysqli_data_seek($q_lokasi, 0);
							while($lok = mysqli_fetch_assoc($q_lokasi)): 
							?>
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
					
					<!-- ============================================================ -->
					<!-- TAMBAH: KETERANGAN PENEMPATAN -->
					<!-- ============================================================ -->
					<div class="mb-3">
						<label class="form-label fw-bold small">Keterangan Penempatan</label>
						<textarea name="keterangan_penempatan" class="form-control" rows="2"
								  placeholder="Informasi detail tentang penempatan (misal: Rak 3 Lantai 2, Meja Supervisor, dll)..."><?= htmlspecialchars($data['keterangan_penempatan'] ?? '') ?></textarea>
						<small class="text-muted">
							<i class="fas fa-info-circle me-1"></i>
							Isikan informasi detail tentang lokasi penempatan aset.
						</small>
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

<!-- ============================================================ -->
<!-- JAVASCRIPT - SEMUA DALAM 1 BLOK -->
<!-- ============================================================ -->
<script>
$(document).ready(function() {
    
    // ============================================================
    // 1. INISIALISASI SELECT2
    // ============================================================
    $('.select2-barang').select2({
        theme: 'bootstrap-5',
        placeholder: "-- Ketik untuk mencari barang --",
        allowClear: true,
        width: '100%'
    });

    // ============================================================
    // 2. AUTO-FILL BARANG
    // ============================================================
    $('#select_barang').on('change', function() {
        const idBarang = $(this).val();
        const selectedOption = $(this).find('option:selected');
        const namaBarang = selectedOption.data('nama');
        const merkMaster = selectedOption.data('merk');

        if (idBarang) {
            $('#nama_asset_hidden').val(namaBarang);
            $('[name="merk"]').val(merkMaster);

            $.ajax({
                url: 'get_pembelian_info.php',
                type: 'GET',
                data: { id_barang: idBarang },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'found') {
                        $('[name="sumber_perolehan"]').val('PEMBELIAN').trigger('change');
                        $('[name="supplier"]').val(response.supplier);
                        $('[name="no_request"]').val(response.no_request);
                        $('[name="tgl_perolehan"]').val(response.tgl_perolehan);
                        $('[name="harga_perolehan"]').val(response.harga);
                        $('#harga_perolehan').val(formatRupiah(response.harga));
                        if(response.merk) $('[name="merk"]').val(response.merk);
                        alert('Data pembelian ditemukan! Form perolehan telah terisi otomatis.');
                    } else {
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

    // ============================================================
    // 3. FORMAT RUPIAH
    // ============================================================
    $('#harga_perolehan').on('keyup', function() {
        let val = $(this).val().replace(/[^0-9]/g, '');
        $(this).val(formatRupiah(val));
    });

    // ============================================================
    // 4. PREVIEW FOTO
    // ============================================================
    $('#input_foto').on('change', function(e) {
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

    // ============================================================
    // 5. LOKASI MANUAL
    // ============================================================
    $('#lokasi_select').on('change', function() {
        const manual = $('#lokasi_manual');
        if (this.value === "_manual_") {
            manual.removeClass('d-none');
            manual.prop('required', true);
        } else {
            manual.addClass('d-none');
            manual.prop('required', false);
        }
    });

    // ============================================================
    // 6. KONDISI - FUNGSI TOGGLE MANUAL
    // ============================================================
    function toggleKondisiManual() {
        const selectVal = $('#kondisi_select').val();
        const wrapper = $('#kondisi_manual_wrapper');
        const manualInput = $('#kondisi_manual');
        const displayDiv = $('#selected_kondisi_display');
        const displayText = $('#selected_kondisi_text');
        
        if (selectVal === 'MANUAL') {
            wrapper.removeClass('d-none');
            manualInput.prop('required', true);
            manualInput.focus();
            displayDiv.addClass('d-none');
        } else if (selectVal && selectVal !== '') {
            wrapper.addClass('d-none');
            manualInput.prop('required', false);
            displayText.text(selectVal);
            displayDiv.removeClass('d-none');
        } else {
            wrapper.addClass('d-none');
            manualInput.prop('required', false);
            displayDiv.addClass('d-none');
        }
    }

    // ============================================================
    // 7. KONDISI - EVENT CHANGE
    // ============================================================
    $('#kondisi_select').on('change', function() {
        toggleKondisiManual();
        if ($(this).val() !== 'MANUAL' && $(this).val() !== '') {
            $('#kondisi_manual').val('');
        }
    });

    // ============================================================
    // 8. KONDISI - AUTO-COMPLETE
    // ============================================================
    $('#kondisi_manual').on('keyup', function() {
        const val = $(this).val().trim().toUpperCase();
        if (val.length < 2) return;
        
        let matchFound = false;
        $('#kondisi_select option').each(function() {
            const optionVal = $(this).val();
            if (optionVal && optionVal.toUpperCase() === val) {
                $('#kondisi_select').val(optionVal);
                toggleKondisiManual();
                matchFound = true;
                return false;
            }
        });
        
        if (!matchFound) {
            $('#kondisi_select').val('MANUAL');
        }
    });

    // ============================================================
    // 9. KONDISI - TAMBAH BARU (AJAX)
    // ============================================================
    $('#btn_tambah_kondisi').on('click', function() {
        const kondisiBaru = $('#kondisi_manual').val().trim();
        
        if (!kondisiBaru) {
            Swal.fire({
                icon: 'warning',
                title: 'Perhatian',
                text: 'Silakan ketik nama kondisi terlebih dahulu!'
            });
            return;
        }
        
        if (kondisiBaru.length > 150) {
            Swal.fire({
                icon: 'warning',
                title: 'Terlalu Panjang',
                text: 'Nama kondisi maksimal 150 karakter!'
            });
            return;
        }
        
        const exists = $('#kondisi_select option').filter(function() {
            return $(this).val() && $(this).val().toUpperCase() === kondisiBaru.toUpperCase();
        }).length > 0;
        
        if (exists) {
            Swal.fire({
                icon: 'info',
                title: 'Sudah Ada',
                text: 'Kondisi "' + kondisiBaru + '" sudah ada di daftar!'
            });
            $('#kondisi_select').val(kondisiBaru);
            toggleKondisiManual();
            $('#kondisi_manual').val('');
            return;
        }
        
        const btn = $(this);
        const originalHtml = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin"></i> Menyimpan...');
        btn.prop('disabled', true);
        
        $.ajax({
            url: 'ajax_tambah_kondisi.php',
            type: 'POST',
            data: { nama_kondisi: kondisiBaru },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const newOption = $('<option>', {
                        value: response.nama_kondisi,
                        text: response.nama_kondisi
                    });
                    
                    const manualOption = $('#kondisi_select option[value="MANUAL"]');
                    if (manualOption.length) {
                        newOption.insertBefore(manualOption);
                    } else {
                        $('#kondisi_select').append(newOption);
                    }
                    
                    $('#kondisi_select').val(response.nama_kondisi);
                    toggleKondisiManual();
                    $('#kondisi_manual').val('');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Kondisi "' + response.nama_kondisi + '" berhasil ditambahkan!',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: response.message || 'Terjadi kesalahan!'
                    });
                }
            },
            error: function(xhr, status, error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error Server',
                    text: 'Terjadi kesalahan: ' + error
                });
            },
            complete: function() {
                btn.html(originalHtml);
                btn.prop('disabled', false);
            }
        });
    });

    // ============================================================
    // 10. KONDISI - REFRESH DROPDOWN (AJAX)
    // ============================================================
    $('#btn_refresh_kondisi').on('click', function() {
        const currentVal = $('#kondisi_select').val();
        const btn = $(this);
        const icon = btn.find('i');
        
        icon.addClass('fa-spin');
        btn.prop('disabled', true);
        
        $.ajax({
            url: 'ajax_get_kondisi.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const isManual = (currentVal === 'MANUAL');
                    const manualVal = $('#kondisi_manual').val();
                    
                    $('#kondisi_select').empty();
                    $('#kondisi_select').append('<option value="">-- Pilih Kondisi --</option>');
                    
                    $.each(response.data, function(index, item) {
                        $('#kondisi_select').append(
                            $('<option>', {
                                value: item.nama_kondisi,
                                text: item.nama_kondisi
                            })
                        );
                    });
                    
                    $('#kondisi_select').append(
                        $('<option>', {
                            value: 'MANUAL',
                            text: '✏️ -- Isi Manual --'
                        })
                    );
                    
                    if (isManual) {
                        $('#kondisi_select').val('MANUAL');
                        if (manualVal) {
                            $('#kondisi_manual').val(manualVal);
                            toggleKondisiManual();
                        }
                    } else if (currentVal && $('#kondisi_select option[value="' + currentVal + '"]').length > 0) {
                        $('#kondisi_select').val(currentVal);
                        toggleKondisiManual();
                    } else {
                        $('#kondisi_select').val('');
                        toggleKondisiManual();
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Daftar kondisi berhasil diperbarui!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Gagal memperbarui daftar kondisi!'
                });
            },
            complete: function() {
                icon.removeClass('fa-spin');
                btn.prop('disabled', false);
            }
        });
    });

    // ============================================================
    // 11. KONDISI - TOMBOL ENTER
    // ============================================================
    $('#kondisi_manual').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#btn_tambah_kondisi').click();
        }
    });

    // ============================================================
    // 12. INISIALISASI
    // ============================================================
    toggleKondisiManual();
    
    const manualVal = $('#kondisi_manual').val();
    if (manualVal && $('#kondisi_select').val() === 'MANUAL') {
        $('#kondisi_manual_wrapper').removeClass('d-none');
    }
    
});

// ============================================================
// FUNGSI GLOBAL: formatRupiah
// ============================================================
function formatRupiah(angka) {
    if (angka == null || angka == '') return '0';
    let val = Math.round(angka).toString();
    return val.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}
</script>