<?php
session_start();

// 1. Hapus semua data session
$_SESSION = [];

// 2. Hapus cookie session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"], 
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Hancurkan session
session_destroy();

// 4. Hapus cookie remember me jika ada
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 42000, '/');
    // Hapus juga dari database jika menggunakan token
    require_once 'config/koneksi.php';
    $token = $_COOKIE['remember_user'];
    $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?")->execute([$token]);
}

// 5. Clear cache header
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Pragma: no-cache");

// 6. Redirect dengan JavaScript fallback
header("Location: index.php");

// Fallback jika header redirect gagal
echo '<script>window.location.href="index.php";</script>';
exit();
?>