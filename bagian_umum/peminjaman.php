<?php
session_start();
include '../config/koneksi.php';

/* ========================
   CEK LOGIN (PREPARED)
   ======================== */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user_login = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user_login === false || $id_user_login <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Ambil data user login (nama, role)
$user = null;
$stmtUser = $conn->prepare("SELECT nama, role FROM users WHERE id_user = ? LIMIT 1");
$stmtUser->bind_param("i", $id_user_login);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
if ($resUser) {
    $user = $resUser->fetch_assoc();
}
$stmtUser->close();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Hanya bagian_umum yang boleh akses halaman ini
if ($user['role'] !== 'bagian_umum') {
    header("Location: ../peminjam/dashboard.php");
    exit;
}

/* ===================== NOTIFIKASI (BADGE + RIWAYAT) ===================== */
$notifPeminjaman        = [];
$notifRusak             = [];
$jumlahNotifPeminjaman  = 0;
$jumlahNotifRusak       = 0;
$jumlahNotif            = 0;

// Peminjaman baru (status usulan)
$sqlNotifP = "
    SELECT 
        p.id_pinjam,
        u.nama,
        p.tanggal_mulai
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.status = 'usulan'
    ORDER BY p.id_pinjam DESC
    LIMIT 5
";
$stmtNotifP = $conn->prepare($sqlNotifP);
if ($stmtNotifP) {
    $stmtNotifP->execute();
    $resNotifP = $stmtNotifP->get_result();
    while ($row = $resNotifP->fetch_assoc()) {
        $notifPeminjaman[] = $row;
    }
    $stmtNotifP->close();
}
$jumlahNotifPeminjaman = count($notifPeminjaman);

// Pengembalian dengan kondisi rusak
$sqlNotifR = "
    SELECT 
        k.id_kembali,
        k.id_pinjam,
        u.nama,
        k.tgl_kembali
    FROM pengembalian k
    JOIN peminjaman p ON k.id_pinjam = p.id_pinjam
    JOIN users u ON p.id_user = u.id_user
    WHERE k.kondisi = 'rusak'
    ORDER BY k.id_kembali DESC
    LIMIT 5
";
$stmtNotifR = $conn->prepare($sqlNotifR);
if ($stmtNotifR) {
    $stmtNotifR->execute();
    $resNotifR = $stmtNotifR->get_result();
    while ($row = $resNotifR->fetch_assoc()) {
        $notifRusak[] = $row;
    }
    $stmtNotifR->close();
}
$jumlahNotifRusak = count($notifRusak);

// Total untuk badge di icon bell
$jumlahNotif = $jumlahNotifPeminjaman + $jumlahNotifRusak;

// Variable untuk badge sidebar
$statUsulan = $jumlahNotifPeminjaman;

/* ========================
   AMBIL FLASH MESSAGE
   ======================== */
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/* =======================================================
   Aksi: TOLAK (POST) -> dengan alasan_penolakan (PREPARED)
   ======================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tolak') {
    $id_pinjam_raw    = $_POST['id_pinjam'] ?? 0;
    $id_pinjam        = filter_var($id_pinjam_raw, FILTER_VALIDATE_INT);
    $alasan_penolakan = trim($_POST['alasan_penolakan'] ?? '');

    // Validasi ID
    if ($id_pinjam === false || $id_pinjam <= 0) {
        $_SESSION['error'] = "ID peminjaman tidak valid.";
        header("Location: peminjaman.php");
        exit;
    }

    // Validasi alasan
    if ($alasan_penolakan === '' || mb_strlen($alasan_penolakan) < 5) {
        $_SESSION['error'] = "Alasan penolakan tidak boleh kosong dan minimal 5 karakter.";
        header("Location: peminjaman.php");
        exit;
    }
    if (mb_strlen($alasan_penolakan) > 500) {
        $_SESSION['error'] = "Alasan penolakan terlalu panjang (maksimal 500 karakter).";
        header("Location: peminjaman.php");
        exit;
    }

    // Update status & alasan (prepared)
    $sqlTolak = "
        UPDATE peminjaman
        SET status = 'ditolak',
            alasan_penolakan = ?
        WHERE id_pinjam = ?
    ";
    $stmtTolak = $conn->prepare($sqlTolak);
    if ($stmtTolak) {
        $stmtTolak->bind_param("si", $alasan_penolakan, $id_pinjam);
        if ($stmtTolak->execute()) {
            $_SESSION['success'] = "Peminjaman #$id_pinjam berhasil ditolak dengan alasan.";
        } else {
            $_SESSION['error'] = "Gagal menolak peminjaman.";
        }
        $stmtTolak->close();
    } else {
        $_SESSION['error'] = "Gagal menyiapkan query penolakan.";
    }

    header("Location: peminjaman.php");
    exit;
}

/* =======================================================
   Aksi: ubah status / hapus (GET, PREPARED)
   ======================================================== */
if (isset($_GET['aksi'], $_GET['id'])) {
    $aksi_raw = $_GET['aksi'];
    $id_raw   = $_GET['id'];

    $aksi     = filter_var($aksi_raw, FILTER_SANITIZE_STRING);
    $id_pinjam = filter_var($id_raw, FILTER_VALIDATE_INT);

    $allowedAksi = ['terima', 'selesai', 'hapus'];
    if (!in_array($aksi, $allowedAksi, true)) {
        $_SESSION['error'] = "Aksi tidak dikenal.";
        header("Location: peminjaman.php");
        exit;
    }

    if ($id_pinjam === false || $id_pinjam <= 0) {
        $_SESSION['error'] = "ID peminjaman tidak valid.";
        header("Location: peminjaman.php");
        exit;
    }

    // cek dulu datanya ada
    $sqlCek = "
        SELECT p.id_pinjam, p.id_user, p.status, u.nama
        FROM peminjaman p
        JOIN users u ON p.id_user = u.id_user
        WHERE p.id_pinjam = ?
        LIMIT 1
    ";
    $stmtCek = $conn->prepare($sqlCek);
    $stmtCek->bind_param("i", $id_pinjam);
    $stmtCek->execute();
    $resCek = $stmtCek->get_result();
    $data   = $resCek ? $resCek->fetch_assoc() : null;
    $stmtCek->close();

    if ($data) {
        $new_status = null;

        if ($aksi === 'hapus') {
            // Hapus secara cascade dengan prepared
            // 1) Hapus data pengembalian
            $stmtDelK = $conn->prepare("DELETE FROM pengembalian WHERE id_pinjam = ?");
            if ($stmtDelK) {
                $stmtDelK->bind_param("i", $id_pinjam);
                $stmtDelK->execute();
                $stmtDelK->close();
            }

            // 2) Hapus detail peminjaman fasilitas
            $stmtDelDF = $conn->prepare("DELETE FROM daftar_peminjaman_fasilitas WHERE id_pinjam = ?");
            if ($stmtDelDF) {
                $stmtDelDF->bind_param("i", $id_pinjam);
                $stmtDelDF->execute();
                $stmtDelDF->close();
            }

            // 3) Hapus data utama peminjaman
            $stmtDelP = $conn->prepare("DELETE FROM peminjaman WHERE id_pinjam = ?");
            if ($stmtDelP) {
                $stmtDelP->bind_param("i", $id_pinjam);
                if ($stmtDelP->execute()) {
                    $_SESSION['success'] = "Peminjaman #$id_pinjam beserta data pengembalian dan detail fasilitasnya berhasil dihapus.";
                } else {
                    $_SESSION['error'] = "Gagal menghapus peminjaman.";
                }
                $stmtDelP->close();
            } else {
                $_SESSION['error'] = "Gagal menyiapkan query hapus peminjaman.";
            }

            header("Location: peminjaman.php");
            exit;
        }

        // Jika bukan hapus → tentukan status baru
        switch ($aksi) {
            case 'terima':
                $new_status = 'diterima';
                break;
            case 'selesai':
                $new_status = 'selesai';
                break;
        }

        if ($new_status !== null) {
            $stmtUpd = $conn->prepare("UPDATE peminjaman SET status = ? WHERE id_pinjam = ?");
            if ($stmtUpd) {
                $stmtUpd->bind_param("si", $new_status, $id_pinjam);
                if ($stmtUpd->execute()) {
                    $_SESSION['success'] = "Status peminjaman #$id_pinjam diubah menjadi '$new_status'.";
                } else {
                    $_SESSION['error'] = "Gagal mengubah status peminjaman.";
                }
                $stmtUpd->close();
            } else {
                $_SESSION['error'] = "Gagal menyiapkan query ubah status.";
            }
        }
    } else {
        $_SESSION['error'] = "Data peminjaman tidak ditemukan.";
    }

    header("Location: peminjaman.php");
    exit;
}

/* ========================
   AMBIL DATA UNTUK TABEL (PREPARED)
   ======================== */
$sqlList = "
    SELECT 
        p.*,
        u.nama,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.lokasi SEPARATOR ', '), '-')          AS lokasi_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.kategori SEPARATOR ', '), '-')        AS kategori_list
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    GROUP BY p.id_pinjam
    ORDER BY p.id_pinjam DESC
";
$stmtList = $conn->prepare($sqlList);
$result   = null;
if ($stmtList) {
    $stmtList->execute();
    $result = $stmtList->get_result();
}

// ========================
// TEMPLATE ADMIN
// ========================
$pageTitle   = 'Kelola Peminjaman';
$currentPage = 'peminjaman';

include '../includes/admin/header.php';
include '../includes/admin/sidebar.php';
?>

<style>
    .peminjaman-title {
        font-size: 1.4rem;
    }
    .peminjaman-subtitle {
        font-size: 0.9rem;
    }
    .card-peminjaman table thead th {
        font-size: 0.85rem;
        text-transform: none;
        letter-spacing: .03em;
    }
    .card-peminjaman table tbody td {
        font-size: 0.9rem;
    }
    .badge-status {
        font-size: 0.8rem;
        padding: .4rem .7rem;
        font-weight: 600;
    }
    .btn-action {
        padding: 0.35rem 0.7rem;
        border-radius: 6px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-action i {
        font-size: 0.9rem;
    }
</style>

<!-- Main Content Area -->
<div id="layoutSidenav_content">
    
    <?php include '../includes/admin/navbar.php'; ?>

    <main>
        <div class="container-fluid px-4">

            <!-- Header Halaman -->
            <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                <div>
                    <h2 class="fw-bold text-success mb-1 peminjaman-title">
                        <i class="fas fa-hand-holding me-2"></i>
                        Kelola Peminjaman
                    </h2>
                    <p class="text-muted mb-0 peminjaman-subtitle">
                        Pengelolaan status peminjaman fasilitas kampus oleh Super Admin.
                    </p>
                </div>
                <a href="peminjaman.php" class="btn btn-outline-success shadow-sm">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </a>
            </div>

            <hr class="mt-0 mb-4" style="border-top: 2px solid #0f172a; opacity: .25;">

            <!-- Alert -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Tabel Data Peminjaman -->
            <div class="card shadow-sm border-0 mb-4 card-peminjaman">
                <div class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i> Data Peminjaman Fasilitas</span>
                    <span class="small opacity-75">
                        Total: <?= $result ? $result->num_rows : 0; ?> peminjaman
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="datatablesSimple" class="table table-bordered table-hover align-middle">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Peminjam</th>
                                    <th>Fasilitas</th>
                                    <th style="width: 120px;">Tanggal Mulai</th>
                                    <th style="width: 120px;">Tanggal Selesai</th>
                                    <th style="width: 120px;">Status</th>
                                    <th>Catatan / Alasan</th>
                                    <th style="width: 280px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;

                                if ($result && $result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                        $status = strtolower($row['status']);

                                        switch ($status) {
                                            case 'usulan':
                                                $badgeClass = 'warning';
                                                $label      = 'Usulan';
                                                break;
                                            case 'diterima':
                                                $badgeClass = 'success';
                                                $label      = 'Diterima';
                                                break;
                                            case 'ditolak':
                                                $badgeClass = 'danger';
                                                $label      = 'Ditolak';
                                                break;
                                            case 'selesai':
                                                $badgeClass = 'primary';
                                                $label      = 'Selesai';
                                                break;
                                            default:
                                                $badgeClass = 'dark';
                                                $label      = $row['status'];
                                        }

                                        $alasan_tolak = $row['alasan_penolakan'] ?? '';
                                        $catatan      = $row['catatan'] ?? '';
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['nama']); ?></strong><br>
                                        <small class="text-muted">ID: #<?= (int)$row['id_user']; ?></small>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['fasilitas_list']); ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-tag me-1"></i><?= htmlspecialchars($row['kategori_list']); ?> |
                                            <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($row['lokasi_list']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center"><?= date('d-m-Y', strtotime($row['tanggal_mulai'])); ?></td>
                                    <td class="text-center"><?= date('d-m-Y', strtotime($row['tanggal_selesai'])); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $badgeClass; ?> badge-status">
                                            <?= $label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($status === 'ditolak' && !empty($alasan_tolak)): ?>
                                            <strong class="text-danger">Alasan penolakan:</strong><br>
                                            <span class="text-danger"><?= nl2br(htmlspecialchars($alasan_tolak)); ?></span>
                                            <?php if (!empty($catatan)): ?>
                                                <hr class="my-1">
                                                <small class="text-muted">
                                                    Catatan: <?= nl2br(htmlspecialchars($catatan)); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?= !empty($catatan) ? nl2br(htmlspecialchars($catatan)) : '<span class="text-muted">-</span>'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Tombol Detail -->
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary btn-action me-1 mb-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalDetail<?= $row['id_pinjam']; ?>"
                                                title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </button>

                                        <!-- TERIMA: usulan / ditolak -->
                                        <?php if ($status === 'usulan' || $status === 'ditolak'): ?>
                                            <a href="peminjaman.php?aksi=terima&id=<?= $row['id_pinjam']; ?>"
                                               class="btn btn-sm btn-success btn-action me-1 mb-1"
                                               onclick="return confirm('Setujui peminjaman #<?= $row['id_pinjam']; ?> ?');"
                                               title="Terima Peminjaman">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>

                                        <!-- TOLAK: usulan / diterima -->
                                        <?php if ($status === 'usulan' || $status === 'diterima'): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-danger btn-action me-1 mb-1 btn-tolak"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalTolak"
                                                    data-id="<?= $row['id_pinjam']; ?>"
                                                    data-info="<?=
                                                        $status === 'diterima'
                                                        ? 'Batalkan peminjaman #' . $row['id_pinjam'] . ' - ' . htmlspecialchars($row['nama'], ENT_QUOTES)
                                                        : 'Peminjaman #' . $row['id_pinjam'] . ' - ' . htmlspecialchars($row['nama'], ENT_QUOTES);
                                                    ?>"
                                                    title="Tolak Peminjaman">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>

                                        <!-- SELESAI: jika diterima -->
                                        <?php if ($status === 'diterima'): ?>
                                            <a href="peminjaman.php?aksi=selesai&id=<?= $row['id_pinjam']; ?>"
                                               class="btn btn-sm btn-primary btn-action me-1 mb-1"
                                               onclick="return confirm('Tandai peminjaman #<?= $row['id_pinjam']; ?> sebagai selesai?');"
                                               title="Tandai Selesai">
                                                <i class="fas fa-flag-checkered"></i>
                                            </a>
                                        <?php endif; ?>

                                        <!-- HAPUS -->
                                        <a href="peminjaman.php?aksi=hapus&id=<?= $row['id_pinjam']; ?>"
                                           class="btn btn-sm btn-dark btn-action mb-1"
                                           onclick="return confirm('Yakin ingin menghapus peminjaman #<?= $row['id_pinjam']; ?> ?');"
                                           title="Hapus Peminjaman">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>

                                <!-- MODAL DETAIL PEMINJAMAN -->
                                <div class="modal fade" id="modalDetail<?= $row['id_pinjam']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-success text-white">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    Detail Peminjaman #<?= $row['id_pinjam']; ?>
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <p class="mb-2"><strong><i class="fas fa-user me-1 text-primary"></i> Peminjam:</strong><br>
                                                            <?= htmlspecialchars($row['nama']); ?>
                                                        </p>
                                                        <p class="mb-2"><strong><i class="fas fa-calendar-alt me-1 text-success"></i> Tanggal Mulai:</strong><br>
                                                            <?= date('d-m-Y', strtotime($row['tanggal_mulai'])); ?>
                                                        </p>
                                                        <p class="mb-2"><strong><i class="fas fa-calendar-check me-1 text-danger"></i> Tanggal Selesai:</strong><br>
                                                            <?= date('d-m-Y', strtotime($row['tanggal_selesai'])); ?>
                                                        </p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p class="mb-2"><strong><i class="fas fa-flag me-1 text-warning"></i> Status:</strong><br>
                                                            <span class="badge bg-<?= $badgeClass; ?> badge-status"><?= $label; ?></span>
                                                        </p>
                                                        <p class="mb-2"><strong><i class="fas fa-building me-1 text-info"></i> Fasilitas:</strong><br>
                                                            <?= htmlspecialchars($row['fasilitas_list']); ?>
                                                        </p>
                                                        <p class="mb-2"><strong><i class="fas fa-map-marker-alt me-1 text-secondary"></i> Kategori & Lokasi:</strong><br>
                                                            <?= htmlspecialchars($row['kategori_list']); ?> | <?= htmlspecialchars($row['lokasi_list']); ?>
                                                        </p>
                                                    </div>
                                                </div>

                                                <hr>

                                                <p class="mb-2"><strong><i class="fas fa-sticky-note me-1 text-primary"></i> Catatan Peminjam:</strong><br>
                                                    <?= !empty($catatan) ? nl2br(htmlspecialchars($catatan)) : '<span class="text-muted">Tidak ada catatan</span>'; ?>
                                                </p>

                                                <?php if (!empty($alasan_tolak)): ?>
                                                    <hr>
                                                    <p class="mb-0"><strong><i class="fas fa-exclamation-triangle me-1 text-danger"></i> Alasan Penolakan:</strong><br>
                                                        <span class="text-danger"><?= nl2br(htmlspecialchars($alasan_tolak)); ?></span>
                                                    </p>
                                                <?php endif; ?>

                                                <?php if (!empty($row['dokumen_peminjaman'])): ?>
                                                    <hr>
                                                    <p class="mb-2"><strong><i class="fas fa-file-alt me-1 text-warning"></i> Surat / Dokumen Pendukung:</strong></p>
                                                    <a href="../uploads/surat/<?= htmlspecialchars($row['dokumen_peminjaman']); ?>"
                                                       class="btn btn-outline-success btn-sm"
                                                       target="_blank">
                                                        <i class="fas fa-download me-1"></i> Buka Dokumen
                                                    </a>
                                                <?php else: ?>
                                                    <hr>
                                                    <p class="mb-0"><strong><i class="fas fa-file-alt me-1 text-muted"></i> Surat / Dokumen Pendukung:</strong>
                                                        <span class="text-muted ms-1">Tidak ada dokumen yang diunggah.</span>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                    <i class="fas fa-times me-1"></i> Tutup
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- END MODAL DETAIL -->

                                <?php
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                        Tidak ada data peminjaman.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer-admin">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>E-Fasilitas</strong> &copy; <?= date('Y'); ?> - Sistem Peminjaman Fasilitas Kampus
            </div>
            <div>
                Version 1.0
            </div>
        </div>
    </footer>

</div>

<!-- MODAL TOLAK (ALASAN PENOLAKAN) -->
<div class="modal fade" id="modalTolak" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form action="peminjaman.php" method="post">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>
                        Tolak Peminjaman
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="tolak">
                    <input type="hidden" name="id_pinjam" id="tolak_id_pinjam">

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Anda akan menolak <strong id="tolak_info"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Alasan penolakan <span class="text-danger">*</span>
                        </label>
                        <textarea name="alasan_penolakan" 
                                  class="form-control" 
                                  rows="4" 
                                  required
                                  minlength="5"
                                  maxlength="500"
                                  placeholder="Tuliskan alasan penolakan (minimal 5 karakter, maksimal 500 karakter)&#10;&#10;Contoh:&#10;- Jadwal bentrok dengan acara lain&#10;- Fasilitas sedang dalam perbaikan&#10;- Dokumen pendukung tidak lengkap"></textarea>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Alasan ini akan dilihat oleh peminjam.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Batal
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-ban me-1"></i> Tolak Peminjaman
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/admin/footer.php'; ?>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// DataTables Init
$(document).ready(function() {
    $('#datatablesSimple').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        pageLength: 10,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });
});

// Isi data modal tolak
var modalTolak = document.getElementById('modalTolak');
if (modalTolak) {
    modalTolak.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var id     = button.getAttribute('data-id');
        var info   = button.getAttribute('data-info');

        document.getElementById('tolak_id_pinjam').value = id;
        document.getElementById('tolak_info').textContent = info;
    });
}
</script>
