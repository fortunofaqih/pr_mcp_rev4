<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';
include '../../auth/keep_alive.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}

// ════════════════════════════════════════════════════════════════
// Ambil data barang SEKALI, simpan ke array.
// Dipakai untuk: (1) render tabel, (2) build opsi dropdown filter.
// ════════════════════════════════════════════════════════════════
$sql = "SELECT b.*, 
            (SELECT SUM(qty) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'MASUK') as t_masuk,
            (SELECT SUM(qty) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'KELUAR') as t_keluar
        FROM master_barang b 
        WHERE b.status_aktif = 'AKTIF' 
        AND b.nama_barang NOT LIKE '%LANGSUNG PAKAI%' 
        ORDER BY b.nama_barang ASC";

$query    = mysqli_query($koneksi, $sql);
$list_stok = [];
while ($d = mysqli_fetch_assoc($query)) {
    $list_stok[] = $d;
}
mysqli_free_result($query);

// Build opsi dropdown unik untuk Kategori dan Lokasi Rak
$opt_kategori = [];
$opt_lokasi   = [];
foreach ($list_stok as $d) {
    if (!empty($d['kategori'])   && !in_array($d['kategori'],   $opt_kategori)) $opt_kategori[] = $d['kategori'];
    if (!empty($d['lokasi_rak']) && !in_array($d['lokasi_rak'], $opt_lokasi))   $opt_lokasi[]   = $d['lokasi_rak'];
}
sort($opt_kategori);
sort($opt_lokasi);

// Hitung statistik stok
$sql_stat = "SELECT b.id_barang, 
                (SELECT SUM(qty) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'MASUK') as t_masuk,
                (SELECT SUM(qty) FROM tr_stok_log WHERE id_barang = b.id_barang AND tipe_transaksi = 'KELUAR') as t_keluar
             FROM master_barang b 
             WHERE b.status_aktif = 'AKTIF' 
             AND b.nama_barang NOT LIKE '%LANGSUNG PAKAI%'";

$res_stat = mysqli_query($koneksi, $sql_stat);
$habis = 0; $tipis = 0; $aman = 0;
while ($s = mysqli_fetch_array($res_stat)) {
    $akhir = ($s['t_masuk'] ?? 0) - ($s['t_keluar'] ?? 0);
    if ($akhir <= 0)     $habis++;
    elseif ($akhir <= 3) $tipis++;
    else                 $aman++;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Stok Barang - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        table.dataTable thead th { vertical-align: middle; text-align: center; background-color: #f1f4f9; }
        .uom-badge { background: #e7f0ff; color: #004dc0; font-weight: bold; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; }

        /* Warna Baris Stok */
        .stok-warning { background-color: #fff3cd !important; color: #856404 !important; }
        .stok-danger  { background-color: #f8d7da !important; color: #721c24 !important; }
        .table-hover tbody tr.stok-danger:hover  { background-color: #f1b0b7 !important; }
        .table-hover tbody tr.stok-warning:hover { background-color: #ffe8a1 !important; }

        .card-counter { padding: 15px; border-radius: 12px; transition: transform 0.3s; }
        .card-counter:hover { transform: translateY(-5px); }
        .icon-stat { font-size: 2.5rem; opacity: 0.3; position: absolute; right: 15px; top: 15px; }

        /* ── Filter Dropdown di Header Tabel ── */
        .filter-select {
            font-size: 0.7rem;
            padding: 2px 4px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            background-color: #fff;
            color: #333;
            width: 100%;
            margin-top: 5px;
            cursor: pointer;
            font-weight: normal;
            text-transform: none;
        }
        .filter-select:focus {
            outline: none;
            border-color: #0000FF;
            box-shadow: 0 0 0 2px rgba(0,0,255,0.15);
        }
    </style>
</head>
<body>

<nav class="navbar navbar-mcp mb-4">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-boxes-stacked me-2"></i> MONITORING STOK BARANG</span>
        <div>
            <a href="../../index.php" class="btn btn-sm btn-danger fw-bold px-3"><i class="fas fa-rotate-left me-1"></i> KEMBALI</a>
        </div>
    </div>
</nav>

<!-- Statistik Card -->
<div class="container-fluid px-4 mb-4">
    <div class="row g-3 justify-content-center">
        <div class="col-md-3">
            <div class="card bg-danger text-white card-counter h-100">
                <div class="card-body">
                    <div class="small fw-bold text-uppercase">Stok Habis (Kosong)</div>
                    <h2 class="fw-bold mb-0"><?= $habis ?> <small class="fs-6">Items</small></h2>
                    <i class="fas fa-circle-xmark icon-stat"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark card-counter h-100">
                <div class="card-body">
                    <div class="small fw-bold text-uppercase">Stok Tipis (≤ 3)</div>
                    <h2 class="fw-bold mb-0"><?= $tipis ?> <small class="fs-6">Items</small></h2>
                    <i class="fas fa-triangle-exclamation icon-stat"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white card-counter h-100">
                <div class="card-body">
                    <div class="small fw-bold text-uppercase">Stok Aman</div>
                    <h2 class="fw-bold mb-0"><?= $aman ?> <small class="fs-6">Items</small></h2>
                    <i class="fas fa-check-circle icon-stat"></i>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body d-flex align-items-center">
                    <div style="min-width: 80px;">
                        <canvas id="stokChart" style="max-height: 80px; max-width: 80px;"></canvas>
                    </div>
                    <div class="vr mx-3 opacity-25" style="height: 60px;"></div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            <span class="small fw-bold text-muted text-uppercase">Informasi Data</span>
                        </div>
                        <p class="mb-0 text-secondary" style="font-size: 0.75rem; line-height: 1.4;">
                            Statistik hanya menampilkan <strong>stok inventaris (fisik)</strong>.<br>
                            Barang kategori <span class="badge bg-light text-dark border">Langsung Pakai</span> otomatis dikecualikan dari monitoring gudang.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Stok -->
<div class="container-fluid px-4">
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tabelStok" class="table table-hover table-bordered align-middle w-100">
                    <thead class="small text-uppercase">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama Items</th>
                            <th>Merk</th>

                            <!-- ══════════════════════════════════════════
                                 KOLOM KATEGORI — filter dropdown
                                 data-col="3" → index kolom DataTable
                            ═══════════════════════════════════════════ -->
                            <th class="text-center" data-col="3">
                                Kategori
                                <select class="filter-select" id="filterKategori">
                                    <option value="">-- Semua --</option>
                                    <?php foreach ($opt_kategori as $kat): ?>
                                        <option value="<?= htmlspecialchars($kat) ?>"><?= htmlspecialchars($kat) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </th>

                            <!-- ══════════════════════════════════════════
                                 KOLOM LOKASI RAK — filter dropdown
                                 data-col="4" → index kolom DataTable
                            ═══════════════════════════════════════════ -->
                            <th data-col="4">
                                Lokasi Rak
                                <select class="filter-select" id="filterLokasi">
                                    <option value="">-- Semua --</option>
                                    <?php foreach ($opt_lokasi as $lok): ?>
                                        <option value="<?= htmlspecialchars($lok) ?>"><?= htmlspecialchars($lok) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </th>

                            <th class="text-center">Satuan</th>
                            <th class="text-center">Stok Akhir</th>
                            <th class="text-center">Status</th>
                            <th width="8%" class="text-center">Kartu Stok</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                    <?php
                    $no = 1;
                    foreach ($list_stok as $d):
                        $masuk       = $d['t_masuk']  ?? 0;
                        $keluar      = $d['t_keluar'] ?? 0;
                        $stok_akhir  = $masuk - $keluar;

                        $row_class    = '';
                        $label_status = '';
                        if ($stok_akhir <= 0)     { $row_class = 'stok-danger';  $label_status = 'HABIS'; }
                        elseif ($stok_akhir <= 3) { $row_class = 'stok-warning'; $label_status = 'STOK TIPIS'; }

                        // data-kategori & data-lokasi dipakai custom search JS
                    ?>
                    <tr class="<?= $row_class ?>"
                        data-kategori="<?= htmlspecialchars($d['kategori']   ?? '') ?>"
                        data-lokasi="<?=   htmlspecialchars($d['lokasi_rak'] ?? '') ?>">

                        <td class="text-center text-muted"><?= $no++ ?></td>
                        <td>
                            <div class="fw-bold text-uppercase"><?= htmlspecialchars($d['nama_barang']) ?></div>
                            <?php if ($label_status): ?>
                                <span class="badge bg-dark" style="font-size: 0.6rem;"><?= $label_status ?></span>
                            <?php endif; ?>
                        </td>
                        <td><small><?= $d['merk'] ?: '-' ?></small></td>

                        <!-- Kolom Kategori (index 3) -->
                        <td class="text-center">
                            <span class="badge rounded-pill bg-secondary px-3"><?= htmlspecialchars($d['kategori']) ?></span>
                        </td>

                        <!-- Kolom Lokasi Rak (index 4) -->
                        <td class="text-center">
                            <small><i class="fas fa-map-marker-alt text-primary me-1"></i><?= $d['lokasi_rak'] ?: '-' ?></small>
                        </td>

                        <td class="text-center"><span class="uom-badge"><?= $d['satuan'] ?></span></td>
                        <td class="text-center fw-bold fs-6">
							<?php 
								// 1. Gunakan 4 desimal agar angka presisi tetap tertangkap
								$stok_fmt = number_format($d['stok_akhir'], 4, ',', '.'); 
								
								// 2. Buang nol di ujung kanan, lalu buang koma jika angka bulat
								echo rtrim(rtrim($stok_fmt, '0'), ','); 
							?>
						</td>
						
                        <td class="text-center">
                            <span class="badge <?= ($d['status_aktif'] == 'AKTIF') ? 'bg-success' : 'bg-danger' ?>">
                                <?= $d['status_aktif'] ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-2">
                            
                            <a href="../master/kartu_stok.php?id=<?= $d['id_barang'] ?>" 
                            class="btn btn-sm btn-primary shadow-sm" 
                            title="Lihat Kartu Stok">
                                <i class="fas fa-history me-1"></i> LOG
                            </a>

                            <a href="proses_adjustment.php?id_barang=<?= $d['id_barang'] ?>&stok_master=<?= $d['stok_akhir'] ?>" 
                            class="btn btn-sm btn-warning shadow-sm text-white" 
                            title="Adjustment Stok"
                            onclick="return confirm('Apakah Anda yakin ingin melakukan adjustment stok?')">
                                <i class="fas fa-sync-alt me-1"></i> ADJUST
                            </a>

                        </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function () {

    // ── 1. Inisialisasi DataTable ────────────────────────────────
    const table = $('#tabelStok').DataTable({
        pageLength  : 25,
        language    : { url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json' },
        order       : [[1, 'asc']],
        columnDefs  : [{ orderable: false, targets: [0, 8] }]
    });

    // ── 2. Custom Search: Filter Kategori (kolom index 3) ────────
    // Teknik sama dengan pr.php: baca data-attribute dari <tr>,
    // bukan teks HTML dari <td> (agar badge/span tidak ganggu).
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        const kat  = $('#filterKategori').val();
        const lok  = $('#filterLokasi').val();
        const node = $(table.row(dataIndex).node());

        const rowKat = node.data('kategori') || '';
        const rowLok = node.data('lokasi')   || '';

        // Kedua filter harus terpenuhi sekaligus (AND)
        const lolosKat = !kat || rowKat === kat;
        const lolosLok = !lok || rowLok === lok;

        return lolosKat && lolosLok;
    });

    // ── 3. Event: perubahan dropdown → redraw tabel ──────────────
    $('#filterKategori, #filterLokasi').on('click', function (e) {
        e.stopPropagation(); // Cegah klik dropdown memicu sort kolom
    });

    $('#filterKategori, #filterLokasi').on('change', function () {
        table.draw();
    });

    // ── 4. Donut Chart ───────────────────────────────────────────
    const ctx = document.getElementById('stokChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels  : ['Habis', 'Tipis', 'Aman'],
            datasets: [{
                data           : [<?= $habis ?>, <?= $tipis ?>, <?= $aman ?>],
                backgroundColor: ['#dc3545', '#ffc107', '#198754'],
                hoverOffset    : 4,
                borderWidth    : 0
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            cutout : '65%'
        }
    });
});
</script>
<script>
    let idleTime = 0;
    const maxIdleMinutes = 15;
    let lastServerUpdate = Date.now();

    // Fungsi untuk mereset timer idle
    function resetTimer() {
        idleTime = 0;
        
        let now = Date.now();
        // Kirim sinyal "Keep Alive" ke server setiap 5 menit sekali jika user aktif
        // Ini mencegah session PHP mati saat user sedang asyik mengetik/input
        if (now - lastServerUpdate > 300000) { // 300.000 ms = 5 menit
            const depth = window.location.pathname.split('/').length - 2;
            const prefix = "../".repeat(Math.max(0, depth - 1));
            
            fetch(prefix + 'auth/keep_alive.php')
                .then(response => console.log("Sesi diperbarui secara background"))
                .catch(err => console.error("Gagal memperbarui sesi", err));
            
            lastServerUpdate = now;
        }
    }

    // Deteksi interaksi user
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    // Interval cek setiap 1 menit
    setInterval(function() {
        idleTime++;
        if (idleTime >= maxIdleMinutes) {
            alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
            const depth = window.location.pathname.split('/').length - 2;
            const prefix = "../".repeat(Math.max(0, depth - 1));
            window.location.href = prefix + "login.php?pesan=timeout";
        }
    }, 60000); // Cek setiap 60 detik
</script>
</body>
</html>