<?php
session_start();
include '../config/koneksi.php';
include '../config/helpers.php';
// Cek login dengan validasi ketat
if (!isset($_SESSION['id_user']) || $_SESSION['role'] !== 'peminjam') {
    header("Location: ../auth/login.php");
    exit;
}
// Validasi dan sanitasi input dari session
$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}
$nama_user = htmlspecialchars($_SESSION['nama'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8');

// Set page variables
$pageTitle = 'Dashboard Peminjam';
$currentPage = 'dashboard';

/* =========================================================
   1) AMBIL RIWAYAT NOTIFIKASI (10 terbaru) - PREPARED STATEMENT
   ========================================================= */
$notifikasiList = [];
$stmt = $conn->prepare("
    SELECT id_notifikasi, id_pinjam, judul, pesan, tipe, is_read, created_at
    FROM notifikasi
    WHERE id_user = ?
    ORDER BY created_at DESC
    LIMIT 10
");

if ($stmt) {
    $stmt->bind_param("i", $id_user);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notifikasiList[] = [
            'id_notifikasi' => (int)$row['id_notifikasi'],
            'id_pinjam' => (int)$row['id_pinjam'],
            'judul' => htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8'),
            'pesan' => htmlspecialchars($row['pesan'], ENT_QUOTES, 'UTF-8'),
            'tipe' => htmlspecialchars($row['tipe'], ENT_QUOTES, 'UTF-8'),
            'is_read' => (int)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();
}

/* =========================================================
   2) NOTIFIKASI BELUM DIBACA (untuk popup)
   ========================================================= */
$popupNotifs = [];
$stmt2 = $conn->prepare("
    SELECT id_notifikasi, id_pinjam, judul, pesan, tipe, created_at
    FROM notifikasi
    WHERE id_user = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");

if ($stmt2) {
    $stmt2->bind_param("i", $id_user);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    while ($row = $result2->fetch_assoc()) {
        // Tentukan link berdasarkan tipe dengan validasi
        $linkTujuan = '#';
        $tipe = strtolower(trim($row['tipe']));
        $id_pinjam = (int)$row['id_pinjam'];
        
        if ($tipe === 'peminjaman' && $id_pinjam > 0) {
            $linkTujuan = 'peminjaman_saya.php#pinjam' . $id_pinjam;
        } elseif ($tipe === 'pengembalian' && $id_pinjam > 0) {
            $linkTujuan = 'riwayat.php#kembali' . $id_pinjam;
        }

        $popupNotifs[] = [
            'judul' => htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8'),
            'pesan' => htmlspecialchars($row['pesan'], ENT_QUOTES, 'UTF-8'),
            'tipe'  => $tipe,
            'link'  => htmlspecialchars($linkTujuan, ENT_QUOTES, 'UTF-8'),
            'waktu' => format_datetime($row['created_at'])
        ];
    }
    $stmt2->close();
}

/* =========================================================
   3) STATISTIK DASHBOARD - PREPARED STATEMENTS
   ========================================================= */
// Total peminjaman user
$totalPeminjaman = 0;
$stmt_total = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE id_user = ?");
if ($stmt_total) {
    $stmt_total->bind_param("i", $id_user);
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    if ($row = $result_total->fetch_assoc()) {
        $totalPeminjaman = (int)$row['total'];
    }
    $stmt_total->close();
}

// Peminjaman menunggu
$totalMenunggu = 0;
$stmt_menunggu = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE id_user = ? AND status = 'menunggu'");
if ($stmt_menunggu) {
    $stmt_menunggu->bind_param("i", $id_user);
    $stmt_menunggu->execute();
    $result_menunggu = $stmt_menunggu->get_result();
    if ($row = $result_menunggu->fetch_assoc()) {
        $totalMenunggu = (int)$row['total'];
    }
    $stmt_menunggu->close();
}

// Peminjaman diterima
$totalDiterima = 0;
$stmt_diterima = $conn->prepare("SELECT COUNT(*) as total FROM peminjaman WHERE id_user = ? AND status = 'diterima'");
if ($stmt_diterima) {
    $stmt_diterima->bind_param("i", $id_user);
    $stmt_diterima->execute();
    $result_diterima = $stmt_diterima->get_result();
    if ($row = $result_diterima->fetch_assoc()) {
        $totalDiterima = (int)$row['total'];
    }
    $stmt_diterima->close();
}

include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>

<!-- HERO SECTION -->
<section class="hero-dashboard">
    <div class="hero-content">
        <div class="container">
            <h1 class="hero-title" data-aos="fade-up">
                Selamat Datang, <span class="text-accent"><?= $nama_user ?></span> 👋
            </h1>
            <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="100">
                Kelola dan ajukan peminjaman fasilitas kampus secara digital dengan mudah
            </p>
            <div class="hero-actions" data-aos="fade-up" data-aos-delay="200">
                <a href="fasilitas.php" class="btn-hero-primary">
                    <i class="bi bi-building me-2"></i>Lihat Fasilitas
                </a>
                <a href="peminjaman_saya.php" class="btn-hero-secondary">
                    <i class="bi bi-clipboard-check me-2"></i>Peminjaman Saya
                </a>
            </div>
        </div>
    </div>
</section>

<!-- STATISTIK SECTION -->
<section class="stats-section">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card">
                    <div class="stat-icon bg-primary">
                        <i class="bi bi-clipboard-data"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalPeminjaman ?></h3>
                        <p>Total Peminjaman</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card">
                    <div class="stat-icon bg-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalMenunggu ?></h3>
                        <p>Menunggu Approval</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card">
                    <div class="stat-icon bg-success">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $totalDiterima ?></h3>
                        <p>Peminjaman Diterima</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- INFORMASI SECTION -->
<section class="info-section">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="section-title" data-aos="fade-up">Tentang E-Fasilitas</h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                Sistem Digital Peminjaman Fasilitas Kampus Politeknik Negeri Bengkalis
            </p>
        </div>

        <div class="row align-items-center mb-5">
            <div class="col-lg-6" data-aos="fade-right">
                <h3 class="fw-bold text-primary mb-3">Apa itu E-Fasilitas?</h3>
                <p class="text-muted" style="text-align: justify; line-height: 1.8;">
                    E-Fasilitas adalah platform digital yang memudahkan civitas akademika 
                    Politeknik Negeri Bengkalis dalam mengajukan peminjaman fasilitas kampus 
                    seperti ruang kelas, laboratorium, aula, kendaraan, dan fasilitas lainnya 
                    secara online. Sistem ini dirancang untuk meningkatkan efisiensi dan 
                    transparansi dalam proses peminjaman.
                </p>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <img src="../assets/img/gedung.jpg" alt="Kampus" class="img-fluid rounded shadow">
            </div>
        </div>

        <!-- Fasilitas Populer -->
        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark" data-aos="fade-up">Fasilitas Populer</h3>
            <p class="text-muted" data-aos="fade-up" data-aos-delay="100">
                Beberapa fasilitas yang sering dipinjam oleh mahasiswa dan dosen
            </p>
        </div>

        <div class="row g-4">
            <?php
            // Data fasilitas dengan validasi
            $fasilitas = [
                ["img" => "aula.jpg", "nama" => "Aula Serbaguna", "desc" => "Tempat seminar & acara kampus"],
                ["img" => "lab.jpg", "nama" => "Lab Komputer", "desc" => "Perangkat modern untuk praktikum"],
                ["img" => "ruang rapat.jpg", "nama" => "Ruang Rapat", "desc" => "Tempat pertemuan resmi"],
                ["img" => "lapangan.jpg", "nama" => "Lapangan Olahraga", "desc" => "Kegiatan olahraga & event"]
            ];

            foreach ($fasilitas as $index => $f): 
                // Sanitasi data sebelum ditampilkan
                $img_name = htmlspecialchars($f['img'], ENT_QUOTES, 'UTF-8');
                $nama = htmlspecialchars($f['nama'], ENT_QUOTES, 'UTF-8');
                $desc = htmlspecialchars($f['desc'], ENT_QUOTES, 'UTF-8');
            ?>
                <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="<?= ($index + 1) * 100 ?>">
                    <div class="facility-card">
                        <img src="../assets/img/<?= $img_name ?>" 
                             class="facility-img" 
                             alt="<?= $nama ?>">
                        <div class="facility-body">
                            <h5 class="facility-title"><?= $nama ?></h5>
                            <p class="facility-desc"><?= $desc ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5" data-aos="fade-up">
            <a href="fasilitas.php" class="btn-primary-custom">
                <i class="bi bi-eye me-2"></i>Lihat Semua Fasilitas
            </a>
        </div>
    </div>
</section>

<!-- MODAL DETAIL NOTIFIKASI -->
<?php foreach ($notifikasiList as $n): ?>
    <?php
    // Tentukan link dengan validasi
    $linkTujuan = '#';
    $tipe = strtolower($n['tipe']);
    $id_pinjam = (int)$n['id_pinjam'];
    
    if ($tipe === 'peminjaman' && $id_pinjam > 0) {
        $linkTujuan = 'peminjaman_saya.php#pinjam' . $id_pinjam;
    } elseif ($tipe === 'pengembalian' && $id_pinjam > 0) {
        $linkTujuan = 'riwayat.php#kembali' . $id_pinjam;
    }
    ?>
    <div class="modal fade" id="modalNotif<?= $n['id_notifikasi'] ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white;">
                    <h5 class="modal-title">
                        <i class="bi bi-bell me-2"></i>
                        <?= $n['judul'] ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?= nl2br($n['pesan']) ?></p>
                    <hr>
                    <small class="text-muted">
                        <i class="bi bi-clock me-1"></i>
                        <?= format_datetime($n['created_at']) ?>
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <?php if ($linkTujuan !== '#'): ?>
                        <a href="<?= htmlspecialchars($linkTujuan, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i>Buka Halaman
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- POPUP NOTIFIKASI -->
<div id="notifPopup"></div>

<?php include '../includes/peminjam/footer.php'; ?>

<script>
// Initialize AOS
AOS.init({ duration: 1000, once: true, offset: 100 });

// Notification Popup Handler dengan keamanan XSS
document.addEventListener("DOMContentLoaded", function () {
    const popup = document.getElementById("notifPopup");
    const notifs = <?= json_encode($popupNotifs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    notifs.forEach((n, i) => {
        const div = document.createElement("div");
        let tipeClass = n.tipe || 'info';

        div.className = "notif-popup-item " + tipeClass;
        
        // Buat elemen dengan DOM manipulation untuk keamanan
        const header = document.createElement("div");
        header.className = "notif-popup-header";
        
        const title = document.createElement("strong");
        title.innerHTML = '<i class="bi bi-bell me-2"></i>' + n.judul;
        
        const closeBtn = document.createElement("span");
        closeBtn.className = "notif-popup-close";
        closeBtn.innerHTML = "&times;";
        closeBtn.onclick = () => div.remove();
        
        header.appendChild(title);
        header.appendChild(closeBtn);
        
        const body = document.createElement("div");
        body.className = "notif-popup-body";
        body.textContent = n.pesan;
        
        const time = document.createElement("div");
        time.className = "notif-popup-time";
        time.innerHTML = '<i class="bi bi-clock me-1"></i>' + n.waktu;
        
        div.appendChild(header);
        div.appendChild(body);
        div.appendChild(time);
        
        // Click handler dengan validasi
        div.onclick = (e) => {
            if (e.target !== closeBtn && n.link && n.link !== '#') {
                window.location.href = n.link;
            }
        };

        popup.appendChild(div);
        
        // Auto-remove dengan animasi
        setTimeout(() => {
            div.style.animation = 'slideOut 0.5s ease';
            setTimeout(() => div.remove(), 500);
        }, 8000 + i * 1200);
    });
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
</script>
