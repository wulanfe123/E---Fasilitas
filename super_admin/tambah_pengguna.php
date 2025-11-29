<?php
session_start();
include '../config/koneksi.php';

// ==== CEK LOGIN & ROLE SUPER ADMIN ====
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user_login = (int) $_SESSION['id_user'];

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

// ==== OPSIONAL: DAFTAR UNIT (SAMA SEPERTI DI KELOLA_PENGGUNA) ====
$unitOptions = [];
$cekUnitTable = $conn->query("SHOW TABLES LIKE 'unit'");
if ($cekUnitTable && $cekUnitTable->num_rows > 0) {
    $qUnit = $conn->query("SELECT id_unit, nama_unit FROM unit ORDER BY nama_unit ASC");
    while ($u = $qUnit->fetch_assoc()) {
        $unitOptions[(int)$u['id_unit']] = $u['nama_unit'];
    }
}

// ==== PROSES SIMPAN PENGGUNA BARU ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role_u   = trim($_POST['role'] ?? '');
    $id_unit_post = isset($_POST['id_unit']) ? (int) $_POST['id_unit'] : 0;
    $password = $_POST['password'] ?? '';
    $password2= $_POST['password_confirmation'] ?? '';

    // ---- VALIDASI DASAR ----
    if ($nama === '' || $username === '' || $role_u === '' || $password === '' || $password2 === '') {
        $error = "Semua field yang wajib diisi harus diisi.";
    }
    // panjang username
    elseif (strlen($username) < 4 || strlen($username) > 30) {
        $error = "Username harus 4–30 karakter.";
    }
    // username HANYA huruf (tanpa angka/spasi/simbol)
    elseif (!preg_match('/^[A-Za-z]+$/', $username)) {
        $error = "Username hanya boleh berisi huruf (tanpa angka, spasi, atau simbol).";
    }
    // email harus format valid (kalau diisi)
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    }
    // role harus salah satu dari 3 role ini
    elseif (!in_array($role_u, ['super_admin', 'bagian_umum', 'peminjam'], true)) {
        $error = "Role tidak valid.";
    }
    // password minimal 8 karakter dan kombinasi (huruf besar, kecil, angka, simbol)
    elseif (strlen($password) < 8) {
        $error = "Password minimal 8 karakter.";
    }
    elseif (
        !preg_match('/[a-z]/', $password) ||   // ada huruf kecil
        !preg_match('/[A-Z]/', $password) ||   // ada huruf besar
        !preg_match('/\d/', $password)    ||   // ada angka
        !preg_match('/[\W_]/', $password)      // ada simbol (non huruf/angka)
    ) {
        $error = "Password harus mengandung huruf besar, huruf kecil, angka, dan simbol.";
    }
    // konfirmasi password
    elseif ($password !== $password2) {
        $error = "Konfirmasi password tidak sama.";
    }
    else {
        // ---- CEK USERNAME UNIK (prepared) ----
        if ($stmtCek = $conn->prepare("SELECT id_user FROM users WHERE username = ? LIMIT 1")) {
            $stmtCek->bind_param("s", $username);
            $stmtCek->execute();
            $resCek = $stmtCek->get_result();
            if ($resCek->num_rows > 0) {
                $error = "Username sudah digunakan.";
            }
            $stmtCek->close();
        } else {
            $error = "Gagal menyiapkan query pengecekan username.";
        }
    }

    if ($error === '') {
        $emailVal  = $email !== '' ? $email : null;
        $idUnitVal = ($id_unit_post > 0 && isset($unitOptions[$id_unit_post]))
                     ? $id_unit_post : null;
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $sqlIns = "
                INSERT INTO users (nama, username, email, role, id_unit, password, created)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";
            if ($stmtIns = $conn->prepare($sqlIns)) {
                $stmtIns->bind_param("ssssis",
              $nama,
             $username,
                    $emailVal,    
                    $role_u,
                    $idUnitVal,   
                    $password_hash
                );
                        $stmtIns->execute();

                if ($stmtIns->affected_rows > 0) {
                    // kirim pesan sukses via session dan kembali ke kelola_pengguna
                    $_SESSION['success'] = "Pengguna baru berhasil ditambahkan.";
                    header("Location: kelola_pengguna.php");
                    exit;
                } else {
                    $error = "Gagal menambahkan pengguna.";
                }
                $stmtIns->close();
            } else {
                $error = "Gagal menyiapkan query insert pengguna.";
            }
        } catch (mysqli_sql_exception $e) {
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}

$pageTitle = "Tambah Pengguna";
include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
include '../includes/admin/sidebar.php';
?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <h2 class="fw-bold text-primary mb-1">Tambah Pengguna</h2>
            <p class="text-muted mb-0">Form untuk menambahkan akun pengguna baru.</p>
        </div>
        <a href="kelola_pengguna.php" class="btn btn-outline-secondary">
            &laquo; Kembali
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-primary text-white">
            <i class="fas fa-user-plus me-2"></i> Form Tambah Pengguna
        </div>
        <div class="card-body">
            <form method="POST" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" required
                           value="<?= htmlspecialchars($_POST['nama'] ?? ''); ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Username</label>
                    <input type="text" name="username" class="form-control" required
                           value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>">
                    <div class="form-text">Hanya huruf, angka, dan _ , 4–30 karakter.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <div class="form-text">Boleh dikosongkan.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Role</label>
                    <select name="role" class="form-select" required>
                        <option value="">-- Pilih Role --</option>
                        <option value="super_admin" <?= (($_POST['role'] ?? '') === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="bagian_umum" <?= (($_POST['role'] ?? '') === 'bagian_umum') ? 'selected' : ''; ?>>Bagian Umum</option>
                        <option value="peminjam" <?= (($_POST['role'] ?? '') === 'peminjam') ? 'selected' : ''; ?>>Peminjam</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Unit</label>
                    <?php if (!empty($unitOptions)): ?>
                        <select name="id_unit" class="form-select">
                            <option value="0">-- Tanpa Unit --</option>
                            <?php foreach ($unitOptions as $id_unit => $nama_unit): ?>
                                <option value="<?= $id_unit; ?>"
                                  <?= ((int)($_POST['id_unit'] ?? 0) === $id_unit) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($nama_unit); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="number" name="id_unit" class="form-control"
                               value="<?= htmlspecialchars($_POST['id_unit'] ?? ''); ?>"
                               placeholder="ID Unit (opsional)">
                    <?php endif; ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" required>
                        <div class="form-text">Minimal 8 karakter kombinasi.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Konfirmasi Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin/footer.php'; ?>
