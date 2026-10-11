<?php
/**
 * FILE: config/functions.php
 * Kumpulan fungsi helper untuk sistem pembayaran sekolah
 */

// Pastikan session tidak dimulai ganda
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Mengubah angka menjadi terbilang (contoh: 100 -> "seratus")
 */
function terbilang($angka) {
    $angka = abs($angka);
    $bilangan = array(
        '', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'
    );
    
    $terbilang = '';
    if ($angka < 12) {
        $terbilang = ' ' . $bilangan[$angka];
    } elseif ($angka < 20) {
        $terbilang = terbilang($angka - 10) . ' belas';
    } elseif ($angka < 100) {
        $terbilang = terbilang($angka / 10) . ' puluh' . terbilang($angka % 10);
    } elseif ($angka < 200) {
        $terbilang = ' seratus' . terbilang($angka - 100);
    } elseif ($angka < 1000) {
        $terbilang = terbilang($angka / 100) . ' ratus' . terbilang($angka % 100);
    } elseif ($angka < 2000) {
        $terbilang = ' seribu' . terbilang($angka - 1000);
    } elseif ($angka < 1000000) {
        $terbilang = terbilang($angka / 1000) . ' ribu' . terbilang($angka % 1000);
    } elseif ($angka < 1000000000) {
        $terbilang = terbilang($angka / 1000000) . ' juta' . terbilang($angka % 1000000);
    }
    
    return trim($terbilang);
}

/**
 * Mengekspor data ke format Excel
 */
function exportToExcel($data, $filename = 'laporan') {
    header("Content-type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=$filename.xls");
    
    echo '<table border="1">';
    // Header
    echo '<tr>';
    foreach ($data[0] as $key => $value) {
        echo '<th>' . ucfirst(str_replace('_', ' ', $key)) . '</th>';
    }
    echo '</tr>';
    
    // Data
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $value) {
            echo '<td>' . $value . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit();
}

/**
 * Membersihkan input dari user
 */
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Redirect berdasarkan role user
 */
function redirectBasedOnRole($role) {
    switch ($role) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'bendahara':
            header("Location: bendahara/dashboard.php");
            break;
        case 'siswa':
            header("Location: siswa/dashboard.php");
            break;
        default:
            header("Location: index.php");
    }
    exit();
}

/**
 * Mencatat aktivitas ke log sistem - DIPERBAIKI
 */
function logActivity($pdo, $user_id, $activity) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, activity, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $activity, $ip_address, $user_agent]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Memformat angka ke format rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Validasi login user
 */
function validateLogin($username, $password, $role) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
    $stmt->execute([$username, $role]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session user dengan struktur yang konsisten
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'no_telepon' => $user['no_telepon']
        ];
        return $user;
    }
    
    return false;
}

/**
 * Memeriksa role user yang diizinkan - DIPERBAIKI
 * @param array $allowed_roles Daftar role yang diizinkan
 */
function requireRole($allowed_roles) {
    if (!isset($_SESSION['user'])) {
        header("Location: ../login.php");
        exit();
    }
    
    if (!in_array($_SESSION['user']['role'], $allowed_roles)) {
        header("Location: ../unauthorized.php");
        exit();
    }
    
    // Set variabel session tambahan untuk kompatibilitas
    $_SESSION['username'] = $_SESSION['user']['username'] ?? null;
    $_SESSION['user_id'] = $_SESSION['user']['id'] ?? null;
    $_SESSION['role'] = $_SESSION['user']['role'] ?? null;
}

/**
 * Mengecek apakah user memiliki izin tertentu berdasarkan rolenya
 * @param string $permission Nama izin (contoh: 'create_tagihan')
 * @return bool True jika diizinkan, False jika tidak
 */
function hasPermission($permission) {
    $role = $_SESSION['user']['role'] ?? null;

    $permissions = [
        'admin' => ['create_tagihan', 'edit_tagihan', 'delete_tagihan', 'lihat_semua_data', 'atur_akses'],
        'bendahara' => ['edit_tagihan', 'lihat_semua_data', 'verifikasi_pembayaran', 'kelola_tagihan', 'lihat_laporan', 'export_laporan',' delete_tagihan'],
        'siswa' => ['lihat_pembayaran', 'upload_bukti']
    ];

    return in_array($permission, $permissions[$role] ?? []);
}

/**
 * Mendapatkan data user dari session
 */
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Fungsi baru: Validasi ID pembayaran
 */
function validatePaymentId($pdo, $payment_id, $user_id = null) {
    $payment_id = (int)$payment_id;
    if ($payment_id <= 0) {
        return ['valid' => false, 'message' => 'ID pembayaran tidak valid'];
    }
    
    // Cek apakah pembayaran ada
    $query = "SELECT id, status, siswa_id FROM pembayaran WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        return ['valid' => false, 'message' => 'Pembayaran tidak ditemukan'];
    }
    
    // Cek status
    if ($payment['status'] !== 'pending') {
        return ['valid' => false, 'message' => 'Pembayaran sudah diproses sebelumnya'];
    }
    
    return ['valid' => true, 'data' => $payment];
}

?>