<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}
include '../config/koneksi.php';
include '../config/notifikasi_helper.php';


$id_user   = isset($_SESSION['id_user']) ? (int) $_SESSION['id_user'] : 0;
$nama_user = $_SESSION['nama_user'] ?? 'Peminjam';

if ($id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

/* ==========================================================
   1. PROSES KONFIRMASI PENGEMBALIAN (JIKA ADA ?id=...)
   ========================================================== */
$id_pinjam_konfirmasi = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id_pinjam_konfirmasi > 0) {

    // Cek peminjaman: milik user ini & status masih 'diterima'
    $sqlCek = "
        SELECT id_pinjam, id_user, status
        FROM peminjaman
        WHERE id_pinjam = ?
        LIMIT 1
    ";
    $stmtCek = $conn->prepare($sqlCek);
    if (!$stmtCek) {
        $_SESSION['error'] = "Gagal cek data peminjaman: " . $conn->error;
        header("Location: peminjaman_saya.php");
        exit;
    }
    $stmtCek->bind_param("i", $id_pinjam_konfirmasi);
    $stmtCek->execute();
    $resCek = $stmtCek->get_result();
    $dataP  = $resCek->fetch_assoc();
    $stmtCek->close();

    if (!$dataP) {
        $_SESSION['error'] = "Data peminjaman tidak ditemukan.";
        header("Location: peminjaman_saya.php");
        exit;
    }

    if ((int)$dataP['id_user'] !== $id_user) {
        $_SESSION['error'] = "Anda tidak berhak mengubah peminjaman ini.";
        header("Location: peminjaman_saya.php");
        exit;
    }

    if (strtolower($dataP['status']) !== 'diterima') {
        $_SESSION['error'] = "Peminjaman ini sudah tidak aktif atau sudah dikembalikan.";
        header("Location: peminjaman_saya.php");
        exit;
    }

    // Mulai transaksi (supaya insert + update konsisten)
    $conn->begin_transaction();

    try {
        // 1) Cek apakah sudah ada record pengembalian untuk id_pinjam ini
        $sqlCekKembali = "SELECT 1 FROM pengembalian WHERE id_pinjam = ? LIMIT 1";
        $stmtCK = $conn->prepare($sqlCekKembali);
        if (!$stmtCK) {
            throw new Exception("Gagal cek pengembalian: " . $conn->error);
        }
        $stmtCK->bind_param("i", $id_pinjam_konfirmasi);
        $stmtCK->execute();
        $resCK = $stmtCK->get_result();
        $sudahAdaPengembalian = $resCK->fetch_assoc() ? true : false;
        $stmtCK->close();

        // ===== PERUBAHAN: jika sudah ada pengembalian, jangan diproses ulang =====
        if ($sudahAdaPengembalian) {
            throw new Exception(
                "Pengembalian untuk peminjaman ini sudah diproses oleh Bagian Umum. " .
                "Silakan cek riwayat pengembalian atau hubungi Bagian Umum jika ada kendala."
            );
        }
        // =======================================================================

        // 2) Jika belum ada, insert ke tabel pengembalian (otomatis kondisi baik)
        $sqlIns = "
            INSERT INTO pengembalian (id_pinjam, kondisi, catatan, tgl_kembali)
            VALUES (?, 'baik', '', CURDATE())
        ";
        $stmtIns = $conn->prepare($sqlIns);
        if (!$stmtIns) {
            throw new Exception("Gagal menyiapkan insert pengembalian: " . $conn->error);
        }
        $stmtIns->bind_param("i", $id_pinjam_konfirmasi);
        if (!$stmtIns->execute()) {
            throw new Exception("Gagal menyimpan data pengembalian.");
        }
        $stmtIns->close();

        // 3) Update status peminjaman menjadi 'selesai'
        $sqlUp = "UPDATE peminjaman SET status = 'selesai' WHERE id_pinjam = ?";
        $stmtUp = $conn->prepare($sqlUp);
        if (!$stmtUp) {
            throw new Exception("Gagal menyiapkan update peminjaman: " . $conn->error);
        }
        $stmtUp->bind_param("i", $id_pinjam_konfirmasi);
        if (!$stmtUp->execute()) {
            throw new Exception("Gagal mengupdate status peminjaman.");
        }
        $stmtUp->close();
        $conn->commit();
        // Mengirim notifikasi ke peminjam (opsional) dan ke semua admin//
        notif_pengembalian_baru($conn, $id_pinjam_konfirmasi, $id_user);

        $_SESSION['success'] = "Pengembalian fasilitas berhasil dikonfirmasi. 
        Peminjaman dipindah ke riwayat dan fasilitas akan kembali tersedia.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }

    // Setelah proses, kembali ke halaman peminjaman aktif
    header("Location: peminjaman_saya.php");
    exit;
}

/* ==========================================================
   2. TAMPILKAN RIWAYAT PENGEMBALIAN (TANPA ?id=...)
   Tambah info id_tindaklanjut_terakhir untuk akses chat kerusakan
   ========================================================== */
$sql = "
    SELECT 
        k.id_kembali,
        k.id_pinjam,
        k.kondisi,
        k.catatan,
        k.tgl_kembali,
        p.tanggal_mulai,
        p.tanggal_selesai,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_dipinjam,
        COALESCE(GROUP_CONCAT(DISTINCT f.kategori SEPARATOR ', '), '-')        AS kategori_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.lokasi SEPARATOR ', '), '-')          AS lokasi_list,
        tl_last.id_tindaklanjut AS id_tindaklanjut_terakhir
    FROM pengembalian k
    JOIN peminjaman p ON k.id_pinjam = p.id_pinjam
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    LEFT JOIN (
        SELECT id_kembali, MAX(id_tindaklanjut) AS id_tindaklanjut
        FROM tindaklanjut
        GROUP BY id_kembali
    ) AS tl_last ON tl_last.id_kembali = k.id_kembali
    WHERE p.id_user = ?
    GROUP BY 
        k.id_kembali, 
        k.id_pinjam, 
        k.kondisi, 
        k.catatan, 
        k.tgl_kembali, 
        p.tanggal_mulai, 
        p.tanggal_selesai,
        tl_last.id_tindaklanjut
    ORDER BY k.id_kembali DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Query error: " . $conn->error);
}
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();

/* ============================
   HEADER & NAVBAR PEMINJAM
   ============================ */
include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>

<!-- HERO -->
<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-1">Riwayat Pengembalian</h2>
        <p class="mb-2">
            Lihat riwayat pengembalian fasilitas yang pernah kamu gunakan beserta kondisinya.
        </p>
        <span class="hero-badge">
            <i class="bi bi-arrow-counterclockwise"></i>
            Tip: Pastikan kondisi fasilitas dilaporkan dengan jujur saat mengembalikan.
        </span>
    </div>
</section>

<div class="container mb-5 flex-grow-1">

    <!-- FLASH MESSAGE -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="alert alert-success mt-2">
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="alert alert-danger mt-2">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($result && $result->num_rows > 0): ?>

        <!-- FILTER BAR -->
        <div class="row justify-content-center mb-3">
            <div class="col-lg-11 col-xl-10">
                <div class="filter-bar">
                    <div class="filter-title mb-2">
                        <i class="bi bi-funnel me-1"></i> Filter & Pencarian
                    </div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4 col-sm-12">
                            <label for="filterKondisi" class="form-label mb-1">Kondisi Fasilitas</label>
                            <select id="filterKondisi" class="form-select form-select-sm">
                                <option value="">Semua Kondisi</option>
                                <option value="baik">Baik</option>
                                <option value="rusak">Rusak</option>
                            </select>
                        </div>
                        <div class="col-md-8 col-sm-12">
                            <label for="searchFasilitas" class="form-label mb-1">Cari Fasilitas</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="searchFasilitas"
                                       class="form-control"
                                       placeholder="Ketik nama fasilitas yang ingin dicari...">
                                <button class="btn btn-primary"
                                        type="button"
                                        id="btnSearch">
                                    <i class="bi bi-search"></i> Cari
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="btnReset">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                            </div>
                        </div>
                    </div>
                </div><!-- .filter-bar -->
            </div>
        </div>

        <!-- TABEL RIWAYAT PENGEMBALIAN -->
        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10">
                <div class="card card-table">
                    <div class="card-body p-3">
                        <div class="table-wrapper">
                            <div class="table-responsive">
                                <table class="custom-table" id="tablePengembalian">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Fasilitas</th>
                                            <th>Tanggal Pinjam</th>
                                            <th>Tanggal Kembali</th>
                                            <th>Kondisi</th>
                                            <th>Catatan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; ?>
                                        <?php while ($row = $result->fetch_assoc()) : 
                                            $kondisi = strtolower($row['kondisi'] ?? '');

                                            if ($kondisi === 'baik') {
                                                $kondisiClass = 'bg-success';
                                            } elseif ($kondisi === 'rusak') {
                                                $kondisiClass = 'bg-danger';
                                            } else {
                                                $kondisiClass = 'bg-secondary';
                                            }

                                            $fasilitas = $row['fasilitas_dipinjam'] ?? '-';
                                            $kategori  = $row['kategori_list'] ?? '-';
                                            $lokasi    = $row['lokasi_list'] ?? '-';

                                            $tglMulai = !empty($row['tanggal_mulai'])
                                                ? date('d M Y', strtotime($row['tanggal_mulai']))
                                                : '-';

                                            $tglSelesai = !empty($row['tanggal_selesai'])
                                                ? date('d M Y', strtotime($row['tanggal_selesai']))
                                                : '-';

                                            $tglKembali = !empty($row['tgl_kembali'])
                                                ? date('d M Y', strtotime($row['tgl_kembali']))
                                                : '-';

                                            $catatan = $row['catatan'] ?? '';

                                            // id tindak lanjut terakhir (kalau ada)
                                            $idTindakLanjut = isset($row['id_tindaklanjut_terakhir'])
                                                ? (int)$row['id_tindaklanjut_terakhir']
                                                : 0;
                                        ?>
                                            <tr class="row-pengembalian"
                                                data-kondisi="<?= htmlspecialchars($kondisi); ?>"
                                                data-fasilitas="<?= htmlspecialchars(strtolower($fasilitas)); ?>">
                                                <td class="text-center"><?= $no++; ?></td>
                                                <td>
                                                    <div class="fasilitas-name">
                                                        <?= htmlspecialchars($fasilitas); ?>
                                                    </div>
                                                    <div class="fasilitas-meta">
                                                        <i class="bi bi-tags"></i> Kategori: <?= htmlspecialchars($kategori); ?> &nbsp; | &nbsp;
                                                        <i class="bi bi-geo-alt"></i> Lokasi: <?= htmlspecialchars($lokasi); ?>
                                                    </div>
                                                    <?php if ($idTindakLanjut > 0): ?>
                                                        <div class="mt-1">
                                                            <span class="badge bg-warning text-dark">
                                                                <i class="bi bi-tools me-1"></i>
                                                                Ada tindak lanjut kerusakan
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?= $tglMulai; ?> s/d <br><?= $tglSelesai; ?>
                                                </td>
                                                <td class="text-center"><?= $tglKembali; ?></td>
                                                <td class="text-center">
                                                    <span class="badge <?= $kondisiClass; ?> px-3 py-2">
                                                        <?= ucfirst($kondisi ?: '-'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?= !empty($catatan) 
                                                        ? nl2br(htmlspecialchars($catatan)) 
                                                        : '<span class="text-muted">-</span>'; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-inline-flex flex-column gap-1">
                                                        <a href="detail_peminjaman_saya.php?id=<?= (int)$row['id_pinjam']; ?>"
                                                           class="btn-detail-round"
                                                           title="Detail Peminjaman & Pengembalian">
                                                            <i class="bi bi-info-circle"></i>
                                                        </a>

                                                        <?php if ($kondisi === 'rusak' || $idTindakLanjut > 0): ?>
                                                            <a href="komunikasi_tindaklanjut.php?id_pinjam=<?= (int)$row['id_pinjam']; ?>&id_tl=<?= (int)$idTindakLanjut; ?>"
                                                               class="btn btn-outline-danger btn-sm"
                                                               title="Komunikasi Kerusakan">
                                                                <i class="bi bi-chat-dots me-1"></i>
                                                                Komunikasi Kerusakan
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>

                                        <tr id="rowEmptyFilter" style="display:none;">
                                            <td colspan="7" class="text-center text-muted py-3">
                                                Tidak ada data pengembalian yang sesuai dengan filter/carian.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div><!-- .table-responsive -->
                        </div><!-- .table-wrapper -->
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>

        <!-- EMPTY STATE -->
        <div class="empty-state text-center py-5 px-3 mt-4">
            <i class="bi bi-inbox display-4 d-block mb-3 text-secondary"></i>
            <h5 class="fw-semibold mb-2">Belum ada riwayat pengembalian</h5>
            <p class="text-muted mb-3">
                Riwayat pengembalian akan muncul di sini setelah kamu mengembalikan fasilitas
                dan pengelola memproses pengembalian tersebut.
            </p>
            <a href="peminjaman_saya.php" class="btn btn-primary">
                <i class="bi bi-arrow-left-circle me-1"></i> Kembali ke Peminjaman Aktif
            </a>
        </div>

    <?php endif; ?>
</div>

<script>
    const filterKondisi   = document.getElementById('filterKondisi');
    const searchFasilitas = document.getElementById('searchFasilitas');
    const rows            = document.querySelectorAll('.row-pengembalian');
    const rowEmpty        = document.getElementById('rowEmptyFilter');
    const btnSearch       = document.getElementById('btnSearch');
    const btnReset        = document.getElementById('btnReset');

    function applyFilter() {
        const kd = (filterKondisi ? filterKondisi.value : '').toLowerCase();
        const q  = (searchFasilitas ? searchFasilitas.value : '').toLowerCase().trim();

        let visibleCount = 0;

        rows.forEach(row => {
            const rowKondisi   = (row.dataset.kondisi || '').toLowerCase();
            const rowFasilitas = (row.dataset.fasilitas || '').toLowerCase();

            let show = true;

            if (kd && rowKondisi !== kd) show = false;
            if (q && !rowFasilitas.includes(q)) show = false;

            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        if (rowEmpty) {
            rowEmpty.style.display = visibleCount === 0 ? '' : 'none';
        }
    }

    if (btnSearch) {
        btnSearch.addEventListener('click', applyFilter);
    }
    if (btnReset) {
        btnReset.addEventListener('click', () => {
            if (filterKondisi) filterKondisi.value = '';
            if (searchFasilitas) searchFasilitas.value = '';
            applyFilter();
        });
    }
    if (searchFasilitas) {
        searchFasilitas.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                applyFilter();
            }
        });
    }
</script>

<?php include '../includes/peminjam/footer.php'; ?>
