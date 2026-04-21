<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
include '../../auth/keep_alive.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Mobil Baru - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { background-color: #f4f7f6; }
        .card-input { max-width: 550px; margin: 40px auto; border-radius: 15px; border: none; }
        .bg-mcp { background-color: #0000FF; color: white; }
        input { text-transform: uppercase; }
        .is-invalid { border-color: #dc3545 !important; }
        .is-valid { border-color: #198754 !important; }
    </style>
</head>
<body>

<div class="container">
    <div class="card card-input shadow-lg">
        <div class="card-header bg-mcp py-3 text-center">
            <h5 class="m-0 fw-bold text-uppercase"><i class="fas fa-truck me-2"></i> Tambah Armada Baru</h5>
        </div>
        
        <div class="card-body p-4">
            <form action="proses_tambah_mobil.php" method="POST" id="formMobil">
                <div class="mb-3">
                    <label class="small fw-bold text-muted">PLAT NOMOR</label>
                    <input type="text" 
                        name="plat_nomor" 
                        id="plat_nomor"
                        class="form-control form-control-lg fw-bold" 
                        placeholder="CONTOH : L1234AB" 
                        maxlength="12"
                        required autofocus>
                    <div id="pesan_plat" class="mt-1 fw-bold" style="font-size: 0.75rem;"></div>
                </div>
                
                <div class="mb-3">
                    <label class="small fw-bold text-muted">NAMA DRIVER</label>
                    <input type="text" name="driver_tetap" class="form-control" placeholder="NAMA DRIVER" required>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">STATUS AKTIF</label>
                    <select name="status_aktif" class="form-select">
                        <option value="AKTIF">AKTIF</option>
                        <option value="NONAKTIF">NONAKTIF</option>
                    </select>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="small fw-bold text-muted">JENIS KENDARAAN</label>
                        <input type="text" name="jenis_kendaraan" class="form-control" placeholder="MISAL: TRUK / PICKUP">
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold text-muted">KATEGORI</label>
                        <select name="kategori_kendaraan" class="form-select">
                            <option value="PERUSAHAAN">PERUSAHAAN</option>
                            <option value="PRIBADI">PRIBADI</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-7">
                        <label class="small fw-bold text-muted">MERK / TIPE</label>
                        <input type="text" name="merk_tipe" class="form-control" placeholder="MISAL: HINO / ISUZU">
                    </div>
                    <div class="col-5">
                        <label class="small fw-bold text-muted">TAHUN</label>
                        <input type="number" name="tahun_kendaraan" class="form-control" value="<?= date('Y') ?>">
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-12 mb-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" id="btnSimpan">
                            <i class="fas fa-save me-2"></i> SIMPAN DATA MOBIL
                        </button>
                    </div>
                    <div class="col-6">
                        <a href="data_mobil.php" class="btn btn-outline-secondary w-100 fw-bold py-2">
                            <i class="fas fa-list me-2"></i> DATA MOBIL
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    const pesan = urlParams.get('pesan');

    if (pesan === 'berhasil') {
        Swal.fire({
            icon: 'success',
            title: 'BERHASIL!',
            text: 'Data armada baru telah ditambahkan.',
            confirmButtonColor: '#0000FF'
        });
    } else if (pesan === 'format_plat_salah') {
        Swal.fire({
            icon: 'error',
            title: 'FORMAT SALAH!',
            text: 'Gunakan spasi, contoh: L 1234 AB',
            confirmButtonColor: '#dc3545'
        });
    } else if (pesan === 'gagal') {
        Swal.fire({
            icon: 'error',
            title: 'GAGAL!',
            text: 'Terjadi kesalahan saat menyimpan data.',
            confirmButtonColor: '#dc3545'
        });
    }

    // Bersihkan URL tanpa reload
    window.history.replaceState({}, document.title, window.location.pathname);
</script>

<script>
    const platInput = document.getElementById('plat_nomor');
    const pesanPlat = document.getElementById('pesan_plat');
    const btnSimpan = document.getElementById('btnSimpan');

    platInput.addEventListener('input', function (e) {
        // 1. Hanya ubah ke Huruf Besar dan buang karakter aneh (selain huruf & angka)
        // Spasi tetap diizinkan agar user bisa mengetik manual jika mau
        let val = e.target.value.toUpperCase().replace(/[^A-Z0-9 ]/g, '');
        e.target.value = val;

        // 2. AJAX Cek Duplikat
        const currentPlat = val.trim();
        if (currentPlat.length >= 3) {
            $.ajax({
                url: 'cek_plat.php',
                type: 'POST',
                data: { plat_nomor: currentPlat },
                success: function(respon) {
                    if (respon.trim() === 'ada') {
                        pesanPlat.style.color = 'red';
                        pesanPlat.innerHTML = '<i class="fas fa-times-circle"></i> Plat Nomor sudah terdaftar!';
                        btnSimpan.disabled = true;
                        platInput.classList.add('is-invalid');
                        platInput.classList.remove('is-valid');
                    } else {
                        pesanPlat.style.color = 'green';
                        pesanPlat.innerHTML = '<i class="fas fa-check-circle"></i> Plat Nomor tersedia.';
                        btnSimpan.disabled = false;
                        platInput.classList.remove('is-invalid');
                        platInput.classList.add('is-valid');
                    }
                }
            });
        } else {
            pesanPlat.innerHTML = '';
            platInput.classList.remove('is-invalid', 'is-valid');
            btnSimpan.disabled = false;
        }
    });

    // 3. Final Validasi saat Submit (Regex Fleksibel)
    document.getElementById('formMobil').addEventListener('submit', function(e) {
        const plat = platInput.value.trim();
        
        // Regex Baru: 
        // Boleh ada spasi atau tidak di antara kode wilayah, angka, dan seri belakang
        // Contoh valid: "L1234", "L 1234", "L1234AB", "L 1234 AB"
        const regex = /^[A-Z]{1,2}\s?[0-9]{1,4}(\s?[A-Z]{1,3})?$/;
        
        if (!regex.test(plat)) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'PERIKSA KEMBALI',
                text: 'Format Plat Nomor tidak valid. Contoh: L 1234 atau L1234AB',
                confirmButtonColor: '#ffc107'
            });
        }
    });
</script>

</body>
</html>