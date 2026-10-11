<?php
// kelola_tagihan.php
require_once '../config/koneksi.php';
require_once '../config/functions.php';

requireRole(['bendahara']);

$page_title = "Kelola Tagihan";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

// ====== CSRF TOKEN ======
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Filter parameter dengan sanitization
$kelas  = isset($_GET['kelas'])  ? trim($_GET['kelas'])  : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$bulan  = isset($_GET['bulan'])  ? (int)$_GET['bulan']   : (int)date('m');
$tahun  = isset($_GET['tahun'])  ? (int)$_GET['tahun']   : (int)date('Y');
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validasi bulan dan tahun
if ($bulan < 1 || $bulan > 12) $bulan = (int)date('m');
if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');

// Query utama - Ambil semua siswa dan status pembayaran mereka
$query = "SELECT 
            s.id as siswa_id,
            s.nama_lengkap, 
            s.nisn,
            s.kelas, 
            s.jurusan,
            t.id as tagihan_id,
            t.nama_tagihan, 
            t.kategori,
            t.jumlah, 
            t.tanggal_jatuh_tempo,
            p.id as pembayaran_id,
            p.status as status_pembayaran,
            p.tanggal_pembayaran,
            p.tanggal_verifikasi,
            p.metode_pembayaran,
            p.bukti_pembayaran,
            p.catatan as catatan_pembayaran,
            CASE 
                WHEN p.status = 'lunas' THEN 'Lunas'
                WHEN p.status = 'pending' THEN 'Menunggu Verifikasi'
                WHEN p.status = 'ditolak' THEN 'Ditolak'
                WHEN p.id IS NULL AND t.tanggal_jatuh_tempo < CURDATE() THEN 'Terlambat'
                WHEN p.id IS NULL THEN 'Belum Bayar'
                ELSE 'Status Tidak Dikenal'
            END as status_pembayaran_text
          FROM siswa s
          LEFT JOIN tagihan t ON s.id = t.siswa_id AND t.status = 'aktif'
          LEFT JOIN pembayaran p ON t.id = p.tagihan_id AND p.siswa_id = s.id
          WHERE 1=1";

$params = [];

if (!empty($kelas)) {
    $query .= " AND s.kelas = :kelas";
    $params[':kelas'] = $kelas;
}

if (!empty($status)) {
    if ($status == 'lunas') {
        $query .= " AND p.status = 'lunas'";
    } elseif ($status == 'pending') {
        $query .= " AND p.status = 'pending'";
    } elseif ($status == 'ditolak') {
        $query .= " AND p.status = 'ditolak'";
    } elseif ($status == 'belum') {
        $query .= " AND p.id IS NULL";
    } elseif ($status == 'terlambat') {
        $query .= " AND p.id IS NULL AND t.tanggal_jatuh_tempo < CURDATE()";
    }
}

if (!empty($bulan) && !empty($tahun)) {
    $query .= " AND MONTH(t.tanggal_jatuh_tempo) = :bulan AND YEAR(t.tanggal_jatuh_tempo) = :tahun";
    $params[':bulan'] = $bulan;
    $params[':tahun'] = $tahun;
}

if (!empty($search)) {
    $query .= " AND (s.nama_lengkap LIKE :search OR s.nisn LIKE :search OR t.nama_tagihan LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY s.nama_lengkap ASC, t.tanggal_jatuh_tempo DESC";

try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $tagihan = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error query tagihan: " . $e->getMessage());
    $tagihan = [];
}

// Dapatkan daftar kelas untuk filter
try {
    $stmt = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas");
    $kelas_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error query kelas: " . $e->getMessage());
    $kelas_list = [];
}

// Hitung statistik
$total_siswa = count(array_unique(array_column($tagihan, 'siswa_id')));
$lunas = $pending = $belum_bayar = $terlambat = $ditolak = 0;

foreach ($tagihan as $item) {
    switch ($item['status_pembayaran_text']) {
        case 'Lunas': $lunas++; break;
        case 'Menunggu Verifikasi': $pending++; break;
        case 'Belum Bayar': $belum_bayar++; break;
        case 'Terlambat': $terlambat++; break;
        case 'Ditolak': $ditolak++; break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/style_kelola_tagihan.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <style>
        /* Sedikit kosmetik untuk tombol hapus & modal */
        .btn-action { display:inline-flex; align-items:center; gap:6px; padding:.35rem .6rem; border-radius:8px; text-decoration:none; font-size:.875rem; }
        .btn-edit   { background:#eef6ff; color:#1162ff; border:1px solid #dbe9ff; }
        .btn-delete { background:#ffecec; color:#d6293e; border:1px solid #ffd4d8; }
        .btn-delete:hover { filter:brightness(.98); }
        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,.35);
            display: none; align-items: center; justify-content: center; z-index: 9999;
        }
        .modal-box {
            background:#fff; border-radius:12px; max-width:440px; width:92%; padding:20px; box-shadow:0 20px 50px rgba(0,0,0,.15);
        }
        .modal-box h3 { margin:0 0 6px; }
        .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }
        .btn { border:none; border-radius:10px; padding:.55rem .9rem; cursor:pointer; }
        .btn-danger { background:#e53935; color:#fff; }
        .btn-light  { background:#f1f1f1; color:#333; }
        
        /* Alert styles - konsisten dengan style_tambah_tagihan.css */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            box-shadow: 0 4px 12px rgb(6 95 70 / 0.15);
        }
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            box-shadow: 0 4px 12px rgb(231 76 60 / 0.15);
        }
        .badge { background:#eef0f6; color:#333; border-radius:999px; padding:.25rem .6rem; font-size:.8rem; }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <!-- Sidebar -->
    <?php include_once '../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div class="header-title">
                <h1>Kelola Tagihan</h1>
                <p class="subtitle">Manajemen tagihan siswa</p>
            </div>
            <div class="header-actions">
                <a href="tambah_tagihan.php" class="btn-add">
                    <i class="fas fa-plus"></i> Tambah Tagihan
                </a>
            </div>
        </div>

        <!-- Notification Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" id="successAlert">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success_message'] ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error_message'] ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Statistik -->
        <div class="stats-row">
            <div class="stat-card success">
                <h4>Lunas</h4>
                <div class="stat-value"><?= $lunas ?></div>
                <div class="stat-percentage"><?= $total_siswa > 0 ? round(($lunas/$total_siswa)*100, 1) : 0 ?>%</div>
            </div>
            <div class="stat-card warning">
                <h4>Menunggu Verifikasi</h4>
                <div class="stat-value"><?= $pending ?></div>
                <div class="stat-percentage"><?= $total_siswa > 0 ? round(($pending/$total_siswa)*100, 1) : 0 ?>%</div>
            </div>
            <div class="stat-card danger">
                <h4>Belum Bayar</h4>
                <div class="stat-value"><?= $belum_bayar ?></div>
                <div class="stat-percentage"><?= $total_siswa > 0 ? round(($belum_bayar/$total_siswa)*100, 1) : 0 ?>%</div>
            </div>
            <div class="stat-card info">
                <h4>Terlambat</h4>
                <div class="stat-value"><?= $terlambat ?></div>
                <div class="stat-percentage"><?= $total_siswa > 0 ? round(($terlambat/$total_siswa)*100, 1) : 0 ?>%</div>
            </div>
            <div class="stat-card secondary">
                <h4>Ditolak</h4>
                <div class="stat-value"><?= $ditolak ?></div>
                <div class="stat-percentage"><?= $total_siswa > 0 ? round(($ditolak/$total_siswa)*100, 1) : 0 ?>%</div>
            </div>
        </div>

        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Cari</label>
                    <input type="text" id="search" name="search" placeholder="Nama/NISN/Tagihan" value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="form-group">
                    <label for="kelas">Kelas</label>
                    <select id="kelas" name="kelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelas_list as $kls): ?>
                            <option value="<?= htmlspecialchars($kls['kelas']) ?>" <?= $kelas == $kls['kelas'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kls['kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="lunas"     <?= $status == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                        <option value="pending"   <?= $status == 'pending' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                        <option value="ditolak"   <?= $status == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                        <option value="belum"     <?= $status == 'belum' ? 'selected' : '' ?>>Belum Bayar</option>
                        <option value="terlambat" <?= $status == 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="bulan">Bulan</label>
                        <select id="bulan" name="bulan" class="form-select">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= (int)$bulan === $i ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tahun">Tahun</label>
                        <select id="tahun" name="tahun" class="form-select">
                            <?php for ($i = (int)date('Y') - 2; $i <= (int)date('Y') + 2; $i++): ?>
                                <option value="<?= $i ?>" <?= (int)$tahun === $i ? 'selected' : '' ?>><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="kelola_tagihan.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h3>Daftar Tagihan</h3>
                <div class="card-tools">
                    <span class="badge"><?= $total_siswa ?> Siswa</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($tagihan) > 0): ?>
                <div class="table-responsive">
                    <table class="data-table" id="invoices-table">
                        <thead>
                        <tr>
                            <th>No</th>
                            <th>Siswa</th>
                            <th>NISN</th>
                            <th>Kelas</th>
                            <th>Tagihan</th>
                            <th>Jatuh Tempo</th>
                            <th>Nominal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php 
                        $current_student = null;
                        $row_count = 0;
                        foreach ($tagihan as $key => $item): 
                            // Pastikan kita hanya menampilkan satu baris per siswa-tagihan
                            $unique_key = $item['siswa_id'] . '-' . $item['tagihan_id'];
                            if (isset($displayed[$unique_key])) continue;
                            $displayed[$unique_key] = true;
                            
                            if ($current_student != $item['siswa_id']) {
                                $current_student = $item['siswa_id'];
                                $row_count++;
                            }
                        ?>
                        <tr data-tagihan-id="<?= (int)($item['tagihan_id'] ?? 0) ?>">
                            <td><?= $row_count ?></td>
                            <td><?= htmlspecialchars($item['nama_lengkap']) ?></td>
                            <td><?= htmlspecialchars($item['nisn']) ?></td>
                            <td><?= htmlspecialchars($item['kelas']) ?></td>
                            <td>
                                <div class="invoice-info">
                                    <div class="invoice-name"><?= htmlspecialchars($item['nama_tagihan'] ?? 'Tidak ada tagihan') ?></div>
                                    <div class="invoice-meta"><?= htmlspecialchars($item['kategori'] ?? '') ?></div>
                                </div>
                            </td>
                            <td><?= !empty($item['tanggal_jatuh_tempo']) ? date('d M Y', strtotime($item['tanggal_jatuh_tempo'])) : '-' ?></td>
                            <td><?= !empty($item['jumlah']) ? 'Rp ' . number_format($item['jumlah'], 0, ',', '.') : '-' ?></td>
                            <td>
                                <span class="status-badge <?= 
                                    ($item['status_pembayaran_text'] == 'Lunas') ? 'success' : 
                                    (($item['status_pembayaran_text'] == 'Menunggu Verifikasi') ? 'warning' : 
                                    (($item['status_pembayaran_text'] == 'Ditolak') ? 'secondary' : 'danger')) 
                                ?>">
                                    <?= $item['status_pembayaran_text'] ?>
                                    <?php if ($item['status_pembayaran_text'] == 'Lunas' && !empty($item['tanggal_pembayaran'])): ?>
                                        <br><small><?= date('d M Y', strtotime($item['tanggal_pembayaran'])) ?></small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if (!empty($item['tagihan_id'])): ?>
                                        <a href="edit_tagihan.php?id=<?= (int)$item['tagihan_id'] ?>" class="btn btn-action btn-edit" title="Edit Tagihan">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button type="button" 
                                                class="btn btn-action btn-delete js-delete"
                                                title="Hapus Tagihan"
                                                data-id="<?= (int)$item['tagihan_id'] ?>"
                                                data-nama="<?= htmlspecialchars($item['nama_tagihan'] ?? 'Tagihan') ?>">
                                            <i class="fas fa-trash-alt"></i> Hapus
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <h3>Tidak ada data tagihan</h3>
                        <p>Tidak ditemukan tagihan yang sesuai dengan filter yang dipilih</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- ===== Modal Konfirmasi Hapus ===== -->
<div class="modal-backdrop" id="modalConfirm">
    <div class="modal-box">
        <h3>Hapus Tagihan</h3>
        <p id="modalText">Anda yakin ingin menghapus tagihan ini? Tindakan ini tidak dapat dibatalkan.</p>
        <div class="modal-actions">
            <button class="btn btn-light" id="btnCancel">Batal</button>
            <button class="btn btn-danger" id="btnConfirmDelete"><i class="fas fa-trash-alt"></i> Hapus</button>
        </div>
    </div>
</div>

<!-- CSRF untuk JS -->
<input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
$(function () {
    // Inisialisasi DataTable (opsional, sesuaikan)
    $('#invoices-table').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[1, 'asc']]
    });

    const $modal = $('#modalConfirm');
    const $modalText = $('#modalText');
    let deleteId = null;
    let $rowEl = null;

    // Klik tombol Hapus
    $(document).on('click', '.js-delete', function () {
        deleteId = $(this).data('id');
        const nama = $(this).data('nama') || 'Tagihan';
        $rowEl = $(this).closest('tr');

        $modalText.text(`Anda yakin ingin menghapus "${nama}"? Semua pembayaran terkait tagihan ini juga akan dihapus.`);
        $modal.fadeIn(120);
    });

    // Batal
    $('#btnCancel').on('click', function () {
        deleteId = null;
        $rowEl = null;
        $modal.fadeOut(120);
    });

    // Konfirmasi Hapus
    $('#btnConfirmDelete').on('click', function () {
        if (!deleteId) return;

        const csrf = $('#csrf_token').val();

        fetch('delete_tagihan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: deleteId, csrf_token: csrf })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Hapus baris dari tabel (tanpa reload)
                if ($rowEl) $rowEl.fadeOut(160, function(){ $(this).remove(); });
                // Tampilkan notifikasi ringan
                $('main.main-content').prepend(`
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> ${res.message || 'Tagihan berhasil dihapus.'}
                    </div>
                `);
            } else {
                $('main.main-content').prepend(`
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> ${res.message || 'Gagal menghapus tagihan.'}
                    </div>
                `);
            }
        })
        .catch(err => {
            $('main.main-content').prepend(`
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> Terjadi kesalahan jaringan. Silakan coba lagi.
                </div>
            `);
            console.error(err);
        })
        .finally(() => {
            deleteId = null;
            $rowEl = null;
            $modal.fadeOut(120);
        });
    });

    // Tutup modal bila klik backdrop
    $modal.on('click', function(e){
        if (e.target === this) { $('#btnCancel').trigger('click'); }
    });
    
    // Auto fade out alert sukses
    const successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s ease';
            successAlert.style.opacity = '0';
            setTimeout(() => successAlert.remove(), 500);
        }, 4000);
    }
});
</script>
</body>
</html>
<?php include_once '../includes/footer.php'; ?>