<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../../auth/login.php");  // ✅ FIXED
    exit;
}

include '../config/koneksi.php';          // ✅ FIXED
include '../config/notifikasi_helper.php';// ✅ FIXED

$id_user_login = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user_login === false || $id_user_login <= 0) {
    session_destroy();
    header("Location: ../../auth/login.php");  // ✅ FIXED
    exit;
}

$role_session = htmlspecialchars($_SESSION['role'] ?? 'peminjam', ENT_QUOTES, 'UTF-8');
$nama_user = htmlspecialchars($_SESSION['nama'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8');

// Set page variables
$pageTitle = 'Komunikasi Kerusakan';
$currentPage = 'komunikasi';

/* ==========================
   FLASH MESSAGE
   ========================== */
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/* ==========================
   VALIDASI PARAMETER GET
   ========================== */
$id_pinjam = filter_var($_GET['id_pinjam'] ?? 0, FILTER_VALIDATE_INT);
$id_tl = filter_var($_GET['id_tl'] ?? 0, FILTER_VALIDATE_INT);

if ($id_pinjam === false || $id_pinjam <= 0) {
    $_SESSION['error'] = "ID peminjaman tidak valid.";
    header("Location: riwayat.php");
    exit;
}

// Validasi id_tl jika ada
if ($id_tl === false) {
    $id_tl = 0;
}

/* ==========================
   VALIDASI: PEMINJAMAN HARUS MILIK PEMINJAM INI
   ========================== */
$detailPinjam = [
    'id_pinjam' => $id_pinjam,
    'nama_peminjam' => $nama_user,
    'tanggal_mulai' => null,
    'tanggal_selesai' => null,
];

$sqlP = "
    SELECT 
        p.tanggal_mulai, 
        p.tanggal_selesai, 
        u.nama AS nama_peminjam
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.id_pinjam = ? AND p.id_user = ?
    LIMIT 1
";

$stmtP = $conn->prepare($sqlP);
if (!$stmtP) {
    die("Query error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

$stmtP->bind_param("ii", $id_pinjam, $id_user_login);
$stmtP->execute();
$resP = $stmtP->get_result();

if ($rowP = $resP->fetch_assoc()) {
    $detailPinjam['nama_peminjam'] = htmlspecialchars($rowP['nama_peminjam'], ENT_QUOTES, 'UTF-8');
    $detailPinjam['tanggal_mulai'] = $rowP['tanggal_mulai'];
    $detailPinjam['tanggal_selesai'] = $rowP['tanggal_selesai'];
} else {
    $_SESSION['error'] = "Anda tidak berhak mengakses komunikasi peminjaman ini.";
    $stmtP->close();
    header("Location: riwayat.php");
    exit;
}
$stmtP->close();

/* ==========================
   DETAIL TINDAK LANJUT (JIKA ADA id_tl)
   ========================== */
$dataTl = null;
if ($id_tl > 0) {
    $sqlTl = "
        SELECT 
            tl.id_tindaklanjut,
            tl.tindakan,
            tl.deskripsi,
            tl.status,
            tl.tanggal
        FROM tindaklanjut tl
        JOIN pengembalian pg ON tl.id_kembali = pg.id_kembali
        WHERE tl.id_tindaklanjut = ?
          AND pg.id_pinjam = ?
        LIMIT 1
    ";
    
    $stmtTl = $conn->prepare($sqlTl);
    if ($stmtTl) {
        $stmtTl->bind_param("ii", $id_tl, $id_pinjam);
        $stmtTl->execute();
        $resTl = $stmtTl->get_result();
        if ($rowTl = $resTl->fetch_assoc()) {
            $dataTl = [
                'id_tindaklanjut' => (int)$rowTl['id_tindaklanjut'],
                'tindakan' => htmlspecialchars($rowTl['tindakan'], ENT_QUOTES, 'UTF-8'),
                'deskripsi' => htmlspecialchars($rowTl['deskripsi'], ENT_QUOTES, 'UTF-8'),
                'status' => htmlspecialchars($rowTl['status'], ENT_QUOTES, 'UTF-8'),
                'tanggal' => $rowTl['tanggal'],
            ];
        }
        $stmtTl->close();
    }
}

// Default jika tidak ketemu
if (!$dataTl) {
    $dataTl = [
        'id_tindaklanjut' => $id_tl,
        'tindakan' => 'Komunikasi kerusakan / klarifikasi peminjaman',
        'status' => 'proses',
        'deskripsi' => '',
        'tanggal' => null,
    ];
}

/* ==========================
   PROSES KIRIM CHAT (POST)
   ========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_chat'])) {
    $pesan = trim($_POST['pesan'] ?? '');
    
    // Validasi pesan
    if ($pesan === '') {
        $_SESSION['error'] = "Pesan chat tidak boleh kosong.";
    } elseif (mb_strlen($pesan) > 1000) {
        $_SESSION['error'] = "Pesan terlalu panjang (maksimal 1000 karakter).";
    } elseif (mb_strlen($pesan) < 3) {
        $_SESSION['error'] = "Pesan terlalu pendek (minimal 3 karakter).";
    } else {
        // Sanitasi pesan
        $pesan_clean = htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8');
        
        $sqlIns = "
            INSERT INTO komunikasi_kerusakan (
                id_pinjam,
                id_tindaklanjut,
                id_user,
                peran_pengirim,
                pesan,
                dibaca_admin,
                dibaca_peminjam,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, 0, 1, NOW())
        ";
        
        $stmtIns = $conn->prepare($sqlIns);
        if ($stmtIns) {
            $id_tindak_for_bind = $id_tl > 0 ? $id_tl : null;
            $peran_pengirim = 'peminjam';
            
            $stmtIns->bind_param(
                "iiiss",
                $id_pinjam,
                $id_tindak_for_bind,
                $id_user_login,
                $peran_pengirim,
                $pesan_clean
            );
            
            if ($stmtIns->execute()) {
                $_SESSION['success'] = "Pesan berhasil dikirim ke admin.";
            } else {
                $_SESSION['error'] = "Gagal mengirim pesan. Silakan coba lagi.";
                error_log("Error kirim chat: " . $stmtIns->error);
            }
            $stmtIns->close();
        } else {
            $_SESSION['error'] = "Gagal menyiapkan query kirim chat.";
        }
    }
    
    // Redirect untuk menghindari resubmit form
    $redir = "komunikasi_tindaklanjut.php?id_pinjam=" . $id_pinjam;
    if ($id_tl > 0) {
        $redir .= "&id_tl=" . $id_tl;
    }
    header("Location: " . $redir);
    exit;
}

$listChat = [];
$sqlChat = "
    SELECT 
        ck.pesan,
        ck.peran_pengirim,
        ck.created_at,
        ck.dibaca_admin,
        ck.dibaca_peminjam,
        u.nama
    FROM komunikasi_kerusakan ck
    JOIN users u ON ck.id_user = u.id_user
    WHERE ck.id_pinjam = ?
";

$types = "i";
$params = [$id_pinjam];

if ($id_tl > 0) {
    $sqlChat .= " AND (ck.id_tindaklanjut = ? OR ck.id_tindaklanjut IS NULL)";
    $types .= "i";
    $params[] = $id_tl;
}

$sqlChat .= " ORDER BY ck.created_at ASC";

$stmtChat = $conn->prepare($sqlChat);
if ($stmtChat) {
    $stmtChat->bind_param($types, ...$params);
    $stmtChat->execute();
    $resChat = $stmtChat->get_result();
    while ($row = $resChat->fetch_assoc()) {
        $listChat[] = [
            'pesan' => htmlspecialchars($row['pesan'], ENT_QUOTES, 'UTF-8'),
            'peran_pengirim' => htmlspecialchars($row['peran_pengirim'], ENT_QUOTES, 'UTF-8'),
            'created_at' => $row['created_at'],
            'dibaca_admin' => (int)$row['dibaca_admin'],
            'dibaca_peminjam' => (int)$row['dibaca_peminjam'],
            'nama' => htmlspecialchars($row['nama'], ENT_QUOTES, 'UTF-8'),
        ];
    }
    $stmtChat->close();
}

// Update status dibaca untuk peminjam
if (!empty($listChat)) {
    $sqlUpdate = "
        UPDATE komunikasi_kerusakan 
        SET dibaca_peminjam = 1 
        WHERE id_pinjam = ? 
        AND peran_pengirim = 'admin' 
        AND dibaca_peminjam = 0
    ";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    if ($stmtUpdate) {
        $stmtUpdate->bind_param("i", $id_pinjam);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    }
}

include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>
<br><br>
<style>
    /* ======== HERO SECTION ======== */
    .hero-section {
        background: linear-gradient(135deg, var(--danger-color) 0%, #ef4444 100%);
        padding: 60px 0;
        color: white;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 50%;
        height: 100%;
        background: url('../assets/img/gedung.jpg') center/cover no-repeat;
        opacity: 0.1;
    }

    .hero-section h2 {
        color: white !important;
        font-size: 2.5rem;
        font-weight: 800;
        text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
        position: relative;
        z-index: 2;
    }

    .hero-section p {
        color: rgba(255, 255, 255, 0.95) !important;
        font-size: 1.1rem;
        font-weight: 300;
        position: relative;
        z-index: 2;
    }

    .hero-badge {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.4);
        color: white;
        padding: 10px 20px;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        display: inline-block;
        margin-top: 10px;
        position: relative;
        z-index: 2;
    }

    /* ======== CHAT PAGE ======== */
    .chat-page-wrapper {
        max-width: 1100px;
        margin: 0 auto 40px;
    }

    .chat-info-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(11, 44, 97, 0.1);
        border: none;
        overflow: hidden;
    }

    .chat-info-card .card-body {
        padding: 25px;
    }

    /* ======== CHAT BOX ======== */
    .chat-box {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 20px;
        padding: 25px;
        max-height: 500px;
        overflow-y: auto;
        border: 2px solid var(--border-color);
        position: relative;
    }

    .chat-box::-webkit-scrollbar {
        width: 8px;
    }

    .chat-box::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 10px;
    }

    .chat-box::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border-radius: 10px;
    }

    /* ======== CHAT ITEMS ======== */
    .chat-item {
        margin-bottom: 18px;
        display: flex;
        animation: fadeInUp 0.3s ease;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .chat-bubble {
        border-radius: 18px;
        padding: 12px 18px;
        max-width: 75%;
        font-size: 0.95rem;
        position: relative;
        word-wrap: break-word;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    /* Chat dari admin/kiri */
    .chat-left {
        justify-content: flex-start;
    }

    .chat-left .chat-bubble {
        background: white;
        border: 2px solid var(--border-color);
        border-bottom-left-radius: 4px;
    }

    /* Chat dari peminjam/kanan */
    .chat-right {
        justify-content: flex-end;
    }

    .chat-right .chat-bubble {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border-bottom-right-radius: 4px;
    }

    .chat-name {
        font-size: 0.85rem;
        font-weight: 700;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .chat-badge {
        font-size: 0.7rem;
        padding: 3px 10px;
        border-radius: 12px;
        background: rgba(0, 0, 0, 0.1);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .chat-left .chat-badge {
        background: var(--warning-color);
        color: white;
    }

    .chat-right .chat-badge {
        background: rgba(255, 255, 255, 0.3);
        color: white;
    }

    .chat-meta {
        font-size: 0.75rem;
        opacity: 0.7;
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .chat-empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--muted-text);
    }

    .chat-empty i {
        font-size: 4rem;
        opacity: 0.3;
        display: block;
        margin-bottom: 15px;
    }

    /* ======== CHAT FORM ======== */
    .chat-form {
        margin-top: 20px;
    }

    .chat-form textarea {
        resize: vertical;
        min-height: 70px;
        max-height: 150px;
        border: 2px solid var(--border-color);
        border-radius: 15px;
        padding: 15px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .chat-form textarea:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(11, 44, 97, 0.15);
    }

    .chat-form .btn-danger {
        background: linear-gradient(135deg, var(--danger-color), #ef4444);
        border: none;
        border-radius: 15px;
        padding: 15px 30px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
    }

    .chat-form .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(220, 38, 38, 0.3);
    }

    /* ======== INFO SECTION ======== */
    .info-detail {
        font-size: 0.9rem;
        line-height: 1.8;
    }

    .info-detail strong {
        color: var(--primary-color);
    }

    /* ======== BADGES ======== */
    .badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bg-warning {
        background: linear-gradient(135deg, var(--warning-color), #fbbf24) !important;
        color: white !important;
    }

    .bg-success {
        background: linear-gradient(135deg, var(--success-color), #22c55e) !important;
    }

    .bg-secondary {
        background: linear-gradient(135deg, #6b7280, #9ca3af) !important;
    }

    /* ======== BUTTONS ======== */
    .btn-outline-secondary {
        border: 2px solid var(--border-color);
        color: var(--muted-text);
        border-radius: 12px;
        padding: 8px 20px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    /* ======== ALERTS ======== */
    .alert {
        border-radius: 15px;
        border: none;
        padding: 18px 24px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .alert-success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
        border-left: 4px solid var(--success-color);
    }

    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        border-left: 4px solid var(--danger-color);
    }

    /* ======== RESPONSIVE ======== */
    @media (max-width: 768px) {
        .hero-section h2 {
            font-size: 2rem;
        }

        .hero-section p {
            font-size: 1rem;
        }

        .chat-bubble {
            max-width: 85%;
        }

        .chat-box {
            max-height: 400px;
            padding: 18px;
        }

        .chat-info-card .card-body {
            padding: 20px;
        }

        .chat-form .btn-danger {
            width: 100%;
            margin-top: 10px;
        }
    }
</style>

<!-- HERO SECTION -->
<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-3" data-aos="fade-up">
            <i class="bi bi-chat-dots-fill me-2"></i>Komunikasi Kerusakan
        </h2>
        <p class="mb-2" data-aos="fade-up" data-aos-delay="100">
            Ruang chat antara kamu dan admin terkait kerusakan fasilitas atau tindak lanjut perbaikan
        </p>
        <span class="hero-badge" data-aos="fade-up" data-aos-delay="200">
            <i class="bi bi-info-circle me-1"></i>
            Gunakan dengan bahasa yang sopan & jelas
        </span>
    </div>
</section>

<div class="container flex-grow-1 mb-5">
    <div class="chat-page-wrapper">

        <!-- FLASH MESSAGE -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" data-aos="fade-down">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" data-aos="fade-down">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- INFO RINGKAS -->
        <div class="card chat-info-card mb-4" data-aos="fade-up">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8 mb-3 mb-md-0">
                        <div class="info-detail">
                            <h5 class="mb-2">
                                <i class="bi bi-clipboard-check me-2"></i>
                                Peminjaman #<?= $detailPinjam['id_pinjam'] ?>
                            </h5>
                            <p class="mb-1">
                                <strong>Peminjam:</strong> <?= $detailPinjam['nama_peminjam'] ?>
                            </p>
                            <?php if ($dataTl['id_tindaklanjut'] > 0): ?>
                                <p class="mb-1">
                                    <strong>Tindak Lanjut:</strong> #<?= $dataTl['id_tindaklanjut'] ?> - <?= $dataTl['tindakan'] ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($detailPinjam['tanggal_mulai'])): ?>
                                <p class="mb-1">
                                    <strong>Periode:</strong>
                                    <?= date('d M Y', strtotime($detailPinjam['tanggal_mulai'])) ?>
                                    - <?= date('d M Y', strtotime($detailPinjam['tanggal_selesai'])) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($dataTl['tanggal'])): ?>
                                <p class="mb-0">
                                    <strong>Tanggal Tindak Lanjut:</strong>
                                    <?= date('d M Y H:i', strtotime($dataTl['tanggal'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <?php
                            $badgeClass = 'secondary';
                            $statusTl = strtolower($dataTl['status'] ?? '');
                            $statusIcon = 'dash-circle';
                            
                            if ($statusTl === 'proses') {
                                $badgeClass = 'warning';
                                $statusIcon = 'hourglass-split';
                            } elseif ($statusTl === 'selesai') {
                                $badgeClass = 'success';
                                $statusIcon = 'check-circle';
                            }
                        ?>
                        <span class="badge bg-<?= $badgeClass ?> d-block mb-3">
                            <i class="bi bi-<?= $statusIcon ?> me-1"></i>
                            Status: <?= ucfirst($dataTl['status']) ?>
                        </span>
                        <a href="riwayat.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Kembali ke Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOX CHAT -->
        <div class="card chat-info-card" data-aos="fade-up" data-aos-delay="100">
            <div class="card-body">
                <h5 class="mb-3">
                    <i class="bi bi-chat-left-text me-2"></i>Percakapan
                </h5>
                
                <div class="chat-box mb-3" id="chatBox">
                    <?php if (empty($listChat)): ?>
                        <div class="chat-empty">
                            <i class="bi bi-chat-square-text"></i>
                            <p class="mb-0">
                                Belum ada percakapan. Tulis pesan pertama kamu di bawah untuk memulai komunikasi dengan admin.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($listChat as $c): 
                            $isMe = ($c['peran_pengirim'] === 'peminjam');
                            $sideCls = $isMe ? 'chat-right' : 'chat-left';
                        ?>
                            <div class="chat-item <?= $sideCls ?>">
                                <div class="chat-bubble">
                                    <div class="chat-name">
                                        <?= $c['nama'] ?>
                                        <span class="chat-badge">
                                            <?= $c['peran_pengirim'] ?>
                                        </span>
                                    </div>
                                    <div><?= nl2br($c['pesan']) ?></div>
                                    <div class="chat-meta">
                                        <i class="bi bi-clock"></i>
                                        <?= date('d M Y H:i', strtotime($c['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- FORM KIRIM CHAT -->
                <form method="post" class="chat-form" onsubmit="return validateMessage()">
                    <div class="input-group">
                        <textarea 
                            name="pesan" 
                            id="pesanInput"
                            class="form-control" 
                            rows="3"
                            placeholder="Tulis pesan ke admin di sini... (min. 3 karakter, maks. 1000 karakter)"
                            required
                            maxlength="1000"></textarea>
                        <button class="btn btn-danger" type="submit" name="kirim_chat">
                            <i class="bi bi-send-fill me-2"></i>Kirim
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2" id="charCounter">
                        <i class="bi bi-info-circle me-1"></i>
                        Pesan yang kamu kirim akan terlihat oleh admin/super admin pada halaman komunikasi kerusakan.
                    </small>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/peminjam/footer.php'; ?>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // Initialize AOS
    AOS.init({ 
        duration: 900, 
        once: true,
        offset: 100
    });

    // Auto scroll ke bawah saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        const box = document.getElementById('chatBox');
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    });

    // Validasi pesan sebelum submit
    function validateMessage() {
        const pesan = document.getElementById('pesanInput').value.trim();
        
        if (pesan.length < 3) {
            alert('⚠️ Pesan terlalu pendek! Minimal 3 karakter.');
            return false;
        }
        
        if (pesan.length > 1000) {
            alert('⚠️ Pesan terlalu panjang! Maksimal 1000 karakter.');
            return false;
        }
        
        return true;
    }

    // Character counter
    document.addEventListener('DOMContentLoaded', function() {
        const textarea = document.getElementById('pesanInput');
        const counter = document.getElementById('charCounter');
        
        if (textarea && counter) {
            function updateCounter() {
                const length = textarea.value.length;
                const baseText = 'Pesan yang kamu kirim akan terlihat oleh admin. ';
                counter.innerHTML = `<i class="bi bi-info-circle me-1"></i>${baseText}<strong>${length}/1000 karakter</strong>`;
                
                if (length > 900) {
                    counter.style.color = '#dc3545';
                } else if (length > 700) {
                    counter.style.color = '#f59e0b';
                } else {
                    counter.style.color = '#6b7280';
                }
            }
            
            textarea.addEventListener('input', updateCounter);
            updateCounter();
        }
    });

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        }
    });
</script>
