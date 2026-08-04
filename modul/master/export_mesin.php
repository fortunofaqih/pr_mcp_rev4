<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="data_mesin_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

// Header CSV
fputcsv($output, [
    'ID',
    'ID Mesin',
    'Nama Mesin',
    'Spesifikasi',
    'Tanggal Manufactur',
    'Manufactured By',
    'Supplier',
    'Harga Beli',
    'Tanggal Beli',
    'Acc Reff',
    'Keterangan',
    'Status',
    'Kapasitas'
]);

$query = mysqli_query($koneksi, "SELECT * FROM master_mesin ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($query)) {
    fputcsv($output, [
        $row['id'],
        $row['id_mesin'],
        $row['name'],
        $row['spec'],
        $row['manufactured_date'],
        $row['manufactured_by'],
        $row['supplier'],
        $row['purchase_price'],
        $row['purchase_date'],
        $row['acc_reff'],
        $row['remarks'],
        $row['active'] == 1 ? 'AKTIF' : 'NONAKTIF',
        $row['capacity']
    ]);
}

fclose($output);
exit;
?>