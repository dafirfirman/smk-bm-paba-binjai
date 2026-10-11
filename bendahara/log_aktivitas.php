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
function isValidDate(?string $date, string $format = 'Y-m-d'): bool {
    if (!$date) return false;
    $dt = DateTime::createFromFormat($format, $date);
    return $dt && $dt->format($format) === $date;
}

// ---------- Page Meta ----------
$page_title = "Log Aktivitas Bendahara";

// ---------- Filter ----------
$tanggal   = isset($_GET['tanggal']) ? trim((string)$_GET['tanggal']) : '';
$aktivitas = isset($_GET['aktivitas']) ? trim((string)$_GET['aktivitas']) : '';

if ($tanggal !== '' && !isValidDate($tanggal, 'Y-m-d')) {
    // Jika format tidak valid, kosongkan agar tidak error SQL
    $tanggal = '';
}

// ---------- Query ----------
$sql = "
    SELECT l.id, l.activity, l.ip_address, l.created_at, u.username
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE u.role = 'bendahara'
";
$params = [];

if ($tanggal !== '') {
    $sql .= " AND DATE(l.created_at) = :tanggal";
    $params[':tanggal'] = $tanggal;
}
if ($aktivitas !== '') {
    $sql .= " AND l.activity LIKE :aktivitas";
    $params[':aktivitas'] = "%{$aktivitas}%";
}

$sql .= " ORDER BY l.created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':aktivitas' ? PDO::PARAM_STR : PDO::PARAM_STR);
    }
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (Throwable $e) {
    $logs = [];
    $_SESSION['error'] = "Gagal memuat log aktivitas: " . $e->getMessage();
}

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= h($page_title) ?></title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

    <!-- Styles -->
    <link rel="stylesheet" href="../assets/css/style_log_aktivitas.css"/>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"/>

    <!-- (Opsional) Bootstrap bila dibutuhkan komponen bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"/>
</head>
<body>
<div class="dashboard-container">
    <?php include_once '../includes/sidebar.php'; ?>

    <main class="content-wrapper" role="main">
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

        <header class="page-header">
            <div class="title-wrap">
                <h1><i class="fas fa-history"></i> <?= h($page_title) ?></h1>
                <p class="subtitle">Riwayat aktivitas sistem oleh bendahara</p>
            </div>
        </header>

        <!-- Filter -->
        <section class="filter-section no-print" aria-label="Filter log aktivitas">
            <form method="GET" class="filter-form" action="log_aktivitas.php">
                <div class="form-group">
                    <label for="tanggal"><i class="fas fa-calendar-alt"></i> Tanggal</label>
                    <input type="date" id="tanggal" name="tanggal" value="<?= h($tanggal) ?>" />
                </div>

                <div class="form-group">
                    <label for="aktivitas"><i class="fas fa-tasks"></i> Aktivitas</label>
                    <input type="text" id="aktivitas" name="aktivitas" placeholder="Kata kunci aktivitas" value="<?= h($aktivitas) ?>" />
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="log_aktivitas.php" class="btn btn-secondary"><i class="fas fa-sync-alt"></i> Reset</a>
                </div>
            </form>
        </section>

        <!-- Tabel Log -->
        <section class="card log-card" aria-labelledby="tabelLogTitle">
            <div class="card-header">
                <h3 id="tabelLogTitle">Tabel Log Aktivitas</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover data-table" id="logTable" aria-describedby="tabelLogTitle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Username</th>
                                <th>Aktivitas</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $i => $log): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td><?= h(date('d M Y H:i:s', strtotime($log['created_at']))) ?></td>
                                        <td><?= h($log['username'] ?? 'System') ?></td>
                                        <td><?= h($log['activity']) ?></td>
                                        <td><?= h($log['ip_address']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center empty-row">
                                        <i class="fas fa-info-circle"></i> Tidak ada data log untuk ditampilkan
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function() {
    $('#logTable').DataTable({
        responsive: true,
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' },
        dom: '<"top"f>rt<"bottom"lip><"clear">',
        pageLength: 10,
        order: [[1, 'desc']]
    });
});
</script>
</body>
</html>
