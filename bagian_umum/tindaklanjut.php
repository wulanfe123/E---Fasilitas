<?php
session_start();
include '../config/koneksi.php';
include '../config/notifikasi_helper.php'; // <<< TAMBAHAN: helper notifikasi

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
if ($stmtNP = $conn->prepare($sqlNotifP)) {
    $stmtNP->execute();
    $resNP = $stmtNP->get_result();
    while ($row = $resNP->fetch_assoc()) {
        $notifPeminjaman[] = $row;
    }
    $stmtNP->close();
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
if ($stmtNR = $conn->prepare($sqlNotifR)) {
    $stmtNR->execute();
    $resNR = $stmtNR->get_result();
    while ($row = $resNR->fetch_assoc()) {
        $notifRusak[] = $row;
    }
    $stmtNR->close();
}
$jumlahNotifRusak = count($notifRusak);

// Total untuk badge di icon bell
$jumlahNotif = $jumlahNotifPeminjaman + $jumlahNotifRusak;

// Variable untuk badge sidebar
$statUsulan = $jumlahNotifPeminjaman;

/* ==================================
   AMBIL DATA TINDAK LANJUT (UNTUK EDIT)
   ================================== */
$editData = null;
if (isset($_GET['edit'])) {
    $id_tindaklanjut = (int) $_GET['edit'];
    if ($id_tindaklanjut > 0) {
        $sqlEdit = "SELECT * FROM tindaklanjut WHERE id_tindaklanjut = ? LIMIT 1";
        if ($stmtEdit = $conn->prepare($sqlEdit)) {
            $stmtEdit->bind_param("i", $id_tindaklanjut);
            $stmtEdit->execute();
            $resultEdit = $stmtEdit->get_result();
            $editData   = $resultEdit->fetch_assoc() ?: null;
            $stmtEdit->close();
        }
    }
}

/* ==================================
   UPDATE DATA TINDAK LANJUT KERUSAKAN
   (FORM INPUT -> PREPARED + NOTIF)
   ================================== */
if (isset($_POST['update'])) {
    $id_tindaklanjut = (int) ($_POST['id_tindaklanjut'] ?? 0);
    $tindakan        = trim($_POST['tindakan'] ?? '');
    $deskripsi       = trim($_POST['deskripsi'] ?? '');
    $status          = trim($_POST['status'] ?? '');

    // VALIDASI INPUT
    if ($id_tindaklanjut <= 0) {
        $_SESSION['error'] = "Data tindak lanjut tidak valid.";
    } elseif ($tindakan === '') {
        $_SESSION['error'] = "Tindakan tidak boleh kosong.";
    } elseif (mb_strlen($tindakan) > 255) {
        $_SESSION['error'] = "Tindakan terlalu panjang (maksimal 255 karakter).";
    } elseif (mb_strlen($deskripsi) > 500) {
        $_SESSION['error'] = "Deskripsi terlalu panjang (maksimal 500 karakter).";
    } elseif (!in_array($status, ['proses','selesai'], true)) {
        $_SESSION['error'] = "Status tindak lanjut tidak valid.";
    } else {
        // UPDATE tindaklanjut pakai prepared statement
        $sql = "
            UPDATE tindaklanjut 
            SET tindakan = ?, 
                deskripsi = ?, 
                status = ?
            WHERE id_tindaklanjut = ?
        ";

        if ($stmtUpd = $conn->prepare($sql)) {
            $stmtUpd->bind_param("sssi", $tindakan, $deskripsi, $status, $id_tindaklanjut);
            $execUpd = $stmtUpd->execute();
            $stmtUpd->close();

            if ($execUpd) {
                // Ambil id_kembali + id_pinjam + id_user peminjam
                $id_kembali              = 0;
                $id_pinjam_for_tl        = null;
                $id_peminjam_for_notif   = null;

                $sqlTL = "
                    SELECT 
                        tl.id_kembali,
                        pg.id_pinjam,
                        p.id_user
                    FROM tindaklanjut tl
                    JOIN pengembalian pg ON tl.id_kembali = pg.id_kembali
                    JOIN peminjaman p    ON pg.id_pinjam  = p.id_pinjam
                    WHERE tl.id_tindaklanjut = ?
                    LIMIT 1
                ";
                if ($stmtTL = $conn->prepare($sqlTL)) {
                    $stmtTL->bind_param("i", $id_tindaklanjut);
                    $stmtTL->execute();
                    $resTL = $stmtTL->get_result();
                    if ($rowTL = $resTL->fetch_assoc()) {
                        $id_kembali            = (int) $rowTL['id_kembali'];
                        $id_pinjam_for_tl      = (int) $rowTL['id_pinjam'];
                        $id_peminjam_for_notif = (int) $rowTL['id_user'];
                    }
                    $stmtTL->close();
                }

                // Jika status selesai → update pengembalian & peminjaman + NOTIF
                if ($id_kembali > 0 && $status === 'selesai') {
                    // 1) Update pengembalian → kondisi jadi bagus
                    $sqlPeng = "
                        UPDATE pengembalian 
                        SET kondisi = 'bagus'
                        WHERE id_kembali = ?
                    ";
                    if ($stmtPeng = $conn->prepare($sqlPeng)) {
                        $stmtPeng->bind_param("i", $id_kembali);
                        $stmtPeng->execute();
                        $stmtPeng->close();
                    }

                    // 2) peminjaman dianggap selesai juga (jika masih diterima)
                    $sqlPinjam = "
                        UPDATE peminjaman p
                        JOIN pengembalian pg ON p.id_pinjam = pg.id_pinjam
                        SET p.status = 'selesai'
                        WHERE pg.id_kembali = ?
                          AND p.status = 'diterima'
                    ";
                    if ($stmtPinjam = $conn->prepare($sqlPinjam)) {
                        $stmtPinjam->bind_param("i", $id_kembali);
                        $stmtPinjam->execute();
                        $stmtPinjam->close();
                    }

                    // 3) NOTIFIKASI ke peminjam bahwa tindak lanjut kerusakan selesai
                    if ($id_peminjam_for_notif !== null && $id_peminjam_for_notif > 0 && $id_pinjam_for_tl !== null) {
                        $judulNotif = "Tindak Lanjut Kerusakan Selesai";
                        $pesanNotif = "Tindak lanjut kerusakan pada peminjaman #{$id_pinjam_for_tl} telah dinyatakan SELESAI. "
                                    . "Fasilitas telah diperbaiki dan peminjaman dinyatakan selesai.";
                        if ($deskripsi !== '') {
                            $pesanNotif .= " Rincian tindak lanjut: " . $deskripsi;
                        }

                        // tipe bisa kamu atur misalnya 'tindaklanjut'
                        tambah_notif(
                            $conn,
                            $id_peminjam_for_notif,
                            $id_pinjam_for_tl,
                            $judulNotif,
                            $pesanNotif,
                            'tindaklanjut'
                        );
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

    header("Location: tindaklanjut.php");
    exit;
}

/* ==================================
   AMBIL DATA UNTUK TAMPILAN
   (FILTER -> PREPARED STATEMENT)
   ================================== */

/*
   Kita buat 4 kondisi:
   1) Tanpa filter
   2) Hanya q
   3) Hanya status
   4) q + status
*/

/* --------- DATA TINDAK LANJUT (TAB 1) --------- */
$dataTindak = null;

$baseSelectTL = "
    SELECT 
        tl.*,
        pg.id_pinjam,
        u.nama AS nama_peminjam
    FROM tindaklanjut tl
    LEFT JOIN pengembalian pg ON tl.id_kembali = pg.id_kembali
    LEFT JOIN peminjaman p    ON pg.id_pinjam = p.id_pinjam
    LEFT JOIN users u         ON p.id_user = u.id_user
";

if ($filter_q === '' && $filter_status === '') {
    // tanpa filter
    $sqlDataTindak = $baseSelectTL . "
        WHERE 1=1
        ORDER BY tl.id_tindaklanjut DESC
    ";
    $stmtTL = $conn->prepare($sqlDataTindak);
} elseif ($filter_q !== '' && $filter_status === '') {
    // hanya q
    $sqlDataTindak = $baseSelectTL . "
        WHERE 
            (
                tl.id_tindaklanjut LIKE ?
                OR tl.id_kembali LIKE ?
                OR tl.tindakan LIKE ?
                OR tl.deskripsi LIKE ?
                OR pg.id_pinjam LIKE ?
                OR u.nama LIKE ?
            )
        ORDER BY tl.id_tindaklanjut DESC
    ";
    $stmtTL = $conn->prepare($sqlDataTindak);
    $like = '%' . $filter_q . '%';
    $stmtTL->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
} elseif ($filter_q === '' && $filter_status !== '') {
    // hanya status
    $sqlDataTindak = $baseSelectTL . "
        WHERE tl.status = ?
        ORDER BY tl.id_tindaklanjut DESC
    ";
    $stmtTL = $conn->prepare($sqlDataTindak);
    $stmtTL->bind_param("s", $filter_status);
} else {
    // q + status
    $sqlDataTindak = $baseSelectTL . "
        WHERE 
            (
                tl.id_tindaklanjut LIKE ?
                OR tl.id_kembali LIKE ?
                OR tl.tindakan LIKE ?
                OR tl.deskripsi LIKE ?
                OR pg.id_pinjam LIKE ?
                OR u.nama LIKE ?
            )
            AND tl.status = ?
        ORDER BY tl.id_tindaklanjut DESC
    ";
    $stmtTL = $conn->prepare($sqlDataTindak);
    $like = '%' . $filter_q . '%';
    $stmtTL->bind_param("sssssss", $like, $like, $like, $like, $like, $like, $filter_status);
}

if ($stmtTL) {
    $stmtTL->execute();
    $dataTindak = $stmtTL->get_result();
}

/* --------- REKAP TINDAK LANJUT (TAB 2) --------- */
$dataRekap = null;

$baseSelectRekap = "
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
";

if ($filter_q === '' && $filter_status === '') {
    $sqlRekap = $baseSelectRekap . "
        WHERE 1=1
        GROUP BY tl.id_tindaklanjut
        ORDER BY tl.tanggal DESC
    ";
    $stmtRekap = $conn->prepare($sqlRekap);
} elseif ($filter_q !== '' && $filter_status === '') {
    $sqlRekap = $baseSelectRekap . "
        WHERE 
            (
                tl.id_tindaklanjut LIKE ?
                OR tl.id_kembali LIKE ?
                OR tl.tindakan LIKE ?
                OR tl.deskripsi LIKE ?
                OR pg.id_pinjam LIKE ?
                OR u.nama LIKE ?
            )
        GROUP BY tl.id_tindaklanjut
        ORDER BY tl.tanggal DESC
    ";
    $stmtRekap = $conn->prepare($sqlRekap);
    $like = '%' . $filter_q . '%';
    $stmtRekap->bind_param("ssssss", $like, $like, $like, $like, $like, $like);
} elseif ($filter_q === '' && $filter_status !== '') {
    $sqlRekap = $baseSelectRekap . "
        WHERE tl.status = ?
        GROUP BY tl.id_tindaklanjut
        ORDER BY tl.tanggal DESC
    ";
    $stmtRekap = $conn->prepare($sqlRekap);
    $stmtRekap->bind_param("s", $filter_status);
} else {
    $sqlRekap = $baseSelectRekap . "
        WHERE 
            (
                tl.id_tindaklanjut LIKE ?
                OR tl.id_kembali LIKE ?
                OR tl.tindakan LIKE ?
                OR tl.deskripsi LIKE ?
                OR pg.id_pinjam LIKE ?
                OR u.nama LIKE ?
            )
            AND tl.status = ?
        GROUP BY tl.id_tindaklanjut
        ORDER BY tl.tanggal DESC
    ";
    $stmtRekap = $conn->prepare($sqlRekap);
    $like = '%' . $filter_q . '%';
    $stmtRekap->bind_param("sssssss", $like, $like, $like, $like, $like, $like, $filter_status);
}

if ($stmtRekap) {
    $stmtRekap->execute();
    $dataRekap = $stmtRekap->get_result();
}

/* ==================================
   TEMPLATE ADMIN
   ================================== */
$pageTitle   = 'Tindak Lanjut Kerusakan';
$currentPage = 'tindaklanjut';

include '../includes/admin/header.php';
include '../includes/admin/sidebar.php';
?>

<style>
    .tindaklanjut-title {
        font-size: 1.4rem;
    }
    .tindaklanjut-subtitle {
        font-size: 0.9rem;
    }
    .tab-card {
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        border: 1px solid #e5e7eb;
    }
    .nav-tabs {
        border-bottom: none;
        gap: .5rem;
    }
    .nav-tabs .nav-link {
        font-weight: 600;
        color: #6b7280;
        border-radius: 10px;
        border: 1px solid transparent;
        padding: 0.6rem 1.3rem;
        transition: all 0.3s ease;
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
    .badge-status {
        font-size: 0.8rem;
        padding: .4rem .7rem;
        font-weight: 600;
    }
    .btn-action {
        padding: 0.35rem 0.7rem;
        border-radius: 6px;
        font-size: 0.85rem;
    }
    .filter-section {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1rem;
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
                    <h2 class="fw-bold text-danger mb-1 tindaklanjut-title">
                        <i class="fas fa-tools me-2"></i>
                        Tindak Lanjut Kerusakan
                    </h2>
                    <p class="text-muted mb-0 tindaklanjut-subtitle">
                        Kelola tindak lanjut kerusakan fasilitas dan pantau rekap komunikasi peminjam dengan admin.
                    </p>
                </div>
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

            <!-- NAV TABS -->
            <ul class="nav nav-tabs mb-3" id="tlTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-tl-tab" data-bs-toggle="tab" data-bs-target="#tab-tl"
                            type="button" role="tab" aria-controls="tab-tl" aria-selected="true">
                        <i class="fas fa-tools me-2"></i> Tindak Lanjut
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-rekap-tab" data-bs-toggle="tab" data-bs-target="#tab-rekap"
                            type="button" role="tab" aria-controls="tab-rekap" aria-selected="false">
                        <i class="fas fa-chart-bar me-2"></i> Rekap & Laporan
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="tlTabContent">
                <!-- TAB 1: TINDAK LANJUT -->
                <div class="tab-pane fade show active" id="tab-tl" role="tabpanel" aria-labelledby="tab-tl-tab">

                    <?php if ($editData): ?>
                    <!-- FORM EDIT -->
                    <div class="card tab-card mb-4">
                        <div class="card-body">
                            <h5 class="fw-semibold mb-3">
                                <i class="fas fa-edit me-2"></i>
                                Edit Tindak Lanjut
                            </h5>
                            <form method="post">
                                <input type="hidden" name="id_tindaklanjut" value="<?= (int) $editData['id_tindaklanjut']; ?>">

                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-hashtag me-1"></i> ID Kembali
                                        </label>
                                        <input type="text" class="form-control" 
                                               value="ID Kembali #<?= (int)$editData['id_kembali']; ?>" readonly>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-wrench me-1"></i> Tindakan <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" name="tindakan" class="form-control"
                                               value="<?= htmlspecialchars($editData['tindakan']); ?>"
                                               maxlength="255"
                                               required>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-flag me-1"></i> Status <span class="text-danger">*</span>
                                        </label>
                                        <select name="status" class="form-select" required>
                                            <option value="proses"  <?= ($editData['status'] === 'proses') ? 'selected' : ''; ?>>
                                                ⏳ Proses
                                            </option>
                                            <option value="selesai" <?= ($editData['status'] === 'selesai') ? 'selected' : ''; ?>>
                                                ✅ Selesai
                                            </option>
                                        </select>
                                    </div>

                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" name="update" class="btn btn-primary w-100">
                                            <i class="fas fa-save me-1"></i> Perbarui
                                        </button>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-comment-alt me-1"></i> Keterangan / Deskripsi
                                        </label>
                                        <textarea name="deskripsi" 
                                                  class="form-control" 
                                                  rows="3" 
                                                  maxlength="500"
                                                  placeholder="Deskripsi detail tindakan perbaikan..."><?= htmlspecialchars($editData['deskripsi'] ?? ''); ?></textarea>
                                        <small class="text-muted">Maksimal 500 karakter</small>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <a href="tindaklanjut.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i> Batal Edit
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card tab-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-semibold mb-0">
                                    <i class="fas fa-list me-2"></i>
                                    Data Tindak Lanjut
                                </h5>
                            </div>

                            <!-- FILTER / PENCARIAN -->
                            <div class="filter-section">
                                <form method="get" action="tindaklanjut.php" class="row g-2 align-items-end">
                                    <div class="col-md-5">
                                        <label class="form-label fw-semibold small">
                                            <i class="fas fa-search me-1"></i> Pencarian
                                        </label>
                                        <input type="text" name="q" class="form-control"
                                               placeholder="ID Pinjam / Nama Peminjam / Tindakan / Deskripsi"
                                               value="<?= htmlspecialchars($filter_q); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold small">
                                            <i class="fas fa-filter me-1"></i> Status
                                        </label>
                                        <select name="status" class="form-select">
                                            <option value="">Semua Status</option>
                                            <option value="proses"  <?= $filter_status==='proses'?'selected':''; ?>>Proses</option>
                                            <option value="selesai" <?= $filter_status==='selesai'?'selected':''; ?>>Selesai</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-search me-1"></i> Filter
                                        </button>
                                        <a href="tindaklanjut.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-redo me-1"></i> Reset
                                        </a>
                                    </div>
                                </form>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th style="width: 90px;">ID Kembali</th>
                                            <th style="width: 90px;">ID Pinjam</th>
                                            <th>Peminjam</th>
                                            <th>Tindakan</th>
                                            <th>Deskripsi</th>
                                            <th style="width: 100px;">Status</th>
                                            <th style="width: 120px;">Tanggal</th>
                                            <th style="width: 150px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $no = 1;
                                    if ($dataTindak && $dataTindak->num_rows > 0):
                                        while ($row = $dataTindak->fetch_assoc()):
                                            $badge = ($row['status'] === 'proses') ? 'warning' : 'success';
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary">#<?= (int) $row['id_kembali']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info">#<?= htmlspecialchars($row['id_pinjam'] ?? '-'); ?></span>
                                            </td>
                                            <td><strong><?= htmlspecialchars($row['nama_peminjam'] ?? '-'); ?></strong></td>
                                            <td><?= htmlspecialchars($row['tindakan']); ?></td>
                                            <td><?= nl2br(htmlspecialchars($row['deskripsi'])); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $badge; ?> badge-status">
                                                    <?= ucfirst(htmlspecialchars($row['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?= $row['tanggal'] ? date('d-m-Y', strtotime($row['tanggal'])) : '-'; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="tindaklanjut.php?edit=<?= $row['id_tindaklanjut']; ?>" 
                                                   class="btn btn-sm btn-outline-primary btn-action mb-1"
                                                   title="Edit Tindak Lanjut">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (!empty($row['id_pinjam'])): ?>
                                                    <a href="komunikasi_tindaklanjut.php?id_pinjam=<?= (int)$row['id_pinjam']; ?>&id_tl=<?= (int)$row['id_tindaklanjut']; ?>"
                                                       class="btn btn-sm btn-outline-danger btn-action mb-1"
                                                       title="Chat Komunikasi">
                                                        <i class="fas fa-comments"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                                Belum ada tindak lanjut (atau tidak ada yang cocok dengan filter).
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: REKAP & LAPORAN -->
                <div class="tab-pane fade" id="tab-rekap" role="tabpanel" aria-labelledby="tab-rekap-tab">
                    <div class="card tab-card mb-5">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-semibold mb-0">
                                    <i class="fas fa-chart-bar me-2"></i>
                                    Rekap Tindak Lanjut & Komunikasi
                                </h5>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Info:</strong> Rekap ini menggabungkan data peminjaman, tindak lanjut kerusakan, 
                                dan jumlah chat komunikasi kerusakan antara peminjam dengan admin. 
                                Filter yang digunakan sama dengan tab Tindak Lanjut.
                            </div>

                            <!-- Ringkasan filter aktif -->
                            <?php if ($filter_q !== '' || $filter_status !== ''): ?>
                                <div class="alert alert-light border mb-3">
                                    <strong><i class="fas fa-filter me-2"></i>Filter aktif:</strong>
                                    <?php if ($filter_q !== ''): ?>
                                        Pencarian: "<em><?= htmlspecialchars($filter_q); ?></em>"
                                    <?php endif; ?>
                                    <?php if ($filter_status !== ''): ?>
                                        <?= $filter_q !== '' ? ' · ' : ''; ?>
                                        Status: <span class="badge bg-secondary"><?= htmlspecialchars($filter_status); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">No</th>
                                            <th style="width: 80px;">ID TL</th>
                                            <th style="width: 90px;">ID Pinjam</th>
                                            <th>Peminjam</th>
                                            <th>Tindakan</th>
                                            <th style="width: 100px;">Status TL</th>
                                            <th style="width: 100px;">Jumlah Chat</th>
                                            <th style="width: 150px;">Tanggal TL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php
                                    $no = 1;
                                    if ($dataRekap && $dataRekap->num_rows > 0):
                                        while ($r = $dataRekap->fetch_assoc()):
                                            $badgeClass = 'secondary';
                                            if ($r['status'] === 'proses')      $badgeClass = 'warning';
                                            elseif ($r['status'] === 'selesai') $badgeClass = 'success';
                                    ?>
                                        <tr>
                                            <td class="text-center"><?= $no++; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary">#<?= $r['id_tindaklanjut']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-info">#<?= $r['id_pinjam']; ?></span>
                                            </td>
                                            <td><strong><?= htmlspecialchars($r['nama_peminjam']); ?></strong></td>
                                            <td><?= htmlspecialchars($r['tindakan']); ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $badgeClass; ?> badge-status">
                                                    <?= ucfirst(htmlspecialchars($r['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary">
                                                    <i class="fas fa-comments me-1"></i>
                                                    <?= (int) $r['total_chat']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?= $r['tanggal'] ? date('d M Y H:i', strtotime($r['tanggal'])) : '-'; ?>
                                            </td>
                                        </tr>
                                    <?php
                                        endwhile;
                                    else:
                                    ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
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

<script>
// Bootstrap Tab functionality
document.addEventListener('DOMContentLoaded', function () {
    var triggerTabList = [].slice.call(document.querySelectorAll('#tlTab button'))
    triggerTabList.forEach(function (triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl)
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault()
            tabTrigger.show()
        })
    })
});
</script>
