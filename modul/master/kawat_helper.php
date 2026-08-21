<?php
// kawat_helper.php
// Helper untuk sinkron master kawat + pembelian menjadi transaksi stok.

if (!function_exists('kawat_parse_ukuran')) {
    function kawat_parse_ukuran(string $nama): ?float
    {
        if (preg_match('/\bKAWAT\s+([0-9]+(?:[.,][0-9]+)?)/i', $nama, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }
        return null;
    }
}

if (!function_exists('kawat_get_or_create_master')) {
    function kawat_get_or_create_master(mysqli $db, float $ukuran, string $user = 'system'): int
    {
        $stmt = $db->prepare("SELECT id_kawat FROM master_kawat WHERE ukuran = ? LIMIT 1");
        $stmt->bind_param("d", $ukuran);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($row = $res->fetch_assoc()) {
            $stmt->close();
            return (int)$row['id_kawat'];
        }
        $stmt->close();

        $stmt = $db->prepare("
            INSERT INTO master_kawat (ukuran, stok_awal, created_by)
            VALUES (?, 0, ?)
        ");
        $stmt->bind_param("ds", $ukuran, $user);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();

        return $id;
    }
}

if (!function_exists('kawat_sync_master_barang')) {
    function kawat_sync_master_barang(mysqli $db, string $user = 'system'): array
    {
        $created = 0;
        $linked = 0;

        $sql = "
            SELECT id_barang, nama_barang
            FROM master_barang
            WHERE is_active = 1
              AND UPPER(TRIM(nama_barang)) REGEXP '^KAWAT[[:space:]]+[0-9]+([,.][0-9]+)?'
            ORDER BY id_barang
        ";

        $res = $db->query($sql);
        if (!$res) {
            return ['created' => 0, 'linked' => 0, 'error' => $db->error];
        }

        while ($row = $res->fetch_assoc()) {
            $ukuran = kawat_parse_ukuran($row['nama_barang']);
            if ($ukuran === null) {
                continue;
            }

            $before = $db->query("SELECT COUNT(*) AS c FROM master_kawat")->fetch_assoc()['c'];
            $idKawat = kawat_get_or_create_master($db, $ukuran, $user);
            $after = $db->query("SELECT COUNT(*) AS c FROM master_kawat")->fetch_assoc()['c'];
            if ($after > $before) {
                $created++;
            }

            $idBarang = (int)$row['id_barang'];
            $stmt = $db->prepare("
                INSERT IGNORE INTO master_kawat_barang (id_kawat, id_barang)
                VALUES (?, ?)
            ");
            $stmt->bind_param("ii", $idKawat, $idBarang);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $linked++;
            }
            $stmt->close();
        }

        return ['created' => $created, 'linked' => $linked, 'error' => null];
    }
}

if (!function_exists('kawat_factor_ke_kg')) {
    function kawat_factor_ke_kg(string $satuan): ?float
    {
        $s = strtoupper(trim($satuan));
        return match ($s) {
            'KG'  => 1.0,
            'ONS' => 0.1,
            'G', 'GRAM' => 0.001,
            default => null
        };
    }
}

if (!function_exists('kawat_sync_pembelian')) {
    function kawat_sync_pembelian(mysqli $db, string $user = 'system'): array
    {
        // Pastikan master & mapping terbentuk dulu.
        kawat_sync_master_barang($db, $user);

        $inserted = 0;
        $skipped = 0;

        $sql = "
            SELECT
                p.id_pembelian,
                COALESCE(p.tgl_beli_barang, p.tgl_beli, DATE(p.created_at)) AS tanggal,
                p.no_request,
                p.nama_barang_beli,
                p.qty,
                p.supplier,
                p.keterangan
            FROM pembelian p
            WHERE UPPER(TRIM(p.nama_barang_beli))
                  REGEXP '^KAWAT[[:space:]]+[0-9]+([,.][0-9]+)?'
            ORDER BY p.id_pembelian
        ";

        $res = $db->query($sql);
        if (!$res) {
            return ['inserted' => 0, 'skipped' => 0, 'error' => $db->error];
        }

        while ($p = $res->fetch_assoc()) {
            $idPembelian = (int)$p['id_pembelian'];

            // Sudah pernah disinkron?
            $stmt = $db->prepare("
                SELECT id_transaksi
                FROM transaksi_kawat
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

            $ukuran = kawat_parse_ukuran($p['nama_barang_beli']);
            if ($ukuran === null) {
                $skipped++;
                continue;
            }

            $idKawat = kawat_get_or_create_master($db, $ukuran, $user);

            // Cari master_barang paling cocok untuk menentukan satuan asal.
            $stmt = $db->prepare("
                SELECT mb.id_barang, mb.satuan
                FROM master_kawat_barang mkb
                JOIN master_barang mb ON mb.id_barang = mkb.id_barang
                WHERE mkb.id_kawat = ?
                ORDER BY
                    CASE WHEN UPPER(TRIM(mb.nama_barang)) = UPPER(TRIM(?)) THEN 0 ELSE 1 END,
                    mb.id_barang ASC
                LIMIT 1
            ");
            $namaBeli = $p['nama_barang_beli'];
            $stmt->bind_param("is", $idKawat, $namaBeli);
            $stmt->execute();
            $barang = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $satuan = $barang['satuan'] ?? 'KG';
            $factor = kawat_factor_ke_kg($satuan);

            // Jangan memasukkan transaksi dengan unit yang tidak dapat
            // dikonversi dengan aman ke KG.
            if ($factor === null) {
                $skipped++;
                continue;
            }

            $qtyAsal = (float)$p['qty'];
            $qtyKg = $qtyAsal * $factor;

            // Cari id_satuan sesuai master_satuan.
            $idSatuan = null;
            $stmt = $db->prepare("
                SELECT id_satuan
                FROM master_satuan
                WHERE UPPER(TRIM(nama_satuan)) = UPPER(TRIM(?))
                LIMIT 1
            ");
            $stmt->bind_param("s", $satuan);
            $stmt->execute();
            if ($sat = $stmt->get_result()->fetch_assoc()) {
                $idSatuan = (int)$sat['id_satuan'];
            }
            $stmt->close();

            $tanggal = $p['tanggal'] ?: date('Y-m-d');
            $noRef = $p['no_request'] ?: ('PEMBELIAN-' . $idPembelian);
            $ket = trim(
                'Auto dari pembelian: ' . $p['nama_barang_beli'] .
                ($p['supplier'] ? ' | Supplier: ' . $p['supplier'] : '') .
                ($p['keterangan'] ? ' | ' . $p['keterangan'] : '')
            );

            $stmt = $db->prepare("
                INSERT INTO transaksi_kawat
                (
                    tanggal, id_kawat, jenis_mutasi, kondisi, arah,
                    qty_asal, id_satuan_asal, qty_kg,
                    source_type, source_id, no_referensi, keterangan, created_by
                )
                VALUES
                (?, ?, 'PEMBELIAN', 'BARU', 'IN',
                 ?, ?, ?, 'PEMBELIAN', ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "sididisss",
                $tanggal,
                $idKawat,
                $qtyAsal,
                $idSatuan,
                $qtyKg,
                $idPembelian,
                $noRef,
                $ket,
                $user
            );

            if ($stmt->execute()) {
                $inserted++;
            } else {
                // duplicate source dianggap sudah sinkron
                $skipped++;
            }
            $stmt->close();
        }

        return ['inserted' => $inserted, 'skipped' => $skipped, 'error' => null];
    }
}

if (!function_exists('kawat_get_saldo')) {
    function kawat_get_saldo(mysqli $db, int $idKawat, string $kondisi): float
    {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(
                CASE WHEN arah = 'IN' THEN qty_kg ELSE -qty_kg END
            ), 0) AS saldo
            FROM transaksi_kawat
            WHERE id_kawat = ? AND kondisi = ?
        ");
        $stmt->bind_param("is", $idKawat, $kondisi);
        $stmt->execute();
        $saldo = (float)($stmt->get_result()->fetch_assoc()['saldo'] ?? 0);
        $stmt->close();
        return $saldo;
    }
}
