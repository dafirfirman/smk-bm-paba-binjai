<?php
session_start();
require_once '../config/koneksi.php';
require_once '../config/functions.php';

// Batasi hanya admin
if (!isset($_SESSION['user']) || (($_SESSION['user']['role'] ?? '') !== 'admin')) {
    http_response_code(403);
    echo "Akses ditolak.";
    exit();
}

$page_title = "Manajemen Pengguna";

/* =========================
   Helper kecil
   ========================= */
function flash_and_redirect(string $msg, bool $ok = false): void {
    $_SESSION[$ok ? 'success' : 'error'] = $msg;
    header("Location: manajemen_pengguna.php");
    exit();
}
function need_fields(array $fields, array $src): bool {
    foreach ($fields as $f) if (!isset($src[$f]) || trim((string)$src[$f]) === '') return false;
    return true;
}
function valid_date(?string $s): bool {
    if (!$s) return false;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}
function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/* =========================
   EDIT PENGGUNA
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_pengguna'])) {
    $id          = cleanInput($_POST['id']);
    $username    = cleanInput($_POST['username']);
    $role        = cleanInput($_POST['role']);
    $email       = cleanInput($_POST['email']);
    $no_telepon  = cleanInput($_POST['no_telepon']);
    $status      = cleanInput($_POST['status']);

    if (!in_array($role, ['admin','bendahara','siswa'], true))        flash_and_redirect("Role tidak valid");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                   flash_and_redirect("Format email tidak valid");
    if (!preg_match('/^[0-9]{10,15}$/', $no_telepon))                 flash_and_redirect("No telepon harus 10–15 digit angka");

    // Cek duplikat username/email
    $cek = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? AND deleted_at IS NULL");
    $cek->execute([$username, $email, $id]);
    if ($cek->fetch()) flash_and_redirect("Username atau email sudah digunakan oleh pengguna lain");

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE users SET
                username = ?, role = ?, email = ?, no_telepon = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$username, $role, $email, $no_telepon, $status, $id]);

        if ($role === 'siswa') {
            // Data siswa
            $nisn            = cleanInput($_POST['nisn'] ?? '');
            $nama_lengkap    = cleanInput($_POST['nama_lengkap'] ?? '');
            $tempat_lahir    = cleanInput($_POST['tempat_lahir'] ?? '');
            $tanggal_lahir   = cleanInput($_POST['tanggal_lahir'] ?? '');
            $jenis_kelamin   = cleanInput($_POST['jenis_kelamin'] ?? '');
            $alamat          = cleanInput($_POST['alamat'] ?? '');
            $email_siswa     = cleanInput($_POST['email_siswa'] ?? $email);
            $no_telepon_s    = cleanInput($_POST['no_telepon_siswa'] ?? $no_telepon);
            $kelas           = cleanInput($_POST['kelas'] ?? '');
            $jurusan         = cleanInput($_POST['jurusan'] ?? '');
            $prestasi        = cleanInput($_POST['prestasi'] ?? '');
            $nama_wali       = cleanInput($_POST['nama_wali'] ?? '');
            $no_telepon_wali = cleanInput($_POST['no_telepon_wali'] ?? '');

            if (!need_fields(['nisn','nama_lengkap','jenis_kelamin','kelas'], $_POST)) {
                $pdo->rollBack(); flash_and_redirect("Field siswa wajib (NISN, Nama Lengkap, Jenis Kelamin, Kelas) harus diisi");
            }
            if ($tanggal_lahir && !valid_date($tanggal_lahir))          { $pdo->rollBack(); flash_and_redirect("Format tanggal lahir harus YYYY-MM-DD"); }
            if ($email_siswa && !filter_var($email_siswa, FILTER_VALIDATE_EMAIL)) { $pdo->rollBack(); flash_and_redirect("Format email siswa tidak valid"); }
            if ($no_telepon_s && !preg_match('/^[0-9]{10,15}$/', $no_telepon_s)) { $pdo->rollBack(); flash_and_redirect("No telepon siswa harus 10–15 digit angka"); }
            if ($no_telepon_wali && !preg_match('/^[0-9]{10,15}$/', $no_telepon_wali)) { $pdo->rollBack(); flash_and_redirect("No telepon wali harus 10–15 digit angka"); }

            // siswa exists?
            $q = $pdo->prepare("SELECT id FROM siswa WHERE user_id = ?");
            $q->execute([$id]);
            $siswaRow = $q->fetch();

            // unique NISN
            $q2 = $pdo->prepare("SELECT id FROM siswa WHERE nisn = ? AND user_id <> ?");
            $q2->execute([$nisn, $id]);
            if ($q2->fetch()) { $pdo->rollBack(); flash_and_redirect("NISN sudah digunakan oleh siswa lain"); }

            if ($siswaRow) {
                $up = $pdo->prepare("
                    UPDATE siswa SET 
                        nisn=?, nama_lengkap=?, tempat_lahir=?, tanggal_lahir=?, jenis_kelamin=?, alamat=?, email=?, 
                        no_telepon=?, kelas=?, jurusan=?, prestasi=?, nama_wali=?, no_telepon_wali=?
                    WHERE user_id=?
                ");
                $up->execute([
                    $nisn,$nama_lengkap,$tempat_lahir,$tanggal_lahir,$jenis_kelamin,$alamat,$email_siswa,
                    $no_telepon_s,$kelas,$jurusan,$prestasi,$nama_wali,$no_telepon_wali,$id
                ]);
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO siswa
                    (user_id, nisn, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, email, no_telepon, kelas, jurusan, prestasi, nama_wali, no_telepon_wali)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $ins->execute([
                    $id,$nisn,$nama_lengkap,$tempat_lahir,$tanggal_lahir,$jenis_kelamin,$alamat,$email_siswa,
                    $no_telepon_s,$kelas,$jurusan,$prestasi,$nama_wali,$no_telepon_wali
                ]);
            }
        } else {
            // Jika berubah bukan siswa, hapus data siswa
            $pdo->prepare("DELETE FROM siswa WHERE user_id=?")->execute([$id]);
        }

        $pdo->commit();
        flash_and_redirect("Pengguna berhasil diperbarui!", true);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_and_redirect("Gagal memperbarui pengguna: ".$e->getMessage());
    }
}

/* =========================
   TAMBAH PENGGUNA
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_pengguna'])) {
    $username     = cleanInput($_POST['username']);
    $role         = cleanInput($_POST['role']);
    $email        = cleanInput($_POST['email']);
    $no_telepon   = cleanInput($_POST['no_telepon']);
    $status       = cleanInput($_POST['status']);
    $password     = $_POST['password'] ?? '';
    $password2    = $_POST['confirm_password'] ?? '';

    if (!in_array($role, ['admin','bendahara','siswa'], true))        flash_and_redirect("Role tidak valid");
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))                   flash_and_redirect("Format email tidak valid");
    if (!preg_match('/^[0-9]{10,15}$/', $no_telepon))                 flash_and_redirect("No telepon harus 10–15 digit angka");
    if ($password !== $password2)                                     flash_and_redirect("Password dan konfirmasi password tidak sama");
    if (strlen($password) < 8)                                        flash_and_redirect("Password minimal 8 karakter");

    try {
        $pdo->beginTransaction();

        $cek = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND deleted_at IS NULL");
        $cek->execute([$username, $email]);
        if ($cek->fetch()) { $pdo->rollBack(); flash_and_redirect("Username atau email sudah digunakan"); }

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]);
        $insU = $pdo->prepare("
            INSERT INTO users (username,password,role,email,no_telepon,status,created_at,updated_at)
            VALUES (?,?,?,?,?,?,NOW(),NOW())
        ");
        $insU->execute([$username,$hashed,$role,$email,$no_telepon,$status]);
        $newId = (int)$pdo->lastInsertId();

        if ($role === 'siswa') {
            $nisn            = cleanInput($_POST['nisn'] ?? '');
            $nama_lengkap    = cleanInput($_POST['nama_lengkap'] ?? '');
            $tempat_lahir    = cleanInput($_POST['tempat_lahir'] ?? '');
            $tanggal_lahir   = cleanInput($_POST['tanggal_lahir'] ?? '');
            $jenis_kelamin   = cleanInput($_POST['jenis_kelamin'] ?? '');
            $alamat          = cleanInput($_POST['alamat'] ?? '');
            $email_siswa     = cleanInput($_POST['email_siswa'] ?? $email);
            $no_telepon_s    = cleanInput($_POST['no_telepon_siswa'] ?? $no_telepon);
            $kelas           = cleanInput($_POST['kelas'] ?? '');
            $jurusan         = cleanInput($_POST['jurusan'] ?? '');
            $prestasi        = cleanInput($_POST['prestasi'] ?? '');
            $nama_wali       = cleanInput($_POST['nama_wali'] ?? '');
            $no_telepon_wali = cleanInput($_POST['no_telepon_wali'] ?? '');

            if (!need_fields(['nisn','nama_lengkap','jenis_kelamin','kelas'], $_POST)) {
                $pdo->rollBack(); flash_and_redirect("Field siswa wajib (NISN, Nama Lengkap, Jenis Kelamin, Kelas) harus diisi");
            }
            if ($tanggal_lahir && !valid_date($tanggal_lahir))          { $pdo->rollBack(); flash_and_redirect("Format tanggal lahir harus YYYY-MM-DD"); }
            if ($email_siswa && !filter_var($email_siswa, FILTER_VALIDATE_EMAIL)) { $pdo->rollBack(); flash_and_redirect("Format email siswa tidak valid"); }
            if ($no_telepon_s && !preg_match('/^[0-9]{10,15}$/', $no_telepon_s)) { $pdo->rollBack(); flash_and_redirect("No telepon siswa harus 10–15 digit angka"); }
            if ($no_telepon_wali && !preg_match('/^[0-9]{10,15}$/', $no_telepon_wali)) { $pdo->rollBack(); flash_and_redirect("No telepon wali harus 10–15 digit angka"); }

            $cekN = $pdo->prepare("SELECT id FROM siswa WHERE nisn = ?");
            $cekN->execute([$nisn]);
            if ($cekN->fetch()) { $pdo->rollBack(); flash_and_redirect("NISN sudah digunakan"); }

            $insS = $pdo->prepare("
                INSERT INTO siswa
                (user_id, nisn, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, alamat, email, no_telepon, kelas, jurusan, prestasi, nama_wali, no_telepon_wali)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $insS->execute([
                $newId,$nisn,$nama_lengkap,$tempat_lahir,$tanggal_lahir,$jenis_kelamin,$alamat,$email_siswa,
                $no_telepon_s,$kelas,$jurusan,$prestasi,$nama_wali,$no_telepon_wali
            ]);
        }

        $pdo->commit();
        flash_and_redirect("Pengguna baru berhasil ditambahkan!", true);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062) {
            flash_and_redirect("Username, email, atau NISN sudah digunakan");
        }
        flash_and_redirect("Gagal menambahkan pengguna: ".$e->getMessage());
    }
}

/* =========================
   HAPUS PENGGUNA
   ========================= */
if (isset($_GET['hapus'])) {
    $id = cleanInput($_GET['hapus']);
    try {
        $pdo->beginTransaction();
        // Soft delete - tandai sebagai dihapus
        $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM siswa WHERE user_id=?")->execute([$id]);
        $pdo->commit();
        flash_and_redirect("Pengguna berhasil dihapus!", true);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_and_redirect("Gagal menghapus pengguna: ".$e->getMessage());
    }
}

/* =========================
   AMBIL SEMUA PENGGUNA
   ========================= */
$stmt = $pdo->query("
    SELECT 
        u.*,
        s.id AS siswa_id, s.nisn, s.nama_lengkap, s.tempat_lahir, s.tanggal_lahir, s.jenis_kelamin,
        s.alamat, s.email AS email_siswa, s.no_telepon AS no_telepon_siswa,
        s.kelas, s.jurusan, s.prestasi, s.nama_wali, s.no_telepon_wali
    FROM users u
    LEFT JOIN siswa s ON s.user_id = u.id
    WHERE u.deleted_at IS NULL
    ORDER BY 
        CASE 
            WHEN u.role = 'admin' THEN 1
            WHEN u.role = 'bendahara' THEN 2
            WHEN u.role = 'siswa' THEN 3
            ELSE 4
        END,
        u.username
");
$pengguna = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($page_title) ?></title>
  <link rel="stylesheet" href="../assets/css/style_manajemen_pengguna.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body class="dashboard-body">
  <!-- Sidebar -->
  <?php include_once '../includes/sidebar.php'; ?>

  <div class="main-content-wrapper">
    <div class="content-container">
      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-users-gear"></i> Manajemen Pengguna</h1>
        <div class="page-actions">
          <button type="button" class="btn btn-primary" onclick="openAddModal()">
            <i class="fa-solid fa-user-plus"></i> Tambah Pengguna
          </button>
        </div>
      </div>

      <!-- Alerts -->
      <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i><?= h($_SESSION['error']); unset($_SESSION['error']); ?></div>
      <?php endif; ?>
      <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i><?= h($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php endif; ?>

      <!-- Tabel Pengguna -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fa-solid fa-list-check"></i> Daftar Pengguna</h3>
          <div class="table-tools">
            <input type="search" class="search-input" placeholder="Cari username/email/role..." oninput="filterUserTable(this.value)">
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="user-table" id="userTable">
              <thead>
                <tr>
                  <th>No</th>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Email</th>
                  <th>No. Telp</th>
                  <th>Status</th>
                  <th>Terakhir Login</th>
                  <th>Data Siswa</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $prev_role = null;
                foreach ($pengguna as $i => $u): 
                  // Skip duplicate entries based on username and role
                  if ($prev_role === $u['role'] && $prev_username === $u['username']) {
                    continue;
                  }
                  $prev_role = $u['role'];
                  $prev_username = $u['username'];
                ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= h($u['username']) ?></td>
                  <td><span class="badge role-<?= h($u['role']) ?>"><?= ucfirst(h($u['role'])) ?></span></td>
                  <td><?= h($u['email']) ?></td>
                  <td><?= h($u['no_telepon']) ?></td>
                  <td><span class="status-badge status-<?= h($u['status']) ?>"><?= ucfirst(h($u['status'])) ?></span></td>
                  <td><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : 'Belum pernah' ?></td>
                  <td>
                    <?php if ($u['role'] === 'siswa'): ?>
                      <div class="small">
                        <div><strong><?= h($u['nama_lengkap'] ?: '-') ?></strong></div>
                        <div>NISN: <?= h($u['nisn'] ?: '-') ?></div>
                        <div>Kelas/Jurusan: <?= h($u['kelas'] ?: '-') ?>/<?= h($u['jurusan'] ?: '-') ?></div>
                      </div>
                    <?php else: ?>-<?php endif; ?>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <button class="btn btn-ghost" onclick='openEditModal(<?= json_encode($u, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>
                        <i class="fa-solid fa-pen-to-square"></i> Edit
                      </button>
                      <a class="btn btn-danger" href="manajemen_pengguna.php?hapus=<?= (int)$u['id'] ?>" onclick="return confirm('Yakin ingin menghapus pengguna ini?')">
                        <i class="fa-solid fa-trash-can"></i> Hapus
                      </a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pengguna)): ?>
                <tr><td colspan="9" class="text-center">Belum ada data pengguna.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
    <?php include_once '../includes/footer.php'; ?>
  </div>

  <!-- Modal Edit -->
  <div id="editModal" class="modal" aria-hidden="true">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fa-solid fa-user-pen"></i> Edit Pengguna</h2>
        <button type="button" class="btn-close" onclick="closeEditModal()" aria-label="Tutup">&times;</button>
      </div>

      <form method="POST" id="editForm">
        <input type="hidden" name="edit_pengguna" value="1">
        <input type="hidden" name="id" id="edit_id">

        <div class="grid-3">
          <div class="form-group">
            <label for="edit_username">Username</label>
            <input type="text" id="edit_username" name="username" required>
          </div>
          <div class="form-group">
            <label for="edit_role">Role</label>
            <select id="edit_role" name="role" required onchange="toggleSiswaSection('edit')">
              <option value="admin">Admin</option>
              <option value="bendahara">Bendahara</option>
              <option value="siswa">Siswa</option>
            </select>
          </div>
          <div class="form-group">
            <label for="edit_status">Status</label>
            <select id="edit_status" name="status" required>
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label for="edit_email">Email</label>
            <input type="email" id="edit_email" name="email" required>
          </div>
          <div class="form-group">
            <label for="edit_no_telepon">No Telepon</label>
            <input type="tel" id="edit_no_telepon" name="no_telepon" pattern="[0-9]{10,15}" required>
            <small class="helper">10–15 digit angka</small>
          </div>
        </div>

        <div id="edit_section_siswa" class="section-siswa hidden">
          <h3><i class="fa-solid fa-id-card-clip"></i> Identitas Siswa</h3>

          <div class="grid-3">
            <div class="form-group">
              <label for="edit_nisn">NISN *</label>
              <input type="text" id="edit_nisn" name="nisn" maxlength="20">
            </div>
            <div class="form-group">
              <label for="edit_nama_lengkap">Nama Lengkap *</label>
              <input type="text" id="edit_nama_lengkap" name="nama_lengkap">
            </div>
            <div class="form-group">
              <label for="edit_jenis_kelamin">Jenis Kelamin *</label>
              <select id="edit_jenis_kelamin" name="jenis_kelamin">
                <option value="">Pilih</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
              </select>
            </div>
          </div>

          <div class="grid-3">
            <div class="form-group">
              <label for="edit_tempat_lahir">Tempat Lahir</label>
              <input type="text" id="edit_tempat_lahir" name="tempat_lahir">
            </div>
            <div class="form-group">
              <label for="edit_tanggal_lahir">Tanggal Lahir</label>
              <input type="date" id="edit_tanggal_lahir" name="tanggal_lahir">
            </div>
            <div class="form-group">
              <label for="edit_kelas">Kelas *</label>
              <input type="text" id="edit_kelas" name="kelas" placeholder="Contoh: XI IPA 2">
            </div>
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label for="edit_jurusan">Jurusan</label>
              <input type="text" id="edit_jurusan" name="jurusan">
            </div>
            <div class="form-group">
              <label for="edit_prestasi">Prestasi</label>
              <input type="text" id="edit_prestasi" name="prestasi">
            </div>
          </div>

          <div class="form-group">
            <label for="edit_alamat">Alamat</label>
            <textarea id="edit_alamat" name="alamat" rows="2"></textarea>
          </div>

          <div class="grid-3">
            <div class="form-group">
              <label for="edit_email_siswa">Email Siswa</label>
              <input type="email" id="edit_email_siswa" name="email_siswa">
            </div>
            <div class="form-group">
              <label for="edit_no_telepon_siswa">No Telepon Siswa</label>
              <input type="tel" id="edit_no_telepon_siswa" name="no_telepon_siswa" pattern="[0-9]{10,15}">
            </div>
            <div class="form-group">
              <label for="edit_nama_wali">Nama Wali</label>
              <input type="text" id="edit_nama_wali" name="nama_wali">
            </div>
          </div>

          <div class="form-group">
            <label for="edit_no_telepon_wali">No Telepon Wali</label>
            <input type="tel" id="edit_no_telepon_wali" name="no_telepon_wali" pattern="[0-9]{10,15}">
          </div>

          <div class="small muted">(*) Wajib diisi saat role = siswa</div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="closeEditModal()">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Add -->
  <div id="addModal" class="modal" aria-hidden="true">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fa-solid fa-user-plus"></i> Tambah Pengguna</h2>
        <button type="button" class="btn-close" onclick="closeAddModal()" aria-label="Tutup">&times;</button>
      </div>

      <form method="POST" id="addForm">
        <input type="hidden" name="tambah_pengguna" value="1">

        <div class="grid-3">
          <div class="form-group">
            <label for="add_username">Username</label>
            <input type="text" id="add_username" name="username" required minlength="3" maxlength="50">
          </div>
          <div class="form-group">
            <label for="add_role">Role</label>
            <select id="add_role" name="role" required onchange="toggleSiswaSection('add')">
              <option value="">Pilih Role</option>
              <option value="admin">Admin</option>
              <option value="bendahara">Bendahara</option>
              <option value="siswa">Siswa</option>
            </select>
          </div>
          <div class="form-group">
            <label for="add_status">Status</label>
            <select id="add_status" name="status" required>
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label for="add_email">Email</label>
            <input type="email" id="add_email" name="email" required>
          </div>
          <div class="form-group">
            <label for="add_no_telepon">No Telepon</label>
            <input type="tel" id="add_no_telepon" name="no_telepon" pattern="[0-9]{10,15}" required>
            <small class="helper">10–15 digit angka</small>
          </div>
        </div>

        <div class="grid-2">
          <div class="form-group">
            <label for="add_password">Password</label>
            <div class="password-input">
              <input type="password" id="add_password" name="password" required minlength="8">
              <button type="button" class="toggle-password" onclick="togglePassword('add_password')"><i class="fa-solid fa-eye"></i></button>
            </div>
            <small class="helper">Minimal 8 karakter</small>
          </div>
          <div class="form-group">
            <label for="add_confirm_password">Konfirmasi Password</label>
            <div class="password-input">
              <input type="password" id="add_confirm_password" name="confirm_password" required minlength="8">
              <button type="button" class="toggle-password" onclick="togglePassword('add_confirm_password')"><i class="fa-solid fa-eye"></i></button>
            </div>
          </div>
        </div>

        <div id="add_section_siswa" class="section-siswa hidden">
          <h3><i class="fa-solid fa-id-card-clip"></i> Identitas Siswa (Wajib jika Role = Siswa)</h3>

          <div class="grid-3">
            <div class="form-group">
              <label for="add_nisn">NISN *</label>
              <input type="text" id="add_nisn" name="nisn" maxlength="20">
            </div>
            <div class="form-group">
              <label for="add_nama_lengkap">Nama Lengkap *</label>
              <input type="text" id="add_nama_lengkap" name="nama_lengkap">
            </div>
            <div class="form-group">
              <label for="add_jenis_kelamin">Jenis Kelamin *</label>
              <select id="add_jenis_kelamin" name="jenis_kelamin">
                <option value="">Pilih</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
              </select>
            </div>
          </div>

          <div class="grid-3">
            <div class="form-group">
              <label for="add_tempat_lahir">Tempat Lahir</label>
              <input type="text" id="add_tempat_lahir" name="tempat_lahir">
            </div>
            <div class="form-group">
              <label for="add_tanggal_lahir">Tanggal Lahir</label>
              <input type="date" id="add_tanggal_lahir" name="tanggal_lahir">
            </div>
            <div class="form-group">
              <label for="add_kelas">Kelas *</label>
              <input type="text" id="add_kelas" name="kelas" placeholder="Contoh: X IPS 1">
            </div>
          </div>

          <div class="grid-2">
            <div class="form-group">
              <label for="add_jurusan">Jurusan</label>
              <input type="text" id="add_jurusan" name="jurusan">
            </div>
            <div class="form-group">
              <label for="add_prestasi">Prestasi</label>
              <input type="text" id="add_prestasi" name="prestasi">
            </div>
          </div>

          <div class="form-group">
            <label for="add_alamat">Alamat</label>
            <textarea id="add_alamat" name="alamat" rows="2"></textarea>
          </div>

          <div class="grid-3">
            <div class="form-group">
              <label for="add_email_siswa">Email Siswa</label>
              <input type="email" id="add_email_siswa" name="email_siswa">
            </div>
            <div class="form-group">
              <label for="add_no_telepon_siswa">No Telepon Siswa</label>
              <input type="tel" id="add_no_telepon_siswa" name="no_telepon_siswa" pattern="[0-9]{10,15}">
            </div>
            <div class="form-group">
              <label for="add_nama_wali">Nama Wali</label>
              <input type="text" id="add_nama_wali" name="nama_wali">
            </div>
          </div>

          <div class="form-group">
            <label for="add_no_telepon_wali">No Telepon Wali</label>
            <input type="tel" id="add_no_telepon_wali" name="no_telepon_wali" pattern="[0-9]{10,15}">
          </div>

          <div class="small muted">(*) Wajib diisi saat role = siswa</div>
        </div>

        <div class="form-actions">
          <button type="button" class="btn btn-ghost" onclick="closeAddModal()">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Tambah</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Filter table cepat (client-side)
    function filterUserTable(q) {
      q = (q || '').toLowerCase();
      const rows = document.querySelectorAll('#userTable tbody tr');
      rows.forEach(tr => {
        const txt = tr.innerText.toLowerCase();
        tr.style.display = txt.includes(q) ? '' : 'none';
      });
    }

    // Toggle password
    function togglePassword(id){
      const el = document.getElementById(id);
      const icon = el.nextElementSibling.querySelector('i');
      if (el.type === 'password'){ el.type='text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
      else { el.type='password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
    }

    // Toggle siswa section based on role
    function toggleSiswaSection(formType) {
      const roleSelect = document.getElementById(`${formType}_role`);
      const siswaSection = document.getElementById(`${formType}_section_siswa`);
      
      if (roleSelect.value === 'siswa') {
        siswaSection.classList.remove('hidden');
        // Make required fields visible
        const requiredFields = ['nisn', 'nama_lengkap', 'jenis_kelamin', 'kelas'];
        requiredFields.forEach(field => {
          const input = document.getElementById(`${formType}_${field}`);
          if (input) {
            input.setAttribute('required', 'required');
          }
        });
      } else {
        siswaSection.classList.add('hidden');
        // Remove required attribute
        const allFields = siswaSection.querySelectorAll('input, select, textarea');
        allFields.forEach(field => {
          field.removeAttribute('required');
        });
      }
    }

    // Modal helpers
    function openAddModal(){ 
      document.getElementById('addModal').style.display='flex'; 
      document.getElementById('add_username').focus(); 
    }
    function closeAddModal(){ 
      document.getElementById('addModal').style.display='none'; 
      document.getElementById('addForm').reset(); 
      document.getElementById('add_section_siswa').classList.add('hidden'); 
    }
    function openEditModal(user){
      // Prefill umum
      document.getElementById('edit_id').value = user.id;
      document.getElementById('edit_username').value = user.username;
      document.getElementById('edit_role').value = user.role;
      document.getElementById('edit_email').value = user.email;
      document.getElementById('edit_no_telepon').value = user.no_telepon;
      document.getElementById('edit_status').value = user.status;

      const sec = document.getElementById('edit_section_siswa');
      if (user.role === 'siswa') {
        sec.classList.remove('hidden');
        document.getElementById('edit_nisn').value = user.nisn || '';
        document.getElementById('edit_nama_lengkap').value = user.nama_lengkap || '';
        document.getElementById('edit_tempat_lahir').value = user.tempat_lahir || '';
        document.getElementById('edit_tanggal_lahir').value = (user.tanggal_lahir || '').substring(0,10);
        document.getElementById('edit_jenis_kelamin').value = user.jenis_kelamin || '';
        document.getElementById('edit_alamat').value = user.alamat || '';
        document.getElementById('edit_email_siswa').value = user.email_siswa || user.email || '';
        document.getElementById('edit_no_telepon_siswa').value = user.no_telepon_siswa || user.no_telepon || '';
        document.getElementById('edit_kelas').value = user.kelas || '';
        document.getElementById('edit_jurusan').value = user.jurusan || '';
        document.getElementById('edit_prestasi').value = user.prestasi || '';
        document.getElementById('edit_nama_wali').value = user.nama_wali || '';
        document.getElementById('edit_no_telepon_wali').value = user.no_telepon_wali || '';
        
        // Set required attributes for siswa fields
        const requiredFields = ['nisn', 'nama_lengkap', 'jenis_kelamin', 'kelas'];
        requiredFields.forEach(field => {
          const input = document.getElementById(`edit_${field}`);
          if (input) {
            input.setAttribute('required', 'required');
          }
        });
      } else {
        sec.classList.add('hidden');
        // Remove required attribute for non-siswa
        const allFields = sec.querySelectorAll('input, select, textarea');
        allFields.forEach(field => {
          field.removeAttribute('required');
          field.value = '';
        });
      }
      document.getElementById('editModal').style.display='flex';
    }
    function closeEditModal(){ 
      document.getElementById('editModal').style.display='none'; 
    }

    // Close modals when clicking backdrop
    window.addEventListener('click', (e)=>{
      if (e.target === document.getElementById('addModal'))  closeAddModal();
      if (e.target === document.getElementById('editModal')) closeEditModal();
    });

    // Validasi front-end sederhana
    document.getElementById('addForm').addEventListener('submit', function(e){
      const role = document.getElementById('add_role').value;
      const email = document.getElementById('add_email').value;
      const pwd   = document.getElementById('add_password').value;
      const pwd2  = document.getElementById('add_confirm_password').value;

      const emailOk = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (!emailOk) { alert('Format email tidak valid'); e.preventDefault(); return; }
      if (pwd.length < 8) { alert('Password minimal 8 karakter'); e.preventDefault(); return; }
      if (pwd !== pwd2) { alert('Password dan konfirmasi password tidak sama'); e.preventDefault(); return; }

      if (role === 'siswa') {
        const req = [['add_nisn','NISN'],['add_nama_lengkap','Nama Lengkap'],['add_jenis_kelamin','Jenis Kelamin'],['add_kelas','Kelas']];
        for (const [id,label] of req) {
          const el = document.getElementById(id);
          if (!el || !el.value.trim()) { alert(label+' wajib diisi untuk siswa'); e.preventDefault(); return; }
        }
        const emS = document.getElementById('add_email_siswa').value;
        if (emS && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emS)) { alert('Format email siswa tidak valid'); e.preventDefault(); return; }
        const ntS = document.getElementById('add_no_telepon_siswa').value;
        if (ntS && !/^[0-9]{10,15}$/.test(ntS)) { alert('No telepon siswa harus 10–15 digit angka'); e.preventDefault(); return; }
        const ntW = document.getElementById('add_no_telepon_wali').value;
        if (ntW && !/^[0-9]{10,15}$/.test(ntW)) { alert('No telepon wali harus 10–15 digit angka'); e.preventDefault(); return; }
      }
    });

    document.getElementById('editForm').addEventListener('submit', function(e){
      const email = document.getElementById('edit_email').value;
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { alert('Format email tidak valid'); e.preventDefault(); return; }
      const nt = document.getElementById('edit_no_telepon').value;
      if (!/^[0-9]{10,15}$/.test(nt)) { alert('No telepon harus 10–15 digit angka'); e.preventDefault(); return; }

      if (document.getElementById('edit_role').value === 'siswa') {
        const req = [['edit_nisn','NISN'],['edit_nama_lengkap','Nama Lengkap'],['edit_jenis_kelamin','Jenis Kelamin'],['edit_kelas','Kelas']];
        for (const [id,label] of req) {
          const el = document.getElementById(id);
          if (!el || !el.value.trim()) { alert(label+' wajib diisi untuk siswa'); e.preventDefault(); return; }
        }
        const emS = document.getElementById('edit_email_siswa').value;
        if (emS && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emS)) { alert('Format email siswa tidak valid'); e.preventDefault(); return; }
        const ntS = document.getElementById('edit_no_telepon_siswa').value;
        if (ntS && !/^[0-9]{10,15}$/.test(ntS)) { alert('No telepon siswa harus 10–15 digit angka'); e.preventDefault(); return; }
        const ntW = document.getElementById('edit_no_telepon_wali').value;
        if (ntW && !/^[0-9]{10,15}$/.test(ntW)) { alert('No telepon wali harus 10–15 digit angka'); e.preventDefault(); return; }
      }
    });
  </script>
</body>
</html>