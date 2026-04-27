<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $tgl_form      = $_POST['tgl_request'];
    $tgl_kode      = date('Ymd', strtotime($tgl_form));
    $user_login    = $_SESSION['username']; 
    $nama_pemesan  = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_pemesan']));
    $nama_pembeli  = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_pembeli'] ?? ''));

    // 1. LOCK TABLES
    mysqli_query($koneksi, "LOCK TABLES tr_request WRITE, tr_request_detail WRITE");

    // 2. Generate Nomor Request
    $query_no = mysqli_query($koneksi, "SELECT MAX(no_request) as max_code FROM tr_request WHERE no_request LIKE 'PR-$tgl_kode%'");
    $data_no  = mysqli_fetch_array($query_no);
    $last_no  = $data_no['max_code'] ?? '';
    $sort_no  = (int) substr($last_no, -3);
    $new_no   = "PR-" . $tgl_kode . "-" . str_pad(($sort_no + 1), 3, "0", STR_PAD_LEFT);

    // 3. Simpan Header
    $query_header = "INSERT INTO tr_request (no_request, tgl_request, nama_pemesan, nama_pembeli, status_request, created_by) 
                     VALUES ('$new_no', '$tgl_form', '$nama_pemesan', '$nama_pembeli', 'PENDING', '$user_login')";

    if (mysqli_query($koneksi, $query_header)) {
        $id_header = mysqli_insert_id($koneksi);

        // 4. Tangkap Data Detail
        $id_barang_array   = $_POST['id_barang'];
        $nama_barang_array = $_POST['nama_barang_manual']; 
        $kategori_request  = $_POST['kategori_request']; // Sekarang menangkap dari input readonly
        $kwalifikasi       = $_POST['kwalifikasi'];
        $id_mobil          = $_POST['id_mobil'];
        $tipe_request      = $_POST['tipe_request']; 
        $jumlah            = $_POST['jumlah'];
        $satuan            = $_POST['satuan']; // Sekarang menangkap dari input readonly
        $harga_array       = $_POST['harga'];
        $keterangan        = $_POST['keterangan']; 

        // 5. Looping Detail
        foreach ($id_barang_array as $key => $val) {
            if(empty($val)) continue; 

            $id_brg = (int)$val;
            $nama   = strtoupper(mysqli_real_escape_string($koneksi, $nama_barang_array[$key] ?? ''));
            $kat    = strtoupper(mysqli_real_escape_string($koneksi, $kategori_request[$key] ?? ''));
            $kwal   = strtoupper(mysqli_real_escape_string($koneksi, $kwalifikasi[$key] ?? ''));
            $mobil  = (int)($id_mobil[$key] ?? 0);
            $tipe   = strtoupper(mysqli_real_escape_string($koneksi, $tipe_request[$key] ?? 'STOK'));
            $qty    = (float)($jumlah[$key] ?? 0);
            $sat    = strtoupper(mysqli_real_escape_string($koneksi, $satuan[$key] ?? ''));
            $hrg    = (float)($harga_array[$key] ?? 0);
            $ket    = strtoupper(mysqli_real_escape_string($koneksi, $keterangan[$key] ?? ''));
            $subtotal = $qty * $hrg;

            $query_detail = "INSERT INTO tr_request_detail 
                            (id_request, nama_barang_manual, id_barang, id_mobil, jumlah, satuan, harga_satuan_estimasi, subtotal_estimasi, kategori_barang, tipe_request, kwalifikasi, keterangan) 
                            VALUES 
                            ('$id_header', '$nama', '$id_brg', '$mobil', '$qty', '$sat', '$hrg', '$subtotal', '$kat', '$tipe', '$kwal', '$ket')";
            
            mysqli_query($koneksi, $query_detail);
        }

        // UNLOCK SETELAH LOOP SELESAI
        mysqli_query($koneksi, "UNLOCK TABLES");

        // 6. Redirect Berhasil
        if (isset($_SESSION['role']) && ($_SESSION['role'] == 'bagian_pembelian' || $_SESSION['role'] == 'pembelian')) {
            header("location:../pembelian/index.php?pesan=berhasil&no=$new_no");
        } else {
            header("location:pr.php?pesan=berhasil&no=$new_no");
        }
        exit;

    } else {
        // UNLOCK JIKA GAGAL
        mysqli_query($koneksi, "UNLOCK TABLES");
        header("location:pr.php?pesan=gagal");
        exit;
    }
}
?>