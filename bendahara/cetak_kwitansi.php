<?php
// File: cetak_kwitansi_content.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pastikan data pembayaran tersedia dalam $pembayaran_detail (array)
if (!isset($pembayaran_detail) || !is_array($pembayaran_detail)) {
    die("Data pembayaran tidak ditemukan");
}

// Helper aman
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function rp($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

// Normalisasi & default
$defaults = [
    'id'                 => 0,
    'nama_lengkap'       => 'Tidak diketahui',
    'nisn'               => '-',
    'kelas'              => '-',
    'jurusan'            => '-',
    'metode_pembayaran'  => 'Tunai',
    'nama_tagihan'       => 'Pembayaran Sekolah',
    'periode'            => date('Y'),
    'jumlah_tagihan'     => 0,
    'jumlah'             => 0,
    'denda'              => 0,
    'keterangan_denda'   => '',
    'tanggal_pembayaran' => '',
    'tanggal_verifikasi' => '',
    'total_bayar'        => 0,
];
$pembayaran_detail = array_merge($defaults, $pembayaran_detail);

// Tanggal - PERBAIKAN: Gunakan tanggal verifikasi sebagai tanggal pembayaran resmi
$tanggal_resmi = $pembayaran_detail['tanggal_verifikasi']
    ? date('d/m/Y', strtotime($pembayaran_detail['tanggal_verifikasi']))
    : ($pembayaran_detail['tanggal_pembayaran']
        ? date('d/m/Y', strtotime($pembayaran_detail['tanggal_pembayaran']))
        : 'Tanggal tidak tersedia');

// Tanggal input (untuk informasi tambahan)
$tanggal_input = $pembayaran_detail['tanggal_pembayaran']
    ? date('d/m/Y', strtotime($pembayaran_detail['tanggal_pembayaran']))
    : 'Tanggal tidak tersedia';

// Nomor kwitansi
$no_kwitansi = sprintf("KW/%s/%03d", date('Y'), (int)$pembayaran_detail['id']);

// Hitung total
$show_denda  = (float)$pembayaran_detail['denda'] > 0;
$total_bayar = (float)$pembayaran_detail['total_bayar'];
if ($total_bayar <= 0) {
    $total_bayar = (float)$pembayaran_detail['jumlah'];
    if ($show_denda) $total_bayar += (float)$pembayaran_detail['denda'];
}

// Info penanda tangan
$nama_bendahara = isset($_SESSION['user']['nama']) ? (string)$_SESSION['user']['nama'] : 'Bendahara';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Kwitansi Pembayaran - <?= h($pembayaran_detail['nama_tagihan']) ?></title>
  <!-- CSS khusus print kwitansi -->
  <link rel="stylesheet" href="../assets/css/kwitansi_print.css" />
</head>
<body>
  <main class="kwitansi size-a5" role="document" aria-label="Kwitansi Pembayaran">
    <!-- Kop Instansi -->
    <header class="kwitansi__header">
      <div class="brand">
        <!-- Ganti logo jika ada -->
        <img src="../assets/images/logo_smk_binjai.jpg" alt="Logo Sekolah" class="brand__logo" />
        <div class="brand__meta">
          <div class="brand__name">SMK BM PABA BINJAI</div>
          <div class="brand__addr">Jl. Pendidikan No. 123, Binjai</div>
          <div class="brand__contact">Telp: (061) 123456 • Email: info@smkpaba.sch.id</div>
        </div>
      </div>

      <div class="receipt">
        <div class="receipt__title">KWITANSI PEMBAYARAN</div>
        <div class="receipt__number">No: <?= h($no_kwitansi) ?></div>
      </div>
    </header>

    <!-- Identitas Siswa -->
    <section class="section">
      <table class="kv-table">
        <tbody>
          <tr>
            <th>Nama Siswa</th>
            <td><?= h($pembayaran_detail['nama_lengkap']) ?></td>
          </tr>
          <tr>
            <th>NISN</th>
            <td><?= h($pembayaran_detail['nisn']) ?></td>
          </tr>
          <tr>
            <th>Kelas/Jurusan</th>
            <td><?= h($pembayaran_detail['kelas']) ?> / <?= h($pembayaran_detail['jurusan']) ?></td>
          </tr>
          <tr>
            <th>Tanggal Pembayaran</th>
            <td><?= h($tanggal_resmi) ?></td>
          </tr>
          <tr>
            <th>Metode Pembayaran</th>
            <td><?= h($pembayaran_detail['metode_pembayaran']) ?></td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Rincian Pembayaran -->
    <section class="section">
      <table class="detail-table">
        <thead>
          <tr>
            <th>Uraian</th>
            <th class="text-right">Jumlah</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= h($pembayaran_detail['nama_tagihan']) ?> (<?= h((string)$pembayaran_detail['periode']) ?>)</td>
            <td class="text-right"><?= rp($pembayaran_detail['jumlah_tagihan']) ?></td>
          </tr>
          <?php if ($show_denda): ?>
          <tr>
            <td>
              Denda Keterlambatan
              <?php if (!empty($pembayaran_detail['keterangan_denda'])): ?>
                <div class="muted">(<?= h($pembayaran_detail['keterangan_denda']) ?>)</div>
              <?php endif; ?>
            </td>
            <td class="text-right"><?= rp($pembayaran_detail['denda']) ?></td>
          </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <th>Total Pembayaran</th>
            <th class="text-right"><?= rp($total_bayar) ?></th>
          </tr>
        </tfoot>
      </table>
    </section>

    <!-- Tanda Tangan -->
    <section class="section signature-block">
      <div class="sign-place">
        <div>Binjai, <?= h($tanggal_resmi) ?></div>
        <div class="muted">Bendahara SMK BM PABA</div>
        <div class="sign-line"></div>
        <div class="sign-name"><?= h($nama_bendahara) ?></div>
      </div>
      <!-- (Opsional) QR/Barcode bukti -->
      <!-- <div class="qr-box"><img src="../assets/images/qr_example.png" alt="QR Validasi"></div> -->
    </section>

    <footer class="footer">
      Kwitansi ini merupakan bukti pembayaran yang sah. Mohon disimpan untuk keperluan administrasi.
    </footer>

    <!-- Watermark opsional -->
    <!-- <div class="watermark">SMK BM PABA BINJAI</div> -->
  </main>

  <!-- Tombol cetak (hanya tampil di layar, disembunyikan saat print) -->
  <div class="toolbar no-print">
    <button onclick="window.print()" class="btn-print">
      Cetak Kwitansi
    </button>
  </div>
</body>
</html>