<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once __DIR__ . '/../../config/koneksi.php';

$id_user_login = isset($_SESSION['id_user']) ? (int)$_SESSION['id_user'] : 0;
$namaUser      = $_SESSION['nama'] ?? 'Admin';
$role          = $_SESSION['role'] ?? '';

/* =========================================================
   NOTIFIKASI (BADGE & LIST DROPDOWN) DENGAN PREPARED STATEMENT
   ========================================================= */
$jumlahNotif   = 0;
$notifBellList = [];

if ($id_user_login > 0) {

    // Hitung notif belum dibaca (angka di lonceng)
    $sqlCount = "
        SELECT COUNT(*) AS total 
        FROM notifikasi 
        WHERE id_user = ? AND is_read = 0
    ";
    if ($stmtC = $conn->prepare($sqlCount)) {
        $stmtC->bind_param("i", $id_user_login);
        $stmtC->execute();
        $resC = $stmtC->get_result();
        if ($resC && $row = $resC->fetch_assoc()) {
            $jumlahNotif = (int)$row['total'];
        }
        $stmtC->close();
    }

    // Ambil list notifikasi terbaru
    $sqlList = "
        SELECT 
            id_notifikasi,
            id_pinjam,
            judul,
            pesan,
            tipe,
            is_read,
            created_at AS tanggal
        FROM notifikasi
        WHERE id_user = ?
        ORDER BY created_at DESC
        LIMIT 10
    ";
    if ($stmtL = $conn->prepare($sqlList)) {
        $stmtL->bind_param("i", $id_user_login);
        $stmtL->execute();
        $resL = $stmtL->get_result();
        if ($resL) {
            while ($row = $resL->fetch_assoc()) {
                $notifBellList[] = $row;
            }
        }
        $stmtL->close();
    }
}

// Tandai semua notifikasi sebagai sudah dibaca (dipanggil via ?read=1)
if (isset($_GET['read']) && $_GET['read'] == 1 && $id_user_login > 0) {
    $sqlMarkRead = "
        UPDATE notifikasi 
        SET is_read = 1 
        WHERE id_user = ?
    ";
    if ($stmtM = $conn->prepare($sqlMarkRead)) {
        $stmtM->bind_param("i", $id_user_login);
        $stmtM->execute();
        $stmtM->close();
    }
    exit('OK');
}

/* =========================================================
   FUNGSI INISIAL NAMA USER
   ========================================================= */
function getUserInitials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

$userInitials = getUserInitials($namaUser);
?>

<style>
/* ===== HAMBURGER MENU ANIMATION ===== */
.btn-sidebar-toggle {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.btn-sidebar-toggle:hover {
    background: #f4f6fb;
    transform: scale(1.05);
}

.btn-sidebar-toggle:active {
    transform: scale(0.95);
}

.hamburger-icon {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    width: 24px;
    height: 18px;
    cursor: pointer;
}

.hamburger-icon span {
    display: block;
    height: 3px;
    width: 100%;
    background: #0b2c61;
    border-radius: 3px;
    transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

/* Animasi saat sidebar terbuka */
body.sb-sidenav-toggled .hamburger-icon span:nth-child(1) {
    transform: rotate(45deg) translate(6px, 6px);
}

body.sb-sidenav-toggled .hamburger-icon span:nth-child(2) {
    opacity: 0;
    transform: translateX(-20px);
}

body.sb-sidenav-toggled .hamburger-icon span:nth-child(3) {
    transform: rotate(-45deg) translate(6px, -6px);
}

/* ===== LOGO DI NAVBAR ===== */
.navbar-brand-admin {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--primary-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    margin-left: 10px;
}

.navbar-brand-admin:hover {
    color: var(--secondary-color);
    transform: scale(1.02);
}

.navbar-logo {
    width: 36px;
    height: 36px;
    object-fit: contain;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    transition: all 0.3s ease;
}

.navbar-brand-admin:hover .navbar-logo {
    transform: scale(1.1) rotate(5deg);
    filter: drop-shadow(0 4px 8px rgba(255, 183, 3, 0.4));
}

/* ===== RIPPLE EFFECT ===== */
@keyframes ripple {
    0% {
        transform: scale(0);
        opacity: 1;
    }
    100% {
        transform: scale(4);
        opacity: 0;
    }
}

.ripple-effect {
    position: absolute;
    border-radius: 50%;
    background: rgba(11, 44, 97, 0.3);
    width: 20px;
    height: 20px;
    animation: ripple 0.6s ease-out;
    pointer-events: none;
}

/* Responsive */
@media (max-width: 768px) {
    .navbar-logo {
        width: 32px;
        height: 32px;
    }
    
    .navbar-brand-admin {
        font-size: 1.1rem;
    }
}
</style>

<!-- Top Navbar -->
<nav class="navbar-admin">
    <div class="navbar-left">
        <!-- Hamburger Toggle Button dengan Animasi -->
        <button class="btn-sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
            <div class="hamburger-icon">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
        
        <!-- Brand dengan Logo -->
        <a class="navbar-brand-admin d-none d-md-flex" href="dashboard.php">
            <img src="../assets/img/Logo.png" alt="E-Fasilitas Logo" class="navbar-logo">
            <span>E-Fasilitas</span>
        </a>
    </div>

    <div class="navbar-user">
        
        <!-- Notifications -->
        <div class="navbar-notifications">
            <button class="btn-notification" id="notificationBtn" type="button">
                <i class="fas fa-bell"></i>
                <?php if ($jumlahNotif > 0): ?>
                    <span class="notification-badge"><?= $jumlahNotif; ?></span>
                <?php endif; ?>
            </button>

            <div class="dropdown-notifications" id="notificationDropdown">
                <div class="dropdown-notifications-header">
                    <i class="fas fa-bell me-2"></i>
                    Notifikasi
                    <?php if ($jumlahNotif > 0): ?>
                        <span class="badge bg-danger ms-2"><?= $jumlahNotif; ?></span>
                    <?php endif; ?>
                </div>

                <div class="dropdown-notifications-items">
                    <?php if (empty($notifBellList)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="far fa-bell-slash fa-2x mb-2"></i>
                            <p class="mb-0">Tidak ada notifikasi</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifBellList as $n): ?>
                            <?php
                            $iconClass = 'fa-info-circle text-primary';
                            $link      = 'dashboard.php';

                            if ($n['tipe'] === 'peminjaman') {
                                $iconClass = 'fa-hand-holding text-success';
                                $link      = 'peminjaman.php';
                            } elseif ($n['tipe'] === 'pengembalian') {
                                $iconClass = 'fa-undo-alt text-warning';
                                $link      = 'pengembalian.php';
                            } elseif ($n['tipe'] === 'tindaklanjut') {
                                $iconClass = 'fa-tools text-info';
                                $link      = 'tindaklanjut.php';
                            }

                            $isUnread = ($n['is_read'] == 0) ? 'unread' : '';
                            $waktu    = date('d M Y H:i', strtotime($n['tanggal']));
                            ?>
                            <a href="<?= $link; ?>" class="dropdown-notifications-item <?= $isUnread; ?>">
                                <div class="notification-title">
                                    <i class="fas <?= $iconClass; ?> me-2"></i>
                                    <?= htmlspecialchars($n['judul']); ?>
                                </div>
                                <div class="notification-text">
                                    <?= htmlspecialchars(substr($n['pesan'], 0, 60)); ?>...
                                </div>
                                <div class="notification-time">
                                    <i class="far fa-clock me-1"></i>
                                    <?= $waktu; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($notifBellList)): ?>
                    <div class="dropdown-notifications-footer">
                        <a href="#" onclick="markAllAsRead(); return false;">
                            Tandai semua sudah dibaca
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- User Info Dropdown -->
        <div class="dropdown">
            <button class="btn user-info" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar">
                    <?= $userInitials; ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($namaUser); ?></div>
                    <div class="user-role"><?= strtoupper($role); ?></div>
                </div>
                <i class="fas fa-chevron-down ms-2"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="../auth/logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>
                        Keluar
                    </a>
                </li>
            </ul>
        </div>

    </div>
</nav>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ===== SIDEBAR TOGGLE =====
    const sidebarToggle = document.getElementById('sidebarToggle');
    const body          = document.body;
    
    // Function untuk create ripple effect
    function createRipple(event) {
        const button = event.currentTarget;
        const ripple = document.createElement('span');
        const rect   = button.getBoundingClientRect();
        const size   = Math.max(rect.width, rect.height);
        const x      = event.clientX - rect.left - size / 2;
        const y      = event.clientY - rect.top - size / 2;
        
        ripple.style.width  = size + 'px';
        ripple.style.height = size + 'px';
        ripple.style.left   = x + 'px';
        ripple.style.top    = y + 'px';
        ripple.classList.add('ripple-effect');
        
        button.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 600);
    }
    
    // Toggle sidebar dengan animasi
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            createRipple(e);
            body.classList.toggle('sb-sidenav-toggled');
            
            // Simpan state ke localStorage
            if (body.classList.contains('sb-sidenav-toggled')) {
                localStorage.setItem('sb|sidebar-toggle', 'true');
            } else {
                localStorage.setItem('sb|sidebar-toggle', 'false');
            }
        });
    }
    
    // Load saved state dari localStorage
    if (localStorage.getItem('sb|sidebar-toggle') === 'true') {
        body.classList.add('sb-sidenav-toggled');
    }
    
    // ===== NOTIFICATION DROPDOWN =====
    const notificationBtn     = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationBtn && notificationDropdown) {
        // Toggle dropdown saat tombol diklik
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');

            // Panggil API tandai sudah dibaca
            markAllAsRead();
        });
        
        // Tutup dropdown saat klik di luar area
        document.addEventListener('click', function(e) {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('show');
            }
        });
    }
    
    // ===== HANDLE WINDOW RESIZE =====
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth >= 992) {
                if (body.classList.contains('sb-sidenav-toggled')) {
                    body.classList.remove('sb-sidenav-toggled');
                }
            }
        }, 250);
    });
});

// Fungsi untuk tandai semua sudah dibaca
function markAllAsRead() {
    fetch(window.location.pathname + '?read=1', {
        method: 'GET'
    })
    .then(response => response.text())
    .then(data => {
        if (data === 'OK') {
            // reload supaya badge/daftar ikut ter-update
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>
