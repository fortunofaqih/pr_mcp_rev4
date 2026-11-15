<?php
// ============================================================
// proses_edit_besar.php
// Edit PR Besar + Upload File Penawaran PDF
// file_penawaran disimpan di tabel tr_request
// ============================================================

session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("location:pr.php");
    exit;
}

$id_request     = (int)($_POST['id_request'] ?? 0);
$username_login = $_SESSION['username'] ?? 'SYSTEM';

if (!$id_request) {
    header("location:pr.php");
    exit;
}

// ============================================================
// 1. AMBIL DATA PR
// ============================================================
$pr = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tr_request
     WHERE id_request = '$id_request'
       AND kategori_pr = 'BESAR'
     LIMIT 1"
));

if (!$pr) {
    header("location:pr.php?pesan=tidak_ditemukan");
    exit;
}

// ============================================================
// 2. VALIDASI STATUS
// ============================================================
$status_req = $pr['status_request'];
$status_app = $pr['status_approval'];

$status_boleh_edit = in_array($status_req, ['PENDING', 'PROSES'])
                     || $status_app == 'DITOLAK'
                     || $status_app == 'MENUNGGU APPROVAL';

if (!$status_boleh_edit) {
    header("location:pr.php?pesan=tidak_bisa_edit");
    exit;
}

// ============================================================
// 3. VALIDASI HAK AKSES
// ============================================================
$user_role = $_SESSION['role'] ?? '';

$is_admin = in_array($user_role, ['admin_gudang', 'superadmin']);
$is_owner = (strtoupper(trim($pr['created_by'] ?? '')) === strtoupper(trim($username_login)));

if (!$is_admin && !$is_owner) {
    header("location:pr.php?pesan=akses_ditolak");
    exit;
}

// ============================================================
// 4. SANITASI INPUT HEADER
// ============================================================
$tgl_request  = mysqli_real_escape_string($koneksi, $_POST['tgl_request'] ?? date('Y-m-d'));
$nama_pemesan = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_pemesan'] ?? ''));
$nama_pembeli = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_pembeli'] ?? ''));
$keterangan   = mysqli_real_escape_string($koneksi, strtoupper($_POST['keterangan'] ?? ''));
// Revisi mata uang: validasi server-side supaya tidak bergantung pada JavaScript.
$mata_uang = strtoupper(trim((string)($_POST['mata_uang'] ?? '')));
if (!in_array($mata_uang, ['IDR', 'USD'], true)) {
    $_SESSION['error_edit'] = 'Pilih mata uang Rupiah atau USD terlebih dahulu.';
    header("location:edit_request_besar.php?id=$id_request&pesan=gagal");
    exit;
}

$updated_by   = mysqli_real_escape_string($koneksi, $username_login);
$now          = date('Y-m-d H:i:s');

// Variabel untuk cleanup kalau transaksi gagal
$nama_file_baru_safe = '';
$file_penawaran_sql  = '';

mysqli_begin_transaction($koneksi);

try {

    // ========================================================
    // 5. HANDLE UPLOAD FILE PENAWARAN PDF
    //    Disimpan ke tr_request.file_penawaran
    // ========================================================
    if (
        isset($_FILES['file_penawaran']) &&
        $_FILES['file_penawaran']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES['file_penawaran']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload file penawaran gagal.");
        }

        $max_size = 5 * 1024 * 1024; // 5 MB

        if ($_FILES['file_penawaran']['size'] > $max_size) {
            throw new Exception("Ukuran file penawaran maksimal 5MB.");
        }

        $nama_asli = $_FILES['file_penawaran']['name'];
        $ext       = strtolower(pathinfo($nama_asli, PATHINFO_EXTENSION));

        if ($ext !== 'pdf') {
            throw new Exception("File penawaran harus berformat PDF.");
        }

        $upload_dir = 'C:/uploads_pr/penawaran/';

        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                throw new Exception("Folder upload penawaran gagal dibuat.");
            }
        }

        $nama_file_baru = 'penawaran_pr_' . $id_request . '_' . date('YmdHis') . '.pdf';
        $tujuan_file    = $upload_dir . $nama_file_baru;

        if (!move_uploaded_file($_FILES['file_penawaran']['tmp_name'], $tujuan_file)) {
            throw new Exception("Gagal menyimpan file penawaran.");
        }

        $nama_file_baru_safe = mysqli_real_escape_string($koneksi, $nama_file_baru);
        $file_penawaran_sql  = ", file_penawaran = '$nama_file_baru_safe'";
    }

    // ========================================================
    // 6. UPDATE HEADER PR + RESET APPROVAL
    // ========================================================
    $sql_header = "UPDATE tr_request SET
                    tgl_request      = '$tgl_request',
                    nama_pemesan     = '$nama_pemesan',
                    nama_pembeli     = '$nama_pembeli',
                    keterangan       = '$keterangan',

                    status_request   = 'PENDING',
                    status_approval  = 'MENUNGGU APPROVAL',

                    approve1_by      = NULL,
                    approve1_at      = NULL,
                    catatan_approve1 = NULL,

                    approve2_by      = NULL,
                    approve2_at      = NULL,
                    catatan_approve2 = NULL,

                    approve3_by      = NULL,
                    approve3_at      = NULL,
                    catatan_approve3 = NULL,

                    approve_by       = NULL,
                    tgl_approval     = NULL,

                    tolak_by         = NULL,
                    tolak_at         = NULL,
                    catatan_tolak    = NULL,

                    updated_by       = '$updated_by',
                    updated_at       = '$now'
                    $file_penawaran_sql
                   WHERE id_request  = '$id_request'";

    if (!mysqli_query($koneksi, $sql_header)) {
        throw new Exception("Gagal update header: " . mysqli_error($koneksi));
    }

    // Jika upload file baru berhasil dan update DB berhasil,
    // hapus file lama agar tidak menumpuk
    if (!empty($nama_file_baru_safe) && !empty($pr['file_penawaran'])) {
        $file_lama = __DIR__ . '/../../uploads/penawaran/' . $pr['file_penawaran'];

        if (is_file($file_lama)) {
            @unlink($file_lama);
        }
    }

    // ========================================================
    // 7. HAPUS DETAIL LAMA
    // ========================================================
    $sql_delete_detail = "DELETE FROM tr_request_detail WHERE id_request = '$id_request'";

    if (!mysqli_query($koneksi, $sql_delete_detail)) {
        throw new Exception("Gagal hapus detail lama: " . mysqli_error($koneksi));
    }

    // ========================================================
    // 8. INSERT DETAIL BARU
    // ========================================================
    $id_barang_arr   = $_POST['id_barang']           ?? [];
    $nama_arr        = $_POST['nama_barang_manual']  ?? [];
    $kategori_arr    = $_POST['kategori_request']    ?? [];
    $kwalifikasi_arr = $_POST['kwalifikasi']         ?? [];
    $id_mobil_arr    = $_POST['id_mobil']            ?? [];
    $tipe_arr        = $_POST['tipe_request']        ?? [];
    $jumlah_arr      = $_POST['jumlah']              ?? [];
    $satuan_arr      = $_POST['satuan']              ?? [];
    $harga_arr       = $_POST['harga']               ?? [];
    $ket_item_arr    = $_POST['keterangan_item']     ?? [];
    $is_ban_arr      = $_POST['is_ban_val']          ?? [];

    $subtotal_total = 0;
    $item_tersimpan = 0;
    $jumlah_baris   = count($id_barang_arr);

    for ($i = 0; $i < $jumlah_baris; $i++) {

        $id_brg = (int)($id_barang_arr[$i] ?? 0);
        $qty    = (float)str_replace(',', '.', $jumlah_arr[$i] ?? 0);

        if ($id_brg <= 0 || $qty <= 0) {
            continue;
        }

        $nm_manual = mysqli_real_escape_string(
            $koneksi,
            strtoupper($nama_arr[$i] ?? '')
        );

        $kat = mysqli_real_escape_string(
            $koneksi,
            strtoupper($kategori_arr[$i] ?? '')
        );

        $kwal = mysqli_real_escape_string(
            $koneksi,
            strtoupper($kwalifikasi_arr[$i] ?? '')
        );

        $mbl = (int)($id_mobil_arr[$i] ?? 0);

        $tipe = mysqli_real_escape_string(
            $koneksi,
            strtoupper($tipe_arr[$i] ?? 'LANGSUNG')
        );

        if (!in_array($tipe, ['STOK', 'LANGSUNG'])) {
            $tipe = 'LANGSUNG';
        }

        $sat = mysqli_real_escape_string(
            $koneksi,
            strtoupper($satuan_arr[$i] ?? '')
        );

        $hrg = (float)str_replace(',', '.', $harga_arr[$i] ?? 0);
        $sub = $qty * $hrg;

        // Sinkronkan mata uang dari input yang divalidasi server.
        // Jika USD, simpan penanda di baris terakhir Catatan Detail agar report existing terbaca.
        // Jika kembali ke Rupiah, hilangkan hanya baris penanda USD yang berdiri sendiri.
        $ket_raw = strtoupper((string)($ket_item_arr[$i] ?? ''));
        $ket_raw = preg_replace('/^\s*USD\s*$/mi', '', $ket_raw);
        $ket_raw = trim(preg_replace('/\n{3,}/', "\n\n", str_replace("\r", '', $ket_raw)));
        if ($mata_uang === 'USD') {
            $ket_raw .= ($ket_raw !== '' ? "\n" : '') . 'USD';
        }
        $ket = mysqli_real_escape_string($koneksi, $ket_raw);

        $ban = (int)($is_ban_arr[$i] ?? 0);

        if (empty($nm_manual)) {
            $res_n = mysqli_query($koneksi,
                "SELECT nama_barang
                 FROM master_barang
                 WHERE id_barang = '$id_brg'
                   AND is_active = 1
                 LIMIT 1"
            );

            if ($row_n = mysqli_fetch_assoc($res_n)) {
                $nm_manual = mysqli_real_escape_string(
                    $koneksi,
                    strtoupper($row_n['nama_barang'])
                );
            }
        }

        $sql_det = "INSERT INTO tr_request_detail
                    (
                        id_request,
                        nama_barang_manual,
                        id_barang,
                        id_mobil,
                        jumlah,
                        satuan,
                        harga_satuan_estimasi,
                        subtotal_estimasi,
                        kategori_barang,
                        kwalifikasi,
                        tipe_request,
                        keterangan,
                        status_item,
                        is_ban
                    )
                    VALUES
                    (
                        '$id_request',
                        '$nm_manual',
                        '$id_brg',
                        '$mbl',
                        '$qty',
                        '$sat',
                        '$hrg',
                        '$sub',
                        '$kat',
                        '$kwal',
                        '$tipe',
                        '$ket',
                        'PENDING',
                        '$ban'
                    )";

        if (!mysqli_query($koneksi, $sql_det)) {
            throw new Exception(
                "Gagal insert item ke-" . ($i + 1) . ": " . mysqli_error($koneksi)
            );
        }

        $subtotal_total += $sub;
        $item_tersimpan++;
    }

    if ($item_tersimpan === 0) {
        throw new Exception("Tidak ada item valid. Pastikan barang dan jumlah sudah diisi.");
    }

    // ========================================================
    // 9. INPUT / UPDATE PO
    // ========================================================
    $id_supplier = (int)($_POST['id_supplier'] ?? 0);

    $tgl_po = mysqli_real_escape_string(
        $koneksi,
        $_POST['tgl_po'] ?? date('Y-m-d')
    );

    $diskon = max(0, (float)($_POST['diskon'] ?? 0));

    $ppn_persen = (float)($_POST['ppn_persen'] ?? 0);

    $cat_po = mysqli_real_escape_string(
        $koneksi,
        $_POST['catatan_po'] ?? ''
    );

    $total_po = max(0, $subtotal_total - $diskon);
    $ppn_nom  = $total_po * ($ppn_persen / 100);
    $grand_po = $total_po + $ppn_nom;

    $cek_po = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT id_po, no_po
         FROM tr_purchase_order
         WHERE id_request = '$id_request'
         LIMIT 1"
    ));

    if ($cek_po) {

        $sql_po = "UPDATE tr_purchase_order SET
                    id_supplier  = '$id_supplier',
                    tgl_po       = '$tgl_po',
                    subtotal     = '$subtotal_total',
                    diskon       = '$diskon',
                    total        = '$total_po',
                    ppn_persen   = '$ppn_persen',
                    ppn_nominal  = '$ppn_nom',
                    grand_total  = '$grand_po',
                    catatan      = '$cat_po',
                    status_po    = 'DRAFT',
                    approved_by  = NULL,
                    tgl_approve  = NULL
                   WHERE id_request = '$id_request'";

    } else {

        $no_po_baru = 'PO-' . date('YmdHis') . '-' . $id_request;
        $no_po_baru = mysqli_real_escape_string($koneksi, $no_po_baru);

        $sql_po = "INSERT INTO tr_purchase_order
                    (
                        no_po,
                        id_request,
                        id_supplier,
                        tgl_po,
                        subtotal,
                        diskon,
                        total,
                        ppn_persen,
                        ppn_nominal,
                        grand_total,
                        catatan,
                        status_po,
                        created_by
                    )
                   VALUES
                    (
                        '$no_po_baru',
                        '$id_request',
                        '$id_supplier',
                        '$tgl_po',
                        '$subtotal_total',
                        '$diskon',
                        '$total_po',
                        '$ppn_persen',
                        '$ppn_nom',
                        '$grand_po',
                        '$cat_po',
                        'DRAFT',
                        '$updated_by'
                    )";
    }

    if (!mysqli_query($koneksi, $sql_po)) {
        throw new Exception("Gagal simpan PO: " . mysqli_error($koneksi));
    }

    mysqli_commit($koneksi);

    header("location:pr.php?pesan=revisi_berhasil");
    exit;

} catch (Exception $e) {

    mysqli_rollback($koneksi);

    // Jika transaksi gagal tapi file baru sudah terupload,
    // hapus file baru agar tidak menjadi file yatim
    if (!empty($nama_file_baru_safe)) {
        $file_baru_gagal = __DIR__ . '/../../uploads/penawaran/' . $nama_file_baru_safe;

        if (is_file($file_baru_gagal)) {
            @unlink($file_baru_gagal);
        }
    }

    $_SESSION['error_edit'] = $e->getMessage();

    header("location:edit_request_besar.php?id=$id_request&pesan=gagal");
    exit;
}