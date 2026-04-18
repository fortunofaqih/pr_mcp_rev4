<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if (!isset($_REQUEST['id'])) {
    exit("<div class='p-4 text-center text-danger'>ID tidak ditemukan.</div>");
}

$id = mysqli_real_escape_string($koneksi, $_REQUEST['id']);

$query_header = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '$id'");
$h = mysqli_fetch_array($query_header);

if (!$h) {
    echo "<div class='p-4 text-center text-danger'>Data tidak ditemukan.</div>";
    exit;
}

function badge_status_item($status) {
    switch ($status) {
        case 'TERBELI':
            return '<span class="badge bg-success" style="font-size:10px;">TERBELI</span>';
        case 'APPROVED':
            return '<span class="badge bg-primary" style="font-size:10px;">APPROVED</span>';
        case 'MENUNGGU VERIFIKASI':
            return '<span class="badge bg-warning text-dark" style="font-size:10px;">MENUNGGU VERIFIKASI</span>';
        case 'REJECTED':
            return '<span class="badge bg-danger" style="font-size:10px;">REJECTED</span>';
        default:
            return '<span class="badge bg-secondary" style="font-size:10px;">PENDING</span>';
    }
}

$summary = [
    'PENDING'            => 0,
    'APPROVED'           => 0,
    'MENUNGGU VERIFIKASI'  => 0,
    'REJECTED'           => 0,
    'TERBELI'            => 0,
];

$sql_detail = "SELECT d.*, m.plat_nomor, b.nama_barang as nama_barang_master
               FROM tr_request_detail d
               LEFT JOIN master_mobil m ON d.id_mobil = m.id_mobil
               LEFT JOIN master_barang b ON d.id_barang = b.id_barang
               WHERE d.id_request = '$id' 
               ORDER BY d.id_detail ASC";

$query_detail = mysqli_query($koneksi, $sql_detail);

$rows = [];
while ($d = mysqli_fetch_array($query_detail)) {
    $rows[] = $d;
    $status_key = $d['status_item'] ?? 'PENDING';
    if (array_key_exists($status_key, $summary)) {
        $summary[$status_key]++;
    }
}
?>

<div class="p-3 bg-light border-bottom">
    <div class="row small fw-bold text-uppercase">
        <div class="col-md-4">
            <span class="text-muted d-block" style="font-size: 10px;">No. Request:</span>
            <span class="text-primary" style="font-size: 14px;"><?= $h['no_request'] ?></span>
        </div>
        <div class="col-md-4 text-center border-start border-end">
            <span class="text-muted d-block" style="font-size: 10px;">Admin:</span>
            <span><?= strtoupper($h['nama_pemesan']) ?></span>
        </div>
        <div class="col-md-4 text-end">
            <span class="text-muted d-block" style="font-size: 10px;">Tanggal:</span>
            <span><?= date('d/m/Y', strtotime($h['tgl_request'])) ?></span>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-hover mb-0" style="font-size: 0.8rem;">
        <thead class="table-dark text-uppercase" style="font-size: 0.7rem;">
            <tr>
                <th class="text-center" width="40">NO</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th class="text-center">Unit/Mobil</th>
                <th class="text-center">Tipe</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Harga Estimasi</th>
                <th>Keterangan</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $no = 1;
            $total_estimasi = 0;

            if (empty($rows)) {
                echo '<tr><td colspan="9" class="text-center py-3">Tidak ada detail item.</td></tr>';
            }

            foreach ($rows as $d):
                $nama_tampil = !empty($d['nama_barang_master']) ? $d['nama_barang_master'] : $d['nama_barang_manual'];
                $unit_tampil = (!empty($d['plat_nomor'])) ? $d['plat_nomor'] : "-";
                $status_item = $d['status_item'] ?? 'PENDING';
                
                // Hitung Total Keseluruhan
                $total_estimasi += (float)$d['subtotal_estimasi'];

                // Format Harga Per Baris
                $harga_est = ($d['harga_satuan_estimasi'] > 0) ? "Rp " . number_format($d['harga_satuan_estimasi'], 0, ',', '.') : "-";

                $row_class = '';
                switch ($status_item) {
                    case 'TERBELI':             $row_class = 'table-success'; break;
                    case 'APPROVED':            $row_class = 'table-primary'; break;
                    case 'MENUNGGU VERIFIKASI': $row_class = 'table-warning'; break;
                    case 'REJECTED':            $row_class = 'table-danger';  break;
                    default:                    $row_class = '';              break;
                }
            ?>
            <tr class="<?= $row_class ?>">
                <td class="text-center text-muted"><?= $no++ ?></td>
                <td class="fw-bold text-dark"><?= strtoupper($nama_tampil) ?></td>
                <td><small><?= strtoupper($d['kategori_barang']) ?></small></td>
                <td class="text-center">
                    <?php if ($unit_tampil != "-"): ?>
                        <span class="badge bg-light text-dark border"><?= $unit_tampil ?></span>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge <?= $d['tipe_request'] == 'LANGSUNG' ? 'bg-outline-danger text-danger' : 'bg-outline-primary text-primary' ?> border" style="font-size: 10px;">
                        <?= $d['tipe_request'] ?>
                    </span>
                </td>
                <td class="text-center fw-bold">
                    <?= (float)$d['jumlah'] ?> <small class="text-muted"><?= $d['satuan'] ?></small>
                </td>
                <td class="text-end fw-bold text-primary">
                    <?= $harga_est ?>
                </td>
                <td><small><?= $d['keterangan'] ?: '-' ?></small></td>
                <td class="text-center"><?= badge_status_item($status_item) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td colspan="6" class="text-end">TOTAL ESTIMASI KESELURUHAN</td>
                <td class="text-end text-primary">
                    Rp <?= number_format($total_estimasi, 0, ',', '.') ?>
                </td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="p-3 bg-white border-top">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <small class="text-muted fw-bold me-1">Ringkasan Status Item:</small>
        <?php if ($summary['TERBELI'] > 0): ?>
            <span class="badge bg-success"><?= $summary['TERBELI'] ?> Terbeli</span>
        <?php endif; ?>
        <?php if ($summary['APPROVED'] > 0): ?>
            <span class="badge bg-primary"><?= $summary['APPROVED'] ?> Approved</span>
        <?php endif; ?>
        <?php if ($summary['MENUNGGU VERIFIKASI'] > 0): ?>
            <span class="badge bg-warning text-dark"><?= $summary['MENUNGGU VERIFIKASI'] ?> Menunggu Verifikasi</span>
        <?php endif; ?>
        <?php if ($summary['PENDING'] > 0): ?>
            <span class="badge bg-secondary"><?= $summary['PENDING'] ?> Pending</span>
            <button type="button" class="btn btn-sm btn-warning ms-2" onclick="printPendingItems()">Print Pending Items</button>
        <?php endif; ?>
        <?php if ($summary['REJECTED'] > 0): ?>
            <span class="badge bg-danger"><?= $summary['REJECTED'] ?> Rejected</span>
        <?php endif; ?>
    </div>
</div>

<script>
var pendingItems = <?= json_encode(array_values(array_filter(array_map(function($d) {
    if (($d['status_item'] ?? 'PENDING') !== 'PENDING') return null;
    $nama_tampil = !empty($d['nama_barang_master']) ? $d['nama_barang_master'] : $d['nama_barang_manual'];
    
    return [
        'nama'       => $nama_tampil,
        'kategori'   => $d['kategori_barang'] ?: '-',
        'unit'       => !empty($d['plat_nomor']) ? $d['plat_nomor'] : '-',
        'tipe'       => $d['tipe_request'],
        'qty'        => (float)$d['jumlah'] . ' ' . $d['satuan'],
        'harga'      => ($d['harga_satuan_estimasi'] > 0) ? number_format($d['harga_satuan_estimasi'], 0, ',', '.') : '-',
        'subtotal'   => (float)$d['subtotal_estimasi'],
        'keterangan' => $d['keterangan'] ?: '-',
    ];
}, $rows)))) ?>;

var noRequest   = <?= json_encode($h['no_request']) ?>;
var namaPemesan = <?= json_encode(strtoupper($h['nama_pemesan'])) ?>;
var tglRequest  = <?= json_encode(date('d/m/Y', strtotime($h['tgl_request']))) ?>;

function printPendingItems() {
    if (pendingItems.length === 0) {
        alert('Tidak ada item dengan status PENDING.');
        return;
    }

    var totalCetak = 0;
    var printWindow = window.open('', '_blank');
    
    var html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Cetak PR Pending</title>';
    html += '<style>' +
            '@page { size: 21.5cm 16.5cm landscape; margin: 0.5cm 0.7cm; }' +
            'body { font-family: Arial, sans-serif; font-size: 8pt; margin:0; padding:10px; }' +
            '.header { text-align:center; border-bottom:1.5px solid #000; padding-bottom:4px; margin-bottom:10px; }' +
            'table { width:100%; border-collapse:collapse; margin-top:5px; }' +
            'th, td { border:0.5px solid #000; padding:4px; vertical-align:top; }' +
            'th { background:#f0f0f0; }' +
            '.text-center { text-align:center; } .text-right { text-align:right; } .fw-bold { font-weight:bold; }' +
            '</style></head><body>';

    html += '<div class="header"><h2>PURCHASE REQUEST (PENDING ITEMS)</h2><h4>PT. MUTIARA CAHAYA PLASTINDO</h4></div>';
    
    html += '<table style="border:none; width:100%; margin-bottom:10px;">' +
            '<tr style="border:none;"><td style="border:none;" width="15%">No. Request</td><td style="border:none;">: ' + noRequest + '</td>' +
            '<td style="border:none;" width="15%">Admin</td><td style="border:none;">: ' + namaPemesan + '</td></tr>' +
            '<tr style="border:none;"><td style="border:none;">Tanggal</td><td style="border:none;">: ' + tglRequest + '</td>' +
            '<td style="border:none;"></td><td style="border:none;"></td></tr>' +
            '</table>';

    html += '<table><thead><tr>' +
            '<th>NO</th><th>Nama Barang</th><th>Kategori</th><th>Unit</th><th>Tipe</th><th>Qty</th><th>Harga Est.</th><th>Subtotal</th>' +
            '</tr></thead><tbody>';

    pendingItems.forEach(function(item, index) {
        totalCetak += item.subtotal;
        html += '<tr>' +
            '<td class="text-center">' + (index + 1) + '</td>' +
            '<td class="fw-bold">' + item.nama.toUpperCase() + '</td>' +
            '<td>' + item.kategori.toUpperCase() + '</td>' +
            '<td class="text-center">' + item.unit + '</td>' +
            '<td class="text-center">' + item.tipe + '</td>' +
            '<td class="text-center">' + item.qty + '</td>' +
            '<td class="text-right">Rp ' + item.harga + '</td>' +
            '<td class="text-right">Rp ' + item.subtotal.toLocaleString('id-ID') + '</td>' +
            '</tr>';
    });

    html += '</tbody><tfoot><tr class="fw-bold">' +
            '<td colspan="7" class="text-right">TOTAL ESTIMASI PENDING</td>' +
            '<td class="text-right">Rp ' + totalCetak.toLocaleString('id-ID') + '</td>' +
            '</tr></tfoot></table></body></html>';

    printWindow.document.write(html);
    printWindow.document.close();
    setTimeout(function(){ printWindow.print(); }, 500);
}
</script>