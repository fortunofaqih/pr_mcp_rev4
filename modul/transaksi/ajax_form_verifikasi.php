<?php
/**
 * ajax_form_verifikasi.php
 * Dipanggil via AJAX GET → return HTML form verifikasi untuk 1 item staging
 * REVISI: Mengubah inputan Satuan menjadi Dropdown dari master_satuan dengan default Satuan PR.
 * REVISI USD: Menampilkan prefix USD pada harga/subtotal jika keterangan mengandung "USD".
 */
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Identitas manager login dicocokkan dengan kolom approve*_by (username atau nama lengkap).
function prb_is_approver($row, $koneksi) {
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    if ($role === 'administrator') return true; // hak override administrator existing
    if ($role !== 'manager') return false;
    $username = trim((string)($_SESSION['username'] ?? ''));
    if ($username === '') return false;
    $identitas = [strtoupper($username)];
    if (!empty($_SESSION['nama'])) $identitas[] = strtoupper(trim((string)$_SESSION['nama']));
    $userEsc = mysqli_real_escape_string($koneksi, $username);
    $qu = mysqli_query($koneksi, "SELECT nama_lengkap FROM users WHERE username='$userEsc' AND role='manager' AND status_aktif='AKTIF' LIMIT 1");
    if ($qu && ($u=mysqli_fetch_assoc($qu)) && !empty($u['nama_lengkap'])) {
        $identitas[] = strtoupper(trim($u['nama_lengkap']));
    }
    foreach (['approve1_by','approve2_by','approve3_by'] as $field) {
        if (!empty($row[$field]) && in_array(strtoupper(trim((string)$row[$field])), $identitas, true)) return true;
    }
    return false;
}

// Form otorisasi pembayaran PRB: dipanggil dengan mode=prb_pembayaran&id_request=...
if (($_GET['mode'] ?? '') === 'prb_pembayaran') {
    $id_pr = (int)($_GET['id_request'] ?? 0);
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if (($_SESSION['status'] ?? '') !== 'login' ||
        !in_array($role, ['manager','administrator'], true)) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Hanya Manager atau Administrator yang dapat memverifikasi pembayaran PRB.</div>';
        exit;
    }
    $qpr = mysqli_query($koneksi, "SELECT r.no_request, r.status_approval, r.status_request,
              r.approve1_by, r.approve1_at, r.approve2_by, r.approve2_at,
              r.approve3_by, r.approve3_at,
              r.file_penawaran, r.keterangan, CASE WHEN UPPER(COALESCE(r.keterangan,'')) LIKE '%USD%'
                OR EXISTS (SELECT 1 FROM tr_request_detail rd_usd
                           WHERE rd_usd.id_request = r.id_request
                             AND UPPER(COALESCE(rd_usd.keterangan,'')) LIKE '%USD%')
           THEN 'USD' ELSE 'Rp' END AS mata_uang_prb, b.*, po.no_po, po.id_supplier,
              ms.nama_supplier
              FROM tr_request r JOIN tr_prb_pembayaran b ON b.id_request=r.id_request
              LEFT JOIN tr_purchase_order po ON po.id_po=(SELECT MAX(id_po) FROM tr_purchase_order WHERE id_request=r.id_request)
              LEFT JOIN master_supplier ms ON ms.id_supplier=po.id_supplier
              WHERE r.id_request=$id_pr AND r.kategori_pr='BESAR' LIMIT 1");
    $d = $qpr ? mysqli_fetch_assoc($qpr) : null;
    if (!$d || $d['status_request']==='BATAL' || !in_array($d['status_approval'],['APPROVED 2','APPROVED'],true) ||
        empty($d['approve1_by']) || empty($d['approve2_by']) ||
        !in_array($d['status_bayar'], ['MENUNGGU_VERIFIKASI','DP_TERBAYAR'], true)) {
        echo '<div class="alert alert-warning">Belum bisa verifikasi: approval belum lengkap, pembayaran masih diproses, atau PR telah selesai/batal.</div>';
        exit;
    }
    if (!prb_is_approver($d,$koneksi)) {
        http_response_code(403);
        echo '<div class="alert alert-danger">Verifikasi pembayaran hanya untuk manager yang telah menyetujui PRB ini (atau Administrator).</div>'; exit;
    }
    $sisa = round((float)$d['total_tagihan']-(float)$d['total_terbayar'], 2);
    $mata_uang_prb = $d['mata_uang_prb'] === 'USD' ? 'USD' : 'Rp';
    if ($sisa<=0 || empty($d['id_supplier'])) {
        echo '<div class="alert alert-warning">Sisa tagihan nol atau supplier PO belum ditentukan.</div>';
        exit;
    }
    $h = static function($v){ return htmlspecialchars((string)($v??''), ENT_QUOTES, 'UTF-8'); };
    ?>
    <input type="hidden" id="csrf_prb_payment" value="<?= htmlspecialchars((string)($_SESSION['csrf_prb_payment']??''),ENT_QUOTES,'UTF-8') ?>">
    <div class="alert alert-info small">Verifikasi ini adalah <strong>otorisasi pembayaran</strong>. Pembayaran boleh dilakukan Finance jika sudah diverifikasi oleh manager.</div>
    <div class="row g-3 mb-3 small">
      <div class="col-md-6"><strong>No. PR:</strong> <?= $h($d['no_request']) ?></div>
      <div class="col-md-6"><strong>Supplier:</strong> <?= $h($d['nama_supplier']) ?></div>
      <div class="col-12"><strong>Approval 1:</strong> <?= $h($d['approve1_by']) ?><?= $d['approve1_at'] ? ' · '.date('d/m/Y H:i',strtotime($d['approve1_at'])) : '' ?></div>
      <div class="col-12"><strong>Approval 2:</strong> <?= $h($d['approve2_by']) ?><?= $d['approve2_at'] ? ' · '.date('d/m/Y H:i',strtotime($d['approve2_at'])) : '' ?></div>
      <?php if (!empty($d['approve3_by'])): ?><div class="col-12"><strong>Approval 3:</strong> <?= $h($d['approve3_by']) ?></div><?php endif; ?>
      <div class="col-md-6"><strong>Total Tagihan:</strong> <?= $mata_uang_prb ?> <?=  number_format((float)$d['total_tagihan'],2,',','.') ?></div>
      <div class="col-md-6"><strong>Sudah dibayar:</strong> <?= $mata_uang_prb ?> <?=  number_format((float)$d['total_terbayar'],2,',','.') ?></div>
      <div class="col-md-12"><strong>Sisa Tagihan:</strong> <span class="text-danger fw-bold"><?= $mata_uang_prb ?> <?=  number_format($sisa,2,',','.') ?></span></div>
      <?php if (!empty($d['file_penawaran'])): ?>
        <div class="col-12"><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="../../download_penawaran.php?file=<?= urlencode($d['file_penawaran']) ?>&amp;id_request=<?= $id_pr ?>"><i class="fas fa-file-pdf me-1"></i>Buka PDF Penawaran</a></div>
      <?php endif; ?>
    </div>
    <div class="row g-3">
      <div class="col-md-4"><label class="fw-bold">Jenis Pembayaran </label>
        <select id="prb_jenis" class="form-select"><option value="DP">DOWN PAYMENT (DP)</option><option value="LUNAS">PELUNASAN</option></select>
      </div>
      <div class="col-md-4"><label class="fw-bold">Nominal Disetujui (<?= $mata_uang_prb ?>)</label>
        <input id="prb_nominal" data-currency="<?= $mata_uang_prb ?>" type="number" class="form-control" min="0.01" step="0.01" max="<?= $sisa ?>" value="" placeholder="Isi nominal tanpa pemisah ribuan">
      </div>
      <div class="col-md-4"><label class="fw-bold">Sisa Tagihan (<?= $mata_uang_prb ?>)</label><input class="form-control" value="<?= $sisa ?>" readonly></div>
        <div class="col-md-12">
                <label class="fw-bold small">KETERANGAN <span class="text-danger">*</span></label>
                <input type="text" id="modal_keterangan"
                       class="form-control form-control-sm"
                       value="<?= $h($d['keterangan']) ?>">
            </div>
      <div class="col-12"><label class="fw-bold">Catatan Manager</label><textarea id="prb_catatan" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="text-end mt-3"><button type="button" class="btn btn-success fw-bold" onclick="simpanOtorisasiPRB(<?= $id_pr ?>)">Setujui untuk Finance</button></div>
    <?php
    exit;
}

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

// ── REVISI HAK AKSES PRB (TAMPILAN FORM) ───────────────────
// Backend proses_verifikasi.php harus tetap melakukan validasi role
// untuk APPROVE/TOLAK; pemeriksaan di sini mencegah form PRB dimuat
// langsung melalui URL AJAX oleh role yang tidak berwenang.
if (($_SESSION['status'] ?? '') !== 'login') {
    http_response_code(401);
    echo '<div class="alert alert-danger">Sesi login tidak valid. Silakan login kembali.</div>';
    exit;
}

$role_login = strtolower(trim((string)($_SESSION['role'] ?? '')));
// Gunakan nomor request dari tabel staging, konsisten dengan validasi backend.
$no_request_akses = trim((string)($s['no_request'] ?? ''));
$is_prb = preg_match('/^PRB(?=[\-\/]|$)/i', $no_request_akses) === 1;

if ($is_prb && !in_array($role_login, ['manager', 'administrator'], true)) {
    http_response_code(403);
    echo '<div class="alert alert-warning mb-0">'
       . '<i class="fas fa-lock me-1"></i>'
       . 'Akses ditolak. Form verifikasi PR Besar (PRB) hanya dapat dibuka oleh Manager atau Administrator.'
       . '</div>';
    exit;
}
// ── END REVISI HAK AKSES PRB ───────────────────────────────

$subtotal   = $s['qty'] * $s['harga'];
$tgl_nota   = date('d-m-Y', strtotime($s['tgl_beli_barang']));

// ── REVISI USD: helper & deteksi mata uang ─────────────────
function is_usd($keterangan) {
    return stripos((string)$keterangan, 'USD') !== false;
}
function format_uang($nilai, $keterangan) {
    $prefix = is_usd($keterangan) ? 'USD ' : 'Rp ';
    return $prefix . number_format($nilai, 0, ',', '.');
}
$ket_item    = $s['keterangan'] ?? '';
$is_usd_item = is_usd($ket_item);
// ── END REVISI USD ──────────────────────────────────────────

// Daftar mobil untuk dropdown
$q_mob   = mysqli_query($koneksi, "SELECT id_mobil, plat_nomor, jenis_kendaraan FROM master_mobil WHERE status_aktif='AKTIF' ORDER BY plat_nomor ASC");
$mobil_list = [];
while ($m = mysqli_fetch_assoc($q_mob)) $mobil_list[] = $m;

// 1. QUERY TAMBAHAN: Ambil daftar satuan dari master_satuan
$q_sat = mysqli_query($koneksi, "SELECT nama_satuan FROM master_satuan ORDER BY nama_satuan ASC");
$satuan_list = [];
while ($sat = mysqli_fetch_assoc($q_sat)) {
    $satuan_list[] = $sat['nama_satuan'];
}

// Tentukan satuan yang saat ini terpilih/dibawa oleh sistem
$satuan_terpilih = strtoupper($s['satuan_barang_beli'] ?? $s['satuan_pr'] ?? '');
?>

<div class="row g-2 mb-4">
    <div class="col-12">
        <div class="alert alert-info py-2 mb-0 small">
            <i class="fas fa-info-circle me-1"></i>
            <strong>Periksa semua informasi di bawah.</strong> Anda dapat mengedit field yang salah (termasuk Qty, Harga, dan Dropdown Satuan) sebelum menyetujui.
        </div>
    </div>
</div>

<div class="card border-secondary mb-4">
    <div class="card-header bg-secondary text-white py-1 small fw-bold">
        <i class="fas fa-file-alt me-1"></i>REFERENSI DARI PURCHASE REQUEST
    </div>
    <div class="card-body py-2">
        <div class="row g-2 small">
            <div class="col-md-2"><span class="text-muted">No. PR</span><br><strong><?= $s['no_request'] ?? '-' ?></strong></div>
            <div class="col-md-3"><span class="text-muted">Pemesan</span><br><strong><?= strtoupper($s['nama_pemesan'] ?? '-') ?></strong></div>
            <div class="col-md-3"><span class="text-muted">Nama Barang (PR)</span><br><strong><?= $s['nama_barang_beli'] ?></strong></div>
            <div class="col-md-1"><span class="text-muted">Qty PR</span><br><strong><?= (float)($s['qty_pr']??0) ?></strong></div>
            <div class="col-md-1"><span class="text-muted">Satuan PR</span><br><strong><?= strtoupper($s['satuan_pr'] ?? '-') ?></strong></div>
            <div class="col-md-2"><span class="text-muted">Est. Harga</span><br><strong><?= number_format($s['harga_satuan_estimasi']??0) ?></strong></div>
        </div>
        <?php if (!empty($s['ket_pr'])): ?>
        <div class="mt-1 small"><span class="text-muted">Catatan PR:</span> <?= $s['ket_pr'] ?></div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-success">
    <div class="card-header py-1 small fw-bold" style="background:#d1fae5;">
        <i class="fas fa-edit me-1 text-success"></i>DATA YANG DIINPUT PETUGAS — BISA DIEDIT
        <?php /* ── REVISI USD: badge mata uang di header card ── */ ?>
        <?php if ($is_usd_item): ?>
            <span class="badge bg-primary ms-2">MATA UANG: USD</span>
        <?php endif; ?>
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

            <div class="col-md-2">
                <label class="fw-bold small">SATUAN <span class="text-danger">*</span></label>
                <select id="modal_satuan" class="form-select form-select-sm fw-bold text-center" readonly style="text-transform:uppercase;">
                    <?php 
                    // Jika satuan bawaan PR/Staging ternyata tidak ada di master_satuan, tetep munculkan sebagai opsi teratas
                    if (!empty($satuan_terpilih) && !in_array($satuan_terpilih, $satuan_list)): 
                    ?>
                        <option value="<?= $satuan_terpilih ?>" selected><?= $satuan_terpilih ?></option>
                    <?php endif; ?>

                    <?php foreach ($satuan_list as $sat_nama): 
                        $sat_nama_up = strtoupper($sat_nama);
                        $selected = ($sat_nama_up == $satuan_terpilih) ? 'selected' : '';
                    ?>
                        <option value="<?= $sat_nama_up ?>" <?= $selected ?>><?= $sat_nama_up ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php /* ── REVISI USD: label HARGA SATUAN + badge mata uang ── */ ?>
            <div class="col-md-2">
                <label class="fw-bold small">
                    HARGA SATUAN
                    <span class="badge <?= $is_usd_item ? 'bg-primary' : 'bg-secondary' ?> ms-1">
                        <?= $is_usd_item ? 'USD' : 'Rp' ?>
                    </span>
                    <span class="text-danger">*</span>
                </label>
                <input type="number" id="modal_harga"
                       class="form-control form-control-sm text-end"
                       value="<?= (float)$s['harga'] ?>" min="0" step="1">
            </div>

            <div class="col-md-2">
                <label class="fw-bold small">ALOKASI STOK</label>
                <select id="modal_alokasi" class="form-select form-select-sm">
                    <option value="LANGSUNG PAKAI" <?= $s['alokasi_stok']=='LANGSUNG PAKAI'?'selected':'' ?>>LANGSUNG PAKAI</option>
                    <option value="MASUK STOK"     <?= $s['alokasi_stok']=='MASUK STOK'?'selected':'' ?>>MASUK STOK</option>
                </select>
            </div>

            <?php /* ── REVISI USD: subtotal pakai format_uang + data-currency ── */ ?>
            <div class="col-md-4">
                <label class="fw-bold small">SUBTOTAL</label>
                <div class="form-control form-control-sm text-end fw-bold text-success bg-light" id="modal_subtotal"
                     data-currency="<?= $is_usd_item ? 'USD' : 'Rp' ?>">
                    <?= format_uang($subtotal, $ket_item) ?>
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

        </div></div></div>

<div class="mt-3 p-2 bg-light rounded small text-muted">
    <i class="fas fa-user me-1"></i>Diinput oleh: <strong><?= $s['driver'] ?></strong>
    &nbsp;|&nbsp;
    <i class="fas fa-clock me-1"></i>Waktu input: <strong><?= date('d/m/Y H:i', strtotime($s['created_at'])) ?></strong>
</div>

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