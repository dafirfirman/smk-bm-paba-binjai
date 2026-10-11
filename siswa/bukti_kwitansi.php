<?php
session_start();

$page_title = "Bukti Kwitansi";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Cek login siswa
if (!isset($_SESSION['siswa'])) {
    header("Location: ../login.php");
    exit;
}

$siswa_id = $_SESSION['siswa']['id'];

// Ambil data pembayaran
try {
    $stmt = $pdo->prepare("SELECT p.*, t.nama_tagihan, t.periode, t.jumlah as jumlah_tagihan, 
                          t.tanggal_jatuh_tempo, u.username as verifikator,
                          s.nama_lengkap as nama_siswa, s.kelas, s.jurusan, s.nisn
                          FROM pembayaran p
                          JOIN tagihan t ON p.tagihan_id = t.id
                          JOIN siswa s ON p.siswa_id = s.id
                          LEFT JOIN users u ON p.diverifikasi_oleh = u.id
                          WHERE p.siswa_id = ? AND p.status = 'lunas'
                          ORDER BY p.tanggal_pembayaran DESC");
    $stmt->execute([$siswa_id]);
    $pembayaran = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Gagal mengambil data: ' . $e->getMessage();
    $pembayaran = [];
}

// Redirect ke halaman cetak jika ada parameter id
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    header("Location: cetak_kwitansi.php?id=".$_GET['id']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/css/style_bukti_kwitansi.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<!-- Sidebar -->
<div>
    <?php include_once '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h4 class="mb-0"><i class="fas fa-receipt text-primary me-2"></i> Daftar Kwitansi Pembayaran</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?= $_SESSION['error'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <?php if (empty($pembayaran)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-info-circle fa-4x text-muted mb-4"></i>
                            <h4 class="text-dark">Belum ada kwitansi</h4>
                            <p class="text-muted">Tidak ada pembayaran yang telah diverifikasi</p>
                            <a href="tagihan.php" class="btn btn-primary mt-3">
                                <i class="fas fa-money-bill-wave me-1"></i> Lihat Tagihan
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">#</th>
                                        <th width="25%">Tagihan</th>
                                        <th width="15%">Periode</th>
                                        <th width="15%">Tanggal Bayar</th>
                                        <th width="15%">Jumlah</th>
                                        <th width="25%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pembayaran as $key => $item): ?>
                                    <tr>
                                        <td><?= $key+1 ?></td>
                                        <td><?= htmlspecialchars($item['nama_tagihan']) ?></td>
                                        <td><?= date('M Y', strtotime($item['periode'])) ?></td>
                                        <td><?= date('d/m/Y', strtotime($item['tanggal_pembayaran'])) ?></td>
                                        <td>Rp <?= number_format($item['jumlah_tagihan'], 0, ',', '.') ?></td>
                                        <td>
                                            <a href="cetak_kwitansi.php?id=<?= $item['id'] ?>" 
                                               class="btn btn-sm btn-success" target="_blank">
                                                <i class="fas fa-print me-1"></i> Cetak
                                            </a>
                                            
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<!-- Script untuk Sidebar Responsive -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar di mobile
    const sidebarToggle = document.createElement('button');
    sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
    sidebarToggle.className = 'btn btn-sm btn-primary sidebar-toggle d-md-none';
    sidebarToggle.style.position = 'fixed';
    sidebarToggle.style.bottom = '20px';
    sidebarToggle.style.right = '20px';
    sidebarToggle.style.zIndex = '1000';
    document.body.appendChild(sidebarToggle);
    
    sidebarToggle.addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });
});
</script>