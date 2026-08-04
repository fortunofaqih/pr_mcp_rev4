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
    
    // Validasi file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal upload file']);
        exit;
    }
    
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($file_ext) !== 'csv') {
        echo json_encode(['status' => 'error', 'message' => 'Hanya file CSV yang diperbolehkan']);
        exit;
    }
    
    // Baca file CSV
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['status' => 'error', 'message' => 'Gagal membaca file']);
        exit;
    }
    
    $success = 0;
    $failed = 0;
    $errors = [];
    $row_num = 0;
    $header_checked = false;
    
    // Cek apakah ada BOM
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        // Kembalikan pointer ke awal jika bukan BOM
        rewind($handle);
    }
    
    while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        $row_num++;
        
        // Skip header jika dipilih
        if ($skip_first && $row_num === 1) {
            // Cek apakah ini header
            if (strpos(strtolower($data[0] ?? ''), 'id_mesin') !== false || 
                strpos(strtolower($data[0] ?? ''), 'id mesin') !== false) {
                // Ini header, skip
                continue;
            }
        }
        
        // Validasi minimal kolom (bisa kurang dari 12 karena ada kolom opsional)
        if (count($data) < 2) {
            $failed++;
            $errors[] = "Row $row_num: Data tidak valid (minimal 2 kolom)";
            continue;
        }
        
        // Mapping kolom dengan nilai default
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
        
        // Validasi required fields
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
        
        // === FORMATTING DATA ===
        
        // Format tanggal: support berbagai format
        $manufactured_date_formatted = formatDateForMySQL($manufactured_date);
        $purchase_date_formatted = formatDateForMySQL($purchase_date);
        
        // Format active: support berbagai nilai
        $active_value = formatActiveValue($active);
        
        // Format purchase_price: bersihkan dan konversi ke float
        $purchase_price_clean = cleanCurrency($purchase_price);
        
        // Escape string
        $spec_esc = mysqli_real_escape_string($koneksi, $spec);
        $manufactured_by_esc = mysqli_real_escape_string($koneksi, $manufactured_by);
        $supplier_esc = mysqli_real_escape_string($koneksi, $supplier);
        $acc_reff_esc = mysqli_real_escape_string($koneksi, $acc_reff);
        $remarks_esc = mysqli_real_escape_string($koneksi, $remarks);
        $capacity_esc = mysqli_real_escape_string($koneksi, $capacity);
        
        $query = "INSERT INTO master_mesin (
            id_mesin, name, spec, manufactured_date, manufactured_by,
            supplier, purchase_price, purchase_date, acc_reff, remarks, active, capacity
        ) VALUES (
            '$id_mesin', '$name', '$spec_esc', $manufactured_date_formatted, '$manufactured_by_esc',
            '$supplier_esc', $purchase_price_clean, $purchase_date_formatted, '$acc_reff_esc', '$remarks_esc', $active_value, '$capacity_esc'
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
        if (count($errors) > 10) {
            $message .= "\n... dan " . (count($errors) - 10) . " error lainnya";
        }
    }
    
    echo json_encode([
        'status' => $success > 0 ? 'success' : 'error',
        'message' => $message,
        'success' => $success,
        'failed' => $failed,
        'errors' => $errors
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}

// === HELPER FUNCTIONS ===

function formatDateForMySQL($date) {
    if (empty($date) || trim($date) === '') {
        return 'NULL';
    }
    
    $date = trim($date);
    
    // Format: YYYY-MM-DD (sudah benar)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return "'$date'";
    }
    
    // Format: DD-MMM-YY (26-Apr-17)
    if (preg_match('/^\d{1,2}-[A-Za-z]{3}-\d{2,4}$/', $date)) {
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return "'" . date('Y-m-d', $timestamp) . "'";
        }
    }
    
    // Format: DD-MM-YYYY
    if (preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $date)) {
        $parts = explode('-', $date);
        $timestamp = strtotime($parts[2] . '-' . $parts[1] . '-' . $parts[0]);
        if ($timestamp !== false) {
            return "'" . date('Y-m-d', $timestamp) . "'";
        }
    }
    
    // Format: DD/MM/YYYY
    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date)) {
        $parts = explode('/', $date);
        $timestamp = strtotime($parts[2] . '-' . $parts[1] . '-' . $parts[0]);
        if ($timestamp !== false) {
            return "'" . date('Y-m-d', $timestamp) . "'";
        }
    }
    
    // Coba dengan strtotime
    $timestamp = strtotime($date);
    if ($timestamp !== false) {
        return "'" . date('Y-m-d', $timestamp) . "'";
    }
    
    // Jika semua gagal, return NULL
    return 'NULL';
}

function formatActiveValue($value) {
    $value = strtolower(trim($value));
    
    // Nilai yang dianggap 1 (AKTIF)
    if ($value === '1' || $value === 'true' || $value === 'aktif' || 
        $value === 'active' || $value === 'checked' || $value === 'yes' || 
        $value === 'y' || $value === 'iya' || $value === 'ya') {
        return 1;
    }
    
    // Nilai yang dianggap 0 (NONAKTIF)
    if ($value === '0' || $value === 'false' || $value === 'nonaktif' || 
        $value === 'inactive' || $value === 'no' || $value === 'n' || 
        $value === 'tidak') {
        return 0;
    }
    
    // Default: 1 (AKTIF)
    return 1;
}

function cleanCurrency($value) {
    if (empty($value) || trim($value) === '') {
        return 0;
    }
    
    // Hapus simbol mata uang dan spasi
    $value = preg_replace('/[Rp,.\s]/', '', $value);
    $value = str_replace(' ', '', $value);
    $value = trim($value);
    
    // Konversi ke float
    return (float)$value;
}
?>