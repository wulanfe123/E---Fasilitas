<?php
session_start();
include '../config/koneksi.php';

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

/* =====================
   NOTIFIKASI UNTUK NAVBAR
   ===================== */

/* ===================== NOTIFIKASI (BADGE + RIWAYAT) ===================== */

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
/* Riwayat 10 notifikasi terbaru */
$qNotif = $conn->query("
    SELECT 
        id_notifikasi,
        id_pinjam,
        judul,
        pesan,
        tipe,
        created_at,
        is_read
    FROM notifikasi
    WHERE id_user = $id_user_login
    ORDER BY created_at DESC
    LIMIT 10
");
if ($qNotif) {
    while ($row = $qNotif->fetch_assoc()) {
        $notifList[] = $row;
    }
}

/* =======================
   FUNGSI HELPER NOTIFIKASI
   ======================= */

/**
 * Kirim notifikasi ke peminjam saat ada tindak lanjut kerusakan.
 */
function kirimNotifikasiTindakLanjut(mysqli $conn, int $id_kembali, string $tindakan, string $status): void
{
    $q = $conn->query("
        SELECT p.id_pinjam, p.id_user 
        FROM pengembalian pg
        JOIN peminjaman p ON pg.id_pinjam = p.id_pinjam
        WHERE pg.id_kembali = $id_kembali
        LIMIT 1
    ");

    if ($q && $row = $q->fetch_assoc()) {
        $id_pinjam        = (int) $row['id_pinjam'];
        $id_user_peminjam = (int) $row['id_user'];

        $judul = "Tindak Lanjut Kerusakan Peminjaman #{$id_pinjam}";
        $pesan = "Petugas telah memproses tindak lanjut kerusakan untuk peminjaman #{$id_pinjam}. "
               . "Tindakan: {$tindakan}. Status tindak lanjut: {$status}. "
               . "Silakan buka menu Tindak Lanjut/Klarifikasi di akun Anda jika ingin memberikan penjelasan.";

        $judulEsc = mysqli_real_escape_string($conn, $judul);
        $pesanEsc = mysqli_real_escape_string($conn, $pesan);

        $conn->query("
            INSERT INTO notifikasi (id_user, id_pinjam, judul, pesan, tipe, created_at, is_read)
            VALUES ($id_user_peminjam, $id_pinjam, '$judulEsc', '$pesanEsc', 'tindaklanjut', NOW(), 0)
        ");
    }
}

/**
 * Kirim notifikasi ke peminjam saat admin membalas komplain tindak lanjut.
 */
function kirimNotifikasiBalasanKomplain(mysqli $conn, int $id_komplain): void
{
    $q = $conn->query("
        SELECT id_user, id_pinjam
        FROM komplain_tindaklanjut
        WHERE id_komplain = $id_komplain
        LIMIT 1
    ");

    if ($q && $k = $q->fetch_assoc()) {
        $id_user_peminjam = (int) $k['id_user'];
        $id_pinjam        = (int) $k['id_pinjam'];

        $judul = "Balasan Komplain Tindak Lanjut #{$id_pinjam}";
        $pesan = "Bagian Umum/Super Admin telah membalas komplain Anda "
               . "terkait tindak lanjut kerusakan peminjaman #{$id_pinjam}.";

        $judulEsc = mysqli_real_escape_string($conn, $judul);
        $pesanEsc = mysqli_real_escape_string($conn, $pesan);

        $conn->query("
            INSERT INTO notifikasi (id_user, id_pinjam, judul, pesan, tipe, created_at, is_read)
            VALUES ($id_user_peminjam, $id_pinjam, '$judulEsc', '$pesanEsc', 'komplain_tl', NOW(), 0)
        ");
    }
}

/* ===================================================
   PARAMETER DARI PENGEMBALIAN (KETIKA DITEMUKAN RUSAK)
   =================================================== */
// jika datang dari pengembalian.php?id_kembali=XX
$id_kembali_param = isset($_GET['id_kembali']) ? (int) $_GET['id_kembali'] : 0;
$selectedKembali  = null;

if ($id_kembali_param > 0) {
    $qSel = $conn->query("
        SELECT pg.id_kembali, pg.id_pinjam
        FROM pengembalian pg
        WHERE pg.id_kembali = $id_kembali_param
          AND pg.kondisi = 'rusak'
        LIMIT 1
    ");
    if ($qSel && $qSel->num_rows > 0) {
        $selectedKembali = $qSel->fetch_assoc();
    } else {
        // jika id_kembali tidak valid / bukan rusak, abaikan parameter
        $id_kembali_param = 0;
    }
}

/* ===========================================
   PROSES BALAS KOMPLAIN (ADMIN/BAGIAN UMUM)
   =========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['balas_komplain'])) {
    $id_komplain = (int) ($_POST['id_komplain'] ?? 0);
    $balasan     = trim($_POST['balasan'] ?? '');
    $status_k    = $_POST['status'] ?? 'dibalas';

    if ($id_komplain <= 0) {
        $_SESSION['error'] = "Data komplain tidak valid.";
    } elseif ($balasan === '') {
        $_SESSION['error'] = "Balasan tidak boleh kosong.";
    } else {
        $balasanEsc = mysqli_real_escape_string($conn, $balasan);

        // cek status lama
        $status_lama = 'baru';
        $qOld = $conn->query("
            SELECT status 
            FROM komplain_tindaklanjut 
            WHERE id_komplain = $id_komplain
            LIMIT 1
        ");
        if ($qOld && $rowOld = $qOld->fetch_assoc()) {
            $status_lama = $rowOld['status'];
        }

        // jika sebelumnya 'baru' dan admin lupa ganti status, paksa jadi 'dibalas'
        if ($status_lama === 'baru' && $status_k === 'baru') {
            $status_k = 'dibalas';
        }

        if (!in_array($status_k, ['baru','dibalas','selesai'], true)) {
            $status_k = 'dibalas';
        }

        $statusEsc  = mysqli_real_escape_string($conn, $status_k);

        $conn->query("
            UPDATE komplain_tindaklanjut
            SET balasan   = '$balasanEsc',
                status    = '$statusEsc',
                updated_at = NOW()
            WHERE id_komplain = $id_komplain
        ");

        kirimNotifikasiBalasanKomplain($conn, $id_komplain);

        $_SESSION['success'] = "Balasan komplain berhasil dikirim.";
    }

    header("Location: tindaklanjut.php#tab-komplain");
    exit;
}

/* ==================================
   TAMBAH DATA TINDAK LANJUT KERUSAKAN
   ================================== */
if (isset($_POST['simpan'])) {
    $id_kembali = (int) ($_POST['id_kembali'] ?? 0);
    $tindakan   = trim($_POST['tindakan'] ?? '');
    $deskripsi  = trim($_POST['deskripsi'] ?? '');
    $status     = trim($_POST['status'] ?? '');
    $id_user    = $id_user_login;

    if ($id_kembali <= 0) {
        $_SESSION['error'] = "ID pengembalian tidak valid.";
    } elseif ($tindakan === '') {
        $_SESSION['error'] = "Tindakan tidak boleh kosong.";
    } elseif (strlen($tindakan) > 255) {
        $_SESSION['error'] = "Tindakan terlalu panjang (maksimal 255 karakter).";
    } elseif (strlen($deskripsi) > 500) {
        $_SESSION['error'] = "Deskripsi terlalu panjang (maksimal 500 karakter).";
    } elseif (!in_array($status, ['proses','selesai'], true)) {
        $_SESSION['error'] = "Status tindak lanjut tidak valid.";
    } else {
        $tindakanEsc  = mysqli_real_escape_string($conn, $tindakan);
        $deskripsiEsc = mysqli_real_escape_string($conn, $deskripsi);
        $statusEsc    = mysqli_real_escape_string($conn, $status);

        // Insert tindak lanjut baru
        $conn->query("
            INSERT INTO tindaklanjut (id_kembali, id_user, tindakan, deskripsi, status, tanggal) 
            VALUES ($id_kembali, $id_user, '$tindakanEsc', '$deskripsiEsc', '$statusEsc', NOW())
        ");

        // Jika perbaikan dinyatakan selesai → ubah kondisi pengembalian jadi bagus
        if ($status === 'selesai') {
            $conn->query("
                UPDATE pengembalian 
                SET kondisi = 'bagus'
                WHERE id_kembali = $id_kembali
            ");
        }

        // Kirim notifikasi ke peminjam
        kirimNotifikasiTindakLanjut($conn, $id_kembali, $tindakan, $status);

        $_SESSION['success'] = "Tindak lanjut berhasil disimpan.";
    }

    header("Location: tindaklanjut.php");
    exit;
}

/* ==================================
   UPDATE DATA TINDAK LANJUT KERUSAKAN
   ================================== */
$editData = null;
if (isset($_GET['edit'])) {
    $id_tindaklanjut = (int) $_GET['edit'];
    if ($id_tindaklanjut > 0) {
        $get = $conn->query("
            SELECT * FROM tindaklanjut 
            WHERE id_tindaklanjut = $id_tindaklanjut
            LIMIT 1
        ");
        $editData = $get ? $get->fetch_assoc() : null;
    }
}

if (isset($_POST['update'])) {
    $id_tindaklanjut = (int) ($_POST['id_tindaklanjut'] ?? 0);
    $tindakan        = trim($_POST['tindakan'] ?? '');
    $deskripsi       = trim($_POST['deskripsi'] ?? '');
    $status          = trim($_POST['status'] ?? '');

    if ($id_tindaklanjut <= 0) {
        $_SESSION['error'] = "Data tindak lanjut tidak valid.";
    } elseif ($tindakan === '') {
        $_SESSION['error'] = "Tindakan tidak boleh kosong.";
    } elseif (strlen($tindakan) > 255) {
        $_SESSION['error'] = "Tindakan terlalu panjang (maksimal 255 karakter).";
    } elseif (strlen($deskripsi) > 500) {
        $_SESSION['error'] = "Deskripsi terlalu panjang (maksimal 500 karakter).";
    } elseif (!in_array($status, ['proses','selesai'], true)) {
        $_SESSION['error'] = "Status tindak lanjut tidak valid.";
    } else {
        $tindakanEsc  = mysqli_real_escape_string($conn, $tindakan);
        $deskripsiEsc = mysqli_real_escape_string($conn, $deskripsi);
        $statusEsc    = mysqli_real_escape_string($conn, $status);

        $conn->query("
            UPDATE tindaklanjut 
            SET tindakan = '$tindakanEsc', 
                deskripsi = '$deskripsiEsc', 
                status = '$statusEsc'
            WHERE id_tindaklanjut = $id_tindaklanjut
        ");

        // Ambil id_kembali untuk update pengembalian & notifikasi
        $qTL = $conn->query("
            SELECT id_kembali 
            FROM tindaklanjut 
            WHERE id_tindaklanjut = $id_tindaklanjut
            LIMIT 1
        ");
        if ($qTL && $rowTL = $qTL->fetch_assoc()) {
            $id_kembali = (int) $rowTL['id_kembali'];

            if ($status === 'selesai') {
                $conn->query("
                    UPDATE pengembalian 
                    SET kondisi = 'bagus'
                    WHERE id_kembali = $id_kembali
                ");
            }

            kirimNotifikasiTindakLanjut($conn, $id_kembali, $tindakan, $status);
        }

        $_SESSION['success'] = "Data tindak lanjut berhasil diperbarui.";
    }

    header("Location: tindaklanjut.php");
    exit;
}

/* ==================================
   AMBIL DATA UNTUK TAMPILAN
   ================================== */
$dataTindak = $conn->query("
    SELECT tl.*, pg.id_pinjam
    FROM tindaklanjut tl
    LEFT JOIN pengembalian pg ON tl.id_kembali = pg.id_kembali
    ORDER BY tl.id_tindaklanjut DESC
");

$dataKomplain = $conn->query("
    SELECT kt.*, u.nama, p.tanggal_mulai, p.tanggal_selesai
    FROM komplain_tindaklanjut kt
    JOIN users u ON kt.id_user = u.id_user
    JOIN peminjaman p ON kt.id_pinjam = p.id_pinjam
    ORDER BY kt.created_at DESC
");

$dataRekap = $conn->query("
    SELECT 
        tl.id_tindaklanjut,
        tl.tanggal,
        tl.tindakan,
        tl.status,
        pg.id_pinjam,
        u.nama AS nama_peminjam,
        COUNT(kt.id_komplain) AS total_komplain
    FROM tindaklanjut tl
    JOIN pengembalian pg ON tl.id_kembali = pg.id_kembali
    JOIN peminjaman p    ON pg.id_pinjam = p.id_pinjam
    JOIN users u         ON p.id_user = u.id_user
    LEFT JOIN komplain_tindaklanjut kt 
        ON tl.id_tindaklanjut = kt.id_tindaklanjut
    GROUP BY tl.id_tindaklanjut
    ORDER BY tl.tanggal DESC
");

/* ==================================
   TEMPLATE ADMIN
   ================================== */
include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
include '../includes/admin/sidebar.php';
?>

<style>
    .tab-card {
        border-radius: 12px;
        box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08);
        border: none;
    }
    .nav-tabs .nav-link {
        font-weight: 500;
        color: #6c757d;
    }
    .nav-tabs .nav-link:hover {
        color: #0d6efd;
    }
    .nav-tabs .nav-link.active {
        background-color: #ffc107;  /* kuning sesuai header pengembalian */
        color: #212529;
        border-color: #ffc107 #ffc107 #fff;
    }
</style>

<div class="container-fluid px-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold text-warning mb-1">Tindak Lanjut Kerusakan</h2>
            <p class="text-muted mb-0">
                Kelola tindak lanjut kerusakan fasilitas, klarifikasi/komplain peminjam, dan rekap laporan.
            </p>
        </div>
        <div></div>
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

    <!-- NAV TABS -->
    <ul class="nav nav-tabs mb-3" id="tlTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-tl-tab" data-bs-toggle="tab" data-bs-target="#tab-tl"
                    type="button" role="tab" aria-controls="tab-tl" aria-selected="true">
                <i class="bi bi-tools me-1"></i> Tindak Lanjut
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-komplain-tab" data-bs-toggle="tab" data-bs-target="#tab-komplain"
                    type="button" role="tab" aria-controls="tab-komplain" aria-selected="false">
                <i class="bi bi-chat-dots me-1"></i> Komplain Peminjam
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-rekap-tab" data-bs-toggle="tab" data-bs-target="#tab-rekap"
                    type="button" role="tab" aria-controls="tab-rekap" aria-selected="false">
                <i class="bi bi-clipboard-data me-1"></i> Rekap
            </button>
        </li>
    </ul>

    <div class="tab-content" id="tlTabContent">
        <!-- TAB 1: TINDAK LANJUT -->
        <div class="tab-pane fade show active" id="tab-tl" role="tabpanel" aria-labelledby="tab-tl-tab">
            <div class="card tab-card p-4 mb-4">
                <h5 class="fw-semibold mb-3">
                    <?= $editData ? 'Edit Tindak Lanjut' : 'Tambah Tindak Lanjut Baru'; ?>
                </h5>
                <form method="post">
                    <?php if ($editData): ?>
                        <input type="hidden" name="id_tindaklanjut" value="<?= (int) $editData['id_tindaklanjut']; ?>">
                    <?php endif; ?>

                    <div class="row g-3">
                        <?php if ($editData): ?>
                            <!-- Saat edit, id_kembali sudah tersimpan di DB -->
                        <?php else: ?>
                            <?php if ($selectedKembali): ?>
                                <!-- Datang dari pengembalian.php (id_kembali dikunci) -->
                                <input type="hidden" name="id_kembali" value="<?= (int) $selectedKembali['id_kembali']; ?>">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Pengembalian</label>
                                    <input type="text" class="form-control"
                                           value="ID Kembali #<?= $selectedKembali['id_kembali']; ?> · Peminjaman #<?= $selectedKembali['id_pinjam']; ?>"
                                           readonly>
                                </div>
                            <?php else: ?>
                                <!-- Tambah manual: pilih dari daftar pengembalian yang kondisi = rusak -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">ID Pengembalian (Rusak)</label>
                                    <select name="id_kembali" class="form-select" required>
                                        <option value="">Pilih ID</option>
                                        <?php
                                        $getKembali = $conn->query("
                                            SELECT id_kembali 
                                            FROM pengembalian 
                                            WHERE kondisi = 'rusak'
                                            ORDER BY id_kembali DESC
                                        ");
                                        if ($getKembali) {
                                            while ($k = $getKembali->fetch_assoc()) {
                                                echo "<option value='{$k['id_kembali']}'>
                                                        ID Kembali #{$k['id_kembali']}
                                                      </option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Tindakan</label>
                            <input type="text" name="tindakan" class="form-control"
                                   value="<?= htmlspecialchars($editData['tindakan'] ?? ($selectedKembali ? 'Perbaikan fasilitas' : '')); ?>"
                                   maxlength="255"
                                   required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="proses"  <?= (isset($editData['status']) && $editData['status'] === 'proses') ? 'selected' : ''; ?>>
                                    Proses
                                </option>
                                <option value="selesai" <?= (isset($editData['status']) && $editData['status'] === 'selesai') ? 'selected' : ''; ?>>
                                    Selesai
                                </option>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <?php if ($editData): ?>
                                <button type="submit" name="update" class="btn btn-primary w-100">
                                    <i class="bi bi-save me-1"></i> Perbarui
                                </button>
                            <?php else: ?>
                                <button type="submit" name="simpan" class="btn btn-success w-100">
                                    <i class="bi bi-plus-circle me-1"></i> Simpan
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Keterangan / Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="2" maxlength="500"><?= htmlspecialchars($editData['deskripsi'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card tab-card mb-4">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Data Tindak Lanjut</h5>
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-dark text-center">
                            <tr>
                                <th>No</th>
                                <th>ID Kembali</th>
                                <th>ID Pinjam</th>
                                <th>Tindakan</th>
                                <th>Deskripsi</th>
                                <th>Status</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $no = 1;
                        if ($dataTindak && $dataTindak->num_rows > 0):
                            while ($row = $dataTindak->fetch_assoc()):
                                $badge = ($row['status'] === 'proses') ? 'warning text-dark' : 'success';
                        ?>
                            <tr>
                                <td class="text-center"><?= $no++; ?></td>
                                <td class="text-center"><?= (int) $row['id_kembali']; ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['id_pinjam'] ?? '-'); ?></td>
                                <td><?= htmlspecialchars($row['tindakan']); ?></td>
                                <td><?= nl2br(htmlspecialchars($row['deskripsi'])); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $badge; ?>">
                                        <?= htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['tanggal']); ?></td>
                                <td class="text-center">
                                    <a href="tindaklanjut.php?edit=<?= $row['id_tindaklanjut']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">
                                    Belum ada tindak lanjut.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: KOMPLAIN PEMINJAM -->
        <div class="tab-pane fade" id="tab-komplain" role="tabpanel" aria-labelledby="tab-komplain-tab">
            <div class="card tab-card mb-5">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Komplain Tindak Lanjut dari Peminjam</h5>

                    <?php if ($dataKomplain && $dataKomplain->num_rows > 0): ?>
                        <?php while ($k = $dataKomplain->fetch_assoc()): ?>
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <div>
                                        <strong><?= htmlspecialchars($k['nama']); ?></strong>
                                        <small class="text-muted d-block">
                                            Peminjaman #<?= $k['id_pinjam']; ?> · Tindak Lanjut #<?= $k['id_tindaklanjut']; ?><br>
                                            Tgl Pinjam: <?= date('d M Y', strtotime($k['tanggal_mulai'])); ?> 
                                            - <?= date('d M Y', strtotime($k['tanggal_selesai'])); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">
                                            <?= date('d M Y H:i', strtotime($k['created_at'])); ?>
                                        </small>
                                        <?php
                                            $badgeClass = 'secondary';
                                            if ($k['status'] === 'baru')     $badgeClass = 'warning text-dark';
                                            elseif ($k['status'] === 'dibalas') $badgeClass = 'primary';
                                            elseif ($k['status'] === 'selesai') $badgeClass = 'success';
                                        ?>
                                        <span class="badge bg-<?= $badgeClass; ?>">
                                            <?= htmlspecialchars($k['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <p class="mb-1">
                                    <strong>Pesan Peminjam:</strong><br>
                                    <?= nl2br(htmlspecialchars($k['pesan'])); ?>
                                </p>

                                <?php if (!empty($k['balasan'])): ?>
                                    <p class="mb-1 text-primary">
                                        <strong>Balasan Anda:</strong><br>
                                        <?= nl2br(htmlspecialchars($k['balasan'])); ?>
                                    </p>
                                <?php else: ?>
                                    <p class="mb-1 text-muted"><em>Belum ada balasan.</em></p>
                                <?php endif; ?>

                                <form method="post" class="mt-2">
                                    <input type="hidden" name="id_komplain" value="<?= $k['id_komplain']; ?>">
                                    <div class="mb-2">
                                        <label class="form-label">Balasan</label>
                                        <textarea name="balasan" class="form-control" rows="2"
                                                  placeholder="Tulis balasan untuk peminjam..."><?= htmlspecialchars($k['balasan'] ?? ''); ?></textarea>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Status Komplain</label>
                                        <select name="status" class="form-select form-select-sm" style="max-width:200px;">
                                            <option value="baru"    <?= $k['status']==='baru'?'selected':''; ?>>Baru</option>
                                            <option value="dibalas" <?= $k['status']==='dibalas'?'selected':''; ?>>Dibalas</option>
                                            <option value="selesai" <?= $k['status']==='selesai'?'selected':''; ?>>Selesai</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="balas_komplain" class="btn btn-sm btn-primary">
                                        <i class="bi bi-reply me-1"></i> Simpan Balasan
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted mb-0">Belum ada komplain tindak lanjut dari peminjam.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 3: REKAP & LAPORAN -->
        <div class="tab-pane fade" id="tab-rekap" role="tabpanel" aria-labelledby="tab-rekap-tab">
            <div class="card tab-card mb-5">
                <div class="card-body">
                    <h5 class="fw-semibold mb-3">Rekap Tindak Lanjut & Komplain</h5>
                    <p class="text-muted" style="font-size: 0.9rem;">
                        Rekap ini menggabungkan data peminjaman, tindak lanjut kerusakan, dan jumlah komplain peminjam.
                    </p>

                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th>No</th>
                                <th>ID TL</th>
                                <th>ID Pinjam</th>
                                <th>Peminjam</th>
                                <th>Tindakan</th>
                                <th>Status TL</th>
                                <th>Jumlah Komplain</th>
                                <th>Tanggal TL</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $no = 1;
                        if ($dataRekap && $dataRekap->num_rows > 0):
                            while ($r = $dataRekap->fetch_assoc()):
                                $badgeClass = 'secondary';
                                if ($r['status'] === 'proses')  $badgeClass = 'warning text-dark';
                                elseif ($r['status'] === 'selesai') $badgeClass = 'success';
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>
                                <td><?= $r['id_tindaklanjut']; ?></td>
                                <td><?= $r['id_pinjam']; ?></td>
                                <td><?= htmlspecialchars($r['nama_peminjam']); ?></td>
                                <td><?= htmlspecialchars($r['tindakan']); ?></td>
                                <td>
                                    <span class="badge bg-<?= $badgeClass; ?>">
                                        <?= htmlspecialchars($r['status']); ?>
                                    </span>
                                </td>
                                <td><?= (int) $r['total_komplain']; ?></td>
                                <td><?= date('d M Y H:i', strtotime($r['tanggal'])); ?></td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">
                                    Belum ada data rekap.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<br><br><br>

<?php include '../includes/admin/footer.php'; ?>
