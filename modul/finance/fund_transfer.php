<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fund Transfer BCA - Input Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .container-custom { max-width: 1100px; margin: 0 auto; }
        .form-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 28px 32px;
            margin-bottom: 25px;
        }
        .form-card h4 {
            color: #1a3c6e;
            border-bottom: 2px solid #1a3c6e;
            padding-bottom: 8px;
            margin-bottom: 18px;
            font-weight: 700;
            font-size: 16px;
        }
        .form-card h4 i { margin-right: 8px; }
        .form-label {
            font-weight: 600;
            font-size: 12.5px;
            color: #333;
            margin-bottom: 2px;
        }
        .form-control, .form-select {
            border-radius: 5px;
            border: 1px solid #ced4da;
            font-size: 13px;
            padding: 5px 10px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #1a3c6e;
            box-shadow: 0 0 0 0.15rem rgba(26,60,110,0.15);
        }
        .section-divider {
            border-top: 1px dashed #dee2e6;
            margin: 20px 0;
        }
        .btn-success-custom {
            background: #198754;
            border: none;
            padding: 10px 36px;
            font-weight: 700;
            border-radius: 8px;
        }
        .btn-success-custom:hover { background: #146c43; }
        .preview-box {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 12px;
            color: #555;
        }
        .preview-box strong { color: #1a3c6e; }
        .terbilang-box {
            background: #e7f3ff;
            border-left: 4px solid #1a3c6e;
            padding: 8px 14px;
            border-radius: 4px;
            font-weight: 600;
            color: #1a3c6e;
            min-height: 36px;
            font-size: 13.5px;
        }
        .date-input-group {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .date-input-group input {
            width: 48px;
            text-align: center;
            font-weight: 700;
            font-size: 15px;
            padding: 5px 3px;
        }
        .date-input-group span { font-weight: 700; font-size: 15px; }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 18px;
            margin-top: 4px;
        }
        .checkbox-group .form-check { margin-bottom: 0; min-width: 110px; }
        .checkbox-group .form-check-label { font-size: 12.5px; font-weight: 500; }
        .radio-inline { display: flex; gap: 20px; flex-wrap: wrap; }
        .radio-inline .form-check { margin-bottom: 0; }
        .jenis-pengiriman { display: flex; flex-wrap: wrap; gap: 10px 16px; }
        .sumber-dana-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        .sumber-dana-row .form-check { min-width: 110px; }
        .sumber-dana-row input[type="text"] {
            width: 90px;
            font-size: 12px;
            padding: 3px 6px;
        }
        .note-small { font-size: 11px; color: #cf1111; font-style: italic; }

        /* ===================== PRINT SHEET ===================== */
        .print-sheet { display: none; }

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
            .no-print, .container-custom, .form-card, .btn, h3 {
                display: none !important;
            }
            .print-sheet {
                display: block !important;
                position: relative;
                width: 190mm;
                height: 270mm;
                margin: 0 auto;
                font-family: Arial, Helvetica, sans-serif;
                font-size: 8pt;
                font-weight: 600;
                color: #000;
                line-height: 1.15;
                transform: none !important;
            }
            .print-sheet .val {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            /*
             * CATATAN REVISI:
             * Field-field yang sumbernya checkbox/radio (Jenis Pengiriman,
             * Tipe Nasabah, Status, Kewarganegaraan, Hubungan Keuangan,
             * Biaya Koresponden, dan seluruh Sumber Dana) SUDAH TIDAK DICETAK.
             * User akan mencentang manual di kertas dengan ballpoint.
             * Posisi CSS untuk field-field tersebut sudah dihapus dari sini,
             * dan elemen HTML-nya juga sudah dihapus dari #printSheet di bawah.
             */

            .p-tgl {
                position: absolute;
                top: 11mm;
                left: 10mm;
                font-size: 8pt;
                letter-spacing: 2mm;
                word-spacing: 5mm;
            }

            .p-rek-penerima { position: absolute; top: 26mm; left: 17mm; width: 85mm; font-size: 8pt; }
            .p-nama-penerima { position: absolute; top: 31mm; left: 17mm; width: 85mm; font-size: 8pt; }
            .p-alamat-penerima { position: absolute; top: 36mm; left: 17mm; width: 85mm; font-size: 8pt; }
            .p-kota-penerima { position: absolute; top: 40mm; left: 17mm; width: 85mm; font-size: 8pt; }
            .p-kode-negara-penerima { position: absolute; top: 62mm; left: 17mm; width: 40mm; font-size: 8pt; }

            .p-nama-bank { position: absolute; top: 26mm; left: 122mm; width: 85mm; font-size: 8pt; }
            .p-alamat-bank { position: absolute; top: 31mm; left: 122mm; width: 85mm; font-size: 8pt; }
            .p-kota-bank { position: absolute; top: 39mm; left: 122mm; width: 85mm; font-size: 8pt; }
            .p-state-bank { position: absolute; top: 41mm; left: 122mm; width: 85mm; font-size: 8pt; }
            .p-negara-bank { position: absolute; top: 43mm; left: 122mm; width: 50mm; font-size: 8pt; }
            .p-kode-negara-bank { position: absolute; top: 45mm; left: 122mm; width: 40mm; font-size: 8pt; }
            .p-swift { position: absolute; top: 43mm; left: 111mm; width: 85mm; font-size: 8pt; }

            .p-nama-pengirim { position: absolute; top: 83mm; left: 17mm; width: 85mm; font-size: 8pt; }
            .p-ktp { position: absolute; top: 85mm; left: 17mm; width: 50mm; font-size: 8pt; }
            .p-alamat-pengirim { position: absolute; top: 87mm; left: 17mm; width: 85mm; font-size: 8pt; }
            .p-kontak { position: absolute; top: 90mm; left: 17mm; width: 50mm; font-size: 8pt; }
            .p-hp { position: absolute; top: 91mm; left: 17mm; width: 50mm; font-size: 8pt; }
            .p-kota-pengirim { position: absolute; top: 109mm; left: 17mm; width: 50mm; font-size: 8pt; }
            .p-rek-bca { position: absolute; top: 123mm; left: 17mm; width: 60mm; font-size: 8pt; }

            .p-tujuan { position: absolute; top: 93mm; left: 111mm; width: 85mm; font-size: 8pt; }
            .p-berita { position: absolute; top: 100mm; left: 111mm; width: 85mm; font-size: 8pt; }

            .p-operator { position: absolute; top: 141mm; left: 131mm; width: 28mm; font-size: 8pt; }
            .p-verifier { position: absolute; top: 141mm; left: 163mm; width: 25mm; font-size: 8pt; }

            /* ===== JUMLAH: TURUN 1,5cm DAN KE KIRI 0,5cm ===== */
            .p-mata-uang { position: absolute; top: 163.5mm; left: 12.5mm; width: 20mm; font-size: 8pt; }
            .p-jml-valas { position: absolute; top: 163.5mm; left: -0.5mm; width: 40mm; text-align: right; font-size: 8pt; }
            .p-kurs { position: absolute; top: 163.5mm; left: 38.5mm; width: 28mm; text-align: right; font-size: 8pt; }
            .p-jml-rupiah { position: absolute; top: 163.5mm; left: 51.5mm; width: 45mm; text-align: right; font-size: 8pt; }
            .p-provisi { position: absolute; top: 177.5mm; left: -0.5mm; width: 40mm; text-align: right; font-size: 8pt; }
            .p-biaya { position: absolute; top: 178.5mm; left: -0.5mm; width: 40mm; text-align: right; font-size: 8pt; }
            .p-total { position: absolute; top: 179.5mm; left: 51.5mm; width: 45mm; text-align: right; font-size: 8pt; }
            .p-terbilang { position: absolute; top: 186.5mm; left: 3.5mm; width: 170mm; font-size: 8pt; white-space: normal; }
        }
    </style>
</head>
<body>

<div class="container-custom">

    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h3 class="text-primary fw-bold mb-0">
            <i class="fas fa-money-bill-transfer me-2"></i>Fund Transfer BCA
        </h3>
        <div>
            <a href="../../index.php" class="btn btn-danger fw-bold me-2">
                <i class="fas fa-arrow-left me-1"></i> KEMBALI
            </a>
            <a href="list_data.php" class="btn btn-info fw-bold me-2 text-white">
                <i class="fas fa-list me-1"></i> DATA TERSIMPAN
            </a>
            <button onclick="saveAndPrint()" class="btn btn-success-custom me-2">
                <i class="fas fa-save me-2"></i>SIMPAN & CETAK
            </button>
            <button onclick="resetForm()" class="btn btn-secondary">
                <i class="fas fa-undo me-2"></i>RESET
            </button>
        </div>
    </div>

    <form id="formTransfer" method="POST" action="proses_simpan.php">
        <div class="form-card no-print" id="formCard">

            <div class="row mb-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><i class="far fa-calendar-alt me-1"></i>Tanggal / Date</label>
                    <div class="date-input-group">
                        <input type="text" id="tglHari" name="tglHari" maxlength="2" placeholder="DD" class="form-control text-center">
                        <span>/</span>
                        <input type="text" id="tglBulan" name="tglBulan" maxlength="2" placeholder="MM" class="form-control text-center">
                        <span>/</span>
                        <input type="text" id="tglTahun" name="tglTahun" maxlength="2" placeholder="YY" class="form-control text-center">
                    </div>
                    <input type="hidden" id="tanggal" name="tanggal">
                </div>
                <div class="col-md-9">
                    <label class="form-label">Jenis Pengiriman <span class="note-small">(akan dicentang manual di kertas)</span></label>
                    <div class="jenis-pengiriman">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="jpKawat" name="jenis_pengiriman[]" value="Kawat">
                            <label class="form-check-label" for="jpKawat">Kawat</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="jpWesel" name="jenis_pengiriman[]" value="Wesel">
                            <label class="form-check-label" for="jpWesel">Wesel</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="jpRTGS" name="jenis_pengiriman[]" value="RTGS">
                            <label class="form-check-label" for="jpRTGS">RTGS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="jpBIFAST" name="jenis_pengiriman[]" value="BI-FAST">
                            <label class="form-check-label" for="jpBIFAST">BI-FAST</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="jpSKN" name="jenis_pengiriman[]" value="SKN">
                            <label class="form-check-label" for="jpSKN">SKN</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-user-check"></i> A. PENERIMA / BENEFICIARY</h4>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Nomor Rekening Penerima</label>
                    <input type="text" id="rekPenerima" name="rekening_penerima" class="form-control" value="">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Penerima</label>
                    <input type="text" id="namaPenerima" name="nama_penerima" class="form-control text-uppercase" value="">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alamat Penerima</label>
                    <input type="text" id="alamatPenerima" name="alamat_penerima" class="form-control text-uppercase" value="">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Kota</label>
                    <input type="text" id="kotaPenerima" name="kota_penerima" class="form-control text-uppercase" value="">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Negara Bagian</label>
                    <input type="text" id="statePenerima" name="state_penerima" class="form-control text-uppercase">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Negara</label>
                    <input type="text" id="negaraPenerima" name="negara_penerima" class="form-control text-uppercase">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Negara</label>
                    <input type="text" id="kodeNegaraPenerima" name="kode_negara_penerima" class="form-control text-uppercase" value="">
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-4">
                    <label class="form-label">Tipe Nasabah <span class="note-small">(dicentang manual)</span></label>
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe_nasabah[]" id="tnPerorangan" value="Perorangan">
                            <label class="form-check-label" for="tnPerorangan">Perorangan</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe_nasabah[]" id="tnPerusahaan" value="Perusahaan">
                            <label class="form-check-label" for="tnPerusahaan">Perusahaan</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe_nasabah[]" id="tnPemerintah" value="Pemerintah">
                            <label class="form-check-label" for="tnPemerintah">Pemerintah</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status <span class="note-small">(dicentang manual)</span></label>
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status_nasabah[]" id="stPenduduk" value="Penduduk">
                            <label class="form-check-label" for="stPenduduk">Penduduk</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status_nasabah[]" id="stNonPenduduk" value="Non Penduduk">
                            <label class="form-check-label" for="stNonPenduduk">Non Penduduk</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kewarganegaraan <span class="note-small">(dicentang manual)</span></label>
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kewarganegaraan_penerima[]" id="kwWNI" value="WNI">
                            <label class="form-check-label" for="kwWNI">WNI</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kewarganegaraan_penerima[]" id="kwWNA" value="WNA">
                            <label class="form-check-label" for="kwWNA">WNA</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-university"></i> B. BANK PENERIMA</h4>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Nama Bank</label>
                    <input type="text" id="namaBank" name="nama_bank" class="form-control text-uppercase" value="">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alamat Bank</label>
                    <input type="text" id="alamatBank" name="alamat_bank" class="form-control text-uppercase" value="">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kota</label>
                    <input type="text" id="kotaBank" name="kota_bank" class="form-control text-uppercase" value="">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Negara Bagian</label>
                    <input type="text" id="stateBank" name="state_bank" class="form-control text-uppercase">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Negara</label>
                    <input type="text" id="negaraBank" name="negara_bank" class="form-control text-uppercase">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Negara</label>
                    <input type="text" id="kodeNegaraBank" name="kode_negara_bank" class="form-control text-uppercase">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode SWIFT</label>
                    <input type="text" id="swiftCode" name="swift_code" class="form-control text-uppercase">
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-user-edit"></i> C. PENGIRIM / REMITTER</h4>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label">Nama Pengirim</label>
                    <input type="text" id="namaPengirim" name="nama_pengirim" class="form-control text-uppercase" value="PT. MUTIARACAHAYA PLASTINDO">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Kartu Identitas</label>
                    <input type="text" id="noKTP" name="no_ktp" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alamat Pengirim</label>
                    <input type="text" id="alamatPengirim" name="alamat_pengirim" class="form-control text-uppercase" value="MASTRIP 33 SURABAYA">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Nama yang dihubungi</label>
                    <input type="text" id="kontakPerson" name="kontak_person" class="form-control text-uppercase" value="SUSAN">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Handphone</label>
                    <input type="text" id="noHP" name="no_hp" class="form-control" value="0816528099">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" id="noTelp" name="no_telp" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input type="email" id="emailPengirim" name="email_pengirim" class="form-control">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Kota</label>
                    <input type="text" id="kotaPengirim" name="kota_pengirim" class="form-control text-uppercase" value="">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipe Nasabah <span class="note-small">(dicentang manual)</span></label>
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe_nasabah_pengirim[]" id="tnpPerorangan" value="Perorangan">
                            <label class="form-check-label" for="tnpPerorangan">Perorangan</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe_nasabah_pengirim[]" id="tnpPerusahaan" value="Perusahaan">
                            <label class="form-check-label" for="tnpPerusahaan">Perusahaan</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe_nasabah_pengirim[]" id="tnpPemerintah" value="Pemerintah">
                            <label class="form-check-label" for="tnpPemerintah">Pemerintah</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status <span class="note-small">(dicentang manual)</span></label>
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status_pengirim[]" id="stpPenduduk" value="Penduduk">
                            <label class="form-check-label" for="stpPenduduk">Penduduk</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status_pengirim[]" id="stpNonPenduduk" value="Non Penduduk">
                            <label class="form-check-label" for="stpNonPenduduk">Non Penduduk</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kewarganegaraan <span class="note-small">(dicentang manual)</span></label>
                    <div class="checkbox-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kewarganegaraan_pengirim[]" id="kwpWNI" value="WNI">
                            <label class="form-check-label" for="kwpWNI">WNI</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kewarganegaraan_pengirim[]" id="kwpWNA" value="WNA">
                            <label class="form-check-label" for="kwpWNA">WNA</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-4">
                    <label class="form-label">No. Rekening di BCA</label>
                    <input type="text" id="rekBCA" name="rekening_bca" class="form-control" value="">
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-database"></i> D. DATA</h4>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Hubungan Keuangan <span class="note-small">(dicentang manual)</span></label>
                    <div class="radio-inline mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="hubungan_keuangan" id="hkYa" value="Ya">
                            <label class="form-check-label" for="hkYa">Ya</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="hubungan_keuangan" id="hkTidak" value="Tidak">
                            <label class="form-check-label" for="hkTidak">Tidak</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tujuan Transaksi</label>
                    <input type="text" id="tujuanTransaksi" name="tujuan_transaksi" class="form-control text-uppercase" value="">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Berita / Message</label>
                    <input type="text" id="berita" name="berita" class="form-control text-uppercase" value="">
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-12">
                    <label class="form-label">Sumber Dana <span class="note-small">(seluruh bagian ini akan ditulis manual di kertas, tidak dicetak)</span></label>
                    <div class="mt-1">
                        <div class="sumber-dana-row">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sdTunai" name="sd_tunai" value="1">
                                <label class="form-check-label" for="sdTunai">Tunai</label>
                            </div>
                            <span class="text-muted small">Rp</span>
                            <input type="text" id="sdTunaiRp" name="sd_tunai_rp" class="form-control form-control-sm" placeholder="0">
                        </div>
                        <div class="sumber-dana-row">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sdTabungan" name="sd_tabungan" value="1">
                                <label class="form-check-label" for="sdTabungan">Tabungan</label>
                            </div>
                            <span class="text-muted small">No.</span>
                            <input type="text" id="sdTabunganNo" name="sd_tabungan_no" class="form-control form-control-sm" placeholder="No. Rek">
                            <span class="text-muted small">Rp</span>
                            <input type="text" id="sdTabunganRp" name="sd_tabungan_rp" class="form-control form-control-sm" placeholder="0">
                        </div>
                        <div class="sumber-dana-row">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sdCek" name="sd_cek" value="1">
                                <label class="form-check-label" for="sdCek">Cek BCA</label>
                            </div>
                            <span class="text-muted small">No.</span>
                            <input type="text" id="sdCekNo" name="sd_cek_no" class="form-control form-control-sm" placeholder="No. Cek">
                            <span class="text-muted small">Rp</span>
                            <input type="text" id="sdCekRp" name="sd_cek_rp" class="form-control form-control-sm" placeholder="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-calculator"></i> JUMLAH YANG DIKIRIM</h4>
            <div class="row g-2">
                <div class="col-md-2">
                    <label class="form-label">Mata Uang</label>
                    <select id="mataUang" name="mata_uang" class="form-select" onchange="hitungTotal()">
                        <option value="IDR" selected>IDR</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jumlah Valuta Asing</label>
                    <input type="number" id="jmlValas" name="jml_valas" class="form-control text-end" value="" placeholder="0" oninput="hitungTotal()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kurs</label>
                    <input type="number" id="kurs" name="kurs" class="form-control text-end" value="" placeholder="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jumlah Rupiah</label>
                    <input type="text" id="jmlRupiah" name="jml_rupiah" class="form-control text-end fw-bold text-primary" value="">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Provisi</label>
                    <input type="number" id="provisi" name="provisi" class="form-control text-end" value="" placeholder="0">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Biaya / Charge</label>
                    <input type="number" id="biaya" name="biaya" class="form-control text-end" value="" placeholder="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jumlah / Total</label>
                    <input type="text" id="jmlTotal" name="jml_total" class="form-control text-end fw-bold text-danger" value="">
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-12">
                    <label class="form-label">Terbilang</label>
                    <div class="terbilang-box" id="terbilangDisplay">—</div>
                    <input type="hidden" id="terbilang" name="terbilang" value="">
                </div>
            </div>

            <div class="section-divider"></div>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Biaya bank koresponden dibebankan ke: <span class="note-small">(dicentang manual)</span></label>
                    <div class="radio-inline mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="biaya_koresponden" id="bkBeneficiary" value="Penerima">
                            <label class="form-check-label" for="bkBeneficiary">Penerima</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="biaya_koresponden" id="bkRemitter" value="Pengirim">
                            <label class="form-check-label" for="bkRemitter">Pengirim</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Today Value</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="todayValue" name="today_value" value="1">
                        <label class="form-check-label" for="todayValue">Today Value</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Instruksi Khusus</label>
                    <input type="text" id="instruksiKhusus" name="instruksi_khusus" class="form-control text-uppercase">
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-3">
                    <label class="form-label">Operator</label>
                    <input type="text" id="operator" name="operator" class="form-control text-uppercase">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Verifier</label>
                    <input type="text" id="verifier" name="verifier" class="form-control text-uppercase">
                </div>
            </div>
        </div>
    </form>

    <div class="form-card no-print" style="background: #f8faff;">
        <h4><i class="fas fa-eye"></i> PREVIEW</h4>
        <div class="row">
            <div class="col-md-6">
                <div class="preview-box">
                    <strong>PENERIMA:</strong><br>
                    <span id="previewRek">-</span> — <span id="previewNamaPenerima">-</span><br>
                    <span id="previewAlamatPenerima">-</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="preview-box">
                    <strong>BANK:</strong><br>
                    <span id="previewNamaBank">-</span><br>
                    <span id="previewAlamatBank">-</span>
                </div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6">
                <div class="preview-box">
                    <strong>PENGIRIM:</strong><br>
                    <span id="previewNamaPengirim">PT. MUTIARACAHAYA PLASTINDO</span><br>
                    <span id="previewAlamatPengirim">MASTRIP 33 SURABAYA</span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="preview-box">
                    <strong>Terbilang:</strong> <span id="previewTerbilang">—</span>
                </div>
            </div>
        </div>
    </div>

</div>

<!--
     LEMBAR CETAK: HANYA ISIAN TEKS/ANGKA/TANGGAL
     Field yang sumbernya checkbox/radio (Jenis Pengiriman, Tipe Nasabah,
     Status, Kewarganegaraan, Hubungan Keuangan, Biaya Koresponden, dan
     seluruh Sumber Dana) SUDAH DIHAPUS dari lembar cetak ini, karena akan
     dicentang / ditulis manual oleh user di kertas menggunakan ballpoint.
-->
<div class="print-sheet" id="printSheet">
    <div class="p-tgl val" id="pTgl"></div>

    <div class="p-rek-penerima val" id="pRekPenerima"></div>
    <div class="p-nama-penerima val" id="pNamaPenerima"></div>
    <div class="p-alamat-penerima val" id="pAlamatPenerima"></div>
    <div class="p-kota-penerima val" id="pKotaPenerima"></div>
    <div class="p-kode-negara-penerima val" id="pKodeNegaraPenerima"></div>

    <div class="p-nama-bank val" id="pNamaBank"></div>
    <div class="p-alamat-bank val" id="pAlamatBank"></div>
    <div class="p-kota-bank val" id="pKotaBank"></div>
    <div class="p-state-bank val" id="pStateBank"></div>
    <div class="p-negara-bank val" id="pNegaraBank"></div>
    <div class="p-kode-negara-bank val" id="pKodeNegaraBank"></div>
    <div class="p-swift val" id="pSwift"></div>

    <div class="p-nama-pengirim val" id="pNamaPengirim"></div>
    <div class="p-ktp val" id="pKtp"></div>
    <div class="p-alamat-pengirim val" id="pAlamatPengirim"></div>
    <div class="p-kontak val" id="pKontak"></div>
    <div class="p-hp val" id="pHp"></div>
    <div class="p-kota-pengirim val" id="pKotaPengirim"></div>
    <div class="p-rek-bca val" id="pRekBca"></div>

    <div class="p-tujuan val" id="pTujuan"></div>
    <div class="p-berita val" id="pBerita"></div>

    <div class="p-operator val" id="pOperator"></div>
    <div class="p-verifier val" id="pVerifier"></div>

    <div class="p-mata-uang val" id="pMataUang"></div>
    <div class="p-jml-valas val" id="pJmlValas"></div>
    <div class="p-kurs val" id="pKurs"></div>
    <div class="p-jml-rupiah val" id="pJmlRupiah"></div>
    <div class="p-provisi val" id="pProvisi"></div>
    <div class="p-biaya val" id="pBiaya"></div>
    <div class="p-total val" id="pTotal"></div>
    <div class="p-terbilang val" id="pTerbilang"></div>
</div>

<script>
    function terbilang(angka) {
        if (angka === 0) return 'Nol';
        const satuan = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan'];
        const belasan = ['Sepuluh', 'Sebelas', 'Dua Belas', 'Tiga Belas', 'Empat Belas', 'Lima Belas', 'Enam Belas', 'Tujuh Belas', 'Delapan Belas', 'Sembilan Belas'];
        const puluhan = ['', '', 'Dua Puluh', 'Tiga Puluh', 'Empat Puluh', 'Lima Puluh', 'Enam Puluh', 'Tujuh Puluh', 'Delapan Puluh', 'Sembilan Puluh'];
        const ribuan = ['', 'Ribu', 'Juta', 'Miliar', 'Triliun'];
        function convert(n) {
            if (n < 10) return satuan[n];
            if (n < 20) return belasan[n - 10];
            if (n < 100) {
                let p = Math.floor(n / 10), s = n % 10;
                return puluhan[p] + (s ? ' ' + satuan[s] : '');
            }
            if (n < 1000) {
                let r = Math.floor(n / 100), s = n % 100;
                let h = (r === 1 ? 'Seratus' : satuan[r] + ' Ratus');
                if (s) h += ' ' + convert(s);
                return h;
            }
            return '';
        }
        let parts = [], num = Math.floor(angka), i = 0;
        while (num > 0) {
            let seg = num % 1000;
            if (seg > 0) {
                let ss = convert(seg);
                if (i === 1 && seg === 1) ss = 'Seribu';
                else if (i === 1 && seg > 1) ss += ' Ribu';
                else if (i > 1) ss += ' ' + ribuan[i];
                parts.push(ss);
            }
            num = Math.floor(num / 1000);
            i++;
        }
        return parts.reverse().join(' ').trim().replace(/\s+/g, ' ') + ' Rupiah';
    }

    function terbilangEnglish(angka) {
        if (angka === 0) return 'Zero USD';
        
        const satuan = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
        const belasan = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        const puluhan = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        const ribuan = ['', 'Thousand', 'Million', 'Billion', 'Trillion'];
        
        function convert(n) {
            if (n < 10) return satuan[n];
            if (n < 20) return belasan[n - 10];
            if (n < 100) {
                let p = Math.floor(n / 10), s = n % 10;
                return puluhan[p] + (s ? ' ' + satuan[s] : '');
            }
            if (n < 1000) {
                let r = Math.floor(n / 100), s = n % 100;
                let h = (r === 1 ? 'One Hundred' : satuan[r] + ' Hundred');
                if (s) h += ' ' + convert(s);
                return h;
            }
            return '';
        }
        
        let parts = [], num = Math.floor(angka), i = 0;
        while (num > 0) {
            let seg = num % 1000;
            if (seg > 0) {
                let ss = convert(seg);
                if (i > 0) ss += ' ' + ribuan[i];
                parts.push(ss);
            }
            num = Math.floor(num / 1000);
            i++;
        }
        
        let result = parts.reverse().join(' ').trim().replace(/\s+/g, ' ');
        return result + ' USD';
    }

    function formatRupiah(n) {
        return new Intl.NumberFormat('id-ID').format(Math.round(n));
    }

   function hitungTotal() {
    let valasRaw = document.getElementById('jmlValas').value;
    let mataUang = document.getElementById('mataUang').value;
    let kursRaw = document.getElementById('kurs').value;
    let biayaRaw = document.getElementById('biaya').value;

    // Ambil nilai manual dari input
    let valas = parseFloat(valasRaw);
    let kurs = parseFloat(kursRaw);
    let biaya = parseFloat(biayaRaw);

    // ===== HITUNG JUMLAH RUPIAH = VALAS x KURS =====
    let jmlRupiah = 0;
    if (!isNaN(valas) && !isNaN(kurs) && valas > 0 && kurs > 0) {
        jmlRupiah = valas * kurs;
    }
    document.getElementById('jmlRupiah').value = jmlRupiah > 0 ? formatRupiah(jmlRupiah) : '';

    // ===== HITUNG TOTAL = JUMLAH RUPIAH + BIAYA =====
    if (isNaN(biaya)) biaya = 0;
    let total = jmlRupiah + biaya;
    document.getElementById('jmlTotal').value = total > 0 ? formatRupiah(total) : '';

    // Jika valas kosong, tampilkan blank untuk terbilang
    if (valasRaw === '' || isNaN(valas)) {
        document.getElementById('terbilangDisplay').textContent = '—';
        document.getElementById('previewTerbilang').textContent = '—';
        document.getElementById('terbilang').value = '';
        return;
    }

    valas = isNaN(valas) ? 0 : valas;

    // ===== TERBILANG BERDASARKAN JUMLAH VALAS =====
    let t = '';
    if (mataUang === 'USD') {
        t = terbilangEnglish(Math.round(valas));
    } else {
        t = terbilang(Math.round(valas));
    }
    
    document.getElementById('terbilangDisplay').textContent = t;
    document.getElementById('terbilang').value = t;
    document.getElementById('previewTerbilang').textContent = t;
}

    function updatePreview() {
        document.getElementById('previewRek').textContent = document.getElementById('rekPenerima').value || '-';
        document.getElementById('previewNamaPenerima').textContent = document.getElementById('namaPenerima').value || '-';
        document.getElementById('previewAlamatPenerima').textContent = document.getElementById('alamatPenerima').value || '-';
        document.getElementById('previewNamaBank').textContent = document.getElementById('namaBank').value || '-';
        document.getElementById('previewAlamatBank').textContent = document.getElementById('alamatBank').value || '-';
        document.getElementById('previewNamaPengirim').textContent = document.getElementById('namaPengirim').value || '-';
        document.getElementById('previewAlamatPengirim').textContent = document.getElementById('alamatPengirim').value || '-';
    }

    function formatTanggal() {
        var dd = document.getElementById('tglHari').value || '';
        var mm = document.getElementById('tglBulan').value || '';
        var yy = document.getElementById('tglTahun').value || '';
        var fullYear = '20' + yy;
        if (dd && mm && yy) {
            document.getElementById('tanggal').value = fullYear + '-' + mm + '-' + dd;
        }
        return [dd, mm, yy].join('  ');
    }

    function saveAndPrint() {
        // Format tanggal
        formatTanggal();
        
        // Kumpulkan semua data dari form
        var formData = new FormData(document.getElementById('formTransfer'));
        
        // Ambil nilai perhitungan
        var jmlRupiahValue = document.getElementById('jmlRupiah').value.replace(/\./g, '');
        var jmlTotalValue = document.getElementById('jmlTotal').value.replace(/\./g, '');
        var terbilangValue = document.getElementById('terbilang').value;
        
        formData.append('jml_rupiah', jmlRupiahValue || '0');
        formData.append('jml_total', jmlTotalValue || '0');
        formData.append('terbilang', terbilangValue || '');
        
        // Kirim ke server untuk simpan
        fetch('proses_simpan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Setelah berhasil simpan, lanjutkan cetak
                
                alert('Data berhasil disimpan! ID: ' + data.id);
                preparePrint();
            } else {
                alert('Gagal menyimpan data: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
    }

    /*
     * preparePrint() HANYA mengisi field teks/angka/tanggal.
     * Field checkbox/radio (jenis pengiriman, tipe nasabah, status,
     * kewarganegaraan, hubungan keuangan, biaya koresponden, sumber dana)
     * SENGAJA TIDAK diisi ke lembar cetak — akan dicentang/ditulis manual
     * oleh user di kertas menggunakan ballpoint.
     */
    function preparePrint() {
        var dd = document.getElementById('tglHari').value || '';
        var mm = document.getElementById('tglBulan').value || '';
        var yy = document.getElementById('tglTahun').value || '';
        document.getElementById('pTgl').textContent = [dd, mm, yy].join('  ');

        document.getElementById('pRekPenerima').textContent = document.getElementById('rekPenerima').value;
        document.getElementById('pNamaPenerima').textContent = document.getElementById('namaPenerima').value;
        document.getElementById('pAlamatPenerima').textContent = document.getElementById('alamatPenerima').value;
        document.getElementById('pKotaPenerima').textContent = document.getElementById('kotaPenerima').value;
        document.getElementById('pKodeNegaraPenerima').textContent = document.getElementById('kodeNegaraPenerima').value;

        document.getElementById('pNamaBank').textContent = document.getElementById('namaBank').value;
        document.getElementById('pAlamatBank').textContent = document.getElementById('alamatBank').value;
        document.getElementById('pKotaBank').textContent = document.getElementById('kotaBank').value;
        document.getElementById('pStateBank').textContent = document.getElementById('stateBank').value;
        document.getElementById('pNegaraBank').textContent = document.getElementById('negaraBank').value;
        document.getElementById('pKodeNegaraBank').textContent = document.getElementById('kodeNegaraBank').value;
        document.getElementById('pSwift').textContent = document.getElementById('swiftCode').value;

        document.getElementById('pNamaPengirim').textContent = document.getElementById('namaPengirim').value;
        document.getElementById('pKtp').textContent = document.getElementById('noKTP').value;
        document.getElementById('pAlamatPengirim').textContent = document.getElementById('alamatPengirim').value;
        document.getElementById('pKontak').textContent = document.getElementById('kontakPerson').value;
        document.getElementById('pHp').textContent = document.getElementById('noHP').value;
        document.getElementById('pKotaPengirim').textContent = document.getElementById('kotaPengirim').value;
        document.getElementById('pRekBca').textContent = document.getElementById('rekBCA').value;

        document.getElementById('pTujuan').textContent = document.getElementById('tujuanTransaksi').value;
        document.getElementById('pBerita').textContent = document.getElementById('berita').value;

        document.getElementById('pOperator').textContent = document.getElementById('operator').value;
        document.getElementById('pVerifier').textContent = document.getElementById('verifier').value;

        document.getElementById('pMataUang').textContent = document.getElementById('mataUang').value;
        document.getElementById('pJmlValas').textContent = document.getElementById('jmlValas').value || '';
        document.getElementById('pKurs').textContent = document.getElementById('kurs').value || '';
        document.getElementById('pJmlRupiah').textContent = document.getElementById('jmlRupiah').value || '';
        document.getElementById('pProvisi').textContent = document.getElementById('provisi').value || '';
        document.getElementById('pBiaya').textContent = document.getElementById('biaya').value || '';
        document.getElementById('pTotal').textContent = document.getElementById('jmlTotal').value || '';
        document.getElementById('pTerbilang').textContent = document.getElementById('terbilangDisplay').textContent;

        window.print();
    }

    function resetForm() {
        if (confirm('Reset semua data?')) location.reload();
    }

        document.querySelectorAll('#formCard input, #formCard select').forEach(function(el) {
            el.addEventListener('input', function() {
                updatePreview();
                if (['jmlValas','mataUang','kurs','biaya'].indexOf(this.id) >= 0) hitungTotal();
            });
            el.addEventListener('change', function() {
                updatePreview();
                if (['jmlValas','mataUang','kurs','biaya'].indexOf(this.id) >= 0) hitungTotal();
            });
        });

    document.querySelectorAll('.text-uppercase').forEach(function(el) {
        el.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
    });

    window.onload = function() {
        var now = new Date();
        document.getElementById('tglHari').value = String(now.getDate()).padStart(2, '0');
        document.getElementById('tglBulan').value = String(now.getMonth() + 1).padStart(2, '0');
        document.getElementById('tglTahun').value = String(now.getFullYear()).slice(-2);
        document.getElementById('tanggal').value = now.getFullYear() + '-' + 
            String(now.getMonth() + 1).padStart(2, '0') + '-' + 
            String(now.getDate()).padStart(2, '0');
        hitungTotal();
        updatePreview();
    };
</script>

</body>
</html>