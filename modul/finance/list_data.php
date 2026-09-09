<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';
require_once __DIR__ . '/../../auth/check_session.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Fund Transfer BCA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .container-custom { max-width: 1400px; margin: 0 auto; }
        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .table th {
            background: #1a3c6e;
            color: white;
            font-size: 12px;
            white-space: nowrap;
        }
        .table td {
            font-size: 12px;
            vertical-align: middle;
        }
        .badge-status {
            font-size: 10px;
        }
        .search-box {
            max-width: 300px;
        }
        .btn-print {
            background: #198754;
            color: white;
        }
        .btn-print:hover {
            background: #146c43;
            color: white;
        }
        .btn-view {
            background: #0d6efd;
            color: white;
        }
        .btn-view:hover {
            background: #0b5ed7;
            color: white;
        }
    </style>
</head>
<body>
<div class="container-custom">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="text-primary fw-bold mb-0">
            <i class="fas fa-list me-2"></i>Daftar Fund Transfer BCA
        </h3>
        <div>
            <a href="fund_transfer.php" class="btn btn-success me-2">
                <i class="fas fa-plus me-1"></i> TAMBAH BARU
            </a>
            <a href="../../index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> KEMBALI
            </a>
        </div>
    </div>

    <div class="table-container">
        <div class="row mb-3">
            <div class="col-md-6">
                <form class="d-flex gap-2" method="GET">
                    <input type="text" name="search" class="form-control search-box" placeholder="Cari nama penerima..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <a href="list_data.php" class="btn btn-secondary"><i class="fas fa-times"></i> Reset</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <span class="text-muted small">Total data: <strong id="totalData">0</strong></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Jenis Kirim</th>
                        <th>Rek. Penerima</th>
                        <th>Nama Penerima</th>
                        <th>Bank</th>
                        <th>Pengirim</th>
                        <th>Mata Uang</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php
                    $limit = 50;
                    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                    $offset = ($page - 1) * $limit;
                    $search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';
                    
                    $where = '';
                    if (!empty($search)) {
                        $where = "WHERE nama_penerima LIKE '%$search%' OR rekening_penerima LIKE '%$search%' OR nama_pengirim LIKE '%$search%'";
                    }
                    
                    $countQuery = "SELECT COUNT(*) as total FROM fund_transfer_bca $where";
                    $countResult = $koneksi->query($countQuery);
                    $totalData = $countResult->fetch_assoc()['total'];
                    
                    $query = "SELECT * FROM fund_transfer_bca $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
                    $result = $koneksi->query($query);
                    
                    if ($result && $result->num_rows > 0) {
                        $no = $offset + 1;
                        while ($row = $result->fetch_assoc()) {
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                                <td><?= htmlspecialchars($row['jenis_pengiriman']) ?></td>
                                <td><?= htmlspecialchars($row['rekening_penerima']) ?></td>
                                <td><strong><?= htmlspecialchars($row['nama_penerima']) ?></strong></td>
                                <td><?= htmlspecialchars($row['nama_bank']) ?></td>
                                <td><?= htmlspecialchars($row['nama_pengirim']) ?></td>
                                <td><span class="badge bg-info"><?= htmlspecialchars($row['mata_uang']) ?></span></td>
                                <td class="text-end fw-bold">Rp <?= number_format($row['jml_total'], 0, ',', '.') ?></td>
                                <td>
                                    <a href="view_data.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-view" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="print_data.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-print" target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <button onclick="deleteData(<?= $row['id'] ?>)" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                Belum ada data
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalData > $limit): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>Menampilkan <?= $offset + 1 ?> - <?= min($offset + $limit, $totalData) ?> dari <?= $totalData ?> data</div>
            <nav>
                <ul class="pagination mb-0">
                    <?php
                    $totalPages = ceil($totalData / $limit);
                    for ($i = 1; $i <= $totalPages; $i++):
                    ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.getElementById('totalData').textContent = '<?= $totalData ?>';

    function deleteData(id) {
        if (confirm('Yakin ingin menghapus data ini?')) {
            fetch('delete_data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + id
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Data berhasil dihapus');
                    location.reload();
                } else {
                    alert('Gagal menghapus data: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>