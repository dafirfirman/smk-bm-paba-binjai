<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';

requireRole(['siswa']);

$page_title = "Dashboard Siswa";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

$siswa_id = $_SESSION['siswa']['id'];

// Ambil data siswa lengkap termasuk foto profil
$stmt = $pdo->prepare("SELECT s.*, u.email, u.no_telepon as no_hp_user 
                      FROM siswa s
                      JOIN users u ON s.user_id = u.id
                      WHERE s.id = ?");
$stmt->execute([$siswa_id]);
$siswa_data = $stmt->fetch();


// Ambil tagihan bulan ini
$current_month = date('Y-m');
$stmt = $pdo->prepare("SELECT t.*, p.status as status_pembayaran 
                       FROM tagihan t
                       LEFT JOIN pembayaran p ON t.id = p.tagihan_id AND p.siswa_id = ?
                       WHERE t.periode = ? AND t.status = 'aktif'");
$stmt->execute([$siswa_id, $current_month]);
$tagihan = $stmt->fetch();

// Ambil riwayat pembayaran
$stmt = $pdo->prepare("SELECT p.*, t.nama_tagihan 
                       FROM pembayaran p
                       JOIN tagihan t ON p.tagihan_id = t.id
                       WHERE p.siswa_id = ?
                       ORDER BY p.tanggal_pembayaran DESC LIMIT 5");
$stmt->execute([$siswa_id]);
$riwayat_pembayaran = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/style_dashboard_siswa.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <div>
        <?php include_once '../includes/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <main class="main-content">
        <div class="welcome-section">
            <div class="welcome-card">
                <h1>Selamat datang, <span class="highlight"><?= $_SESSION['siswa']['nama_lengkap'] ?></span></h1>
                <div class="user-info">
                    <div class="info-item">
                        <i class="fas fa-user-graduate"></i>
                        <span>Siswa - <?= $_SESSION['siswa']['kelas'] ?> | <?= $_SESSION['siswa']['jurusan'] ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar-day"></i>
                        <span><?= date('l, d F Y') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <!-- Tagihan Bulan Ini -->
            <div class="card bill-card">
                <div class="card-header">
                    <h2><i class="fas fa-file-invoice-dollar"></i> Tagihan Bulan Ini</h2>
                </div>
                <div class="card-body">
                    <?php if ($tagihan): ?>
                    <div class="bill-details <?= $tagihan['status_pembayaran'] == 'diverifikasi' ? 'paid' : '' ?>">
                        <div class="bill-info">
                            <h3><?= $tagihan['nama_tagihan'] ?></h3>
                            <div class="meta">
                                <span><i class="fas fa-calendar-alt"></i> <?= date('F Y', strtotime($tagihan['periode'])) ?></span>
                                <span><i class="fas fa-clock"></i> Jatuh Tempo: <?= date('d M Y', strtotime($tagihan['tanggal_jatuh_tempo'])) ?></span>
                            </div>
                        </div>
                        <div class="bill-amount">
                            <span class="amount">Rp <?= number_format($tagihan['jumlah'], 0, ',', '.') ?></span>
                            <div class="status">
                                <?php if ($tagihan['status_pembayaran'] == 'diverifikasi'): ?>
                                    <span class="badge success"><i class="fas fa-check-circle"></i> Lunas</span>
                                <?php elseif ($tagihan['status_pembayaran'] == 'pending'): ?>
                                    <span class="badge warning"><i class="fas fa-clock"></i> Proses</span>
                                <?php else: ?>
                                    <span class="badge danger"><i class="fas fa-exclamation-circle"></i> Belum Bayar</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!$tagihan['status_pembayaran'] || $tagihan['status_pembayaran'] == 'ditolak'): ?>
                    <div class="actions">
                        <a href="pembayaran.php?tagihan_id=<?= $tagihan['id'] ?>" class="btn btn-pay">
                            <i class="fas fa-money-bill-wave"></i> Bayar Sekarang
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>Tidak ada tagihan untuk bulan ini</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Riwayat Pembayaran -->
            <div class="card history-card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Riwayat Pembayaran Terakhir</h2>
                </div>
                <div class="card-body">
                    <?php if ($riwayat_pembayaran): ?>
                    <div class="history-list">
                        <?php foreach ($riwayat_pembayaran as $key => $pembayaran): ?>
                        <div class="history-item">
                            <div class="history-main">
                                <div class="history-info">
                                    <h3><?= $pembayaran['nama_tagihan'] ?></h3>
                                    <div class="meta">
                                        <span>Rp <?= number_format($pembayaran['jumlah'], 0, ',', '.') ?></span>
                                        <span><?= date('d M Y', strtotime($pembayaran['tanggal_pembayaran'])) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <p>Belum ada riwayat pembayaran</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>