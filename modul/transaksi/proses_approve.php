<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
$id = $_GET['id'];
$status = $_GET['status'];
$user = $_SESSION['nama'];

$sql = "UPDATE tr_request SET 
        status_approval = '$status', 
        tgl_approval = NOW(), 
        approve_by = '$user' 
        WHERE id_request = '$id'";

if(mysqli_query($koneksi, $sql)){
    header("location:approval_pimpinan.php?pesan=berhasil");
}
?>