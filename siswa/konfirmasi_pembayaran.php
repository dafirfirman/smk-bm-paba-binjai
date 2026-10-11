<?php
session_start();

require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Verifikasi login dan role siswa
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'siswa') {
    header("Location: ../index.php");
    exit();
}

// Ambil data siswa
if (!isset($_SESSION['siswa'])) {
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE user_id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $_SESSION['siswa'] = $stmt->fetch();
}

$siswa = $_SESSION['siswa'];

// Proses pembayaran
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tagihan_id = cleanInput($_POST['tagihan_id']);
    $metode_pembayaran = cleanInput($_POST['metode_pembayaran']);
    $catatan = cleanInput($_POST['catatan']);
    $siswa_id = $siswa['id'];
    
    // Ambil data tagihan
    $stmt = $pdo->prepare("SELECT * FROM tagihan WHERE id = ?");
    $stmt->execute([$tagihan_id]);
    $tagihan = $stmt->fetch();

    if (!$tagihan) {
        $_SESSION['error'] = "Tagihan tidak ditemukan";
        header("Location: tagihan.php");
        exit();
    }

    // Hitung denda keterlambatan PER MINGGU
    $denda = 0;
    $keterangan_denda = 'Tidak ada denda';
    $minggu_terlambat = 0;
    
    $tanggal_jatuh_tempo = new DateTime($tagihan['tanggal_jatuh_tempo']);
    $tanggal_sekarang = new DateTime();
    
    if ($tanggal_sekarang > $tanggal_jatuh_tempo) {
        $selisih = $tanggal_sekarang->diff($tanggal_jatuh_tempo);
        $hari_terlambat = $selisih->days;
        
        if ($hari_terlambat > 0) {
            // Hitung jumlah minggu terlambat (7 hari = 1 minggu)
            $minggu_terlambat = ceil($hari_terlambat / 7);
            $denda = $minggu_terlambat * 30000; // 30rb per minggu
            
            $keterangan_denda = "Terlambat $minggu_terlambat minggu (Denda Rp " . number_format($denda, 0, ',', '.') . ")";
        }
    }

    $total_bayar = $tagihan['jumlah'] + $denda;

    // Upload bukti pembayaran
    $bukti_pembayaran = '';
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
        $target_dir = "../uploads/bukti_pembayaran/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = 'pembayaran_' . $siswa_id . '_' . time() . '.' . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $target_file)) {
                $bukti_pembayaran = $target_file;
            }
        }
    }
    
    // Simpan pembayaran
    try {
        $stmt = $pdo->prepare("INSERT INTO pembayaran 
                              (siswa_id, tagihan_id, jumlah, metode_pembayaran, bukti_pembayaran, status, catatan, denda, keterangan_denda) 
                              VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
        $stmt->execute([
            $siswa_id, 
            $tagihan_id, 
            $total_bayar, 
            $metode_pembayaran, 
            $bukti_pembayaran, 
            $catatan,
            $denda,
            $keterangan_denda
        ]);
        
        // Set session untuk halaman konfirmasi
        $_SESSION['payment_confirmation'] = [
            'success' => true,
            'tagihan' => $tagihan['nama_tagihan'],
            'jumlah' => $total_bayar,
            'metode' => $metode_pembayaran,
            'denda' => $denda,
            'minggu_terlambat' => $minggu_terlambat,
            'keterangan_denda' => $keterangan_denda
        ];
        
        header("Location: konfirmasi_pembayaran.php");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Gagal menyimpan pembayaran: " . $e->getMessage();
        header("Location: pembayaran.php?tagihan_id=" . $tagihan_id);
        exit();
    }
}

// Ambil data tagihan untuk form
$tagihan_id = isset($_GET['tagihan_id']) ? cleanInput($_GET['tagihan_id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM tagihan WHERE id = ?");
$stmt->execute([$tagihan_id]);
$tagihan = $stmt->fetch();

if (!$tagihan) {
    header("Location: tagihan.php");
    exit();
}

// Hitung denda untuk ditampilkan di form (PER MINGGU)
$denda = 0;
$minggu_terlambat = 0;
$total_bayar = $tagihan['jumlah'];
$tanggal_jatuh_tempo = new DateTime($tagihan['tanggal_jatuh_tempo']);
$tanggal_sekarang = new DateTime();

if ($tanggal_sekarang > $tanggal_jatuh_tempo) {
    $selisih = $tanggal_sekarang->diff($tanggal_jatuh_tempo);
    $hari_terlambat = $selisih->days;
    
    if ($hari_terlambat > 0) {
        $minggu_terlambat = ceil($hari_terlambat / 7);
        $denda = $minggu_terlambat * 30000; // 30rb per minggu
        $total_bayar = $tagihan['jumlah'] + $denda;
    }
}

// Data rekening dan metode pembayaran
$metode_pembayaran_info = [
    'transfer' => [
        'judul' => 'Transfer Bank',
        'rekening' => [
            ['bank' => 'Bank Mandiri', 'nomor' => '123-456-7890', 'atas_nama' => 'SMK BM PABA BINJAI'],
            ['bank' => 'Bank BRI', 'nomor' => '987-654-3210', 'atas_nama' => 'SMK BM PABA BINJAI']
        ],
        'tata_cara' => [
            '1. Lakukan transfer ke salah satu rekening di atas',
            '2. Jumlah transfer harus sesuai (Rp '.number_format($total_bayar, 0, ',', '.').')',
            '3. Simpan bukti transfer',
            '4. Unggah bukti transfer pada form ini',
            '5. Pembayaran akan diverifikasi dalam 1x24 jam'
        ]
    ],
    'e-wallet' => [
        'judul' => 'E-Wallet',
        'rekening' => [
            ['provider' => 'Dana', 'nomor' => '081234567890', 'atas_nama' => 'SMK BM PABA'],
            ['provider' => 'OVO', 'nomor' => '081234567890', 'atas_nama' => 'SMK BM PABA'],
            ['provider' => 'Gopay', 'nomor' => '081234567890', 'atas_nama' => 'SMK BM PABA']
        ],
        'tata_cara' => [
            '1. Buka aplikasi e-wallet Anda',
            '2. Pilih transfer ke nomor tujuan',
            '3. Masukkan nomor tujuan sesuai provider yang dipilih',
            '4. Jumlah transfer harus sesuai (Rp '.number_format($total_bayar, 0, ',', '.').')',
            '5. Simpan bukti transfer dan unggah pada form ini'
        ]
    ],
    'debit' => [
        'judul' => 'Kartu Debit',
        'rekening' => [
            ['info' => 'Pembayaran langsung di kantor bendahara sekolah'],
            ['info' => 'Jam pelayanan: Senin-Jumat, 08:00-14:00 WIB']
        ],
        'tata_cara' => [
            '1. Datang ke kantor bendahara sekolah',
            '2. Bawa kartu debit dan kartu pelajar',
            '3. Informasikan nomor tagihan Anda',
            '4. Lakukan pembayaran sesuai jumlah (Rp '.number_format($total_bayar, 0, ',', '.').')',
            '5. Minta bukti pembayaran resmi dari bendahara'
        ]
    ]
];

include_once '../includes/header.php';
include_once '../includes/navbar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - SMK BM PABA Binjai</title>
    <link rel="stylesheet" href="../assets/css/style_pembayaran_siswa.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tagihan-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .tagihan-info h3 {
            color: #007bff;
            margin-top: 0;
        }
        
        .tagihan-info p {
            margin: 8px 0;
        }
        
        .tagihan-info .amount {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
        }
        
        .tagihan-info .denda {
            color: #dc3545;
            font-weight: bold;
        }
        
        .tagihan-info .total {
            font-size: 1.3em;
            font-weight: bold;
            color: #17a2b8;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .payment-method-info {
            display: none;
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #007bff;
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .account-item {
            background-color: white;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .instructions {
            background-color: white;
            border-radius: 6px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .instructions ol {
            padding-left: 20px;
            margin-bottom: 0;
        }
        
        .instructions li {
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <?php include_once '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="payment-container">
            <h1>Pembayaran Tagihan</h1>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <div class="payment-form">
                <div class="tagihan-info">
                    <h3><?= htmlspecialchars($tagihan['nama_tagihan']) ?></h3>
                    <p><i class="fas fa-calendar-alt"></i> Periode: <?= date('F Y', strtotime($tagihan['periode'])) ?></p>
                    <p><i class="fas fa-clock"></i> Jatuh Tempo: <?= date('d M Y', strtotime($tagihan['tanggal_jatuh_tempo'])) ?></p>
                    <p class="amount"><i class="fas fa-money-bill-wave"></i> Jumlah Tagihan: Rp <?= number_format($tagihan['jumlah'], 0, ',', '.') ?></p>
                    
                    <?php if ($denda > 0): ?>
                        <p class="denda"><i class="fas fa-exclamation-triangle"></i> Denda Keterlambatan: Rp <?= number_format($denda, 0, ',', '.') ?> (<?= $minggu_terlambat ?> minggu)</p>
                    <?php endif; ?>
                    
                    <p class="total"><i class="fas fa-calculator"></i> Total Pembayaran: Rp <?= number_format($total_bayar, 0, ',', '.') ?></p>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="tagihan_id" value="<?= $tagihan_id ?>">
                    
                    <div class="form-group">
                        <label for="metode_pembayaran">Metode Pembayaran</label>
                        <select id="metode_pembayaran" name="metode_pembayaran" class="form-control" required>
                            <option value="">Pilih Metode Pembayaran</option>
                            <option value="transfer">Transfer Bank</option>
                            <option value="e-wallet">E-Wallet</option>
                            <option value="debit">Kartu Debit</option>
                        </select>
                    </div>
                    
                    <!-- Informasi metode pembayaran akan muncul di sini -->
                    <div id="payment-method-info-container"></div>
                    
                    <div class="form-group">
                        <label for="bukti_pembayaran">Bukti Pembayaran</label>
                        <div class="file-upload">
                            <label for="bukti_pembayaran" class="upload-btn">
                                <i class="fas fa-cloud-upload-alt"></i> Pilih File
                            </label>
                            <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" accept="image/*,.pdf" required>
                            <span class="file-info">Format: JPG, PNG, PDF (max 2MB)</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="catatan">Catatan (Opsional)</label>
                        <textarea id="catatan" name="catatan" rows="3" class="form-control" placeholder="Tambahkan catatan jika diperlukan"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Ajukan Pembayaran
                        </button>
                        <a href="tagihan.php" class="btn-cancel">
                            <i class="fas fa-arrow-left"></i> Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<?php include_once '../includes/footer.php'; ?>

<script>
// Fungsi untuk menampilkan informasi metode pembayaran
document.getElementById('metode_pembayaran').addEventListener('change', function() {
    const method = this.value;
    const container = document.getElementById('payment-method-info-container');
    
    if (!method) {
        container.innerHTML = '';
        return;
    }
    
    // Data metode pembayaran dari PHP
    const paymentMethods = <?= json_encode($metode_pembayaran_info) ?>;
    const selectedMethod = paymentMethods[method];
    
    let html = `
        <div class="payment-method-info" style="display: block;">
            <h3>${selectedMethod.judul}</h3>
            
            <div class="account-list">
                ${selectedMethod.rekening.map(account => `
                    <div class="account-item">
                        ${account.bank ? `<strong>Bank:</strong> ${account.bank}<br>` : ''}
                        ${account.provider ? `<strong>Provider:</strong> ${account.provider}<br>` : ''}
                        <strong>Nomor:</strong> ${account.nomor}<br>
                        <strong>Atas Nama:</strong> ${account.atas_nama}
                        ${account.info ? `<p>${account.info}</p>` : ''}
                    </div>
                `).join('')}
            </div>
            
            <div class="instructions">
                <h4>Tata Cara Pembayaran:</h4>
                <ol>
                    ${selectedMethod.tata_cara.map(step => `<li>${step}</li>`).join('')}
                </ol>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
});
</script>
</body>
</html>