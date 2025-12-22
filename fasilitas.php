<?php
session_start();
include 'config/koneksi.php';

/* =========================================================
   SECURITY HEADERS
   ========================================================= */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

/* =========================================================
   STATUS LOGIN
   ========================================================= */
$isLoggedIn = isset($_SESSION['id_user'], $_SESSION['role']) && $_SESSION['role'] === 'peminjam';
$id_user    = $isLoggedIn ? (int) $_SESSION['id_user'] : 0;
$nama_user  = $isLoggedIn ? ($_SESSION['nama'] ?? 'Peminjam') : 'Pengunjung';

/* =========================================================
   VALIDASI INPUT FILTER (GET) - WHITELIST APPROACH
   ========================================================= */
$allowedKategori = ['ruangan', 'kendaraan', 'lapangan', 'pendukung'];
$allowedStatus   = ['tersedia', 'tidak_tersedia'];

// Validasi kategori dengan whitelist
$kategori = isset($_GET['kategori']) ? strtolower(trim($_GET['kategori'])) : '';
if (!in_array($kategori, $allowedKategori, true)) {
    $kategori = '';
}

// Validasi status dengan whitelist
$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

// Validasi dan sanitasi search input
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
// Batasi panjang & sanitasi
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
// Sanitasi tambahan
$search = filter_var($search, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

/* =========================================================
   QUERY FASILITAS DENGAN PREPARED STATEMENT (AMAN)
   ========================================================= */
$sql = "
    SELECT
        f.*,
        CASE
            WHEN EXISTS (
                SELECT 1
                FROM daftar_peminjaman_fasilitas df
                JOIN peminjaman p ON df.id_pinjam = p.id_pinjam
                LEFT JOIN pengembalian pg ON p.id_pinjam = pg.id_pinjam
                WHERE df.id_fasilitas = f.id_fasilitas
                  AND p.status = 'diterima'
                  AND CURDATE() BETWEEN p.tanggal_mulai AND p.tanggal_selesai
                  AND pg.id_pinjam IS NULL
            )
            THEN 'tidak_tersedia'
            ELSE 'tersedia'
        END AS ketersediaan_aktual
    FROM fasilitas f
    WHERE 1=1
";

$types  = '';
$params = [];

// Filter kategori dengan prepared statement
if ($kategori !== '') {
    $sql     .= " AND LOWER(f.kategori) = ?";
    $types   .= 's';
    $params[] = $kategori;
}

// Filter search dengan prepared statement (LIKE)
if ($search !== '') {
    $sql     .= " AND (f.nama_fasilitas LIKE ? OR f.keterangan LIKE ?)";
    $types   .= 'ss';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

// Filter status menggunakan HAVING
$sql .= " HAVING 1=1";
if ($status !== '') {
    $sql     .= " AND ketersediaan_aktual = ?";
    $types   .= 's';
    $params[] = $status;
}

$sql .= " ORDER BY f.id_fasilitas ASC";

// Eksekusi prepared statement
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Terjadi kesalahan sistem. Silakan hubungi administrator.");
}

// Bind parameters jika ada
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sistem Digital Peminjaman Fasilitas Kampus Politeknik Negeri Bengkalis">
    <title>Daftar Fasilitas | Pemfas Polbeng</title>

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- CUSTOM STYLING -->
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary-color: #0b2c61;
    --secondary-color: #0a2350;
    --accent-color: #ffb703;
    --light-bg: #f4f6fb;
    --dark-text: #1e293b;
    --muted-text: #64748b;
    --border-color: #e5e7eb;
    --success-color: #16a34a;
    --warning-color: #f59e0b;
    --danger-color: #dc2626;
}

body {
    font-family: 'Poppins', sans-serif;
    background: var(--light-bg);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    color: var(--dark-text);
}

html {
    scroll-behavior: smooth;
}

/* ============================================
   NAVBAR
   ============================================ */
.navbar {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    backdrop-filter: blur(10px);
    padding: 1rem 0;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(11, 44, 97, 0.1);
    z-index: 1000;
}

.navbar.scrolled {
    background: rgba(11, 44, 97, 0.95);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    padding: 0.7rem 0;
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white !important;
    font-weight: 700;
    font-size: 1.4rem;
    transition: transform 0.3s ease;
}

.navbar-brand:hover {
    transform: scale(1.05);
}

.navbar-brand img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.navbar-brand:hover img {
    transform: rotate(360deg);
}

.brand-text {
    color: white;
    font-size: 1.3rem;
}

.navbar-toggler {
    border: 2px solid rgba(255, 255, 255, 0.3);
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.navbar-toggler:hover {
    border-color: var(--accent-color);
    background: rgba(255, 183, 3, 0.1);
}

.navbar-toggler:focus {
    box-shadow: 0 0 0 0.25rem rgba(255, 183, 3, 0.25);
}

.navbar-toggler-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 1%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    width: 24px;
    height: 24px;
}

.navbar-nav {
    gap: 5px;
    margin: 0 auto;
}

.nav-link {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500;
    font-size: 0.95rem;
    padding: 10px 20px !important;
    border-radius: 25px;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    align-items: center;
}

.nav-link i {
    font-size: 1rem;
}

.nav-link:hover,
.nav-link.active {
    background: rgba(255, 183, 3, 0.2);
    color: var(--accent-color) !important;
    transform: translateY(-2px);
}

.btn-login {
    background: rgba(255, 183, 3, 0.15);
    border: 2px solid rgba(255, 183, 3, 0.3);
    color: var(--accent-color) !important;
    font-weight: 700;
    padding: 8px 24px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.btn-login:hover {
    background: var(--accent-color);
    color: var(--primary-color) !important;
    border-color: var(--accent-color);
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(255, 183, 3, 0.3);
}

.btn-outline-light {
    border: 2px solid rgba(255, 255, 255, 0.5);
    color: white !important;
    font-weight: 600;
    padding: 8px 20px;
    border-radius: 25px;
    transition: all 0.3s ease;
}

.btn-outline-light:hover {
    background: white;
    color: var(--primary-color) !important;
    border-color: white;
    transform: translateY(-3px);
}

/* ============================================
   HERO SECTION
   ============================================ */
.hero-section {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    padding: 140px 0 80px 0;
    color: white;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.4;
}

.hero-section h2 {
    font-size: 2.8rem;
    font-weight: 800;
    text-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    position: relative;
    z-index: 1;
}

.hero-section h2 i {
    color: var(--accent-color);
}

.hero-section .lead {
    font-size: 1.2rem;
    font-weight: 400;
    opacity: 0.95;
    position: relative;
    z-index: 1;
}

/* ============================================
   FILTER BAR
   ============================================ */
.filter-bar {
    background: white;
    padding: 30px;
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(11, 44, 97, 0.12);
    position: relative;
    z-index: 10;
    margin-top: -40px;
    border: 1px solid rgba(11, 44, 97, 0.05);
}

.filter-bar .form-label {
    font-weight: 700;
    color: var(--primary-color);
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.filter-bar .form-label i {
    color: var(--accent-color);
}

.filter-bar .form-select,
.filter-bar .form-control {
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 12px 18px;
    font-weight: 500;
    transition: all 0.3s ease;
    color: var(--dark-text);
}

.filter-bar .form-select:focus,
.filter-bar .form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 0.25rem rgba(255, 183, 3, 0.15);
    outline: none;
}

.filter-bar .btn-main {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 12px;
    font-weight: 700;
    transition: all 0.3s ease;
}

.filter-bar .btn-main:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(11, 44, 97, 0.4);
}

.filter-bar .btn-outline-secondary {
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 12px 20px;
    transition: all 0.3s ease;
    color: var(--muted-text);
}

.filter-bar .btn-outline-secondary:hover {
    background: var(--danger-color);
    border-color: var(--danger-color);
    color: white;
    transform: translateY(-3px);
}

/* ============================================
   FASILITAS CARDS
   ============================================ */
.fasilitas-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 8px 30px rgba(11, 44, 97, 0.1);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid var(--border-color);
    height: 100%;
}

.fasilitas-card:hover {
    transform: translateY(-15px) scale(1.02);
    box-shadow: 0 20px 60px rgba(11, 44, 97, 0.2);
    border-color: var(--accent-color);
}

.fasilitas-card-img {
    position: relative;
    height: 240px;
    overflow: hidden;
    background: var(--light-bg);
}

.fasilitas-card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: all 0.5s ease;
}

.fasilitas-card:hover .fasilitas-card-img img {
    transform: scale(1.15) rotate(2deg);
}

.fasilitas-card-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: linear-gradient(135deg, rgba(255, 183, 3, 0.95) 0%, rgba(255, 215, 0, 0.95) 100%);
    color: var(--primary-color);
    padding: 8px 18px;
    border-radius: 30px;
    font-weight: 700;
    font-size: 0.85rem;
    box-shadow: 0 4px 15px rgba(255, 183, 3, 0.4);
    backdrop-filter: blur(10px);
}

.fasilitas-card h5 {
    font-weight: 800;
    color: var(--primary-color);
    font-size: 1.3rem;
    margin-bottom: 10px;
}

.badge-jenis {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.badge-status {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    color: white;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.badge-status.bg-success {
    background: linear-gradient(135deg, var(--success-color) 0%, #22c55e 100%);
}

.badge-status.bg-danger {
    background: linear-gradient(135deg, var(--danger-color) 0%, #ef4444 100%);
}

.btn-main {
    background: linear-gradient(135deg, var(--accent-color) 0%, #ffd700 100%);
    color: var(--primary-color);
    border: none;
    padding: 14px 28px;
    border-radius: 12px;
    font-weight: 700;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(255, 183, 3, 0.3);
}

.btn-main:hover:not(.disabled) {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(255, 183, 3, 0.5);
    color: var(--primary-color);
}

.btn-main.disabled {
    background: var(--muted-text);
    cursor: not-allowed;
    opacity: 0.6;
    color: white;
}

/* ============================================
   EMPTY STATE
   ============================================ */
.empty-state {
    padding: 80px 20px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 8px 30px rgba(11, 44, 97, 0.1);
}

.empty-state i {
    color: var(--accent-color);
    opacity: 0.5;
}

.empty-state h4 {
    font-weight: 800;
    margin-bottom: 15px;
    color: var(--primary-color);
}

.empty-state p {
    color: var(--muted-text);
}

/* ============================================
   FOOTER
   ============================================ */
.footer-section {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    color: white;
    padding: 60px 0 20px;
    margin-top: 0;
}

.footer-section .container {
    max-width: 1200px;
}

.footer-section .footer-about {
    text-align: left !important;
}

.footer-section .footer-about .footer-title {
    text-align: left !important;
    color: var(--accent-color);
    font-weight: 700;
    margin-bottom: 20px;
    font-size: 1.3rem;
}

.footer-section .footer-about p {
    text-align: left !important;
    max-width: 100%;
    margin: 0 0 20px 0;
    color: rgba(255, 255, 255, 0.8);
    line-height: 1.8;
}

.footer-section .footer-about .social-links {
    justify-content: flex-start !important;
    display: flex;
    gap: 10px;
}

.footer-section .footer-links {
    text-align: center !important;
}

.footer-section .footer-links .footer-title {
    text-align: center !important;
    color: var(--accent-color);
    font-weight: 700;
    margin-bottom: 20px;
    font-size: 1.3rem;
}

.footer-section .footer-links ul {
    text-align: center !important;
    padding-left: 0;
    list-style: none;
    margin: 0;
}

.footer-section .footer-links ul li {
    text-align: center !important;
    margin-bottom: 12px;
}

.footer-section .footer-links a {
    justify-content: center !important;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
}

.footer-section .footer-links a:hover {
    color: var(--accent-color);
    transform: translateX(5px);
}

.footer-section .footer-links a i {
    margin-right: 8px;
}

.footer-section .footer-contact {
    text-align: right !important;
}

.footer-section .footer-contact .footer-title {
    text-align: right !important;
    color: var(--accent-color);
    font-weight: 700;
    margin-bottom: 20px;
    font-size: 1.3rem;
}

.footer-section .footer-contact ul {
    text-align: right !important;
    padding-right: 0;
    list-style: none;
    margin: 0;
}

.footer-section .footer-contact ul li {
    text-align: right !important;
    justify-content: flex-end !important;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
}

.footer-section .footer-contact i {
    color: var(--accent-color);
    margin-right: 8px;
    flex-shrink: 0;
}

.social-links a {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255, 183, 3, 0.15);
    border: 2px solid rgba(255, 183, 3, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--accent-color);
    transition: all 0.3s;
    text-decoration: none;
}

.social-links a:hover {
    background: var(--accent-color);
    color: var(--primary-color);
    border-color: var(--accent-color);
    transform: translateY(-5px);
}

.footer-divider {
    border: none;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin: 40px 0 20px;
}

.footer-bottom {
    text-align: center !important;
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    padding-top: 10px;
}

.footer-bottom p {
    margin: 0;
    text-align: center !important;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .hero-section {
        padding: 120px 0 60px;
    }
    
    .hero-section h2 {
        font-size: 2rem;
    }
    
    .hero-section .lead {
        font-size: 1rem;
    }

    .fasilitas-card-img {
        height: 200px;
    }

    .filter-bar {
        padding: 20px;
        margin-top: -30px;
    }

    /* Footer Mobile Center */
    .footer-section .footer-about,
    .footer-section .footer-links,
    .footer-section .footer-contact {
        text-align: center !important;
        margin-bottom: 30px;
    }

    .footer-section .footer-about .footer-title,
    .footer-section .footer-links .footer-title,
    .footer-section .footer-contact .footer-title {
        text-align: center !important;
    }

    .footer-section .footer-about p {
        text-align: center !important;
    }

    .footer-section .footer-about .social-links {
        justify-content: center !important;
    }

    .footer-section .footer-links ul,
    .footer-section .footer-contact ul {
        text-align: center !important;
    }

    .footer-section .footer-links ul li,
    .footer-section .footer-contact ul li {
        text-align: center !important;
        justify-content: center !important;
    }
}
    </style>
</head>
<body>

<!-- ========== NAVBAR ========== -->
<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="assets/img/Logo.png" alt="Logo Polbeng" 
                 onerror="this.src='assets/img/no-image.jpg'">
            <span class="brand-text">Pemfas</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-house-door-fill me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="fasilitas.php">
                        <i class="bi bi-building me-1"></i> Fasilitas
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <?php if ($isLoggedIn): ?>
                    <span class="text-white small me-2">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($nama_user, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <a href="peminjam/dashboard.php" class="btn btn-login btn-sm">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                    <a href="auth/logout.php" class="btn-outline-light btn btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="auth/login.php" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- ========== HERO SECTION ========== -->
<section class="hero-section">
    <div class="container text-center">
        <h2 class="mb-3" data-aos="fade-up">
            <i class="bi bi-building me-2"></i>Daftar Fasilitas Kampus
        </h2>
        <p class="lead mb-0" data-aos="fade-up" data-aos-delay="100">
            Temukan fasilitas yang Anda butuhkan. Login untuk mengajukan peminjaman.
        </p>
    </div>
</section>

<!-- ========== FILTER & CONTENT ========== -->
<div class="container my-4 flex-grow-1">

    <!-- FILTER BAR -->
    <div class="filter-bar mb-4" data-aos="fade-up">
        <form class="row g-3" method="get" action="fasilitas.php">
            <div class="col-lg-3 col-md-6">
                <label for="filterKategori" class="form-label">
                    <i class="bi bi-tag-fill me-1"></i>Kategori
                </label>
                <select id="filterKategori" name="kategori" class="form-select">
                    <option value="">Semua Kategori</option>
                    <option value="ruangan" <?= $kategori === 'ruangan' ? 'selected' : ''; ?>>Ruangan</option>
                    <option value="kendaraan" <?= $kategori === 'kendaraan' ? 'selected' : ''; ?>>Kendaraan</option>
                    <option value="lapangan" <?= $kategori === 'lapangan' ? 'selected' : ''; ?>>Lapangan</option>
                    <option value="pendukung" <?= $kategori === 'pendukung' ? 'selected' : ''; ?>>Pendukung</option>
                </select>
            </div>

            <div class="col-lg-3 col-md-6">
                <label for="filterStatus" class="form-label">
                    <i class="bi bi-check-circle-fill me-1"></i>Status
                </label>
                <select id="filterStatus" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="tersedia" <?= $status === 'tersedia' ? 'selected' : ''; ?>>Tersedia</option>
                    <option value="tidak_tersedia" <?= $status === 'tidak_tersedia' ? 'selected' : ''; ?>>Tidak Tersedia</option>
                </select>
            </div>

            <div class="col-lg-6 col-md-12">
                <label for="searchNama" class="form-label">
                    <i class="bi bi-search me-1"></i>Cari Fasilitas
                </label>
                <div class="input-group">
                    <input type="text" id="searchNama" name="q" class="form-control" 
                           placeholder="Nama fasilitas atau keterangan..."
                           value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                           maxlength="100">
                    <button class="btn btn-main" type="submit">
                        <i class="bi bi-search me-1"></i> Cari
                    </button>
                    <?php if ($kategori != '' || $status != '' || $search != ''): ?>
                        <a href="fasilitas.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- GRID FASILITAS -->
    <div class="row g-4">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php 
            $delay = 0;
            while ($data = $result->fetch_assoc()):
                // Sanitasi output untuk keamanan XSS
                $namaFasilitas = htmlspecialchars($data['nama_fasilitas'], ENT_QUOTES, 'UTF-8');
                $keterangan    = htmlspecialchars($data['keterangan'] ?? '', ENT_QUOTES, 'UTF-8');
                $lokasi        = htmlspecialchars($data['lokasi'] ?? '-', ENT_QUOTES, 'UTF-8');
                $jenis         = htmlspecialchars($data['jenis_fasilitas'] ?? '-', ENT_QUOTES, 'UTF-8');
                
                $keteranganPendek = mb_strlen($keterangan) > 100
                    ? mb_substr($keterangan, 0, 100) . '...'
                    : $keterangan;

                $statusAktual = strtolower($data['ketersediaan_aktual'] ?? 'tersedia');
                $statusLabel  = $statusAktual === 'tersedia' ? 'Tersedia' : 'Tidak Tersedia';
                $statusClass  = $statusAktual === 'tersedia' ? 'bg-success' : 'bg-danger';

                $kategoriRow = htmlspecialchars(ucfirst(strtolower($data['kategori'] ?? '')), ENT_QUOTES, 'UTF-8');

                // Gambar fasilitas dengan fallback
                $gambarPath = "uploads/fasilitas/" . ($data['gambar'] ?? '');
                if (empty($data['gambar']) || !file_exists($gambarPath)) {
                    $gambarPath = "assets/img/no-image.jpg";
                }

                $idFasilitas = (int) $data['id_fasilitas'];

                // URL Ajukan Peminjaman
                if ($isLoggedIn) {
                    $ajukanUrl = "peminjam/form_peminjaman.php?id=" . $idFasilitas;
                } else {
                    $redirectTarget = "peminjam/form_peminjaman.php?id=" . $idFasilitas;
                    $ajukanUrl = "auth/login.php?redirect=" . urlencode($redirectTarget);
                }

                $delay += 50;
            ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= $delay; ?>">
                    <div class="fasilitas-card h-100">
                        <div class="fasilitas-card-img">
                            <img src="<?= htmlspecialchars($gambarPath, ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?= $namaFasilitas; ?>"
                                 loading="lazy"
                                 onerror="this.src='assets/img/no-image.jpg'">
                            <div class="fasilitas-card-badge">
                                <i class="bi bi-tag me-1"></i>
                                <?= $kategoriRow; ?>
                            </div>
                        </div>

                        <div class="card-body p-4">
                            <div class="text-center mb-3">
                                <h5 class="mb-2"><?= $namaFasilitas; ?></h5>
                                <span class="badge-jenis">
                                    <i class="bi bi-building me-1"></i>
                                    <?= $jenis; ?>
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt-fill me-1 text-primary"></i>
                                    <?= $lokasi; ?>
                                </small>
                                <span class="badge-status <?= $statusClass; ?>">
                                    <i class="bi bi-<?= $statusAktual === 'tersedia' ? 'check' : 'x'; ?>-circle me-1"></i>
                                    <?= $statusLabel; ?>
                                </span>
                            </div>

                            <p class="text-muted small mb-4" style="min-height: 60px;">
                                <?= $keteranganPendek ? nl2br($keteranganPendek) : 'Tidak ada keterangan.'; ?>
                            </p>

                            <a href="<?= $statusAktual === 'tersedia' ? htmlspecialchars($ajukanUrl, ENT_QUOTES, 'UTF-8') : 'javascript:void(0)'; ?>"
                               class="btn btn-main w-100 <?= $statusAktual === 'tersedia' ? '' : 'disabled'; ?>"
                               <?php if ($statusAktual !== 'tersedia'): ?>
                                   onclick="return false;"
                                   aria-disabled="true"
                               <?php endif; ?>>
                                <i class="bi bi-<?= $statusAktual === 'tersedia' ? 'calendar-check' : 'x-circle'; ?> me-2"></i>
                                <?php
                                    if ($statusAktual !== 'tersedia') {
                                        echo 'Tidak Tersedia';
                                    } else {
                                        echo $isLoggedIn ? 'Ajukan Peminjaman' : 'Login untuk Meminjam';
                                    }
                                ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state text-center">
                    <i class="bi bi-inbox display-1 d-block mb-4"></i>
                    <h4 class="text-muted mb-2">Tidak Ada Fasilitas</h4>
                    <p class="text-muted">Tidak ada fasilitas yang sesuai dengan filter Anda.</p>
                    <?php if ($kategori != '' || $status != '' || $search != ''): ?>
                        <a href="fasilitas.php" class="btn btn-main mt-3">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- FOOTER -->
<footer class="footer-section">
    <div class="container">
        <div class="row g-4">
            <!-- KOLOM KIRI: Pemfas -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-about">
                    <h5 class="footer-title">Pemfas</h5>
                    <p>Sistem Digital Peminjaman Fasilitas Kampus Politeknik Negeri Bengkalis</p>
                    <div class="social-links mt-3">
                        <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                        <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" aria-label="Twitter"><i class="bi bi-twitter"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>
            </div>

            <!-- KOLOM TENGAH: Link Cepat -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-links">
                    <h5 class="footer-title">Link Cepat</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php"><i class="bi bi-chevron-right me-2"></i>Home</a></li>
                        <li><a href="fasilitas.php"><i class="bi bi-chevron-right me-2"></i>Fasilitas</a></li>
                        <li><a href="auth/login.php"><i class="bi bi-chevron-right me-2"></i>Login</a></li>
                    </ul>
                </div>
            </div>

            <!-- KOLOM KANAN: Kontak -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-contact">
                    <h5 class="footer-title">Kontak</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-geo-alt-fill me-2"></i>Politeknik Negeri Bengkalis</li>
                        <li><i class="bi bi-telephone-fill me-2"></i>(0766) 24566</li>
                        <li><i class="bi bi-envelope-fill me-2"></i>info@polbeng.ac.id</li>
                    </ul>
                </div>
            </div>
        </div>

        <hr class="footer-divider">

        <div class="footer-bottom text-center">
            <p class="mb-0">&copy; 2025 Pemfas - Polbeng. All Rights Reserved. | by WFE</p>
        </div>
    </div>
</footer>

<?php
// Tutup statement dan koneksi database
$stmt->close();
$conn->close();
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // Initialize AOS Animation
    AOS.init({ 
        duration: 800, 
        once: true, 
        offset: 100 
    });

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        }
    });

    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const searchInput = document.getElementById('searchNama');
        if (searchInput && searchInput.value.length > 100) {
            e.preventDefault();
            alert('Pencarian terlalu panjang. Maksimal 100 karakter.');
            return false;
        }
    });
</script>
</body>
</html>
