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
$pageTitle = 'Riwayat Peminjaman';
$currentPage = 'riwayat';

/* ============================
   1) Hitung notifikasi belum dibaca (PREPARED)
   ============================ */
$jumlah_notif = 0;
$sqlNotif = "SELECT COUNT(*) AS jml 
             FROM notifikasi 
             WHERE id_user = ? AND is_read = 0";
$stmtNotif = $conn->prepare($sqlNotif);
if ($stmtNotif) {
    $stmtNotif->bind_param("i", $id_user);
    $stmtNotif->execute();
    $resNotif = $stmtNotif->get_result();
    if ($rowN = $resNotif->fetch_assoc()) {
        $jumlah_notif = (int)($rowN['jml'] ?? 0);
    }
    $stmtNotif->close();
}

/* ============================
   2) Ambil riwayat peminjaman (PREPARED)
      - status 'selesai'  => sudah ada pengembalian
      - status 'ditolak'  => pengajuan ditolak
      Sekaligus ambil pengembalian & tindak lanjut TERAKHIR jika ada
   ============================ */
$sqlRiwayat = "
    SELECT 
        p.id_pinjam,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.status AS status_pinjam,
        p.catatan,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_list,
        
        pg.id_kembali,
        pg.kondisi,
        pg.catatan AS catatan_kembali,
        pg.tgl_kembali,
        
        tl_last.id_tindaklanjut,
        tl_last.tindakan,
        tl_last.deskripsi AS deskripsi_tindaklanjut,
        tl_last.status AS status_tindaklanjut,
        tl_last.tanggal AS tanggal_tindaklanjut
    FROM peminjaman p
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    LEFT JOIN pengembalian pg ON p.id_pinjam = pg.id_pinjam
    LEFT JOIN (
        SELECT t1.*
        FROM tindaklanjut t1
        JOIN (
            SELECT id_kembali, MAX(id_tindaklanjut) AS max_tl
            FROM tindaklanjut
            GROUP BY id_kembali
        ) t2 ON t1.id_kembali = t2.id_kembali
             AND t1.id_tindaklanjut = t2.max_tl
    ) AS tl_last ON tl_last.id_kembali = pg.id_kembali
    WHERE p.id_user = ?
      AND p.status IN ('selesai', 'ditolak')
    GROUP BY 
        p.id_pinjam,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.status,
        p.catatan,
        pg.id_kembali,
        pg.kondisi,
        pg.catatan,
        pg.tgl_kembali,
        tl_last.id_tindaklanjut,
        tl_last.tindakan,
        tl_last.deskripsi,
        tl_last.status,
        tl_last.tanggal
    ORDER BY p.id_pinjam DESC
";

$stmtRiwayat = $conn->prepare($sqlRiwayat);
if (!$stmtRiwayat) {
    die("Query riwayat error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
$stmtRiwayat->bind_param("i", $id_user);
$stmtRiwayat->execute();
$resultRiwayat = $stmtRiwayat->get_result();

/* ============================
   3) LOAD HEADER & NAVBAR PEMINJAM
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

    /* ======== RIWAYAT CARD ======== */
    .riwayat-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(11, 44, 97, 0.1);
        overflow: hidden;
        margin-top: 20px;
    }

    .riwayat-table-wrapper {
        padding: 0;
    }

    .riwayat-table-responsive {
        overflow-x: auto;
    }

    .riwayat-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .riwayat-table thead {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    }

    .riwayat-table thead th {
        color: white;
        font-weight: 700;
        font-size: 0.9rem;
        padding: 18px 15px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border: none;
        white-space: nowrap;
    }

    .riwayat-table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid var(--border-color);
    }

    .riwayat-table tbody tr:hover {
        background: rgba(11, 44, 97, 0.03);
        transform: scale(1.005);
    }

    .riwayat-table tbody td {
        padding: 18px 15px;
        color: var(--dark-text);
        font-size: 0.9rem;
        vertical-align: middle;
    }

    .riwayat-table tbody td.fw-semibold {
        color: var(--primary-color);
        font-weight: 600;
    }

    /* ======== STATUS PILLS ======== */
    .status-pill {
        padding: 8px 18px;
        border-radius: 25px;
        font-size: 0.85rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-pill.status-selesai {
        background: linear-gradient(135deg, var(--success-color), #22c55e);
        color: white;
        box-shadow: 0 2px 8px rgba(22, 163, 74, 0.3);
    }

    .status-pill.status-ditolak {
        background: linear-gradient(135deg, var(--danger-color), #ef4444);
        color: white;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
    }

    .status-pill.status-proses-tindaklanjut {
        background: linear-gradient(135deg, var(--warning-color), #fbbf24);
        color: white;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
        animation: pulse-warning 2s infinite;
    }

    @keyframes pulse-warning {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.8;
        }
    }

    /* ======== BADGE KONDISI & TINDAK LANJUT ======== */
    .badge-kondisi,
    .badge-tl {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin: 2px;
    }

    .badge-kondisi.bg-danger {
        background: linear-gradient(135deg, var(--danger-color), #ef4444) !important;
        box-shadow: 0 2px 6px rgba(220, 38, 38, 0.3);
    }

    .badge-kondisi.bg-success {
        background: linear-gradient(135deg, var(--success-color), #22c55e) !important;
        box-shadow: 0 2px 6px rgba(22, 163, 74, 0.3);
    }

    .badge-kondisi.bg-secondary {
        background: linear-gradient(135deg, #6b7280, #9ca3af) !important;
        box-shadow: 0 2px 6px rgba(107, 114, 128, 0.3);
    }

    .badge-tl.bg-warning {
        background: linear-gradient(135deg, var(--warning-color), #fbbf24) !important;
        box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);
    }

    .badge-tl.bg-success {
        background: linear-gradient(135deg, var(--success-color), #22c55e) !important;
        box-shadow: 0 2px 6px rgba(22, 163, 74, 0.3);
    }

    .badge-tl.bg-info {
        background: linear-gradient(135deg, #3b82f6, #60a5fa) !important;
        box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
    }

    .badge-tl.bg-secondary {
        background: linear-gradient(135deg, #6b7280, #9ca3af) !important;
        box-shadow: 0 2px 6px rgba(107, 114, 128, 0.3);
    }

    /* ======== BUTTONS ======== */
    .btn-detail {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border: none;
        border-radius: 10px;
        padding: 8px 18px;
        font-size: 0.85rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(11, 44, 97, 0.2);
    }

    .btn-detail:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(11, 44, 97, 0.3);
        color: white;
    }

    .btn-outline-danger {
        border: 2px solid var(--danger-color) !important;
        color: var(--danger-color) !important;
        background: white;
        border-radius: 10px;
        padding: 8px 16px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-outline-danger:hover {
        background: var(--danger-color) !important;
        color: white !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3);
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

        .riwayat-table {
            font-size: 0.85rem;
        }

        .riwayat-table thead th,
        .riwayat-table tbody td {
            padding: 12px 10px;
        }

        .status-pill {
            font-size: 0.75rem;
            padding: 6px 14px;
        }

        .badge-kondisi,
        .badge-tl {
            font-size: 0.7rem;
            padding: 5px 12px;
        }

        .btn-detail,
        .btn-outline-danger {
            font-size: 0.75rem;
            padding: 6px 12px;
        }

        .empty-state {
            padding: 40px 20px;
        }
    }

    /* ======== SCROLLBAR STYLING ======== */
    .riwayat-table-responsive::-webkit-scrollbar {
        height: 8px;
    }

    .riwayat-table-responsive::-webkit-scrollbar-track {
        background: var(--light-bg);
        border-radius: 10px;
    }

    .riwayat-table-responsive::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border-radius: 10px;
    }

    .riwayat-table-responsive::-webkit-scrollbar-thumb:hover {
        background: var(--primary-color);
    }
</style>

<!-- ========================= HERO SECTION ========================= -->
<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-3" data-aos="fade-up">Riwayat Peminjaman</h2>
        <p class="mb-2" data-aos="fade-up" data-aos-delay="100">
            Daftar peminjaman yang sudah <strong>selesai</strong>, <strong>ditolak</strong>, atau dalam <strong>proses tindak lanjut</strong>
        </p>
        <span class="hero-badge" data-aos="fade-up" data-aos-delay="200">
            <i class="bi bi-info-circle-fill me-1"></i>
            Riwayat juga menampilkan kondisi pengembalian & tindak lanjut kerusakan
        </span>
    </div>
</section>

<div class="container mb-5">

    <?php if ($resultRiwayat && $resultRiwayat->num_rows > 0): ?>
        <div class="row justify-content-center">
            <div class="col-12" data-aos="fade-up">
                <div class="riwayat-card">
                    <div class="riwayat-table-wrapper">
                        <div class="riwayat-table-responsive">
                            <table class="riwayat-table text-center align-middle">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Fasilitas</th>
                                        <th>Tanggal Pinjam</th>
                                        <th>Tanggal Kembali</th>
                                        <th>Status</th>
                                        <th>Kondisi & Tindak Lanjut</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    while ($data = $resultRiwayat->fetch_assoc()):
                                        // Sanitasi semua output
                                        $id_pinjam = (int)$data['id_pinjam'];
                                        $statusPinjam = strtolower(htmlspecialchars($data['status_pinjam'], ENT_QUOTES, 'UTF-8'));
                                        $fasilitasList = htmlspecialchars($data['fasilitas_list'] ?? '-', ENT_QUOTES, 'UTF-8');
                                        
                                        // Format tanggal
                                        $tglMulai = !empty($data['tanggal_mulai']) 
                                            ? date('d M Y', strtotime($data['tanggal_mulai'])) 
                                            : '-';
                                        $tglKembali = !empty($data['tgl_kembali'])
                                            ? date('d M Y', strtotime($data['tgl_kembali']))
                                            : '-';

                                        // Kondisi pengembalian
                                        $kondisi = strtolower($data['kondisi'] ?? '');
                                        
                                        // Tindak lanjut terakhir
                                        $id_tindaklanjut = !empty($data['id_tindaklanjut']) ? (int)$data['id_tindaklanjut'] : 0;
                                        $statusTLRaw = strtolower($data['status_tindaklanjut'] ?? '');

                                        /* ============================================
                                           LOGIKA BARU: Tentukan status display
                                           - Jika ada kerusakan DAN tindak lanjut masih proses => "Proses Tindak Lanjut"
                                           - Jika tidak ada kerusakan atau tindak lanjut selesai => Status asli
                                           ============================================ */
                                        $displayStatus = $statusPinjam;
                                        $statusClass = '';
                                        $statusIcon = '';
                                        $statusText = '';

                                        // Cek apakah ada kerusakan dan tindak lanjut masih proses
                                        $isTindakLanjutAktif = false;
                                        if ($kondisi === 'rusak' && $id_tindaklanjut > 0) {
                                            // Jika status tindak lanjut adalah proses/pending/belum selesai
                                            if (in_array($statusTLRaw, ['proses', 'pending', 'menunggu', ''])) {
                                                $displayStatus = 'proses_tindaklanjut';
                                                $isTindakLanjutAktif = true;
                                            }
                                        }

                                        // Set status class, icon, dan text berdasarkan display status
                                        switch ($displayStatus) {
                                            case 'proses_tindaklanjut':
                                                $statusClass = 'status-proses-tindaklanjut';
                                                $statusIcon = 'tools';
                                                $statusText = 'Proses Tindak Lanjut';
                                                break;
                                            case 'selesai':
                                                $statusClass = 'status-selesai';
                                                $statusIcon = 'check-circle';
                                                $statusText = 'Selesai';
                                                break;
                                            case 'ditolak':
                                                $statusClass = 'status-ditolak';
                                                $statusIcon = 'x-circle';
                                                $statusText = 'Ditolak';
                                                break;
                                            default:
                                                $statusClass = 'status-selesai';
                                                $statusIcon = 'check-circle';
                                                $statusText = ucfirst($displayStatus);
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td class="fw-semibold"><?= $fasilitasList ?></td>
                                        <td><?= $tglMulai ?></td>
                                        <td><?= $tglKembali ?></td>
                                        <td>
                                            <span class="status-pill <?= $statusClass ?>">
                                                <i class="bi bi-<?= $statusIcon ?>"></i>
                                                <?= $statusText ?>
                                            </span>
                                            <?php if ($isTindakLanjutAktif): ?>
                                                <small class="d-block mt-1 text-muted" style="font-size: 0.75rem;">
                                                    <i class="bi bi-exclamation-circle"></i>
                                                    Menunggu perbaikan
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Kondisi pengembalian -->
                                            <?php if ($kondisi === 'rusak'): ?>
                                                <span class="badge-kondisi bg-danger text-white mb-1 d-inline-block">
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                    Kembali: Rusak
                                                </span><br>
                                            <?php elseif ($kondisi === 'baik'): ?>
                                                <span class="badge-kondisi bg-success text-white mb-1 d-inline-block">
                                                    <i class="bi bi-check-circle"></i>
                                                    Kembali: Baik
                                                </span><br>
                                            <?php elseif (!empty($data['id_kembali'])): ?>
                                                <span class="badge-kondisi bg-secondary text-white mb-1 d-inline-block">
                                                    <i class="bi bi-dash-circle"></i>
                                                    Kembali: Lainnya
                                                </span><br>
                                            <?php else: ?>
                                                <span class="badge-kondisi bg-secondary text-white mb-1 d-inline-block">
                                                    <i class="bi bi-hourglass"></i>
                                                    Belum dinilai
                                                </span><br>
                                            <?php endif; ?>

                                            <!-- Status Tindak Lanjut TERAKHIR -->
                                            <?php if ($id_tindaklanjut > 0): ?>
                                                <?php 
                                                    if (in_array($statusTLRaw, ['proses', 'pending', 'menunggu', ''])) {
                                                        $tlClass = 'bg-warning text-dark';
                                                        $tlIcon = 'hourglass-split';
                                                        $tlText  = 'Tindak lanjut: Proses';
                                                    } elseif ($statusTLRaw === 'selesai') {
                                                        $tlClass = 'bg-success text-white';
                                                        $tlIcon = 'check-circle';
                                                        $tlText  = 'Tindak lanjut: Selesai';
                                                    } else {
                                                        $tlClass = 'bg-info text-white';
                                                        $tlIcon = 'info-circle';
                                                        $tlText  = 'Tindak lanjut: ' . ucfirst(htmlspecialchars($statusTLRaw, ENT_QUOTES, 'UTF-8'));
                                                    }
                                                ?>
                                                <span class="badge-tl <?= $tlClass ?> d-inline-block">
                                                    <i class="bi bi-<?= $tlIcon ?>"></i>
                                                    <?= $tlText ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-tl bg-secondary text-white d-inline-block">
                                                    <i class="bi bi-dash-circle"></i>
                                                    Tidak ada tindak lanjut
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column gap-2 align-items-center">
                                                <!-- Detail peminjaman -->
                                                <a href="detail_peminjaman.php?id=<?= $id_pinjam ?>" 
                                                   class="btn-detail" 
                                                   title="Lihat detail peminjaman">
                                                    <i class="bi bi-info-circle"></i> Detail
                                                </a>
                                                
                                                <!-- Komunikasi Kerusakan (CHAT) - Tampil jika ada kerusakan atau tindak lanjut -->
                                                <?php if ($kondisi === 'rusak' || $id_tindaklanjut > 0): ?>
                                                    <a href="komunikasi_tindaklanjut.php?id_pinjam=<?= $id_pinjam ?>&id_tl=<?= $id_tindaklanjut ?>"
                                                       class="btn btn-outline-danger btn-sm"
                                                       title="Komunikasi Kerusakan dengan Admin">
                                                        <i class="bi bi-chat-dots"></i>
                                                        Komunikasi
                                                    </a>
                                                    <?php if ($isTindakLanjutAktif): ?>
                                                        <small class="text-warning" style="font-size: 0.7rem;">
                                                            <i class="bi bi-bell-fill"></i>
                                                            Sedang diproses
                                                        </small>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div><!-- .riwayat-table-responsive -->
                    </div><!-- .riwayat-table-wrapper -->
                </div><!-- .riwayat-card -->
            </div>
        </div>

    <?php else: ?>
        <div class="empty-state text-center" data-aos="fade-up">
            <i class="bi bi-clock-history display-1 d-block mb-4"></i>
            <h5 class="fw-semibold mb-3">Belum Ada Riwayat Peminjaman</h5>
            <p class="text-muted mb-4">
                Riwayat akan muncul setelah peminjaman Anda selesai diproses atau ditolak oleh admin.
            </p>
            <a href="fasilitas.php" class="btn btn-primary">
                <i class="bi bi-building me-2"></i>Ajukan Peminjaman Baru
            </a>
        </div>
    <?php endif; ?>

</div>

<?php 
$stmtRiwayat->close();
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

    // Table row click highlight
    document.addEventListener('DOMContentLoaded', function() {
        const tableRows = document.querySelectorAll('.riwayat-table tbody tr');
        
        tableRows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Don't trigger if clicking on a button/link
                if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON' && !e.target.closest('a')) {
                    this.style.backgroundColor = 'rgba(11, 44, 97, 0.05)';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 500);
                }
            });
        });
    });
</script>
