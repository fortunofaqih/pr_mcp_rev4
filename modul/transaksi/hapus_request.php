<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") { header("location:../../login.php"); exit; }

$id = isset($_GET['id']) ? mysqli_real_escape_string($koneksi, $_GET['id']) : "";

if ($id != "") {
    $cek_status = mysqli_query($koneksi, "SELECT status_request FROM tr_request WHERE id_request = '$id'");
    $data = mysqli_fetch_array($cek_status);

    if ($data && $data['status_request'] == 'PENDING') {
        mysqli_begin_transaction($koneksi);
        try {
            mysqli_query($koneksi, "DELETE FROM tr_request_detail WHERE id_request = '$id'");
            mysqli_query($koneksi, "DELETE FROM tr_request WHERE id_request = '$id'");
            mysqli_commit($koneksi);
            header("location:pr.php?pesan=hapus_sukses");
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            header("location:pr.php?pesan=gagal_hapus");
        }
    } else {
        echo "<script>alert('Gagal: Hanya status PENDING yang boleh dihapus!'); window.location.href='pr.php';</script>";
    }
} else {
    header("location:pr.php");
}
?>