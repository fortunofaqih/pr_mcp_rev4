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

    if (empty($file['name'])) return $foto_lama;

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed)) return $foto_lama;
    if ($file['size'] > 2 * 1024 * 1024) return $foto_lama;

    $nama_file = 'asset_' . time() . '_' . rand(100, 999) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $nama_file)) {
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
// HELPER: Proses Kondisi (untuk SIMPAN dan UPDATE)
// ============================================================
function prosesKondisi($koneksi, $kondisi_sel, $kondisi_man, $max_length = 150) {
    // Trim dan uppercase
    $kondisi_sel = trim($kondisi_sel ?? '');
    $kondisi_man = trim($kondisi_man ?? '');
    
    if ($kondisi_sel === 'MANUAL' && !empty($kondisi_man)) {
        // Ambil dari input manual
        $kondisi = substr(mysqli_real_escape_string($koneksi, strtoupper($kondisi_man)), 0, $max_length);
        
        // Auto-simpan ke master_it_kondisi jika belum ada
        $cek_kondisi = mysqli_query($koneksi, "SELECT id_kondisi FROM master_it_kondisi WHERE UPPER(nama_kondisi) = UPPER('$kondisi')");
        if (mysqli_num_rows($cek_kondisi) == 0) {
            mysqli_query($koneksi, "INSERT INTO master_it_kondisi (nama_kondisi) VALUES ('$kondisi')");
        }
        
        return $kondisi;
    } else {
        // Ambil dari dropdown
        if (!empty($kondisi_sel) && $kondisi_sel !== 'MANUAL') {
            return substr(mysqli_real_escape_string($koneksi, $kondisi_sel), 0, $max_length);
        }
    }
    
    // Default
    return 'BAGUS';
}

// ============================================================
// HELPER: Proses Harga
// ============================================================
function prosesHarga($harga_input) {
    // Hilangkan titik, koma, dan spasi
    $harga_bersih = preg_replace('/[^0-9]/', '', $harga_input);
    return (float)$harga_bersih;
}

// ============================================================
// PROSES SIMPAN (INSERT BARU)
// ============================================================
if ($aksi == 'simpan') {
    $kode_asset      = mysqli_real_escape_string($koneksi, $_POST['kode_asset']);
    $id_barang       = !empty($_POST['id_barang']) ? (int)$_POST['id_barang'] : 'NULL';
    $nama_asset      = mysqli_real_escape_string($koneksi, $_POST['nama_asset']);
    $merk            = mysqli_real_escape_string($koneksi, $_POST['merk'] ?? '');
    $model           = mysqli_real_escape_string($koneksi, $_POST['model'] ?? '');
    $spesifikasi     = mysqli_real_escape_string($koneksi, $_POST['spesifikasi'] ?? '');
    $serial_number   = mysqli_real_escape_string($koneksi, $_POST['serial_number'] ?? '');
    $no_imei         = mysqli_real_escape_string($koneksi, $_POST['no_imei'] ?? '');
    $sumber          = mysqli_real_escape_string($koneksi, $_POST['sumber_perolehan'] ?? 'MANUAL');
    $tgl_perolehan   = mysqli_real_escape_string($koneksi, $_POST['tgl_perolehan'] ?? date('Y-m-d'));
	$keterangan_kondisi = mysqli_real_escape_string($koneksi, $_POST['keterangan_kondisi'] ?? '');
    $keterangan_penempatan = mysqli_real_escape_string($koneksi, $_POST['keterangan_penempatan'] ?? '');
	$keterangan_barang = mysqli_real_escape_string($koneksi, $_POST['keterangan_barang'] ?? '');
    
    
    // Proses harga - HILANGKAN TITIK
    $harga_perolehan = prosesHarga($_POST['harga_perolehan'] ?? '0');
    
    $supplier        = mysqli_real_escape_string($koneksi, $_POST['supplier'] ?? '');
    $no_request      = mysqli_real_escape_string($koneksi, $_POST['no_request'] ?? '');
    $tgl_garansi_mulai   = !empty($_POST['tgl_garansi_mulai'])   ? "'" . $_POST['tgl_garansi_mulai'] . "'" : 'NULL';
    $tgl_garansi_selesai = !empty($_POST['tgl_garansi_selesai']) ? "'" . $_POST['tgl_garansi_selesai'] . "'" : 'NULL';
    
    // ============================================================
    // PROSES KONDISI - PERBAIKAN UTAMA
    // ============================================================
    $kondisi_sel = $_POST['kondisi'] ?? '';
    $kondisi_man = $_POST['kondisi_manual'] ?? '';
    $kondisi = prosesKondisi($koneksi, $kondisi_sel, $kondisi_man);
    
    $status_asset    = mysqli_real_escape_string($koneksi, $_POST['status_asset'] ?? 'AKTIF');
    $keterangan      = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');
    $departemen      = mysqli_real_escape_string($koneksi, $_POST['departemen'] ?? '');
    $pengguna        = mysqli_real_escape_string($koneksi, $_POST['pengguna'] ?? '');

    // ============================================================
    // PROSES LOKASI
    // ============================================================
    $lokasi_sel = $_POST['lokasi'] ?? '';
    $lokasi_man = $_POST['lokasi_manual'] ?? '';

    if ($lokasi_sel == '_manual_' && !empty($lokasi_man)) {
        $lokasi = mysqli_real_escape_string($koneksi, $lokasi_man);
        $cek_lokasi = mysqli_query($koneksi, "SELECT id_lokasi FROM master_it_lokasi WHERE nama_lokasi = '$lokasi'");
        if (mysqli_num_rows($cek_lokasi) == 0) {
            mysqli_query($koneksi, "INSERT INTO master_it_lokasi (nama_lokasi) VALUES ('$lokasi')");
        }
    } else {
        $lokasi = mysqli_real_escape_string($koneksi, $lokasi_sel);
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
         kondisi, keterangan_kondisi, status_asset, lokasi, keterangan_penempatan, 
         pengguna, departemen, keterangan, keterangan_barang, foto,
         created_by, created_at)
        VALUES
        ('$kode_asset', '$nama_asset', $id_barang, '$merk', '$model', '$spesifikasi', '$serial_number', '$no_imei',
         '$sumber', '$tgl_perolehan', $harga_perolehan, '$supplier', '$no_request',
         $tgl_garansi_mulai, $tgl_garansi_selesai,
         '$kondisi', '$keterangan_kondisi', '$status_asset', '$lokasi', '$keterangan_penempatan', 
         '$pengguna', '$departemen', '$keterangan', '$keterangan_barang', $foto_val,
         '$nama', NOW())";

    if (mysqli_query($koneksi, $sql)) {
        $id_baru = mysqli_insert_id($koneksi);
        updateKodeCounter($koneksi, $kode_asset);
        
        // Auto-insert riwayat PENERIMAAN
        $ket_awal = mysqli_real_escape_string($koneksi, "Aset diterima/didaftarkan. Kondisi: $kondisi. Lokasi: $lokasi.");
        mysqli_query($koneksi, "INSERT INTO tr_it_asset_history
            (id_asset, tgl_kejadian, jenis_history, kondisi_sebelum, kondisi_sesudah, lokasi_sesudah, pengguna_sesudah, keterangan, created_by)
            VALUES ($id_baru, '$tgl_perolehan', 'PENERIMAAN', NULL, '$kondisi', '$lokasi', '$pengguna', '$ket_awal', '$nama')");

        $_SESSION['flash_success'] = "Aset IT <strong>$kode_asset</strong> berhasil disimpan!";
        //header("Location: detail_asset.php?id=$id_baru");
		header("Location: index.php");
		exit;
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
    $id_barang       = !empty($_POST['id_barang']) ? (int)$_POST['id_barang'] : 'NULL';
    $nama_asset      = mysqli_real_escape_string($koneksi, $_POST['nama_asset']);
    $merk            = mysqli_real_escape_string($koneksi, $_POST['merk'] ?? '');
    $model           = mysqli_real_escape_string($koneksi, $_POST['model'] ?? '');
    $spesifikasi     = mysqli_real_escape_string($koneksi, $_POST['spesifikasi'] ?? '');
    $serial_number   = mysqli_real_escape_string($koneksi, $_POST['serial_number'] ?? '');
    $no_imei         = mysqli_real_escape_string($koneksi, $_POST['no_imei'] ?? '');
    $sumber          = mysqli_real_escape_string($koneksi, $_POST['sumber_perolehan'] ?? 'MANUAL');
    $tgl_perolehan   = mysqli_real_escape_string($koneksi, $_POST['tgl_perolehan'] ?? date('Y-m-d'));
	$keterangan_kondisi = mysqli_real_escape_string($koneksi, $_POST['keterangan_kondisi'] ?? '');
    $keterangan_penempatan = mysqli_real_escape_string($koneksi, $_POST['keterangan_penempatan'] ?? '');
	$keterangan_barang = mysqli_real_escape_string($koneksi, $_POST['keterangan_barang'] ?? '');
    
    // Proses harga - HILANGKAN TITIK
    $harga_perolehan = prosesHarga($_POST['harga_perolehan'] ?? '0');
    
    $supplier        = mysqli_real_escape_string($koneksi, $_POST['supplier'] ?? '');
    $no_request      = mysqli_real_escape_string($koneksi, $_POST['no_request'] ?? '');
    $tgl_garansi_mulai   = !empty($_POST['tgl_garansi_mulai'])   ? "'" . $_POST['tgl_garansi_mulai'] . "'" : 'NULL';
    $tgl_garansi_selesai = !empty($_POST['tgl_garansi_selesai']) ? "'" . $_POST['tgl_garansi_selesai'] . "'" : 'NULL';
    $keterangan      = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');
    $departemen      = mysqli_real_escape_string($koneksi, $_POST['departemen'] ?? '');
    $pengguna        = mysqli_real_escape_string($koneksi, $_POST['pengguna'] ?? '');
    $foto_lama       = mysqli_real_escape_string($koneksi, $_POST['foto_lama'] ?? '');

    // ============================================================
    // PROSES KONDISI - PERBAIKAN UTAMA
    // ============================================================
    $kondisi_sel = $_POST['kondisi'] ?? '';
    $kondisi_man = $_POST['kondisi_manual'] ?? '';
    $kondisi_baru = prosesKondisi($koneksi, $kondisi_sel, $kondisi_man);
    
    $status_asset    = mysqli_real_escape_string($koneksi, $_POST['status_asset'] ?? 'AKTIF');

    // Ambil kondisi & lokasi lama untuk history
    $q_lama = mysqli_query($koneksi, "SELECT kondisi, keterangan_kondisi, lokasi, keterangan_penempatan, pengguna FROM master_it_asset WHERE id_asset = $id_asset");
    $lama   = mysqli_fetch_assoc($q_lama);
    $kondisi_lama  = $lama['kondisi'] ?? '';
    $ket_kondisi_lama = $lama['keterangan_kondisi'] ?? '';
    $lokasi_lama   = $lama['lokasi'] ?? '';
    $ket_penempatan_lama = $lama['keterangan_penempatan'] ?? '';
    $pengguna_lama = $lama['pengguna'] ?? '';

    // ============================================================
    // PROSES LOKASI
    // ============================================================
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
        keterangan_kondisi = '$keterangan_kondisi',
        status_asset    = '$status_asset',
        lokasi          = '$lokasi',
        keterangan_penempatan = '$keterangan_penempatan',
        pengguna        = '$pengguna',
        departemen      = '$departemen',
        keterangan      = '$keterangan',
        keterangan_barang = '$keterangan_barang',
        foto            = $foto_val,
        updated_by      = '$nama',
        updated_at      = NOW()
        WHERE id_asset  = $id_asset";

    if (mysqli_query($koneksi, $sql)) {
        // Jika kondisi berubah, tambah history
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

    $q = mysqli_query($koneksi, "SELECT kode_asset, foto FROM master_it_asset WHERE id_asset = $id_asset");
    $row = mysqli_fetch_assoc($q);

    if (!empty($row['foto'])) {
        $foto_path = __DIR__ . '/../../uploads/it_asset/' . $row['foto'];
        if (file_exists($foto_path)) unlink($foto_path);
    }

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