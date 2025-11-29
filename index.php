<?php
// index.php - Halaman Awal E-Fasilitas (pengunjung belum login)
session_start();

/*
 * Jika user SUDAH login, langsung arahkan ke dashboard sesuai role.
 */
if (isset($_SESSION['id_user'], $_SESSION['role'])) {
    $role = $_SESSION['role'];

    if ($role === 'peminjam') {
        header('Location: peminjam/dashboard.php');
        exit;
    } elseif ($role === 'bagian_umum') {
        header('Location: bagian_umum/dashboard.php');
        exit;
    } elseif ($role === 'super_admin') {
        header('Location: super_admin/dashboard.php');
        exit;
    }
}

/* Security headers dasar */
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>E-Fasilitas | Politeknik Negeri Bengkalis</title>

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- AOS Animation -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

  <!-- CSS Utama -->
  <link rel="stylesheet" href="assets/css/peminjam.css">
</head>

<body>

<!-- ======== NAVBAR ======== -->
<nav class="navbar navbar-expand-lg fixed-top shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="assets/img/logo.png" alt="Logo" style="width:45px;height:45px;border-radius:50%;margin-right:10px;">
      <span>E-Fasilitas</span>
    </a>
    <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <!-- Menu di TENGAH -->
      <ul class="navbar-nav mx-auto align-items-center">
        <li class="nav-item">
          <a class="nav-link active" href="index.php">
            <i class="bi bi-house-door-fill"></i> Home
          </a>
        </li>
        <li class="nav-item">
          <!-- Hanya scroll ke section #fasilitas di halaman ini -->
          <a class="nav-link" href="fasilitas.php">
            <i class="bi bi-building"></i> Fasilitas
          </a>
        </li>
      </ul>

      <!-- Tombol login di KANAN -->
      <div class="d-flex">
        <a href="auth/login.php" class="btn btn-login" style="background-color: #ffc933;">
          <i class="bi bi-box-arrow-in-right"></i> Login
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- ======== HERO ======== -->
<section id="home" class="hero">
  <div class="hero-content container">
    <h1>Selamat Datang di <span style="color:#ffb703;">E-Fasilitas</span></h1>
    <p>Sistem Digital Peminjaman Fasilitas Kampus Politeknik Negeri Bengkalis</p>
    <a href="auth/login.php" class="btn btn-hero" style="background-color: #ffc933;">
      <i class="bi bi-calendar-check me-2"></i>Pinjam Sekarang
    </a>
  </div>
</section>

<!-- ======== SECTION INFO ======== -->
<section id="fasilitas" class="section-info">
  <div class="container"><br><br>
    <h2 class="text-center mb-5" data-aos="fade-up">Langkah Peminjaman Fasilitas</h2>
    <div class="row g-4 justify-content-center">
      <div class="col-md-4" data-aos="fade-right">
        <div class="card card-step text-center p-4 shadow-sm">
          <i class="bi bi-person-check display-4 text-primary mb-3"></i>
          <h5 class="fw-semibold">1. Login / Registrasi</h5>
          <p>Masuk ke akun Anda atau daftar terlebih dahulu untuk dapat mengajukan peminjaman fasilitas.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up">
        <div class="card card-step text-center p-4 shadow-sm">
          <i class="bi bi-building text-success display-4 mb-3"></i>
          <h5 class="fw-semibold">2. Pilih Fasilitas</h5>
          <p>Tentukan fasilitas yang ingin dipinjam, pilih tanggal peminjaman dan keperluan Anda.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-left">
        <div class="card card-step text-center p-4 shadow-sm">
          <i class="bi bi-check-circle text-warning display-4 mb-3"></i>
          <h5 class="fw-semibold">3. Tunggu Persetujuan</h5>
          <p>Peminjaman akan diverifikasi oleh bagian umum. Setelah disetujui, fasilitas dapat digunakan sesuai jadwal.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/peminjam/footer.php'; ?>

<!-- ======== SCRIPT ======== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  AOS.init({ duration: 1000, once: true });

  window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    navbar.classList.toggle('scrolled', window.scrollY > 50);
  });
</script>
</body>
</html>
