<?php
session_start();
require_once 'config/koneksi.php';
require_once 'config/functions.php';

$error = '';
$message = '';
$showForm = false;

$token = $_GET['token'] ?? '';

if (!$token) {
    $error = "Token reset password tidak ditemukan.";
} else {
    $stmt = $pdo->prepare("SELECT pr.user_id, u.username FROM password_resets pr JOIN users u ON pr.user_id = u.id WHERE pr.token = ? AND pr.expires_at > NOW()");
    $stmt->execute([$token]);
    $data = $stmt->fetch();

    if (!$data) {
        $error = "Token tidak valid atau sudah kadaluarsa.";
    } else {
        $showForm = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';

            if (strlen($password) < 6) {
                $error = "Password minimal 6 karakter.";
            } elseif ($password !== $password_confirm) {
                $error = "Password dan konfirmasi password tidak sama.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$password_hash, $data['user_id']]);

                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$data['user_id']]);

                $message = "Password berhasil diubah. Silakan <a href='login.php'>login</a> dengan password baru Anda.";
                $showForm = false;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Reset Password - SMK BM PABA BINJAI</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/lupa_pw.css" />
</head>
<body>
    <div class="forgot-container">
        <h1>Reset Password</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($showForm): ?>
            <form method="POST" autocomplete="off">
                <input type="password" name="password" placeholder="Password baru (min 6 karakter)" required />
                <input type="password" name="password_confirm" placeholder="Konfirmasi password baru" required />
                <button type="submit">Ubah Password</button>
            </form>
        <?php else: ?>
            <a href="login.php" class="back-link">&larr; Kembali ke Login</a>
        <?php endif; ?>
    </div>
</body>
</html>