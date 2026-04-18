<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
if ($_SESSION['role'] != "superadmin") { die("Akses Ditolak!"); }

$sql = "SELECT * FROM tr_request WHERE status_approval = 'PENDING' AND kategori_pr = 'BESAR'";
$query = mysqli_query($koneksi, $sql);
?>
<table class="table">
    <thead>
        <tr>
            <th>No Request</th>
            <th>Pemesan</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = mysqli_fetch_assoc($query)){ ?>
        <tr>
            <td><?= $row['no_request'] ?></td>
            <td><?= $row['nama_pemesan'] ?></td>
            <td>
                <a href="proses_approve.php?id=<?= $row['id_request'] ?>&status=DISETUJUI" class="btn btn-success">Setuju</a>
                <a href="proses_approve.php?id=<?= $row['id_request'] ?>&status=DITOLAK" class="btn btn-danger">Tolak</a>
            </td>
        </tr>
        <?php } ?>
    </tbody>
</table>