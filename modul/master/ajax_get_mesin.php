<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi ID
    if (!isset($_POST['id_edit']) || empty($_POST['id_edit'])) {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid!']);
        exit;
    }
    
    $id = (int)$_POST['id_edit'];
    
    // Cek apakah data dengan ID tersebut ada
    $cekData = mysqli_query($koneksi, "SELECT id FROM master_mesin WHERE id = $id");
    if (mysqli_num_rows($cekData) == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan!']);
        exit;
    }
    
    // Sanitasi input
    $id_mesin = isset($_POST['id_mesin']) ? mysqli_real_escape_string($koneksi, trim($_POST['id_mesin'])) : '';
    $name = isset($_POST['name']) ? mysqli_real_escape_string($koneksi, trim($_POST['name'])) : '';
    
    if (empty($id_mesin) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'ID Mesin dan Nama Mesin wajib diisi!']);
        exit;
    }
    
    $spec = isset($_POST['spec']) ? mysqli_real_escape_string($koneksi, $_POST['spec']) : '';
    $manufactured_by = isset($_POST['manufactured_by']) ? mysqli_real_escape_string($koneksi, $_POST['manufactured_by']) : '';
    $supplier = isset($_POST['supplier']) ? mysqli_real_escape_string($koneksi, $_POST['supplier']) : '';
    $unit = isset($_POST['unit']) ? mysqli_real_escape_string($koneksi, $_POST['unit']) : '';
    $acc_reff = isset($_POST['acc_reff']) ? mysqli_real_escape_string($koneksi, $_POST['acc_reff']) : '';
    $remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($koneksi, $_POST['remarks']) : '';
    $capacity = isset($_POST['capacity']) ? mysqli_real_escape_string($koneksi, $_POST['capacity']) : '';
    $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    
    // Handle tanggal
    $manufactured_date = (!empty($_POST['manufactured_date'])) 
        ? "'" . mysqli_real_escape_string($koneksi, $_POST['manufactured_date']) . "'" 
        : "NULL";
    
    $purchase_date = (!empty($_POST['purchase_date'])) 
        ? "'" . mysqli_real_escape_string($koneksi, $_POST['purchase_date']) . "'" 
        : "NULL";
    
    $purchase_price = (isset($_POST['purchase_price']) && $_POST['purchase_price'] !== '') 
        ? (float)$_POST['purchase_price'] 
        : 0;

    // Cek duplikat id_mesin (exclude current)
    $check = mysqli_query($koneksi, "SELECT id FROM master_mesin WHERE id_mesin = '$id_mesin' AND id != $id");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID Mesin sudah digunakan oleh data lain!']);
        exit;
    }

    // Query update
    $query = "UPDATE master_mesin SET
        id_mesin = '$id_mesin',
        name = '$name',
        spec = '$spec',
        manufactured_date = $manufactured_date,
        manufactured_by = '$manufactured_by',
        supplier = '$supplier',
        unit = '$unit',
        purchase_price = $purchase_price,
        purchase_date = $purchase_date,
        acc_reff = '$acc_reff',
        remarks = '$remarks',
        active = $active,
        capacity = '$capacity'
    WHERE id = $id";
    
    if (mysqli_query($koneksi, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Data mesin berhasil diupdate!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal update data: ' . mysqli_error($koneksi)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>