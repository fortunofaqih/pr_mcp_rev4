<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "mutiarac_db_purchase_system_dummy";
$port = 3306;

// Melakukan koneksi
$koneksi = mysqli_connect($host, $user, $pass, $db, $port);

// Cek koneksi
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

date_default_timezone_set('Asia/Jakarta');
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . "/pr_mcp/";

// Ambil data sesi dengan pengaman (escape string) untuk menghindari SQL Injection pada Trigger
$user_login = isset($_SESSION['username']) ? mysqli_real_escape_string($koneksi, $_SESSION['username']) : 'System/Guest';
$user_ip    = $_SERVER['REMOTE_ADDR']; 

// Set variabel untuk Trigger
mysqli_query($koneksi, "SET @user_aksi = '$user_login'");
mysqli_query($koneksi, "SET @ip_aksi = '$user_ip'");
?>