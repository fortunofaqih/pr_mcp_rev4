<?php
/**
 * proses_simpan.php
 * Endpoint penyimpanan data Fund Transfer BCA.
 */

// ============================================================
// LANGKAH 1: MATIKAN SEMUA OUTPUT ERROR SEBELUM APA-APA
// ============================================================
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Buffer SEMUA output supaya kalau ada warning/notice, tidak bocor ke response
ob_start();

// Set header JSON SEBELUM apapun
header('Content-Type: application/json; charset=utf-8');

// ============================================================
// LANGKAH 2: CATCH SEMUA ERROR (termasuk fatal error) 
// ============================================================
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Bersihkan semua buffer yang mungkin sudah terisi
        while (ob_get_level() > 0) { ob_end_clean(); }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $err['message'] . ' di ' . $err['file'] . ':' . $err['line']
        ]);
    }
});

// ============================================================
// LANGKAH 3: VALIDASI FILE INCLUDE
// ============================================================
$koneksi_path   = __DIR__ . '/../../config/koneksi.php';
$session_path   = __DIR__ . '/../../auth/check_session.php';

if (!file_exists($koneksi_path)) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'File koneksi.php tidak ditemukan di: ' . $koneksi_path
    ]);
    exit;
}
if (!file_exists($session_path)) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'File check_session.php tidak ditemukan di: ' . $session_path
    ]);
    exit;
}

session_start();
require_once $koneksi_path;
require_once $session_path;

// Bersihkan buffer setelah require (jaga-jaga kalau ada notice)
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit;
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function ambilSebagaiString($key, $default = '') {
    if (!isset($_POST[$key])) return $default;
    $val = $_POST[$key];
    if (is_array($val)) {
        return implode(', ', array_map('strval', $val));
    }
    return (string) $val;
}

function bersihkanAngka($key, $default = 0) {
    if (!isset($_POST[$key]) || $_POST[$key] === '') return $default;
    $val = $_POST[$key];
    if (is_array($val)) $val = '0';
    $val = preg_replace('/[^0-9\-.]/', '', (string) $val);
    return $val === '' ? $default : $val;
}

// ============================================================
// PROSES UTAMA
// ============================================================
try {
    // Pastikan koneksi valid
    if (!isset($koneksi) || !$koneksi) {
        throw new Exception('Koneksi database gagal. Cek config/koneksi.php');
    }
    
    if ($koneksi->connect_error) {
        throw new Exception('Koneksi error: ' . $koneksi->connect_error);
    }

    // Tanggal
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

    // Field checkbox (array → string)
    $jenis_pengiriman = ambilSebagaiString('jenis_pengiriman');

    // A. PENERIMA
    $rekening_penerima        = $_POST['rekening_penerima'] ?? '';
    $nama_penerima            = $_POST['nama_penerima'] ?? '';
    $alamat_penerima          = $_POST['alamat_penerima'] ?? '';
    $kota_penerima            = $_POST['kota_penerima'] ?? '';
    $state_penerima           = $_POST['state_penerima'] ?? '';
    $negara_penerima          = $_POST['negara_penerima'] ?? '';
    $kode_negara_penerima     = $_POST['kode_negara_penerima'] ?? '';
    $tipe_nasabah             = ambilSebagaiString('tipe_nasabah');
    $status_nasabah           = ambilSebagaiString('status_nasabah');
    $kewarganegaraan_penerima = ambilSebagaiString('kewarganegaraan_penerima');

    // B. BANK
    $nama_bank         = $_POST['nama_bank'] ?? '';
    $alamat_bank       = $_POST['alamat_bank'] ?? '';
    $kota_bank         = $_POST['kota_bank'] ?? '';
    $state_bank        = $_POST['state_bank'] ?? '';
    $negara_bank       = $_POST['negara_bank'] ?? '';
    $kode_negara_bank  = $_POST['kode_negara_bank'] ?? '';
    $swift_code        = $_POST['swift_code'] ?? '';

    // C. PENGIRIM
    $nama_pengirim              = $_POST['nama_pengirim'] ?? '';
    $no_ktp                     = $_POST['no_ktp'] ?? '';
    $alamat_pengirim            = $_POST['alamat_pengirim'] ?? '';
    $kontak_person              = $_POST['kontak_person'] ?? '';
    $no_hp                      = $_POST['no_hp'] ?? '';
    $no_telp                    = $_POST['no_telp'] ?? '';
    $email_pengirim             = $_POST['email_pengirim'] ?? '';
    $kota_pengirim              = $_POST['kota_pengirim'] ?? '';
    $tipe_nasabah_pengirim      = ambilSebagaiString('tipe_nasabah_pengirim');
    $status_pengirim            = ambilSebagaiString('status_pengirim');
    $kewarganegaraan_pengirim   = ambilSebagaiString('kewarganegaraan_pengirim');
    $rekening_bca               = $_POST['rekening_bca'] ?? '';

    // D. DATA
    $hubungan_keuangan = $_POST['hubungan_keuangan'] ?? '';
    $tujuan_transaksi  = $_POST['tujuan_transaksi'] ?? '';
    $berita            = $_POST['berita'] ?? '';

    // Sumber Dana
    $sd_tunai        = isset($_POST['sd_tunai'])    ? 1 : 0;
    $sd_tunai_rp     = bersihkanAngka('sd_tunai_rp');
    $sd_tabungan     = isset($_POST['sd_tabungan']) ? 1 : 0;
    $sd_tabungan_no  = $_POST['sd_tabungan_no'] ?? '';
    $sd_tabungan_rp  = bersihkanAngka('sd_tabungan_rp');
    $sd_cek          = isset($_POST['sd_cek'])      ? 1 : 0;
    $sd_cek_no       = $_POST['sd_cek_no'] ?? '';
    $sd_cek_rp       = bersihkanAngka('sd_cek_rp');

    // JUMLAH
    $mata_uang   = $_POST['mata_uang'] ?? 'IDR';
    $jml_valas   = bersihkanAngka('jml_valas');
    $kurs        = bersihkanAngka('kurs');
    $jml_rupiah  = bersihkanAngka('jml_rupiah');
    $provisi     = bersihkanAngka('provisi');
    $biaya       = bersihkanAngka('biaya');
    $jml_total   = bersihkanAngka('jml_total');
    $terbilang   = $_POST['terbilang'] ?? '';

    // LAINNYA
    $biaya_koresponden = $_POST['biaya_koresponden'] ?? '';
    $today_value       = isset($_POST['today_value']) ? 1 : 0;
    $instruksi_khusus  = $_POST['instruksi_khusus'] ?? '';
    $operator          = $_POST['operator'] ?? '';
    $verifier          = $_POST['verifier'] ?? '';

    // Validasi
    if (empty($nama_penerima) || empty($rekening_penerima)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Nama Penerima dan Rekening Penerima wajib diisi']);
        exit;
    }

    $sql = "INSERT INTO fund_transfer_bca (
        tanggal, jenis_pengiriman,
        rekening_penerima, nama_penerima, alamat_penerima, kota_penerima, state_penerima,
        negara_penerima, kode_negara_penerima, tipe_nasabah, status_nasabah, kewarganegaraan_penerima,
        nama_bank, alamat_bank, kota_bank, state_bank, negara_bank, kode_negara_bank, swift_code,
        nama_pengirim, no_ktp, alamat_pengirim, kontak_person, no_hp, no_telp, email_pengirim,
        kota_pengirim, tipe_nasabah_pengirim, status_pengirim, kewarganegaraan_pengirim, rekening_bca,
        hubungan_keuangan, tujuan_transaksi, berita,
        sd_tunai, sd_tunai_rp, sd_tabungan, sd_tabungan_no, sd_tabungan_rp,
        sd_cek, sd_cek_no, sd_cek_rp,
        mata_uang, jml_valas, kurs, jml_rupiah, provisi, biaya, jml_total, terbilang,
        biaya_koresponden, today_value, instruksi_khusus, operator, verifier,
        created_by
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?
    )";

    $stmt = $koneksi->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Gagal prepare query: ' . $koneksi->error);
    }

    $created_by = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'system';
    $types = str_repeat('s', 56);

    $stmt->bind_param(
        $types,
        $tanggal, $jenis_pengiriman,
        $rekening_penerima, $nama_penerima, $alamat_penerima, $kota_penerima, $state_penerima,
        $negara_penerima, $kode_negara_penerima, $tipe_nasabah, $status_nasabah, $kewarganegaraan_penerima,
        $nama_bank, $alamat_bank, $kota_bank, $state_bank, $negara_bank, $kode_negara_bank, $swift_code,
        $nama_pengirim, $no_ktp, $alamat_pengirim, $kontak_person, $no_hp, $no_telp, $email_pengirim,
        $kota_pengirim, $tipe_nasabah_pengirim, $status_pengirim, $kewarganegaraan_pengirim, $rekening_bca,
        $hubungan_keuangan, $tujuan_transaksi, $berita,
        $sd_tunai, $sd_tunai_rp, $sd_tabungan, $sd_tabungan_no, $sd_tabungan_rp,
        $sd_cek, $sd_cek_no, $sd_cek_rp,
        $mata_uang, $jml_valas, $kurs, $jml_rupiah, $provisi, $biaya, $jml_total, $terbilang,
        $biaya_koresponden, $today_value, $instruksi_khusus, $operator, $verifier,
        $created_by
    );

    if (!$stmt->execute()) {
        throw new Exception('Gagal execute: ' . $stmt->error);
    }

    $id = $koneksi->insert_id;
    $stmt->close();

    if (ob_get_length()) ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Data berhasil disimpan',
        'id' => $id
    ]);

} catch (\Throwable $e) {
    error_log('proses_simpan.php error: ' . $e->getMessage());
    while (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

if (isset($koneksi) && $koneksi) {
    $koneksi->close();
}