<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if (!isset($koneksi) || !$koneksi) {
    die("Koneksi database tidak tersedia.");
}

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
    <title>Input Barang Baru - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { background-color: #f4f7f6; }
        .card-input { max-width: 600px; margin: 50px auto; border-radius: 15px; overflow: hidden; }
        .bg-mcp { background-color: #0000FF; color: white; }
        input, select { text-transform: uppercase; }
        /* Style tambahan untuk input harga agar terlihat seperti mata uang */
        .input-group-text { background-color: #e9ecef; font-weight: bold; color: #495057; }
    </style>
</head>
<body>

<div class="container">
    <div class="card card-input shadow">
        <div class="card-header bg-mcp py-3 text-center">
            <h5 class="m-0 fw-bold"><i class="fas fa-plus-circle me-2"></i> TAMBAH MASTER BARANG BARU</h5>
        </div>
        <div class="card-body p-4">
            <form action="proses_barang.php" method="POST">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">NAMA BARANG / DESKRIPSI</label>
                    <input type="text" name="nama_barang" class="form-control form-control-lg" placeholder="Masukkan nama barang..." required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">MERK / KWAL.</label>
                    <input type="text" name="merk" class="form-control" placeholder="Contoh: TOYOTA, ASPIRA, GENUINE" required>
                </div> 

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">TGL INPUT STOK AWAL</label>
                        <input type="date" name="tgl_log" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">SATUAN UTAMA</label>
                        <select name="satuan" class="form-select" required>
                        <option value="">- PILIH SATUAN -</option>
                        <?php
                        
                        $query_satuan = mysqli_query($koneksi, "SELECT * FROM master_satuan ORDER BY nama_satuan ASC");
                        while($s = mysqli_fetch_array($query_satuan)){
                            echo "<option value='".$s['nama_satuan']."'>".$s['nama_satuan']."</option>";
                        }
                        ?>
                    </select>
                    </div>
                    <div class="col-md-4">
                       <label class="form-label small fw-bold text-muted">STOK AWAL</label>
                        <input type="number" name="stok_awal" class="form-control" placeholder="0.00" step="any" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">HARGA BARANG STOK (OPSIONAL)</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" id="input_harga" class="form-control" placeholder="0">
                        <input type="hidden" name="harga_barang_stok" id="harga_bersih">
                    </div>
                    <div class="form-text">Masukkan harga per satuan (Contoh: 150.000).</div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">KATEGORI BARANG</label>
                    <select name="kategori" class="form-select" required>
                    <option value="">-- PILIH KATEGORI --</option>
                    <?php
                    $query_kat = mysqli_query($koneksi, "SELECT * FROM master_kategori ORDER BY nama_kategori ASC");
                    while($k = mysqli_fetch_array($query_kat)){
                        echo "<option value='".$k['nama_kategori']."'>".$k['nama_kategori']."</option>";
                    }
                    ?>
                </select>
                </div>

               <label class="form-label small fw-bold text-muted">LOKASI PENYIMPANAN (RAK)</label>
                <select name="lokasi_rak" class="form-select">
                    <option value="">-- PILIH RAK --</option>
                    <?php
                    $query_rak = mysqli_query($koneksi, "SELECT * FROM master_rak ORDER BY nama_rak ASC");
                    while($r = mysqli_fetch_array($query_rak)){
                        echo "<option value='".$r['nama_rak']."'>".$r['nama_rak']."</option>";
                    }
                    ?>
                </select>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">STATUS AKTIF</label>
                    <select name="status_aktif" class="form-select">
                        <option value="AKTIF">AKTIF</option>
                        <option value="NONAKTIF">NONAKTIF</option>
                    </select>
                </div>

                <div class="row g-2">
                    <div class="col-12 mb-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                            <i class="fas fa-save me-2"></i> SIMPAN DATA BARANG
                        </button>
                    </div>
                    <div class="col-6">
                        <a href="data_barang.php" class="btn btn-outline-secondary w-100 fw-bold py-2">
                            <i class="fas fa-list me-2"></i> DATA BARANG
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="../../index.php" class="btn btn-danger w-100 py-2 fw-bold">
                            <i class="fas fa-rotate-left me-2"></i> KEMBALI
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    const pesan = urlParams.get('pesan');
    
    const inputHarga = document.getElementById('input_harga');
    const hargaBersih = document.getElementById('harga_bersih');

inputHarga.addEventListener('keyup', function(e) {
    // Ambil angka saja dari input
    let number_string = this.value.replace(/[^,\d]/g, '').toString();
    let split    = number_string.split(',');
    let sisa     = split[0].length % 3;
    let rupiah   = split[0].substr(0, sisa);
    let ribuan   = split[0].substr(sisa).match(/\d{3}/gi);

    // Tambahkan titik jika input ribuan
    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
    
    // Tampilkan ke user
    this.value = rupiah;
    
    // Simpan angka bersih (tanpa titik) ke input hidden untuk dikirim ke PHP
    hargaBersih.value = number_string.replace(/\./g, '');
});

    if (pesan === 'berhasil') {
        Swal.fire({
            icon: 'success',
            title: 'BERHASIL!',
            text: 'Data barang baru telah disimpan ke sistem.',
            confirmButtonColor: '#0000FF'
        });
    } else if (pesan === 'ada') {
        Swal.fire({
            icon: 'warning',
            title: 'DATA SUDAH ADA!',
            text: 'Nama barang tersebut sudah terdaftar di database.',
            confirmButtonColor: '#ffc107'
        });
    } else if (pesan === 'gagal') {
        Swal.fire({
            icon: 'error',
            title: 'GAGAL!',
            text: 'Terjadi kesalahan teknis saat menyimpan data.',
            confirmButtonColor: '#d33'
        });
    }
    
    // Bersihkan URL
    window.history.replaceState({}, document.title, window.location.pathname);
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
            fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
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
        window.location.href = "http://192.168.31.200/pr_mcp/auth/logout.php?pesan=timeout";
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
        fetch('http://192.168.31.200/pr_mcp/auth/keep_alive.php')
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