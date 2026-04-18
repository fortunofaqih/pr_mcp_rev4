<?php
include "../../config/koneksi.php";
include '../../auth/check_session.php';

if (isset($_POST['simpan'])) {
    $tgl_beli    = $_POST['tgl_beli'];
    $nama_barang = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_barang']));
    $merk        = mysqli_real_escape_string($koneksi, strtoupper($_POST['merk']));
    $supplier    = mysqli_real_escape_string($koneksi, strtoupper($_POST['supplier']));
    
    // Membersihkan format titik pada harga sebelum simpan ke database
    $harga       = str_replace('.', '', $_POST['harga']); 
    
    $alokasi     = $_POST['alokasi'];
    $keterangan  = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    
    // Penanda khusus untuk data perbandingan
    $no_request  = "COMP-" . date('YmdHis'); 

    // Query ditambah kolom keterangan
    $query = "INSERT INTO perbandingan_harga (tgl_data, nama_barang, merk, supplier, harga, alokasi_stok, no_request, sumber_data, keterangan) 
              VALUES ('$tgl_beli', '$nama_barang', '$merk', '$supplier', '$harga', '$alokasi', '$no_request', 'MANUAL', '$keterangan')";
    
    if (mysqli_query($koneksi, $query)) {
        echo "<script>alert('Data Perbandingan Berhasil Disimpan!'); window.location='data_perbandingan.php';</script>";
    } else {
        echo "Error: " . mysqli_error($koneksi);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Perbandingan - MCP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
        .card { border-radius: 15px; border: none; }
    </style>
</head>
<body class="py-5">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card shadow-lg">
                <div class="card-header bg-success text-white p-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-balance-scale me-2"></i> INPUT PERBANDINGAN HARGA</h5>
                </div>
                <div class="card-body p-4">
                    <form action="" method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">TANGGAL BELI</label>
                                <input type="date" name="tgl_beli" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">ALOKASI</label>
                                <select name="alokasi" class="form-select">
                                    <option value="LANGSUNG PAKAI">LANGSUNG PAKAI</option>
                                    <option value="MASUK STOK">MASUK STOK</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">NAMA BARANG</label>
                            <select name="nama_barang" id="select_barang" class="form-select" required>
                                <option value="">-- CARI BARANG --</option>
                                <?php
                                $res = mysqli_query($koneksi, "SELECT nama_barang, merk FROM master_barang ORDER BY nama_barang ASC");
                                while($row = mysqli_fetch_array($res)) {
                                    echo "<option value='".$row['nama_barang']."' data-merk='".$row['merk']."'>".$row['nama_barang']."</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">MERK</label>
                            <input type="text" name="merk" id="input_merk" class="form-control" placeholder="Merk...">
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">SUPPLIER / TOKO</label>
                            <input type="text" name="supplier" class="form-control" placeholder="Contoh: TB. MAJU JAYA" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">HARGA SATUAN (RP)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">Rp</span>
                                    <input type="text" name="harga" id="input_harga" class="form-control" placeholder="0" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold">KETERANGAN</label>
                                <input type="text" name="keterangan" class="form-control" placeholder="Catatan tambahan...">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between pt-3 border-top">
                            <a href="data_perbandingan.php" class="btn btn-outline-secondary px-4 fw-bold">BATAL</a>
                            <button type="submit" name="simpan" class="btn btn-success px-5 fw-bold shadow">SIMPAN PERBANDINGAN</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Inisialisasi Select2
    $('#select_barang').select2({ theme: 'bootstrap-5', placeholder: '-- CARI NAMA BARANG --', allowClear: true });
    
    $('#select_barang').on('select2:select', function (e) {
        var merk = $(e.currentTarget).find(':selected').data('merk');
        $('#input_merk').val(merk);
    });

    // Event Listener untuk format rupiah
    $('#input_harga').on('keyup', function() {
        $(this).val(formatRupiah($(this).val()));
    });

    // Fungsi format rupiah
    function formatRupiah(angka, prefix) {
        var number_string = angka.replace(/[^,\d]/g, '').toString(),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return prefix == undefined ? rupiah : (rupiah ? 'Rp. ' + rupiah : '');
    }
});
</script>
</body>
</html>