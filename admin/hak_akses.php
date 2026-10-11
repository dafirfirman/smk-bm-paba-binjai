<?php
session_start();
require_once '../config/koneksi.php';
require_once '../config/functions.php';

requireRole(['admin']);

$page_title = "Hak Akses & Otorisasi";

/* Ikon untuk setiap permission */
$permission_icons = [
    'view_dashboard'     => 'fas fa-tachometer-alt',
    'make_payment'       => 'fas fa-money-bill-wave',
    'view_receipt'       => 'fas fa-receipt',
    'print_receipt'      => 'fas fa-print',
    'view_profile'       => 'fas fa-user',
    'view_bills'         => 'fas fa-file-invoice',
    'create_bill'        => 'fas fa-file-invoice-dollar',
    'manage_bills'       => 'fas fa-clipboard-list',
    'view_finance_report'=> 'fas fa-chart-line',
    'verify_payment'     => 'fas fa-check-circle',
    'manage_users'       => 'fas fa-users-cog',
    'manage_all_bills'   => 'fas fa-file-invoice',
    'view_statistics'    => 'fas fa-chart-pie'
];

/* Mapping halaman ↔ permission per role */
$role_pages_permissions = [
    'siswa' => [
        '../siswa/dashboard.php'       => 'view_dashboard',
        '../siswa/pembayaran.php'      => 'make_payment',
        '../siswa/bukti_kwitansi.php'  => 'view_receipt',
        '../siswa/cetak_kwitansi.php'  => 'print_receipt',
        '../siswa/profil.php'          => 'view_profile',
        '../siswa/tagihan.php'         => 'view_bills'
    ],
    'bendahara' => [
        '../bendahara/dashboard.php'          => 'view_dashboard',
        '../bendahara/buat_tagihan.php'       => 'create_bill',
        '../bendahara/kelola_tagihan.php'     => 'manage_bills',
        '../bendahara/laporan_keuangan.php'   => 'view_finance_report',
        '../bendahara/verifikasi_pembayaran.php'=> 'verify_payment',
        '../bendahara/kwitansi.php'           => 'print_receipt',
        '../bendahara/log_aktifitas.php'      => 'view_finance_report',
        '../bendahara/notifikasi.php'         => 'view_dashboard'
    ],
    'admin' => [
        '../admin/dashboard.php'              => 'view_dashboard',
        '../admin/manajemen_pengguna.php'     => 'manage_users',
        '../admin/manajemen_tagihan.php'      => 'manage_all_bills',
        '../admin/statistik.php'              => 'view_statistics',
        '../admin/keamanan.php'               => 'manage_users',
        '../admin/konfigurasi_transaksi.php'  => 'manage_all_bills',
        '../admin/metode_transaksi.php'       => 'manage_all_bills',
        '../admin/backup.php'                 => 'manage_users'
    ]
];

/* Label permission */
$all_permissions = [
    'view_dashboard'      => 'Lihat Dashboard',
    'make_payment'        => 'Melakukan Pembayaran',
    'view_receipt'        => 'Lihat Bukti Kwitansi',
    'print_receipt'       => 'Cetak Kwitansi',
    'view_profile'        => 'Lihat Profil',
    'view_bills'          => 'Lihat Tagihan',
    'create_bill'         => 'Buat Tagihan',
    'manage_bills'        => 'Kelola Tagihan',
    'view_finance_report' => 'Lihat Laporan Keuangan',
    'verify_payment'      => 'Verifikasi Pembayaran',
    'manage_users'        => 'Kelola Pengguna',
    'manage_all_bills'    => 'Kelola Semua Tagihan',
    'view_statistics'     => 'Lihat Statistik'
];

/* Proses simpan */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $role = cleanInput($_POST['role'] ?? '');
        if ($role === '') {
            $_SESSION['error'] = 'Role tidak valid.';
            header("Location: hak_akses.php");
            exit();
        }

        $pdo->beginTransaction();

        // Hapus semua permission role
        $del = $pdo->prepare("DELETE FROM role_permissions WHERE role = ?");
        $del->execute([$role]);

        // Simpan yang baru
        if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
            $ins = $pdo->prepare("INSERT INTO role_permissions (role, permission, page) VALUES (?, ?, ?)");
            foreach ($_POST['permissions'] as $perm) {
                if (isset($role_pages_permissions[$role])) {
                    $page = array_search($perm, $role_pages_permissions[$role], true);
                    if ($page !== false) {
                        $ins->execute([$role, $perm, $page]);
                    }
                }
            }
        }

        $pdo->commit();

        // Refresh permission user sekarang jika rolenya sama
        if (!empty($_SESSION['user']) && ($_SESSION['user']['role'] === $role)) {
            $s = $pdo->prepare("SELECT permission FROM role_permissions WHERE role = ?");
            $s->execute([$role]);
            $_SESSION['user']['permissions'] = $s->fetchAll(PDO::FETCH_COLUMN, 0);
        }

        $_SESSION['success'] = "Hak akses untuk role '".ucfirst($role)."' berhasil diperbarui!";
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "Gagal memperbarui hak akses: " . $e->getMessage();
    }

    header("Location: hak_akses.php?role=" . urlencode($_POST['role'] ?? ''));
    exit();
}

/* Ambil daftar role */
$roles = $pdo->query("SELECT DISTINCT role FROM users")->fetchAll(PDO::FETCH_COLUMN, 0);
if (empty($roles)) $roles = ['admin','bendahara','siswa'];

/* Role terpilih */
$selected_role = $_GET['role'] ?? $roles[0];

/* Ambil yang sudah tersimpan */
$role_permissions = [];
$st = $pdo->prepare("SELECT permission FROM role_permissions WHERE role = ?");
$st->execute([$selected_role]);
$current_permissions = $st->fetchAll(PDO::FETCH_COLUMN, 0);

/* Permission yang tersedia untuk role ini */
$available_permissions = isset($role_pages_permissions[$selected_role])
    ? array_values($role_pages_permissions[$selected_role]) : [];
$filtered_permissions = array_intersect_key($all_permissions, array_flip($available_permissions));

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="../assets/css/style_hak_akses.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="dashboard-body">
  <!-- Sidebar -->
  <?php include_once '../includes/sidebar.php'; ?>

  <!-- Konten -->
  <div class="main-content-wrapper">
    <div class="content-container">
      <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-user-shield"></i> <?= htmlspecialchars($page_title) ?></h1>
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

      <div class="hak-akses-layout">
        <!-- Role List -->
        <aside class="role-panel">
          <h2><i class="fa-solid fa-users"></i> Pilih Role</h2>
          <ul class="role-list">
            <?php foreach ($roles as $r): ?>
              <li class="<?= $selected_role === $r ? 'active' : '' ?>">
                <a href="hak_akses.php?role=<?= urlencode($r) ?>">
                  <i class="fa-solid fa-user-tag"></i>
                  <span><?= ucfirst($r) ?></span>
                  <?php if ($selected_role === $r): ?>
                    <i class="fa-solid fa-check selected-icon" aria-hidden="true"></i>
                  <?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </aside>

        <!-- Form Permissions -->
        <section class="permission-card">
          <div class="card-header">
            <h3><i class="fa-solid fa-key"></i> Hak Akses untuk <strong><?= ucfirst($selected_role) ?></strong></h3>
            <div class="bulk-actions">
              <button type="button" class="btn btn-ghost" onclick="toggleAll(true)">
                <i class="fa-solid fa-square-check"></i> Pilih Semua
              </button>
              <button type="button" class="btn btn-ghost" onclick="toggleAll(false)">
                <i class="fa-regular fa-square"></i> Bersihkan
              </button>
            </div>
          </div>

          <form method="POST" class="permission-form">
            <input type="hidden" name="role" value="<?= htmlspecialchars($selected_role) ?>">

            <div class="permissions-grid">
              <?php foreach ($filtered_permissions as $key => $label): ?>
                <label for="perm_<?= $key ?>" class="perm-item">
                  <input
                    type="checkbox"
                    id="perm_<?= $key ?>"
                    name="permissions[]"
                    value="<?= $key ?>"
                    <?= in_array($key, $current_permissions, true) ? 'checked' : '' ?>
                  >
                  <span class="perm-icon"><i class="<?= $permission_icons[$key] ?? 'fa-solid fa-circle-dot' ?>"></i></span>
                  <span class="perm-label"><?= $label ?></span>
                </label>
              <?php endforeach; ?>

              <?php if (empty($filtered_permissions)): ?>
                <div class="empty-state">
                  <i class="fa-regular fa-face-meh"></i>
                  Tidak ada permission yang tersedia untuk role ini.
                </div>
              <?php endif; ?>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
              </button>
            </div>
          </form>
        </section>
      </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>

  <script>
    function toggleAll(state){
      document.querySelectorAll('.permissions-grid input[type="checkbox"]').forEach(cb => cb.checked = !!state);
    }
  </script>
</body>
</html>
