<?php
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
            <div class="alert alert-success">
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
                            <option value="lunas" <?= $status == 'lunas' ? 'selected' : '' ?>>Lunas</option>
                            <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                            <option value="ditolak" <?= $status == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                            <option value="belum" <?= $status == 'belum' ? 'selected' : '' ?>>Belum Bayar</option>
                            <option value="terlambat" <?= $status == 'terlambat' ? 'selected' : '' ?>>Terlambat</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="bulan">Bulan</label>
                            <select id="bulan" name="bulan" class="form-select">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= $bulan == $i ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tahun">Tahun</label>
                            <select id="tahun" name="tahun" class="form-select">
                                <?php for ($i = date('Y') - 2; $i <= date('Y') + 2; $i++): ?>
                                <option value="<?= $i ?>" <?= $tahun == $i ? 'selected' : '' ?>><?= $i ?></option>
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
                        <button class="btn btn-export" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export
                        </button>
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
                                    if ($current_student != $item['siswa_id']) {
                                        $current_student = $item['siswa_id'];
                                        $row_count++;
                                    }
                                ?>
                                <tr>
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
                                            <a href="edit_tagihan.php?id=<?= $item['tagihan_id'] ?>" class="btn btn-action btn-edit" title="Edit Tagihan">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <button class="btn btn-action btn-delete" 
                                                    onclick="confirmDelete(<?= $item['tagihan_id'] ?>, '<?= addslashes(htmlspecialchars($item['nama_tagihan'])) ?>')" 
                                                    title="Hapus Tagihan">
                                                <i class="fas fa-trash"></i> Hapus
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

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Konfirmasi Hapus</h4>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus tagihan <strong id="delete-tagihan-name"></strong>?</p>
                <p class="text-danger"><strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan dan akan menghapus semua data pembayaran yang terkait dengan tagihan ini.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                <a href="#" id="confirm-delete-btn" class="btn btn-danger">Hapus</a>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#invoices-table').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
                },
                dom: '<"top"<"row"<"col-md-6"l><"col-md-6"f>>>rt<"bottom"<"row"<"col-md-6"i><"col-md-6"p>>>',
                initComplete: function() {
                    $('.dataTables_filter input').attr('placeholder', 'Cari...');
                },
                columnDefs: [
                    { responsivePriority: 1, targets: 0 },
                    { responsivePriority: 2, targets: -1 },
                    { orderable: false, targets: -1 }
                ]
            });
        });

        function confirmDelete(id, name) {
            $('#delete-tagihan-name').text(name);
            $('#confirm-delete-btn').attr('href', 'kelola_tagihan.php?delete_id=' + id);
            $('#deleteModal').show();
        }

        function closeDeleteModal() {
            $('#deleteModal').hide();
        }

        function exportToExcel() {
            // Get table data
            const table = document.getElementById('invoices-table');
            const workbook = XLSX.utils.table_to_book(table);
            
            // Generate Excel file
            XLSX.writeFile(workbook, 'Daftar_Tagihan_' + new Date().toISOString().slice(0,10) + '.xlsx');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('deleteModal')) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
<?php include_once '../includes/footer.php'; ?>