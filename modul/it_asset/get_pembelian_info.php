<?php
require_once __DIR__ . '/../../config/koneksi.php';

$id_barang = isset($_GET['id_barang']) ? (int)$_GET['id_barang'] : 0;

if ($id_barang > 0) {
    // Ambil data pembelian terbaru berdasarkan id_barang
    // Kita join dengan master_barang jika perlu, atau langsung cari di tabel pembelian
    // Asumsi: nama_barang_beli di tabel pembelian berkaitan dengan nama di master_barang
    
    $sql = "SELECT p.* FROM pembelian p 
            INNER JOIN master_barang mb ON p.nama_barang_beli = mb.nama_barang 
            WHERE mb.id_barang = $id_barang 
            ORDER BY p.tgl_beli DESC LIMIT 1";
            
    $query = mysqli_query($koneksi, $sql);
    $data = mysqli_fetch_assoc($query);

    if ($data) {
        echo json_encode([
            'status' => 'found',
            'sumber' => 'PEMBELIAN',
            'supplier' => $data['supplier'],
            'no_request' => $data['no_request'],
            'tgl_perolehan' => $data['tgl_beli'],
            'harga' => $data['harga'],
            'merk' => $data['merk_beli']
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
}
?>