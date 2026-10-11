<?php
session_start();
require_once '../config/koneksi.php';
require_once '../config/functions.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$current_role = $_SESSION['user']['role'];
$allowed_roles = ['admin', 'bendahara', 'siswa'];

if (!in_array($current_role, $allowed_roles)) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

// Log aktivitas
if (!isset($_SESSION['last_activity'])) {
    logActivity($_SESSION['user']['id'], 'Akses sistem');
}

$_SESSION['last_activity'] = time();
?>