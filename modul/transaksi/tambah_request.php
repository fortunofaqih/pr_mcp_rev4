<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Pastikan index session sesuai dengan yang Anda gunakan saat login (misal: 'username' atau 'nama')
$nama_user_login = isset($_SESSION['username']) ? strtoupper($_SESSION['username']) : "USER";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Request Baru - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f4f7f6; font-size: 0.85rem; }
        .card-header { background: white; border-bottom: 2px solid #eee; }
        .table-input thead { background: var(--mcp-blue); color: white; font-size: 0.75rem; text-transform: uppercase; }
        
        .table-responsive { border-radius: 8px; overflow-x: auto; }
        /* Lebar tabel disesuaikan karena beberapa kolom disembunyikan */
        .table-input { min-width: 1000px; table-layout: fixed; }
        
        .col-brg { width: 220px; }
        .col-kat { width: 140px; }
        .col-kwal { width: 160px; }
        .col-mbl { width: 130px; }
        .col-tip { width: 100px; }
        .col-qty { width: 80px; }
        .col-sat { width: 110px; }
        .col-hrg { width: 130px; }
        .col-tot { width: 130px; }
        .col-ket { width: 350px; }
        .col-aks { width: 50px; }

        input, select, textarea { text-transform: uppercase; font-size: 0.8rem !important; }
        .bg-autonumber { background-color: #e9ecef; border-style: dashed; color: #00008B; font-weight: bold; }
        
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 31px !important;
            padding: 2px 5px !important;
        }

        textarea.input-keterangan { 
            resize: vertical; 
            min-height: 35px; 
            line-height: 1.4;
        }

        @media (max-width: 768px) {
            .container-fluid { padding: 5px; }
            .fw-bold.m-0 { font-size: 1rem; }
        }
        textarea.input-keterangan:focus { min-height: 80px; transition: 0.3s; }
    </style>
</head>

<body class="py-4">
<div class="container-fluid">
    <form action="proses_simpan_request.php" method="POST">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header py-3">
                        <h5 class="fw-bold m-0 text-primary"><i class="fas fa-edit me-2"></i> PURCHASE REQUEST (PR) FORM</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <label class="small fw-bold text-muted">NOMOR REQUEST</label>
                                <input type="text" class="form-control bg-autonumber" value="[ GENERATE OTOMATIS ]" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold text-muted">TANGGAL REQUEST</label>
                                <input type="date" name="tgl_request" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-muted">ADMIN BAUT (PEMBUAT)</label>
                                <input type="text" name="nama_pemesan" class="form-control bg-light" value="<?= $nama_user_login ?>" readonly required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-primary">PETUGAS PEMBELIAN</label>
                                <select name="nama_pembeli" class="form-select select-pembeli" required>
                                    <option value="">-- PILIH PEMBELI --</option>
                                    <?php
                                    // Filter hanya yang bagian Pembelian atau role bagian_pembelian
                                    $user_beli = mysqli_query($koneksi, "SELECT nama_lengkap FROM users 
                                                                         WHERE status_aktif='AKTIF' 
                                                                         AND (role='bagian_pembelian' OR bagian='Pembelian') 
                                                                         ORDER BY nama_lengkap ASC");
                                    while($u = mysqli_fetch_array($user_beli)){
                                        echo "<option value='".strtoupper($u['nama_lengkap'])."'>".strtoupper($u['nama_lengkap'])."</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <hr>

                        <div class="table-responsive">
                            <table class="table table-bordered table-input align-middle" id="tableItem">
                                <thead>
                                    <tr class="text-center">
                                        <th class="col-brg">Nama Barang</th>
                                        <th class="col-kat">Kategori</th>
                                        <th class="col-mbl">Unit/Mobil</th>
                                        <th class="col-tip">Tipe</th>
                                        <th class="col-qty">Qty</th>
                                        <th class="col-sat">Satuan</th>
                                        <th class="col-ket">Keperluan / Ket. nama driver jika beda</th>
                                        <th class="col-aks"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="item-row">
                                        <td>
                                           <select name="id_barang[]" class="form-select form-select-sm select-barang" required>
                                        <option value="">-- PILIH BARANG --</option>
                                        <?php
                                        $brg = mysqli_query($koneksi, "SELECT * FROM master_barang WHERE status_aktif='AKTIF' AND is_active = 1 ORDER BY nama_barang ASC");
                                        while($b = mysqli_fetch_array($brg)){
                                            // VALUE SEKARANG ADALAH ID, BUKAN NAMA
                                            echo "<option value='".$b['id_barang']."' 
                                                    data-nama='".strtoupper($b['nama_barang'])."'
                                                    data-satuan='".strtoupper($b['satuan'])."' 
                                                    data-merk='".strtoupper($b['merk'])."' 
                                                    data-kategori='".strtoupper($b['kategori'])."'
                                                    data-harga='".$b['harga_barang_stok']."'>".$b['nama_barang']."</option>";
                                        }
                                        ?>
                                    </select>
                                    <input type="hidden" name="nama_barang_manual[]" class="input-nama-barang">
                                        </td>
                                        <td>
                                            <input type="text" name="kategori_request[]" class="form-control form-control-sm input-kategori-readonly bg-light" placeholder="Otomatis..." readonly>
                                        </td>
                                        <td>
                                            <select name="id_mobil[]" class="form-select form-select-sm select-mobil">
                                                <option value="0">NON MOBIL</option>
                                                <?php
                                                $mbl = mysqli_query($koneksi, "SELECT id_mobil, plat_nomor FROM master_mobil WHERE status_aktif='AKTIF' ORDER BY plat_nomor ASC");
                                                while($m = mysqli_fetch_array($mbl)){
                                                    echo "<option value='".$m['id_mobil']."'>".$m['plat_nomor']."</option>";
                                                }
                                                ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="tipe_request[]" class="form-select form-select-sm select-tipe">
                                                <option value="STOK">STOK</option>
                                                <option value="LANGSUNG">LANGSUNG</option>
                                            </select>
                                        </td>
                                        <td><input type="number" name="jumlah[]" class="form-control form-control-sm input-qty text-center" step="any" value="1" required></td>
                                        <td>
                                           <input type="text" name="satuan[]" class="form-control form-control-sm input-satuan-readonly bg-light" placeholder="Otomatis..." readonly>
                                        </td>
                                        <input type="hidden" name="kwalifikasi[]" class="input-kwalifikasi">
                                        <input type="hidden" name="harga[]" class="input-harga" value="0">
                                        <input type="hidden" class="input-subtotal" value="0">

                                        <td>
                                            <textarea name="keterangan[]" class="form-control form-control-sm input-keterangan" rows="1" placeholder="Catatan detail..."></textarea>
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row border-0"><i class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <button type="button" id="addRow" class="btn btn-sm btn-success fw-bold px-3 mt-2 shadow-sm">
                            <i class="fas fa-plus me-1"></i> Tambah Baris
                        </button>

                        </div>

                    <div class="card-footer bg-white py-3">
                        <button type="submit" class="btn btn-primary fw-bold px-5 shadow-sm">
                            <i class="fas fa-save me-1"></i> SIMPAN REQUEST
                        </button>
                       <a href="../../index.php" class="btn btn-danger fw-bold px-4">BATAL</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function(){
    
    // 1. Inisialisasi Select2
    function initSelect2() {
        // Hanya inisialisasi pada elemen yang memang butuh pencarian (bukan readonly)
        $('.select-barang, .select-mobil, .select-tipe, .select-pembeli').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: "-- PILIH --"
        });
    }
    initSelect2();

    // 2. Fungsi hitung subtotal (opsional untuk PR Kecil)
    function hitungSubtotal(row) {
        var qty = parseFloat(row.find('.input-qty').val()) || 0;
        var harga = parseFloat(row.find('.input-harga').val()) || 0;
        var subtotal = qty * harga;
        row.find('.input-subtotal').val(subtotal); 
    }

    // 3. Event saat Barang dipilih
    $(document).on('change', '.select-barang', function(){
        var row = $(this).closest('tr');
        var selected = $(this).find(':selected');
        
        if (selected.val() != "") {
            // Isi nama barang ke hidden input
            row.find('.input-nama-barang').val(selected.data('nama')); 
            
            // Isi Merk/Spek ke hidden input
            row.find('.input-kwalifikasi').val(selected.data('merk'));
            
            // Isi Harga estimasi ke hidden input
            row.find('.input-harga').val(selected.data('harga'));
            
            // AUTOMATIC FILL: Kategori & Satuan (Input Teks Readonly)
            row.find('.input-kategori-readonly').val(selected.data('kategori'));
            row.find('.input-satuan-readonly').val(selected.data('satuan'));
        } else {
            // Reset jika pilihan dikosongkan
            row.find('.input-kategori-readonly, .input-satuan-readonly, .input-nama-barang').val('');
        }
        
        hitungSubtotal(row);
    });

    // 4. Tambah Baris Baru
    $("#addRow").click(function(){
        // Clone baris terakhir
        var newRow = $('.item-row:last').clone(); 
        
        // Hapus container Select2 yang lama agar tidak error saat inisialisasi ulang
        newRow.find('.select2-container').remove();
        newRow.find('.select2-hidden-accessible')
              .removeClass('select2-hidden-accessible')
              .removeAttr('data-select2-id')
              .attr('tabindex', '0');
        
        // Reset semua nilai input di baris baru
        newRow.find('input').val('');
        newRow.find('textarea').val('');
        newRow.find('.input-qty').val('1');
        newRow.find('.input-subtotal').val('0');
        newRow.find('.input-harga').val('0');
        
        // Reset select ke default
        newRow.find('select').val('');
        newRow.find('.select-mobil').val('0');
        newRow.find('.select-tipe').val('STOK');
        
        // Masukkan baris baru ke tabel
        $("#tableItem tbody").append(newRow);
        
        // Jalankan ulang Select2 untuk baris yang baru
        initSelect2();
    });

    // 5. Hapus Baris
    $(document).on('click', '.remove-row', function(){
        if($("#tableItem tbody tr").length > 1){
            $(this).closest('tr').remove();
        } else {
            Swal.fire('Info', 'Minimal harus ada 1 item dalam request.', 'info');
        }
    });

    // 6. Validasi & Proses Simpan (Submit)
    $('form').on('submit', function(e) {
        e.preventDefault();
        var form = this;

        // Cek apakah barang pertama sudah dipilih
        if (!$('.select-barang').first().val()) {
            Swal.fire('Peringatan', 'Mohon pilih minimal satu barang.', 'warning');
            return false;
        }

        Swal.fire({
            title: 'Simpan Purchase Request?',
            text: "Pastikan data item dan kategori sudah sesuai.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0000FF',
            confirmButtonText: 'Ya, Simpan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Sedang Memproses...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                form.submit();
            }
        });
    });

    // 7. Hitung ulang jika qty diubah manual
    $(document).on('input', '.input-qty', function(){
        hitungSubtotal($(this).closest('tr'));
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