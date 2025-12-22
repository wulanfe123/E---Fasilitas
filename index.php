<?php
session_start();
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}
if (isset($_SESSION['id_user'], $_SESSION['role'])) {
    $role = $_SESSION['role'];
    
    // Whitelist role yang diizinkan
    $allowed_roles = ['peminjam', 'bagian_umum', 'super_admin'];
    
    // Validasi role dengan strict comparison
    if (in_array($role, $allowed_roles, true)) {
        switch ($role) {
            case 'peminjam':
                header('Location: peminjam/dashboard.php');
                exit;
            case 'bagian_umum':
                header('Location: bagian_umum/dashboard.php');
                exit;
            case 'super_admin':
                header('Location: super_admin/dashboard.php');
                exit;
        }
    } else {
        // Role tidak valid, destroy session untuk keamanan
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
    }
}

// Enhanced Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

// Content Security Policy untuk mencegah XSS
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://unpkg.com 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com https://unpkg.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");

// Strict Transport Security (jika menggunakan HTTPS)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Sistem Digital Peminjaman Fasilitas Kampus Politeknik Negeri Bengkalis dengan keamanan Prepared Statement">
  <meta name="keywords" content="Pemfas, peminjaman fasilitas, politeknik bengkalis, kampus">
  <meta name="author" content="Politeknik Negeri Bengkalis">
  <meta name="robots" content="index, follow">
  
  <title>Pemfas | Politeknik Negeri Bengkalis</title>

  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- AOS Animation -->
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  
  <!-- Custom CSS -->
  <link href="assets/css/peminjam.css" rel="stylesheet">

  <style>
    :root {
      --primary-color: #0b2c61;
      --accent-color:  #ffb703;
      --light-bg:      #f4f6fb;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      overflow-x: hidden;
      background: var(--light-bg);
    }

    /* ========== NAVBAR ========== */
    .navbar {
      background: linear-gradient(135deg, var(--primary-color) 0%, #0a2350 100%);
      backdrop-filter: blur(10px);
      transition: all 0.3s ease;
      padding: 1rem 0;
    }

    .navbar.scrolled {
      background: rgba(11, 44, 97, 0.95);
      box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    }

    .navbar-brand {
      font-size: 1.5rem;
      font-weight: 700;
      color: white !important;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .navbar-brand img {
      transition: transform 0.3s ease;
      pointer-events: none;
      user-select: none;
    }

    .navbar-brand:hover img {
      transform: scale(1.1) rotate(5deg);
    }

    .navbar-brand:hover {
      transform: scale(1.05);
      transition: 0.3s;
    }

    .nav-link {
      color: rgba(255,255,255,0.9) !important;
      font-weight: 500;
      margin: 0 15px;
      padding: 8px 20px !important;
      border-radius: 25px;
      transition: all 0.3s;
      position: relative;
    }

    .nav-link:hover, .nav-link.active {
      background: rgba(255,183,3,0.2);
      color: var(--accent-color) !important;
      transform: translateY(-2px);
    }

    .btn-login {
      background: linear-gradient(135deg, var(--accent-color) 0%, #ffd700 100%) !important;
      color: #0b2c61 !important;
      font-weight: 700 !important;
      padding: 10px 30px !important;
      border-radius: 25px !important;
      border: none !important;
      box-shadow: 0 4px 15px rgba(255,183,3,0.4) !important;
      transition: all 0.3s !important;
    }

    .btn-login:hover {
      transform: translateY(-3px) !important;
      box-shadow: 0 6px 20px rgba(255,183,3,0.6) !important;
      background: linear-gradient(135deg, #ffd700 0%, var(--accent-color) 100%) !important;
    }

    /* ========== HERO SECTION ========== */
    .hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      overflow: hidden;
      padding-top: 80px;
      background: url('assets/img/gedung.jpg') center/cover no-repeat;
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
    }

    .hero::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(11, 44, 97, 0.7) 0%, rgba(10, 35, 80, 0.8) 100%);
      z-index: 1;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      text-align: center;
      color: white;
    }

    .hero-content h1 {
      font-size: 3.5rem;
      font-weight: 800;
      margin-bottom: 1.5rem;
      text-shadow: 2px 2px 10px rgba(0,0,0,0.3);
      animation: fadeInUp 1s ease;
    }

    .hero-content h1 span {
      background: linear-gradient(to right, var(--accent-color), #ffd700);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      text-shadow: none;
    }

    .hero-content p {
      font-size: 1.3rem;
      margin-bottom: 2.5rem;
      opacity: 0.95;
      animation: fadeInUp 1.2s ease;
      text-shadow: 1px 1px 5px rgba(0,0,0,0.3);
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .btn-hero {
      background: linear-gradient(135deg, var(--accent-color) 0%, #ffd700 100%);
      color: #333 !important;
      font-size: 1.2rem;
      font-weight: 700;
      padding: 15px 50px;
      border-radius: 50px;
      border: none;
      box-shadow: 0 8px 25px rgba(255,183,3,0.4);
      transition: all 0.3s;
      text-decoration: none;
      display: inline-block;
      animation: fadeInUp 1.4s ease;
    }

    .btn-hero:hover {
      transform: translateY(-5px) scale(1.05);
      box-shadow: 0 12px 35px rgba(255,183,3,0.6);
    }

    /* ========== SECTION INFO ========== */
    .section-info {
      padding: 100px 0;
      background: var(--light-bg);
      position: relative;
    }

    .section-info h2 {
      font-size: 2.8rem;
      font-weight: 700;
      color: var(--primary-color);
      margin-bottom: 60px;
      position: relative;
      display: inline-block;
    }

    .section-info h2::after {
      content: '';
      position: absolute;
      bottom: -15px;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
      background: linear-gradient(to right, var(--primary-color), var(--accent-color));
      border-radius: 2px;
    }

    .card-step {
      border: none;
      border-radius: 20px;
      transition: all 0.4s ease;
      background: white;
      height: 100%;
      overflow: hidden;
      position: relative;
      box-shadow: 0 5px 15px rgba(11, 44, 97, 0.08);
    }

    .card-step::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 5px;
      background: linear-gradient(to right, var(--primary-color), var(--accent-color));
      transform: scaleX(0);
      transition: transform 0.4s ease;
    }

    .card-step:hover {
      transform: translateY(-15px);
      box-shadow: 0 20px 40px rgba(11, 44, 97, 0.15);
    }

    .card-step:hover::before {
      transform: scaleX(1);
    }

    .card-step i {
      font-size: 4rem;
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 20px;
    }

    .card-step h5 {
      color: var(--primary-color);
      font-weight: 700;
      margin-bottom: 15px;
      font-size: 1.3rem;
    }

    .card-step p {
      color: #666;
      line-height: 1.8;
      font-size: 1rem;
    }

    /* ========== FEATURES SECTION ========== */
    .section-features {
      padding: 100px 0;
      background: linear-gradient(135deg, var(--primary-color) 0%, #0a2350 100%);
      color: white;
    }

    .section-features h2 {
      color: white;
      margin-bottom: 60px;
    }

    .section-features h2::after {
      background: linear-gradient(to right, var(--accent-color), #ffd700);
    }

    .feature-box {
      text-align: center;
      padding: 30px;
      border-radius: 15px;
      background: rgba(255,255,255,0.1);
      backdrop-filter: blur(10px);
      transition: all 0.3s;
      border: 1px solid rgba(255,255,255,0.1);
    }

    .feature-box:hover {
      background: rgba(255,183,3,0.15);
      border-color: var(--accent-color);
      transform: translateY(-10px);
    }

    .feature-box i {
      font-size: 3rem;
      margin-bottom: 20px;
      color: var(--accent-color);
    }

    .feature-box h5 {
      color: white;
      font-weight: 600;
      margin-bottom: 10px;
    }

    .feature-box p {
      color: rgba(255,255,255,0.8);
      margin: 0;
    }

   /* ========== FOOTER ========== */
.footer-section {
  background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
  color: white;
  padding: 60px 0 20px;
  margin-top: 0;
}

.footer-section .container {
  max-width: 1200px;
}

/* Tambahkan ini untuk memastikan semua kolom sejajar */
.footer-section .row {
  align-items: flex-start;
}

/* KOLOM KIRI: Pemfas */
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

/* KOLOM TENGAH: Link Cepat */
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

/* KOLOM KANAN: Kontak - PERBAIKAN DI SINI */
/* KOLOM KANAN: Kontak - PERBAIKAN STRUKTUR */
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
  padding: 0;
  list-style: none;
  margin: 0;
}

.footer-section .footer-contact ul li {
  margin-bottom: 12px;
}

.footer-section .footer-contact ul li span {
  color: rgba(255, 255, 255, 0.8);
  line-height: 1.6;
}

.footer-section .footer-contact ul li i {
  color: var(--accent-color);
  font-size: 1rem;
  flex-shrink: 0;
  width: 20px;
  text-align: center;
}


/* Social Links */
.social-links a {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: rgba(255, 183, 3, 0.15);
  border: 2px solid rgba(255, 183, 3, 0.3);
  display: inline-flex; /* UBAH dari flex ke inline-flex */
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
  box-shadow: 0 5px 15px rgba(255, 183, 3, 0.4);
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
  .hero-content h1 {
    font-size: 2.5rem;
  }
  
  .hero-content p {
    font-size: 1.1rem;
  }

  .section-info h2 {
    font-size: 2rem;
  }

  .btn-hero {
    padding: 12px 35px;
    font-size: 1rem;
  }

  /* Footer Mobile: Semua kolom di tengah */
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
    padding-left: 0;
  }

  .footer-section .footer-links ul li,
  .footer-section .footer-contact ul li {
    text-align: center !important;
    justify-content: center !important;
  }

  /* Fix icon position di mobile */
  .footer-section .footer-contact ul li i {
    margin-left: 0;
    margin-right: 8px;
  }
}

  </style>
</head>

<body>

<!-- ========== NAVBAR ========== -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container">
    <a class="navbar-brand" href="index.php" aria-label="Home Pemfas">
      <img src="assets/img/Logo.png" alt="Logo Politeknik Negeri Bengkalis" style="width:50px;height:50px;border-radius:50%;">
      <span>Pemfas</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link active" href="index.php" aria-current="page">
            <i class="bi bi-house-door-fill me-1"></i> Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="fasilitas.php">
            <i class="bi bi-building me-1"></i> Fasilitas
          </a>
        </li>
      </ul>

      <div class="d-flex">
        <a href="auth/login.php" class="btn btn-login">
          <i class="bi bi-box-arrow-in-right me-2"></i> Login
        </a>
      </div>
    </div>
  </div>
</nav>

<!-- ========== HERO SECTION ========== -->
<section id="home" class="hero">
  <div class="hero-content container">
    <h1 data-aos="fade-up">Selamat Datang di <span>Pemfas</span></h1>
    <p data-aos="fade-up" data-aos-delay="200">
      Sistem Digital Peminjaman Fasilitas Kampus<br>
      <strong>Politeknik Negeri Bengkalis</strong>
    </p>
    <a href="auth/login.php" class="btn btn-hero" data-aos="fade-up" data-aos-delay="400">
      <i class="bi bi-calendar-check me-2"></i>Pinjam Sekarang
    </a>
  </div>
</section>

<!-- ========== LANGKAH PEMINJAMAN ========== -->
<section id="langkah" class="section-info">
  <div class="container">
    <div class="text-center mb-5">
      <h2 data-aos="fade-up">Langkah Mudah Peminjaman Fasilitas</h2>
    </div>
    
    <div class="row g-4">
      <div class="col-md-4" data-aos="fade-right" data-aos-delay="100">
        <div class="card card-step p-4">
          <i class="bi bi-person-check-fill"></i>
          <h5>1. Login / Registrasi</h5>
          <p>Masuk ke akun Anda atau daftar terlebih dahulu untuk dapat mengajukan peminjaman fasilitas kampus.</p>
        </div>
      </div>
      
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card card-step p-4">
          <i class="bi bi-building-fill-check"></i>
          <h5>2. Pilih Fasilitas</h5>
          <p>Pilih fasilitas yang tersedia, tentukan tanggal dan waktu peminjaman sesuai kebutuhan Anda.</p>
        </div>
      </div>
      
      <div class="col-md-4" data-aos="fade-left" data-aos-delay="300">
        <div class="card card-step p-4">
          <i class="bi bi-check-circle-fill"></i>
          <h5>3. Tunggu Persetujuan</h5>
          <p>Peminjaman akan diverifikasi oleh bagian umum. Notifikasi otomatis akan dikirim setelah disetujui.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ========== FITUR UNGGULAN ========== -->
<section class="section-features">
  <div class="container">
    <div class="text-center mb-5">
      <h2 data-aos="fade-up">Fitur Unggulan</h2>
    </div>
    
    <div class="row g-4">
      <div class="col-md-3" data-aos="zoom-in" data-aos-delay="100">
        <div class="feature-box">
          <i class="bi bi-lightning-charge-fill"></i>
          <h5>Cepat & Mudah</h5>
          <p>Proses peminjaman yang simpel dan cepat</p>
        </div>
      </div>
      
      <div class="col-md-3" data-aos="zoom-in" data-aos-delay="200">
        <div class="feature-box">
          <i class="bi bi-shield-check"></i>
          <h5>Aman</h5>
          <p>Data terenkripsi dan terlindungi</p>
        </div>
      </div>
      
      <div class="col-md-3" data-aos="zoom-in" data-aos-delay="300">
        <div class="feature-box">
          <i class="bi bi-bell-fill"></i>
          <h5>Notifikasi Real-time</h5>
          <p>Update status peminjaman langsung</p>
        </div>
      </div>
      
      <div class="col-md-3" data-aos="zoom-in" data-aos-delay="400">
        <div class="feature-box">
          <i class="bi bi-graph-up-arrow"></i>
          <h5>Laporan Lengkap</h5>
          <p>Riwayat dan statistik peminjaman</p>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- ========== FOOTER ========== -->
<footer class="footer-section">
  <div class="container">
    <div class="row g-4">
      <!-- KOLOM KIRI: Pemfas -->
      <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="footer-about">
          <h5 class="footer-title">Pemfas</h5>
          <p>Sistem Digital Peminjaman Fasilitas Kampus Politeknik Negeri Bengkalis</p>
          <div class="social-links mt-3">
            <a href="#" aria-label="Facebook" rel="noopener noreferrer"><i class="bi bi-facebook"></i></a>
            <a href="#" aria-label="Instagram" rel="noopener noreferrer"><i class="bi bi-instagram"></i></a>
            <a href="#" aria-label="Twitter" rel="noopener noreferrer"><i class="bi bi-twitter"></i></a>
            <a href="#" aria-label="LinkedIn" rel="noopener noreferrer"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>
      </div>

      <!-- KOLOM TENGAH: Link Cepat -->
      <div class="col-lg-4 col-md-4 col-sm-12">
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
      <div class="col-lg-4 col-md-4 col-sm-12">
        <div class="footer-contact">
          <h5 class="footer-title">Kontak</h5>
          <ul class="list-unstyled">
            <li>
              <span class="d-flex align-items-start justify-content-end">
                <span class="text-end flex-grow-1">Politeknik Negeri Bengkalis</span>
                <i class="bi bi-geo-alt-fill ms-2"></i>
              </span>
            </li>
            <li>
              <span class="d-flex align-items-start justify-content-end">
                <span class="text-end flex-grow-1">(0766) 24566</span>
                <i class="bi bi-telephone-fill ms-2"></i>
              </span>
            </li>
            <li>
              <span class="d-flex align-items-start justify-content-end">
                <span class="text-end flex-grow-1">polbeng@polbeng.ac.id</span>
                <i class="bi bi-envelope-fill ms-2"></i>
              </span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <hr class="footer-divider">

    <div class="footer-bottom text-center">
      <p class="mb-0">&copy; <?= date('Y') ?>  Pemfas - Polbeng. All Rights Reserved. | by WFE
</p>
    </div>
  </div>
</footer>

<!-- ========== SCRIPTS ========== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
  // Initialize AOS Animation
  AOS.init({ 
    duration: 1000, 
    once: true,
    offset: 100
  });

  // Navbar scroll effect
  window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  });

  // Smooth scroll
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });
  if (window.top !== window.self) {
    window.top.location = window.self.location;
  }
  document.querySelectorAll('.navbar-brand img, .footer img').forEach(img => {
    img.addEventListener('contextmenu', e => e.preventDefault());
  });
</script>

</body>
</html>
