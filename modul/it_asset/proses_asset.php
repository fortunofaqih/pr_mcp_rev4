<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';


$role = $_SESSION['role'] ?? '';
$nama = $_SESSION['nama'] ?? '';

if (!in_array($role, ['administrator', 'it'])) {
    header("Location: " . $base_url . "index.php");
    exit;
}

$aksi = $_POST['aksi'] ?? $_GET['aksi'] ?? '';

// ============================================================
// HELPER: Upload Foto
// ============================================================
function uploadFoto($file, $foto_lama = '') {
    $upload_dir = __DIR__ . '/../../uploads/it_asset/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

    if (empty($file['name'])) return $foto_lama; // tidak ada file baru

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) return $foto_lama;
    if ($file['size'] > 2 * 1024 * 1024) return $foto_lama; // max 2MB

    $nama_file = 'asset_' . time() . '_' . rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $nama_file)) {
        // Hapus foto lama jika ada
        if ($foto_lama && file_exists($upload_dir . $foto_lama)) {
            unlink($upload_dir . $foto_lama);
        }
        return $nama_file;
    }
    return $foto_lama;
}

// ============================================================
// HELPER: Update counter kode aset
// ============================================================
function updateKodeCounter($koneksi, $kode_asset) {
    // Kode format: IT-YYYY-NNN
    $parts = explode('-', $kode_asset);
    if (count($parts) == 3) {
        $tahun  = $parts[1];
        $nomor  = (int)$parts[2];
        $q = mysqli_query($koneksi, "SELECT last_number FROM master_it_asset_counter WHERE tahun = '$tahun'");
        if (mysqli_num_rows($q) == 0) {
            mysqli_query($koneksi, "INSERT INTO master_it_asset_counter (tahun, last_number) VALUES ('$tahun', $nomor)");
        } else {
            $row = mysqli_fetch_assoc($q);
            if ($nomor > $row['last_number']) {
                mysqli_query($koneksi, "UPDATE master_it_asset_counter SET last_number = $nomor WHERE tahun = '$tahun'");
            }
        }
    }
}

// ============================================================
// PROSES SIMPAN (INSERT BARU)
// ============================================================
if ($aksi == 'simpan') {
    $kode_asset      = mysqli_real_escape_string($koneksi, $_POST['kode_asset']);
    // Tambahkan id_barang (relasi ke master_barang)
    $id_barang       = !empty($_POST['id_barang']) ? (int)$_POST['id_barang'] : 'NULL';
    $nama_asset      = mysqli_real_escape_string($koneksi, $_POST['nama_asset']);
    $merk            = mysqli_real_escape_string($koneksi, $_POST['merk'] ?? '');
    $model           = mysqli_real_escape_string($koneksi, $_POST['model'] ?? '');
    $spesifikasi     = mysqli_real_escape_string($koneksi, $_POST['spesifikasi'] ?? '');
    $serial_number   = mysqli_real_escape_string($koneksi, $_POST['serial_number'] ?? '');
    $no_imei         = mysqli_real_escape_string($koneksi, $_POST['no_imei'] ?? '');
    $sumber          = mysqli_real_escape_string($koneksi, $_POST['sumber_perolehan'] ?? 'MANUAL');
    $tgl_perolehan   = mysqli_real_escape_string($koneksi, $_POST['tgl_perolehan'] ?? date('Y-m-d'));
    $harga_input = $_POST['harga_perolehan'] ?? '0';
    $harga_bersih = str_replace('.', '', $harga_input); // Buang titik
    $harga_perolehan = (float)$harga_bersih;
    $supplier        = mysqli_real_escape_string($koneksi, $_POST['supplier'] ?? '');
    $no_request      = mysqli_real_escape_string($koneksi, $_POST['no_request'] ?? '');
    $tgl_garansi_mulai   = !empty($_POST['tgl_garansi_mulai'])   ? "'" . $_POST['tgl_garansi_mulai'] . "'" : 'NULL';
    $tgl_garansi_selesai = !empty($_POST['tgl_garansi_selesai']) ? "'" . $_POST['tgl_garansi_selesai'] . "'" : 'NULL';
    $kondisi         = mysqli_real_escape_string($koneksi, $_POST['kondisi'] ?? 'BAGUS');
    $status_asset    = mysqli_real_escape_string($koneksi, $_POST['status_asset'] ?? 'AKTIF');
    $keterangan      = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');
    $departemen      = mysqli_real_escape_string($koneksi, $_POST['departemen'] ?? '');
    $pengguna        = mysqli_real_escape_string($koneksi, $_POST['pengguna'] ?? '');

    
    // --- LOGIKA LOKASI ---
    $lokasi_sel = $_POST['lokasi'] ?? '';
    $lokasi_man = $_POST['lokasi_manual'] ?? '';

    if ($lokasi_sel == '_manual_' && !empty($lokasi_man)) {
        $lokasi = mysqli_real_escape_string($koneksi, $lokasi_man);
        
        // CEK: Apakah lokasi ini sudah ada di master_it_lokasi?
        $cek_lokasi = mysqli_query($koneksi, "SELECT id_lokasi FROM master_it_lokasi WHERE nama_lokasi = '$lokasi'");
        
        if (mysqli_num_rows($cek_lokasi) == 0) {
            // Jika belum ada, masukkan ke tabel master_it_lokasi agar besok muncul di dropdown
            mysqli_query($koneksi, "INSERT INTO master_it_lokasi (nama_lokasi) VALUES ('$lokasi')");
        }
    } else {
        $lokasi = mysqli_real_escape_string($koneksi, $lokasi_sel);
    }
     // --- LOGIKA KONDISI ---
    $kondisi_sel = $_POST['kondisi'] ?? '';
    $kondisi_man = $_POST['kondisi_manual'] ?? '';

    if ($kondisi_sel == '_manual_' && !empty($kondisi_man)) {
        $kondisi = mysqli_real_escape_string($koneksi, $kondisi_man);
        
        // CEK: Apakah kondisi ini sudah ada di master_it_kondisi?
        $cek_kondisi = mysqli_query($koneksi, "SELECT id_kondisi FROM master_it_kondisi WHERE nama_kondisi = '$kondisi'");
        
        if (mysqli_num_rows($cek_kondisi) == 0) {
            // Jika belum ada, masukkan ke tabel master_it_kondisi agar besok muncul di dropdown
            mysqli_query($koneksi, "INSERT INTO master_it_kondisi (nama_kondisi) VALUES ('$kondisi')");
        }
    } else {
        $kondisi = mysqli_real_escape_string($koneksi, $kondisi_sel);
    }

    // Upload foto
    $foto = '';
    if (!empty($_FILES['foto']['name'])) {
        $foto = uploadFoto($_FILES['foto'], '');
    }
    $foto_val = $foto ? "'$foto'" : 'NULL';

    $sql = "INSERT INTO master_it_asset
        (kode_asset, nama_asset, id_barang, merk, model, spesifikasi, serial_number, no_imei,
         sumber_perolehan, tgl_perolehan, harga_perolehan, supplier, no_request,
         tgl_garansi_mulai, tgl_garansi_selesai,
         kondisi, status_asset, lokasi, pengguna, departemen, keterangan, foto,
         created_by, created_at)
        VALUES
        ('$kode_asset', '$nama_asset', $id_barang, '$merk', '$model', '$spesifikasi', '$serial_number', '$no_imei',
         '$sumber', '$tgl_perolehan', $harga_perolehan, '$supplier', '$no_request',
         $tgl_garansi_mulai, $tgl_garansi_selesai,
         '$kondisi', '$status_asset', '$lokasi', '$pengguna', '$departemen', '$keterangan', $foto_val,
         '$nama', NOW())";

    if (mysqli_query($koneksi, $sql)) {
        $id_baru = mysqli_insert_id($koneksi);
        // Update counter
        updateKodeCounter($koneksi, $kode_asset);
        // Auto-insert riwayat PENERIMAAN
        $ket_awal = mysqli_real_escape_string($koneksi, "Aset diterima/didaftarkan. Kondisi: $kondisi. Lokasi: $lokasi.");
        mysqli_query($koneksi, "INSERT INTO tr_it_asset_history
            (id_asset, tgl_kejadian, jenis_history, kondisi_sebelum, kondisi_sesudah, lokasi_sesudah, pengguna_sesudah, keterangan, created_by)
            VALUES ($id_baru, '$tgl_perolehan', 'PENERIMAAN', NULL, '$kondisi', '$lokasi', '$pengguna', '$ket_awal', '$nama')");

        $_SESSION['flash_success'] = "Aset IT <strong>$kode_asset</strong> berhasil disimpan!";
        header("Location: detail_asset.php?id=$id_baru");
    } else {
        $_SESSION['flash_error'] = "Gagal menyimpan: " . mysqli_error($koneksi);
        header("Location: form_asset.php");
    }
    exit;
}

// ============================================================
// PROSES UPDATE (EDIT)
// ============================================================
if ($aksi == 'update') {
    $id_asset        = (int)$_POST['id_asset'];
    // Tambahkan id_barang
    $id_barang       = !empty($_POST['id_barang']) ? (int)$_POST['id_barang'] : 'NULL';
    $nama_asset      = mysqli_real_escape_string($koneksi, $_POST['nama_asset']);
    $merk            = mysqli_real_escape_string($koneksi, $_POST['merk'] ?? '');
    $model           = mysqli_real_escape_string($koneksi, $_POST['model'] ?? '');
    $spesifikasi     = mysqli_real_escape_string($koneksi, $_POST['spesifikasi'] ?? '');
    $serial_number   = mysqli_real_escape_string($koneksi, $_POST['serial_number'] ?? '');
    $no_imei         = mysqli_real_escape_string($koneksi, $_POST['no_imei'] ?? '');
    $sumber          = mysqli_real_escape_string($koneksi, $_POST['sumber_perolehan'] ?? 'MANUAL');
    $tgl_perolehan   = mysqli_real_escape_string($koneksi, $_POST['tgl_perolehan'] ?? date('Y-m-d'));
    $harga_perolehan = (float)($_POST['harga_perolehan'] ?? 0);
    $supplier        = mysqli_real_escape_string($koneksi, $_POST['supplier'] ?? '');
    $no_request      = mysqli_real_escape_string($koneksi, $_POST['no_request'] ?? '');
    $tgl_garansi_mulai   = !empty($_POST['tgl_garansi_mulai'])   ? "'" . $_POST['tgl_garansi_mulai'] . "'" : 'NULL';
    $tgl_garansi_selesai = !empty($_POST['tgl_garansi_selesai']) ? "'" . $_POST['tgl_garansi_selesai'] . "'" : 'NULL';
    $keterangan      = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');
    $departemen      = mysqli_real_escape_string($koneksi, $_POST['departemen'] ?? '');
    $pengguna        = mysqli_real_escape_string($koneksi, $_POST['pengguna'] ?? '');
    $foto_lama       = mysqli_real_escape_string($koneksi, $_POST['foto_lama'] ?? '');

    // Kondisi - cek apakah berubah untuk otomatis insert history
    $kondisi_baru    = mysqli_real_escape_string($koneksi, $_POST['kondisi'] ?? 'BAGUS');
    $status_asset    = mysqli_real_escape_string($koneksi, $_POST['status_asset'] ?? 'AKTIF');

    // Ambil kondisi & lokasi lama
    $q_lama = mysqli_query($koneksi, "SELECT kondisi, lokasi, pengguna FROM master_it_asset WHERE id_asset = $id_asset");
    $lama   = mysqli_fetch_assoc($q_lama);
    $kondisi_lama  = $lama['kondisi'];
    $lokasi_lama   = $lama['lokasi'];
    $pengguna_lama = $lama['pengguna'];

    // Lokasi
    $lokasi_sel = $_POST['lokasi'] ?? '';
    $lokasi_man = $_POST['lokasi_manual'] ?? '';
    $lokasi     = ($lokasi_sel == '_manual_') ? $lokasi_man : $lokasi_sel;
    $lokasi     = mysqli_real_escape_string($koneksi, $lokasi);

    // Upload foto
    $foto = uploadFoto($_FILES['foto'] ?? [], $foto_lama);
    $foto_val = $foto ? "'$foto'" : 'NULL';

   $sql = "UPDATE master_it_asset SET
        id_barang       = $id_barang,
        nama_asset      = '$nama_asset',
        merk            = '$merk',
        model           = '$model',
        spesifikasi     = '$spesifikasi',
        serial_number   = '$serial_number',
        no_imei         = '$no_imei',
        sumber_perolehan = '$sumber',
        tgl_perolehan   = '$tgl_perolehan',
        harga_perolehan = $harga_perolehan,
        supplier        = '$supplier',
        no_request      = '$no_request',
        tgl_garansi_mulai   = $tgl_garansi_mulai,
        tgl_garansi_selesai = $tgl_garansi_selesai,
        kondisi         = '$kondisi_baru',
        status_asset    = '$status_asset',
        lokasi          = '$lokasi',
        pengguna        = '$pengguna',
        departemen      = '$departemen',
        keterangan      = '$keterangan',
        foto            = $foto_val,
        updated_by      = '$nama',
        updated_at      = NOW()
        WHERE id_asset  = $id_asset";

    if (mysqli_query($koneksi, $sql)) {
        // Jika kondisi berubah, tambah history otomatis
        if ($kondisi_lama !== $kondisi_baru) {
            $ket_hist = mysqli_real_escape_string($koneksi, "Update kondisi dari $kondisi_lama menjadi $kondisi_baru.");
            mysqli_query($koneksi, "INSERT INTO tr_it_asset_history
                (id_asset, tgl_kejadian, jenis_history, kondisi_sebelum, kondisi_sesudah, keterangan, created_by)
                VALUES ($id_asset, CURDATE(), 'KONDISI UPDATE', '$kondisi_lama', '$kondisi_baru', '$ket_hist', '$nama')");
        }
        // Jika lokasi berubah
        if ($lokasi_lama !== $lokasi) {
            $ket_hist2 = mysqli_real_escape_string($koneksi, "Pindah lokasi dari '$lokasi_lama' ke '$lokasi'.");
            mysqli_query($koneksi, "INSERT INTO tr_it_asset_history
                (id_asset, tgl_kejadian, jenis_history, lokasi_sebelum, lokasi_sesudah, keterangan, created_by)
                VALUES ($id_asset, CURDATE(), 'PINDAH LOKASI', '$lokasi_lama', '$lokasi', '$ket_hist2', '$nama')");
        }
        $_SESSION['flash_success'] = "Aset IT berhasil diperbarui!";
        header("Location: detail_asset.php?id=$id_asset");
    } else {
        $_SESSION['flash_error'] = "Gagal update: " . mysqli_error($koneksi);
        header("Location: form_asset.php?id=$id_asset");
    }
    exit;
}

// ============================================================
// PROSES HAPUS
// ============================================================
if ($aksi == 'hapus') {
    $id_asset = (int)$_GET['id'];

    // Ambil info dulu
    $q = mysqli_query($koneksi, "SELECT kode_asset, foto FROM master_it_asset WHERE id_asset = $id_asset");
    $row = mysqli_fetch_assoc($q);

    // Hapus foto
    if (!empty($row['foto'])) {
        $foto_path = __DIR__ . '/../../uploads/it_asset/' . $row['foto'];
        if (file_exists($foto_path)) unlink($foto_path);
    }

    // Hapus (ON DELETE CASCADE otomatis hapus history)
    if (mysqli_query($koneksi, "DELETE FROM master_it_asset WHERE id_asset = $id_asset")) {
        $_SESSION['flash_success'] = "Aset <strong>" . $row['kode_asset'] . "</strong> berhasil dihapus.";
    } else {
        $_SESSION['flash_error'] = "Gagal menghapus aset.";
    }
    header("Location: index.php");
    exit;
}

// Fallback
header("Location: index.php");
exit;
?>