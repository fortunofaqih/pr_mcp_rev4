<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitasi input
    $id_mesin = mysqli_real_escape_string($koneksi, trim($_POST['id_mesin']));
    $name = mysqli_real_escape_string($koneksi, trim($_POST['name']));
    $spec = mysqli_real_escape_string($koneksi, $_POST['spec'] ?? '');
    $manufactured_date = !empty($_POST['manufactured_date']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['manufactured_date']) . "'" : "NULL";
    $manufactured_by = mysqli_real_escape_string($koneksi, $_POST['manufactured_by'] ?? '');
    $supplier = mysqli_real_escape_string($koneksi, $_POST['supplier'] ?? '');
    $unit = mysqli_real_escape_string($koneksi, $_POST['unit'] ?? '');
    $purchase_price = isset($_POST['purchase_price']) && $_POST['purchase_price'] !== '' ? (float)$_POST['purchase_price'] : 0;
    $purchase_date = !empty($_POST['purchase_date']) ? "'" . mysqli_real_escape_string($koneksi, $_POST['purchase_date']) . "'" : "NULL";
    $acc_reff = mysqli_real_escape_string($koneksi, $_POST['acc_reff'] ?? '');
    $remarks = mysqli_real_escape_string($koneksi, $_POST['remarks'] ?? '');
    $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;
    $capacity = mysqli_real_escape_string($koneksi, $_POST['capacity'] ?? '');

    // Validasi field wajib
    if (empty($id_mesin) || empty($name)) {
        echo json_encode(['status' => 'error', 'message' => 'ID Mesin dan Nama Mesin wajib diisi!']);
        exit;
    }

    // Cek duplikat id_mesin
    $check = mysqli_query($koneksi, "SELECT id FROM master_mesin WHERE id_mesin = '$id_mesin'");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID Mesin sudah digunakan!']);
        exit;
    }

    $query = "INSERT INTO master_mesin (
        id_mesin, name, spec, manufactured_date, manufactured_by, 
        supplier, unit, purchase_price, purchase_date, acc_reff, remarks, active, capacity
    ) VALUES (
        '$id_mesin', '$name', '$spec', $manufactured_date, '$manufactured_by',
        '$supplier', '$unit', $purchase_price, $purchase_date, '$acc_reff', '$remarks', $active, '$capacity'
    )";

    if (mysqli_query($koneksi, $query)) {
        echo json_encode(['status' => 'success', 'message' => 'Data mesin berhasil ditambahkan!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menambahkan data: ' . mysqli_error($koneksi)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
}
?>