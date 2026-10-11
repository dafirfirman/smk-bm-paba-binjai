<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';

requireRole(['bendahara']);

$page_title = "Dashboard Bendahara";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Hitung data ringkasan keuangan
$current_year = date('Y');
$current_month = date('m');

// Total pemasukan bulan ini (status 'lunas')
$stmt = $pdo->prepare("SELECT SUM(jumlah) as total FROM pembayaran 
                      WHERE status = 'lunas' 
                      AND YEAR(tanggal_verifikasi) = ? 
                      AND MONTH(tanggal_verifikasi) = ?");
$stmt->execute([$current_year, $current_month]);
$total_pemasukan = $stmt->fetch()['total'] ?? 0;

// Total yang belum bayar
$stmt = $pdo->query("SELECT COUNT(*) as total FROM tagihan 
                    WHERE status = 'aktif' 
                    AND tanggal_jatuh_tempo < CURDATE() 
                    AND id NOT IN (SELECT tagihan_id FROM pembayaran WHERE status = 'lunas')");
$total_belum_bayar = $stmt->fetch()['total'];

// Saldo rekening sekolah (contoh)
$saldo_rekening = 15000000; // Bisa diambil dari tabel khusus jika ada

// Jumlah siswa
$stmt = $pdo->query("SELECT COUNT(*) as total FROM siswa");
$jumlah_siswa = $stmt->fetch()['total'];

// Jumlah kelas
$stmt = $pdo->query("SELECT COUNT(DISTINCT kelas) as total FROM siswa");
$jumlah_kelas = $stmt->fetch()['total'];

// Tahun ajaran aktif
$tahun_ajaran = "2023/2024"; // Bisa diambil dari tabel setting jika ada

// Ambil data pembayaran pending untuk tabel
$stmt = $pdo->query("SELECT p.*, s.nama_lengkap, s.kelas, s.jurusan, t.nama_tagihan 
                     FROM pembayaran p
                     JOIN siswa s ON p.siswa_id = s.id
                     JOIN tagihan t ON p.tagihan_id = t.id
                     WHERE p.status = 'pending'
                     ORDER BY p.tanggal_pembayaran DESC LIMIT 10");
$pembayaran_pending = $stmt->fetchAll();

// Ambil data status pembayaran siswa
$stmt = $pdo->query("SELECT p.*, s.nama_lengkap, s.kelas, s.jurusan, t.nama_tagihan,
                     CASE WHEN p.status = 'lunas' THEN 'Lunas'
                          WHEN p.status = 'pending' THEN 'Menunggu Verifikasi'
                          WHEN p.status = 'ditolak' THEN 'Ditolak'
                          ELSE 'Belum Bayar' END as status_pembayaran
                     FROM pembayaran p
                     JOIN siswa s ON p.siswa_id = s.id
                     JOIN tagihan t ON p.tagihan_id = t.id
                     ORDER BY p.tanggal_pembayaran DESC LIMIT 10");
$status_pembayaran = $stmt->fetchAll();

// Status verifikasi pembayaran (ringkasan)
$stmt = $pdo->query("SELECT status, COUNT(*) as total FROM pembayaran GROUP BY status");
$status_verifikasi = $stmt->fetchAll();

// Pencarian username di tabel users
$search_username = $_GET['username'] ?? '';
$search_results = [];
if (!empty($search_username)) {
    $search_query = "%$search_username%";
    $stmt = $pdo->prepare("SELECT id, username, email, role, status, last_login FROM users WHERE username LIKE ? AND deleted_at IS NULL ORDER BY username LIMIT 50");
    $stmt->execute([$search_query]);
    $search_results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/css/style_dashboard_bendahara.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
    <style>
        /* Tambahan styling untuk hasil pencarian */
        .search-container {
            margin-bottom: 20px;
        }
        .search-results {
            margin-top: 20px;
        }
        .search-results h3 {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <?php include_once '../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div class="header-title">
                <h1>Dashboard Bendahara</h1>
                <p class="subtitle">Ringkasan keuangan dan pembayaran terbaru</p>
            </div>
        </div>

        <!-- Form Pencarian Username -->
        <div class="search-container">
            <form action="" method="GET">
                <label for="username">Cari Username:</label>
                <input type="text" name="username" id="username" placeholder="Masukkan username" value="<?= htmlspecialchars($search_username) ?>" required />
                <button type="submit" class="btn btn-search"><i class="fas fa-search"></i> Cari</button>
            </form>
        </div>

        <?php if ($search_username !== ''): ?>
        <div class="search-results">
            <h3>Hasil Pencarian untuk: <em><?= htmlspecialchars($search_username) ?></em></h3>
            <?php if (count($search_results) > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered data-table" id="user-search-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td><?= htmlspecialchars($user['status']) ?></td>
                            <td><?= $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p>Tidak ditemukan user dengan username tersebut.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card income">
        <div class="card-icon">
            <img src="../assets/icons/coin.png" alt="Total Pemasukan" class="icon" />
        </div>
        <div class="card-details">
            <span class="card-title">Total Pemasukan</span>
            <span class="card-value">Rp <?= number_format($total_pemasukan, 0, ',', '.') ?></span>
            <span class="card-info">
                <img src="../assets/icons/up-arrow.png" alt="Bulan ini" class="icon-sm" /> Bulan ini
            </span>
        </div>
        </div>

        <div class="summary-card pending">
            <div class="card-icon">
                <img src="../assets/icons/exclamation-mark-in-a-circle.png" alt="Belum Bayar" class="icon" />
            </div>
            <div class="card-details">
                <span class="card-title">Belum Bayar</span>
                <span class="card-value"><?= $total_belum_bayar ?> Tagihan</span>
                <span class="card-info">
                    <img src="../assets/icons/clock.png" alt="Perlu tindakan" class="icon-sm" /> Perlu tindakan
                </span>
            </div>
        </div>

        <div class="summary-card balance">
            <div class="card-icon">
                <img src="../assets/icons/mobile-banking.png" alt="Saldo Rekening" class="icon" />
            </div>
            <div class="card-details">
                <span class="card-title">Saldo Rekening</span>
                <span class="card-value">Rp <?= number_format($saldo_rekening, 0, ',', '.') ?></span>
                <span class="card-info">
                    <img src="../assets/icons/mortarboard.png" alt="Rekening Sekolah" class="icon-sm" /> Rekening Sekolah
                </span>
            </div>
        </div>

        <div class="summary-card students">
        <div class="card-icon">
            <img src="../assets/icons/student.png" alt="Jumlah Siswa" class="icon" />
        </div>
        <div class="card-details">
            <span class="card-title">Jumlah Siswa</span>
            <span class="card-value"><?= $jumlah_siswa ?> Siswa</span>
            <span class="card-info">
                <img src="../assets/icons/all.png" alt="Jumlah Kelas" class="icon-sm" /> <?= $jumlah_kelas ?> Kelas
            </span>
        </div>
    </div>
</div>

        <!-- Dua Kolom Utama -->
        <div class="content-columns">
            <!-- Kolom Kiri -->
            <div class="content-col">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Status Pembayaran Siswa</h3>
                        <div class="card-tools">
                            <!-- <select class="form-select" id="filter-kelas">
                                <option value="">Semua Kelas</option>
                                <?php
                                $stmt = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas");
                                while ($row = $stmt->fetch()) {
                                    echo "<option value='" . htmlspecialchars($row['kelas']) . "'>" . htmlspecialchars($row['kelas']) . "</option>";
                                }
                                ?>
                            </select> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table" id="payment-status-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Tagihan</th>
                                        <th>Nominal</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($status_pembayaran as $key => $pembayaran): ?>
                                    <tr>
                                        <td><?= $key + 1 ?></td>
                                        <td><?= htmlspecialchars($pembayaran['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($pembayaran['kelas']) ?></td>
                                        <td><?= htmlspecialchars($pembayaran['nama_tagihan']) ?></td>
                                        <td>Rp <?= number_format($pembayaran['jumlah'], 0, ',', '.') ?></td>
                                        <td>
                                            <span class="status-badge <?= 
                                                $pembayaran['status'] == 'lunas' ? 'success' : 
                                                ($pembayaran['status'] == 'pending' ? 'warning' : 
                                                ($pembayaran['status'] == 'ditolak' ? 'danger' : 'secondary')) 
                                            ?>">
                                                <?= htmlspecialchars($pembayaran['status_pembayaran']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom kebawah -->
            <div class="content-col">
                <div class="content-card">
                    <div class="card-header">
                        <h3>Verifikasi Pembayaran</h3>
                        <span class="badge"><?= count($pembayaran_pending) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pembayaran_pending)): ?>
                        <div class="table-responsive">
                            <table class="data-table" id="verification-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Nama Siswa</th>
                                        <th>Kelas</th>
                                        <th>Nominal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pembayaran_pending as $pembayaran): ?>
                                    <tr>
                                        <td>
                                            <div class="student-info">
                                                <div class="student-name"><?= htmlspecialchars($pembayaran['nama_lengkap']) ?></div>
                                                <div class="student-class"><?= htmlspecialchars($pembayaran['kelas']) ?></div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($pembayaran['kelas']) ?></td>
                                        <td>Rp <?= number_format($pembayaran['jumlah'], 0, ',', '.') ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="verifikasi_pembayaran.php?id=<?= $pembayaran['id'] ?>" class="btn btn-verify">
                                                    <i class="fas fa-check"></i> Verifikasi
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>Tidak ada pembayaran yang menunggu verifikasi</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>           
            </div>
        </div>
    </main>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(document).ready(function() {
    $('#payment-status-table').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        }
    });
    $('#verification-table').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        }
    });
    $('#user-search-table').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        }
    });

    // Chart verifikasi pembayaran
    const ctx = document.getElementById('verification-chart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php foreach ($status_verifikasi as $status): ?>
                '<?= 
                    $status['status'] == 'lunas' ? 'Lunas' : 
                    ($status['status'] == 'pending' ? 'Pending' : 
                    ($status['status'] == 'ditolak' ? 'Ditolak' : ucfirst($status['status'])))
                ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($status_verifikasi as $status): ?>
                    <?= $status['total'] ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    '#1cc88a', '#f6c23e', '#e74a3b', '#858796'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>
</body>
</html>
<?php include_once '../includes/footer.php'; ?>
