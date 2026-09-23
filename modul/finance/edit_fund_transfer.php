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

/**
 * Helper untuk mengambil nilai checkbox/radio yang tersimpan
 * sebagai JSON array atau string.
 */
function selectedValues($value) {
    if (empty($value)) return [];
    $decoded = json_decode($value, true);
    if (is_array($decoded)) return $decoded;
    return [$value];
}

function isSelected($value, $needle) {
    return in_array($needle, selectedValues($value), true) ? 'checked' : '';
}

// Pecah tanggal menjadi dd / mm / yy
$tglHari  = date('d', strtotime($data['tanggal']));
$tglBulan = date('m', strtotime($data['tanggal']));
$tglTahun = date('y', strtotime($data['tanggal']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Fund Transfer BCA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
        .btn-update-custom {
            background: #fd7e14;
            color: white;
            border: none;
            padding: 10px 36px;
            font-weight: 700;
            border-radius: 8px;
        }
        .btn-update-custom:hover { background: #e36a0a; color: white; }
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
        .edit-banner {
            background: #fff4e5;
            border-left: 5px solid #fd7e14;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #8a4b08;
        }
        .edit-banner strong { color: #b1590b; }
    </style>
</head>
<body>

<div class="container-custom">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="text-warning fw-bold mb-0">
            <i class="fas fa-edit me-2"></i>Edit Fund Transfer BCA
        </h3>
        <div>
            <a href="list_data.php" class="btn btn-secondary fw-bold me-2">
                <i class="fas fa-arrow-left me-1"></i> KEMBALI
            </a>
            <button onclick="submitUpdate()" class="btn btn-update-custom">
                <i class="fas fa-save me-2"></i>UPDATE DATA
            </button>
        </div>
    </div>

    <div class="edit-banner">
        <i class="fas fa-info-circle me-2"></i>
        Anda sedang mengedit data <strong>#<?= str_pad($data['id'], 4, '0', STR_PAD_LEFT) ?></strong>
        — <strong><?= htmlspecialchars($data['nama_penerima']) ?></strong>
    </div>

    <form id="formTransfer" method="POST" action="proses_update.php">
        <input type="hidden" name="id" value="<?= (int)$data['id'] ?>">

        <div class="form-card">

            <div class="row mb-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label"><i class="far fa-calendar-alt me-1"></i>Tanggal / Date</label>
                    <div class="date-input-group">
                        <input type="text" id="tglHari" name="tglHari" maxlength="2" placeholder="DD" class="form-control text-center" value="<?= htmlspecialchars($tglHari) ?>">
                        <span>/</span>
                        <input type="text" id="tglBulan" name="tglBulan" maxlength="2" placeholder="MM" class="form-control text-center" value="<?= htmlspecialchars($tglBulan) ?>">
                        <span>/</span>
                        <input type="text" id="tglTahun" name="tglTahun" maxlength="2" placeholder="YY" class="form-control text-center" value="<?= htmlspecialchars($tglTahun) ?>">
                    </div>
                    <input type="hidden" id="tanggal" name="tanggal" value="<?= htmlspecialchars($data['tanggal']) ?>">
                </div>
                <div class="col-md-9">
                    <label class="form-label">Jenis Pengiriman</label>
                    <div class="jenis-pengiriman">
                        <?php
                        $jenisList = ['Kawat', 'Wesel', 'RTGS', 'BI-FAST', 'SKN'];
                        foreach ($jenisList as $j):
                        ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="jp<?= md5($j) ?>" name="jenis_pengiriman[]" value="<?= $j ?>" <?= isSelected($data['jenis_pengiriman'], $j) ?>>
                            <label class="form-check-label" for="jp<?= md5($j) ?>"><?= $j ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-user-check"></i> A. PENERIMA / BENEFICIARY</h4>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Nomor Rekening Penerima</label>
                    <input type="text" id="rekPenerima" name="rekening_penerima" class="form-control" value="<?= htmlspecialchars($data['rekening_penerima']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Penerima</label>
                    <input type="text" id="namaPenerima" name="nama_penerima" class="form-control text-uppercase" value="<?= htmlspecialchars($data['nama_penerima']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alamat Penerima</label>
                    <input type="text" id="alamatPenerima" name="alamat_penerima" class="form-control text-uppercase" value="<?= htmlspecialchars($data['alamat_penerima']) ?>">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Kota</label>
                    <input type="text" id="kotaPenerima" name="kota_penerima" class="form-control text-uppercase" value="<?= htmlspecialchars($data['kota_penerima']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Negara Bagian</label>
                    <input type="text" id="statePenerima" name="state_penerima" class="form-control text-uppercase" value="<?= htmlspecialchars($data['state_penerima'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Negara</label>
                    <input type="text" id="negaraPenerima" name="negara_penerima" class="form-control text-uppercase" value="<?= htmlspecialchars($data['negara_penerima'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Negara</label>
                    <input type="text" id="kodeNegaraPenerima" name="kode_negara_penerima" class="form-control text-uppercase" value="<?= htmlspecialchars($data['kode_negara_penerima']) ?>">
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-4">
                    <label class="form-label">Tipe Nasabah</label>
                    <div class="checkbox-group">
                        <?php foreach (['Perorangan','Perusahaan','Pemerintah'] as $v): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe_nasabah[]" id="tn<?= md5($v) ?>" value="<?= $v ?>" <?= isSelected($data['tipe_nasabah'], $v) ?>>
                            <label class="form-check-label" for="tn<?= md5($v) ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <div class="checkbox-group">
                        <?php foreach (['Penduduk','Non Penduduk'] as $v): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status_nasabah[]" id="st<?= md5($v) ?>" value="<?= $v ?>" <?= isSelected($data['status_nasabah'], $v) ?>>
                            <label class="form-check-label" for="st<?= md5($v) ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kewarganegaraan</label>
                    <div class="checkbox-group">
                        <?php foreach (['WNI','WNA'] as $v): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kewarganegaraan_penerima[]" id="kw<?= md5($v) ?>" value="<?= $v ?>" <?= isSelected($data['kewarganegaraan_penerima'], $v) ?>>
                            <label class="form-check-label" for="kw<?= md5($v) ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-university"></i> B. BANK PENERIMA</h4>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Nama Bank</label>
                    <input type="text" id="namaBank" name="nama_bank" class="form-control text-uppercase" value="<?= htmlspecialchars($data['nama_bank']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alamat Bank</label>
                    <input type="text" id="alamatBank" name="alamat_bank" class="form-control text-uppercase" value="<?= htmlspecialchars($data['alamat_bank']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kota</label>
                    <input type="text" id="kotaBank" name="kota_bank" class="form-control text-uppercase" value="<?= htmlspecialchars($data['kota_bank']) ?>">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Negara Bagian</label>
                    <input type="text" id="stateBank" name="state_bank" class="form-control text-uppercase" value="<?= htmlspecialchars($data['state_bank'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Negara</label>
                    <input type="text" id="negaraBank" name="negara_bank" class="form-control text-uppercase" value="<?= htmlspecialchars($data['negara_bank'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode Negara</label>
                    <input type="text" id="kodeNegaraBank" name="kode_negara_bank" class="form-control text-uppercase" value="<?= htmlspecialchars($data['kode_negara_bank'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kode SWIFT</label>
                    <input type="text" id="swiftCode" name="swift_code" class="form-control text-uppercase" value="<?= htmlspecialchars($data['swift_code'] ?? '') ?>">
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-user-edit"></i> C. PENGIRIM / REMITTER</h4>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label">Nama Pengirim</label>
                    <input type="text" id="namaPengirim" name="nama_pengirim" class="form-control text-uppercase" value="<?= htmlspecialchars($data['nama_pengirim']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Kartu Identitas</label>
                    <input type="text" id="noKTP" name="no_ktp" class="form-control" value="<?= htmlspecialchars($data['no_ktp'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alamat Pengirim</label>
                    <input type="text" id="alamatPengirim" name="alamat_pengirim" class="form-control text-uppercase" value="<?= htmlspecialchars($data['alamat_pengirim']) ?>">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Nama yang dihubungi</label>
                    <input type="text" id="kontakPerson" name="kontak_person" class="form-control text-uppercase" value="<?= htmlspecialchars($data['kontak_person'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Handphone</label>
                    <input type="text" id="noHP" name="no_hp" class="form-control" value="<?= htmlspecialchars($data['no_hp'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" id="noTelp" name="no_telp" class="form-control" value="<?= htmlspecialchars($data['no_telp'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Email</label>
                    <input type="email" id="emailPengirim" name="email_pengirim" class="form-control" value="<?= htmlspecialchars($data['email_pengirim'] ?? '') ?>">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Kota</label>
                    <input type="text" id="kotaPengirim" name="kota_pengirim" class="form-control text-uppercase" value="<?= htmlspecialchars($data['kota_pengirim'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipe Nasabah</label>
                    <div class="checkbox-group">
                        <?php foreach (['Perorangan','Perusahaan','Pemerintah'] as $v): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipe_nasabah_pengirim[]" id="tnp<?= md5($v) ?>" value="<?= $v ?>" <?= isSelected($data['tipe_nasabah_pengirim'], $v) ?>>
                            <label class="form-check-label" for="tnp<?= md5($v) ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <div class="checkbox-group">
                        <?php foreach (['Penduduk','Non Penduduk'] as $v): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status_pengirim[]" id="stp<?= md5($v) ?>" value="<?= $v ?>" <?= isSelected($data['status_pengirim'], $v) ?>>
                            <label class="form-check-label" for="stp<?= md5($v) ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kewarganegaraan</label>
                    <div class="checkbox-group">
                        <?php foreach (['WNI','WNA'] as $v): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kewarganegaraan_pengirim[]" id="kwp<?= md5($v) ?>" value="<?= $v ?>" <?= isSelected($data['kewarganegaraan_pengirim'], $v) ?>>
                            <label class="form-check-label" for="kwp<?= md5($v) ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-4">
                    <label class="form-label">No. Rekening di BCA</label>
                    <input type="text" id="rekBCA" name="rekening_bca" class="form-control" value="<?= htmlspecialchars($data['rekening_bca'] ?? '') ?>">
                </div>
            </div>

            <div class="section-divider"></div>

            <h4><i class="fas fa-database"></i> D. DATA</h4>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label">Hubungan Keuangan</label>
                    <div class="radio-inline mt-1">
                        <?php foreach (['Ya','Tidak'] as $v): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="hubungan_keuangan" id="hk<?= $v ?>" value="<?= $v ?>" <?= ($data['hubungan_keuangan'] === $v) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="hk<?= $v ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tujuan Transaksi</label>
                    <input type="text" id="tujuanTransaksi" name="tujuan_transaksi" class="form-control text-uppercase" value="<?= htmlspecialchars($data['tujuan_transaksi'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Berita / Message</label>
                    <input type="text" id="berita" name="berita" class="form-control text-uppercase" value="<?= htmlspecialchars($data['berita'] ?? '') ?>">
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-12">
                    <label class="form-label">Sumber Dana</label>
                    <div class="mt-1">
                        <div class="sumber-dana-row">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sdTunai" name="sd_tunai" value="1" <?= !empty($data['sd_tunai']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sdTunai">Tunai</label>
                            </div>
                            <span class="text-muted small">Rp</span>
                            <input type="text" id="sdTunaiRp" name="sd_tunai_rp" class="form-control form-control-sm" placeholder="0" value="<?= htmlspecialchars($data['sd_tunai_rp'] ?? '') ?>">
                        </div>
                        <div class="sumber-dana-row">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sdTabungan" name="sd_tabungan" value="1" <?= !empty($data['sd_tabungan']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sdTabungan">Tabungan</label>
                            </div>
                            <span class="text-muted small">No.</span>
                            <input type="text" id="sdTabunganNo" name="sd_tabungan_no" class="form-control form-control-sm" placeholder="No. Rek" value="<?= htmlspecialchars($data['sd_tabungan_no'] ?? '') ?>">
                            <span class="text-muted small">Rp</span>
                            <input type="text" id="sdTabunganRp" name="sd_tabungan_rp" class="form-control form-control-sm" placeholder="0" value="<?= htmlspecialchars($data['sd_tabungan_rp'] ?? '') ?>">
                        </div>
                        <div class="sumber-dana-row">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="sdCek" name="sd_cek" value="1" <?= !empty($data['sd_cek']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="sdCek">Cek BCA</label>
                            </div>
                            <span class="text-muted small">No.</span>
                            <input type="text" id="sdCekNo" name="sd_cek_no" class="form-control form-control-sm" placeholder="No. Cek" value="<?= htmlspecialchars($data['sd_cek_no'] ?? '') ?>">
                            <span class="text-muted small">Rp</span>
                            <input type="text" id="sdCekRp" name="sd_cek_rp" class="form-control form-control-sm" placeholder="0" value="<?= htmlspecialchars($data['sd_cek_rp'] ?? '') ?>">
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
                        <option value="IDR" <?= ($data['mata_uang'] === 'IDR') ? 'selected' : '' ?>>IDR</option>
                        <option value="USD" <?= ($data['mata_uang'] === 'USD') ? 'selected' : '' ?>>USD</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jumlah Valuta Asing</label>
                    <input type="number" step="any" id="jmlValas" name="jml_valas" class="form-control text-end" value="<?= htmlspecialchars($data['jml_valas']) ?>" oninput="hitungTotal()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Kurs</label>
                    <input type="number" step="any" id="kurs" name="kurs" class="form-control text-end" value="<?= htmlspecialchars($data['kurs']) ?>" oninput="hitungTotal()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jumlah Rupiah</label>
                    <input type="text" id="jmlRupiah" name="jml_rupiah" class="form-control text-end fw-bold text-primary" value="<?= number_format((float)$data['jml_rupiah'], 0, ',', '.') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Provisi</label>
                    <input type="number" step="any" id="provisi" name="provisi" class="form-control text-end" value="<?= htmlspecialchars($data['provisi']) ?>">
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Biaya / Charge</label>
                    <input type="number" step="any" id="biaya" name="biaya" class="form-control text-end" value="<?= htmlspecialchars($data['biaya']) ?>" oninput="hitungTotal()">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jumlah / Total</label>
                    <input type="text" id="jmlTotal" name="jml_total" class="form-control text-end fw-bold text-danger" value="<?= number_format((float)$data['jml_total'], 0, ',', '.') ?>">
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-12">
                    <label class="form-label">Terbilang</label>
                    <div class="terbilang-box" id="terbilangDisplay" style="background:#e7f3ff;border-left:4px solid #1a3c6e;padding:8px 14px;border-radius:4px;font-weight:600;color:#1a3c6e;min-height:36px;font-size:13.5px;">
                        <?= htmlspecialchars($data['terbilang'] ?? '—') ?>
                    </div>
                    <input type="hidden" id="terbilang" name="terbilang" value="<?= htmlspecialchars($data['terbilang'] ?? '') ?>">
                </div>
            </div>

            <div class="section-divider"></div>

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label">Biaya bank koresponden dibebankan ke:</label>
                    <div class="radio-inline mt-1">
                        <?php foreach (['Penerima','Pengirim'] as $v): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="biaya_koresponden" id="bk<?= $v ?>" value="<?= $v ?>" <?= ($data['biaya_koresponden'] === $v) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="bk<?= $v ?>"><?= $v ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Today Value</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="todayValue" name="today_value" value="1" <?= !empty($data['today_value']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="todayValue">Today Value</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Instruksi Khusus</label>
                    <input type="text" id="instruksiKhusus" name="instruksi_khusus" class="form-control text-uppercase" value="<?= htmlspecialchars($data['instruksi_khusus'] ?? '') ?>">
                </div>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-3">
                    <label class="form-label">Operator</label>
                    <input type="text" id="operator" name="operator" class="form-control text-uppercase" value="<?= htmlspecialchars($data['operator'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Verifier</label>
                    <input type="text" id="verifier" name="verifier" class="form-control text-uppercase" value="<?= htmlspecialchars($data['verifier'] ?? '') ?>">
                </div>
            </div>

        </div>
    </form>

</div>

<script>
    // ====== Terbilang functions (sama dengan fund_transfer.php) ======
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
        return parts.reverse().join(' ').trim().replace(/\s+/g, ' ') + ' USD';
    }

    function formatRupiah(n) {
        return new Intl.NumberFormat('id-ID').format(Math.round(n));
    }

    function hitungTotal() {
        let valasRaw = document.getElementById('jmlValas').value;
        let mataUang = document.getElementById('mataUang').value;
        let kursRaw = document.getElementById('kurs').value;
        let biayaRaw = document.getElementById('biaya').value;

        let valas = parseFloat(valasRaw);
        let kurs = parseFloat(kursRaw);
        let biaya = parseFloat(biayaRaw);

        let jmlRupiah = 0;
        if (!isNaN(valas) && !isNaN(kurs) && valas > 0 && kurs > 0) {
            jmlRupiah = valas * kurs;
        }
        document.getElementById('jmlRupiah').value = jmlRupiah > 0 ? formatRupiah(jmlRupiah) : '';

        if (isNaN(biaya)) biaya = 0;
        let total = jmlRupiah + biaya;
        document.getElementById('jmlTotal').value = total > 0 ? formatRupiah(total) : '';

        if (valasRaw === '' || isNaN(valas)) {
            document.getElementById('terbilangDisplay').textContent = '—';
            document.getElementById('terbilang').value = '';
            return;
        }

        valas = isNaN(valas) ? 0 : valas;
        let t = (mataUang === 'USD') ? terbilangEnglish(Math.round(valas)) : terbilang(Math.round(valas));
        document.getElementById('terbilangDisplay').textContent = t;
        document.getElementById('terbilang').value = t;
    }

    function formatTanggal() {
        var dd = document.getElementById('tglHari').value || '';
        var mm = document.getElementById('tglBulan').value || '';
        var yy = document.getElementById('tglTahun').value || '';
        var fullYear = '20' + yy;
        if (dd && mm && yy) {
            document.getElementById('tanggal').value = fullYear + '-' + mm + '-' + dd;
        }
    }

    function submitUpdate() {
        formatTanggal();

        var formData = new FormData(document.getElementById('formTransfer'));
        // Bersihkan format ribuan sebelum dikirim
        var jmlRupiahValue = document.getElementById('jmlRupiah').value.replace(/\./g, '').replace(/,/g, '');
        var jmlTotalValue  = document.getElementById('jmlTotal').value.replace(/\./g, '').replace(/,/g, '');
        var terbilangValue = document.getElementById('terbilang').value;
        formData.set('jml_rupiah', jmlRupiahValue || '0');
        formData.set('jml_total',  jmlTotalValue  || '0');
        formData.set('terbilang',  terbilangValue || '');

        var btn = document.querySelector('button[onclick="submitUpdate()"]');
        var originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>MENYIMPAN...';

        fetch('proses_update.php', {
            method: 'POST',
            body: formData
        })
        .then(async function(response) {
            var rawText = await response.text();
            try { return JSON.parse(rawText); }
            catch (e) {
                console.error('Response bukan JSON:', rawText);
                throw new Error('Server tidak mengembalikan JSON. HTTP Status: ' + response.status + '. Cuplikan: ' + rawText.substring(0, 300));
            }
        })
        .then(function(data) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            if (data.success) {
                alert('Data berhasil diupdate!');
                window.location.href = 'list_data.php';
            } else {
                alert('Gagal update data: ' + data.message);
            }
        })
        .catch(function(error) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Error: ' + error.message);
            console.error(error);
        });
    }

    document.querySelectorAll('#formCard input, #formCard select').forEach(function(el) {
        el.addEventListener('input', function() {
            if (['jmlValas','mataUang','kurs','biaya'].indexOf(this.id) >= 0) hitungTotal();
        });
        el.addEventListener('change', function() {
            if (['jmlValas','mataUang','kurs','biaya'].indexOf(this.id) >= 0) hitungTotal();
        });
    });

    document.querySelectorAll('.text-uppercase').forEach(function(el) {
        el.addEventListener('input', function() { this.value = this.value.toUpperCase(); });
    });

    window.onload = function() {
        hitungTotal();
    };
</script>

</body>
</html>