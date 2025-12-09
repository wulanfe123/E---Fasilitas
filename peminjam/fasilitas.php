<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
// Validasi ID user dari session
$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$nama_user = htmlspecialchars($_SESSION['nama'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8');

// Set page variables
$pageTitle = 'Fasilitas Kampus';
$currentPage = 'fasilitas';

/* =========================================================
   VALIDASI INPUT FILTER (GET)
   ========================================================= */
$allowedKategori = ['ruangan', 'kendaraan', 'lapangan', 'pendukung'];
$allowedStatus   = ['tersedia', 'tidak_tersedia'];

$kategori = isset($_GET['kategori']) ? strtolower(trim($_GET['kategori'])) : '';
if (!in_array($kategori, $allowedKategori, true)) {
    $kategori = '';
}

$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
// Batasi panjang search untuk keamanan
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}
// Sanitasi search input
$search = htmlspecialchars($search, ENT_QUOTES, 'UTF-8');

/* ======================================
   QUERY FASILITAS + KETERSEDIAAN AKTUAL 
   ======================================*/
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
                  AND (
                        (CURDATE() BETWEEN p.tanggal_mulai AND p.tanggal_selesai)
                        OR (pg.kondisi = 'rusak')
                  )
            )
            THEN 'tidak_tersedia'
            ELSE 'tersedia'
        END AS ketersediaan_aktual
    FROM fasilitas f
    WHERE 1=1
";

$types  = '';
$params = [];

// Filter kategori
if ($kategori !== '') {
    $sql    .= " AND LOWER(f.kategori) = ?";
    $types  .= 's';
    $params[] = $kategori;
}

// Filter search (nama / keterangan)
if ($search !== '') {
    $sql    .= " AND (f.nama_fasilitas LIKE ? OR f.keterangan LIKE ?)";
    $types  .= 'ss';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

// Filter status lewat HAVING
$sql .= " HAVING 1=1";
if ($status !== '') {
    $sql    .= " AND ketersediaan_aktual = ?";
    $types  .= 's';
    $params[] = $status;
}

$sql .= " ORDER BY f.nama_fasilitas ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Terjadi kesalahan query: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =======================================
   TEMPLATE HEADER & NAVBAR PEMINJAM
   =======================================*/
include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>
<br><br>
<style>
    /* ======== HERO SECTION ======== */
    .hero-section {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        padding: 60px 0;
        color: white;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 50%;
        height: 100%;
        background: url('../assets/img/gedung.jpg') center/cover no-repeat;
        opacity: 0.1;
    }

    .hero-section h2 {
        color: white !important;
        font-size: 2.5rem;
        font-weight: 800;
        text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
        position: relative;
        z-index: 2;
    }

    .hero-section .lead {
        color: rgba(255, 255, 255, 0.95) !important;
        font-size: 1.1rem;
        font-weight: 300;
        position: relative;
        z-index: 2;
    }

    /* ======== FILTER BAR ======== */
    .filter-bar {
        background: white;
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 5px 20px rgba(11, 44, 97, 0.08);
        margin-bottom: 30px;
    }

    .filter-bar .form-label {
        font-weight: 600;
        color: var(--dark-text);
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .filter-bar .form-select,
    .filter-bar .form-control {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 10px 15px;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }

    .filter-bar .form-select:focus,
    .filter-bar .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(11, 44, 97, 0.15);
    }

    .btn-main {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(11, 44, 97, 0.2);
    }

    .btn-main:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(11, 44, 97, 0.3);
        color: white;
    }

    .btn-outline-secondary {
        border: 2px solid var(--border-color);
        color: var(--muted-text);
        border-radius: 12px;
        padding: 10px 20px;
        transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
        background: var(--danger-color);
        border-color: var(--danger-color);
        color: white;
    }

    /* ======== FASILITAS CARD ======== */
    .fasilitas-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(11, 44, 97, 0.08);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        border: 2px solid transparent;
    }

    .fasilitas-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 15px 40px rgba(11, 44, 97, 0.15);
        border-color: var(--primary-color);
    }

    .fasilitas-card-img {
        position: relative;
        height: 220px;
        overflow: hidden;
    }

    .fasilitas-card-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }

    .fasilitas-card:hover .fasilitas-card-img img {
        transform: scale(1.1);
    }

    .fasilitas-card-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        background: linear-gradient(135deg, var(--accent-color), #ffd700);
        color: var(--primary-color);
        padding: 8px 16px;
        border-radius: 25px;
        font-weight: 700;
        font-size: 0.85rem;
        box-shadow: 0 4px 12px rgba(255, 183, 3, 0.4);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .badge-jenis {
        background: rgba(11, 44, 97, 0.1);
        color: var(--primary-color);
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .badge-status {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-block;
    }

    .badge-status.bg-success {
        background: linear-gradient(135deg, var(--success-color), #22c55e) !important;
        color: white;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
    }

    .badge-status.bg-danger {
        background: linear-gradient(135deg, var(--danger-color), #ef4444) !important;
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
    }

    .fasilitas-card .card-body {
        padding: 20px;
    }

    .fasilitas-card h5 {
        color: var(--primary-color);
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .fasilitas-card .text-muted {
        color: var(--muted-text) !important;
        line-height: 1.6;
    }

    .fasilitas-card .btn-main {
        border-radius: 12px;
        padding: 12px 20px;
        font-weight: 700;
        font-size: 0.95rem;
    }

    .fasilitas-card .btn-main.disabled {
        background: linear-gradient(135deg, #9ca3af, #6b7280);
        cursor: not-allowed;
        opacity: 0.7;
    }

    .fasilitas-card .btn-main.disabled:hover {
        transform: none;
        box-shadow: 0 4px 12px rgba(11, 44, 97, 0.2);
    }

    /* ======== EMPTY STATE ======== */
    .empty-state {
        padding: 80px 20px;
        text-align: center;
    }

    .empty-state i {
        color: var(--muted-text);
        opacity: 0.5;
    }

    .empty-state .lead {
        color: var(--dark-text);
        font-weight: 600;
    }

    /* ======== RESPONSIVE ======== */
    @media (max-width: 768px) {
        .hero-section h2 {
            font-size: 2rem;
        }

        .hero-section .lead {
            font-size: 1rem;
        }

        .filter-bar {
            padding: 20px;
        }

        .filter-bar .col-md-4 {
            margin-bottom: 15px;
        }

        .fasilitas-card-img {
            height: 200px;
        }
    }
</style>

<!-- HERO / TITLE SECTION -->
<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-3" data-aos="fade-up">Daftar Fasilitas Kampus</h2>
        <p class="lead mb-0" data-aos="fade-up" data-aos-delay="100">
            Pilih fasilitas kampus sesuai kebutuhan kegiatan akademik, organisasi, maupun acara resmi lainnya.
        </p>
    </div>
</section>

<div class="container my-4">

    <!-- FILTER BAR (SERVER SIDE) -->
    <div class="filter-bar" data-aos="fade-up">
        <form class="row g-3 align-items-end" method="get">
            <div class="col-md-4">
                <label for="filterKategori" class="form-label">
                    <i class="bi bi-funnel me-1"></i>Kategori Fasilitas
                </label>
                <select id="filterKategori" name="kategori" class="form-select">
                    <option value="">Semua Kategori</option>
                    <option value="ruangan"   <?= $kategori === 'ruangan'   ? 'selected' : ''; ?>>🏢 Ruangan</option>
                    <option value="kendaraan" <?= $kategori === 'kendaraan' ? 'selected' : ''; ?>>🚗 Kendaraan</option>
                    <option value="lapangan"  <?= $kategori === 'lapangan'  ? 'selected' : ''; ?>>⚽ Lapangan</option>
                    <option value="pendukung" <?= $kategori === 'pendukung' ? 'selected' : ''; ?>>🔧 Pendukung</option>
                </select>
            </div>

            <div class="col-md-4">
                <label for="filterStatus" class="form-label">
                    <i class="bi bi-check-circle me-1"></i>Status Ketersediaan
                </label>
                <select id="filterStatus" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="tersedia"        <?= $status === 'tersedia'        ? 'selected' : ''; ?>>✅ Tersedia</option>
                    <option value="tidak_tersedia"  <?= $status === 'tidak_tersedia'  ? 'selected' : ''; ?>>❌ Tidak Tersedia</option>
                </select>
            </div>

            <div class="col-md-4">
                <label for="searchNama" class="form-label">
                    <i class="bi bi-search me-1"></i>Cari Fasilitas
                </label>
                <div class="input-group">
                    <input type="text"
                           id="searchNama"
                           name="q"
                           class="form-control"
                           placeholder="Nama atau keterangan..."
                           value="<?= $search ?>">
                    <button class="btn btn-main" type="submit">
                        <i class="bi bi-search"></i> Cari
                    </button>
                    <?php if ($kategori !== '' || $status !== '' || $search !== ''): ?>
                        <a href="fasilitas.php" class="btn btn-outline-secondary" title="Reset Filter">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- INFO FILTER AKTIF -->
    <?php if ($kategori !== '' || $status !== '' || $search !== ''): ?>
        <div class="alert alert-info d-flex align-items-center mb-4" data-aos="fade-up">
            <i class="bi bi-info-circle me-2"></i>
            <div>
                Filter aktif: 
                <?php if ($kategori !== ''): ?>
                    <strong>Kategori: <?= htmlspecialchars(ucfirst($kategori), ENT_QUOTES, 'UTF-8') ?></strong>
                <?php endif; ?>
                <?php if ($status !== ''): ?>
                    <strong>Status: <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status)), ENT_QUOTES, 'UTF-8') ?></strong>
                <?php endif; ?>
                <?php if ($search !== ''): ?>
                    <strong>Pencarian: "<?= $search ?>"</strong>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- GRID FASILITAS -->
    <div class="row g-4">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($data = $result->fetch_assoc()):
                // Sanitasi semua data output
                $id_fasilitas = (int)$data['id_fasilitas'];
                $nama = htmlspecialchars($data['nama_fasilitas'], ENT_QUOTES, 'UTF-8');
                $keterangan = htmlspecialchars($data['keterangan'] ?? '', ENT_QUOTES, 'UTF-8');
                $lokasi = htmlspecialchars($data['lokasi'] ?? '-', ENT_QUOTES, 'UTF-8');
                $jenis = htmlspecialchars($data['jenis_fasilitas'] ?? '-', ENT_QUOTES, 'UTF-8');
                $kategoriRow = htmlspecialchars($data['kategori'] ?? '', ENT_QUOTES, 'UTF-8');
                
                // Potong keterangan
                $keteranganPendek = mb_strlen($keterangan) > 110
                    ? mb_substr($keterangan, 0, 110) . '...'
                    : $keterangan;

                // Status ketersediaan
                $statusAktual = strtolower($data['ketersediaan_aktual'] ?? 'tersedia');
                $statusLabel  = $statusAktual === 'tersedia' ? 'Tersedia' : 'Tidak Tersedia';
                $statusClass  = $statusAktual === 'tersedia' ? 'bg-success' : 'bg-danger';

                // Gambar fasilitas dengan validasi
                $gambar = $data['gambar'] ?? '';
                $gambarPath = "../uploads/fasilitas/" . $gambar;
                if (empty($gambar) || !file_exists($gambarPath)) {
                    $gambarPath = "../assets/img/no-image.jpg";
                }
                $gambarPath = htmlspecialchars($gambarPath, ENT_QUOTES, 'UTF-8');
            ?>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= ($id_fasilitas % 3) * 100 ?>">
                    <div class="fasilitas-card h-100">
                        <div class="fasilitas-card-img">
                            <img src="<?= $gambarPath ?>" alt="<?= $nama ?>">
                            <div class="fasilitas-card-badge">
                                <i class="bi bi-tag"></i>
                                <?= ucfirst($kategoriRow ?: 'Fasilitas') ?>
                            </div>
                        </div>

                        <div class="card-body d-flex flex-column">
                            <div class="mb-3 text-center">
                                <h5 class="mb-2"><?= $nama ?></h5>
                                <span class="badge-jenis">
                                    <i class="bi bi-building"></i>
                                    <?= $jenis ?>
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt-fill me-1"></i>
                                    <?= $lokasi ?>
                                </small>
                                <span class="badge-status <?= $statusClass ?>">
                                    <?= $statusLabel ?>
                                </span>
                            </div>

                            <p class="text-muted small mb-3 flex-grow-1">
                                <?= $keteranganPendek !== '' ? nl2br($keteranganPendek) : 'Belum ada keterangan fasilitas.' ?>
                            </p>

                            <a href="form_peminjaman.php?id=<?= $id_fasilitas ?>"
                               class="btn btn-main w-100 mt-auto <?= $statusAktual === 'tersedia' ? '' : 'disabled' ?>">
                                <i class="bi bi-<?= $statusAktual === 'tersedia' ? 'plus-circle' : 'x-circle' ?> me-2"></i>
                                <?= $statusAktual === 'tersedia' ? 'Ajukan Peminjaman' : 'Tidak Dapat Dipinjam' ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state" data-aos="fade-up">
                    <i class="bi bi-building display-1 d-block mb-4"></i>
                    <p class="lead mb-2">Tidak ada fasilitas yang cocok dengan filter</p>
                    <p class="text-muted mb-4">Coba ubah kategori, status, atau kata kunci pencarian Anda.</p>
                    <a href="fasilitas.php" class="btn btn-main">
                        <i class="bi bi-arrow-clockwise me-2"></i>Reset Filter
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
include '../includes/peminjam/footer.php';
?>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // Initialize AOS
    AOS.init({ 
        duration: 900, 
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
