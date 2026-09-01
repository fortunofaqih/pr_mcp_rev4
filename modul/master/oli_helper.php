<?php
/* =========================================================
   OLI HELPER
   ========================================================= */

/**
 * Sinkronisasi master barang ke master_oli
 */
function oli_sync_master_barang($koneksi, $user = 'system')
{
    $inserted = 0;
    $updated = 0;

    // HANYA 4 jenis yang dibutuhkan
    $oliList = [
        'OLI SAE 40',
        'OLI SAE 460',
        'OLI HIDROLIS',
        'SOLAR INDUSTRI'
    ];

    foreach ($oliList as $namaOli) {
        // Cek apakah sudah ada
        $stmt = $koneksi->prepare("
            SELECT id_oli, nama_oli 
            FROM master_oli 
            WHERE nama_oli = ?
        ");
        $stmt->bind_param("s", $namaOli);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();

        if (!$existing) {
            // Insert jika belum ada
            $stmt = $koneksi->prepare("
                INSERT INTO master_oli (nama_oli, created_by) 
                VALUES (?, ?)
            ");
            $stmt->bind_param("ss", $namaOli, $user);
            if ($stmt->execute()) {
                $inserted++;
            }
            $stmt->close();
        }
    }

    return [
        'inserted' => $inserted,
        'updated' => $updated
    ];
}

/**
 * Sinkronisasi pembelian dari tabel pembelian ke riwayat_oli
 * SEMUA pembelian yang mengandung keyword akan masuk, tanpa melihat alokasi_stok
 */
function oli_sync_pembelian($koneksi, $user = 'system')
{
    $inserted = 0;
    $skipped = 0;

    // Mapping keyword di nama_barang_beli ke nama oli di master_oli
    $barangOliMap = [
        'OLI SAE 40' => 'OLI SAE 40',
        'OLI SAE 460' => 'OLI SAE 460',
        'OLI HIDROLIS' => 'OLI HIDROLIS',
        'SOLAR INDUSTRI' => 'SOLAR INDUSTRI',
        'SOLAR' => 'SOLAR INDUSTRI', // Tambahan untuk jaga-jaga
    ];

    foreach ($barangOliMap as $keyword => $namaOli) {
        // Ambil id_oli dari master_oli
        $stmtOli = $koneksi->prepare("
            SELECT id_oli FROM master_oli WHERE nama_oli = ?
        ");
        $stmtOli->bind_param("s", $namaOli);
        $stmtOli->execute();
        $resultOli = $stmtOli->get_result();
        $oli = $resultOli->fetch_assoc();
        $stmtOli->close();

        if (!$oli) {
            continue; // Skip jika oli belum ada di master
        }

        $idOli = $oli['id_oli'];

        // Ambil SEMUA pembelian yang belum disync (tanpa filter alokasi_stok)
        $stmt = $koneksi->prepare("
            SELECT
                p.id_pembelian,
                p.tgl_beli AS tanggal,
                p.qty AS jumlah,
                p.keterangan,
                p.created_at,
                p.nama_barang_beli,
                p.alokasi_stok
            FROM pembelian p
            WHERE UPPER(p.nama_barang_beli) LIKE CONCAT('%', UPPER(?), '%')
              AND NOT EXISTS (
                  SELECT 1
                  FROM riwayat_oli r
                  WHERE r.source_type = 'PEMBELIAN'
                    AND r.source_id = p.id_pembelian
                    AND r.id_oli = ?
              )
        ");
        $stmt->bind_param("si", $keyword, $idOli);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Insert ke riwayat_oli
            $stmtInsert = $koneksi->prepare("
                INSERT INTO riwayat_oli
                (
                    id_oli,
                    tanggal,
                    jenis_mutasi,
                    jenis_transaksi,
                    jumlah,
                    source_type,
                    source_id,
                    no_referensi,
                    keterangan,
                    status_transaksi,
                    created_by,
                    created_at
                )
                VALUES
                (
                    ?, ?, 'PEMBELIAN', 'MASUK', ?,
                    'PEMBELIAN', ?, ?, ?,
                    'AKTIF', ?, NOW()
                )
            ");

            // Buat no_referensi dari id_pembelian
            $noReferensi = 'PO-' . $row['id_pembelian'];
            
            // Keterangan lengkap
            $keterangan = 'Pembelian ' . $row['nama_barang_beli'];
            if (!empty($row['alokasi_stok'])) {
                $keterangan .= ' (Alokasi: ' . $row['alokasi_stok'] . ')';
            }
            if (!empty($row['keterangan'])) {
                $keterangan .= ' - ' . $row['keterangan'];
            }
            if (!empty($row['created_at'])) {
                $keterangan .= ' (Tgl PO: ' . date('d/m/Y', strtotime($row['created_at'])) . ')';
            }

            $stmtInsert->bind_param(
                "isdisss",
                $idOli,
                $row['tanggal'],
                $row['jumlah'],
                $row['id_pembelian'],
                $noReferensi,
                $keterangan,
                $user
            );

            if ($stmtInsert->execute()) {
                $inserted++;
            } else {
                $skipped++;
            }
            $stmtInsert->close();
        }
        $stmt->close();
    }

    return [
        'inserted' => $inserted,
        'skipped' => $skipped
    ];
}

/**
 * Cek apakah stok awal sudah ada
 */
function oli_has_stok_awal($koneksi, $idOli, $excludeId = 0)
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM riwayat_oli
        WHERE id_oli = ?
          AND jenis_mutasi = 'STOK_AWAL'
          AND status_transaksi = 'AKTIF'
    ";

    if ($excludeId > 0) {
        $sql .= " AND id_riwayat != ?";
    }

    $stmt = $koneksi->prepare($sql);

    if ($excludeId > 0) {
        $stmt->bind_param("ii", $idOli, $excludeId);
    } else {
        $stmt->bind_param("i", $idOli);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)($row['total'] ?? 0) > 0;
}

/**
 * Get saldo oli
 */
function oli_get_saldo($koneksi, $idOli, $excludeId = 0)
{
    $sql = "
        SELECT COALESCE(
            SUM(
                CASE
                    WHEN status_transaksi = 'AKTIF'
                     AND jenis_transaksi = 'MASUK'
                    THEN jumlah

                    WHEN status_transaksi = 'AKTIF'
                     AND jenis_transaksi = 'KELUAR'
                    THEN -jumlah

                    ELSE 0
                END
            ),
            0
        ) AS saldo
        FROM riwayat_oli
        WHERE id_oli = ?
    ";

    if ($excludeId > 0) {
        $sql .= " AND id_riwayat != ?";
    }

    $stmt = $koneksi->prepare($sql);

    if ($excludeId > 0) {
        $stmt->bind_param("ii", $idOli, $excludeId);
    } else {
        $stmt->bind_param("i", $idOli);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (float)($row['saldo'] ?? 0);
}

/**
 * Get ID Oli dari nama
 */
function oli_get_id_by_name($koneksi, $namaOli)
{
    $stmt = $koneksi->prepare("
        SELECT id_oli FROM master_oli WHERE nama_oli = ?
    ");
    $stmt->bind_param("s", $namaOli);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ? (int)$row['id_oli'] : 0;
}