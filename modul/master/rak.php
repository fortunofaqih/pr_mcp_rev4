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
    <title>Master Rak - MCP System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f4f7f6; }
        .card-input { max-width: 500px; margin: 50px auto; border-radius: 15px; overflow: hidden; }
        .bg-mcp { background-color: #0000FF; color: white; }
        input { text-transform: uppercase; }
    </style>
</head>
<body>

<div class="container">
    <div class="card card-input shadow">
        <div class="card-header bg-mcp py-3 text-center">
            <h5 class="m-0 fw-bold"><i class="fas fa-box me-2"></i> TAMBAH MASTER RAK</h5>
        </div>
        <div class="card-body p-4">
            <form action="proses_rak.php" method="POST">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">NAMA RAK / LOKASI</label>
                    <input type="text" name="nama_rak" class="form-control form-control-lg" placeholder="masukkan nama rak atau lokasi" required autofocus>
                </div>

                <div class="row g-2">
                    <div class="col-12 mb-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                            <i class="fas fa-save me-2"></i> SIMPAN RAK
                        </button>
                    </div>
                    <div class="col-12">
                        <a href="data_rak.php" class="btn btn-outline-secondary w-100 fw-bold">
                            <i class="fas fa-arrow-left me-2"></i> KEMBALI KE DATA RAK
                        </a>
                    </div>

                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('pesan') === 'berhasil') {
        Swal.fire({ icon: 'success', title: 'BERHASIL!', text: 'Data telah disimpan.', confirmButtonColor: '#0000FF' });
    }
</script>
</body>
</html>