<?php
require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Pastikan hanya admin dan bendahara yang bisa akses
requireRole(['admin', 'bendahara']);

// Tambah tagihan - hanya admin
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_tagihan'])) {
    if (!hasPermission('create_tagihan')) {
        $_SESSION['error'] = "Anda tidak memiliki izin untuk menambah tagihan";
        header("Location: manajemen_tagihan.php");
        exit();
    }
    
    $nama_tagihan = cleanInput($_POST['nama_tagihan']);
    $kategori = cleanInput($_POST['kategori']);
    $jumlah = cleanInput($_POST['jumlah']);
    $periode = cleanInput($_POST['periode']);
    $tanggal_jatuh_tempo = cleanInput($_POST['tanggal_jatuh_tempo']);
    
    $stmt = $pdo->prepare("INSERT INTO tagihan (nama_tagihan, kategori, jumlah, periode, tanggal_jatuh_tempo) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nama_tagihan, $kategori, $jumlah, $periode, $tanggal_jatuh_tempo]);
    
    $_SESSION['success'] = "Tagihan berhasil ditambahkan!";
    header("Location: manajemen_tagihan.php");
    exit();
}

// Edit tagihan - admin dan bendahara
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_tagihan'])) {
    if (!hasPermission('edit_tagihan')) {
        $_SESSION['error'] = "Anda tidak memiliki izin untuk mengedit tagihan";
        header("Location: manajemen_tagihan.php");
        exit();
    }
    
    $id = cleanInput($_POST['id']);
    $nama_tagihan = cleanInput($_POST['nama_tagihan']);
    $kategori = cleanInput($_POST['kategori']);
    $jumlah = cleanInput($_POST['jumlah']);
    $periode = cleanInput($_POST['periode']);
    $tanggal_jatuh_tempo = cleanInput($_POST['tanggal_jatuh_tempo']);
    $status = cleanInput($_POST['status']);
    
    $stmt = $pdo->prepare("UPDATE tagihan SET nama_tagihan = ?, kategori = ?, jumlah = ?, periode = ?, tanggal_jatuh_tempo = ?, status = ? WHERE id = ?");
    $stmt->execute([$nama_tagihan, $kategori, $jumlah, $periode, $tanggal_jatuh_tempo, $status, $id]);
    
    $_SESSION['success'] = "Tagihan berhasil diperbarui!";
    header("Location: manajemen_tagihan.php");
    exit();
}

// Hapus tagihan - hanya admin
if (isset($_GET['hapus'])) {
    if (!hasPermission('delete_tagihan')) {
        $_SESSION['error'] = "Anda tidak memiliki izin untuk menghapus tagihan";
        header("Location: manajemen_tagihan.php");
        exit();
    }
    
    $id = cleanInput($_GET['hapus']);
    
    $stmt = $pdo->prepare("DELETE FROM tagihan WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['success'] = "Tagihan berhasil dihapus!";
    header("Location: manajemen_tagihan.php");
    exit();
}

// Ambil semua tagihan
$stmt = $pdo->query("SELECT * FROM tagihan ORDER BY tanggal_jatuh_tempo DESC");
$tagihan = $stmt->fetchAll();

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Pembayaran Sekolah</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- File CSS Utama -->
    <link rel="stylesheet" href="../assets/css/style_manajemen_tagihan.css">

    <!-- Tambahan CSS jika perlu -->
</head>

<body class="dashboard-body">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div>
            <?php include_once '../includes/sidebar.php'; ?>
        </div>

        <div class="dashboard-content">
            <div class="container">
                <div class="dashboard-header">
                    <h1 class="dashboard-title">Manajemen Tagihan</h1>
                </div>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <div class="invoice-management">
                    <?php if (hasPermission('create_tagihan')): ?>
                    <div class="add-invoice-form">
                        <h2><i class="fas fa-plus-circle"></i> Tambah Tagihan Baru</h2>
                        <form method="POST">
                            <div class="form-group">
                                <label for="nama_tagihan">Nama Tagihan</label>
                                <input type="text" id="nama_tagihan" name="nama_tagihan" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="kategori">Kategori</label>
                                    <select id="kategori" name="kategori" required>
                                        <option value="uang_sekolah">Uang Sekolah</option>
                                        <option value="seragam">Seragam</option>
                                        <option value="buku">Buku</option>
                                        <option value="lainnya">Lainnya</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="jumlah">Jumlah (Rp)</label>
                                    <input type="number" id="jumlah" name="jumlah" min="0" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="periode">Periode (YYYY-MM)</label>
                                    <input type="month" id="periode" name="periode" required>
                                </div>
                                <div class="form-group">
                                    <label for="tanggal_jatuh_tempo">Jatuh Tempo</label>
                                    <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" required>
                                </div>
                            </div>
                            
                            <button type="submit" name="tambah_tagihan" class="btn btn-add">
                                <i class="fas fa-save"></i> Tambah Tagihan
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <div class="invoice-list">
                        <h2><i class="fas fa-list"></i> Daftar Tagihan</h2>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Deskripsi</th>
                                        <th>Kategori</th>
                                        <th>Jumlah</th>
                                        <th>Jatuh Tempo</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tagihan as $key => $item): ?>
                                    <tr>
                                        <td><?= $key + 1 ?></td>
                                        <td><?= htmlspecialchars($item['nama_tagihan']) ?></td>
                                        <td><?= ucfirst(str_replace('_', ' ', htmlspecialchars($item['kategori']))) ?></td>
                                        <td>Rp <?= number_format($item['jumlah'], 0, ',', '.') ?></td>
                                        <td><?= date('d M Y', strtotime($item['tanggal_jatuh_tempo'])) ?></td>
                                        <td>
                                            <span class="status <?= htmlspecialchars($item['status']) ?>">
                                                <?= ucfirst(htmlspecialchars($item['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <?php if (hasPermission('edit_tagihan')): ?>
                                            <button class="btn btn-edit" onclick="openEditModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['nama_tagihan']) ?>', '<?= htmlspecialchars($item['kategori']) ?>', <?= $item['jumlah'] ?>, '<?= htmlspecialchars($item['periode']) ?>', '<?= htmlspecialchars($item['tanggal_jatuh_tempo']) ?>', '<?= htmlspecialchars($item['status']) ?>')">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('delete_tagihan')): ?>
                                            <a href="manajemen_tagihan.php?hapus=<?= $item['id'] ?>" class="btn btn-delete" onclick="return confirm('Yakin ingin menghapus tagihan ini?')">
                                                <i class="fas fa-trash-alt"></i> Hapus
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit Tagihan -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2><i class="fas fa-edit"></i> Edit Tagihan</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="edit_tagihan">
                
                <div class="form-group">
                    <label for="edit_nama_tagihan">Nama Tagihan</label>
                    <input type="text" id="edit_nama_tagihan" name="nama_tagihan" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_kategori">Kategori</label>
                        <select id="edit_kategori" name="kategori" required>
                            <option value="uang_sekolah">Uang Sekolah</option>
                            <option value="seragam">Seragam</option>
                            <option value="buku">Buku</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_jumlah">Jumlah (Rp)</label>
                        <input type="number" id="edit_jumlah" name="jumlah" min="0" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_periode">Periode (YYYY-MM)</label>
                        <input type="month" id="edit_periode" name="periode" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_tanggal_jatuh_tempo">Jatuh Tempo</label>
                        <input type="date" id="edit_tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" required>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-save">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <script>
    function openEditModal(id, nama_tagihan, kategori, jumlah, periode, tanggal_jatuh_tempo, status) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_nama_tagihan').value = nama_tagihan;
        document.getElementById('edit_kategori').value = kategori;
        document.getElementById('edit_jumlah').value = jumlah;
        document.getElementById('edit_periode').value = periode;
        document.getElementById('edit_tanggal_jatuh_tempo').value = tanggal_jatuh_tempo;
        document.getElementById('edit_status').value = status;
        
        document.getElementById('editModal').style.display = 'block';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>