<?php
// oli_helper.php

if (!function_exists('oli_normalize_name')) {
    function oli_normalize_name(string $nama, ?string $merk = null, ?int $idBarang = null): ?string
    {
        /*
         * MASTER OLI DIPERKETAT BERDASARKAN NAMA BARANG + MERK.
         *
         * Prinsip:
         * - nama barang harus EXACT MATCH setelah normalisasi spasi/case.
         * - untuk OLI HIDROLIS, merk juga harus EXACT MATCH:
         *      OLI HIDROLIS (PAK TARJI)
         *
         * Dengan demikian barang seperti:
         *   SELANG FLEXIBEL OLI HIDROLIS KABIN (LANGSUNG PAKAI)
         * tidak akan pernah dianggap master oli.
         */
        $namaNorm = strtoupper(trim($nama));
        $namaNorm = preg_replace('/\s+/', ' ', $namaNorm);

        $merkNorm = strtoupper(trim((string)$merk));
        $merkNorm = preg_replace('/\s+/', ' ', $merkNorm);

        if ($namaNorm === 'OLI SAE 40') {
            return 'OLI SAE 40';
        }

        if ($namaNorm === 'OLI SAE 460') {
            return 'OLI SAE 460';
        }

        if (
            ($idBarang === null || $idBarang === 3169)
            && $namaNorm === 'OLI HIDROLIS'
            && $merkNorm === 'OLI HIDROLIS (PAK TARJI)'
        ) {
            return 'OLI HIDROLIS';
        }

        return null;
    }
}

if (!function_exists('oli_get_or_create_master')) {
    function oli_get_or_create_master(
        mysqli $db,
        string $namaOli,
        string $user = 'system'
    ): int {
        $stmt = $db->prepare("
            SELECT id_oli
            FROM master_oli
            WHERE UPPER(TRIM(nama_oli)) = UPPER(TRIM(?))
            LIMIT 1
        ");
        $stmt->bind_param("s", $namaOli);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
            return (int)$row['id_oli'];
        }

        $stmt = $db->prepare("
            INSERT INTO master_oli
            (
                nama_oli,
                stok_awal,
                stok_saat_ini,
                created_by
            )
            VALUES
            (?, 0, 0, ?)
        ");
        $stmt->bind_param("ss", $namaOli, $user);
        $stmt->execute();

        $id = (int)$stmt->insert_id;
        $stmt->close();

        return $id;
    }
}

if (!function_exists('oli_sync_master_barang')) {
    function oli_sync_master_barang(
        mysqli $db,
        string $user = 'system'
    ): array {
        $created = 0;
        $linked  = 0;

        $sql = "
            SELECT
                id_barang,
                nama_barang,
                merk
            FROM master_barang
            WHERE is_active = 1
              AND (
                    UPPER(TRIM(nama_barang)) IN ('OLI SAE 40', 'OLI SAE 460')
                    OR (
                        id_barang = 3169
                        AND UPPER(TRIM(nama_barang)) = 'OLI HIDROLIS'
                        AND UPPER(TRIM(merk)) = 'OLI HIDROLIS (PAK TARJI)'
                    )
                  )
            ORDER BY id_barang
        ";

        $result = $db->query($sql);

        if (!$result) {
            return [
                'created' => 0,
                'linked'  => 0,
                'error'   => $db->error
            ];
        }

        while ($row = $result->fetch_assoc()) {

            $namaOli = oli_normalize_name(
                $row['nama_barang'],
                $row['merk'] ?? null,
                (int)$row['id_barang']
            );

            if ($namaOli === null) {
                continue;
            }

            $stmt = $db->prepare("
                SELECT id_oli
                FROM master_oli
                WHERE UPPER(TRIM(nama_oli)) = UPPER(TRIM(?))
                LIMIT 1
            ");
            $stmt->bind_param("s", $namaOli);
            $stmt->execute();

            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $idOli = (int)$existing['id_oli'];
            } else {
                $idOli = oli_get_or_create_master(
                    $db,
                    $namaOli,
                    $user
                );
                $created++;
            }

            $idBarang = (int)$row['id_barang'];

            $stmt = $db->prepare("
                INSERT IGNORE INTO master_oli_barang
                (id_oli, id_barang)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ii", $idOli, $idBarang);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $linked++;
            }

            $stmt->close();
        }

        return [
            'created' => $created,
            'linked'  => $linked,
            'error'   => null
        ];
    }
}

if (!function_exists('oli_sync_pembelian')) {
    function oli_sync_pembelian(
        mysqli $db,
        string $user = 'system'
    ): array {
        oli_sync_master_barang($db, $user);

        $inserted = 0;
        $skipped  = 0;

        $sql = "
            SELECT
                p.id_pembelian,
                COALESCE(
                    p.tgl_beli_barang,
                    p.tgl_beli,
                    DATE(p.created_at)
                ) AS tanggal,
                p.no_request,
                p.nama_barang_beli,
                p.merk_beli,
                p.qty,
                p.supplier,
                p.keterangan
            FROM pembelian p
            WHERE
                UPPER(TRIM(p.nama_barang_beli)) IN (
                    'OLI SAE 40',
                    'OLI SAE 460',
                    'OLI HIDROLIS'
                )
            ORDER BY p.id_pembelian
        ";

        $result = $db->query($sql);

        if (!$result) {
            return [
                'inserted' => 0,
                'skipped'  => 0,
                'error'    => $db->error
            ];
        }

        while ($p = $result->fetch_assoc()) {

            /*
             * Untuk tabel pembelian, nama barang sudah difilter EXACT MATCH
             * pada SQL di atas. Tidak memakai pencarian LIKE '%OLI%'.
             */
            $namaBeliNorm = strtoupper(trim((string)$p['nama_barang_beli']));
            $namaBeliNorm = preg_replace('/\s+/', ' ', $namaBeliNorm);

            $namaOli = match ($namaBeliNorm) {
                'OLI SAE 40'   => 'OLI SAE 40',
                'OLI SAE 460'  => 'OLI SAE 460',
                'OLI HIDROLIS' => 'OLI HIDROLIS',
                default        => null
            };

            if ($namaOli === null) {
                continue;
            }

            $idPembelian = (int)$p['id_pembelian'];

            $stmt = $db->prepare("
                SELECT id_riwayat
                FROM riwayat_oli
                WHERE source_type = 'PEMBELIAN'
                  AND source_id = ?
                LIMIT 1
            ");
            $stmt->bind_param("i", $idPembelian);
            $stmt->execute();

            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                $skipped++;
                continue;
            }

            $idOli = oli_get_or_create_master(
                $db,
                $namaOli,
                $user
            );

            /*
             * Untuk modul oli, qty pembelian diasumsikan dalam LITER.
             * Jika nanti pembelian menggunakan PAIL/DRUM, sebaiknya
             * ditambahkan tabel konversi satuan oli.
             */
            $jumlahLiter = (float)$p['qty'];

            if ($jumlahLiter <= 0) {
                $skipped++;
                continue;
            }

            $tanggal = $p['tanggal'] ?: date('Y-m-d');
            $noRef   = $p['no_request']
                ?: ('PEMBELIAN-' . $idPembelian);

            $ket = trim(
                'Auto dari pembelian: ' .
                $p['nama_barang_beli'] .
                ($p['supplier']
                    ? ' | Supplier: ' . $p['supplier']
                    : '') .
                ($p['keterangan']
                    ? ' | ' . $p['keterangan']
                    : '')
            );

            $stmt = $db->prepare("
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
                    tujuan_tipe,
                    tujuan_nama,
                    keterangan,
                    status_transaksi,
                    created_by
                )
                VALUES
                (
                    ?,
                    ?,
                    'PEMBELIAN',
                    'MASUK',
                    ?,
                    'PEMBELIAN',
                    ?,
                    ?,
                    NULL,
                    NULL,
                    ?,
                    'AKTIF',
                    ?
                )
            ");

            $stmt->bind_param(
                "isdisss",
                $idOli,
                $tanggal,
                $jumlahLiter,
                $idPembelian,
                $noRef,
                $ket,
                $user
            );

            if ($stmt->execute()) {
                $inserted++;
            } else {
                $skipped++;
            }

            $stmt->close();
        }

        return [
            'inserted' => $inserted,
            'skipped'  => $skipped,
            'error'    => null
        ];
    }
}

if (!function_exists('oli_get_saldo')) {
    function oli_get_saldo(
        mysqli $db,
        int $idOli,
        int $excludeId = 0
    ): float {
        if ($excludeId > 0) {

            $stmt = $db->prepare("
                SELECT COALESCE(
                    SUM(
                        CASE
                            WHEN jenis_transaksi = 'MASUK'
                            THEN jumlah
                            ELSE -jumlah
                        END
                    ),
                    0
                ) AS saldo
                FROM riwayat_oli
                WHERE id_oli = ?
                  AND status_transaksi = 'AKTIF'
                  AND id_riwayat <> ?
            ");

            $stmt->bind_param(
                "ii",
                $idOli,
                $excludeId
            );

        } else {

            $stmt = $db->prepare("
                SELECT COALESCE(
                    SUM(
                        CASE
                            WHEN jenis_transaksi = 'MASUK'
                            THEN jumlah
                            ELSE -jumlah
                        END
                    ),
                    0
                ) AS saldo
                FROM riwayat_oli
                WHERE id_oli = ?
                  AND status_transaksi = 'AKTIF'
            ");

            $stmt->bind_param(
                "i",
                $idOli
            );
        }

        $stmt->execute();

        $saldo = (float)(
            $stmt
                ->get_result()
                ->fetch_assoc()['saldo']
            ?? 0
        );

        $stmt->close();

        return $saldo;
    }
}

if (!function_exists('oli_has_stok_awal')) {
    function oli_has_stok_awal(
        mysqli $db,
        int $idOli,
        int $excludeId = 0
    ): bool {
        if ($excludeId > 0) {

            $stmt = $db->prepare("
                SELECT id_riwayat
                FROM riwayat_oli
                WHERE id_oli = ?
                  AND jenis_mutasi = 'STOK_AWAL'
                  AND status_transaksi = 'AKTIF'
                  AND id_riwayat <> ?
                LIMIT 1
            ");

            $stmt->bind_param(
                "ii",
                $idOli,
                $excludeId
            );

        } else {

            $stmt = $db->prepare("
                SELECT id_riwayat
                FROM riwayat_oli
                WHERE id_oli = ?
                  AND jenis_mutasi = 'STOK_AWAL'
                  AND status_transaksi = 'AKTIF'
                LIMIT 1
            ");

            $stmt->bind_param(
                "i",
                $idOli
            );
        }

        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $exists;
    }
}
