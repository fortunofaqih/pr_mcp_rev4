<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

if (!isset($_POST['id_mobil']) || empty($_POST['id_mobil'])) {
    echo '<p class="text-center text-danger">ID mobil tidak valid</p>';
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_POST['id_mobil']);
$query = "SELECT * FROM kondisi_kendaraan WHERE id_mobil = '$id' ORDER BY created_at DESC";
$result = mysqli_query($koneksi, $query);

if (mysqli_num_rows($result) > 0) {
    echo '<div class="list-group">';
    while ($row = mysqli_fetch_assoc($result)) {
        $badge = '';
        if ($row['kondisi'] == 'BAIK') {
            $badge = 'bg-success';
        } else if ($row['kondisi'] == 'DISERVICE') {
            $badge = 'bg-warning text-dark';
        } else if ($row['kondisi'] == 'RUSAK RINGAN') {
            $badge = 'bg-warning';
        } else if ($row['kondisi'] == 'RUSAK BERAT') {
            $badge = 'bg-danger';
        }
        
        // Hitung durasi
        $durasi = '';
        if ($row['start_date'] && $row['end_date']) {
            $start = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $diff = $start->diff($end);
            $durasi = ' (' . ($diff->days + 1) . ' hari)';
        }
        
        echo '<div class="list-group-item">';
        echo '<div class="d-flex w-100 justify-content-between">';
        echo '<h6 class="mb-1"><span class="badge ' . $badge . '">' . $row['kondisi'] . '</span> ' . $durasi . '</h6>';
        echo '<small>' . date('d-M-Y H:i', strtotime($row['created_at'])) . '</small>';
        echo '</div>';
        if ($row['keterangan']) {
            echo '<p class="mb-1 small">' . nl2br($row['keterangan']) . '</p>';
        }
        if ($row['start_date'] || $row['end_date']) {
            echo '<small class="text-muted">';
            if ($row['start_date']) echo 'Start: ' . date('d-M-Y', strtotime($row['start_date'])) . ' ';
            if ($row['end_date']) echo 'End: ' . date('d-M-Y', strtotime($row['end_date']));
            echo '</small>';
        }
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<p class="text-center text-muted">Belum ada data kondisi untuk kendaraan ini</p>';
}
?>