<?php
//session_start();
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
                    <input type="text" name="merk" class="form-control" placeholder="Contoh: TOYOTA, ASPIRA, GENUINE">
                </div> 

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">TGL INPUT STOK AWAL</label>
                        <input type="date" name="tgl_log" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">SATUAN UTAMA</label>
                        <select name="satuan" class="form-select" required>
                             <option value="">- PILIH -</option>
                             <option value="PCS">PCS</option>
                             <option value="DUS">SET</option>
                             <option value="KG">KG</option>
                              <option value="ONS">ONS</option>
                             <option value="LITER">LITER</option>
                             <option value="ML">MiliLiter</option>
                             <option value="METER">METER</option>
                             <option value="CM">CM</option>
                             <option value="LEMBAR">LEMBAR</option>
                             <option value="LONJOR">LONJOR</option>
                             <option value="SET">DUS</option>
                             <option value="ROLL">ROLL</option>
                             <option value="PACK">PACK</option>
                             <option value="UNIT">UNIT</option>
                             <option value="SAK">SAK</option>
                             <option value="GALON">GALON</option>
                             <option value="PAIL">PAIL</option>
                             <option value="TABUNG">TABUNG</option>
                             <option value="KALENG">KALENG</option>
                             <option value="DRUM">DRUM</option>
                             <option value="KOTAK">KOTAK</option>
                             <option value="BATANG">BATANG</option>
                             <option value="COLT">COLT</option>
                             <option value="JURIGEN">JURIGEN</option>
							 <option value="PEKERJAAN">PEKERJAAN</option>
							  <option value="ACARA">ACARA</option>
							  <option value="RIM">RIM</option>
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
                        <option value="UMUM">KANTOR (ATK/UMUM)</option>
                        <option value="BANGUNAN">BANGUNAN</option>
                        <option value="LAS">LAS</option>
                        <optgroup label="BENGKEL">
                            <option value="MOBIL">BENGKEL - MOBIL</option>
                            <option value="LISTRIK">BENGKEL - LISTRIK</option>
                            <option value="DINAMO">BENGKEL - DINAMO</option>
                            <option value="BUBUT">BENGKEL - BUBUT</option>
                        </optgroup>
						<optgroup label="PR BESAR">
                            <option value="INVESTASI MESIN">INVESTASI MESIN</option>
                            <option value="INVESTASI KENDARAAN">INVESTASI KENDARAAN</option>
                            <option value="INVESTASI IT">INVESTASI IT</option>
                            <option value="ACARA">ACARA</option>
							<option value="INVESTASI LAINNYA">INVESTASI LAINNYA</option>
                        </optgroup>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">LOKASI PENYIMPANAN (RAK)</label>
                    <input type="text" name="lokasi_rak" class="form-control" placeholder="CONTOH: RAK-A1">
                </div>

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