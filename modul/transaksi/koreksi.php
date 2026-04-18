<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// Proses Simpan Koreksi
if(isset($_POST['simpan_koreksi'])){
    $tgl            = $_POST['tgl_koreksi'];
    $id_barang      = $_POST['id_barang'];
    $stok_sesudah   = (float)$_POST['stok_sesudah']; // Angka riil hasil hitung fisik
    $alasan         = mysqli_real_escape_string($koneksi, strtoupper($_POST['alasan_koreksi']));
    $id_user        = $_SESSION['id_user'];
    $user_nama      = $_SESSION['nama'];

    mysqli_begin_transaction($koneksi);

    try {
        // 1. AMBIL STOK TERAKHIR DARI DATABASE (Jangan percaya input form untuk stok lama)
        $q_cek = mysqli_query($koneksi, "SELECT stok_akhir FROM master_barang WHERE id_barang = '$id_barang' FOR UPDATE");
        $d_cek = mysqli_fetch_array($q_cek);
        $stok_sebelum = $d_cek['stok_akhir'];

        // 2. HITUNG SELISIH
        $selisih = $stok_sesudah - $stok_sebelum;
        $tipe_koreksi = ($selisih > 0) ? "TAMBAH" : (($selisih < 0) ? "KURANG" : "PENYESUAIAN");

        // 3. Simpan ke tabel tr_koreksi
        mysqli_query($koneksi, "INSERT INTO tr_koreksi (tgl_koreksi, id_barang, stok_sebelum, stok_sesudah, selisih, tipe_koreksi, alasan_koreksi, id_user) 
                                VALUES ('$tgl', '$id_barang', '$stok_sebelum', '$stok_sesudah', '$selisih', '$tipe_koreksi', '$alasan', '$id_user')");
        
        // 4. Update stok di master_barang menjadi angka hasil opname
        mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = '$stok_sesudah' WHERE id_barang='$id_barang'");

        // 5. Catat ke Kartu Stok (tr_stok_log) agar sinkron
        if ($selisih != 0) {
            $qty_log = abs($selisih);
            $tipe_log = ($selisih > 0) ? "MASUK" : "KELUAR";
            $tgl_full = $tgl . " " . date('H:i:s');
            $ket_log = "KOREKSI/OPNAME: $alasan (Stok awal: $stok_sebelum)";

            // Perbaikan Nama Kolom: tgl_log, tipe_transaksi, qty
            mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                                    VALUES ('$id_barang', '$tgl_full', '$tipe_log', '$qty_log', '$ket_log', '$user_nama')");
        }

        mysqli_commit($koneksi);
        echo "<script>alert('Stok Berhasil Dikoreksi!'); window.location='koreksi.php';</script>";
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Gagal melakukan koreksi stok: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Koreksi Stok - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-purple: #6f42c1; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); border-radius: 10px; }
        .bg-purple { background-color: var(--mcp-purple) !important; color: white; }
        .btn-purple { background-color: var(--mcp-purple); color: white; }
        .btn-purple:hover { background-color: #59359a; color: white; }
        .table thead { background-color: #f1f3f5; }
        input[readonly] { background-color: #e9ecef !important; cursor: not-allowed; }
        .bg-focus { background-color: #fcf8ff; border: 1px solid #d1c4e9; padding: 15px; border-radius: 8px; }
        input, textarea { text-transform: uppercase; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="m-0 fw-bold text-dark text-uppercase">
                        <i class="fas fa-sync-alt me-2 text-purple"></i>Riwayat Koreksi Stok (Opname)
                    </h5>
                </div>
                <div class="d-flex gap-2">
                    <a href="../../index.php" class="btn btn-danger px-2"><i class="fas fa-rotate-left"></i> Kembali</a>
                    <button type="button" class="btn btn-sm btn-purple px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalKoreksi">
                        <i class="fas fa-plus-circle me-1"></i> INPUT KOREKSI BARU
                    </button>
                    
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0" id="tabelKoreksi">
                    <thead>
                        <tr class="text-center small fw-bold text-uppercase">
                            <th>Tanggal</th>
                            <th>Nama Barang</th>
                            <th>Stok Sistem</th>
                            <th>Stok Fisik</th>
                            <th>Selisih</th>
                            <th>Tipe</th>
                            <th>Alasan Koreksi</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php
                        $hist = mysqli_query($koneksi, "SELECT k.*, m.nama_barang, m.satuan FROM tr_koreksi k JOIN master_barang m ON k.id_barang=m.id_barang ORDER BY k.id_koreksi DESC");
                        while($h = mysqli_fetch_array($hist)):
                            $warna_selisih = ($h['selisih'] < 0) ? 'text-danger' : (($h['selisih'] > 0) ? 'text-success' : 'text-dark');
                            $tanda = ($h['selisih'] > 0) ? '+' : '';
                        ?>
                        <tr>
                            <td class="text-center"><?= date('d/m/Y', strtotime($h['tgl_koreksi'])) ?></td>
                            <td class="fw-bold"><?= $h['nama_barang'] ?></td>
                            <td class="text-center"><?= $h['stok_sebelum'] ?> <small><?= $h['satuan'] ?></small></td>
                            <td class="text-center fw-bold bg-light"><?= $h['stok_sesudah'] ?></td>
                            <td class="text-center fw-bold <?= $warna_selisih ?>"><?= $tanda.$h['selisih'] ?></td>
                            <td class="text-center">
                                <span class="badge <?= ($h['tipe_koreksi'] == 'KURANG') ? 'bg-danger' : 'bg-success' ?>">
                                    <?= $h['tipe_koreksi'] ?>
                                </span>
                            </td>
                            <td><?= $h['alasan_koreksi'] ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalKoreksi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-purple text-white">
                <h6 class="modal-title fw-bold"><i class="fas fa-edit me-2"></i>FORM PENYESUAIAN STOK (KOREKSI)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-bold mb-1">TANGGAL KOREKSI</label>
                        <input type="date" name="tgl_koreksi" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">PILIH BARANG YANG AKAN DIKOREKSI</label>
                        <select name="id_barang" id="id_barang" class="form-select select2" onchange="updateInfoStok()" required>
                            <option value="">-- CARI BARANG --</option>
                            <?php
                            // Query ini menghitung saldo real-time dari tr_stok_log
                            $sql_realtime = "SELECT 
                                                m.id_barang, 
                                                m.nama_barang, 
                                                m.satuan,
                                                (SELECT SUM(CASE WHEN tipe_transaksi = 'MASUK' THEN qty ELSE 0 END) FROM tr_stok_log WHERE id_barang = m.id_barang) -
                                                (SELECT SUM(CASE WHEN tipe_transaksi = 'KELUAR' THEN qty ELSE 0 END) FROM tr_stok_log WHERE id_barang = m.id_barang) as stok_log
                                            FROM master_barang m 
                                            ORDER BY m.nama_barang ASC";
                            
                            $brg = mysqli_query($koneksi, $sql_realtime);
                            while($b = mysqli_fetch_array($brg)){
                                // Jika stok_log null (barang baru belum ada transaksi), set ke 0
                                $stok_sekarang = $b['stok_log'] ?? 0;
                                
                                echo "<option value='{$b['id_barang']}' 
                                            data-stok='{$stok_sekarang}' 
                                            data-satuan='{$b['satuan']}'>
                                        {$b['nama_barang']} (Sistem: {$stok_sekarang} {$b['satuan']})
                                    </option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="bg-focus mb-3">
                        <div class="row g-2 text-center">
                            <div class="col-md-4">
                                <label class="small fw-bold text-muted">STOK DI SISTEM</label>
                                <input type="number" name="stok_sebelum" id="stok_sebelum" class="form-control form-control-lg text-center fw-bold" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-primary">STOK FISIK (REAL)</label>
                                <input type="number" name="stok_sesudah" id="stok_sesudah" class="form-control form-control-lg text-center fw-bold border-primary" oninput="hitungSelisih()" placeholder="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="small fw-bold text-muted">SELISIH (+/-)</label>
                                <input type="number" id="selisih_tampil" class="form-control form-control-lg text-center fw-bold" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="small fw-bold mb-1">ALASAN KOREKSI / KETERANGAN</label>
                        <textarea name="alasan_koreksi" class="form-control" rows="3" placeholder="CONTOH: TEMUAN STOCK OPNAME JANUARI, BARANG RUSAK, DLL" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-danger" data-bs-dismiss="modal">BATAL</button>
                    <button type="submit" name="simpan_koreksi" class="btn btn-sm btn-purple px-4 fw-bold">
                        <i class="fas fa-save me-1"></i> SIMPAN PERUBAHAN STOK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    function updateInfoStok() {
        const select = document.getElementById('id_barang');
        const selected = select.options[select.selectedIndex];
        const stokLama = selected.getAttribute('data-stok');
        
        document.getElementById('stok_sebelum').value = stokLama ? stokLama : 0;
        document.getElementById('stok_sesudah').value = "";
        document.getElementById('selisih_tampil').value = 0;
        document.getElementById('selisih_tampil').style.color = 'black';
    }

    function hitungSelisih() {
        const lama = parseFloat(document.getElementById('stok_sebelum').value) || 0;
        const baru = parseFloat(document.getElementById('stok_sesudah').value) || 0;
        const selisih = baru - lama;
        
        const output = document.getElementById('selisih_tampil');
        output.value = selisih;
        
        if(selisih < 0) { output.style.color = 'red'; } 
        else if(selisih > 0) { output.style.color = 'green'; }
        else { output.style.color = 'black'; }
    }

    $(document).ready(function () {
        $('#tabelKoreksi').DataTable({
            "pageLength": 10,
            "order": [[0, "desc"]],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
            }
        });
    });
    $(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#modalKoreksi')
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</body>
</html>