<?php
session_start();
require_once '../config/koneksi.php';
require_once '../config/functions.php';

requireRole(['admin']); // batasi hanya admin

$page_title = "Keamanan Sistem";

/* =========================
   Utilities pengaturan
   ========================= */
function settings_init(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            `key`   VARCHAR(100) PRIMARY KEY,
            `value` TEXT NULL,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}
function setting_get(PDO $pdo, string $key, $default = null) {
    $q = $pdo->prepare("SELECT `value` FROM app_settings WHERE `key` = ?");
    $q->execute([$key]);
    $val = $q->fetchColumn();
    if ($val === false) return $default;
    return $val;
}
function setting_set(PDO $pdo, string $key, $value): void {
    $q = $pdo->prepare("
        INSERT INTO app_settings (`key`,`value`,`created_at`,`updated_at`)
        VALUES (:k,:v,NOW(),NOW())
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()
    ");
    $q->execute([':k'=>$key, ':v'=>$value]);
}

/* =========================
   CSRF
   ========================= */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_verify(?string $t): bool {
    return is_string($t) && hash_equals($_SESSION['csrf_token'] ?? '', $t);
}

/* =========================
   Init + baca pengaturan
   ========================= */
settings_init($pdo);

$https_enforced          = (int) setting_get($pdo, 'security_https', 1);           // default ON
$trans_password_required = (int) setting_get($pdo, 'security_txn_password', 1);    // default ON
$activity_log_enabled    = (int) setting_get($pdo, 'security_activity_log', 1);    // default ON

/* =========================
   Paksa HTTPS (jika aktif)
   ========================= */
if ($https_enforced) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!$is_https) {
        // redirect ke https
        $host = $_SERVER['HTTP_HOST'];
        $uri  = $_SERVER['REQUEST_URI'];
        header("Location: https://{$host}{$uri}", true, 301);
        exit();
    }

    // kirim HSTS (1 tahun, include subdomain)
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

/* =========================
   Handle POST (simpan)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        $_SESSION['error'] = "Sesi tidak valid (CSRF). Silakan muat ulang halaman.";
        header("Location: keamanan.php");
        exit();
    }

    $https               = isset($_POST['https']) ? 1 : 0;
    $password_transaksi  = isset($_POST['password_transaksi']) ? 1 : 0;
    $log_aktivitas       = isset($_POST['log_aktivitas']) ? 1 : 0;

    try {
        setting_set($pdo, 'security_https',          $https);
        setting_set($pdo, 'security_txn_password',   $password_transaksi);
        setting_set($pdo, 'security_activity_log',   $log_aktivitas);

        // Optional: catat log perubahan (jika log diaktifkan)
        if ($activity_log_enabled) {
            $u = $_SESSION['user']['id'] ?? null;
            $detail = "Ubah pengaturan keamanan: https={$https}, txn_pwd={$password_transaksi}, log={$log_aktivitas}";
            logActivity($pdo, (int)$u, $detail);
        }

        $_SESSION['success'] = "Pengaturan keamanan berhasil diperbarui!";
    } catch (Throwable $e) {
        $_SESSION['error'] = "Gagal menyimpan pengaturan: " . $e->getMessage();
    }

    header("Location: keamanan.php");
    exit();
}

/* =========================
   Ambil log aktivitas
   ========================= */
$stmt = $pdo->query("
    SELECT l.*, u.username 
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 50
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   View
   ========================= */
require_once '../includes/header.php';
require_once '../includes/navbar.php';
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="../assets/css/style_keamanan.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
</head>
<body class="dashboard-body">

  <!-- Sidebar -->
  <?php include_once '../includes/sidebar.php'; ?>

  <!-- Main -->
  <div class="main-content-wrapper">
    <div class="content-container">
      <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-shield-halved"></i> Keamanan Sistem</h1>
        <div class="page-meta">
          <i class="fa-regular fa-calendar"></i>
          <span><?= date('d F Y') ?></span>
        </div>
      </div>

      <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error">
          <i class="fa-solid fa-circle-exclamation"></i>
          <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
          <i class="fa-solid fa-circle-check"></i>
          <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
        </div>
      <?php endif; ?>

      <div class="security-grid">
        <!-- Settings -->
        <div class="card settings-card">
          <h2 class="card-title"><i class="fa-solid fa-gear"></i> Pengaturan Keamanan</h2>
          <form method="POST" class="settings-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <label class="setting-row">
              <div class="setting-info">
                <div class="setting-title">Wajib HTTPS</div>
                <div class="setting-desc">Memaksa semua koneksi memakai protokol HTTPS yang aman.</div>
              </div>
              <div class="setting-toggle">
                <input type="checkbox" id="https" name="https" <?= $https_enforced ? 'checked' : '' ?> />
                <span class="switch"></span>
              </div>
            </label>

            <label class="setting-row">
              <div class="setting-info">
                <div class="setting-title">Password Transaksi</div>
                <div class="setting-desc">Minta password tambahan saat transaksi penting (mis. verifikasi/ubah data sensitif).</div>
              </div>
              <div class="setting-toggle">
                <input type="checkbox" id="password_transaksi" name="password_transaksi" <?= $trans_password_required ? 'checked' : '' ?> />
                <span class="switch"></span>
              </div>
            </label>

            <label class="setting-row">
              <div class="setting-info">
                <div class="setting-title">Log Aktivitas</div>
                <div class="setting-desc">Catat semua aktivitas penting pengguna ke tabel <code>logs</code>.</div>
              </div>
              <div class="setting-toggle">
                <input type="checkbox" id="log_aktivitas" name="log_aktivitas" <?= $activity_log_enabled ? 'checked' : '' ?> />
                <span class="switch"></span>
              </div>
            </label>

            <button type="submit" class="btn btn-primary">
              <i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan
            </button>
          </form>
        </div>

        <!-- Logs -->
        <div class="card logs-card">
          <h2 class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Log Aktivitas Terakhir</h2>
          <div class="logs-list">
            <?php if (empty($logs)): ?>
              <div class="empty">
                <i class="fa-regular fa-note-sticky"></i>
                <p>Belum ada data log untuk ditampilkan.</p>
              </div>
            <?php else: ?>
              <?php foreach ($logs as $log): ?>
                <div class="log-item">
                  <div class="log-head">
                    <div class="log-user">
                      <i class="fa-regular fa-user"></i>
                      <strong><?= htmlspecialchars($log['username'] ?? 'System') ?></strong>
                    </div>
                    <div class="log-time"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></div>
                  </div>
                  <div class="log-body"><?= htmlspecialchars($log['activity']) ?></div>
                  <div class="log-foot">
                    <span><i class="fa-solid fa-network-wired"></i> <?= htmlspecialchars($log['ip_address']) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>

  <!-- Tidak perlu JS tambahan; toggle murni native -->
</body>
</html>
