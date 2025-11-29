<?php
session_start();
include '../config/koneksi.php';

// ========================
// CEK LOGIN
// ========================
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user_login = (int) $_SESSION['id_user'];

// Ambil data user login (prepared)
$user = null;
if ($stmtUser = $conn->prepare("SELECT nama, role FROM users WHERE id_user = ? LIMIT 1")) {
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

// Hanya super_admin yang boleh akses halaman ini
if ($user['role'] !== 'super_admin') {
    header("Location: ../peminjam/dashboard.php");
    exit;
}

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

/* ===================== HELPER: UPDATE STATUS FASILITAS ===================== */
function setStatusFasilitasByPeminjaman(mysqli $conn, int $id_pinjam, string $statusBaru)
{
    $sql = "
        UPDATE fasilitas f
        JOIN daftar_peminjaman_fasilitas df ON f.id_fasilitas = df.id_fasilitas
        SET f.keterangan = ?
        WHERE df.id_pinjam = ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $statusBaru, $id_pinjam);
        $stmt->execute();
        $stmt->close();
    }
}

// ========================
// AMBIL FLASH MESSAGE
// ========================
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

/* =======================================================
   Aksi: TOLAK (POST) -> dengan alasan_penolakan
   ======================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'tolak') {
    $id_pinjam        = (int) ($_POST['id_pinjam'] ?? 0);
    $alasan_penolakan = trim($_POST['alasan_penolakan'] ?? '');

    // Validasi ID
    if ($id_pinjam <= 0) {
        $_SESSION['error'] = "ID peminjaman tidak valid.";
        header("Location: peminjaman.php");
        exit;
    }

    // Validasi alasan (tidak kosong, minimal 5 karakter, maksimal 500 misalnya)
    if ($alasan_penolakan === '' || strlen($alasan_penolakan) < 5) {
        $_SESSION['error'] = "Alasan penolakan tidak boleh kosong dan minimal 5 karakter.";
        header("Location: peminjaman.php");
        exit;
    }
    if (strlen($alasan_penolakan) > 500) {
        $_SESSION['error'] = "Alasan penolakan terlalu panjang (maksimal 500 karakter).";
        header("Location: peminjaman.php");
        exit;
    }

    // Update status & alasan dengan prepared statement
    if ($stmt = $conn->prepare("
        UPDATE peminjaman
        SET status = 'ditolak',
            alasan_penolakan = ?
        WHERE id_pinjam = ?
    ")) {
        $stmt->bind_param("si", $alasan_penolakan, $id_pinjam);
        if ($stmt->execute()) {
            // kalau sebelumnya sudah diterima, fasilitas dikembalikan jadi tersedia
            setStatusFasilitasByPeminjaman($conn, $id_pinjam, 'tersedia');
            $_SESSION['success'] = "Peminjaman #$id_pinjam berhasil ditolak dengan alasan.";
        } else {
            $_SESSION['error'] = "Gagal menolak peminjaman: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Gagal menyiapkan query penolakan peminjaman.";
    }

    header("Location: peminjaman.php");
    exit;
}

/* =======================================================
   Aksi: ubah status / hapus (GET)
   ======================================================= */
if (isset($_GET['aksi'], $_GET['id'])) {
    $id_pinjam = (int) $_GET['id'];
    $aksi      = $_GET['aksi'];

    // validasi aksi
    $allowedAksi = ['terima', 'selesai', 'hapus'];
    if (!in_array($aksi, $allowedAksi, true)) {
        $_SESSION['error'] = "Aksi tidak dikenal.";
        header("Location: peminjaman.php");
        exit;
    }

    if ($id_pinjam > 0) {
        // cek dulu datanya ada (prepared)
        if ($stmtCek = $conn->prepare("
            SELECT p.id_pinjam, p.id_user, p.status, u.nama
            FROM peminjaman p
            JOIN users u ON p.id_user = u.id_user
            WHERE p.id_pinjam = ?
            LIMIT 1
        ")) {
            $stmtCek->bind_param("i", $id_pinjam);
            $stmtCek->execute();
            $resCek = $stmtCek->get_result();
            $data   = $resCek->fetch_assoc();
            $stmtCek->close();
        } else {
            $_SESSION['error'] = "Gagal menyiapkan query cek peminjaman.";
            header("Location: peminjaman.php");
            exit;
        }

        if ($data) {
            $new_status = null;

            switch ($aksi) {
                case 'terima':
                    $new_status = 'diterima';
                    setStatusFasilitasByPeminjaman($conn, $id_pinjam, 'tidak tersedia');
                    break;

                case 'selesai':
                    $new_status = 'selesai';
                    setStatusFasilitasByPeminjaman($conn, $id_pinjam, 'tersedia');
                    break;

                case 'hapus':
                    // sebelum hapus, pastikan fasilitas dikembalikan ke tersedia
                    setStatusFasilitasByPeminjaman($conn, $id_pinjam, 'tersedia');

                    // Jalankan penghapusan dalam "transaksi" sederhana
                    $conn->begin_transaction();

                    try {
                        // 1) Hapus data pengembalian
                        if ($stmtDelPeng = $conn->prepare("DELETE FROM pengembalian WHERE id_pinjam = ?")) {
                            $stmtDelPeng->bind_param("i", $id_pinjam);
                            $stmtDelPeng->execute();
                            $stmtDelPeng->close();
                        } else {
                            throw new Exception("Gagal menyiapkan query hapus pengembalian.");
                        }

                        // 2) Hapus detail peminjaman fasilitas
                        if ($stmtDelDetail = $conn->prepare("DELETE FROM daftar_peminjaman_fasilitas WHERE id_pinjam = ?")) {
                            $stmtDelDetail->bind_param("i", $id_pinjam);
                            $stmtDelDetail->execute();
                            $stmtDelDetail->close();
                        } else {
                            throw new Exception("Gagal menyiapkan query hapus detail fasilitas.");
                        }

                        // 3) Hapus data utama peminjaman
                        if ($stmtDelMaster = $conn->prepare("DELETE FROM peminjaman WHERE id_pinjam = ?")) {
                            $stmtDelMaster->bind_param("i", $id_pinjam);
                            $stmtDelMaster->execute();
                            $stmtDelMaster->close();
                        } else {
                            throw new Exception("Gagal menyiapkan query hapus peminjaman.");
                        }

                        $conn->commit();
                        $_SESSION['success'] = "Peminjaman #$id_pinjam beserta data pengembalian dan detail fasilitasnya berhasil dihapus.";
                    } catch (Exception $e) {
                        $conn->rollback();
                        $_SESSION['error'] = "Terjadi kesalahan saat menghapus peminjaman: " . $e->getMessage();
                    }

                    header("Location: peminjaman.php");
                    exit;
            }

            // Jika BUKAN hapus dan ada status baru → update status (prepared)
            if ($aksi !== 'hapus' && $new_status !== null) {
                if ($stmtUpd = $conn->prepare("
                    UPDATE peminjaman
                    SET status = ?
                    WHERE id_pinjam = ?
                ")) {
                    $stmtUpd->bind_param("si", $new_status, $id_pinjam);
                    if ($stmtUpd->execute()) {
                        $_SESSION['success'] = "Status peminjaman #$id_pinjam diubah menjadi '$new_status'.";
                    } else {
                        $_SESSION['error'] = "Gagal mengubah status peminjaman: " . $stmtUpd->error;
                    }
                    $stmtUpd->close();
                } else {
                    $_SESSION['error'] = "Gagal menyiapkan query ubah status peminjaman.";
                }
            }

        } else {
            $_SESSION['error'] = "Data peminjaman tidak ditemukan.";
        }
    } else {
        $_SESSION['error'] = "ID peminjaman tidak valid.";
    }

    header("Location: peminjaman.php");
    exit;
}

/* ========================
   AMBIL DATA UNTUK TABEL
   ======================== */
$sql = "
    SELECT 
        p.*,
        u.nama,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.lokasi SEPARATOR ', '), '-')          AS lokasi_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.kategori SEPARATOR ', '), '-')        AS kategori_list
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    GROUP BY p.id_pinjam
    ORDER BY p.id_pinjam DESC
";

$result = mysqli_query($conn, $sql);

// ========================
// TEMPLATE ADMIN
// ========================
include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
include '../includes/admin/sidebar.php';
?>

<div class="container-fluid px-4">
    <!-- Header Halaman -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <h2 class="fw-bold text-success mb-1">Kelola Peminjaman</h2>
            <p class="text-muted mb-0">Pengelolaan status peminjaman fasilitas kampus</p>
        </div>
        <a href="peminjaman.php" class="btn btn-outline-success shadow-sm">
            <i class="fas fa-sync-alt me-1"></i> Refresh
        </a>
    </div>

    <hr class="mt-0 mb-4" style="border-top: 2px solid #198754; opacity: 0.4;">

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

    <!-- Tabel Data Peminjaman -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Data Peminjaman Fasilitas</span>
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table table-bordered table-hover align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th>Peminjam</th>
                        <th>Fasilitas</th>
                        <th>Tanggal Mulai</th>
                        <th>Tanggal Selesai</th>
                        <th>Status</th>
                        <th>Catatan / Alasan Ditolak</th>
                        <th style="width: 22%;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;

                    if ($result && mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)):
                            $status = strtolower($row['status']);

                            switch ($status) {
                                case 'usulan':
                                    $badgeClass = 'warning';
                                    $label = 'Usulan';
                                    break;
                                case 'diterima':
                                    $badgeClass = 'success';
                                    $label = 'Diterima';
                                    break;
                                case 'ditolak':
                                    $badgeClass = 'danger';
                                    $label = 'Ditolak';
                                    break;
                                case 'selesai':
                                    $badgeClass = 'primary';
                                    $label = 'Selesai';
                                    break;
                                default:
                                    $badgeClass = 'dark';
                                    $label = $row['status'];
                            }

                            $alasan_tolak = $row['alasan_penolakan'] ?? '';
                            $catatan      = $row['catatan'] ?? '';
                    ?>
                    <tr>
                        <td class="text-center"><?= $no++; ?></td>
                        <td>
                            <strong><?= htmlspecialchars($row['nama']); ?></strong><br>
                            <small class="text-muted">ID Peminjam: <?= (int)$row['id_user']; ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($row['fasilitas_list']); ?></strong><br>
                            <small class="text-muted">
                                Kategori: <?= htmlspecialchars($row['kategori_list']); ?> |
                                Lokasi: <?= htmlspecialchars($row['lokasi_list']); ?>
                            </small>
                        </td>
                        <td><?= date('d-m-Y', strtotime($row['tanggal_mulai'])); ?></td>
                        <td><?= date('d-m-Y', strtotime($row['tanggal_selesai'])); ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $badgeClass; ?> px-3 py-2">
                                <?= $label; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($status === 'ditolak' && !empty($alasan_tolak)): ?>
                                <strong>Alasan penolakan:</strong><br>
                                <span class="text-danger"><?= nl2br(htmlspecialchars($alasan_tolak)); ?></span>
                                <?php if (!empty($catatan)): ?>
                                    <hr class="my-1">
                                    <small class="text-muted">
                                        Catatan peminjam: <?= nl2br(htmlspecialchars($catatan)); ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= !empty($catatan) ? nl2br(htmlspecialchars($catatan)) : '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <!-- Tombol Detail -->
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary me-1 mb-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalDetail<?= $row['id_pinjam']; ?>">
                                <i class="fas fa-eye"></i> Detail
                            </button>

                            <!-- Tampilkan TERIMA jika status usulan ATAU ditolak -->
                            <?php if ($status === 'usulan' || $status === 'ditolak'): ?>
                                <a href="peminjaman.php?aksi=terima&id=<?= $row['id_pinjam']; ?>"
                                   class="btn btn-sm btn-success me-1 mb-1"
                                   onclick="return confirm('Setujui peminjaman #<?= $row['id_pinjam']; ?> ?');">
                                    <i class="fas fa-check me-1"></i> Terima
                                </a>
                            <?php endif; ?>

                            <!-- Tampilkan TOLAK (dengan alasan) jika status usulan ATAU diterima -->
                            <?php if ($status === 'usulan' || $status === 'diterima'): ?>
                                <button type="button"
                                        class="btn btn-sm btn-danger me-1 mb-1 btn-tolak"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalTolak"
                                        data-id="<?= $row['id_pinjam']; ?>"
                                        data-info="<?=
                                            $status === 'diterima'
                                            ? 'Batalkan & tolak peminjaman #' . $row['id_pinjam'] . ' - ' . htmlspecialchars($row['nama'], ENT_QUOTES)
                                            : 'Peminjaman #' . $row['id_pinjam'] . ' - ' . htmlspecialchars($row['nama'], ENT_QUOTES);
                                        ?>">
                                    <i class="fas fa-times me-1"></i> Tolak
                                </button>
                            <?php endif; ?>

                            <!-- Jika status DITERIMA → tambahan tombol SELESAI -->
                            <?php if ($status === 'diterima'): ?>
                                <a href="peminjaman.php?aksi=selesai&id=<?= $row['id_pinjam']; ?>"
                                   class="btn btn-sm btn-primary me-1 mb-1"
                                   onclick="return confirm('Tandai peminjaman #<?= $row['id_pinjam']; ?> sebagai selesai?');">
                                    <i class="fas fa-flag-checkered me-1"></i> Selesai
                                </a>
                            <?php endif; ?>

                            <!-- Hapus (selalu ada) -->
                            <a href="peminjaman.php?aksi=hapus&id=<?= $row['id_pinjam']; ?>"
                               class="btn btn-sm btn-dark mb-1"
                               onclick="return confirm('Yakin ingin menghapus peminjaman #<?= $row['id_pinjam']; ?> ?');">
                                <i class="fas fa-trash me-1"></i> Hapus
                            </a>
                        </td>
                    </tr>

                    <!-- MODAL DETAIL PEMINJAMAN -->
                    <div class="modal fade" id="modalDetail<?= $row['id_pinjam']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">
                                        Detail Peminjaman #<?= $row['id_pinjam']; ?>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-2">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Peminjam:</strong><br><?= htmlspecialchars($row['nama']); ?></p>
                                            <p class="mb-1"><strong>Tanggal Mulai:</strong><br><?= date('d-m-Y', strtotime($row['tanggal_mulai'])); ?></p>
                                            <p class="mb-1"><strong>Tanggal Selesai:</strong><br><?= date('d-m-Y', strtotime($row['tanggal_selesai'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Status:</strong><br>
                                                <span class="badge bg-<?= $badgeClass; ?>"><?= $label; ?></span>
                                            </p>
                                            <p class="mb-1"><strong>Fasilitas:</strong><br><?= htmlspecialchars($row['fasilitas_list']); ?></p>
                                            <p class="mb-1"><strong>Kategori & Lokasi:</strong><br>
                                                <?= htmlspecialchars($row['kategori_list']); ?> | <?= htmlspecialchars($row['lokasi_list']); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <hr>

                                    <p><strong>Catatan Peminjam:</strong><br>
                                        <?= !empty($catatan) ? nl2br(htmlspecialchars($catatan)) : '<span class="text-muted">-</span>'; ?>
                                    </p>

                                    <?php if (!empty($alasan_tolak)): ?>
                                        <p class="mt-2"><strong>Alasan Penolakan:</strong><br>
                                            <span class="text-danger"><?= nl2br(htmlspecialchars($alasan_tolak)); ?></span>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($row['dokumen_peminjaman'])): ?>
                                        <hr>
                                        <p class="mb-1"><strong>Surat / Dokumen Pendukung:</strong></p>
                                        <a href="../uploads/surat/<?= htmlspecialchars($row['dokumen_peminjaman']); ?>"
                                           class="btn btn-outline-success btn-sm"
                                           target="_blank">
                                            <i class="fas fa-file-alt me-1"></i> Buka Surat / Dokumen
                                        </a>
                                    <?php else: ?>
                                        <hr>
                                        <p class="mb-0"><strong>Surat / Dokumen Pendukung:</strong>
                                            <span class="text-muted ms-1">Tidak ada dokumen yang diunggah.</span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END MODAL DETAIL -->

                    <?php
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-3">
                            Tidak ada data peminjaman.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL TOLAK (ALASAN PENOLAKAN) -->
<div class="modal fade" id="modalTolak" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form action="peminjaman.php" method="post">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Tolak Peminjaman</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aksi" value="tolak">
                    <input type="hidden" name="id_pinjam" id="tolak_id_pinjam">

                    <p class="mb-2">
                        Anda akan menolak <span id="tolak_info" class="fw-semibold"></span>.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Alasan penolakan <span class="text-danger">*</span></label>
                        <textarea name="alasan_penolakan" class="form-control" rows="4" required
                                  placeholder="Tuliskan alasan penolakan, misalnya jadwal bentrok, fasilitas tidak tersedia, dll."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Tolak Peminjaman</button>
                </div>
            </form>
        </div>
    </div>
</div>
<br><br><br>
<?php include '../includes/admin/footer.php'; ?>
<script src="js/datatables-simple-demo.js"></script>

<script>
// isi data modal tolak
var modalTolak = document.getElementById('modalTolak');
if (modalTolak) {
    modalTolak.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget; // tombol yang diklik
        var id     = button.getAttribute('data-id');
        var info   = button.getAttribute('data-info');

        document.getElementById('tolak_id_pinjam').value = id;
        document.getElementById('tolak_info').textContent = info;
    });
}
</script>
