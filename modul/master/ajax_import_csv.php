<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_csv'])) {
    $file = $_FILES['file_csv'];
    $skip_first = isset($_POST['skip_first_row']) && $_POST['skip_first_row'] == '1';
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal upload file']);
        exit;
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($file_ext) !== 'csv') {
        echo json_encode(['status' => 'error', 'message' => 'Hanya file CSV yang diperbolehkan']);
        exit;
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membaca file']);
        exit;
    }
    
    $success = 0;
    $failed = 0;
    $errors = [];
    $row_num = 0;
    
    // Cek BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }
    
    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row_num++;
        
        if ($skip_first && $row_num === 1) {
            if (strpos(strtolower($data[0] ?? ''), 'id_mesin') !== false) {
                continue;
            }
        }
        
        if (count($data) < 2) {
            $failed++;
            $errors[] = "Row $row_num: Data tidak valid";
            continue;
        }
        
        $id_mesin = trim($data[0] ?? '');
        $name = trim($data[1] ?? '');
        $spec = trim($data[2] ?? '');
        $manufactured_date = trim($data[3] ?? '');
        $manufactured_by = trim($data[4] ?? '');
        $supplier = trim($data[5] ?? '');
        $purchase_price = trim($data[6] ?? '0');
        $purchase_date = trim($data[7] ?? '');
        $acc_reff = trim($data[8] ?? '');
        $remarks = trim($data[9] ?? '');
        $active = trim($data[10] ?? '1');
        $capacity = trim($data[11] ?? '');
        $unit = trim($data[12] ?? '');
        
        if (empty($id_mesin) || empty($name)) {
            $failed++;
            $errors[] = "Row $row_num: ID Mesin dan Nama Mesin wajib diisi";
            continue;
        }
        
        // Cek duplikat
        $check = mysqli_query($koneksi, "SELECT id FROM master_mesin WHERE id_mesin = '$id_mesin'");
        if (mysqli_num_rows($check) > 0) {
            $failed++;
            $errors[] = "Row $row_num: ID Mesin '$id_mesin' sudah ada";
            continue;
        }
        
        // Format data
        $manufactured_date_formatted = !empty($manufactured_date) ? "'" . mysqli_real_escape_string($koneksi, $manufactured_date) . "'" : "NULL";
        $purchase_date_formatted = !empty($purchase_date) ? "'" . mysqli_real_escape_string($koneksi, $purchase_date) . "'" : "NULL";
        $active_value = (int)$active;
        $purchase_price_clean = (float)$purchase_price;
        
        // Escape
        $spec_esc = mysqli_real_escape_string($koneksi, $spec);
        $manufactured_by_esc = mysqli_real_escape_string($koneksi, $manufactured_by);
        $supplier_esc = mysqli_real_escape_string($koneksi, $supplier);
        $unit_esc = mysqli_real_escape_string($koneksi, $unit);
        $acc_reff_esc = mysqli_real_escape_string($koneksi, $acc_reff);
        $remarks_esc = mysqli_real_escape_string($koneksi, $remarks);
        $capacity_esc = mysqli_real_escape_string($koneksi, $capacity);
        
        $query = "INSERT INTO master_mesin (
            id_mesin, name, spec, manufactured_date, manufactured_by,
            supplier, unit, purchase_price, purchase_date, acc_reff, remarks, active, capacity
        ) VALUES (
            '$id_mesin', '$name', '$spec_esc', $manufactured_date_formatted, '$manufactured_by_esc',
            '$supplier_esc', '$unit_esc', $purchase_price_clean, $purchase_date_formatted, 
            '$acc_reff_esc', '$remarks_esc', $active_value, '$capacity_esc'
        )";
        
        if (mysqli_query($koneksi, $query)) {
            $success++;
        } else {
            $failed++;
            $errors[] = "Row $row_num: " . mysqli_error($koneksi);
        }
    }
    
    fclose($handle);
    
    $message = "Import selesai! Berhasil: $success, Gagal: $failed";
    if (!empty($errors)) {
        $message .= "\n\nDetail error:\n" . implode("\n", array_slice($errors, 0, 10));
    }
    
    echo json_encode([
        'status' => $success > 0 ? 'success' : 'error',
        'message' => $message,
        'success' => $success,
        'failed' => $failed
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>