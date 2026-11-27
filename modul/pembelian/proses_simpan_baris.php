<?php
/**
 * proses_simpan_baris.php — revisi keamanan antrean PR Kecil
 * - Menyimpan ke pembelian_staging saja, bukan tabel pembelian.
 * - Item yang ditolak dapat disimpan ulang setelah kembali PENDING.
 * - Mencegah staging MENUNGGU ganda melalui transaction + row lock.
 * - Mempertahankan format respons JSON yang digunakan pembelian.php.
 */
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
header('Content-Type: application/json; charset=utf-8');

function jawab(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jawab(['status' => 'error', 'message' => 'Metode tidak valid.']);
}
if (($_SESSION['status'] ?? '') !== 'login') {
    jawab(['status' => 'error', 'message' => 'Session habis, silakan login ulang.']);
}

$id_detail       = (int)($_POST['id_detail'] ?? 0);
$id_request      = (int)($_POST['id_request'] ?? 0);
$id_mobil        = (int)($_POST['id_mobil'] ?? 0);
$nama_pembeli    = strtoupper(trim((string)($_POST['nama_pembeli'] ?? '')));
$supplier        = strtoupper(trim((string)($_POST['supplier'] ?? '')));
$keterangan      = strtoupper(trim((string)($_POST['keterangan'] ?? '')));
$tgl_nota_raw    = trim((string)($_POST['tgl_nota'] ?? ''));
$qty             = (float)($_POST['qty'] ?? 0);
$harga           = (float)($_POST['harga'] ?? 0);
$id_user_beli    = (int)($_SESSION['id_user'] ?? 0);

if ($id_detail <= 0 || $id_request <= 0) jawab(['status'=>'error','message'=>'ID request/detail tidak valid.']);
if ($qty <= 0 || !is_finite($qty)) jawab(['status'=>'error','message'=>'Qty harus lebih dari 0.']);
if ($harga <= 0 || !is_finite($harga)) jawab(['status'=>'error','message'=>'Harga harus lebih dari 0.']);
if ($supplier === '') jawab(['status'=>'error','message'=>'Nama toko/supplier wajib diisi.']);
if ($nama_pembeli === '') jawab(['status'=>'error','message'=>'Nama staf pembeli wajib diisi.']);
if ($keterangan === '') jawab(['status'=>'error','message'=>'Keterangan wajib diisi.']);

if ($tgl_nota_raw === '') {
    $tgl_beli_barang = date('Y-m-d');
} else {
    $tanggal = DateTimeImmutable::createFromFormat('!d-m-Y', $tgl_nota_raw);
    if (!$tanggal || $tanggal->format('d-m-Y') !== $tgl_nota_raw) {
        jawab(['status'=>'error','message'=>'Tanggal nota harus berformat dd-mm-yyyy.']);
    }
    $tgl_beli_barang = $tanggal->format('Y-m-d');
}

try {
    mysqli_begin_transaction($koneksi);

    // Lock header lebih dahulu; status_request harus masih aktif.
    $q_header = mysqli_query($koneksi, "
        SELECT id_request, no_request, nama_pemesan, kategori_pr, status_request
        FROM tr_request
        WHERE id_request = $id_request
        FOR UPDATE
    ");
    $header = $q_header ? mysqli_fetch_assoc($q_header) : null;
    if (!$header) throw new Exception('PR tidak ditemukan.');
    if (!in_array($header['status_request'], ['PENDING', 'PROSES'], true)) {
        throw new Exception('PR sudah tidak aktif untuk pembelian. Muat ulang halaman.');
    }
    // PR Besar memiliki alur pembelian/otorisasi khusus; tidak diproses melalui form ini.
    if (($header['kategori_pr'] ?? '') === 'BESAR' || stripos(trim($header['no_request']), 'PRB') === 0) {
        throw new Exception('PR Besar mengikuti alur khusus, bukan simpan baris PR Kecil.');
    }

    // Lock item sebelum memutuskan boleh disimpan agar tidak terjadi double submit.
    $q_item = mysqli_query($koneksi, "
        SELECT id_detail, id_request, id_barang, id_mobil, jumlah, satuan,
               nama_barang_manual, kategori_barang, keterangan, status_item,
               is_dibeli, tipe_request
        FROM tr_request_detail
        WHERE id_detail = $id_detail AND id_request = $id_request
        FOR UPDATE
    ");
    $item = $q_item ? mysqli_fetch_assoc($q_item) : null;
    if (!$item) throw new Exception('Detail item tidak ditemukan pada PR tersebut.');
    if (!in_array($item['status_item'], ['PENDING', 'APPROVED'], true) || (int)$item['is_dibeli'] === 1) {
        throw new Exception('Item sudah dibeli, ditolak permanen, atau sedang menunggu verifikasi. Muat ulang halaman.');
    }

    $q_aktif = mysqli_query($koneksi, "
        SELECT id_staging
        FROM pembelian_staging
        WHERE id_request_detail = $id_detail AND status_staging = 'MENUNGGU'
        LIMIT 1
    ");
    if (!$q_aktif) throw new Exception('Gagal memeriksa staging: ' . mysqli_error($koneksi));
    if (mysqli_num_rows($q_aktif) > 0) {
        throw new Exception('Item masih memiliki staging yang menunggu verifikasi.');
    }

    // Pengaman tambahan: tidak membuat ulang item yang sudah masuk buku realisasi.
    $q_final = mysqli_query($koneksi, "
        SELECT id_pembelian FROM pembelian
        WHERE id_request_detail = $id_detail LIMIT 1
    ");
    if (!$q_final) throw new Exception('Gagal memeriksa pembelian: ' . mysqli_error($koneksi));
    if (mysqli_num_rows($q_final) > 0) {
        throw new Exception('Item sudah memiliki pembelian final; tidak boleh diinput ulang.');
    }

    // Identitas item berasal dari database, bukan input tersembunyi browser.
    $id_barang = (int)($item['id_barang'] ?? 0);
    $nama_barang = trim((string)($item['nama_barang_manual'] ?? ''));
    if ($nama_barang === '' && $id_barang > 0) {
        $qb = mysqli_query($koneksi, "SELECT nama_barang FROM master_barang WHERE id_barang=$id_barang LIMIT 1");
        if ($qb && ($rb = mysqli_fetch_assoc($qb))) $nama_barang = (string)$rb['nama_barang'];
    }
    if ($nama_barang === '') throw new Exception('Nama barang PR tidak tersedia.');
    $kategori_pr = strtoupper(trim((string)($header['kategori_pr'] ?? 'KECIL')));
    $nama_pemesan = strtoupper(trim((string)($header['nama_pemesan'] ?? '')));
    $kategori_barang = strtoupper(trim((string)($item['kategori_barang'] ?? '-')));
    $tipe_request = strtoupper(trim((string)($item['tipe_request'] ?? 'LANGSUNG')));
    $alokasi_dari_db = ($tipe_request === 'STOK') ? 'MASUK STOK' : 'LANGSUNG PAKAI';
    $no_request = (string)$header['no_request'];

    $plat_nomor = '-';
    if ($id_mobil > 0) {
        $qp = mysqli_query($koneksi, "SELECT plat_nomor FROM master_mobil WHERE id_mobil=$id_mobil LIMIT 1");
        if (!$qp || !($rp = mysqli_fetch_assoc($qp))) throw new Exception('Mobil tidak ditemukan.');
        $plat_nomor = strtoupper((string)$rp['plat_nomor']);
    }

    $id_brg_val = $id_barang > 0 ? (string)$id_barang : 'NULL';
    $id_mob_val = $id_mobil > 0 ? (string)$id_mobil : 'NULL';
    $esc = static function ($v) use ($koneksi) { return mysqli_real_escape_string($koneksi, (string)$v); };
    $q_insert = "INSERT INTO pembelian_staging
        (id_request,id_request_detail,no_request,tgl_beli,tgl_beli_barang,
         supplier,nama_barang_beli,id_barang,id_mobil,qty,harga,
         kategori_beli,alokasi_stok,nama_pemesan,driver,plat_nomor,
         keterangan,id_user_beli,status_staging)
        VALUES
        ($id_request,$id_detail,'".$esc($no_request)."',CURDATE(),'".$esc($tgl_beli_barang)."',
         '".$esc($supplier)."','".$esc(strtoupper($nama_barang))."',$id_brg_val,$id_mob_val,$qty,$harga,
         '".$esc($kategori_pr)."','".$esc($alokasi_dari_db)."','".$esc($nama_pemesan)."','".$esc($nama_pembeli)."','".$esc($plat_nomor)."',
         '".$esc($keterangan)."',$id_user_beli,'MENUNGGU')";
    if (!mysqli_query($koneksi, $q_insert)) {
        throw new Exception('Gagal simpan staging: ' . mysqli_error($koneksi));
    }

    if (!mysqli_query($koneksi, "
        UPDATE tr_request_detail
        SET status_item='MENUNGGU VERIFIKASI', jumlah=$qty
        WHERE id_detail=$id_detail AND id_request=$id_request
    ")) {
        throw new Exception('Gagal update item: '.mysqli_error($koneksi));
    }

    if (!mysqli_query($koneksi, "
        UPDATE tr_request SET status_request='PROSES'
        WHERE id_request=$id_request AND status_request='PENDING'
    ")) {
        throw new Exception('Gagal update status PR: '.mysqli_error($koneksi));
    }

    // Hitung status sebelum COMMIT, supaya hasil konsisten dengan simpan.
    $q_rekap = mysqli_query($koneksi, "
        SELECT
          SUM(CASE WHEN status_item NOT IN ('TERBELI','MENUNGGU VERIFIKASI','REJECTED') THEN 1 ELSE 0 END) AS sisa_pending,
          SUM(CASE WHEN status_item = 'MENUNGGU VERIFIKASI' THEN 1 ELSE 0 END) AS jml_menunggu,
          SUM(CASE WHEN status_item NOT IN ('TERBELI','REJECTED') THEN 1 ELSE 0 END) AS jml_belum
        FROM tr_request_detail WHERE id_request=$id_request
    ");
    if (!$q_rekap) throw new Exception('Gagal menghitung antrean: '.mysqli_error($koneksi));
    $rekap = mysqli_fetch_assoc($q_rekap);
    $sisa_pending = (int)($rekap['sisa_pending'] ?? 0);
    $jml_menunggu = (int)($rekap['jml_menunggu'] ?? 0);
    $pr_selesai = ((int)($rekap['jml_belum'] ?? 0) === 0);

    mysqli_commit($koneksi);
    $subtotal = $qty * $harga;
    jawab([
        'status' => 'ok',
        'message' => 'Tersimpan! Menunggu verifikasi admin gudang.',
        'id_detail' => $id_detail,
        'subtotal' => $subtotal,
        'subtotal_fmt' => 'Rp '.number_format($subtotal, 0, ',', '.'),
        'plat_nomor' => $plat_nomor,
        'alokasi' => $alokasi_dari_db,
        'kategori_beli' => $kategori_pr,
        'kategori_barang' => $kategori_barang,
        'pr_selesai' => $pr_selesai,
        'semua_diinput' => ($sisa_pending === 0),
        'item_menunggu' => $jml_menunggu,
    ]);
} catch (Throwable $e) {
    try { mysqli_rollback($koneksi); } catch (Throwable $ignored) {}
    jawab(['status'=>'error','message'=>$e->getMessage()]);
}
