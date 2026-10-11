<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Proses backup manual
if (isset($_GET['backup'])) {
    $backup_type = cleanInput($_GET['backup']);
    $backup_file = 'backup_' . date('Ymd_His') . '.zip';
    
    $stmt = $pdo->prepare("INSERT INTO backup_config (lokasi_backup, jadwal, terakhir_backup) VALUES (?, 'manual', NOW())");
    $stmt->execute([$backup_file]);
    
    $_SESSION['success'] = "Backup berhasil dibuat: $backup_file";
    header("Location: backup.php");
    exit();
}

// Update jadwal backup
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $jadwal = cleanInput($_POST['jadwal']);
    $lokasi = cleanInput($_POST['lokasi']);
    
    $stmt = $pdo->prepare("UPDATE backup_config SET jadwal = ?, lokasi_backup = ? WHERE id = 1");
    $stmt->execute([$jadwal, $lokasi]);
    
    $_SESSION['success'] = "Jadwal backup berhasil diperbarui!";
    header("Location: backup.php");
    exit();
}

// Ambil info backup
$stmt = $pdo->query("SELECT * FROM backup_config ORDER BY terakhir_backup DESC LIMIT 1");
$backup_config = $stmt->fetch();

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Backup & Pemeliharaan</title>
  <link rel="stylesheet" href="../assets/css/style_backup.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">

  <!-- Sidebar -->
  <?php include_once '../includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content-wrapper">
    <div class="content-container">
      <div class="dashboard-header">
        <h1 class="dashboard-title">Backup & Pemeliharaan</h1>
      </div>

      <div class="backup-management">
        <!-- Backup Manual -->
        <div class="backup-card">
          <h2 class="backup-title">
            <img src="../assets/icons/history.png" class="icon-sm" alt="History"> Backup Manual
          </h2>
          <div class="action-buttons">
            <a href="backup.php?backup=full" class="btn-backup">
              <div class="backup-icon">
                <img src="../assets/icons/database.png" class="icon-sm" alt="Database">
              </div>
              <div class="backup-info">
                <h3>Backup Database</h3>
                <p>Backup seluruh data database</p>
              </div>
            </a>

            <a href="backup.php?backup=uploads" class="btn-backup">
              <div class="backup-icon">
                <img src="../assets/icons/folder.png" class="icon-sm" alt="Folder">
              </div>
              <div class="backup-info">
                <h3>Backup Uploads</h3>
                <p>Backup semua file yang diunggah</p>
              </div>
            </a>

            <a href="backup.php?backup=full" class="btn-backup">
              <div class="backup-icon">
                <img src="../assets/icons/archive.png" class="icon-sm" alt="Archive">
              </div>
              <div class="backup-info">
                <h3>Backup Lengkap</h3>
                <p>Backup database dan file</p>
              </div>
            </a>
          </div>

          <!-- Info Backup Terakhir -->
          <div class="last-backup">
            <h3>
              <img src="../assets/icons/clock.png" class="icon-sm" alt="Clock"> Backup Terakhir
            </h3>
            <div class="backup-details">
              <?php if ($backup_config): ?>
                <div class="detail-item">
                  <span class="detail-label">File:</span>
                  <span class="detail-value"><?= $backup_config['lokasi_backup'] ?></span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Waktu:</span>
                  <span class="detail-value"><?= date('d M Y H:i', strtotime($backup_config['terakhir_backup'])) ?></span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Jadwal:</span>
                  <span class="detail-value"><?= ucfirst($backup_config['jadwal']) ?></span>
                </div>
              <?php else: ?>
                <div class="no-backup">
                  <img src="../assets/icons/exclamation.png" class="icon-sm" alt="Info">
                  <p>Belum ada backup yang dibuat</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Pengaturan Backup -->
        <div class="settings-card">
          <h2 class="settings-title">
            <img src="../assets/icons/settings.png" class="icon-sm" alt="Settings"> Pengaturan Backup
          </h2>
          <form method="POST" class="settings-form">
            <div class="form-group">
              <label for="jadwal">
                <img src="../assets/icons/calendar.png" class="icon-sm" alt="Calendar"> Jadwal Backup
              </label>
              <select id="jadwal" name="jadwal" class="custom-select" required>
                <option value="harian" <?= $backup_config && $backup_config['jadwal'] == 'harian' ? 'selected' : '' ?>>Harian</option>
                <option value="mingguan" <?= $backup_config && $backup_config['jadwal'] == 'mingguan' ? 'selected' : '' ?>>Mingguan</option>
                <option value="bulanan" <?= $backup_config && $backup_config['jadwal'] == 'bulanan' ? 'selected' : '' ?>>Bulanan</option>
              </select>
            </div>

            <div class="form-group">
              <label for="lokasi">
                <img src="../assets/icons/cloud.png" class="icon-sm" alt="Cloud"> Lokasi Backup
              </label>
              <select id="lokasi" name="lokasi" class="custom-select" required>
                <option value="google_drive" <?= $backup_config && $backup_config['lokasi_backup'] == 'google_drive' ? 'selected' : '' ?>>Google Drive</option>
                <option value="server_lokal" <?= $backup_config && $backup_config['lokasi_backup'] == 'server_lokal' ? 'selected' : '' ?>>Server Lokal</option>
              </select>
            </div>

            <button type="submit" class="btn-save">
              <img src="../assets/icons/save.png" class="icon-sm" alt="Save"> Simpan Pengaturan
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <?php include_once '../includes/footer.php'; ?>
</body>
</html>
