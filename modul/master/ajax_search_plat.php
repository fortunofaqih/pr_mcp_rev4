<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';

$query = "SELECT plat_nomor, driver_tetap FROM master_mobil 
          WHERE plat_nomor LIKE '%$search%' 
          ORDER BY plat_nomor ASC 
          LIMIT 20";
$result = mysqli_query($koneksi, $query);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = [
        'id' => $row['plat_nomor'],
        'text' => $row['plat_nomor'] . ' - ' . $row['driver_tetap']
    ];
}

echo json_encode($items);
?>