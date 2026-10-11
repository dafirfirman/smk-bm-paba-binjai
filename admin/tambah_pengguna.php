<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';
requireRole(['admin']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = cleanInput($_POST['username']);
    $password = password_hash(cleanInput($_POST['password']), PASSWORD_DEFAULT);
    $role = cleanInput($_POST['role']);
    $email = cleanInput($_POST['email']);
    $no_telepon = cleanInput($_POST['no_telepon']);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, no_telepon) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $password, $role, $email, $no_telepon]);

    $_SESSION['success'] = "Pengguna berhasil ditambahkno_teleponan!";
    header("Location: manajemen_pengguna.php");
    exit();
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<div class="container">
    <h1>Tambah Pengguna Baru</h1>
    
    <form method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <option value="admin">Admin</option>
                <option value="bendahara">Bendahara</option>
                <option value="siswa">Siswa</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="no_telepon">No Telepon</label>
            <input type="tel" id="no_telepon" name="no_telepon" required>
        </div>
        
        <button type="submit" class="btn-save">Simpan</button>
    </form>
</div>

<?php include_once '../includes/footer.php'; ?>