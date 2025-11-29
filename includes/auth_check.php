<?php
// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Fungsi wajib login
 * Memastikan user sudah login sebelum mengakses halaman
 */
function require_login() {
    if (!isset($_SESSION['id_user'])) {

        // Mencegah halaman disimpan di cache browser
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");

        header("Location: ../auth/login.php");
        exit;
    }
}

/**
 * Fungsi validasi role
 * $allowed_roles = array('super_admin', 'bagian_umum', 'peminjam')
 */
function require_role($allowed_roles = []) {
    require_login(); // Pastikan sudah login

    $current_role = $_SESSION['role'] ?? '';

    // Jika role user tidak termasuk yang diizinkan → akses ditolak
    if (!in_array($current_role, $allowed_roles)) {

        // Mencegah akses tanpa hak
        header("HTTP/1.1 403 Forbidden");
        header("Location: ../auth/unauthorized.php");
        exit;
    }
}
?>
