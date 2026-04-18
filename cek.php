<?php
if (function_exists('mysqli_connect')) {
    echo "✅ MySQLi sudah AKTIF!";
} else {
    echo "❌ MySQLi BELUM aktif. Periksa kembali php.ini";
    phpinfo(); // Ini akan menampilkan file php.ini mana yang sedang dipakai
}
?>