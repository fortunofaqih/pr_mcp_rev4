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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Fund Transfer BCA</title>
    <style>
        /* Reset dan base */
        * { 
            box-sizing: border-box; 
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            padding: 0;
            margin: 0;
            background: white;
        }
        
        .print-sheet {
            width: 190mm;
            height: 277mm;
            margin: 0 auto;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 10pt;
            color: #000;
            line-height: 1.2;
            position: relative;
            background: white;
        }
        
        .print-sheet .val {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-family: 'Times New Roman', Times, serif;
        }

        /* === HEADER / TANGGAL === */
        .p-tgl {
            position: absolute;
            top: 18mm;
            left: 14mm;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            letter-spacing: 1.5mm;
        }
        .p-jenis {
            position: absolute;
            top: 18mm;
            left: 110mm;
            font-size: 9pt;
            font-weight: bold;
            font-family: 'Times New Roman', Times, serif;
        }

        /* === SECTION A - PENERIMA (Kiri) === */
        .p-rek-penerima { 
            position: absolute; 
            top: 42mm; 
            left: 14mm; 
            width: 75mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-nama-penerima { 
            position: absolute; 
            top: 47mm; 
            left: 14mm; 
            width: 75mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-alamat-penerima { 
            position: absolute; 
            top: 52mm; 
            left: 14mm; 
            width: 75mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-kota-penerima { 
            position: absolute; 
            top: 57mm; 
            left: 14mm; 
            width: 75mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-kode-negara-penerima { 
            position: absolute; 
            top: 62mm; 
            left: 14mm; 
            width: 30mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-tipe-a { 
            position: absolute; 
            top: 72mm; 
            left: 14mm; 
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-status-a { 
            position: absolute; 
            top: 77mm; 
            left: 14mm; 
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-kw-a { 
            position: absolute; 
            top: 82mm; 
            left: 14mm; 
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }

        /* === SECTION B - BANK PENERIMA (Kanan) === */
        .p-nama-bank { 
            position: absolute; 
            top: 42mm; 
            left: 105mm; 
            width: 70mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-alamat-bank { 
            position: absolute; 
            top: 47mm; 
            left: 105mm; 
            width: 70mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-kota-bank { 
            position: absolute; 
            top: 52mm; 
            left: 105mm; 
            width: 70mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-state-bank { 
            position: absolute; 
            top: 57mm; 
            left: 105mm; 
            width: 70mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-negara-bank { 
            position: absolute; 
            top: 62mm; 
            left: 105mm; 
            width: 50mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-kode-negara-bank { 
            position: absolute; 
            top: 67mm; 
            left: 105mm; 
            width: 40mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-swift { 
            position: absolute; 
            top: 72mm; 
            left: 105mm; 
            width: 70mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }

        /* === SECTION C - PENGIRIM (Kiri Bawah) === */
        .p-nama-pengirim { 
            position: absolute; 
            top: 95mm; 
            left: 14mm; 
            width: 75mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-ktp { 
            position: absolute; 
            top: 100mm; 
            left: 14mm; 
            width: 50mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-alamat-pengirim { 
            position: absolute; 
            top: 105mm; 
            left: 14mm; 
            width: 75mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-kontak { 
            position: absolute; 
            top: 110mm; 
            left: 14mm; 
            width: 50mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-hp { 
            position: absolute; 
            top: 115mm; 
            left: 14mm; 
            width: 50mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-kota-pengirim { 
            position: absolute; 
            top: 120mm; 
            left: 14mm; 
            width: 50mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-rek-bca { 
            position: absolute; 
            top: 140mm; 
            left: 14mm; 
            width: 60mm; 
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-tipe-c { 
            position: absolute; 
            top: 130mm; 
            left: 14mm; 
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-status-c { 
            position: absolute; 
            top: 130mm; 
            left: 65mm; 
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-kw-c { 
            position: absolute; 
            top: 135mm; 
            left: 14mm; 
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }

        /* === SECTION D - DATA LAINNYA (Kanan Bawah) === */
        .p-hub-keuangan { 
            position: absolute; 
            top: 95mm; 
            left: 105mm; 
            width: 70mm;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-tujuan { 
            position: absolute; 
            top: 102mm; 
            left: 105mm; 
            width: 70mm;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-berita { 
            position: absolute; 
            top: 109mm; 
            left: 105mm; 
            width: 70mm;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-sumber-dana { 
            position: absolute; 
            top: 120mm; 
            left: 105mm; 
            width: 70mm;
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
        }

        /* === OPERATOR & VERIFIER === */
        .p-biaya-kor { 
            position: absolute; 
            top: 148mm; 
            left: 14mm; 
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-operator { 
            position: absolute; 
            top: 148mm; 
            left: 105mm; 
            width: 35mm;
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-verifier { 
            position: absolute; 
            top: 148mm; 
            left: 145mm; 
            width: 30mm;
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
        }

        /* === JUMLAH / NOMINAL === */
        .p-mata-uang { 
            position: absolute; 
            top: 205mm; 
            left: 14mm; 
            width: 20mm;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-jml-valas { 
            position: absolute; 
            top: 205mm; 
            left: 35mm; 
            width: 35mm;
            text-align: right;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-kurs { 
            position: absolute; 
            top: 205mm; 
            left: 72mm; 
            width: 28mm;
            text-align: right;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-jml-rupiah { 
            position: absolute; 
            top: 205mm; 
            left: 100mm; 
            width: 50mm;
            text-align: right;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-provisi { 
            position: absolute; 
            top: 210mm; 
            left: 100mm; 
            width: 50mm;
            text-align: right;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-biaya { 
            position: absolute; 
            top: 215mm; 
            left: 100mm; 
            width: 50mm;
            text-align: right;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
        }
        .p-total { 
            position: absolute; 
            top: 220mm; 
            left: 100mm; 
            width: 50mm;
            text-align: right;
            font-size: 10pt;
            font-family: 'Times New Roman', Times, serif;
            font-weight: bold;
        }
        .p-terbilang { 
            position: absolute; 
            top: 228mm; 
            left: 14mm; 
            width: 170mm;
            font-size: 9pt;
            font-family: 'Times New Roman', Times, serif;
            white-space: normal;
            text-transform: uppercase;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 8mm 10mm;
            }
            html, body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                height: auto !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .print-sheet {
                margin: 0 auto;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="print-sheet">
        <!-- TANGGAL -->
        <div class="p-tgl" id="pTgl">
            <?= date('d', strtotime($data['tanggal'])) ?>  
            <?= date('m', strtotime($data['tanggal'])) ?>  
            <?= date('y', strtotime($data['tanggal'])) ?>
        </div>
        <div class="p-jenis" id="pJenis"><?= htmlspecialchars($data['jenis_pengiriman']) ?></div>

        <!-- SECTION A - PENERIMA -->
        <div class="p-rek-penerima" id="pRekPenerima"><?= htmlspecialchars($data['rekening_penerima']) ?></div>
        <div class="p-nama-penerima" id="pNamaPenerima"><?= htmlspecialchars($data['nama_penerima']) ?></div>
        <div class="p-alamat-penerima" id="pAlamatPenerima"><?= htmlspecialchars($data['alamat_penerima']) ?></div>
        <div class="p-kota-penerima" id="pKotaPenerima"><?= htmlspecialchars($data['kota_penerima']) ?></div>
        <div class="p-kode-negara-penerima" id="pKodeNegaraPenerima"><?= htmlspecialchars($data['kode_negara_penerima']) ?></div>
        <div class="p-tipe-a" id="pTipeA"><?= htmlspecialchars($data['tipe_nasabah']) ?></div>
        <div class="p-status-a" id="pStatusA"><?= htmlspecialchars($data['status_nasabah']) ?></div>
        <div class="p-kw-a" id="pKwA"><?= htmlspecialchars($data['kewarganegaraan_penerima']) ?></div>

        <!-- SECTION B - BANK -->
        <div class="p-nama-bank" id="pNamaBank"><?= htmlspecialchars($data['nama_bank']) ?></div>
        <div class="p-alamat-bank" id="pAlamatBank"><?= htmlspecialchars($data['alamat_bank']) ?></div>
        <div class="p-kota-bank" id="pKotaBank"><?= htmlspecialchars($data['kota_bank']) ?></div>
        <div class="p-state-bank" id="pStateBank"><?= htmlspecialchars($data['state_bank']) ?></div>
        <div class="p-negara-bank" id="pNegaraBank"><?= htmlspecialchars($data['negara_bank']) ?></div>
        <div class="p-kode-negara-bank" id="pKodeNegaraBank"><?= htmlspecialchars($data['kode_negara_bank']) ?></div>
        <div class="p-swift" id="pSwift"><?= htmlspecialchars($data['swift_code']) ?></div>

        <!-- SECTION C - PENGIRIM -->
        <div class="p-nama-pengirim" id="pNamaPengirim"><?= htmlspecialchars($data['nama_pengirim']) ?></div>
        <div class="p-ktp" id="pKtp"><?= htmlspecialchars($data['no_ktp']) ?></div>
        <div class="p-alamat-pengirim" id="pAlamatPengirim"><?= htmlspecialchars($data['alamat_pengirim']) ?></div>
        <div class="p-kontak" id="pKontak"><?= htmlspecialchars($data['kontak_person']) ?></div>
        <div class="p-hp" id="pHp"><?= htmlspecialchars($data['no_hp']) ?></div>
        <div class="p-kota-pengirim" id="pKotaPengirim"><?= htmlspecialchars($data['kota_pengirim']) ?></div>
        <div class="p-rek-bca" id="pRekBca"><?= htmlspecialchars($data['rekening_bca']) ?></div>
        <div class="p-tipe-c" id="pTipeC"><?= htmlspecialchars($data['tipe_nasabah_pengirim']) ?></div>
        <div class="p-status-c" id="pStatusC"><?= htmlspecialchars($data['status_pengirim']) ?></div>
        <div class="p-kw-c" id="pKwC"><?= htmlspecialchars($data['kewarganegaraan_pengirim']) ?></div>

        <!-- SECTION D - DATA LAIN -->
        <div class="p-hub-keuangan" id="pHubKeuangan"><?= htmlspecialchars($data['hubungan_keuangan']) ?></div>
        <div class="p-tujuan" id="pTujuan"><?= htmlspecialchars($data['tujuan_transaksi']) ?></div>
        <div class="p-berita" id="pBerita"><?= htmlspecialchars($data['berita']) ?></div>
        <div class="p-sumber-dana" id="pSumberDana">
            <?php
            $sumber = [];
            if ($data['sd_tunai']) $sumber[] = 'Tunai Rp ' . number_format($data['sd_tunai_rp'], 0, ',', '.');
            if ($data['sd_tabungan']) $sumber[] = 'Tabungan No.' . $data['sd_tabungan_no'] . ' Rp ' . number_format($data['sd_tabungan_rp'], 0, ',', '.');
            if ($data['sd_cek']) $sumber[] = 'Cek No.' . $data['sd_cek_no'] . ' Rp ' . number_format($data['sd_cek_rp'], 0, ',', '.');
            echo htmlspecialchars(implode(' | ', $sumber));
            ?>
        </div>

        <!-- OPERATOR -->
        <div class="p-biaya-kor" id="pBiayaKor"><?= htmlspecialchars($data['biaya_koresponden']) ?></div>
        <div class="p-operator" id="pOperator"><?= htmlspecialchars($data['operator']) ?></div>
        <div class="p-verifier" id="pVerifier"><?= htmlspecialchars($data['verifier']) ?></div>

        <!-- NOMINAL -->
        <div class="p-mata-uang" id="pMataUang"><?= htmlspecialchars($data['mata_uang']) ?></div>
        <div class="p-jml-valas" id="pJmlValas"><?= number_format($data['jml_valas'], 2, ',', '.') ?></div>
        <div class="p-kurs" id="pKurs"><?= number_format($data['kurs'], 2, ',', '.') ?></div>
        <div class="p-jml-rupiah" id="pJmlRupiah">Rp <?= number_format($data['jml_rupiah'], 0, ',', '.') ?></div>
        <div class="p-provisi" id="pProvisi">Rp <?= number_format($data['provisi'], 0, ',', '.') ?></div>
        <div class="p-biaya" id="pBiaya">Rp <?= number_format($data['biaya'], 0, ',', '.') ?></div>
        <div class="p-total" id="pTotal">Rp <?= number_format($data['jml_total'], 0, ',', '.') ?></div>
        <div class="p-terbilang" id="pTerbilang"><?= htmlspecialchars($data['terbilang']) ?></div>
    </div>
</body>
</html>