<?php
$namaUser = $_SESSION['nama'] ?? ($_SESSION['username'] ?? 'User');
$role     = $_SESSION['role'] ?? '';
$page     = basename($_SERVER['PHP_SELF']);
$kategori = $_GET['kategori'] ?? '';

$isFasilitasPage = ($page === 'daftar_fasilitas.php');
?>

<div id="layoutSidenav">
    <!-- SIDEBAR KIRI -->
    <div id="layoutSidenav_nav">
        <nav class="sb-sidenav">

            <!-- USER PANEL -->
            <div class="sidebar-user">
                <div class="sidebar-user-avatar superadmin-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name"><?= htmlspecialchars($namaUser); ?></div>
                    <div class="sidebar-user-role"><?= strtoupper($role ?: '-'); ?></div>
                </div>
            </div>

            <div class="sb-sidenav-menu">
                <div class="nav">

                    <div class="sb-sidenav-menu-heading">Menu Utama</div>

                    <!-- Dashboard -->
                    <a class="nav-link <?= $page === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                    </a>

                    <!-- Kelola Pengguna -->
                    <?php if ($role === 'super_admin'): ?>
                        <a class="nav-link <?= $page === 'kelola_pengguna.php' ? 'active' : '' ?>" href="kelola_pengguna.php">
                            <i class="fas fa-users-cog me-2"></i> Kelola Pengguna
                        </a>
                    <?php endif; ?>
                    <!-- ============================ -->
                    <!--       DAFTAR FASILITAS       -->
                    <!-- ============================ -->

                    <a class="nav-link d-flex justify-content-between align-items-center has-dropdown 
                        <?= $isFasilitasPage ? 'active' : '' ?>"
                       data-bs-toggle="collapse"
                       href="#menuFasilitas"
                       role="button"
                       aria-expanded="<?= $isFasilitasPage ? 'true' : 'false' ?>"
                       aria-controls="menuFasilitas">

                        <span>
                            <i class="fas fa-building me-2"></i> Daftar Fasilitas
                        </span>

                     <!-- Chevron di ujung kanan -->
                      <span class="chevron-right">
                        <i class=" <?= $isFasilitasPage ? 'rotate' : '' ?>"></i>
                    </span>
                </a>

                    <div class="collapse submenu <?= $isFasilitasPage ? 'show' : '' ?>" id="menuFasilitas">
                        <a class="nav-link sub-link <?= $kategori === 'kendaraan' ? 'active-sub' : '' ?>"
                           href="daftar_fasilitas.php?kategori=kendaraan">
                            <span class="submenu-dot"></span> Kendaraan
                        </a>

                        <a class="nav-link sub-link <?= $kategori === 'ruangan' ? 'active-sub' : '' ?>"
                           href="daftar_fasilitas.php?kategori=ruangan">
                            <span class="submenu-dot"></span> Ruangan
                        </a>

                        <a class="nav-link sub-link <?= $kategori === 'pendukung' ? 'active-sub' : '' ?>"
                           href="daftar_fasilitas.php?kategori=pendukung">
                            <span class="submenu-dot"></span> Pendukung
                        </a>
                    </div>


                    <!-- Menu lain -->
                    <a class="nav-link <?= $page === 'peminjaman.php' ? 'active' : '' ?>" href="peminjaman.php">
                        <i class="fas fa-hand-holding me-2"></i> Peminjaman
                    </a>
                    <a class="nav-link <?= $page === 'pengembalian.php' ? 'active' : '' ?>" href="pengembalian.php">
                        <i class="fas fa-undo-alt me-2"></i> Pengembalian
                    </a>
                    <a class="nav-link <?= $page === 'tindaklanjut.php' ? 'active' : '' ?>" href="tindaklanjut.php">
                        <i class="fas fa-tools me-2"></i> Tindak Lanjut
                    </a>
                    <a class="nav-link <?= $page === 'laporan.php' ? 'active' : '' ?>" href="laporan.php">
                        <i class="fas fa-chart-line me-2"></i> Laporan
                    </a>

                </div>
            </div>

            <!-- FOOTER SIDEBAR -->
            <div class="sb-sidenav-footer">
                <div class="small">Login sebagai:</div>
                <?= strtoupper($role) ?>
            </div>
        </nav>
    </div>

    <!-- KONTEN -->
    <div id="layoutSidenav_content">
        <main class="container-fluid px-4 mt-4">
