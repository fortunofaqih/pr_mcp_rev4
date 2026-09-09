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
    <title>Detail Fund Transfer BCA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .container-custom { max-width: 1200px; margin: 0 auto; }
        .detail-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .section-title {
            color: #1a3c6e;
            border-bottom: 2px solid #1a3c6e;
            padding-bottom: 8px;
            margin-bottom: 18px;
            font-weight: 700;
            font-size: 16px;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
            font-size: 12px;
        }
        .detail-value {
            font-weight: 500;
            font-size: 14px;
            color: #333;
        }
        .info-row {
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .badge-currency {
            font-size: 14px;
            padding: 5px 12px;
        }
    </style>
</head>
<body>
<div class="container-custom">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="text-primary fw-bold mb-0">
            <i class="fas fa-file-invoice me-2"></i>Detail Fund Transfer
        </h3>
        <div>
            <a href="print_data.php?id=<?= $data['id'] ?>" class="btn btn-success" target="_blank">
                <i class="fas fa-print me-1"></i> CETAK
            </a>
            <a href="list_data.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> KEMBALI
            </a>
        </div>
    </div>

    <div class="detail-card">
        <div class="row">
            <div class="col-md-6">
                <div class="info-row">
                    <span class="detail-label">ID Transaksi</span><br>
                    <span class="detail-value">#<?= str_pad($data['id'], 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="detail-label">Tanggal</span><br>
                    <span class="detail-value"><?= date('d F Y', strtotime($data['tanggal'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="detail-label">Jenis Pengiriman</span><br>
                    <span class="detail-value"><?= htmlspecialchars($data['jenis_pengiriman']) ?></span>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <span class="badge bg-primary badge-currency"><?= htmlspecialchars($data['mata_uang']) ?></span>
                <h2 class="text-danger fw-bold mt-2">Rp <?= number_format($data['jml_total'], 0, ',', '.') ?></h2>
                <p class="text-muted small"><?= htmlspecialchars($data['terbilang']) ?></p>
            </div>
        </div>

        <hr>

        <div class="row mt-3">
            <div class="col-md-4">
                <h5 class="section-title"><i class="fas fa-user-check me-2"></i>A. PENERIMA</h5>
                <p><strong>Rekening:</strong> <?= htmlspecialchars($data['rekening_penerima']) ?></p>
                <p><strong>Nama:</strong> <?= htmlspecialchars($data['nama_penerima']) ?></p>
                <p><strong>Alamat:</strong> <?= htmlspecialchars($data['alamat_penerima']) ?></p>
                <p><strong>Kota:</strong> <?= htmlspecialchars($data['kota_penerima']) ?></p>
                <p><strong>Negara:</strong> <?= htmlspecialchars($data['negara_penerima']) ?> (<?= htmlspecialchars($data['kode_negara_penerima']) ?>)</p>
                <p><strong>Tipe:</strong> <?= htmlspecialchars($data['tipe_nasabah']) ?> | <strong>Status:</strong> <?= htmlspecialchars($data['status_nasabah']) ?> | <strong>KW:</strong> <?= htmlspecialchars($data['kewarganegaraan_penerima']) ?></p>
            </div>
            <div class="col-md-4">
                <h5 class="section-title"><i class="fas fa-university me-2"></i>B. BANK PENERIMA</h5>
                <p><strong>Nama Bank:</strong> <?= htmlspecialchars($data['nama_bank']) ?></p>
                <p><strong>Alamat:</strong> <?= htmlspecialchars($data['alamat_bank']) ?></p>
                <p><strong>Kota:</strong> <?= htmlspecialchars($data['kota_bank']) ?></p>
                <p><strong>Negara:</strong> <?= htmlspecialchars($data['negara_bank']) ?> (<?= htmlspecialchars($data['kode_negara_bank']) ?>)</p>
                <p><strong>SWIFT:</strong> <?= htmlspecialchars($data['swift_code']) ?></p>
            </div>
            <div class="col-md-4">
                <h5 class="section-title"><i class="fas fa-user-edit me-2"></i>C. PENGIRIM</h5>
                <p><strong>Nama:</strong> <?= htmlspecialchars($data['nama_pengirim']) ?></p>
                <p><strong>KTP:</strong> <?= htmlspecialchars($data['no_ktp']) ?></p>
                <p><strong>Alamat:</strong> <?= htmlspecialchars($data['alamat_pengirim']) ?></p>
                <p><strong>Kontak:</strong> <?= htmlspecialchars($data['kontak_person']) ?> | <strong>HP:</strong> <?= htmlspecialchars($data['no_hp']) ?></p>
                <p><strong>Rek. BCA:</strong> <?= htmlspecialchars($data['rekening_bca']) ?></p>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-md-4">
                <h5 class="section-title"><i class="fas fa-database me-2"></i>D. DATA</h5>
                <p><strong>Hubungan Keuangan:</strong> <?= htmlspecialchars($data['hubungan_keuangan']) ?></p>
                <p><strong>Tujuan:</strong> <?= htmlspecialchars($data['tujuan_transaksi']) ?></p>
                <p><strong>Berita:</strong> <?= htmlspecialchars($data['berita']) ?></p>
            </div>
            <div class="col-md-4">
                <h5 class="section-title"><i class="fas fa-money-bill me-2"></i>JUMLAH</h5>
                <p><strong>Mata Uang:</strong> <?= htmlspecialchars($data['mata_uang']) ?></p>
                <p><strong>Jml Valas:</strong> <?= number_format($data['jml_valas'], 2, ',', '.') ?></p>
                <p><strong>Kurs:</strong> <?= number_format($data['kurs'], 2, ',', '.') ?></p>
                <p><strong>Jml Rupiah:</strong> Rp <?= number_format($data['jml_rupiah'], 0, ',', '.') ?></p>
                <p><strong>Provisi:</strong> Rp <?= number_format($data['provisi'], 0, ',', '.') ?></p>
                <p><strong>Biaya:</strong> Rp <?= number_format($data['biaya'], 0, ',', '.') ?></p>
                <p><strong>Total:</strong> <span class="text-danger fw-bold">Rp <?= number_format($data['jml_total'], 0, ',', '.') ?></span></p>
            </div>
            <div class="col-md-4">
                <h5 class="section-title"><i class="fas fa-info-circle me-2"></i>INFORMASI</h5>
                <p><strong>Biaya Koresponden:</strong> <?= htmlspecialchars($data['biaya_koresponden']) ?></p>
                <p><strong>Today Value:</strong> <?= $data['today_value'] ? 'Ya' : 'Tidak' ?></p>
                <p><strong>Instruksi Khusus:</strong> <?= htmlspecialchars($data['instruksi_khusus']) ?></p>
                <p><strong>Operator:</strong> <?= htmlspecialchars($data['operator']) ?></p>
                <p><strong>Verifier:</strong> <?= htmlspecialchars($data['verifier']) ?></p>
                <p><strong>Dibuat:</strong> <?= date('d/m/Y H:i', strtotime($data['created_at'])) ?></p>
                <p><strong>Oleh:</strong> <?= htmlspecialchars($data['created_by']) ?></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>