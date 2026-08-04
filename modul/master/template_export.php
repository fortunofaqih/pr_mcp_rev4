<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'template') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="template_mesin.csv"');
    
    // Tambahkan BOM untuk UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Header CSV dengan kolom yang jelas
    fputcsv($output, [
        'id_mesin*',
        'name*',
        'spec',
        'manufactured_date (YYYY-MM-DD)',
        'manufactured_by',
        'supplier',
        'purchase_price (angka)',
        'purchase_date (YYYY-MM-DD)',
        'acc_reff',
        'remarks',
        'active (1=AKTIF, 0=NONAKTIF)',
        'capacity'
    ]);
    
    // Contoh data dengan format yang benar
    fputcsv($output, [
        'M-001',
        'Mesin CNC',
        'Kapasitas 500 kg/jam, 3 phase',
        '2023-01-15',
        'Toyoda',
        'PT Teknik Jaya',
        '150000000',
        '2023-02-01',
        'ACC-001',
        'Mesin produksi utama',
        '1',
        '500 kg/jam'
    ]);
    
    fputcsv($output, [
        'M-002',
        'Mesin Injection Molding',
        '150 ton, 220V',
        '2022-06-20',
        'Haitian',
        'PT Plastik Indonesia',
        '250000000',
        '2022-07-01',
        'ACC-002',
        'Mesin molding plastik',
        '1',
        '150 ton'
    ]);
    
    fclose($output);
    exit;
} else {
    echo "Invalid action";
}
?>