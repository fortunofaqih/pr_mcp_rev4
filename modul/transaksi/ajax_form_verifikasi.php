<?php
/**
 * ajax_form_verifikasi.php
 * Dipanggil via AJAX GET → return HTML form verifikasi untuk 1 item staging
 */
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">ID tidak valid.</div>'; exit;
}

$id_staging = (int)$_GET['id'];
$q = mysqli_query($koneksi, "
    SELECT s.*, r.nama_pemesan AS pemesan_pr, r.no_request AS no_pr,
           rd.jumlah AS qty_pr, rd.satuan AS satuan_pr,
           rd.harga_satuan_estimasi, rd.kategori_barang, rd.keterangan AS ket_pr
    FROM pembelian_staging s
    LEFT JOIN tr_request r  ON r.id_request  = s.id_request
    LEFT JOIN tr_request_detail rd ON rd.id_detail = s.id_request_detail
    WHERE s.id_staging = $id_staging AND s.status_staging = 'MENUNGGU'
    LIMIT 1
");
$s = mysqli_fetch_assoc($q);

if (!$s) {
    echo '<div class="alert alert-warning">Data tidak ditemukan atau sudah diverifikasi.</div>'; exit;
}

$subtotal   = $s['qty'] * $s['harga'];
$tgl_nota   = date('d-m-Y', strtotime($s['tgl_beli_barang']));

// Daftar mobil untuk dropdown
$q_mob   = mysqli_query($koneksi, "SELECT id_mobil, plat_nomor, jenis_kendaraan FROM master_mobil WHERE status_aktif='AKTIF' ORDER BY plat_nomor ASC");
$mobil_list = [];
while ($m = mysqli_fetch_assoc($q_mob)) $mobil_list[] = $m;
?>

<!-- Info PR (read-only reference) -->
<div class="row g-2 mb-4">
    <div class="col-12">
        <div class="alert alert-info py-2 mb-0 small">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Periksa semua informasi di bawah.</strong> Anda dapat mengedit field yang salah sebelum menyetujui.
            Jika ditolak, item akan kembali ke antrean petugas pembelian.
        </div>
    </div>
</div>

<!-- Referensi dari PR -->
<div class="card border-secondary mb-4">
    <div class="card-header bg-secondary text-white py-1 small fw-bold">
        <i class="fas fa-file-alt me-1"></i>REFERENSI DARI PURCHASE REQUEST
    </div>
    <div class="card-body py-2">
        <div class="row g-2 small">
            <div class="col-md-2"><span class="text-muted">No. PR</span><br><strong><?= $s['no_request'] ?? '-' ?></strong></div>
            <div class="col-md-3"><span class="text-muted">Pemesan</span><br><strong><?= strtoupper($s['nama_pemesan'] ?? '-') ?></strong></div>
            <div class="col-md-3"><span class="text-muted">Nama Barang (PR)</span><br><strong><?= $s['nama_barang_beli'] ?></strong></div>
            <div class="col-md-2"><span class="text-muted">Qty PR</span><br><strong><?= (float)($s['qty_pr']??0) ?> <?= $s['satuan_pr'] ?></strong></div>
            <div class="col-md-2"><span class="text-muted">Est. Harga</span><br><strong><?= number_format($s['harga_satuan_estimasi']??0) ?></strong></div>
        </div>
        <?php if (!empty($s['ket_pr'])): ?>
        <div class="mt-1 small"><span class="text-muted">Catatan PR:</span> <?= $s['ket_pr'] ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Form Editable -->
<div class="card border-success">
    <div class="card-header py-1 small fw-bold" style="background:#d1fae5;">
        <i class="fas fa-edit me-1 text-success"></i>DATA YANG DIINPUT PETUGAS — BISA DIEDIT
    </div>
    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-2">
                <label class="fw-bold small">TGL NOTA <span class="text-danger">*</span></label>
                <input type="text" id="modal_tgl_nota"
                       class="form-control form-control-sm text-center"
                       value="<?= $tgl_nota ?>" style="text-transform:none;">
            </div>

            <div class="col-md-4">
                <label class="fw-bold small">NAMA BARANG <span class="text-danger">*</span></label>
                <input type="text" id="modal_nama_barang"
                       class="form-control form-control-sm fw-bold"
                       value="<?= $s['nama_barang_beli'] ?>" readonly>
            </div>

            <div class="col-md-3">
                <label class="fw-bold small">TOKO / SUPPLIER <span class="text-danger">*</span></label>
                <input type="text" id="modal_supplier"
                       class="form-control form-control-sm"
                       value="<?= $s['supplier'] ?>">
            </div>

            <div class="col-md-3">
                <label class="fw-bold small">UNIT / MOBIL</label>
                <select id="modal_id_mobil" class="form-select form-select-sm">
                    <option value="0">- BUKAN KENDARAAN -</option>
                    <?php foreach ($mobil_list as $mob): ?>
                        <option value="<?= $mob['id_mobil'] ?>"
                            <?= ($s['id_mobil'] == $mob['id_mobil']) ? 'selected' : '' ?>>
                            <?= strtoupper($mob['plat_nomor']) ?>
                            <?= !empty($mob['jenis_kendaraan']) ? ' — '.strtoupper($mob['jenis_kendaraan']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="fw-bold small">QTY <span class="text-danger">*</span></label>
                <input type="number" id="modal_qty"
                       class="form-control form-control-sm text-center"
                       value="<?= (float)$s['qty'] ?>" min="0.01" step="0.01">
            </div>

            <div class="col-md-3">
                <label class="fw-bold small">HARGA SATUAN <span class="text-danger">*</span></label>
                <input type="number" id="modal_harga"
                       class="form-control form-control-sm text-end"
                       value="<?= (float)$s['harga'] ?>" min="0" step="1">
            </div>

            <div class="col-md-3">
                <label class="fw-bold small">ALOKASI STOK</label>
                <select id="modal_alokasi" class="form-select form-select-sm">
                    <option value="LANGSUNG PAKAI" <?= $s['alokasi_stok']=='LANGSUNG PAKAI'?'selected':'' ?>>LANGSUNG PAKAI</option>
                    <option value="MASUK STOK"     <?= $s['alokasi_stok']=='MASUK STOK'?'selected':'' ?>>MASUK STOK</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="fw-bold small">SUBTOTAL</label>
                <div class="form-control form-control-sm text-end fw-bold text-success bg-light" id="modal_subtotal">
                    <?= number_format($subtotal, 0, ',', '.') ?>
                </div>
            </div>

            <div class="col-md-12">
                <label class="fw-bold small">KETERANGAN <span class="text-danger">*</span></label>
                <input type="text" id="modal_keterangan"
                       class="form-control form-control-sm"
                       value="<?= $s['keterangan'] ?>">
            </div>

            <div class="col-md-12">
                <label class="fw-bold small text-danger">
                    <i class="fas fa-comment-alt me-1"></i>CATATAN VERIFIKASI
                    <span class="text-muted fw-normal">(wajib diisi jika menolak)</span>
                </label>
                <textarea id="modal_catatan" class="form-control form-control-sm" rows="2"
                          placeholder="Contoh: Harga tidak sesuai nota, supplier salah ketik, dll..."></textarea>
            </div>

        </div><!-- /row -->
    </div><!-- /card-body -->
</div>

<!-- Info Petugas -->
<div class="mt-3 p-2 bg-light rounded small text-muted">
    <i class="fas fa-user me-1"></i>Diinput oleh: <strong><?= $s['driver'] ?></strong>
    &nbsp;|&nbsp;
    <i class="fas fa-clock me-1"></i>Waktu input: <strong><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></strong>
</div>

<!-- Tombol Aksi -->
<div class="d-flex justify-content-end gap-2 mt-4">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="fas fa-times me-1"></i>TUTUP
    </button>
    <button type="button" id="btnTolak" data-id="<?= $id_staging ?>"
            class="btn btn-danger px-4 fw-bold">
        <i class="fas fa-times-circle me-1"></i>TOLAK
    </button>
    <button type="button" id="btnApprove" data-id="<?= $id_staging ?>"
            class="btn btn-success px-5 fw-bold shadow">
        <i class="fas fa-check-circle me-1"></i>SETUJUI & SIMPAN
    </button>
</div>