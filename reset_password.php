<?php
include 'config/koneksi.php';

echo "<h3>Reset / Insert User System</h3>";

$users = [
    [
        'username' => 'gudang1',
        'password' => 'Gudang123',
        'nama_lengkap' => 'Admin Gudang 1',
        'role' => 'admin_gudang',
        'bagian' => 'Gudang',
        'status_aktif' => 'AKTIF'
    ],
    [
        'username' => 'gudang2',
        'password' => 'Gudang123',
        'nama_lengkap' => 'Admin Gudang 2',
        'role' => 'admin_gudang',
        'bagian' => 'Gudang',
        'status_aktif' => 'AKTIF'
    ],
    [
        'username' => 'beli1',
        'password' => 'Beli123',
        'nama_lengkap' => 'Admin Pembelian 1',
        'role' => 'bagian_pembelian',
        'bagian' => 'Pembelian',
        'status_aktif' => 'AKTIF'
    ],
    [
        'username' => 'superadmin',
        'password' => 'SuperAdmin_MCP123',
        'nama_lengkap' => 'Super Administrator',
        'role' => 'administrator',
        'bagian' => 'IT',
        'status_aktif' => 'AKTIF'
    ],
    [
        'username' => 'manager_mcp',
        'password' => 'Manager_Mutiara_2026',
        'nama_lengkap' => 'Manager Mutiaracahaya Plastindo',
        'role' => 'manager',
        'bagian' => 'Manager',
        'status_aktif' => 'AKTIF'
    ],
];

foreach ($users as $u) {

    $username     = mysqli_real_escape_string($koneksi, $u['username']);
    $nama_lengkap = mysqli_real_escape_string($koneksi, $u['nama_lengkap']);
    $role         = $u['role'];
    $bagian       = $u['bagian'];
    $status       = $u['status_aktif'];

    $passwordHash = password_hash($u['password'], PASSWORD_DEFAULT);

    // Cek user
    $cek = mysqli_query(
        $koneksi,
        "SELECT id_user FROM users WHERE username='$username'"
    );

    if (mysqli_num_rows($cek) > 0) {

        // UPDATE
        $query = "
            UPDATE users SET
                password      = '$passwordHash',
                nama_lengkap  = '$nama_lengkap',
                role          = '$role',
                bagian        = '$bagian',
                status_aktif  = '$status'
            WHERE username = '$username'
        ";

        mysqli_query($koneksi, $query);
        echo "UPDATE user <b>$username</b> ✔<br>";

    } else {

        // INSERT
        $query = "
            INSERT INTO users
                (username, password, nama_lengkap, role, bagian, status_aktif)
            VALUES
                ('$username', '$passwordHash', '$nama_lengkap', '$role', '$bagian', '$status')
        ";

        mysqli_query($koneksi, $query);
        echo "INSERT user <b>$username</b> ✔<br>";
    }
}

echo "<br><b>SELESAI</b><br>";
echo "<a href='login.php'>Kembali ke Login</a>";
?>
