<?php
session_start();
include '../config/koneksi.php';

// ================== CEK LOGIN & ROLE ==================
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user_login = (int) $_SESSION['id_user'];

// Ambil role user login (prepared)
$role = '';
if ($stmtRole = $conn->prepare("SELECT role FROM users WHERE id_user = ? LIMIT 1")) {
    $stmtRole->bind_param("i", $id_user_login);
    $stmtRole->execute();
    $resRole = $stmtRole->get_result();
    if ($rowRole = $resRole->fetch_assoc()) {
        $role = $rowRole['role'];
    }
    $stmtRole->close();
}

if ($role !== 'super_admin') {
    header("Location: ../auth/unauthorized.php");
    exit;
}

$success = '';
$error   = '';

// ================== OPSIONAL: DAFTAR UNIT ==================
$unitOptions = [];
$cekUnitTable = $conn->query("SHOW TABLES LIKE 'unit'");
if ($cekUnitTable && $cekUnitTable->num_rows > 0) {
    $qUnit = $conn->query("SELECT id_unit, nama_unit FROM unit ORDER BY nama_unit ASC");
    while ($u = $qUnit->fetch_assoc()) {
        $unitOptions[(int)$u['id_unit']] = $u['nama_unit'];
    }
}

// ================== HAPUS PENGGUNA (DELETE) ==================
if (isset($_GET['hapus'])) {
    $id_del = (int) $_GET['hapus'];

    if ($id_del === $id_user_login) {
        $error = "Anda tidak dapat menghapus akun yang sedang digunakan.";
    } elseif ($id_del > 0) {
        try {
            if ($stmtDel = $conn->prepare("DELETE FROM users WHERE id_user = ?")) {
                $stmtDel->bind_param("i", $id_del);
                $stmtDel->execute();

                if ($stmtDel->affected_rows > 0) {
                    $success = "Pengguna berhasil dihapus.";
                } else {
                    $error = "Gagal menghapus pengguna. Kemungkinan data masih terhubung dengan tabel lain.";
                }
                $stmtDel->close();
            } else {
                $error = "Gagal menyiapkan query hapus pengguna.";
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Gagal menghapus pengguna: " . $e->getMessage();
        }
    }
}

// ================== UPDATE PENGGUNA (EDIT) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    $id_user = (int) ($_POST['id_user'] ?? 0);
    $nama    = trim($_POST['nama'] ?? '');
    $username= trim($_POST['username'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $role_u  = trim($_POST['role'] ?? '');
    $id_unit_post = isset($_POST['id_unit']) ? (int) $_POST['id_unit'] : 0;
    $password = $_POST['password'] ?? '';

    // -------- VALIDASI DASAR --------
    if ($id_user <= 0 || $nama === '' || $username === '' || $role_u === '') {
        $error = "Data tidak lengkap untuk update pengguna.";
    } elseif (strlen($username) < 4 || strlen($username) > 30) {
        $error = "Username harus 4–30 karakter.";
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        $error = "Username hanya boleh huruf, angka, dan underscore (_).";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (!in_array($role_u, ['super_admin', 'bagian_umum', 'peminjam'], true)) {
        $error = "Role tidak valid.";
    } else {
        // -------- CEK USERNAME UNIQUE (prepared) --------
        if ($stmtCek = $conn->prepare("
            SELECT id_user 
            FROM users 
            WHERE username = ? AND id_user != ?
            LIMIT 1
        ")) {
            $stmtCek->bind_param("si", $username, $id_user);
            $stmtCek->execute();
            $resCek = $stmtCek->get_result();
            if ($resCek->num_rows > 0) {
                $error = "Username sudah digunakan pengguna lain.";
            }
            $stmtCek->close();
        } else {
            $error = "Gagal menyiapkan query pengecekan username.";
        }
    }

    if ($error === '') {
        // Siapkan nilai email & unit (boleh NULL)
        $emailVal   = $email !== '' ? $email : null;
        $idUnitVal  = ($id_unit_post > 0 && isset($unitOptions[$id_unit_post]))
                      ? $id_unit_post : null;

        try {
            if ($password !== '') {
                // Update + password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $sqlUpdate = "
                    UPDATE users 
                    SET nama = ?, username = ?, email = ?, role = ?, id_unit = ?, password = ?
                    WHERE id_user = ?
                ";
                if ($stmtUp = $conn->prepare($sqlUpdate)) {
                    $stmtUp->bind_param(
                        "ssssisi",
                        $nama,
                        $username,
                        $emailVal,
                        $role_u,
                        $idUnitVal,
                        $password_hash,
                        $id_user
                    );
                    $stmtUp->execute();

                    if ($stmtUp->affected_rows >= 0) {
                        $success = "Data pengguna berhasil diperbarui.";
                    } else {
                        $error = "Tidak ada perubahan data.";
                    }
                    $stmtUp->close();
                } else {
                    $error = "Gagal menyiapkan query update pengguna.";
                }
            } else {
                // Update tanpa password
                $sqlUpdate = "
                    UPDATE users 
                    SET nama = ?, username = ?, email = ?, role = ?, id_unit = ?
                    WHERE id_user = ?
                ";
                if ($stmtUp = $conn->prepare($sqlUpdate)) {
                    $stmtUp->bind_param(
                        "ssssii",
                        $nama,
                        $username,
                        $emailVal,
                        $role_u,
                        $idUnitVal,
                        $id_user
                    );
                    $stmtUp->execute();

                    if ($stmtUp->affected_rows >= 0) {
                        $success = "Data pengguna berhasil diperbarui.";
                    } else {
                        $error = "Tidak ada perubahan data.";
                    }
                    $stmtUp->close();
                } else {
                    $error = "Gagal menyiapkan query update pengguna.";
                }
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Gagal memperbarui pengguna: " . $e->getMessage();
        }
    }
}

// ================== NOTIFIKASI (BELL NAVBAR) ==================
$notifPeminjaman       = [];
$notifRusak            = [];
$jumlahNotifPeminjaman = 0;
$jumlahNotifRusak      = 0;
$jumlahNotif           = 0;

// Peminjaman baru (usulan)
$qNotifPeminjaman = $conn->query("
    SELECT p.id_pinjam, u.nama, p.tanggal_mulai
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.status = 'usulan'
    ORDER BY p.id_pinjam DESC
    LIMIT 5
");
if ($qNotifPeminjaman) {
    while ($row = $qNotifPeminjaman->fetch_assoc()) {
        $notifPeminjaman[] = $row;
    }
}
$jumlahNotifPeminjaman = count($notifPeminjaman);

// Pengembalian rusak
$qNotifRusak = $conn->query("
    SELECT k.id_kembali, k.id_pinjam, u.nama, k.tgl_kembali
    FROM pengembalian k
    JOIN peminjaman p ON k.id_pinjam = p.id_pinjam
    JOIN users u ON p.id_user = u.id_user
    WHERE k.kondisi = 'rusak'
    ORDER BY k.id_kembali DESC
    LIMIT 5
");
if ($qNotifRusak) {
    while ($row = $qNotifRusak->fetch_assoc()) {
        $notifRusak[] = $row;
    }
}
$jumlahNotifRusak = count($notifRusak);
$jumlahNotif      = $jumlahNotifPeminjaman + $jumlahNotifRusak;

// ================== AMBIL DATA SEMUA PENGGUNA ==================
$result = $conn->query("SELECT * FROM users ORDER BY id_user DESC");

// ================== TITLE & TEMPLATE ==================
$pageTitle = 'Kelola Pengguna';

include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
include '../includes/admin/sidebar.php';
?>

<!-- ====== CSS KHUSUS HALAMAN INI (BIAR BERBEDA) ====== -->
<style>
    .user-header-title {
        font-size: 1.4rem;
    }
    .user-header-subtitle {
        font-size: 0.9rem;
    }
    .card-user table thead th {
        font-size: 0.85rem;
        text-transform: none; 
        letter-spacing: .03em;
    }
    .card-user table tbody td {
        font-size: 0.9rem;
    }
    .btn-edit-user,
    .btn-delete-user {
    padding: 0.35rem 0.8rem;
    border-radius: 999px;      /* bentuk pill / kapsul */
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    }

    .btn-edit-user i,
    .btn-delete-user i {
        font-size: 0.9rem;
    }
    .badge-role {
        font-size: 0.75rem;
        padding: .25rem .55rem;
    }
    /* ================================
   STYLE MODAL EDIT PENGGUNA
   ================================ */

    #modalEditUser .modal-dialog {
        max-width: 520px;
    }

    #modalEditUser .modal-content {
        border-radius: 14px;
        border: none;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35);
    }

    /* Header modal */
    #modalEditUser .modal-header {
        background: linear-gradient(90deg, #0d47a1, #1565c0);
        color: #f9fafb;
        border-bottom: none;
    }
    #modalEditUser .btn-close {
        filter: invert(1);
    }

    /* Icon sebelum title */
    #modalEditUser .modal-title::before {
        content: "\f4ff";
        font-family: "Font Awesome 5 Free";
        font-weight: 900;
        font-size: 0.95rem;
        margin-right: 8px;
    }

    /* Body */
    #modalEditUser .modal-body {
        background: #f9fafb;
        padding: 1.1rem 1.25rem 1rem;
    }

    /* Label & input */
    #modalEditUser .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #1f2933;
        margin-bottom: 4px;
    }

    #modalEditUser .form-control,
    #modalEditUser .form-select {
        font-size: 0.9rem;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        padding: 0.45rem 0.65rem;
    }

    #modalEditUser .form-control:focus,
    #modalEditUser .form-select:focus {
        border-color: #0d47a1;
        box-shadow: 0 0 0 0.12rem rgba(13, 71, 161, 0.25);
    }

    /* Footer */
    #modalEditUser .modal-footer {
        background: #f3f4f6;
        border-top: 1px solid #e5e7eb;
        padding: 0.65rem 1.25rem 0.75rem;
        justify-content: space-between;
    }

    /* Tombol Batal */
    #modalEditUser .btn-secondary {
        font-size: 0.85rem;
        border-radius: 999px;
        padding: 0.35rem 0.9rem;
    }

    /* ============================
    TOMBOL SIMPAN PERUBAHAN (BIRU)
    ============================ */
    #modalEditUser .btn-warning {
        background: linear-gradient(90deg, #0d47a1, #1565c0) !important;
        border: none !important;
        color: white !important;
        border-radius: 999px;
        font-size: 0.9rem;
        padding: 0.45rem 1.2rem;
        font-weight: 600;
        transition: 0.25s ease;
    }

    #modalEditUser .btn-warning:hover {
        background: linear-gradient(90deg, #093b8c, #0f57c7) !important;
        transform: translateY(-1px);
    }

    /* Jarak antar field */
    #modalEditUser .mb-3 {
        margin-bottom: 0.7rem !important;
    }


</style>

<div class="container-fluid px-4">

    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <h2 class="fw-bold text-primary mb-1 user-header-title">Kelola Pengguna</h2>
            <p class="text-muted mb-0 user-header-subtitle">
                Manajemen akun pengguna sistem E-Fasilitas (Super Admin, Bagian Umum, &amp; Peminjam).
            </p>
        </div>
        <a href="tambah_pengguna.php" class="btn btn-primary shadow-sm">
            <i class="fas fa-user-plus me-1"></i> Tambah Pengguna
        </a>
    </div>

    <hr class="mt-0 mb-4" style="border-top: 2px solid #0f172a; opacity: .25;">

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4 border-0 card-user">
        <div class="card-header bg-primary text-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-users me-2"></i> Data Pengguna</span>
            <span class="small opacity-75">Total: <?= $result ? $result->num_rows : 0; ?> akun</span>
        </div>

        <div class="card-body">
            <table id="datatablesSimple" class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-center">
                        <th>No</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Unit</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): 
                        $no = 1;
                        while ($row = $result->fetch_assoc()):
                            $roleBadge = [
                                'super_admin' => 'danger',
                                'bagian_umum' => 'warning',
                                'peminjam'    => 'success'
                            ][$row['role']] ?? 'secondary';

                            if (!empty($unitOptions) && isset($unitOptions[(int)$row['id_unit']])) {
                                $nama_unit = $unitOptions[(int)$row['id_unit']];
                            } elseif (!empty($row['id_unit'])) {
                                $nama_unit = 'Unit #' . (int)$row['id_unit'];
                            } else {
                                $nama_unit = '-';
                            }
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++; ?></td>
                        <td><?= htmlspecialchars($row['nama']); ?></td>
                        <td><?= htmlspecialchars($row['username']); ?></td>
                        <td><?= htmlspecialchars($row['email']); ?></td>
                        <td class="text-center">
                            <span class="badge badge-role bg-<?= $roleBadge; ?> text-capitalize">
                                <?= htmlspecialchars($row['role']); ?>
                            </span>
                        </td>
                        <td class="text-center"><?= htmlspecialchars($nama_unit); ?></td>
                        <td class="text-center">
                            <?= $row['created'] ? date('d/m/Y', strtotime($row['created'])) : '-'; ?>
                        </td>
                        <td class="text-center">
                              <!-- Tombol Edit -->
                            <!-- Tombol Edit -->
                            <button
                                class="btn btn-sm btn-warning me-1 btn-edit-user"
                                data-bs-toggle="modal"
                                data-bs-target="#modalEditUser"
                                data-id="<?= $row['id_user']; ?>"
                                data-nama="<?= htmlspecialchars($row['nama'], ENT_QUOTES); ?>"
                                data-username="<?= htmlspecialchars($row['username'], ENT_QUOTES); ?>"
                                data-email="<?= htmlspecialchars($row['email'], ENT_QUOTES); ?>"
                                data-role="<?= htmlspecialchars($row['role'], ENT_QUOTES); ?>"
                                data-id_unit="<?= (int)$row['id_unit']; ?>"
                            >
                                <i class="bi bi-pencil-square"></i>
                            </button>

                            <!-- Tombol Hapus (tidak boleh hapus diri sendiri) -->
                            <?php if ($row['id_user'] != $id_user_login): ?>
                            <a href="kelola_pengguna.php?hapus=<?= $row['id_user']; ?>"
                            class="btn btn-sm btn-danger btn-delete-user"
                            onclick="return confirm('Yakin ingin menghapus pengguna ini?')">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>

                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">
                            Tidak ada data pengguna.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Modal Edit Pengguna -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-labelledby="modalEditUserLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" id="formEditUser">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="modalEditUserLabel">Edit Pengguna</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="id_user" id="edit_id_user">

        <div class="mb-3">
          <label class="form-label fw-semibold">Nama Lengkap</label>
          <input type="text" name="nama" id="edit_nama" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Username</label>
          <input type="text"
                 name="username"
                 id="edit_username"
                 class="form-control"
                 required
                 minlength="4"
                 maxlength="30"
                 pattern="[A-Za-z0-9_]+">
          <div class="form-text">
            Hanya huruf, angka, dan underscore (_), panjang 4–30 karakter.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="email" id="edit_email" class="form-control">
          <div class="form-text">
            Boleh dikosongkan, tapi jika diisi harus format email yang valid.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Role</label>
          <select name="role" id="edit_role" class="form-select" required>
            <option value="super_admin">Super Admin</option>
            <option value="bagian_umum">Bagian Umum</option>
            <option value="peminjam">Peminjam</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Unit</label>
          <?php if (!empty($unitOptions)): ?>
            <select name="id_unit" id="edit_id_unit" class="form-select">
              <option value="0">-- Tanpa Unit --</option>
              <?php foreach ($unitOptions as $id_unit => $nama_unit): ?>
                <option value="<?= $id_unit; ?>"><?= htmlspecialchars($nama_unit); ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="number" name="id_unit" id="edit_id_unit" class="form-control" placeholder="ID Unit (jika ada)">
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Password Baru (opsional)</label>
          <input type="password" name="password" id="edit_password" class="form-control"
                 placeholder="Kosongkan jika tidak diubah">
          <div class="form-text">
            Jika diisi: minimal 6 karakter, kombinasi huruf dan angka.
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-warning">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
// Helper: cek email valid
function isValidEmail(email) {
    // simple regex, cukup untuk validasi front-end
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Helper: cek username valid (4-30, huruf/angka/_)
function isValidUsername(username) {
    const re = /^[A-Za-z0-9_]{4,30}$/;
    return re.test(username);
}

// Helper: cek password kuat (min 6, ada huruf & angka)
function isStrongPassword(pw) {
    if (pw.length < 6) return false;
    const hasLetter = /[A-Za-z]/.test(pw);
    const hasDigit  = /[0-9]/.test(pw);
    return hasLetter && hasDigit;
}

// Isi form edit dari tombol + validasi submit
document.addEventListener('DOMContentLoaded', function () {
    const buttons    = document.querySelectorAll('.btn-edit-user');
    const idField    = document.getElementById('edit_id_user');
    const namaField  = document.getElementById('edit_nama');
    const userField  = document.getElementById('edit_username');
    const emailField = document.getElementById('edit_email');
    const roleField  = document.getElementById('edit_role');
    const unitField  = document.getElementById('edit_id_unit');
    const passField  = document.getElementById('edit_password');
    const formEdit   = document.getElementById('formEditUser');

    // Isi data ke modal saat tombol Edit diklik
    buttons.forEach(btn => {
        btn.addEventListener('click', function () {
            idField.value    = this.dataset.id;
            namaField.value  = this.dataset.nama;
            userField.value  = this.dataset.username;
            emailField.value = this.dataset.email;
            roleField.value  = this.dataset.role;

            const idUnit = this.dataset.id_unit || 0;
            if (unitField && unitField.tagName === 'SELECT') {
                [...unitField.options].forEach(opt => {
                    opt.selected = (opt.value == idUnit);
                });
            } else if (unitField) {
                unitField.value = idUnit;
            }

            // kosongkan field password setiap kali buka modal
            if (passField) passField.value = '';
        });
    });

    // Validasi sebelum submit (mirip dengan Tambah Pengguna)
    if (formEdit) {
        formEdit.addEventListener('submit', function (e) {
            const nama     = namaField.value.trim();
            const username = userField.value.trim();
            const email    = emailField.value.trim();
            const role     = roleField.value.trim();
            const password = passField.value; // boleh kosong

            // 1. Nama wajib
            if (nama === '') {
                alert('Nama tidak boleh kosong.');
                namaField.focus();
                e.preventDefault();
                return;
            }

            // 2. Username valid
            if (!isValidUsername(username)) {
                alert('Username harus 4–30 karakter dan hanya boleh huruf, angka, dan underscore (_).');
                userField.focus();
                e.preventDefault();
                return;
            }

            // 3. Email (jika diisi, harus valid)
            if (email !== '' && !isValidEmail(email)) {
                alert('Format email tidak valid.');
                emailField.focus();
                e.preventDefault();
                return;
            }

            // 4. Role valid
            const allowedRoles = ['super_admin', 'bagian_umum', 'peminjam'];
            if (!allowedRoles.includes(role)) {
                alert('Role tidak valid.');
                roleField.focus();
                e.preventDefault();
                return;
            }

            // 5. Password (opsional, tapi kalau diisi harus kuat)
            if (password !== '' && !isStrongPassword(password)) {
                alert('Password baru minimal 6 karakter dan harus kombinasi huruf dan angka.');
                passField.focus();
                e.preventDefault();
                return;
            }

        });
    }
});
</script>

<?php include '../includes/admin/footer.php'; ?>
