<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// Tentukan jenis transaksi dari URL
$jenis_transaksi = isset($_GET['jenis']) ? $_GET['jenis'] : 'MASUK';
if (!in_array($jenis_transaksi, ['MASUK', 'KELUAR'])) {
    $jenis_transaksi = 'MASUK';
}

// Proses Tambah Data
if (isset($_POST['submit'])) {
    $tanggal = mysqli_real_escape_string($koneksi, $_POST['tanggal']);
    $nama_oli = mysqli_real_escape_string($koneksi, $_POST['nama_oli']);
    $jenis_transaksi_post = mysqli_real_escape_string($koneksi, $_POST['jenis_transaksi']);
    $id_mobil = !empty($_POST['id_mobil']) ? mysqli_real_escape_string($koneksi, $_POST['id_mobil']) : 'NULL';
    $jumlah = mysqli_real_escape_string($koneksi, $_POST['jumlah']);
    $keterangan = mysqli_real_escape_string($koneksi, $_POST['keterangan']);
    $created_by = $_SESSION['username'] ?? 'system';

    // Cek apakah oli sudah ada di master_oli
    $query_cek = "SELECT id_oli, stok_saat_ini FROM master_oli WHERE nama_oli = '$nama_oli'";
    $result_cek = mysqli_query($koneksi, $query_cek);
    
    if (mysqli_num_rows($result_cek) > 0) {
        // Oli sudah ada
        $row = mysqli_fetch_assoc($result_cek);
        $id_oli = $row['id_oli'];
        $stok_saat_ini = $row['stok_saat_ini'];
        
        // Cek stok jika transaksi KELUAR
        if ($jenis_transaksi_post == 'KELUAR' && $stok_saat_ini < $jumlah) {
            $_SESSION['error'] = "Stok tidak mencukupi! Stok tersisa: " . number_format($stok_saat_ini, 2) . " Liter";
            header("Location: tambah_oli.php?jenis=$jenis_transaksi_post");
            exit();
        }
    } else {
        // Oli belum ada, insert ke master_oli
        $query_insert_oli = "INSERT INTO master_oli (nama_oli, stok_awal, stok_saat_ini, created_by) 
                             VALUES ('$nama_oli', 0, 0, '$created_by')";
        if (mysqli_query($koneksi, $query_insert_oli)) {
            $id_oli = mysqli_insert_id($koneksi);
            $_SESSION['info'] = "Jenis oli baru '$nama_oli' berhasil ditambahkan ke master!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan jenis oli baru: " . mysqli_error($koneksi);
            header("Location: tambah_oli.php?jenis=$jenis_transaksi_post");
            exit();
        }
    }

    // Insert ke riwayat_oli
    $query = "INSERT INTO riwayat_oli (tanggal, id_oli, jenis_transaksi, id_mobil, jumlah, keterangan, created_by) 
              VALUES ('$tanggal', '$id_oli', '$jenis_transaksi_post', $id_mobil, '$jumlah', '$keterangan', '$created_by')";

    if (mysqli_query($koneksi, $query)) {
        // Update stok_saat_ini di master_oli
        if ($jenis_transaksi_post == 'MASUK') {
            $query_update = "UPDATE master_oli SET stok_saat_ini = stok_saat_ini + $jumlah WHERE id_oli = $id_oli";
        } else {
            $query_update = "UPDATE master_oli SET stok_saat_ini = stok_saat_ini - $jumlah WHERE id_oli = $id_oli";
        }
        mysqli_query($koneksi, $query_update);
        
        $_SESSION['success'] = "Data berhasil ditambahkan! " . ($_SESSION['info'] ?? '');
        unset($_SESSION['info']);
        header("Location: master_oli.php");
        exit();
    } else {
        $_SESSION['error'] = "Gagal menambahkan data: " . mysqli_error($koneksi);
        header("Location: tambah_oli.php?jenis=$jenis_transaksi_post");
        exit();
    }
}

// Ambil data master_mobil (hanya yang aktif)
$query_mobil = "SELECT id_mobil, plat_nomor, driver_tetap FROM master_mobil WHERE status_aktif = 'AKTIF' ORDER BY plat_nomor ASC";
$result_mobil = mysqli_query($koneksi, $query_mobil);

// Daftar oli default
$oli_default = ['SAE 40', 'SAE 460', 'Hidrolis'];

// Judul berdasarkan jenis transaksi
$judul = ($jenis_transaksi == 'MASUK') ? 'Pembelian Oli (Masuk Stok)' : 'Pemakaian Oli (Keluar Stok)';
$icon = ($jenis_transaksi == 'MASUK') ? 'fa-arrow-down' : 'fa-arrow-up';
$btn_color = ($jenis_transaksi == 'MASUK') ? 'btn-success' : 'btn-danger';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $judul ?> - MCP System</title>
    <link rel="icon" type="image/png" href="<?php echo $base_url; ?>assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .card-container { max-width: 700px; margin: 40px auto; }
        .card-header-custom {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
        }
        .card-body-custom {
            background: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-custom-primary {
            background: #e67e22;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-custom-primary:hover {
            background: #d35400;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(230, 126, 34, 0.3);
        }
        .form-label {
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            color: #34495e;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #e67e22;
            box-shadow: 0 0 0 0.2rem rgba(230, 126, 34, 0.15);
        }
        .info-oli {
            background: #fef9e7;
            border-left: 4px solid #e67e22;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 13px;
        }
        .info-oli i {
            color: #e67e22;
        }
        .stok-info {
            background: #eaf2f8;
            border-left: 4px solid #3498db;
            padding: 10px 15px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 13px;
        }
        .stok-info i {
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="container card-container">
        <div class="card-header-custom">
            <h5 class="m-0 fw-bold">
                <i class="fas <?= $icon ?> me-2"></i> <?= $judul ?>
            </h5>
            <small class="opacity-75"><i class="fas fa-clock me-1"></i> Input transaksi oli kendaraan</small>
        </div>
        <div class="card-body-custom">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info alert-dismissible fade show">
                    <i class="fas fa-info-circle me-2"></i> <?= $_SESSION['info'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['info']); ?>
            <?php endif; ?>

            <form action="" method="POST">
                <input type="hidden" name="jenis_transaksi" value="<?= $jenis_transaksi ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-calendar-alt me-1"></i> Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-oil-can me-1"></i> Jenis Oli</label>
                        <select name="nama_oli" class="form-select" required>
                            <option value="">Pilih Jenis Oli</option>
                            <?php foreach ($oli_default as $oli): ?>
                            <option value="<?= $oli ?>"><?= $oli ?></option>
                            <?php endforeach; ?>
                            <option value="LAINNYA">--- Lainnya (Ketik Manual) ---</option>
                        </select>
                        <div class="info-oli">
                            <i class="fas fa-info-circle me-1"></i>
                            Oli akan otomatis tersimpan di master jika belum ada
                        </div>
                    </div>
                </div>

                <!-- Input manual untuk oli lainnya (muncul jika pilih LAINNYA) -->
                <div class="row" id="oliLainnya" style="display: none;">
                    <div class="col-md-12 mb-3">
                        <label class="form-label"><i class="fas fa-pen me-1"></i> Nama Oli Lainnya</label>
                        <input type="text" name="nama_oli_lain" class="form-control" placeholder="Masukkan nama oli baru...">
                        <small class="text-muted">Contoh: SAE 30, ATF, DLL</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-car me-1"></i> Mobil</label>
                        <select name="id_mobil" class="form-select" <?= ($jenis_transaksi == 'MASUK') ? '' : 'required' ?>>
                            <option value=""><?= ($jenis_transaksi == 'MASUK') ? '- Tidak Pakai Mobil -' : 'Pilih Mobil' ?></option>
                            <?php while ($row = mysqli_fetch_assoc($result_mobil)): ?>
                            <option value="<?= $row['id_mobil'] ?>">
                                <?= $row['plat_nomor'] ?> - <?= $row['driver_tetap'] ?? 'Tanpa Driver' ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if ($jenis_transaksi == 'KELUAR'): ?>
                        <small class="text-muted">Wajib pilih mobil untuk pemakaian</small>
                        <?php else: ?>
                        <small class="text-muted">Opsional untuk pembelian</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><i class="fas fa-tint me-1"></i> Jumlah (Liter)</label>
                        <input type="number" step="0.01" name="jumlah" class="form-control" placeholder="Contoh: 5.5" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label"><i class="fas fa-comment me-1"></i> Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="3" placeholder="Masukkan keterangan tambahan (opsional)"></textarea>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="master_oli.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                    <button type="submit" name="submit" class="btn <?= $btn_color ?>">
                        <i class="fas fa-save me-1"></i> Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle input manual untuk oli lainnya
        document.querySelector('select[name="nama_oli"]').addEventListener('change', function() {
            var oliLainnya = document.getElementById('oliLainnya');
            if (this.value === 'LAINNYA') {
                oliLainnya.style.display = 'block';
                document.querySelector('input[name="nama_oli_lain"]').required = true;
            } else {
                oliLainnya.style.display = 'none';
                document.querySelector('input[name="nama_oli_lain"]').required = false;
            }
        });

        // Validasi form
        document.querySelector('form').addEventListener('submit', function(e) {
            var selectOli = document.querySelector('select[name="nama_oli"]');
            var inputOliLain = document.querySelector('input[name="nama_oli_lain"]');
            
            if (selectOli.value === 'LAINNYA' && inputOliLain.value.trim() === '') {
                e.preventDefault();
                alert('Mohon isi nama oli lainnya!');
                inputOliLain.focus();
                return false;
            }
            
            // Jika pilih LAINNYA, set value ke input hidden atau rename
            if (selectOli.value === 'LAINNYA') {
                // Kita akan rename input nama_oli_lain menjadi nama_oli di server
                // Tapi karena pakai POST biasa, kita set value select ke value input
                var newValue = inputOliLain.value.trim();
                if (newValue) {
                    // Buat option baru dan pilih
                    var option = document.createElement('option');
                    option.value = newValue;
                    option.text = newValue;
                    option.selected = true;
                    selectOli.appendChild(option);
                }
            }
            
            return true;
        });

        // Auto close alert
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Session timeout
        let idleTime = 0;
        const maxIdleMinutes = 15;
        let lastServerUpdate = Date.now();
        let sessionValid = true;

        function resetTimer() {
            idleTime = 0;
            let now = Date.now();
            if (now - lastServerUpdate > 300000) {
                fetch('/pr_mcp_rev4/auth/keep_alive.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status !== 'success') {
                            sessionValid = false;
                            forceLogout();
                        }
                    })
                    .catch(err => {});
                lastServerUpdate = now;
            }
        }

        function forceLogout() {
            alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
            window.location.href = "/pr_mcp_rev4/auth/logout.php?pesan=timeout";
        }

        window.onload = resetTimer;
        document.onmousemove = resetTimer;
        document.onkeypress = resetTimer;
        document.onmousedown = resetTimer;
        document.onclick = resetTimer;
        document.onscroll = resetTimer;

        setInterval(function() {
            idleTime++;
            fetch('/pr_mcp_rev4/auth/keep_alive.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        sessionValid = false;
                        forceLogout();
                    }
                })
                .catch(err => {});
            if (idleTime >= maxIdleMinutes && sessionValid) {
                forceLogout();
            }
        }, 60000);
    </script>
</body>
</html>