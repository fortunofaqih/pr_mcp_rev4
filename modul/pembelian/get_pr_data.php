<?php
include '../../config/koneksi.php';
include '../../auth/check_session.php';
header('Content-Type: application/json');

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = mysqli_real_escape_string($koneksi, $_GET['id']);
    
    $q = mysqli_query($koneksi, "SELECT UPPER(nama_pemesan) as nama_pemesan, 
                                        UPPER(nama_pembeli) as nama_pembeli, 
                                        no_request 
                                 FROM tr_request 
                                 WHERE id_request = '$id'");
    
    if ($q && mysqli_num_rows($q) > 0) {
        $d = mysqli_fetch_assoc($q);
        echo json_encode($d);
    } else {
        echo json_encode(['nama_pemesan' => '', 'nama_pembeli' => '', 'no_request' => '']);
    }
} else {
    echo json_encode(['error' => 'ID Kosong']);
}
?>