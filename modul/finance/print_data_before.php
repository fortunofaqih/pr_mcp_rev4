<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    die('ID tidak valid');
}

$query = "SELECT * FROM fund_transfer_bca WHERE id = ?";
$stmt = $koneksi->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Data tidak ditemukan');
}

$data = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Fund Transfer BCA</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Bookman Old Style', 'Times New Roman', Times, serif;
            background: white;
            margin: 0;
            padding: 0;
        }

        .print-sheet {
            width: 215mm;
            height: 180mm;
            margin: 0 auto;
            padding: 0;
            font-family: 'Bookman Old Style', 'Times New Roman', Times, serif;
            font-size: 9pt;
            font-weight: 600;
            color: #000;
            line-height: 1.15;
            position: relative;
            overflow: hidden;
        }

        .print-sheet .val {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ===================== TANGGAL & JENIS ===================== */
        .p-tgl {
            position: absolute;
            top: 24mm;
            left: 33mm;
            font-size: 9pt;
            letter-spacing: 2.2mm;
            word-spacing: 3.8mm;
        }
        .p-jenis {
            position: absolute;
            top: 11mm;
            left: 118mm;
            font-size: 9pt;
        }

        /* ===================== BAGIAN A ===================== */
        .p-rek-penerima        { position: absolute; top: 33mm; left: 42mm; width: 90mm; }
        .p-nama-penerima       { position: absolute; top: 38mm; left: 42mm; width: 90mm; }
        .p-alamat-penerima     { position: absolute; top: 43mm; left: 42mm; width: 90mm; }
        .p-kota-penerima       { position: absolute; top: 48mm; left: 42mm; width: 90mm; }
        .p-kode-negara-penerima{ position: absolute; top: 63mm; left: 65mm; width: 40mm; }
        .p-tipe-a              { position: absolute; top: 68mm; left: 42mm; }
        .p-status-a            { position: absolute; top: 72mm; left: 42mm; }
        .p-kw-a                { position: absolute; top: 76mm; left: 42mm; }

        /* ===================== BAGIAN B ===================== */
        /* Semua digeser kanan 1cm, dan seluruh grup dinaikkan agar jarak antar baris tetap rapat */
        .p-nama-bank           { position: absolute; top: 33mm; left: 138mm; width: 90mm; }
        .p-alamat-bank         { position: absolute; top: 38mm; left: 138mm; width: 90mm; }
        .p-kota-bank           { position: absolute; top: 43mm; left: 138mm; width: 90mm; }
        .p-state-bank          { position: absolute; top: 48mm; left: 138mm; width: 90mm; }
        .p-negara-bank         { position: absolute; top: 53mm; left: 138mm; width: 50mm; }
        .p-kode-negara-bank    { position: absolute; top: 58mm; left: 138mm; width: 40mm; }
        .p-swift               { position: absolute; top: 66mm; left: 138mm; width: 90mm; }

        /* ===================== BAGIAN C ===================== */
        .p-nama-pengirim       { position: absolute; top: 78mm; left: 42mm; width: 90mm; }
        .p-ktp                 { position: absolute; top: 83mm; left: 42mm; width: 60mm; }
        .p-alamat-pengirim     { position: absolute; top: 88mm; left: 42mm; width: 90mm; }
        .p-kontak              { position: absolute; top: 93mm; left: 42mm; width: 60mm; }
        .p-hp                  { position: absolute; top: 98mm; left: 42mm; width: 60mm; }
        .p-kota-pengirim       { position: absolute; top: 103mm; left: 42mm; width: 60mm; }
        .p-rek-bca             { position: absolute; top: 108mm; left: 42mm; width: 70mm; }
        .p-tipe-c              { position: absolute; top: 113mm; left: 42mm; }
        .p-status-c            { position: absolute; top: 117mm; left: 75mm; }
        .p-kw-c                { position: absolute; top: 121mm; left: 42mm; }

        /* ===================== BAGIAN D ===================== */
        .p-hub-keuangan        { position: absolute; top: 78mm; left: 138mm; }
        .p-tujuan              { position: absolute; top: 83mm; left: 138mm; width: 90mm; }
        .p-berita              { position: absolute; top: 88mm; left: 138mm; width: 90mm; }
        .p-sumber-dana         { position: absolute; top: 94mm; left: 138mm; width: 90mm; }

        /* ===================== OPERATOR ===================== */
        .p-biaya-kor           { position: absolute; top: 118mm; left: 32mm; }
        .p-operator            { position: absolute; top: 118mm; left: 140mm; width: 30mm; }
        .p-verifier            { position: absolute; top: 118mm; left: 175mm; width: 28mm; }

        /* ===================== JUMLAH ===================== */
        .p-mata-uang           { position: absolute; top: 150mm; left: 42mm; width: 22mm; }
        .p-jml-valas           { position: absolute; top: 150mm; left: 35mm; width: 40mm; text-align: right; }
        .p-kurs                { position: absolute; top: 150mm; left: 65mm; width: 28mm; text-align: right; }
        .p-jml-rupiah          { position: absolute; top: 150mm; left: 75mm; width: 45mm; text-align: right; }
        .p-provisi             { position: absolute; top: 155mm; left: 75mm; width: 45mm; text-align: right; }
        .p-biaya               { position: absolute; top: 160mm; left: 75mm; width: 45mm; text-align: right; }
        .p-total               { position: absolute; top: 165mm; left: 75mm; width: 45mm; text-align: right; }
        .p-terbilang           { position: absolute; top: 170mm; left: 20mm; width: 190mm; white-space: normal; }

        /* ===================== PRINT SETTINGS ===================== */
        @media print {
            @page {
                size: 215mm 180mm portrait;
                margin: 0;
            }
            html, body {
                margin: 0 !important;
                padding: 0 !important;
                width: 215mm !important;
                height: 180mm !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .print-sheet {
                width: 215mm !important;
                height: 180mm !important;
                margin: 0 !important;
                page-break-after: avoid;
                transform: none !important;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="print-sheet">

        <div class="p-tgl val">
            <?= date('d', strtotime($data['tanggal'])) ?>  
            <?= date('m', strtotime($data['tanggal'])) ?>  
            <?= date('y', strtotime($data['tanggal'])) ?>
        </div>
        <div class="p-jenis val"><?= htmlspecialchars($data['jenis_pengiriman']) ?></div>

        <!-- A -->
        <div class="p-rek-penerima val"><?= htmlspecialchars($data['rekening_penerima']) ?></div>
        <div class="p-nama-penerima val"><?= htmlspecialchars($data['nama_penerima']) ?></div>
        <div class="p-alamat-penerima val"><?= htmlspecialchars($data['alamat_penerima']) ?></div>
        <div class="p-kota-penerima val"><?= htmlspecialchars($data['kota_penerima']) ?></div>
        <div class="p-kode-negara-penerima val"><?= htmlspecialchars($data['kode_negara_penerima']) ?></div>
        <div class="p-tipe-a val"><?= htmlspecialchars($data['tipe_nasabah']) ?></div>
        <div class="p-status-a val"><?= htmlspecialchars($data['status_nasabah']) ?></div>
        <div class="p-kw-a val"><?= htmlspecialchars($data['kewarganegaraan_penerima']) ?></div>

        <!-- B -->
        <div class="p-nama-bank val"><?= htmlspecialchars($data['nama_bank']) ?></div>
        <div class="p-alamat-bank val"><?= htmlspecialchars($data['alamat_bank']) ?></div>
        <div class="p-kota-bank val"><?= htmlspecialchars($data['kota_bank']) ?></div>
        <div class="p-state-bank val"><?= htmlspecialchars($data['state_bank']) ?></div>
        <div class="p-negara-bank val"><?= htmlspecialchars($data['negara_bank']) ?></div>
        <div class="p-kode-negara-bank val"><?= htmlspecialchars($data['kode_negara_bank']) ?></div>
        <div class="p-swift val"><?= htmlspecialchars($data['swift_code']) ?></div>

        <!-- C -->
        <div class="p-nama-pengirim val"><?= htmlspecialchars($data['nama_pengirim']) ?></div>
        <div class="p-ktp val"><?= htmlspecialchars($data['no_ktp']) ?></div>
        <div class="p-alamat-pengirim val"><?= htmlspecialchars($data['alamat_pengirim']) ?></div>
        <div class="p-kontak val"><?= htmlspecialchars($data['kontak_person']) ?></div>
        <div class="p-hp val"><?= htmlspecialchars($data['no_hp']) ?></div>
        <div class="p-kota-pengirim val"><?= htmlspecialchars($data['kota_pengirim']) ?></div>
        <div class="p-rek-bca val"><?= htmlspecialchars($data['rekening_bca']) ?></div>
        <div class="p-tipe-c val"><?= htmlspecialchars($data['tipe_nasabah_pengirim']) ?></div>
        <div class="p-status-c val"><?= htmlspecialchars($data['status_pengirim']) ?></div>
        <div class="p-kw-c val"><?= htmlspecialchars($data['kewarganegaraan_pengirim']) ?></div>

        <!-- D -->
        <div class="p-hub-keuangan val"><?= htmlspecialchars($data['hubungan_keuangan']) ?></div>
        <div class="p-tujuan val"><?= htmlspecialchars($data['tujuan_transaksi']) ?></div>
        <div class="p-berita val"><?= htmlspecialchars($data['berita']) ?></div>
        <div class="p-sumber-dana val">
            <?php
            $sumber = [];
            if (!empty($data['sd_tunai'])) $sumber[] = 'Tunai Rp ' . number_format($data['sd_tunai_rp'], 0, ',', '.');
            if (!empty($data['sd_tabungan'])) $sumber[] = 'Tabungan No.' . $data['sd_tabungan_no'] . ' Rp ' . number_format($data['sd_tabungan_rp'], 0, ',', '.');
            if (!empty($data['sd_cek'])) $sumber[] = 'Cek No.' . $data['sd_cek_no'] . ' Rp ' . number_format($data['sd_cek_rp'], 0, ',', '.');
            echo htmlspecialchars(implode(' | ', $sumber));
            ?>
        </div>

        <div class="p-biaya-kor val"><?= htmlspecialchars($data['biaya_koresponden']) ?></div>
        <div class="p-operator val"><?= htmlspecialchars($data['operator']) ?></div>
        <div class="p-verifier val"><?= htmlspecialchars($data['verifier']) ?></div>

        <!-- Jumlah -->
        <div class="p-mata-uang val"><?= htmlspecialchars($data['mata_uang']) ?></div>
        <div class="p-jml-valas val"><?= number_format($data['jml_valas'], 2, ',', '.') ?></div>
        <div class="p-kurs val"><?= number_format($data['kurs'], 2, ',', '.') ?></div>
        <div class="p-jml-rupiah val">Rp <?= number_format($data['jml_rupiah'], 0, ',', '.') ?></div>
        <div class="p-provisi val">Rp <?= number_format($data['provisi'], 0, ',', '.') ?></div>
        <div class="p-biaya val">Rp <?= number_format($data['biaya'], 0, ',', '.') ?></div>
        <div class="p-total val">Rp <?= number_format($data['jml_total'], 0, ',', '.') ?></div>
        <div class="p-terbilang val"><?= htmlspecialchars($data['terbilang']) ?></div>

    </div>
</body>
</html>