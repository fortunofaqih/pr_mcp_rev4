<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';


if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <title>Database Master Barang - MCP System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    
  <style>
    :root { --mcp-blue: #0000FF; }
    body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .navbar-mcp { background: var(--mcp-blue); color: white; }
    .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    table.dataTable thead th { vertical-align: middle; text-align: center; background-color: #f1f4f9; }
    .uom-badge { background: #e7f0ff; color: #004dc0; font-weight: bold; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; }
    .price-text { color: #28a745; font-weight: 600; }

    /* PERBAIKAN WARNA BARIS GUDANG */
    /* Stok Habis (Merah Lembut agar teks hitam tetap terbaca) */
    .stok-danger { background-color: #ffd6d6 !important; } 
    /* Stok Tipis (Kuning Lembut) */
    .stok-warning { background-color: #fff3cd !important; }

    /* Pastikan teks tetap gelap agar terbaca jelas di background warna */
    .stok-danger td, .stok-warning td { color: #333 !important; }
    .stok-danger .text-muted, .stok-warning .text-muted { color: #666 !important; }
</style>
</head>
<body>

<nav class="navbar navbar-mcp mb-4">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-database me-2"></i> MASTER BARANG</span>
        <div>
             <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left"></i> KEMBALI</a>
            <a href="barang.php" class="btn btn-sm btn-light fw-bold"><i class="fas fa-plus-circle"></i> TAMBAH BARANG BARU</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelBarang" class="table table-hover table-bordered align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama Items</th>
                            <th>Merk</th>
                            <th class="text-center">Kategori</th> 
                            <th>Lokasi Rak</th>
                            <th class="text-center">Satuan</th>
                            <th class="text-center">Harga Satuan</th> <th class="text-center">Stok Akhir</th>
                            <th class="text-center">Status</th>
                            <th width="10%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                    <?php
                        $no = 1;
                        $sql = "SELECT b.*, 
                                (SELECT SUM(qty) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'MASUK') as t_masuk,
                                (SELECT SUM(qty) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'KELUAR') as t_keluar
                                FROM master_barang b 
                                ORDER BY b.nama_barang ASC";

                        $query = mysqli_query($koneksi, $sql);
                        while($d = mysqli_fetch_array($query)){
                            $masuk    = $d['t_masuk'] ?? 0;
                            $keluar   = $d['t_keluar'] ?? 0;
                            $stok_akhir_log = $masuk - $keluar;
                            
                            $row_class = "";
                            $label_status = "";

                            if ($stok_akhir_log <= 0) {
                                $row_class = "stok-danger";
                                $label_status = "HABIS";
                            } elseif ($stok_akhir_log <= 3) {
                                $row_class = "stok-warning";
                                $label_status = "STOK TIPIS";
                            }
                    ?>
                        <tr class="<?= $row_class ?>">
                            <td class="text-center text-muted"><?= $no++; ?></td>
                            <td>
                                <div class="fw-bold text-uppercase"><?= $d['nama_barang']; ?></div>
                                <?php if($label_status != ""): ?>
                                    <small class="badge bg-dark" style="font-size: 0.6rem;"><?= $label_status ?></small>
                                <?php endif; ?>
                            </td>
                            <td><small><?= $d['merk'] ?: '-'; ?></small></td>
                            <td class="text-center">
                                <span class="badge rounded-pill bg-secondary px-3"><?= $d['kategori']; ?></span>
                            </td>
                            <td class="text-center">
                                <small><i class="fas fa-map-marker-alt text-primary me-1"></i> <?= $d['lokasi_rak'] ?: '-'; ?></small>
                            </td>
                            <td class="text-center"><span class="uom-badge"><?= $d['satuan']; ?></span></td>
                            
                            <td class="text-end fw-bold">
                                <span class="price-text">Rp <?= number_format($d['harga_barang_stok'], 0, ',', '.'); ?></span>
                            </td>

                          <td class="text-center fw-bold">
							<?php 
								// 1. Pastikan jadi float agar nol mubazir hilang
								$angka_bersih = (float)$d['stok_akhir']; 
								
								// 2. Tampilkan dengan format Indonesia
								// rtrim akan menghapus nol di ujung kanan, dan menghapus koma jika sisa koma saja
								echo rtrim(rtrim(number_format($angka_bersih, 4, ',', '.'), '0'), ','); 
							?>
						</td>
                            <td class="text-center">
                                <span class="badge <?= ($d['status_aktif'] == 'AKTIF') ? 'bg-success' : 'bg-danger'; ?>"><?= $d['status_aktif']; ?></span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="edit_barang.php?id=<?= $d['id_barang']; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelBarang').DataTable({
        "pageLength": 10,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" },
        "order": [[ 1, "asc" ]],
        "columnDefs": [ { "orderable": false, "targets": [0, 9] } ] // Target 9 karena ada kolom baru
    });
});
</script>
<script>
    let idleTime = 0;
    const maxIdleMinutes = 15; // Samakan dengan server
    let lastServerUpdate = Date.now();
    let sessionValid = true;

    // Fungsi reset timer saat ada gerakan
    function resetTimer() {
        idleTime = 0;
        let now = Date.now();

        // Kirim sinyal ke server setiap 5 menit agar session PHP tidak expired
        if (now - lastServerUpdate > 300000) {
            fetch('/pr_mcp_rev4/auth/keep_alive.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        sessionValid = false;
                        forceLogout();
                    }
                })
                .catch(err => {
                    console.error("Koneksi ke server terputus");
                });
            lastServerUpdate = now;
        }
    }

    // Fungsi paksa logout
    function forceLogout() {
        alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
        // Redirect ke logout.php agar session server juga dihancurkan
        window.location.href = "/pr_mcp_rev4/auth/logout.php?pesan=timeout";
    }

    // Pantau aktivitas user
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    // Cek status idle setiap 1 menit
    setInterval(function() {
        idleTime++;
        // Cek session ke server juga
        fetch('/pr_mcp_rev4/auth/keep_alive.php')
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    sessionValid = false;
                    forceLogout();
                }
            })
            .catch(err => {
                // Jika error koneksi, biarkan user tetap di halaman
            });
        if (idleTime >= maxIdleMinutes && sessionValid) {
            forceLogout();
        }
    }, 60000);
</script>
</body>
</html>