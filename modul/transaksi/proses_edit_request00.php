<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_POST) {
    $id_request   = mysqli_real_escape_string($koneksi, $_POST['id_request']);
    $tgl_request  = mysqli_real_escape_string($koneksi, $_POST['tgl_request']);
    $nama_pemesan = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_pemesan']));
    $nama_pembeli = strtoupper(mysqli_real_escape_string($koneksi, $_POST['nama_pembeli']));
    $user_login   = $_SESSION['username'];
    $now          = date('Y-m-d H:i:s');

    // ════════════════════════════════════════════════════════════════
    // Ambil array dari POST
    // Semua field (termasuk baris locked) sudah terkirim sebagai
    // hidden input biasa — tidak ada lagi 'disabled' select.
    // Index array dijamin sinkron karena setiap baris mengirimkan
    // semua field dalam urutan yang sama.
    // ════════════════════════════════════════════════════════════════
    $id_detail_array       = $_POST['id_detail']          ?? [];
    $id_barang_array       = $_POST['id_barang']          ?? [];
    $nama_barang_array     = $_POST['nama_barang_manual'] ?? [];
    $kategori_array        = $_POST['kategori_request']   ?? [];
    $kwalifikasi_array     = $_POST['kwalifikasi']        ?? [];
    $id_mobil_array        = $_POST['id_mobil']           ?? [];
    $tipe_array            = $_POST['tipe_request']       ?? [];
    $jumlah_array          = $_POST['jumlah']             ?? [];
    $satuan_array          = $_POST['satuan']             ?? [];
    $harga_array           = $_POST['harga']              ?? [];
    $keterangan_array      = $_POST['keterangan']         ?? [];

    mysqli_begin_transaction($koneksi);
    try {
        // ── 1. UPDATE HEADER ─────────────────────────────────────
        $query_h = "UPDATE tr_request SET 
                        tgl_request  = '$tgl_request', 
                        nama_pemesan = '$nama_pemesan',
                        nama_pembeli = '$nama_pembeli',
                        updated_by   = '$user_login',
                        updated_at   = '$now' 
                    WHERE id_request = '$id_request'";
        if (!mysqli_query($koneksi, $query_h)) throw new Exception(mysqli_error($koneksi));

        // ── 2. Pisahkan id_detail yang valid (existing) vs baru ──
        // id_detail[] kosong/0 = baris baru (INSERT)
        // id_detail[] berisi angka = baris existing (UPDATE atau skip jika locked)
        $id_detail_dikirim = array_filter(
            $id_detail_array,
            fn($v) => !empty($v) && intval($v) > 0
        );

        // ── 3. Hapus baris yang DIHAPUS user ────────────────────
        // Hanya hapus baris yang:
        //   a. Tidak ada di daftar id_detail yang dikirim form
        //   b. Statusnya masih PENDING / APPROVED / REJECTED
        // Baris TERBELI dan MENUNGGU VERIFIKASI TIDAK akan terhapus
        // karena terlindungi oleh kondisi status_item IN (...)
        if (!empty($id_detail_dikirim)) {
            $ids_aman  = implode(',', array_map('intval', $id_detail_dikirim));
            $query_del = "DELETE FROM tr_request_detail 
                          WHERE id_request = '$id_request' 
                          AND   id_detail  NOT IN ($ids_aman)
                          AND   status_item IN ('PENDING', 'APPROVED', 'REJECTED')";
        } else {
            // Tidak ada baris existing yang dikirim → hapus semua PENDING/APPROVED/REJECTED
            $query_del = "DELETE FROM tr_request_detail 
                          WHERE id_request = '$id_request'
                          AND   status_item IN ('PENDING', 'APPROVED', 'REJECTED')";
        }
        if (!mysqli_query($koneksi, $query_del)) throw new Exception(mysqli_error($koneksi));

        // ── 4. LOOP: UPDATE existing atau INSERT baru ───────────
        foreach ($id_barang_array as $key => $val) {
            // Skip jika id_barang kosong
            if (empty($val)) continue;

            $id_detail = intval($id_detail_array[$key]   ?? 0);
            $id_brg    = intval($val);
            $nama_m    = strtoupper(mysqli_real_escape_string($koneksi, $nama_barang_array[$key]   ?? ''));
            $kat       = strtoupper(mysqli_real_escape_string($koneksi, $kategori_array[$key]      ?? ''));
            $kwal      = strtoupper(mysqli_real_escape_string($koneksi, $kwalifikasi_array[$key]   ?? ''));
            $mobil     = intval($id_mobil_array[$key]    ?? 0);
            $tipe      = strtoupper(mysqli_real_escape_string($koneksi, $tipe_array[$key]          ?? 'STOK'));
            $qty       = floatval($jumlah_array[$key]    ?? 0);
            $sat       = strtoupper(mysqli_real_escape_string($koneksi, $satuan_array[$key]        ?? ''));
            $hrg       = floatval($harga_array[$key]     ?? 0);
            $ket       = strtoupper(mysqli_real_escape_string($koneksi, $keterangan_array[$key]    ?? ''));
            $subtotal  = $qty * $hrg;

            if ($id_detail > 0) {
                // ── UPDATE baris existing ────────────────────────
                // WHERE status_item = 'PENDING' memastikan baris locked
                // (TERBELI / MENUNGGU VERIFIKASI) tidak akan diubah
                // meskipun datanya ikut terkirim via hidden input.
                $query_d = "UPDATE tr_request_detail SET
                                nama_barang_manual    = '$nama_m',
                                id_barang             = '$id_brg',
                                id_mobil              = '$mobil',
                                jumlah                = '$qty',
                                satuan                = '$sat',
                                harga_satuan_estimasi = '$hrg',
                                subtotal_estimasi     = '$subtotal',
                                kategori_barang       = '$kat',
                                kwalifikasi           = '$kwal',
                                tipe_request          = '$tipe',
                                keterangan            = '$ket'
                            WHERE id_detail   = '$id_detail'
                            AND   id_request  = '$id_request'
                            AND   status_item = 'PENDING'";
                // Catatan: query ini akan affected 0 rows untuk baris locked → aman
            } else {
                // ── INSERT baris baru ────────────────────────────
                $query_d = "INSERT INTO tr_request_detail 
                                (id_request, nama_barang_manual, id_barang, id_mobil, jumlah, satuan, 
                                 harga_satuan_estimasi, subtotal_estimasi, kategori_barang, kwalifikasi, 
                                 tipe_request, keterangan, status_item) 
                            VALUES 
                                ('$id_request', '$nama_m', '$id_brg', '$mobil', '$qty', '$sat', 
                                 '$hrg', '$subtotal', '$kat', '$kwal', '$tipe', '$ket', 'PENDING')";
            }
            if (!mysqli_query($koneksi, $query_d)) throw new Exception(mysqli_error($koneksi));
        }

        mysqli_commit($koneksi);
        header("location:pr.php?pesan=update_sukses");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        echo "<div style='font-family:sans-serif; color:red; padding:20px;'>";
        echo "<strong>Gagal menyimpan perubahan:</strong><br>";
        echo htmlspecialchars($e->getMessage());
        echo "<br><br><a href='javascript:history.back()'>« Kembali</a>";
        echo "</div>";
    }
}
?>