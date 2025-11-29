<?php
session_start();
include '../config/koneksi.php';
require '../includes/auth_check.php';

// Hanya super_admin & bagian_umum yang boleh akses dashboard admin
require_role(['super_admin', 'bagian_umum']);

$pageTitle = "Dashboard Admin";

// ========================= GRAFIK PEMINJAMAN & KONDISI =========================
$tahunSekarang = date('Y');

// -------- PEMINJAMAN PER BULAN --------
$bulanLabelsPeminjaman = [];
$peminjamanData        = [];

$qPeminjaman = $conn->query("
    SELECT 
        MONTH(tanggal_mulai) AS bulan,
        COUNT(*) AS total
    FROM peminjaman
    WHERE YEAR(tanggal_mulai) = $tahunSekarang
    GROUP BY MONTH(tanggal_mulai)
    ORDER BY bulan ASC
");

if ($qPeminjaman) {
    while ($row = $qPeminjaman->fetch_assoc()) {
        $namaBulan = date('M', mktime(0, 0, 0, $row['bulan'], 10)); // Jan, Feb, dst
        $bulanLabelsPeminjaman[] = $namaBulan;
        $peminjamanData[]        = (int) $row['total'];
    }
}

// -------- KONDISI PENGEMBALIAN PER BULAN --------
$bulanLabelsKondisi = [];
$dataKondisiBagus   = [];
$dataKondisiRusak   = [];

$qKondisi = $conn->query("
    SELECT 
        MONTH(tgl_kembali) AS bulan,
        SUM(CASE WHEN kondisi = 'bagus' THEN 1 ELSE 0 END) AS bagus,
        SUM(CASE WHEN kondisi = 'rusak' THEN 1 ELSE 0 END) AS rusak
    FROM pengembalian
    WHERE YEAR(tgl_kembali) = $tahunSekarang
    GROUP BY MONTH(tgl_kembali)
    ORDER BY bulan ASC
");

if ($qKondisi) {
    while ($row = $qKondisi->fetch_assoc()) {
        $namaBulan = date('M', mktime(0, 0, 0, $row['bulan'], 10));
        $bulanLabelsKondisi[] = $namaBulan;
        $dataKondisiBagus[]   = (int) ($row['bagus'] ?? 0);
        $dataKondisiRusak[]   = (int) ($row['rusak'] ?? 0);
    }
}

// ========================= NOTIFIKASI SUPER ADMIN / BAGIAN UMUM =========================
$notifPeminjaman        = [];
$notifRusak             = [];
$jumlahNotifPeminjaman  = 0;
$jumlahNotifRusak       = 0;
$jumlahNotif            = 0;

// Peminjaman baru (status usulan)
$qNotifPeminjaman = $conn->query("
    SELECT 
        p.id_pinjam,
        u.nama,
        p.tanggal_mulai
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.status = 'usulan'
    ORDER BY p.id_pinjam DESC
    LIMIT 5
");
if ($qNotifPeminjaman) {
    while ($row = $qNotifPeminjaman->fetch_assoc()) {
        $notifPeminjaman[] = $row;
    }
}
$jumlahNotifPeminjaman = count($notifPeminjaman);

// Pengembalian dengan kondisi rusak
$qNotifRusak = $conn->query("
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
");
if ($qNotifRusak) {
    while ($row = $qNotifRusak->fetch_assoc()) {
        $notifRusak[] = $row;
    }
}
$jumlahNotifRusak = count($notifRusak);

// Total untuk badge di icon bell
$jumlahNotif = $jumlahNotifPeminjaman + $jumlahNotifRusak;

// ========================= STATISTIK CEPAT DASHBOARD =========================
$statUsers        = 0;
$statFasilitas    = 0;
$statPeminjaman   = 0;
$statPengembalian = 0;
$statTindakLanjut = 0;
$statLaporan      = 0;

// Total pengguna
if ($res = $conn->query("SELECT COUNT(*) AS total FROM users")) {
    $row       = $res->fetch_assoc();
    $statUsers = (int) ($row['total'] ?? 0);
}

// Total fasilitas
if ($res = $conn->query("SELECT COUNT(*) AS total FROM fasilitas")) {
    $row           = $res->fetch_assoc();
    $statFasilitas = (int) ($row['total'] ?? 0);
}

// Total peminjaman
if ($res = $conn->query("SELECT COUNT(*) AS total FROM peminjaman")) {
    $row            = $res->fetch_assoc();
    $statPeminjaman = (int) ($row['total'] ?? 0);
}

// Total pengembalian
if ($res = $conn->query("SELECT COUNT(*) AS total FROM pengembalian")) {
    $row              = $res->fetch_assoc();
    $statPengembalian = (int) ($row['total'] ?? 0);
}

// Total tindak lanjut
if ($res = $conn->query("SELECT COUNT(*) AS total FROM tindaklanjut")) {
    $row             = $res->fetch_assoc();
    $statTindakLanjut = (int) ($row['total'] ?? 0);
}

// Laporan = total aktivitas (peminjaman + pengembalian)
$statLaporan = $statPeminjaman + $statPengembalian;

// ========================= MULAI TAMPILAN (INCLUDE TEMPLATE) =========================
include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
include '../includes/admin/sidebar.php';
?>

    <!-- ====== KONTEN UTAMA DASHBOARD ====== -->

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="fw-bold text-dark mb-1">Dashboard Admin</h1>
            <p class="page-header-subtitle mb-0">
                Ringkasan aktivitas peminjaman, pengembalian, tindak lanjut, dan kondisi fasilitas kampus.
            </p>
        </div>
    </div>

    <!-- KARTU STATISTIK UTAMA -->
    <div class="row mb-4">
        <!-- Pengguna -->
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card bg-primary text-white h-100 quick-card">
                <div class="card-body">
                    <div>
                        <div class="fw-semibold mb-1">
                            <i class="fas fa-users me-2"></i> Pengguna
                        </div>
                        <div class="fs-2 fw-bold"><?= $statUsers; ?></div>
                        <small class="opacity-75">Total akun terdaftar</small>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a class="text-white small" href="kelola_pengguna.php">Kelola Pengguna</a>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </div>

        <!-- Fasilitas -->
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card bg-danger text-white h-100 quick-card">
                <div class="card-body">
                    <div>
                        <div class="fw-semibold mb-1">
                            <i class="fas fa-building me-2"></i> Fasilitas
                        </div>
                        <div class="fs-2 fw-bold"><?= $statFasilitas; ?></div>
                        <small class="opacity-75">Total fasilitas terdaftar</small>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a class="text-white small" href="daftar_fasilitas.php">Kelola Fasilitas</a>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </div>

        <!-- Peminjaman -->
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card bg-success text-white h-100 quick-card">
                <div class="card-body">
                    <div>
                        <div class="fw-semibold mb-1">
                            <i class="fas fa-hand-holding me-2"></i> Peminjaman
                        </div>
                        <div class="fs-2 fw-bold"><?= $statPeminjaman; ?></div>
                        <small class="opacity-75">Total peminjaman tercatat</small>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a class="text-white small" href="peminjaman.php">Kelola Peminjaman</a>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Pengembalian -->
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card bg-warning text-white h-100 quick-card">
                <div class="card-body">
                    <div>
                        <div class="fw-semibold mb-1">
                            <i class="fas fa-undo-alt me-2"></i> Pengembalian
                        </div>
                        <div class="fs-2 fw-bold"><?= $statPengembalian; ?></div>
                        <small class="opacity-75">Total pengembalian tercatat</small>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a class="text-white small" href="pengembalian.php">Periksa Pengembalian</a>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </div>

        <!-- Tindak Lanjut -->
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card bg-secondary text-white h-100 quick-card">
                <div class="card-body">
                    <div>
                        <div class="fw-semibold mb-1">
                            <i class="fas fa-tools me-2"></i> Tindak Lanjut
                        </div>
                        <div class="fs-2 fw-bold"><?= $statTindakLanjut; ?></div>
                        <small class="opacity-75">Kasus tindak lanjut kerusakan</small>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a class="text-white small" href="tindaklanjut.php">Kelola Tindak Lanjut</a>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </div>

        <!-- Laporan -->
        <div class="col-xl-4 col-md-6 mb-3">
            <div class="card bg-info text-white h-100 quick-card">
                <div class="card-body">
                    <div>
                        <div class="fw-semibold mb-1">
                            <i class="fas fa-chart-line me-2"></i> Laporan
                        </div>
                        <div class="fs-2 fw-bold"><?= $statLaporan; ?></div>
                        <small class="opacity-75">Total aktivitas (pinjam + kembali)</small>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a class="text-white small" href="laporan.php">Lihat Laporan</a>
                    <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- GRAFIK -->
    <div class="row">
        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-chart-line me-2"></i> Grafik Peminjaman per Bulan (<?= $tahunSekarang; ?>)
                </div>
                <div class="card-body">
                    <canvas id="chartPeminjaman" height="150"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <i class="fas fa-chart-bar me-2"></i> Grafik Kondisi Pengembalian per Bulan (<?= $tahunSekarang; ?>)
                </div>
                <div class="card-body">
                    <canvas id="chartKondisi" height="150"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT KHUSUS HALAMAN INI -->
    <!-- Chart.js (kalau di header/footer sudah ada, yang ini boleh dihapus, tapi aman walau double) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Toggle sidebar
        document.getElementById('sidebarToggle')?.addEventListener('click', function () {
            document.body.classList.toggle('sb-sidenav-toggled');
        });

        // Mark notifikasi sudah dibaca
        document.getElementById('notifDropdown')?.addEventListener('click', function () {
            const badge = document.getElementById('notifBadge');
            if (!badge) return;

            fetch('mark_read_superadmin.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({})
            })
                .then(res => res.json())
                .then(data => { badge.remove(); })
                .catch(err => { console.error(err); badge.remove(); });
        });

        // Data grafik dari PHP
        const bulanLabelsPeminjaman = <?= json_encode($bulanLabelsPeminjaman) ?>;
        const dataPeminjaman        = <?= json_encode($peminjamanData) ?>;

        const ctxPeminjaman = document.getElementById('chartPeminjaman');
        if (ctxPeminjaman) {
            new Chart(ctxPeminjaman, {
                type: 'line',
                data: {
                    labels: bulanLabelsPeminjaman,
                    datasets: [{
                        label: 'Jumlah Peminjaman (<?= $tahunSekarang; ?>)',
                        data: dataPeminjaman,
                        backgroundColor: 'rgba(13,202,240,0.15)',
                        borderColor: '#0dcaf0',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {beginAtZero: true, ticks: {precision: 0}}
                    }
                }
            });
        }

        const bulanLabelsKondisi = <?= json_encode($bulanLabelsKondisi) ?>;
        const dataKondisiBagus   = <?= json_encode($dataKondisiBagus) ?>;
        const dataKondisiRusak   = <?= json_encode($dataKondisiRusak) ?>;

        const ctxKondisi = document.getElementById('chartKondisi');
        if (ctxKondisi) {
            new Chart(ctxKondisi, {
                type: 'bar',
                data: {
                    labels: bulanLabelsKondisi,
                    datasets: [
                        { label: 'Bagus', data: dataKondisiBagus, backgroundColor: '#198754' },
                        { label: 'Rusak', data: dataKondisiRusak, backgroundColor: '#dc3545' }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }
    </script>

<?php
// FOOTER & penutup body/html
include '../includes/admin/footer.php';
?>
