<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}
include '../config/koneksi.php';

$id_user   = isset($_SESSION['id_user']) ? (int) $_SESSION['id_user'] : 0;
$nama_user = $_SESSION['nama_user'] ?? 'Peminjam';

if ($id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$id_pinjam = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id_pinjam <= 0) {
    $_SESSION['error'] = "Peminjaman tidak valid.";
    header("Location: riwayat.php");
    exit;
}

/* ==========================================================
   AMBIL DETAIL PEMINJAMAN + FASILITAS + PENGEMBALIAN + TINDAK LANJUT
   (PREPARED STATEMENT)
   ========================================================== */

$sql = "
    SELECT 
        p.*,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_list,
        COALESCE(GROUP_CONCAT(DISTINCT CONCAT(f.nama_fasilitas, ' (', f.kategori, ')') SEPARATOR ' | '), '-') AS fasilitas_detail,
        COALESCE(GROUP_CONCAT(DISTINCT f.kategori SEPARATOR ', '), '-') AS kategori_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.lokasi SEPARATOR ', '), '-')   AS lokasi_list,
        
        pg.id_kembali,
        pg.kondisi,
        pg.catatan      AS catatan_kembali,
        pg.tgl_kembali,
        
        tl.id_tindaklanjut,
        tl.tindakan,
        tl.deskripsi    AS deskripsi_tindaklanjut,
        tl.status       AS status_tindaklanjut,
        tl.tanggal      AS tanggal_tindaklanjut
    FROM peminjaman p
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    LEFT JOIN pengembalian pg ON pg.id_pinjam = p.id_pinjam
    LEFT JOIN tindaklanjut tl ON tl.id_kembali = pg.id_kembali
    WHERE p.id_pinjam = ?
      AND p.id_user   = ?
    GROUP BY p.id_pinjam
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query error: " . $conn->error);
}
$stmt->bind_param("ii", $id_pinjam, $id_user);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['error'] = "Data peminjaman tidak ditemukan.";
    $stmt->close();
    header("Location: riwayat.php");
    exit;
}
$detail = $result->fetch_assoc();
$stmt->close();

/* ==========================
   OLAH DATA UNTUK TAMPILAN
   ========================== */

$status       = strtolower($detail['status'] ?? '');
$fasilitas    = $detail['fasilitas_list']         ?? '-';
$fasilitasDet = $detail['fasilitas_detail']       ?? '-';
$kategori     = $detail['kategori_list']          ?? '-';
$lokasi       = $detail['lokasi_list']            ?? '-';

$tglMulai     = $detail['tanggal_mulai']   ? date('d M Y', strtotime($detail['tanggal_mulai']))   : '-';
$tglSelesai   = $detail['tanggal_selesai'] ? date('d M Y', strtotime($detail['tanggal_selesai'])) : '-';

$tglKembali   = $detail['tgl_kembali']     ? date('d M Y', strtotime($detail['tgl_kembali']))     : '-';
$kondisi      = strtolower($detail['kondisi'] ?? '');

$dokumen      = $detail['dokumen_peminjaman'] ?? '';

$id_kembali       = (int) ($detail['id_kembali']       ?? 0);
$id_tindaklanjut  = (int) ($detail['id_tindaklanjut']  ?? 0);

/* Badge status peminjaman */
switch ($status) {
    case 'usulan':
        $statusClass = 'status-pill-usulan';
        $statusText  = 'Usulan';
        break;
    case 'diterima':
        $statusClass = 'status-pill-diterima';
        $statusText  = 'Diterima';
        break;
    case 'ditolak':
        $statusClass = 'status-pill-ditolak';
        $statusText  = 'Ditolak';
        break;
    case 'selesai':
        $statusClass = 'status-pill-selesai';
        $statusText  = 'Selesai';
        break;
    default:
        $statusClass = 'status-pill-selesai';
        $statusText  = ucfirst($status ?: 'Tidak diketahui');
        break;
}

/* Kondisi pengembalian */
if ($kondisi === 'baik') {
    $kondisiClass = 'bg-success text-white';
    $kondisiText  = 'Baik';
} elseif ($kondisi === 'rusak') {
    $kondisiClass = 'bg-danger text-white';
    $kondisiText  = 'Rusak';
} elseif ($id_kembali > 0) {
    $kondisiClass = 'bg-secondary text-white';
    $kondisiText  = 'Lainnya';
} else {
    $kondisiClass = 'bg-secondary text-white';
    $kondisiText  = 'Belum dinilai';
}

/* Status tindak lanjut */
$tlStatus      = strtolower($detail['status_tindaklanjut'] ?? '');
$tlDisplayText = 'Tidak ada tindak lanjut';
$tlClass       = 'bg-secondary text-white';

if ($id_tindaklanjut > 0) {
    if ($tlStatus === 'pending') {
        $tlClass       = 'bg-warning text-dark';
        $tlDisplayText = 'Pending';
    } elseif ($tlStatus === 'selesai') {
        $tlClass       = 'bg-success text-white';
        $tlDisplayText = 'Selesai';
    } elseif ($tlStatus !== '') {
        $tlClass       = 'bg-info text-white';
        $tlDisplayText = ucfirst($tlStatus);
    }
}

/* ==========================
   LOAD HEADER & NAVBAR
   ========================== */
include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>

<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-1">Detail Peminjaman</h2>
        <p class="mb-1 text-muted">
            Informasi lengkap mengenai peminjaman fasilitas yang kamu ajukan, termasuk pengembalian dan tindak lanjut.
        </p>
    </div>
</section>

<div class="container mb-5 detail-peminjaman-wrapper">
    <div class="row justify-content-center">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="col-lg-10 col-xl-9">
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="col-lg-10 col-xl-9">
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-lg-10 col-xl-9">
            <div class="card detail-card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Peminjaman #<?= (int)$detail['id_pinjam']; ?></h5>
                        <small class="text-light-subtle">Diajukan oleh: <?= htmlspecialchars($nama_user); ?></small>
                    </div>
                    <span class="status-pill <?= $statusClass; ?>">
                        <?= htmlspecialchars($statusText); ?>
                    </span>
                </div>

                <div class="card-body">
                    <!-- Section 1: Info umum -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h6 class="section-title">Informasi Waktu</h6>
                            <div class="info-row">
                                <span class="label">Tanggal Mulai</span>
                                <span class="value"><?= htmlspecialchars($tglMulai); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Tanggal Selesai</span>
                                <span class="value"><?= htmlspecialchars($tglSelesai); ?></span>
                            </div>
                            <?php if (!empty($detail['created_at'])): ?>
                                <div class="info-row">
                                    <span class="label">Tanggal Pengajuan</span>
                                    <span class="value"><?= date('d M Y H:i', strtotime($detail['created_at'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <h6 class="section-title">Fasilitas & Lokasi</h6>
                            <div class="info-row">
                                <span class="label">Fasilitas</span>
                                <span class="value"><?= htmlspecialchars($fasilitas); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Kategori</span>
                                <span class="value"><?= htmlspecialchars($kategori); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Lokasi</span>
                                <span class="value"><?= htmlspecialchars($lokasi); ?></span>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Section 2: Dokumen & Catatan -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h6 class="section-title">Dokumen Peminjaman</h6>
                            <?php if (!empty($dokumen)): ?>
                                <p class="mb-1">
                                    <i class="bi bi-file-earmark-pdf-fill me-1 text-danger"></i>
                                    <!-- $dokumen sudah berisi path relatif yang disimpan di DB -->
                                    <a href="<?= htmlspecialchars($dokumen); ?>" target="_blank">
                                        Lihat dokumen peminjaman
                                    </a>
                                </p>
                                <small class="text-muted">Pastikan dokumen yang diunggah sudah sesuai format persyaratan.</small>
                            <?php else: ?>
                                <p class="text-muted mb-0">
                                    Belum ada dokumen peminjaman yang diunggah.
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <h6 class="section-title">Catatan Peminjaman</h6>
                            <p class="mb-0 text-muted" style="white-space:pre-line;">
                                <?= $detail['catatan'] ? htmlspecialchars($detail['catatan']) : 'Tidak ada catatan tambahan.'; ?>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <!-- Section 3: Pengembalian -->
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h6 class="section-title">Informasi Pengembalian</h6>
                            <div class="info-row">
                                <span class="label">Tanggal Pengembalian</span>
                                <span class="value"><?= htmlspecialchars($tglKembali); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="label">Kondisi</span>
                                <span class="value">
                                    <span class="badge badge-kondisi <?= $kondisiClass; ?>">
                                        <?= htmlspecialchars($kondisiText); ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="section-title">Catatan Pengembalian</h6>
                            <p class="mb-0 text-muted" style="white-space:pre-line;">
                                <?= $detail['catatan_kembali'] ? htmlspecialchars($detail['catatan_kembali']) : 'Belum ada catatan pengembalian.'; ?>
                            </p>
                        </div>
                    </div>

                    <hr>

                    <!-- Section 4: Tindak Lanjut & Komunikasi -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <h6 class="section-title d-flex align-items-center">
                                <i class="bi bi-tools me-2"></i> Tindak Lanjut Kerusakan & Komunikasi
                            </h6>

                            <div class="p-3 rounded border bg-light-subtle mb-2">
                                <?php if ($id_tindaklanjut > 0): ?>
                                    <p class="mb-1">
                                        <strong>Status Tindak Lanjut:</strong>
                                        <span class="badge <?= $tlClass; ?> ms-1">
                                            <?= htmlspecialchars($tlDisplayText); ?>
                                        </span>
                                    </p>

                                    <?php if (!empty($detail['tanggal_tindaklanjut'])): ?>
                                        <p class="mb-1">
                                            <strong>Tanggal:</strong>
                                            <?= date('d M Y H:i', strtotime($detail['tanggal_tindaklanjut'])); ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="mb-1">
                                        <strong>Tindakan:</strong><br>
                                        <?= nl2br(htmlspecialchars($detail['tindakan'] ?? '-')); ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Deskripsi:</strong><br>
                                        <?= $detail['deskripsi_tindaklanjut']
                                            ? nl2br(htmlspecialchars($detail['deskripsi_tindaklanjut']))
                                            : '<span class="text-muted">Tidak ada deskripsi tambahan.</span>'; ?>
                                    </p>

                                    <div class="mt-3">
                                        <a href="komunikasi_tindaklanjut.php?id_pinjam=<?= (int)$detail['id_pinjam']; ?>&id_tl=<?= (int)$id_tindaklanjut; ?>"
                                           class="btn btn-danger btn-sm">
                                            <i class="bi bi-chat-dots-fill me-1"></i>
                                            Buka Komunikasi Kerusakan
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted mb-0">
                                        Tidak ada tindak lanjut kerusakan untuk peminjaman ini,
                                        atau fasilitas dikembalikan dalam kondisi baik.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tombol Aksi utama -->
                    <div class="d-flex justify-content-between align-items-center mt-4 flex-wrap gap-2">
                        <a href="riwayat.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
                        </a>

                        <div class="d-flex gap-2">
                            <?php if ($status === 'usulan'): ?>
                                <a href="edit_peminjaman.php?id=<?= (int)$detail['id_pinjam']; ?>"
                                   class="btn btn-warning text-white">
                                    <i class="bi bi-pencil-square me-1"></i> Edit Pengajuan
                                </a>
                                <a href="batalkan_peminjaman.php?id=<?= (int)$detail['id_pinjam']; ?>"
                                   class="btn btn-danger"
                                   onclick="return confirm('Yakin ingin membatalkan pengajuan peminjaman ini?');">
                                    <i class="bi bi-x-circle me-1"></i> Batalkan
                                </a>
                            <?php endif; ?>

                            <?php if ($status === 'diterima' && $id_kembali === 0): ?>
                                <a href="pengembalian_peminjaman.php?id=<?= (int)$detail['id_pinjam']; ?>"
                                   class="btn btn-success"
                                   onclick="return confirm('Konfirmasi bahwa fasilitas sudah dikembalikan?');">
                                    <i class="bi bi-box-arrow-in-left me-1"></i> Konfirmasi Pengembalian
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
