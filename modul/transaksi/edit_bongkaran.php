<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
include '../../auth/keep_alive.php';

$id = $_GET['id'];
$data = mysqli_fetch_array(mysqli_query($koneksi, "SELECT * FROM tr_bongkaran WHERE id_bongkaran='$id'"));

if(isset($_POST['update'])){
    $nama    = $_POST['nama_barang'];
    $asal    = $_POST['asal_bongkaran'];
    $kondisi = $_POST['kondisi_barang'];
    $ket     = $_POST['keterangan'];

    $query = mysqli_query($koneksi, "UPDATE tr_bongkaran SET 
             nama_barang='$nama', asal_bongkaran='$asal', kondisi_barang='$kondisi', keterangan='$ket' 
             WHERE id_bongkaran='$id'");
    
    if($query) echo "<script>alert('Data diperbarui'); window.location='bongkaran.php';</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Bongkaran</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0 fw-bold">EDIT DATA BONGKARAN</h6>
                    
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="small fw-bold">Nama Barang</label>
                            <input type="text" name="nama_barang" class="form-control" value="<?= $data['nama_barang'] ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Asal / Kategori</label>
                            <select name="asal_bongkaran" class="form-select">
                                <option value="BENGKEL" <?= $data['asal_bongkaran'] == 'BENGKEL' ? 'selected' : '' ?>>BENGKEL</option>
                                <option value="KANTOR" <?= $data['asal_bongkaran'] == 'KANTOR' ? 'selected' : '' ?>>KANTOR</option>
                                <option value="BANGUNAN" <?= $data['asal_bongkaran'] == 'BANGUNAN' ? 'selected' : '' ?>>BANGUNAN</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Kondisi</label>
                            <select name="kondisi_barang" class="form-select">
                                <option value="BAGUS" <?= $data['kondisi_barang'] == 'BAGUS' ? 'selected' : '' ?>>BAGUS</option>
                                <option value="PERBAIKAN" <?= $data['kondisi_barang'] == 'PERBAIKAN' ? 'selected' : '' ?>>PERBAIKAN</option>
                                <option value="RUSAK" <?= $data['kondisi_barang'] == 'RUSAK' ? 'selected' : '' ?>>RUSAK</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"><?= $data['keterangan'] ?></textarea>
                        </div>
                        <div class="d-grid gap-2">
                        <button type="submit" name="update" class="btn btn-primary btn-lg shadow fw-bold"><i class="fas fa-save"></i> SIMPAN PERUBAHAN</button>
                        <a href="bongkaran.php" class="btn btn-lg btn-danger w-100"><i class="fas fa-rotate-left"></i> Kembali</a>
                        </div>
                    </form>

                   
                </div>
            </div>
        </div>
    </div>
</div>
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