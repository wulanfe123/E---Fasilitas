<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$id_user_login = (int) ($_SESSION['id_user'] ?? 0);
$role_session  = $_SESSION['role'] ?? 'peminjam';
$nama_user     = $_SESSION['nama_user'] ?? 'Peminjam';

if ($id_user_login <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

/* ==========================
   FLASH MESSAGE
   ========================== */
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/* ==========================
   PARAMETER GET
   ========================== */
$id_pinjam = isset($_GET['id_pinjam']) ? (int) $_GET['id_pinjam'] : 0;
$id_tl     = isset($_GET['id_tl']) ? (int) $_GET['id_tl'] : 0; // optional

if ($id_pinjam <= 0) {
    $_SESSION['error'] = "Peminjaman tidak valid.";
    header("Location: riwayat_peminjaman.php");
    exit;
}

/* ==========================
   VALIDASI: PEMINJAMAN HARUS MILIK PEMINJAM INI
   ========================== */
$detailPinjam = [
    'id_pinjam'       => $id_pinjam,
    'nama_peminjam'   => $nama_user,
    'tanggal_mulai'   => null,
    'tanggal_selesai' => null,
];

$sqlP = "
    SELECT p.tanggal_mulai, p.tanggal_selesai, u.nama AS nama_peminjam
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.id_pinjam = ? AND p.id_user = ?
    LIMIT 1
";
if ($stmtP = $conn->prepare($sqlP)) {
    $stmtP->bind_param("ii", $id_pinjam, $id_user_login);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    if ($rowP = $resP->fetch_assoc()) {
        $detailPinjam['nama_peminjam']   = $rowP['nama_peminjam'];
        $detailPinjam['tanggal_mulai']   = $rowP['tanggal_mulai'];
        $detailPinjam['tanggal_selesai'] = $rowP['tanggal_selesai'];
    } else {
        // Bukan milik user ini atau tidak ada
        $_SESSION['error'] = "Anda tidak berhak mengakses komunikasi peminjaman ini.";
        $stmtP->close();
        header("Location: riwayat_peminjaman.php");
        exit;
    }
    $stmtP->close();
} else {
    $_SESSION['error'] = "Gagal mengambil data peminjaman.";
    header("Location: riwayat_peminjaman.php");
    exit;
}

/* ==========================
   (OPSIONAL) DETAIL TINDAK LANJUT (JIKA ADA id_tl)
   Pastikan tindak lanjut ini memang terkait
   dengan pengembalian peminjaman ini.
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
    if ($stmtTl = $conn->prepare($sqlTl)) {
        $stmtTl->bind_param("ii", $id_tl, $id_pinjam);
        $stmtTl->execute();
        $resTl  = $stmtTl->get_result();
        $dataTl = $resTl->fetch_assoc();
        $stmtTl->close();
    }
}

/* kalau tidak ketemu, jangan error — tetap boleh chat umum */
if (!$dataTl) {
    $dataTl = [
        'id_tindaklanjut' => $id_tl,
        'tindakan'        => 'Komunikasi kerusakan / klarifikasi peminjaman',
        'status'          => 'proses',
        'deskripsi'       => '',
        'tanggal'         => null,
    ];
}

/* ==========================
   PROSES KIRIM CHAT (POST)
   ========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_chat'])) {
    $pesan = trim($_POST['pesan'] ?? '');

    if ($pesan === '') {
        $_SESSION['error'] = "Pesan chat tidak boleh kosong.";
    } elseif (mb_strlen($pesan) > 1000) {
        $_SESSION['error'] = "Pesan terlalu panjang (maksimal 1000 karakter).";
    } else {
        $sqlIns = "
            INSERT INTO komunikasi_kerusakan (
                id_pinjam,
                id_tindaklanjut,
                id_kembali,
                id_user,
                peran_pengirim,
                pesan,
                dibaca_admin,
                dibaca_peminjam,
                created_at
            )
            VALUES (
                ?,           -- id_pinjam
                ?,           -- id_tindaklanjut (boleh NULL)
                NULL,        -- id_kembali (tidak dipakai di sini)
                ?,           -- id_user
                ?,           -- peran_pengirim
                ?,           -- pesan
                0,
                0,
                NOW()
            )
        ";

        if ($stmtIns = $conn->prepare($sqlIns)) {
            // kalau id_tl 0 → set NULL
            $id_tindak_for_bind = $id_tl > 0 ? $id_tl : null;
            $peran_pengirim     = 'peminjam';

            $stmtIns->bind_param(
                "iiiss",
                $id_pinjam,
                $id_tindak_for_bind,
                $id_user_login,
                $peran_pengirim,
                $pesan
            );

            if ($stmtIns->execute()) {
                $_SESSION['success'] = "Pesan berhasil dikirim.";
            } else {
                $_SESSION['error'] = "Gagal mengirim pesan: " . $stmtIns->error;
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

/* ==========================
   AMBIL RIWAYAT CHAT
   ========================== */
$listChat = [];

$sqlChat = "
    SELECT 
        ck.*,
        u.nama
    FROM komunikasi_kerusakan ck
    JOIN users u ON ck.id_user = u.id_user
    WHERE ck.id_pinjam = ?
";
$types  = "i";
$params = [$id_pinjam];

if ($id_tl > 0) {
    $sqlChat .= " AND ck.id_tindaklanjut = ?";
    $types   .= "i";
    $params[] = $id_tl;
}

$sqlChat .= " ORDER BY ck.created_at ASC";

if ($stmtChat = $conn->prepare($sqlChat)) {
    $stmtChat->bind_param($types, ...$params);
    $stmtChat->execute();
    $resChat = $stmtChat->get_result();
    while ($row = $resChat->fetch_assoc()) {
        $listChat[] = $row;
    }
    $stmtChat->close();
}

/* ==========================
   TEMPLATE PEMINJAM
   ========================== */
include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>

<style>
.chat-page-wrapper {
    max-width: 1100px;
    margin: 0 auto 40px;
}

.chat-info-card {
    border-radius: 14px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    border: 1px solid #e5e7eb;
}

.chat-box {
    background: #f9fafb;
    border-radius: 16px;
    padding: 18px;
    max-height: 480px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
}

.chat-item {
    margin-bottom: 12px;
    display: flex;
}

.chat-badge {
    font-size: .7rem;
    padding: 2px 7px;
    border-radius: 999px;
    background: #f3f4f6;
    color: #6b7280;
}

.chat-bubble {
    border-radius: 14px;
    padding: 8px 12px;
    max-width: 80%;
    font-size: .9rem;
    position: relative;
}

.chat-left .chat-bubble {
    background: #ffffff;
    border: 1px solid #e5e7eb;
}

.chat-right {
    justify-content: flex-end;
}

.chat-right .chat-bubble {
    background: #0d6efd;
    color: #ffffff;
}

.chat-name {
    font-size: .8rem;
    font-weight: 600;
    margin-bottom: 2px;
}

.chat-meta {
    font-size: .7rem;
    color: #9ca3af;
    margin-top: 3px;
}

.chat-empty {
    font-size: .9rem;
    color: #9ca3af;
}

.chat-form textarea {
    resize: vertical;
    min-height: 60px;
    max-height: 120px;
}

@media (max-width: 768px) {
    .chat-bubble {
        max-width: 100%;
    }
}
</style>

<!-- HERO SECTION -->
<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-2 text-danger">Komunikasi Kerusakan</h2>
        <p class="mb-1">
            Ruang chat antara kamu dan admin terkait kerusakan fasilitas atau tindak lanjut perbaikan.
        </p>
        <span class="hero-badge">
            <i class="bi bi-chat-dots-fill me-1"></i>
            Gunakan dengan bahasa yang sopan & jelas.
        </span>
    </div>
</section>

<div class="container flex-grow-1">
    <div class="chat-page-wrapper">

        <!-- FLASH MESSAGE -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- INFO RINGKAS -->
        <div class="card chat-info-card mb-3">
            <div class="card-body d-flex justify-content-between align-items-start flex-wrap">
                <div class="me-3 mb-2">
                    <div class="fw-semibold mb-1">
                        Peminjaman #<?= $detailPinjam['id_pinjam']; ?> 
                        · <?= htmlspecialchars($detailPinjam['nama_peminjam']); ?>
                    </div>
                    <div class="text-muted" style="font-size:.85rem;">
                        <?php if (!empty($dataTl['id_tindaklanjut'])): ?>
                            Tindak Lanjut #<?= (int)$dataTl['id_tindaklanjut']; ?> · 
                        <?php endif; ?>
                        Tindakan: <?= htmlspecialchars($dataTl['tindakan']); ?>
                        <?php if (!empty($detailPinjam['tanggal_mulai'])): ?>
                            <br>
                            Periode Pinjam:
                            <?= date('d M Y', strtotime($detailPinjam['tanggal_mulai'])); ?>
                            -
                            <?= date('d M Y', strtotime($detailPinjam['tanggal_selesai'])); ?>
                        <?php endif; ?>
                        <?php if (!empty($dataTl['tanggal'])): ?>
                            <br>
                            Tanggal Tindak Lanjut: 
                            <?= date('d M Y H:i', strtotime($dataTl['tanggal'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end mb-2">
                    <?php
                        $badgeClass = 'secondary';
                        $statusTl   = strtolower($dataTl['status'] ?? '');
                        if ($statusTl === 'proses')  $badgeClass = 'warning text-dark';
                        elseif ($statusTl === 'selesai') $badgeClass = 'success';
                    ?>
                    <span class="badge bg-<?= $badgeClass; ?> text-uppercase">
                        <?= htmlspecialchars($dataTl['status'] ?? '-'); ?>
                    </span>
                    <div class="mt-2">
                        <a href="riwayat_peminjaman.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali ke Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- BOX CHAT -->
        <div class="card chat-info-card mb-4">
            <div class="card-body">
                <div class="chat-box mb-3" id="chatBox">
                    <?php if (empty($listChat)): ?>
                        <p class="chat-empty text-center my-3">
                            Belum ada percakapan. Tulis pesan pertama kamu di bawah.
                        </p>
                    <?php else: ?>
                        <?php foreach ($listChat as $c): 
                            $isMe    = ((int)$c['id_user'] === $id_user_login);
                            $sideCls = $isMe ? 'chat-right' : 'chat-left';
                        ?>
                            <div class="chat-item <?= $sideCls; ?>">
                                <div class="chat-bubble">
                                    <div class="chat-name">
                                        <?= htmlspecialchars($c['nama']); ?>
                                        <span class="chat-badge ms-1">
                                            <?= htmlspecialchars($c['peran_pengirim']); ?>
                                        </span>
                                    </div>
                                    <div><?= nl2br(htmlspecialchars($c['pesan'])); ?></div>
                                    <div class="chat-meta">
                                        <?= date('d M Y H:i', strtotime($c['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- FORM KIRIM CHAT -->
                <form method="post" class="chat-form mt-2">
                    <div class="input-group">
                        <textarea name="pesan" class="form-control" rows="2"
                                  placeholder="Tulis pesan ke admin di sini..." required></textarea>
                        <button class="btn btn-danger" type="submit" name="kirim_chat">
                            <i class="bi bi-send-fill me-1"></i> Kirim
                        </button>
                    </div>
                    <small class="text-muted d-block mt-1" style="font-size:.8rem;">
                        Pesan yang kamu kirim akan terlihat oleh admin/super admin pada halaman komunikasi kerusakan.
                    </small>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const box = document.getElementById('chatBox');
    if (box) {
        box.scrollTop = box.scrollHeight;
    }
});
</script>

<?php include '../includes/peminjam/footer.php'; ?>
