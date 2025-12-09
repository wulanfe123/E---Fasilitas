<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/notifikasi_helper.php';

// Validasi ID user dari session dengan ketat
$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$nama_user = htmlspecialchars($_SESSION['nama'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8');

// Set page variables
$pageTitle = 'Peminjaman Saya';
$currentPage = 'peminjaman';

/* ==========================================================
   AMBIL DATA PEMINJAMAN AKTIF (USULAN + DITERIMA)
   PAKAI PREPARED STATEMENT
   ========================================================== */
$sql = "
    SELECT 
        p.id_pinjam,
        p.id_user,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.status,
        p.catatan,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas ORDER BY f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_dipinjam,
        COALESCE(GROUP_CONCAT(DISTINCT f.kategori ORDER BY f.kategori SEPARATOR ', '), '-') AS kategori_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.lokasi ORDER BY f.lokasi SEPARATOR ', '), '-') AS lokasi_list
    FROM peminjaman p
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    WHERE p.id_user = ?
      AND p.status IN ('usulan', 'diterima')
    GROUP BY p.id_pinjam, p.id_user, p.tanggal_mulai, p.tanggal_selesai, p.status, p.catatan
    ORDER BY p.id_pinjam DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();

/* ============================
   HEADER & NAVBAR PEMINJAM
   ============================ */
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

    .hero-section p {
        color: rgba(255, 255, 255, 0.95) !important;
        font-size: 1.1rem;
        font-weight: 300;
        position: relative;
        z-index: 2;
    }

    .hero-badge {
        background: rgba(255, 183, 3, 0.2);
        border: 2px solid rgba(255, 183, 3, 0.4);
        color: var(--accent-color);
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-block;
        margin-top: 10px;
        position: relative;
        z-index: 2;
    }

    /* ======== FILTER BAR ======== */
    .filter-bar {
        background: white;
        padding: 25px;
        border-radius: 20px;
        box-shadow: 0 5px 20px rgba(11, 44, 97, 0.08);
        margin-bottom: 25px;
    }

    .filter-title {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1.1rem;
        margin-bottom: 15px;
    }

    .filter-bar .form-label {
        font-weight: 600;
        color: var(--dark-text);
        font-size: 0.9rem;
    }

    .filter-bar .form-select,
    .filter-bar .form-control {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }

    .filter-bar .form-select:focus,
    .filter-bar .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(11, 44, 97, 0.15);
    }

    .filter-bar .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border: none;
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(11, 44, 97, 0.2);
    }

    .filter-bar .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(11, 44, 97, 0.3);
    }

    .filter-bar .btn-outline-secondary {
        border: 2px solid var(--border-color);
        color: var(--muted-text);
        border-radius: 12px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .filter-bar .btn-outline-secondary:hover {
        background: var(--danger-color);
        border-color: var(--danger-color);
        color: white;
    }

    /* ======== CARD TABLE ======== */
    .card-table {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(11, 44, 97, 0.1);
        border: none;
        overflow: hidden;
    }

    .table-wrapper {
        padding: 0;
    }

    .table-responsive {
        overflow-x: auto;
    }

    .custom-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .custom-table thead {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    }

    .custom-table thead th {
        color: white;
        font-weight: 700;
        font-size: 0.9rem;
        padding: 18px 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        white-space: nowrap;
    }

    .custom-table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid var(--border-color);
    }

    .custom-table tbody tr:hover {
        background: rgba(11, 44, 97, 0.03);
        transform: scale(1.005);
    }

    .custom-table tbody td {
        padding: 18px 15px;
        color: var(--dark-text);
        font-size: 0.9rem;
        vertical-align: middle;
    }

    /* ======== FASILITAS INFO ======== */
    .fasilitas-name {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1rem;
        margin-bottom: 5px;
    }

    .fasilitas-meta {
        font-size: 0.85rem;
        color: var(--muted-text);
    }

    .fasilitas-meta i {
        color: var(--accent-color);
    }

    /* ======== STATUS PILLS ======== */
    .status-pill {
        padding: 8px 18px;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-block;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-pill-usulan {
        background: linear-gradient(135deg, var(--warning-color), #fbbf24);
        color: white;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
    }

    .status-pill-diterima {
        background: linear-gradient(135deg, var(--success-color), #22c55e);
        color: white;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
    }

    .status-pill-ditolak {
        background: linear-gradient(135deg, var(--danger-color), #ef4444);
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
    }

    .status-pill-selesai {
        background: linear-gradient(135deg, #6b7280, #9ca3af);
        color: white;
        box-shadow: 0 2px 8px rgba(107, 114, 128, 0.3);
    }

    /* ======== ACTION BUTTONS ======== */
    .btn-detail-round {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border: none;
        border-radius: 10px;
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(11, 44, 97, 0.2);
        text-decoration: none;
    }

    .btn-detail-round:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(11, 44, 97, 0.3);
        color: white;
    }

    .btn-action-small {
        border: none;
        border-radius: 10px;
        width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        padding: 0;
    }

    .btn-action-small.btn-warning {
        background: linear-gradient(135deg, var(--warning-color), #fbbf24);
        box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2);
    }

    .btn-action-small.btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-action-small.btn-danger {
        background: linear-gradient(135deg, var(--danger-color), #ef4444);
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.2);
    }

    .btn-action-small.btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(220, 38, 38, 0.3);
    }

    .btn-action-small.btn-success {
        background: linear-gradient(135deg, var(--success-color), #22c55e);
        box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2);
    }

    .btn-action-small.btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(22, 163, 74, 0.3);
    }

    /* ======== ALERTS ======== */
    .alert {
        border-radius: 12px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 20px;
    }

    .alert-success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
        border-left: 4px solid var(--success-color);
    }

    /* ======== EMPTY STATE ======== */
    .empty-state {
        background: white;
        border-radius: 20px;
        padding: 60px 40px;
        box-shadow: 0 10px 40px rgba(11, 44, 97, 0.1);
    }

    .empty-state i {
        color: var(--muted-text);
        opacity: 0.5;
    }

    .empty-state h5 {
        color: var(--dark-text);
        font-weight: 700;
    }

    .empty-state .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border: none;
        border-radius: 12px;
        padding: 12px 32px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(11, 44, 97, 0.2);
    }

    .empty-state .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(11, 44, 97, 0.3);
    }

    /* ======== RESPONSIVE ======== */
    @media (max-width: 768px) {
        .hero-section h2 {
            font-size: 2rem;
        }

        .hero-section p {
            font-size: 1rem;
        }

        .hero-badge {
            font-size: 0.8rem;
            padding: 8px 16px;
        }

        .filter-bar {
            padding: 20px;
        }

        .custom-table {
            font-size: 0.85rem;
        }

        .custom-table thead th,
        .custom-table tbody td {
            padding: 12px 10px;
        }

        .fasilitas-name {
            font-size: 0.9rem;
        }

        .fasilitas-meta {
            font-size: 0.75rem;
        }

        .status-pill {
            font-size: 0.75rem;
            padding: 6px 14px;
        }

        .btn-detail-round,
        .btn-action-small {
            width: 32px;
            height: 32px;
            font-size: 0.85rem;
        }

        .empty-state {
            padding: 40px 20px;
        }
    }

    /* ======== SCROLLBAR STYLING ======== */
    .table-responsive::-webkit-scrollbar {
        height: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: var(--light-bg);
        border-radius: 10px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border-radius: 10px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: var(--primary-color);
    }
</style>

<!-- HERO -->
<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-3" data-aos="fade-up">Peminjaman Saya</h2>
        <p class="mb-2" data-aos="fade-up" data-aos-delay="100">
            Pantau pengajuan peminjaman fasilitas yang masih berlangsung dan menunggu proses.
        </p>
        <span class="hero-badge" data-aos="fade-up" data-aos-delay="200">
            <i class="bi bi-lightning-charge-fill me-1"></i>
            Tip: Ajukan peminjaman lebih awal untuk menghindari bentrok jadwal
        </span>
    </div>
</section>

<div class="container mb-5">

    <!-- FLASH MESSAGE -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="alert alert-success" data-aos="fade-down">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($result && $result->num_rows > 0): ?>

        <!-- FILTER BAR -->
        <div class="row justify-content-center mb-3">
            <div class="col-lg-11 col-xl-10" data-aos="fade-up">
                <div class="filter-bar">
                    <div class="filter-title">
                        <i class="bi bi-funnel me-2"></i>Filter & Pencarian
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="filterStatus" class="form-label">
                                <i class="bi bi-check-circle me-1"></i>Status Peminjaman
                            </label>
                            <select id="filterStatus" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="usulan">⏳ Usulan</option>
                                <option value="diterima">✅ Diterima</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="searchFasilitas" class="form-label">
                                <i class="bi bi-search me-1"></i>Cari Fasilitas
                            </label>
                            <div class="input-group">
                                <input type="text" id="searchFasilitas"
                                       class="form-control"
                                       placeholder="Ketik nama fasilitas yang ingin dicari...">
                                <button class="btn btn-primary" type="button" id="btnSearch">
                                    <i class="bi bi-search"></i> Cari
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="btnReset">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABEL PEMINJAMAN -->
        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10" data-aos="fade-up" data-aos-delay="100">
                <div class="card card-table">
                    <div class="card-body p-0">
                        <div class="table-wrapper">
                            <div class="table-responsive">
                                <table class="custom-table" id="tablePeminjaman">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Fasilitas</th>
                                            <th>Tanggal Pinjam</th>
                                            <th>Tanggal Kembali</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        while ($data = $result->fetch_assoc()):
                                            // Sanitasi semua output
                                            $id_pinjam = (int)$data['id_pinjam'];
                                            $status = strtolower(htmlspecialchars($data['status'] ?? '', ENT_QUOTES, 'UTF-8'));
                                            $fasilitas = htmlspecialchars($data['fasilitas_dipinjam'] ?? '-', ENT_QUOTES, 'UTF-8');
                                            $kategori = htmlspecialchars($data['kategori_list'] ?? '-', ENT_QUOTES, 'UTF-8');
                                            $lokasi = htmlspecialchars($data['lokasi_list'] ?? '-', ENT_QUOTES, 'UTF-8');

                                            // Status pill class
                                            $pillClass = 'status-pill-selesai';
                                            if ($status === 'usulan') {
                                                $pillClass = 'status-pill-usulan';
                                            } elseif ($status === 'diterima') {
                                                $pillClass = 'status-pill-diterima';
                                            } elseif ($status === 'ditolak') {
                                                $pillClass = 'status-pill-ditolak';
                                            }

                                            // Format tanggal
                                            $tglMulai = !empty($data['tanggal_mulai']) 
                                                ? date('d M Y', strtotime($data['tanggal_mulai'])) 
                                                : '-';
                                            $tglSelesai = !empty($data['tanggal_selesai']) 
                                                ? date('d M Y', strtotime($data['tanggal_selesai'])) 
                                                : '-';
                                        ?>
                                            <tr class="row-peminjaman"
                                                data-status="<?= $status ?>"
                                                data-fasilitas="<?= strtolower($fasilitas) ?>">
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td>
                                                    <div class="fasilitas-name"><?= $fasilitas ?></div>
                                                    <div class="fasilitas-meta">
                                                        <i class="bi bi-tags"></i> <?= $kategori ?> &nbsp;|&nbsp;
                                                        <i class="bi bi-geo-alt"></i> <?= $lokasi ?>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?= $tglMulai ?></td>
                                                <td class="text-center"><?= $tglSelesai ?></td>
                                                <td class="text-center">
                                                    <span class="status-pill <?= $pillClass ?>">
                                                        <?= ucfirst($status) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-inline-flex gap-2">
                                                        <!-- Detail -->
                                                        <a href="detail_peminjaman.php?id=<?= $id_pinjam ?>"
                                                           class="btn-detail-round" title="Detail Peminjaman">
                                                            <i class="bi bi-info-circle"></i>
                                                        </a>

                                                        <!-- Aksi untuk USULAN -->
                                                        <?php if ($status === 'usulan'): ?>
                                                            <a href="edit_peminjaman.php?id=<?= $id_pinjam ?>"
                                                               class="btn btn-warning btn-action-small text-white" 
                                                               title="Edit Peminjaman">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </a>
                                                            <a href="batalkan_peminjaman.php?id=<?= $id_pinjam ?>"
                                                               class="btn btn-danger btn-action-small" 
                                                               title="Batalkan Peminjaman"
                                                               onclick="return confirm('⚠️ Yakin ingin membatalkan pengajuan peminjaman ini?');">
                                                                <i class="bi bi-x-circle"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <!-- Aksi untuk DITERIMA (Konfirmasi Pengembalian) -->
                                                        <?php if ($status === 'diterima'): ?>
                                                            <a href="pengembalian_peminjaman.php?id=<?= $id_pinjam ?>"
                                                               class="btn btn-success btn-action-small" 
                                                               title="Konfirmasi Pengembalian"
                                                               onclick="return confirm('✅ Konfirmasi bahwa fasilitas sudah dikembalikan? Data akan dipindah ke Riwayat.');">
                                                                <i class="bi bi-box-arrow-in-left"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>

                                        <tr id="rowEmptyFilter" style="display:none;">
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="bi bi-search display-6 d-block mb-2"></i>
                                                Tidak ada data peminjaman yang sesuai dengan filter/pencarian.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div><!-- .table-responsive -->
                        </div><!-- .table-wrapper -->
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- EMPTY STATE -->
        <div class="empty-state text-center" data-aos="fade-up">
            <i class="bi bi-inbox display-1 d-block mb-4"></i>
            <h5 class="fw-semibold mb-3">Belum Ada Peminjaman Aktif</h5>
            <p class="text-muted mb-4">
                Kamu belum memiliki peminjaman yang sedang berjalan.<br>
                Mulai ajukan sekarang untuk kebutuhan kegiatanmu!
            </p>
            <a href="fasilitas.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>Ajukan Peminjaman Baru
            </a>
        </div>

    <?php endif; ?>
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

    // Filter & Search functionality dengan validasi
    const filterStatus = document.getElementById('filterStatus');
    const searchFasilitas = document.getElementById('searchFasilitas');
    const rows = document.querySelectorAll('.row-peminjaman');
    const rowEmpty = document.getElementById('rowEmptyFilter');
    const btnSearch = document.getElementById('btnSearch');
    const btnReset = document.getElementById('btnReset');

    function sanitizeInput(input) {
        // Sanitasi input untuk mencegah XSS
        const temp = document.createElement('div');
        temp.textContent = input;
        return temp.innerHTML;
    }

    function applyFilter() {
        const st = filterStatus ? sanitizeInput(filterStatus.value.toLowerCase().trim()) : '';
        const q = searchFasilitas ? sanitizeInput(searchFasilitas.value.toLowerCase().trim()) : '';

        // Validasi panjang input pencarian
        if (q.length > 100) {
            alert('⚠️ Pencarian terlalu panjang! Maksimal 100 karakter.');
            return;
        }

        let visibleCount = 0;

        rows.forEach(row => {
            const rowStatus = (row.dataset.status || '').toLowerCase();
            const rowFasilitas = (row.dataset.fasilitas || '').toLowerCase();

            let show = true;

            // Filter berdasarkan status
            if (st && rowStatus !== st) {
                show = false;
            }

            // Filter berdasarkan pencarian fasilitas
            if (q && !rowFasilitas.includes(q)) {
                show = false;
            }

            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        // Tampilkan pesan jika tidak ada hasil
        if (rowEmpty) {
            rowEmpty.style.display = visibleCount === 0 ? '' : 'none';
        }

        console.log(`Filter applied: ${visibleCount} rows visible`);
    }

    if (btnSearch) {
        btnSearch.addEventListener('click', applyFilter);
    }

    if (btnReset) {
        btnReset.addEventListener('click', () => {
            if (filterStatus) filterStatus.value = '';
            if (searchFasilitas) searchFasilitas.value = '';
            applyFilter();
        });
    }

    if (searchFasilitas) {
        searchFasilitas.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                applyFilter();
            }
        });

        // Real-time validation untuk panjang input
        searchFasilitas.addEventListener('input', function() {
            if (this.value.length > 100) {
                this.value = this.value.substring(0, 100);
                alert('⚠️ Pencarian dibatasi maksimal 100 karakter!');
            }
        });
    }

    // Auto-apply filter saat halaman dimuat jika ada parameter
    document.addEventListener('DOMContentLoaded', function() {
        applyFilter();
    });
</script>
