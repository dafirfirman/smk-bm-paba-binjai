<?php
// delete_tagihan.php
require_once '../config/koneksi.php';
require_once '../config/functions.php';
session_start();

// Cek autentikasi dan role
requireRole(['bendahara']);

// Set header untuk response JSON
header('Content-Type: application/json');

// Cek apakah request menggunakan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan']);
    exit;
}

// Ambil data JSON dari request body
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Validasi data yang diterima
if (!$data || !isset($data['id']) || !isset($data['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    exit;
}

// Validasi CSRF token
if (!isset($_SESSION['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid']);
    exit;
}

// Ambil ID tagihan dan pastikan integer
$tagihanId = (int)$data['id'];
if ($tagihanId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID tagihan tidak valid']);
    exit;
}

try {
    // Mulai transaksi untuk memastikan integritas data
    $pdo->beginTransaction();
    
    // 1. Hapus notifikasi yang terkait dengan tagihan ini
    $stmt = $pdo->prepare("DELETE FROM notifikasi WHERE tagihan_id = :tagihan_id");
    $stmt->bindParam(':tagihan_id', $tagihanId, PDO::PARAM_INT);
    $stmt->execute();
    
    // 2. Hapus pembayaran yang terkait dengan tagihan ini
    $stmt = $pdo->prepare("DELETE FROM pembayaran WHERE tagihan_id = :tagihan_id");
    $stmt->bindParam(':tagihan_id', $tagihanId, PDO::PARAM_INT);
    $stmt->execute();
    
    // 3. Hapus tagihan itu sendiri
    $stmt = $pdo->prepare("DELETE FROM tagihan WHERE id = :id");
    $stmt->bindParam(':id', $tagihanId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Commit transaksi jika semua berhasil
    $pdo->commit();
    
    // Set pesan sukses
    $_SESSION['success_message'] = "Tagihan berhasil dihapus beserta semua data terkait";
    
    // Kirim response sukses
    echo json_encode([
        'success' => true, 
        'message' => 'Tagihan berhasil dihapus'
    ]);
    
} catch (PDOException $e) {
    // Rollback transaksi jika terjadi error
    $pdo->rollBack();
    
    // Log error
    error_log("Error menghapus tagihan: " . $e->getMessage());
    
    // Kirim response error
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Terjadi kesalahan saat menghapus tagihan: ' . $e->getMessage()
    ]);
}
?>