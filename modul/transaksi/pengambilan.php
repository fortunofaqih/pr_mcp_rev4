<?php
session_start();
include '../../config/koneksi.php';
include '../../auth/check_session.php';

if ($_SESSION['status'] != "login") {
    header("location:../../login.php");
    exit;
}

// 1. Generate No. Permintaan Otomatis
$bulan_sekarang = date('Ym');
$query_no = mysqli_query($koneksi, "SELECT MAX(no_permintaan) as max_no FROM bon_permintaan WHERE no_permintaan LIKE 'PB-$bulan_sekarang-%'");
$data_no = mysqli_fetch_array($query_no);
$no_urut = (int) substr($data_no['max_no'] ?? '', -4);
$no_urut++;
$no_permintaan = "PB-" . $bulan_sekarang . "-" . sprintf("%04s", $no_urut);

// 2. Proses Simpan Multi Barang
if(isset($_POST['simpan'])){
    $no_req         = $_POST['no_permintaan'];
    $tgl            = $_POST['tgl_keluar'];
    $penerima       = mysqli_real_escape_string($koneksi, strtoupper($_POST['penerima']));
    
    $id_barangs      = $_POST['id_barang'] ?? [];
    $qty_keluars     = $_POST['qty_keluar'] ?? [];
    $plat_nomors     = $_POST['plat_nomor_item'] ?? [];
    $keperluan_items = $_POST['keperluan_item'] ?? [];
    
    $valid_items = array_filter($id_barangs);
    if(empty($valid_items)){
        echo "<script>alert('Pilih minimal 1 barang!'); window.location='pengambilan.php';</script>";
        exit;
    }
    
    // --- A. CEK STOK SEMUA BARANG DULU (SATPAM INPUT BARU) ---
    $error_stok = [];
    foreach($id_barangs as $idx => $id_barang){
        if(empty($id_barang)) continue;
        $qty = (float)($qty_keluars[$idx] ?? 0);
        if($qty <= 0) continue;
		
		// Tambahkan status_aktif ke dalam query
		$res_master = mysqli_fetch_array(mysqli_query($koneksi, "SELECT nama_barang, stok_akhir, status_aktif FROM master_barang WHERE id_barang='$id_barang'"));
		$stok_sekarang = $res_master['stok_akhir'];
		$status_aktif = $res_master['status_aktif'];

		if($status_aktif !== 'AKTIF'){
			$error_stok[] = $res_master['nama_barang'] . " (BARANG NONAKTIF)";
		} elseif($qty > $stok_sekarang){
			$error_stok[] = $res_master['nama_barang'] . " (Tersedia: $stok_sekarang, Diminta: $qty)";
		}
        
       
    }
    
    if(!empty($error_stok)){
        echo "<script>alert('Gagal! Stok tidak mencukupi untuk:\\n- ".implode("\\n- ", $error_stok)."'); window.location='pengambilan.php';</script>";
        exit;
    }
    
    // --- B. PROSES SIMPAN DENGAN TRANSACTION ---
    mysqli_query($koneksi, "BEGIN");
    $success = true;
    $id_cetak = null;
    
    foreach($id_barangs as $idx => $id_barang){
        if(empty($id_barang)) continue;
        $qty = (float)($qty_keluars[$idx] ?? 0);
        if($qty <= 0) continue;
        
        $plat_item  = mysqli_real_escape_string($koneksi, trim($plat_nomors[$idx] ?? ''));
        $keperluan  = mysqli_real_escape_string($koneksi, strtoupper(trim($keperluan_items[$idx] ?? '')));
        $waktu_sekarang = date('H:i:s');
        $tgl_full = "$tgl $waktu_sekarang";

        // 1. Catat ke tr_stok_log
        $info_plat = ($plat_item != "") ? " [UNIT: $plat_item]" : "";
        $keterangan_log = "PENGAMBILAN: $penerima ($keperluan)$info_plat";
        $q_log = mysqli_query($koneksi, "INSERT INTO tr_stok_log (id_barang, tgl_log, tipe_transaksi, qty, keterangan) 
                         VALUES ('$id_barang', '$tgl_full', 'KELUAR', '$qty', '$keterangan_log')");
        
        $id_log_baru = mysqli_insert_id($koneksi);

        // 2. Insert ke bon_permintaan
        $q_bon = mysqli_query($koneksi, "INSERT INTO bon_permintaan (id_log, no_permintaan, id_barang, tgl_keluar, qty_keluar, penerima, keperluan, plat_nomor) 
                         VALUES ('$id_log_baru', '$no_req', '$id_barang', '$tgl', '$qty', '$penerima', '$keperluan', '$plat_item')");
        
        if(is_null($id_cetak)) $id_cetak = mysqli_insert_id($koneksi); 

        // 3. Update stok di master_barang
        $q_stok = mysqli_query($koneksi, "UPDATE master_barang SET stok_akhir = stok_akhir - $qty WHERE id_barang='$id_barang'");

        if(!$q_log || !$q_bon || !$q_stok){ 
            $success = false; 
            break; 
        }
    }
    
    if($success){
        mysqli_query($koneksi, "COMMIT");
        echo "<script>
                if(confirm('✅ Berhasil Simpan! Cetak Bukti?')){
                    window.open('cetak_permintaan.php?id=$id_cetak', '_blank');
                }
                window.location='pengambilan.php';
              </script>";
    } else {
        mysqli_query($koneksi, "ROLLBACK");
        echo "<script>alert('❌ Gagal menyimpan.'); window.location='pengambilan.php';</script>";
    }
}
function formatAngka($angka) {
    if ($angka == 0) return '0';
    return rtrim(rtrim(number_format($angka, 4, ',', '.'), '0'), ',');
}
?>   
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Permintaan Barang - MCP</title>
    <link rel="icon" type="image/png" href="/pr_mcp/assets/img/logo_mcp.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        :root { --mcp-blue: #0000FF; }
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; font-size: 0.85rem; }
        .card { border: none; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.05); border-radius: 10px; }
        .bg-mcp { background-color: var(--mcp-blue) !important; color: white; }
        input, select, textarea { text-transform: uppercase; }
        .btn-mcp { background-color: var(--mcp-blue); color: white; }
        .stok-label { background: #e7f0ff; padding: 8px; border-radius: 6px; border-left: 4px solid blue; font-size: 0.9rem; }
        
        /* Style untuk item barang dinamis */
        .item-barang-row {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 12px;
            background: #fff;
            position: relative;
            box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        .item-barang-row:hover { border-color: #0d6efd; }
        .btn-hapus-item {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 14px;
            width: 28px;
            height: 28px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
		.item-barang-row {
		position: relative; /* Wajib agar tombol hapus tidak lari ke mana-mana */
		border: 1px solid #e0e0e0;
		padding: 15px;
		padding-top: 30px; /* Ruang untuk header item & tombol hapus */
		border-radius: 8px;
		margin-bottom: 15px;
		background-color: #ffffff;
	}

	.item-header {
		position: absolute;
		top: 0;
		left: 0;
		background: #f8f9fa;
		padding: 2px 10px;
		font-size: 10px;
		font-weight: bold;
		border-bottom-right-radius: 8px;
		border-right: 1px solid #e0e0e0;
		border-bottom: 1px solid #e0e0e0;
	}

	.btn-hapus-item {
		position: absolute;
		top: 5px;
		right: 5px;
		z-index: 10; /* Supaya selalu di atas */
	}
        .max-items-warning { font-size: 11px; color: #dc3545; font-weight: 500; }
        .item-header { font-size: 11px; color: #666; margin-bottom: 8px; font-weight: 600; }
        
        @media (max-width: 768px) {
            .item-barang-row .col-md-2, .item-barang-row .col-md-3 { width: 100%; margin-bottom: 8px; }
        }
    </style>
</head>
<body class="py-4">

<div class="container-fluid">
    
    <!-- Statistik -->
    <div class="row mb-4">
        <?php
            $sql_top_barang = "SELECT m.nama_barang, COUNT(b.id_barang) as total_transaksi 
                               FROM bon_permintaan b JOIN master_barang m ON b.id_barang = m.id_barang 
                               GROUP BY b.id_barang ORDER BY total_transaksi DESC LIMIT 5";
            $res_top = mysqli_query($koneksi, $sql_top_barang);
            $labels_top = []; $data_top = [];
            while($row = mysqli_fetch_array($res_top)){
                $labels_top[] = $row['nama_barang'];
                $data_top[] = $row['total_transaksi'];
            }
            $labels_tren = []; $data_tren = [];
            for ($i = 6; $i >= 0; $i--) {
                $tgl = date('Y-m-d', strtotime("-$i days"));
                $labels_tren[] = date('d M', strtotime($tgl));
                $sql_t = mysqli_query($koneksi, "SELECT COUNT(*) as total_transaksi FROM bon_permintaan WHERE tgl_keluar = '$tgl'");
                $dt_t = mysqli_fetch_array($sql_t);
                $data_tren[] = $dt_t['total_transaksi'] ?? 0;
            }
        ?>
         <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0 fw-bold text-dark"><i class="fas fa-chart-area me-2 text-primary"></i>STATISTIK PENGAMBILAN</h5>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-bold mb-3 text-muted"><i class="fas fa-chart-bar me-2 text-primary"></i>TOP 5 BARANG PALING SERING KELUAR</h6>
                    <div style="height: 250px;"><canvas id="chartTopBarang"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-bold mb-3 text-muted"><i class="fas fa-line-chart me-2 text-success"></i>TREN FREKUENSI PENGAMBILAN (7 HARI)</h6>
                    <div style="height: 250px;"><canvas id="chartTrenHarian"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Histori -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold text-dark"><i class="fas fa-clipboard-list me-2 text-primary"></i>RIWAYAT PENGAMBILAN</h5>
                <div class="d-flex gap-2">
                    <a href="../../index.php" class="btn btn-sm btn-danger px-3"><i class="fas fa-arrow-left"></i> KEMBALI</a>
                    <button type="button" class="btn btn-sm btn-mcp px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#modalAmbil">
                        <i class="fas fa-plus-circle me-1"></i> BUAT PERMINTAAN
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle" id="tabelHistori">
                    <thead class="bg-light">
                        <tr class="text-center small fw-bold">
                            <th>NO. PB</th>
                            <th>TANGGAL</th>
                            <th>NAMA BARANG</th>
                            <th>QTY</th>
                            <th>PENERIMA</th>
                            <th>UNIT</th>
                            <th>KEPERLUAN</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $histori = mysqli_query($koneksi, "SELECT b.*, m.nama_barang, m.satuan FROM bon_permintaan b JOIN master_barang m ON b.id_barang=m.id_barang ORDER BY b.id_bon DESC");
                        while($h = mysqli_fetch_array($histori)):
                        ?>
                        <tr>
                            <td class="text-center fw-bold text-primary"><?= $h['no_permintaan'] ?></td>
                            <td class="text-center"><?= date('d/m/Y', strtotime($h['tgl_keluar'])) ?></td>
                            <td class="fw-bold"><?= $h['nama_barang'] ?></td>
                            <td class="text-center text-danger fw-bold"><?= formatAngka($h['qty_keluar']) ?> <?= $h['satuan'] ?></td>
                            <td><?= $h['penerima'] ?></td>
                            <td class="small"><?= $h['plat_nomor'] ?: '-' ?></td>
                            <td class="small"><?= $h['keperluan'] ?></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="cetak_permintaan.php?id=<?= $h['id_bon'] ?>" target="_blank" class="btn btn-sm btn-success" title="Cetak">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning btn-edit" 
										data-id="<?= $h['id_bon'] ?>" 
										data-log="<?= $h['id_log'] ?>"  data-barang="<?= $h['nama_barang'] ?>"
										data-qty="<?= $h['qty_keluar'] ?>" 
										data-penerima="<?= $h['penerima'] ?>"
										data-keperluan="<?= $h['keperluan'] ?>"
										data-tgl="<?= date('Y-m-d', strtotime($h['tgl_keluar'])) ?>"
										data-plat="<?= $h['plat_nomor'] ?>"
										title="Edit"> 
									<i class="fas fa-edit"></i>
								</button>
                                 <!--  <a href="proses_hapus_pengambilan.php?id=<?= $h['id_bon'] ?>&id_log=<?= $h['id_log'] ?>" 
									   class="btn btn-sm btn-danger" 
									   onclick="return confirm('BATALKAN PENGAMBILAN?\n\nBarang: <?= $h['nama_barang'] ?>\nQty: <?= $h['qty_keluar'] ?>\n\nStok akan dikembalikan!')"
									   title="Hapus">
										<i class="fas fa-trash"></i>
									</a>-->
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL FORM PENGELUARAN BARANG (MULTI-ITEM) -->
<div class="modal fade" id="modalAmbil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0">
            <div class="modal-header bg-mcp">
                <h6 class="modal-title fw-bold text-white">FORM PENGELUARAN BARANG</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" id="formPengambilan">
                <div class="modal-body p-4">
                    <div class="alert alert-info py-2 mb-3" style="font-size: 11px;">
                        <i class="fas fa-info-circle me-1"></i> 
                        <strong>Multi-Item:</strong> Tambah hingga 5 barang. Setiap barang bisa memiliki Unit Mobil & Keperluan berbeda.
                    </div>

                    <div class="row g-3 mb-4 pb-3 border-bottom">
                        <div class="col-md-4">
                            <label class="small fw-bold mb-1">NO. PERMINTAAN</label>
                            <input type="text" name="no_permintaan" class="form-control fw-bold bg-light" value="<?= $no_permintaan ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold mb-1">TANGGAL AMBIL</label>
                            <input type="date" name="tgl_keluar" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="small fw-bold mb-1">PENERIMA BARANG</label>
                            <input type="text" name="penerima" class="form-control" required placeholder="NAMA KARYAWAN / TEKNISI">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small fw-bold mb-2 text-primary">
                            <i class="fas fa-boxes me-1"></i> DAFTAR BARANG 
                            <span class="max-items-warning">(Maksimal 5 Item)</span>
                        </label>
                        
                        <div id="container-items">
                            <div class="item-barang-row" data-index="0">
                                <div class="item-header">ITEM #<span class="item-number">1</span></div>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-item" onclick="hapusItem(this)" title="Hapus" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="small fw-bold mb-1">BARANG</label>
                                        <select name="id_barang[]" class="form-select select2-barang-item border-primary" onchange="cekStokItem(this)" required>
                                            <option value="">-- PILIH BARANG --</option>
                                            <?php
                                            // QUERY DIPERBAIKI: Langsung ambil dari stok_akhir
                                            $sql_load = "SELECT id_barang, nama_barang, satuan, stok_akhir 
                                                         FROM master_barang 
                                                         WHERE status_aktif = 'AKTIF' 
                                                         AND stok_akhir > 0 
                                                         ORDER BY nama_barang ASC";
                                            
                                            $res_load = mysqli_query($koneksi, $sql_load);
                                            while($b = mysqli_fetch_array($res_load)){
                                                $sisa = $b['stok_akhir'];
                                                // Menampilkan angka tanpa banyak nol di belakang koma untuk estetika
                                                $stok_tampil = (float)$sisa; 
                                                echo "<option value='{$b['id_barang']}' data-stok='{$sisa}' data-satuan='{$b['satuan']}'>{$b['nama_barang']} (Stok: $stok_tampil)</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold mb-1">STOK</label>
                                        <div class="stok-label text-center">
                                            <span class="txt_stok fw-bold text-primary">0</span> <span class="txt_satuan small"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold text-danger mb-1">QTY</label>
                                        <input type="number" name="qty_keluar[]" class="form-control form-control-sm fw-bold border-danger qty-input" 
                                               min="0.01" step="0.01" placeholder="0.00" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold mb-1 text-primary">UNIT</label>
                                        <select name="plat_nomor_item[]" class="form-select form-select-sm select2-mobil-item">
                                            <option value="">-- UMUM --</option>
                                            <?php
                                            $mobil = mysqli_query($koneksi, "SELECT plat_nomor, merk_tipe FROM master_mobil WHERE status_aktif = 'AKTIF' ORDER BY plat_nomor ASC");
                                            while($m = mysqli_fetch_array($mobil)){
                                                echo "<option value='{$m['plat_nomor']}'>{$m['plat_nomor']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold mb-1">KEPERLUAN</label>
                                        <input type="text" name="keperluan_item[]" class="form-control form-control-sm" placeholder="Kebutuhan">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="btnTambahItem" onclick="tambahItem()">
                            <i class="fas fa-plus me-1"></i> + Tambah Barang
                        </button>
                        <span id="itemCount" class="small text-muted ms-2">1 dari 5 item</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="simpan" class="btn btn-mcp fw-bold py-2 px-4">
                        <i class="fas fa-save me-1"></i> SIMPAN & POTONG STOK
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDIT (SINGLE ITEM) -->
<div class="modal fade" id="modalEditAmbil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="proses_edit_pengambilan.php" method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pengambilan Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_bon" id="edit_id">
                    <input type="hidden" name="id_log" id="edit_id_log">
                    <input type="hidden" name="qty_lama" id="edit_qty_lama"> <div class="mb-3">
                        <label class="form-label fw-bold">Penerima</label>
                        <input type="text" name="penerima" id="edit_penerima" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Qty Diambil</label>
                        <input type="number" step="0.01" name="qty_baru" id="edit_qty" class="form-control" required>
                        <small class="text-muted">*Masukkan angka baru (Contoh: 3)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Unit / Plat Nomor</label>
                        <select name="plat_nomor" id="edit_plat" class="form-select select2-edit">
                            <option value="">-- UMUM --</option>
                            <?php 
                            $m_mobil = mysqli_query($koneksi, "SELECT plat_nomor FROM master_mobil WHERE status_aktif='AKTIF'");
                            while($m = mysqli_fetch_array($m_mobil)) echo "<option value='{$m['plat_nomor']}'>{$m['plat_nomor']}</option>";
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Keperluan</label>
                        <textarea name="keperluan" id="edit_keperluan" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tanggal</label>
                        <input type="date" name="tgl_keluar" id="edit_tgl" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let itemCount = 1;
    const MAX_ITEMS = 5;

    $(document).ready(function() {
        // DataTables
        $('#tabelHistori').DataTable({ "order": [[0, "desc"]] });

        // --- INISIALISASI MODAL TAMBAH ---
        // Panggil init saat modal akan ditampilkan
        $('#modalAmbil').on('shown.bs.modal', function () {
            // Bersihkan dan mulai dari 1 item
            $('#container-items').html('');
            itemCount = 0;
            tambahItem();
        });

        // Reset form saat modal ditutup
        $('#modalAmbil').on('hidden.bs.modal', function () {
            $('#formPengambilan')[0].reset();
        });

        // --- INISIALISASI MODAL EDIT ---
        $(document).on('click', '.btn-edit', function() {
            const d = $(this).data();
            
            // Isi data ke input modal edit
            $('#edit_id').val(d.id);
            $('#edit_id_log').val(d.log);
            $('#edit_penerima').val(d.penerima);
            $('#edit_qty').val(d.qty);
            $('#edit_qty_lama').val(d.qty); // Simpan angka 4 untuk validasi PHP
            $('#edit_keperluan').val(d.keperluan);
            $('#edit_tgl').val(d.tgl);
            
            // Masukkan nilai ke select unit
            $('#edit_plat').val(d.plat);

            // Re-init Select2 khusus Modal Edit agar bisa diklik
            if ($('#edit_plat').hasClass("select2-hidden-accessible")) {
                $('#edit_plat').select2('destroy');
            }

            $('#edit_plat').select2({
                theme: 'bootstrap-5',
                placeholder: '-- PILIH UNIT --',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#modalEditAmbil') // WAJIB: agar dropdown tidak macet
            }).trigger('change');

            $('#modalEditAmbil').modal('show');
        });
    });

    // Fungsi Global Init Select2 Barang (Modal Tambah)
    function initSelect2Barang(selector) {
        $(selector).select2({
            theme: 'bootstrap-5',
            placeholder: '-- CARI BARANG --',
            allowClear: true,
            dropdownParent: $('#modalAmbil'),
            width: '100%'
        }).on('select2:select', function (e) { 
            cekStokItem(this); 
        });
    }

    // Fungsi Global Init Select2 Mobil (Modal Tambah)
    function initSelect2Mobil(selector) {
        $(selector).select2({
            theme: 'bootstrap-5',
            placeholder: '-- PILIH UNIT --',
            allowClear: true,
            dropdownParent: $('#modalAmbil'),
            width: '100%'
        });
    }

    // Fungsi Tambah Baris (Modal Tambah)
    function tambahItem() {
        if(itemCount >= MAX_ITEMS) { 
            alert('Maksimal 5 barang!'); 
            return; 
        }
        
        itemCount++;
        const index = itemCount - 1;
        
       const newItemHtml = `
    <div class="item-barang-row mb-3 p-2 border rounded bg-light" data-index="${index}">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold text-primary">ITEM #<span class="item-number">${itemCount}</span></span>
            <button type="button" class="btn btn-sm btn-outline-danger btn-hapus-item" onclick="hapusItem(this)">
                <i class="fas fa-times"></i> Hapus
            </button>
        </div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="small fw-bold">BARANG</label>
                <select name="id_barang[]" class="form-select select2-barang-item" onchange="cekStokItem(this)" required>
                    <option value="">-- PILIH --</option>
                    <?php
                    // QUERY DIPERBAIKI: Langsung ambil stok_akhir dari master
                    $sql_load = "SELECT id_barang, nama_barang, satuan, stok_akhir 
                                 FROM master_barang 
                                 WHERE status_aktif = 'AKTIF' 
                                 AND stok_akhir > 0 
                                 ORDER BY nama_barang ASC";
                    $res_load = mysqli_query($koneksi, $sql_load);
                    while($b = mysqli_fetch_array($res_load)){
                        $sisa = $b['stok_akhir'];
                        $stok_tampil = (float)$sisa;
                        echo "<option value='{$b['id_barang']}' data-stok='{$sisa}' data-satuan='{$b['satuan']}'>{$b['nama_barang']} (Stok: $stok_tampil)</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2 text-center">
                <label class="small fw-bold">STOK</label>
                <div class="mt-1"><span class="txt_stok fw-bold text-primary">0</span> <span class="txt_satuan small"></span></div>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-danger">QTY</label>
                <input type="number" name="qty_keluar[]" class="form-control form-control-sm qty-input" min="0.01" step="0.01" placeholder="0.00" required>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold text-primary">UNIT</label>
                <select name="plat_nomor_item[]" class="form-select form-select-sm select2-mobil-item">
                    <option value="">-- UMUM --</option>
                    <?php
                    $mobil = mysqli_query($koneksi, "SELECT plat_nomor FROM master_mobil WHERE status_aktif = 'AKTIF' ORDER BY plat_nomor ASC");
                    while($m = mysqli_fetch_array($mobil)) echo "<option value='{$m['plat_nomor']}'>{$m['plat_nomor']}</option>";
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="small fw-bold">KEPERLUAN</label>
                <input type="text" name="keperluan_item[]" class="form-control form-control-sm" placeholder="...">
            </div>
        </div>
    </div>`;
        
        $('#container-items').append(newItemHtml);
        const newItem = $('#container-items .item-barang-row').last();
        initSelect2Barang(newItem.find('.select2-barang-item'));
        initSelect2Mobil(newItem.find('.select2-mobil-item'));
        updateItemCountDisplay();
    }

    // Hapus Baris
    function hapusItem(btn) {
        if(itemCount <= 1) return;
        $(btn).closest('.item-barang-row').remove();
        itemCount--;
        updateItemCountDisplay();
        $('.item-number').each(function(idx) { $(this).text(idx + 1); });
    }

    function updateItemCountDisplay() {
        $('#btnTambahItem').prop('disabled', itemCount >= MAX_ITEMS);
    }

    // Validasi Stok Real-time (Modal Tambah)
    function cekStokItem(selectElement) {
        const selected = selectElement.options[selectElement.selectedIndex];
        const stok = parseFloat(selected.getAttribute('data-stok')) || 0;
        const satuan = selected.getAttribute('data-satuan') || "";
        const row = $(selectElement).closest('.item-barang-row');
        
        row.find('.txt_stok').text(stok);
        row.find('.txt_satuan').text(satuan);
        
        const qtyInput = row.find('.qty-input');
        qtyInput.off('input').on('input', function() {
            this.value = this.value.replace(',', '.');
            if(parseFloat(this.value) > stok) {
                alert(`Stok tidak cukup! Maksimal: ${stok}`);
                this.value = stok;
            }
        });
    }

    // Global listener untuk koma ke titik
    $(document).on('input', '.qty-input, #edit_qty', function() {
        this.value = this.value.replace(',', '.');
    });
</script>
<!-- Chart Scripts (FIXED) -->
<script>
$(document).ready(function() {
    // 1. Chart Top Barang (Horizontal Bar)
    const ctxTop = document.getElementById('chartTopBarang').getContext('2d');
    new Chart(ctxTop, {
        type: 'bar',
        data: {  // ✅ FIX: Tambahkan "data:" di sini
            labels: <?= json_encode($labels_top) ?>,
            datasets: [{
                label: 'Total Transaksi',
                data: <?= json_encode($data_top) ?>,
                backgroundColor: 'rgba(0, 0, 255, 0.7)',
                borderRadius: 5,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false } 
            }
        }
    });

    // 2. Chart Tren Harian (Line)
    const ctxTren = document.getElementById('chartTrenHarian').getContext('2d');
    new Chart(ctxTren, {
        type: 'line',
        data: {  // ✅ FIX: Tambahkan "data:" di sini
            labels: <?= json_encode($labels_tren) ?>,
            datasets: [{
                label: 'Jumlah Transaksi',
                data: <?= json_encode($data_tren) ?>,
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#198754'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false } 
            },
            scales: {
                y: { 
                    beginAtZero: true 
                }
            }
        }
    });
});
</script>
</body>
</html>