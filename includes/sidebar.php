<?php
session_start();
$user_role = $_SESSION['user']['role'] ?? null;
$user_name = $_SESSION['user']['name'] ?? 'Pengguna';
$user_permissions = $_SESSION['user']['permissions'] ?? [];

if (!$user_role) {
    header("Location: ../index.php");
    exit();
}

// Definisikan menu lengkap dengan permission yang dibutuhkan di index ke-3
$menus = [
    'admin' => [
        ['dashboard.php', 'fas fa-tachometer-alt', 'Dashboard', 'view_dashboard'],
        ['manajemen_pengguna.php', 'fas fa-users-cog', 'Manajemen Pengguna', 'manage_users'],
        ['hak_akses.php', 'fas fa-user-lock', 'Hak Akses & Otorisasi', 'manage_users'],
        ['konfigurasi_transaksi.php', 'fas fa-credit-card', 'Konfigurasi Metode Transaksi', 'manage_all_bills'],
        ['keamanan.php', 'fas fa-shield-alt', 'Keamanan Sistem', 'manage_users'],
        ['backup.php', 'fas fa-database', 'Backup', 'manage_users'],
        ['statistik.php', 'fas fa-chart-bar', 'Statistik', 'view_statistics'],
        ['../logout.php', 'fas fa-sign-out-alt', 'Logout', 'logout']
    ],
    'bendahara' => [
        ['dashboard.php', 'fas fa-tachometer-alt', 'Dashboard', 'view_dashboard'],
        ['kelola_tagihan.php', 'fas fa-file-invoice', 'Kelola Tagihan', 'manage_bills'],
        ['verifikasi_pembayaran.php', 'fas fa-check-circle', 'Verifikasi Pembayaran', 'verify_payment'],
        ['kwitansi.php', 'fas fa-receipt', 'Cetak & Kirim Kwitansi', 'print_receipt'],
        ['laporan_keuangan.php', 'fas fa-chart-line', 'Laporan Keuangan', 'view_finance_report'],
        ['#', '', 'Filter & Pencarian Cerdas', 'menu-section'],
        ['notifikasi.php', 'fas fa-bell', 'Notifikasi Cerdas', 'view_dashboard'],
        ['log_aktivitas.php', 'fas fa-history', 'Log Aktivitas', 'view_finance_report'],
        ['../logout.php', 'fas fa-sign-out-alt', 'Logout', 'logout']
    ],
    'siswa' => [
        ['dashboard.php', 'fas fa-home', 'Dashboard', 'view_dashboard'],
        ['profil.php', 'fas fa-user', 'Profil', 'view_profile'],
        ['tagihan.php', 'fas fa-credit-card', 'Pembayaran', 'view_bills'],
        ['bukti_kwitansi.php', 'fas fa-file-invoice-dollar', 'Bukti Kwitansi', 'view_receipt'],
        ['../logout.php', 'fas fa-sign-out-alt', 'Logout', 'logout']
    ]
];

if (!array_key_exists($user_role, $menus)) {
    echo "<div class='error-message'>Role tidak valid</div>";
    exit();
}

$user_menu = $menus[$user_role];
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Sidebar Navigation</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/style_sidebar.css" />
</head>
<body>
    <!-- Sidebar Overlay (Mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" tabindex="-1" aria-hidden="true"></div>
    
    <!-- Sidebar -->
    <aside class="dashboard-sidebar" id="dashboardSidebar" role="navigation" aria-label="Sidebar">
        <!-- Logo dan Nama Sekolah -->
        <div class="sidebar-top">
            <div class="sidebar-actions">
                <button class="icon-btn" id="sidebarClose" aria-label="Close sidebar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Info Pengguna -->
        <div class="sidebar-user">
            <div class="avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
            <div class="user-meta">
                <span class="user-name"><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="user-role"><?php echo htmlspecialchars(ucfirst($user_role), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
        
        <!-- Menu Navigasi -->
        <ul class="sidebar-menu" role="list">
            <?php foreach ($user_menu as $menu_item):
                $link = $menu_item[0];
                $icon = $menu_item[1];
                $title = $menu_item[2];
                $permission = $menu_item[3] ?? '';
                
                // Tampilkan section menu tanpa cek permission
                if ($permission === 'menu-section'): ?>
                    <li class="menu-section" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
                        <span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                <?php
                continue;
                endif;
                
                // Tampilkan menu logout tanpa cek permission
                if ($permission === 'logout') {
                    $is_active = ($current_page === basename($link)) ? 'active' : '';
                    ?>
                    <li class="<?php echo $is_active; ?>">
                        <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" class="menu-link" tabindex="0" aria-current="<?php echo $is_active ? 'page' : 'false'; ?>">
                            <i class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <span class="menu-text"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    </li>
                    <?php
                    continue;
                }
                
                // Cek apakah user punya permission untuk menu ini
                if (in_array($permission, $user_permissions)):
                    $is_active = ($current_page === basename($link)) ? 'active' : '';
                    ?>
                    <li class="<?php echo $is_active; ?>">
                        <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" class="menu-link" tabindex="0" aria-current="<?php echo $is_active ? 'page' : 'false'; ?>">
                            <i class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <span class="menu-text"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
        
        <!-- Footer Sidebar -->
        <div class="sidebar-footer">
            <small>&copy; 2025 SMK BM PABA BINJAI</small>
        </div>
    </aside>
    
    <!-- Tombol toggle sidebar - HANYA DITAMPILKAN DI MOBILE -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-controls="dashboardSidebar" aria-label="Toggle sidebar" aria-expanded="false">
        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <div class="close-icon">
            <i class="fas fa-times"></i>
        </div>
    </button>
    
    <script src="../assets/js/sidebar.js"></script>
</body>
</html>