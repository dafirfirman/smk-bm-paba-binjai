<?php
// Mulai session di awal file
session_start();

require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Pastikan user sudah login dan berperan sebagai siswa
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['user']['role'] !== 'siswa') {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

// Pastikan data siswa tersedia
if (!isset($_SESSION['siswa']['id'])) {
    header("Location: ../index.php?error=session_expired");
    exit();
}

$siswa_id = $_SESSION['siswa']['id'];

// --- PERBAIKAN QUERY DIMULAI DI SINI ---
// Query diubah untuk hanya mengambil tagihan milik siswa yang sedang login
$stmt = $pdo->prepare("SELECT 
                           t.*, 
                           p.status as status_pembayaran,
                           p.tanggal_verifikasi
                       FROM tagihan t
                       LEFT JOIN pembayaran p ON t.id = p.tagihan_id AND p.siswa_id = t.siswa_id
                       WHERE t.siswa_id = ? AND t.status = 'aktif'
                       ORDER BY t.tanggal_jatuh_tempo DESC");
// --- PERBAIKAN QUERY SELESAI ---

$stmt->execute([$siswa_id]);
$tagihan = $stmt->fetchAll();

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan - SMK BM PABA Binjai</title>
    <link rel="stylesheet" href="../assets/css/style_tagihan_siswa.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <?php include_once '../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="tagihan-container">
            <h1>Daftar Tagihan</h1>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <div class="tagihan-list">
                <?php if (!empty($tagihan)): ?>
                    <table class="tagihan-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Tagihan</th>
                                <th>Periode</th>
                                <th>Jatuh Tempo</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tagihan as $key => $item): ?>
                            <tr>
                                <td><?= $key + 1 ?></td>
                                <td><?= htmlspecialchars($item['nama_tagihan']) ?></td>
                                
                                <td><?= htmlspecialchars($item['periode']) ?></td>
                                
                                <td><?= date('d M Y', strtotime($item['tanggal_jatuh_tempo'])) ?></td>
                                <td>Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></td>
                                <td>
                                    <?php 
                                    if ($item['status_pembayaran'] == 'lunas'): ?>
                                        <span class="status-badge success"><i class="fas fa-check-circle"></i> Lunas</span>
                                    <?php elseif ($item['status_pembayaran'] == 'pending'): ?>
                                        <span class="status-badge warning"><i class="fas fa-clock"></i> Menunggu Verifikasi</span>
                                    <?php elseif ($item['status_pembayaran'] == 'ditolak'): ?>
                                        <span class="status-badge danger"><i class="fas fa-times-circle"></i> Ditolak</span>
                                    <?php else: ?>
                                        <span class="status-badge danger"><i class="fas fa-exclamation-circle"></i> Belum Bayar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if (empty($item['status_pembayaran']) || $item['status_pembayaran'] == 'ditolak'): ?>
                                        <a href="pembayaran.php?tagihan_id=<?= $item['id'] ?>" class="btn-action btn-bayar">
                                            <i class="fas fa-money-bill-wave"></i> Bayar
                                        </a>
                                    <?php elseif ($item['status_pembayaran'] == 'pending'): ?>
                                        <span class="btn-action btn-pending">
                                            <i class="fas fa-clock"></i> Proses Verifikasi
                                        </span>
                                    <?php elseif ($item['status_pembayaran'] == 'lunas'): ?>
                                        <a href="cetak_kwitansi.php?tagihan_id=<?= $item['id'] ?>&siswa_id=<?= $siswa_id ?>" class="btn-action btn-kwitansi">
                                            <i class="fas fa-receipt"></i> Kwitansi
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-info-circle"></i>
                        <p>Tidak ada tagihan yang tersedia untuk Anda saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>
</body>
</html>