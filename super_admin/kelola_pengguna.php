<?php
session_start();
include '../config/koneksi.php';

// ================== CEK LOGIN & ROLE (PREPARED) ==================
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user_login = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user_login === false || $id_user_login <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Ambil role user login (prepared)
$role = '';
$stmtRole = $conn->prepare("SELECT role FROM users WHERE id_user = ? LIMIT 1");
$stmtRole->bind_param("i", $id_user_login);
$stmtRole->execute();
$resRole = $stmtRole->get_result();
if ($resRole && $rowRole = $resRole->fetch_assoc()) {
    $role = $rowRole['role'];
}
$stmtRole->close();

if ($role !== 'super_admin') {
    header("Location: ../auth/unauthorized.php");
    exit;
}

$success = '';
$error   = '';

/* ================== OPSIONAL: DAFTAR UNIT (PREPARED) ================== */
$unitOptions = [];

// cek tabel unit ada atau tidak
$stmtCekUnit = $conn->prepare("SHOW TABLES LIKE 'unit'");
$stmtCekUnit->execute();
$resCekUnit = $stmtCekUnit->get_result();
if ($resCekUnit && $resCekUnit->num_rows > 0) {
    $stmtUnit = $conn->prepare("SELECT id_unit, nama_unit FROM unit ORDER BY nama_unit ASC");
    $stmtUnit->execute();
    $resUnit = $stmtUnit->get_result();
    while ($u = $resUnit->fetch_assoc()) {
        $unitOptions[(int)$u['id_unit']] = $u['nama_unit'];
    }
    $stmtUnit->close();
}
$stmtCekUnit->close();

/* ================== HAPUS PENGGUNA (DELETE, PREPARED) ================== */
if (isset($_GET['hapus'])) {
    $id_del = filter_var($_GET['hapus'], FILTER_VALIDATE_INT);

    if ($id_del === $id_user_login) {
        $error = "Anda tidak dapat menghapus akun yang sedang digunakan.";
    } elseif ($id_del && $id_del > 0) {
        $stmtDel = $conn->prepare("DELETE FROM users WHERE id_user = ?");
        $stmtDel->bind_param("i", $id_del);
        $stmtDel->execute();

        if ($stmtDel->affected_rows > 0) {
            $success = "Pengguna berhasil dihapus.";
        } else {
            $error = "Gagal menghapus pengguna. Kemungkinan data masih terhubung dengan tabel lain.";
        }
        $stmtDel->close();
    }
}

/* ================== UPDATE PENGGUNA (EDIT, PREPARED) ================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_user') {
    $id_user      = filter_var($_POST['id_user'] ?? 0, FILTER_VALIDATE_INT);
    $nama         = trim($_POST['nama'] ?? '');
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $role_u       = trim($_POST['role'] ?? '');
    $id_unit_post = isset($_POST['id_unit']) ? (int) $_POST['id_unit'] : 0;
    $password     = $_POST['password'] ?? '';

    // -------- VALIDASI DASAR (SERVER-SIDE) --------
    if ($id_user === false || $id_user <= 0 || $nama === '' || $username === '' || $role_u === '') {
        $error = "Data tidak lengkap untuk update pengguna.";
    } elseif (mb_strlen($username) < 4 || mb_strlen($username) > 30) {
        $error = "Username harus 4–30 karakter.";
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        $error = "Username hanya boleh huruf, angka, dan underscore (_).";
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (!in_array($role_u, ['super_admin', 'bagian_umum', 'peminjam'], true)) {
        $error = "Role tidak valid.";
    } elseif ($password !== '' && !(
        strlen($password) >= 6 &&
        preg_match('/[A-Za-z]/', $password) &&
        preg_match('/[0-9]/', $password)
    )) {
        $error = "Password baru minimal 6 karakter dan harus kombinasi huruf dan angka.";
    } else {
        // -------- CEK USERNAME UNIQUE (PREPARED) --------
        $stmtCek = $conn->prepare("SELECT id_user FROM users WHERE username = ? AND id_user != ? LIMIT 1");
        $stmtCek->bind_param("si", $username, $id_user);
        $stmtCek->execute();
        $resCek = $stmtCek->get_result();
        if ($resCek && $resCek->num_rows > 0) {
            $error = "Username sudah digunakan pengguna lain.";
        }
        $stmtCek->close();
    }

    if ($error === '') {
        // Siapkan nilai email & unit (boleh NULL)
        $emailParam  = $email !== '' ? $email : null;
        $idUnitParam = ($id_unit_post > 0 && isset($unitOptions[$id_unit_post]))
            ? $id_unit_post
            : null;

        if ($password !== '') {
            // Update dengan password baru
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sqlUpdate = "
                UPDATE users 
                SET nama = ?, 
                    username = ?, 
                    email = ?, 
                    role = ?, 
                    id_unit = ?, 
                    password = ?
                WHERE id_user = ?
            ";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            // s = string, i = int; email & id_unit boleh null -> pakai types "s" dan "i" tapi nilai null
            $stmtUpdate->bind_param(
                "ssssisi",
                $nama,
                $username,
                $emailParam,
                $role_u,
                $idUnitParam,
                $password_hash,
                $id_user
            );
        } else {
            // Update tanpa password
            $sqlUpdate = "
                UPDATE users 
                SET nama = ?, 
                    username = ?, 
                    email = ?, 
                    role = ?, 
                    id_unit = ?
                WHERE id_user = ?
            ";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param(
                "ssssid",
                $nama,
                $username,
                $emailParam,
                $role_u,
                $idUnitParam,
                $id_user
            );
        }

        if ($stmtUpdate && $stmtUpdate->execute()) {
            $success = "Data pengguna berhasil diperbarui.";
        } else {
            $error = "Gagal memperbarui data pengguna.";
        }
        if ($stmtUpdate) {
            $stmtUpdate->close();
        }
    }
}

/* ================== NOTIFIKASI (BELL NAVBAR, PREPARED) ================== */
$notifPeminjaman       = [];
$notifRusak            = [];
$jumlahNotifPeminjaman = 0;
$jumlahNotifRusak      = 0;
$jumlahNotif           = 0;

// Peminjaman baru (usulan)
$sqlNotifP = "
    SELECT p.id_pinjam, u.nama, p.tanggal_mulai
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.status = 'usulan'
    ORDER BY p.id_pinjam DESC
    LIMIT 5
";
$stmtNotifP = $conn->prepare($sqlNotifP);
if ($stmtNotifP) {
    $stmtNotifP->execute();
    $resNotifP = $stmtNotifP->get_result();
    while ($row = $resNotifP->fetch_assoc()) {
        $notifPeminjaman[] = $row;
    }
    $stmtNotifP->close();
}
$jumlahNotifPeminjaman = count($notifPeminjaman);

// Pengembalian rusak
$sqlNotifR = "
    SELECT k.id_kembali, k.id_pinjam, u.nama, k.tgl_kembali
    FROM pengembalian k
    JOIN peminjaman p ON k.id_pinjam = p.id_pinjam
    JOIN users u ON p.id_user = u.id_user
    WHERE k.kondisi = 'rusak'
    ORDER BY k.id_kembali DESC
    LIMIT 5
";
$stmtNotifR = $conn->prepare($sqlNotifR);
if ($stmtNotifR) {
    $stmtNotifR->execute();
    $resNotifR = $stmtNotifR->get_result();
    while ($row = $resNotifR->fetch_assoc()) {
        $notifRusak[] = $row;
    }
    $stmtNotifR->close();
}
$jumlahNotifRusak = count($notifRusak);
$jumlahNotif      = $jumlahNotifPeminjaman + $jumlahNotifRusak;

// ================== AMBIL DATA SEMUA PENGGUNA (PREPARED) ==================
$result = null;
$stmtUsers = $conn->prepare("SELECT * FROM users ORDER BY id_user DESC");
if ($stmtUsers) {
    $stmtUsers->execute();
    $result = $stmtUsers->get_result();
}

// ================== TITLE & TEMPLATE ==================
$pageTitle   = 'Kelola Pengguna';
$currentPage = 'kelola_pengguna';
$statUsulan  = $jumlahNotifPeminjaman; // untuk badge di sidebar

include '../includes/admin/header.php';
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
        border-radius: 999px;
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

    /* Modal Edit Pengguna */
    #modalEditUser .modal-dialog {
        max-width: 520px;
    }

    #modalEditUser .modal-content {
        border-radius: 14px;
        border: none;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.35);
    }

    #modalEditUser .modal-header {
        background: linear-gradient(90deg, #0d47a1, #1565c0);
        color: #f9fafb;
        border-bottom: none;
    }
    
    #modalEditUser .btn-close {
        filter: invert(1);
    }

    #modalEditUser .modal-title::before {
        content: "\f4ff";
        font-family: "Font Awesome 5 Free";
        font-weight: 900;
        font-size: 0.95rem;
        margin-right: 8px;
    }

    #modalEditUser .modal-body {
        background: #f9fafb;
        padding: 1.1rem 1.25rem 1rem;
    }

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

    #modalEditUser .modal-footer {
        background: #f3f4f6;
        border-top: 1px solid #e5e7eb;
        padding: 0.65rem 1.25rem 0.75rem;
        justify-content: space-between;
    }

    #modalEditUser .btn-secondary {
        font-size: 0.85rem;
        border-radius: 999px;
        padding: 0.35rem 0.9rem;
    }

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

    #modalEditUser .mb-3 {
        margin-bottom: 0.7rem !important;
    }

    .footer-admin {
        padding: 0.75rem 1.5rem;
        border-top: 1px solid #e5e7eb;
        background-color: #f9fafb;
        font-size: 0.85rem;
        color: #4b5563;
    }
</style>

<!-- Main Content Area -->
<div id="layoutSidenav_content">
    
    <?php include '../includes/admin/navbar.php'; ?>

    <main>
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-primary mb-1 user-header-title">
                        <i class="fas fa-users-cog me-2"></i>
                        Kelola Pengguna
                    </h2>
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
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
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
                    <div class="table-responsive">
                        <table id="datatablesSimple" class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr class="text-center">
                                    <th style="width: 50px;">No</th>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th style="width: 120px;">Role</th>
                                    <th style="width: 120px;">Unit</th>
                                    <th style="width: 120px;">Dibuat</th>
                                    <th style="width: 130px;">Aksi</th>
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
                                    <td><strong><?= htmlspecialchars($row['nama']); ?></strong></td>
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
                                            title="Edit Pengguna"
                                        >
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <!-- Tombol Hapus (tidak boleh hapus diri sendiri) -->
                                        <?php if ($row['id_user'] != $id_user_login): ?>
                                        <a href="kelola_pengguna.php?hapus=<?= $row['id_user']; ?>"
                                           class="btn btn-sm btn-danger btn-delete-user"
                                           onclick="return confirm('Yakin ingin menghapus pengguna <?= htmlspecialchars($row['nama']); ?>?')"
                                           title="Hapus Pengguna">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="Tidak bisa hapus diri sendiri">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-users fa-3x mb-3 d-block opacity-25"></i>
                                        Tidak ada data pengguna.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer-admin">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>E-Fasilitas</strong> &copy; <?= date('Y'); ?> - Sistem Peminjaman Fasilitas Kampus
            </div>
            <div>
                Version 1.0
            </div>
        </div>
    </footer>

</div>

<!-- Modal Edit Pengguna -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-labelledby="modalEditUserLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="formEditUser">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEditUserLabel">Edit Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id_user" id="edit_id_user">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="edit_nama" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
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
                    <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Batal
                </button>
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-save me-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/admin/footer.php'; ?>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// DataTables Init
$(document).ready(function() {
    $('#datatablesSimple').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        pageLength: 10,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });
});

// Helper: cek email valid
function isValidEmail(email) {
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

    // Validasi sebelum submit (client-side tambahan)
    if (formEdit) {
        formEdit.addEventListener('submit', function (e) {
            const nama     = namaField.value.trim();
            const username = userField.value.trim();
            const email    = emailField.value.trim();
            const role     = roleField.value.trim();
            const password = passField.value;

            if (nama === '') {
                alert('Nama tidak boleh kosong.');
                namaField.focus();
                e.preventDefault();
                return;
            }

            if (!isValidUsername(username)) {
                alert('Username harus 4–30 karakter dan hanya boleh huruf, angka, dan underscore (_).');
                userField.focus();
                e.preventDefault();
                return;
            }

            if (email !== '' && !isValidEmail(email)) {
                alert('Format email tidak valid.');
                emailField.focus();
                e.preventDefault();
                return;
            }

            const allowedRoles = ['super_admin', 'bagian_umum', 'peminjam'];
            if (!allowedRoles.includes(role)) {
                alert('Role tidak valid.');
                roleField.focus();
                e.preventDefault();
                return;
            }

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
