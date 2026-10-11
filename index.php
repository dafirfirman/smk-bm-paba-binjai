<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/functions.php';

if (isset($_SESSION['user'])) {
    redirectBasedOnRole($_SESSION['user']['role']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = cleanInput($_POST['username']);
    $password = $_POST['password'];
    $role = cleanInput($_POST['role']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Simpan data user di session
        $_SESSION['user'] = $user;

        // Ambil permission role dari database
        $stmtPerm = $pdo->prepare("SELECT permission FROM role_permissions WHERE role = ?");
        $stmtPerm->execute([$user['role']]);
        $permissions = $stmtPerm->fetchAll(PDO::FETCH_COLUMN, 0);

        // Simpan permission di session
        $_SESSION['user']['permissions'] = $permissions;

        if ($user['role'] == 'siswa') {
            $stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
            $stmtSiswa->execute([$user['id']]);
            $_SESSION['siswa'] = $stmtSiswa->fetch();
        }
        
        // Log aktivitas login
        logActivity($user['id'], 'Login ke sistem');
        
        // Set cookie jika "Ingat saya" dicentang
        if (isset($_POST['remember'])) {
            setcookie('remember_user', $user['id'], time() + (86400 * 30), "/");
        }
        
        redirectBasedOnRole($user['role']);
    } else {
        $error = "Username, password, atau role tidak valid";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Sistem Pembayaran Uang Sekolah SMK BM PABA BINJAI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/style_login.css" />
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo-container">
                <img src="assets/images/logo_smk_binjai.jpg" alt="Logo SMK BM PABA BINJAI" class="logo" />
            </div>
            <h1>Sistem Informasi Pembayaran Uang Sekolah</h1>
            <h2>SMK BM PABA BINJAI</h2>
            <p class="address">Jl. Padang Sidempuan, Rambung Barat, Kota Binjai</p>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="login-form" autocomplete="off">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username</label>
                <input type="text" id="username" name="username" required placeholder="Masukkan username" />
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <div class="password-input">
                    <input type="password" id="password" name="password" required placeholder="Masukkan password" />
                    <span class="toggle-password" onclick="togglePassword()" title="Tampilkan/Sembunyikan Password">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <div class="form-group">
                <label for="role"><i class="fas fa-user-tag"></i> Role</label>
                <select id="role" name="role" required>
                    <option value="" disabled selected>Pilih Role</option>
                    <option value="admin">Admin</option>
                    <option value="bendahara">Bendahara</option>
                    <option value="siswa">Siswa</option>
                </select>
            </div>
            
            <div class="form-group remember">
                <input type="checkbox" id="remember" name="remember" />
                <label for="remember">Ingat saya</label>
            </div>
            
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
            
            <div class="forgot-password">
                <a href="forgot_password.php">Lupa Password?</a>
            </div>
            
            <div class="register-link">
                Belum punya akun? <a href="register.php">Daftar akun</a>
            </div>
        </form>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.querySelector('.toggle-password i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>