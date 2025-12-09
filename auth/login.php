<?php
/**
 * login.php - Halaman Login E-Fasilitas
 * 
 * SKRIPSI: IMPLEMENTASI PREPARED STATEMENT PADA FORM INPUT 
 *          UNTUK MENCEGAH SQL INJECTION PADA APLIKASI WEB 
 *          PEMINJAMAN FASILITAS KAMPUS
 * 
 * Security Features:
 * - CSRF Token Protection
 * - Session Security
 * - Input Validation (client & server side)
 * - Security Headers
 * - Rate Limiting Prevention
 */

session_start();

// Regenerate session ID untuk mencegah session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Redirect jika sudah login
if (isset($_SESSION['id_user'], $_SESSION['role'])) {
    $role = $_SESSION['role'];
    $allowed_roles = ['peminjam', 'bagian_umum', 'super_admin'];
    
    if (in_array($role, $allowed_roles, true)) {
        switch ($role) {
            case 'peminjam':
                header('Location: ../peminjam/dashboard.php');
                exit;
            case 'bagian_umum':
                header('Location: ../bagian_umum/dashboard.php');
                exit;
            case 'super_admin':
                header('Location: ../super_admin/dashboard.php');
                exit;
        }
    }
}

// Generate CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil error message (jika ada)
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);

// Security Headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data:; connect-src 'self';");
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Login E-Fasilitas Politeknik Negeri Bengkalis - Sistem Peminjaman Fasilitas Kampus">
  <meta name="robots" content="noindex, nofollow">
  <title>Login - E-Fasilitas Polbeng</title>
  
  <!-- Bootstrap & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-color: #0b2c61;
      --accent-color: #ffb703;
      --light-bg: #f4f6fb;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: url('../assets/img/gedung.jpg') no-repeat center center fixed;
      background-size: cover;
      min-height: 100vh;
      position: relative;
    }

    /* Overlay dengan gradient */
    .overlay {
      position: fixed;
      inset: 0;
      background: linear-gradient(135deg, rgba(11, 44, 97, 0.85) 0%, rgba(10, 35, 80, 0.9) 100%);
      backdrop-filter: blur(5px);
      z-index: 0;
    }

    /* Pattern Background */
    .overlay::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-image: 
        radial-gradient(circle at 20% 50%, rgba(255, 183, 3, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 183, 3, 0.1) 0%, transparent 50%);
      animation: float 8s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-20px); }
    }

    main {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    /* Login Card */
    .login-card {
      width: 100%;
      max-width: 450px;
      border-radius: 25px;
      overflow: hidden;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
      background: white;
      animation: slideUp 0.6s ease;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Login Header */
    .login-header {
      background: linear-gradient(135deg, var(--primary-color) 0%, #0a2350 100%);
      color: white;
      text-align: center;
      padding: 40px 30px;
      position: relative;
      overflow: hidden;
    }

    .login-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -50%;
      width: 200px;
      height: 200px;
      background: rgba(255, 183, 3, 0.15);
      border-radius: 50%;
    }

    .login-header img {
      position: relative;
      z-index: 2;
      border: 4px solid rgba(255, 183, 3, 0.3);
      transition: all 0.3s ease;
      pointer-events: none;
      user-select: none;
    }

    .login-header img:hover {
      transform: scale(1.1) rotate(5deg);
      border-color: var(--accent-color);
    }

    .login-header h5 {
      position: relative;
      z-index: 2;
      font-weight: 800;
      margin-bottom: 5px;
      font-size: 1.5rem;
    }

    .login-header small {
      position: relative;
      z-index: 2;
      opacity: 0.9;
      font-size: 0.9rem;
    }

    /* Form Container */
    .login-body {
      padding: 40px 35px;
    }

    /* Alert */
    .alert {
      border-radius: 12px;
      border: none;
      font-size: 0.9rem;
      animation: shake 0.5s ease;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }

    .alert-danger {
      background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
      color: white;
      box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
    }

    /* Form Label */
    .form-label {
      font-weight: 600;
      color: var(--primary-color);
      margin-bottom: 8px;
      font-size: 0.9rem;
    }

    /* Input Group */
    .input-group {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(11, 44, 97, 0.08);
      transition: all 0.3s ease;
    }

    .input-group:focus-within {
      box-shadow: 0 4px 15px rgba(11, 44, 97, 0.15);
      transform: translateY(-2px);
    }

    .input-group-text {
      background: var(--primary-color);
      color: white;
      border: none;
      padding: 12px 15px;
    }

    .form-control {
      border: 2px solid #e5e7eb;
      border-left: none;
      padding: 12px 15px;
      font-size: 0.95rem;
    }

    .form-control:focus {
      border-color: var(--accent-color);
      box-shadow: none;
    }

    .form-control.is-invalid {
      border-color: #dc2626;
    }

    /* Feedback validation */
    .invalid-feedback {
      font-size: 0.85rem;
      margin-top: 5px;
    }

    /* Toggle Password */
    .toggle-pass {
      cursor: pointer;
      user-select: none;
      transition: all 0.3s ease;
    }

    .toggle-pass:hover {
      background: var(--accent-color) !important;
      color: var(--primary-color) !important;
    }

    /* Login Button */
    .btn-login {
      background: linear-gradient(135deg, var(--primary-color) 0%, #0a2350 100%);
      color: white;
      font-weight: 700;
      padding: 14px;
      border-radius: 12px;
      border: none;
      font-size: 1.05rem;
      box-shadow: 0 8px 20px rgba(11, 44, 97, 0.3);
      transition: all 0.3s ease;
      margin-top: 10px;
    }

    .btn-login:hover:not(:disabled) {
      background: linear-gradient(135deg, var(--accent-color) 0%, #ffd700 100%);
      color: var(--primary-color);
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(255, 183, 3, 0.4);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .btn-login:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    /* Back to Home Link */
    .back-home {
      text-align: center;
      margin-top: 20px;
    }

    .back-home a {
      color: #64748b;
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    .back-home a:hover {
      color: var(--primary-color);
      transform: translateX(-5px);
    }

    /* Footer */
    .login-footer {
      text-align: center;
      padding: 20px;
      background: var(--light-bg);
      color: #64748b;
      font-size: 0.85rem;
    }

    /* Loading spinner */
    .spinner-border-sm {
      width: 1rem;
      height: 1rem;
    }

    /* Responsive */
    @media (max-width: 576px) {
      .login-card {
        max-width: 100%;
      }

      .login-header {
        padding: 30px 20px;
      }

      .login-header h5 {
        font-size: 1.3rem;
      }

      .login-body {
        padding: 30px 25px;
      }

      .btn-login {
        padding: 12px;
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>
  <div class="overlay"></div>

  <main>
    <div class="login-card">
      <!-- Header -->
      <div class="login-header">
        <img src="../assets/img/Logo.png" width="80" height="80" class="rounded-circle mb-3" alt="Logo Politeknik Negeri Bengkalis"/>
        <h5 class="mb-1">E-Fasilitas</h5>
        <small>Sistem Digital Peminjaman Fasilitas<br>Politeknik Negeri Bengkalis</small>
      </div>

      <!-- Body -->
      <div class="login-body">
        <!-- Error Alert -->
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger text-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="proses_login.php" method="POST" id="loginForm" novalidate>
          
          <!-- CSRF Token (Security) -->
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          
          <!-- Username -->
          <div class="mb-3">
            <label for="username" class="form-label">
              <i class="bi bi-person-circle me-1"></i>Username
            </label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="bi bi-person-fill"></i>
              </span>
              <input 
                type="text" 
                name="username" 
                id="username"
                class="form-control" 
                placeholder="Masukkan username"
                required 
                autocomplete="username" 
                autofocus
                minlength="3"
                maxlength="50"
                pattern="^[a-zA-Z0-9_.-]+$"
                title="Username hanya boleh mengandung huruf, angka, titik, underscore, dan dash">
              <div class="invalid-feedback">
                Username harus diisi (3-50 karakter, alfanumerik)
              </div>
            </div>
          </div>

          <!-- Password -->
          <div class="mb-3">
            <label for="password" class="form-label">
              <i class="bi bi-shield-lock me-1"></i>Password
            </label>
            <div class="input-group">
              <span class="input-group-text">
                <i class="bi bi-lock-fill"></i>
              </span>
              <input 
                type="password" 
                name="password" 
                id="password" 
                class="form-control" 
                placeholder="Masukkan password"
                required 
                autocomplete="current-password"
                minlength="6"
                maxlength="255">
              <span class="input-group-text toggle-pass bg-light" onclick="togglePassword()" tabindex="0" role="button" aria-label="Toggle password visibility">
                <i class="bi bi-eye-slash" id="icon-pass"></i>
              </span>
              <div class="invalid-feedback">
                Password harus diisi (minimal 6 karakter)
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn-login w-100" id="btnLogin">
            <i class="bi bi-box-arrow-in-right me-2"></i>
            <span id="btnText">Login Sekarang</span>
            <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2 d-none" role="status" aria-hidden="true"></span>
          </button>

        </form>

        <!-- Back to Home -->
        <div class="back-home">
          <a href="../index.php">
            <i class="bi bi-arrow-left"></i>
            Kembali ke Beranda
          </a>
        </div>
      </div>

      <!-- Footer -->
      <div class="login-footer">
        <i class="bi bi-shield-check me-1"></i>
        © <?= date('Y') ?> Politeknik Negeri Bengkalis
      </div>
    </div>
  </main>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script>
    // Toggle Password Visibility
    function togglePassword() {
      const pass = document.getElementById('password');
      const icon = document.getElementById('icon-pass');
      
      if (pass.type === 'password') {
        pass.type = 'text';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
      } else {
        pass.type = 'password';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
      }
    }

    // Form Validation & Security
    (function() {
      'use strict';

      const form = document.getElementById('loginForm');
      const btnLogin = document.getElementById('btnLogin');
      const btnText = document.getElementById('btnText');
      const btnSpinner = document.getElementById('btnSpinner');
      const usernameInput = document.getElementById('username');
      const passwordInput = document.getElementById('password');

      // Input Sanitization (Client-side)
      usernameInput.addEventListener('input', function() {
        // Remove special characters except allowed ones
        this.value = this.value.replace(/[^a-zA-Z0-9_.-]/g, '');
      });

      passwordInput.addEventListener('input', function() {
        // Trim whitespace
        this.value = this.value.trim();
      });

      // Form Submit Handler
      form.addEventListener('submit', function(event) {
        event.preventDefault();
        event.stopPropagation();

        // Validate form
        if (!form.checkValidity()) {
          form.classList.add('was-validated');
          return false;
        }

        // Additional validation
        const username = usernameInput.value.trim();
        const password = passwordInput.value;

        if (username.length < 3 || username.length > 50) {
          usernameInput.setCustomValidity('Username harus 3-50 karakter');
          form.classList.add('was-validated');
          return false;
        }

        if (password.length < 6) {
          passwordInput.setCustomValidity('Password minimal 6 karakter');
          form.classList.add('was-validated');
          return false;
        }

        // Clear custom validity
        usernameInput.setCustomValidity('');
        passwordInput.setCustomValidity('');

        // Disable button & show loading
        btnLogin.disabled = true;
        btnText.textContent = 'Memproses...';
        btnSpinner.classList.remove('d-none');

        // Submit form
        form.submit();
      });

      // Auto hide alert after 5 seconds
      const alert = document.querySelector('.alert');
      if (alert) {
        setTimeout(() => {
          alert.style.transition = 'opacity 0.5s ease';
          alert.style.opacity = '0';
          setTimeout(() => alert.remove(), 500);
        }, 5000);
      }

      // Prevent multiple form submissions
      let formSubmitted = false;
      form.addEventListener('submit', function() {
        if (formSubmitted) {
          return false;
        }
        formSubmitted = true;
      });

      // Security: Prevent opening in iframe
      if (window.top !== window.self) {
        window.top.location = window.self.location;
      }

      // Security: Clear password on page unload
      window.addEventListener('beforeunload', function() {
        passwordInput.value = '';
      });

    })();
  </script>

</body>
</html>
