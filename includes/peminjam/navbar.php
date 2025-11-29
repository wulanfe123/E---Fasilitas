<?php
// ==========================
// NAVBAR PEMINJAM + NOTIF
// ==========================

// Pastikan session aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../../config/koneksi.php'; // SESUAIKAN kalau beda path

$id_user   = isset($_SESSION['id_user']) ? (int) $_SESSION['id_user'] : 0;
$nama_user = $_SESSION['nama_user'] ?? 'Peminjam';

$notifikasiList = [];
$jumlahNotif    = 0;

if ($id_user > 0) {
    /* 1) Ambil 10 notifikasi terbaru (untuk dropdown & modal) */
    $sqlList = "
        SELECT 
            id_notifikasi,
            id_pinjam,
            judul,
            pesan,
            tipe,
            is_read,
            created_at
        FROM notifikasi
        WHERE id_user = ?
        ORDER BY created_at DESC
        LIMIT 10
    ";
    if ($stmtList = $conn->prepare($sqlList)) {
        $stmtList->bind_param("i", $id_user);
        $stmtList->execute();
        $resList = $stmtList->get_result();
        while ($row = $resList->fetch_assoc()) {
            $notifikasiList[] = $row;
        }
        $stmtList->close();
    }

    /* 2) Hitung notifikasi belum dibaca (untuk badge angka merah) */
    $sqlCount = "SELECT COUNT(*) AS jml FROM notifikasi WHERE id_user = ? AND is_read = 0";
    if ($stmtCount = $conn->prepare($sqlCount)) {
        $stmtCount->bind_param("i", $id_user);
        $stmtCount->execute();
        $resCount = $stmtCount->get_result();
        if ($rowC = $resCount->fetch_assoc()) {
            $jumlahNotif = (int) ($rowC['jml'] ?? 0);
        }
        $stmtCount->close();
    }
}

// Untuk kasih class "active" di menu
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <img src="../assets/img/logo.png" alt="Logo" width="45" height="45" class="rounded-circle me-2">
            <span>E-Fasilitas</span>
        </a>

        <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                        <i class="bi bi-house-door-fill"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'fasilitas.php' ? 'active' : ''; ?>" href="fasilitas.php">
                        <i class="bi bi-building-fill"></i> Fasilitas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'peminjaman.php' ? 'active' : ''; ?>" href="peminjaman.php">
                        <i class="bi bi-calendar-check-fill"></i> Peminjaman
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'riwayat.php' ? 'active' : ''; ?>" href="riwayat.php">
                        <i class="bi bi-clock-history"></i> Riwayat
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav align-items-center nav-right-icons">
                <!-- ============ ICON NOTIFIKASI ============ -->
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative"
                       href="#"
                       id="notifDropdown"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">
                        <i class="bi bi-bell-fill fs-5"></i>

                        <?php if ($jumlahNotif > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $jumlahNotif; ?>
                            </span>
                        <?php endif; ?>
                    </a>

                    <!-- DROPDOWN RIWAYAT NOTIFIKASI -->
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2"
                        aria-labelledby="notifDropdown">
                        <li class="dropdown-header small text-muted px-3">
                            Notifikasi Terbaru
                        </li>

                        <?php if (empty($notifikasiList)): ?>
                            <li>
                                <span class="dropdown-item small text-muted">
                                    Belum ada notifikasi.
                                </span>
                            </li>
                        <?php else: ?>
                            <?php foreach ($notifikasiList as $n): ?>
                                <li>
                                    <a class="dropdown-item small"
                                       href="#"
                                       data-bs-toggle="modal"
                                       data-bs-target="#modalNotif<?= (int)$n['id_notifikasi']; ?>">
                                        <i class="bi bi-info-circle me-1"></i>
                                        <?= htmlspecialchars($n['judul']); ?><br>
                                        <small class="text-muted">
                                            <?= date('d-m-Y H:i', strtotime($n['created_at'])); ?>
                                        </small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </li>

                <!-- ============ DROPDOWN USER ============ -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center"
                       href="#"
                       id="userDropdown"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 me-2 text-white"></i>
                        <span class="text-white fw-semibold"><?= htmlspecialchars($nama_user); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2"
                        aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item text-danger fw-semibold" href="../auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>

            </ul>
        </div>
    </div>
</nav>

<?php if (!empty($notifikasiList)): ?>
    <?php foreach ($notifikasiList as $n): ?>
        <?php
        // Tentukan link tujuan sesuai tipe (peminjaman/pengembalian)
        $linkTujuan = '#';
        if ($n['tipe'] === 'peminjaman') {
            $linkTujuan = 'peminjaman_saya.php#pinjam' . (int)$n['id_pinjam'];
        } elseif ($n['tipe'] === 'pengembalian') {
            $linkTujuan = 'riwayat.php#kembali' . (int)$n['id_pinjam'];
        }
        ?>
        <!-- MODAL DETAIL NOTIFIKASI -->
        <div class="modal fade" id="modalNotif<?= (int)$n['id_notifikasi']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><?= htmlspecialchars($n['judul']); ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p><?= nl2br(htmlspecialchars($n['pesan'])); ?></p>
                        <small class="text-muted">
                            Diterima: <?= date('d-m-Y H:i', strtotime($n['created_at'])); ?>
                        </small>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <a href="<?= $linkTujuan; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i> Buka Halaman
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
