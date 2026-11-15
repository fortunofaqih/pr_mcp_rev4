<?php
/** Mencatat pembayaran PRB. Saat lunas, catat item ke Buku Realisasi tanpa mutasi stok. */
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

function kembaliFinance($msg) {
    header('Location: finance_prb_pembayaran.php?pesan=' . urlencode($msg));
    exit;
}

if (($_SESSION['status'] ?? '') !== 'login' || !in_array($_SESSION['role'] ?? '', ['finance', 'administrator'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('POST wajib.');
}
$token = $_POST['csrf_prb_finance'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_prb_finance']) || !hash_equals($_SESSION['csrf_prb_finance'], $token)) {
    kembaliFinance('Token keamanan tidak valid. Refresh halaman.');
}
$id = filter_var($_POST['id_request'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$referensi = $_POST['referensi_bayar'] ?? '';
$catatan = $_POST['catatan'] ?? '';
if (!$id || !is_string($referensi) || trim($referensi) === '' || strlen(trim($referensi)) > 150 || !is_string($catatan)) {
    kembaliFinance('Cash / Transfer wajib diisi dengan referensi yang valid.');
}
$referensi = trim($referensi);
$catatan = trim($catatan);
$username = trim((string)($_SESSION['username'] ?? ''));
if ($username === '') {
    kembaliFinance('Username Finance tidak tersedia. Silakan login kembali.');
}

// Semua SQL variabel numerik divalidasi, sedangkan teks diikat ke prepared statement.
function prbFinanceQuery($db, $sql) {
    $result = mysqli_query($db, $sql);
    if ($result === false) {
        throw new RuntimeException(mysqli_error($db));
    }
    return $result;
}

$transactionOpen = false;
try {
    if (!mysqli_begin_transaction($koneksi)) {
        throw new RuntimeException('Gagal memulai transaksi pembayaran.');
    }
    $transactionOpen = true;
    $q = prbFinanceQuery($koneksi, "SELECT b.*, r.status_request, r.void_by, r.status_approval, r.kategori_pr, r.nama_pembeli
        FROM tr_prb_pembayaran b JOIN tr_request r ON r.id_request=b.id_request
        WHERE b.id_request=$id FOR UPDATE");
    $b = mysqli_fetch_assoc($q);
    if (!$b) {
        throw new DomainException('Tagihan tidak ditemukan.');
    }
    if ($b['kategori_pr'] !== 'BESAR' || $b['status_approval'] !== 'APPROVED' ||
        in_array($b['status_request'], ['BATAL', 'SELESAI'], true) || !empty($b['void_by'])) {
        throw new DomainException('PR tidak valid untuk pembayaran.');
    }
    if ($b['status_bayar'] !== 'MENUNGGU_FINANCE') {
        throw new DomainException('Pembayaran sudah diproses atau belum disetujui manager.');
    }
    $jenis = $b['jenis_disetujui'];
    if (!in_array($jenis, ['DP', 'LUNAS', 'PELUNASAN'], true)) {
        throw new DomainException('Jenis pembayaran tidak valid.');
    }

    $total = (int)round((float)$b['total_tagihan'] * 100);
    $paid = (int)round((float)$b['total_terbayar'] * 100);
    $jumlah = (int)round((float)$b['nominal_disetujui'] * 100);
    if ($jumlah <= 0 || $paid < 0 || $paid + $jumlah > $total) {
        throw new DomainException('Nominal pembayaran tidak valid.');
    }
    $lunas = ($paid + $jumlah === $total);
    if ($jenis === 'DP' && $lunas) {
        throw new DomainException('DP tidak boleh sekaligus lunas.');
    }
    if ($jenis !== 'DP' && !$lunas) {
        throw new DomainException('Nominal pelunasan tidak mencukupi.');
    }

    $jumlahBarisBaru = 0;
    $idUserBeli = null;
    if ($lunas) {
        // PO yang sama dengan yang ditampilkan pada halaman Finance (id_po terbaru).
        $po = mysqli_fetch_assoc(prbFinanceQuery($koneksi, "SELECT po.id_po, po.grand_total, po.status_po, ms.nama_supplier
            FROM tr_purchase_order po
            LEFT JOIN master_supplier ms ON ms.id_supplier=po.id_supplier
            WHERE po.id_request=$id ORDER BY po.id_po DESC LIMIT 1 FOR UPDATE"));
        if (!$po || $po['status_po'] === 'BATAL' || trim((string)$po['nama_supplier']) === '') {
            throw new DomainException('PO aktif atau supplier tidak ditemukan untuk Buku Realisasi.');
        }
        if ((int)round((float)$po['grand_total'] * 100) !== $total) {
            throw new DomainException('Grand total PO berbeda dari tagihan yang diotorisasi. Periksa PO dan tagihan sebelum mencatat pelunasan.');
        }
        $idPo = (int)$po['id_po'];

        // Laporan membaca users melalui id_user_beli, bukan teks pada driver.
        // PRB lama bisa menyimpan nama lengkap atau username. Jangan menebak jika ambigu.
        $namaPembeli = trim((string)($b['nama_pembeli'] ?? ''));
        if ($namaPembeli !== '') {
            $petugas = mysqli_prepare($koneksi, "SELECT id_user FROM users
                WHERE id_user > 0
                  AND (role='bagian_pembelian' OR bagian='Pembelian')
                  AND (UPPER(TRIM(nama_lengkap))=UPPER(?) OR UPPER(TRIM(username))=UPPER(?))
                LIMIT 2");
            if (!$petugas || !mysqli_stmt_bind_param($petugas, 'ss', $namaPembeli, $namaPembeli) ||
                !mysqli_stmt_execute($petugas) || !mysqli_stmt_store_result($petugas)) {
                throw new RuntimeException('Gagal mencari ID petugas pembelian.');
            }
            if (mysqli_stmt_num_rows($petugas) === 1) {
                if (!mysqli_stmt_bind_result($petugas, $idPetugas) || mysqli_stmt_fetch($petugas) !== true) {
                    throw new RuntimeException('Gagal membaca ID petugas pembelian.');
                }
                $idUserBeli = (int)$idPetugas;
            }
            mysqli_stmt_close($petugas);
        }
        $idUserBeliSql = $idUserBeli === null ? 'NULL' : (string)$idUserBeli;

        $detail = mysqli_fetch_assoc(prbFinanceQuery($koneksi, "SELECT COUNT(*) AS jumlah,
            SUM(CASE WHEN d.jumlah IS NULL OR d.jumlah <= 0 OR d.harga_satuan_estimasi IS NULL
                OR d.harga_satuan_estimasi < 0
                OR COALESCE(NULLIF(TRIM(d.nama_barang_manual), ''), NULLIF(TRIM(mb.nama_barang), '')) IS NULL
                THEN 1 ELSE 0 END) AS tidak_valid
            FROM tr_request_detail d LEFT JOIN master_barang mb ON mb.id_barang=d.id_barang
            WHERE d.id_request=$id AND COALESCE(d.status_item, '') <> 'REJECTED'"));
        if ((int)$detail['jumlah'] === 0 || (int)$detail['tidak_valid'] > 0) {
            throw new DomainException('Detail PRB kosong atau nama barang, qty, atau harga belum valid.');
        }

        // Baris lama tanpa tautan detail tidak bisa dipasangkan otomatis tanpa risiko duplikat.
        $lama = prbFinanceQuery($koneksi, "SELECT p.id_pembelian
            FROM tr_request r
            JOIN pembelian p ON p.id_request=r.id_request OR p.no_request=r.no_request
            LEFT JOIN tr_request_detail d ON d.id_detail=p.id_request_detail AND d.id_request=r.id_request
            WHERE r.id_request=$id AND d.id_detail IS NULL LIMIT 1");
        if (mysqli_fetch_assoc($lama)) {
            throw new DomainException('Ada realisasi lama tanpa tautan detail PRB yang valid. Cocokkan data lama sebelum mencatat pelunasan.');
        }

        // Satu baris per item yang belum tercatat. Tidak mengubah stok, status item,
        // atau status penerimaan barang. Tanggal realisasi mengikuti konfirmasi Finance.
        prbFinanceQuery($koneksi, "INSERT INTO pembelian
            (id_request, id_request_detail, no_request, tgl_beli_barang, tgl_beli,
             supplier, nama_barang_beli, merk_beli, qty, harga, kategori_beli,
             alokasi_stok, nama_pemesan, driver, plat_nomor, id_mobil, keterangan, id_user_beli, sumber_data)
            SELECT r.id_request, d.id_detail, r.no_request, CURDATE(), CURDATE(),
                ms.nama_supplier,
                LEFT(COALESCE(NULLIF(TRIM(d.nama_barang_manual), ''), mb.nama_barang), 150),
                LEFT(COALESCE(NULLIF(TRIM(d.kwalifikasi), ''), mb.merk), 100),
                d.jumlah, d.harga_satuan_estimasi,
                LEFT(COALESCE(NULLIF(TRIM(d.kategori_barang), ''), mb.kategori), 100),
                CASE WHEN d.tipe_request='STOK' THEN 'MASUK STOK' ELSE 'LANGSUNG PAKAI' END,
                r.nama_pemesan, r.nama_pembeli, COALESCE(mm.plat_nomor, '-'), COALESCE(d.id_mobil, 0),
                CASE WHEN UPPER(COALESCE(d.keterangan, '')) NOT LIKE '%USD%'
                     AND (UPPER(COALESCE(r.keterangan, '')) LIKE '%USD%' OR EXISTS (
                          SELECT 1 FROM tr_request_detail usd
                          WHERE usd.id_request=r.id_request AND UPPER(COALESCE(usd.keterangan, '')) LIKE '%USD%'
                     ))
                     THEN CONCAT_WS(CHAR(10), NULLIF(d.keterangan, ''), 'USD')
                     ELSE d.keterangan END,
                $idUserBeliSql, 'SISTEM'
            FROM tr_request r
            JOIN tr_request_detail d ON d.id_request=r.id_request
            JOIN tr_purchase_order po ON po.id_po=$idPo AND po.id_request=r.id_request
            JOIN master_supplier ms ON ms.id_supplier=po.id_supplier
            LEFT JOIN master_barang mb ON mb.id_barang=d.id_barang
            LEFT JOIN master_mobil mm ON mm.id_mobil=d.id_mobil
            LEFT JOIN pembelian p ON p.id_request_detail=d.id_detail
                AND (p.id_request=r.id_request OR p.no_request=r.no_request)
            WHERE r.id_request=$id AND COALESCE(d.status_item, '') <> 'REJECTED'
              AND p.id_pembelian IS NULL");
        $jumlahBarisBaru = mysqli_affected_rows($koneksi);
    }

    $newpaid = number_format(($paid + $jumlah) / 100, 2, '.', '');
    $newstatus = $lunas ? 'LUNAS' : 'DP_TERBAYAR';
    $logaksi = 'BAYAR_' . $jenis;
    $amount = number_format($jumlah / 100, 2, '.', '');
    $stmt = mysqli_prepare($koneksi, "UPDATE tr_prb_pembayaran SET total_terbayar=?, status_bayar=?,
        nominal_disetujui=0, jenis_disetujui=NULL, last_paid_by=?, last_paid_at=NOW()
        WHERE id_request=? AND status_bayar='MENUNGGU_FINANCE'");
    if (!$stmt || !mysqli_stmt_bind_param($stmt, 'sssi', $newpaid, $newstatus, $username, $id) ||
        !mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
        throw new RuntimeException('Gagal memperbarui status pembayaran.');
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO tr_prb_pembayaran_log
        (id_request, aksi, nominal, catatan, referensi_bayar, actor) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt || !mysqli_stmt_bind_param($stmt, 'isssss', $id, $logaksi, $amount, $catatan, $referensi, $username) ||
        !mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Gagal menyimpan riwayat pembayaran.');
    }
    mysqli_stmt_close($stmt);
    if (!mysqli_commit($koneksi)) {
        throw new RuntimeException('Gagal menyelesaikan transaksi pembayaran.');
    }
    $transactionOpen = false;
    kembaliFinance($lunas
        ? 'Pembayaran lunas tercatat. Buku Realisasi: ' . $jumlahBarisBaru . ' item baru.'
            . ($jumlahBarisBaru > 0 && $idUserBeli === null
                ? ' Petugas PRB belum dapat dicocokkan secara unik. Pilih petugas melalui Edit di halaman Pembelian.'
                : '')
        : 'DP tercatat; PRB tetap tampil untuk verifikasi pelunasan.');
} catch (Throwable $e) {
    if ($transactionOpen) {
        try { mysqli_rollback($koneksi); } catch (Throwable $ignored) {}
    }
    if (!$e instanceof DomainException) {
        error_log('PRB finance error id=' . $id . ': ' . $e->getMessage());
    }
    kembaliFinance('Pembayaran tidak disimpan: ' . ($e instanceof DomainException
        ? $e->getMessage() : 'Gagal mencatat pembayaran atau Buku Realisasi. Hubungi administrator.'));
}
