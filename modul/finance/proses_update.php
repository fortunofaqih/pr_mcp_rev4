<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan']);
    exit;
}

if (!isset($_SESSION['status']) || $_SESSION['status'] !== 'login') {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid']);
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

/* =========================================================
 * HELPER
 * ========================================================= */

/**
 * Ambil value dari POST sebagai string.
 * Kalau field-nya array (checkbox), gabung dengan koma.
 * Konsisten dengan proses_simpan.php → tidak ada JSON, tidak ada '[]'.
 *
 * Catatan: karena checkbox/radio akan dicentang manual oleh user
 * di kertas, field-field ini biasanya tetap kosong di form edit.
 * Kalau user tidak mengubah apapun, nilainya tetap '' (string kosong).
 */
function ambilSebagaiString($key, $default = '') {
    if (!isset($_POST[$key])) return $default;
    $val = $_POST[$key];
    if (is_array($val)) {
        // Buang nilai kosong, lalu gabung koma
        $val = array_filter(array_map('strval', $val), function ($v) {
            return $v !== '';
        });
        return implode(', ', $val);
    }
    return (string) $val;
}

function postVal($key, $default = '') {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

/* =========================================================
 * AMBIL DATA DARI FORM
 * ========================================================= */

$tanggal                   = postVal('tanggal');
$jenis_pengiriman          = ambilSebagaiString('jenis_pengiriman');

$rekening_penerima         = postVal('rekening_penerima');
$nama_penerima             = postVal('nama_penerima');
$alamat_penerima           = postVal('alamat_penerima');
$kota_penerima             = postVal('kota_penerima');
$state_penerima            = postVal('state_penerima');
$negara_penerima           = postVal('negara_penerima');
$kode_negara_penerima      = postVal('kode_negara_penerima');
$tipe_nasabah              = ambilSebagaiString('tipe_nasabah');
$status_nasabah            = ambilSebagaiString('status_nasabah');
$kewarganegaraan_penerima  = ambilSebagaiString('kewarganegaraan_penerima');

$nama_bank                 = postVal('nama_bank');
$alamat_bank               = postVal('alamat_bank');
$kota_bank                 = postVal('kota_bank');
$state_bank                = postVal('state_bank');
$negara_bank               = postVal('negara_bank');
$kode_negara_bank          = postVal('kode_negara_bank');
$swift_code                = postVal('swift_code');

$nama_pengirim             = postVal('nama_pengirim');
$no_ktp                    = postVal('no_ktp');
$alamat_pengirim           = postVal('alamat_pengirim');
$kontak_person             = postVal('kontak_person');
$no_hp                     = postVal('no_hp');
$no_telp                   = postVal('no_telp');
$email_pengirim            = postVal('email_pengirim');
$kota_pengirim             = postVal('kota_pengirim');
$tipe_nasabah_pengirim     = ambilSebagaiString('tipe_nasabah_pengirim');
$status_pengirim           = ambilSebagaiString('status_pengirim');
$kewarganegaraan_pengirim  = ambilSebagaiString('kewarganegaraan_pengirim');
$rekening_bca              = postVal('rekening_bca');

$hubungan_keuangan         = postVal('hubungan_keuangan');
$tujuan_transaksi          = postVal('tujuan_transaksi');
$berita                    = postVal('berita');

$sd_tunai                  = isset($_POST['sd_tunai'])     ? 1 : 0;
$sd_tunai_rp               = postVal('sd_tunai_rp', 0);
$sd_tabungan               = isset($_POST['sd_tabungan'])  ? 1 : 0;
$sd_tabungan_no            = postVal('sd_tabungan_no');
$sd_tabungan_rp            = postVal('sd_tabungan_rp', 0);
$sd_cek                    = isset($_POST['sd_cek'])       ? 1 : 0;
$sd_cek_no                 = postVal('sd_cek_no');
$sd_cek_rp                 = postVal('sd_cek_rp', 0);

$mata_uang                 = postVal('mata_uang', 'IDR');
$jml_valas                 = (float) postVal('jml_valas', 0);
$kurs                      = (float) postVal('kurs', 0);
$jml_rupiah                = (float) postVal('jml_rupiah', 0);
$provisi                   = (float) postVal('provisi', 0);
$biaya                     = (float) postVal('biaya', 0);
$jml_total                 = (float) postVal('jml_total', 0);
$terbilang                 = postVal('terbilang');

$biaya_koresponden         = postVal('biaya_koresponden');
$today_value               = isset($_POST['today_value']) ? 1 : 0;
$instruksi_khusus          = postVal('instruksi_khusus');
$operator                  = postVal('operator');
$verifier                  = postVal('verifier');

/* =========================================================
 * PASTIKAN DATA ADA
 * ========================================================= */
$checkStmt = $koneksi->prepare("SELECT id FROM fund_transfer_bca WHERE id = ?");
$checkStmt->bind_param('i', $id);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();
if ($checkRes->num_rows === 0) {
    $checkStmt->close();
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    exit;
}
$checkStmt->close();

/* =========================================================
 * QUERY UPDATE
 * ========================================================= */
$sql = "
    UPDATE fund_transfer_bca SET
        tanggal = ?,
        jenis_pengiriman = ?,
        rekening_penerima = ?,
        nama_penerima = ?,
        alamat_penerima = ?,
        kota_penerima = ?,
        state_penerima = ?,
        negara_penerima = ?,
        kode_negara_penerima = ?,
        tipe_nasabah = ?,
        status_nasabah = ?,
        kewarganegaraan_penerima = ?,
        nama_bank = ?,
        alamat_bank = ?,
        kota_bank = ?,
        state_bank = ?,
        negara_bank = ?,
        kode_negara_bank = ?,
        swift_code = ?,
        nama_pengirim = ?,
        no_ktp = ?,
        alamat_pengirim = ?,
        kontak_person = ?,
        no_hp = ?,
        no_telp = ?,
        email_pengirim = ?,
        kota_pengirim = ?,
        tipe_nasabah_pengirim = ?,
        status_pengirim = ?,
        kewarganegaraan_pengirim = ?,
        rekening_bca = ?,
        hubungan_keuangan = ?,
        tujuan_transaksi = ?,
        berita = ?,
        sd_tunai = ?,
        sd_tunai_rp = ?,
        sd_tabungan = ?,
        sd_tabungan_no = ?,
        sd_tabungan_rp = ?,
        sd_cek = ?,
        sd_cek_no = ?,
        sd_cek_rp = ?,
        mata_uang = ?,
        jml_valas = ?,
        kurs = ?,
        jml_rupiah = ?,
        provisi = ?,
        biaya = ?,
        jml_total = ?,
        terbilang = ?,
        biaya_koresponden = ?,
        today_value = ?,
        instruksi_khusus = ?,
        operator = ?,
        verifier = ?
    WHERE id = ?
";

$stmt = $koneksi->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare gagal: ' . $koneksi->error]);
    exit;
}

$stmt->bind_param(
    'sssssssssssssssssssssssssssssssssssssssssssssssssssssssi',
    $tanggal,
    $jenis_pengiriman,
    $rekening_penerima,
    $nama_penerima,
    $alamat_penerima,
    $kota_penerima,
    $state_penerima,
    $negara_penerima,
    $kode_negara_penerima,
    $tipe_nasabah,
    $status_nasabah,
    $kewarganegaraan_penerima,
    $nama_bank,
    $alamat_bank,
    $kota_bank,
    $state_bank,
    $negara_bank,
    $kode_negara_bank,
    $swift_code,
    $nama_pengirim,
    $no_ktp,
    $alamat_pengirim,
    $kontak_person,
    $no_hp,
    $no_telp,
    $email_pengirim,
    $kota_pengirim,
    $tipe_nasabah_pengirim,
    $status_pengirim,
    $kewarganegaraan_pengirim,
    $rekening_bca,
    $hubungan_keuangan,
    $tujuan_transaksi,
    $berita,
    $sd_tunai,
    $sd_tunai_rp,
    $sd_tabungan,
    $sd_tabungan_no,
    $sd_tabungan_rp,
    $sd_cek,
    $sd_cek_no,
    $sd_cek_rp,
    $mata_uang,
    $jml_valas,
    $kurs,
    $jml_rupiah,
    $provisi,
    $biaya,
    $jml_total,
    $terbilang,
    $biaya_koresponden,
    $today_value,
    $instruksi_khusus,
    $operator,
    $verifier,
    $id
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Data berhasil diupdate', 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal update: ' . $stmt->error]);
}

$stmt->close();
$koneksi->close();