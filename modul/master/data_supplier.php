<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
include '../../auth/keep_alive.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// PROSES SIMPAN / UPDATE via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_sup   = (int)($_POST['id_supplier'] ?? 0);
    $nama     = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_supplier']));
    $alamat   = mysqli_real_escape_string($koneksi, $_POST['alamat'] ?? '');
    $kota     = mysqli_real_escape_string($koneksi, strtoupper($_POST['kota'] ?? ''));
    $telp     = mysqli_real_escape_string($koneksi, $_POST['telp'] ?? '');
    $fax      = mysqli_real_escape_string($koneksi, $_POST['fax'] ?? '');
    $email    = mysqli_real_escape_string($koneksi, $_POST['email'] ?? '');
    $cp       = mysqli_real_escape_string($koneksi, strtoupper($_POST['contact_person'] ?? ''));
    $uname    = mysqli_real_escape_string($koneksi, strtoupper($_POST['atas_nama'] ?? ''));
    $norek    = mysqli_real_escape_string($koneksi, $_POST['no_rekening'] ?? '');
    $bank     = mysqli_real_escape_string($koneksi, strtoupper($_POST['nama_bank'] ?? ''));
    $arek     = mysqli_real_escape_string($koneksi, strtoupper($_POST['atas_nama_rekening'] ?? ''));
    $status   = mysqli_real_escape_string($koneksi, $_POST['status_aktif'] ?? 'AKTIF');

    if ($id_sup > 0) {
        mysqli_query($koneksi, "UPDATE master_supplier SET nama_supplier='$nama', alamat='$alamat', kota='$kota', telp='$telp', fax='$fax', email='$email', contact_person='$cp', atas_nama='$uname', no_rekening='$norek', nama_bank='$bank', atas_nama_rekening='$arek', status_aktif='$status' WHERE id_supplier='$id_sup'");
    } else {
        mysqli_query($koneksi, "INSERT INTO master_supplier (nama_supplier, alamat, kota, telp, fax, email, contact_person, atas_nama, no_rekening, nama_bank, atas_nama_rekening, status_aktif) VALUES ('$nama','$alamat','$kota','$telp','$fax','$email','$cp','$uname','$norek','$bank','$arek','$status')");
    }
    header("location:data_supplier.php?pesan=berhasil");
    exit;
}

// PROSES HAPUS
if (isset($_GET['hapus'])) {
    $del = (int)$_GET['hapus'];
    $cek = mysqli_fetch_array(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM tr_purchase_order WHERE id_supplier='$del'"));
    if ($cek['c'] > 0) {
        header("location:data_supplier.php?pesan=tidak_bisa_hapus");
    } else {
        mysqli_query($koneksi, "DELETE FROM master_supplier WHERE id_supplier='$del'");
        header("location:data_supplier.php?pesan=hapus_berhasil");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Supplier - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #f4f6f9; font-size: 0.85rem; }
        .navbar-sup { background: linear-gradient(135deg, #155e9a, #1a78c2); }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.07); }
    </style>
</head>
<body>
<nav class="navbar navbar-sup mb-4 py-2 px-4">
    <span class="text-white fw-bold"><i class="fas fa-truck me-2"></i>MASTER SUPPLIER</span>
    <div class="d-flex gap-2">
	 <a href="../../index.php" class="btn btn-sm btn-danger"><i class="fas fa-rotate-left me-1"></i> Kembali</a>
        <button class="btn btn-sm btn-light fw-bold" onclick="openModal()">
            <i class="fas fa-plus me-1"></i> Tambah Supplier
        </button>
       
    </div>
</nav>

<div class="container-fluid px-4">
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelSupplier" class="table table-hover table-bordered align-middle w-100 small">
                    <thead style="background:#f1f4f9; font-size:0.72rem;" class="text-uppercase">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama Supplier</th>
                            <th>Kota</th>
                            <th>Telp</th>
                            <th>U/P</th>
                            <th>No. Rekening</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" width="10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    $sup = mysqli_query($koneksi, "SELECT * FROM master_supplier ORDER BY nama_supplier ASC");
                    while ($s = mysqli_fetch_array($sup)):
                    ?>
                    <tr>
                        <td class="text-center text-muted"><?= $no++ ?></td>
                        <td class="fw-bold"><?= $s['nama_supplier'] ?></td>
                        <td><?= $s['kota'] ?: '-' ?></td>
                        <td><?= $s['telp'] ?: '-' ?></td>
                        <td><?= $s['atas_nama'] ?: '-' ?></td>
                        <td>
                            <?php if ($s['no_rekening']): ?>
                            <small><?= $s['no_rekening'] ?> (<?= $s['nama_bank'] ?>)</small>
                            <?php else: echo '-'; endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $s['status_aktif'] === 'AKTIF' ? 'bg-success' : 'bg-danger' ?>">
                                <?= $s['status_aktif'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary" title="Edit"
                                onclick='editSupplier(<?= json_encode($s) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?hapus=<?= $s['id_supplier'] ?>" class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Hapus supplier ini?')" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL TAMBAH/EDIT SUPPLIER -->
<div class="modal fade" id="modalSupplier" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title fw-bold" id="modalSupTitle"><i class="fas fa-truck me-2"></i>Tambah Supplier</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="data_supplier.php">
                    <input type="hidden" name="id_supplier" id="sup_id" value="0">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="small fw-bold">Nama Supplier / Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" name="nama_supplier" id="sup_nama" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Status</label>
                            <select name="status_aktif" id="sup_status" class="form-select">
                                <option value="AKTIF">AKTIF</option>
                                <option value="NONAKTIF">NONAKTIF</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="small fw-bold">Alamat</label>
                            <input type="text" name="alamat" id="sup_alamat" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Kota</label>
                            <input type="text" name="kota" id="sup_kota" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Telp</label>
                            <input type="text" name="telp" id="sup_telp" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Fax</label>
                            <input type="text" name="fax" id="sup_fax" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Email</label>
                            <input type="email" name="email" id="sup_email" class="form-control" style="text-transform:none;">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">Contact Person</label>
                            <input type="text" name="contact_person" id="sup_cp" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="small fw-bold">U/P (Atas Nama Kontak)</label>
                            <input type="text" name="atas_nama" id="sup_uname" class="form-control" placeholder="Bpk / Ibu ...">
                        </div>
                        <hr class="my-1">
                        <div class="col-md-4">
                            <label class="small fw-bold">No. Rekening</label>
                            <input type="text" name="no_rekening" id="sup_norek" class="form-control" style="text-transform:none;">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Nama Bank</label>
                            <input type="text" name="nama_bank" id="sup_bank" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold">Atas Nama Rekening</label>
                            <input type="text" name="atas_nama_rekening" id="sup_arek" class="form-control">
                        </div>
                    </div>
                    <div class="mt-4 text-end">
                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="fas fa-save me-1"></i> Simpan Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$('#tabelSupplier').DataTable({
    pageLength: 15,
    language: { url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json' }
});

function openModal() {
    document.getElementById('modalSupTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Tambah Supplier Baru';
    document.getElementById('sup_id').value = '0';
    document.querySelector('form').reset();
    new bootstrap.Modal(document.getElementById('modalSupplier')).show();
}

function editSupplier(s) {
    document.getElementById('modalSupTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Supplier';
    document.getElementById('sup_id').value    = s.id_supplier;
    document.getElementById('sup_nama').value  = s.nama_supplier;
    document.getElementById('sup_alamat').value= s.alamat || '';
    document.getElementById('sup_kota').value  = s.kota || '';
    document.getElementById('sup_telp').value  = s.telp || '';
    document.getElementById('sup_fax').value   = s.fax || '';
    document.getElementById('sup_email').value = s.email || '';
    document.getElementById('sup_cp').value    = s.contact_person || '';
    document.getElementById('sup_uname').value = s.atas_nama || '';
    document.getElementById('sup_norek').value = s.no_rekening || '';
    document.getElementById('sup_bank').value  = s.nama_bank || '';
    document.getElementById('sup_arek').value  = s.atas_nama_rekening || '';
    document.getElementById('sup_status').value= s.status_aktif;
    new bootstrap.Modal(document.getElementById('modalSupplier')).show();
}

// Notifikasi
const p = new URLSearchParams(window.location.search).get('pesan');
const notif = {
    'berhasil': ['success','Berhasil!', 'Data supplier tersimpan.'],
    'hapus_berhasil': ['success','Dihapus!', 'Supplier berhasil dihapus.'],
    'tidak_bisa_hapus': ['warning','Tidak Bisa Dihapus!', 'Supplier ini sudah digunakan di PO.']
};
if (p && notif[p]) {
    Swal.fire({ icon: notif[p][0], title: notif[p][1], text: notif[p][2] });
    history.replaceState({}, '', location.pathname);
}
</script>
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