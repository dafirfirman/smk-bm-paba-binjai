<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Pastikan user berhak (opsional, kalau perlu aktifkan)
requireRole(['admin','bendahara','siswa']); // atau sesuaikan

$page_title = "Statistik & Aktivitas";

// ====== DATA INTI ======
$current_month = date('Y-m');

// Total pembayaran bulan ini (status lunas)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(jumlah),0) AS total 
    FROM pembayaran 
    WHERE status = 'lunas' 
      AND DATE_FORMAT(tanggal_verifikasi, '%Y-%m') = ?
");
$stmt->execute([$current_month]);
$total_pembayaran = (float)($stmt->fetch()['total'] ?? 0);

// Jumlah siswa aktif
$stmt = $pdo->query("SELECT COUNT(*) AS total FROM siswa");
$total_siswa = (int)($stmt->fetch()['total'] ?? 0);

// Metode pembayaran terbanyak (di antara lunas)
$stmt = $pdo->query("
    SELECT metode_pembayaran, COUNT(*) AS jumlah
    FROM pembayaran
    WHERE status = 'lunas'
    GROUP BY metode_pembayaran
    ORDER BY jumlah DESC
    LIMIT 1
");
$metode_terbanyak = $stmt->fetch();

// Rata-rata waktu verifikasi (menit)
$stmt = $pdo->query("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, tanggal_pembayaran, tanggal_verifikasi)) AS rata_rata
    FROM pembayaran
    WHERE status = 'lunas'
");
$rata_waktu_verifikasi = $stmt->fetch()['rata_rata'];

// Data chart 6 bulan terakhir: total nominal & jumlah transaksi
$stmt = $pdo->query("
    SELECT DATE_FORMAT(tanggal_verifikasi, '%Y-%m') AS bulan,
           SUM(jumlah) AS total_nominal,
           COUNT(*) AS total_transaksi
    FROM pembayaran
    WHERE status = 'lunas'
      AND tanggal_verifikasi >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY bulan
    ORDER BY bulan
");
$chart_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format ke label & dataset
$labels = [];
$data_nominal = [];
$data_transaksi = [];
foreach ($chart_rows as $row) {
    $labels[] = date('M Y', strtotime($row['bulan'] . '-01'));
    $data_nominal[] = (float) $row['total_nominal'];
    $data_transaksi[] = (int) $row['total_transaksi'];
}

// Aktivitas terakhir
$stmt = $pdo->query("
    SELECT l.*, u.username 
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 10
");
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header/navbar
require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/style_statistik.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome opsional jika perlu -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="dashboard-body">
    <!-- Sidebar -->
    <div>
        <?php include_once '../includes/sidebar.php'; ?>
    </div>

    <!-- Konten Utama -->
    <div class="main-content-wrapper">
        <div class="content-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">
                    <img src="../assets/icons/performance.png" class="icon-md" alt="Analytics">
                    Statistik & Aktivitas
                </h1>
                <div class="dashboard-date">
                    <img src="../assets/icons/calendar.png" class="icon-sm" alt="Calendar">
                    <span><?= date('l, d F Y') ?></span>
                </div>
            </div>

            <!-- Ringkasan -->
            <div class="statistics-summary">
                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../assets/icons/revenue.png" class="icon-md" alt="Revenue">
                    </div>
                    <div class="stat-meta">
                        <h3>Pembayaran Bulan Ini</h3>
                        <p class="stat-value">Rp <?= number_format($total_pembayaran, 0, ',', '.') ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../assets/icons/student.png" class="icon-md" alt="Students">
                    </div>
                    <div class="stat-meta">
                        <h3>Siswa Aktif</h3>
                        <p class="stat-value"><?= number_format($total_siswa) ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../assets/icons/wallet.png" class="icon-md" alt="Payment Method">
                    </div>
                    <div class="stat-meta">
                        <h3>Metode Terbanyak</h3>
                        <p class="stat-value"><?= $metode_terbanyak ? ucfirst($metode_terbanyak['metode_pembayaran']) : '-' ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <img src="../assets/icons/clock.png" class="icon-md" alt="Timer">
                    </div>
                    <div class="stat-meta">
                        <h3>Rata Waktu Verifikasi</h3>
                        <p class="stat-value"><?= $rata_waktu_verifikasi ? round($rata_waktu_verifikasi) . ' menit' : '-' ?></p>
                    </div>
                </div>
            </div>

            <!-- Grafik -->
            <div class="statistics-charts">
                <div class="chart-card">
                    <div class="chart-header">
                        <h2>
                            <img src="../assets/icons/graph.png" class="icon-sm" alt="Graph">
                            Pembayaran 6 Bulan Terakhir
                        </h2>
                        <div class="chart-legend-hint">Nominal vs Jumlah Transaksi</div>
                    </div>
                    <div class="chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Aktivitas Terakhir -->
            <div class="recent-activities">
                <div class="activities-header">
                    <h2>
                        <img src="../assets/icons/report.png" class="icon-sm" alt="Report">
                        Aktivitas Terakhir
                    </h2>
                </div>
                <div class="table-responsive">
                    <table class="nice-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Waktu</th>
                                <th>Pengguna</th>
                                <th>Aktivitas</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($activities)): ?>
                                <tr>
                                    <td colspan="5" class="empty-row">
                                        <img src="../assets/icons/info.png" class="icon-sm" alt="Info">
                                        Tidak ada aktivitas
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activities as $i => $act): ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td><?= date('d M Y H:i', strtotime($act['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($act['username'] ?: 'System') ?></td>
                                    <td><?= htmlspecialchars($act['activity']) ?></td>
                                    <td><?= htmlspecialchars($act['ip_address']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php include_once '../includes/footer.php'; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Util format rupiah
    function formatRupiah(n){
        try{
            return 'Rp ' + (n ?? 0).toLocaleString('id-ID');
        }catch(e){
            return 'Rp ' + String(n).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('paymentChart');
        const ctx = el.getContext('2d');

        // Gradien ungu-biru
        const gradient = ctx.createLinearGradient(0, 0, 0, el.height);
        gradient.addColorStop(0, 'rgba(108, 99, 255, 0.28)'); // ungu
        gradient.addColorStop(1, 'rgba(73, 169, 255, 0.05)'); // biru

        const labels = <?= json_encode($labels) ?>;
        const nominal = <?= json_encode($data_nominal) ?>;
        const transaksi = <?= json_encode($data_transaksi) ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Total Pembayaran',
                        data: nominal,
                        backgroundColor: gradient,
                        borderColor: 'rgba(108, 99, 255, 1)',
                        borderWidth: 1.5,
                        yAxisID: 'y',
                        barThickness: 28,
                        order: 2
                    },
                    {
                        type: 'line',
                        label: 'Jumlah Transaksi',
                        data: transaksi,
                        borderColor: 'rgba(73, 169, 255, 1)',
                        backgroundColor: 'rgba(73, 169, 255, .12)',
                        tension: 0.35,
                        fill: false,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                        yAxisID: 'y1',
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        labels: { boxWidth: 14, boxHeight: 14 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context){
                                const label = context.dataset.label || '';
                                const val = context.parsed.y;
                                if(context.dataset.yAxisID === 'y'){
                                    return ` ${label}: ${formatRupiah(val)}`;
                                }
                                return ` ${label}: ${val}x`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(108, 99, 255, .08)' },
                        ticks: {
                            callback: (v)=>formatRupiah(v)
                        },
                        title: { display: true, text: 'Nominal (Rupiah)' }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            callback: (v)=>v + 'x'
                        },
                        title: { display: true, text: 'Jumlah Transaksi' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>
