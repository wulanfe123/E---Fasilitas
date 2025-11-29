<?php
include '../config/koneksi.php';
session_start();

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

function redirect_error($msg) {
    $_SESSION['error'] = $msg;
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_error("Akses tidak valid.");
}

// Delay brute force
usleep(500000);

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

/* ------------------------------
   VALIDASI USERNAME
------------------------------ */
if ($username === '' || $password === '') {
    redirect_error("Username dan password wajib diisi.");
}
elseif (strlen($username) < 4 || strlen($username) > 30) {
    redirect_error("Username harus 4–30 karakter.");
}
elseif (!preg_match('/^[A-Za-z]+$/', $username)) {
    redirect_error("Username hanya boleh huruf (A–Z), tanpa angka atau simbol.");
}
elseif (preg_match('/(admin|root|kontol|fuck|anjing|babi)/i', $username)) {
    redirect_error("Username mengandung kata tidak pantas.");
}
elseif ($_SESSION['login_attempts'] >= 5) {
    redirect_error("Terlalu banyak percobaan login. Coba lagi nanti.");
}

/* ------------------------------
   VALIDASI PASSWORD KOMBINASI
------------------------------ */
elseif (strlen($password) < 8) {
    redirect_error("Password minimal 8 karakter.");
}
elseif (!preg_match('/[A-Z]/', $password)) {
    redirect_error("Password harus mengandung huruf besar.");
}
elseif (!preg_match('/[a-z]/', $password)) {
    redirect_error("Password harus mengandung huruf kecil.");
}
elseif (!preg_match('/[0-9]/', $password)) {
    redirect_error("Password harus mengandung angka.");
}
elseif (!preg_match('/[\W]/', $password)) {
    redirect_error("Password harus mengandung simbol.");
}

/* ------------------------------
   CEK DATABASE (PREPARED)
------------------------------ */

$stmt = $conn->prepare("SELECT id_user, nama, username, password, role 
                        FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $_SESSION['login_attempts']++;
    redirect_error("Username atau password salah.");
}

$data   = $result->fetch_assoc();
$dbPass = $data['password'];
$userId = (int)$data['id_user'];

$isHashed = (str_starts_with($dbPass, '$2y$') || str_starts_with($dbPass, '$argon'));

$loginOK = false;

if ($isHashed) {
    if (password_verify($password, $dbPass)) {
        $loginOK = true;
    }
} else {
    // plaintext → migrasi
    if (hash_equals($dbPass, $password)) {
        $loginOK = true;

        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id_user = ?");
        $upd->bind_param("si", $newHash, $userId);
        $upd->execute();
        $upd->close();
    }
}

if (!$loginOK) {
    $_SESSION['login_attempts']++;
    redirect_error("Username atau password salah.");
}

// Login berhasil
$_SESSION['login_attempts'] = 0;

session_regenerate_id(true);

$_SESSION['id_user']   = $data['id_user'];
$_SESSION['role']      = $data['role'];
$_SESSION['username']  = $data['username'];
$_SESSION['nama_user'] = $data['nama'];

// Update last login
$u = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id_user = ?");
$u->bind_param("i", $userId);
$u->execute();
$u->close();

// Redirect sesuai role
switch ($data['role']) {
    case 'peminjam': 
        header("Location: ../peminjam/dashboard.php"); break;
    case 'bagian_umum': 
        header("Location: ../bagian_umum/dashboard.php"); break;
    default: 
        header("Location: ../super_admin/dashboard.php"); break;
}

exit;
?>
