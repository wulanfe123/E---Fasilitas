<?php
session_start();
include '../config/koneksi.php';

/* =========================================================
   1. CEK LOGIN & ROLE
   ========================================================= */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

// Ambil role user (boleh pakai query biasa, boleh prepared)
$qRole = mysqli_query($conn, "SELECT role FROM users WHERE id_user = $id_user LIMIT 1");
$roleData = mysqli_fetch_assoc($qRole);
$role = $roleData ? $roleData['role'] : '';

if (!in_array($role, ['super_admin', 'bagian_umum'])) {
    header("Location: ../auth/unauthorized.php");
    exit;
}

$success = '';
$error   = '';

/* =========================================================
   2. FLASH MESSAGE DARI SESSION
   ========================================================= */
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

/* =========================================================
   3. HELPER UPLOAD GAMBAR FASILITAS
   ========================================================= */
function uploadGambarFasilitas($fieldName, $oldFile = null) {
    $uploadDir = __DIR__ . '/../uploads/fasilitas/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldFile;
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return $oldFile;
    }

    $tmpName  = $_FILES[$fieldName]['tmp_name'];
    $fileName = $_FILES[$fieldName]['name'];

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png'];

    if (!in_array($ext, $allowed)) {
        return $oldFile;
    }

    $newName = 'fas_' . time() . '_' . rand(1000,9999) . '.' . $ext;
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

    return $oldFile;
}

/* =========================================================
   4. FILTER KATEGORI (semua / ruangan / kendaraan / pendukung)
   ========================================================= */
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
   5. HANDLE CREATE / UPDATE (HANYA SUPER_ADMIN)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'super_admin') {
    $action          = $_POST['action'] ?? '';
    $nama_fasilitas  = trim($_POST['nama_fasilitas'] ?? '');
    $kategoriPost    = trim($_POST['kategori'] ?? '');
    $lokasi          = trim($_POST['lokasi'] ?? '');
    $ketersediaan    = trim($_POST['ketersediaan'] ?? '');
    $keterangan      = trim($_POST['keterangan'] ?? '');
    $gambar_lama     = trim($_POST['gambar_lama'] ?? '');

    // -------- VALIDASI INPUT UTAMA --------
    if ($nama_fasilitas === '' || $kategoriPost === '' || $lokasi === '' || $ketersediaan === '') {
        $error = "Semua field wajib diisi kecuali keterangan dan gambar.";
    } elseif (strlen($nama_fasilitas) < 3 || strlen($nama_fasilitas) > 100) {
        $error = "Nama fasilitas harus 3–100 karakter.";
    } elseif (strlen($lokasi) < 3 || strlen($lokasi) > 100) {
        $error = "Lokasi harus 3–100 karakter.";
    } else {
        // Validasi kategori (hanya 3: ruangan, kendaraan, pendukung)
        $validKategori = ['ruangan','kendaraan','pendukung'];
        if (!in_array($kategoriPost, $validKategori, true)) {
            $error = "Kategori fasilitas tidak valid.";
        }

        // Validasi ketersediaan
        $validKetersediaan = ['tersedia','tidak_tersedia'];
        if ($error === '' && !in_array($ketersediaan, $validKetersediaan, true)) {
            $error = "Status ketersediaan tidak valid.";
        }
    }

    // -------- JIKA VALID, LANJUT UPLOAD GAMBAR + PREPARED STATEMENT --------
    if ($error === '') {
        $namaGambar = uploadGambarFasilitas('gambar', $gambar_lama);
        $ketVal     = $keterangan !== '' ? $keterangan : null;
        $imgVal     = $namaGambar ?: null;

        if ($action === 'create') {
            $sql = "
                INSERT INTO fasilitas 
                    (nama_fasilitas, kategori, lokasi, ketersediaan, keterangan, gambar)
                VALUES 
                    (?, ?, ?, ?, ?, ?)
            ";
            if ($stmt = $conn->prepare($sql)) {
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
                    $success = "Fasilitas baru berhasil ditambahkan.";
                } else {
                    $error = "Gagal menambah fasilitas: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Gagal menyiapkan query tambah fasilitas.";
            }

        } elseif ($action === 'update') {
            $id_fasilitas = (int) ($_POST['id_fasilitas'] ?? 0);
            if ($id_fasilitas <= 0) {
                $error = "ID fasilitas tidak valid untuk update.";
            } else {
                $sql = "
                    UPDATE fasilitas
                    SET 
                        nama_fasilitas = ?,
                        kategori       = ?,
                        lokasi         = ?,
                        ketersediaan   = ?,
                        keterangan     = ?,
                        gambar         = ?
                    WHERE id_fasilitas = ?
                ";
                if ($stmt = $conn->prepare($sql)) {
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
                        $success = "Fasilitas berhasil diperbarui.";
                    } else {
                        $error = "Gagal memperbarui fasilitas: " . $stmt->error;
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
   6. HANDLE DELETE (HANYA SUPER_ADMIN) + CEK FOREIGN KEY
   ========================================================= */
if (isset($_GET['delete']) && $role === 'super_admin') {
    $id_del = (int) $_GET['delete'];

    if ($id_del > 0) {
        // Ambil nama file gambar dulu
        $qImg = mysqli_query($conn, "SELECT gambar FROM fasilitas WHERE id_fasilitas = $id_del LIMIT 1");
        $imgRow = mysqli_fetch_assoc($qImg);
        $oldImg = $imgRow['gambar'] ?? null;

        // Cek apakah fasilitas sudah digunakan di daftar_peminjaman_fasilitas
        $cek = mysqli_query($conn, "
            SELECT 1 
            FROM daftar_peminjaman_fasilitas 
            WHERE id_fasilitas = $id_del
            LIMIT 1
        ");

        if ($cek && mysqli_num_rows($cek) > 0) {
            $_SESSION['error'] = "Fasilitas tidak dapat dihapus karena sudah digunakan dalam data peminjaman.";
        } else {
            // Pakai prepared statement untuk delete juga
            if ($stmtDel = $conn->prepare("DELETE FROM fasilitas WHERE id_fasilitas = ?")) {
                $stmtDel->bind_param("i", $id_del);
                if ($stmtDel->execute()) {
                    if ($oldImg && file_exists('../uploads/fasilitas/' . $oldImg)) {
                        @unlink('../uploads/fasilitas/' . $oldImg);
                    }
                    $_SESSION['success'] = "Fasilitas berhasil dihapus.";
                } else {
                    $_SESSION['error'] = "Gagal menghapus fasilitas: " . $stmtDel->error;
                }
                $stmtDel->close();
            } else {
                $_SESSION['error'] = "Gagal menyiapkan query hapus fasilitas.";
            }
        }
    } else {
        $_SESSION['error'] = "ID fasilitas tidak valid.";
    }

    header("Location: daftar_fasilitas.php?kategori=" . urlencode($kategori));
    exit;
}

/* =========================================================
   7. QUERY DATA FASILITAS + FILTER KATEGORI
   ========================================================= */
$whereKategori = "";
if ($kategori !== 'semua') {
    $kategoriEsc = mysqli_real_escape_string($conn, $kategori);
    $whereKategori = "WHERE f.kategori = '$kategoriEsc'";
}

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
    $whereKategori
    ORDER BY f.id_fasilitas DESC
";

$result = mysqli_query($conn, $sqlFasilitas);

/* =========================================================
   8. NOTIFIKASI (UNTUK NAVBAR ADMIN)
   ========================================================= */
$notifPeminjaman       = [];
$notifRusak            = [];
$jumlahNotifPeminjaman = 0;
$jumlahNotifRusak      = 0;
$jumlahNotif           = 0;

// Peminjaman baru (usulan)
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

// Pengembalian rusak
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
$jumlahNotif      = $jumlahNotifPeminjaman + $jumlahNotifRusak;

/* =========================================================
   9. TITLE & INCLUDE TEMPLATE ADMIN
   ========================================================= */
$pageTitle = 'Daftar Fasilitas';
include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
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
        padding: 0.3rem 0.6rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-edit-fas i,
    .btn-delete-fas i {
        font-size: 0.85rem;
    }
</style>

<div class="container-fluid px-4">

    <!-- Header Halaman -->
    <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
        <div>
            <h2 class="fw-bold text-danger mb-1 fasilitas-title">Daftar Fasilitas</h2>
            <p class="text-muted mb-0 fasilitas-subtitle">
                Informasi fasilitas kampus yang tersedia untuk peminjaman.
                <span class="ms-1 badge bg-<?= $badgeClass; ?> badge-kat">
                    <?= htmlspecialchars($badgeKategori); ?>
                </span>
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Filter kategori quick -->
            <div class="btn-group">
                <a href="daftar_fasilitas.php?kategori=semua"
                   class="btn btn-sm btn-outline-secondary <?= $kategori === 'semua' ? 'active' : ''; ?>">Semua</a>
                <a href="daftar_fasilitas.php?kategori=ruangan"
                   class="btn btn-sm btn-outline-primary <?= $kategori === 'ruangan' ? 'active' : ''; ?>">Ruangan</a>
                <a href="daftar_fasilitas.php?kategori=kendaraan"
                   class="btn btn-sm btn-outline-success <?= $kategori === 'kendaraan' ? 'active' : ''; ?>">Kendaraan</a>
                <a href="daftar_fasilitas.php?kategori=pendukung"
                   class="btn btn-sm btn-outline-warning <?= $kategori === 'pendukung' ? 'active' : ''; ?>">Pendukung</a>
            </div>

            <a href="daftar_fasilitas.php?kategori=<?= urlencode($kategori); ?>" class="btn btn-outline-danger btn-sm shadow-sm">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </a>

            <?php if ($role === 'super_admin'): ?>
                <button class="btn btn-danger btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus me-1"></i> Tambah
                </button>
            <?php endif; ?>
        </div>
    </div>

    <hr class="mt-0 mb-4" style="border-top: 2px solid #dc3545; opacity: 0.35;">

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

    <!-- Tabel Data Fasilitas -->
    <div class="card shadow-sm border-0 mb-4 card-fasilitas">
        <div class="card-header bg-danger text-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-building me-2"></i> <?= htmlspecialchars($labelKategori); ?></span>
            <span class="small opacity-75">
                <?= $result ? mysqli_num_rows($result) : 0; ?> data
            </span>
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width:5%;">No</th>
                        <th>Nama Fasilitas</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th>Gambar</th>
                        <th style="width:9%;">Ketersediaan</th>
                        <th>Keterangan</th>
                        <?php if ($role === 'super_admin'): ?>
                            <th style="width:12%;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php $no = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                            $statusNow = $row['ketersediaan_aktual'] ?? 'tersedia';
                            $gambar = $row['gambar'] ?? '';
                            if (!empty($gambar) && file_exists('../uploads/fasilitas/' . $gambar)) {
                                $thumbPath = '../uploads/fasilitas/' . $gambar;
                            } else {
                                $thumbPath = '../assets/img/no-image.jpg';
                            }
                        ?>
                        <tr>
                            <td class="text-center"><?= $no++; ?></td>
                            <td><?= htmlspecialchars($row['nama_fasilitas']); ?></td>
                            <td class="text-capitalize"><?= htmlspecialchars($row['kategori']); ?></td>
                            <td><?= htmlspecialchars($row['lokasi']); ?></td>
                            <td class="text-center">
                                <img src="<?= $thumbPath; ?>" alt="Gambar" 
                                     style="max-width:70px; max-height:60px; object-fit:cover; border-radius:6px;">
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= ($statusNow === 'tersedia') ? 'success' : 'danger'; ?>">
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
                                    >
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <a href="daftar_fasilitas.php?delete=<?= $row['id_fasilitas']; ?>&kategori=<?= urlencode($kategori); ?>"
                                       class="btn btn-danger btn-sm btn-delete-fas"
                                       onclick="return confirm('Yakin ingin menghapus fasilitas ini?');">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?= $role === 'super_admin' ? 8 : 7; ?>" class="text-center text-muted py-3">
                            Tidak ada data fasilitas.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($role === 'super_admin'): ?>

<!-- Modal Tambah Fasilitas -->
<div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Tambah Fasilitas</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="create">

        <div class="mb-3">
          <label class="form-label">Nama Fasilitas</label>
          <input type="text" name="nama_fasilitas" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Kategori</label>
          <select name="kategori" class="form-select" required>
            <option value="" hidden>-- Pilih Kategori --</option>
            <option value="ruangan">Ruangan</option>
            <option value="kendaraan">Kendaraan</option>
            <option value="pendukung">Pendukung</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Lokasi</label>
          <input type="text" name="lokasi" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Ketersediaan (default)</label>
          <select name="ketersediaan" class="form-select" required>
            <option value="tersedia">Tersedia</option>
            <option value="tidak_tersedia">Tidak Tersedia</option>
          </select>
          <small class="text-muted">
            Ketersediaan aktual akan otomatis menjadi <strong>Tidak Tersedia</strong> jika fasilitas sedang dipinjam (status Diterima & tanggal aktif).
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label">Gambar Fasilitas (opsional)</label>
          <input type="file" name="gambar" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
          <small class="text-muted">Hanya file JPG / PNG. Disarankan rasio landscape untuk tampilan rapi.</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Keterangan (opsional)</label>
          <textarea name="keterangan" class="form-control" rows="3"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Edit Fasilitas -->
<div class="modal fade" id="modalEdit" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" class="modal-content" enctype="multipart/form-data">
      <div class="modal-header" style="background: linear-gradient(90deg,#0d47a1,#1565c0); color:#f9fafb;">
        <h5 class="modal-title">Edit Fasilitas</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id_fasilitas" id="edit_id_fasilitas">
        <input type="hidden" name="gambar_lama" id="edit_gambar_lama">

        <div class="mb-3">
          <label class="form-label">Nama Fasilitas</label>
          <input type="text" name="nama_fasilitas" id="edit_nama_fasilitas" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Kategori</label>
          <select name="kategori" id="edit_kategori" class="form-select" required>
            <option value="ruangan">Ruangan</option>
            <option value="kendaraan">Kendaraan</option>
            <option value="pendukung">Pendukung</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Lokasi</label>
          <input type="text" name="lokasi" id="edit_lokasi" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Ketersediaan (default)</label>
          <select name="ketersediaan" id="edit_ketersediaan" class="form-select" required>
            <option value="tersedia">Tersedia</option>
            <option value="tidak_tersedia">Tidak Tersedia</option>
          </select>
          <small class="text-muted">
            Ketersediaan aktual tetap dihitung otomatis dari data peminjaman (status Diterima & tanggal aktif).
          </small>
        </div>

        <div class="mb-3">
          <label class="form-label">Gambar Fasilitas (opsional)</label>
          <div class="mb-2">
            <img id="preview_edit_gambar" src="../assets/img/no-image.jpg" alt="Preview" 
                 style="max-width:100%; max-height:180px; object-fit:cover; border-radius:8px; border:1px solid #ddd;">
          </div>
          <input type="file" name="gambar" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
          <small class="text-muted">Kosongkan jika tidak ingin mengubah gambar. Hanya JPG / PNG.</small>
        </div>

        <div class="mb-3">
          <label class="form-label">Keterangan (opsional)</label>
          <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="3"></textarea>
        </div>
      </div>

      <div class="modal-footer" style="background:#f3f4f6;">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
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
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>

<?php include '../includes/admin/footer.php'; ?>
