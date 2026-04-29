<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

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
    <title>Data Master Satuan  - MCP System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f4f7f6; }
        .bg-mcp { background-color: #0000FF; color: white; }
        .card { border-radius: 15px; }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-mcp py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="m-0 fw-bold"><i class="fas fa-layer-group me-2"></i> DATA MASTER SATUAN</h5>
            <div class="d-flex gap-2">
                <a href="barang.php" class="btn btn-danger fw-bold"><i class="fas fa-arrow-left"></i> KEMBALI</a>
                <a href="satuan.php" class="btn btn-light btn-sm fw-bold"><i class="fas fa-plus"></i> TAMBAH SATUAN</a>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered text-center">
                    <thead class="table-light">
                        <tr>
                            <th width="10%">NO</th>
                            <th>NAMA SATUAN</th>
                            <th width="20%">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        $data = mysqli_query($koneksi, "SELECT * FROM master_satuan ORDER BY nama_satuan ASC");
                        while($d = mysqli_fetch_array($data)){
                        ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td class="fw-bold text-primary"><?= $d['nama_satuan']; ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm fw-bold text-white" onclick="editSatuan(<?= $d['id_satuan']; ?>, '<?= $d['nama_satuan']; ?>')">
                                    <i class="fas fa-edit"></i> EDIT
                                </button>
                                
                                <button class="btn btn-danger btn-sm fw-bold" onclick="hapusSatuan(<?= $d['id_satuan']; ?>)">
                                    <i class="fas fa-trash"></i> HAPUS
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</div>

<script>
// Fungsi Edit menggunakan SweetAlert Input
function editSatuan(id, namaLama) {
    Swal.fire({
        title: 'Edit Nama Satuan',
        input: 'text',
        inputValue: namaLama,
        showCancelButton: true,
        confirmButtonText: 'Update',
        confirmButtonColor: '#0000FF',
        inputValidator: (value) => {
            if (!value) return 'Nama satuan tidak boleh kosong!'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `update_satuan.php?id=${id}&nama_satuan=${result.value.toUpperCase()}`;
        }
    })
}

// Fungsi Hapus dengan Konfirmasi
function hapusSatuan(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data satuan akan dihapus permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `hapus_satuan.php?id=${id}`;
        }
    })
}

// Notifikasi pesan
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('pesan') === 'update') {
    Swal.fire('Berhasil!', 'Data satuan telah diperbarui.', 'success');
} else if (urlParams.get('pesan') === 'hapus') {
    Swal.fire('Terhapus!', 'Data satuan telah dihapus.', 'success');
} else if (urlParams.get('pesan') === 'gagal') {
    Swal.fire('Gagal!', 'Terjadi kesalahan sistem.', 'error');
}
window.history.replaceState({}, document.title, window.location.pathname);
</script>

</body>
</html>