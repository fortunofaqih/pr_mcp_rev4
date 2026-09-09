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

// Gunakan template yang sama dengan halaman utama untuk print
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Fund Transfer BCA</title>
    <style>
        /* Copy semua style dari halaman utama di sini */
        * { box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            padding: 0;
            margin: 0;
            background: white;
        }
        .print-sheet {
            width: 190mm;
            height: 270mm;
            margin: 0 auto;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 9pt;
            font-weight: 600;
            color: #000;
            line-height: 1.15;
            position: relative;
        }
        .print-sheet .val {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Tanggal */
        .p-tgl {
            position: absolute;
            top: 17mm;
            left: 5mm;
            font-size: 9pt;
            letter-spacing: 2mm;
            word-spacing: 5mm;
        }
        .p-jenis {
            position: absolute;
            top: 15mm;
            left: 95mm;
            font-size: 9pt;
            font-weight: 600;
        }

        /* A (kiri) */
        .p-rek-penerima { position: absolute; top: 38mm; left: 16mm; width: 85mm; font-size: 9pt; }
        .p-nama-penerima { position: absolute; top: 43mm; left: 16mm; width: 85mm; font-size: 9pt; }
        .p-alamat-penerima { position: absolute; top: 48mm; left: 16mm; width: 85mm; font-size: 9pt; }
        .p-kota-penerima { position: absolute; top: 52mm; left: 16mm; width: 85mm; font-size: 9pt; }
        .p-kode-negara-penerima { position: absolute; top: 68mm; left: 50mm; width: 40mm; font-size: 9pt; }
        .p-tipe-a { position: absolute; top: 71mm; left: 16mm; font-size: 9pt; font-weight: 600; }
        .p-status-a { position: absolute; top: 75mm; left: 16mm; font-size: 9pt; font-weight: 600; }
        .p-kw-a { position: absolute; top: 78mm; left: 16mm; font-size: 9pt; font-weight: 600; }

        /* B (kanan) */
        .p-nama-bank { position: absolute; top: 38mm; left: 140mm; width: 85mm; font-size: 9pt; }
        .p-alamat-bank { position: absolute; top: 43mm; left: 140mm; width: 85mm; font-size: 9pt; }
        .p-kota-bank { position: absolute; top: 48mm; left: 140mm; width: 85mm; font-size: 9pt; }
        .p-state-bank { position: absolute; top: 53mm; left: 140mm; width: 85mm; font-size: 9pt; }
        .p-negara-bank { position: absolute; top: 58mm; left: 140mm; width: 50mm; font-size: 9pt; }
        .p-kode-negara-bank { position: absolute; top: 61mm; left: 140mm; width: 40mm; font-size: 9pt; }
        .p-swift { position: absolute; top: 65mm; left: 140mm; width: 131mm; font-size: 9pt; }

        /* C (kiri bawah) */
        .p-nama-pengirim { position: absolute; top: 80mm; left: 16mm; width: 85mm; font-size: 9pt; }
        .p-ktp { position: absolute; top: 85mm; left: 16mm; width: 50mm; font-size: 9pt; }
        .p-alamat-pengirim { position: absolute; top: 90mm; left: 16mm; width: 85mm; font-size: 9pt; }
        .p-kontak { position: absolute; top: 95mm; left: 16mm; width: 50mm; font-size: 9pt; }
        .p-hp { position: absolute; top: 100mm; left: 16mm; width: 50mm; font-size: 9pt; }
        .p-kota-pengirim { position: absolute; top: 105mm; left: 16mm; width: 50mm; font-size: 9pt; }
        .p-rek-bca { position: absolute; top: 125mm; left: 16mm; width: 60mm; font-size: 9pt; }
        .p-tipe-c { position: absolute; top: 114mm; left: 16mm; font-size: 9pt; font-weight: 600; }
        .p-status-c { position: absolute; top: 114mm; left: 65mm; font-size: 9pt; font-weight: 600; }
        .p-kw-c { position: absolute; top: 122.5mm; left: 16mm; font-size: 9pt; font-weight: 600; }

        /* D (kanan bawah) */
        .p-hub-keuangan { position: absolute; top: 85mm; left: 120mm; font-size: 9pt; }
        .p-tujuan { position: absolute; top: 92mm; left: 120mm; width: 85mm; font-size: 9pt; }
        .p-berita { position: absolute; top: 99mm; left: 120mm; width: 85mm; font-size: 9pt; }
        .p-sumber-dana { position: absolute; top: 110mm; left: 120mm; width: 85mm; font-size: 9pt; }

        /* Biaya kor + Operator */
        .p-biaya-kor { position: absolute; top: 140mm; left: 16mm; font-size: 9pt; }
        .p-operator { position: absolute; top: 140mm; left: 140mm; width: 28mm; font-size: 9pt; }
        .p-verifier { position: absolute; top: 140mm; left: 172mm; width: 25mm; font-size: 9pt; }

        /* Jumlah */
        .p-mata-uang { position: absolute; top: 200mm; left: 11mm; width: 20mm; font-size: 9pt; }
        .p-jml-valas { position: absolute; top: 200mm; left: 8mm; width: 40mm; text-align: right; font-size: 9pt; }
        .p-kurs { position: absolute; top: 200mm; left: 47mm; width: 28mm; text-align: right; font-size: 9pt; }
        .p-jml-rupiah { position: absolute; top: 200mm; left: 60mm; width: 45mm; text-align: right; font-size: 9pt; }
        .p-provisi { position: absolute; top: 205mm; left: 60mm; width: 45mm; text-align: right; font-size: 9pt; }
        .p-biaya { position: absolute; top: 210mm; left: 60mm; width: 45mm; text-align: right; font-size: 9pt; }
        .p-total { position: absolute; top: 215mm; left: 60mm; width: 45mm; text-align: right; font-size: 9pt; }
        .p-terbilang { position: absolute; top: 220mm; left: 12mm; width: 170mm; font-size: 9pt; white-space: normal; }

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
        }
    </style>
</head>
<body onload="window.print()">
    <div class="print-sheet">
        <div class="p-tgl val" id="pTgl">
            <?= date('d', strtotime($data['tanggal'])) ?>  
            <?= date('m', strtotime($data['tanggal'])) ?>  
            <?= date('y', strtotime($data['tanggal'])) ?>
        </div>
        <div class="p-jenis val" id="pJenis"><?= htmlspecialchars($data['jenis_pengiriman']) ?></div>

        <div class="p-rek-penerima val" id="pRekPenerima"><?= htmlspecialchars($data['rekening_penerima']) ?></div>
        <div class="p-nama-penerima val" id="pNamaPenerima"><?= htmlspecialchars($data['nama_penerima']) ?></div>
        <div class="p-alamat-penerima val" id="pAlamatPenerima"><?= htmlspecialchars($data['alamat_penerima']) ?></div>
        <div class="p-kota-penerima val" id="pKotaPenerima"><?= htmlspecialchars($data['kota_penerima']) ?></div>
        <div class="p-kode-negara-penerima val" id="pKodeNegaraPenerima"><?= htmlspecialchars($data['kode_negara_penerima']) ?></div>
        <div class="p-tipe-a val" id="pTipeA"><?= htmlspecialchars($data['tipe_nasabah']) ?></div>
        <div class="p-status-a val" id="pStatusA"><?= htmlspecialchars($data['status_nasabah']) ?></div>
        <div class="p-kw-a val" id="pKwA"><?= htmlspecialchars($data['kewarganegaraan_penerima']) ?></div>

        <div class="p-nama-bank val" id="pNamaBank"><?= htmlspecialchars($data['nama_bank']) ?></div>
        <div class="p-alamat-bank val" id="pAlamatBank"><?= htmlspecialchars($data['alamat_bank']) ?></div>
        <div class="p-kota-bank val" id="pKotaBank"><?= htmlspecialchars($data['kota_bank']) ?></div>
        <div class="p-state-bank val" id="pStateBank"><?= htmlspecialchars($data['state_bank']) ?></div>
        <div class="p-negara-bank val" id="pNegaraBank"><?= htmlspecialchars($data['negara_bank']) ?></div>
        <div class="p-kode-negara-bank val" id="pKodeNegaraBank"><?= htmlspecialchars($data['kode_negara_bank']) ?></div>
        <div class="p-swift val" id="pSwift"><?= htmlspecialchars($data['swift_code']) ?></div>

        <div class="p-nama-pengirim val" id="pNamaPengirim"><?= htmlspecialchars($data['nama_pengirim']) ?></div>
        <div class="p-ktp val" id="pKtp"><?= htmlspecialchars($data['no_ktp']) ?></div>
        <div class="p-alamat-pengirim val" id="pAlamatPengirim"><?= htmlspecialchars($data['alamat_pengirim']) ?></div>
        <div class="p-kontak val" id="pKontak"><?= htmlspecialchars($data['kontak_person']) ?></div>
        <div class="p-hp val" id="pHp"><?= htmlspecialchars($data['no_hp']) ?></div>
        <div class="p-kota-pengirim val" id="pKotaPengirim"><?= htmlspecialchars($data['kota_pengirim']) ?></div>
        <div class="p-rek-bca val" id="pRekBca"><?= htmlspecialchars($data['rekening_bca']) ?></div>
        <div class="p-tipe-c val" id="pTipeC"><?= htmlspecialchars($data['tipe_nasabah_pengirim']) ?></div>
        <div class="p-status-c val" id="pStatusC"><?= htmlspecialchars($data['status_pengirim']) ?></div>
        <div class="p-kw-c val" id="pKwC"><?= htmlspecialchars($data['kewarganegaraan_pengirim']) ?></div>

        <div class="p-hub-keuangan val" id="pHubKeuangan"><?= htmlspecialchars($data['hubungan_keuangan']) ?></div>
        <div class="p-tujuan val" id="pTujuan"><?= htmlspecialchars($data['tujuan_transaksi']) ?></div>
        <div class="p-berita val" id="pBerita"><?= htmlspecialchars($data['berita']) ?></div>
        <div class="p-sumber-dana val" id="pSumberDana">
            <?php
            $sumber = [];
            if ($data['sd_tunai']) $sumber[] = 'Tunai Rp ' . number_format($data['sd_tunai_rp'], 0, ',', '.');
            if ($data['sd_tabungan']) $sumber[] = 'Tabungan No.' . $data['sd_tabungan_no'] . ' Rp ' . number_format($data['sd_tabungan_rp'], 0, ',', '.');
            if ($data['sd_cek']) $sumber[] = 'Cek No.' . $data['sd_cek_no'] . ' Rp ' . number_format($data['sd_cek_rp'], 0, ',', '.');
            echo htmlspecialchars(implode(' | ', $sumber));
            ?>
        </div>

        <div class="p-biaya-kor val" id="pBiayaKor"><?= htmlspecialchars($data['biaya_koresponden']) ?></div>
        <div class="p-operator val" id="pOperator"><?= htmlspecialchars($data['operator']) ?></div>
        <div class="p-verifier val" id="pVerifier"><?= htmlspecialchars($data['verifier']) ?></div>

        <div class="p-mata-uang val" id="pMataUang"><?= htmlspecialchars($data['mata_uang']) ?></div>
        <div class="p-jml-valas val" id="pJmlValas"><?= number_format($data['jml_valas'], 2, ',', '.') ?></div>
        <div class="p-kurs val" id="pKurs"><?= number_format($data['kurs'], 2, ',', '.') ?></div>
        <div class="p-jml-rupiah val" id="pJmlRupiah">Rp <?= number_format($data['jml_rupiah'], 0, ',', '.') ?></div>
        <div class="p-provisi val" id="pProvisi">Rp <?= number_format($data['provisi'], 0, ',', '.') ?></div>
        <div class="p-biaya val" id="pBiaya">Rp <?= number_format($data['biaya'], 0, ',', '.') ?></div>
        <div class="p-total val" id="pTotal">Rp <?= number_format($data['jml_total'], 0, ',', '.') ?></div>
        <div class="p-terbilang val" id="pTerbilang"><?= htmlspecialchars($data['terbilang']) ?></div>
    </div>
</body>
</html>