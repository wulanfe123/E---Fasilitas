<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
// Validasi ID user dari session dengan ketat
$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$nama_user = htmlspecialchars($_SESSION['nama'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8');

// Set page variables
$pageTitle = 'Detail Peminjaman';
$currentPage = 'peminjaman';

// Validasi ID peminjaman dari URL
$id_pinjam = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($id_pinjam === false || $id_pinjam <= 0) {
    $_SESSION['error'] = "ID peminjaman tidak valid.";
    header("Location: riwayat.php");
    exit;
}

/* ==========================================================
   AMBIL DETAIL PEMINJAMAN + FASILITAS + PENGEMBALIAN + TINDAK LANJUT
   (PREPARED STATEMENT) - PERBAIKAN QUERY
   ========================================================== */
$sql = "
    SELECT 
        p.id_pinjam,
        p.id_user,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.status,
        p.catatan,
        p.dokumen_peminjaman,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas ORDER BY f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_list,
        COALESCE(GROUP_CONCAT(DISTINCT CONCAT(f.nama_fasilitas, ' (', f.kategori, ')') ORDER BY f.nama_fasilitas SEPARATOR ' | '), '-') AS fasilitas_detail,
        COALESCE(GROUP_CONCAT(DISTINCT f.kategori ORDER BY f.kategori SEPARATOR ', '), '-') AS kategori_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.lokasi ORDER BY f.lokasi SEPARATOR ', '), '-') AS lokasi_list,
        
        pg.id_kembali,
        pg.kondisi,
        pg.catatan AS catatan_kembali,
        pg.tgl_kembali,
        
        tl.id_tindaklanjut,
        tl.tindakan,
        tl.deskripsi AS deskripsi_tindaklanjut,
        tl.status AS status_tindaklanjut,
        tl.tanggal AS tanggal_tindaklanjut
    FROM peminjaman p
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    LEFT JOIN pengembalian pg ON pg.id_pinjam = p.id_pinjam
    LEFT JOIN tindaklanjut tl ON tl.id_kembali = pg.id_kembali
    WHERE p.id_pinjam = ?
      AND p.id_user = ?
    GROUP BY 
        p.id_pinjam, 
        p.id_user, 
        p.tanggal_mulai, 
        p.tanggal_selesai, 
        p.status, 
        p.catatan, 
        p.dokumen_peminjaman,
        pg.id_kembali, 
        pg.kondisi, 
        pg.catatan, 
        pg.tgl_kembali,
        tl.id_tindaklanjut, 
        tl.tindakan, 
        tl.deskripsi, 
        tl.status, 
        tl.tanggal
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

$stmt->bind_param("ii", $id_pinjam, $id_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Data peminjaman tidak ditemukan atau Anda tidak memiliki akses.";
    $stmt->close();
    header("Location: riwayat.php");
    exit;
}

$detail = $result->fetch_assoc();
$stmt->close();

/* ==========================
   OLAH DATA UNTUK TAMPILAN (SANITASI SEMUA OUTPUT)
   ========================== */
// Sanitasi data peminjaman
$status = strtolower(htmlspecialchars($detail['status'] ?? '', ENT_QUOTES, 'UTF-8'));
$fasilitas = htmlspecialchars($detail['fasilitas_list'] ?? '-', ENT_QUOTES, 'UTF-8');
$fasilitasDet = htmlspecialchars($detail['fasilitas_detail'] ?? '-', ENT_QUOTES, 'UTF-8');
$kategori = htmlspecialchars($detail['kategori_list'] ?? '-', ENT_QUOTES, 'UTF-8');
$lokasi = htmlspecialchars($detail['lokasi_list'] ?? '-', ENT_QUOTES, 'UTF-8');
$catatan = htmlspecialchars($detail['catatan'] ?? '', ENT_QUOTES, 'UTF-8');

// Format tanggal dengan validasi
$tglMulai = !empty($detail['tanggal_mulai']) 
    ? date('d M Y', strtotime($detail['tanggal_mulai'])) 
    : '-';
$tglSelesai = !empty($detail['tanggal_selesai']) 
    ? date('d M Y', strtotime($detail['tanggal_selesai'])) 
    : '-';
$tglKembali = !empty($detail['tgl_kembali']) 
    ? date('d M Y', strtotime($detail['tgl_kembali'])) 
    : '-';

// Sanitasi data pengembalian
$kondisi = strtolower(htmlspecialchars($detail['kondisi'] ?? '', ENT_QUOTES, 'UTF-8'));
$catatan_kembali = htmlspecialchars($detail['catatan_kembali'] ?? '', ENT_QUOTES, 'UTF-8');

// Dokumen dengan validasi path
$dokumen = htmlspecialchars($detail['dokumen_peminjaman'] ?? '', ENT_QUOTES, 'UTF-8');

// ID dengan type casting
$id_kembali = (int)($detail['id_kembali'] ?? 0);
$id_tindaklanjut = (int)($detail['id_tindaklanjut'] ?? 0);

// Badge status peminjaman
$statusClass = 'status-pill-selesai';
$statusText = 'Tidak diketahui';
$statusIcon = 'question-circle';

switch ($status) {
    case 'usulan':
        $statusClass = 'status-pill-usulan';
        $statusText = 'Usulan';
        $statusIcon = 'hourglass-split';
        break;
    case 'diterima':
        $statusClass = 'status-pill-diterima';
        $statusText = 'Diterima';
        $statusIcon = 'check-circle';
        break;
    case 'ditolak':
        $statusClass = 'status-pill-ditolak';
        $statusText = 'Ditolak';
        $statusIcon = 'x-circle';
        break;
    case 'selesai':
        $statusClass = 'status-pill-selesai';
        $statusText = 'Selesai';
        $statusIcon = 'check-all';
        break;
}

// Kondisi pengembalian
$kondisiClass = 'bg-secondary text-white';
$kondisiText = 'Belum dinilai';
$kondisiIcon = 'hourglass';

if ($kondisi === 'baik') {
    $kondisiClass = 'bg-success text-white';
    $kondisiText = 'Baik';
    $kondisiIcon = 'check-circle';
} elseif ($kondisi === 'rusak') {
    $kondisiClass = 'bg-danger text-white';
    $kondisiText = 'Rusak';
    $kondisiIcon = 'exclamation-triangle';
} elseif ($id_kembali > 0) {
    $kondisiClass = 'bg-secondary text-white';
    $kondisiText = 'Lainnya';
    $kondisiIcon = 'dash-circle';
}

// Status tindak lanjut
$tlStatus = strtolower(htmlspecialchars($detail['status_tindaklanjut'] ?? '', ENT_QUOTES, 'UTF-8'));
$tlDisplayText = 'Tidak ada tindak lanjut';
$tlClass = 'bg-secondary text-white';
$tlIcon = 'dash-circle';

if ($id_tindaklanjut > 0) {
    if ($tlStatus === 'pending' || $tlStatus === 'proses') {
        $tlClass = 'bg-warning text-dark';
        $tlDisplayText = 'Proses';
        $tlIcon = 'hourglass-split';
    } elseif ($tlStatus === 'selesai') {
        $tlClass = 'bg-success text-white';
        $tlDisplayText = 'Selesai';
        $tlIcon = 'check-circle';
    } elseif ($tlStatus !== '') {
        $tlClass = 'bg-info text-white';
        $tlDisplayText = ucfirst($tlStatus);
        $tlIcon = 'info-circle';
    }
}

// Sanitasi data tindak lanjut
$tindakan = htmlspecialchars($detail['tindakan'] ?? '', ENT_QUOTES, 'UTF-8');
$deskripsi_tl = htmlspecialchars($detail['deskripsi_tindaklanjut'] ?? '', ENT_QUOTES, 'UTF-8');

/* ==========================
   LOAD HEADER & NAVBAR
   ========================== */
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

    /* ======== DETAIL CARD ======== */
    .detail-card {
        background: white;
        border-radius: 20px;
        border: none;
        overflow: hidden;
        box-shadow: 0 10px 40px rgba(11, 44, 97, 0.1);
    }

    .detail-card .card-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 25px;
        border: none;
    }

    .detail-card .card-header h5 {
        color: white;
        font-weight: 700;
        margin: 0;
    }

    .detail-card .card-header small {
        opacity: 0.9;
    }

    .detail-card .card-body {
        padding: 30px;
    }

    /* ======== SECTION TITLE ======== */
    .section-title {
        color: var(--primary-color);
        font-weight: 700;
        font-size: 1.1rem;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* ======== INFO ROW ======== */
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-row .label {
        font-weight: 600;
        color: var(--muted-text);
        font-size: 0.9rem;
    }

    .info-row .value {
        font-weight: 600;
        color: var(--dark-text);
        text-align: right;
        font-size: 0.9rem;
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

    /* ======== BADGES ======== */
    .badge-kondisi {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
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

    /* ======== INFO BOX ======== */
    .bg-light-subtle {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border: 2px solid var(--border-color);
    }

    /* ======== BUTTONS ======== */
    .btn-outline-secondary {
        border: 2px solid var(--border-color);
        color: var(--muted-text);
        border-radius: 12px;
        padding: 10px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-color), #fbbf24);
        border: none;
        color: white !important;
        border-radius: 12px;
        padding: 10px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(245, 158, 11, 0.3);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger-color), #ef4444);
        border: none;
        border-radius: 12px;
        padding: 10px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(220, 38, 38, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success-color), #22c55e);
        border: none;
        border-radius: 12px;
        padding: 10px 24px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(22, 163, 74, 0.3);
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

    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        border-left: 4px solid var(--danger-color);
    }

    /* ======== RESPONSIVE ======== */
    @media (max-width: 768px) {
        .hero-section h2 {
            font-size: 2rem;
        }

        .hero-section p {
            font-size: 1rem;
        }

        .detail-card .card-header {
            padding: 20px;
            flex-direction: column;
            align-items: flex-start !important;
            gap: 10px;
        }

        .detail-card .card-body {
            padding: 20px;
        }

        .info-row {
            flex-direction: column;
            gap: 5px;
        }

        .info-row .value {
            text-align: left;
        }

        .d-flex.gap-2 {
            flex-direction: column;
            width: 100%;
        }

        .d-flex.gap-2 .btn {
            width: 100%;
        }
    }
</style>

<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-3" data-aos="fade-up">Detail Peminjaman</h2>
        <p class="mb-0" data-aos="fade-up" data-aos-delay="100">
            Informasi lengkap mengenai peminjaman fasilitas yang kamu ajukan, termasuk pengembalian dan tindak lanjut
        </p>
    </div>
</section>

<div class="container mb-5">
    <div class="row justify-content-center">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="col-lg-10 col-xl-9" data-aos="fade-down">
                <div class="alert alert-success">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="col-lg-10 col-xl-9" data-aos="fade-down">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-lg-10 col-xl-9" data-aos="fade-up">
            <div class="card detail-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">
                            <i class="bi bi-clipboard-check me-2"></i>Peminjaman #<?= $id_pinjam ?>
                        </h5>
                        <small>Diajukan oleh: <?= $nama_user ?></small>
                    </div>
                    <span class="status-pill <?= $statusClass ?>">
                        <i class="bi bi-<?= $statusIcon ?>"></i>
                        <?= $statusText ?>
                    </span>
                </div>

                <div class="card-body">
                    <!-- Section 1: Info Waktu -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h6 class="section-title">
                                <i class="bi bi-calendar-event"></i>Informasi Waktu
                            </h6>
                            <div class="info-row">
                                <span class="label">Tanggal Mulai</span>
                                <span class="value"><?= $tglMulai ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Tanggal Selesai</span>
                                <span class="value"><?= $tglSelesai ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="section-title">
                                <i class="bi bi-building"></i>Fasilitas & Lokasi
                            </h6>
                            <div class="info-row">
                                <span class="label">Fasilitas</span>
                                <span class="value"><?= $fasilitas ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Kategori</span>
                                <span class="value"><?= $kategori ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Lokasi</span>
                                <span class="value"><?= $lokasi ?></span>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Section 2: Dokumen & Catatan -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h6 class="section-title">
                                <i class="bi bi-file-earmark-pdf"></i>Dokumen Peminjaman
                            </h6>
                            <?php if (!empty($dokumen)): ?>
                                <p class="mb-2">
                                    <i class="bi bi-file-earmark-pdf-fill me-2 text-danger" style="font-size: 1.5rem;"></i>
                                    <a href="<?= $dokumen ?>" target="_blank" class="text-decoration-none">
                                        <strong>Lihat Dokumen Peminjaman</strong>
                                    </a>
                                </p>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Pastikan dokumen yang diunggah sudah sesuai format persyaratan.
                                </small>
                            <?php else: ?>
                                <p class="text-muted mb-0">
                                    <i class="bi bi-dash-circle me-1"></i>
                                    Belum ada dokumen peminjaman yang diunggah.
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <h6 class="section-title">
                                <i class="bi bi-chat-left-text"></i>Catatan Peminjaman
                            </h6>
                            <p class="mb-0 text-muted" style="white-space: pre-line; line-height: 1.7;">
                                <?= $catatan ?: 'Tidak ada catatan tambahan.' ?>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <!-- Section 3: Pengembalian -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h6 class="section-title">
                                <i class="bi bi-box-arrow-in-left"></i>Informasi Pengembalian
                            </h6>
                            <div class="info-row">
                                <span class="label">Tanggal Pengembalian</span>
                                <span class="value"><?= $tglKembali ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Kondisi Fasilitas</span>
                                <span class="value">
                                    <span class="badge badge-kondisi <?= $kondisiClass ?>">
                                        <i class="bi bi-<?= $kondisiIcon ?>"></i>
                                        <?= $kondisiText ?>
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="section-title">
                                <i class="bi bi-chat-left-text"></i>Catatan Pengembalian
                            </h6>
                            <p class="mb-0 text-muted" style="white-space: pre-line; line-height: 1.7;">
                                <?= $catatan_kembali ?: 'Belum ada catatan pengembalian.' ?>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <!-- Section 4: Tindak Lanjut & Komunikasi -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 class="section-title">
                                <i class="bi bi-tools"></i>Tindak Lanjut Kerusakan & Komunikasi
                            </h6>

                            <div class="p-4 rounded border bg-light-subtle">
                                <?php if ($id_tindaklanjut > 0): ?>
                                    <div class="mb-3">
                                        <strong>Status Tindak Lanjut:</strong>
                                        <span class="badge <?= $tlClass ?> ms-2">
                                            <i class="bi bi-<?= $tlIcon ?>"></i>
                                            <?= $tlDisplayText ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($detail['tanggal_tindaklanjut'])): ?>
                                        <p class="mb-3">
                                            <strong><i class="bi bi-calendar me-1"></i>Tanggal:</strong>
                                            <?= date('d M Y H:i', strtotime($detail['tanggal_tindaklanjut'])) ?>
                                        </p>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <strong><i class="bi bi-wrench me-1"></i>Tindakan:</strong>
                                        <p class="mt-1 mb-0 text-muted" style="white-space: pre-line;">
                                            <?= $tindakan ?: '-' ?>
                                        </p>
                                    </div>

                                    <div class="mb-3">
                                        <strong><i class="bi bi-file-text me-1"></i>Deskripsi:</strong>
                                        <p class="mt-1 mb-0 text-muted" style="white-space: pre-line;">
                                            <?= $deskripsi_tl ?: 'Tidak ada deskripsi tambahan.' ?>
                                        </p>
                                    </div>

                                    <div class="mt-4">
                                        <a href="komunikasi_tindaklanjut.php?id_pinjam=<?= $id_pinjam ?>&id_tl=<?= $id_tindaklanjut ?>"
                                           class="btn btn-danger">
                                            <i class="bi bi-chat-dots-fill me-2"></i>
                                            Buka Komunikasi Kerusakan
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Tidak ada tindak lanjut kerusakan untuk peminjaman ini,
                                        atau fasilitas dikembalikan dalam kondisi baik.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi Utama -->
                    <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-3">
                        <a href="riwayat.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Kembali ke Riwayat
                        </a>

                        <div class="d-flex gap-2 flex-wrap">
                            <?php if ($status === 'usulan'): ?>
                                <a href="edit_peminjaman.php?id=<?= $id_pinjam ?>"
                                   class="btn btn-warning">
                                    <i class="bi bi-pencil-square me-2"></i>Edit Pengajuan
                                </a>
                                <a href="batalkan_peminjaman.php?id=<?= $id_pinjam ?>"
                                   class="btn btn-danger"
                                   onclick="return confirm('⚠️ Yakin ingin membatalkan pengajuan peminjaman ini?');">
                                    <i class="bi bi-x-circle me-2"></i>Batalkan
                                </a>
                            <?php endif; ?>

                            <?php if ($status === 'diterima' && $id_kembali === 0): ?>
                                <a href="pengembalian_peminjaman.php?id=<?= $id_pinjam ?>"
                                   class="btn btn-success"
                                   onclick="return confirm('✅ Konfirmasi bahwa fasilitas sudah dikembalikan?');">
                                    <i class="bi bi-box-arrow-in-left me-2"></i>Konfirmasi Pengembalian
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<?php include '../includes/peminjam/footer.php'; ?>

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
</script>
