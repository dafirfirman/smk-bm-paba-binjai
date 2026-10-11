<?php 
require_once '../config/koneksi.php';
require_once '../config/functions.php';
requireRole(['bendahara']);

$page_title = "Cetak & Kirim Kwitansi";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Fungsi untuk validasi input
function validateInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Filter status
$status_filter = $_GET['status'] ?? 'lunas';

// Search filter
$search = $_GET['search'] ?? '';

// Query untuk mendapatkan data pembayaran
$query = "SELECT p.*, s.nama_lengkap, s.nisn, s.kelas, s.jurusan, t.nama_tagihan, t.jumlah as jumlah_tagihan, t.periode, u.username as verifikator 
          FROM pembayaran p 
          JOIN siswa s ON p.siswa_id = s.id 
          JOIN tagihan t ON p.tagihan_id = t.id 
          LEFT JOIN users u ON p.diverifikasi_oleh = u.id 
          WHERE 1=1";

$params = [];

// Filter berdasarkan status
if ($status_filter === 'lunas') {
    $query .= " AND p.status = 'lunas'";
} elseif ($status_filter === 'pending') {
    $query .= " AND p.status = 'pending'";
} elseif ($status_filter === 'ditolak') {
    $query .= " AND p.status = 'ditolak'";
} else {
    $query .= " AND (p.status = 'lunas' OR p.status = 'pending' OR p.status = 'ditolak')";
}

// Filter berdasarkan pencarian
if (!empty($search)) {
    $query .= " AND (s.nama_lengkap LIKE :search OR s.nisn LIKE :search OR t.nama_tagihan LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY p.tanggal_pembayaran DESC";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$pembayaran = $stmt->fetchAll();

// Jika ada aksi cetak kwitansi
if (isset($_GET['cetak'])) {
    $id = validateInput($_GET['cetak']);
    try {
        $stmt = $pdo->prepare("SELECT p.*, s.nama_lengkap, s.nisn, s.kelas, s.jurusan, t.nama_tagihan, t.jumlah as jumlah_tagihan, t.periode, u.username as verifikator 
                              FROM pembayaran p 
                              JOIN siswa s ON p.siswa_id = s.id 
                              JOIN tagihan t ON p.tagihan_id = t.id 
                              LEFT JOIN users u ON p.diverifikasi_oleh = u.id 
                              WHERE p.id = ? AND p.status = 'lunas'");
        $stmt->execute([$id]);
        $pembayaran_detail = $stmt->fetch();

        if ($pembayaran_detail) {
            include_once 'cetak_kwitansi.php';
            exit;
        } else {
            $_SESSION['error'] = "Data pembayaran tidak ditemukan atau belum diverifikasi";
            header("Location: kwitansi.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: kwitansi.php");
        exit;
    }
}

if (isset($_POST['hapus_pembayaran'])) {
    $id = validateInput($_POST['id']);
    try {
        // Mulai transaksi
        $pdo->beginTransaction();

        // Dapatkan data pembayaran untuk log
        $stmt = $pdo->prepare("SELECT p.*, s.nama_lengkap FROM pembayaran p JOIN siswa s ON p.siswa_id = s.id WHERE p.id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if ($data) {
            // Hapus pembayaran
            $delete_stmt = $pdo->prepare("DELETE FROM pembayaran WHERE id = ?");
            $delete_stmt->execute([$id]);

            // Catat log penghapusan
            logActivity($pdo, $_SESSION['user']['id'], "Menghapus pembayaran ID " . $id . " - " . $data['nama_lengkap'] . " - Rp " . number_format($data['jumlah'], 0, ',', '.'));

            $pdo->commit();
            $success_message = "Pembayaran berhasil dihapus";
        } else {
            $error_message = "Data pembayaran tidak ditemukan";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Gagal menghapus pembayaran: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/css/style_kwitansi.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include_once '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>Cetak & Kirim Kwitansi</h1>
            <p>Kelola pencetakan dan pengiriman kwitansi pembayaran siswa</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success" id="successAlert">
                <div class="alert-content">
                    <i class="fas fa-check-circle"></i> 
                    <span><?= $success_message ?></span>
                </div>
                <button class="alert-close" onclick="closeAlert('successAlert')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" id="errorAlert">
                <div class="alert-content">
                    <i class="fas fa-exclamation-circle"></i> 
                    <span><?= $error_message ?></span>
                </div>
                <button class="alert-close" onclick="closeAlert('errorAlert')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" id="sessionSuccessAlert">
                <div class="alert-content">
                    <i class="fas fa-check-circle"></i> 
                    <span><?= $_SESSION['success'] ?></span>
                </div>
                <button class="alert-close" onclick="closeAlert('sessionSuccessAlert')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" id="sessionErrorAlert">
                <div class="alert-content">
                    <i class="fas fa-exclamation-circle"></i> 
                    <span><?= $_SESSION['error'] ?></span>
                </div>
                <button class="alert-close" onclick="closeAlert('sessionErrorAlert')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Status Pembayaran</label>
                    <select id="status" name="status" class="select-custom">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="lunas" <?= $status_filter === 'lunas' ? 'selected' : '' ?>>Lunas</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="ditolak" <?= $status_filter === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Terapkan Filter
                    </button>
                    <a href="kwitansi.php" class="btn btn-secondary">
                        <i class="fas fa-sync"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Daftar Pembayaran</h3>
                <span class="badge"><?= count($pembayaran) ?> Pembayaran</span>
            </div>
            <div class="card-body">
                <?php if (count($pembayaran) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Siswa</th>
                                    <th>NISN</th>
                                    <th>Tagihan</th>
                                    <th>Kelas</th>
                                    <th>Nominal</th>
                                    <th>Tanggal Bayar</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pembayaran as $key => $item): ?>
                                    <tr>
                                        <td><?= $key + 1 ?></td>
                                        <td><?= htmlspecialchars($item['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($item['nisn']) ?></td>
                                        <td><?= $item['nama_tagihan'] ?></td>
                                        <td><?= $item['kelas'] ?> <?= $item['jurusan'] ?></td>
                                        <td>Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></td>
                                        <td>
                                            <?php 
                                                // Perbaikan tampilan tanggal pembayaran
                                                if ($item['status'] === 'lunas' && !empty($item['tanggal_verifikasi'])) {
                                                    echo date('d M Y', strtotime($item['tanggal_verifikasi']));
                                                } elseif (!empty($item['tanggal_pembayaran'])) {
                                                    echo date('d M Y', strtotime($item['tanggal_pembayaran']));
                                                } else {
                                                    echo '-';
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $item['status'] ?>">
                                                <?= 
                                                    $item['status'] === 'lunas' ? 'Lunas' : 
                                                    ($item['status'] === 'pending' ? 'Pending' : 'Ditolak')
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($item['status'] === 'lunas'): ?>
                                                    <a href="?cetak=<?= $item['id'] ?>" target="_blank" class="btn-action btn-print" title="Cetak Kwitansi">
                                                        <i class="fas fa-print"></i> Cetak
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Tidak tersedia</span>
                                                <?php endif; ?>
                                                <button type="button" onclick="openDeleteModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['nama_lengkap']) ?>')" class="btn-action btn-delete" title="Hapus Pembayaran">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-receipt"></i>
                        <h3>Tidak ada data pembayaran</h3>
                        <p>Tidak ditemukan pembayaran dengan filter yang dipilih</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Modal untuk hapus pembayaran -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Hapus Pembayaran</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pembayaran dari <span id="delete_nama" class="font-weight-bold"></span>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
                <form method="POST" action="">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Batal</button>
                        <button type="submit" name="hapus_pembayaran" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/kwitansi.js"></script>
    <script>
        // Function to close alerts
        function closeAlert(alertId) {
            document.getElementById(alertId).style.display = 'none';
        }

        // Auto close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>