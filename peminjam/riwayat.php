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

/* ============================
   1) Hitung notifikasi belum dibaca (PREPARED)
   ============================ */
$jumlah_notif = 0;
$sqlNotif = "SELECT COUNT(*) AS jml 
             FROM notifikasi 
             WHERE id_user = ? AND is_read = 0";
$stmtNotif = $conn->prepare($sqlNotif);
if ($stmtNotif) {
    $stmtNotif->bind_param("i", $id_user);
    $stmtNotif->execute();
    $resNotif = $stmtNotif->get_result();
    if ($rowN = $resNotif->fetch_assoc()) {
        $jumlah_notif = (int) ($rowN['jml'] ?? 0);
    }
    $stmtNotif->close();
}

/* ============================
   2) Ambil riwayat peminjaman (PREPARED)
      - status 'selesai'  => sudah ada pengembalian
      - status 'ditolak'  => pengajuan ditolak
      Sekaligus ambil pengembalian & tindak lanjut jika ada
   ============================ */
$sqlRiwayat = "
    SELECT 
        p.id_pinjam,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.status AS status_pinjam,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_list,
        
        pg.id_kembali,
        pg.kondisi,
        pg.catatan      AS catatan_kembali,
        pg.tgl_kembali,
        
        tl.id_tindaklanjut,
        tl.tindakan,
        tl.deskripsi    AS deskripsi_tindaklanjut,
        tl.status       AS status_tindaklanjut,
        tl.tanggal      AS tanggal_tindaklanjut
    FROM peminjaman p
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    LEFT JOIN pengembalian pg ON p.id_pinjam = pg.id_pinjam
    LEFT JOIN tindaklanjut tl ON tl.id_kembali = pg.id_kembali
    WHERE p.id_user = ?
      AND p.status IN ('selesai', 'ditolak')
    GROUP BY p.id_pinjam
    ORDER BY p.id_pinjam DESC
";

$stmtRiwayat = $conn->prepare($sqlRiwayat);
if (!$stmtRiwayat) {
    die("Query riwayat error: " . $conn->error);
}
$stmtRiwayat->bind_param("i", $id_user);
$stmtRiwayat->execute();
$resultRiwayat = $stmtRiwayat->get_result();

/* ============================
   3) LOAD HEADER & NAVBAR PEMINJAM
   ============================ */
include '../includes/peminjam/header.php';      // <head> + link CSS + buka <body>
include '../includes/peminjam/navbar.php';      // navbar peminjam (home/fasilitas/peminjaman/riwayat)
?>

<!-- ========================= HERO SECTION ========================= -->
<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-2">Riwayat Peminjaman</h2>
        <p class="mb-1">
            Daftar peminjaman yang sudah <strong>selesai</strong> atau <strong>ditolak</strong>.
        </p>
        <span class="hero-badge">
            <i class="bi bi-info-circle-fill me-1"></i>
            Riwayat juga menampilkan kondisi pengembalian & tindak lanjut kerusakan.
        </span>
    </div>
</section>

<div class="container mb-5 flex-grow-1">

    <?php if ($resultRiwayat && $resultRiwayat->num_rows > 0) : ?>
        <div class="row justify-content-center">
            <div class="col-lg-12 col-xl-11">
                <div class="riwayat-card">
                    <div class="riwayat-table-wrapper">
                        <div class="riwayat-table-responsive">
                            <table class="riwayat-table text-center align-middle">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Fasilitas</th>
                                        <th>Tanggal Pinjam</th>
                                        <th>Tanggal Kembali</th>
                                        <th>Status</th>
                                        <th>Kondisi & Tindak Lanjut</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    while ($data = $resultRiwayat->fetch_assoc()) :
                                        $statusPinjam = strtolower($data['status_pinjam']);
                                        $tglMulai     = $data['tanggal_mulai'] 
                                                        ? date('d M Y', strtotime($data['tanggal_mulai'])) 
                                                        : '-';
                                        $tglKembali   = !empty($data['tgl_kembali'])
                                                        ? date('d M Y', strtotime($data['tgl_kembali']))
                                                        : '-';

                                        $statusClass = ($statusPinjam === 'selesai')
                                            ? 'status-selesai'
                                            : 'status-ditolak';

                                        $fasilitasList = $data['fasilitas_list'] ?? '-';

                                        // Kondisi pengembalian
                                        $kondisi = strtolower($data['kondisi'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td class="fw-semibold">
                                            <?= htmlspecialchars($fasilitasList); ?>
                                        </td>
                                        <td><?= $tglMulai; ?></td>
                                        <td><?= $tglKembali; ?></td>
                                        <td>
                                            <span class="status-pill <?= $statusClass; ?>">
                                                <?= ucfirst($statusPinjam); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <!-- Kondisi pengembalian -->
                                            <?php if ($kondisi === 'rusak'): ?>
                                                <span class="badge-kondisi bg-danger text-white mb-1 d-inline-block">
                                                    Kembali: Rusak
                                                </span><br>
                                            <?php elseif ($kondisi === 'baik'): ?>
                                                <span class="badge-kondisi bg-success text-white mb-1 d-inline-block">
                                                    Kembali: Baik
                                                </span><br>
                                            <?php elseif (!empty($data['id_kembali'])): ?>
                                                <span class="badge-kondisi bg-secondary text-white mb-1 d-inline-block">
                                                    Kembali: Lainnya
                                                </span><br>
                                            <?php else: ?>
                                                <span class="badge-kondisi bg-secondary text-white mb-1 d-inline-block">
                                                    Belum dinilai
                                                </span><br>
                                            <?php endif; ?>

                                            <!-- Status Tindak Lanjut -->
                                            <?php if (!empty($data['id_tindaklanjut'])): ?>
                                                <?php 
                                                    $stTL = strtolower($data['status_tindaklanjut'] ?? '');
                                                    if ($stTL === 'pending') {
                                                        $tlClass = 'bg-warning text-dark';
                                                        $tlText  = 'Tindak lanjut: Pending';
                                                    } elseif ($stTL === 'selesai') {
                                                        $tlClass = 'bg-success text-white';
                                                        $tlText  = 'Tindak lanjut: Selesai';
                                                    } else {
                                                        $tlClass = 'bg-info text-white';
                                                        $tlText  = 'Tindak lanjut: ' . ucfirst($stTL);
                                                    }
                                                ?>
                                                <span class="badge-tl <?= $tlClass; ?> d-inline-block">
                                                    <?= $tlText; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-tl bg-secondary text-white d-inline-block">
                                                    Tidak ada tindak lanjut
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Detail peminjaman -->
                                            <a href="detail_peminjaman.php?id=<?= (int)$data['id_pinjam']; ?>" 
                                               class="btn-detail mb-1" title="Lihat detail peminjaman">
                                                <i class="bi bi-info-circle"></i>
                                            </a>
                                            <!-- Detail tindak lanjut (jika ada) -->
                                            <?php if (!empty($data['id_tindaklanjut'])): ?>
                                                <a href="detail_tindaklanjut.php?id=<?= (int)$data['id_tindaklanjut']; ?>" 
                                                   class="btn-detail mt-1" title="Lihat detail tindak lanjut">
                                                    <i class="bi bi-tools"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div><!-- .riwayat-table-responsive -->
                    </div><!-- .riwayat-table-wrapper -->
                </div><!-- .riwayat-card -->
            </div>
        </div>

    <?php else: ?>
        <div class="empty-state text-center py-5 px-3 mt-4">
            <i class="bi bi-clock-history display-4 d-block mb-3 text-secondary"></i>
            <h5 class="fw-semibold mb-2">Belum ada riwayat peminjaman</h5>
            <p class="text-muted mb-3">
                Riwayat akan muncul setelah peminjamanmu selesai diproses atau ditolak.
            </p>
            <a href="fasilitas.php" class="btn btn-primary">
                <i class="bi bi-building me-1"></i> Ajukan Peminjaman
            </a>
        </div>
    <?php endif; ?>
</div>

<?php 
include '../includes/peminjam/footer.php';
$stmtRiwayat->close();
?>
