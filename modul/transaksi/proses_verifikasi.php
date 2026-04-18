<?php
/**
 * proses_verifikasi.php
 * Dipanggil via AJAX POST dari verifikasi_pembelian.php
 * Aksi: APPROVE -> pindah ke tabel pembelian + update stok + status item TERBELI + update tipe_request
 * TOLAK   -> update staging DITOLAK + status item kembali PENDING
 */
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status'=>'error','message'=>'Metode tidak valid.']); exit;
}
if ($_SESSION['status'] != 'login') {
    echo json_encode(['status'=>'error','message'=>'Session habis.']); exit;
}

$id_staging = (int)($_POST['id_staging'] ?? 0);
$aksi       = strtoupper(trim($_POST['aksi'] ?? ''));
$username   = $_SESSION['username'] ?? 'ADMIN';

if ($id_staging <= 0 || !in_array($aksi, ['APPROVE','TOLAK'])) {
    echo json_encode(['status'=>'error','message'=>'Parameter tidak valid.']); exit;
}

// Ambil data staging
$q_stg = mysqli_query($koneksi, "SELECT * FROM pembelian_staging WHERE id_staging = $id_staging AND status_staging = 'MENUNGGU' LIMIT 1");
$stg   = mysqli_fetch_assoc($q_stg);

if (!$stg) {
    echo json_encode(['status'=>'error','message'=>'Data staging tidak ditemukan atau sudah diproses.']); exit;
}

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
                                tipe_request = '$tipe_request_baru'
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