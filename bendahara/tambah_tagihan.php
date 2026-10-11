<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';
requireRole(['bendahara']);
$page_title = "Tambah Tagihan";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Fungsi untuk mengkonversi format mata uang Indonesia ke angka
function currencyToNumber($currency) {
    // Hapus semua karakter kecuali digit dan koma
    $cleaned = preg_replace('/[^\d,]/', '', $currency);
    // Ganti koma dengan titik (untuk desimal)
    $cleaned = str_replace(',', '.', $cleaned);
    // Konversi ke float
    return (float) $cleaned;
}

// Inisialisasi variabel pesan
$success_message = null; // Variabel ini akan kita gunakan untuk pesan sukses
$error_message = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Ambil data dari form
    $nama_tagihan = $_POST['nama_tagihan'];
    $kategori = $_POST['kategori'];
    $jumlah = currencyToNumber($_POST['jumlah']);
    $periode = $_POST['periode'];
    $tanggal_jatuh_tempo = $_POST['tanggal_jatuh_tempo'];
    $tahun_ajaran = $_POST['tahun_ajaran'];

    try {
        // Get all active students
        $stmt = $pdo->query("SELECT id FROM siswa");
        $siswa_list = $stmt->fetchAll();

        if (empty($siswa_list)) {
            $error_message = "Tidak ada data siswa yang ditemukan.";
        } else {
            $total_siswa = count($siswa_list);
            $siswa_ditambahkan = 0;
            $siswa_dilewati = 0;

            // Mulai transaksi
            $pdo->beginTransaction();

            try {
                // Insert tagihan for each student
                foreach ($siswa_list as $siswa) {
                    // Cek apakah siswa ini sudah memiliki tagihan dengan kombinasi yang sama (Logika ini sudah benar)
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tagihan
                                                 WHERE siswa_id = ? AND nama_tagihan = ? AND periode = ? AND tahun_ajaran = ?");
                    $check_stmt->execute([$siswa['id'], $nama_tagihan, $periode, $tahun_ajaran]);
                    $count = $check_stmt->fetchColumn();

                    if ($count == 0) {
                        // Jika belum ada, tambahkan tagihan
                        $stmt = $pdo->prepare("INSERT INTO tagihan (siswa_id, nama_tagihan, kategori, jumlah, periode, tanggal_jatuh_tempo, status, tahun_ajaran)
                                               VALUES (?, ?, ?, ?, ?, ?, 'aktif', ?)");
                        $result = $stmt->execute([$siswa['id'], $nama_tagihan, $kategori, $jumlah, $periode, $tanggal_jatuh_tempo, $tahun_ajaran]);

                        if ($result) {
                            $siswa_ditambahkan++;
                        }
                    } else {
                        // Jika sudah ada, lewati
                        $siswa_dilewati++;
                    }
                }

                // Commit transaksi
                $pdo->commit();

                // --- PERUBAHAN DIMULAI DI SINI ---

                // Set pesan sukses ke variabel untuk ditampilkan di halaman ini, bukan di session.
                if ($siswa_ditambahkan > 0) {
                    $pesan = "Tagihan berhasil disimpan. $siswa_ditambahkan siswa berhasil ditambahkan tagihan baru.";
                    if ($siswa_dilewati > 0) {
                        $pesan .= " Sebanyak $siswa_dilewati siswa dilewati karena sudah memiliki tagihan serupa.";
                    }
                    $success_message = $pesan; // Simpan ke variabel success_message
                } else {
                    $error_message = "Tidak ada tagihan baru yang ditambahkan. Semua siswa sudah memiliki tagihan ini.";
                }
                
                // Hapus redirect agar tetap di halaman ini
                // header("Location: kelola_tagihan.php");
                // exit();
                
                // --- PERUBAHAN SELESAI ---

            } catch (Exception $e) {
                // Rollback transaksi jika ada error
                $pdo->rollBack();
                $error_message = "Terjadi kesalahan saat menambahkan tagihan: " . $e->getMessage();
            }
        }
    } catch (PDOException $e) {
        $error_message = "Terjadi kesalahan saat mengambil data siswa: " . $e->getMessage();
    }
}

// Get list of students (untuk ditampilkan di form)
try {
    $stmt = $pdo->query("SELECT id, nama_lengkap, nisn, kelas FROM siswa ORDER BY nama_lengkap");
    $siswa_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Terjadi kesalahan saat mengambil data siswa: " . $e->getMessage();
    $siswa_list = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?= htmlspecialchars($page_title) ?></title>
    <link rel="stylesheet" href="../assets/css/style_tambah_tagihan.css" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" />
    <style>
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            display: flex;
            align-items: center;
        }
        .alert-success .fas {
            margin-right: 10px;
        }
    </style>
</head>
<body>
<div class="layout-wrapper">
    <div>
        <?php include_once '../includes/sidebar.php'; ?>
    </div>
    
    <main class="main-content">
        <div class="page-header">
            <div class="header-title">
                <h1>Tambah Tagihan</h1>
                <p class="subtitle">Tambah tagihan untuk semua siswa</p>
            </div>
            <div class="header-breadcrumb">
                <a href="kelola_tagihan.php" class="breadcrumb-link">
                    <i class="fas fa-arrow-left"></i> Kembali ke Kelola Tagihan
                </a>
            </div>
        </div>
        
        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>
        
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Form Tambah Tagihan</h3>
                <p class="card-subtitle">Isi formulir di bawah untuk menambahkan tagihan baru</p>
            </div>
            <div class="card-body">
                <form method="POST" class="form" id="tagihanForm" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nama_tagihan">Nama Tagihan <span class="required">*</span></label>
                            <input type="text" id="nama_tagihan" name="nama_tagihan" required 
                                   placeholder="Contoh: Uang Sekolah Bulan Januari" />
                            <div class="form-hint">Masukkan nama tagihan yang jelas dan mudah dipahami</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="kategori">Kategori <span class="required">*</span></label>
                            <select id="kategori" name="kategori" required>
                                <option value="">Pilih Kategori</option>
                                <option value="Uang Sekolah">Uang Sekolah</option>
                                </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="jumlah">Jumlah (Rp) <span class="required">*</span></label>
                            <div class="input-with-prefix">
                                <span class="input-prefix">Rp</span>
                                <input type="text" id="jumlah" name="jumlah" required 
                                       placeholder="250.000" oninput="formatCurrency(this)" />
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="periode">Periode <span class="required">*</span></label>
                            <select id="periode" name="periode" required>
                                <option value="">Pilih Periode</option>
                                <option value="Bulanan">Bulanan</option>
                                <option value="Tahunan">Tahunan</option>
                                <option value="Semester">Semester</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tanggal_jatuh_tempo">Tanggal Jatuh Tempo <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" required />
                                <i class="fas fa-calendar-alt input-icon"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="tahun_ajaran">Tahun Ajaran <span class="required">*</span></label>
                            <input type="text" id="tahun_ajaran" name="tahun_ajaran" 
                                   value="<?= date('Y') . '/' . (date('Y') + 1) ?>" required 
                                   placeholder="Contoh: 2023/2024" />
                        </div>
                    </div>
                    
                    <div class="form-summary">
                        <h4>Ringkasan Tagihan</h4>
                        <div class="summary-content">
                            <div class="summary-item">
                                <span class="summary-label">Jumlah Siswa:</span>
                                <span class="summary-value"><?= count($siswa_list) ?> siswa</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Total yang akan ditagih:</span>
                                <span class="summary-value" id="total-tagihan">Rp 0</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Simpan Tagihan
                        </button>
                        <a href="kelola_tagihan.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
<script>
// Format currency and calculate total
function formatCurrency(input) {
    let value = input.value.replace(/\D/g, '');
    if (value) {
        input.value = parseInt(value).toLocaleString('id-ID');
        calculateTotal(parseInt(value));
    } else {
        document.getElementById('total-tagihan').textContent = 'Rp 0';
    }
}

// Calculate total tagihan
function calculateTotal(jumlah) {
    const totalSiswa = <?= count($siswa_list) ?>;
    const total = jumlah * totalSiswa;
    document.getElementById('total-tagihan').textContent = 'Rp ' + total.toLocaleString('id-ID');
}

document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const nextWeek = new Date(today.getTime() + 7 * 24 * 60 * 60 * 1000);
    const tglJatuhTempoInput = document.getElementById('tanggal_jatuh_tempo');
    if (!tglJatuhTempoInput.value) {
        tglJatuhTempoInput.valueAsDate = nextWeek;
    }
    
    document.getElementById('jumlah').addEventListener('input', function() {
        const value = parseInt(this.value.replace(/\D/g, '')) || 0;
        calculateTotal(value);
    });
    
    const form = document.getElementById('tagihanForm');
    const submitBtn = document.getElementById('submitBtn');
    
    form.addEventListener('submit', function(e) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
        
        // Cek validitas form. Jika tidak valid, JS akan menghentikan submit
        // dan browser akan menampilkan pesan error. Kita aktifkan lagi tombolnya.
        if (!form.checkValidity()) {
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Simpan Tagihan';
            }, 100);
        }
    });
});
</script>
<?php include_once '../includes/footer.php'; ?>
</body>
</html>