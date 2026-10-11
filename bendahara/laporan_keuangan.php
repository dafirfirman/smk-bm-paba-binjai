<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/koneksi.php';
require_once '../config/functions.php';
requireRole(['bendahara']); // hanya bendahara
// ---------- Helper ----------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
// CSRF token init
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf_token'];
// ---------- Judul Halaman ----------
$page_title = "Laporan Keuangan";
// ---------- Ambil & validasi filter ----------
$bulan  = isset($_GET['bulan'])  ? (int)$_GET['bulan']  : (int)date('m');
$tahun  = isset($_GET['tahun'])  ? (int)$_GET['tahun']  : (int)date('Y');
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
if ($bulan < 1 || $bulan > 12) {
    $bulan = (int)date('m');
}
if ($tahun < 2000 || $tahun > 2100) {
    $tahun = (int)date('Y');
}
// ---------- Hapus pembayaran (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $postToken   = $_POST['csrf_token'] ?? '';
    $delete_id   = isset($_POST['delete_id']) ? (int)$_POST['delete_id'] : 0;
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $postToken)) {
        $_SESSION['error'] = "Token keamanan tidak valid.";
        header("Location: laporan_keuangan.php");
        exit();
    }
    if ($delete_id <= 0) {
        $_SESSION['error'] = "ID pembayaran tidak valid.";
        header("Location: laporan_keuangan.php");
        exit();
    }
    try {
        $pdo->beginTransaction();
        // Ambil data pembayaran untuk log
        $stmt = $pdo->prepare("SELECT * FROM pembayaran WHERE id = :id");
        $stmt->execute([':id' => $delete_id]);
        $payment = $stmt->fetch();
        if (!$payment) {
            $pdo->rollBack();
            $_SESSION['error'] = "Data pembayaran tidak ditemukan.";
            header("Location: laporan_keuangan.php");
            exit();
        }
        // Hapus pembayaran
        $del = $pdo->prepare("DELETE FROM pembayaran WHERE id = :id");
        $del->execute([':id' => $delete_id]);
        // Log activity
        $action = sprintf(
            "Menghapus pembayaran ID: %d - Siswa ID: %s - Jumlah: %s",
            $delete_id,
            (string)$payment['siswa_id'],
            (string)$payment['jumlah']
        );
        logActivity($pdo, $_SESSION['user']['id'], $action);
        $pdo->commit();
        $_SESSION['success'] = "Pembayaran berhasil dihapus.";
        header("Location: laporan_keuangan.php");
        exit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "Gagal menghapus pembayaran: " . $e->getMessage();
        header("Location: laporan_keuangan.php");
        exit();
    }
}
// ---------- Query: Ringkasan Bulanan ----------
try {
    $sqlSummary = "
        SELECT 
            MONTH(tanggal_verifikasi) AS bulan,
            COUNT(*)                 AS jumlah_siswa,
            AVG(jumlah)              AS rata_rata,
            SUM(jumlah)              AS total_pemasukan
        FROM pembayaran
        WHERE status = 'lunas'
          AND YEAR(tanggal_verifikasi) = :tahun
    ";
    $paramsSummary = [':tahun' => $tahun];
    if (!empty($_GET['bulan'])) { // jika user memilih bulan spesifik
        $sqlSummary .= " AND MONTH(tanggal_verifikasi) = :bulan";
        $paramsSummary[':bulan'] = $bulan;
    }
    $sqlSummary .= " GROUP BY MONTH(tanggal_verifikasi)
                     ORDER BY MONTH(tanggal_verifikasi)";
    $stmt = $pdo->prepare($sqlSummary);
    foreach ($paramsSummary as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
    $stmt->execute();
    $laporan_bulanan = $stmt->fetchAll();
} catch (Throwable $e) {
    $laporan_bulanan = [];
    $_SESSION['error'] = "Gagal memuat ringkasan bulanan: " . $e->getMessage();
}
// ---------- Query: Pembayaran Siswa (detail) ----------
try {
    $sqlStudents = "
        SELECT 
            p.*, 
            s.nama_lengkap, s.kelas, s.jurusan, s.nisn, 
            t.nama_tagihan
        FROM pembayaran p
        JOIN siswa   s ON p.siswa_id = s.id
        JOIN tagihan t ON p.tagihan_id = t.id
        WHERE p.status = 'lunas'
          AND YEAR(p.tanggal_verifikasi) = :tahun
    ";
    $paramsStudents = [':tahun' => $tahun];
    if (!empty($_GET['bulan'])) {
        $sqlStudents .= " AND MONTH(p.tanggal_verifikasi) = :bulan";
        $paramsStudents[':bulan'] = $bulan;
    }
    if ($search !== '') {
        $sqlStudents .= " AND (s.nama_lengkap LIKE :search OR s.nisn LIKE :search)";
        $paramsStudents[':search'] = "%{$search}%";
    }
    $sqlStudents .= " ORDER BY p.tanggal_verifikasi DESC";
    $stmt_siswa = $pdo->prepare($sqlStudents);
    foreach ($paramsStudents as $k => $v) {
        $type = ($k === ':search') ? PDO::PARAM_STR : PDO::PARAM_INT;
        $stmt_siswa->bindValue($k, $v, $type);
    }
    $stmt_siswa->execute();
    $pembayaran_siswa = $stmt_siswa->fetchAll();
} catch (Throwable $e) {
    $pembayaran_siswa = [];
    $_SESSION['error'] = "Gagal memuat pembayaran siswa: " . $e->getMessage();
}
// ---------- Data Chart ----------
$chartLabels = [];
$chartValues = [];
foreach ($laporan_bulanan as $item) {
    $bln = (int)$item['bulan'];
    $chartLabels[] = date('M', mktime(0, 0, 0, $bln, 1));
    $chartValues[] = (float)$item['total_pemasukan'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= h($page_title) ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/laporan_keuangan.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"/>
    <!-- Scripts (Chart) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include_once '../includes/navbar.php'; ?>
    <div class="dashboard-container">
        <?php include_once '../includes/sidebar.php'; ?>
        <main class="main-content" role="main">
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= h($_SESSION['success']); unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= h($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>
            <div class="page-header">
                <h1><?= h($page_title) ?></h1>
                <div class="header-actions">
                    <button type="button" class="btn btn-primary no-print" onclick="window.print()">
                        <i class="fas fa-print"></i> Cetak Laporan
                    </button>
                </div>
            </div>
            <!-- Filter -->
            <section class="filter-section no-print" aria-label="Filter laporan keuangan">
                <form method="GET" class="filter-form" action="laporan_keuangan.php">
                    <div class="form-group">
                        <label for="bulan">Bulan</label>
                        <select id="bulan" name="bulan">
                            <option value="">Semua Bulan</option>
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= ($bulan === $i && isset($_GET['bulan']) && $_GET['bulan'] !== '') ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tahun">Tahun</label>
                        <select id="tahun" name="tahun">
                            <?php for ($i = (int)date('Y') - 2; $i <= (int)date('Y') + 2; $i++): ?>
                                <option value="<?= $i ?>" <?= $tahun === $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="search">Cari Siswa/NISN</label>
                        <input type="text" id="search" name="search" placeholder="Nama atau NISN" value="<?= h($search) ?>" />
                    </div>
                    <div class="form-group" style="align-self:end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="laporan_keuangan.php" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </div>
                </form>
            </section>
            <!-- Ringkasan Bulanan -->
            <section class="card" aria-labelledby="ringkasanTitle">
                <div class="card-header">
                    <h3 id="ringkasanTitle">Ringkasan Bulanan (<?= h((string)$tahun) ?><?= isset($_GET['bulan']) && $_GET['bulan'] !== '' ? ' - ' . date('F', mktime(0,0,0,$bulan,1)) : '' ?>)</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table" aria-describedby="ringkasanTitle">
                            <thead>
                                <tr>
                                    <th>Bulan</th>
                                    <th>Jumlah Siswa Bayar</th>
                                    <th>Rata-rata per Siswa</th>
                                    <th>Total Pemasukan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($laporan_bulanan)): ?>
                                    <?php foreach ($laporan_bulanan as $item): ?>
                                        <tr>
                                            <td><?= h(date('F', mktime(0, 0, 0, (int)$item['bulan'], 1))) ?></td>
                                            <td><?= (int)$item['jumlah_siswa'] ?></td>
                                            <td>Rp <?= number_format((float)$item['rata_rata'], 0, ',', '.') ?></td>
                                            <td>Rp <?= number_format((float)$item['total_pemasukan'], 0, ',', '.') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Tidak ada data untuk ditampilkan</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <!-- Status Pembayaran Siswa -->
            <section class="card" aria-labelledby="statusTitle">
                <div class="card-header">
                    <h3 id="statusTitle">Status Pembayaran Siswa</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="data-table" aria-describedby="statusTitle">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Siswa</th>
                                    <th>NISN</th>
                                    <th>Kelas</th>
                                    <th>Jurusan</th>
                                    <th>Tagihan</th>
                                    <th>Nominal</th>
                                    <th>Tanggal Bayar</th>
                                    <th class="no-print">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pembayaran_siswa)): ?>
                                    <?php foreach ($pembayaran_siswa as $idx => $item): ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td><?= h($item['nama_lengkap']) ?></td>
                                            <td><?= h($item['nisn']) ?></td>
                                            <td><?= h($item['kelas']) ?></td>
                                            <td><?= h($item['jurusan']) ?></td>
                                            <td><?= h($item['nama_tagihan']) ?></td>
                                            <td>Rp <?= number_format((float)$item['jumlah'], 0, ',', '.') ?></td>
                                            <td><?= h(date('d M Y H:i', strtotime($item['tanggal_verifikasi']))) ?></td>
                                            <td class="no-print">
                                                <form method="POST" onsubmit="return false;" class="inline-form">
                                                    <input type="hidden" name="csrf_token" value="<?= h($CSRF) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="delete_id" value="<?= (int)$item['id'] ?>">
                                                    <button type="button" class="btn btn-danger btn-delete" onclick="confirmDelete(this)">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Tidak ada data untuk ditampilkan</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            
            <!-- Grafik (tidak akan dicetak) -->
            <div class="chart-wrapper no-print">
                <section class="chart-section">
                    <div class="chart-container">
                        <canvas id="monthlyChart" aria-label="Grafik Pemasukan Bulanan" role="img"></canvas>
                    </div>
                </section>
            </div>
            
            <!-- Tanda Tangan Kepala Sekolah (hanya muncul saat cetak) -->
            <div class="signature-section only-print">
                <div class="signature-container">
                    <div class="signature-place">
                        <p>Mengetahui,</p>
                        <p>Kepala Sekolah</p>
                        <div class="signature-name">
                            <p>&nbsp;</p>
                            <p><strong>(_________________________)</strong></p>
                            <p>NIP. _________________________</p>
                        </div>
                    </div>
                    <div class="signature-date">
                        <p><?= h(date('d F Y')) ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- Modal Konfirmasi Hapus -->
    <div id="confirmationDialog" class="confirmation-dialog no-print" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
        <div class="confirmation-dialog__content">
            <h4 id="confirmTitle">Konfirmasi</h4>
            <p>Apakah Anda yakin ingin menghapus pembayaran ini?</p>
            <div class="confirmation-buttons">
                <button type="button" class="btn btn-secondary" onclick="cancelDelete()">Batal</button>
                <button type="button" class="btn btn-danger" onclick="proceedDelete()">Hapus</button>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script>
    // Chart data dari PHP
    const chartLabels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const chartValues = <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE) ?>;
    window.addEventListener('DOMContentLoaded', () => {
        // Render chart
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Total Pemasukan',
                    data: chartValues,
                    backgroundColor: 'rgba(78, 115, 223, 0.5)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1,
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title:  { display: true, text: 'Grafik Pemasukan Bulanan' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (val) => 'Rp ' + Number(val).toLocaleString('id-ID')
                        }
                    }
                }
            }
        });
    });
    // Hapus (modal konfirmasi)
    let currentForm = null;
    function confirmDelete(buttonEl) {
        currentForm = buttonEl.closest('form');
        const dlg = document.getElementById('confirmationDialog');
        dlg.style.display = 'flex';
        dlg.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function cancelDelete() {
        const dlg = document.getElementById('confirmationDialog');
        dlg.style.display = 'none';
        dlg.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = 'auto';
        currentForm = null;
    }
    function proceedDelete() {
        if (!currentForm) return;
        // Buat form submit POST nyata agar aman
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'laporan_keuangan.php';
        // Copy field yang diperlukan
        for (const [name, value] of [
            ['csrf_token', currentForm.querySelector('input[name="csrf_token"]').value],
            ['action',      currentForm.querySelector('input[name="action"]').value],
            ['delete_id',   currentForm.querySelector('input[name="delete_id"]').value],
        ]) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
    }
    </script>
</body>
</html>