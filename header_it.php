<?php

include 'config/koneksi.php';
include 'auth/check_session.php'; 

$role  = $_SESSION['role'];  
$nama  = $_SESSION['nama'];

$tahun_pilihan = isset($_GET['tahun_filter']) ? $_GET['tahun_filter'] : date('Y');

// Hitung notif approval
$jumlah_notif = 0;
if ($role == 'administrator' || $role == 'manager') {
    $q_notif = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tr_request WHERE kategori_pr = 'BESAR' AND status_approval = 'MENUNGGU APPROVAL'");
    $d_notif = mysqli_fetch_assoc($q_notif);
    $jumlah_notif = $d_notif['total'];
}

// Apakah tampilkan menu gudang?
$is_gudang_access = ($role == 'admin_gudang' || $role == 'administrator');
$is_pemesan_pr  = ($role == 'pemesan_pr_besar');
$is_finance     = ($role == 'finance');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'MCP System' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
     <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <!-- Masukkan di dalam blok $additional_css atau sebelum tag </script> -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        :root { --mcp-blue: #0000FF; --mcp-dark: #00008B; }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
        
        /* FULL MONITOR - No Sidebar */
        .wrapper { display: block; width: 100%; }
        #content { width: 100%; min-height: 100vh; }
        
        .topbar { 
            background: #fff; 
            padding: 10px 20px; 
            position: sticky; 
            top: 0; 
            z-index: 1030;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
    </style>
    <!-- BARIS KRUSIAL: Mencetak CSS dari file halaman -->
    <?php if (isset($additional_css)) { echo $additional_css; } ?>
</head>
<body>

<div class="wrapper">
    <div id="content">
        <header class="topbar d-flex justify-content-between align-items-center">
            <!-- Brand & Navigation Cepat -->
            <div class="d-flex align-items-center gap-3">
                
                <h5 class="fw-bold m-0" style="color: var(--mcp-blue);">INVENTARIS IT</h5>
                <nav class="ms-4 d-none d-md-flex gap-3">
                    <a href="#" class="text-decoration-none text-dark small fw-bold">DASHBOARD</a>
                   
                </nav>
            </div>

            <!-- User Info -->
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <div class="small fw-bold text-uppercase"><?= $nama ?></div>
                    <span class="badge bg-success" style="font-size: 0.6rem;"><?= strtoupper($role) ?></span>
                </div>
               <a href="index.php" class="btn btn-success btn-sm fw-bold shadow-sm">
                <i class="fas fa-home me-1"></i> HOME
                </a>
                  <a href="../../auth/logout.php" class="btn btn-danger btn-sm fw-bold shadow-sm">
                <i class="fas fa-power-off me-1"></i> <span class="d-none d-md-inline">LOGOUT</span>
            </a>
           
            </div>
            
        </header>

        <div class="container-fluid p-4">
