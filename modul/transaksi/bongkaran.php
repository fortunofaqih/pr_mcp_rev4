<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// --- LOGIKA PROSES (SAMA SEPERTI SEBELUMNYA) ---
if(isset($_POST['proses_pakai'])){
    $id_bongkaran = $_POST['id_bongkaran'];
    $qty_ambil    = $_POST['qty_ambil'];
    $penerima     = mysqli_real_escape_string($koneksi, strtoupper($_POST['penerima']));
    $keperluan    = mysqli_real_escape_string($koneksi, strtoupper($_POST['keperluan']));
    $tgl_pakai    = $_POST['tgl_pakai'];

    mysqli_begin_transaction($koneksi);
    try {
        $cek = mysqli_fetch_array(mysqli_query($koneksi, "SELECT qty_sisa FROM tr_bongkaran WHERE id_bongkaran='$id_bongkaran' FOR UPDATE"));
        
        if($qty_ambil > 0 && $qty_ambil <= $cek['qty_sisa']){
            mysqli_query($koneksi, "UPDATE tr_bongkaran SET qty_sisa = qty_sisa - $qty_ambil WHERE id_bongkaran='$id_bongkaran'");
            mysqli_query($koneksi, "INSERT INTO tr_bongkaran_keluar (id_bongkaran, tgl_keluar, qty_keluar, penerima, keperluan) 
                                    VALUES ('$id_bongkaran', '$tgl_pakai', '$qty_ambil', '$penerima', '$keperluan')");
            
            mysqli_commit($koneksi);
            echo "<script>alert('Berhasil!'); window.location='bongkaran.php';</script>";
        } else {
            throw new Exception("Stok tidak cukup");
        }
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Gagal: " . $e->getMessage() . "'); window.location='bongkaran.php';</script>";
    }
}

// --- LOGIKA PROSES SIMPAN (Ditambah uppercase otomatis untuk satuan) ---
if(isset($_POST['simpan'])){
    $tgl = $_POST['tgl_bongkar'];
    $asal = $_POST['asal_bongkaran'];
    $nama = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_barang']));
    $qty = (int)$_POST['qty_bongkar'];
    $satuan = mysqli_real_escape_string($koneksi, strtoupper($_POST['satuan_bongkar']));
    $kondisi = $_POST['kondisi_barang'];
    $ket = mysqli_real_escape_string($koneksi, strtoupper($_POST['keterangan']));
    
    // Gunakan prepared statement atau minimal validasi angka
    $query = "INSERT INTO tr_bongkaran (tgl_bongkar, asal_bongkaran, nama_barang, qty_bongkar, qty_sisa, satuan_bongkar, kondisi_barang, keterangan) 
              VALUES ('$tgl', '$asal', '$nama', '$qty', '$qty', '$satuan', '$kondisi', '$ket')";
    
    if(mysqli_query($koneksi, $query)) {
        echo "<script>alert('Data Bongkaran Berhasil Tersimpan!'); window.location='bongkaran.php';</script>";
    }
}

// --- LOGIKA HAPUS (Ditambah Proteksi) ---
if(isset($_GET['hapus'])){
    $id = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    
    // Cek apakah barang sudah pernah dipakai
    $cek_pakai = mysqli_query($koneksi, "SELECT qty_bongkar, qty_sisa FROM tr_bongkaran WHERE id_bongkaran='$id'");
    $data_cek = mysqli_fetch_array($cek_pakai);
    
    if($data_cek['qty_bongkar'] != $data_cek['qty_sisa']) {
        echo "<script>alert('Gagal! Barang ini sudah memiliki riwayat pengambilan dan tidak bisa dihapus.'); window.location='bongkaran.php';</script>";
    } else {
        mysqli_query($koneksi, "DELETE FROM tr_bongkaran WHERE id_bongkaran='$id'");
        header("location:bongkaran.php");
    }
}

$bln_filter = $_GET['bulan'] ?? date('m');
$thn_filter = $_GET['tahun'] ?? date('Y');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Bongkaran - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.9rem; }
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1); border-radius: 12px; }
        .table thead { background-color: var(--mcp-blue); color: white; }
        .btn-mcp { background-color: var(--mcp-blue); color: white; border-radius: 6px; }
        .btn-mcp:hover { background-color: #0000CC; color: white; }
        input, select, textarea { text-transform: uppercase; }
        .sticky-header { position: sticky; top: 0; z-index: 1000; background: white; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center g-3">
                <div class="col-md-3">
                    <h5 class="m-0 fw-bold text-dark"><i class="fas fa-boxes-stacked me-2 text-primary"></i>STOK BONGKARAN</h5>
                </div>
                
                <div class="col-md-9">
                    <div class="d-flex flex-wrap justify-content-md-end gap-2">
                        <div class="input-group input-group-sm" style="width: 200px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Cari barang...">
                        </div>

                        <form action="" method="GET" class="d-flex gap-1">
                            <select name="bulan" class="form-select form-select-sm w-auto">
                                <?php for($m=1;$m<=12;$m++){
                                    $v=sprintf('%02d',$m);
                                    $s=($v==$bln_filter)?'selected':'';
                                    echo"<option value='$v' $s>".date('M',mktime(0,0,0,$m,1))."</option>";
                                } ?>
                            </select>
                            <select name="tahun" class="form-select form-select-sm w-auto">
                                <?php for($y=date('Y');$y>=2024;$y--){
                                    $s=($y==$thn_filter)?'selected':'';
                                    echo"<option value='$y' $s>$y</option>";
                                } ?>
                            </select>
                            <button type="submit" class="btn btn-sm btn-dark">Filter</button>
                        </form>
                        <a href="../../index.php" class="btn btn-danger px-2"><i class="fas fa-rotate-left"></i>Kembali</a>
                        <button type="button" class="btn btn-sm btn-mcp px-3" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus-circle me-1"></i> INPUT BARANG
                        </button>
                        
                        
                        <a href="laporan_pengambilan.php" class="btn btn-sm btn-warning px-3"><i class="fas fa-file-lines me-1"></i> Report</a>
                        
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="dataTable">
                    <thead>
                        <tr>
                            <th class="ps-3">Nama Barang</th>
                            <th>Asal</th>
                            <th class="text-center">Sisa Stok</th>
                            <th class="text-center">Kondisi</th>
                            <th>Keterangan</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q = mysqli_query($koneksi, "SELECT * FROM tr_bongkaran WHERE MONTH(tgl_bongkar) = '$bln_filter' AND YEAR(tgl_bongkar) = '$thn_filter' AND qty_sisa > 0 ORDER BY nama_barang ASC");
                        if(mysqli_num_rows($q) > 0){
                            while($d = mysqli_fetch_array($q)):
                        ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= $d['nama_barang'] ?></td>
                            <td><span class="badge bg-light text-primary border"><?= $d['asal_bongkaran'] ?></span></td>
                            <td class="text-center fw-bold text-primary fs-6"><?= $d['qty_sisa'] ?> <?= $d['satuan_bongkar'] ?></td>
                            <td class="text-center">
                                <span class="badge <?= ($d['kondisi_barang']=='BAGUS')?'bg-success':(($d['kondisi_barang']=='PERBAIKAN')?'bg-warning text-dark':'bg-danger') ?>">
                                    <?= $d['kondisi_barang'] ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= $d['keterangan'] ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAmbil" 
                                    data-id="<?= $d['id_bongkaran'] ?>" data-nama="<?= $d['nama_barang'] ?>" 
                                    data-sisa="<?= $d['qty_sisa'] ?>" data-satuan="<?= $d['satuan_bongkar'] ?>">
                                    <i class="fas fa-hand-holding"></i> Ambil
                                </button>
                                <a href="edit_bongkaran.php?id=<?= $d['id_bongkaran'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                                <a href="bongkaran.php?hapus=<?= $d['id_bongkaran'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; } else { echo "<tr><td colspan='6' class='text-center py-5 text-muted'>Data tidak ditemukan.</td></tr>"; } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-mcp text-white py-2">
                <h6 class="modal-title">INPUT BARANG BONGKARAN</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small fw-bold">Tgl Masuk</label>
                            <input type="date" name="tgl_bongkar" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Kategori/Asal</label>
                            <select name="asal_bongkaran" class="form-select form-select-sm" required>
                                <option value="BENGKEL">BENGKEL</option>
                                <option value="KANTOR">KANTOR</option>
                                <option value="BANGUNAN">BANGUNAN</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold">Nama Barang</label>
                        <input type="text" name="nama_barang" class="form-control form-control-sm" placeholder="Contoh: Lampu Philips 20W" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="small fw-bold">Jumlah</label>
                            <input type="number" name="qty_bongkar" class="form-control form-control-sm" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Satuan</label>
                            <input type="text" name="satuan_bongkar" class="form-control form-control-sm" placeholder="PCS / UNIT" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold">Kondisi</label>
                        <select name="kondisi_barang" class="form-select form-select-sm">
                            <option value="BAGUS">BAGUS (LAYAK PAKAI)</option>
                            <option value="PERBAIKAN">PERLU PERBAIKAN</option>
                            <option value="RUSAK">RUSAK / AFVAL</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="small fw-bold">Keterangan Tambahan</label>
                        <textarea name="keterangan" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-danger px-4 fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan" class="btn btn-sm btn-mcp px-4 fw-bold">SIMPAN DATA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAmbil" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title">PENGAMBILAN BARANG</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_bongkaran" id="m_id">
                    <div class="mb-2 text-center">
                        <span class="small text-muted d-block">Barang yang akan diambil:</span>
                        <h5 id="m_nama" class="fw-bold text-dark"></h5>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">Tgl Keluar</label>
                            <input type="date" name="tgl_pakai" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">Qty Ambil</label>
                            <div class="input-group input-group-sm">
                                <input type="number" name="qty_ambil" id="m_qty" class="form-control" min="1" required>
                                <span class="input-group-text" id="m_satuan"></span>
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="small fw-bold">Nama Penerima</label>
                        <input type="text" name="penerima" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-0">
                        <label class="small fw-bold">Keperluan / Lokasi Pasang</label>
                        <textarea name="keperluan" class="form-control form-control-sm" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="submit" name="proses_pakai" class="btn btn-sm btn-success w-100 fw-bold">PROSES PENGELUARAN BARANG</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Live Search
    document.getElementById('searchInput').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('#dataTable tbody tr').forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(val) ? '' : 'none';
        });
    });

    // Bridge Data ke Modal Ambil
    const modalAmbil = document.getElementById('modalAmbil');
    modalAmbil.addEventListener('show.bs.modal', e => {
        const b = e.relatedTarget;
        document.getElementById('m_id').value = b.getAttribute('data-id');
        document.getElementById('m_nama').innerText = b.getAttribute('data-nama');
        document.getElementById('m_qty').max = b.getAttribute('data-sisa');
        document.getElementById('m_satuan').innerText = b.getAttribute('data-satuan');
    });
</script>
</body>
</html>