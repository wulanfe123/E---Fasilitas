<?php
ob_start();
session_start();
include '../config/koneksi.php';
include '../config/notifikasi_helper.php'; // <<< TAMBAHAN: helper notifikasi

/* =========================================================
   1. CEK LOGIN & ROLE (PREPARED)
   ========================================================== */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user_login = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user_login === false || $id_user_login <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Ambil data user login (role + nama)
$user = null;
$stmtUser = $conn->prepare("SELECT role, nama FROM users WHERE id_user = ? LIMIT 1");
$stmtUser->bind_param("i", $id_user_login);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
if ($resUser) {
    $user = $resUser->fetch_assoc();
}
$stmtUser->close();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$role = $user['role'] ?? '';

if (!in_array($role, ['bagian_umum', 'super_admin'], true)) {
    header("Location: ../auth/unauthorized.php");
    exit;
}

/* =========================================================
   2. NOTIFIKASI UNTUK NAVBAR ADMIN (BADGE + DROPDOWN)
   ========================================================== */
$notifPeminjaman        = [];
$notifRusak             = [];
$jumlahNotifPeminjaman  = 0;
$jumlahNotifRusak       = 0;
$jumlahNotif            = 0;

// Peminjaman baru (status usulan)
$sqlNotifP = "
    SELECT 
        p.id_pinjam,
        u.nama,
        p.tanggal_mulai
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    WHERE p.status = 'usulan'
    ORDER BY p.id_pinjam DESC
    LIMIT 5
";
$stmtNotifP = $conn->prepare($sqlNotifP);
if ($stmtNotifP) {
    $stmtNotifP->execute();
    $resNotifP = $stmtNotifP->get_result();
    while ($row = $resNotifP->fetch_assoc()) {
        $notifPeminjaman[] = $row;
    }
    $stmtNotifP->close();
}
$jumlahNotifPeminjaman = count($notifPeminjaman);

// Pengembalian dengan kondisi rusak
$sqlNotifR = "
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
";
$stmtNotifR = $conn->prepare($sqlNotifR);
if ($stmtNotifR) {
    $stmtNotifR->execute();
    $resNotifR = $stmtNotifR->get_result();
    while ($row = $resNotifR->fetch_assoc()) {
        $notifRusak[] = $row;
    }
    $stmtNotifR->close();
}
$jumlahNotifRusak = count($notifRusak);

// Total untuk badge di icon bell
$jumlahNotif = $jumlahNotifPeminjaman + $jumlahNotifRusak;

// Variable untuk badge sidebar
$statUsulan = $jumlahNotifPeminjaman;

/* =========================================================
   3. FLASH MESSAGE
   ========================================================== */
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/* =========================================================
   4. UPDATE KONDISI PENGEMBALIAN (PREPARED + VALIDASI + NOTIF)
   ========================================================== */
if (isset($_POST['update']) && in_array($role, ['bagian_umum', 'bagian_umum'], true)) {
    $id_kembali_raw = $_POST['id_kembali'] ?? 0;
    $id_kembali     = filter_var($id_kembali_raw, FILTER_VALIDATE_INT);

    $kondisi  = trim($_POST['kondisi'] ?? '');
    $catatan  = trim($_POST['catatan'] ?? '');
    $tgl_raw  = trim($_POST['tgl_kembali'] ?? '');

    $allowedKondisi = ['bagus', 'rusak'];
    $patternDate    = '/^\d{4}-\d{2}-\d{2}$/';

    if ($id_kembali === false || $id_kembali <= 0) {
        $_SESSION['error'] = "ID pengembalian tidak valid.";
    } elseif (!in_array($kondisi, $allowedKondisi, true)) {
        $_SESSION['error'] = "Kondisi pengembalian tidak valid.";
    } elseif ($tgl_raw === '' || !preg_match($patternDate, $tgl_raw)) {
        $_SESSION['error'] = "Tanggal kembali harus diisi dengan format YYYY-MM-DD.";
    } elseif (mb_strlen($catatan) > 500) {
        $_SESSION['error'] = "Catatan terlalu panjang (maksimal 500 karakter).";
    } else {

        // Ambil id_pinjam + id_user peminjam untuk notifikasi
        $id_pinjam_for_kembali     = null;
        $id_peminjam_pengembalian  = null;

        $stmtInfo = $conn->prepare("
            SELECT pg.id_pinjam, p.id_user
            FROM pengembalian pg
            JOIN peminjaman p ON pg.id_pinjam = p.id_pinjam
            WHERE pg.id_kembali = ?
            LIMIT 1
        ");
        if ($stmtInfo) {
            $stmtInfo->bind_param("i", $id_kembali);
            $stmtInfo->execute();
            $resInfo = $stmtInfo->get_result();
            if ($resInfo && $rowInfo = $resInfo->fetch_assoc()) {
                $id_pinjam_for_kembali    = (int)$rowInfo['id_pinjam'];
                $id_peminjam_pengembalian = (int)$rowInfo['id_user'];
            }
            $stmtInfo->close();
        }

        if ($id_pinjam_for_kembali === null || $id_peminjam_pengembalian === null) {
            $_SESSION['error'] = "Data peminjaman/pengembalian tidak ditemukan.";
            header("Location: pengembalian.php");
            exit;
        }

        // Update pengembalian
        $sqlUpd = "
            UPDATE pengembalian 
            SET kondisi = ?, catatan = ?, tgl_kembali = ?
            WHERE id_kembali = ?
        ";
        $stmtUpd = $conn->prepare($sqlUpd);
        if ($stmtUpd) {
            $catatanParam = $catatan !== '' ? $catatan : null;
            $stmtUpd->bind_param("sssi", $kondisi, $catatanParam, $tgl_raw, $id_kembali);
            $ok = $stmtUpd->execute();
            $stmtUpd->close();

            if ($ok) {

                // HANYA JIKA kondisi = 'bagus' → peminjaman dianggap selesai
                if ($kondisi === 'bagus') {
                    $stmtSelesai = $conn->prepare("
                        UPDATE peminjaman
                        SET status = 'selesai'
                        WHERE id_pinjam = ?
                          AND status = 'diterima'
                    ");
                    if ($stmtSelesai) {
                        $stmtSelesai->bind_param("i", $id_pinjam_for_kembali);
                        $stmtSelesai->execute();
                        $stmtSelesai->close();
                    }

                    // NOTIFIKASI ke peminjam: pengembalian diterima dengan kondisi bagus
                    if ($id_peminjam_pengembalian > 0) {
                        $judulNotif = "Pengembalian Diterima";
                        $pesanNotif = "Pengembalian fasilitas untuk peminjaman #{$id_pinjam_for_kembali} telah diperiksa dan dinyatakan dalam kondisi BAGUS. Terima kasih telah menggunakan fasilitas kampus dengan baik.";
                        if ($catatan !== '') {
                            $pesanNotif .= " Catatan: " . $catatan;
                        }
                        tambah_notif($conn, $id_peminjam_pengembalian, $id_pinjam_for_kembali, $judulNotif, $pesanNotif, 'pengembalian');
                    }

                }

                // Jika kondisi rusak → buat TINDAK LANJUT otomatis (jika belum ada)
                if ($kondisi === 'rusak') {
                    $stmtCekTL = $conn->prepare("SELECT id_tindaklanjut FROM tindaklanjut WHERE id_kembali = ? LIMIT 1");
                    $stmtCekTL->bind_param("i", $id_kembali);
                    $stmtCekTL->execute();
                    $resTL = $stmtCekTL->get_result();
                    $sudahAdaTL = $resTL && $resTL->num_rows > 0;
                    $stmtCekTL->close();

                    if (!$sudahAdaTL) {
                        $tindakanDefault  = 'Perbaikan fasilitas';
                        $deskripsiDefault = 'Fasilitas mengalami kerusakan dan perlu diperbaiki.';
                        $statusDefault    = 'proses';

                        $stmtInsTL = $conn->prepare("
                            INSERT INTO tindaklanjut (id_kembali, id_user, tindakan, deskripsi, status, tanggal)
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        if ($stmtInsTL) {
                            $stmtInsTL->bind_param(
                                "iisss",
                                $id_kembali,
                                $id_user_login,
                                $tindakanDefault,
                                $deskripsiDefault,
                                $statusDefault
                            );
                            $stmtInsTL->execute();
                            $stmtInsTL->close();
                        }
                    }

                    // NOTIFIKASI ke peminjam: pengembalian rusak → sedang ditindaklanjuti
                    if ($id_peminjam_pengembalian > 0) {
                        $judulNotif = "Pengembalian dengan Kondisi Rusak";
                        $pesanNotif = "Pengembalian fasilitas untuk peminjaman #{$id_pinjam_for_kembali} tercatat dalam kondisi RUSAK dan sedang ditindaklanjuti oleh bagian terkait.";
                        if ($catatan !== '') {
                            $pesanNotif .= " Catatan pemeriksaan: " . $catatan;
                        }
                        tambah_notif($conn, $id_peminjam_pengembalian, $id_pinjam_for_kembali, $judulNotif, $pesanNotif, 'pengembalian');
                    }
                }

                $_SESSION['success'] = "Data pengembalian berhasil diperbarui.";
            } else {
                $_SESSION['error'] = "Gagal memperbarui pengembalian.";
            }
        } else {
            $_SESSION['error'] = "Gagal menyiapkan query update pengembalian.";
        }
    }

    header("Location: pengembalian.php");
    exit;
}

/* =========================================================
   5. HAPUS PENGEMBALIAN + TINDAK LANJUT (hanya bagian_umum, PREPARED)
   ========================================================== */
if (isset($_GET['hapus']) && $role === 'bagian_umum') {
    $id_raw = $_GET['hapus'] ?? 0;
    $id     = filter_var($id_raw, FILTER_VALIDATE_INT);

    if ($id === false || $id <= 0) {
        $_SESSION['error'] = "ID pengembalian tidak valid.";
    } else {
        // Hapus tindak lanjut dulu
        $stmtDelTL = $conn->prepare("DELETE FROM tindaklanjut WHERE id_kembali = ?");
        if ($stmtDelTL) {
            $stmtDelTL->bind_param("i", $id);
            $stmtDelTL->execute();
            $stmtDelTL->close();
        }

        // Hapus pengembalian
        $stmtDelPg = $conn->prepare("DELETE FROM pengembalian WHERE id_kembali = ?");
        if ($stmtDelPg) {
            $stmtDelPg->bind_param("i", $id);
            if ($stmtDelPg->execute()) {
                $_SESSION['success'] = "Data pengembalian dan tindak lanjut terkait berhasil dihapus.";
            } else {
                $_SESSION['error'] = "Gagal menghapus pengembalian.";
            }
            $stmtDelPg->close();
        } else {
            $_SESSION['error'] = "Gagal menyiapkan query hapus pengembalian.";
        }
    }

    header("Location: pengembalian.php");
    exit;
}

/* =========================================================
   6. UPDATE TINDAK LANJUT DARI MODAL (PREPARED + VALIDASI + NOTIF)
   ========================================================== */
if (isset($_POST['tindaklanjut']) && in_array($role, ['bagian_umum', 'bagian_umum'], true)) {
    $id_tl_raw        = $_POST['id_tindaklanjut'] ?? 0;
    $id_tindaklanjut  = filter_var($id_tl_raw, FILTER_VALIDATE_INT);

    $status    = trim($_POST['status'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    $allowedStatus = ['proses', 'selesai'];

    if ($id_tindaklanjut === false || $id_tindaklanjut <= 0) {
        $_SESSION['error'] = "ID tindak lanjut tidak valid.";
    } elseif (!in_array($status, $allowedStatus, true)) {
        $_SESSION['error'] = "Status tindak lanjut tidak valid.";
    } elseif (mb_strlen($deskripsi) > 500) {
        $_SESSION['error'] = "Deskripsi tindak lanjut terlalu panjang (maksimal 500 karakter).";
    } else {
        // Update tindaklanjut
        $sqlUpdTL = "
            UPDATE tindaklanjut 
            SET status = ?, deskripsi = ?
            WHERE id_tindaklanjut = ?
        ";
        $stmtUpdTL = $conn->prepare($sqlUpdTL);
        if ($stmtUpdTL) {
            $deskripsiParam = $deskripsi !== '' ? $deskripsi : null;
            $stmtUpdTL->bind_param("ssi", $status, $deskripsiParam, $id_tindaklanjut);
            $okTL = $stmtUpdTL->execute();
            $stmtUpdTL->close();

            if ($okTL) {
                // Ambil id_kembali
                $id_kembali = null;
                $stmtGetK = $conn->prepare("SELECT id_kembali FROM tindaklanjut WHERE id_tindaklanjut = ? LIMIT 1");
                $stmtGetK->bind_param("i", $id_tindaklanjut);
                $stmtGetK->execute();
                $resK = $stmtGetK->get_result();
                if ($resK && $rk = $resK->fetch_assoc()) {
                    $id_kembali = (int)$rk['id_kembali'];
                }
                $stmtGetK->close();

                // Jika tindak lanjut selesai → update pengembalian & peminjaman + notif
                if ($id_kembali !== null && $status === 'selesai') {
                    // Ambil catatan lama
                    $catatanLama = '';
                    $stmtCat = $conn->prepare("SELECT catatan, id_pinjam FROM pengembalian WHERE id_kembali = ? LIMIT 1");
                    $stmtCat->bind_param("i", $id_kembali);
                    $stmtCat->execute();
                    $resCat = $stmtCat->get_result();

                    $id_pinjam_for_kembali = null;

                    if ($resCat && $rc = $resCat->fetch_assoc()) {
                        $catatanLama          = $rc['catatan'] ?? '';
                        $id_pinjam_for_kembali = (int)$rc['id_pinjam'];
                    }
                    $stmtCat->close();

                    $tambahan = 'Perbaikan selesai';
                    if ($catatanLama === '' || $catatanLama === null) {
                        $catatanBaru = $tambahan;
                    } else {
                        $catatanBaru = $catatanLama . ' | ' . $tambahan;
                    }

                    // Update pengembalian → kondisi bagus + catatanBaru
                    $stmtUpdPg = $conn->prepare("
                        UPDATE pengembalian
                        SET kondisi = 'bagus', catatan = ?
                        WHERE id_kembali = ?
                    ");
                    if ($stmtUpdPg) {
                        $stmtUpdPg->bind_param("si", $catatanBaru, $id_kembali);
                        $stmtUpdPg->execute();
                        $stmtUpdPg->close();
                    }

                    // Setelah perbaikan selesai, peminjaman dianggap selesai juga
                    if ($id_pinjam_for_kembali !== null) {
                        $stmtSelesai2 = $conn->prepare("
                            UPDATE peminjaman
                            SET status = 'selesai'
                            WHERE id_pinjam = ?
                              AND status = 'diterima'
                        ");
                        if ($stmtSelesai2) {
                            $stmtSelesai2->bind_param("i", $id_pinjam_for_kembali);
                            $stmtSelesai2->execute();
                            $stmtSelesai2->close();
                        }

                        // Ambil id_user peminjam → untuk notifikasi
                        $id_peminjam_notif = null;
                        $stmtPem = $conn->prepare("
                            SELECT p.id_user
                            FROM peminjaman p
                            WHERE p.id_pinjam = ?
                            LIMIT 1
                        ");
                        if ($stmtPem) {
                            $stmtPem->bind_param("i", $id_pinjam_for_kembali);
                            $stmtPem->execute();
                            $resPem = $stmtPem->get_result();
                            if ($resPem && $rp = $resPem->fetch_assoc()) {
                                $id_peminjam_notif = (int)$rp['id_user'];
                            }
                            $stmtPem->close();
                        }

                        if ($id_peminjam_notif !== null && $id_peminjam_notif > 0) {
                            $judulNotif = "Tindak Lanjut Kerusakan Selesai";
                            $pesanNotif = "Tindak lanjut kerusakan pada peminjaman #{$id_pinjam_for_kembali} telah dinyatakan SELESAI. Fasilitas telah diperbaiki dan peminjaman dinyatakan selesai.";
                            if ($deskripsi !== '') {
                                $pesanNotif .= " Rincian tindak lanjut: " . $deskripsi;
                            }
                            tambah_notif($conn, $id_peminjam_notif, $id_pinjam_for_kembali, $judulNotif, $pesanNotif, 'pengembalian');
                        }
                    }
                }

                $_SESSION['success'] = "Data tindak lanjut berhasil diperbarui.";
            } else {
                $_SESSION['error'] = "Gagal memperbarui tindak lanjut.";
            }
        } else {
            $_SESSION['error'] = "Gagal menyiapkan query update tindak lanjut.";
        }
    }

    header("Location: pengembalian.php");
    exit;
}

/* =========================================================
   7. TEMPLATE ADMIN (TIDAK DIUBAH)
   ========================================================== */
$pageTitle   = 'Kelola Pengembalian';
$currentPage = 'pengembalian';

include '../includes/admin/header.php';
include '../includes/admin/sidebar.php';
?>

<style>
    .pengembalian-title {
        font-size: 1.4rem;
    }
    .pengembalian-subtitle {
        font-size: 0.9rem;
    }
    .card-pengembalian table thead th {
        font-size: 0.85rem;
        text-transform: none;
        letter-spacing: .03em;
    }
    .card-pengembalian table tbody td {
        font-size: 0.9rem;
    }
    .badge-kondisi {
        font-size: 0.8rem;
        padding: .4rem .7rem;
        font-weight: 600;
    }
    .btn-action {
        padding: 0.35rem 0.7rem;
        border-radius: 6px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-action i {
        font-size: 0.9rem;
    }
</style>

<!-- Main Content Area -->
<div id="layoutSidenav_content">
    
    <?php include '../includes/admin/navbar.php'; ?>

    <main>
        <div class="container-fluid px-4">

            <!-- Header Halaman -->
            <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
                <div>
                    <h2 class="fw-bold text-warning mb-1 pengembalian-title">
                        <i class="fas fa-undo-alt me-2"></i>
                        Kelola Pengembalian
                    </h2>
                    <p class="text-muted mb-0 pengembalian-subtitle">
                        Pemeriksaan kondisi fasilitas yang telah dikembalikan dan tindak lanjut jika terjadi kerusakan.
                    </p>
                </div>
                <a href="pengembalian.php" class="btn btn-outline-warning shadow-sm">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </a>
            </div>

            <hr class="mt-0 mb-4" style="border-top: 2px solid #0f172a; opacity: .25;">

            <!-- Alert -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Tabel Data Pengembalian -->
            <div class="card shadow-sm border-0 mb-4 card-pengembalian">
                <div class="card-header bg-warning text-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i> Data Pengembalian Fasilitas</span>
                    <span class="small opacity-75">
                        Total: <?php 
                            $totalPeng = 0;
                            $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM pengembalian");
                            if ($stmtCount) {
                                $stmtCount->execute();
                                $resCount = $stmtCount->get_result();
                                if ($resCount && $rc = $resCount->fetch_assoc()) {
                                    $totalPeng = (int)$rc['total'];
                                }
                                $stmtCount->close();
                            }
                            echo $totalPeng;
                        ?> pengembalian
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="datatablesSimple" class="table table-bordered table-hover align-middle">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 90px;">ID Pinjam</th>
                                    <th>Peminjam</th>
                                    <th>Fasilitas</th>
                                    <th style="width: 130px;">Tanggal Kembali</th>
                                    <th style="width: 100px;">Kondisi</th>
                                    <th>Catatan</th>
                                    <th style="width: 130px;">Tindak Lanjut</th>
                                    <th style="width: 180px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $sqlList = "
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
                                $stmtList = $conn->prepare($sqlList);
                                $result   = null;
                                if ($stmtList) {
                                    $stmtList->execute();
                                    $result = $stmtList->get_result();
                                }

                                if ($result && $result->num_rows > 0):
                                    while ($row = $result->fetch_assoc()):
                                        $badgeClass = ($row['kondisi'] === 'bagus') ? 'success' : 'danger';

                                        // Ambil tindak lanjut (jika ada)
                                        $tindak = null;
                                        $idK    = (int) $row['id_kembali'];
                                        $stmtTL = $conn->prepare("
                                            SELECT * FROM tindaklanjut 
                                            WHERE id_kembali = ?
                                            LIMIT 1
                                        ");
                                        if ($stmtTL) {
                                            $stmtTL->bind_param("i", $idK);
                                            $stmtTL->execute();
                                            $resTL = $stmtTL->get_result();
                                            if ($resTL && $resTL->num_rows > 0) {
                                                $tindak = $resTL->fetch_assoc();
                                            }
                                            $stmtTL->close();
                                        }

                                        $fasilitasText = $row['fasilitas_list'] !== '-' 
                                            ? htmlspecialchars($row['fasilitas_list']) 
                                            : '<span class="text-muted fst-italic">Tidak ada data fasilitas</span>';
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++; ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">#<?= (int)$row['id_pinjam']; ?></span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($row['nama']); ?></strong></td>
                                    <td><?= $fasilitasText; ?></td>
                                    <td class="text-center"><?= date('d-m-Y', strtotime($row['tgl_kembali'])); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $badgeClass ?> text-uppercase badge-kondisi">
                                            <?= htmlspecialchars($row['kondisi']); ?>
                                        </span>
                                    </td>
                                    <td><?= nl2br(htmlspecialchars($row['catatan'] ?? '-')); ?></td>
                                    <td class="text-center">
                                        <?php if ($row['kondisi'] === 'rusak'): ?>
                                            <?php if ($tindak): 
                                                $badgeTL = ($tindak['status'] === 'proses') ? 'warning' : 'success'; ?>
                                                <button class="btn btn-sm btn-outline-<?= $badgeTL ?> btn-action"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#modalTL<?= $tindak['id_tindaklanjut']; ?>"
                                                        title="Lihat Tindak Lanjut">
                                                    <i class="fas fa-tools me-1"></i><?= ucfirst($tindak['status']); ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic small">Belum ditindaklanjuti</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-success fw-bold">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Periksa / Edit -->
                                        <button class="btn btn-sm btn-outline-primary btn-action me-1 mb-1"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editModal<?= $row['id_kembali']; ?>"
                                                title="Periksa Pengembalian">
                                            <i class="fas fa-search"></i>
                                        </button>

                                        <!-- Hapus (hanya bagian_umum) -->
                                        <?php if ($role === 'bagian_umum'): ?>
                                        <a href="pengembalian.php?hapus=<?= $row['id_kembali']; ?>"
                                           class="btn btn-sm btn-outline-danger btn-action mb-1"
                                           onclick="return confirm('Yakin ingin menghapus data pengembalian ini beserta tindak lanjutnya?');"
                                           title="Hapus Pengembalian">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <!-- Modal Pemeriksaan Pengembalian -->
                                <div class="modal fade" id="editModal<?= $row['id_kembali']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-warning text-white">
                                                <h5 class="modal-title fw-semibold">
                                                    <i class="fas fa-clipboard-check me-2"></i>
                                                    Periksa Pengembalian
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_kembali" value="<?= $row['id_kembali']; ?>">

                                                    <div class="alert alert-info">
                                                        <strong><i class="fas fa-user me-1"></i> Peminjam:</strong> <?= htmlspecialchars($row['nama']); ?><br>
                                                        <strong><i class="fas fa-building me-1"></i> Fasilitas:</strong> <?= strip_tags($fasilitasText); ?>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">
                                                            <i class="fas fa-clipboard-check me-1"></i>
                                                            Kondisi <span class="text-danger">*</span>
                                                        </label>
                                                        <select name="kondisi" class="form-select" required>
                                                            <option value="bagus" <?= $row['kondisi'] === 'bagus' ? 'selected' : '' ?>>
                                                                ✅ Bagus (Tidak ada kerusakan)
                                                            </option>
                                                            <option value="rusak" <?= $row['kondisi'] === 'rusak' ? 'selected' : '' ?>>
                                                                ❌ Rusak (Perlu tindak lanjut)
                                                            </option>
                                                        </select>
                                                        <small class="text-muted">
                                                            <i class="fas fa-info-circle me-1"></i>
                                                            Pilih "Rusak" jika fasilitas mengalami kerusakan. Tindak lanjut akan dibuat otomatis.
                                                        </small>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">
                                                            <i class="fas fa-sticky-note me-1"></i>
                                                            Catatan
                                                        </label>
                                                        <textarea name="catatan" 
                                                                  class="form-control" 
                                                                  rows="3"
                                                                  maxlength="500"
                                                                  placeholder="Catatan pemeriksaan pengembalian (opsional)"><?= htmlspecialchars($row['catatan']); ?></textarea>
                                                        <small class="text-muted">Maksimal 500 karakter</small>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">
                                                            <i class="fas fa-calendar-alt me-1"></i>
                                                            Tanggal Kembali <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="date" name="tgl_kembali"
                                                               value="<?= htmlspecialchars($row['tgl_kembali']); ?>"
                                                               class="form-control" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        <i class="fas fa-times me-1"></i> Batal
                                                    </button>
                                                    <button type="submit" name="update" class="btn btn-success">
                                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
                                                    </button>
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
                                                <h5 class="modal-title">
                                                    <i class="fas fa-tools me-2"></i>
                                                    Tindak Lanjut Kerusakan
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <input type="hidden" name="id_tindaklanjut" value="<?= $tindak['id_tindaklanjut']; ?>">

                                                    <div class="alert alert-warning">
                                                        <strong><i class="fas fa-exclamation-triangle me-1"></i> Info:</strong> 
                                                        Jika status diubah menjadi "Selesai", kondisi pengembalian akan otomatis berubah menjadi "Bagus" dan peminjaman akan ditandai sebagai "Selesai".
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">
                                                            <i class="fas fa-comment-alt me-1"></i>
                                                            Deskripsi Tindakan
                                                        </label>
                                                        <textarea name="deskripsi" 
                                                                  class="form-control" 
                                                                  rows="4"
                                                                  maxlength="500"
                                                                  placeholder="Deskripsi tindakan perbaikan atau penanganan kerusakan..."><?= htmlspecialchars($tindak['deskripsi']); ?></textarea>
                                                        <small class="text-muted">Maksimal 500 karakter</small>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">
                                                            <i class="fas fa-flag me-1"></i>
                                                            Status Tindak Lanjut <span class="text-danger">*</span>
                                                        </label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="proses"  <?= $tindak['status'] === 'proses'  ? 'selected' : '' ?>>
                                                                ⏳ Dalam Proses
                                                            </option>
                                                            <option value="selesai" <?= $tindak['status'] === 'selesai' ? 'selected' : '' ?>>
                                                                ✅ Selesai
                                                            </option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                        <i class="fas fa-times me-1"></i> Tutup
                                                    </button>
                                                    <button type="submit" name="tindaklanjut" class="btn btn-success">
                                                        <i class="fas fa-save me-1"></i> Simpan Perubahan
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
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                        Tidak ada data pengembalian.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// DataTables Init
$(document).ready(function() {
    $('#datatablesSimple').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        pageLength: 10,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [8] }
        ]
    });
});
</script>
