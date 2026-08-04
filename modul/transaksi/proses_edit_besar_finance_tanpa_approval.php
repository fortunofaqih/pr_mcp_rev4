<?php
// ============================================================
// proses_edit_besar_finance.php
// Proses Edit PR Finance - HANYA UPDATE CATATAN PO
// ============================================================

session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// HARUS ROLE FINANCE
if ($_SESSION['role'] !== 'finance') {
    header("location:pr.php?pesan=akses_ditolak");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("location:pr_finance.php");
    exit;
}

$id_request     = (int)($_POST['id_request'] ?? 0);
$username_login = $_SESSION['username'] ?? 'SYSTEM';

if (!$id_request) {
    header("location:pr_finance.php");
    exit;
}

// AMBIL DATA PR
$pr = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT * FROM tr_request
     WHERE id_request = '$id_request'
       AND kategori_pr = 'BESAR'
     LIMIT 1"
));

if (!$pr) {
    header("location:pr_finance.php?pesan=tidak_ditemukan");
    exit;
}

// SANITASI INPUT - HANYA CATATAN PO
$catatan_po = mysqli_real_escape_string($koneksi, $_POST['catatan_po'] ?? '');
$updated_by = mysqli_real_escape_string($koneksi, $username_login);
$now        = date('Y-m-d H:i:s');

// CEK APAKAH PO ADA
$cek_po = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT id_po FROM tr_purchase_order WHERE id_request = '$id_request' LIMIT 1"
));

mysqli_begin_transaction($koneksi);

try {
    
    if ($cek_po) {
        // UPDATE CATATAN PO SAJA
        $sql_po = "UPDATE tr_purchase_order SET
                    catatan      = '$catatan_po'
                   WHERE id_request = '$id_request'";
    } else {
        // JIKA PO BELUM ADA, BUAT BARU DENGAN DATA DARI PR
        $no_po_baru = 'PO-FIN-' . date('YmdHis') . '-' . $id_request;
        $no_po_baru = mysqli_real_escape_string($koneksi, $no_po_baru);
        
        // Hitung total dari detail
        $subtotal_total = 0;
        $res_detail = mysqli_query($koneksi,
            "SELECT jumlah, harga_satuan_estimasi 
             FROM tr_request_detail 
             WHERE id_request = '$id_request'"
        );
        while ($row = mysqli_fetch_assoc($res_detail)) {
            $subtotal_total += (float)$row['jumlah'] * (float)$row['harga_satuan_estimasi'];
        }
        
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
                        '0',
                        CURDATE(),
                        '$subtotal_total',
                        '0',
                        '$subtotal_total',
                        '0',
                        '0',
                        '$subtotal_total',
                        '$catatan_po',
                        'DRAFT',
                        '$updated_by'
                    )";
    }
    
    if (!mysqli_query($koneksi, $sql_po)) {
        throw new Exception("Gagal update catatan PO: " . mysqli_error($koneksi));
    }
    
    // UPDATE UPDATED_AT DI TR_REQUEST
    $sql_update_request = "UPDATE tr_request SET
                            updated_by = '$updated_by',
                            updated_at = '$now'
                           WHERE id_request = '$id_request'";
    
    if (!mysqli_query($koneksi, $sql_update_request)) {
        throw new Exception("Gagal update timestamp: " . mysqli_error($koneksi));
    }
    
    mysqli_commit($koneksi);
    
    header("location:pr_finance.php?pesan=update_sukses");
    exit;
    
} catch (Exception $e) {
    mysqli_rollback($koneksi);
    $_SESSION['error_edit'] = $e->getMessage();
    header("location:edit_request_besar_finance.php?id=$id_request&pesan=gagal");
    exit;
}
?>