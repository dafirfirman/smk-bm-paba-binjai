<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/functions.php';

if (isset($_SESSION['user'])) {
    redirectBasedOnRole($_SESSION['user']['role']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi dan sanitasi input
    $nisn = cleanInput($_POST['nisn']);
    $nama_lengkap = cleanInput($_POST['nama_lengkap']);
    $tempat_lahir = cleanInput($_POST['tempat_lahir']);
    $tanggal_lahir = cleanInput($_POST['tanggal_lahir']);
    $jenis_kelamin = cleanInput($_POST['jenis_kelamin']);
    $alamat = cleanInput($_POST['alamat']);
    $email = cleanInput($_POST['email']);
    $no_telepon = cleanInput($_POST['no_telepon']);
    $kelas = cleanInput($_POST['kelas']);
    $jurusan = cleanInput($_POST['jurusan']);
    $username = cleanInput($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $prestasi = cleanInput($_POST['prestasi']);
    $nama_ayah = cleanInput($_POST['nama_ayah']);
    $nama_ibu = cleanInput($_POST['nama_ibu']);
    $nomor_telepon_orang_tua = cleanInput($_POST['nomor_telepon_orang_tua']);
    
    // Handle file upload
    $foto = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $target_dir = "uploads/siswa/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = uniqid('siswa_', true) . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                $foto = $target_file;
            }
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert ke tabel users
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, no_telepon, created_at) VALUES (?, ?, 'siswa', ?, ?, NOW())");
        $stmt->execute([$username, $password, $email, $no_telepon]);
        $user_id = $pdo->lastInsertId();
        
        // Insert ke tabel siswa dengan data orang tua
        $stmt = $pdo->prepare("INSERT INTO siswa (user_id, nisn, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, email, no_telepon, kelas, jurusan, foto, prestasi, nama_ayah, nama_ibu, nomor_telepon_orang_tua) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id, $nisn, $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, 
            $alamat, $email, $no_telepon, $kelas, $jurusan, $foto, $prestasi,
            $nama_ayah, $nama_ibu, $nomor_telepon_orang_tua
        ]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Registrasi berhasil! Silakan login.";
        header("Location: index.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Registrasi gagal: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Siswa - SMK BM PABA BINJAI</title>
    <link rel="stylesheet" href="assets/css/style_register.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tambahan CSS untuk form orang tua */
        .form-section {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .form-section h2 {
            color: #007bff;
            margin-top: 0;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Registrasi Siswa Baru</h1>
            <p>SMK Bisnis Manajemen PABA Binjai</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="register-form">
            <div class="form-section">
                <h2><i class="fas fa-user-graduate"></i> Data Pribadi Siswa</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nisn">NISN</label>
                        <input type="text" id="nisn" name="nisn" required>
                    </div>
                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="tempat_lahir">Tempat Lahir</label>
                        <input type="text" id="tempat_lahir" name="tempat_lahir" required>
                    </div>
                    <div class="form-group">
                        <label for="tanggal_lahir">Tanggal Lahir</label>
                        <input type="date" id="tanggal_lahir" name="tanggal_lahir" required>
                    </div>
                    <div class="form-group">
                        <label for="jenis_kelamin">Jenis Kelamin</label>
                        <select id="jenis_kelamin" name="jenis_kelamin" required>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <textarea id="alamat" name="alamat" rows="3" required></textarea>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-users"></i> Data Orang Tua</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="nama_ayah">Nama Ayah</label>
                        <input type="text" id="nama_ayah" name="nama_ayah" required>
                    </div>
                    <div class="form-group">
                        <label for="nama_ibu">Nama Ibu</label>
                        <input type="text" id="nama_ibu" name="nama_ibu" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="nomor_telepon_orang_tua">Nomor Telepon Orang Tua</label>
                    <input type="tel" id="nomor_telepon_orang_tua" name="nomor_telepon_orang_tua" required>
                    <small class="text-muted">Nomor yang bisa dihubungi</small>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-phone"></i> Kontak & Pendidikan</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="no_telepon">No Telepon Siswa</label>
                        <input type="tel" id="no_telepon" name="no_telepon" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="kelas">Kelas</label>
                        <select id="kelas" name="kelas" required>
                            <option value="">Pilih Kelas</option>
                            <option value="X">X</option>
                            <option value="XI">XI</option>
                            <option value="XII">XII</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="jurusan">Jurusan</label>
                        <select id="jurusan" name="jurusan" required>
                            <option value="">Pilih Jurusan</option>
                            <option value="AKL">AKL</option>
                            <option value="OTKP">OTKP</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h2><i class="fas fa-user-circle"></i> Akun & Lainnya</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                        <small class="text-muted">Untuk login ke sistem</small>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                        <small class="text-muted">Minimal 8 karakter</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="foto">Foto Siswa</label>
                    <input type="file" id="foto" name="foto" accept="image/*">
                    <small class="text-muted">Format: JPG/PNG (max 2MB)</small>
                </div>
                
                <div class="form-group">
                    <label for="prestasi">Prestasi (Opsional)</label>
                    <textarea id="prestasi" name="prestasi" rows="2" placeholder="Prestasi yang pernah diraih"></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Daftar Sekarang
                </button>
                
                <div class="login-link">
                    <p>Sudah punya akun? <a href="index.php"><i class="fas fa-sign-in-alt"></i> Login disini</a></p>
                </div>
            </div>
        </form>
    </div>
</body>
</html>