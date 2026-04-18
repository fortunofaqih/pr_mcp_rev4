<?php
session_start();
include '../config/koneksi.php';

// Hapus session_token dari DB saat logout
if (isset($_SESSION['id_user'])) {
    $id_user = (int) $_SESSION['id_user'];
    mysqli_query($koneksi,
        "UPDATE users SET session_token=NULL WHERE id_user='$id_user'"
    );
}

session_unset();
session_destroy();

header("location:../login.php");
exit();