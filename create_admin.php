<?php
require 'config/koneksi.php';

$data = [
    'admin' => password_hash('admin123', PASSWORD_DEFAULT),
    'bendahara' => password_hash('bendahara123', PASSWORD_DEFAULT)
];

foreach ($data as $username => $password) {
    // Cek apakah username sudah ada
    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);

    if ($check->rowCount() === 0) {
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $username]);
        echo "Akun $username berhasil dibuat.<br>";
    } else {
        echo "Akun $username sudah ada, dilewati.<br>";
    }
}
?>
