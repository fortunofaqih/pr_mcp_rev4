<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// ── Validasi parameter masuk ─────────────────────────────────
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location:data_pembelian.php?pesan=invalid");
    exit;
}

$id     = (int)$_GET['id'];
$alasan = trim($_GET['alasan'] ?? '');
$user   = $_SESSION['nama'] ?? 'System';
$now    = date('Y-m-d H:i:s');

// Validasi alasan tidak boleh kosong
if (empty($alasan)) {
    echo "<script>alert('Alasan retur tidak boleh kosong!'); window.history.back();</script>";
    exit;
}

// ── Ambil data pembelian ─────────────────────────────────────
// FIX: Tidak JOIN ke master_barang lewat nama (nama bisa beda case/spasi)
//      Gunakan id_barang langsung dari tabel pembelian jika ada,
//      atau JOIN tapi pakai LOWER/TRIM untuk toleransi
$query_beli = mysqli_query($koneksi,
    "SELECT p.*,
            m.id_barang   AS id_barang_master,
            m.stok_akhir  AS stok_di_master
     FROM pembelian p
     LEFT JOIN master_barang m
            ON LOWER(TRIM(p.nama_barang_beli)) = LOWER(TRIM(m.nama_barang))
     WHERE p.id_pembelian = '$id'
     LIMIT 1");

if (!$query_beli) {
    echo "<script>alert('Query error: " . mysqli_error($koneksi) . "'); window.location='data_pembelian.php';</script>";
    exit;
}

$data = mysqli_fetch_assoc($query_beli);

if (!$data) {
    echo "<script>alert('Data pembelian tidak ditemukan!'); window.location='data_pembelian.php';</script>";
    exit;
}

// ── Siapkan variabel ─────────────────────────────────────────
$id_barang     = $data['id_barang_master'] ?? null;
$nama_barang   = mysqli_real_escape_string($koneksi, $data['nama_barang_beli']);
$qty_retur     = (float)$data['qty'];
$alokasi       = $data['alokasi_stok'];
$no_pr         = $data['no_request'] ?? '';
$supplier      = mysqli_real_escape_string($koneksi, $data['supplier']);
$stok_saat_ini = (float)($data['stok_di_master'] ?? 0);
$alasan_esc    = mysqli_real_escape_string($koneksi, $alasan);
$user_esc      = mysqli_real_escape_string($koneksi, $user);

// FIX: Cari id_request_detail — coba dari kolom di pembelian dulu,
//      kalau tidak ada cari lewat no_request + nama_barang
$id_req_detail = $data['id_request_detail'] ?? null;

if (empty($id_req_detail) && !empty($no_pr)) {
    $no_pr_esc = mysqli_real_escape_string($koneksi, $no_pr);
    $cari = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT d.id_detail
         FROM tr_request_detail d
         JOIN tr_request r ON d.id_request = r.id_request
         WHERE r.no_request = '$no_pr_esc'
           AND LOWER(TRIM(d.nama_barang_manual)) = LOWER(TRIM('$nama_barang'))
         LIMIT 1"));
    if ($cari) {
        $id_req_detail = $cari['id_detail'];
    }
}

// ── Mulai transaksi ───────────────────────────────────────────
mysqli_begin_transaction($koneksi);

try {

    // A. Jika barang masuk stok — kurangi stok dan catat mutasi
    if ($alokasi == 'MASUK STOK') {

        if (empty($id_barang)) {
            throw new Exception("Barang '{$data['nama_barang_beli']}' tidak ditemukan di Master Barang. Hubungi Admin.");
        }

        if ($stok_saat_ini < $qty_retur) {
            throw new Exception("Gagal Retur! Stok di gudang tersisa {$stok_saat_ini}, tidak cukup untuk meretur {$qty_retur}.");
        }

        // Kurangi stok master
        $sql_stok = "UPDATE master_barang
                     SET stok_akhir = stok_akhir - $qty_retur
                     WHERE id_barang = '$id_barang'";
        if (!mysqli_query($koneksi, $sql_stok)) {
            throw new Exception("Gagal update stok: " . mysqli_error($koneksi));
        }

        // Catat mutasi keluar di kartu stok
        $ket_log  = mysqli_real_escape_string($koneksi, "RETUR KE TOKO ($supplier) - ALASAN: $alasan");
        $sql_log  = "INSERT INTO tr_stok_log
                        (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input)
                     VALUES
                        ('$id_barang', '$now', 'KELUAR', '$qty_retur', '$ket_log', '$user_esc')";
        if (!mysqli_query($koneksi, $sql_log)) {
            throw new Exception("Gagal catat stok log: " . mysqli_error($koneksi));
        }
    }

    // B. Catat ke log retur
    $sql_log_retur = "INSERT INTO log_retur
                        (tgl_retur, no_request, nama_barang_retur, qty_retur,
                         supplier, alokasi_sebelumnya, alasan_retur, eksekutor_retur)
                      VALUES
                        ('$now', '$no_pr', '$nama_barang', '$qty_retur',
                         '$supplier', '$alokasi', '$alasan_esc', '$user_esc')";
    if (!mysqli_query($koneksi, $sql_log_retur)) {
        throw new Exception("Gagal catat log retur: " . mysqli_error($koneksi));
    }

    // C. Kembalikan status item PR supaya bisa dibeli ulang (opsional)
    if (!empty($id_req_detail)) {
        $sql_pr = "UPDATE tr_request_detail
                   SET is_dibeli  = 0,
                       tgl_dibeli = NULL,
                       dibeli_oleh = NULL
                   WHERE id_detail = '$id_req_detail'";
        if (!mysqli_query($koneksi, $sql_pr)) {
            throw new Exception("Gagal reset status item PR: " . mysqli_error($koneksi));
        }

        // Jika PO terlanjur CLOSE, kembalikan ke OPEN
        $row_req = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT id_request FROM tr_request_detail WHERE id_detail = '$id_req_detail'"));
        if ($row_req) {
            $id_req = (int)$row_req['id_request'];
            mysqli_query($koneksi,
                "UPDATE tr_purchase_order
                 SET status_po = 'OPEN'
                 WHERE id_request = '$id_req' AND status_po = 'CLOSE'");
            mysqli_query($koneksi,
                "UPDATE tr_request
                 SET status_request = 'PROSES'
                 WHERE id_request = '$id_req' AND status_request = 'SELESAI'");
        }
    }

    // D. Hapus data pembelian
    $sql_hapus = "DELETE FROM pembelian WHERE id_pembelian = '$id'";
    if (!mysqli_query($koneksi, $sql_hapus)) {
        throw new Exception("Gagal hapus data pembelian: " . mysqli_error($koneksi));
    }

    mysqli_commit($koneksi);
    header("location:data_pembelian.php?pesan=retur_sukses");
    exit;

} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $pesan_error = urlencode($e->getMessage());
    header("location:data_pembelian.php?pesan=retur_gagal&error=$pesan_error");
    exit;
}
?>