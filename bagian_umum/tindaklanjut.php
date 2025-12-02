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

if (!in_array($role, ['bagian_umum', 'super_admin'], true)) {
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
   PARAMETER FILTER
   ========================== */
$filter_q      = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? '';

if (!in_array($filter_status, ['', 'proses', 'selesai'], true)) {
    $filter_status = '';
}

/* =====================
   NOTIFIKASI UNTUK NAVBAR
   ===================== */

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

/* Riwayat 10 notifikasi terbaru */
$notifList = [];
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
               . "Silakan buka menu Komunikasi Kerusakan pada detail peminjaman Anda jika ingin berdiskusi.";

        $judulEsc = mysqli_real_escape_string($conn, $judul);
        $pesanEsc = mysqli_real_escape_string($conn, $pesan);

        $conn->query("
            INSERT INTO notifikasi (id_user, id_pinjam, judul, pesan, tipe, created_at, is_read)
            VALUES ($id_user_peminjam, $id_pinjam, '$judulEsc', '$pesanEsc', 'tindaklanjut', NOW(), 0)
        ");
    }
}

/* ==================================
   UPDATE DATA TINDAK LANJUT KERUSAKAN
   (TIDAK ADA TAMBAH MANUAL DI SINI)
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
                // kondisi pengembalian jadi bagus
                $conn->query("
                    UPDATE pengembalian 
                    SET kondisi = 'bagus'
                    WHERE id_kembali = $id_kembali
                ");

                // peminjaman dianggap selesai juga
                $conn->query("
                    UPDATE peminjaman p
                    JOIN pengembalian pg ON p.id_pinjam = pg.id_pinjam
                    SET p.status = 'selesai'
                    WHERE pg.id_kembali = $id_kembali
                      AND p.status = 'diterima'
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
   BUILD WHERE UNTUK FILTER
   ================================== */
$whereTL = "1=1";

if ($filter_q !== '') {
    $qEsc = mysqli_real_escape_string($conn, $filter_q);
    $like = "'%$qEsc%'";
    $whereTL .= "
        AND (
            tl.id_tindaklanjut LIKE $like
            OR tl.id_kembali LIKE $like
            OR tl.tindakan LIKE $like
            OR tl.deskripsi LIKE $like
            OR pg.id_pinjam LIKE $like
            OR u.nama LIKE $like
        )
    ";
}

if ($filter_status !== '') {
    $statusEsc = mysqli_real_escape_string($conn, $filter_status);
    $whereTL  .= " AND tl.status = '$statusEsc'";
}

/* ==================================
   AMBIL DATA UNTUK TAMPILAN
   ================================== */
$sqlDataTindak = "
    SELECT 
        tl.*,
        pg.id_pinjam,
        u.nama AS nama_peminjam
    FROM tindaklanjut tl
    LEFT JOIN pengembalian pg ON tl.id_kembali = pg.id_kembali
    LEFT JOIN peminjaman p    ON pg.id_pinjam = p.id_pinjam
    LEFT JOIN users u         ON p.id_user = u.id_user
    WHERE $whereTL
    ORDER BY tl.id_tindaklanjut DESC
";
$dataTindak = $conn->query($sqlDataTindak);

/* Rekap: gabungkan tindaklanjut + peminjaman + total chat dari komunikasi_kerusakan */
$sqlRekap = "
    SELECT 
        tl.id_tindaklanjut,
        tl.tanggal,
        tl.tindakan,
        tl.status,
        pg.id_pinjam,
        u.nama AS nama_peminjam,
        COUNT(DISTINCT ck.id_chat) AS total_chat
    FROM tindaklanjut tl
    JOIN pengembalian pg ON tl.id_kembali = pg.id_kembali
    JOIN peminjaman p    ON pg.id_pinjam = p.id_pinjam
    JOIN users u         ON p.id_user = u.id_user
    LEFT JOIN komunikasi_kerusakan ck
        ON ck.id_tindaklanjut = tl.id_tindaklanjut
    WHERE $whereTL
    GROUP BY tl.id_tindaklanjut
    ORDER BY tl.tanggal DESC
";
$dataRekap = $conn->query($sqlRekap);

/* ==================================
   TEMPLATE ADMIN
   ================================== */
include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
include '../includes/admin/sidebar.php';
?>

<style>
    .tab-card {
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        border: 1px solid #e5e7eb;
    }
    .nav-tabs {
        border-bottom: none;
        gap: .5rem;
    }
    .nav-tabs .nav-link {
        font-weight: 500;
        color: #6b7280;
        border-radius: 999px;
        border: 1px solid transparent;
        padding: 0.5rem 1.1rem;
    }
    .nav-tabs .nav-link:hover {
        color: #dc3545;
        background-color: #f9fafb;
    }
    .nav-tabs .nav-link.active {
        background-color: #dc3545;
        color: #ffffff;
        border-color: transparent;
        box-shadow: 0 4px 10px rgba(220, 53, 69, 0.35);
    }
    .tab-card .card-body h5 {
        border-left: 4px solid #dc3545;
        padding-left: .6rem;
    }
</style>

<div class="container-fluid px-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold text-danger mb-1">Tindak Lanjut Kerusakan</h2>
            <p class="text-muted mb-0">
                Kelola tindak lanjut kerusakan fasilitas dan pantau rekap komunikasi peminjam dengan admin.
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
            <button class="nav-link" id="tab-rekap-tab" data-bs-toggle="tab" data-bs-target="#tab-rekap"
                    type="button" role="tab" aria-controls="tab-rekap" aria-selected="false">
                <i class="bi bi-clipboard-data me-1"></i> Rekap
            </button>
        </li>
    </ul>

    <div class="tab-content" id="tlTabContent">
        <!-- TAB 1: TINDAK LANJUT -->
        <div class="tab-pane fade show active" id="tab-tl" role="tabpanel" aria-labelledby="tab-tl-tab">

            <?php if ($editData): ?>
            <!-- HANYA MUNCUL JIKA SEDANG EDIT -->
            <div class="card tab-card p-4 mb-4">
                <h5 class="fw-semibold mb-3">
                    Edit Tindak Lanjut
                </h5>
                <form method="post">
                    <input type="hidden" name="id_tindaklanjut" value="<?= (int) $editData['id_tindaklanjut']; ?>">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ID Kembali</label>
                            <input type="text" class="form-control" 
                                   value="ID Kembali #<?= (int)$editData['id_kembali']; ?>" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tindakan</label>
                            <input type="text" name="tindakan" class="form-control"
                                   value="<?= htmlspecialchars($editData['tindakan']); ?>"
                                   maxlength="255"
                                   required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="proses"  <?= ($editData['status'] === 'proses') ? 'selected' : ''; ?>>
                                    Proses
                                </option>
                                <option value="selesai" <?= ($editData['status'] === 'selesai') ? 'selected' : ''; ?>>
                                    Selesai
                                </option>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" name="update" class="btn btn-primary w-100">
                                <i class="bi bi-save me-1"></i> Perbarui
                            </button>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Keterangan / Deskripsi</label>
                            <textarea name="deskripsi" class="form-control" rows="2" maxlength="500"><?= htmlspecialchars($editData['deskripsi'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="card tab-card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="fw-semibold mb-0">Data Tindak Lanjut</h5>
                    </div>

                    <!-- FILTER / PENCARIAN -->
                    <form method="get" action="tindaklanjut.php" class="row g-2 align-items-end mb-3">
                        <div class="col-md-5">
                            <label class="form-label">Cari</label>
                            <input type="text" name="q" class="form-control"
                                   placeholder="ID Pinjam / Nama Peminjam / Tindakan / Deskripsi"
                                   value="<?= htmlspecialchars($filter_q); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="proses"  <?= $filter_status==='proses'?'selected':''; ?>>Proses</option>
                                <option value="selesai" <?= $filter_status==='selesai'?'selected':''; ?>>Selesai</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-danger mt-auto">
                                <i class="bi bi-search me-1"></i> Filter
                            </button>
                            <a href="tindaklanjut.php" class="btn btn-outline-secondary mt-auto">
                                Reset
                            </a>
                        </div>
                    </form>

                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>No</th>
                                <th>ID Kembali</th>
                                <th>ID Pinjam</th>
                                <th>Peminjam</th>
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
                                <td><?= htmlspecialchars($row['nama_peminjam'] ?? '-'); ?></td>
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
                                       class="btn btn-sm btn-outline-primary mb-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (!empty($row['id_pinjam'])): ?>
                                        <a href="komunikasi_tindaklanjut.php?id_pinjam=<?= (int)$row['id_pinjam']; ?>&id_tl=<?= (int)$row['id_tindaklanjut']; ?>"
                                           class="btn btn-sm btn-outline-danger mt-1">
                                            <i class="bi bi-chat-dots me-1"></i> Chat
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">
                                    Belum ada tindak lanjut (atau tidak ada yang cocok dengan filter).
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: REKAP & LAPORAN -->
        <div class="tab-pane fade" id="tab-rekap" role="tabpanel" aria-labelledby="tab-rekap-tab">
            <div class="card tab-card mb-5">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="fw-semibold mb-0">Rekap Tindak Lanjut & Komunikasi</h5>
                    </div>
                    <p class="text-muted" style="font-size: 0.9rem;">
                        Rekap ini menggabungkan data peminjaman, tindak lanjut kerusakan, dan jumlah chat komunikasi kerusakan
                        antara peminjam dengan admin. Filter yang digunakan sama dengan tab Tindak Lanjut.
                    </p>

                    <!-- Opsional: tampilkan ringkasan filter aktif -->
                    <?php if ($filter_q !== '' || $filter_status !== ''): ?>
                        <div class="alert alert-light border mb-3 py-2" style="font-size:.85rem;">
                            <strong>Filter aktif:</strong>
                            <?php if ($filter_q !== ''): ?>
                                Pencarian: "<em><?= htmlspecialchars($filter_q); ?></em>"
                            <?php endif; ?>
                            <?php if ($filter_status !== ''): ?>
                                <?= $filter_q !== '' ? ' · ' : ''; ?>
                                Status: <span class="badge bg-secondary"><?= htmlspecialchars($filter_status); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <table class="table table-striped table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>ID TL</th>
                                <th>ID Pinjam</th>
                                <th>Peminjam</th>
                                <th>Tindakan</th>
                                <th>Status TL</th>
                                <th>Jumlah Chat</th>
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
                                <td><?= (int) $r['total_chat']; ?></td>
                                <td><?= date('d M Y H:i', strtotime($r['tanggal'])); ?></td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">
                                    Belum ada data rekap (atau tidak ada yang cocok dengan filter).
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
