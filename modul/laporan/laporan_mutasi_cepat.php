<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// 1. Ambil Parameter Filter
$tgl_mulai    = $_REQUEST['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai  = $_REQUEST['tgl_selesai'] ?? date('Y-m-d');
$huruf_awal   = $_REQUEST['huruf_awal'] ?? 'A';
$huruf_akhir  = $_REQUEST['huruf_akhir'] ?? 'Z';
$search_nama  = mysqli_real_escape_string($koneksi, $_REQUEST['search_nama'] ?? '');

// Handle Checkbox Rak (Array)
$rak_terpilih = $_REQUEST['filter_rak'] ?? []; 
if (!empty($rak_terpilih) && is_array($rak_terpilih)) {
    $rak_terpilih = array_map(function($val) use ($koneksi) {
        return mysqli_real_escape_string($koneksi, trim($val));
    }, $rak_terpilih);
}

// 2. Query List Rak untuk Checkbox
// ✅ KOREKSI: Hanya tampilkan rak dari barang yang status_aktif='AKTIF' AND is_active=1
$list_rak_query = mysqli_query($koneksi, "SELECT DISTINCT lokasi_rak FROM master_barang WHERE lokasi_rak != '' AND lokasi_rak IS NOT NULL AND status_aktif = 'AKTIF' AND is_active = 1");
$rak_options = [];
if ($list_rak_query) {
    while($rk = mysqli_fetch_assoc($list_rak_query)) {
        $rak_options[] = $rk['lokasi_rak'];
    }
}
// Natural Sort agar A1, A2, ... A10 urut dengan benar
natsort($rak_options);
$rak_options = array_values($rak_options);


// 3. Query Utama
// ✅ PERBAIKAN: Menambahkan logic 'RETUR' agar dihitung sebagai penambah stok (+)
$sql = "SELECT 
            m.id_barang, m.nama_barang, m.satuan, m.lokasi_rak,
            (COALESCE(awal.total_awal, 0) + COALESCE(mutasi.m_masuk, 0) - COALESCE(mutasi.m_keluar, 0)) as stok_akhir
        FROM master_barang m
        LEFT JOIN (
            SELECT id_barang, 
            SUM(CASE 
                WHEN tipe_transaksi IN ('MASUK', 'RETUR') THEN qty 
                WHEN tipe_transaksi = 'KELUAR' THEN -qty 
                ELSE 0 END) as total_awal
            FROM tr_stok_log 
            WHERE tgl_log < '$tgl_mulai 00:00:00' 
            GROUP BY id_barang
        ) awal ON m.id_barang = awal.id_barang
        LEFT JOIN (
            SELECT id_barang, 
            SUM(CASE WHEN tipe_transaksi IN ('MASUK', 'RETUR') THEN qty ELSE 0 END) as m_masuk,
            SUM(CASE WHEN tipe_transaksi = 'KELUAR' THEN qty ELSE 0 END) as m_keluar
            FROM tr_stok_log 
            WHERE tgl_log BETWEEN '$tgl_mulai 00:00:00' AND '$tgl_selesai 23:59:59' 
            GROUP BY id_barang
        ) mutasi ON m.id_barang = mutasi.id_barang
        WHERE m.status_aktif = 'AKTIF' AND m.is_active = 1";

// ==========================================
// LOGIKA FILTER BERTUMPUK (INDEPENDEN)
// Semua filter bisa bekerja sendiri-sendiri atau digabung
// ==========================================

// A. Filter Checkbox Rak (Jika ada yang dicentang)
if (!empty($rak_terpilih)) {
    $rak_list_sql = "'" . implode("','", $rak_terpilih) . "'";
    $sql .= " AND m.lokasi_rak IN ($rak_list_sql)";
}

// B. Filter Alfabet Nama Barang (Jika bukan default A-Z)
// Filter ini tetap jalan meskipun checkbox rak dipakai
if ($huruf_awal != 'A' || $huruf_akhir != 'Z') {
    $sql .= " AND LEFT(m.nama_barang, 1) BETWEEN '$huruf_awal' AND '$huruf_akhir'";
}

// C. Filter Cari Nama (Search Text)
if ($search_nama != '') {
    $sql .= " AND m.nama_barang LIKE '%$search_nama%'";
}

// Sorting Data Tabel (Natural Sorting via PHP logic)
$sql .= " ORDER BY 
    CASE WHEN m.lokasi_rak = '' OR m.lokasi_rak = '-' THEN 0 ELSE 1 END ASC,
    LEFT(m.lokasi_rak, 1) ASC, 
    LENGTH(m.lokasi_rak) ASC, 
    m.lokasi_rak ASC, 
    m.nama_barang ASC";

$query = mysqli_query($koneksi, $sql);
$data_tabel = [];
$rak_list_for_jump = [];
if ($query) {
    while($row = mysqli_fetch_assoc($query)) {
        $data_tabel[] = $row;
        $val_rak = $row['lokasi_rak'] ?: '-';
        if (!in_array($val_rak, $rak_list_for_jump)) $rak_list_for_jump[] = $val_rak;
    }
}
natsort($rak_list_for_jump);
$rak_list_for_jump = array_values($rak_list_for_jump);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Mutasi & SO</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html { scroll-behavior: smooth; }
        body { background:#f4f7f6; font-size: 11px; }
        .table-mcp { background-color: #0d6efd !important; color: #ffffff !important; }
        .table thead th { position: sticky; top: 0; z-index: 10; background-color: #0d6efd !important; color: white !important; border: 1px solid #fff; }
        .sticky-filter { position: sticky; top: 0; z-index: 1020; background: #f4f7f6; padding-top: 10px; }
        .table-scroll-container { max-height: 55vh; overflow-y: auto; background: white; border: 1px solid #dee2e6; }
        tr[id^="target-"] { scroll-margin-top: 180px; }
        .cek-box { width: 16px; height: 16px; border: 1px solid #000; display: inline-block; }
        
        /* Style Khusus Checkbox Rak */
        .rak-checkbox-container {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            background: #fff;
            padding: 5px;
            font-size: 10px;
        }
        .rak-checkbox-item {
            display: block;
            margin-bottom: 2px;
            cursor: pointer;
        }
        .rak-checkbox-item input { margin-right: 4px; }
        
        @media print { .no-print { display: none !important; } .table-scroll-container { max-height: none !important; overflow: visible !important; } }
    </style>
</head>
<body class="p-3">

<div class="container-fluid">
    <div class="sticky-filter no-print">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-3">
                <form method="POST" class="row g-2 align-items-end">
                    
                    <!-- FILTER CHECKBOX RAK -->
                    <div class="col-md-3">
                        <label class="fw-bold small text-muted">FILTER RAK (CHECKBOX)</label>
                        <div class="rak-checkbox-container">
                            <label class="rak-checkbox-item fw-bold border-bottom pb-1 mb-1">
                                <input type="checkbox" id="checkAllRak"> Select All / None
                            </label>
                            <?php foreach($rak_options as $ro): 
                                $checked = in_array($ro, $rak_terpilih) ? 'checked' : '';
                            ?>
                                <label class="rak-checkbox-item">
                                    <input type="checkbox" name="filter_rak[]" value="<?= htmlspecialchars($ro) ?>" <?= $checked ?>>
                                    <?= htmlspecialchars($ro) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-muted fst-italic" style="font-size:9px;">*Bisa digabung dengan filter alfabet</small>
                    </div>

                    <!-- FILTER ALFABET NAMA BARANG -->
                    <div class="col-md-2">
                        <label class="fw-bold small text-muted">ALFABET NAMA</label>
                        <div class="input-group input-group-sm">
                            <select name="huruf_awal" class="form-select">
                                <?php foreach(range('A','Z') as $l) echo "<option ".($l==$huruf_awal?'selected':'').">$l</option>"; ?>
                            </select>
                            <span class="input-group-text">s/d</span>
                            <select name="huruf_akhir" class="form-select">
                                <?php foreach(range('A','Z') as $l) echo "<option ".($l==$huruf_akhir?'selected':'').">$l</option>"; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- FILTER CARI NAMA -->
                    <div class="col-md-2">
                        <label class="fw-bold small text-muted">CARI NAMA</label>
                        <input type="text" name="search_nama" class="form-control form-control-sm" value="<?= htmlspecialchars($search_nama) ?>" placeholder="Ketik nama...">
                    </div>
                    
                    <!-- FILTER PERIODE -->
                    <div class="col-md-2">
                        <label class="fw-bold small text-muted">PERIODE</label>
                        <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= $tgl_mulai ?>">
                    </div>
                    
                    <!-- TOMBOL AKSI -->
                    <div class="col-md-3">
                        <div class="d-flex gap-1 justify-content-end">
                            <button type="submit" class="btn btn-sm btn-dark px-3 fw-bold"><i class="fas fa-filter"></i> FILTER</button>
                            <a href="laporan_mutasi_cepat.php" class="btn btn-sm btn-outline-secondary px-2">RESET</a>
                            <a href="../../index.php" class="btn btn-sm btn-danger px-3 fw-bold">KEMBALI</a>
                        </div>
                    </div>
                </form>

                <!-- Jump Links (Navigasi) -->
                <?php if (count($rak_list_for_jump) > 0): ?>
                <div class="mt-2 pt-2 border-top">
                    <small class="fw-bold text-muted me-2">LOKASI RAK:</small>
                    <?php foreach($rak_list_for_jump as $rk): 
                        $target = preg_replace("/[^A-Za-z0-9]/", "", $rk == '-' ? 'TANPARAK' : $rk); ?>
                        <a href="#target-<?= $target ?>" class="btn btn-outline-primary btn-sm fw-bold mb-1" style="font-size: 9px; padding: 1px 6px;"><?= $rk ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="text-center mb-4">
                <h4 class="fw-bold mb-0">LAPORAN MUTASI & STOK OPNAME</h4>
                <p class="text-muted fw-bold">Periode: <?= date('d/m/Y', strtotime($tgl_mulai)) ?> s/d <?= date('d/m/Y', strtotime($tgl_selesai)) ?></p>
                
                <!-- Info Filter Aktif -->
                <?php if (!empty($rak_terpilih) || $huruf_awal != 'A' || $huruf_akhir != 'Z' || $search_nama != ''): ?>
                <div class="alert alert-info py-1 mb-2" style="font-size: 10px;">
                    <i class="fas fa-info-circle me-1"></i> Filter Aktif: 
                    <?php if(!empty($rak_terpilih)) echo "<strong>".count($rak_terpilih)." Rak dipilih</strong> "; ?>
                    <?php if($huruf_awal != 'A' || $huruf_akhir != 'Z') echo "<strong>Nama: $huruf_awal-$huruf_akhir</strong> "; ?>
                    <?php if($search_nama != '') echo "<strong>Cari: '$search_nama'</strong>"; ?>
                </div>
                <?php endif; ?>

                <div class="no-print mt-2 d-flex justify-content-center gap-2">
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-primary px-4 fw-bold">
                        <i class="fas fa-print me-2"></i> CETAK PDF
                    </button>
					<form method="POST" action="export_excel.php" style="display:inline;">
						<!-- Kirim ulang semua nilai filter sebagai hidden input -->
						<input type="hidden" name="tgl_mulai"    value="<?= htmlspecialchars($tgl_mulai) ?>">
						<input type="hidden" name="tgl_selesai"  value="<?= htmlspecialchars($tgl_selesai) ?>">
						<input type="hidden" name="huruf_awal"   value="<?= htmlspecialchars($huruf_awal) ?>">
						<input type="hidden" name="huruf_akhir"  value="<?= htmlspecialchars($huruf_akhir) ?>">
						<input type="hidden" name="search_nama"  value="<?= htmlspecialchars($search_nama) ?>">
						<?php foreach($rak_terpilih as $rak): ?>
							<input type="hidden" name="filter_rak[]" value="<?= htmlspecialchars($rak) ?>">
						<?php endforeach; ?>
						<button type="submit" class="btn btn-sm btn-success px-4 fw-bold">
							<i class="fas fa-file-excel me-2"></i> EXCEL
						</button>

					</form>
                </div>
            </div>

            <div class="table-scroll-container">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-mcp text-center align-middle">
                        <tr>
                            <th width="30">NO</th>
                            <th>NAMA BARANG</th>
                            <th width="100">RAK</th>
                            <th width="80">SATUAN</th>
                            <th width="120">STOK SISTEM</th>
                            <th width="120">STOK FISIK</th>
                            <th width="40">CEK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1; $last_rak = null; 
                        if(empty($data_tabel)) {
                            echo "<tr><td colspan='7' class='text-center py-3 text-muted'>Tidak ada data ditemukan dengan filter tersebut</td></tr>";
                        }
                        foreach($data_tabel as $row): 
                            $curr = $row['lokasi_rak'] ?: '-';
                            if ($curr !== $last_rak): 
                                $rak_id = preg_replace("/[^A-Za-z0-9]/", "", $curr == '-' ? 'TANPARAK' : $curr);
                        ?>
                            <tr id="target-<?= $rak_id ?>" class="table-light fw-bold no-print">
                                <td colspan="7" class="ps-3 py-1 text-primary bg-light border-bottom border-primary">
                                    <i class="fas fa-warehouse me-2"></i> LOKASI RAK: <?= $curr ?>
                                </td>
                            </tr>
                       <?php $last_rak = $curr; endif; ?>
							<tr class="align-middle">
								<td class="text-center text-muted"><?= $no++ ?></td>
								<td class="fw-bold text-uppercase"><?= $row['nama_barang'] ?></td>
								<td class="text-center bg-light"><?= $row['lokasi_rak'] ?: '-' ?></td>
								<td class="text-center"><?= $row['satuan'] ?></td>
								
								<td class="text-center text-danger fw-bold">
									<?php 
										$stok_raw = number_format($row['stok_akhir'], 4, ',', '.');
										echo rtrim(rtrim($stok_raw, '0'), ','); 
									?>
								</td>

								<td style="border-bottom: 2px solid #000 !important; background:#fff;"></td>
								<td class="text-center"><div class="cek-box"></div></td>
							</tr>
						<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="ttd-container mt-5 d-flex justify-content-between text-center">
                <div style="width:200px"><p class="small">Admin</p><div style="height:50px"></div><p class="fw-bold border-top">________________</p></div>
                <div style="width:200px"><p class="small">Pemeriksa</p><div style="height:50px"></div><p class="fw-bold border-top">________________</p></div>
                <div style="width:200px"><p class="small">Manager</p><div style="height:50px"></div><p class="fw-bold border-top">________________</p></div>
            </div>
        </div>
    </div>
</div>

<!-- Script untuk Select All Checkbox -->
<script>
document.getElementById('checkAllRak').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('input[name="filter_rak[]"]');
    for(var checkbox of checkboxes) {
        checkbox.checked = this.checked;
    }
});
</script>

</body>
</html>