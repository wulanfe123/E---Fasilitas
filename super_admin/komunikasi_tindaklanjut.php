<?php
session_start();
include '../config/koneksi.php';
include '../config/notifikasi_helper.php';

/* ==========================
   CEK LOGIN & ROLE
   ========================== */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user_login = (int) $_SESSION['id_user'];
$role          = $_SESSION['role'] ?? '';

if (!in_array($role, ['super_admin', 'bagian_umum'], true)) {
    header("Location: ../auth/unauthorized.php");
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
    $_SESSION['error'] = "ID peminjaman tidak valid.";
    header("Location: tindaklanjut.php");
    exit;
}

/* ==========================
   DETAIL PEMINJAMAN + PEMINJAM
   ========================== */
$detailPinjam = [
    'id_pinjam'       => $id_pinjam,
    'nama_peminjam'   => '-',
    'tanggal_mulai'   => null,
    'tanggal_selesai' => null,
];

$sqlP = "
    SELECT p.tanggal_mulai, p.tanggal_selesai, u.nama AS nama_peminjam
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.id_pinjam = ?
    LIMIT 1
";
if ($stmtP = $conn->prepare($sqlP)) {
    $stmtP->bind_param("i", $id_pinjam);
    $stmtP->execute();
    $resP = $stmtP->get_result();
    if ($rowP = $resP->fetch_assoc()) {
        $detailPinjam['nama_peminjam']   = $rowP['nama_peminjam'];
        $detailPinjam['tanggal_mulai']   = $rowP['tanggal_mulai'];
        $detailPinjam['tanggal_selesai'] = $rowP['tanggal_selesai'];
    }
    $stmtP->close();
}

/* ==========================
   (OPSIONAL) DETAIL TINDAK LANJUT
   ========================== */
$dataTl = null;
if ($id_tl > 0) {
    $sqlTl = "
        SELECT *
        FROM tindaklanjut
        WHERE id_tindaklanjut = ?
        LIMIT 1
    ";
    if ($stmtTl = $conn->prepare($sqlTl)) {
        $stmtTl->bind_param("i", $id_tl);
        $stmtTl->execute();
        $resTl  = $stmtTl->get_result();
        $dataTl = $resTl->fetch_assoc();
        $stmtTl->close();
    }
}

/* kalau tidak ketemu, jangan dibuat error — tetap boleh chat */
if (!$dataTl) {
    $dataTl = [
        'id_tindaklanjut' => $id_tl,
        'tindakan'        => 'Tindak lanjut kerusakan',
        'status'          => 'proses',
    ];
}

/* ==========================
   PROSES KIRIM CHAT
   ========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_chat'])) {
    $pesan = trim($_POST['pesan'] ?? '');

    // VALIDASI INPUT
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
                ?,        -- id_pinjam
                ?,        -- id_tindaklanjut (boleh null)
                NULL,     -- id_kembali (belum dipakai)
                ?,        -- id_user
                ?,        -- peran_pengirim
                ?,        -- pesan
                0,
                0,
                NOW()
            )
        ";

        if ($stmtIns = $conn->prepare($sqlIns)) {
            // kalau id_tl 0 → set NULL
            $id_tindak_for_bind = $id_tl > 0 ? $id_tl : null;
            $peran_pengirim     = $role; // 'super_admin' / 'bagian_umum'

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

                // Contoh: kalau mau kirim notifikasi ke peminjam, bisa pakai helper
                // cari id_user peminjam dulu, lalu:
                // tambah_notif($conn, $id_user_peminjam, $id_pinjam, "Chat baru tindak lanjut", $pesan, "chat_tl");

            } else {
                $_SESSION['error'] = "Gagal mengirim pesan: " . $stmtIns->error;
            }
            $stmtIns->close();
        } else {
            $_SESSION['error'] = "Gagal menyiapkan query kirim chat.";
        }
    }

    $redir = "komunikasi_tindaklanjut.php?id_pinjam=" . $id_pinjam;
    if ($id_tl > 0) {
        $redir .= "&id_tl=" . $id_tl;
    }
    header("Location: " . $redir);
    exit;
}

/* ==========================
   AMBIL RIWAYAT CHAT (PREPARED)
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
$params = [$id_pinjam];
$types  = "i";

if ($id_tl > 0) {
    $sqlChat .= " AND ck.id_tindaklanjut = ?";
    $params[] = $id_tl;
    $types   .= "i";
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
   TEMPLATE ADMIN
   ========================== */
include '../includes/admin/header.php';
include '../includes/admin/sidebar.php';
?>

<style>
/* Bungkus seluruh area chat */
.chat-wrapper {
    margin-top: 0;
}

/* Kolom utama chat supaya sejajar dengan judul, tapi tetap lebar */
/* Kolom utama chat: agak dekat ke sidebar, tapi tidak mepet */
.chat-column {
    width: 100%;
    max-width: 1200px;      /* boleh disesuaikan */
    margin-left: 0.75rem;   /* jarak dari sidebar */
    margin-right: 0;        /* lebih rapat ke kanan */
}

@media (max-width: 991.98px) {
    /* Di layar kecil tetap full & tengah */
    .chat-column {
        max-width: 100%;
        margin-left: 0;
        margin-right: 0;
    }
}

/* Box chat */
.chat-box {
    background: #f9fafb;
    border-radius: 16px;
    padding: 20px 20px;
    max-height: 520px;
    overflow-y: auto;
    border: 1px solid #e5e7eb;
}

/* Item setiap chat */
.chat-item {
    margin-bottom: 14px;
    display: flex;
}

/* Badge kecil di nama pengirim */
.chat-badge {
    font-size: .72rem;
    padding: 2px 8px;
    border-radius: 999px;
}

/* Bubble chat */
.chat-bubble {
    border-radius: 14px;
    padding: 10px 14px;
    max-width: 80%;
    font-size: .9rem;
}

/* Posisi kiri (peminjam) */
.chat-left {
    justify-content: flex-start;
}
.chat-left .chat-bubble {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    margin-right: 60px;   /* supaya agak ke tengah */
}

/* Posisi kanan (admin/super admin) */
.chat-right {
    justify-content: flex-end;
}
.chat-right .chat-bubble {
    background: #dc3545;
    color: #fff;
    margin-left: 60px;    /* supaya agak ke tengah */
}

/* Meta waktu */
.chat-meta {
    font-size: .75rem;
    color: #6b7280;
    margin-top: 4px;
}

/* Card header info peminjaman */
.card-info {
    border-radius: 14px;
    box-shadow: 0 3px 10px rgba(15,23,42,.08);
}

/* Form input chat */
.chat-input-group textarea {
    resize: vertical;
    min-height: 60px;
    max-height: 140px;
}
</style>

<div id="layoutSidenav_content">
    
    <?php include '../includes/admin/navbar.php'; ?>

    <main>
        <div class="container-fluid px-4 mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h2 class="fw-bold text-danger mb-1">Komunikasi Kerusakan</h2>
                    <p class="text-muted mb-0">
                        Ruang chat antara peminjam dan admin terkait tindak lanjut kerusakan fasilitas.
                    </p>
                </div>
                <a href="tindaklanjut.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke Tindak Lanjut
                </a>
            </div>

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

            <div class="chat-wrapper row mb-5">
                <div class="col-12 col-lg-10 chat-column">
                    <!-- INFO RINGKAS -->
                    <div class="card mb-3 card-info border-0">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">
                                    Peminjaman #<?= $detailPinjam['id_pinjam']; ?> 
                                    · <?= htmlspecialchars($detailPinjam['nama_peminjam']); ?>
                                </div>
                                <div class="text-muted" style="font-size:.85rem;">
                                    <?php if (!empty($dataTl['id_tindaklanjut'])): ?>
                                        Tindak Lanjut #<?= $dataTl['id_tindaklanjut']; ?> · 
                                    <?php endif; ?>
                                    Tindakan: <?= htmlspecialchars($dataTl['tindakan']); ?>
                                    <?php if (!empty($detailPinjam['tanggal_mulai'])): ?>
                                        <br>
                                        Periode: <?= date('d M Y', strtotime($detailPinjam['tanggal_mulai'])); ?>
                                        - <?= date('d M Y', strtotime($detailPinjam['tanggal_selesai'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php
                                    $badgeClass = 'secondary';
                                    if (($dataTl['status'] ?? '') === 'proses')      $badgeClass = 'warning text-dark';
                                    elseif (($dataTl['status'] ?? '') === 'selesai') $badgeClass = 'success';
                                ?>
                                <span class="badge bg-<?= $badgeClass; ?> text-uppercase">
                                    <?= htmlspecialchars($dataTl['status'] ?? '-'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- BOX CHAT -->
                    <div class="card mb-3 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="chat-box mb-3" id="chatBox">
                                <?php if (empty($listChat)): ?>
                                    <p class="text-muted text-center my-3" style="font-size:.9rem;">
                                        Belum ada percakapan. Mulai chat dengan peminjam di bawah.
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($listChat as $c): 
                                        $isMe    = ((int)$c['id_user'] === $id_user_login);
                                        $sideCls = $isMe ? 'chat-right' : 'chat-left';
                                    ?>
                                        <div class="chat-item <?= $sideCls; ?>">
                                            <div>
                                                <div class="chat-bubble">
                                                    <div class="fw-semibold" style="font-size:.78rem;">
                                                        <?= htmlspecialchars($c['nama']); ?>
                                                        <span class="chat-badge bg-light text-muted ms-1">
                                                            <?= htmlspecialchars($c['peran_pengirim']); ?>
                                                        </span>
                                                    </div>
                                                    <div><?= nl2br(htmlspecialchars($c['pesan'])); ?></div>
                                                    <div class="chat-meta">
                                                        <?= date('d M Y H:i', strtotime($c['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- FORM KIRIM CHAT -->
                            <form method="post" class="mt-2">
                                <div class="input-group chat-input-group">
                                    <textarea name="pesan" class="form-control"
                                              placeholder="Tulis pesan ke peminjam di sini..." required></textarea>
                                    <button class="btn btn-danger" type="submit" name="kirim_chat">
                                        <i class="bi bi-send-fill me-1"></i> Kirim
                                    </button>
                                </div>
                                <small class="text-muted d-block mt-1" style="font-size:.8rem;">
                                    Pesan akan terlihat juga di akun peminjam pada halaman komunikasi kerusakan peminjaman ini.
                                </small>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <footer class="footer-admin">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>E-Fasilitas</strong> &copy; <?= date('Y'); ?> - Sistem Peminjaman Fasilitas Kampus
            </div>
            <div>
                Version 1.0
            </div>
        </div>
    </footer>

</div>

<?php include '../includes/admin/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // auto scroll ke bawah
    const box = document.getElementById('chatBox');
    if (box) {
        box.scrollTop = box.scrollHeight;
    }
});
</script>
