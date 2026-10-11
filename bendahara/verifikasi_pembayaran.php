<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Pastikan session dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

requireRole(['bendahara']);

$page_title = "Verifikasi Pembayaran";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Status filter
$status_filter = $_GET['status'] ?? 'pending';

// Search filter
$search = $_GET['search'] ?? '';

// Handle verifikasi/tolak action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Matikan output buffering untuk memastikan redirect berfungsi
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    if (isset($_POST['action']) && isset($_POST['pembayaran_id'])) {
        $pembayaran_id = (int)$_POST['pembayaran_id'];
        $action = $_POST['action'];
        $catatan = $_POST['catatan'] ?? '';
        
        // Validasi ID pembayaran
        if ($pembayaran_id <= 0) {
            $_SESSION['error'] = "ID pembayaran tidak valid";
            header("Location: verifikasi_pembayaran.php?status=pending");
            exit();
        }
        
        try {
            $pdo->beginTransaction();
            
            // CEK DULU apakah pembayaran dengan ID tersebut ada dan masih pending
            $check_query = "SELECT id, status FROM pembayaran WHERE id = ?";
            $check_stmt = $pdo->prepare($check_query);
            $check_stmt->execute([$pembayaran_id]);
            $payment_exists = $check_stmt->fetch();
            
            if (!$payment_exists) {
                $_SESSION['error'] = "Pembayaran tidak ditemukan";
                header("Location: verifikasi_pembayaran.php?status=pending");
                exit();
            }
            
            if ($payment_exists['status'] !== 'pending') {
                $_SESSION['error'] = "Pembayaran sudah diverifikasi sebelumnya";
                header("Location: verifikasi_pembayaran.php?status=pending");
                exit();
            }
            
            // Update status pembayaran
            $status = ($action === 'verify') ? 'lunas' : 'ditolak';
            $verification_date = date('Y-m-d H:i:s');
            
            $update_query = "UPDATE pembayaran 
                            SET status = :status, 
                                tanggal_verifikasi = :tanggal_verifikasi,
                                diverifikasi_oleh = :diverifikasi_oleh,
                                catatan = :catatan
                            WHERE id = :id";
            
            $update_stmt = $pdo->prepare($update_query);
            $update_stmt->execute([
                ':status' => $status,
                ':tanggal_verifikasi' => $verification_date,
                ':diverifikasi_oleh' => $_SESSION['user']['id'],
                ':catatan' => $catatan,
                ':id' => $pembayaran_id
            ]);
            
            // PERBAIKAN: Query yang lebih baik untuk mendapatkan user_id
            $pembayaran_data = $pdo->prepare("SELECT p.*, s.user_id as siswa_user_id, u.id as user_id_direct,
                                             t.nama_tagihan, p.tagihan_id, p.metode_pembayaran, s.nisn
                                             FROM pembayaran p 
                                             JOIN siswa s ON p.siswa_id = s.id 
                                             JOIN tagihan t ON p.tagihan_id = t.id
                                             LEFT JOIN users u ON s.user_id = u.id
                                             WHERE p.id = ?");
            $pembayaran_data->execute([$pembayaran_id]);
            $pembayaran_info = $pembayaran_data->fetch();
            
            if (!$pembayaran_info) {
                throw new Exception("Data pembayaran tidak ditemukan untuk notifikasi");
            }
            
            // Tentukan user_id yang akan digunakan
            $user_id_notifikasi = null;
            if (!empty($pembayaran_info['user_id_direct'])) {
                $user_id_notifikasi = $pembayaran_info['user_id_direct'];
            } elseif (!empty($pembayaran_info['siswa_user_id'])) {
                $user_id_notifikasi = $pembayaran_info['siswa_user_id'];
            } else {
                // Jika masih tidak ada, coba dapatkan user_id dari username (NISN)
                $user_fallback = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $user_fallback->execute([$pembayaran_info['nisn']]);
                $user_data = $user_fallback->fetch();
                if ($user_data) {
                    $user_id_notifikasi = $user_data['id'];
                }
            }
            
            // Buat notifikasi hanya jika user_id valid
            if (!empty($user_id_notifikasi)) {
                $judul = ($action === 'verify') ? "Pembayaran Diverifikasi" : "Pembayaran Ditolak";
                $pesan = ($action === 'verify') 
                    ? "Pembayaran Anda untuk tagihan '{$pembayaran_info['nama_tagihan']}' telah diverifikasi. Terima kasih." 
                    : "Pembayaran Anda untuk tagihan '{$pembayaran_info['nama_tagihan']}' ditolak. Catatan: " . $catatan;
                
                $notifikasi_query = "INSERT INTO notifikasi (user_id, judul, pesan, tagihan_id, metode, status, tanggal_kirim, catatan)
                                    VALUES (:user_id, :judul, :pesan, :tagihan_id, :metode, :status, NOW(), :catatan)";
                
                $notifikasi_stmt = $pdo->prepare($notifikasi_query);
                $notifikasi_stmt->execute([
                    ':user_id' => $user_id_notifikasi,
                    ':judul' => $judul,
                    ':pesan' => $pesan,
                    ':tagihan_id' => $pembayaran_info['tagihan_id'],
                    ':metode' => $pembayaran_info['metode_pembayaran'],
                    ':status' => 'terkirim',
                    ':catatan' => $catatan
                ]);
            } else {
                // Log warning jika tidak dapat menemukan user_id
                error_log("Warning: Tidak dapat menemukan user_id untuk pembayaran ID: " . $pembayaran_id . ". Notifikasi tidak dikirim.");
            }
            
            // Log activity
            $activity = ($action === 'verify') 
                ? "Memverifikasi pembayaran ID: " . $pembayaran_id 
                : "Menolak pembayaran ID: " . $pembayaran_id . " - Catatan: " . $catatan;
            
            logActivity($pdo, $_SESSION['user']['id'], $activity);
            
            $pdo->commit();
            
            // Set session message
            $_SESSION['success'] = ($action === 'verify') 
                ? "Pembayaran berhasil diverifikasi" 
                : "Pembayaran berhasil ditolak";
            
            // PERUBAHAN: Simpan status filter saat ini di session
            $_SESSION['current_status_filter'] = $status_filter;
            
            // Redirect ke halaman yang sama dengan filter yang sama
            $redirect_url = "verifikasi_pembayaran.php?status=" . $status_filter;
            header("Location: " . $redirect_url);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
            header("Location: verifikasi_pembayaran.php?status=pending");
            exit();
        }
    } else {
        $_SESSION['error'] = "Data yang diperlukan tidak lengkap";
        header("Location: verifikasi_pembayaran.php?status=pending");
        exit();
    }
}

// Query untuk mendapatkan data pembayaran yang perlu diverifikasi
$query = "SELECT p.*, 
                 s.nama_lengkap, s.kelas, s.jurusan, s.nisn,
                 t.nama_tagihan, t.jumlah as jumlah_tagihan, t.tanggal_jatuh_tempo,
                 u.username as verifikator
          FROM pembayaran p
          JOIN siswa s ON p.siswa_id = s.id
          JOIN tagihan t ON p.tagihan_id = t.id
          LEFT JOIN users u ON p.diverifikasi_oleh = u.id
          WHERE 1=1";

$params = [];

// Filter berdasarkan status
if ($status_filter === 'pending') {
    $query .= " AND p.status = 'pending'";
} elseif ($status_filter === 'verified') {
    $query .= " AND p.status = 'lunas'";
} elseif ($status_filter === 'rejected') {
    $query .= " AND p.status = 'ditolak'";
} else {
    $query .= " AND (p.status = 'pending' OR p.status = 'lunas' OR p.status = 'ditolak')";
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/css/style_verifikasi.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tambahkan style untuk alert sukses */
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .alert i {
            margin-right: 10px;
            font-size: 20px;
        }
        
        /* Animasi untuk alert */
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .alert {
            animation: slideDown 0.5s ease-out;
        }
        
        /* PERUBAHAN: Style untuk notifikasi yang muncul tanpa reload */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: none;
            align-items: center;
            animation: slideInRight 0.5s ease-out;
        }
        
        .notification.success {
            background-color: #28a745;
        }
        
        .notification.error {
            background-color: #dc3545;
        }
        
        .notification i {
            margin-right: 10px;
            font-size: 18px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .notification.hide {
            animation: slideOutRight 0.5s ease-out forwards;
        }
    </style>
</head>
<body>
    <div>
    <div class="dashboard-container"></div>
        <?php include_once '../includes/sidebar.php'; ?>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h1>Verifikasi Pembayaran</h1>
            <p>Kelola dan verifikasi pembayaran dari siswa</p>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="filter-section">
            <div class="filter-header">
                <h3><i class="fas fa-filter"></i> Filter Data</h3>
                <button type="button" class="btn-toggle-filter" onclick="toggleFilterSection()">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <form method="GET" class="filter-form" id="filterForm">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="status">Status Pembayaran</label>
                        <select id="status" name="status" class="select-custom">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="verified" <?= $status_filter === 'verified' ? 'selected' : '' ?>>Terverifikasi</option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                        </select>
                    </div>
                    
                    <div class="form-group action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                        <a href="verifikasi_pembayaran.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Daftar Pembayaran</h3>
                <div class="header-info">
                    <span class="badge"><?= count($pembayaran) ?> Pembayaran</span>
                    <div class="summary">
                        <?php
                        $pending_count = 0;
                        $verified_count = 0;
                        $rejected_count = 0;
                        
                        foreach ($pembayaran as $item) {
                            if ($item['status'] === 'pending') $pending_count++;
                            if ($item['status'] === 'lunas') $verified_count++;
                            if ($item['status'] === 'ditolak') $rejected_count++;
                        }
                        ?>
                        <span class="summary-item pending"><i class="fas fa-clock"></i> <?= $pending_count ?> Pending</span>
                        <span class="summary-item verified"><i class="fas fa-check-circle"></i> <?= $verified_count ?> Terverifikasi</span>
                        <span class="summary-item rejected"><i class="fas fa-times-circle"></i> <?= $rejected_count ?> Ditolak</span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($pembayaran) > 0): ?>
                    <div class="payment-list">
                        <?php foreach ($pembayaran as $item): ?>
                            <?php
                            $status_class = '';
                            $status_icon = '';
                            if ($item['status'] === 'pending') {
                                $status_class = 'payment-pending';
                                $status_icon = 'clock';
                            } else if ($item['status'] === 'lunas') {
                                $status_class = 'payment-verified';
                                $status_icon = 'check-circle';
                            } else if ($item['status'] === 'ditolak') {
                                $status_class = 'payment-rejected';
                                $status_icon = 'times-circle';
                            }
                            ?>
                            
                            <div class="payment-card <?= $status_class ?>">
                                <div class="payment-header">
                                    <div class="payment-title">
                                        <i class="fas fa-<?= $status_icon ?>"></i>
                                        <h4><?= htmlspecialchars($item['nama_lengkap']) ?> <span class="nisn">(<?= $item['nisn'] ?>)</span></h4>
                                    </div>
                                    <div class="payment-meta">
                                        <span class="payment-date">
                                            <i class="far fa-calendar-alt"></i>
                                            <?= date('d M Y H:i', strtotime($item['tanggal_pembayaran'])) ?>
                                        </span>
                                        <span class="status-badge status-<?= 
                                            $item['status'] === 'pending' ? 'pending' : 
                                            ($item['status'] === 'lunas' ? 'verified' : 'rejected')
                                        ?>">
                                            <?= 
                                                $item['status'] === 'pending' ? 'Menunggu Verifikasi' : 
                                                ($item['status'] === 'lunas' ? 'Terverifikasi' : 'Ditolak')
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="payment-content">
                                    <div class="payment-details">
                                        <div class="detail-group">
                                            <h5>Informasi Siswa</h5>
                                            <div class="detail-item">
                                                <span class="label">Kelas</span>
                                                <span class="value"><?= $item['kelas'] ?> - <?= $item['jurusan'] ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <h5>Detail Tagihan</h5>
                                            <div class="detail-item">
                                                <span class="label">Tagihan</span>
                                                <span class="value"><?= $item['nama_tagihan'] ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="label">Jatuh Tempo</span>
                                                <span class="value"><?= date('d M Y', strtotime($item['tanggal_jatuh_tempo'])) ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-group">
                                            <h5>Informasi Pembayaran</h5>
                                            <div class="detail-item">
                                                <span class="label">Metode</span>
                                                <span class="value"><?= $item['metode_pembayaran'] ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="label">Jumlah Tagihan</span>
                                                <span class="value">Rp <?= number_format($item['jumlah_tagihan'], 0, ',', '.') ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="label">Dibayar</span>
                                                <span class="value">Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></span>
                                            </div>
                                            <?php if (!empty($item['denda'])): ?>
                                                <div class="detail-item">
                                                    <span class="label">Denda</span>
                                                    <span class="value">Rp <?= number_format($item['denda'], 0, ',', '.') ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="detail-item total">
                                                <span class="label">Total</span>
                                                <span class="value">Rp <?= number_format($item['total_bayar'], 0, ',', '.') ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($item['bukti_pembayaran'])): ?>
                                    <div class="proof-section">
                                        <h5>Bukti Pembayaran</h5>
                                        <?php
                                        $bukti_path = '../uploads/bukti_pembayaran/' . $item['bukti_pembayaran'];
                                        $file_exists = file_exists($bukti_path);
                                        ?>
                                        
                                        <?php if ($file_exists): ?>
                                            <div class="proof-image-container">
                                                <img src="<?= $bukti_path ?>" 
                                                    alt="Bukti Pembayaran" 
                                                    class="proof-image"
                                                    onclick="openImageModal('<?= $bukti_path ?>')">
                                                <div class="proof-overlay" onclick="openImageModal('<?= $bukti_path ?>')">
                                                    <i class="fas fa-search-plus"></i>
                                                    Lihat Detail
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="proof-missing">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                File bukti tidak ditemukan: <?= $item['bukti_pembayaran'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($item['status'] !== 'pending'): ?>
                                        <div class="verification-info">
                                            <h5>Informasi Verifikasi</h5>
                                            <div class="detail-item">
                                                <span class="label">Tanggal Verifikasi</span>
                                                <span class="value"><?= date('d M Y H:i', strtotime($item['tanggal_verifikasi'])) ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <span class="label">Diverifikasi oleh</span>
                                                <span class="value"><?= $item['verifikator'] ?? 'System' ?></span>
                                            </div>
                                            <?php if (!empty($item['catatan'])): ?>
                                                <div class="detail-item">
                                                    <span class="label">Catatan</span>
                                                    <span class="value"><?= htmlspecialchars($item['catatan']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                    
                                <?php if ($item['status'] === 'pending'): ?>
                                    <div class="payment-actions">
                                        <button type="button" class="btn btn-success btn-action" 
                                                onclick="openVerificationModal(<?= $item['id'] ?>, 'verify')">
                                            <i class="fas fa-check-circle"></i> Verifikasi
                                        </button>
                                        <button type="button" class="btn btn-danger btn-action" 
                                                onclick="openRejectionModal(<?= $item['id'] ?>)">
                                            <i class="fas fa-times-circle"></i> Tolak
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
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

    <!-- PERUBAHAN: Notifikasi muncul tanpa reload -->
    <div id="notification" class="notification">
        <i class="fas fa-check-circle"></i>
        <span id="notification-message">Pembayaran berhasil diverifikasi</span>
    </div>

    <!-- Modal for image preview -->
    <div id="imageModal" class="image-modal">
        <span class="close">&times;</span>
        <div class="image-modal-content">
            <img id="modalImage" src="" alt="Bukti Pembayaran">
        </div>
    </div>

    <!-- Modal for verification -->
    <div id="verificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Verifikasi Pembayaran</h3>
                <span class="close">&times;</span>
            </div>
            <form id="verificationForm" method="POST">
                <input type="hidden" name="pembayaran_id" id="pembayaranId">
                <input type="hidden" name="action" id="actionType">
                
                <div class="modal-body">
                    <p id="modalMessage">Apakah Anda yakin ingin memverifikasi pembayaran ini?</p>
                    <div class="form-group">
                        <label for="catatan">Catatan (Opsional)</label>
                        <textarea name="catatan" id="catatan" class="text-field" placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmit">Verifikasi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal for rejection -->
    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tolak Pembayaran</h3>
                <span class="close">&times;</span>
            </div>
            <form id="rejectionForm" method="POST">
                <input type="hidden" name="pembayaran_id" id="rejectionPembayaranId">
                <input type="hidden" name="action" value="reject">
                
                <div class="modal-body">
                    <p>Mengapa pembayaran ini ditolak?</p>
                    <div class="form-group">
                        <label for="rejectionCatatan">Alasan Penolakan *</label>
                        <textarea name="catatan" id="rejectionCatatan" class="text-field" placeholder="Jelaskan alasan penolakan..." required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Pembayaran</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // PERUBAHAN: Fungsi untuk menampilkan notifikasi
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            const notificationMessage = document.getElementById('notification-message');
            
            // Set pesan dan tipe notifikasi
            notificationMessage.textContent = message;
            notification.className = 'notification ' + type;
            
            // Tampilkan notifikasi
            notification.style.display = 'flex';
            
            // Sembunyikan notifikasi setelah 5 detik
            setTimeout(() => {
                notification.classList.add('hide');
                setTimeout(() => {
                    notification.style.display = 'none';
                    notification.classList.remove('hide');
                }, 500);
            }, 5000);
        }
        
        // Function to open image modal
        function openImageModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        // Function to close modals
        function closeModal() {
            document.getElementById('verificationModal').style.display = 'none';
            document.getElementById('rejectionModal').style.display = 'none';
            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Function to open verification modal
        function openVerificationModal(id, action) {
            document.getElementById('pembayaranId').value = id;
            document.getElementById('actionType').value = action;
            
            if (action === 'verify') {
                document.getElementById('modalTitle').textContent = 'Verifikasi Pembayaran';
                document.getElementById('modalMessage').textContent = 'Apakah Anda yakin ingin memverifikasi pembayaran ini?';
                document.getElementById('modalSubmit').textContent = 'Verifikasi';
                document.getElementById('modalSubmit').className = 'btn btn-success';
            }
            
            document.getElementById('verificationModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        // Function to open rejection modal
        function openRejectionModal(id) {
            document.getElementById('rejectionPembayaranId').value = id;
            document.getElementById('rejectionModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        // Toggle filter section visibility
        function toggleFilterSection() {
            const filterForm = document.getElementById('filterForm');
            const toggleBtn = document.querySelector('.btn-toggle-filter i');
            
            if (filterForm.style.display === 'none') {
                filterForm.style.display = 'block';
                toggleBtn.className = 'fas fa-chevron-up';
            } else {
                filterForm.style.display = 'none';
                toggleBtn.className = 'fas fa-chevron-down';
            }
        }
        
        // Close modals when clicking on X
        document.querySelectorAll('.close').forEach(function(closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
            if (event.target.classList.contains('image-modal')) {
                closeModal();
            }
        });
        
        // PERUBAHAN: Handle form submission dengan AJAX
        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = formData.get('action');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                // Tampilkan notifikasi sukses
                if (action === 'verify') {
                    showNotification('Pembayaran berhasil diverifikasi', 'success');
                } else {
                    showNotification('Pembayaran berhasil ditolak', 'success');
                }
                
                // Tutup modal
                closeModal();
                
                // PERUBAHAN: Refresh halaman setelah 2 detik untuk menampilkan data terbaru
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan saat memproses permintaan', 'error');
            });
        });
        
        // PERUBAHAN: Handle form submission dengan AJAX untuk penolakan
        document.getElementById('rejectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text();
            })
            .then(data => {
                // Tampilkan notifikasi sukses
                showNotification('Pembayaran berhasil ditolak', 'success');
                
                // Tutup modal
                closeModal();
                
                // PERUBAHAN: Refresh halaman setelah 2 detik untuk menampilkan data terbaru
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan saat memproses permintaan', 'error');
            });
        });
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to cards
            const cards = document.querySelectorAll('.payment-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // PERUBAHAN: Cek jika ada session success untuk menampilkan notifikasi
            <?php if (isset($_SESSION['success'])): ?>
                showNotification("<?= $_SESSION['success'] ?>", "success");
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            // PERUBAHAN: Cek jika ada session error untuk menampilkan notifikasi
            <?php if (isset($_SESSION['error'])): ?>
                showNotification("<?= $_SESSION['error'] ?>", "error");
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>