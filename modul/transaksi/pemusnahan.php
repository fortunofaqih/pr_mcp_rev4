<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
include '../../auth/keep_alive.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

$username = $_SESSION['username'];
$id_user_logged = $_SESSION['id_user'];

// --- 1. LOGIKA GENERATE NO. PEMUSNAHAN (Hanya untuk tampilan awal) ---
$bulan = date('Ym');
$query_no = mysqli_query($koneksi, "SELECT MAX(no_pemusnahan) as max_no FROM tr_pemusnahan WHERE no_pemusnahan LIKE 'PMS-$bulan-%'");
$data_no = mysqli_fetch_array($query_no);
$no_urut = (int) substr($data_no['max_no'] ?? '', -4);
$no_urut++;
$no_pms_tampilan = "PMS-" . $bulan . "-" . sprintf("%04s", $no_urut);

// --- 2. PROSES SIMPAN PEMUSNAHAN ---
if(isset($_POST['simpan_pemusnahan'])){
    $tgl            = $_POST['tgl_pemusnahan'];
    $id_barang      = (int)$_POST['id_barang'];
    $qty            = (float)$_POST['qty_dimusnahkan'];
    $satuan         = mysqli_real_escape_string($koneksi, $_POST['satuan']);
    $metode         = mysqli_real_escape_string($koneksi, $_POST['metode_pemusnahan']);
    $nilai_jual     = (int)($_POST['nilai_jual_scrap'] ?? 0);
    $alasan         = mysqli_real_escape_string($koneksi, strtoupper($_POST['alasan_pemusnahan']));

    mysqli_begin_transaction($koneksi);

    try {
        // A. Generate No PMS Final (Cek ulang saat simpan agar tidak duplikat)
        $q_gen = mysqli_query($koneksi, "SELECT MAX(no_pemusnahan) as max_no FROM tr_pemusnahan WHERE no_pemusnahan LIKE 'PMS-$bulan-%' FOR UPDATE");
        $d_gen = mysqli_fetch_array($q_gen);
        $urut_final = (int) substr($d_gen['max_no'] ?? '', -4);
        $urut_final++;
        $no_pms_final = "PMS-" . $bulan . "-" . sprintf("%04s", $urut_final);

        // B. Cek Stok di Master (Kunci Baris)
        $q_cek = mysqli_query($koneksi, "SELECT stok_akhir FROM master_barang WHERE id_barang = $id_barang FOR UPDATE");
        $d_cek = mysqli_fetch_assoc($q_cek);
        if(!$d_cek) throw new Exception("Barang tidak ditemukan di master.");
        
        $stok_sekarang = (float)$d_cek['stok_akhir'];
        if($stok_sekarang < $qty) throw new Exception("Stok tidak cukup! Sisa: $stok_sekarang");

        // C. Potong Stok Master
        $upd_master = mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir - $qty WHERE id_barang = $id_barang");
        if(!$upd_master) throw new Exception("Gagal potong stok master.");

        // D. Simpan Transaksi Pemusnahan
        $sql_ins = "INSERT INTO tr_pemusnahan (no_pemusnahan, tgl_pemusnahan, id_barang, qty_dimusnahkan, satuan, metode_pemusnahan, nilai_jual_scrap, alasan_pemusnahan, id_user) 
                    VALUES ('$no_pms_final', '$tgl', $id_barang, $qty, '$satuan', '$metode', $nilai_jual, '$alasan', $id_user_logged)";
        if(!mysqli_query($koneksi, $sql_ins)) throw new Exception("Gagal simpan data pemusnahan.");

        // E. Simpan Log Stok (Kartu Stok)
        $ket_log = "PEMUSNAHAN ($metode): $alasan | REF: $no_pms_final";
        $sql_log = "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                    VALUES ($id_barang, NOW(), 'KELUAR', $qty, '$ket_log', '$username')";
        if(!mysqli_query($koneksi, $sql_log)) throw new Exception("Gagal simpan log kartu stok.");

        mysqli_commit($koneksi);
        echo "<script>alert('Berhasil! Stok berkurang dan log tercatat.'); window.location='pemusnahan.php';</script>";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Gagal Simpan: ".$e->getMessage()."'); window.location='pemusnahan.php';</script>";
    }
}

// --- 3. PROSES HAPUS (BATAL & KEMBALIKAN STOK) ---
if(isset($_GET['aksi']) && $_GET['aksi'] == 'hapus'){
    $id_hps = (int)$_GET['id'];
    
    mysqli_begin_transaction($koneksi);

    try {
        // A. Ambil data lama & Kunci
        $q_data = mysqli_query($koneksi, "SELECT * FROM tr_pemusnahan WHERE id_pemusnahan = $id_hps FOR UPDATE");
        $d_lama = mysqli_fetch_array($q_data);
        if(!$d_lama) throw new Exception("Data pemusnahan tidak ditemukan.");

        $brg_id      = $d_lama['id_barang'];
        $qty_kembali = $d_lama['qty_dimusnahkan'];
        $no_pms_lama = $d_lama['no_pemusnahan'];

        // B. Kembalikan stok ke master_barang
        mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir + $qty_kembali WHERE id_barang = $brg_id");

        // C. Tambahkan log pembatalan
        $ket_batal = "BATAL PEMUSNAHAN: $no_pms_lama (STOK KEMBALI)";
        mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan, user_input) 
                                VALUES ($brg_id, NOW(), 'MASUK', $qty_kembali, '$ket_batal', '$username')");

        // D. Hapus data utama
        mysqli_query($koneksi, "DELETE FROM tr_pemusnahan WHERE id_pemusnahan = $id_hps");

        mysqli_commit($koneksi);
        echo "<script>alert('Berhasil dihapus & stok dikembalikan!'); window.location='pemusnahan.php';</script>";

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<script>alert('Gagal Hapus: ".$e->getMessage()."'); window.location='pemusnahan.php';</script>";
    }
}
function formatAngka($angka) {
    if ($angka == 0) return '0';
    return rtrim(rtrim(number_format($angka, 4, ',', '.'), '0'), ',');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pemusnahan Barang - MCP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-red: #d63031; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); border-radius: 10px; }
        .bg-red { background-color: var(--mcp-red) !important; color: white; }
        .btn-red { background-color: var(--mcp-red); color: white; }
        input, select, textarea { text-transform: uppercase; }
        .stok-info { background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; border-radius: 8px; }
    </style>
</head>
<body class="py-4">

<div class="container-fluid">
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="m-0 fw-bold text-dark"><i class="fas fa-trash-alt me-2 text-danger"></i>RIWAYAT PEMUSNAHAN BARANG</h5>
            <div class="gap-2 d-flex">
                <a href="../../index.php" class="btn btn-sm btn-outline-secondary px-3"><i class="fas fa-arrow-left"></i> KEMBALI</a>
                <button class="btn btn-sm btn-red px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalPms">
                    <i class="fas fa-plus-circle me-1"></i> INPUT PEMUSNAHAN
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle" id="tabelPms">
                    <thead class="text-center small fw-bold">
                        <tr>
                            <th>NO. TRANSAKSI</th>
                            <th>TANGGAL</th>
                            <th>NAMA BARANG</th>
                            <th>QTY</th>
                            <th>METODE</th>
                            <th>PETUGAS</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php
                        $q = mysqli_query($koneksi, "SELECT p.*, m.nama_barang, u.nama_lengkap 
                             FROM tr_pemusnahan p 
                             JOIN master_barang m ON p.id_barang = m.id_barang 
                             JOIN users u ON p.id_user = u.id_user 
                             ORDER BY p.id_pemusnahan DESC");
                        while($h = mysqli_fetch_array($q)):
                        ?>
                        <tr>
                            <td class="text-center fw-bold text-danger"><?= $h['no_pemusnahan'] ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($h['tgl_pemusnahan'])) ?></td>
                            <td class="fw-bold"><?= $h['nama_barang'] ?></td>
                            <td class="text-center"><?= formatAngka($h['qty_dimusnahkan'], 2) ?> <?= $h['satuan'] ?></td>
                            <td class="text-center"><span class="badge bg-secondary"><?= $h['metode_pemusnahan'] ?></span></td>
                            <td class="text-center small"><?= $h['nama_lengkap'] ?></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="cetak_pms.php?id=<?= $h['id_pemusnahan'] ?>" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-print"></i></a>
                                    <a href="?aksi=hapus&id=<?= $h['id_pemusnahan'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Batalkan pemusnahan? Stok akan dikembalikan.')"><i class="fas fa-trash-alt"></i></a>
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

<div class="modal fade" id="modalPms" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header bg-red">
                <h6 class="modal-title fw-bold text-white"><i class="fas fa-fire me-2"></i>FORM PENGHAPUSAN / PEMUSNAHAN</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="small fw-bold mb-1">NO. TRANSAKSI (AUTO)</label>
                            <input type="text" class="form-control fw-bold text-danger bg-light" value="<?= $no_pms_tampilan ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold mb-1">TANGGAL</label>
                            <input type="date" name="tgl_pemusnahan" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">PILIH BARANG</label>
                        <select name="id_barang" id="id_barang" class="form-select select2-barang" required>
                            <option value="">-- PILIH BARANG --</option>
                            <?php
                            $brg = mysqli_query($koneksi, "SELECT id_barang, nama_barang, satuan, stok_akhir FROM master_barang WHERE stok_akhir > 0 ORDER BY nama_barang ASC");
                            while($b = mysqli_fetch_array($brg)){
                                echo "<option value='{$b['id_barang']}' data-satuan='{$b['satuan']}' data-stok='{$b['stok_akhir']}'>
                                        {$b['nama_barang']} (Stok: ".number_format($b['stok_akhir'],2)." {$b['satuan']})
                                      </option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="stok-info mb-3">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <label class="small fw-bold text-danger">QTY DIMUSNAHKAN</label>
                                <input type="number" step="0.01" name="qty_dimusnahkan" id="qty_input" class="form-control form-control-lg fw-bold" min="0.01" required>
                            </div>
                            <div class="col-md-2">
                                <label class="small fw-bold">SATUAN</label>
                                <input type="text" name="satuan" id="satuan_input" class="form-control form-control-lg bg-white text-center" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">METODE</label>
                                <select name="metode_pemusnahan" class="form-select form-select-lg" required>
                                    <option value="DIHANCURKAN">DIHANCURKAN (SCRAP)</option>
                                    <option value="DIJUAL">DIJUAL (ROMBENG)</option>
                                    <option value="DIBUANG">DIBUANG (SAMPAH)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-1">NILAI JUAL SCRAP (RP)</label>
                        <input type="number" name="nilai_jual_scrap" class="form-control" value="0">
                    </div>

                    <div class="mb-0">
                        <label class="small fw-bold mb-1">ALASAN PEMUSNAHAN</label>
                        <textarea name="alasan_pemusnahan" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">BATAL</button>
                    <button type="submit" name="simpan_pemusnahan" class="btn btn-sm btn-red px-4 fw-bold">SIMPAN DATA</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#tabelPms').DataTable({ "order": [[0, "desc"]] });
    $('.select2-barang').select2({ theme: 'bootstrap-5', dropdownParent: $('#modalPms') });

    $('.select2-barang').on('select2:select', function (e) {
        const data = e.params.data.element;
        $('#satuan_input').val(data.getAttribute('data-satuan'));
        $('#qty_input').attr('max', data.getAttribute('data-stok')).val('');
    });

    $('#qty_input').on('input', function() {
        if(parseFloat($(this).val()) > parseFloat($(this).attr('max'))) {
            alert('Stok tidak cukup!');
            $(this).val($(this).attr('max'));
        }
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