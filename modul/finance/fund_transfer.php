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
        .note-small { font-size: 11px; color: #666; font-style: italic; }

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

            /* Tanggal: kiri 1cm, bawah 0.5cm; jarak DD MM YY = 0.5cm */
            .p-tgl {
                position: absolute;
                top: 10mm;
                left: 19mm;
                font-size: 8pt;
                letter-spacing: 2mm;
                word-spacing: 5mm;
            }
            /* Jenis */
            .p-jenis {
                position: absolute;
                top: 15mm;
                left: 95mm;
                font-size: 7.5pt;
                font-weight: 600;
            }

            /* ===== A (kiri) +1cm kanan ===== */
            .p-rek-penerima { position: absolute; top: 25mm; left: 26mm; width: 85mm; font-size: 8pt; }
            .p-nama-penerima { position: absolute; top: 30mm; left: 26mm; width: 85mm; font-size: 8pt; }
            .p-alamat-penerima { position: absolute; top: 35mm; left: 26mm; width: 85mm; font-size: 8pt; }
            .p-kota-penerima { position: absolute; top: 39mm; left: 26mm; width: 85mm; font-size: 8pt; }
            .p-kode-negara-penerima { position: absolute; top: 61mm; left: 26mm; width: 40mm; font-size: 8pt; }
            .p-tipe-a { position: absolute; top: 54mm; left: 26mm; font-size: 7pt; font-weight: 600; }
            .p-status-a { position: absolute; top: 54mm; left: 65mm; font-size: 7pt; font-weight: 600; }
            .p-kw-a { position: absolute; top: 57.5mm; left: 16mm; font-size: 7pt; font-weight: 600; }

            /* ===== B (kanan) +1cm kanan ===== */
            .p-nama-bank { position: absolute; top: 25mm; left: 131mm; width: 85mm; font-size: 8pt; }
            .p-alamat-bank { position: absolute; top: 30mm; left: 131mm; width: 85mm; font-size: 8pt; }
            .p-kota-bank { position: absolute; top: 38mm; left: 131mm; width: 85mm; font-size: 8pt; }
            .p-state-bank { position: absolute; top: 40mm; left: 131mm; width: 85mm; font-size: 8pt; }
            .p-negara-bank { position: absolute; top: 42mm; left: 131mm; width: 50mm; font-size: 8pt; }
            .p-kode-negara-bank { position: absolute; top: 44mm; left: 131mm; width: 40mm; font-size: 8pt; }
            .p-swift { position: absolute; top: 42mm; left: 120mm; width: 85mm; font-size: 8pt; }

            /* ===== C (kiri bawah) +1cm kanan ===== */
            .p-nama-pengirim { position: absolute; top: 82mm; left: 26mm; width: 85mm; font-size: 8pt; }
            .p-ktp { 
                position: absolute; 
                top: 84mm; 
                left: 26mm; 
                width: 50mm; 
                font-size: 8pt; 
            }
            .p-alamat-pengirim { position: absolute; top: 86mm; left: 26mm; width: 85mm; font-size: 8pt; }
            .p-kontak { position: absolute; top: 89mm; left: 26mm; width: 50mm; font-size: 8pt; }
            .p-hp { position: absolute; top: 90mm; left: 26mm; width: 50mm; font-size: 8pt; }
            .p-kota-pengirim { position: absolute; top: 108mm; left: 26mm; width: 50mm; font-size: 8pt; }
            .p-rek-bca { position: absolute; top: 122mm; left: 26mm; width: 60mm; font-size: 8pt; }
            .p-tipe-c { position: absolute; top: 114mm; left: 26mm; font-size: 7pt; font-weight: 600; }
            .p-status-c { position: absolute; top: 114mm; left: 65mm; font-size: 7pt; font-weight: 600; }
           .p-kw-c { position: absolute; top: 122.5mm; left: 16mm; font-size: 7pt; font-weight: 600; }

            /* ===== D (kanan bawah) +1cm kanan ===== */
            .p-hub-keuangan { position: absolute; top: 85mm; left: 120mm; font-size: 8pt; }
            .p-tujuan { position: absolute; top: 92mm; left: 120mm; width: 85mm; font-size: 8pt; }
            .p-berita { position: absolute; top: 99mm; left: 120mm; width: 85mm; font-size: 8pt; }
            .p-sumber-dana { position: absolute; top: 110mm; left: 120mm; width: 85mm; font-size: 7.5pt; }

            /* Biaya kor + Operator (+1cm untuk konsistensi kiri) */
            .p-biaya-kor { position: absolute; top: 140mm; left: 16mm; font-size: 8pt; }
            .p-operator { position: absolute; top: 140mm; left: 140mm; width: 28mm; font-size: 8pt; }
            .p-verifier { position: absolute; top: 140mm; left: 172mm; width: 25mm; font-size: 8pt; }

            /* Jumlah */
            /* Mata uang +0.8cm kanan; valas -2cm kiri; kurs -2.5cm kiri; rupiah/total -4.5cm kiri */
            .p-mata-uang { position: absolute; top: 156mm; left: 21mm; width: 20mm; font-size: 8pt; }
            .p-jml-valas { position: absolute; top: 156mm; left: 8mm; width: 40mm; text-align: right; font-size: 8pt; }
            .p-kurs { position: absolute; top: 156mm; left: 47mm; width: 28mm; text-align: right; font-size: 8pt; }
            .p-jml-rupiah { position: absolute; top: 156mm; left: 60mm; width: 45mm; text-align: right; font-size: 8pt; }
            .p-provisi { position: absolute; top: 170mm; left: 8mm; width: 40mm; text-align: right; font-size: 8pt; }
            .p-biaya { position: absolute; top: 171mm; left: 8mm; width: 40mm; text-align: right; font-size: 8pt; }
            .p-total { position: absolute; top: 172mm; left: 60mm; width: 45mm; text-align: right; font-size: 8pt; }
            /* Terbilang naik 0.5cm */
            .p-terbilang { position: absolute; top: 179mm; left: 12mm; width: 170mm; font-size: 8pt; white-space: normal; }
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
            <button onclick="preparePrint()" class="btn btn-success-custom me-2">
                <i class="fas fa-print me-2"></i>CETAK BLANKO
            </button>
            <button onclick="resetForm()" class="btn btn-secondary">
                <i class="fas fa-undo me-2"></i>RESET
            </button>
        </div>
    </div>

    <div class="form-card no-print" id="formCard">

        <div class="row mb-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><i class="far fa-calendar-alt me-1"></i>Tanggal / Date</label>
                <div class="date-input-group">
                    <input type="text" id="tglHari" maxlength="2" placeholder="DD" class="form-control text-center">
                    <span>/</span>
                    <input type="text" id="tglBulan" maxlength="2" placeholder="MM" class="form-control text-center">
                    <span>/</span>
                    <input type="text" id="tglTahun" maxlength="2" placeholder="YY" class="form-control text-center">
                </div>
            </div>
            <div class="col-md-9">
                <label class="form-label">Jenis Pengiriman</label>
                <div class="jenis-pengiriman">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="jpKawat" value="Kawat">
                        <label class="form-check-label" for="jpKawat">Kawat</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="jpWesel" value="Wesel">
                        <label class="form-check-label" for="jpWesel">Wesel</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="jpRTGS" value="RTGS">
                        <label class="form-check-label" for="jpRTGS">RTGS</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="jpBIFAST" value="BI-FAST">
                        <label class="form-check-label" for="jpBIFAST">BI-FAST</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="jpSKN" value="SKN">
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
                <input type="text" id="rekPenerima" class="form-control" value="">
            </div>
            <div class="col-md-4">
                <label class="form-label">Nama Penerima</label>
                <input type="text" id="namaPenerima" class="form-control text-uppercase" value="">
            </div>
            <div class="col-md-4">
                <label class="form-label">Alamat Penerima</label>
                <input type="text" id="alamatPenerima" class="form-control text-uppercase" value="">
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-3">
                <label class="form-label">Kota</label>
                <input type="text" id="kotaPenerima" class="form-control text-uppercase" value="">
            </div>
            <div class="col-md-3">
                <label class="form-label">Negara Bagian</label>
                <input type="text" id="statePenerima" class="form-control text-uppercase">
            </div>
            <div class="col-md-3">
                <label class="form-label">Negara</label>
                <input type="text" id="negaraPenerima" class="form-control text-uppercase">
            </div>
            <div class="col-md-3">
                <label class="form-label">Kode Negara</label>
                <input type="text" id="kodeNegaraPenerima" class="form-control text-uppercase" value="">
            </div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-4">
                <label class="form-label">Tipe Nasabah</label>
                <div class="checkbox-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tipeNasabah" id="tnPerorangan" value="Perorangan">
                        <label class="form-check-label" for="tnPerorangan">Perorangan</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tipeNasabah" id="tnPerusahaan" value="Perusahaan">
                        <label class="form-check-label" for="tnPerusahaan">Perusahaan</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tipeNasabah" id="tnPemerintah" value="Pemerintah">
                        <label class="form-check-label" for="tnPemerintah">Pemerintah</label>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <div class="checkbox-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="statusNasabah" id="stPenduduk" value="Penduduk">
                        <label class="form-check-label" for="stPenduduk">Penduduk</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="statusNasabah" id="stNonPenduduk" value="Non Penduduk">
                        <label class="form-check-label" for="stNonPenduduk">Non Penduduk</label>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Kewarganegaraan</label>
                <div class="checkbox-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="kewarganegaraan" id="kwWNI" value="WNI">
                        <label class="form-check-label" for="kwWNI">WNI</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="kewarganegaraan" id="kwWNA" value="WNA">
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
                <input type="text" id="namaBank" class="form-control text-uppercase" value="">
            </div>
            <div class="col-md-4">
                <label class="form-label">Alamat Bank</label>
                <input type="text" id="alamatBank" class="form-control text-uppercase" value="">
            </div>
            <div class="col-md-4">
                <label class="form-label">Kota</label>
                <input type="text" id="kotaBank" class="form-control text-uppercase" value="">
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-3">
                <label class="form-label">Negara Bagian</label>
                <input type="text" id="stateBank" class="form-control text-uppercase">
            </div>
            <div class="col-md-3">
                <label class="form-label">Negara</label>
                <input type="text" id="negaraBank" class="form-control text-uppercase">
            </div>
            <div class="col-md-3">
                <label class="form-label">Kode Negara</label>
                <input type="text" id="kodeNegaraBank" class="form-control text-uppercase">
            </div>
            <div class="col-md-3">
                <label class="form-label">Kode SWIFT</label>
                <input type="text" id="swiftCode" class="form-control text-uppercase">
            </div>
        </div>

        <div class="section-divider"></div>

        <h4><i class="fas fa-user-edit"></i> C. PENGIRIM / REMITTER</h4>
        <div class="row g-2">
            <div class="col-md-5">
                <label class="form-label">Nama Pengirim</label>
                <input type="text" id="namaPengirim" class="form-control text-uppercase" value="PT. MUTIARACAHAYA PLASTINDO">
            </div>
            <div class="col-md-3">
                <label class="form-label">No. Kartu Identitas</label>
                <input type="text" id="noKTP" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Alamat Pengirim</label>
                <input type="text" id="alamatPengirim" class="form-control text-uppercase" value="MASTRIP 33 SURABAYA">
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-3">
                <label class="form-label">Nama yang dihubungi</label>
                <input type="text" id="kontakPerson" class="form-control text-uppercase" value="SUSAN">
            </div>
            <div class="col-md-3">
                <label class="form-label">No. Handphone</label>
                <input type="text" id="noHP" class="form-control" value="0816528099">
            </div>
            <div class="col-md-3">
                <label class="form-label">No. Telepon</label>
                <input type="text" id="noTelp" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" id="emailPengirim" class="form-control">
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-3">
                <label class="form-label">Kota</label>
                <input type="text" id="kotaPengirim" class="form-control text-uppercase" value="">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipe Nasabah</label>
                <div class="checkbox-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tipeNasabahPengirim" id="tnpPerorangan" value="Perorangan">
                        <label class="form-check-label" for="tnpPerorangan">Perorangan</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tipeNasabahPengirim" id="tnpPerusahaan" value="Perusahaan">
                        <label class="form-check-label" for="tnpPerusahaan">Perusahaan</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tipeNasabahPengirim" id="tnpPemerintah" value="Pemerintah">
                        <label class="form-check-label" for="tnpPemerintah">Pemerintah</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <div class="checkbox-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="statusPengirim" id="stpPenduduk" value="Penduduk">
                        <label class="form-check-label" for="stpPenduduk">Penduduk</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="statusPengirim" id="stpNonPenduduk" value="Non Penduduk">
                        <label class="form-check-label" for="stpNonPenduduk">Non Penduduk</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kewarganegaraan</label>
                <div class="checkbox-group">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="kwPengirim" id="kwpWNI" value="WNI">
                        <label class="form-check-label" for="kwpWNI">WNI</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="kwPengirim" id="kwpWNA" value="WNA">
                        <label class="form-check-label" for="kwpWNA">WNA</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-4">
                <label class="form-label">No. Rekening di BCA</label>
                <input type="text" id="rekBCA" class="form-control" value="">
            </div>
        </div>

        <div class="section-divider"></div>

        <h4><i class="fas fa-database"></i> D. DATA</h4>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Hubungan Keuangan</label>
                <div class="radio-inline mt-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="hubKeuangan" id="hkYa" value="Ya">
                        <label class="form-check-label" for="hkYa">Ya</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="hubKeuangan" id="hkTidak" value="Tidak">
                        <label class="form-check-label" for="hkTidak">Tidak</label>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tujuan Transaksi</label>
                <input type="text" id="tujuanTransaksi" class="form-control text-uppercase" value="">
            </div>
            <div class="col-md-4">
                <label class="form-label">Berita / Message</label>
                <input type="text" id="berita" class="form-control text-uppercase" value="">
            </div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-12">
                <label class="form-label">Sumber Dana</label>
                <div class="mt-1">
                    <div class="sumber-dana-row">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sdTunai" value="Tunai">
                            <label class="form-check-label" for="sdTunai">Tunai</label>
                        </div>
                        <span class="text-muted small">Rp</span>
                        <input type="text" id="sdTunaiRp" class="form-control form-control-sm" placeholder="0">
                    </div>
                    <div class="sumber-dana-row">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sdTabungan" value="Tabungan">
                            <label class="form-check-label" for="sdTabungan">Tabungan</label>
                        </div>
                        <span class="text-muted small">No.</span>
                        <input type="text" id="sdTabunganNo" class="form-control form-control-sm" placeholder="No. Rek">
                        <span class="text-muted small">Rp</span>
                        <input type="text" id="sdTabunganRp" class="form-control form-control-sm" placeholder="0">
                    </div>
                    <div class="sumber-dana-row">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sdCek" value="Cek BCA">
                            <label class="form-check-label" for="sdCek">Cek BCA</label>
                        </div>
                        <span class="text-muted small">No.</span>
                        <input type="text" id="sdCekNo" class="form-control form-control-sm" placeholder="No. Cek">
                        <span class="text-muted small">Rp</span>
                        <input type="text" id="sdCekRp" class="form-control form-control-sm" placeholder="0">
                    </div>
                </div>
            </div>
        </div>

        <div class="section-divider"></div>

        <h4><i class="fas fa-calculator"></i> JUMLAH YANG DIKIRIM</h4>
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label">Mata Uang</label>
                <select id="mataUang" class="form-select" onchange="hitungTotal()">
                    <option value="IDR" selected>IDR</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="SGD">SGD</option>
                    <option value="JPY">JPY</option>
                    <option value="AUD">AUD</option>
                    <option value="CNY">CNY</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Jumlah Valuta Asing</label>
                <input type="number" id="jmlValas" class="form-control text-end" value="" placeholder="0" oninput="hitungTotal()">
            </div>
            <div class="col-md-2">
                <label class="form-label">Kurs</label>
                <input type="number" id="kurs" class="form-control text-end" value="" placeholder="0" oninput="hitungTotal()">
            </div>
            <div class="col-md-3">
                <label class="form-label">Jumlah Rupiah</label>
                <input type="text" id="jmlRupiah" class="form-control text-end fw-bold text-primary" readonly value="">
            </div>
            <div class="col-md-2">
                <label class="form-label">Provisi</label>
                <input type="number" id="provisi" class="form-control text-end" value="" placeholder="0" oninput="hitungTotal()">
            </div>
        </div>
        <div class="row g-2 mt-1">
            <div class="col-md-3">
                <label class="form-label">Biaya / Charge</label>
                <input type="number" id="biaya" class="form-control text-end" value="" placeholder="0" oninput="hitungTotal()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Jumlah / Total</label>
                <input type="text" id="jmlTotal" class="form-control text-end fw-bold text-danger" readonly value="">
            </div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-12">
                <label class="form-label">Terbilang</label>
                <div class="terbilang-box" id="terbilangDisplay">—</div>
            </div>
        </div>

        <div class="section-divider"></div>

        <div class="row g-2">
            <div class="col-md-6">
                <label class="form-label">Biaya bank koresponden dibebankan ke:</label>
                <div class="radio-inline mt-1">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="biayaKoresponden" id="bkBeneficiary" value="Penerima">
                        <label class="form-check-label" for="bkBeneficiary">Penerima</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="biayaKoresponden" id="bkRemitter" value="Pengirim">
                        <label class="form-check-label" for="bkRemitter">Pengirim</label>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Today Value</label>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="todayValue" value="Today Value">
                    <label class="form-check-label" for="todayValue">Today Value</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Instruksi Khusus</label>
                <input type="text" id="instruksiKhusus" class="form-control text-uppercase">
            </div>
        </div>
        <div class="row g-2 mt-2">
            <div class="col-md-3">
                <label class="form-label">Operator</label>
                <input type="text" id="operator" class="form-control text-uppercase">
            </div>
            <div class="col-md-3">
                <label class="form-label">Verifier</label>
                <input type="text" id="verifier" class="form-control text-uppercase">
            </div>
        </div>
    </div>

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
                    <strong>TOTAL:</strong> <span id="previewTotal" class="text-danger fw-bold">Rp —</span><br>
                    <strong>Terbilang:</strong> <span id="previewTerbilang">—</span>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- LEMBAR CETAK: HANYA ISIAN (seperti teks merah di contoh) -->
<div class="print-sheet" id="printSheet">
    <div class="p-tgl val" id="pTgl"></div>
    <div class="p-jenis val" id="pJenis"></div>

    <div class="p-rek-penerima val" id="pRekPenerima"></div>
    <div class="p-nama-penerima val" id="pNamaPenerima"></div>
    <div class="p-alamat-penerima val" id="pAlamatPenerima"></div>
    <div class="p-kota-penerima val" id="pKotaPenerima"></div>
    <div class="p-kode-negara-penerima val" id="pKodeNegaraPenerima"></div>
    <div class="p-tipe-a val" id="pTipeA"></div>
    <div class="p-status-a val" id="pStatusA"></div>
    <div class="p-kw-a val" id="pKwA"></div>

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
    <div class="p-tipe-c val" id="pTipeC"></div>
    <div class="p-status-c val" id="pStatusC"></div>
    <div class="p-kw-c val" id="pKwC"></div>

    <div class="p-hub-keuangan val" id="pHubKeuangan"></div>
    <div class="p-tujuan val" id="pTujuan"></div>
    <div class="p-berita val" id="pBerita"></div>
    <div class="p-sumber-dana val" id="pSumberDana"></div>

    <div class="p-biaya-kor val" id="pBiayaKor"></div>
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

    function formatRupiah(n) {
        return new Intl.NumberFormat('id-ID').format(Math.round(n));
    }

    function hitungTotal() {
        let valasRaw = document.getElementById('jmlValas').value;
        let kursRaw = document.getElementById('kurs').value;
        let provisiRaw = document.getElementById('provisi').value;
        let biayaRaw = document.getElementById('biaya').value;

        let valas = parseFloat(valasRaw);
        let kurs = parseFloat(kursRaw);
        let provisi = parseFloat(provisiRaw) || 0;
        let biaya = parseFloat(biayaRaw) || 0;

        // Jika semua kosong, tampilkan blank
        if ((valasRaw === '' || isNaN(valas)) && (kursRaw === '' || isNaN(kurs)) && !provisiRaw && !biayaRaw) {
            document.getElementById('jmlRupiah').value = '';
            document.getElementById('jmlTotal').value = '';
            document.getElementById('terbilangDisplay').textContent = '—';
            document.getElementById('previewTotal').textContent = 'Rp —';
            document.getElementById('previewTerbilang').textContent = '—';
            return;
        }

        valas = isNaN(valas) ? 0 : valas;
        kurs = isNaN(kurs) ? 0 : kurs;

        let jmlRupiah = valas * kurs;
        if (document.getElementById('mataUang').value === 'IDR') {
            jmlRupiah = valas;
            // set kurs 1 hanya jika kosong, jangan paksa overwrite input user
            if (kursRaw === '' || isNaN(parseFloat(kursRaw))) {
                document.getElementById('kurs').value = 1;
                kurs = 1;
            }
        }

        let total = jmlRupiah + provisi + biaya;
        document.getElementById('jmlRupiah').value = formatRupiah(jmlRupiah);
        document.getElementById('jmlTotal').value = formatRupiah(total);

        let t = terbilang(Math.round(total));
        document.getElementById('terbilangDisplay').textContent = t;
        document.getElementById('previewTotal').textContent = 'Rp ' + formatRupiah(total);
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

    function getChecked(name) {
        return Array.from(document.querySelectorAll('input[name="' + name + '"]:checked')).map(e => e.value).join(', ');
    }

    function preparePrint() {
        var dd = document.getElementById('tglHari').value || '';
        var mm = document.getElementById('tglBulan').value || '';
        var yy = document.getElementById('tglTahun').value || '';
        document.getElementById('pTgl').textContent = [dd, mm, yy].join('  ');

        var jenis = [];
        ['jpKawat','jpWesel','jpRTGS','jpBIFAST','jpSKN'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el && el.checked) jenis.push(el.value);
        });
        document.getElementById('pJenis').textContent = jenis.join('  |  ');

        document.getElementById('pRekPenerima').textContent = document.getElementById('rekPenerima').value;
        document.getElementById('pNamaPenerima').textContent = document.getElementById('namaPenerima').value;
        document.getElementById('pAlamatPenerima').textContent = document.getElementById('alamatPenerima').value;
        document.getElementById('pKotaPenerima').textContent = document.getElementById('kotaPenerima').value;
        document.getElementById('pKodeNegaraPenerima').textContent = document.getElementById('kodeNegaraPenerima').value;
        document.getElementById('pTipeA').textContent = getChecked('tipeNasabah');
        document.getElementById('pStatusA').textContent = getChecked('statusNasabah');
        document.getElementById('pKwA').textContent = getChecked('kewarganegaraan');

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
        document.getElementById('pTipeC').textContent = getChecked('tipeNasabahPengirim');
        document.getElementById('pStatusC').textContent = getChecked('statusPengirim');
        document.getElementById('pKwC').textContent = getChecked('kwPengirim');

        var hub = document.querySelector('input[name="hubKeuangan"]:checked');
        document.getElementById('pHubKeuangan').textContent = hub ? hub.value : '';
        document.getElementById('pTujuan').textContent = document.getElementById('tujuanTransaksi').value;
        document.getElementById('pBerita').textContent = document.getElementById('berita').value;

        var sumber = [];
        if (document.getElementById('sdTunai').checked)
            sumber.push('Tunai Rp ' + (document.getElementById('sdTunaiRp').value || '0'));
        if (document.getElementById('sdTabungan').checked)
            sumber.push('Tabungan No.' + (document.getElementById('sdTabunganNo').value || '') + ' Rp ' + (document.getElementById('sdTabunganRp').value || '0'));
        if (document.getElementById('sdCek').checked)
            sumber.push('Cek No.' + (document.getElementById('sdCekNo').value || '') + ' Rp ' + (document.getElementById('sdCekRp').value || '0'));
        document.getElementById('pSumberDana').textContent = sumber.join(' | ');

        var bk = document.querySelector('input[name="biayaKoresponden"]:checked');
        document.getElementById('pBiayaKor').textContent = bk ? bk.value : '';
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
            if (['jmlValas','kurs','provisi','biaya','mataUang'].indexOf(this.id) >= 0) hitungTotal();
        });
        el.addEventListener('change', function() {
            updatePreview();
            if (['jmlValas','kurs','provisi','biaya','mataUang'].indexOf(this.id) >= 0) hitungTotal();
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
        hitungTotal();
        updatePreview();
    };
</script>

</body>
</html>
