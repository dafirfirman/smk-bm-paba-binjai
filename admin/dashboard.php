<?php 
require_once '../config/koneksi.php';
require_once '../config/functions.php';
requireRole(['admin']);

$page_title = "Dashboard Admin";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

/* =========================
   Data Sampel untuk Pengujian
   ========================= */

// Jika database kosong, gunakan data sampel
$use_sample_data = true;

// Data sampel untuk pengujian visualisasi
$sample_status_pembayaran = [
    ['status' => 'pending', 'jumlah' => 15],
    ['status' => 'lunas', 'jumlah' => 45],
    ['status' => 'ditolak', 'jumlah' => 5]
];

$sample_metode_pembayaran = [
    ['metode_pembayaran' => 'Transfer Bank', 'jumlah' => 30],
    ['metode_pembayaran' => 'Tunai', 'jumlah' => 20],
    ['metode_pembayaran' => 'E-Wallet', 'jumlah' => 10]
];

$sample_status_tagihan = [
    ['status' => 'aktif', 'jumlah' => 40],
    ['status' => 'nonaktif', 'jumlah' => 15]
];

$sample_pembayaran_siswa = [
    ['nama_lengkap' => 'Ahmad Sutisna', 'total_bayar' => 1250000],
    ['nama_lengkap' => 'Siti Rahayu', 'total_bayar' => 1100000],
    ['nama_lengkap' => 'Budi Santoso', 'total_bayar' => 980000],
    ['nama_lengkap' => 'Dewi Anggraini', 'total_bayar' => 875000],
    ['nama_lengkap' => 'Rudi Hermawan', 'total_bayar' => 760000],
    ['nama_lengkap' => 'Maya Indah', 'total_bayar' => 650000],
    ['nama_lengkap' => 'Fajar Pratama', 'total_bayar' => 540000],
    ['nama_lengkap' => 'Rina Wijaya', 'total_bayar' => 430000],
    ['nama_lengkap' => 'Hendra Kurniawan', 'total_bayar' => 320000],
    ['nama_lengkap' => 'Lina Septiani', 'total_bayar' => 210000]
];

$sample_status_backup = [
    ['status' => 'sukses', 'jumlah' => 8],
    ['status' => 'gagal', 'jumlah' => 2]
];

$sample_status_notifikasi = [
    ['dibaca' => 1, 'jumlah' => 35],
    ['dibaca' => 0, 'jumlah' => 12]
];

$sample_tren_pembayaran = [];
$start_date = date('Y-m-d', strtotime('-6 days'));
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime($start_date . " +$i days"));
    $sample_tren_pembayaran[] = [
        'tanggal' => $date,
        'jumlah_transaksi' => rand(5, 20),
        'total_nominal' => rand(500000, 2000000)
    ];
}

$sample_siswa_jurusan = [
    ['jurusan' => 'IPA', 'jumlah' => 120],
    ['jurusan' => 'IPS', 'jumlah' => 85],
    ['jurusan' => 'Bahasa', 'jumlah' => 45]
];

$sample_pembayaran_gateway = [
    ['nama_gateway' => 'Midtrans', 'jumlah_transaksi' => 35, 'total_nominal' => 3500000],
    ['nama_gateway' => 'Duitku', 'jumlah_transaksi' => 20, 'total_nominal' => 2100000],
    ['nama_gateway' => 'Direct Payment', 'jumlah_transaksi' => 10, 'total_nominal' => 900000]
];

/* =========================
   Ringkasan & Dataset
   ========================= */

// Ringkasan jumlah akun & status pembayaran
$total_siswa              = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'siswa' AND deleted_at IS NULL")->fetchColumn();
$total_bendahara          = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'bendahara' AND deleted_at IS NULL")->fetchColumn();
$pembayaran_pending       = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'pending'")->fetchColumn();
$pembayaran_terverifikasi = (int)$pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'lunas' AND DATE(tanggal_verifikasi) = CURDATE()")->fetchColumn();

// Jika data kosong, gunakan data sampel
if ($total_siswa == 0) {
    $total_siswa = 65;
    $total_bendahara = 3;
    $pembayaran_pending = 15;
    $pembayaran_terverifikasi = 8;
    $use_sample_data = true;
} else {
    $use_sample_data = false;
}

// Transaksi 30 hari terakhir
$chart_stmt = $pdo->query("
  SELECT DATE(tanggal_pembayaran) AS tanggal,
         COUNT(*) AS total_transaksi,
         COALESCE(SUM(total_bayar),0) AS total_nominal
  FROM pembayaran
  WHERE status = 'lunas'
    AND tanggal_pembayaran >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
  GROUP BY DATE(tanggal_pembayaran)
  ORDER BY tanggal ASC
");
$chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Jika data kosong, gunakan data sampel
if (empty($chart_data)) {
    for ($i = 0; $i < 30; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chart_data[] = [
            'tanggal' => $date,
            'total_transaksi' => rand(1, 15),
            'total_nominal' => rand(100000, 1500000)
        ];
    }
    $chart_data = array_reverse($chart_data);
}

// Tagihan belum dibayar per kategori
$tagihan_chart_stmt = $pdo->query("
  SELECT t.kategori,
         COUNT(*) AS jumlah,
         COALESCE(SUM(t.jumlah),0) AS total_nominal
  FROM tagihan t
  LEFT JOIN pembayaran p
    ON t.id = p.tagihan_id AND p.status = 'lunas'
  WHERE p.id IS NULL AND t.status = 'aktif'
  GROUP BY t.kategori
  ORDER BY jumlah DESC
");
$tagihan_chart_data = $tagihan_chart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Jika data kosong, gunakan data sampel
if (empty($tagihan_chart_data)) {
    $tagihan_chart_data = [
        ['kategori' => 'SPP', 'jumlah' => 15, 'total_nominal' => 750000],
        ['kategori' => 'Uang Bangunan', 'jumlah' => 8, 'total_nominal' => 1200000],
        ['kategori' => 'Kegiatan', 'jumlah' => 5, 'total_nominal' => 500000]
    ];
}

// 5 tagihan belum dibayar teratas (detail)
$tagihan_belum_stmt = $pdo->query("
  SELECT t.nama_tagihan, t.kategori,
         COUNT(*) AS jumlah, COALESCE(SUM(t.jumlah),0) AS total_nominal
  FROM tagihan t
  LEFT JOIN pembayaran p
    ON t.id = p.tagihan_id AND p.status = 'lunas'
  WHERE p.id IS NULL AND t.status = 'aktif'
  GROUP BY t.nama_tagihan, t.kategori
  ORDER BY jumlah DESC
  LIMIT 5
");
$tagihan_belum_dibayar = $tagihan_belum_stmt->fetchAll(PDO::FETCH_ASSOC);

// Jika data kosong, gunakan data sampel
if (empty($tagihan_belum_dibayar)) {
    $tagihan_belum_dibayar = [
        ['nama_tagihan' => 'SPP April 2023', 'kategori' => 'SPP', 'jumlah' => 15, 'total_nominal' => 750000],
        ['nama_tagihan' => 'Uang Bangunan', 'kategori' => 'Uang Bangunan', 'jumlah' => 8, 'total_nominal' => 1200000],
        ['nama_tagihan' => 'Study Tour', 'kategori' => 'Kegiatan', 'jumlah' => 5, 'total_nominal' => 500000],
        ['nama_tagihan' => 'SPP Mei 2023', 'kategori' => 'SPP', 'jumlah' => 3, 'total_nominal' => 150000],
        ['nama_tagihan' => 'Uang Seragam', 'kategori' => 'Seragam', 'jumlah' => 2, 'total_nominal' => 300000]
    ];
}

// 5 transaksi terbaru
$trx_stmt = $pdo->query("
  SELECT p.*, s.nama_lengkap AS nama_siswa, t.nama_tagihan
  FROM pembayaran p
  JOIN siswa s ON p.siswa_id = s.id
  JOIN tagihan t ON p.tagihan_id = t.id
  WHERE p.status = 'lunas'
  ORDER BY p.tanggal_verifikasi DESC
  LIMIT 5
");
$transaksi_terbaru = $trx_stmt->fetchAll(PDO::FETCH_ASSOC);

// Jika data kosong, gunakan data sampel
if (empty($transaksi_terbaru)) {
    $transaksi_terbaru = [
        ['nama_siswa' => 'Ahmad Sutisna', 'nama_tagihan' => 'SPP April 2023', 'total_bayar' => 50000],
        ['nama_siswa' => 'Siti Rahayu', 'nama_tagihan' => 'Uang Bangunan', 'total_bayar' => 150000],
        ['nama_siswa' => 'Budi Santoso', 'nama_tagihan' => 'Study Tour', 'total_bayar' => 100000],
        ['nama_siswa' => 'Dewi Anggraini', 'nama_tagihan' => 'SPP Mei 2023', 'total_bayar' => 50000],
        ['nama_siswa' => 'Rudi Hermawan', 'nama_tagihan' => 'Uang Seragam', 'total_bayar' => 150000]
    ];
}

// Total pendapatan bulan ini
$pendapatan_bulan_ini = (float)($pdo->query("
  SELECT COALESCE(SUM(total_bayar),0) AS total
  FROM pembayaran
  WHERE status = 'lunas'
    AND MONTH(tanggal_verifikasi) = MONTH(CURDATE())
    AND YEAR(tanggal_verifikasi)  = YEAR(CURDATE())
")->fetchColumn() ?: 0);

if ($pendapatan_bulan_ini == 0) {
    $pendapatan_bulan_ini = 3250000;
}

// Estimasi total tagihan belum dibayar (aktif & belum lunas)
$total_tagihan_belum_dibayar = (float)($pdo->query("
  SELECT COALESCE(SUM(t.jumlah),0) AS total
  FROM tagihan t
  LEFT JOIN pembayaran p
    ON t.id = p.tagihan_id AND p.status = 'lunas'
  WHERE p.id IS NULL AND t.status = 'aktif'
")->fetchColumn() ?: 0);

if ($total_tagihan_belum_dibayar == 0) {
    $total_tagihan_belum_dibayar = 2750000;
}

// Data untuk visualisasi baru
if ($use_sample_data) {
    // Jika menggunakan data sampel
    $status_pembayaran = $sample_status_pembayaran;
    $metode_pembayaran = $sample_metode_pembayaran;
    $status_tagihan = $sample_status_tagihan;
    $pembayaran_per_siswa = $sample_pembayaran_siswa;
    $status_backup = $sample_status_backup;
    $status_notifikasi = $sample_status_notifikasi;
    $tren_pembayaran = $sample_tren_pembayaran;
    $siswa_per_jurusan = $sample_siswa_jurusan;
    $pembayaran_per_gateway = $sample_pembayaran_gateway;
} else {
    // Query data dari database
    // 1. Status Pembayaran
    $status_pembayaran = $pdo->query("
      SELECT status, COUNT(*) as jumlah 
      FROM pembayaran 
      GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Metode Pembayaran
    $metode_pembayaran = $pdo->query("
      SELECT metode_pembayaran, COUNT(*) as jumlah 
      FROM pembayaran 
      WHERE metode_pembayaran IS NOT NULL 
      GROUP BY metode_pembayaran
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Status Tagihan
    $status_tagihan = $pdo->query("
      SELECT status, COUNT(*) as jumlah 
      FROM tagihan 
      GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 4. Pembayaran per Siswa (Top 10)
    $pembayaran_per_siswa = $pdo->query("
      SELECT s.nama_lengkap, COALESCE(SUM(p.total_bayar), 0) as total_bayar
      FROM siswa s
      LEFT JOIN pembayaran p ON s.id = p.siswa_id AND p.status = 'lunas'
      GROUP BY s.id, s.nama_lengkap
      ORDER BY total_bayar DESC
      LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 5. Status Backup
    $status_backup = $pdo->query("
      SELECT status, COUNT(*) as jumlah 
      FROM backup_config 
      GROUP BY status
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 6. Status Notifikasi
    $status_notifikasi = $pdo->query("
      SELECT dibaca, COUNT(*) as jumlah 
      FROM notifikasi 
      GROUP BY dibaca
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 7. Tren Pembayaran (7 hari terakhir)
    $tren_pembayaran = $pdo->query("
      SELECT DATE(tanggal_pembayaran) as tanggal, COUNT(*) as jumlah_transaksi, COALESCE(SUM(total_bayar), 0) as total_nominal
      FROM pembayaran
      WHERE status = 'lunas' AND tanggal_pembayaran >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
      GROUP BY DATE(tanggal_pembayaran)
      ORDER BY tanggal ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 8. Siswa per Jurusan
    $siswa_per_jurusan = $pdo->query("
      SELECT jurusan, COUNT(*) as jumlah 
      FROM siswa 
      WHERE jurusan IS NOT NULL 
      GROUP BY jurusan
    ")->fetchAll(PDO::FETCH_ASSOC);

    // 9. Pembayaran per Gateway
    $pembayaran_per_gateway = $pdo->query("
      SELECT pg.nama_gateway, COUNT(p.id) as jumlah_transaksi, COALESCE(SUM(p.total_bayar), 0) as total_nominal
      FROM payment_gateway pg
      LEFT JOIN pembayaran p ON pg.id = p.metode_pembayaran
      WHERE p.status = 'lunas'
      GROUP BY pg.id, pg.nama_gateway
    ")->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($page_title) ?></title>

  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/dashboard_admin.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="dashboard-body">
  <!-- Sidebar -->
  <?php include_once '../includes/sidebar.php'; ?>

  <!-- Wrapper -->
  <div class="main-content-wrapper">
    <div class="content-container">
      <div class="dashboard-header">
        <h1 class="dashboard-title">Dashboard Admin</h1>
        <div class="dashboard-date">
          <img src="../assets/icons/calendar.png" alt="Calendar" class="icon-sm">
          <span><?= date('l, d F Y') ?></span>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="summary-grid">
        <div class="summary-card card-primary">
          <div class="card-icon">
            <img src="../assets/icons/student.png" alt="Students" class="icon-md">
          </div>
          <div class="card-content">
            <h3>Total Siswa</h3>
            <p><?= number_format($total_siswa) ?></p>
          </div>
        </div>

        <div class="summary-card card-success">
          <div class="card-icon">
            <img src="../assets/icons/entrepeneur.png" alt="Treasurer" class="icon-md">
          </div>
          <div class="card-content">
            <h3>Total Bendahara</h3>
            <p><?= number_format($total_bendahara) ?></p>
          </div>
        </div>

        <div class="summary-card card-warning">
          <div class="card-icon">
            <img src="../assets/icons/wallet.png" alt="Pending" class="icon-md">
          </div>
          <div class="card-content">
            <h3>Pembayaran Pending</h3>
            <p><?= number_format($pembayaran_pending) ?></p>
          </div>
        </div>

        <div class="summary-card card-info">
          <div class="card-icon">
            <img src="../assets/icons/verified.png" alt="Verified" class="icon-md">
          </div>
          <div class="card-content">
            <h3>Terverifikasi Hari Ini</h3>
            <p><?= number_format($pembayaran_terverifikasi) ?></p>
          </div>
        </div>

        <div class="summary-card card-revenue">
          <div class="card-icon">
            <img src="../assets/icons/revenue.png" alt="Revenue" class="icon-md">
          </div>
          <div class="card-content">
            <h3>Pendapatan Bulan Ini</h3>
            <p>Rp <?= number_format($pendapatan_bulan_ini, 0, ',', '.') ?></p>
          </div>
        </div>
      </div>

      <!-- Mini Charts -->
      <div class="mini-chart-grid">
        <!-- <div class="mini-chart-card">
          <div class="card-header">
            <h3><img src="../assets/icons/pie-chart.png" class="icon-sm" alt=""> Tagihan per Kategori</h3>
          </div>
          <div class="mini-chart-container">
            <canvas id="tagihanChart" height="200"></canvas>
          </div>
        </div> -->

        <div class="mini-chart-card">
          <div class="card-header">
            <h3><img src="../assets/icons/burden.png" class="icon-sm" alt=""> Pendapatan vs Tunggakan</h3>
          </div>
          <div class="mini-chart-container">
            <canvas id="revenueChart" height="200"></canvas>
          </div>
        </div>
      </div>

      <!-- Charts + Lists -->
      <div class="dashboard-row">
        <!-- Transactions Line/Bar Combo -->
        <div class="dashboard-card">
          <div class="card-header">
            <h3><img src="../assets/icons/graph.png" class="icon-sm" alt=""> Grafik Transaksi 30 Hari Terakhir</h3>
          </div>
          <div class="chart-container">
            <canvas id="transactionChart" height="320"></canvas>
          </div>
        </div>

        <div class="dashboard-grid">
          <!-- Unpaid Bills -->
          <div class="dashboard-card">
            <div class="card-header">
              <h3><img src="../assets/icons/exclamation.png" class="icon-sm" alt=""> Tagihan Belum Dibayar</h3>
              <span class="badge badge-warning"><?= count($tagihan_belum_dibayar) ?> items</span>
            </div>
            <div class="unpaid-list">
              <?php if (empty($tagihan_belum_dibayar)): ?>
                <div class="empty-state">
                  <img src="../assets/icons/check.png" alt="" class="icon-lg">
                  <p>Semua tagihan telah dibayar</p>
                </div>
              <?php else: foreach ($tagihan_belum_dibayar as $t): ?>
                <div class="unpaid-item">
                  <div class="unpaid-info">
                    <h4><?= htmlspecialchars($t['nama_tagihan']) ?></h4>
                    <span><?= htmlspecialchars($t['kategori']) ?> • <?= (int)$t['jumlah'] ?> siswa</span>
                  </div>
                  <div class="unpaid-amount">Rp <?= number_format((float)$t['total_nominal'], 0, ',', '.') ?></div>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>

          <!-- Recent Transactions -->
          <!-- <div class="dashboard-card">
            <div class="card-header">
              <h3><img src="../assets/icons/report.png" class="icon-sm" alt=""> Transaksi Terbaru</h3>
            </div>
            <div class="transactions-list">
              <?php if (empty($transaksi_terbaru)): ?>
                <div class="empty-state">
                  <img src="../assets/icons/receipt.png" alt="" class="icon-lg">
                  <p>Belum ada transaksi</p>
                </div>
              <?php else: foreach ($transaksi_terbaru as $trx): ?>
                <div class="transaction-item">
                  <div class="transaction-info">
                    <h4><?= htmlspecialchars($trx['nama_siswa']) ?></h4>
                    <span><?= htmlspecialchars($trx['nama_tagihan']) ?></span>
                  </div>
                  <div class="transaction-amount">
                    Rp <?= number_format((float)$trx['total_bayar'], 0, ',', '.') ?>
                  </div>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div> -->
        </div>
      </div>

      <!-- New Visualizations Section -->
      <div class="dashboard-section">
        <h2 class="section-title">Visualisasi Data Lengkap</h2>
        <?php if ($use_sample_data): ?>
          <div class="alert alert-info">
            <strong>Info:</strong> Dashboard menggunakan data sampel untuk demonstrasi. Data akan ditampilkan dari database ketika tersedia.
          </div>
        <?php endif; ?>
        
        <!-- Row 1: Status Pembayaran, Metode Pembayaran, Status Tagihan -->
        <div class="visualization-grid">
          <div class="visualization-card">
            <div class="card-header">
              <h3><img src="../assets/icons/check-list.png" class="icon-sm" alt=""> Status Pembayaran</h3>
            </div>
            <div class="chart-container">
              <canvas id="statusPembayaranChart" height="250"></canvas>
            </div>
          </div>

          <div class="visualization-card">
            <div class="card-header">
              <h3><img src="../assets/icons/cashless-payment.png" class="icon-sm" alt=""> Metode Pembayaran</h3>
            </div>
            <div class="chart-container">
              <canvas id="metodePembayaranChart" height="250"></canvas>
            </div>
          </div>

          <div class="visualization-card">
            <div class="card-header">
              <h3><img src="../assets/icons/invoice.png" class="icon-sm" alt=""> Status Tagihan</h3>
            </div>
            <div class="chart-container">
              <canvas id="statusTagihanChart" height="250"></canvas>
            </div>
            <div class="data-source-notice">
              <?= $use_sample_data ? 'Data Sampel' : 'Data Database' ?>
            </div>
          </div>
        </div>

        <!-- Row 2: Pembayaran per Siswa, Status Backup, Status Notifikasi -->
        <div class="visualization-grid">
          <div class="visualization-card wide-card">
            <div class="card-header">
              <h3><img src="../assets/icons/sponsorship.png" class="icon-sm" alt=""> Pembayaran per Siswa (Top 10)</h3>
            </div>
            <div class="chart-container">
              <canvas id="pembayaranSiswaChart" height="300"></canvas>
            </div>
          </div>

          <!-- <div class="visualization-grid-small">
            <div class="visualization-card">
              <div class="card-header">
                <h3><img src="../assets/icons/backup.png" class="icon-sm" alt=""> Status Backup</h3>
              </div>
              <div class="chart-container">
                <canvas id="statusBackupChart" height="200"></canvas>
              </div>
            </div> -->

            <div class="visualization-card">
              <div class="card-header">
                <h3><img src="../assets/icons/notification.png" class="icon-sm" alt=""> Status Notifikasi</h3>
              </div>
              <div class="chart-container">
                <canvas id="statusNotifikasiChart" height="200"></canvas>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 3: Tren Pembayaran, Siswa per Jurusan, Pembayaran per Gateway -->
        <div class="visualization-grid">
          <div class="visualization-card">
            <div class="card-header">
              <h3><img src="../assets/icons/performance.png" class="icon-sm" alt=""> Tren Pembayaran (7 Hari)</h3>
            </div>
            <div class="chart-container">
              <canvas id="trenPembayaranChart" height="250"></canvas>
            </div>
          </div>

          <div class="visualization-card">
            <div class="card-header">
              <h3><img src="../assets/icons/online-learning.png" class="icon-sm" alt=""> Siswa per Jurusan</h3>
            </div>
            <div class="chart-container">
              <canvas id="siswaJurusanChart" height="250"></canvas>
            </div>
          </div>

          <div class="visualization-card">
            <div class="card-header">
              <h3><img src="../assets/icons/gateway.png" class="icon-sm" alt=""> Pembayaran per Gateway</h3>
            </div>
            <div class="chart-container">
              <canvas id="pembayaranGatewayChart" height="250"></canvas>
            </div>
          </div>
        </div>
      </div>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>

  <!-- ===== Charts Init (langsung tampil) ===== -->
  <script>
    // ------- Data dari PHP -------
    const transactionDates  = <?= json_encode(array_column($chart_data, 'tanggal'), JSON_UNESCAPED_UNICODE) ?>;
    const transactionCounts = <?= json_encode(array_map('intval', array_column($chart_data, 'total_transaksi'))) ?>;
    const transactionAmounts= <?= json_encode(array_map('floatval', array_column($chart_data, 'total_nominal'))) ?>;

    const tagihanCategories = <?= json_encode(array_column($tagihan_chart_data, 'kategori'), JSON_UNESCAPED_UNICODE) ?>;
    const tagihanCounts     = <?= json_encode(array_map('intval', array_column($tagihan_chart_data, 'jumlah'))) ?>;
    const tagihanAmounts    = <?= json_encode(array_map('floatval', array_column($tagihan_chart_data, 'total_nominal'))) ?>;

    const pendapatanBulanIni = <?= json_encode($pendapatan_bulan_ini) ?>;
    const totalTunggakan     = <?= json_encode($total_tagihan_belum_dibayar) ?>;

    // Data untuk visualisasi baru
    const statusPembayaranData = <?= json_encode($status_pembayaran) ?>;
    const metodePembayaranData = <?= json_encode($metode_pembayaran) ?>;
    const statusTagihanData = <?= json_encode($status_tagihan) ?>;
    const pembayaranSiswaData = <?= json_encode($pembayaran_per_siswa) ?>;
    const statusBackupData = <?= json_encode($status_backup) ?>;
    const statusNotifikasiData = <?= json_encode($status_notifikasi) ?>;
    const trenPembayaranData = <?= json_encode($tren_pembayaran) ?>;
    const siswaJurusanData = <?= json_encode($siswa_per_jurusan) ?>;
    const pembayaranGatewayData = <?= json_encode($pembayaran_per_gateway) ?>;

    // ------- Helper -------
    const rupiah = (n) => 'Rp ' + Number(n||0).toLocaleString('id-ID');
    const formatNumber = (n) => Number(n||0).toLocaleString('id-ID');

    // Label tanggal lebih ramah
    const trxLabels = (transactionDates && transactionDates.length ? transactionDates : [])
      .map(d => {
        const [y, m, day] = d.split('-').map(Number);
        const dt = new Date(y, (m||1)-1, day||1);
        return dt.toLocaleDateString('id-ID', { day:'2-digit', month:'short' });
      });

    // Fungsi untuk membuat chart dengan error handling
    function createChart(ctx, config) {
      try {
        return new Chart(ctx, config);
      } catch (error) {
        console.error('Error creating chart:', error);
        ctx.parentElement.innerHTML = '<div class="empty-state"><p>Error loading chart</p></div>';
        return null;
      }
    }

    // ------- Transaction Chart (Combo Line+Bar) -------
    const ctxTrans = document.getElementById('transactionChart');
    if (ctxTrans) {
      createChart(ctxTrans, {
        type: 'bar',
        data: {
          labels: trxLabels,
          datasets: [
            {
              type: 'bar',
              label: 'Jumlah Transaksi',
              data: transactionCounts,
              borderWidth: 0,
              yAxisID: 'yCount'
            },
            {
              type: 'line',
              label: 'Nominal (Rp)',
              data: transactionAmounts,
              tension: 0.35,
              pointRadius: 3,
              yAxisID: 'yAmount'
            }
          ]
        },
        options: {
          responsive: true,
          interaction: { mode: 'index', intersect: false },
          plugins: {
            legend: { position: 'top' },
            tooltip: {
              callbacks: {
                label: (ctx) => {
                  const v = ctx.parsed.y;
                  return ctx.dataset.label.includes('Nominal') ? `${ctx.dataset.label}: ${rupiah(v)}` : `${ctx.dataset.label}: ${v}`;
                }
              }
            },
            title: { display: false }
          },
          scales: {
            yCount: {
              type: 'linear',
              position: 'left',
              title: { display: true, text: 'Transaksi' },
              grid: { drawOnChartArea: false }
            },
            yAmount: {
              type: 'linear',
              position: 'right',
              title: { display: true, text: 'Nominal (Rp)' },
              ticks: {
                callback: (v) => (Number(v)).toLocaleString('id-ID')
              }
            }
          }
        }
      });
    }

    // ------- Tagihan per Kategori (Horizontal Bar) -------
    const ctxTagihan = document.getElementById('tagihanChart');
    if (ctxTagihan) {
      createChart(ctxTagihan, {
        type: 'bar',
        data: {
          labels: tagihanCategories.length ? tagihanCategories : ['-'],
          datasets: [{
            label: 'Belum Dibayar (Siswa)',
            data: tagihanCounts.length ? tagihanCounts : [0],
            borderWidth: 0
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          plugins: {
            legend: { display: false },
            title: { display: false },
            tooltip: {
              callbacks: {
                afterLabel: (ctx) => {
                  const idx = ctx.dataIndex;
                  const nominal = tagihanAmounts[idx] || 0;
                  return '  • ' + rupiah(nominal);
                }
              }
            }
          }
        }
      });
    }

    // ------- Pendapatan vs Tunggakan (Doughnut) -------
    const ctxRevenue = document.getElementById('revenueChart');
    if (ctxRevenue) {
      createChart(ctxRevenue, {
        type: 'doughnut',
        data: {
          labels: ['Pendapatan Bulan Ini', 'Tunggakan Aktif'],
          datasets: [{
            data: [pendapatanBulanIni || 0, totalTunggakan || 0],
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          cutout: '65%',
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: { label: (ctx) => `${ctx.label}: ${rupiah(ctx.parsed)}` }
            }
          }
        }
      });
    }

    // ------- New Charts -------

    // 1. Status Pembayaran
    const ctxStatusPembayaran = document.getElementById('statusPembayaranChart');
    if (ctxStatusPembayaran) {
      createChart(ctxStatusPembayaran, {
        type: 'bar',
        data: {
          labels: statusPembayaranData.map(item => item.status),
          datasets: [{
            label: 'Jumlah Pembayaran',
            data: statusPembayaranData.map(item => item.jumlah),
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56'],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${formatNumber(ctx.parsed.y)}`
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true
            }
          }
        }
      });
    }

    // 2. Metode Pembayaran
    const ctxMetodePembayaran = document.getElementById('metodePembayaranChart');
    if (ctxMetodePembayaran) {
      createChart(ctxMetodePembayaran, {
        type: 'doughnut',
        data: {
          labels: metodePembayaranData.map(item => item.metode_pembayaran || 'Tidak Diketahui'),
          datasets: [{
            data: metodePembayaranData.map(item => item.jumlah),
            backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'],
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.label}: ${formatNumber(ctx.parsed)}`
              }
            }
          }
        }
      });
    }

    // 3. Status Tagihan
    const ctxStatusTagihan = document.getElementById('statusTagihanChart');
    if (ctxStatusTagihan) {
      createChart(ctxStatusTagihan, {
        type: 'pie',
        data: {
          labels: statusTagihanData.map(item => item.status),
          datasets: [{
            data: statusTagihanData.map(item => item.jumlah),
            backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56'],
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.label}: ${formatNumber(ctx.parsed)}`
              }
            }
          }
        }
      });
    }

    // 4. Pembayaran per Siswa
    const ctxPembayaranSiswa = document.getElementById('pembayaranSiswaChart');
    if (ctxPembayaranSiswa) {
      createChart(ctxPembayaranSiswa, {
        type: 'bar',
        data: {
          labels: pembayaranSiswaData.map(item => item.nama_lengkap),
          datasets: [{
            label: 'Total Pembayaran (Rp)',
            data: pembayaranSiswaData.map(item => item.total_bayar),
            backgroundColor: '#36A2EB',
            borderWidth: 0
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${rupiah(ctx.parsed.x)}`
              }
            }
          }
        }
      });
    }

    // 5. Status Backup
    const ctxStatusBackup = document.getElementById('statusBackupChart');
    if (ctxStatusBackup) {
      createChart(ctxStatusBackup, {
        type: 'pie',
        data: {
          labels: statusBackupData.map(item => item.status),
          datasets: [{
            data: statusBackupData.map(item => item.jumlah),
            backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56'],
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.label}: ${formatNumber(ctx.parsed)}`
              }
            }
          }
        }
      });
    }

    // 6. Status Notifikasi
    const ctxStatusNotifikasi = document.getElementById('statusNotifikasiChart');
    if (ctxStatusNotifikasi) {
      createChart(ctxStatusNotifikasi, {
        type: 'doughnut',
        data: {
          labels: statusNotifikasiData.map(item => item.dibaca == 1 ? 'Dibaca' : 'Belum Dibaca'),
          datasets: [{
            data: statusNotifikasiData.map(item => item.jumlah),
            backgroundColor: ['#36A2EB', '#FF6384'],
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: 'bottom' },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.label}: ${formatNumber(ctx.parsed)}`
              }
            }
          }
        }
      });
    }

    // 7. Tren Pembayaran
    const trenLabels = trenPembayaranData.map(item => {
      const [y, m, d] = item.tanggal.split('-');
      return new Date(y, m-1, d).toLocaleDateString('id-ID', {day: '2-digit', month: 'short'});
    });
    
    const ctxTrenPembayaran = document.getElementById('trenPembayaranChart');
    if (ctxTrenPembayaran) {
      createChart(ctxTrenPembayaran, {
        type: 'line',
        data: {
          labels: trenLabels,
          datasets: [{
            label: 'Jumlah Transaksi',
            data: trenPembayaranData.map(item => item.jumlah_transaksi),
            borderColor: '#36A2EB',
            tension: 0.1,
            yAxisID: 'y'
          }, {
            label: 'Total Nominal (Rp)',
            data: trenPembayaranData.map(item => item.total_nominal),
            borderColor: '#FF6384',
            tension: 0.1,
            yAxisID: 'y1'
          }]
        },
        options: {
          responsive: true,
          interaction: {
            mode: 'index',
            intersect: false,
          },
          scales: {
            y: {
              type: 'linear',
              display: true,
              position: 'left',
              title: {
                display: true,
                text: 'Jumlah Transaksi'
              }
            },
            y1: {
              type: 'linear',
              display: true,
              position: 'right',
              title: {
                display: true,
                text: 'Nominal (Rp)'
              },
              grid: {
                drawOnChartArea: false,
              },
              ticks: {
                callback: (value) => value.toLocaleString('id-ID')
              }
            }
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: (context) => {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  if (context.dataset.label.includes('Nominal')) {
                    label += rupiah(context.parsed.y);
                  } else {
                    label += formatNumber(context.parsed.y);
                  }
                  return label;
                }
              }
            }
          }
        }
      });
    }

    // 8. Siswa per Jurusan
    const ctxSiswaJurusan = document.getElementById('siswaJurusanChart');
    if (ctxSiswaJurusan) {
      createChart(ctxSiswaJurusan, {
        type: 'bar',
        data: {
          labels: siswaJurusanData.map(item => item.jurusan),
          datasets: [{
            label: 'Jumlah Siswa',
            data: siswaJurusanData.map(item => item.jumlah),
            backgroundColor: '#4BC0C0',
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: (ctx) => `${ctx.dataset.label}: ${formatNumber(ctx.parsed.y)}`
              }
            }
          }
        }
      });
    }

    // 9. Pembayaran per Gateway
    const ctxPembayaranGateway = document.getElementById('pembayaranGatewayChart');
    if (ctxPembayaranGateway) {
      createChart(ctxPembayaranGateway, {
        type: 'bar',
        data: {
          labels: pembayaranGatewayData.map(item => item.nama_gateway || 'Tidak Diketahui'),
          datasets: [{
            label: 'Jumlah Transaksi',
            data: pembayaranGatewayData.map(item => item.jumlah_transaksi),
            backgroundColor: '#9966FF',
            borderWidth: 0,
            yAxisID: 'y'
          }, {
            label: 'Total Nominal (Rp)',
            data: pembayaranGatewayData.map(item => item.total_nominal),
            backgroundColor: '#FF9F40',
            borderWidth: 0,
            yAxisID: 'y1'
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: {
              type: 'linear',
              display: true,
              position: 'left',
              title: {
                display: true,
                text: 'Jumlah Transaksi'
              }
            },
            y1: {
              type: 'linear',
              display: true,
              position: 'right',
              title: {
                display: true,
                text: 'Nominal (Rp)'
              },
              grid: {
                drawOnChartArea: false,
              },
              ticks: {
                callback: (value) => value.toLocaleString('id-ID')
              }
            }
          },
          plugins: {
            tooltip: {
              callbacks: {
                label: (context) => {
                  let label = context.dataset.label || '';
                  if (label) {
                    label += ': ';
                  }
                  if (context.dataset.label.includes('Nominal')) {
                    label += rupiah(context.parsed.y);
                  } else {
                    label += formatNumber(context.parsed.y);
                  }
                  return label;
                }
              }
            }
          }
        }
      });
    }

    // Cek jika ada elemen canvas yang tidak dibuat chart-nya
    document.querySelectorAll('canvas').forEach(canvas => {
      if (!canvas.__chartjs) {
        console.warn('Canvas without chart:', canvas.id);
      }
    });
  </script>
</body>
</html>