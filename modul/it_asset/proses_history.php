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
// SIMPAN HISTORY BARU
// ============================================================
if ($aksi == '' || !isset($_GET['aksi'])) {
    // POST = simpan baru
    $id_asset     = (int)($_POST['id_asset'] ?? 0);
    $tgl_kejadian = mysqli_real_escape_string($koneksi, $_POST['tgl_kejadian'] ?? date('Y-m-d'));
    $jenis        = mysqli_real_escape_string($koneksi, $_POST['jenis_history'] ?? 'CATATAN');
    $kondisi_sblm = !empty($_POST['kondisi_sebelum']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['kondisi_sebelum']) . "'" : 'NULL';
    $kondisi_ssdh = !empty($_POST['kondisi_sesudah']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['kondisi_sesudah']) . "'" : 'NULL';
    $lokasi_sblm  = mysqli_real_escape_string($koneksi, $_POST['lokasi_sebelum'] ?? '');
    $lokasi_ssdh  = mysqli_real_escape_string($koneksi, $_POST['lokasi_sesudah'] ?? '');
    $pggn_sblm    = mysqli_real_escape_string($koneksi, $_POST['pengguna_sebelum'] ?? '');
    $pggn_ssdh    = mysqli_real_escape_string($koneksi, $_POST['pengguna_sesudah'] ?? '');
    $vendor       = mysqli_real_escape_string($koneksi, $_POST['vendor_servis'] ?? '');
    $biaya        = (float)($_POST['biaya_servis'] ?? 0);
    $est_selesai  = !empty($_POST['tgl_estimasi_selesai']) ? "'" . $_POST['tgl_estimasi_selesai'] . "'" : 'NULL';
    $tgl_selesai  = !empty($_POST['tgl_selesai_servis'])   ? "'" . $_POST['tgl_selesai_servis'] . "'" : 'NULL';
    $keterangan   = mysqli_real_escape_string($koneksi, $_POST['keterangan'] ?? '');

    $sql = "INSERT INTO tr_it_asset_history
        (id_asset, tgl_kejadian, jenis_history,
         kondisi_sebelum, kondisi_sesudah,
         lokasi_sebelum, lokasi_sesudah,
         pengguna_sebelum, pengguna_sesudah,
         vendor_servis, biaya_servis,
         tgl_estimasi_selesai, tgl_selesai_servis,
         keterangan, created_by)
        VALUES
        ($id_asset, '$tgl_kejadian', '$jenis',
         $kondisi_sblm, $kondisi_ssdh,
         '$lokasi_sblm', '$lokasi_ssdh',
         '$pggn_sblm', '$pggn_ssdh',
         '$vendor', $biaya,
         $est_selesai, $tgl_selesai,
         '$keterangan', '$nama')";

    if (mysqli_query($koneksi, $sql)) {
        // Update kondisi aset jika ada perubahan kondisi
        $cond_val = trim($_POST['kondisi_sesudah'] ?? '');
        if ($cond_val) {
            $cond_esc = mysqli_real_escape_string($koneksi, $cond_val);
            mysqli_query($koneksi, "UPDATE master_it_asset SET kondisi='$cond_esc', updated_by='$nama', updated_at=NOW() WHERE id_asset=$id_asset");
        }

        // Update lokasi jika ada perpindahan
        $lok_val = trim($_POST['lokasi_sesudah'] ?? '');
        if ($lok_val && $jenis == 'PINDAH LOKASI') {
            $lok_esc = mysqli_real_escape_string($koneksi, $lok_val);
            mysqli_query($koneksi, "UPDATE master_it_asset SET lokasi='$lok_esc', updated_by='$nama', updated_at=NOW() WHERE id_asset=$id_asset");
        }

        // Update pengguna jika pindah pengguna
        $pggn_val = trim($_POST['pengguna_sesudah'] ?? '');
        if ($pggn_val && $jenis == 'PINDAH PENGGUNA') {
            $pggn_esc = mysqli_real_escape_string($koneksi, $pggn_val);
            mysqli_query($koneksi, "UPDATE master_it_asset SET pengguna='$pggn_esc', updated_by='$nama', updated_at=NOW() WHERE id_asset=$id_asset");
        }

        $_SESSION['flash_success'] = "Riwayat berhasil ditambahkan!";
    } else {
        $_SESSION['flash_error'] = "Gagal menyimpan riwayat: " . mysqli_error($koneksi);
    }

    header("Location: detail_asset.php?id=$id_asset");
    exit;
}

// ============================================================
// HAPUS HISTORY
// ============================================================
if ($aksi == 'hapus') {
    $id_history = (int)($_GET['id'] ?? 0);
    $id_asset   = (int)($_GET['id_asset'] ?? 0);

    if (mysqli_query($koneksi, "DELETE FROM tr_it_asset_history WHERE id_history = $id_history")) {
        $_SESSION['flash_success'] = "Riwayat berhasil dihapus.";
    } else {
        $_SESSION['flash_error'] = "Gagal menghapus riwayat.";
    }
    header("Location: detail_asset.php?id=$id_asset");
    exit;
}

header("Location: index.php");
exit;
?>