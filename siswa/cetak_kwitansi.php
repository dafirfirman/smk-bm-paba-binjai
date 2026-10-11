<?php
session_start();

require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Cek login siswa
if (!isset($_SESSION['siswa'])) {
    header("Location: ../login.php");
    exit;
}

$siswa_id = $_SESSION['siswa']['id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: bukti_kwitansi.php");
    exit;
}

$id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT p.*, t.nama_tagihan, t.periode, t.jumlah as jumlah_tagihan,
                          s.nama_lengkap as nama_siswa, s.kelas, s.jurusan, s.nisn
                          FROM pembayaran p
                          JOIN tagihan t ON p.tagihan_id = t.id
                          JOIN siswa s ON p.siswa_id = s.id
                          WHERE p.id = ? AND p.siswa_id = ?");
    $stmt->execute([$id, $siswa_id]);
    $kwitansi = $stmt->fetch();

    if (!$kwitansi) {
        header("Location: bukti_kwitansi.php");
        exit;
    }
} catch (PDOException $e) {
    header("Location: bukti_kwitansi.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Kwitansi - SMK PABA</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .kwitansi-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
            background: white;
        }
        .kwitansi-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .kwitansi-logo {
            height: 80px;
            margin-bottom: 10px;
        }
        .kwitansi-title {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .kwitansi-divider {
            border-top: 2px solid #000;
            margin: 15px 0;
        }
        .kwitansi-info {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
        }
        .kwitansi-info td {
            padding: 5px 0;
        }
        .kwitansi-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .kwitansi-table th, 
        .kwitansi-table td {
            border: 1px solid #000;
            padding: 10px;
        }
        .kwitansi-table th {
            background-color: #f5f5f5;
        }
        .text-right {
            text-align: right;
        }
        .kwitansi-signature {
            margin-top: 60px;
            text-align: right;
        }
        .no-print {
            display: none;
        }
        @page {
            size: A4;
            margin: 15mm;
        }
    </style>
</head>
<body>
    <!-- Tombol Cetak (Hanya tampil saat preview) -->
    <div class="no-print" style="text-align:center; margin:20px 0;">
        <button onclick="window.print()" style="padding:10px 20px; background:#28a745; color:white; border:none; cursor:pointer;">
            <i class="fas fa-print"></i> Cetak Kwitansi
        </button>
        <a href="bukti_kwitansi.php" style="margin-left:20px; padding:10px 20px; background:#6c757d; color:white; text-decoration:none;">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <!-- Konten Kwitansi -->
    <div class="kwitansi-container">
        <div class="kwitansi-header">
            <img src="../assets/images/logo_smk_binjai.jpg" class="kwitansi-logo" 
                 onerror="this.style.display='none'">
            <h2>SMK PABA BINJAI</h2>
            <p>Jl. Pendidikan No. 1, Binjai - Sumatera Utara</p>
            <div class="kwitansi-divider"></div>
            <h3 class="kwitansi-title">KWITANSI PEMBAYARAN</h3>
            <p>No. <?= str_pad($kwitansi['id'], 6, '0', STR_PAD_LEFT) ?></p>
        </div>

        <table class="kwitansi-info">
            <tr>
                <td width="25%">Nama Siswa</td>
                <td width="5%">:</td>
                <td><?= htmlspecialchars($kwitansi['nama_siswa']) ?></td>
            </tr>
            <tr>
                <td>NISN</td>
                <td>:</td>
                <td><?= htmlspecialchars($kwitansi['nisn']) ?></td>
            </tr>
            <tr>
                <td>Kelas/Jurusan</td>
                <td>:</td>
                <td><?= htmlspecialchars($kwitansi['kelas']) ?> / <?= htmlspecialchars($kwitansi['jurusan']) ?></td>
            </tr>
            <tr>
                <td>Tanggal Bayar</td>
                <td>:</td>
                <td><?= date('d F Y', strtotime($kwitansi['tanggal_pembayaran'])) ?></td>
            </tr>
        </table>

        <table class="kwitansi-table">
            <tr>
                <th width="70%">Keterangan Pembayaran</th>
                <th width="30%">Jumlah</th>
            </tr>
            <tr>
                <td>
                    <?= htmlspecialchars($kwitansi['nama_tagihan']) ?><br>
                    <small>Periode: <?= date('F Y', strtotime($kwitansi['periode'])) ?></small>
                </td>
                <td class="text-right">Rp <?= number_format($kwitansi['jumlah_tagihan'], 0, ',', '.') ?></td>
            </tr>
            <tr>
                <td>Denda Keterlambatan</td>
                <td class="text-right">
                    <?php if ($kwitansi['denda'] > 0) : ?>
                        Rp <?= number_format($kwitansi['denda'], 0, ',', '.') ?>
                    <?php else : ?>
                        Rp 0
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>TOTAL PEMBAYARAN</strong></td>
                <td class="text-right"><strong>Rp <?= number_format($kwitansi['total_bayar'], 0, ',', '.') ?></strong></td>
            </tr>
        </table>

        <div class="kwitansi-signature">
            <p>Binjai, <?= date('d F Y') ?></p>
            <br><br>
            <p>Bendahara SMK PABA</p>
            <p>(___________________)</p>
        </div>
    </div>

    <script>
        // Auto print saat halaman dimuat
        window.onload = function() {
            if(!window.location.search.includes('preview')) {
                window.print();
            }
        };
        
        // Deteksi setelah cetak
        window.onafterprint = function() {
            if(!window.location.search.includes('preview')) {
                window.close();
            }
        };
    </script>
</body>
</html>
