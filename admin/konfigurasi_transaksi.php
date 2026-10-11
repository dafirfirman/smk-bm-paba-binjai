<?php
session_start();
require_once '../config/koneksi.php';
require_once '../config/functions.php';

requireRole(['admin']);

$page_title = "Konfigurasi Metode Transaksi";

/* Simpan / Perbarui konfigurasi */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metode            = cleanInput($_POST['metode'] ?? '');
    $api_key           = cleanInput($_POST['api_key'] ?? '');
    $status            = cleanInput($_POST['status'] ?? 'nonaktif');
    $template_kwitansi = cleanInput($_POST['template_kwitansi'] ?? '');

    try {
        $cek = $pdo->prepare("SELECT id FROM transaksi_config WHERE metode = ?");
        $cek->execute([$metode]);
        $exists = (bool)$cek->fetchColumn();

        if ($exists) {
            $q = $pdo->prepare("
                UPDATE transaksi_config
                SET api_key = :api_key, status = :status, template_kwitansi = :tpl, updated_at = NOW()
                WHERE metode = :metode
            ");
        } else {
            $q = $pdo->prepare("
                INSERT INTO transaksi_config (metode, api_key, status, template_kwitansi, created_at, updated_at)
                VALUES (:metode, :api_key, :status, :tpl, NOW(), NOW())
            ");
        }

        $q->execute([
            ':metode' => $metode,
            ':api_key' => $api_key,
            ':status' => in_array($status, ['aktif','nonaktif'], true) ? $status : 'nonaktif',
            ':tpl' => $template_kwitansi
        ]);

        $_SESSION['success'] = "Konfigurasi untuk '".ucfirst($metode)."' berhasil diperbarui!";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal menyimpan konfigurasi: " . $e->getMessage();
    }

    header("Location: konfigurasi_transaksi.php");
    exit();
}

/* Ambil semua konfigurasi tersimpan */
$stmt = $pdo->query("SELECT * FROM transaksi_config");
$configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Mapping metode untuk tampilan – gunakan ikon dari assets */
$metode_pembayaran = [
    'transfer' => [
        'nama' => 'Transfer Bank',
        'icon' => '../assets/icons/bank.png'           // ganti sesuai nama file di assets/icons
    ],
    'e-wallet' => [
        'nama' => 'E-Wallet',
        'icon' => '../assets/icons/wallet.png'
    ],
    'debit' => [
        'nama' => 'Kartu Debit',
        'icon' => '../assets/icons/credit-card.png'
    ],
    'kwitansi' => [
        'nama' => 'Template Kwitansi',
        'icon' => '../assets/icons/invoice.png'
    ],
];

/* Helper: ambil config per metode */
function cfg_find(array $configs, string $key): ?array {
    foreach ($configs as $c) if (($c['metode'] ?? '') === $key) return $c;
    return null;
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="../assets/css/style_konfigurasi_transaksi.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet" />
  <!-- Hapus Font Awesome; kita pakai ikon dari assets -->
  <style>
    /* Tambahan kecil agar ikon assets rapi */
    .asset-icon-sm{ width:18px; height:18px; object-fit:contain; vertical-align:middle; }
    .asset-icon-md{ width:28px; height:28px; object-fit:contain; }
    .asset-icon-lg{ width:40px; height:40px; object-fit:contain; }
    .page-title img{ margin-right:8px; }
    .method-icon img{ width:36px; height:36px; object-fit:contain; }
    .alert{ display:flex; align-items:flex-start; gap:10px; }
    .alert img{ width:18px; height:18px; margin-top:3px; }
    .btn .btn-icon{ width:18px; height:18px; margin-right:8px; object-fit:contain; vertical-align:middle; }
  </style>
</head>
<body class="dashboard-body">
  <!-- Sidebar -->
  <?php include_once '../includes/sidebar.php'; ?>

  <!-- Main -->
  <div class="main-content-wrapper">
    <div class="content-container">

      <div class="page-header">
        <h1 class="page-title">
          <img src="../assets/icons/sliders.png" alt="Sliders" class="asset-icon-md" />
          Konfigurasi Metode Transaksi
        </h1>
        <div class="page-meta">
          <img src="../assets/icons/calendar.png" alt="Tanggal" class="asset-icon-sm" />
          <span><?= date('d F Y') ?></span>
        </div>
      </div>

      <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error">
          <img src="../assets/icons/error.png" alt="Error">
          <div><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success">
          <img src="../assets/icons/check.png" alt="Sukses">
          <div><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        </div>
      <?php endif; ?>

      <div class="payment-methods">
        <?php foreach ($metode_pembayaran as $key => $m):
              $cfg = cfg_find($configs, $key);
              $isAktif = $cfg && ($cfg['status'] === 'aktif');
        ?>
          <div class="method-card">
            <div class="method-header">
              <div class="method-icon">
                <img src="<?= htmlspecialchars($m['icon']) ?>" alt="<?= htmlspecialchars($m['nama']) ?>">
              </div>
              <div class="method-title">
                <h3><?= htmlspecialchars($m['nama']) ?></h3>
                <div class="status-badge <?= $isAktif ? 'status-aktif' : 'status-nonaktif' ?>">
                  <?= $isAktif ? 'Aktif' : 'Nonaktif' ?>
                </div>
              </div>
            </div>

            <form method="POST" class="method-form" autocomplete="off">
              <input type="hidden" name="metode" value="<?= htmlspecialchars($key) ?>">

              <?php if ($key !== 'kwitansi'): ?>
                <div class="form-group">
                  <label for="api_key_<?= $key ?>">API Key</label>
                  <div class="input-with-icon">
                    <img src="../assets/icons/key.png" alt="key" class="asset-icon-sm">
                    <input
                      type="text"
                      id="api_key_<?= $key ?>"
                      name="api_key"
                      value="<?= htmlspecialchars($cfg['api_key'] ?? '') ?>"
                      placeholder="Masukkan API key untuk <?= htmlspecialchars($m['nama']) ?>"
                    />
                  </div>
                  <small class="help">Simpan kredensial dari penyedia pembayaran Anda.</small>
                </div>
              <?php else: ?>
                <div class="form-group">
                  <label for="template_kwitansi">Template Kwitansi</label>
                  <textarea
                    id="template_kwitansi"
                    name="template_kwitansi"
                    rows="6"
                    placeholder="Gunakan placeholder seperti {{nama_siswa}}, {{nisn}}, {{nama_tagihan}}, {{jumlah}}, {{total_bayar}}, {{tanggal_verifikasi}}."
                  ><?= htmlspecialchars($cfg['template_kwitansi'] ?? '') ?></textarea>
                  <small class="help">Placeholder akan diganti otomatis saat cetak kwitansi.</small>
                </div>
              <?php endif; ?>

              <div class="form-group">
                <label for="status_<?= $key ?>">Status</label>
                <select id="status_<?= $key ?>" name="status" class="status-select">
                  <option value="aktif"    <?= $isAktif ? 'selected' : '' ?>>Aktif</option>
                  <option value="nonaktif" <?= !$isAktif ? 'selected' : '' ?>>Nonaktif</option>
                </select>
              </div>

              <button type="submit" class="btn btn-primary">
                <img src="../assets/icons/save.png" alt="Simpan" class="btn-icon">
                Simpan Perubahan
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>

  <script>
    // Ubah badge saat status diganti
    document.querySelectorAll('.status-select').forEach(sel => {
      sel.addEventListener('change', function(){
        const card = this.closest('.method-card');
        const badge = card.querySelector('.status-badge');
        if (this.value === 'aktif') {
          badge.classList.remove('status-nonaktif'); badge.classList.add('status-aktif');
          badge.textContent = 'Aktif';
        } else {
          badge.classList.remove('status-aktif'); badge.classList.add('status-nonaktif');
          badge.textContent = 'Nonaktif';
        }
      });
    });
  </script>
</body>
</html>
