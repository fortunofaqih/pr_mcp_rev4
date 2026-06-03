<?php
$page_title = "Detail Aset IT";
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

if (!in_array($role, ['administrator', 'it'])) {
    header("Location: ../../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: index.php"); exit; }

$q = mysqli_query($koneksi, "SELECT * FROM master_it_asset WHERE id_asset = $id");
if (!$q || mysqli_num_rows($q) == 0) { header("Location: index.php"); exit; }
$asset = mysqli_fetch_assoc($q);

$q_hist = mysqli_query($koneksi, "SELECT * FROM tr_it_asset_history WHERE id_asset = $id ORDER BY tgl_kejadian DESC, id_history DESC");

// Hitung garansi
$garansi_info  = '-';
$garansi_class = 'text-muted';
if ($asset['tgl_garansi_selesai']) {
    $tgl_garansi = new DateTime($asset['tgl_garansi_selesai']);
    $today = new DateTime();
    $diff  = $today->diff($tgl_garansi);
    $sisa  = $tgl_garansi > $today ? $diff->days : -$diff->days;
    if ($sisa < 0) {
        $garansi_info  = 'Expired ' . abs($sisa) . ' hari lalu';
        $garansi_class = 'text-danger fw-bold';
    } elseif ($sisa <= 30) {
        $garansi_info  = 'Sisa ' . $sisa . ' hari lagi!';
        $garansi_class = 'text-warning fw-bold';
    } else {
        $garansi_info  = 'Aktif (sisa ' . $sisa . ' hari)';
        $garansi_class = 'text-success';
    }
}

$kondisi_class = [
    'BAGUS'       => 'success',
    'RUSAK'       => 'danger',
    'DI-SERVICE'  => 'warning',
    'TIDAK AKTIF' => 'secondary',
    'HILANG'      => 'dark',
][$asset['kondisi']] ?? 'secondary';

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ✅ DEFINISIKAN $additional_css SEBELUM include header
$additional_css = '
<style>
    .timeline { position: relative; padding-left: 30px; }
    .timeline::before { content: ""; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
    .timeline-item { position: relative; margin-bottom: 20px; }
    .timeline-item::before {
        content: ""; position: absolute; left: -24px; top: 6px;
        width: 12px; height: 12px; border-radius: 50%;
        background: #0d6efd; border: 2px solid #fff;
        box-shadow: 0 0 0 2px #0d6efd;
    }
    .timeline-item.penerimaan::before { background:#198754; box-shadow:0 0 0 2px #198754; }
    .timeline-item.rusak::before      { background:#dc3545; box-shadow:0 0 0 2px #dc3545; }
    .timeline-item.servis::before     { background:#fd7e14; box-shadow:0 0 0 2px #fd7e14; }
    .timeline-item.pindah::before     { background:#0dcaf0; box-shadow:0 0 0 2px #0dcaf0; }
    .timeline-item.dispose::before    { background:#6c757d; box-shadow:0 0 0 2px #6c757d; }
    .info-row { display:flex; flex-wrap:wrap; gap:12px; margin:-6px; }
    .info-item { padding:14px 16px; border-radius:8px; flex:0 0 calc(50% - 12px); background:#f8f9fa; border-left:4px solid #0d6efd; transition:all 0.2s ease; }
    .info-item:hover { background:#e7f1ff; border-left-color:#0056b3; box-shadow:0 2px 8px rgba(13,110,253,0.1); transform:translateY(-1px); }
    @media(max-width:575px) { .info-item { flex:0 0 100%; } }
    .info-label { font-size:0.7rem; text-transform:uppercase; font-weight:800; color:#6c757d; letter-spacing:1px; margin-bottom:4px; }
    .info-value { font-size:0.95rem; font-weight:500; color:#212529; }
</style>';

// ✅ INCLUDE HEADER DI SINI — setelah semua variabel siap
require_once __DIR__ . '/../../header_it.php';


// Tampilkan flash
if ($flash_success): ?>
<div class="alert alert-success alert-dismissible fade show shadow-sm">
    <i class="fas fa-check-circle me-2"></i> <?= $flash_success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif;
if ($flash_error): ?>
<div class="alert alert-danger alert-dismissible fade show shadow-sm">
    <i class="fas fa-times-circle me-2"></i> <?= $flash_error ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Breadcrumb & Toolbar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div class="d-flex align-items-center gap-2">
        
        <h5 class="fw-bold mb-0 text-primary">
            <i class="fas fa-laptop me-2"></i> Detail Aset IT
        </h5>
    </div>
    <div class="d-flex gap-2 flex-wrap">
       <a href="report_excel_aset.php?id=<?= $id ?>"
            class="btn btn-success btn-sm fw-bold">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </a>
            
            <!-- ✅ Tombol Cetak PDF — tambahkan ke toolbar detail_aset.php -->
            <a href="cetak_pdf_aset.php?id=<?= $id ?>"
            class="btn btn-danger btn-sm fw-bold"
            target="_blank"
            title="Cetak atau simpan sebagai PDF">
                <i class="fas fa-file-pdf me-1"></i> Cetak PDF
            </a>
 
        <a href="form_asset.php?id=<?= $id ?>" class="btn btn-warning btn-sm fw-bold">
            <i class="fas fa-pencil me-1"></i> Edit
        </a>
        <button class="btn btn-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalHistory">
            <i class="fas fa-plus me-1"></i> Tambah Riwayat
        </button>
        <button onclick="konfirmasiHapus(<?= $id ?>, '<?= htmlspecialchars($asset['nama_asset'], ENT_QUOTES) ?>')"
                class="btn btn-danger btn-sm fw-bold">
            <i class="fas fa-trash me-1"></i> Hapus
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- ===== KOLOM KIRI: Info Aset ===== -->
    <div class="col-12 col-lg-5">

        <!-- Card Header Aset -->
        <div class="card mb-4">
            <div class="card-body text-center py-4">
                <?php if (!empty($asset['foto'])): ?>
                <img src="../../uploads/it_asset/<?= $asset['foto'] ?>"
                     class="img-fluid rounded mb-3" style="max-height:200px; object-fit:contain;">
                <?php else: ?>
                <div class="bg-light rounded p-4 mb-3 d-inline-block">
                    <i class="fas fa-laptop fa-4x text-muted"></i>
                </div>
                <?php endif; ?>
                <div class="fw-bold fs-5"><?= htmlspecialchars($asset['nama_asset']) ?></div>
                <?php if ($asset['merk']): ?>
                <div class="text-muted"><?= htmlspecialchars($asset['merk']) ?> <?= htmlspecialchars($asset['model'] ?? '') ?></div>
                <?php endif; ?>
                <div class="mt-2">
                    <span class="badge bg-<?= $kondisi_class ?> fs-6 px-3 py-2"><?= $asset['kondisi'] ?></span>
                </div>
                <div class="mt-2">
                    <span class="bg-primary text-white px-3 py-1 rounded fw-bold" style="font-family:monospace; letter-spacing:1px;">
                        <?= htmlspecialchars($asset['kode_asset']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Informasi Detail -->
        <div class="card mb-4 ">
            <div class="card-header bg-primary text-white fw-bold py-2 small">
                <i class="fas fa-info-circle me-2"></i> Identitas Aset
            </div>
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Serial Number</div>
                    <div class="info-value"><?= $asset['serial_number'] ?: '-' ?></div>
                    
                </div>
                <div class="info-item">
                     <div class="info-label">No. IMEI</div>
                    <div class="info-value"><?= $asset['no_imei'] ?: '-' ?></div>
                </div>
                <div class="info-item" style="flex:0 0 100%;">
                   <div class="info-label">Spesifikasi</div>
                    <div class="info-value small"><?= nl2br(htmlspecialchars($asset['spesifikasi'] ?: '-')) ?></div>
                    
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-success text-white fw-bold py-2 small">
                <i class="fas fa-shopping-bag me-2"></i> Info Perolehan
            </div>
            <div class="info-row">
                <div class="info-item">
                   <div class="info-label">Sumber</div>
                    <div class="info-value">
                        <span class="badge bg-<?= $asset['sumber_perolehan']=='PEMBELIAN'?'info text-dark':'secondary' ?>">
                            <?= $asset['sumber_perolehan'] ?>
                        </span>
                    </div>
                   
                </div>
                <div class="info-item">
                    <div class="info-label">Tgl Perolehan</div>
                    <div class="info-value"><?= $asset['tgl_perolehan'] ? date('d/m/Y', strtotime($asset['tgl_perolehan'])) : '-' ?></div>
                  
                </div>
                <div class="info-item">
                    <div class="info-label">Harga Perolehan</div>
                    <div class="info-value">Rp <?= number_format($asset['harga_perolehan'], 0, ',', '.') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Supplier</div>
                    <div class="info-value"><?= $asset['supplier'] ?: '-' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">No. PR</div>
                    <div class="info-value"><?= $asset['no_request'] ?: '-' ?></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-warning text-dark fw-bold py-2 small">
                <i class="fas fa-shield-alt me-2"></i> Garansi
            </div>
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Mulai</div>
                    <div class="info-value"><?= $asset['tgl_garansi_mulai'] ? date('d/m/Y', strtotime($asset['tgl_garansi_mulai'])) : '-' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Selesai</div>
                    <div class="info-value"><?= $asset['tgl_garansi_selesai'] ? date('d/m/Y', strtotime($asset['tgl_garansi_selesai'])) : '-' ?></div>
                </div>
                <div class="info-item" style="flex:0 0 100%;">
                    <div class="info-label">Status Garansi</div>
                    <div class="info-value <?= $garansi_class ?>"><?= $garansi_info ?> </div>
                  
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-info text-white fw-bold py-2 small">
                <i class="fas fa-map-marker-alt me-2"></i> Penempatan
            </div>
            <div class="info-row">
                <div class="info-item">
                    <div class="info-label">Lokasi</div>
                    <div class="info-value"><?= $asset['lokasi'] ?: '-' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Pengguna / PIC</div>
                    <div class="info-value"><?= $asset['pengguna'] ?: '-' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Departemen</div>
                    <div class="info-value"><?= $asset['departemen'] ?: '-' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== KOLOM KANAN: Timeline History ===== -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between bg-dark text-white py-2">
                <div class="fw-bold small">
                    <i class="fas fa-history me-2"></i> Riwayat Aset IT
                </div>
               
            </div>
            <div class="card-body" style="max-height:75vh; overflow-y:auto;">
                <?php if (mysqli_num_rows($q_hist) == 0): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-history fa-3x mb-2 opacity-25"></i>
                    <p>Belum ada riwayat tercatat.</p>
                </div>
                <?php else: ?>
                <div class="timeline">
                <?php while($hist = mysqli_fetch_assoc($q_hist)):
                    $jenis = $hist['jenis_history'];
                    $item_class_map = [
                        'PENERIMAAN'    => 'penerimaan',
                        'RUSAK'         => 'rusak',
                        'SERVIS MASUK'  => 'servis',
                        'SERVIS SELESAI'=> 'penerimaan',
                        'PINDAH LOKASI' => 'pindah',
                        'PINDAH PENGGUNA' => 'pindah',
                        'DISPOSE'       => 'dispose',
                        'HILANG'        => 'rusak',
                    ];
                    $item_class = $item_class_map[$jenis] ?? '';

                    $badge_map = [
                        'PENERIMAAN'     => 'success',
                        'RUSAK'          => 'danger',
                        'SERVIS MASUK'   => 'warning',
                        'SERVIS SELESAI' => 'success',
                        'PINDAH LOKASI'  => 'info',
                        'PINDAH PENGGUNA'=> 'info',
                        'DISPOSE'        => 'secondary',
                        'HILANG'         => 'dark',
                        'KONDISI UPDATE' => 'primary',
                        'CATATAN'        => 'light',
                    ];
                    $badge = $badge_map[$jenis] ?? 'secondary';
                    $icon_map = [
                        'PENERIMAAN'     => 'box-open',
                        'RUSAK'          => 'times-circle',
                        'SERVIS MASUK'   => 'tools',
                        'SERVIS SELESAI' => 'check-circle',
                        'PINDAH LOKASI'  => 'map-marker-alt',
                        'PINDAH PENGGUNA'=> 'user-edit',
                        'DISPOSE'        => 'trash-alt',
                        'HILANG'         => 'question-circle',
                        'KONDISI UPDATE' => 'exchange-alt',
                        'CATATAN'        => 'sticky-note',
                    ];
                    $icon = $icon_map[$jenis] ?? 'circle';
                ?>
                    <div class="timeline-item <?= $item_class ?>">
                        <div class="card shadow-sm border-0 mb-0">
                            <div class="card-body py-2 px-3">
                                <div class="d-flex align-items-start justify-content-between flex-wrap gap-1">
                                    <div>
                                        <span class="badge bg-<?= $badge ?> text-<?= $badge=='light'?'dark':'white' ?> mb-1">
                                            <i class="fas fa-<?= $icon ?> me-1"></i><?= $jenis ?>
                                        </span>
                                        <?php if ($hist['kondisi_sebelum'] && $hist['kondisi_sesudah']): ?>
                                        <div class="small">
                                            <span class="text-muted"><?= $hist['kondisi_sebelum'] ?></span>
                                            <i class="fas fa-arrow-right mx-1 text-primary"></i>
                                            <span class="fw-bold"><?= $hist['kondisi_sesudah'] ?></span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($hist['lokasi_sebelum'] || $hist['lokasi_sesudah']): ?>
                                        <div class="small text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= $hist['lokasi_sebelum'] ?: '-' ?> → <?= $hist['lokasi_sesudah'] ?: '-' ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($hist['pengguna_sebelum'] || $hist['pengguna_sesudah']): ?>
                                        <div class="small text-muted">
                                            <i class="fas fa-user me-1"></i>
                                            <?= $hist['pengguna_sebelum'] ?: '-' ?> → <?= $hist['pengguna_sesudah'] ?: '-' ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($hist['vendor_servis']): ?>
                                        <div class="small"><i class="fas fa-wrench me-1 text-warning"></i>Vendor: <?= htmlspecialchars($hist['vendor_servis']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($hist['biaya_servis'] > 0): ?>
                                        <div class="small text-danger"><i class="fas fa-money-bill me-1"></i>Biaya: Rp <?= number_format($hist['biaya_servis'], 0, ',', '.') ?></div>
                                        <?php endif; ?>
                                        <?php if ($hist['keterangan']): ?>
                                        <div class="small text-muted mt-1 fst-italic">"<?= htmlspecialchars($hist['keterangan']) ?>"</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="small fw-bold"><?= date('d/m/Y', strtotime($hist['tgl_kejadian'])) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($hist['created_by'] ?? '-') ?></div>
                                        <a href="proses_history.php?aksi=hapus&id=<?= $hist['id_history'] ?>&id_asset=<?= $id ?>"
                                           class="btn btn-outline-danger btn-sm mt-1 py-0 px-1"
                                           onclick="return confirm('Hapus riwayat ini?')"
                                           style="font-size:0.65rem;">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== MODAL TAMBAH HISTORY ===== -->
<div class="modal fade" id="modalHistory" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="proses_history.php">
                <input type="hidden" name="id_asset" value="<?= $id ?>">
                <div class="modal-header bg-success text-white">
                    <h6 class="modal-title fw-bold"><i class="fas fa-plus me-2"></i>Tambah Riwayat Aset</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Tanggal Kejadian <span class="text-danger">*</span></label>
                            <input type="date" name="tgl_kejadian" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Jenis Riwayat <span class="text-danger">*</span></label>
                            <select name="jenis_history" class="form-select" required id="jenis_hist_select">
                                <option value="">-- Pilih Jenis --</option>
                                <?php foreach([
                                    'PENERIMAAN','PINDAH LOKASI','PINDAH PENGGUNA',
                                    'SERVIS MASUK','SERVIS SELESAI','RUSAK','HILANG',
                                    'DISPOSE','KONDISI UPDATE','CATATAN'
                                ] as $j): ?>
                                <option value="<?= $j ?>"><?= $j ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 row-kondisi">
                            <label class="form-label fw-bold small">Kondisi Sebelum</label>
                            <select name="kondisi_sebelum" class="form-select">
                                <option value="">-- Pilih --</option>
                                <?php foreach(['BAGUS','RUSAK','DI-SERVICE','TIDAK AKTIF','HILANG'] as $k): ?>
                                <option value="<?= $k ?>" <?= $asset['kondisi']==$k?'selected':'' ?>><?= $k ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 row-kondisi">
                            <label class="form-label fw-bold small">Kondisi Sesudah</label>
                            <select name="kondisi_sesudah" class="form-select">
                                <option value="">-- Pilih --</option>
                                <?php foreach(['BAGUS','RUSAK','DI-SERVICE','TIDAK AKTIF','HILANG'] as $k): ?>
                                <option value="<?= $k ?>"><?= $k ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 row-lokasi">
                            <label class="form-label fw-bold small">Lokasi Sebelum</label>
                            <input type="text" name="lokasi_sebelum" class="form-control" value="<?= htmlspecialchars($asset['lokasi'] ?? '') ?>" placeholder="Lokasi asal...">
                        </div>
                        <div class="col-md-6 row-lokasi">
                            <label class="form-label fw-bold small">Lokasi Sesudah</label>
                            <input type="text" name="lokasi_sesudah" class="form-control" placeholder="Lokasi tujuan...">
                        </div>
                        <div class="col-md-6 row-pengguna">
                            <label class="form-label fw-bold small">Pengguna Sebelum</label>
                            <input type="text" name="pengguna_sebelum" class="form-control" value="<?= htmlspecialchars($asset['pengguna'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 row-pengguna">
                            <label class="form-label fw-bold small">Pengguna Sesudah</label>
                            <input type="text" name="pengguna_sesudah" class="form-control" placeholder="Nama pengguna baru...">
                        </div>
                        <div class="col-md-6 row-servis">
                            <label class="form-label fw-bold small">Vendor / Teknisi Servis</label>
                            <input type="text" name="vendor_servis" class="form-control" placeholder="Nama vendor/teknisi...">
                        </div>
                        <div class="col-md-3 row-servis">
                            <label class="form-label fw-bold small">Biaya Servis (Rp)</label>
                            <input type="number" name="biaya_servis" class="form-control" value="0">
                        </div>
                        <div class="col-md-3 row-servis">
                            <label class="form-label fw-bold small">Est. Selesai</label>
                            <input type="date" name="tgl_estimasi_selesai" class="form-control">
                        </div>
                        <div class="col-md-12 row-servis-done">
                            <label class="form-label fw-bold small">Tanggal Selesai Servis</label>
                            <input type="date" name="tgl_selesai_servis" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Keterangan / Catatan</label>
                            <textarea name="keterangan" class="form-control" rows="3" placeholder="Deskripsi kejadian atau catatan..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="fas fa-save me-1"></i> Simpan Riwayat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Hapus Aset -->
<div class="modal fade" id="modalHapus" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title fw-bold"><i class="fas fa-trash me-2"></i>Konfirmasi Hapus</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p class="mb-1">Yakin ingin menghapus aset:</p>
                <strong id="nama_hapus" class="text-danger fs-6"></strong>
                <p class="text-muted small mt-2">Semua riwayat aset ini juga akan terhapus!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="btn_hapus_confirm" class="btn btn-danger fw-bold">
                    <i class="fas fa-trash me-1"></i> Hapus
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function konfirmasiHapus(id, nama) {
    document.getElementById("nama_hapus").textContent = nama;
    document.getElementById("btn_hapus_confirm").href = "proses_asset.php?aksi=hapus&id=" + id;
    new bootstrap.Modal(document.getElementById("modalHapus")).show();
}

const jenisSel = document.getElementById("jenis_hist_select");
function toggleHistoryFields() {
    const v = jenisSel.value;
    document.querySelectorAll(".row-kondisi").forEach(el => el.style.display   = ["RUSAK","KONDISI UPDATE","PENERIMAAN","SERVIS SELESAI"].includes(v) ? "" : "none");
    document.querySelectorAll(".row-lokasi").forEach(el => el.style.display    = ["PINDAH LOKASI"].includes(v) ? "" : "none");
    document.querySelectorAll(".row-pengguna").forEach(el => el.style.display  = ["PINDAH PENGGUNA"].includes(v) ? "" : "none");
    document.querySelectorAll(".row-servis").forEach(el => el.style.display    = ["SERVIS MASUK"].includes(v) ? "" : "none");
    document.querySelectorAll(".row-servis-done").forEach(el => el.style.display = ["SERVIS SELESAI"].includes(v) ? "" : "none");
}
jenisSel.addEventListener("change", toggleHistoryFields);
toggleHistoryFields();
</script>

