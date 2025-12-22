<?php
session_start();
include '../config/koneksi.php';

// Cek login & role
if (
    !isset($_SESSION['id_user']) ||
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['super_admin', 'bagian_umum'], true)
) {
    header("Location: ../auth/login.php");
    exit;
}

$pageTitle    = "Dashboard Admin";
$currentPage  = "dashboard";
$id_user_login = (int) ($_SESSION['id_user'] ?? 0);
$nama_admin   = $_SESSION['nama'] ?? 'Admin';

if ($id_user_login <= 0) {
    // Jika session id_user tidak valid, paksa login ulang
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$tahunSekarang = (int) date('Y');

/* ================================
   STATISTIK DASHBOARD
   ================================ */
$statUsers        = 0;
$statFasilitas    = 0;
$statPeminjaman   = 0;
$statPengembalian = 0;
$statTindakLanjut = 0;
$statUsulan       = 0;

// Total pengguna
$sqlUsers = "SELECT COUNT(*) AS total FROM users";
if ($stmt = $conn->prepare($sqlUsers)) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $statUsers = (int) $row['total'];
    }
    $stmt->close();
}

// Total fasilitas
$sqlFasilitas = "SELECT COUNT(*) AS total FROM fasilitas";
if ($stmt = $conn->prepare($sqlFasilitas)) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $statFasilitas = (int) $row['total'];
    }
    $stmt->close();
}

// Total peminjaman
$sqlPeminjamanTotal = "SELECT COUNT(*) AS total FROM peminjaman";
if ($stmt = $conn->prepare($sqlPeminjamanTotal)) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $statPeminjaman = (int) $row['total'];
    }
    $stmt->close();
}

// Total usulan (pending)
$statusUsulan = 'usulan';
$sqlUsulan = "SELECT COUNT(*) AS total FROM peminjaman WHERE status = ?";
if ($stmt = $conn->prepare($sqlUsulan)) {
    $stmt->bind_param("s", $statusUsulan);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $statUsulan = (int) $row['total'];
    }
    $stmt->close();
}

// Total pengembalian
$sqlPengembalian = "SELECT COUNT(*) AS total FROM pengembalian";
if ($stmt = $conn->prepare($sqlPengembalian)) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $statPengembalian = (int) $row['total'];
    }
    $stmt->close();
}

// Total tindak lanjut
$sqlTindak = "SELECT COUNT(*) AS total FROM tindaklanjut";
if ($stmt = $conn->prepare($sqlTindak)) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $statTindakLanjut = (int) $row['total'];
    }
    $stmt->close();
}

/* ================================
   GRAFIK PEMINJAMAN PER BULAN
   ================================ */
$bulanLabelsPeminjaman = [];
$peminjamanData        = [];

$sqlGrafikPeminjaman = "
    SELECT 
        MONTH(tanggal_mulai) AS bulan,
        COUNT(*) AS total
    FROM peminjaman
    WHERE YEAR(tanggal_mulai) = ?
    GROUP BY MONTH(tanggal_mulai)
    ORDER BY bulan ASC
";

if ($stmt = $conn->prepare($sqlGrafikPeminjaman)) {
    $stmt->bind_param("i", $tahunSekarang);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $namaBulan               = date('M', mktime(0, 0, 0, (int)$row['bulan'], 10));
            $bulanLabelsPeminjaman[] = $namaBulan;
            $peminjamanData[]        = (int) $row['total'];
        }
    }
    $stmt->close();
}

/* ================================
   GRAFIK KONDISI PENGEMBALIAN
   ================================ */
$bulanLabelsKondisi = [];
$dataKondisiBaik    = [];
$dataKondisiRusak   = [];

$sqlGrafikKondisi = "
    SELECT 
        MONTH(tgl_kembali) AS bulan,
        SUM(CASE WHEN kondisi = 'baik' THEN 1 ELSE 0 END) AS baik,
        SUM(CASE WHEN kondisi = 'rusak' THEN 1 ELSE 0 END) AS rusak
    FROM pengembalian
    WHERE YEAR(tgl_kembali) = ?
    GROUP BY MONTH(tgl_kembali)
    ORDER BY bulan ASC
";

if ($stmt = $conn->prepare($sqlGrafikKondisi)) {
    $stmt->bind_param("i", $tahunSekarang);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $namaBulan            = date('M', mktime(0, 0, 0, (int)$row['bulan'], 10));
            $bulanLabelsKondisi[] = $namaBulan;
            $dataKondisiBaik[]    = (int) ($row['baik'] ?? 0);
            $dataKondisiRusak[]   = (int) ($row['rusak'] ?? 0);
        }
    }
    $stmt->close();
}

/* ================================
   PEMINJAMAN TERBARU
   ================================ */
$peminjamanTerbaru = [];

$sqlTerbaru = "
    SELECT 
        p.id_pinjam,
        p.tanggal_mulai,
        p.status,
        u.nama AS nama_peminjam,
        COALESCE(GROUP_CONCAT(f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    GROUP BY p.id_pinjam, p.tanggal_mulai, p.status, u.nama
    ORDER BY p.id_pinjam DESC
    LIMIT 5
";

if ($stmt = $conn->prepare($sqlTerbaru)) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $peminjamanTerbaru[] = $row;
        }
    }
    $stmt->close();
}

// Include header, sidebar, navbar
include '../includes/admin/header.php';
include '../includes/admin/sidebar.php';
?>

<!-- Main Content Area -->
<div id="layoutSidenav_content">
    
    <?php include '../includes/admin/navbar.php'; ?>

    <!-- Main Content -->
    <main>
        <div class="container-fluid">
            
            <!-- Page Header -->
            <div class="page-header-dashboard">
                <div class="page-header-content">
                    <h1 class="page-title">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard Admin
                    </h1>
                    <p class="page-subtitle">
                        Selamat datang, <strong><?= htmlspecialchars($nama_admin, ENT_QUOTES, 'UTF-8'); ?></strong>! 
                        Ringkasan aktivitas peminjaman fasilitas kampus.
                    </p>
                </div>
                <div class="page-header-actions">
                    <div class="badge-date">
                        <i class="far fa-calendar-alt"></i>
                        <span><?= date('d F Y'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <!-- Users Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stat-card-blue">
                        <div class="stat-card-body">
                            <div class="stat-card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-card-content">
                                <div class="stat-card-number"><?= number_format($statUsers); ?></div>
                                <div class="stat-card-label">Total Pengguna</div>
                            </div>
                        </div>
                        <a href="kelola_pengguna.php" class="stat-card-footer">
                            <span>Kelola</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Fasilitas Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stat-card-purple">
                        <div class="stat-card-body">
                            <div class="stat-card-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="stat-card-content">
                                <div class="stat-card-number"><?= number_format($statFasilitas); ?></div>
                                <div class="stat-card-label">Total Fasilitas</div>
                            </div>
                        </div>
                        <a href="daftar_fasilitas.php" class="stat-card-footer">
                            <span>Kelola</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Peminjaman Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stat-card-green">
                        <div class="stat-card-body">
                            <div class="stat-card-icon">
                                <i class="fas fa-hand-holding"></i>
                            </div>
                            <div class="stat-card-content">
                                <div class="stat-card-number"><?= number_format($statPeminjaman); ?></div>
                                <div class="stat-card-label">Total Peminjaman</div>
                            </div>
                        </div>
                        <a href="peminjaman.php" class="stat-card-footer">
                            <span>Kelola</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Usulan Card -->
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card stat-card-orange">
                        <div class="stat-card-body">
                            <div class="stat-card-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-card-content">
                                <div class="stat-card-number"><?= number_format($statUsulan); ?></div>
                                <div class="stat-card-label">Usulan Pending</div>
                            </div>
                        </div>
                        <a href="peminjaman.php?status=usulan" class="stat-card-footer">
                            <span>Proses</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row g-4 mb-4">
                <!-- Peminjaman Chart -->
                <div class="col-xl-8">
                    <div class="chart-card">
                        <div class="chart-card-header">
                            <div class="chart-card-title-group">
                                <h5 class="chart-card-title">
                                    <i class="fas fa-chart-line"></i>
                                    Grafik Peminjaman per Bulan
                                </h5>
                                <p class="chart-card-subtitle">Tahun <?= $tahunSekarang; ?></p>
                            </div>
                            <div class="chart-card-legend">
                                <span class="legend-item">
                                    <span class="legend-dot bg-primary"></span>
                                    Peminjaman
                                </span>
                            </div>
                        </div>
                        <div class="chart-card-body">
                            <canvas id="chartPeminjaman"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="col-xl-4">
                    <div class="quick-stats-card">
                        <div class="quick-stats-header">
                            <h5 class="quick-stats-title">
                                <i class="fas fa-chart-pie"></i>
                                Statistik Cepat
                            </h5>
                        </div>
                        <div class="quick-stats-body">
                            
                            <div class="quick-stat-item">
                                <div class="quick-stat-icon bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="quick-stat-content">
                                    <div class="quick-stat-value"><?= number_format($statPengembalian); ?></div>
                                    <div class="quick-stat-label">Pengembalian</div>
                                </div>
                            </div>

                            <div class="quick-stat-item">
                                <div class="quick-stat-icon bg-warning">
                                    <i class="fas fa-tools"></i>
                                </div>
                                <div class="quick-stat-content">
                                    <div class="quick-stat-value"><?= number_format($statTindakLanjut); ?></div>
                                    <div class="quick-stat-label">Tindak Lanjut</div>
                                </div>
                            </div>

                            <div class="quick-stat-item">
                                <div class="quick-stat-icon bg-info">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="quick-stat-content">
                                    <div class="quick-stat-value">
                                        <?php 
                                        $persentase = $statPeminjaman > 0 ? round(($statPengembalian / $statPeminjaman) * 100) : 0;
                                        echo $persentase . '%';
                                        ?>
                                    </div>
                                    <div class="quick-stat-label">Tingkat Pengembalian</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts & Activity -->
            <div class="row g-4 mb-4">
                <!-- Kondisi Chart -->
                <div class="col-xl-6">
                    <div class="chart-card">
                        <div class="chart-card-header">
                            <div class="chart-card-title-group">
                                <h5 class="chart-card-title">
                                    <i class="fas fa-chart-bar"></i>
                                    Kondisi Pengembalian
                                </h5>
                                <p class="chart-card-subtitle">Tahun <?= $tahunSekarang; ?></p>
                            </div>
                            <div class="chart-card-legend">
                                <span class="legend-item">
                                    <span class="legend-dot bg-success"></span>
                                    Baik
                                </span>
                                <span class="legend-item">
                                    <span class="legend-dot bg-danger"></span>
                                    Rusak
                                </span>
                            </div>
                        </div>
                        <div class="chart-card-body">
                            <canvas id="chartKondisi"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-xl-6">
                    <div class="activity-card">
                        <div class="activity-card-header">
                            <h5 class="activity-card-title">
                                <i class="fas fa-history"></i>
                                Peminjaman Terbaru
                            </h5>
                            <a href="peminjaman.php" class="activity-card-link">
                                Lihat Semua
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="activity-card-body">
                            <?php if (empty($peminjamanTerbaru)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>Belum ada peminjaman</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($peminjamanTerbaru as $item): 
                                    $status = strtolower($item['status']);
                                    $statusClass = 'secondary';
                                    $statusIcon  = 'circle';
                                    
                                    switch ($status) {
                                        case 'usulan':
                                            $statusClass = 'warning';
                                            $statusIcon  = 'clock';
                                            break;
                                        case 'diterima':
                                            $statusClass = 'success';
                                            $statusIcon  = 'check-circle';
                                            break;
                                        case 'ditolak':
                                            $statusClass = 'danger';
                                            $statusIcon  = 'times-circle';
                                            break;
                                        case 'selesai':
                                            $statusClass = 'info';
                                            $statusIcon  = 'check-double';
                                            break;
                                    }
                                ?>
                                    <div class="activity-item">
                                        <div class="activity-icon bg-<?= $statusClass; ?>">
                                            <i class="fas fa-<?= $statusIcon; ?>"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">
                                                #<?= (int)$item['id_pinjam']; ?> - <?= htmlspecialchars($item['nama_peminjam'], ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div class="activity-subtitle">
                                                <?= htmlspecialchars($item['fasilitas'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div class="activity-meta">
                                                <i class="far fa-calendar-alt"></i>
                                                <?= date('d M Y', strtotime($item['tanggal_mulai'])); ?>
                                            </div>
                                        </div>
                                        <span class="activity-badge bg-<?= $statusClass; ?>">
                                            <?= ucfirst($status); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="footer-admin">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>Pemfas</strong> &copy; <?= date('Y'); ?> - Sistem Peminjaman Fasilitas Kampus. | by WFE
            </div>
            <div>
                Version 1.0
            </div>
        </div>
    </footer>

</div>

<?php include '../includes/admin/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar state dari localStorage sudah di-handle di footer.js kamu
    const bulanLabelsPeminjaman = <?= json_encode($bulanLabelsPeminjaman) ?>;
    const dataPeminjaman        = <?= json_encode($peminjamanData) ?>;

    const ctxPeminjaman = document.getElementById('chartPeminjaman');
    if (ctxPeminjaman) {
        new Chart(ctxPeminjaman, {
            type: 'line',
            data: {
                labels: bulanLabelsPeminjaman.length > 0 ? bulanLabelsPeminjaman : ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
                datasets: [{
                    label: 'Jumlah Peminjaman',
                    data: dataPeminjaman.length > 0 ? dataPeminjaman : Array(12).fill(0),
                    backgroundColor: 'rgba(11, 44, 97, 0.1)',
                    borderColor: '#0b2c61',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#0b2c61',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(11, 44, 97, 0.9)',
                        padding: 12,
                        borderColor: '#ffb703',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#64748b' },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        ticks: { color: '#64748b' },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    const bulanLabelsKondisi = <?= json_encode($bulanLabelsKondisi) ?>;
    const dataKondisiBaik    = <?= json_encode($dataKondisiBaik) ?>;
    const dataKondisiRusak   = <?= json_encode($dataKondisiRusak) ?>;

    const ctxKondisi = document.getElementById('chartKondisi');
    if (ctxKondisi) {
        new Chart(ctxKondisi, {
            type: 'bar',
            data: {
                labels: bulanLabelsKondisi.length > 0 ? bulanLabelsKondisi : ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
                datasets: [
                    {
                        label: 'Baik',
                        data: dataKondisiBaik.length > 0 ? dataKondisiBaik : Array(12).fill(0),
                        backgroundColor: '#16a34a',
                        borderRadius: 8
                    },
                    {
                        label: 'Rusak',
                        data: dataKondisiRusak.length > 0 ? dataKondisiRusak : Array(12).fill(0),
                        backgroundColor: '#dc2626',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(11, 44, 97, 0.9)',
                        padding: 12,
                        borderColor: '#ffb703',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: '#64748b' },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        ticks: { color: '#64748b' },
                        grid: { display: false }
                    }
                }
            }
        });
    }
});
</script>
