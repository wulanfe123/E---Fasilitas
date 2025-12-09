<?php
// ambil dari header.php (kalau belum ada, fallback ke session)
$namaUser = $namaUser ?? ($_SESSION['nama'] ?? 'Admin');
$role     = $role     ?? ($_SESSION['role'] ?? '');
$page     = basename($_SERVER['PHP_SELF']);
$kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';

$isFasilitasPage = ($page === 'daftar_fasilitas.php');

// Hitung inisial dari nama
$words = explode(' ', $namaUser);
if (count($words) >= 2) {
    $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
} else {
    $initials = strtoupper(substr($namaUser, 0, 2));
}
?>

<!-- Sidebar -->
<div id="layoutSidenav_nav">
    <nav class="sb-sidenav">

        <!-- Sidebar Brand dengan Info User -->
        <div class="sidebar-brand">
            <a href="dashboard.php" class="sidebar-brand-link">
                <div class="sidebar-user-profile">
                    <div class="sidebar-user-avatar">
                        <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name">
                            <?= htmlspecialchars($namaUser, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="sidebar-user-role">
                            <span class="status-indicator"></span>
                            <?php
                            if ($role === 'super_admin') {
                                echo 'Super Admin';
                            } elseif ($role === 'bagian_umum') {
                                echo 'Bagian Umum';
                            } else {
                                echo ucwords(str_replace('_', ' ', htmlspecialchars($role, ENT_QUOTES, 'UTF-8')));
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Sidebar Menu -->
        <div class="sidebar-menu">

            <!-- Main Menu -->
            <div class="sidebar-menu-label">Dashboard</div>

            <div class="sidebar-item">
                <a class="sidebar-link <?= $page === 'dashboard.php' ? 'active' : ''; ?>"
                   href="dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <!-- Management -->
            <div class="sidebar-menu-label">Manajemen</div>

            <?php if ($role === 'super_admin'): ?>
                <div class="sidebar-item">
                    <a class="sidebar-link <?= $page === 'kelola_pengguna.php' ? 'active' : ''; ?>"
                       href="kelola_pengguna.php">
                        <i class="fas fa-users-cog"></i>
                        <span>Kelola Pengguna</span>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Fasilitas dengan Submenu -->
            <div class="sidebar-item">
                <a class="sidebar-link <?= $isFasilitasPage ? 'active' : ''; ?>"
                   data-bs-toggle="collapse"
                   href="#menuFasilitas"
                   role="button"
                   aria-expanded="<?= $isFasilitasPage ? 'true' : 'false'; ?>">
                    <i class="fas fa-building"></i>
                    <span>Daftar Fasilitas</span>
                    <i class="fas fa-chevron-down ms-auto" style="font-size: 0.8rem;"></i>
                </a>

                <div class="collapse <?= $isFasilitasPage ? 'show' : ''; ?>" id="menuFasilitas">
                    <div class="sidebar-submenu">
                        <a class="sidebar-link <?= $kategori === 'kendaraan' ? 'active' : ''; ?>"
                           href="daftar_fasilitas.php?kategori=kendaraan">
                            <i class="fas fa-car"></i>
                            <span>Kendaraan</span>
                        </a>
                        <a class="sidebar-link <?= $kategori === 'ruangan' ? 'active' : ''; ?>"
                           href="daftar_fasilitas.php?kategori=ruangan">
                            <i class="fas fa-door-open"></i>
                            <span>Ruangan</span>
                        </a>
                        <a class="sidebar-link <?= $kategori === 'pendukung' ? 'active' : ''; ?>"
                           href="daftar_fasilitas.php?kategori=pendukung">
                            <i class="fas fa-tools"></i>
                            <span>Pendukung</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Transaksi -->
            <div class="sidebar-menu-label">Transaksi</div>

            <div class="sidebar-item">
                <a class="sidebar-link <?= $page === 'peminjaman.php' ? 'active' : ''; ?>"
                   href="peminjaman.php">
                    <i class="fas fa-hand-holding"></i>
                    <span>Peminjaman</span>
                    <?php if (isset($statUsulan) && $statUsulan > 0): ?>
                        <span class="sidebar-link-badge"><?= (int)$statUsulan; ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="sidebar-item">
                <a class="sidebar-link <?= $page === 'pengembalian.php' ? 'active' : ''; ?>"
                   href="pengembalian.php">
                    <i class="fas fa-undo-alt"></i>
                    <span>Pengembalian</span>
                </a>
            </div>

            <div class="sidebar-item">
                <a class="sidebar-link <?= $page === 'tindaklanjut.php' ? 'active' : ''; ?>"
                   href="tindaklanjut.php">
                    <i class="fas fa-tools"></i>
                    <span>Tindak Lanjut</span>
                </a>
            </div>

            <!-- Laporan -->
            <div class="sidebar-menu-label">Laporan</div>

            <div class="sidebar-item">
                <a class="sidebar-link <?= $page === 'laporan.php' ? 'active' : ''; ?>"
                   href="laporan.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Laporan</span>
                </a>
            </div>

        </div>

    </nav>
</div>
