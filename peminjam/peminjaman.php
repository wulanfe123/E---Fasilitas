<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}
include '../config/koneksi.php';

$id_user   = isset($_SESSION['id_user']) ? (int) $_SESSION['id_user'] : 0;
$nama_user = $_SESSION['nama_user'] ?? 'Peminjam';

if ($id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

/* ==========================================================
   AMBIL DATA PEMINJAMAN AKTIF (USULAN + DITERIMA)
   PAKAI PREPARED STATEMENT
   ========================================================== */
$sql = "
    SELECT 
        p.id_pinjam,
        p.id_user,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.status,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_dipinjam,
        COALESCE(GROUP_CONCAT(DISTINCT f.kategori SEPARATOR ', '), '-')        AS kategori_list,
        COALESCE(GROUP_CONCAT(DISTINCT f.lokasi SEPARATOR ', '), '-')          AS lokasi_list
    FROM peminjaman p
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    WHERE p.id_user = ?
      AND p.status IN ('usulan','diterima')
    GROUP BY p.id_pinjam
    ORDER BY p.id_pinjam DESC
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
        <h2 class="fw-bold mb-1">Peminjaman Saya</h2>
        <p class="mb-2">
            Pantau pengajuan peminjaman fasilitas yang masih berlangsung dan menunggu proses.
        </p>
        <span class="hero-badge">
            <i class="bi bi-lightning-charge-fill"></i>
            Tip: Ajukan peminjaman lebih awal untuk menghindari bentrok jadwal.
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
                            <label for="filterStatus" class="form-label mb-1">Status Peminjaman</label>
                            <select id="filterStatus" class="form-select form-select-sm">
                                <option value="">Semua Status</option>
                                <option value="usulan">Usulan</option>
                                <option value="diterima">Diterima</option>
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
                </div>
            </div>
        </div>

        <!-- TABEL PEMINJAMAN -->
        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10">
                <div class="card card-table">
                    <div class="card-body p-3">
                        <div class="table-wrapper">
                            <div class="table-responsive">
                                <table class="custom-table" id="tablePeminjaman">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Fasilitas</th>
                                            <th>Tanggal Pinjam</th>
                                            <th>Tanggal Kembali</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; ?>
                                        <?php while ($data = $result->fetch_assoc()) : 
                                            $status = strtolower($data['status'] ?? '');

                                            // GANTI NESTED TERNARY DENGAN IF/ELSEIF
                                            if ($status === 'usulan') {
                                                $pillClass = 'status-pill-usulan';
                                            } elseif ($status === 'diterima') {
                                                $pillClass = 'status-pill-diterima';
                                            } elseif ($status === 'ditolak') {
                                                $pillClass = 'status-pill-ditolak';
                                            } elseif ($status === 'selesai') {
                                                $pillClass = 'status-pill-selesai';
                                            } else {
                                                $pillClass = 'status-pill-selesai';
                                            }

                                            $fasilitas = $data['fasilitas_dipinjam'] ?? '-';
                                            $kategori  = $data['kategori_list'] ?? '-';
                                            $lokasi    = $data['lokasi_list'] ?? '-';

                                            $tglMulai   = !empty($data['tanggal_mulai']) 
                                                ? date('d M Y', strtotime($data['tanggal_mulai'])) 
                                                : '-';
                                            $tglSelesai = !empty($data['tanggal_selesai']) 
                                                ? date('d M Y', strtotime($data['tanggal_selesai'])) 
                                                : '-';
                                        ?>
                                            <tr class="row-peminjaman"
                                                data-status="<?= htmlspecialchars($status); ?>"
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
                                                </td>
                                                <td class="text-center"><?= $tglMulai; ?></td>
                                                <td class="text-center"><?= $tglSelesai; ?></td>
                                                <td class="text-center">
                                                    <span class="status-pill <?= $pillClass; ?>">
                                                        <?= ucfirst($status); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-inline-flex gap-1">
                                                        <!-- Detail -->
                                                        <a href="detail_peminjaman.php?id=<?= (int)$data['id_pinjam']; ?>"
                                                           class="btn-detail-round" title="Detail">
                                                            <i class="bi bi-info"></i>
                                                        </a>

                                                        <!-- Aksi untuk USULAN -->
                                                        <?php if ($status === 'usulan'): ?>
                                                            <a href="edit_peminjaman.php?id=<?= (int)$data['id_pinjam']; ?>"
                                                               class="btn btn-warning btn-action-small text-white" title="Edit">
                                                                <i class="bi bi-pencil-square"></i>
                                                            </a>
                                                            <a href="batalkan_peminjaman.php?id=<?= (int)$data['id_pinjam']; ?>"
                                                               class="btn btn-danger btn-action-small" title="Batalkan"
                                                               onclick="return confirm('Yakin ingin membatalkan pengajuan peminjaman ini?');">
                                                                <i class="bi bi-x-circle"></i>
                                                            </a>
                                                        <?php endif; ?>

                                                        <!-- Aksi untuk DITERIMA (Konfirmasi Pengembalian) -->
                                                        <?php if ($status === 'diterima'): ?>
                                                            <a href="pengembalian_peminjaman.php?id=<?= (int)$data['id_pinjam']; ?>"
                                                               class="btn btn-success btn-action-small" title="Konfirmasi Pengembalian"
                                                               onclick="return confirm('Konfirmasi bahwa fasilitas sudah dikembalikan? Data akan dipindah ke Riwayat.');">
                                                                <i class="bi bi-box-arrow-in-left"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>

                                        <tr id="rowEmptyFilter" style="display:none;">
                                            <td colspan="6" class="text-center text-muted py-3">
                                                Tidak ada data peminjaman yang sesuai dengan filter/carian.
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
            <h5 class="fw-semibold mb-2">Belum ada peminjaman aktif</h5>
            <p class="text-muted mb-3">
                Kamu belum memiliki peminjaman yang sedang berjalan.  
                Mulai ajukan sekarang untuk kebutuhan kegiatanmu.
            </p>
            <a href="fasilitas.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i> Ajukan Peminjaman
            </a>
        </div>

    <?php endif; ?>
</div>

<script>
    const filterStatus    = document.getElementById('filterStatus');
    const searchFasilitas = document.getElementById('searchFasilitas');
    const rows            = document.querySelectorAll('.row-peminjaman');
    const rowEmpty        = document.getElementById('rowEmptyFilter');
    const btnSearch       = document.getElementById('btnSearch');
    const btnReset        = document.getElementById('btnReset');

    function applyFilter() {
        const st = (filterStatus ? filterStatus.value : '').toLowerCase();
        const q  = (searchFasilitas ? searchFasilitas.value : '').toLowerCase().trim();

        let visibleCount = 0;

        rows.forEach(row => {
            const rowStatus    = (row.dataset.status || '').toLowerCase();
            const rowFasilitas = (row.dataset.fasilitas || '').toLowerCase();

            let show = true;

            if (st && rowStatus !== st) show = false;
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
            if (filterStatus) filterStatus.value    = '';
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
