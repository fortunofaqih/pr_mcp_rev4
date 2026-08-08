<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_mesin.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header
fputcsv($output, [
    'id_mesin*', 'name*', 'spec', 'manufactured_date', 'manufactured_by', 
    'supplier', 'purchase_price', 'purchase_date', 'acc_reff', 'remarks', 
    'active (1=Active,0=Inactive)', 'capacity', 'unit'
]);

// Contoh data
fputcsv($output, [
    'M-001', 'Mesin CNC', 'Kapasitas 500kg/jam, 220V',
    '2023-01-15', 'PT. Mesin Jaya', 'PT. Supplier Maju',
    '150000000', '2023-02-01', 'ACC-001', 'Mesin produksi utama',
    '1', '500 kg/jam', 'Unit Produksi'
]);

fputcsv($output, [
    'M-002', 'Mesin Injection Molding', 'Tekanan 2000 ton',
    '2023-03-10', 'PT. Plastik Indo', 'PT. Supplier Sejahtera',
    '250000000', '2023-04-01', 'ACC-002', 'Untuk produksi plastik',
    '1', '1000 unit/jam', 'Unit Plastik'
]);

fclose($output);
exit;
?>