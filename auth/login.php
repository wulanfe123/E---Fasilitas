<?php
session_start();
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - E-Fasilitas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      background: url('../assets/img/bg-campus.jpg') no-repeat center center fixed;
      background-size: cover;
      min-height: 100vh;
    }
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(3px);
      z-index: 0;
    }
    main {
      position: relative;
      z-index: 1;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-card {
      width: 400px;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 0 18px rgba(0,0,0,0.4);
      background: rgba(255,255,255,0.92);
    }
    .login-header {
      background-color: #002855;
      color: white;
      text-align: center;
      padding: 20px;
    }
    .btn-login {
      background-color: #002855;
      color: white;
    }
    .btn-login:hover {
      background-color: #004080;
    }
    .toggle-pass {
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="overlay"></div>

  <main>
    <div class="login-card">
      <div class="login-header">
        <img src="../assets/img/logo.png" width="60" height="60" class="rounded-circle border border-white mb-2"/>
        <h5 class="fw-bold mb-0">E-Fasilitas Kampus</h5>
        <small>Sistem Peminjaman Fasilitas</small>
      </div>

      <div class="p-4">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger text-center py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="proses_login.php" method="POST">
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Username</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-person-fill"></i></span>
              <input type="text" name="username" class="form-control" required autocomplete="username" autofocus>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-lock-fill"></i></span>
              <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password">
              <span class="input-group-text bg-light toggle-pass" onclick="togglePassword()">
                <i class="bi bi-eye-slash" id="icon-pass"></i>
              </span>
            </div>
          </div>

          <button class="btn btn-login w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Login</button>

        </form>
      </div>

      <div class="text-center text-muted small py-2 bg-light">
        © <?= date('Y') ?> Politeknik Negeri Bengkalis
      </div>
    </div>
  </main>

  <script>
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
  </script>

</body>
</html>
