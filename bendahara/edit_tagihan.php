<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Pastikan session sudah dimulai
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

requireRole(['bendahara']);

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "ID tagihan tidak valid.";
    header("Location: kelola_tagihan.php");
    exit();
}

$tagihan_id = (int)$_GET['id'];

// Get tagihan data
try {
    $stmt = $pdo->prepare("SELECT * FROM tagihan WHERE id = ?");
    $stmt->execute([$tagihan_id]);
    $tagihan = $stmt->fetch();
    
    if (!$tagihan) {
        $_SESSION['error_message'] = "Tagihan tidak ditemukan.";
        header("Location: kelola_tagihan.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error mengambil data tagihan: " . $e->getMessage();
    header("Location: kelola_tagihan.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Matikan output buffering untuk memastikan redirect berfungsi
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $nama_tagihan = $_POST['nama_tagihan'];
    $kategori = $_POST['kategori'];
    $jumlah = $_POST['jumlah'];
    $periode = $_POST['periode'];
    $tanggal_jatuh_tempo = $_POST['tanggal_jatuh_tempo'];
    $tahun_ajaran = $_POST['tahun_ajaran'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE tagihan SET nama_tagihan = ?, kategori = ?, jumlah = ?, periode = ?, 
                              tanggal_jatuh_tempo = ?, tahun_ajaran = ?, status = ?, updated_at = NOW() 
                              WHERE id = ?");
        $stmt->execute([$nama_tagihan, $kategori, $jumlah, $periode, $tanggal_jatuh_tempo, $tahun_ajaran, $status, $tagihan_id]);
        
        // Set session untuk notifikasi sukses
        $_SESSION['success_message'] = "Tagihan berhasil diperbarui.";
        
        // Redirect ke halaman kelola tagihan
        header("Location: kelola_tagihan.php");
        exit();
    } catch (PDOException $e) {
        // Jika terjadi error, simpan error message dan redirect
        $_SESSION['error_message'] = "Error memperbarui tagihan: " . $e->getMessage();
        header("Location: kelola_tagihan.php");
        exit();
    }
}

// Jika bukan POST request, tampilkan form
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SMK PABA Payment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/edit_tagihan.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tambahkan style untuk alert success jika belum ada */
        .alert-success {
            background-color: #f0fff4;
            color: #2f855a;
            border: 1px solid #c6f6d5;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }
        
        .alert-success::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 5px;
            background-color: #48bb78;
        }
        
        .alert-success i {
            margin-right: 12px;
            font-size: 18px;
        }
        
        /* Style untuk loading indicator */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            visibility: hidden;
        }
        
        .loading-overlay.active {
            visibility: visible;
        }
        
        .loading-spinner {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="layout-wrapper">
        <!-- Sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div class="header-title">
                    <h1>Edit Tagihan</h1>
                    <p class="subtitle">Edit data tagihan</p>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
            <?php endif; ?>
            
            <div class="content-card">
                <div class="card-header">
                    <h3>Form Edit Tagihan</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="form" id="editTagihanForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="nama_tagihan">Nama Tagihan *</label>
                                <input type="text" id="nama_tagihan" name="nama_tagihan" value="<?= htmlspecialchars($tagihan['nama_tagihan']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="kategori">Kategori *</label>
                                <select id="kategori" name="kategori" required>
                                    <option value="">Pilih Kategori</option>
                                    <option value="Uang Sekolah" <?= $tagihan['kategori'] == 'Uang Sekolah' ? 'selected' : '' ?>>Uang Sekolah</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="jumlah">Jumlah *</label>
                                <div class="input-with-icon">
                                    <span class="input-icon">Rp</span>
                                    <input type="number" id="jumlah" name="jumlah" min="0" value="<?= $tagihan['jumlah'] ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="periode">Periode *</label>
                                <select id="periode" name="periode" required>
                                    <option value="">Pilih Periode</option>
                                    <option value="Bulanan" <?= $tagihan['periode'] == 'Bulanan' ? 'selected' : '' ?>>Bulanan</option>
                                    <option value="Tahunan" <?= $tagihan['periode'] == 'Tahunan' ? 'selected' : '' ?>>Tahunan</option>
                                    <option value="Semester" <?= $tagihan['periode'] == 'Semester' ? 'selected' : '' ?>>Semester</option>
                                    <option value="Lainnya" <?= $tagihan['periode'] == 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="tanggal_jatuh_tempo">Tanggal Jatuh Tempo *</label>
                                <div class="input-with-icon">
                                    <span class="input-icon"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" value="<?= $tagihan['tanggal_jatuh_tempo'] ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="tahun_ajaran">Tahun Ajaran *</label>
                                <input type="text" id="tahun_ajaran" name="tahun_ajaran" value="<?= htmlspecialchars($tagihan['tahun_ajaran']) ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status *</label>
                                <div class="status-toggle">
                                    <input type="radio" id="status-aktif" name="status" value="aktif" <?= $tagihan['status'] == 'aktif' ? 'checked' : '' ?> required>
                                    <label for="status-aktif" class="toggle-option active-option">Aktif</label>
                                    
                                    <input type="radio" id="status-nonaktif" name="status" value="nonaktif" <?= $tagihan['status'] == 'nonaktif' ? 'checked' : '' ?>>
                                    <label for="status-nonaktif" class="toggle-option inactive-option">Nonaktif</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Perubahan
                            </button>
                            <a href="kelola_tagihan.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
    
    <script>
        // Tambahkan JavaScript untuk menangani form submission
        document.getElementById('editTagihanForm').addEventListener('submit', function(e) {
            // Tampilkan loading overlay
            document.getElementById('loadingOverlay').classList.add('active');
            
            // Tambahkan delay kecil untuk memastikan loading overlay muncul
            setTimeout(() => {
                // Form akan tetap disubmit secara normal
                // Ini hanya untuk memberikan feedback visual kepada pengguna
            }, 100);
        });
    </script>
</body>
</html>