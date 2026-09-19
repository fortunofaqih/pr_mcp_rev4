<?php
/**
 * proses_simpan.php
 * Endpoint penyimpanan data Fund Transfer BCA.
 *
 * PERBAIKAN UTAMA dari versi sebelumnya:
 * 1. Field checkbox (jenis_pengiriman[], tipe_nasabah[], dst) dikirim sebagai
 *    ARRAY oleh browser, tapi sebelumnya langsung di-bind sebagai string.
 *    PHP mengonversi array->string secara diam-diam menjadi teks "Array"
 *    (itu kenapa di tabel Anda muncul nilai literal "Array"), DAN memicu
 *    PHP Warning "Array to string conversion". Warning inilah yang bocor
 *    ke output SEBELUM json_encode dipanggil, sehingga response yang
 *    diterima fetch() bukan JSON murni lagi -> muncul error
 *    "SyntaxError: token '<' is not valid JSON".
 *    FIX: setiap field checkbox di-implode() jadi string dulu.
 *
 * 2. Menambahkan output buffering (ob_start) + mematikan display_errors,
 *    supaya kalau ada warning/notice lain di masa depan, itu TIDAK akan
 *    pernah ikut tercetak ke response dan merusak JSON. Semua error
 *    dicatat ke error_log server, bukan ditampilkan ke browser.
 *
 * 3. catch (\Throwable $e) bukan hanya (Exception $e), supaya TypeError/
 *    Error (misalnya "Call to a member function bind_param() on bool")
 *    juga tertangkap dan tetap mengembalikan JSON yang valid.
 *
 * 4. Validasi hasil prepare() sebelum bind_param(), dan sanitasi angka
 *    (hapus pemisah ribuan) sebelum masuk kolom decimal.
 */

session_start();

// --- Jangan biarkan warning/notice PHP tercetak ke output (JSON harus bersih) ---
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

// Bersihkan buffer dari output apa pun yang mungkin sudah tercetak
// (misalnya notice dari file yang di-require di atas)
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit;
}

/**
 * Helper: ambil field yang bisa berupa array (checkbox) ATAU string,
 * dan selalu kembalikan string yang aman untuk disimpan.
 */
function ambilSebagaiString($key, $default = '') {
    if (!isset($_POST[$key])) {
        return $default;
    }
    $val = $_POST[$key];
    if (is_array($val)) {
        return implode(', ', array_map('strval', $val));
    }
    return (string) $val;
}

/**
 * Helper: bersihkan input angka (hapus titik/koma pemisah ribuan)
 * supaya aman dimasukkan ke kolom decimal.
 */
function bersihkanAngka($key, $default = 0) {
    if (!isset($_POST[$key]) || $_POST[$key] === '') {
        return $default;
    }
    $val = $_POST[$key];
    if (is_array($val)) {
        $val = '0';
    }
    // Hilangkan semua karakter selain digit, minus, dan titik desimal
    $val = preg_replace('/[^0-9\-.]/', '', (string) $val);
    return $val === '' ? $default : $val;
}

try {
    // Tanggal
    $tanggal = $_POST['tanggal'] ?? date('Y-m-d');

    // Jenis pengiriman (checkbox array)
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
    $sd_tunai        = isset($_POST['sd_tunai']) ? 1 : 0;
    $sd_tunai_rp      = bersihkanAngka('sd_tunai_rp');
    $sd_tabungan     = isset($_POST['sd_tabungan']) ? 1 : 0;
    $sd_tabungan_no   = $_POST['sd_tabungan_no'] ?? '';
    $sd_tabungan_rp   = bersihkanAngka('sd_tabungan_rp');
    $sd_cek          = isset($_POST['sd_cek']) ? 1 : 0;
    $sd_cek_no        = $_POST['sd_cek_no'] ?? '';
    $sd_cek_rp        = bersihkanAngka('sd_cek_rp');

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

    // Validasi sederhana
    if (empty($nama_penerima) || empty($rekening_penerima)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Nama Penerima dan Rekening Penerima wajib diisi']);
        exit;
    }

    // Query insert — 56 kolom, 56 placeholder
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
        ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?
    )";

    $stmt = $koneksi->prepare($sql);

    if ($stmt === false) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query: ' . $koneksi->error]);
        exit;
    }

    $created_by = $_SESSION['username'] ?? $_SESSION['nama'] ?? 'system';

    // 56 karakter tipe, dihitung otomatis supaya selalu sinkron dengan jumlah kolom
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

    if ($stmt->execute()) {
        $id = $koneksi->insert_id;
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Data berhasil disimpan',
            'id' => $id
        ]);
    } else {
        $errMsg = $stmt->error;
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data: ' . $errMsg]);
    }

    $stmt->close();

} catch (\Throwable $e) {
    // Tangkap SEMUA jenis error (Exception, Error, TypeError, dll)
    error_log('proses_simpan.php error: ' . $e->getMessage());
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

if (isset($koneksi)) {
    $koneksi->close();
}