<?php
session_start();
include '../config/koneksi.php';

/* =========================================================
   1. CEK LOGIN & ROLE (PREPARED STATEMENT)
   ========================================================== */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);

if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Ambil role user (prepared)
$stmtRole = $conn->prepare("SELECT role FROM users WHERE id_user = ? LIMIT 1");
$stmtRole->bind_param("i", $id_user);
$stmtRole->execute();
$resultRole = $stmtRole->get_result();
$roleData   = $resultRole->fetch_assoc();
$role       = $roleData['role'] ?? '';
$stmtRole->close();

if (!in_array($role, ['super_admin', 'bagian_umum'], true)) {
    header("Location: ../auth/unauthorized.php");
    exit;
}

$success = '';
$error   = '';

/* =========================================================
   2. FLASH MESSAGE DARI SESSION
   ========================================================== */
if (isset($_SESSION['success'])) {
    $success = htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['error']);
}

/* =========================================================
   3. HELPER UPLOAD GAMBAR FASILITAS (VALIDASI LENGKAP)
   ========================================================== */
function uploadGambarFasilitas($fieldName, $oldFile = null) {
    $uploadDir = __DIR__ . '/../uploads/fasilitas/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldFile;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Terjadi kesalahan saat upload file.";
        return $oldFile;
    }

    $tmpName  = $_FILES[$fieldName]['tmp_name'];
    $fileName = $_FILES[$fieldName]['name'];
    $fileSize = $_FILES[$fieldName]['size'];

    // Maksimal 2MB
    if ($fileSize > 2 * 1024 * 1024) {
        $_SESSION['error'] = "Ukuran file maksimal 2MB.";
        return $oldFile;
    }

    $ext     = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png'];
    if (!in_array($ext, $allowed, true)) {
        $_SESSION['error'] = "Format file harus JPG, JPEG, atau PNG.";
        return $oldFile;
    }

    // Validasi MIME type asli
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        $_SESSION['error'] = "File bukan gambar yang valid.";
        return $oldFile;
    }

    $newName = 'fas_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest    = $uploadDir . $newName;

    if (move_uploaded_file($tmpName, $dest)) {
        if ($oldFile) {
            $oldPath = $uploadDir . $oldFile;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }
        return $newName;
    }

    $_SESSION['error'] = "Gagal memindahkan file upload.";
    return $oldFile;
}

/* =========================================================
   4. FILTER KATEGORI (semua / ruangan / kendaraan / pendukung)
   ========================================================== */
$kategori = $_GET['kategori'] ?? 'semua';

$allowedKategori = ['semua', 'ruangan', 'kendaraan', 'pendukung'];
if (!in_array($kategori, $allowedKategori, true)) {
    $kategori = 'semua';
}

switch ($kategori) {
    case 'ruangan':
        $labelKategori = 'Fasilitas Ruangan';
        $badgeKategori = 'Ruangan';
        $badgeClass    = 'primary';
        break;
    case 'kendaraan':
        $labelKategori = 'Fasilitas Kendaraan';
        $badgeKategori = 'Kendaraan';
        $badgeClass    = 'success';
        break;
    case 'pendukung':
        $labelKategori = 'Fasilitas Pendukung';
        $badgeKategori = 'Pendukung';
        $badgeClass    = 'warning';
        break;
    default:
        $labelKategori = 'Semua Fasilitas';
        $badgeKategori = 'Semua';
        $badgeClass    = 'secondary';
        break;
}

/* =========================================================
   5. HANDLE CREATE / UPDATE (HANYA SUPER_ADMIN, PREPARED)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'super_admin') {
    $action        = $_POST['action'] ?? '';
    $nama_fasilitas = trim($_POST['nama_fasilitas'] ?? '');
    $kategoriPost   = trim($_POST['kategori'] ?? '');
    $lokasi         = trim($_POST['lokasi'] ?? '');
    $ketersediaan   = trim($_POST['ketersediaan'] ?? '');
    $keterangan     = trim($_POST['keterangan'] ?? '');
    $gambar_lama    = trim($_POST['gambar_lama'] ?? '');

    // VALIDASI INPUT UTAMA
    if ($nama_fasilitas === '' || $kategoriPost === '' || $lokasi === '' || $ketersediaan === '') {
        $error = "Semua field wajib diisi kecuali keterangan dan gambar.";
    } elseif (mb_strlen($nama_fasilitas) < 3 || mb_strlen($nama_fasilitas) > 100) {
        $error = "Nama fasilitas harus 3–100 karakter.";
    } elseif (mb_strlen($lokasi) < 3 || mb_strlen($lokasi) > 100) {
        $error = "Lokasi harus 3–100 karakter.";
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-\/\.\,]+$/u', $nama_fasilitas)) {
        $error = "Nama fasilitas hanya boleh huruf, angka, spasi, dan tanda baca dasar.";
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-\/\.\,]+$/u', $lokasi)) {
        $error = "Lokasi hanya boleh huruf, angka, spasi, dan tanda baca dasar.";
    } else {
        $validKategori = ['ruangan','kendaraan','pendukung'];
        if (!in_array($kategoriPost, $validKategori, true)) {
            $error = "Kategori fasilitas tidak valid.";
        }

        $validKetersediaan = ['tersedia','tidak_tersedia'];
        if ($error === '' && !in_array($ketersediaan, $validKetersediaan, true)) {
            $error = "Status ketersediaan tidak valid.";
        }
    }

    if ($error === '') {
        $namaGambar = uploadGambarFasilitas('gambar', $gambar_lama);
        $ketVal     = $keterangan !== '' ? $keterangan : null;
        $imgVal     = $namaGambar !== '' ? $namaGambar : null;

        if ($action === 'create') {
            $sql  = "INSERT INTO fasilitas 
                        (nama_fasilitas, kategori, lokasi, ketersediaan, keterangan, gambar)
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "ssssss",
                    $nama_fasilitas,
                    $kategoriPost,
                    $lokasi,
                    $ketersediaan,
                    $ketVal,
                    $imgVal
                );
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Fasilitas baru berhasil ditambahkan.";
                    header("Location: daftar_fasilitas.php?kategori=" . urlencode($kategori));
                    exit;
                } else {
                    $error = "Gagal menambah fasilitas.";
                }
                $stmt->close();
            } else {
                $error = "Gagal menyiapkan query tambah fasilitas.";
            }

        } elseif ($action === 'update') {
            $id_fasilitas = filter_var($_POST['id_fasilitas'] ?? 0, FILTER_VALIDATE_INT);
            if ($id_fasilitas === false || $id_fasilitas <= 0) {
                $error = "ID fasilitas tidak valid untuk update.";
            } else {
                $sql  = "UPDATE fasilitas
                         SET nama_fasilitas = ?, kategori = ?, lokasi = ?, 
                             ketersediaan = ?, keterangan = ?, gambar = ?
                         WHERE id_fasilitas = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param(
                        "ssssssi",
                        $nama_fasilitas,
                        $kategoriPost,
                        $lokasi,
                        $ketersediaan,
                        $ketVal,
                        $imgVal,
                        $id_fasilitas
                    );
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Fasilitas berhasil diperbarui.";
                        header("Location: daftar_fasilitas.php?kategori=" . urlencode($kategori));
                        exit;
                    } else {
                        $error = "Gagal memperbarui fasilitas.";
                    }
                    $stmt->close();
                } else {
                    $error = "Gagal menyiapkan query update fasilitas.";
                }
            }
        }
    }
}

/* =========================================================
   6. HANDLE DELETE (PREPARED + CEK FOREIGN KEY)
   ========================================================== */
if (isset($_GET['delete']) && $role === 'super_admin') {
    $id_del = filter_var($_GET['delete'], FILTER_VALIDATE_INT);

    if ($id_del === false || $id_del <= 0) {
        $_SESSION['error'] = "ID fasilitas tidak valid.";
    } else {
        // Ambil nama file gambar
        $stmtImg = $conn->prepare("SELECT gambar FROM fasilitas WHERE id_fasilitas = ? LIMIT 1");
        $stmtImg->bind_param("i", $id_del);
        $stmtImg->execute();
        $resultImg = $stmtImg->get_result();
        $imgRow    = $resultImg->fetch_assoc();
        $oldImg    = $imgRow['gambar'] ?? null;
        $stmtImg->close();

        // Cek foreign key
        $stmtCek = $conn->prepare("
            SELECT 1 
            FROM daftar_peminjaman_fasilitas 
            WHERE id_fasilitas = ? 
            LIMIT 1
        ");
        $stmtCek->bind_param("i", $id_del);
        $stmtCek->execute();
        $resultCek = $stmtCek->get_result();

        if ($resultCek->num_rows > 0) {
            $_SESSION['error'] = "Fasilitas tidak dapat dihapus karena sudah digunakan dalam data peminjaman.";
        } else {
            $stmtDel = $conn->prepare("DELETE FROM fasilitas WHERE id_fasilitas = ?");
            if ($stmtDel) {
                $stmtDel->bind_param("i", $id_del);
                if ($stmtDel->execute()) {
                    if ($oldImg && file_exists('../uploads/fasilitas/' . $oldImg)) {
                        @unlink('../uploads/fasilitas/' . $oldImg);
                    }
                    $_SESSION['success'] = "Fasilitas berhasil dihapus.";
                } else {
                    $_SESSION['error'] = "Gagal menghapus fasilitas.";
                }
                $stmtDel->close();
            }
        }

        $stmtCek->close();
    }

    header("Location: daftar_fasilitas.php?kategori=" . urlencode($kategori));
    exit;
}

/* =========================================================
   7. QUERY DATA FASILITAS + KETERSEDIAAN AKTUAL (PREPARED)
   ========================================================== */
if ($kategori === 'semua') {
    $sqlFasilitas = "
        SELECT
            f.id_fasilitas,
            f.nama_fasilitas,
            f.kategori,
            f.lokasi,
            f.ketersediaan,
            f.keterangan,
            f.gambar,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM daftar_peminjaman_fasilitas df
                    JOIN peminjaman p ON df.id_pinjam = p.id_pinjam
                    WHERE df.id_fasilitas = f.id_fasilitas
                      AND p.status = 'diterima'
                      AND CURDATE() BETWEEN p.tanggal_mulai AND p.tanggal_selesai
                )
                THEN 'tidak_tersedia'
                ELSE 'tersedia'
            END AS ketersediaan_aktual
        FROM fasilitas f
        ORDER BY f.id_fasilitas DESC
    ";
    $stmtFas = $conn->prepare($sqlFasilitas);
} else {
    $sqlFasilitas = "
        SELECT
            f.id_fasilitas,
            f.nama_fasilitas,
            f.kategori,
            f.lokasi,
            f.ketersediaan,
            f.keterangan,
            f.gambar,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM daftar_peminjaman_fasilitas df
                    JOIN peminjaman p ON df.id_pinjam = p.id_pinjam
                    WHERE df.id_fasilitas = f.id_fasilitas
                      AND p.status = 'diterima'
                      AND CURDATE() BETWEEN p.tanggal_mulai AND p.tanggal_selesai
                )
                THEN 'tidak_tersedia'
                ELSE 'tersedia'
            END AS ketersediaan_aktual
        FROM fasilitas f
        WHERE f.kategori = ?
        ORDER BY f.id_fasilitas DESC
    ";
    $stmtFas = $conn->prepare($sqlFasilitas);
    $stmtFas->bind_param("s", $kategori);
}

$result = null;
if ($stmtFas) {
    $stmtFas->execute();
    $result = $stmtFas->get_result();
}

/* =========================================================
   8. NOTIFIKASI (UNTUK NAVBAR ADMIN) - PREPARED
   ========================================================== */
$notifPeminjaman       = [];
$notifRusak            = [];
$jumlahNotifPeminjaman = 0;
$jumlahNotifRusak      = 0;
$jumlahNotif           = 0;

// Peminjaman baru (usulan)
$sqlNotifP = "
    SELECT p.id_pinjam, u.nama, p.tanggal_mulai
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

// Pengembalian rusak
$sqlNotifR = "
    SELECT k.id_kembali, k.id_pinjam, u.nama, k.tgl_kembali
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
$jumlahNotif      = $jumlahNotifPeminjaman + $jumlahNotifRusak;

// Variable untuk badge sidebar
$statUsulan = $jumlahNotifPeminjaman;

/* =========================================================
   9. TITLE & INCLUDE TEMPLATE ADMIN (TAMPILAN TIDAK DIUBAH)
   ========================================================== */
$pageTitle   = 'Daftar Fasilitas';
$currentPage = 'daftar_fasilitas';

include '../includes/admin/header.php';
include '../includes/admin/sidebar.php';
?>

<!-- ====== CSS KHUSUS HALAMAN INI ====== -->
<style>
    .fasilitas-title {
        font-size: 1.4rem;
    }
    .fasilitas-subtitle {
        font-size: 0.9rem;
    }
    .card-fasilitas table thead th {
        font-size: 0.85rem;
        text-transform: none;
        letter-spacing: .03em;
    }
    .card-fasilitas table tbody td {
        font-size: 0.9rem;
    }
    .badge-kat {
        font-size: 0.75rem;
        padding: .25rem .55rem;
    }
    .btn-edit-fas,
    .btn-delete-fas {
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
    }
    .btn-edit-fas i,
    .btn-delete-fas i {
        font-size: 0.9rem;
    }
    
    .filter-kategori-group .btn {
        font-size: 0.85rem;
        padding: 0.4rem 1rem;
        font-weight: 600;
    }
    
    .img-preview {
        max-width: 100%;
        max-height: 200px;
        object-fit: cover;
        border-radius: 10px;
        border: 2px solid #e2e8f0;
        margin-bottom: 1rem;
    }

    .footer-admin {
        padding: 0.75rem 1.5rem;
        border-top: 1px solid #e5e7eb;
        background-color: #f9fafb;
        font-size: 0.85rem;
        color: #4b5563;
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
                    <h2 class="fw-bold text-danger mb-1 fasilitas-title">
                        <i class="fas fa-building me-2"></i>
                        Daftar Fasilitas
                    </h2>
                    <p class="text-muted mb-0 fasilitas-subtitle">
                        Informasi fasilitas kampus yang tersedia untuk peminjaman.
                        <span class="ms-2 badge bg-<?= $badgeClass; ?> badge-kat">
                            <?= htmlspecialchars($badgeKategori); ?>
                        </span>
                    </p>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <?php if ($role === 'super_admin'): ?>
                        <button class="btn btn-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fas fa-plus me-1"></i> Tambah Fasilitas
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Filter Kategori -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="btn-group filter-kategori-group">
                    <a href="daftar_fasilitas.php?kategori=semua"
                       class="btn btn-sm btn-outline-secondary <?= $kategori === 'semua' ? 'active' : ''; ?>">
                        <i class="fas fa-border-all me-1"></i> Semua
                    </a>
                    <a href="daftar_fasilitas.php?kategori=ruangan"
                       class="btn btn-sm btn-outline-primary <?= $kategori === 'ruangan' ? 'active' : ''; ?>">
                        <i class="fas fa-door-open me-1"></i> Ruangan
                    </a>
                    <a href="daftar_fasilitas.php?kategori=kendaraan"
                       class="btn btn-sm btn-outline-success <?= $kategori === 'kendaraan' ? 'active' : ''; ?>">
                        <i class="fas fa-car me-1"></i> Kendaraan
                    </a>
                    <a href="daftar_fasilitas.php?kategori=pendukung"
                       class="btn btn-sm btn-outline-warning <?= $kategori === 'pendukung' ? 'active' : ''; ?>">
                        <i class="fas fa-tools me-1"></i> Pendukung
                    </a>
                </div>

                <a href="daftar_fasilitas.php?kategori=<?= urlencode($kategori); ?>" 
                   class="btn btn-outline-secondary btn-sm shadow-sm">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </a>
            </div>

            <hr class="mt-0 mb-4" style="border-top: 2px solid #0f172a; opacity: .25;">

            <!-- Alert -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Tabel Data Fasilitas -->
            <div class="card shadow-sm border-0 mb-4 card-fasilitas">
                <div class="card-header bg-danger text-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2"></i> <?= htmlspecialchars($labelKategori); ?></span>
                    <span class="small opacity-75">
                        Total: <?= $result ? $result->num_rows : 0; ?> fasilitas
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="datatablesSimple" class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width:50px;">No</th>
                                    <th>Nama Fasilitas</th>
                                    <th style="width:120px;">Kategori</th>
                                    <th>Lokasi</th>
                                    <th style="width:100px;">Gambar</th>
                                    <th style="width:120px;">Ketersediaan</th>
                                    <th>Keterangan</th>
                                    <?php if ($role === 'super_admin'): ?>
                                        <th style="width:130px;">Aksi</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                                    <?php
                                        $statusNow = $row['ketersediaan_aktual'] ?? 'tersedia';
                                        $gambar    = $row['gambar'] ?? '';
                                        if (!empty($gambar) && file_exists('../uploads/fasilitas/' . $gambar)) {
                                            $thumbPath = '../uploads/fasilitas/' . $gambar;
                                        } else {
                                            $thumbPath = '../assets/img/no-image.jpg';
                                        }
                                    ?>
                                    <tr>
                                        <td class="text-center"><?= $no++; ?></td>
                                        <td><strong><?= htmlspecialchars($row['nama_fasilitas']); ?></strong></td>
                                        <td class="text-center text-capitalize">
                                            <?php
                                            $katBadge = 'secondary';
                                            if ($row['kategori'] === 'ruangan') $katBadge = 'primary';
                                            elseif ($row['kategori'] === 'kendaraan') $katBadge = 'success';
                                            elseif ($row['kategori'] === 'pendukung') $katBadge = 'warning';
                                            ?>
                                            <span class="badge bg-<?= $katBadge; ?>">
                                                <?= htmlspecialchars($row['kategori']); ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($row['lokasi']); ?></td>
                                        <td class="text-center">
                                            <img src="<?= $thumbPath; ?>" alt="Gambar" 
                                                 style="max-width:80px; max-height:70px; object-fit:cover; border-radius:8px; cursor:pointer;"
                                                 onclick="showImageModal('<?= $thumbPath; ?>', '<?= htmlspecialchars($row['nama_fasilitas']); ?>')">
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= ($statusNow === 'tersedia') ? 'success' : 'danger'; ?> px-3 py-2">
                                                <?= ucfirst(str_replace('_',' ',$statusNow)); ?>
                                            </span>
                                        </td>
                                        <td><?= nl2br(htmlspecialchars($row['keterangan'] ?? '-')); ?></td>

                                        <?php if ($role === 'super_admin'): ?>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-1 flex-nowrap">
                                                <button
                                                    type="button"
                                                    class="btn btn-warning btn-sm btn-edit-fas"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEdit"
                                                    data-id="<?= $row['id_fasilitas']; ?>"
                                                    data-nama="<?= htmlspecialchars($row['nama_fasilitas'], ENT_QUOTES); ?>"
                                                    data-kategori="<?= htmlspecialchars($row['kategori'], ENT_QUOTES); ?>"
                                                    data-lokasi="<?= htmlspecialchars($row['lokasi'], ENT_QUOTES); ?>"
                                                    data-ketersediaan="<?= htmlspecialchars($row['ketersediaan'], ENT_QUOTES); ?>"
                                                    data-ket="<?= htmlspecialchars($row['keterangan'] ?? '', ENT_QUOTES); ?>"
                                                    data-gambar="<?= htmlspecialchars($row['gambar'] ?? '', ENT_QUOTES); ?>"
                                                    title="Edit Fasilitas"
                                                >
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <a href="daftar_fasilitas.php?delete=<?= $row['id_fasilitas']; ?>&kategori=<?= urlencode($kategori); ?>"
                                                   class="btn btn-danger btn-sm btn-delete-fas"
                                                   onclick="return confirm('Yakin ingin menghapus fasilitas <?= htmlspecialchars($row['nama_fasilitas']); ?>?');"
                                                   title="Hapus Fasilitas">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= $role === 'super_admin' ? 8 : 7; ?>" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-3x mb-3 d-block opacity-25"></i>
                                        Tidak ada data fasilitas untuk kategori ini.
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
                <strong>E-Fasilitas</strong> &copy; <?= date('Y'); ?> - Sistem Peminjaman Fasilitas Kampus
            </div>
            <div>
                Version 1.0
            </div>
        </div>
    </footer>

</div>

<?php if ($role === 'super_admin'): ?>

<!-- Modal Tambah Fasilitas -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">
            <i class="fas fa-plus-circle me-2"></i>
            Tambah Fasilitas
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="create">

        <div class="mb-3">
          <label class="form-label fw-semibold">Nama Fasilitas <span class="text-danger">*</span></label>
          <input type="text" name="nama_fasilitas" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
          <select name="kategori" class="form-select" required>
            <option value="" hidden>-- Pilih Kategori --</option>
            <option value="ruangan">Ruangan</option>
            <option value="kendaraan">Kendaraan</option>
            <option value="pendukung">Pendukung</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Lokasi <span class="text-danger">*</span></label>
          <input type="text" name="lokasi" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Ketersediaan (default) <span class="text-danger">*</span></label>
          <select name="ketersediaan" class="form-select" required>
            <option value="tersedia">Tersedia</option>
            <option value="tidak_tersedia">Tidak Tersedia</option>
          </select>
          <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Ketersediaan aktual akan otomatis <strong>Tidak Tersedia</strong> jika fasilitas sedang dipinjam (status Diterima & tanggal aktif).
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Gambar Fasilitas</label>
          <input type="file" name="gambar" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
          <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Hanya file JPG / PNG. Disarankan rasio landscape untuk tampilan rapi.
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Keterangan</label>
          <textarea name="keterangan" class="form-control" rows="3" placeholder="Deskripsi atau keterangan tambahan..."></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i> Batal
        </button>
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-save me-1"></i> Simpan
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Fasilitas -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header" style="background: linear-gradient(90deg,#0d47a1,#1565c0); color:#f9fafb;">
        <h5 class="modal-title">
            <i class="fas fa-edit me-2"></i>
            Edit Fasilitas
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id_fasilitas" id="edit_id_fasilitas">
        <input type="hidden" name="gambar_lama" id="edit_gambar_lama">

        <div class="mb-3">
          <label class="form-label fw-semibold">Nama Fasilitas <span class="text-danger">*</span></label>
          <input type="text" name="nama_fasilitas" id="edit_nama_fasilitas" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Kategori <span class="text-danger">*</span></label>
          <select name="kategori" id="edit_kategori" class="form-select" required>
            <option value="ruangan">Ruangan</option>
            <option value="kendaraan">Kendaraan</option>
            <option value="pendukung">Pendukung</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Lokasi <span class="text-danger">*</span></label>
          <input type="text" name="lokasi" id="edit_lokasi" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Ketersediaan (default) <span class="text-danger">*</span></label>
          <select name="ketersediaan" id="edit_ketersediaan" class="form-select" required>
            <option value="tersedia">Tersedia</option>
            <option value="tidak_tersedia">Tidak Tersedia</option>
          </select>
          <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Ketersediaan aktual tetap dihitung otomatis dari data peminjaman (status Diterima & tanggal aktif).
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Gambar Fasilitas</label>
          <div class="mb-2 text-center">
            <img id="preview_edit_gambar" src="../assets/img/no-image.jpg" alt="Preview" 
                 class="img-preview">
          </div>
          <input type="file" name="gambar" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
          <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Kosongkan jika tidak ingin mengubah gambar. Hanya JPG / PNG.
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Keterangan</label>
          <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="3"></textarea>
        </div>
      </div>

      <div class="modal-footer" style="background:#f3f4f6;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i> Batal
        </button>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-1"></i> Update
        </button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<!-- Modal Preview Image -->
<div class="modal fade" id="modalImagePreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImageTitle">Preview Gambar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImageSrc" src="" alt="Preview" style="max-width: 100%; height: auto; border-radius: 10px;">
            </div>
        </div>
    </div>
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
            { orderable: false, targets: [4, <?= $role === 'super_admin' ? 7 : 6; ?>] }
        ]
    });
});

// Show image modal
function showImageModal(imgSrc, title) {
    document.getElementById('modalImageSrc').src = imgSrc;
    document.getElementById('modalImageTitle').textContent = title;
    new bootstrap.Modal(document.getElementById('modalImagePreview')).show();
}

// Edit modal handler
document.addEventListener('DOMContentLoaded', function () {
    const uploadBasePath = '../uploads/fasilitas/';
    const defaultImgPath = '../assets/img/no-image.jpg';

    document.querySelectorAll('.btn-edit-fas').forEach(btn => {
        btn.addEventListener('click', () => {
            const id       = btn.dataset.id;
            const nama     = btn.dataset.nama;
            const kategori = btn.dataset.kategori;
            const lokasi   = btn.dataset.lokasi;
            const keters   = btn.dataset.ketersediaan;
            const ket      = btn.dataset.ket;
            const gambar   = btn.dataset.gambar;

            document.getElementById('edit_id_fasilitas').value   = id;
            document.getElementById('edit_nama_fasilitas').value = nama;
            document.getElementById('edit_lokasi').value         = lokasi;
            document.getElementById('edit_keterangan').value     = ket || '';

            const selKat = document.getElementById('edit_kategori');
            if (selKat && kategori) selKat.value = kategori;

            const selKet = document.getElementById('edit_ketersediaan');
            if (selKet && keters) selKet.value = keters;

            document.getElementById('edit_gambar_lama').value = gambar || '';

            const imgPrev = document.getElementById('preview_edit_gambar');
            if (gambar) {
                imgPrev.src = uploadBasePath + gambar;
            } else {
                imgPrev.src = defaultImgPath;
            }
        });
    });
});
</script>
