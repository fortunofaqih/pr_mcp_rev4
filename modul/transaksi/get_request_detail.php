<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if (!isset($_GET['id'])) {
    echo "ID tidak ditemukan";
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);

// 1. Ambil data Header
$query_header = mysqli_query($koneksi, "SELECT * FROM tr_request WHERE id_request = '$id'");
$h = mysqli_fetch_array($query_header);
?>

<style>
    /* CSS Tampilan Layar */
    .table-detail { font-size: 13px; }
    
    /* CSS Khusus Cetak */
    @media print {
        @page { size: landscape; margin: 1cm; }
        .btn-close, .modal-footer, .btn-primary, .btn-secondary { display: none !important; }
        body { background-color: white !important; padding: 0; margin: 0; }
        .modal-content { border: none !important; box-shadow: none !important; }
        .modal-body { padding: 0 !important; }
        .table-responsive { overflow: visible !important; }
        table { width: 100% !important; border-collapse: collapse !important; table-layout: fixed; }
        table th, table td { border: 1px solid #000 !important; padding: 6px !important; word-wrap: break-word; vertical-align: middle; }
        .table-dark { background-color: #eee !important; color: black !important; }
    }
</style>

<div class="modal-header bg-white border-bottom-0">
    <div class="w-100 text-center">
        <h4 class="fw-bold mb-0">FORM PERMINTAAN BARANG (PURCHASE REQUEST)</h4>
        <hr class="my-2" style="border-top: 2px solid #000; opacity: 1;">
        <div class="d-flex justify-content-between small fw-bold px-2">
            <span>NO: <?= $h['no_request'] ?></span>
            <span>PEMESAN: <?= strtoupper($h['nama_pemesan']) ?></span>
            <span>TANGGAL: <?= date('d/m/Y', strtotime($h['tgl_request'])) ?></span>
        </div>
    </div>
</div>

<div class="modal-body">
    <div class="table-responsive">
        <table class="table table-bordered table-detail">
            <thead>
                <tr class="text-center" style="background-color: #f2f2f2 !important;">
                    <th style="width: 4%;">NO</th>
                    <th style="width: 22%;">NAMA BARANG</th>
                    <th style="width: 15%;">KWALIFIKASI</th>
                    <th style="width: 12%;">UNIT/MOBIL</th>
                    <th style="width: 10%;">QTY</th>
                    <th style="width: 12%;">HARGA (EST)</th>
                    <th style="width: 12%;">SUBTOTAL</th>
                    <th style="width: 10%;">KET</th>
                    <th style="width: 3%;">V</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $no = 1;
                $grand_total = 0;
                
                // --- BAGIAN QUERY YANG TADI ERROR ---
                $sql_detail = "SELECT d.*, m.plat_nomor 
                               FROM tr_request_detail d
                               LEFT JOIN master_mobil m ON d.id_mobil = m.id_mobil
                               WHERE d.id_request = '$id' 
                               ORDER BY d.id_detail ASC";
                $query_detail = mysqli_query($koneksi, $sql_detail);
                // -------------------------------------

                while($d = mysqli_fetch_array($query_detail)) {
                    $subtotal = $d['jumlah'] * $d['harga_satuan_estimasi'];
                    $grand_total += $subtotal;
                ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td class="fw-bold"><?= $d['nama_barang_manual'] ?></td>
                    <td><?= $d['kwalifikasi'] ?></td>
                    <td class="text-center"><?= ($d['id_mobil'] != 0) ? $d['plat_nomor'] : '-' ?></td>
                    <td class="text-center"><?= number_format($d['jumlah'], 0) ?> <?= $d['satuan'] ?></td>
                    <td class="text-end">Rp <?= number_format($d['harga_satuan_estimasi'], 0, ',', '.') ?></td>
                    <td class="text-end fw-bold">Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                    <td class="small"><?= $d['keterangan'] ?></td>
                    <td class="text-center align-middle">
                        <div style="width:12px; height:12px; border:1px solid #000; margin:auto;"></div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="6" class="text-end">GRAND TOTAL ESTIMASI</td>
                    <td class="text-end" style="background-color: #f9f9f9 !important;">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="row mt-4 text-center d-none d-print-flex">
        <div class="col-4">
            <p class="mb-5">Dibuat Oleh,</p>
            <p class="mt-5 fw-bold mb-0">( <?= strtoupper($h['nama_pemesan']) ?> )</p>
            <small>Gudang/User</small>
        </div>
        <div class="col-4">
            <p class="mb-5">Diketahui Oleh,</p>
            <p class="mt-5 fw-bold mb-0">( ________________ )</p>
            <small>Kepala Bagian</small>
        </div>
        <div class="col-4">
            <p class="mb-5">Disetujui Oleh,</p>
            <p class="mt-5 fw-bold mb-0">( ________________ )</p>
            <small>Pembelian/Admin</small>
        </div>
    </div>
</div>

<div class="modal-footer border-top-0">
    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
    <button type="button" class="btn btn-primary btn-sm px-3" onclick="window.print()">
        <i class="fas fa-print me-1"></i> Cetak
    </button>
</div>