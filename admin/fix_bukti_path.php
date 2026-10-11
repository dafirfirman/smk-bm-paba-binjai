<?php
session_start();
require_once '../config/koneksi.php';

// Hanya admin yang bisa menjalankan
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    die("Akses ditolak");
}

echo "<h2>Memperbaiki Data Bukti Pembayaran</h2>";

// Ambil data yang pathnya salah (yang mengandung ../)
$stmt = $pdo->query("SELECT id, bukti_pembayaran FROM pembayaran WHERE bukti_pembayaran LIKE '../%'");
$incorrect_files = $stmt->fetchAll();

if (count($incorrect_files) > 0) {
    echo "<p>Ditemukan " . count($incorrect_files) . " data yang perlu diperbaiki:</p>";
    echo "<ul>";
    
    foreach ($incorrect_files as $file) {
        $old_path = $file['bukti_pembayaran'];
        $new_filename = basename($old_path); // Hanya ambil nama file
        
        echo "<li>Memperbaiki: <strong>$old_path</strong> → <strong>$new_filename</strong></li>";
        
        // Update database
        $update_stmt = $pdo->prepare("UPDATE pembayaran SET bukti_pembayaran = ? WHERE id = ?");
        $update_stmt->execute([$new_filename, $file['id']]);
    }
    
    echo "</ul>";
    echo "<p style='color: green;'><strong>Perbaikan selesai! Data telah diperbarui.</strong></p>";
} else {
    echo "<p>Tidak ada data yang perlu diperbaiki. Semua data sudah benar.</p>";
}

// Tampilkan contoh data
echo "<h3>Contoh Data Terkini:</h3>";
$stmt = $pdo->query("SELECT id, bukti_pembayaran FROM pembayaran LIMIT 5");
$sample_data = $stmt->fetchAll();

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Bukti Pembayaran</th></tr>";
foreach ($sample_data as $data) {
    echo "<tr>";
    echo "<td>{$data['id']}</td>";
    echo "<td>{$data['bukti_pembayaran']}</td>";
    echo "</tr>";
}
echo "</table>";
?>