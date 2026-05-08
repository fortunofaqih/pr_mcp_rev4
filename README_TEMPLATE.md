# Template Header dan Footer - MCP System

File-file ini merupakan template reusable untuk membuat halaman-halaman baru di sistem MCP dengan struktur dan styling yang konsisten.

## 📁 File-file yang Disediakan

### 1. **header.php**

Template header yang berisi:

- Session management dan database connection
- Role-based access checking
- Responsive sidebar dengan navigasi menu
- Top navigation bar dengan user info
- CSS styling dan Bootstrap
- Support untuk custom CSS per halaman

### 2. **footer.php**

Template footer yang berisi:

- Script libraries (Bootstrap, jQuery, Chart.js)
- Sidebar toggle functionality
- Mobile responsive handler
- Support untuk custom JavaScript per halaman
- Closing HTML tags

### 3. **contoh_penggunaan.php**

Contoh implementasi lengkap yang menunjukkan cara menggunakan kedua template di atas.

---

## 🚀 Cara Menggunakan

### Langkah 1: Setup Path

Sesuaikan `$base_url` berdasarkan lokasi file Anda:

```php
<?php
// Jika di root folder
$base_url = '';

// Jika di subfolder (contoh: modul/laporan/halaman_baru.php)
$base_url = '../../';

// Jika di subfolder lebih dalam (contoh: modul/laporan/sub/halaman_baru.php)
$base_url = '../../../';
```

### Langkah 2: Setup Page Title

Tentukan judul halaman yang akan ditampilkan di browser tab:

```php
<?php
$page_title = 'Dashboard Baru';
```

### Langkah 3: Include Header

```php
<?php
include $base_url . 'header.php';
?>
```

### Langkah 4: Tulis Konten

Tambahkan HTML konten halaman Anda di antara header dan footer:

```php
<!-- Konten halaman -->
<h2>Judul Halaman</h2>
<p>Isi konten...</p>
```

### Langkah 5: Include Footer

```php
<?php
include $base_url . 'footer.php';
?>
```

---

## 📝 Contoh Lengkap

Berikut adalah struktur lengkap file halaman baru:

```php
<?php
// Setup
$base_url = '../../../';  // Sesuaikan dengan lokasi
$page_title = 'Dashboard Baru';

// Optional: CSS tambahan
$additional_css = '<style>/* CSS custom */</style>';

// Load header
include $base_url . 'header.php';
?>

<!-- KONTEN HALAMAN -->
<div class="container-fluid">
    <h2 class="fw-bold mb-4">Selamat Datang</h2>

    <div class="card">
        <div class="card-header">
            <h5 class="m-0">Info</h5>
        </div>
        <div class="card-body">
            <p>Konten halaman Anda di sini</p>
        </div>
    </div>
</div>

<?php
// Optional: JavaScript tambahan
$additional_js = '<script>console.log("Page loaded");</script>';

// Load footer
include $base_url . 'footer.php';
?>
```

---

## 🎨 Custom CSS & JavaScript

### Menambahkan CSS Tambahan

```php
<?php
$additional_css = '
<style>
    .my-custom-class {
        color: blue;
        font-weight: bold;
    }
</style>
';
include 'header.php';
?>
```

### Menambahkan JavaScript Tambahan

```php
<?php
$additional_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Kode JavaScript Anda
        console.log("Page loaded successfully");
    });
</script>
';
include 'footer.php';
?>
```

---

## 🔐 Role-Based Access

Header sudah menyediakan variable untuk mengecek role user:

```php
<?php
// Variable yang tersedia:
$role;              // Role user (administrator, manager, admin_gudang, dll)
$nama;              // Nama user
$jumlah_notif;      // Jumlah notifikasi approval

// Variable boolean:
$is_gudang_access;  // Akses gudang (admin_gudang atau administrator)
$is_pemesan_pr;     // Role pemesan PR besar
$is_finance;        // Role finance

// Cara menggunakannya:
if ($role == 'administrator') {
    echo "Halaman hanya untuk Administrator";
}

if ($is_gudang_access) {
    // Tampilkan menu gudang
}
?>
```

---

## 🎯 Variabel-Variabel Penting

Variabel yang dapat digunakan di semua halaman:

| Variabel          | Tipe   | Keterangan           |
| ----------------- | ------ | -------------------- |
| `$role`           | string | Role user saat login |
| `$nama`           | string | Nama lengkap user    |
| `$koneksi`        | mysqli | Database connection  |
| `$tahun_pilihan`  | int    | Tahun yang dipilih   |
| `$jumlah_notif`   | int    | Jumlah notifikasi    |
| `$base_url`       | string | Base URL untuk link  |
| `$page_title`     | string | Judul halaman        |
| `$additional_css` | string | Custom CSS           |
| `$additional_js`  | string | Custom JavaScript    |

---

## 📱 Responsive Design

Template sudah fully responsive untuk:

- ✅ Desktop (1200px+)
- ✅ Tablet (768px - 1199px)
- ✅ Mobile (< 768px)

Sidebar akan otomatis menyembunyikan pada mobile dan bisa di-toggle dengan tombol hamburger.

---

## 🔗 Struktur File

```
pr_mcp_rev4/
├── header.php                 # Template header
├── footer.php                 # Template footer
├── contoh_penggunaan.php      # Contoh penggunaan
├── README_TEMPLATE.md         # File ini
├── config/
│   └── koneksi.php           # Database connection
├── auth/
│   └── check_session.php      # Session check
├── index.php                  # Dashboard utama
└── modul/
    ├── laporan/
    │   └── halaman_baru.php   # Contoh halaman di subfolder
    └── ...
```

---

## ⚠️ Penting

1. **Path**: Sesuaikan `$base_url` dengan lokasi file Anda
2. **Session**: Header sudah handle session, tidak perlu `session_start()` lagi
3. **Koneksi DB**: Database sudah terhubung via `$koneksi`
4. **Bootstrap**: Bootstrap 5.3.0 dan Font Awesome 6.4.0 sudah included
5. **jQuery**: jQuery 3.6.0 dan Chart.js 4.4.0 sudah included

---

## 🐛 Troubleshooting

### 1. "Include path tidak ditemukan"

```
Solusi: Periksa nilai $base_url sesuai dengan lokasi file
```

### 2. "Sidebar tidak muncul"

```
Solusi: Pastikan header.php sudah di-include sebelum konten
```

### 3. "CSS tidak terload"

```
Solusi: Periksa path assets/img/logo_mcp.png sudah benar
```

### 4. "JavaScript error di console"

```
Solusi: Pastikan $additional_js di-set sebelum include footer.php
```

---

## 📞 Support

Jika ada pertanyaan atau masalah, hubungi administrator system.

---

**Versi**: 1.0  
**Terakhir diupdate**: May 2026  
**Status**: Active
