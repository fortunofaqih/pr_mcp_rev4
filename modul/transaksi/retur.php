<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

// Proteksi Login
if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// --- LOGIKA FILTER ---
$tgl_awal  = isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : date('Y-m-01');
$tgl_akhir = isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : date('Y-m-d');

// Generate No. Retur Otomatis
$bulan_sekarang = date('Ym');
$prefix = "RT-" . $bulan_sekarang . "-";
$query_no = mysqli_query($koneksi, "SELECT no_retur FROM tr_retur WHERE no_retur LIKE '$prefix%' ORDER BY no_retur DESC LIMIT 1");
$data_no = mysqli_fetch_array($query_no);

if ($data_no) {
    $no_urut = (int) substr($data_no['no_retur'], -4);
    $no_urut++;
} else {
    $no_urut = 1;
}
$no_retur = $prefix . sprintf("%04s", $no_urut);

// ==========================================
// PROSES SIMPAN RETUR (UPDATE 5 TABEL)
// ==========================================
if(isset($_POST['simpan_retur'])){
    $no_rt      = $_POST['no_retur'];
    $tgl        = $_POST['tgl_retur'];
    $jenis      = $_POST['jenis_retur']; 
    $id_bon     = $_POST['id_bon']; // ID dari tabel bon_permintaan
    $qty        = $_POST['qty_retur'];
    $pengembali = mysqli_real_escape_string($koneksi, strtoupper($_POST['pengembali']));
    $alasan     = mysqli_real_escape_string($koneksi, strtoupper($_POST['alasan_retur']));
    $id_user    = $_SESSION['id_user'];
    $user_nama  = $_SESSION['nama_lengkap'];

    mysqli_begin_transaction($koneksi);
    try {
        // A. Ambil info Detail Barang & Bon terlebih dahulu
        $q_info = mysqli_query($koneksi, "SELECT b.no_permintaan, b.id_barang, m.nama_barang, b.qty_keluar 
                                         FROM bon_permintaan b 
                                         JOIN master_barang m ON b.id_barang = m.id_barang 
                                         WHERE b.id_bon = '$id_bon'");
        $d_info = mysqli_fetch_assoc($q_info);
        
        if (!$d_info) throw new Exception("Data referensi bon tidak ditemukan.");
        
        $id_barang = $d_info['id_barang'];
        $no_req    = $d_info['no_permintaan'];
        $nm_brg    = $d_info['nama_barang'];

        // 1. INSERT ke tr_retur
        $query_retur = "INSERT INTO tr_retur (no_retur, tgl_retur, jenis_retur, id_barang, qty_retur, alasan_retur, pengembali, id_user) 
                        VALUES ('$no_rt', '$tgl', '$jenis', '$id_barang', '$qty', '$alasan', '$pengembali', '$id_user')";
        if (!mysqli_query($koneksi, $query_retur)) throw new Exception("Gagal simpan tr_retur");

        // 2. UPDATE master_barang (Kembalikan Stok)
        mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty WHERE id_barang='$id_barang'");

        // 3. INSERT tr_stok_log (Log Kartu Stok)
        $ket_log = "RETUR ($jenis): DARI $pengembali (REF: $no_req) - $alasan";
        $tgl_full = $tgl . " " . date('H:i:s');
        mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                                VALUES ('$id_barang', '$tgl_full', 'RETUR', '$qty', '$ket_log', '$user_nama')");

        // 4. INSERT log_retur (Audit Log Retur)
        mysqli_query($koneksi, "INSERT INTO log_retur (tgl_retur, no_request, nama_barang_retur, qty_retur, alasan_retur, eksekutor_retur) 
                                VALUES ('$tgl_full', '$no_req', '$nm_brg', '$qty', '$alasan', '$user_nama')");

        // 5. UPDATE/DELETE bon_permintaan (Agar di pengambilan.php data berkurang/hilang)
        mysqli_query($koneksi, "UPDATE bon_permintaan SET qty_keluar = qty_keluar - $qty WHERE id_bon = '$id_bon'");
        // Jika sisa qty di bon jadi 0 atau kurang, hapus recordnya agar tidak tampil lagi
        mysqli_query($koneksi, "DELETE FROM bon_permintaan WHERE id_bon = '$id_bon' AND qty_keluar <= 0");

        mysqli_commit($koneksi);
        echo "<script>alert('Berhasil! Retur diproses dan stok telah diperbarui.'); window.location='retur.php';</script>";
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}

// Proses Hapus Retur (Hanya menghapus record tr_retur & koreksi stok jika diperlukan)
if(isset($_GET['hapus'])){
    $id_retur = mysqli_real_escape_string($koneksi, $_GET['hapus']);
    mysqli_query($koneksi, "DELETE FROM tr_retur WHERE id_retur='$id_retur'");
    echo "<script>alert('Data retur berhasil dihapus.'); window.location='retur.php';</script>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Retur Barang - MCP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-orange: #FF8C00; }
        body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .bg-orange { background-color: var(--mcp-orange) !important; color: white; }
        .btn-orange { background-color: var(--mcp-orange); color: white; border: none; transition: 0.3s; }
        .btn-orange:hover { background-color: #e67e00; color: white; transform: translateY(-1px); }
        .table thead { background-color: #f8f9fa; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; }
        input, select, textarea { text-transform: uppercase; }
        .filter-box { background: #fff; padding: 15px; border-radius: 10px; margin-bottom: 20px; border: 1px solid #eee; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid">
    <div class="filter-box">
        <div class="row align-items-center">
            <div class="col-md-4">
                <h5 class="m-0 fw-bold text-dark"><i class="fas fa-history me-2 text-warning"></i>TRANSAKSI RETUR</h5>
            </div>
            <div class="col-md-8 text-end">
                <form method="GET" class="row g-2 justify-content-end d-inline-flex">
                    <div class="col-auto">
                        <input type="date" name="tgl_awal" class="form-control form-control-sm" value="<?= $tgl_awal ?>">
                    </div>
                    <div class="col-auto pt-1">s/d</div>
                    <div class="col-auto">
                        <input type="date" name="tgl_akhir" class="form-control form-control-sm" value="<?= $tgl_akhir ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-dark px-3"><i class="fas fa-filter me-1"></i> Filter</button>
                        <a href="retur.php" class="btn btn-sm btn-outline-secondary px-3">Reset</a>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-sm btn-orange fw-bold px-3" data-bs-toggle="modal" data-bs-target="#modalRetur">
                            <i class="fas fa-plus-circle me-1"></i> INPUT RETUR
                        </button>
                    </div>
                    <div class="col-auto">
                        <a href="../../index.php" class="btn btn-sm btn-danger px-2"><i class="fas fa-rotate-left"></i> Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" id="tabelRetur">
                    <thead>
                        <tr class="text-center">
                            <th>NO. RETUR</th>
                            <th>TANGGAL</th>
                            <th>NAMA BARANG</th>
                            <th>QTY</th>
                            <th>PENGEMBALI</th>
                            <th>PENERIMA</th>
                            <th>ALASAN</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT r.*, m.nama_barang, m.satuan, u.nama_lengkap 
                                FROM tr_retur r 
                                JOIN master_barang m ON r.id_barang = m.id_barang 
                                JOIN users u ON r.id_user = u.id_user 
                                WHERE r.tgl_retur BETWEEN '$tgl_awal' AND '$tgl_akhir'
                                ORDER BY r.id_retur DESC";
                        $histori = mysqli_query($koneksi, $sql);
                        while($h = mysqli_fetch_array($histori)):
                        ?>
                        <tr>
                            <td class="text-center fw-bold text-primary"><?= $h['no_retur'] ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($h['tgl_retur'])) ?></td>
                            <td class="fw-bold"><?= $h['nama_barang'] ?></td>
                            <td class="text-center fw-bold text-success">+ <?= number_format($h['qty_retur'], 2) ?> <small><?= $h['satuan'] ?></small></td>
                            <td><?= $h['pengembali'] ?></td>
                            <td class="text-muted small"><?= $h['nama_lengkap'] ?></td>
                            <td class="small"><?= $h['alasan_retur'] ?></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <button type="button" onclick="cetakRetur('<?= $h['id_retur'] ?>')" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <a href="retur.php?hapus=<?= $h['id_retur'] ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus data rekap retur ini?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetur" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-orange text-white">
                <h6 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>FORM PENGEMBALIAN BARANG</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">NO. RETUR</label>
                            <input type="text" name="no_retur" class="form-control fw-bold text-danger bg-light" value="<?= $no_retur ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">TANGGAL</label>
                            <input type="date" name="tgl_retur" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold">JENIS RETUR</label>
                            <select name="jenis_retur" class="form-select" required>
                                <option value="BATAL PAKAI">BATAL PAKAI</option>
                                <option value="SALAH AMBIL">SALAH AMBIL BARANG</option>
                                <option value="KELEBIHAN JUMLAH">KELEBIHAN JUMLAH</option>
                                <option value="BARANG RUSAK">BARANG RUSAK</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">PILIH DARI BON PENGAMBILAN</label>
                            <select name="id_bon" id="pilihBon" class="form-select select2" required onchange="setQtyLimit(this)">
                                <option value=""></option> 
                                <?php
                                $sql_bon = "SELECT b.id_bon, b.no_permintaan, b.qty_keluar, b.penerima, m.nama_barang, m.satuan 
                                            FROM bon_permintaan b
                                            JOIN master_barang m ON b.id_barang = m.id_barang
                                            WHERE b.qty_keluar > 0
                                            ORDER BY b.tgl_keluar DESC";
                                $res_bon = mysqli_query($koneksi, $sql_bon);
                                while($b = mysqli_fetch_array($res_bon)){
                                    echo "<option value='{$b['id_bon']}' data-max='{$b['qty_keluar']}'>
                                            {$b['no_permintaan']} - {$b['nama_barang']} (Sisa: {$b['qty_keluar']} {$b['satuan']})
                                          </option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="p-3 bg-light border-start border-warning border-4">
                                <label class="small fw-bold">JUMLAH (QTY) YANG DIKEMBALIKAN</label>
                                <input type="number" name="qty_retur" id="qty_retur" class="form-control form-control-lg fw-bold" placeholder="0.00" step="0.01" min="0.01" required>
                                <div id="limit_info" class="form-text text-danger"></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">YANG MENGEMBALIKAN (NAMA PERSONEL)</label>
                        <input type="text" name="pengembali" class="form-control" placeholder="Contoh: BUDIYONO (WORKSHOP)" required>
                    </div>
                    <div class="mb-0">
                        <label class="small fw-bold">ALASAN DETAIL</label>
                        <textarea name="alasan_retur" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">BATAL</button>
                    <button type="submit" name="simpan_retur" class="btn btn-sm btn-orange px-4">SIMPAN DATA RETUR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {
        $('#tabelRetur').DataTable({
            "order": [[0, "desc"]],
            "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json" }
        });

        $('#pilihBon').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#modalRetur'),
            placeholder: 'Cari No. Bon / Nama Barang...'
        });
    });

    function setQtyLimit(select) {
        const selected = select.options[select.selectedIndex];
        const maxVal = selected.getAttribute('data-max');
        const qtyInput = document.getElementById('qty_retur');
        const info = document.getElementById('limit_info');
        
        if(maxVal) {
            qtyInput.max = maxVal;
            info.innerHTML = "* Maksimal jumlah yang bisa diretur: " + maxVal;
        } else {
            info.innerHTML = "";
        }
    }

    function cetakRetur(id) {
        const width = 800;
        const height = 600;
        const left = (screen.width - width) / 2;
        const top = (screen.height - height) / 2;
        window.open('cetak_retur.php?id=' + id, 'Cetak Retur', `width=${width},height=${height},left=${left},top=${top}`);
    }
</script>
</body>
</html>