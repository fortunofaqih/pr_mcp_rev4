<?php
session_start();
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
if ($is_finance) {
    $bln_awal  = date('Y-m-01');
    $bln_akhir = date('Y-m-t');
    $bln_lalu_awal  = date('Y-m-01', strtotime('-1 month'));
    $bln_lalu_akhir = date('Y-m-t', strtotime('-1 month'));
    $bulan_label_arr = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    // ── Stat cards ──
    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) AS c FROM tr_purchase_order WHERE status_po='OPEN'"));
    $fin_po_open = (int)$r['c'];

    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) AS c FROM tr_purchase_order p
         JOIN tr_request r ON p.id_request=r.id_request
         WHERE p.status_po='CLOSE'
           AND DATE(r.updated_at) BETWEEN '$bln_awal' AND '$bln_akhir'"));
    $fin_po_close_bln = (int)$r['c'];

    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COALESCE(SUM(grand_total),0) AS t FROM tr_purchase_order WHERE status_po='OPEN'"));
    $fin_nilai_open = (float)$r['t'];

    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COALESCE(SUM(grand_total),0) AS t
         FROM tr_purchase_order p
         JOIN tr_request r ON p.id_request=r.id_request
         WHERE p.status_po='CLOSE'
           AND DATE(r.updated_at) BETWEEN '$bln_awal' AND '$bln_akhir'"));
    $fin_nilai_close_bln = (float)$r['t'];

    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COUNT(*) AS c FROM tr_request
         WHERE kategori_pr='BESAR'
           AND status_approval NOT IN ('APPROVED','DISETUJUI')
           AND status_request NOT IN ('SELESAI','REJECTED')"));
    $fin_pr_tunggu = (int)$r['c'];

    // Bln lalu untuk perbandingan nilai
    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COALESCE(SUM(grand_total),0) AS t
         FROM tr_purchase_order p
         JOIN tr_request r ON p.id_request=r.id_request
         WHERE p.status_po='CLOSE'
           AND DATE(r.updated_at) BETWEEN '$bln_lalu_awal' AND '$bln_lalu_akhir'"));
    $fin_nilai_close_lalu = (float)$r['t'];

    // Tren 6 bulan terakhir (nilai PO close per bulan)
    $fin_tren = [];
    for ($i = 5; $i >= 0; $i--) {
        $tgl   = strtotime("-$i month");
        $bl    = date('Y-m-01', $tgl);
        $bk    = date('Y-m-t',  $tgl);
        $label = $bulan_label_arr[(int)date('m', $tgl)].' '.date('y', $tgl);
        $r = mysqli_fetch_assoc(mysqli_query($koneksi,
            "SELECT COALESCE(SUM(grand_total),0) AS t
             FROM tr_purchase_order p
             JOIN tr_request r ON p.id_request=r.id_request
             WHERE p.status_po='CLOSE'
               AND DATE(r.updated_at) BETWEEN '$bl' AND '$bk'"));
        $fin_tren[] = ['label' => $label, 'nilai' => (float)$r['t']];
    }

    // PR Besar 10 terbaru
    $fin_pr_besar = mysqli_query($koneksi,
        "SELECT no_request, nama_pemesan, tgl_request,
                status_request, status_approval, keterangan
         FROM tr_request
         WHERE kategori_pr='BESAR'
           AND status_request NOT IN ('REJECTED')
         ORDER BY id_request DESC LIMIT 10");

    // PO Open terbaru
    $fin_po_open_list = mysqli_query($koneksi,
        "SELECT p.no_po, p.grand_total, p.tgl_approve,
                r.no_request, r.nama_pemesan,
                s.nama_supplier
         FROM tr_purchase_order p
         JOIN tr_request r ON p.id_request=r.id_request
         LEFT JOIN master_supplier s ON p.id_supplier=s.id_supplier
         WHERE p.status_po='OPEN'
         ORDER BY p.tgl_approve DESC LIMIT 8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MCP System</title>
    <link rel="icon" type="image/png" href="assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-blue: #0000FF; --mcp-dark: #00008B; }
        body { background-color: #f4f7f6; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; overflow-x: hidden; }
        
        /* Sidebar Styling */
        #sidebar {
            min-width: 260px;
            max-width: 260px;
            background: var(--mcp-blue);
            color: #fff;
            transition: all 0.3s;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 1040;
        }
        #sidebar.active { margin-left: -260px; }
        
        .sidebar-header { padding: 20px; background: rgba(0,0,0,0.1); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-scroll { flex-grow: 1; overflow-y: auto; overflow-x: hidden; }
        
        .nav-link { color: rgba(255,255,255,0.8); font-size: 0.75rem; padding: 10px 20px; font-weight: 500; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .nav-link:hover, .nav-link.active { background: var(--mcp-dark); color: #fff; }
        
        .nav-category { padding: 15px 20px 5px; font-size: 0.7rem; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 5px; }

        /* Main Content Styling */
        #content { width: 100%; min-height: 100vh; transition: all 0.3s; display: flex; flex-direction: column; }
        .topbar { background: #fff; border-bottom: 1px solid #e3e6f0; padding: 10px 20px; position: sticky; top: 0; z-index: 1030; }
        
        /* Mobile Overlay */
        @media (max-width: 992px) {
            #sidebar { margin-left: -260px; position: fixed; }
            #sidebar.active { margin-left: 0; }
            .overlay { display: none; position: fixed; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1035; }
            .overlay.active { display: block; }
        }

       /* Pastikan Wrapper memenuhi layar */
.wrapper {
    display: flex;
    width: 100%;
    align-items: stretch;
    height: 100vh; /* KUNCI: Wrapper harus setinggi layar */
    overflow: hidden;
}

/* Sidebar Styling */
#sidebar {
    min-width: 260px;
    max-width: 260px;
    background: var(--mcp-blue);
    color: #fff;
    transition: all 0.3s;
    height: 100vh; /* KUNCI: Sidebar setinggi layar */
    display: flex;
    flex-direction: column;
    z-index: 1040;
}

/* Area Scroll Sidebar */
.sidebar-scroll {
    flex: 1; /* Mengambil sisa ruang yang ada */
    overflow-y: auto; /* Memunculkan scrollbar jika menu banyak */
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch; /* Halus di iPhone/Android */
}

/* Tambahkan ini agar scrollbar terlihat (opsional tapi disarankan) */
.sidebar-scroll::-webkit-scrollbar {
    width: 6px;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
}
.sidebar-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.4);
}

/* Content Area juga harus bisa scroll sendiri */
#content {
    flex: 1;
    height: 100vh;
    overflow-y: auto; 
    background-color: #f4f7f6;
    display: flex;
    flex-direction: column;
}

        .card { border: none; border-radius: 8px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); }
        .heart { display: inline-block; animation: pulse 1.5s infinite; color: #ff4d4d; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>

<div class="wrapper">
    <nav id="sidebar">
        <div class="sidebar-header text-center">
            <div class="d-flex align-items-center justify-content-center gap-2">
                <img src="assets/img/logo_mcp.png" alt="Logo" style="width: 32px;">
                <h6 class="fw-bold m-0 text-white">MCP SYSTEM</h6>
            </div>
            <small class="opacity-50 text-white small">INVENTORY & PR</small>
        </div>

        <div class="sidebar-scroll">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="index.php" class="nav-link active"><i class="fas fa-home me-2"></i> Dashboard</a>
                </li>
                <!-- MENU MANAGER -->
                <?php if ($role == 'administrator' || $role == 'manager') : ?>
                    <div class="nav-category text-warning">Panel Approval</div>
                    <li class="nav-item">
                        <a href="modul/pimpinan/approval_pimpinan.php" class="nav-link">
                            <i class="fas fa-circle-check me-2"></i> Approval PR Besar
                            <?php if ($jumlah_notif > 0): ?>
                                <span class="badge rounded-pill bg-danger float-end"><?= $jumlah_notif ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
                <!-- MENU ADMIN GUDANG -->
                <?php if ($is_gudang_access) : ?>
                    <div class="nav-category">-- Master Data --</div>
                    <li class="nav-item"><a href="modul/master/data_barang.php" class="nav-link text-warning fw-bold"><i class="fas fa-boxes me-2"></i> Master Barang</a></li>
                    <li class="nav-item"><a href="modul/master/data_mobil.php" class="nav-link text-warning fw-bold"><i class="fas fa-truck me-2"></i> Master Mobil</a></li>
                    <li class="nav-item"><a href="modul/master/data_supplier.php" class="nav-link text-warning fw-bold"><i class="fa-brands fa-shopify me-2"></i> Master Supplier</a></li>
                    
                    <div class="nav-category">-- Transaksi Gudang --</div>
                    <li class="nav-item"><a href="modul/transaksi/tambah_request.php" class="nav-link text-warning fw-bold"><i class="fas fa-file-invoice me-2"></i> Form PR (Kecil)</a></li>
                    <li class="nav-item"><a href="modul/transaksi/tambah_request_besar.php" class="nav-link text-warning fw-bold"><i class="fas fa-cart-plus me-2"></i> Form PR (Besar)</a></li>
                    <li class="nav-item"><a href="modul/transaksi/pr.php" class="nav-link text-warning fw-bold"><i class="fa-solid fa-bars-progress me-2"></i> Progress PR</a></li>
                    <li class="nav-item"><a href="modul/transaksi/verifikasi_pembelian.php" class="nav-link text-warning fw-bold"><i class="fas fa-check-double me-2"></i> Verifikasi Pembelian</a></li>
                    <li class="nav-item"><a href="modul/transaksi/pengambilan.php" class="nav-link text-warning fw-bold"><i class="fas fa-dolly me-2"></i> Bon Pengambilan</a></li>
                    <li class="nav-item"><a href="modul/transaksi/retur.php" class="nav-link text-warning fw-bold"><i class="fas fa-undo me-2"></i> Retur Barang</a></li>

                    <div class="nav-category">-- Penyesuaian --</div>
                    <li class="nav-item"><a href="modul/transaksi/koreksi.php" class="nav-link text-warning fw-bold"><i class="fas fa-sync me-2"></i> Koreksi Stok</a></li>
                    <li class="nav-item"><a href="modul/transaksi/pemusnahan.php" class="nav-link text-warning fw-bold"><i class="fas fa-trash-alt me-2"></i> Pemusnahan</a></li>

                    <div class="nav-category">-- Laporan & Analisa --</div>
                    <li class="nav-item"><a href="modul/laporan/data_stock.php" class="nav-link text-warning fw-bold"><i class="fas fa-book me-2"></i> Buku Stok Barang</a></li>
                    <li class="nav-item"><a href="modul/laporan/data_pembelian.php" class="nav-link text-warning fw-bold"><i class="fas fa-book me-2"></i> Buku Pembelian</a></li>
                    <li class="nav-item"><a href="modul/laporan/laporan_mutasi_cepat.php" class="nav-link text-warning fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Summary Stok</a></li>
                    <li class="nav-item"><a href="modul/laporan/laporan_mobil.php" class="nav-link text-warning fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Laporan Mobil</a></li>
                    <li class="nav-item"><a href="modul/laporan/laporan_barang_kecil.php" class="nav-link text-warning fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Lap. Barang Kecil</a></li>
                    <li class="nav-item"><a href="modul/laporan/laporan_barang_besar.php" class="nav-link text-warning fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Lap. Barang Besar</a></li>
                    <li class="nav-item"><a href="modul/laporan/laporan_stok_bulanan.php" class="nav-link text-warning fw-bold"><i class="fas fa-layer-group me-2"></i> Stok Bulanan</a></li>
                    <li class="nav-item"><a href="modul/laporan/riwayat_retur.php" class="nav-link text-warning fw-bold"><i class="fas fa-clock-rotate-left me-2"></i> Riwayat Retur</a></li>
                    <li class="nav-item"><a href="modul/laporan/kategori.php" class="nav-link text-warning fw-bold"><i class="fas fa-chart-line me-2"></i> Analisis Kategori</a></li>
                <?php endif; ?>
                <!-- MENU BAGIAN PEMBELIAN -->
                <?php if ($role == 'bagian_pembelian') : ?>
                <div class="nav-category">Pembelian</div>
                <li class="nav-item"><a href="modul/pembelian/index.php" class="nav-link"><i class="fas fa-shopping-cart me-2"></i> Halaman Pembelian</a></li>
                <li class="nav-item"><a href="modul/transaksi/tambah_request.php" class="nav-link"><i class="fas fa-clipboard me-2"></i> Buat Form Request</a></li>
                <li class="nav-item"><a href="modul/transaksi/update_status_ban.php" class="nav-link"><i class="fas fa-car me-2"></i> Update Status PO</a></li>
            <?php endif; ?>
                <!-- MENU BAGIAN PEMESAN PR -->
            <?php if ($is_pemesan_pr) : ?>
				<div class="nav-category">-- Step 1 - Master Data --</div>
                    <li class="nav-item"><a href="modul/master/data_barang.php" class="nav-link text-warning fw-bold"><i class="fas fa-boxes me-2"></i> Master Barang</a></li>
                    <li class="nav-item"><a href="modul/master/data_supplier.php" class="nav-link text-warning fw-bold"><i class="fa-brands fa-shopify me-2"></i> Master Supplier</a></li>
                <div class="nav-category">-- Step 2 - Form Request --</div>
                <li class="nav-item">
                    <a href="modul/transaksi/tambah_request_besar.php" class="nav-link text-warning fw-bold">
                        <i class="fas fa-cart-plus me-2"></i> Form PR Besar
                    </a>
                </li>
                <li class="nav-item">
                    <a href="modul/transaksi/pr.php" class="nav-link text-warning fw-bold">
                        <i class="fas fa-bars-progress me-2"></i> Progress PR
                    </a>
                </li>
				<div class="nav-category">-- Step 3 - Pembelian --</div>
                <li class="nav-item"><a href="modul/pembelian/index.php" class="nav-link text-warning fw-bold"><i class="fas fa-shopping-cart me-2"></i> Halaman Pembelian</a></li>
				<li class="nav-item"><a href="modul/transaksi/verifikasi_pembelian.php" class="nav-link text-warning fw-bold"><i class="fas fa-check-double me-2"></i> Verifikasi Pembelian</a></li>
				<li class="nav-item"><a href="modul/transaksi/update_status_ban.php" class="nav-link text-warning fw-bold"><i class="fas fa-car me-2"></i> Update Status PO</a></li>
                 
            <?php endif; ?>
                 <!-- MENU FINANCE -->
                <?php if ($is_finance) : ?>
                    <div class="nav-category">Finance</div>
                    <li class="nav-item">
                        <a href="modul/finance/index.php" class="nav-link">
                            <i class="fas fa-file-invoice-dollar me-2"></i> Detail Request
                        </a>
                    </li>
                   <!-- <li class="nav-item"><a href="modul/finance/update_pr_besar.php" class="nav-link"><i class="fas fa-bell me-2"></i> Update Status PR</a></li>-->
                <?php endif; ?>
            </ul>
        </div>

        <div class="p-3 border-top border-white-50">
            <a href="ganti_password.php" class="btn btn-sm btn-outline-light w-100 mb-2 fw-bold" style="font-size:0.7rem;">
                <i class="fas fa-key me-1"></i> GANTI PASSWORD
            </a>
            <div class="text-center opacity-50 small" style="font-size: 0.6rem;">
                &copy; <?= date("Y") ?> MCP System
            </div>
        </div>
    </nav>

   <div id="content">
    <header class="topbar d-flex justify-content-between align-items-center shadow-sm">
        <button type="button" id="sidebarCollapse" class="btn btn-primary d-lg-none">
            <i class="fas fa-bars"></i>
        </button>

        <div class="fw-bold text-uppercase d-none d-sm-block" style="color: var(--mcp-blue); font-size: 0.85rem;">
            <i class="fas fa-user-circle me-1"></i>Selamat Datang, <?= $nama ?>
            <span class="badge bg-success ms-1 small"><?= strtoupper($role) ?></span>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="small text-muted fw-bold d-none d-md-block me-2"><?= date('d F Y') ?></span>
            
            <a href="ganti_password.php" class="btn btn-warning btn-sm fw-bold shadow-sm" title="Ganti Password">
                <i class="fas fa-key me-1"></i> <span class="d-none d-md-inline">PASSWORD</span>
            </a>

            <a href="auth/logout.php" class="btn btn-danger btn-sm fw-bold shadow-sm">
                <i class="fas fa-power-off me-1"></i> <span class="d-none d-md-inline">KELUAR</span>
            </a>
        </div>
    </header>

        <div class="container-fluid p-3 p-md-4">
            <!-- NOTIF APPROVAL untuk Manager -->
            <?php if ($role == 'manager' && $jumlah_notif > 0) : ?>
                <div class="alert alert-primary border-0 shadow-sm d-flex flex-column flex-md-row align-items-center justify-content-between p-3 mb-4">
                    <div class="mb-2 mb-md-0 text-center text-md-start">
                        <h6 class="fw-bold mb-1"><i class="fas fa-bell me-2"></i> Perhatian Pimpinan</h6>
                        <span class="small">Ada <strong><?= $jumlah_notif ?></strong> PR Besar menunggu persetujuan.</span>
                    </div>
                    <a href="modul/pimpinan/approval_pimpinan.php" class="btn btn-warning btn-sm fw-bold shadow-sm">PROSES SEKARANG</a>
                </div>
            <?php endif; ?>

           <!-- <div class="row align-items-center mb-4">
                <div class="col-12 col-md-6 mb-3 mb-md-0">
                    <h4 class="fw-bold text-uppercase m-0">Ringkasan Sistem</h4>
                </div>
                <div class="col-12 col-md-6 text-md-end">
                    <form action="" method="GET" class="d-inline-flex bg-white p-2 rounded shadow-sm border align-items-center">
                        <label class="small fw-bold me-2 mb-0 text-muted">TAHUN:</label>
                        <select name="tahun_filter" class="form-select form-select-sm border-0 fw-bold text-primary" onchange="this.form.submit()" style="width:100px;">
                            <?php for ($x = date('Y'); $x >= 2024; $x--) : ?>
                                <option value="<?= $x ?>" <?= ($x == $tahun_pilihan) ? "selected" : "" ?>><?= $x ?></option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>-->

              <!-- DASHBOARD PEMBELIAN -->
            <?php if ($role == 'bagian_pembelian') : ?>
                <h3 class="fw-bold mb-4 text-uppercase">Antrean Kerja Pembelian</h3>
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card border-start border-danger border-4 shadow-sm h-100">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small fw-bold text-danger text-uppercase mb-1">PR Belum Diproses</div>
                                    <?php $d = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tr_request WHERE status_request = 'PENDING'")); ?>
                                    <div class="h3 mb-0 fw-bold"><?= $d['total'] ?></div>
                                    <small class="text-muted">Pemesanan</small>
                                </div>
                                <div class="icon-circle bg-danger text-white shadow-sm"><i class="fas fa-clock fa-lg"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 fw-bold text-primary text-uppercase">Daftar Tunggu PR</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle m-0">
                                <thead class="table-light small text-uppercase">
                                    <tr>
                                        <th class="ps-4">No. Request</th>
                                        <th>Tanggal</th>
                                        <th>Pemesan</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $q = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE status_request = 'PENDING' ORDER BY id_request DESC");
                                    if (mysqli_num_rows($q) > 0) {
                                        while ($r = mysqli_fetch_array($q)) {
                                            echo "<tr>
                                                <td class='fw-bold text-primary ps-4'>{$r['no_request']}</td>
                                                <td>" . date('d/m/Y', strtotime($r['tgl_request'])) . "</td>
                                                <td>{$r['nama_pemesan']}</td>
                                                <td class='text-center'><a href='modul/pembelian/index.php?id_pr={$r['id_request']}' class='btn btn-sm btn-primary px-3 rounded-pill fw-bold'>Proses Beli</a></td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center py-5 text-muted'>Tidak ada antrean saat ini</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <!-- DASHBOARD PR BESAR -->
         <?php if ($is_pemesan_pr) : ?>
        <h3 class="fw-bold mb-4 text-uppercase" style="letter-spacing: 1px;">Form PR Besar</h3>
        
        <div class="card border-0 shadow-sm bg-primary text-white mb-4 overflow-hidden">
            <div class="card-body p-4 position-relative">
                <div class="position-relative" style="z-index: 2;">
                    <h5 class="fw-bold">Halo, <?= $nama ?>! 👋</h5>
                    <p class="mb-0 opacity-75 small">Pantau status pengajuan Purchase Request (PR) Anda secara real-time di sini.</p>
                </div>
                <i class="fas fa-file-invoice fa-5x position-absolute end-0 bottom-0 opacity-25 me-n3 mb-n3"></i>
            </div>
        </div>

        <div class="row">
            <div class="col-6 col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="icon-sm bg-warning-subtle text-warning rounded-3 p-2 me-2">
                                <i class="fas fa-copy"></i>
                            </div>
                            <span class="text-muted small fw-bold">TOTAL PR</span>
                        </div>
                        <?php
                        $user_log = mysqli_real_escape_string($koneksi, $_SESSION['username']);
                        $d_total = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tr_request WHERE kategori_pr = 'BESAR' AND created_by = '$user_log'"));
                        ?>
                        <h3 class="fw-bold mb-0"><?= $d_total['total'] ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="icon-sm bg-danger-subtle text-danger rounded-3 p-2 me-2">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <span class="text-muted small fw-bold">PENDING</span>
                        </div>
                        <?php
                        $d_pending = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as total FROM tr_request WHERE kategori_pr = 'BESAR' AND created_by = '$user_log' AND status_approval NOT IN ('APPROVED','DISETUJUI','DITOLAK')"));
                        ?>
                        <h3 class="fw-bold mb-0"><?= $d_pending['total'] ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-4 mb-4">
                <a href="modul/transaksi/tambah_request_besar.php" class="btn btn-success w-100 h-100 d-flex align-items-center justify-content-center py-3 shadow-sm border-0">
                    <i class="fas fa-plus-circle me-2"></i> 
                    <span class="fw-bold">BUAT PR BARU</span>
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark"><i class="fas fa-history me-2 text-primary"></i> 10 PR Terakhir</h6>
                <span class="badge bg-light text-dark border">Data: <?= $user_log ?></span>
            </div>
            <div class="card-body p-0"> <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem; min-width: 600px;">
                        <thead class="table-light text-uppercase" style="font-size: 0.75rem;">
                            <tr>
                                <th class="ps-3">Nomor PR</th>
                                <th>Status PR</th>
                                <th>Approval 1</th>
                                <th>Approval 2</th>
                                <th>Catatan</th>
                                <!-- <th class="text-center">Aksi</th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $q_list = mysqli_query($koneksi, "SELECT * FROM tr_request 
                                                            WHERE created_by = '$user_log' 
                                                            AND kategori_pr = 'BESAR' 
                                                            ORDER BY created_at DESC LIMIT 10");
                            while ($row = mysqli_fetch_assoc($q_list)) :
                                // Logic Badge Status
                                $st_req = $row['status_request'];
                                $badge_req = ($st_req == 'PENDING') ? 'bg-warning' : (($st_req == 'PROSES') ? 'bg-primary' : 'bg-success');
                                
                                $st_app = $row['status_approval'];
                                $is_rejected = ($st_app == 'DITOLAK');
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold text-dark"><?= $row['no_request'] ?></span><br>
                                    <small class="text-muted"><?= date('d M Y', strtotime($row['tgl_request'])) ?></small>
                                </td>
                                <td><span class="badge <?= $badge_req ?> fw-normal"><?= $st_req ?></span></td>
                                <td>
                                    <?php if($row['approve1_at']): ?>
                                        <span class="text-success small fw-bold"><i class="fas fa-check-double"></i> OK</span>
                                    <?php else: ?>
                                        <span class="text-muted small italic">Waiting</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($row['approve2_at']): ?>
                                        <span class="text-success small fw-bold"><i class="fas fa-check-double"></i> OK</span>
                                    <?php elseif($is_rejected): ?>
                                        <span class="text-danger small fw-bold"><i class="fas fa-times-circle"></i> NO</span>
                                    <?php else: ?>
                                        <span class="text-muted small italic">Waiting</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 150px;" class="text-truncate small">
                                        <?= !empty($row['catatan_pimpinan']) ? $row['catatan_pimpinan'] : ($is_rejected ? $row['catatan_tolak'] : '-') ?>
                                    </div>
                                </td>
                               <!-- <td class="text-center">
                                    <a href="modul/transaksi/get_detail_pr.php?id=<?= $row['id_request'] ?>" class="btn btn-light btn-sm border">
                                        <i class="fas fa-search"></i>
                                    </a>
                                </td>-->
                            </tr>
                            <?php endwhile; ?>
                            <?php if (mysqli_num_rows($q_list) == 0) : ?>
                                <tr><td colspan="6" class="text-center py-5 text-muted">Belum ada pengajuan ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

            <!-- DASHBOARD FINANCE -->
           
<?php if ($is_finance) : ?>

<!-- ── Finance CSS (scoped) ───────────────────────────────── -->
<style>
.fin-wrap {
    font-family: 'Segoe UI', sans-serif;
}

/* Hero greeting */
.fin-hero {
    background: linear-gradient(135deg, #0c2461 0%, #1e3a8a 45%, #1d4ed8 100%);
    border-radius: 16px;
    padding: 24px 28px;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 22px;
    box-shadow: 0 8px 32px rgba(29,78,216,.35);
}
.fin-hero::before {
    content: '';
    position: absolute;
    right: -40px; top: -40px;
    width: 200px; height: 200px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
}
.fin-hero::after {
    content: '';
    position: absolute;
    right: 60px; bottom: -60px;
    width: 140px; height: 140px;
    border-radius: 50%;
    background: rgba(255,255,255,.04);
}
.fin-hero-title {
    font-size: 1.3rem;
    font-weight: 800;
    letter-spacing: -.3px;
    margin-bottom: 4px;
}
.fin-hero-sub {
    font-size: .8rem;
    color: rgba(255,255,255,.65);
}
.fin-hero-badge {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.25);
    color: #fff;
    border-radius: 20px;
    padding: 4px 14px;
    font-size: .72rem;
    font-weight: 700;
}
.fin-hero-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff;
    color: #1e3a8a;
    border-radius: 8px;
    padding: 7px 16px;
    font-size: .78rem;
    font-weight: 800;
    text-decoration: none;
    transition: transform .15s, box-shadow .15s;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    margin-top: 14px;
}
.fin-hero-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,.2);
    color: #1e3a8a;
}

/* Stat cards */
.fin-stat-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
@media(min-width: 992px) {
    .fin-stat-grid { grid-template-columns: repeat(4, 1fr); }
}

.fin-stat {
    background: #fff;
    border-radius: 12px;
    padding: 16px 18px;
    box-shadow: 0 1px 4px rgba(0,0,0,.07), 0 4px 14px rgba(0,0,0,.05);
    border-top: 3px solid transparent;
    transition: transform .18s, box-shadow .18s;
    animation: finFadeUp .35s ease both;
    position: relative; overflow: hidden;
}
.fin-stat:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,0,0,.10); }
.fin-stat.s1 { border-top-color: #1d4ed8; }
.fin-stat.s2 { border-top-color: #0d9488; }
.fin-stat.s3 { border-top-color: #d97706; }
.fin-stat.s4 { border-top-color: #7c3aed; }

.fin-stat-icon {
    width: 36px; height: 36px;
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem; margin-bottom: 10px;
}
.s1 .fin-stat-icon { background: #dbeafe; color: #1d4ed8; }
.s2 .fin-stat-icon { background: #ccfbf1; color: #0f766e; }
.s3 .fin-stat-icon { background: #fef3c7; color: #d97706; }
.s4 .fin-stat-icon { background: #ede9fe; color: #7c3aed; }

.fin-stat-lbl {
    font-size: .62rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    color: #64748b; margin-bottom: 2px;
}
.fin-stat-val {
    font-size: 1.7rem; font-weight: 800;
    line-height: 1; margin-bottom: 2px;
    font-variant-numeric: tabular-nums;
}
.s1 .fin-stat-val { color: #1d4ed8; }
.s2 .fin-stat-val { color: #0f766e; }
.s3 .fin-stat-val { color: #d97706; }
.s4 .fin-stat-val { color: #7c3aed; font-size: 1.1rem; }
.fin-stat-sub { font-size: .68rem; color: #94a3b8; }

.fin-stat-trend {
    position: absolute;
    bottom: 10px; right: 12px;
    font-size: .65rem; font-weight: 700;
}
.trend-up   { color: #059669; }
.trend-down { color: #dc2626; }
.trend-same { color: #94a3b8; }

/* Panels */
.fin-panel {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,.07), 0 4px 14px rgba(0,0,0,.05);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 18px;
    animation: finFadeUp .4s ease both;
}
.fin-panel-head {
    display: flex; align-items: center;
    justify-content: space-between;
    flex-wrap: wrap; gap: 8px;
    padding: 13px 18px;
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(to right, #f8fafc, #fff);
}
.fin-panel-title {
    font-size: .85rem; font-weight: 800;
    color: #0f172a;
    display: flex; align-items: center; gap: 7px;
}
.fin-panel-title i { color: #1d4ed8; }

/* Chart container */
.fin-chart-wrap {
    padding: 16px 18px;
    position: relative;
}
.chart-canvas { width: 100% !important; }

/* Table */
.fin-tbl { width: 100%; border-collapse: collapse; font-size: .78rem; }
.fin-tbl th {
    background: #f8fafc; padding: 9px 14px;
    font-size: .63rem; font-weight: 800;
    text-transform: uppercase; letter-spacing: .6px;
    color: #64748b; border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.fin-tbl td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}
.fin-tbl tbody tr:hover { background: #f8fffe; }
.fin-tbl tbody tr:last-child td { border-bottom: none; }

/* Badges */
.fbdg {
    display: inline-flex; align-items: center; gap: 3px;
    border-radius: 20px; padding: 2px 9px;
    font-size: .62rem; font-weight: 800; white-space: nowrap;
}
.fb-open    { background: #ccfbf1; color: #065f46; }
.fb-close   { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.fb-appr    { background: #ccfbf1; color: #065f46; }
.fb-tunggu  { background: #fef3c7; color: #92400e; }
.fb-proses  { background: #dbeafe; color: #1e40af; }
.fb-selesai { background: #dcfce7; color: #166534; }
.fb-pending { background: #f1f5f9; color: #64748b; }

/* Mini bar chart (CSS-only) */
.mini-bar-wrap {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 36px;
}
.mini-bar {
    flex: 1;
    background: linear-gradient(to top, #1d4ed8, #60a5fa);
    border-radius: 3px 3px 0 0;
    min-height: 3px;
    transition: opacity .2s;
}
.mini-bar:hover { opacity: .75; }

/* Two-column grid for panels */
.fin-grid-2 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-bottom: 18px;
}
@media(min-width: 992px) {
    .fin-grid-2 { grid-template-columns: 1fr 1fr; }
}

/* Empty row */
.fin-empty {
    text-align: center; padding: 30px;
    color: #94a3b8; font-size: .8rem;
}
.fin-empty i { font-size: 1.5rem; opacity: .2; display: block; margin-bottom: 8px; }

/* Animations */
@keyframes finFadeUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.fin-stat:nth-child(1) { animation-delay: .04s; }
.fin-stat:nth-child(2) { animation-delay: .08s; }
.fin-stat:nth-child(3) { animation-delay: .12s; }
.fin-stat:nth-child(4) { animation-delay: .16s; }
.fin-panel:nth-child(1) { animation-delay: .1s; }
.fin-panel:nth-child(2) { animation-delay: .15s; }

/* Rupiah helper */
.mono { font-family: 'Courier New', monospace; font-weight: 700; }

/* Scroll table */
.fin-tbl-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
</style>

<?php
/* ── QUERY DATA FINANCE ──────────────────────────────────── */
$bln_awal  = date('Y-m-01');
$bln_akhir = date('Y-m-t');
$bln_lalu_awal  = date('Y-m-01', strtotime('-1 month'));
$bln_lalu_akhir = date('Y-m-t',  strtotime('-1 month'));
$bulan_lbl = ['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$periode_fin = $bulan_lbl[(int)date('m')].' '.date('Y');

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS c FROM tr_purchase_order WHERE status_po='OPEN'"));
$fin_po_open = (int)$r['c'];

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS c FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request=r.id_request
     WHERE p.status_po='CLOSE'
       AND DATE(r.updated_at) BETWEEN '$bln_awal' AND '$bln_akhir'"));
$fin_po_close_bln = (int)$r['c'];

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(grand_total),0) AS t FROM tr_purchase_order WHERE status_po='OPEN'"));
$fin_nilai_open = (float)$r['t'];

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(grand_total),0) AS t
     FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request=r.id_request
     WHERE p.status_po='CLOSE'
       AND DATE(r.updated_at) BETWEEN '$bln_awal' AND '$bln_akhir'"));
$fin_nilai_close_bln = (float)$r['t'];

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(grand_total),0) AS t
     FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request=r.id_request
     WHERE p.status_po='CLOSE'
       AND DATE(r.updated_at) BETWEEN '$bln_lalu_awal' AND '$bln_lalu_akhir'"));
$fin_nilai_close_lalu = (float)$r['t'];

$r = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS c FROM tr_request
     WHERE kategori_pr='BESAR'
       AND status_approval NOT IN ('APPROVED','DISETUJUI')
       AND status_request NOT IN ('SELESAI','REJECTED')"));
$fin_pr_tunggu = (int)$r['c'];

// Tren 6 bulan
$fin_tren_labels = [];
$fin_tren_nilai  = [];
$fin_tren_max    = 1;
for ($i = 5; $i >= 0; $i--) {
    $tgl = strtotime("-$i month");
    $bl  = date('Y-m-01', $tgl);
    $bk  = date('Y-m-t',  $tgl);
    $lbl = $bulan_lbl[(int)date('m', $tgl)]." '".date('y', $tgl);
    $r = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COALESCE(SUM(grand_total),0) AS t
         FROM tr_purchase_order p
         JOIN tr_request r ON p.id_request=r.id_request
         WHERE p.status_po='CLOSE'
           AND DATE(r.updated_at) BETWEEN '$bl' AND '$bk'"));
    $fin_tren_labels[] = $lbl;
    $fin_tren_nilai[]  = (float)$r['t'];
    if ((float)$r['t'] > $fin_tren_max) $fin_tren_max = (float)$r['t'];
}

// PR Besar terbaru
$fin_pr_besar = mysqli_query($koneksi,
    "SELECT no_request, nama_pemesan, tgl_request,
            status_request, status_approval, keterangan
     FROM tr_request
     WHERE kategori_pr='BESAR'
       AND status_request NOT IN ('REJECTED')
     ORDER BY id_request DESC LIMIT 8");

// PO Open terbaru
$fin_po_list = mysqli_query($koneksi,
    "SELECT p.no_po, p.grand_total, p.tgl_approve,
            r.no_request, r.nama_pemesan,
            s.nama_supplier
     FROM tr_purchase_order p
     JOIN tr_request r ON p.id_request=r.id_request
     LEFT JOIN master_supplier s ON p.id_supplier=s.id_supplier
     WHERE p.status_po='OPEN'
     ORDER BY p.tgl_approve DESC LIMIT 8");

// Nilai tren bulan ini vs bulan lalu
$tren_pct = 0;
$tren_dir = 'same';
if ($fin_nilai_close_lalu > 0) {
    $tren_pct = round(($fin_nilai_close_bln - $fin_nilai_close_lalu) / $fin_nilai_close_lalu * 100, 1);
    $tren_dir = $tren_pct > 0 ? 'up' : ($tren_pct < 0 ? 'down' : 'same');
}

function fin_rp(float $n): string {
    if ($n >= 1_000_000_000) return 'Rp '.number_format($n/1_000_000_000, 2, ',', '.').' M';
    if ($n >= 1_000_000)     return 'Rp '.number_format($n/1_000_000,     1, ',', '.').' jt';
    return 'Rp '.number_format($n, 0, ',', '.');
}
?>

<div class="fin-wrap">

    <!-- Hero greeting -->
    <div class="fin-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="fin-hero-title">
                    <i class="fas fa-landmark me-2" style="color:#7dd3fc;"></i>
                    Finance Monitor
                </div>
                <div class="fin-hero-sub">
                    Selamat datang, <strong style="color:#fff;"><?= htmlspecialchars($nama) ?></strong> — <?= date('l, d F Y') ?>
                </div>
                <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                    <span class="fin-hero-badge"><i class="fas fa-calendar-alt me-1"></i><?= $periode_fin ?></span>
                    <?php if ($fin_pr_tunggu > 0): ?>
                    <span class="fin-hero-badge" style="background:rgba(251,191,36,.25);border-color:rgba(251,191,36,.4);">
                        <i class="fas fa-bell me-1" style="color:#fbbf24;"></i>
                        <?= $fin_pr_tunggu ?> PR Besar menunggu approval
                    </span>
                    <?php endif; ?>
                </div>
                <a href="modul/finance/index.php" class="fin-hero-link">
                    <i class="fas fa-arrow-right"></i> Buka Monitor PO Lengkap
                </a>
            </div>
            <div style="text-align:right;" class="d-none d-md-block">
                <div style="font-size:2.5rem;opacity:.15;line-height:1;">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="fin-stat-grid">
        <div class="fin-stat s1">
            <div class="fin-stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="fin-stat-lbl">PO Open</div>
            <div class="fin-stat-val"><?= $fin_po_open ?></div>
            <div class="fin-stat-sub">Purchase Order aktif</div>
        </div>

        <div class="fin-stat s2">
            <div class="fin-stat-icon"><i class="fas fa-check-double"></i></div>
            <div class="fin-stat-lbl">PO Close Bulan Ini</div>
            <div class="fin-stat-val"><?= $fin_po_close_bln ?></div>
            <div class="fin-stat-sub">Selesai <?= $periode_fin ?></div>
        </div>

        <div class="fin-stat s3">
            <div class="fin-stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="fin-stat-lbl">PR Besar Menunggu</div>
            <div class="fin-stat-val"><?= $fin_pr_tunggu ?></div>
            <div class="fin-stat-sub">Belum disetujui</div>
        </div>

        <div class="fin-stat s4">
            <div class="fin-stat-icon"><i class="fas fa-wallet"></i></div>
            <div class="fin-stat-lbl">Nilai PO Open</div>
            <div class="fin-stat-val"><?= fin_rp($fin_nilai_open) ?></div>
            <div class="fin-stat-sub">Total outstanding</div>
            <?php if ($tren_dir !== 'same'): ?>
            <div class="fin-stat-trend trend-<?= $tren_dir ?>">
                <i class="fas fa-arrow-<?= $tren_dir ?>"></i>
                <?= abs($tren_pct) ?>% vs bln lalu
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tren 6 Bulan + PO Open List -->
    <div class="fin-grid-2">

        <!-- Grafik Tren -->
        <div class="fin-panel">
            <div class="fin-panel-head">
                <div class="fin-panel-title">
                    <i class="fas fa-chart-area"></i>
                    Tren Nilai PO Close — 6 Bulan
                </div>
                <span style="font-size:.68rem;color:#94a3b8;">Realisasi bulanan</span>
            </div>
            <div class="fin-chart-wrap">
                <canvas id="chartTrenFinance" height="200" class="chart-canvas"></canvas>
            </div>
        </div>

        <!-- PO Open List -->
        <div class="fin-panel">
            <div class="fin-panel-head">
                <div class="fin-panel-title">
                    <i class="fas fa-list-ul"></i>
                    PO Open Terbaru
                </div>
                <a href="modul/finance/index.php?tab=open" style="font-size:.72rem;color:#1d4ed8;font-weight:700;text-decoration:none;">
                    Lihat semua <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="fin-tbl-scroll">
                <table class="fin-tbl">
                    <thead>
                        <tr>
                            <th>No. PO</th>
                            <th>Supplier</th>
                            <th class="text-end">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_po = false;
                    if ($fin_po_list) {
                        while ($po = mysqli_fetch_assoc($fin_po_list)):
                            $has_po = true;
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:800;color:#1e3a8a;font-size:.78rem;"><?= htmlspecialchars($po['no_po']) ?></div>
                            <div style="font-size:.67rem;color:#94a3b8;"><?= htmlspecialchars($po['no_request']) ?></div>
                        </td>
                        <td style="font-size:.76rem;font-weight:600;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?= htmlspecialchars($po['nama_supplier'] ?? '-') ?>
                        </td>
                        <td style="text-align:right;" class="mono" style="color:#dc2626;font-size:.74rem;">
                            <span style="color:#dc2626;"><?= fin_rp((float)$po['grand_total']) ?></span>
                        </td>
                    </tr>
                    <?php endwhile; } ?>
                    <?php if (!$has_po): ?>
                    <tr><td colspan="3" class="fin-empty"><i class="fas fa-check-double"></i>Tidak ada PO Open.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- PR Besar terbaru -->
    <div class="fin-panel">
        <div class="fin-panel-head">
            <div class="fin-panel-title">
                <i class="fas fa-clipboard-list"></i>
                PR Kategori Besar — Terbaru
            </div>
            <a href="modul/finance/index.php" style="font-size:.72rem;color:#1d4ed8;font-weight:700;text-decoration:none;">
                Monitor lengkap <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="fin-tbl-scroll">
            <table class="fin-tbl">
                <thead>
                    <tr>
                        <th>No. PR</th>
                        <th>Tanggal</th>
                        <th class="d-none d-md-table-cell">Pemesan</th>
                        <th>Status PR</th>
                        <th>Approval</th>
                        <th class="d-none d-lg-table-cell">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $has_pr = false;
                if ($fin_pr_besar) {
                    while ($pr = mysqli_fetch_assoc($fin_pr_besar)):
                        $has_pr = true;
                        $sr = $pr['status_request'];
                        $is_appr = in_array($pr['status_approval'], ['APPROVED','DISETUJUI']);

                        if ($sr === 'PENDING')  $sc = 'fb-pending';
                        elseif ($sr === 'PROSES') $sc = 'fb-proses';
                        elseif ($sr === 'SELESAI') $sc = 'fb-selesai';
                        else $sc = 'fb-pending';
                ?>
                <tr>
                    <td style="font-weight:800;color:#1e3a8a;font-size:.78rem;"><?= htmlspecialchars($pr['no_request']) ?></td>
                    <td style="font-size:.74rem;color:#64748b;"><?= date('d/m/Y', strtotime($pr['tgl_request'])) ?></td>
                    <td class="d-none d-md-table-cell" style="font-weight:600;font-size:.78rem;"><?= htmlspecialchars(strtoupper($pr['nama_pemesan'])) ?></td>
                    <td><span class="fbdg <?= $sc ?>"><?= htmlspecialchars($sr) ?></span></td>
                    <td>
                        <?php if ($is_appr): ?>
                            <span class="fbdg fb-appr"><i class="fas fa-check-circle me-1"></i>APPROVED</span>
                        <?php else: ?>
                            <span class="fbdg fb-tunggu"><i class="fas fa-hourglass-half me-1"></i>MENUNGGU</span>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-lg-table-cell" style="font-size:.74rem;color:#64748b;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($pr['keterangan'] ?? '-') ?>
                    </td>
                </tr>
                <?php endwhile; } ?>
                <?php if (!$has_pr): ?>
                <tr><td colspan="6" class="fin-empty"><i class="fas fa-inbox"></i>Tidak ada PR Besar.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /fin-wrap -->

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
    const labels = <?= json_encode($fin_tren_labels) ?>;
    const data   = <?= json_encode($fin_tren_nilai) ?>;

    const ctx = document.getElementById('chartTrenFinance');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Nilai PO Close',
                data: data,
                backgroundColor: function(ctx) {
                    const chart = ctx.chart;
                    const {ctx: c, chartArea} = chart;
                    if (!chartArea) return '#1d4ed8';
                    const grad = c.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    grad.addColorStop(0, 'rgba(29,78,216,.5)');
                    grad.addColorStop(1, 'rgba(29,78,216,.95)');
                    return grad;
                },
                borderRadius: 6,
                borderSkipped: false,
                borderColor: 'rgba(29,78,216,.0)',
                hoverBackgroundColor: 'rgba(96,165,250,.9)',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const v = ctx.parsed.y;
                            if (v >= 1e9) return 'Rp ' + (v/1e9).toFixed(2) + ' M';
                            if (v >= 1e6) return 'Rp ' + (v/1e6).toFixed(1) + ' jt';
                            return 'Rp ' + v.toLocaleString('id-ID');
                        }
                    },
                    backgroundColor: '#1e3a8a',
                    titleColor: '#fff',
                    bodyColor: '#bfdbfe',
                    padding: 10,
                    cornerRadius: 8,
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, weight: '700' }, color: '#64748b' }
                },
                y: {
                    grid: { color: 'rgba(0,0,0,.05)', drawBorder: false },
                    ticks: {
                        font: { size: 10 }, color: '#94a3b8',
                        callback: function(v) {
                            if (v >= 1e9) return (v/1e9).toFixed(1)+'M';
                            if (v >= 1e6) return (v/1e6).toFixed(0)+'jt';
                            return v.toLocaleString('id-ID');
                        }
                    }
                }
            }
        }
    });
})();
</script>

<?php endif; ?>


            <!-- DASHBOARD ADMIN GUDANG -->
            <?php if ($is_gudang_access) : ?>
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold m-0 text-uppercase">Ringkasan Sistem</h3>
                    <form action="" method="GET" class="bg-white p-2 rounded shadow-sm border d-flex align-items-center">
                        <label class="small fw-bold me-2 mb-0 text-muted">TAHUN:</label>
                        <select name="tahun_filter" class="form-select form-select-sm border-0 fw-bold text-primary" onchange="this.form.submit()" style="width:100px;">
                            <?php for ($x = date('Y')+1; $x >= 2024; $x--) {
                                $sel = ($x == $tahun_pilihan) ? "selected" : "";
                                echo "<option value='$x' $sel>$x</option>";
                            } ?>
                        </select>
                    </form>
                </div>
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 fw-bold text-primary text-uppercase"><i class="fas fa-chart-bar me-2"></i> Statistik Aktivitas Bulanan</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle m-0">
                                <thead class="table-light text-secondary small text-uppercase">
                                    <tr>
                                        <th class="ps-4">Bulan</th>
                                        <th class="text-center">Item Dibeli</th>
                                        <th>Total Pengeluaran</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $query_bulanan = mysqli_query($koneksi, "
                                        SELECT m.bulan,
                                               COALESCE(pb.jml_beli, 0) as jml_beli,
                                               COALESCE(pb.total_biaya, 0) as total_biaya
                                        FROM (SELECT 1 AS bulan UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
                                              UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) m
                                        LEFT JOIN (
                                            SELECT MONTH(tgl_beli_barang) as bln, COUNT(*) as jml_beli, SUM(qty * harga) as total_biaya
                                            FROM pembelian WHERE YEAR(tgl_beli_barang) = '$tahun_pilihan'
                                            GROUP BY MONTH(tgl_beli_barang)
                                        ) pb ON m.bulan = pb.bln
                                        WHERE pb.jml_beli > 0
                                        ORDER BY m.bulan DESC");

                                    $grand_total_item = 0;
                                    $grand_total_biaya = 0;

                                    if (mysqli_num_rows($query_bulanan) > 0) {
                                        while ($r = mysqli_fetch_array($query_bulanan)) {
                                            $nama_bulan = date("F", mktime(0,0,0,$r['bulan'],10));
                                            $grand_total_item  += $r['jml_beli'];
                                            $grand_total_biaya += $r['total_biaya'];
                                            ?>
                                            <tr>
                                                <td class="fw-bold ps-4 text-dark"><?= strtoupper($nama_bulan) ?></td>
                                                <td class="text-center"><span class="badge rounded-pill bg-secondary shadow-sm"><?= $r['jml_beli'] ?> Item</span></td>
                                                <td class="text-danger fw-bold">Rp <?= number_format($r['total_biaya'], 0, ',', '.') ?></td>
                                                <td class="text-center">
                                                    <a href="modul/laporan/detail_bulan.php?bulan=<?= $r['bulan'] ?>&tahun=<?= $tahun_pilihan ?>" class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill fw-bold">
                                                        <i class="fas fa-eye me-1"></i> Details
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                        <tr class="table-secondary border-top border-dark">
                                            <td class="ps-4 fw-bold text-uppercase">Total (<?= $tahun_pilihan ?>)</td>
                                            <td class="text-center fw-bold"><?= number_format($grand_total_item) ?> Item</td>
                                            <td class="text-danger fw-bold" style="font-size:1.1rem;">Rp <?= number_format($grand_total_biaya, 0, ',', '.') ?></td>
                                            <td></td>
                                        </tr>
                                        <?php
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center py-5 text-muted'>TIDAK ADA AKTIVITAS DI TAHUN $tahun_pilihan</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="alert alert-info border-0 shadow-sm small">
                <i class="fas fa-info-circle me-2"></i> Selamat Datang kembali, <strong><?= $nama ?></strong>. Selamat bekerja!
            </div>

            <footer class="mt-auto py-4 text-center text-muted small">
                &copy; <?= date("Y") ?> PT Mutiara Cahaya Plastindo | Made with <span class="heart">❤️</span> by Team IT
            </footer>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const btn = document.getElementById('sidebarCollapse');

        function toggleSidebar() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        btn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
    });
</script>
</body>
</html>