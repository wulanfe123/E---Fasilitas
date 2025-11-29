<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$namaUser = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');
$role     = $_SESSION['role'] ?? '';
?>

<nav class="sb-topnav navbar navbar-expand navbar-dark">

    <!-- BRAND / LOGO DI NAVBAR SAJA -->
    <a class="navbar-brand ps-3 d-flex align-items-center" href="dashboard.php">
        <img src="../assets/img/logo.png" alt="Logo" class="navbar-logo">
        <span class="fw-bold text-uppercase ms-2">E-Fasilitas</span>
    </a>

    <!-- Toggle Sidebar -->
    <button class="btn btn-link btn-sm text-white me-3" id="sidebarToggle">
        <i class="fas fa-bars fa-lg"></i>
    </button>

    <!-- MENU KANAN (NOTIF + PROFIL) -->
    <ul class="navbar-nav ms-auto align-items-center">

        <!-- Notifikasi -->
        <li class="nav-item dropdown mx-2">
            <a class="nav-link position-relative" id="notifDropdown" role="button" data-bs-toggle="dropdown">
                <i class="fas fa-bell fa-lg"></i>
                <?php if (!empty($jumlahNotif)): ?>
                    <span class="badge bg-danger notif-badge" id="notifBadge">
                        <?= $jumlahNotif ?>
                    </span>
                <?php endif; ?>
            </a>

            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li class="dropdown-header small fw-semibold text-muted">Notifikasi Terbaru</li>

                <?php if ($jumlahNotif == 0): ?>
                    <li><span class="dropdown-item small text-muted">Tidak ada notifikasi</span></li>
                <?php endif; ?>

                <?php foreach ($notifPeminjaman as $n): ?>
                    <li>
                        <a class="dropdown-item small" href="peminjaman.php#pinjam<?= $n['id_pinjam']; ?>">
                            <i class="fas fa-hand-holding me-2 text-success"></i>
                            Peminjaman #<?= $n['id_pinjam']; ?> dari <?= htmlspecialchars($n['nama']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php foreach ($notifRusak as $n): ?>
                    <li>
                        <a class="dropdown-item small" href="pengembalian.php#kembali<?= $n['id_kembali']; ?>">
                            <i class="fas fa-exclamation-circle me-2 text-warning"></i>
                            Pengembalian rusak #<?= $n['id_pinjam']; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </li>

        <!-- PROFIL + LOGOUT DI DROPDOWN -->
        <li class="nav-item dropdown mx-2">
            <a class="nav-link d-flex align-items-center dropdown-toggle text-white" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle fa-lg me-2"></i>
                <span class="fw-semibold"><?= htmlspecialchars($namaUser); ?></span>
            </a>

            <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                <li class="dropdown-header small text-muted">
                    <?= strtoupper($role ?: 'USER'); ?>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger fw-semibold" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Keluar
                    </a>
                </li>
            </ul>
        </li>
    </ul>
</nav>
