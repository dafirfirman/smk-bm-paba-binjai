<?php
session_start();
require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Cek role bendahara
if ($_SESSION['user_role'] != 'bendahara') {
    header("Location: ../login.php");
    exit;
}

$page_title = "Buat Tagihan Baru";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

// Proses form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama_tagihan = clean_input($_POST['nama_tagihan']);
    $jenis_tagihan = clean_input($_POST['jenis_tagihan']);
    $jumlah = clean_input($_POST['jumlah']);
    $periode = clean_input($_POST['periode']);
    $tanggal_jatuh_tempo = clean_input($_POST['tanggal_jatuh_tempo']);
    $keterangan = clean_input($_POST['keterangan']);
    $kelas = isset($_POST['kelas']) ? $_POST['kelas'] : [];
    $jurusan = isset($_POST['jurusan']) ? $_POST['jurusan'] : [];

    // Validasi
    $errors = [];
    if (empty($nama_tagihan)) $errors[] = "Nama tagihan wajib diisi";
    if (!is_numeric($jumlah)) $errors[] = "Jumlah harus angka";
    if (empty($periode)) $errors[] = "Periode wajib diisi";
    if (empty($tanggal_jatuh_tempo)) $errors[] = "Tanggal jatuh tempo wajib diisi";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert tagihan
            $stmt = $pdo->prepare("INSERT INTO tagihan 
                                  (nama_tagihan, jenis_tagihan, jumlah, periode, tanggal_jatuh_tempo, keterangan, dibuat_oleh) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $nama_tagihan,
                $jenis_tagihan,
                $jumlah,
                $periode,
                $tanggal_jatuh_tempo,
                $keterangan,
                $_SESSION['user_id']
            ]);
            $tagihan_id = $pdo->lastInsertId();

            // Tentukan target siswa berdasarkan kelas/jurusan
            $sql_siswa = "SELECT id FROM siswa WHERE 1=1";
            $params = [];
            
            if (!empty($kelas)) {
                $sql_siswa .= " AND kelas IN (" . placeholders($kelas) . ")";
                $params = array_merge($params, $kelas);
            }
            
            if (!empty($jurusan)) {
                $sql_siswa .= " AND jurusan IN (" . placeholders($jurusan) . ")";
                $params = array_merge($params, $jurusan);
            }

            $stmt_siswa = $pdo->prepare($sql_siswa);
            $stmt_siswa->execute($params);
            $siswa_ids = $stmt_siswa->fetchAll(PDO::FETCH_COLUMN);

            // Buat tagihan untuk setiap siswa
            $stmt_tagihan = $pdo->prepare("INSERT INTO tagihan_siswa 
                                          (tagihan_id, siswa_id, status) 
                                          VALUES (?, ?, 'belum lunas')");
            
            foreach ($siswa_ids as $siswa_id) {
                $stmt_tagihan->execute([$tagihan_id, $siswa_id]);
            }

            $pdo->commit();
            $_SESSION['success'] = "Tagihan berhasil dibuat!";
            header("Location: buat_tagihan.php");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Gagal membuat tagihan: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// Ambil data kelas dan jurusan unik
$kelas_list = $pdo->query("SELECT DISTINCT kelas FROM siswa ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);
$jurusan_list = $pdo->query("SELECT DISTINCT jurusan FROM siswa ORDER BY jurusan")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="main-wrapper d-flex">
    <!-- SIDEBAR -->
    <?php include_once '../includes/sidebar_bendahara.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="container-fluid py-4">
            <!-- Notifikasi -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $_SESSION['success'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Card Form -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Buat Tagihan Baru</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <!-- Informasi Dasar -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Nama Tagihan <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama_tagihan" required 
                                           placeholder="Contoh: SPP Bulanan, Uang Gedung">
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label class="form-label">Jenis Tagihan</label>
                                    <select class="form-select" name="jenis_tagihan">
                                        <option value="rutin">Rutin</option>
                                        <option value="bebas">Bebas</option>
                                        <option value="lainnya">Lainnya</option>
                                    </select>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label class="form-label">Jumlah (Rp) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="jumlah" required 
                                           placeholder="Contoh: 500000">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label class="form-label">Periode <span class="text-danger">*</span></label>
                                    <input type="month" class="form-control" name="periode" required>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label class="form-label">Tanggal Jatuh Tempo <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="tanggal_jatuh_tempo" required>
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label class="form-label">Keterangan</label>
                                    <textarea class="form-control" name="keterangan" rows="2" 
                                              placeholder="Tambahkan keterangan jika perlu"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Target Siswa -->
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-users me-2"></i> Target Siswa</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Kelas</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <?php foreach ($kelas_list as $kelas): ?>
                                                <div class="col-md-4 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="kelas[]" value="<?= $kelas ?>" id="kelas-<?= $kelas ?>">
                                                        <label class="form-check-label" for="kelas-<?= $kelas ?>">
                                                            Kelas <?= $kelas ?>
                                                        </label>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Jurusan</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <?php foreach ($jurusan_list as $jurusan): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="jurusan[]" value="<?= $jurusan ?>" id="jurusan-<?= $jurusan ?>">
                                                        <label class="form-check-label" for="jurusan-<?= $jurusan ?>">
                                                            <?= $jurusan ?>
                                                        </label>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i> 
                                Jika tidak memilih kelas/jurusan, tagihan akan diterapkan ke semua siswa.
                            </div>
                        </div>
                        
                        <!-- Tombol Submit -->
                        <div class="text-end">
                            <button type="reset" class="btn btn-secondary me-2">
                                <i class="fas fa-undo me-1"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Simpan Tagihan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<!-- Script untuk Datepicker -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set tanggal jatuh tempo minimal hari ini
    const today = new Date().toISOString().split('T')[0];
    document.querySelector('input[name="tanggal_jatuh_tempo"]').min = today;
    
    // Select all checkbox untuk kelas/jurusan
    document.getElementById('select-all-kelas').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="kelas[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
    
    document.getElementById('select-all-jurusan').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="jurusan[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
});
</script>