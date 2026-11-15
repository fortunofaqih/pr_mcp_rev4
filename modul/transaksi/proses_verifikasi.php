<?php
/**
 * proses_verifikasi.php
 * Dipanggil via AJAX POST dari verifikasi_pembelian.php
 * Aksi: APPROVE -> pindah ke tabel pembelian + update stok + status item TERBELI + update tipe_request
 * TOLAK   -> update staging DITOLAK + status item kembali PENDING
 *
 * REVISI: Khusus nomor request PRB, hanya manager dan administrator
 * yang dapat menjalankan APPROVE maupun TOLAK (validasi server-side).
 */
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Metode tidak valid.']); exit;
}
if (($_SESSION['status'] ?? '') != 'login') {
    echo json_encode(['status'=>'error','message'=>'Session habis.']); exit;
}

// ============================================================
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

// OTORISASI PEMBAYARAN PRB (TERPISAH DARI REALISASI PEMBELIAN)
// Mutasi stok dan is_dibeli TIDAK dilakukan di cabang ini.
// ============================================================
if (strtoupper(trim((string)($_POST['aksi'] ?? ''))) === 'PRB_VERIFIKASI') {
    try {
        $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
        if (!in_array($role, ['manager','administrator'], true)) {
            throw new Exception('Hanya Manager atau Administrator yang dapat memverifikasi pembayaran PRB.');
        }
        if (empty($_SESSION['csrf_prb_payment']) || !hash_equals($_SESSION['csrf_prb_payment'], (string)($_POST['csrf_prb_payment']??''))) {
            throw new Exception('Token keamanan tidak valid. Muat ulang halaman.');
        }
        $id_pr = (int)($_POST['id_request'] ?? 0);
        $jenis = strtoupper(trim((string)($_POST['jenis_bayar'] ?? '')));
        $nominal_raw = trim((string)($_POST['nominal'] ?? ''));
        if ($id_pr <= 0 || !in_array($jenis, ['DP','LUNAS'], true) ||
            !preg_match('/^\d+(?:\.\d{1,2})?$/', $nominal_raw)) {
            throw new Exception('Parameter jenis/nominal pembayaran tidak valid. Gunakan angka tanpa pemisah ribuan.');
        }
        $nominal_sen = (int)round((float)$nominal_raw * 100);
        if ($nominal_sen <= 0) throw new Exception('Nominal harus lebih dari nol.');
        mysqli_begin_transaction($koneksi);
        $q = mysqli_query($koneksi, "SELECT r.id_request, r.status_request, r.status_approval, r.kategori_pr,
                              r.no_request, r.void_by, r.approve1_by, r.approve2_by, r.approve3_by, b.*
                              FROM tr_request r
                              JOIN tr_prb_pembayaran b ON b.id_request=r.id_request
                              WHERE r.id_request=$id_pr FOR UPDATE");
        if (!$q || !($r = mysqli_fetch_assoc($q))) throw new Exception('Data verifikasi pembayaran PRB tidak ditemukan.');
        if ($r['kategori_pr'] !== 'BESAR' || stripos($r['no_request'], 'PRB') !== 0)
            throw new Exception('Fitur ini hanya untuk PR Besar.');
        if (in_array($r['status_request'], ['BATAL','SELESAI'], true) || !empty($r['void_by']))
            throw new Exception('PR sudah batal/selesai, tidak dapat disetujui untuk pembayaran.');
        if (!in_array($r['status_approval'], ['APPROVED 2','APPROVED'], true) ||
            empty($r['approve1_by']) || empty($r['approve2_by']))
            throw new Exception('Approval PR minimal dua manager harus selesai sebelum pembayaran dapat disetujui.');
        if (!prb_is_approver($r, $koneksi))
            throw new Exception('Hanya manager yang pernah menyetujui PRB ini (atau Administrator) dapat memverifikasi pembayaran.');
        if (!in_array($r['status_bayar'], ['MENUNGGU_VERIFIKASI','DP_TERBAYAR'], true))
            throw new Exception('Masih ada otorisasi pembayaran tertunda atau PRB sudah lunas.');
        $qpo = mysqli_query($koneksi, "SELECT id_supplier, status_po, grand_total FROM tr_purchase_order
                                      WHERE id_request=$id_pr ORDER BY id_po DESC LIMIT 1 FOR UPDATE");
        if (!$qpo || !($po=mysqli_fetch_assoc($qpo)) || (int)$po['id_supplier'] <= 0 || $po['status_po']==='BATAL')
            throw new Exception('Supplier/PO tidak valid untuk pembayaran.');
        // Nominal menggunakan snapshot grand total saat PR dibuat, tidak terima total dari browser.
        $total_sen = (int)round((float)$r['total_tagihan']*100);
        $paid_sen = (int)round((float)$r['total_terbayar']*100);
        $sisa_sen = $total_sen - $paid_sen;
        if ($sisa_sen <= 0) throw new Exception('Tagihan sudah lunas.');
        if ($jenis === 'DP' && ($nominal_sen >= $sisa_sen))
            throw new Exception('DP harus lebih kecil dari sisa tagihan. Pilih LUNAS untuk seluruh sisa.');
        if ($jenis === 'LUNAS' && $nominal_sen !== $sisa_sen)
            throw new Exception('Nominal LUNAS harus sama persis dengan sisa tagihan.');
        $jenis_db = $jenis === 'LUNAS' ? ($paid_sen > 0 ? 'PELUNASAN' : 'LUNAS') : 'DP';
        $aksi_log = 'VERIFIKASI_' . $jenis_db;
        $nominal_sql = number_format($nominal_sen/100,2,'.','');
        $actor = mysqli_real_escape_string($koneksi,(string)$_SESSION['username']);
        $catatan = mysqli_real_escape_string($koneksi,trim((string)($_POST['catatan'] ?? '')));
        $upd = "UPDATE tr_prb_pembayaran SET status_bayar='MENUNGGU_FINANCE',
                jenis_disetujui='$jenis_db', nominal_disetujui=$nominal_sql,
                verified_by='$actor', verified_at=NOW(), catatan_verifikasi='$catatan'
                WHERE id_request=$id_pr";
        if (!mysqli_query($koneksi, $upd)) throw new Exception(mysqli_error($koneksi));
        $log = "INSERT INTO tr_prb_pembayaran_log (id_request,aksi,nominal,catatan,actor)
                VALUES ($id_pr,'$aksi_log',$nominal_sql,'$catatan','$actor')";
        if (!mysqli_query($koneksi, $log)) throw new Exception(mysqli_error($koneksi));
        mysqli_commit($koneksi);
        echo json_encode(['status'=>'ok','aksi'=>'PRB_VERIFIKASI',
            'message'=>'Otorisasi pembayaran disetujui dan masuk antrean Finance.']);
    } catch (Throwable $e) {
        try { mysqli_rollback($koneksi); } catch (Throwable $ignored) {}
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

$id_staging = (int)($_POST['id_staging'] ?? 0);
$aksi       = strtoupper(trim($_POST['aksi'] ?? ''));
$username   = $_SESSION['username'] ?? 'ADMIN';

if ($id_staging <= 0 || !in_array($aksi, ['APPROVE','TOLAK'])) {
    echo json_encode(['status'=>'error','message'=>'Parameter tidak valid.']); exit;
}

// Ambil data staging
$q_stg = mysqli_query($koneksi, "SELECT * FROM pembelian_staging WHERE id_staging = $id_staging AND status_staging = 'MENUNGGU' LIMIT 1");
$stg   = $q_stg ? mysqli_fetch_assoc($q_stg) : null;

if (!$stg) {
    echo json_encode(['status'=>'error','message'=>'Data staging tidak ditemukan atau sudah diproses.']); exit;
}

// ============================================================
// REVISI HAK AKSES PR BESAR (PRB)
// Gunakan no_request dari database, bukan input dari browser.
// PRB-X-26-... -> PRB; PRI-... dan PR-... tidak terkena aturan ini.
// ============================================================
$no_request_akses = trim((string)($stg['no_request'] ?? ''));
if ($no_request_akses === '' && (int)($stg['id_request'] ?? 0) > 0) {
    $id_request_akses = (int)$stg['id_request'];
    $q_nomor = mysqli_query($koneksi, "SELECT no_request FROM tr_request WHERE id_request = $id_request_akses LIMIT 1");
    if ($q_nomor && ($r_nomor = mysqli_fetch_assoc($q_nomor))) {
        $no_request_akses = trim((string)$r_nomor['no_request']);
    }
}

// Fail closed jika nomor request tidak tersedia: jangan membiarkan
// request yang tak bisa dikategorikan melewati kontrol akses PRB.
if ($no_request_akses === '') {
    echo json_encode(['status'=>'error','message'=>'Nomor request tidak tersedia; verifikasi tidak dapat dilanjutkan.']); exit;
}

$is_prb = preg_match('/^PRB(?=[\-\/]|$)/i', $no_request_akses) === 1;
$role_login = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($is_prb && !in_array($role_login, ['manager', 'administrator'], true)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Akses ditolak. Verifikasi PR Besar (PRB) hanya dapat dilakukan oleh Manager atau Administrator.'
    ]);
    exit;
}
// ============================================================
// END REVISI HAK AKSES PRB
// ============================================================

$catatan    = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['catatan'] ?? '')));
$verified   = mysqli_real_escape_string($koneksi, $username);
$id_detail  = (int)$stg['id_request_detail'];
$id_request = (int)$stg['id_request'];

mysqli_begin_transaction($koneksi);

try {

    if ($aksi === 'APPROVE') {
        // Ambil field yang sudah diedit admin gudang dari POST
        $tgl_nota_raw  = $_POST['tgl_nota']    ?? '';
        $supplier      = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['supplier']     ?? $stg['supplier'])));
        $nama_barang   = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['nama_barang']  ?? $stg['nama_barang_beli'])));
        $qty           = (float)($_POST['qty']   ?? $stg['qty']);
        $harga         = (float)($_POST['harga'] ?? $stg['harga']);
        $alokasi       = mysqli_real_escape_string($koneksi, $_POST['alokasi']    ?? $stg['alokasi_stok']);
        $keterangan    = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? $stg['keterangan'])));
        $id_mobil_baru = (int)($_POST['id_mobil'] ?? $stg['id_mobil']);

        // Validasi minimal
        if ($qty <= 0)       throw new Exception('Qty harus lebih dari 0.');
        if ($harga <= 0)     throw new Exception('Harga harus diisi.');
        if (empty($supplier)) throw new Exception('Supplier wajib diisi.');

        // Konversi tanggal nota
        if (!empty($tgl_nota_raw)) {
            $parts = explode('-', $tgl_nota_raw);
            $tgl_beli_barang = (count($parts)===3 && strlen($parts[2])===4)
                ? $parts[2].'-'.$parts[1].'-'.$parts[0]
                : date('Y-m-d', strtotime($tgl_nota_raw));
        } else {
            $tgl_beli_barang = $stg['tgl_beli_barang'];
        }

        // Lookup plat_nomor jika id_mobil berubah
        $plat_nomor = $stg['plat_nomor'];
        if ($id_mobil_baru > 0) {
            $qp = mysqli_query($koneksi, "SELECT plat_nomor FROM master_mobil WHERE id_mobil = $id_mobil_baru LIMIT 1");
            $rp = mysqli_fetch_assoc($qp);
            if ($rp) $plat_nomor = mysqli_real_escape_string($koneksi, strtoupper($rp['plat_nomor']));
        } else {
            $plat_nomor = '-';
        }

        $id_req_val = ($id_request > 0) ? $id_request : 'NULL';
        $no_request = mysqli_real_escape_string($koneksi, $stg['no_request'] ?? '');
        $nama_pemesan = mysqli_real_escape_string($koneksi, $stg['nama_pemesan']);
        $driver       = mysqli_real_escape_string($koneksi, $stg['driver']);
        $kategori     = mysqli_real_escape_string($koneksi, $stg['kategori_beli']);
        $id_user_beli = (int)$stg['id_user_beli'];

        // 1. Insert ke tabel pembelian (Data Final Realisasi)
        $q_insert = "INSERT INTO pembelian
                     (id_request, id_request_detail, no_request, tgl_beli, tgl_beli_barang,
                      supplier, nama_barang_beli, qty, harga, kategori_beli,
                      alokasi_stok, nama_pemesan, driver, plat_nomor,
                      keterangan, id_user_beli, sumber_data)
                     VALUES
                     ($id_req_val, $id_detail, '$no_request', CURDATE(), '$tgl_beli_barang',
                      '$supplier', '$nama_barang', $qty, $harga, '$kategori',
                      '$alokasi', '$nama_pemesan', '$driver', '$plat_nomor',
                      '$keterangan', $id_user_beli, 'SISTEM')";

        if (!mysqli_query($koneksi, $q_insert)) {
            throw new Exception('Gagal insert pembelian: '.mysqli_error($koneksi));
        }
        $id_pembelian_baru = mysqli_insert_id($koneksi);

        // 2. Update stok jika MASUK STOK
        if ($alokasi === 'MASUK STOK' && $stg['id_barang'] > 0) {
            $id_barang = (int)$stg['id_barang'];
            if (!mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty WHERE id_barang = $id_barang")) {
                throw new Exception('Gagal update stok: '.mysqli_error($koneksi));
            }
            $ket_log = mysqli_real_escape_string($koneksi, "PEMBELIAN VERIFIED: $supplier (ID: $id_pembelian_baru)");
            mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input)
                                    VALUES ($id_barang, NOW(), 'MASUK', $qty, '$ket_log', '$verified')");
        }

        // 3. Update status item & tipe_request pada tr_request_detail (SINKRONISASI)
        // Mapping: 'MASUK STOK' -> 'STOK', 'LANGSUNG PAKAI' -> 'LANGSUNG'
        $tipe_request_baru = ($alokasi === 'MASUK STOK') ? 'STOK' : 'LANGSUNG';
       $q_update_detail = "UPDATE tr_request_detail SET 
                        status_item  = 'TERBELI',
                        is_dibeli    = 1,
                        tipe_request = '$tipe_request_baru',
                        keterangan   = '$keterangan',
                        id_mobil     = $id_mobil_baru
                    WHERE id_detail  = $id_detail";

        if (!mysqli_query($koneksi, $q_update_detail)) {
            throw new Exception('Gagal update detail request: '.mysqli_error($koneksi));
        }

        // 4. Cek sisa item PR -> update status header (PROSES/SELESAI)
        $sisa = 1;
        if ($id_request > 0) {
            $q_sisa = mysqli_query($koneksi, "SELECT id_detail FROM tr_request_detail
                                              WHERE id_request = $id_request
                                                AND status_item IN ('PENDING','APPROVED','MENUNGGU VERIFIKASI')");
            $sisa        = mysqli_num_rows($q_sisa);
            $status_baru = ($sisa === 0) ? 'SELESAI' : 'PROSES';
            mysqli_query($koneksi, "UPDATE tr_request SET status_request='$status_baru' WHERE id_request=$id_request");
        }

        // 5. Update staging -> DISETUJUI
        mysqli_query($koneksi, "UPDATE pembelian_staging SET
                                 status_staging='DISETUJUI',
                                 alokasi_stok='$alokasi',
                                 catatan_verifikasi='$catatan',
                                 verified_by='$verified',
                                 verified_at=NOW()
                                 WHERE id_staging=$id_staging");

        mysqli_commit($koneksi);
        echo json_encode(['status'=>'ok','aksi'=>'APPROVE','message'=>'Data disetujui. Stok & Tipe Request telah disinkronkan.','pr_selesai'=>($sisa===0)]);

    } else {
        // TOLAK: kembalikan status item ke PENDING agar bisa diedit petugas pembelian lagi

        // 1. Update status item -> PENDING
        if (!mysqli_query($koneksi, "UPDATE tr_request_detail SET status_item='PENDING' WHERE id_detail = $id_detail")) {
            throw new Exception('Gagal reset status item: '.mysqli_error($koneksi));
        }

        // 2. Cek status header PR
        if ($id_request > 0) {
            $q_sisa = mysqli_query($koneksi, "SELECT id_detail FROM tr_request_detail
                                              WHERE id_request = $id_request
                                                AND status_item IN ('MENUNGGU VERIFIKASI','TERBELI')");
            $masih_proses = mysqli_num_rows($q_sisa);
            if ($masih_proses === 0) {
                mysqli_query($koneksi, "UPDATE tr_request SET status_request='PENDING' WHERE id_request=$id_request");
            }
        }

        // 3. Update staging -> DITOLAK
        mysqli_query($koneksi, "UPDATE pembelian_staging SET
                                 status_staging='DITOLAK',
                                 catatan_verifikasi='$catatan',
                                 verified_by='$verified',
                                 verified_at=NOW()
                                 WHERE id_staging=$id_staging");

        mysqli_commit($koneksi);
        echo json_encode(['status'=>'ok','aksi'=>'TOLAK','message'=>'Data ditolak. Item kembali ke antrean petugas pembelian.']);
    }

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
exit;
