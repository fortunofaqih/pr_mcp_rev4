<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

header('Content-Type: application/json');

$search = isset($_GET['search']) ? $_GET['search'] : '';
$like = '%' . $search . '%';

$stmt = mysqli_prepare($koneksi,
    "SELECT plat_nomor, driver_tetap FROM master_mobil
     WHERE plat_nomor LIKE ?
     ORDER BY plat_nomor ASC
     LIMIT 20"
);
mysqli_stmt_bind_param($stmt, "s", $like);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = [
        'id' => $row['plat_nomor'],
        'text' => $row['plat_nomor'] . ' - ' . $row['driver_tetap']
    ];
}

echo json_encode($items);