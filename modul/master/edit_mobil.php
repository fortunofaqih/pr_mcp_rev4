<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
include '../../auth/keep_alive.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);
$edit = mysqli_query($koneksi, "SELECT * FROM master_mobil WHERE id_mobil='$id'");
$d = mysqli_fetch_array($edit);

if (!$d) { echo "Data tidak ditemukan"; exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Mobil - MCP System</title>
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .card-edit { max-width: 600px; margin: 40px auto; border-radius: 15px; border: none; }
        .bg-mcp { background-color: #0000FF; color: white; }
        input { text-transform: uppercase; }
        .audit-section { background: #f8f9fa; border-left: 4px solid #0dcaf0; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="card card-edit shadow-lg">
        <div class="card-header bg-mcp py-3 text-center">
            <h5 class="m-0 fw-bold text-uppercase"><i class="fas fa-edit me-2"></i> Edit Data Armada</h5>
        </div>
        <div class="card-body p-4">
            <form action="update_mobil.php" method="POST">
                <input type="hidden" name="id_mobil" value="<?= $d['id_mobil'] ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">PLAT NOMOR</label>
                        <input type="text" name="plat_nomor" class="form-control fw-bold text-primary" value="<?= $d['plat_nomor'] ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">STATUS AKTIF</label>
                        <select name="status_aktif" class="form-select fw-bold">
                            <option value="AKTIF" <?= ($d['status_aktif'] == 'AKTIF') ? 'selected' : '' ?>>AKTIF</option>
                            <option value="NONAKTIF" <?= ($d['status_aktif'] == 'NONAKTIF') ? 'selected' : '' ?>>NONAKTIF</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">NAMA DRIVER</label>
                    <input type="text" name="driver_tetap" class="form-control" value="<?= $d['driver_tetap'] ?>" required>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">JENIS KENDARAAN</label>
                        <input type="text" name="jenis_kendaraan" class="form-control" value="<?= $d['jenis_kendaraan'] ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label small fw-bold">KATEGORI</label>
                        <select name="kategori_kendaraan" class="form-select" required>
                            <option value="PERUSAHAAN" <?= ($d['kategori_kendaraan'] == 'PERUSAHAAN') ? 'selected' : '' ?>>PERUSAHAAN</option>
                            <option value="PRIBADI" <?= ($d['kategori_kendaraan'] == 'PRIBADI') ? 'selected' : '' ?>>PRIBADI</option>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-8 mb-3">
                        <label class="form-label small fw-bold">MERK / TIPE</label>
                        <input type="text" name="merk_tipe" class="form-control" value="<?= $d['merk_tipe'] ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label small fw-bold">TAHUN</label>
                        <input type="number" name="tahun_kendaraan" class="form-control" value="<?= $d['tahun_kendaraan'] ?>" required>
                    </div>
                </div>

                <div class="p-3 mb-4 rounded audit-section">
                    <h6 class="fw-bold small mb-2 text-info text-uppercase"><i class="fas fa-fingerprint me-2"></i> Histori Data</h6>
                    <div class="row">
                        <div class="col-6 border-end">
                            <small class="text-muted d-block text-uppercase">Didaftarkan Oleh:</small>
                            <span class="fw-bold"><?= $d['created_by'] ?: '-'; ?></span><br>
                            <small class="text-muted"><?= ($d['created_at']) ? date('d/m/Y H:i', strtotime($d['created_at'])) : '-'; ?></small>
                        </div>
                        <div class="col-6 ps-3">
                            <small class="text-muted d-block text-uppercase">Update Terakhir:</small>
                            <span class="fw-bold"><?= $d['updated_by'] ?: '-'; ?></span><br>
                            <small class="text-muted"><?= ($d['updated_at']) ? date('d/m/Y H:i', strtotime($d['updated_at'])) : '-'; ?></small>
                        </div>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-8">
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
                            <i class="fas fa-save me-2"></i> SIMPAN PERUBAHAN
                        </button>
                    </div>
                    <div class="col-4">
                        <a href="data_mobil.php" class="btn btn-danger w-100 py-2 fw-bold">BATAL</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    let idleTime = 0;
    const maxIdleMinutes = 15;
    let lastServerUpdate = Date.now();

    // Fungsi untuk mereset timer idle
    function resetTimer() {
        idleTime = 0;
        
        let now = Date.now();
        // Kirim sinyal "Keep Alive" ke server setiap 5 menit sekali jika user aktif
        // Ini mencegah session PHP mati saat user sedang asyik mengetik/input
        if (now - lastServerUpdate > 300000) { // 300.000 ms = 5 menit
            const depth = window.location.pathname.split('/').length - 2;
            const prefix = "../".repeat(Math.max(0, depth - 1));
            
            fetch(prefix + 'auth/keep_alive.php')
                .then(response => console.log("Sesi diperbarui secara background"))
                .catch(err => console.error("Gagal memperbarui sesi", err));
            
            lastServerUpdate = now;
        }
    }

    // Deteksi interaksi user
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    // Interval cek setiap 1 menit
    setInterval(function() {
        idleTime++;
        if (idleTime >= maxIdleMinutes) {
            alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
            const depth = window.location.pathname.split('/').length - 2;
            const prefix = "../".repeat(Math.max(0, depth - 1));
            window.location.href = prefix + "login.php?pesan=timeout";
        }
    }, 60000); // Cek setiap 60 detik
</script>
</body>
</html>