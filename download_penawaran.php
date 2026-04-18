<?php
// ============================================================
// download_penawaran.php
// Serve file PDF penawaran supplier dengan pengecekan session
// Letakkan di: /pr_mcp/download_penawaran.php
// ============================================================
session_start();
include 'config/koneksi.php';
include 'auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:login.php?pesan=belum_login");
    exit;
}

$file_req   = $_GET['file']       ?? '';
$id_request = (int)($_GET['id_request'] ?? 0);

// Sanitasi — ambil nama file saja, cegah path traversal
$nama_file = basename($file_req);

// Validasi format nama file (harus awalan penawaran_ dan ekstensi .pdf)
if (!preg_match('/^penawaran_[A-Za-z0-9_\-]+\.pdf$/', $nama_file)) {
    http_response_code(400);
    exit('File tidak valid.');
}

// Verifikasi file memang milik request yang diminta (keamanan tambahan)
if ($id_request > 0) {
    $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT id_request FROM tr_request
         WHERE id_request = '$id_request'
         AND file_penawaran = '" . mysqli_real_escape_string($koneksi, $nama_file) . "'
         LIMIT 1"
    ));
    if (!$cek) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

// Path file di luar webroot
$path = 'C:/uploads_pr/penawaran/' . $nama_file;

if (!file_exists($path)) {
    http_response_code(404);
    exit('File tidak ditemukan. Path: ' . $path);
}

// Serve PDF langsung di browser (inline, bukan download)
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nama_file . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;