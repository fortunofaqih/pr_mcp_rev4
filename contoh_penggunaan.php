<?php
/**
 * CONTOH PENGGUNAAN HEADER DAN FOOTER TEMPLATE
 * 
 * File ini menunjukkan cara menggunakan header.php dan footer.php
 * untuk membuat halaman baru di sistem MCP.
 */

// ===== SETUP PATH (SESUAIKAN DENGAN LOKASI FILE) =====
// Jika file ini berada di root folder:
$base_url = '';

// Jika file ini berada di subfolder seperti modul/laporan/:
// $base_url = '../../';

// ===== ATUR TITLE HALAMAN =====
$page_title = 'Halaman Baru'; // Ini akan ditampilkan di browser title

// ===== ATUR CSS TAMBAHAN (OPSIONAL) =====
$additional_css = '
<style>
    /* CSS custom untuk halaman ini */
    .custom-container {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
</style>
';

// ===== LOAD HEADER =====
include $base_url . 'header.php';
?>

<!-- ===== KONTEN HALAMAN DIMULAI DARI SINI ===== -->

<h2 class="fw-bold mb-4 text-uppercase">
    <i class="fas fa-star me-2 text-primary"></i>Judul Halaman Baru
</h2>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="m-0 fw-bold"><i class="fas fa-info-circle me-2"></i>Informasi Halaman</h5>
            </div>
            <div class="card-body">
                <p class="mb-0">
                    Halaman ini adalah contoh penggunaan template header.php dan footer.php. 
                    Silakan ganti konten ini dengan konten halaman Anda sendiri.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-success text-white">
                <h6 class="m-0 fw-bold">Card 1</h6>
            </div>
            <div class="card-body">
                <p>Konten Card 1</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-warning text-white">
                <h6 class="m-0 fw-bold">Card 2</h6>
            </div>
            <div class="card-body">
                <p>Konten Card 2</p>
            </div>
        </div>
    </div>
</div>

<!-- ===== KONTEN HALAMAN SELESAI ===== -->

<?php
// ===== ATUR JAVASCRIPT TAMBAHAN (OPSIONAL) =====
$additional_js = '
<script>
    // JavaScript custom untuk halaman ini
    console.log("Halaman baru berhasil dimuat");
    
    // Contoh: Menambahkan event listener
    document.addEventListener("DOMContentLoaded", function() {
        console.log("DOM fully loaded");
    });
</script>
';

// ===== LOAD FOOTER =====
include $base_url . 'footer.php';
?>
