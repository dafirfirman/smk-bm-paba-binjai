<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_role = $_SESSION['user']['role'] ?? null;
$user_name = $_SESSION['user']['nama_lengkap'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navbar Modern</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style_navbar.css">
</head>
<body>
    <nav class="admin-navbar">
        <div class="navbar-left">
            <div class="navbar-brand">
                <a href="dashboard.php">
                    <img src="../assets/images/logo_smk_binjai.jpg" alt="Logo SMK BM PABA" class="navbar-logo">
                    <div class="brand-text">
                        <span class="brand-short">SMK BM PABA Binjai</span>
                        <span class="brand-full">Sistem Pembayaran Uang Sekolah</span>
                    </div>
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['user'])): ?>
        <div class="user-profile-container">
            <div class="profile-dropdown-trigger">
                <div class="user-info">
                    <span class="user-name">
                        <?= htmlspecialchars($user_name) ?>
                    </span>
                    <span class="user-role-badge">
                        <?php 
                        if ($user_role === 'admin') {
                            echo '<i class="fas fa-user-shield"></i> Admin';
                        } elseif ($user_role === 'bendahara') {
                            echo '<i class="fas fa-calculator"></i> Bendahara';
                        } elseif ($user_role === 'siswa') {
                            echo '<i class="fas fa-user-graduate"></i> Siswa';
                        }
                        ?>
                    </span>
                </div>
                
                <div class="user-avatar">
                    <?= strtoupper(substr($user_name, 0, 1)) ?>
                </div>
                
                <i class="fas fa-chevron-down dropdown-arrow"></i>
            </div>
            
            <div class="profile-dropdown">
                <div class="profile-header">
                    <div class="user-avatar large">
                        <?= strtoupper(substr($user_name, 0, 1)) ?>
                    </div>
                    <div class="profile-header-info">
                        <div class="profile-header-name">
                            <?= htmlspecialchars($user_name) ?>
                        </div>
                        <div class="profile-header-role">
                            <?php 
                            if ($user_role === 'admin') {
                                echo 'Administrator';
                            } elseif ($user_role === 'bendahara') {
                                echo 'Bendahara';
                            } elseif ($user_role === 'siswa') {
                                echo 'Siswa';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <a href="../logout.php" class="dropdown-item logout-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </nav>

    <script>
        // Handle profile dropdown
        const profileTrigger = document.querySelector('.profile-dropdown-trigger');
        
        if (profileTrigger) {
            profileTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = document.querySelector('.profile-dropdown');
                const isVisible = dropdown.style.visibility === 'visible';
                
                if (isVisible) {
                    dropdown.style.visibility = 'hidden';
                    dropdown.style.opacity = '0';
                } else {
                    dropdown.style.visibility = 'visible';
                    dropdown.style.opacity = '1';
                }
            });
        }
        
        // Tutup dropdown ketika klik di luar
        document.addEventListener('click', function(e) {
            const profileContainer = document.querySelector('.user-profile-container');
            const dropdown = document.querySelector('.profile-dropdown');
            
            if (profileContainer && dropdown && !profileContainer.contains(e.target)) {
                dropdown.style.visibility = 'hidden';
                dropdown.style.opacity = '0';
            }
        });

        // Tutup dropdown ketika tekan Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const dropdown = document.querySelector('.profile-dropdown');
                if (dropdown) {
                    dropdown.style.visibility = 'hidden';
                    dropdown.style.opacity = '0';
                }
            }
        });
    </script>
</body>
</html>