<?php
require '../vendor/autoload.php';  // Sesuaikan path ke vendor
require_once '../config/koneksi.php';
require_once '../config/functions.php';
requireRole(['bendahara']);

$page_title = "Notifikasi Siswa Belum Bayar";
include_once '../includes/header.php';
include_once '../includes/navbar.php';

function validateInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// ================= QUERY DAFTAR SISWA =================
$search = $_GET['search'] ?? '';
$query = "SELECT s.id, s.nisn, s.nama_lengkap, s.email, s.no_telepon, s.nomor_telepon_orang_tua, s.kelas, s.jurusan,
          t.id AS tagihan_id, t.nama_tagihan, t.jumlah, t.tanggal_jatuh_tempo,
          DATEDIFF(CURDATE(), t.tanggal_jatuh_tempo) AS hari_terlambat,
          CASE WHEN DATEDIFF(CURDATE(), t.tanggal_jatuh_tempo) > 0 
               THEN CONCAT('Terlambat ', DATEDIFF(CURDATE(), t.tanggal_jatuh_tempo), ' hari') 
               ELSE 'Akan Jatuh Tempo' END AS status_tagihan,
          CASE WHEN DATEDIFF(CURDATE(), t.tanggal_jatuh_tempo) > 0 
               THEN t.jumlah * 0.02 * DATEDIFF(CURDATE(), t.tanggal_jatuh_tempo) 
               ELSE 0 END AS denda
        FROM siswa s
        JOIN tagihan t ON s.id = t.siswa_id
        WHERE t.status = 'aktif'
          AND t.tanggal_jatuh_tempo <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND t.id NOT IN (
              SELECT tagihan_id
              FROM pembayaran
              WHERE status = 'lunas' AND siswa_id = s.id
          )";

$params = [];
if (!empty($search)) {
    $query .= " AND (s.nama_lengkap LIKE :search1 
                  OR s.nisn LIKE :search2 
                  OR t.nama_tagihan LIKE :search3)";
    $params[':search1'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
}

$query .= " ORDER BY t.tanggal_jatuh_tempo ASC, s.nama_lengkap ASC";

$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$siswa_belum_bayar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================= KIRIM NOTIFIKASI =================
if (isset($_POST['kirim_notifikasi'])) {
    $siswa_id   = validateInput($_POST['siswa_id']);
    $tagihan_id = validateInput($_POST['tagihan_id']);
    $metode     = validateInput($_POST['metode']);
    $pesan      = validateInput($_POST['pesan']);

    try {
        // --- ambil data siswa
        $stmt_siswa = $pdo->prepare("SELECT * FROM siswa WHERE id = ?");
        $stmt_siswa->execute([$siswa_id]);
        $siswa = $stmt_siswa->fetch(PDO::FETCH_ASSOC);

        if (!$siswa) {
            throw new Exception("Data siswa dengan ID $siswa_id tidak ditemukan");
        }

        $no_hp = $siswa['nomor_telepon_orang_tua'] ?? null;
        $email = $siswa['email'] ?? null;

        // --- ambil data tagihan
        $stmt_tagihan = $pdo->prepare("SELECT * FROM tagihan WHERE id = ?");
        $stmt_tagihan->execute([$tagihan_id]);
        $tagihan = $stmt_tagihan->fetch(PDO::FETCH_ASSOC);

        if (!$tagihan) {
            throw new Exception("Data tagihan dengan ID $tagihan_id tidak ditemukan");
        }

        // --- hitung denda
        $denda = 0;
        if ($tagihan['tanggal_jatuh_tempo'] < date('Y-m-d')) {
            $hari_terlambat = floor((strtotime(date('Y-m-d')) - strtotime($tagihan['tanggal_jatuh_tempo'])) / 86400);
            $denda = $tagihan['jumlah'] * 0.02 * $hari_terlambat;
        }

        // --- simpan ke tabel notifikasi
        $stmt_notif = $pdo->prepare("
            INSERT INTO notifikasi (user_id, judul, pesan, tagihan_id, metode, status, tanggal_kirim, catatan)
            VALUES (?, ?, ?, ?, ?, 'terkirim', NOW(), ?)
        ");
        $judul = "Pengingat Pembayaran - " . $tagihan['nama_tagihan'];
        $stmt_notif->execute([$siswa_id, $judul, $pesan, $tagihan_id, $metode, "Notifikasi dikirim otomatis"]);

        $success = true;
        $error_message = '';

        // --- kirim email
        if ($metode === 'email' || $metode === 'semua') {
            $email = trim($siswa['email']);
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'gasasisiregar@gmail.com';
                    $mail->Password   = 'jhnjgmijsrnhzzhx'; // ⚠️ gunakan App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('gasasisiregar@gmail.com', 'Bendahara SMK PABA');
                    $mail->addAddress($email, $siswa['nama_lengkap']);
                    $mail->isHTML(false);
                    $mail->Subject = $judul;
                    $mail->Body    = $pesan;
                    $mail->send();
                } catch (Exception $e) {
                    $success = false;
                    $error_message .= "Gagal kirim email ke {$email}: {$mail->ErrorInfo}. ";
                }
            } else {
                $success = false;
                $error_message .= "Alamat email tidak valid/kosong untuk siswa {$siswa['nama_lengkap']}. ";
            }
        }

        // --- kirim WhatsApp (simulasi log)
        if ($metode === 'whatsapp' || $metode === 'semua') {
            if (!empty($siswa['no_telepon'])) {
                error_log("WhatsApp ke siswa: " . $siswa['no_telepon']);
            }
            if (!empty($siswa['nomor_telepon_orang_tua'])) {
                error_log("WhatsApp ke ortu: " . $siswa['nomor_telepon_orang_tua']);
            }
        }

        $_SESSION[$success ? 'success' : 'error'] = 
            $success ? "Notifikasi berhasil dikirim!" : "Notifikasi gagal: $error_message";

    } catch (Exception $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    }

    header("Location: notifikasi.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="../assets/css/style_notifikasi.css">
    <link rel="stylesheet" href="../assets/css/style_kelola_tagihan.css">
    <link rel="stylesheet" href="../assets/css/notifikasi.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="notifikasi-body">
    <!-- Sidebar Menu -->
    <div>
        <?php include_once '../includes/sidebar.php'; ?>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Notifikasi Siswa Belum Bayar</h1>
            <p>Kirim pengingat pembayaran kepada siswa yang belum melunasi tagihan</p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="filter-section">
            <form method="GET">
                <div class="search-input-container">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Cari nama siswa, NISN, atau tagihan..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary" style="margin-left: 10px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Daftar Siswa Belum Bayar</h3>
            </div>
            <div class="card-body">
                <?php if (count($siswa_belum_bayar) > 0): ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Siswa</th>
                                    <th>NISN</th>
                                    <th>Kelas</th>
                                    <th>Tagihan</th>
                                    <th>Jatuh Tempo</th>
                                    <th>Status</th>
                                    <th>Denda</th>
                                    <th>Kontak</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa_belum_bayar as $key => $siswa): ?>
                                    <tr>
                                        <td><?= $key + 1 ?></td>
                                        <td><?= htmlspecialchars($siswa['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($siswa['nisn']) ?></td>
                                        <td><?= htmlspecialchars($siswa['kelas']) ?> <?= htmlspecialchars($siswa['jurusan']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($siswa['nama_tagihan']) ?>
                                            <br><small>Rp <?= number_format($siswa['jumlah'], 0, ',', '.') ?></small>
                                        </td>
                                        <td><?= date('d M Y', strtotime($siswa['tanggal_jatuh_tempo'])) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= $siswa['hari_terlambat'] > 0 ? 'terlambat' : 'akan-jatuh-tempo' ?>">
                                                <?= $siswa['status_tagihan'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($siswa['denda'] > 0): ?>
                                                <span class="text-danger">Rp <?= number_format($siswa['denda'], 0, ',', '.') ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="contact-info">
                                                <?php if ($siswa['email']): ?>
                                                    <div><i class="fas fa-envelope"></i> <?= $siswa['email'] ?></div>
                                                <?php endif; ?>
                                                <?php if ($siswa['no_telepon']): ?>
                                                    <div><i class="fas fa-phone"></i> <?= $siswa['no_telepon'] ?></div>
                                                <?php endif; ?>
                                                <?php if ($siswa['nomor_telepon_orang_tua']): ?>
                                                    <div><i class="fas fa-user-friends"></i> <?= $siswa['nomor_telepon_orang_tua'] ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn-action btn-notify" 
                                                    onclick="openNotifyModal(
                                                        <?= $siswa['id'] ?>, 
                                                        <?= $siswa['tagihan_id'] ?>, 
                                                        '<?= htmlspecialchars($siswa['nama_lengkap']) ?>', 
                                                        '<?= htmlspecialchars($siswa['nama_tagihan']) ?>', 
                                                        <?= $siswa['jumlah'] ?>, 
                                                        '<?= $siswa['tanggal_jatuh_tempo'] ?>', 
                                                        <?= $siswa['hari_terlambat'] ?>, 
                                                        <?= $siswa['denda'] ?>,
                                                        '<?= $siswa['email'] ?>',
                                                        '<?= $siswa['no_telepon'] ?>',
                                                        '<?= $siswa['nomor_telepon_orang_tua'] ?>'
                                                    )">
                                                <i class="fas fa-paper-plane"></i> Kirim
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>Tidak ada siswa yang belum bayar</h3>
                        <p>Semua siswa telah melunasi pembayaran tagihan mereka</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Kirim Notifikasi -->
<div class="modal" id="notifyModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-paper-plane"></i> Kirim Notifikasi</h3>
            <button type="button" class="modal-close" onclick="closeModal('notifyModal')">&times;</button>
        </div>
        
        <form method="POST" action="notifikasi.php">
            <input type="hidden" name="kirim_notifikasi" value="1">
            <input type="hidden" name="siswa_id" id="notify_siswa_id">
            <input type="hidden" name="tagihan_id" id="notify_tagihan_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>Penerima</label>
                    <input type="text" id="notify_nama" readonly>
                </div>
                
                <div class="form-group">
                    <label>Tagihan</label>
                    <input type="text" id="notify_tagihan" readonly>
                </div>
                
                <div class="form-group">
                    <label>Jatuh Tempo</label>
                    <input type="text" id="notify_tanggal" readonly>
                </div>
                
                <div class="form-group">
                    <label>Keterlambatan</label>
                    <input type="text" id="notify_keterlambatan" readonly>
                </div>
                
                <div class="form-group">
                    <label>Denda</label>
                    <input type="text" id="notify_denda" readonly>
                </div>
                
                <div class="form-group">
                    <label for="notify_metode">Metode Pengiriman</label>
                    <select id="notify_metode" name="metode" required>
                        <option value="email">Email</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="semua">Email & WhatsApp</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notify_pesan">Pesan Notifikasi</label>
                    <textarea id="notify_pesan" name="pesan" rows="6" required></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('notifyModal')">Batal</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Kirim Notifikasi
                </button>
            </div>
        </form>
    </div>
</div>

    <script>
        // Function to open notify modal
        function openNotifyModal(siswaId, tagihanId, nama, tagihan, jumlah, tanggal, hariTerlambat, denda, email, telepon, teleponOrtu) {
            document.getElementById('notify_siswa_id').value = siswaId;
            document.getElementById('notify_tagihan_id').value = tagihanId;
            document.getElementById('notify_nama').value = nama;
            document.getElementById('notify_tagihan').value = tagihan + ' (Rp ' + jumlah.toLocaleString('id-ID') + ')';
            
            const tanggalFormatted = new Date(tanggal).toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('notify_tanggal').value = tanggalFormatted;
            
            document.getElementById('notify_keterlambatan').value = hariTerlambat > 0 ? 
                'Terlambat ' + hariTerlambat + ' hari' : 'Masih dalam batas waktu';
            
            document.getElementById('notify_denda').value = denda > 0 ? 
                'Rp ' + denda.toLocaleString('id-ID') : 'Tidak ada denda';
            
            // Generate pesan default
            let pesanDefault = '';
            if (hariTerlambat > 0) {
                pesanDefault = `Yth. Orang Tua/Wali ${nama},

Dengan hormat, kami sampaikan bahwa pembayaran tagihan:
${tagihan} 
sebesar Rp ${jumlah.toLocaleString('id-ID')} 
sudah melewati batas waktu jatuh tempo (${tanggalFormatted}).

Keterlambatan: ${hariTerlambat} hari
Denda yang timbul: Rp ${denda > 0 ? denda.toLocaleString('id-ID') : '0'}

Total yang harus dibayar: Rp ${(jumlah + denda).toLocaleString('id-ID')}

Mohon untuk segera melakukan pembayaran untuk menghindari sanksi administrasi.

Terima kasih.

Hormat kami,
Bendahara SMK PABA Binjai`;
            } else {
                const daysLeft = Math.abs(hariTerlambat);
                pesanDefault = `Yth. Orang Tua/Wali ${nama},

Ini adalah pengingat bahwa tagihan:
${tagihan} 
sebesar Rp ${jumlah.toLocaleString('id-ID')} 
akan jatuh tempo pada ${tanggalFormatted}.

Sisa waktu: ${daysLeft} hari

Mohon untuk menyiapkan pembayaran tepat waktu.

Terima kasih.

Hormat kami,
Bendahara SMK PABA Binjai`;
            }
            
            document.getElementById('notify_pesan').value = pesanDefault;
            document.getElementById('notifyModal').style.display = 'block';
            document.body.classList.add('modal-open');
        }
        
        // Function to close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.classList.remove('modal-open');
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal(event.target.id);
            }
        });
    </script>
</body>
</html>

<?php 
    include_once('../includes/footer.php'); 
?>