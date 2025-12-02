<?php
ob_start();
session_start();
include '../config/koneksi.php';

/* =========================================================
   1. CEK LOGIN & ROLE
   ========================================================= */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user_login = (int) $_SESSION['id_user'];

// Ambil data user login (role + nama) pakai prepared
$user = null;
if ($stmtUser = $conn->prepare("SELECT role, nama FROM users WHERE id_user = ? LIMIT 1")) {
    $stmtUser->bind_param("i", $id_user_login);
    $stmtUser->execute();
    $resUser = $stmtUser->get_result();
    $user    = $resUser->fetch_assoc();
    $stmtUser->close();
}

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$role = $user['role'] ?? '';

if (!in_array($role, ['super_admin', 'bagian_umum'], true)) {
    header("Location: ../auth/unauthorized.php");
    exit;
}

/* =========================================================
   2. NOTIFIKASI UNTUK NAVBAR ADMIN (BADGE + DROPDOWN)
   ========================================================= */

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

// Riwayat 10 notifikasi terbaru (untuk dropdown)
$notifList = [];
if ($stmtNotif = $conn->prepare("
    SELECT 
        id_notifikasi,
        id_pinjam,
        judul,
        pesan,
        tipe,
        created_at,
        is_read
    FROM notifikasi
    WHERE id_user = ?
    ORDER BY created_at DESC
    LIMIT 10
")) {
    $stmtNotif->bind_param("i", $id_user_login);
    $stmtNotif->execute();
    $resNotif = $stmtNotif->get_result();
    while ($row = $resNotif->fetch_assoc()) {
        $notifList[] = $row;
    }
    $stmtNotif->close();
}

/* =========================================================
   3. FLASH MESSAGE
   ========================================================= */
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/* =========================================================
   4. HELPER: AMBIL SATU NILAI INTEGER
   ========================================================= */
function getOneInt(mysqli $conn, string $sql, int $id): ?int {
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if ($row) {
            $val = array_values($row)[0];
            return $val !== null ? (int)$val : null;
        }
    }
    return null;
}

/* =========================================================
   4b. HELPER: KIRIM NOTIFIKASI TINDAK LANJUT KE PEMINJAM
   ========================================================= */
function kirimNotifikasiTindakLanjut(mysqli $conn, int $id_kembali, string $statusTl): void
{
    // Ambil id_pinjam & id_user peminjam
    $sql = "
        SELECT p.id_pinjam, p.id_user
        FROM pengembalian pg
        JOIN peminjaman p ON pg.id_pinjam = p.id_pinjam
        WHERE pg.id_kembali = ?
        LIMIT 1
    ";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_kembali);
        $stmt->execute();
        $stmt->bind_result($id_pinjam, $id_user_peminjam);
        if ($stmt->fetch()) {
            $stmt->close();

            $judul = "Tindak Lanjut Kerusakan Peminjaman #{$id_pinjam}";
            $pesan = "Petugas telah membuat/ memperbarui tindak lanjut kerusakan "
                   . "untuk peminjaman #{$id_pinjam}. Status tindak lanjut: {$statusTl}.";

            $sqlIns = "
                INSERT INTO notifikasi (id_user, id_pinjam, judul, pesan, tipe, created_at, is_read, dibaca)
                VALUES (?, ?, ?, ?, 'info', NOW(), 0, 0)
            ";
            if ($stmtIns = $conn->prepare($sqlIns)) {
                $stmtIns->bind_param("iiss", $id_user_peminjam, $id_pinjam, $judul, $pesan);
                $stmtIns->execute();
                $stmtIns->close();
            }
        } else {
            $stmt->close();
        }
    }
}

/* =========================================================
   5. UPDATE KONDISI PENGEMBALIAN
      - Jika kondisi = rusak → otomatis buat TINDAKLANJUT (status: proses)
      - Jika kondisi = bagus → peminjaman.status diubah menjadi 'selesai'
   ========================================================= */
if (isset($_POST['update']) && in_array($role, ['super_admin', 'bagian_umum'], true)) {
    $id_kembali  = (int) ($_POST['id_kembali'] ?? 0);
    $kondisi     = trim($_POST['kondisi'] ?? '');
    $catatan     = trim($_POST['catatan'] ?? '');
    $tgl_kembali = trim($_POST['tgl_kembali'] ?? '');

    $allowedKondisi = ['bagus', 'rusak'];
    $patternDate    = '/^\d{4}-\d{2}-\d{2}$/';

    if ($id_kembali <= 0) {
        $_SESSION['error'] = "ID pengembalian tidak valid.";
    } elseif (!in_array($kondisi, $allowedKondisi, true)) {
        $_SESSION['error'] = "Kondisi pengembalian tidak valid.";
    } elseif ($tgl_kembali === '' || !preg_match($patternDate, $tgl_kembali)) {
        $_SESSION['error'] = "Tanggal kembali harus diisi dengan format YYYY-MM-DD.";
    } elseif (strlen($catatan) > 500) {
        $_SESSION['error'] = "Catatan terlalu panjang (maksimal 500 karakter).";
    } else {
        if ($stmtUpd = $conn->prepare("
            UPDATE pengembalian 
            SET kondisi = ?, catatan = ?, tgl_kembali = ?
            WHERE id_kembali = ?
        ")) {
            $stmtUpd->bind_param("sssi", $kondisi, $catatan, $tgl_kembali, $id_kembali);
            if ($stmtUpd->execute()) {

                // Ambil id_pinjam dari pengembalian ini
                $id_pinjam_for_kembali = getOneInt(
                    $conn,
                    "SELECT id_pinjam FROM pengembalian WHERE id_kembali = ? LIMIT 1",
                    $id_kembali
                );

                // HANYA JIKA kondisi = 'bagus' → peminjaman dianggap selesai
                if ($id_pinjam_for_kembali !== null && $kondisi === 'bagus') {
                    if ($stmtUpStatus = $conn->prepare("
                        UPDATE peminjaman
                        SET status = 'selesai'
                        WHERE id_pinjam = ?
                          AND status = 'diterima'
                    ")) {
                        $stmtUpStatus->bind_param("i", $id_pinjam_for_kembali);
                        $stmtUpStatus->execute();
                        $stmtUpStatus->close();
                    }
                }

                // Jika kondisi rusak → buat TINDAK LANJUT otomatis (jika belum ada)
                if ($kondisi === 'rusak') {
                    $sudahAdaTL = getOneInt(
                        $conn,
                        "SELECT id_tindaklanjut FROM tindaklanjut WHERE id_kembali = ? LIMIT 1",
                        $id_kembali
                    );

                    if ($sudahAdaTL === null) {
                        $tindakanDefault  = 'Perbaikan fasilitas';
                        $deskripsiDefault = 'Fasilitas mengalami kerusakan dan perlu diperbaiki.';
                        $statusDefault    = 'proses';

                        if ($stmtTL = $conn->prepare("
                            INSERT INTO tindaklanjut (id_kembali, id_user, tindakan, deskripsi, status, tanggal)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ")) {
                            $stmtTL->bind_param(
                                "iisss",
                                $id_kembali,
                                $id_user_login,
                                $tindakanDefault,
                                $deskripsiDefault,
                                $statusDefault
                            );
                            $stmtTL->execute();
                            $stmtTL->close();

                            // kirim notifikasi ke peminjam
                            kirimNotifikasiTindakLanjut($conn, $id_kembali, $statusDefault);
                        }
                    }
                }

                $_SESSION['success'] = "Data pengembalian berhasil diperbarui.";
            } else {
                $_SESSION['error'] = "Gagal memperbarui pengembalian: " . $stmtUpd->error;
            }
            $stmtUpd->close();
        } else {
            $_SESSION['error'] = "Gagal menyiapkan query update pengembalian.";
        }
    }

    header("Location: pengembalian.php");
    exit;
}


/* =========================================================
   6. HAPUS PENGEMBALIAN + TINDAK LANJUT (hanya super_admin)
   ========================================================= */
if (isset($_GET['hapus']) && $role === 'super_admin') {
    $id = (int) ($_GET['hapus'] ?? 0);

    if ($id > 0) {
        $conn->begin_transaction();
        try {
            if ($stmtDelTL = $conn->prepare("DELETE FROM tindaklanjut WHERE id_kembali = ?")) {
                $stmtDelTL->bind_param("i", $id);
                $stmtDelTL->execute();
                $stmtDelTL->close();
            } else {
                throw new Exception("Gagal menyiapkan query hapus tindak lanjut.");
            }

            if ($stmtDelPg = $conn->prepare("DELETE FROM pengembalian WHERE id_kembali = ?")) {
                $stmtDelPg->bind_param("i", $id);
                $stmtDelPg->execute();
                $stmtDelPg->close();
            } else {
                throw new Exception("Gagal menyiapkan query hapus pengembalian.");
            }

            $conn->commit();
            $_SESSION['success'] = "Data pengembalian dan tindak lanjut terkait berhasil dihapus.";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Gagal menghapus pengembalian: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "ID pengembalian tidak valid.";
    }

    header("Location: pengembalian.php");
    exit;
}

/* =========================================================
   7. UPDATE TINDAK LANJUT DARI MODAL
      - Jika status = selesai → kondisi pengembalian jadi bagus
        + catatan ditambah "Perbaikan selesai"
        + peminjaman.status diubah jadi 'selesai'
   ========================================================= */
if (isset($_POST['tindaklanjut']) && in_array($role, ['super_admin', 'bagian_umum'], true)) {
    $id_tindaklanjut = (int) ($_POST['id_tindaklanjut'] ?? 0);
    $status          = trim($_POST['status'] ?? '');
    $deskripsi       = trim($_POST['deskripsi'] ?? '');

    $allowedStatus = ['proses', 'selesai'];

    if ($id_tindaklanjut <= 0) {
        $_SESSION['error'] = "ID tindak lanjut tidak valid.";
    } elseif (!in_array($status, $allowedStatus, true)) {
        $_SESSION['error'] = "Status tindak lanjut tidak valid.";
    } elseif (strlen($deskripsi) > 500) {
        $_SESSION['error'] = "Deskripsi tindak lanjut terlalu panjang (maksimal 500 karakter).";
    } else {
        if ($stmtUpdTL = $conn->prepare("
            UPDATE tindaklanjut 
            SET status = ?, deskripsi = ?
            WHERE id_tindaklanjut = ?
        ")) {
            $stmtUpdTL->bind_param("ssi", $status, $deskripsi, $id_tindaklanjut);
            if ($stmtUpdTL->execute()) {

                $id_kembali = getOneInt(
                    $conn,
                    "SELECT id_kembali FROM tindaklanjut WHERE id_tindaklanjut = ? LIMIT 1",
                    $id_tindaklanjut
                );

                if ($id_kembali !== null && $status === 'selesai') {
                    // Update kondisi pengembalian jadi bagus + tambahkan catatan
                    $catatanLama = null;
                    if ($stmtCat = $conn->prepare("SELECT catatan FROM pengembalian WHERE id_kembali = ? LIMIT 1")) {
                        $stmtCat->bind_param("i", $id_kembali);
                        $stmtCat->execute();
                        $resCat = $stmtCat->get_result();
                        if ($rowCat = $resCat->fetch_assoc()) {
                            $catatanLama = $rowCat['catatan'];
                        }
                        $stmtCat->close();
                    }

                    $tambahan = 'Perbaikan selesai';
                    if ($catatanLama === null || $catatanLama === '') {
                        $catatanBaru = $tambahan;
                    } else {
                        $catatanBaru = $catatanLama . ' | ' . $tambahan;
                    }

                    if ($stmtUpdK = $conn->prepare("
                        UPDATE pengembalian
                        SET kondisi = 'bagus', catatan = ?
                        WHERE id_kembali = ?
                    ")) {
                        $stmtUpdK->bind_param("si", $catatanBaru, $id_kembali);
                        $stmtUpdK->execute();
                        $stmtUpdK->close();
                    }

                    // Setelah perbaikan selesai, peminjaman dianggap selesai juga
                    $id_pinjam_for_kembali = getOneInt(
                        $conn,
                        "SELECT id_pinjam FROM pengembalian WHERE id_kembali = ? LIMIT 1",
                        $id_kembali
                    );
                    if ($id_pinjam_for_kembali !== null) {
                        if ($stmtUpStatus = $conn->prepare("
                            UPDATE peminjaman
                            SET status = 'selesai'
                            WHERE id_pinjam = ?
                              AND status = 'diterima'
                        ")) {
                            $stmtUpStatus->bind_param("i", $id_pinjam_for_kembali);
                            $stmtUpStatus->execute();
                            $stmtUpStatus->close();
                        }
                    }
                }

                // kirim notifikasi ke peminjam tentang update TL
                if ($id_kembali !== null) {
                    kirimNotifikasiTindakLanjut($conn, $id_kembali, $status);
                }

                $_SESSION['success'] = "Data tindak lanjut berhasil diperbarui.";
            } else {
                $_SESSION['error'] = "Gagal memperbarui tindak lanjut: " . $stmtUpdTL->error;
            }
            $stmtUpdTL->close();
        } else {
            $_SESSION['error'] = "Gagal menyiapkan query update tindak lanjut.";
        }
    }

    header("Location: pengembalian.php");
    exit;
}

/* =========================================================
   8. TEMPLATE ADMIN (HEADER / NAVBAR / SIDEBAR)
   ========================================================= */
include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
include '../includes/admin/sidebar.php';
?>

<div class="container-fluid px-4">
    <!-- Header Halaman -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <h2 class="fw-bold text-warning mb-1">Kelola Pengembalian</h2>
            <p class="text-muted mb-0">
                Pemeriksaan kondisi fasilitas yang telah dikembalikan dan tindak lanjut jika terjadi kerusakan.
            </p>
        </div>
        <a href="pengembalian.php" class="btn btn-outline-warning shadow-sm">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </a>
    </div>

    <hr class="mt-0 mb-4" style="border-top: 2px solid #ffc107; opacity: 0.5;">

    <!-- Alert -->
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

    <!-- Tabel Data Pengembalian -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-warning text-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-undo-alt me-2"></i> Data Pengembalian Fasilitas</span>
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table table-bordered table-hover align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width: 4%;">No</th>
                        <th style="width: 8%;">ID Pinjam</th>
                        <th>Peminjam</th>
                        <th>Fasilitas</th>
                        <th>Tanggal Kembali</th>
                        <th>Kondisi</th>
                        <th>Catatan</th>
                        <th>Tindak Lanjut</th>
                        <th style="width: 18%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    $sql = "
                        SELECT 
                            pg.*,
                            p.id_pinjam,
                            u.nama,
                            COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_list
                        FROM pengembalian pg
                        JOIN peminjaman p ON pg.id_pinjam = p.id_pinjam
                        JOIN users u ON p.id_user = u.id_user
                        LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
                        LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
                        GROUP BY pg.id_kembali
                        ORDER BY pg.id_kembali DESC
                    ";
                    $result = mysqli_query($conn, $sql);

                    if ($result && mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)):
                            $badgeClass = ($row['kondisi'] === 'bagus') ? 'success' : 'danger';

                            // Ambil tindak lanjut (jika ada)
                            $tindak = null;
                            $idK = (int) $row['id_kembali'];
                            $qTL  = mysqli_query($conn, "
                                SELECT * FROM tindaklanjut 
                                WHERE id_kembali = $idK
                                LIMIT 1
                            ");
                            if ($qTL && mysqli_num_rows($qTL) > 0) {
                                $tindak = mysqli_fetch_assoc($qTL);
                            }

                            $fasilitasText = $row['fasilitas_list'] !== '-' 
                                ? htmlspecialchars($row['fasilitas_list']) 
                                : '<span class="text-muted fst-italic">Tidak ada data fasilitas</span>';
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++; ?></td>
                        <td class="text-center fw-semibold">#<?= (int)$row['id_pinjam']; ?></td>
                        <td><?= htmlspecialchars($row['nama']); ?></td>
                        <td><?= $fasilitasText; ?></td>
                        <td class="text-center"><?= htmlspecialchars($row['tgl_kembali']); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $badgeClass ?> text-uppercase px-3 py-2">
                                <?= htmlspecialchars($row['kondisi']); ?>
                            </span>
                        </td>
                        <td><?= nl2br(htmlspecialchars($row['catatan'] ?? '-')); ?></td>
                        <td class="text-center">
                            <?php if ($row['kondisi'] === 'rusak'): ?>
                                <?php if ($tindak): 
                                    $badgeTL = ($tindak['status'] === 'proses') ? 'warning' : 'success'; ?>
                                    <button class="btn btn-sm btn-outline-<?= $badgeTL ?> me-1"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalTL<?= $tindak['id_tindaklanjut']; ?>">
                                        <i class="fas fa-tools me-1"></i><?= ucfirst($tindak['status']); ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Belum ditindaklanjuti</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-success">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <!-- Periksa / Edit -->
                            <button class="btn btn-sm btn-outline-primary me-1 mb-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editModal<?= $row['id_kembali']; ?>">
                                <i class="fas fa-search me-1"></i> Periksa
                            </button>

                            <!-- Hapus (hanya super_admin) -->
                            <?php if ($role === 'super_admin'): ?>
                            <a href="pengembalian.php?hapus=<?= $row['id_kembali']; ?>"
                               class="btn btn-sm btn-outline-danger mb-1"
                               onclick="return confirm('Yakin ingin menghapus data pengembalian ini beserta tindak lanjutnya?');">
                                <i class="fas fa-trash me-1"></i> Hapus
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Modal Pemeriksaan Pengembalian -->
                    <div class="modal fade" id="editModal<?= $row['id_kembali']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-warning text-white">
                                    <h5 class="modal-title fw-semibold">Periksa Pengembalian</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="post">
                                    <div class="modal-body">
                                        <input type="hidden" name="id_kembali" value="<?= $row['id_kembali']; ?>">

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Kondisi</label>
                                            <select name="kondisi" class="form-select">
                                                <option value="bagus" <?= $row['kondisi'] === 'bagus' ? 'selected' : '' ?>>
                                                    Bagus
                                                </option>
                                                <option value="rusak" <?= $row['kondisi'] === 'rusak' ? 'selected' : '' ?>>
                                                    Rusak
                                                </option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Catatan</label>
                                            <textarea name="catatan" class="form-control" rows="3"><?= htmlspecialchars($row['catatan']); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Tanggal Kembali</label>
                                            <input type="date" name="tgl_kembali"
                                                   value="<?= htmlspecialchars($row['tgl_kembali']); ?>"
                                                   class="form-control" required>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="update" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i> Simpan
                                        </button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Tindak Lanjut -->
                    <?php if ($tindak): ?>
                    <div class="modal fade" id="modalTL<?= $tindak['id_tindaklanjut']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title">Tindak Lanjut Kerusakan</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="post">
                                    <div class="modal-body">
                                        <input type="hidden" name="id_tindaklanjut" value="<?= $tindak['id_tindaklanjut']; ?>">

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Deskripsi</label>
                                            <textarea name="deskripsi" class="form-control" rows="3"><?= htmlspecialchars($tindak['deskripsi']); ?></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Status</label>
                                            <select name="status" class="form-select">
                                                <option value="proses"  <?= $tindak['status'] === 'proses'  ? 'selected' : '' ?>>Proses</option>
                                                <option value="selesai" <?= $tindak['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="submit" name="tindaklanjut" class="btn btn-success">
                                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                                        </button>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            Tutup
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-3">
                            Tidak ada data pengembalian.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<br><br><br><br><br>

<?php include '../includes/admin/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('datatablesSimple');
    if (table && typeof simpleDatatables !== 'undefined') {
        new simpleDatatables.DataTable(table);
    }
});
</script>
