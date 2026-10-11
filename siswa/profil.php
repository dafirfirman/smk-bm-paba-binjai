<?php
// Mulai session di awal file
session_start();

require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Konfigurasi default
$default_avatar = '../assets/images/student.png'; // Pastikan file ini ada
$allowed_image_types = ['jpg', 'jpeg', 'png'];
$max_file_size = 2 * 1024 * 1024; // 2MB

// Pastikan user sudah login dan berperan sebagai siswa
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

if ($_SESSION['user']['role'] !== 'siswa') {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

// Fungsi untuk validasi path foto profil
function validateProfilePhotoPath($photoPath, $defaultAvatar) {
    if (empty($photoPath)) {
        return $defaultAvatar;
    }
    
    // Normalisasi path
    $normalizedPath = str_replace(['../', './'], '', $photoPath);
    $normalizedPath = ltrim($normalizedPath, '/');
    
    // Pastikan path mengarah ke direktori upload siswa
    if (strpos($normalizedPath, 'uploads/siswa/') !== 0) {
        $normalizedPath = 'uploads/siswa/' . basename($normalizedPath);
    }
    
    // Buat full path
    $fullPath = '../' . $normalizedPath;
    
    // Verifikasi file
    if (file_exists($fullPath) && is_file($fullPath)) {
        return $fullPath;
    }
    
    error_log("Foto profil tidak ditemukan: " . $fullPath);
    return $defaultAvatar;
}

// Ambil data siswa dari session atau database
if (!isset($_SESSION['siswa'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $siswa = $stmt->fetch();
        
        if (!$siswa) {
            $_SESSION['error'] = "Data siswa tidak ditemukan";
            header("Location: dashboard.php");
            exit();
        }
        
        $_SESSION['siswa'] = $siswa;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal mengambil data siswa: " . $e->getMessage();
        header("Location: dashboard.php");
        exit();
    }
}

$siswa = $_SESSION['siswa'];
$foto_profil = validateProfilePhotoPath($siswa['foto'] ?? '', $default_avatar);
$is_default_avatar = ($foto_profil === $default_avatar);

// Proses update profil jika ada form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle update password jika ada
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validasi password
        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Konfirmasi password tidak sesuai";
            header("Location: profil.php");
            exit();
        }
        
        if (strlen($new_password) < 6) {
            $_SESSION['error'] = "Password baru minimal 6 karakter";
            header("Location: profil.php");
            exit();
        }
        
        // Verifikasi password saat ini
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user']['id']]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $_SESSION['error'] = "Password saat ini salah";
                header("Location: profil.php");
                exit();
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['user']['id']]);
            
            $_SESSION['success'] = "Password berhasil diperbarui!";
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Gagal memperbarui password: " . $e->getMessage();
            header("Location: profil.php");
            exit();
        }
    }
    
    // Handle file upload foto profil
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../uploads/siswa/";
        
        // Validasi ekstensi file
        $file_ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_image_types)) {
            $_SESSION['error'] = "Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.";
            header("Location: profil.php");
            exit();
        }
        
        // Validasi ukuran file
        if ($_FILES['foto_profil']['size'] > $max_file_size) {
            $_SESSION['error'] = "Ukuran file terlalu besar. Maksimal 2MB.";
            header("Location: profil.php");
            exit();
        }
        
        // Buat direktori jika belum ada
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                $_SESSION['error'] = "Gagal membuat direktori upload";
                header("Location: profil.php");
                exit();
            }
        }
        
        // Generate nama file baru yang unik
        $new_filename = 'siswa_' . $siswa['id'] . '_' . time() . '.' . $file_ext;
        $target_file = $target_dir . $new_filename;
        
        // Pindahkan file upload
        if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_file)) {
            // Hapus foto lama jika ada dan bukan default avatar
            if (!empty($siswa['foto']) && file_exists($siswa['foto']) && $siswa['foto'] !== $default_avatar) {
                @unlink($siswa['foto']);
            }
            
            // Update database
            try {
                $stmt = $pdo->prepare("UPDATE siswa SET foto = ? WHERE id = ?");
                if ($stmt->execute([$target_file, $siswa['id']])) {
                    // Update session
                    $_SESSION['siswa']['foto'] = $target_file;
                    $foto_profil = $target_file;
                    $is_default_avatar = false;
                    $_SESSION['success'] = "Foto profil berhasil diperbarui!";
                } else {
                    $_SESSION['error'] = "Gagal menyimpan path foto ke database";
                    @unlink($target_file); // Hapus file yang sudah diupload
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Database error: " . $e->getMessage();
                @unlink($target_file);
            }
        } else {
            $_SESSION['error'] = "Gagal mengupload foto profil";
        }
    }
    
    // Update data lainnya yang diizinkan
    $email = cleanInput($_POST['email']);
    $no_telepon = cleanInput($_POST['no_telepon']);
    $alamat = cleanInput($_POST['alamat']);
    
    try {
        // Update data siswa
        $stmt = $pdo->prepare("UPDATE siswa SET email = ?, no_telepon = ?, alamat = ? WHERE user_id = ?");
        $stmt->execute([$email, $no_telepon, $alamat, $_SESSION['user']['id']]);
        
        // Update data user (email)
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$email, $_SESSION['user']['id']]);
        
        // Update data di session
        $_SESSION['siswa']['email'] = $email;
        $_SESSION['siswa']['no_telepon'] = $no_telepon;
        $_SESSION['siswa']['alamat'] = $alamat;
        $_SESSION['user']['email'] = $email;
        
        $_SESSION['success'] = "Profil berhasil diperbarui!";
        header("Location: profil.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal memperbarui profil: " . $e->getMessage();
        header("Location: profil.php");
        exit();
    }
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Siswa - SMK BM PABA Binjai</title>
    <link rel="stylesheet" href="../assets/css/style_profil_siswa.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
    function previewImage(event) {
        const preview = document.getElementById('preview-foto');
        const fileInput = event.target;
        const file = fileInput.files[0];
        const fileInfo = document.querySelector('.file-info');
        
        // Validasi ukuran file
        const maxSize = fileInput.getAttribute('data-max-size');
        if (file && file.size > maxSize) {
            alert('Ukuran file terlalu besar. Maksimal 2MB.');
            fileInput.value = '';
            return;
        }
        
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
                document.querySelector('.default-avatar').style.display = 'none';
                fileInfo.textContent = file.name + ' (' + (file.size/1024/1024).toFixed(2) + 'MB)';
            }
            reader.readAsDataURL(file);
        }
    }
    
    function togglePasswordForm() {
        const form = document.getElementById('password-form');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
    </script>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include_once '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="profile-container">
            <h1>Profil Siswa</h1>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <div class="profile-content">
                <div class="profile-info">
                    <div class="profile-header">
                        <div class="profile-photo-container">
                            <img src="<?= htmlspecialchars($foto_profil) ?>" alt="Foto Profil" id="preview-foto" style="<?= $is_default_avatar ? 'display:none;' : '' ?>">
                            <i class="fas fa-user-circle default-avatar" style="<?= !$is_default_avatar ? 'display:none;' : '' ?>"></i>
                            <div class="avatar-overlay">Klik untuk mengubah</div>
                        </div>
                        <h2><?= htmlspecialchars($siswa['nama_lengkap']) ?></h2>
                        <p>NISN: <?= htmlspecialchars($siswa['nisn']) ?></p>
                        <p>Kelas: <?= htmlspecialchars($siswa['kelas']) ?> | Jurusan: <?= htmlspecialchars($siswa['jurusan']) ?></p>
                    </div>
                    
                    <div class="profile-details">
                        <h3>Informasi Pribadi</h3>
                        <p><i class="fas fa-birthday-cake"></i> 
                            <?= htmlspecialchars($siswa['tempat_lahir']) ?>, 
                            <?= date('d F Y', strtotime($siswa['tanggal_lahir'])) ?>
                        </p>
                        <p><i class="fas fa-venus-mars"></i> 
                            <?= $siswa['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?>
                        </p>
                        
                        <h3>Kontak</h3>
                        <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($siswa['email']) ?></p>
                        <p><i class="fas fa-phone"></i> <?= htmlspecialchars($siswa['no_telepon']) ?></p>
                        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($siswa['alamat']) ?></p>
                        
                        <?php if (!empty($siswa['prestasi'])): ?>
                            <h3>Prestasi</h3>
                            <p><?= htmlspecialchars($siswa['prestasi']) ?></p>
                        <?php endif; ?>
                        
                        <h3>Informasi Orang Tua/Wali</h3>
                        <p><i class="fas fa-user"></i> Ayah: <?= htmlspecialchars($siswa['nama_ayah'] ?? '-') ?></p>
                        <p><i class="fas fa-user"></i> Ibu: <?= htmlspecialchars($siswa['nama_ibu'] ?? '-') ?></p>
                        <p><i class="fas fa-phone"></i> Telp Orang Tua: <?= htmlspecialchars($siswa['nomor_telepon_orang_tua'] ?? '-') ?></p>
                        <?php if (!empty($siswa['nama_wali'])): ?>
                            <p><i class="fas fa-user"></i> Wali: <?= htmlspecialchars($siswa['nama_wali']) ?></p>
                            <p><i class="fas fa-phone"></i> Telp Wali: <?= htmlspecialchars($siswa['no_telepon_wali'] ?? '-') ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Form Ubah Password -->
                    <div class="password-form">
                        <h3>Ubah Password</h3>
                        <button type="button" class="btn-change-password" onclick="togglePasswordForm()">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                        
                        <form id="password-form" method="POST" style="display: none; margin-top: 15px;">
                            <div class="form-group">
                                <label for="current_password">Password Saat Ini</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">Password Baru</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Konfirmasi Password Baru</label>
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                            
                            <button type="submit" class="btn-change-password">
                                <i class="fas fa-save"></i> Simpan Password
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="profile-edit">
                    <h2>Edit Profil</h2>
                    <p class="edit-note">Hanya data tertentu yang dapat diubah. Untuk perubahan data pribadi lainnya, hubungi administrator.</p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Foto Profil</label>
                            <div class="upload-container">
                                <label for="foto_profil" class="upload-btn">
                                    <i class="fas fa-camera"></i> Pilih Foto
                                </label>
                                <input type="file" id="foto_profil" name="foto_profil" 
                                       accept="image/*" onchange="previewImage(event)" 
                                       style="display: none;" data-max-size="<?= $max_file_size ?>">
                                <span class="file-info">Format: JPG, JPEG, PNG (max 2MB)</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?= htmlspecialchars($siswa['email']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="no_telepon">No Telepon</label>
                            <input type="tel" id="no_telepon" name="no_telepon" 
                                   value="<?= htmlspecialchars($siswa['no_telepon']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="alamat">Alamat</label>
                            <textarea id="alamat" name="alamat" rows="3" required><?= htmlspecialchars($siswa['alamat']) ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn-save">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>