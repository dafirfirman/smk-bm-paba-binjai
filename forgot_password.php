<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/functions.php';

if (isset($_SESSION['user'])) {
    redirectBasedOnRole($_SESSION['user']['role']);
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = cleanInput($_POST['username']);
    $email = cleanInput($_POST['email']);
    
    // Validasi input
    if (empty($username) || empty($email)) {
        $error = "Semua field harus diisi!";
    } else {
        // Hanya untuk role siswa
        $role = 'siswa';
        
        // Cek apakah username sesuai dengan role siswa
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
        $stmt->execute([$username, $role]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Untuk siswa, perlu cek email di tabel siswa
            $stmt = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ? AND email = ?");
            $stmt->execute([$user['id'], $email]);
            $siswa = $stmt->fetch();
            
            if ($siswa) {
                // Generate token reset password
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));
                
                // Simpan token ke database
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                if ($stmt->execute([$token, $expires, $user['id']])) {
                    // Kirim email dengan link reset (implementasi sederhana)
                    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                    
                    // Simpan dalam session untuk ditampilkan (karena mungkin tidak ada email server)
                    $_SESSION['reset_link'] = $resetLink;
                    $_SESSION['reset_email'] = $email;
                    
                    // Set pesan sukses
                    $message = "Instruksi reset password telah dikirim ke email Anda!";
                } else {
                    $error = "Terjadi kesalahan sistem. Silakan coba lagi.";
                }
            } else {
                $error = "Email tidak sesuai dengan username yang diberikan.";
            }
        } else {
            $error = "Username tidak ditemukan atau tidak terdaftar sebagai siswa.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Lupa Password - Sistem Pembayaran Uang Sekolah SMK BM PABA BINJAI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/lupa_pw.css" />
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="logo-container">
                <img src="assets/images/logo_smk_binjai.jpg" alt="Logo SMK BM PABA BINJAI" class="logo" />
            </div>
            <h1>Reset Password Siswa</h1>
            <h2>Sistem Informasi Pembayaran Uang Sekolah</h2>
            <p class="address">SMK BM PABA BINJAI - Jl. Padang Sidempuan, Rambung Barat, Kota Binjai</p>
            <p class="info-text">Fitur ini hanya tersedia untuk siswa</p>
        </div>
        
        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="alert-content">
                    <h3>Terjadi Kesalahan</h3>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
                <button class="alert-close" onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($message) && !empty($message)): ?>
            <div class="alert success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <h3>Berhasil!</h3>
                    <p><?= htmlspecialchars($message) ?></p>
                    <p class="alert-info">Silakan periksa inbox email Anda untuk instruksi reset password. Jika tidak ditemukan, cek folder spam.</p>
                </div>
                <button class="alert-close" onclick="this.parentElement.style.display='none'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="success-actions">
                <a href="login.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Kembali ke Login
                </a>
                <a href="forgot_password.php" class="btn-try-again">
                    <i class="fas fa-redo"></i> Reset Password Lainnya
                </a>
            </div>
        <?php else: ?>
            <form method="POST" class="forgot-form" autocomplete="off">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username Siswa</label>
                    <input type="text" id="username" name="username" required placeholder="Masukkan username siswa" />
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Terdaftar</label>
                    <input type="email" id="email" name="email" required placeholder="Masukkan email yang terdaftar" />
                </div>
                
                <button type="submit" class="btn-reset">
                    <i class="fas fa-key"></i> Reset Password
                </button>
                
                <div class="back-to-login">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Auto close alert setelah 5 detik
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>