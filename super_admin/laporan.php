<?php
session_start();
include '../config/koneksi.php';

// ==========================
// CEK LOGIN & ROLE
// ==========================
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

// ===================== NOTIFIKASI (UNTUK NAVBAR BARU) =====================
// Dipakai di navbar.php: $notifList dan $jumlahNotif

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
// Ambil 10 notifikasi terbaru (read + unread) untuk dropdown
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

/* =========================================================
   FILTER TANGGAL (OPSIONAL)
   ========================================================= */
$tgl_awal  = $_GET['tgl_awal']  ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

$pattern = '/^\d{4}-\d{2}-\d{2}$/';
if ($tgl_awal !== '' && !preg_match($pattern, $tgl_awal))  $tgl_awal  = '';
if ($tgl_akhir !== '' && !preg_match($pattern, $tgl_akhir)) $tgl_akhir = '';

// escape untuk keamanan tambahan
$tgl_awal_sql  = $tgl_awal  !== '' ? mysqli_real_escape_string($conn, $tgl_awal)  : '';
$tgl_akhir_sql = $tgl_akhir !== '' ? mysqli_real_escape_string($conn, $tgl_akhir) : '';

// WHERE untuk peminjaman & pengembalian (pakai p.tanggal_mulai)
$whereP = "1=1";
if ($tgl_awal_sql !== '') {
    $whereP .= " AND p.tanggal_mulai >= '{$tgl_awal_sql}'";
}
if ($tgl_akhir_sql !== '') {
    $whereP .= " AND p.tanggal_mulai <= '{$tgl_akhir_sql}'";
}

// WHERE untuk tindak lanjut (pakai tl.tanggal - datetime)
$whereTL = "1=1";
if ($tgl_awal_sql !== '') {
    $whereTL .= " AND tl.tanggal >= '{$tgl_awal_sql} 00:00:00'";
}
if ($tgl_akhir_sql !== '') {
    $whereTL .= " AND tl.tanggal <= '{$tgl_akhir_sql} 23:59:59'";
}

/* =========================================================
   FILTER RANGE UNTUK HITUNG TOTAL PEMINJAMAN PER FASILITAS
   ========================================================= */
$filterRangeForCount = "";
if ($tgl_awal_sql !== '') {
    $filterRangeForCount .= " AND p2.tanggal_mulai >= '{$tgl_awal_sql}'";
}
if ($tgl_akhir_sql !== '') {
    $filterRangeForCount .= " AND p2.tanggal_mulai <= '{$tgl_akhir_sql}'";
}

/* =========================================================
   TEMPLATE
   ========================================================= */
include '../includes/admin/header.php';
include '../includes/admin/navbar.php';
include '../includes/admin/sidebar.php';
?>

<div class="container-fluid px-4">
    <!-- Header Halaman -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-3">
        <div>
            <h2 class="fw-bold text-info mb-1">Laporan Peminjaman & Pengembalian</h2>
            <p class="text-muted mb-0">
                Rekap data aktivitas peminjaman, pengembalian, riwayat per fasilitas, tindak lanjut kerusakan, dan komplain peminjam.
            </p>
        </div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-info shadow-sm" onclick="cetakLaporan('semua')">
                <i class="fas fa-file-pdf me-1"></i> Cetak Semua
            </button>
            <button type="button" class="btn btn-outline-success shadow-sm" onclick="cetakLaporan('peminjaman')">
                <i class="fas fa-file-pdf me-1"></i> Cetak Peminjaman
            </button>
            <button type="button" class="btn btn-outline-warning shadow-sm" onclick="cetakLaporan('tindaklanjut')">
                <i class="fas fa-file-pdf me-1"></i> Cetak Tindak Lanjut
            </button>
        </div>
    </div>

    <hr class="mt-0 mb-4" style="border-top: 2px solid #0dcaf0; opacity: 0.4;">

    <!-- FILTER TANGGAL -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form class="row gy-2 gx-3 align-items-end" method="get">
                <div class="col-md-3 col-sm-6">
                    <label for="filter_tgl_awal" class="form-label mb-1">Tanggal Mulai</label>
                    <input type="date"
                           name="tgl_awal"
                           id="filter_tgl_awal"
                           value="<?= htmlspecialchars($tgl_awal); ?>"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label for="filter_tgl_akhir" class="form-label mb-1">Tanggal Akhir</label>
                    <input type="date"
                           name="tgl_akhir"
                           id="filter_tgl_akhir"
                           value="<?= htmlspecialchars($tgl_akhir); ?>"
                           class="form-control form-control-sm">
                </div>
                <div class="col-md-3 col-sm-6">
                    <button type="submit" class="btn btn-sm btn-info mt-3 text-white">
                        <i class="fas fa-filter me-1"></i> Terapkan Filter
                    </button>
                    <a href="laporan.php" class="btn btn-sm btn-outline-secondary mt-3">
                        Reset
                    </a>
                </div>
                <div class="col-md-3 col-sm-6 text-md-end text-start mt-3 small text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Filter akan diterapkan pada semua tabel dan juga pada tampilan cetak.
                </div>
            </form>
        </div>
    </div>

    <!-- ===================== -->
    <!-- 1. TABEL PEMINJAMAN & PENGEMBALIAN -->
    <!-- ===================== -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-info text-white fw-semibold">
            Rekapitulasi Peminjaman dan Pengembalian
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table table-bordered table-hover align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th>No</th>
                        <th>Nama Peminjam</th>
                        <th>Tanggal Pinjam</th>
                        <th>Tanggal Kembali</th>
                        <th>Status Peminjaman</th>
                        <th>Kondisi Saat Kembali</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    // peminjaman + pengembalian (menggunakan kolom DB: tanggal_mulai, tanggal_selesai, status, tgl_kembali, kondisi)
                    $sqlP = "
                        SELECT 
                            u.nama, 
                            p.tanggal_mulai, 
                            p.tanggal_selesai, 
                            p.status, 
                            pg.kondisi, 
                            pg.tgl_kembali
                        FROM peminjaman p
                        LEFT JOIN pengembalian pg ON p.id_pinjam = pg.id_pinjam
                        JOIN users u ON p.id_user = u.id_user
                        WHERE {$whereP}
                        ORDER BY p.id_pinjam DESC
                    ";
                    $result = $conn->query($sqlP);

                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Badge status peminjaman (sesuai nilai di DB)
                            $statusVal = strtolower($row['status']);
                            if ($statusVal === 'diterima' || $statusVal === 'disetujui') {
                                $statusBadge = "<span class='badge bg-success'>Diterima</span>";
                            } elseif ($statusVal === 'ditolak') {
                                $statusBadge = "<span class='badge bg-danger'>Ditolak</span>";
                            } elseif ($statusVal === 'selesai') {
                                $statusBadge = "<span class='badge bg-primary'>Selesai</span>";
                            } else {
                                $statusBadge = "<span class='badge bg-secondary'>Menunggu</span>";
                            }

                            // Kondisi pengembalian: 'bagus' / 'rusak' atau lainnya
                            $kondisiRaw = $row['kondisi'];
                            if ($kondisiRaw === null || $kondisiRaw === '') {
                                $kondisiLabel = '-';
                            } else {
                                $kondisiLower = strtolower($kondisiRaw);
                                if ($kondisiLower === 'bagus' || $kondisiLower === 'baik') {
                                    $kondisiLabel = "<span class='badge bg-success'>Bagus</span>";
                                } elseif ($kondisiLower === 'rusak') {
                                    $kondisiLabel = "<span class='badge bg-danger'>Rusak</span>";
                                } else {
                                    $kondisiLabel = htmlspecialchars(ucfirst($kondisiRaw));
                                }
                            }

                            $tglPinjam  = $row['tanggal_mulai'] ? date('d-m-Y', strtotime($row['tanggal_mulai'])) : '-';
                            // pakai pg.tgl_kembali dari tabel pengembalian
                            $tglKembali = $row['tgl_kembali']   ? date('d-m-Y', strtotime($row['tgl_kembali']))   : '-';

                            echo "
                            <tr>
                                <td class='text-center'>{$no}</td>
                                <td>".htmlspecialchars($row['nama'])."</td>
                                <td class='text-center'>{$tglPinjam}</td>
                                <td class='text-center'>{$tglKembali}</td>
                                <td class='text-center'>{$statusBadge}</td>
                                <td class='text-center'>{$kondisiLabel}</td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr><td colspan='6' class='text-center text-muted py-3'>Tidak ada data laporan.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===================== -->
    <!-- 2. RIWAYAT PEMINJAMAN PER FASILITAS -->
    <!-- ===================== -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-success text-white fw-semibold d-flex justify-content-between align-items-center">
            <span>Riwayat Peminjaman per Fasilitas</span>
            <small class="text-white-50">
                Contoh: lihat seberapa sering <strong>Miniconfer</strong> dipinjam dalam periode tertentu.
            </small>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4 col-sm-6">
                    <label for="searchFasilitas" class="form-label mb-1">Cari Fasilitas</label>
                    <input type="text" id="searchFasilitas" class="form-control form-control-sm" placeholder="Ketik nama fasilitas (mis. Miniconfer)...">
                </div>
            </div>

            <table class="table table-bordered table-hover align-middle" id="tableRiwayatFasilitas">
                <thead class="table-light text-center">
                    <tr>
                        <th>No</th>
                        <th>Nama Fasilitas</th>
                        <th>Nama Peminjam</th>
                        <th>ID Pinjam</th>
                        <th>Tanggal Pinjam</th>
                        <th>Tanggal Kembali</th>
                        <th>Status Peminjaman</th>
                        <th>Kondisi</th>
                        <th>Total Dipinjam (periode ini)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    // Riwayat peminjaman per fasilitas:
                    //   peminjaman p, daftar_peminjaman_fasilitas df, fasilitas f, users u, pengembalian pg
                    $sqlHist = "
                        SELECT
                            f.nama_fasilitas,
                            u.nama AS nama_peminjam,
                            p.id_pinjam,
                            p.tanggal_mulai,
                            p.status,
                            pg.tgl_kembali,
                            pg.kondisi,
                            (
                                SELECT COUNT(*)
                                FROM peminjaman p2
                                JOIN daftar_peminjaman_fasilitas df2 
                                  ON p2.id_pinjam = df2.id_pinjam
                                WHERE df2.id_fasilitas = df.id_fasilitas
                                {$filterRangeForCount}
                            ) AS total_peminjaman_fasilitas
                        FROM peminjaman p
                        JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
                        JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
                        JOIN users u ON p.id_user = u.id_user
                        LEFT JOIN pengembalian pg ON p.id_pinjam = pg.id_pinjam
                        WHERE {$whereP}
                        ORDER BY f.nama_fasilitas ASC, p.tanggal_mulai DESC
                    ";
                    $queryHist = $conn->query($sqlHist);

                    if ($queryHist && $queryHist->num_rows > 0) {
                        while ($h = $queryHist->fetch_assoc()) {
                            $statusVal = strtolower($h['status']);
                            if     ($statusVal === 'diterima' || $statusVal === 'disetujui') $statusText = 'Diterima';
                            elseif ($statusVal === 'ditolak')                                $statusText = 'Ditolak';
                            elseif ($statusVal === 'selesai')                                $statusText = 'Selesai';
                            else                                                               $statusText = 'Menunggu';

                            $tglPinjam  = $h['tanggal_mulai'] ? date('d-m-Y', strtotime($h['tanggal_mulai'])) : '-';
                            $tglKembali = $h['tgl_kembali']   ? date('d-m-Y', strtotime($h['tgl_kembali']))   : '-';

                            $kondisiRaw = $h['kondisi'];
                            if ($kondisiRaw === null || $kondisiRaw === '') {
                                $kondisiText = '-';
                            } else {
                                $kondisiText = ucfirst(strtolower($kondisiRaw)); // Bagus / Rusak
                            }

                            $totalFas   = (int)($h['total_peminjaman_fasilitas'] ?? 0);
                            $dataAttr   = htmlspecialchars(strtolower($h['nama_fasilitas']), ENT_QUOTES);

                            echo "
                            <tr data-fasilitas='{$dataAttr}'>
                                <td class='text-center'>{$no}</td>
                                <td>".htmlspecialchars($h['nama_fasilitas'])."</td>
                                <td>".htmlspecialchars($h['nama_peminjam'])."</td>
                                <td class='text-center'>{$h['id_pinjam']}</td>
                                <td class='text-center'>{$tglPinjam}</td>
                                <td class='text-center'>{$tglKembali}</td>
                                <td class='text-center'>{$statusText}</td>
                                <td class='text-center'>{$kondisiText}</td>
                                <td class='text-center'>{$totalFas}</td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr><td colspan='9' class='text-center text-muted py-3'>Tidak ada riwayat peminjaman fasilitas dalam periode ini.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===================== -->
    <!-- 3. TABEL TINDAK LANJUT & KOMPLAIN -->
    <!-- ===================== -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-warning fw-semibold">
            Rekap Tindak Lanjut Kerusakan & Komplain Peminjam
        </div>
        <div class="card-body">
            <p class="text-muted" style="font-size: 0.9rem;">
                Bagian ini merangkum setiap tindak lanjut kerusakan yang dilakukan oleh Bagian Umum/Super Admin,
                beserta jumlah komplain yang diajukan peminjam terhadap tindak lanjut tersebut.
            </p>

            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th>No</th>
                        <th>ID Tindak Lanjut</th>
                        <th>ID Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Tindakan</th>
                        <th>Status Tindak Lanjut</th>
                        <th>Jumlah Komplain</th>
                        <th>Tanggal Tindak Lanjut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    // Tabel tindaklanjut & komplain_tindaklanjut, pakai kolom:
                    //   tl.id_tindaklanjut, tl.tanggal, tl.tindakan, tl.status
                    //   pengembalian.id_kembali, peminjaman.id_pinjam, users.nama
                    //   komplain_tindaklanjut.id_komplain
                    $sqlTL = "
                        SELECT 
                            tl.id_tindaklanjut,
                            tl.tanggal,
                            tl.tindakan,
                            tl.status AS status_tl,
                            pg.id_pinjam,
                            u.nama AS nama_peminjam,
                            COUNT(kt.id_komplain) AS total_komplain
                        FROM tindaklanjut tl
                        JOIN pengembalian pg       ON tl.id_kembali = pg.id_kembali
                        JOIN peminjaman p          ON pg.id_pinjam = p.id_pinjam
                        JOIN users u               ON p.id_user = u.id_user
                        LEFT JOIN komplain_tindaklanjut kt 
                               ON tl.id_tindaklanjut = kt.id_tindaklanjut
                        WHERE {$whereTL}
                        GROUP BY tl.id_tindaklanjut
                        ORDER BY tl.tanggal DESC
                    ";
                    $resultTL = $conn->query($sqlTL);

                    if ($resultTL && $resultTL->num_rows > 0) {
                        while ($r = $resultTL->fetch_assoc()) {
                            $statusTL = strtolower($r['status_tl']);
                            $badgeClass = 'secondary';
                            if ($statusTL === 'proses')  $badgeClass = 'warning text-dark';
                            if ($statusTL === 'selesai') $badgeClass = 'success';

                            $tglTL = $r['tanggal'] ? date('d-m-Y H:i', strtotime($r['tanggal'])) : '-';

                            echo "
                            <tr>
                                <td class='text-center'>{$no}</td>
                                <td class='text-center'>{$r['id_tindaklanjut']}</td>
                                <td class='text-center'>{$r['id_pinjam']}</td>
                                <td>".htmlspecialchars($r['nama_peminjam'])."</td>
                                <td>".htmlspecialchars($r['tindakan'])."</td>
                                <td class='text-center'>
                                    <span class='badge bg-{$badgeClass}'>".htmlspecialchars($r['status_tl'])."</span>
                                </td>
                                <td class='text-center'>{$r['total_komplain']}</td>
                                <td class='text-center'>{$tglTL}</td>
                            </tr>";
                            $no++;
                        }
                    } else {
                        echo "<tr><td colspan='8' class='text-center text-muted py-3'>Belum ada data tindak lanjut.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Kirim ke laporan_cetak.php dengan jenis + filter tanggal
function cetakLaporan(jenis) {
    const tglAwal  = document.getElementById('filter_tgl_awal').value;
    const tglAkhir = document.getElementById('filter_tgl_akhir').value;

    let url = 'laporan_cetak.php?jenis=' + encodeURIComponent(jenis);
    if (tglAwal)  url += '&tgl_awal=' + encodeURIComponent(tglAwal);
    if (tglAkhir) url += '&tgl_akhir=' + encodeURIComponent(tglAkhir);

    window.open(url, '_blank'); // buka tab baru untuk tampilan cetak
}

// Filter riwayat per fasilitas (search box)
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('searchFasilitas');
    const rows  = document.querySelectorAll('#tableRiwayatFasilitas tbody tr');

    if (input) {
        input.addEventListener('input', function () {
            const q = this.value.toLowerCase();
            rows.forEach(tr => {
                const fas = tr.getAttribute('data-fasilitas') || '';
                tr.style.display = (!q || fas.includes(q)) ? '' : 'none';
            });
        });
    }
});
</script>
<?php include '../includes/admin/footer.php'; ?>
