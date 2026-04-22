<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php?pesan=belum_login");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Purchase Request (PR) - MCP System</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --mcp-blue: #0000FF; --mcp-dark: #00008B; }
        body { background-color: #f8f9fa; }
        .navbar-mcp { background: var(--mcp-blue); color: white; }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 25px; }
        .btn-mcp { background: var(--mcp-blue); color: white; border-radius: 8px; font-weight: bold; }
        .btn-mcp:hover { background: var(--mcp-dark); color: white; }
        .status-badge { font-size: 0.75rem; font-weight: 700; padding: 6px 15px; border-radius: 20px; text-transform: uppercase; }
        .card-stats { border: none; border-radius: 10px; transition: transform 0.2s; }
        .card-stats:hover { transform: translateY(-5px); }

        /* ── Filter Dropdown di Header Tabel ── */
        .filter-select {
            font-size: 0.7rem;
            padding: 2px 4px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            background-color: #fff;
            color: #333;
            width: 100%;
            margin-top: 4px;
            cursor: pointer;
        }
        .filter-select:focus {
            outline: none;
            border-color: #0000FF;
            box-shadow: 0 0 0 2px rgba(0,0,255,0.15);
        }
    </style>
</head>
<body class="pb-5">

<nav class="navbar navbar-mcp mb-4">
    <div class="container-fluid px-4">
        <span class="navbar-brand fw-bold text-white"><i class="fas fa-file-invoice me-2"></i> PURCHASE REQUEST SYSTEM</span>
        <div>
            <a href="../../index.php" class="btn btn-danger"><i class="fas fa-rotate-left"></i> KEMBALI</a>
            
            <?php 
            // Cek jika role BUKAN pemesan_pr_besar, maka tampilkan tombol
            if ($_SESSION['role'] !== 'pemesan_pr_besar'): 
            ?>
                <a href="tambah_request.php" class="btn btn-sm btn-warning fw-bold">
                    <i class="fas fa-plus-circle"></i> BUAT REQUEST BARU (BARANG KECIL)
                </a>
            <?php endif; ?>

            <button type="button" class="btn btn-sm btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalCetakTanggal">
                <i class="fas fa-print"></i> CETAK PER TANGGAL
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 mt-4">
    <div class="row g-3"> <?php
        $count_pending = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_request FROM tr_request WHERE status_request='PENDING'"));
        $count_proses  = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_request FROM tr_request WHERE status_request='PROSES'"));
        $count_selesai = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_request FROM tr_request WHERE status_request='SELESAI'"));
        $count_total   = mysqli_num_rows(mysqli_query($koneksi, "SELECT id_request FROM tr_request"));
        $persen_selesai = ($count_total > 0) ? round(($count_selesai / $count_total) * 100, 1) : 0;
        ?>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 border-start border-warning border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $count_pending ?> <small class="text-muted fs-6">PR</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 border-start border-primary border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Proses</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $count_proses ?> <small class="text-muted fs-6">PR</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-sync-alt fa-2x text-gray-300" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 border-start border-success border-4 shadow-sm h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Selesai</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800"><?= $count_selesai ?> <small class="text-muted fs-6">PR</small></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100 bg-light">
                <div class="card-body d-flex align-items-center py-2">
                    <div style="width: 60px; height: 60px;" class="me-3 flex-shrink-0">
                        <canvas id="chartProgres"></canvas>
                    </div>
                    <div>
                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Progress</div>
                        <div class="h4 mb-0 font-weight-bold text-primary"><?= $persen_selesai ?>%</div>
                        <div class="text-muted" style="font-size: 0.7rem;">Dari <?= $count_total ?> pengajuan</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <?php if(isset($_GET['pesan']) && $_GET['pesan'] == 'hapus_sukses'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>Berhasil!</strong> Data request telah dihapus secara permanen.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="table-container mt-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle w-100" id="tablePR">
                <thead class="bg-light">
                    <tr class="small text-uppercase">
                        <th class="text-center">No</th>
                        <th>No. Request</th>
                        <th>Tanggal</th>
                        <th>Admin</th>
                        <th class="text-center">Total Item</th>
                        <th class="text-center" id="th-status">
                            Status
                            <!-- ══════════════════════════════════════════
                                 FILTER DROPDOWN — kolom index 5
                                 DataTable akan filter kolom ini saat
                                 user memilih opsi dari dropdown ini.
                            ════════════════════════════════════════════ -->
                           <select id="filterStatus" class="filter-select">
                            <option value="">-- Semua --</option>
                            <option value="PENDING">⏳ PENDING</option>
                            <option value="PROSES">🔄 PROSES</option>
                            <option value="SELESAI">✅ SELESAI</option>
                            <option value="BATAL">❌ BATAL</option>
                            <option value="DITOLAK">🚫 DITOLAK</option>
                        </select>
                        </th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php
                    $no = 1;
                   
                    $sql = "SELECT r.*,
                                p.id_po, p.no_po, p.status_po
                            FROM tr_request r
                            LEFT JOIN tr_purchase_order p ON r.id_request = p.id_request
                            ORDER BY r.id_request DESC";
                    $query = mysqli_query($koneksi, $sql);
                    while ($row = mysqli_fetch_array($query)) {
                        $id_req   = $row['id_request'];
                        $jml_item = mysqli_num_rows(mysqli_query($koneksi,
                            "SELECT id_detail FROM tr_request_detail WHERE id_request='$id_req'"));
                    
                        // Status PO (hanya relevan untuk barang besar)
                        $status_po   = $row['status_po'] ?? null;
                        $no_po       = $row['no_po']     ?? null;
                    
                        switch ($row['status_request']) {
                            case 'PENDING' : $badge_color = 'bg-warning text-dark'; break;
                            case 'PROSES'  : $badge_color = 'bg-primary';           break;
                            case 'SELESAI' : $badge_color = 'bg-success';           break;
                            case 'BATAL'   : $badge_color = 'bg-danger';            break;
                            case 'DITOLAK' : $badge_color = 'bg-danger';            break;
                            default        : $badge_color = 'bg-secondary';
                        }
                    
                        // Helper badge warna status PO
                        if ($status_po === 'OPEN') {
                            $po_badge_bg  = '#dcfce7';
                            $po_badge_col = '#166534';
                            $po_badge_bdr = '#86efac';
                            $po_badge_ico = 'fa-circle';
                            $po_badge_txt = 'OPEN';
                        } elseif ($status_po === 'CLOSE') {
                            $po_badge_bg  = '#f1f5f9';
                            $po_badge_col = '#475569';
                            $po_badge_bdr = '#cbd5e1';
                            $po_badge_ico = 'fa-check-double';
                            $po_badge_txt = 'CLOSE';
                        } else {
                            $status_po = null; // DRAFT atau belum ada PO = tidak ditampilkan
                        }
                    ?>
                    <!-- data-status dipakai custom search JS, bukan teks dalam <td> -->
                    <tr data-status="<?= $row['status_request'] ?>">
                        <td class="text-center text-muted"><?= $no++ ?></td>
                        <td class="fw-bold text-primary"><?= $row['no_request'] ?></td>
                        <td><?= date('d/m/Y', strtotime($row['tgl_request'])) ?></td>
                        <td class="text-uppercase"><?= $row['nama_pemesan'] ?></td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark border px-3"><?= $jml_item ?> Item</span>
                        </td>
                       <td class="text-center">
                            <!-- Badge status PR -->
                            <span class="badge status-badge <?= $badge_color ?> d-block mb-1">
                                <?= $row['status_request'] ?>
                            </span>
                        
                            <!-- Badge status PO — hanya untuk PR Besar yang sudah punya PO aktif -->
                            <?php if ($row['kategori_pr'] === 'BESAR' && $status_po): ?>
                            <span style="
                                display:inline-block;
                                background:<?= $po_badge_bg ?>;
                                color:<?= $po_badge_col ?>;
                                border:1px solid <?= $po_badge_bdr ?>;
                                padding:2px 8px;
                                border-radius:50px;
                                font-size:.65rem;
                                font-weight:700;
                                margin-bottom:3px;
                            ">
                                <i class="fas <?= $po_badge_ico ?> me-1" style="font-size:.5rem;"></i>
                                PO: <?= $po_badge_txt ?>
                            </span>
                            <?php endif; ?>
                        
                            <!-- Badge nama pembeli (existing) -->
                            <?php if (!empty($row['nama_pembeli'])):
                                $p = strtoupper($row['nama_pembeli']);
                                $c = "bg-info";
                                if ($p == "GANG")   $c = "bg-danger";
                                if ($p == "HENDRO") $c = "bg-success text-dark";
                            ?>
                                <span class="badge <?= $c ?>" style="font-size:.65rem;">
                                    <i class="fas fa-user me-1"></i><?= $p ?>
                                </span>
                            <?php else: ?>
                                <div class="small text-danger" style="font-size:.7rem;">
                                    <i class="fas fa-exclamation-circle me-1"></i>BELUM DISET
                                </div>
                            <?php endif; ?>
                        </td>
                     <td class="text-center">
                            <div class="btn-group">
                        
                                <!-- Lihat Detail (semua PR) -->
                                <button type="button" class="btn btn-sm btn-dark text-white btn-view-detail"
                                        data-id="<?= $row['id_request'] ?>"
                                        title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                        
                                <!-- Cetak PO — barang besar yang sudah melewati tahap approval -->
                                <?php if ($row['kategori_pr'] == 'BESAR' && !in_array($row['status_approval'], ['MENUNGGU APPROVAL', 'APPROVED 1', 'APPROVED 2', 'DITOLAK', ''])): ?>
                                    <a href="cetak_po.php?id_request=<?= $row['id_request'] ?>"
                                    target="_blank"
                                    class="btn btn-sm"
                                    style="background-color:#4f46e5;color:white;"
                                    title="Cetak PO<?= $no_po ? ' · '.$no_po : '' ?> | Status: <?= $po_badge_txt ?? 'DRAFT' ?>">
                                        <i class="fas fa-file-invoice"></i>
                                        <!-- Dot indikator warna status PO di dalam tombol -->
                                        <?php if ($status_po === 'OPEN'): ?>
                                            <span style="display:inline-block;width:6px;height:6px;background:#4ade80;border-radius:50%;margin-left:2px;vertical-align:middle;" title="PO OPEN"></span>
                                        <?php elseif ($status_po === 'CLOSE'): ?>
                                            <span style="display:inline-block;width:6px;height:6px;background:#94a3b8;border-radius:50%;margin-left:2px;vertical-align:middle;" title="PO CLOSE"></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endif; ?>
                        
                                <!-- Cetak PR (semua PR) -->
                                <!--<a href="cetak_pr.php?id=<?= $row['id_request'] ?>"
                                target="_blank"
                                class="btn btn-sm btn-info text-white"
                                title="Cetak PR">
                                    <i class="fas fa-print"></i>
                                </a>-->
								 <?php
								$file_cetak = ($row['kategori_pr'] === 'BESAR' || strpos($row['no_request'], 'PRB') === 0)
											  ? 'cetak_pr_besar.php'
											  : 'cetak_pr.php';
								?>
								<a href="<?= $file_cetak ?>?id=<?= $row['id_request'] ?>"
								   target="_blank"
								   class="btn btn-sm <?= $row['kategori_pr'] === 'BESAR' ? 'btn-danger' : 'btn-info text-white' ?>"
								   title="Cetak PR <?= $row['kategori_pr'] === 'BESAR' ? 'Besar (QR)' : 'Kecil' ?>">
									<i class="fas fa-print"></i>
									<?php if ($row['kategori_pr'] === 'BESAR'): ?>
										<i class="fas fa-qrcode ms-1" style="font-size:.6rem;opacity:.8;"></i>
									<?php endif; ?>
								</a>
                        
                             <?php 
								// 1. Tentukan tujuan file berdasarkan kategori barang
								$link_edit = ($row['kategori_pr'] == 'BESAR') ? 'edit_request_besar.php' : 'edit_request.php';

								// 2. Tentukan syarat tombol muncul (Bisa edit jika belum selesai/batal, atau jika ditolak)
								$status_bisa_edit = in_array($row['status_request'], ['PENDING', 'PROSES']) || $row['status_approval'] == 'DITOLAK';

								if ($status_bisa_edit): 
								?>
									<a href="<?= $link_edit ?>?id=<?= $row['id_request'] ?>" 
									   class="btn btn-sm btn-warning" 
									   title="<?= ($row['status_approval'] == 'DITOLAK') ? 'Revisi PR' : 'Edit PR' ?>">
										
										<?php if ($row['status_approval'] == 'DITOLAK'): ?>
											<i class="fas fa-redo me-1"></i> Revisi
										<?php else: ?>
											<i class="fas fa-edit"></i> Edit
										<?php endif; ?>
									</a>
								<?php endif; ?>
                        
                                <!-- Hapus: hanya barang KECIL PENDING -->
                                <?php if ($row['status_request'] == 'PENDING' && $row['kategori_pr'] != 'BESAR'): ?>
                                    <a href="hapus_request.php?id=<?= $row['id_request'] ?>"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Hapus seluruh form request ini?')"
                                    title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                        
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Detail PR -->
<div class="modal fade" id="modalDetailPR" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title small fw-bold"><i class="fas fa-info-circle me-2"></i>DETAIL ITEM PURCHASE REQUEST</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="kontenDetail">
                <div class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 small text-muted">Memuat data...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-sm btn-secondary fw-bold" data-bs-dismiss="modal">TUTUP</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cetak Per Tanggal -->
<div class="modal fade" id="modalCetakTanggal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title small"><i class="fas fa-filter me-2"></i> FILTER CETAK REQUEST</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="cetak_pr_bulk.php" method="GET" target="_blank">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="small fw-bold">PILIH TANGGAL REQUEST</label>
                        <input type="date" name="tgl" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">STATUS REQUEST</label>
                        <select name="status" class="form-select">
                            <option value="PENDING">PENDING (Menunggu)</option>
                            <option value="SELESAI">SELESAI (Sudah Dibelikan)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success w-100 fw-bold">PROSES & CETAK</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function () {

    // ── 1. Inisialisasi DataTable ────────────────────────────────
    const table = $('#tablePR').DataTable({
        destroy     : true,
        pageLength  : 10,
        order       : [[0, 'asc']],
        language    : { url: '//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json' },
        columnDefs  : [
            { orderable: false, targets: [4, 5, 6] }
        ]
    });

    // ── 2. Filter Dropdown Status ────────────────────────────────
    // Pakai custom search function berdasarkan data-status di <tr>.
    // Ini lebih andal karena tidak terpengaruh oleh teks badge lain
    // (nama pembeli, "BELUM DISET", dll) yang ada dalam <td> yang sama.
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        const pilihan = $('#filterStatus').val();
        if (!pilihan) return true; // Kosong = tampilkan semua
        const statusRow = $(table.row(dataIndex).node()).data('status') || '';
        return statusRow === pilihan;
    });

    $('#filterStatus').on('click', function (e) {
        e.stopPropagation(); // Cegah klik dropdown memicu sort kolom
    });

    $('#filterStatus').on('change', function () {
        table.draw(); // Cukup redraw, custom search di atas yang menyaring
    });

    // ── 3. Event Delegation untuk tombol detail ──────────────────
    $(document).on('click', '.btn-view-detail', function () {
        const id = $(this).data('id');
        $('#modalDetailPR').modal('show');
        $('#kontenDetail').html(
            '<div class="text-center p-5">' +
            '<div class="spinner-border text-primary"></div>' +
            '<p class="mt-2 small text-muted">Memuat data...</p>' +
            '</div>'
        );
        $.ajax({
            url    : 'get_detail_pr.php',
            type   : 'GET',
            data   : { id: id },
            success: function (response) { $('#kontenDetail').html(response); },
            error  : function () { $('#kontenDetail').html('<div class="alert alert-danger m-3">Gagal mengambil data.</div>'); }
        });
    });
});
</script>

<script>
    // ── SweetAlert pesan berhasil/gagal ──────────────────────────
    const urlParams = new URLSearchParams(window.location.search);
    const pesan     = urlParams.get('pesan');
    const no_pr     = urlParams.get('no');

    if (pesan === 'berhasil') {
        let textTampil = (no_pr && no_pr !== 'null')
            ? `Request <b>${no_pr}</b> telah berhasil dibuat dan disimpan.`
            : `Request telah berhasil dibuat dan disimpan ke sistem.`;
        Swal.fire({ icon: 'success', title: 'BERHASIL!', html: textTampil, confirmButtonColor: '#0000FF' });
    } else if (pesan === 'gagal') {
        Swal.fire({ icon: 'error', title: 'GAGAL!', text: 'Terjadi kesalahan sistem saat menyimpan data.', confirmButtonColor: '#d33' });
    } else if (pesan === 'update_sukses') {
        Swal.fire({ icon: 'success', title: 'BERHASIL!', text: 'Data request berhasil diperbarui.', confirmButtonColor: '#0000FF' });
    
     } else if (pesan === 'revisi_berhasil') {
        Swal.fire({
            icon: 'success',
            title: 'Revisi Berhasil!',
            html: 'PR telah direvisi dan dikirim ulang.<br><small class="text-muted">Menunggu persetujuan Manager dari awal.</small>',
            confirmButtonColor: '#6d28d9'
        });
    }

    window.history.replaceState({}, document.title, window.location.pathname);
</script>

<script>
    // ── Donut Chart Progress ─────────────────────────────────────
    const ctx = document.getElementById('chartProgres').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data            : [<?= $count_proses ?>, <?= $count_pending ?>],
                backgroundColor : ['#0d6efd', '#ffc107'],
                borderWidth     : 0,
                cutout          : '70%'
            }]
        },
        options: {
            responsive          : true,
            maintainAspectRatio : false,
            plugins             : { tooltip: { enabled: false } }
        }
    });
</script>
<script>
    let idleTime = 0;
    const maxIdleMinutes = 15; // Samakan dengan server
    let lastServerUpdate = Date.now();
    let sessionValid = true;

    // Fungsi reset timer saat ada gerakan
    function resetTimer() {
        idleTime = 0;
        let now = Date.now();

        // Kirim sinyal ke server setiap 5 menit agar session PHP tidak expired
        if (now - lastServerUpdate > 300000) {
            fetch('/pr_mcp_rev4/auth/keep_alive.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        sessionValid = false;
                        forceLogout();
                    }
                })
                .catch(err => {
                    console.error("Koneksi ke server terputus");
                });
            lastServerUpdate = now;
        }
    }

    // Fungsi paksa logout
    function forceLogout() {
        alert("Sesi Anda telah berakhir karena tidak ada aktivitas selama 15 menit.");
        // Redirect ke logout.php agar session server juga dihancurkan
        window.location.href = "/pr_mcp_rev4/auth/logout.php?pesan=timeout";
    }

    // Pantau aktivitas user
    window.onload = resetTimer;
    document.onmousemove = resetTimer;
    document.onkeypress = resetTimer;
    document.onmousedown = resetTimer;
    document.onclick = resetTimer;
    document.onscroll = resetTimer;

    // Cek status idle setiap 1 menit
    setInterval(function() {
        idleTime++;
        // Cek session ke server juga
        fetch('/pr_mcp_rev4/auth/keep_alive.php')
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    sessionValid = false;
                    forceLogout();
                }
            })
            .catch(err => {
                // Jika error koneksi, biarkan user tetap di halaman
            });
        if (idleTime >= maxIdleMinutes && sessionValid) {
            forceLogout();
        }
    }, 60000);
</script>
</body>
</html>