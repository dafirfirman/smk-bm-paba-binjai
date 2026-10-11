<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan memiliki akses ke halaman ini
if (!isset($_SESSION['user_id']) || !in_array('manage_payment_methods', $_SESSION['permissions'])) {
    header('Location: ../login.php');
    exit;
}

// Fungsi untuk menampilkan metode transaksi
function getPaymentMethods($conn) {
    $query = "SELECT * FROM payment_methods";
    $result = mysqli_query($conn, $query);
    return $result;
}

// Fungsi untuk menambahkan metode transaksi
function addPaymentMethod($conn, $name, $description, $is_active) {
    $query = "INSERT INTO payment_methods (name, description, is_active) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssi', $name, $description, $is_active);
    return mysqli_stmt_execute($stmt);
}

// Fungsi untuk mengupdate metode transaksi
function updatePaymentMethod($conn, $id, $name, $description, $is_active) {
    $query = "UPDATE payment_methods SET name = ?, description = ?, is_active = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ssii', $name, $description, $is_active, $id);
    return mysqli_stmt_execute($stmt);
}

// Fungsi untuk menghapus metode transaksi
function deletePaymentMethod($conn, $id) {
    $query = "DELETE FROM payment_methods WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    return mysqli_stmt_execute($stmt);
}

// Proses form tambah/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (addPaymentMethod($conn, $name, $description, $is_active)) {
            $_SESSION['success'] = "Metode transaksi berhasil ditambahkan";
        } else {
            $_SESSION['error'] = "Gagal menambahkan metode transaksi";
        }
    } elseif (isset($_POST['update'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (updatePaymentMethod($conn, $id, $name, $description, $is_active)) {
            $_SESSION['success'] = "Metode transaksi berhasil diperbarui";
        } else {
            $_SESSION['error'] = "Gagal memperbarui metode transaksi";
        }
    } elseif (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        
        if (deletePaymentMethod($conn, $id)) {
            $_SESSION['success'] = "Metode transaksi berhasil dihapus";
        } else {
            $_SESSION['error'] = "Gagal menghapus metode transaksi";
        }
        header('Location: metode_transaksi.php');
        exit;
    }
    
    header('Location: metode_transaksi.php');
    exit;
}

$payment_methods = getPaymentMethods($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metode Transaksi</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <?php include 'dashboard-sidebar.php'; ?>
        
        <div class="dashboard-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12">
                        <h2><i class="fas fa-credit-card"></i> Metode Transaksi</h2>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        
                        <button class="btn btn-primary mb-3" data-toggle="modal" data-target="#addModal">
                            <i class="fas fa-plus"></i> Tambah Metode
                        </button>
                        
                        <div class="card">
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nama</th>
                                            <th>Deskripsi</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($payment_methods)): ?>
                                            <tr>
                                                <td><?= $row['id']; ?></td>
                                                <td><?= htmlspecialchars($row['name']); ?></td>
                                                <td><?= htmlspecialchars($row['description']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $row['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?= $row['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-warning edit-btn" 
                                                            data-id="<?= $row['id']; ?>"
                                                            data-name="<?= htmlspecialchars($row['name']); ?>"
                                                            data-description="<?= htmlspecialchars($row['description']); ?>"
                                                            data-is_active="<?= $row['is_active']; ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <a href="metode_transaksi.php?delete=<?= $row['id']; ?>" 
                                                       class="btn btn-sm btn-danger" 
                                                       onclick="return confirm('Apakah Anda yakin ingin menghapus metode ini?')">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Tambah -->
    <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addModalLabel">Tambah Metode Transaksi</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Nama Metode</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Deskripsi</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" name="add" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Metode Transaksi</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_name">Nama Metode</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_description">Deskripsi</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                            <label class="form-check-label" for="edit_is_active">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
                        <button type="submit" name="update" class="btn btn-primary">Perbarui</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/jquery-3.5.1.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.edit-btn').click(function() {
                var id = $(this).data('id');
                var name = $(this).data('name');
                var description = $(this).data('description');
                var is_active = $(this).data('is_active');
                
                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_is_active').prop('checked', is_active == 1);
                
                $('#editModal').modal('show');
            });
        });
    </script>
</body>
</html>