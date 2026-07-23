<?php
/**
 * AJAX: Menampilkan riwayat episode servis untuk satu mobil.
 *
 * Versi ini sengaja pakai mysqli_query() biasa (bukan prepared
 * statement) untuk keperluan tes -- membandingkan apakah error
 * "Error memuat data!" hilang atau tidak dibanding versi bind_result.
 * id_mobil di-cast ke integer supaya tetap aman dari SQL injection
 * walau tanpa prepared statement.
 */
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

if (!isset($_POST['id_mobil']) || empty($_POST['id_mobil'])) {
    echo '<p class="text-center text-danger">ID mobil tidak valid</p>';
    exit;
}

$id = (int) $_POST['id_mobil'];

$query = "SELECT id_kondisi, kondisi, keterangan, start_date, end_date, created_at
          FROM kondisi_kendaraan
          WHERE id_mobil = $id
          ORDER BY (end_date IS NULL) DESC, start_date DESC";
$result = mysqli_query($koneksi, $query);

if (!$result) {
    echo '<p class="text-center text-danger">Query error: ' . htmlspecialchars(mysqli_error($koneksi)) . '</p>';
    exit;
}

if (mysqli_num_rows($result) > 0) {
    echo '<div class="list-group">';
    while ($row = mysqli_fetch_assoc($result)) {
        $aktif = is_null($row['end_date']);

        $badge = [
            'DISERVICE'    => 'bg-warning text-dark',
            'RUSAK RINGAN' => 'bg-warning',
            'RUSAK BERAT'  => 'bg-danger',
        ][$row['kondisi']] ?? 'bg-secondary';

        $durasi_teks = '';
        if ($row['start_date']) {
            $start = new DateTime($row['start_date']);
            $sampai = $aktif ? new DateTime() : new DateTime($row['end_date']);
            $durasi = $start->diff($sampai)->days + 1;
            $durasi_teks = ' (' . $durasi . ' hari' . ($aktif ? ', masih berjalan' : '') . ')';
        }

        echo '<div class="list-group-item ' . ($aktif ? 'border-start border-warning border-3' : '') . '">';
        echo '<div class="d-flex w-100 justify-content-between">';
        echo '<h6 class="mb-1"><span class="badge ' . $badge . '">' . htmlspecialchars($row['kondisi']) . '</span>' . $durasi_teks;
        echo $aktif ? ' <span class="badge bg-warning text-dark">AKTIF</span>' : ' <span class="badge bg-success">SELESAI</span>';
        echo '</h6>';
        echo '<small>' . date('d-M-Y', strtotime($row['created_at'])) . '</small>';
        echo '</div>';

        if ($row['keterangan']) {
            echo '<p class="mb-1 small">' . nl2br(htmlspecialchars($row['keterangan'])) . '</p>';
        }

        echo '<small class="text-muted">';
        echo 'Mulai: ' . ($row['start_date'] ? date('d-M-Y', strtotime($row['start_date'])) : '-');
        echo ' &nbsp;|&nbsp; Selesai: ' . ($row['end_date'] ? date('d-M-Y', strtotime($row['end_date'])) : '- (belum ditutup)');
        echo '</small>';
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<p class="text-center text-muted">Belum ada riwayat servis untuk kendaraan ini. Status: <span class="badge bg-success">BAIK</span></p>';
}