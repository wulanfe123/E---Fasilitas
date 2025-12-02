<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$id_user   = (int) $_SESSION['id_user'];
$nama_user = $_SESSION['nama_user'] ?? 'Peminjam';

/* =========================================================
   VALIDASI INPUT FILTER (GET)
   ========================================================= */
$allowedKategori = ['ruangan','kendaraan','lapangan','pendukung'];
$allowedStatus   = ['tersedia','tidak_tersedia'];

$kategori = isset($_GET['kategori']) ? strtolower(trim($_GET['kategori'])) : '';
if (!in_array($kategori, $allowedKategori, true)) {
    $kategori = '';
}

$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
// batasi panjang search supaya aman
if (mb_strlen($search) > 100) {
    $search = mb_substr($search, 0, 100);
}

/* =========================================================
   QUERY FASILITAS + KETERSEDIAAN AKTUAL (PREPARED)
   ========================================================= */
/*
   ketersediaan_aktual:
   - 'tidak_tersedia' jika ADA peminjaman status 'diterima'
     dan hari ini antara tanggal_mulai & tanggal_selesai
   - selain itu 'tersedia'
*/
/* =========================================================
   QUERY FASILITAS + KETERSEDIAAN AKTUAL (PREPARED)
   ========================================================= */
/*
   ketersediaan_aktual:
   - 'tidak_tersedia' jika:
       ADA peminjaman status 'diterima'
       DAN (hari ini antara tanggal_mulai & tanggal_selesai
            ATAU fasilitas pernah dikembalikan dengan kondisi 'rusak')
   - selain itu 'tersedia'
*/

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

// filter kategori
if ($kategori !== '') {
    $sql    .= " AND LOWER(f.kategori) = ?";
    $types  .= 's';
    $params[] = $kategori;
}

// filter search (nama / keterangan)
if ($search !== '') {
    $sql    .= " AND (f.nama_fasilitas LIKE ? OR f.keterangan LIKE ?)";
    $types  .= 'ss';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

// filter status lewat HAVING (pakai alias ketersediaan_aktual)
$sql .= " HAVING 1=1";
if ($status !== '') {
    $sql    .= " AND ketersediaan_aktual = ?";
    $types  .= 's';
    $params[] = $status;
}

$sql .= " ORDER BY f.id_fasilitas ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Terjadi kesalahan query: " . htmlspecialchars($conn->error));
}

if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

/* =========================================================
   TEMPLATE HEADER & NAVBAR PEMINJAM
   ========================================================= */
include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>

<!-- HERO / TITLE SECTION -->
<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-2 text-primary">Daftar Fasilitas Kampus</h2>
        <p class="lead text-muted mb-0">
            Pilih fasilitas kampus sesuai kebutuhan kegiatan akademik, organisasi, maupun acara resmi lainnya.
        </p>
    </div>
</section>

<div class="container my-4 flex-grow-1">

    <!-- FILTER BAR (SERVER SIDE) -->
    <div class="filter-bar mb-4">
        <form class="row g-3 align-items-end" method="get">
            <div class="col-md-4">
                <label for="filterKategori" class="form-label">Kategori</label>
                <select id="filterKategori" name="kategori" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    <option value="ruangan"   <?= $kategori === 'ruangan'   ? 'selected' : ''; ?>>Ruangan</option>
                    <option value="kendaraan" <?= $kategori === 'kendaraan' ? 'selected' : ''; ?>>Kendaraan</option>
                    <option value="lapangan"  <?= $kategori === 'lapangan'  ? 'selected' : ''; ?>>Lapangan</option>
                    <option value="pendukung" <?= $kategori === 'pendukung' ? 'selected' : ''; ?>>Pendukung</option>
                </select>
            </div>

            <div class="col-md-4">
                <label for="filterStatus" class="form-label">Status Ketersediaan</label>
                <select id="filterStatus" name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="tersedia"        <?= $status === 'tersedia'        ? 'selected' : ''; ?>>Tersedia</option>
                    <option value="tidak_tersedia"  <?= $status === 'tidak_tersedia'  ? 'selected' : ''; ?>>Tidak Tersedia</option>
                </select>
            </div>

            <div class="col-md-4">
                <label for="searchNama" class="form-label">Cari Fasilitas</label>
                <div class="input-group input-group-sm">
                    <input type="text"
                           id="searchNama"
                           name="q"
                           class="form-control"
                           placeholder="Cari berdasarkan nama / keterangan..."
                           value="<?= htmlspecialchars($search); ?>">
                    <button class="btn btn-main" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if ($kategori !== '' || $status !== '' || $search !== ''): ?>
                        <a href="fasilitas.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- GRID FASILITAS -->
    <div class="row g-4 justify-content-center">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($data = $result->fetch_assoc()):

                $keterangan        = $data['keterangan'] ?? '';
                $keteranganPendek  = mb_strlen($keterangan) > 110
                    ? mb_substr($keterangan, 0, 110) . '...'
                    : $keterangan;

                $statusAktual  = strtolower($data['ketersediaan_aktual'] ?? 'tersedia');
                $statusLabel   = $statusAktual === 'tersedia' ? 'Tersedia' : 'Tidak Tersedia';
                $statusClass   = $statusAktual === 'tersedia' ? 'bg-success' : 'bg-danger';

                $kategoriRow   = strtolower($data['kategori'] ?? '');
                $jenis         = $data['jenis_fasilitas'] ?? '-';

                // Gambar fasilitas
                $gambarPath = "../uploads/fasilitas/" . ($data['gambar'] ?? '');
                if (empty($data['gambar']) || !file_exists($gambarPath)) {
                    $gambarPath = "../assets/img/no-image.jpg"; // fallback
                }
            ?>
                <div class="col-lg-4 col-md-6 col-sm-12" data-aos="fade-up">
                    <div class="fasilitas-card h-100">
                        <div class="fasilitas-card-img">
                            <img src="<?= htmlspecialchars($gambarPath); ?>"
                                 alt="<?= htmlspecialchars($data['nama_fasilitas']); ?>">
                            <div class="fasilitas-card-badge">
                                <i class="bi bi-tag"></i>
                                <?= htmlspecialchars(ucfirst($kategoriRow ?: 'Fasilitas')); ?>
                            </div>
                        </div>

                        <div class="card-body d-flex flex-column p-3">
                            <div class="mb-2 text-center">
                                <h5 class="fw-semibold mb-1">
                                    <?= htmlspecialchars($data['nama_fasilitas']); ?>
                                </h5>
                                <span class="badge-jenis">
                                    <i class="bi bi-building me-1"></i>
                                    <?= htmlspecialchars($jenis); ?>
                                </span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted">
                                    <i class="bi bi-geo-alt-fill me-1"></i>
                                    <?= htmlspecialchars($data['lokasi'] ?? '-'); ?>
                                </small>
                                <span class="badge badge-status <?= $statusClass; ?>">
                                    <?= $statusLabel; ?>
                                </span>
                            </div>

                            <p class="text-muted small mb-3 flex-grow-1">
                                <?= nl2br(htmlspecialchars($keteranganPendek !== '' ? $keteranganPendek : 'Belum ada keterangan fasilitas.')); ?>
                            </p>

                            <a href="form_peminjaman.php?id=<?= (int)$data['id_fasilitas']; ?>"
                               class="btn btn-main w-100 mt-auto <?= $statusAktual === 'tersedia' ? '' : 'disabled'; ?>">
                                <i class="bi bi-plus-circle me-1"></i>
                                <?= $statusAktual === 'tersedia' ? 'Ajukan Peminjaman' : 'Tidak Dapat Dipinjam'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-building display-4 d-block mb-3"></i>
                <p class="lead mb-1">Tidak ada fasilitas yang cocok dengan filter.</p>
                <p class="text-muted mb-0">Coba ubah kategori, status, atau kata kunci pencarian.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/peminjam/footer.php'; ?>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({ duration: 900, once: true });

    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (!navbar) return;
        navbar.classList.toggle('scrolled', window.scrollY > 50);
    });
</script>
