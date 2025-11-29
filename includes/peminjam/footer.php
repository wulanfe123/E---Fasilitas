<footer style="background-color: #0b2c61; color: white;" class="py-5 mt-5">
  <div class="container">
    <div class="row align-items-start text-center text-md-start">
      
      <!-- Kolom kiri -->
      <div class="col-md-4 mb-4" data-aos="fade-right">
        <h6 class="fw-bold text-uppercase">Tentang E-Fasilitas</h6>
        <p class="mt-3 mb-0">
          Sistem peminjaman fasilitas kampus yang memudahkan mahasiswa dan staf 
          untuk melakukan peminjaman secara online, cepat, dan aman.
        </p>
      </div>
      <!-- Kolom tengah -->
      <div class="col-md-4 mb-4 text-center" data-aos="fade-up">
        <h6 class="fw-bold text-uppercase">Link Cepat</h6>
        <ul class="list-unstyled mt-3">
          <li class="mb-2">
            <a href="dashboard.php" class="text-white text-decoration-none">
              <i class="bi bi-house me-2"></i>Home
            </a>
          </li>
          <li class="mb-2">
            <a href="fasilitas.php" class="text-white text-decoration-none">
              <i class="bi bi-building me-2"></i>Fasilitas
            </a>
          </li>
          <li class="mb-2">
            <a href="../auth/logout.php" class="text-white text-decoration-none">
              <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
          </li>
        </ul>
      </div>

      <!-- Kolom kanan -->
      <div class="col-md-4 mb-4 text-md-end" data-aos="fade-left">
        <h6 class="fw-bold text-uppercase text-md-end">Kontak</h6>
        <div class="mt-3">
          <p class="mb-1"><i class="bi bi-envelope me-2"></i>info@polbeng.ac.id</p>
          <p class="mb-2"><i class="bi bi-telephone me-2"></i>(0761) 123456</p>
        </div>
        <div class="social-icons mt-3">
          <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
          <a href="#" class="text-white me-3"><i class="bi bi-instagram"></i></a>
          <a href="#" class="text-white"><i class="bi bi-twitter"></i></a>
        </div>
      </div>

    </div>

    <hr class="border-light">
    <div class="text-center small mt-3">
      &copy; <?= date('Y') ?> Politeknik Negeri Bengkalis | Sistem E-Fasilitas
    </div>
  </div>
</footer>
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