<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../../config/koneksi.php';

// ===== FUNGSI MAP TIPE NOTIF KE 3 KATEGORI BESAR =====
if (!function_exists('efas_map_notif_type')) {
    function efas_map_notif_type(string $tipe_raw): string
    {
        $t = strtolower(trim($tipe_raw));

        // Semua variasi terkait peminjaman baru / konfirmasi
        if (
            $t === 'peminjaman' ||
            $t === 'konfirmasi' ||
            str_contains($t, 'pinjam')
        ) {
            return 'peminjaman';
        }

        // Semua variasi terkait pengembalian
        if (
            $t === 'pengembalian' ||
            str_contains($t, 'pengembalian') ||
            str_contains($t, 'dikembalikan')
        ) {
            return 'pengembalian';
        }

        // Semua variasi terkait komplain / rusak / tindak lanjut
        if (
            $t === 'tindaklanjut' ||
            $t === 'tindak_lanjut' ||
            $t === 'komplain_tindaklanjut' ||
            str_contains($t, 'komplain') ||
            str_contains($t, 'tindak') ||
            str_contains($t, 'rusak')
        ) {
            return 'tindaklanjut';
        }

        // Default dimasukkan ke peminjaman
        return 'peminjaman';
    }
}

// ===== VALIDASI SESSION & INPUT =====
$id_user_login = isset($_SESSION['id_user']) ? filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT) : 0;
$nama_user = 'User';
$username  = 'user';
$role      = $_SESSION['role'] ?? '';

// Ambil data user
if ($id_user_login > 0) {
    $sqlUser = "SELECT nama, username, role FROM users WHERE id_user = ? LIMIT 1";
    if ($stmtUser = $conn->prepare($sqlUser)) {
        $stmtUser->bind_param("i", $id_user_login);
        $stmtUser->execute();
        $resUser = $stmtUser->get_result();
        if ($row = $resUser->fetch_assoc()) {
            $nama_user = !empty($row['nama']) ? $row['nama'] : 'User';
            $username  = !empty($row['username']) ? $row['username'] : 'user';
            $role      = !empty($row['role']) ? $row['role'] : '';
        }
        $stmtUser->close();
    }
}
// ===== NOTIFIKASI PEMINJAM =====
$jumlahNotif    = 0;
$notifikasiList = [];

// 3 kategori besar
$notifPerTipe   = [
    'peminjaman'   => 0,
    'pengembalian' => 0,
    'tindaklanjut' => 0,
];

if ($id_user_login > 0) {

    // 1. HITUNG JUMLAH NOTIFIKASI BELUM DIBACA (SEMUA TIPE)
    if ($stmtCount = $conn->prepare("
        SELECT COUNT(*) AS total 
        FROM notifikasi 
        WHERE id_user = ? AND is_read = 0
    ")) {
        $stmtCount->bind_param("i", $id_user_login);
        $stmtCount->execute();
        $resultCount = $stmtCount->get_result();
        if ($row = $resultCount->fetch_assoc()) {
            $jumlahNotif = (int)$row['total'];
        }
        $stmtCount->close();
    }

    // 2. COUNTER PER TIPE (group by tipe_raw, lalu kita mapping)
    if ($stmtTipe = $conn->prepare("
        SELECT tipe, COUNT(*) AS jumlah
        FROM notifikasi
        WHERE id_user = ? AND is_read = 0
        GROUP BY tipe
    ")) {
        $stmtTipe->bind_param("i", $id_user_login);
        $stmtTipe->execute();
        $resultTipe = $stmtTipe->get_result();
        while ($row = $resultTipe->fetch_assoc()) {
            $tipe_raw  = $row['tipe'] ?? '';
            $jumlahRaw = (int)($row['jumlah'] ?? 0);

            $map = efas_map_notif_type($tipe_raw); // hasil: peminjaman / pengembalian / tindaklanjut

            if (isset($notifPerTipe[$map])) {
                $notifPerTipe[$map] += $jumlahRaw;
            }
        }
        $stmtTipe->close();
    }

    // 3. KOLOM TANGGAL (created_at)
    $dateColumn = 'created_at';

    // 4. LIST NOTIFIKASI TERBARU (10 terakhir) TANPA FILTER TIPE
    if ($stmtList = $conn->prepare("
        SELECT id_notifikasi, id_pinjam, judul, pesan, tipe, is_read, $dateColumn AS tanggal
        FROM notifikasi
        WHERE id_user = ?
        ORDER BY $dateColumn DESC
        LIMIT 10
    ")) {
        $stmtList->bind_param("i", $id_user_login);
        $stmtList->execute();
        $resultList = $stmtList->get_result();
        while ($row = $resultList->fetch_assoc()) {
            $notifikasiList[] = $row;
        }
        $stmtList->close();
    }
}

// ===== TANDAI NOTIFIKASI SUDAH DIBACA (AJAX) =====
if (isset($_GET['read'])) {
    $readParam = filter_input(INPUT_GET, 'read', FILTER_VALIDATE_INT);
    if ($readParam === 1 && $id_user_login > 0) {
        if ($stmtMarkRead = $conn->prepare("
            UPDATE notifikasi 
            SET is_read = 1 
            WHERE id_user = ?
        ")) {
            $stmtMarkRead->bind_param("i", $id_user_login);
            if ($stmtMarkRead->execute()) {
                echo 'OK';
            } else {
                http_response_code(500);
                echo 'ERROR';
            }
            $stmtMarkRead->close();
        }
        exit;
    }
}
?>

<style>
/* --- (CSS sama persis dengan punyamu tadi) --- */
.navbar {
    background: linear-gradient(135deg, #0b2c61 0%, #1e4a8a 100%);
    box-shadow: 0 2px 20px rgba(11, 44, 97, 0.3);
    padding: 0.75rem 0;
}

.navbar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white !important;
    font-weight: 700;
    font-size: 1.3rem;
    text-decoration: none;
}

.navbar-logo {
    width: 40px !important;
    height: 40px !important;
    object-fit: contain;
    border-radius: 8px;
    filter: drop-shadow(0 2px 8px rgba(255,255,255,0.3));
}

.brand-text {
    background: linear-gradient(135deg, #f7b731, #ffed4a);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.nav-link {
    color: rgba(255,255,255,0.8) !important;
    font-weight: 500;
    padding: 0.75rem 1.25rem !important;
    border-radius: 10px;
    margin: 0 4px;
    transition: all 0.3s ease;
}

.nav-link:hover, .nav-link.active {
    color: #f7b731 !important;
    background: rgba(247, 183, 49, 0.1);
    transform: translateY(-2px);
}

.navbar-toggler { border: none; padding: 4px 8px; }

.btn-notif {
    position: relative;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 25px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.btn-notif:hover {
    background: rgba(247, 183, 49, 0.2);
    border-color: #f7b731;
    transform: scale(1.05);
}

.notif-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notif-dropdown {
    min-width: 400px;
    max-height: 500px;
    overflow-y: auto;
    border: none;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    border-radius: 16px;
    margin-top: 12px;
}

.notif-item {
    padding: 16px 20px;
    border-radius: 12px;
    margin: 4px 8px;
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
    display: flex;
}

.notif-item.unread {
    background: linear-gradient(90deg, rgba(247,183,49,0.1), transparent);
    border-left-color: #f7b731;
}

.notif-item:hover {
    background: rgba(247,183,49,0.1);
    transform: translateX(8px);
}

.notif-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    flex-shrink: 0;
    font-size: 1.2rem;
}

.notif-icon.peminjaman {
    background: rgba(40, 167, 69, 0.2);
    color: #28a745;
}

.notif-icon.pengembalian {
    background: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}

.notif-icon.tindaklanjut {
    background: rgba(220, 53, 69, 0.2);
    color: #dc3545;
}

.notif-content {
    flex: 1;
    min-width: 0;
}

.notif-content strong {
    color: #0b2c61;
    display: block;
    margin-bottom: 4px;
}

.notif-content small {
    color: #6c757d;
}

.btn-profile {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 0.5rem 1.25rem;
    border-radius: 25px;
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-profile:hover {
    background: rgba(247, 183, 49, 0.2);
    border-color: #f7b731;
    transform: scale(1.02);
}

@media (max-width: 768px) {
    .navbar-brand { font-size: 1.1rem; }
    .navbar-logo  { width: 32px !important; height: 32px !important; }
    .notif-dropdown { min-width: 320px; }
}
</style>

<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <img src="../assets/img/Logo.png" alt="Logo" class="navbar-logo">
            <span class="brand-text">E-Fasilitas</span>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="bi bi-house-door-fill me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="fasilitas.php">
                        <i class="bi bi-building me-1"></i> Fasilitas
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="peminjaman_saya.php">
                        <i class="bi bi-clipboard-check me-1"></i> Peminjaman Saya
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="riwayat.php">
                        <i class="bi bi-clock-history me-1"></i> Riwayat
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2">
                <!-- NOTIFIKASI PEMINJAM -->
                <div class="dropdown">
                    <button class="btn-notif" id="btnNotifBorrower" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-bell-fill"></i>
                        <?php if ($jumlahNotif > 0): ?>
                            <span class="notif-badge"><?= $jumlahNotif; ?></span>
                        <?php endif; ?>
                    </button>
                    
                    <ul class="dropdown-menu dropdown-menu-end notif-dropdown">
                        <li class="dropdown-header p-3 border-bottom">
                            <strong>Notifikasi Peminjaman</strong>
                            <?php if ($jumlahNotif > 0): ?>
                                <div class="mt-2 small">
                                    <span class="badge bg-success me-1"><?= $notifPerTipe['peminjaman']; ?></span> Peminjaman
                                    <span class="badge bg-warning text-dark ms-2 me-1"><?= $notifPerTipe['pengembalian']; ?></span> Pengembalian
                                    <span class="badge bg-danger ms-2 me-1"><?= $notifPerTipe['tindaklanjut']; ?></span> Tindak Lanjut
                                    <span class="badge bg-primary ms-2"><?= $jumlahNotif; ?> Total</span>
                                </div>
                            <?php endif; ?>
                        </li>
                        
                        <?php if (empty($notifikasiList)): ?>
                            <li class="text-center py-4 text-muted">
                                <i class="bi bi-bell-slash fa-2x mb-2 d-block"></i>
                                <p class="mb-0">Tidak ada notifikasi</p>
                            </li>
                        <?php else: ?>
                            <?php foreach ($notifikasiList as $n): ?>
                                <?php
                                    $tipe_raw = $n['tipe'] ?? '';
                                    $tipe_map = efas_map_notif_type($tipe_raw);

                                    $iconClass = 'notif-icon ';
                                    $iconBi    = 'bi-bell';

                                    if ($tipe_map === 'peminjaman') {
                                        $iconClass .= 'peminjaman';
                                        $iconBi    = 'bi-clipboard-check';
                                    } elseif ($tipe_map === 'pengembalian') {
                                        $iconClass .= 'pengembalian';
                                        $iconBi    = 'bi-box-arrow-in-left';
                                    } elseif ($tipe_map === 'tindaklanjut') {
                                        $iconClass .= 'tindaklanjut';
                                        $iconBi    = 'bi-exclamation-triangle';
                                    }

                                    $pesan_short     = strlen($n['pesan']) > 60 ? substr($n['pesan'], 0, 60) . '...' : $n['pesan'];
                                    $waktu_formatted = date('d M Y H:i', strtotime($n['tanggal']));
                                ?>
                                <li>
                                    <a class="dropdown-item notif-item <?= $n['is_read'] == 0 ? 'unread' : '' ?>" 
                                       href="peminjaman_saya.php?id=<?= htmlspecialchars($n['id_pinjam']); ?>">
                                        <div class="<?= $iconClass ?>">
                                            <i class="bi <?= $iconBi ?>"></i>
                                        </div>
                                        <div class="notif-content">
                                            <strong><?= htmlspecialchars($n['judul']); ?></strong>
                                            <p class="mb-2 small"><?= htmlspecialchars($pesan_short); ?></p>
                                            <small><?= htmlspecialchars($waktu_formatted); ?></small>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-center fw-semibold text-primary py-3" 
                                   onclick="markAllAsReadBorrower(); return false;">
                                    <i class="bi bi-check-all me-2"></i>
                                    Tandai semua sudah dibaca
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- USER PROFILE -->
                <div class="dropdown">
                    <button class="btn-profile" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>
                        <span><?= htmlspecialchars($nama_user); ?></span>
                        <i class="bi bi-chevron-down ms-1"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="border-radius: 16px;">
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger py-2" href="../auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
function markAllAsReadBorrower() {
    fetch(window.location.pathname + '?read=1', {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
    })
    .then(data => {
        if (data === 'OK') {
            location.reload();
        } else {
            alert('Gagal memperbarui notifikasi');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat memperbarui notifikasi');
    });
}
</script>
