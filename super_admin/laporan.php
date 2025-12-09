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

/* ===================== NOTIFIKASI UNTUK NAVBAR ===================== */
$notifPeminjaman        = [];
$notifRusak             = [];
$jumlahNotifPeminjaman  = 0;
$jumlahNotifRusak       = 0;
$jumlahNotif            = 0;

// Peminjaman baru (status usulan)
$qNotifPeminjaman = mysqli_query($conn, "
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
    while ($row = mysqli_fetch_assoc($qNotifPeminjaman)) {
        $notifPeminjaman[] = $row;
    }
}
$jumlahNotifPeminjaman = count($notifPeminjaman);

// Pengembalian dengan kondisi rusak
$qNotifRusak = mysqli_query($conn, "
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
    while ($row = mysqli_fetch_assoc($qNotifRusak)) {
        $notifRusak[] = $row;
    }
}
$jumlahNotifRusak = count($notifRusak);

// Total untuk badge di icon bell
$jumlahNotif = $jumlahNotifPeminjaman + $jumlahNotifRusak;

// Variable untuk badge sidebar
$statUsulan = $jumlahNotifPeminjaman;

/* =========================================================
   FILTER TANGGAL (OPSIONAL)
   ========================================================= */
$tgl_awal  = $_GET['tgl_awal']  ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

$pattern = '/^\d{4}-\d{2}-\d{2}$/'; // YYYY-MM-DD

if ($tgl_awal !== '' && !preg_match($pattern, $tgl_awal)) {
    $tgl_awal = '';
}
if ($tgl_akhir !== '' && !preg_match($pattern, $tgl_akhir)) {
    $tgl_akhir = '';
}

/*
   Siapkan filter & parameter untuk:
   - peminjaman/pengembalian (alias tabel: p)
   - tindaklanjut (alias tabel: tl)
*/
$whereP   = "1=1";
$typesP   = '';
$paramsP  = [];

if ($tgl_awal !== '') {
    $whereP   .= " AND p.tanggal_mulai >= ?";
    $typesP   .= 's';
    $paramsP[] = $tgl_awal;
}
if ($tgl_akhir !== '') {
    $whereP   .= " AND p.tanggal_mulai <= ?";
    $typesP   .= 's';
    $paramsP[] = $tgl_akhir;
}

$whereTL  = "1=1";
$typesTL  = '';
$paramsTL = [];

if ($tgl_awal !== '') {
    $whereTL   .= " AND tl.tanggal >= ?";
    $typesTL   .= 's';
    $paramsTL[] = $tgl_awal . ' 00:00:00';
}
if ($tgl_akhir !== '') {
    $whereTL   .= " AND tl.tanggal <= ?";
    $typesTL   .= 's';
    $paramsTL[] = $tgl_akhir . ' 23:59:59';
}

/* =========================================================
   TEMPLATE
   ========================================================= */
$pageTitle   = 'Laporan';
$currentPage = 'laporan';

include '../includes/admin/header.php';
include '../includes/admin/sidebar.php';
?>

<style>
    .laporan-title {
        font-size: 1.4rem;
    }
    .laporan-subtitle {
        font-size: 0.9rem;
    }
    .card-laporan .card-header {
        font-weight: 600;
        font-size: 1rem;
    }
    .card-laporan table thead th {
        font-size: 0.85rem;
        text-transform: none;
        letter-spacing: .03em;
    }
    .card-laporan table tbody td {
        font-size: 0.9rem;
    }
    .badge-status {
        font-size: 0.8rem;
        padding: .4rem .7rem;
        font-weight: 600;
    }
    .filter-card {
        background: linear-gradient(135deg, #244c8d 0%, #0b2c61 100%);
        border: none;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    .filter-card .form-label {
        color: #0b2c61;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .btn-cetak {
        padding: 0.5rem 1rem;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.3s ease;
    }
    .btn-cetak:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
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
                    <h2 class="fw-bold text-info mb-1 laporan-title">
                        <i class="fas fa-chart-line me-2"></i>
                        Laporan Peminjaman & Pengembalian
                    </h2>
                    <p class="text-muted mb-0 laporan-subtitle">
                        Rekap data aktivitas peminjaman, pengembalian, riwayat per fasilitas, tindak lanjut kerusakan, dan komunikasi peminjam.
                    </p>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-info btn-cetak shadow-sm" onclick="cetakLaporan('semua')">
                        <i class="fas fa-file-pdf me-1"></i> Cetak Semua
                    </button>
                    <button type="button" class="btn btn-success btn-cetak shadow-sm" onclick="cetakLaporan('peminjaman')">
                        <i class="fas fa-file-pdf me-1"></i> Cetak Peminjaman
                    </button>
                    <button type="button" class="btn btn-warning btn-cetak shadow-sm" onclick="cetakLaporan('tindaklanjut')">
                        <i class="fas fa-file-pdf me-1"></i> Cetak Tindak Lanjut
                    </button>
                </div>
            </div>

            <hr class="mt-0 mb-4" style="border-top: 2px solid #0f172a; opacity: .25;">

            <!-- FILTER TANGGAL -->
            <div class="card filter-card shadow-lg mb-4">
                <div class="card-body py-3">
                    <form class="row gy-2 gx-3 align-items-end" method="get">
                        <div class="col-md-3 col-sm-6">
                            <label for="filter_tgl_awal" class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i> Tanggal Mulai
                            </label>
                            <input type="date"
                                   name="tgl_awal"
                                   id="filter_tgl_awal"
                                   value="<?= htmlspecialchars($tgl_awal); ?>"
                                   class="form-control">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label for="filter_tgl_akhir" class="form-label">
                                <i class="fas fa-calendar-check me-1"></i> Tanggal Akhir
                            </label>
                            <input type="date"
                                   name="tgl_akhir"
                                   id="filter_tgl_akhir"
                                   value="<?= htmlspecialchars($tgl_akhir); ?>"
                                   class="form-control">
                        </div>
                        <div class="col-md-4 col-sm-12">
                            <button type="submit" class="btn btn-light fw-bold">
                                <i class="fas fa-filter me-1"></i> Terapkan Filter
                            </button>
                            <a href="laporan.php" class="btn btn-outline-light">
                                <i class="fas fa-redo me-1"></i> Reset
                            </a>
                        </div>
                        <div class="col-md-2 col-sm-12 text-end">
                            <small class="text-white">
                                <i class="fas fa-info-circle me-1"></i>
                                Filter untuk semua tabel
                            </small>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ===================== -->
            <!-- 1. TABEL PEMINJAMAN & PENGEMBALIAN -->
            <!-- ===================== -->
            <div class="card shadow-sm border-0 mb-4 card-laporan">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-clipboard-list me-2"></i>
                        Rekapitulasi Peminjaman dan Pengembalian
                    </span>
                    <span class="badge bg-light text-dark">
                        <?php
                        // COUNT peminjaman (prepared)
                        $totalPeminjaman = 0;
                        $sqlCount1 = "SELECT COUNT(*) AS total FROM peminjaman p WHERE {$whereP}";
                        if ($stmtCount1 = $conn->prepare($sqlCount1)) {
                            if ($typesP !== '') {
                                $stmtCount1->bind_param($typesP, ...$paramsP);
                            }
                            $stmtCount1->execute();
                            $resCount1 = $stmtCount1->get_result();
                            if ($rowCount1 = $resCount1->fetch_assoc()) {
                                $totalPeminjaman = (int)$rowCount1['total'];
                            }
                            $stmtCount1->close();
                        }
                        echo $totalPeminjaman . " data";
                        ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="table1" class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Nama Peminjam</th>
                                    <th style="width: 130px;">Tanggal Pinjam</th>
                                    <th style="width: 130px;">Tanggal Kembali</th>
                                    <th style="width: 130px;">Status Peminjaman</th>
                                    <th style="width: 130px;">Kondisi Saat Kembali</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no   = 1;
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

                                if ($stmtP = $conn->prepare($sqlP)) {
                                    if ($typesP !== '') {
                                        $stmtP->bind_param($typesP, ...$paramsP);
                                    }
                                    $stmtP->execute();
                                    $result = $stmtP->get_result();

                                    if ($result && $result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            // Badge status peminjaman
                                            $statusVal = strtolower($row['status']);
                                            if ($statusVal === 'diterima' || $statusVal === 'disetujui') {
                                                $statusBadge = "<span class='badge bg-success badge-status'>Diterima</span>";
                                            } elseif ($statusVal === 'ditolak') {
                                                $statusBadge = "<span class='badge bg-danger badge-status'>Ditolak</span>";
                                            } elseif ($statusVal === 'selesai') {
                                                $statusBadge = "<span class='badge bg-primary badge-status'>Selesai</span>";
                                            } else {
                                                $statusBadge = "<span class='badge bg-warning badge-status'>Usulan</span>";
                                            }

                                            // Kondisi pengembalian
                                            $kondisiRaw = $row['kondisi'];
                                            if ($kondisiRaw === null || $kondisiRaw === '') {
                                                $kondisiLabel = '<span class="text-muted">-</span>';
                                            } else {
                                                $kondisiLower = strtolower($kondisiRaw);
                                                if ($kondisiLower === 'bagus' || $kondisiLower === 'baik') {
                                                    $kondisiLabel = "<span class='badge bg-success badge-status'>Bagus</span>";
                                                } elseif ($kondisiLower === 'rusak') {
                                                    $kondisiLabel = "<span class='badge bg-danger badge-status'>Rusak</span>";
                                                } else {
                                                    $kondisiLabel = htmlspecialchars(ucfirst($kondisiRaw));
                                                }
                                            }

                                            $tglPinjam  = $row['tanggal_mulai'] ? date('d-m-Y', strtotime($row['tanggal_mulai'])) : '-';
                                            $tglKembali = $row['tgl_kembali']   ? date('d-m-Y', strtotime($row['tgl_kembali']))   : '-';

                                            echo "
                                            <tr>
                                                <td class='text-center'>{$no}</td>
                                                <td><strong>" . htmlspecialchars($row['nama']) . "</strong></td>
                                                <td class='text-center'>{$tglPinjam}</td>
                                                <td class='text-center'>{$tglKembali}</td>
                                                <td class='text-center'>{$statusBadge}</td>
                                                <td class='text-center'>{$kondisiLabel}</td>
                                            </tr>";
                                            $no++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='6' class='text-center text-muted py-4'>
                                            <i class='fas fa-inbox fa-3x mb-3 d-block opacity-25'></i>
                                            Tidak ada data laporan.
                                        </td></tr>";
                                    }

                                    $stmtP->close();
                                } else {
                                    echo "<tr><td colspan='6' class='text-center text-muted py-4'>
                                        Gagal mengambil data laporan.
                                    </td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ===================== -->
            <!-- 2. RIWAYAT PEMINJAMAN PER FASILITAS -->
            <!-- ===================== -->
            <div class="card shadow-sm border-0 mb-4 card-laporan">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-history me-2"></i>
                        Riwayat Peminjaman per Fasilitas
                    </span>
                    <small class="badge bg-light text-dark">
                        Lihat frekuensi peminjaman per fasilitas
                    </small>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4 col-sm-6">
                            <label for="searchFasilitas" class="form-label fw-semibold">
                                <i class="fas fa-search me-1"></i> Cari Fasilitas
                            </label>
                            <input type="text" 
                                   id="searchFasilitas" 
                                   class="form-control" 
                                   placeholder="Ketik nama fasilitas (mis. Miniconfer)...">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0" id="tableRiwayatFasilitas">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th>Nama Fasilitas</th>
                                    <th>Nama Peminjam</th>
                                    <th style="width: 90px;">ID Pinjam</th>
                                    <th style="width: 120px;">Tanggal Pinjam</th>
                                    <th style="width: 120px;">Tanggal Kembali</th>
                                    <th style="width: 130px;">Status Peminjaman</th>
                                    <th style="width: 100px;">Kondisi</th>
                                    <th style="width: 120px;">Total Dipinjam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;

                                // Build query riwayat + prepared statement (termasuk subquery count)
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
                                ";
                                $typesHist  = '';
                                $paramsHist = [];

                                // Filter tanggal untuk subquery p2
                                if ($tgl_awal !== '') {
                                    $sqlHist    .= " AND p2.tanggal_mulai >= ?";
                                    $typesHist  .= 's';
                                    $paramsHist[] = $tgl_awal;
                                }
                                if ($tgl_akhir !== '') {
                                    $sqlHist    .= " AND p2.tanggal_mulai <= ?";
                                    $typesHist  .= 's';
                                    $paramsHist[] = $tgl_akhir;
                                }

                                $sqlHist .= "
                                        ) AS total_peminjaman_fasilitas
                                    FROM peminjaman p
                                    JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
                                    JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
                                    JOIN users u ON p.id_user = u.id_user
                                    LEFT JOIN pengembalian pg ON p.id_pinjam = pg.id_pinjam
                                    WHERE 1=1
                                ";

                                // Filter tanggal untuk peminjaman utama p
                                if ($tgl_awal !== '') {
                                    $sqlHist    .= " AND p.tanggal_mulai >= ?";
                                    $typesHist  .= 's';
                                    $paramsHist[] = $tgl_awal;
                                }
                                if ($tgl_akhir !== '') {
                                    $sqlHist    .= " AND p.tanggal_mulai <= ?";
                                    $typesHist  .= 's';
                                    $paramsHist[] = $tgl_akhir;
                                }

                                $sqlHist .= " ORDER BY f.nama_fasilitas ASC, p.tanggal_mulai DESC";

                                if ($stmtHist = $conn->prepare($sqlHist)) {
                                    if ($typesHist !== '') {
                                        $stmtHist->bind_param($typesHist, ...$paramsHist);
                                    }
                                    $stmtHist->execute();
                                    $queryHist = $stmtHist->get_result();

                                    if ($queryHist && $queryHist->num_rows > 0) {
                                        while ($h = $queryHist->fetch_assoc()) {
                                            $statusVal = strtolower($h['status']);
                                            if      ($statusVal === 'diterima' || $statusVal === 'disetujui') $statusText = "<span class='badge bg-success badge-status'>Diterima</span>";
                                            elseif  ($statusVal === 'ditolak')                                  $statusText = "<span class='badge bg-danger badge-status'>Ditolak</span>";
                                            elseif  ($statusVal === 'selesai')                                  $statusText = "<span class='badge bg-primary badge-status'>Selesai</span>";
                                            else                                                                $statusText = "<span class='badge bg-warning badge-status'>Usulan</span>";

                                            $tglPinjam  = $h['tanggal_mulai'] ? date('d-m-Y', strtotime($h['tanggal_mulai'])) : '-';
                                            $tglKembali = $h['tgl_kembali']   ? date('d-m-Y', strtotime($h['tgl_kembali']))   : '-';

                                            $kondisiRaw = $h['kondisi'];
                                            if ($kondisiRaw === null || $kondisiRaw === '') {
                                                $kondisiText = '<span class="text-muted">-</span>';
                                            } else {
                                                $kondisiLower = strtolower($kondisiRaw);
                                                if ($kondisiLower === 'bagus' || $kondisiLower === 'baik') {
                                                    $kondisiText = "<span class='badge bg-success badge-status'>Bagus</span>";
                                                } elseif ($kondisiLower === 'rusak') {
                                                    $kondisiText = "<span class='badge bg-danger badge-status'>Rusak</span>";
                                                } else {
                                                    $kondisiText = htmlspecialchars(ucfirst($kondisiRaw));
                                                }
                                            }

                                            $totalFas = (int)($h['total_peminjaman_fasilitas'] ?? 0);
                                            $dataAttr = htmlspecialchars(strtolower($h['nama_fasilitas']), ENT_QUOTES);

                                            echo "
                                            <tr data-fasilitas='{$dataAttr}'>
                                                <td class='text-center'>{$no}</td>
                                                <td><strong>" . htmlspecialchars($h['nama_fasilitas']) . "</strong></td>
                                                <td>" . htmlspecialchars($h['nama_peminjam']) . "</td>
                                                <td class='text-center'><span class='badge bg-info'>#{$h['id_pinjam']}</span></td>
                                                <td class='text-center'>{$tglPinjam}</td>
                                                <td class='text-center'>{$tglKembali}</td>
                                                <td class='text-center'>{$statusText}</td>
                                                <td class='text-center'>{$kondisiText}</td>
                                                <td class='text-center'><span class='badge bg-primary'>{$totalFas}x</span></td>
                                            </tr>";
                                            $no++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='9' class='text-center text-muted py-4'>
                                            <i class='fas fa-inbox fa-3x mb-3 d-block opacity-25'></i>
                                            Tidak ada riwayat peminjaman fasilitas dalam periode ini.
                                        </td></tr>";
                                    }

                                    $stmtHist->close();
                                } else {
                                    echo "<tr><td colspan='9' class='text-center text-muted py-4'>
                                        Gagal mengambil riwayat fasilitas.
                                    </td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ===================== -->
            <!-- 3. TABEL TINDAK LANJUT & KOMUNIKASI KERUSAKAN -->
            <!-- ===================== -->
            <div class="card shadow-sm border-0 mb-4 card-laporan">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-tools me-2"></i>
                        Rekap Tindak Lanjut Kerusakan & Komunikasi Peminjam
                    </span>
                    <span class="badge bg-dark">
                        <?php
                        $totalTL  = 0;
                        $sqlCount3 = "SELECT COUNT(*) AS total FROM tindaklanjut tl WHERE {$whereTL}";
                        if ($stmtCount3 = $conn->prepare($sqlCount3)) {
                            if ($typesTL !== '') {
                                $stmtCount3->bind_param($typesTL, ...$paramsTL);
                            }
                            $stmtCount3->execute();
                            $resCount3 = $stmtCount3->get_result();
                            if ($rowCount3 = $resCount3->fetch_assoc()) {
                                $totalTL = (int)$rowCount3['total'];
                            }
                            $stmtCount3->close();
                        }
                        echo $totalTL . " tindak lanjut";
                        ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Informasi:</strong> Bagian ini merangkum setiap tindak lanjut kerusakan yang dilakukan oleh Bagian Umum/Super Admin,
                        beserta jumlah percakapan (chat) antara peminjam dengan admin terkait tindak lanjut tersebut.
                    </div>

                    <div class="table-responsive">
                        <table id="table3" class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 90px;">ID TL</th>
                                    <th style="width: 100px;">ID Peminjaman</th>
                                    <th>Nama Peminjam</th>
                                    <th>Tindakan</th>
                                    <th style="width: 120px;">Status TL</th>
                                    <th style="width: 100px;">Jumlah Chat</th>
                                    <th style="width: 150px;">Tanggal TL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $sqlTL = "
                                    SELECT 
                                        tl.id_tindaklanjut,
                                        tl.tanggal,
                                        tl.tindakan,
                                        tl.status AS status_tl,
                                        pg.id_pinjam,
                                        u.nama AS nama_peminjam,
                                        COUNT(DISTINCT ck.id_chat) AS total_chat
                                    FROM tindaklanjut tl
                                    JOIN pengembalian pg ON tl.id_kembali = pg.id_kembali
                                    JOIN peminjaman p    ON pg.id_pinjam = p.id_pinjam
                                    JOIN users u         ON p.id_user = u.id_user
                                    LEFT JOIN komunikasi_kerusakan ck
                                           ON ck.id_tindaklanjut = tl.id_tindaklanjut
                                    WHERE {$whereTL}
                                    GROUP BY tl.id_tindaklanjut
                                    ORDER BY tl.tanggal DESC
                                ";

                                if ($stmtTL = $conn->prepare($sqlTL)) {
                                    if ($typesTL !== '') {
                                        $stmtTL->bind_param($typesTL, ...$paramsTL);
                                    }
                                    $stmtTL->execute();
                                    $resultTL = $stmtTL->get_result();

                                    if ($resultTL && $resultTL->num_rows > 0) {
                                        while ($r = $resultTL->fetch_assoc()) {
                                            $statusTL   = strtolower($r['status_tl']);
                                            $badgeClass = 'secondary';
                                            if ($statusTL === 'proses')  $badgeClass = 'warning';
                                            if ($statusTL === 'selesai') $badgeClass = 'success';

                                            $tglTL = $r['tanggal'] ? date('d-m-Y H:i', strtotime($r['tanggal'])) : '-';

                                            echo "
                                            <tr>
                                                <td class='text-center'>{$no}</td>
                                                <td class='text-center'><span class='badge bg-secondary'>#{$r['id_tindaklanjut']}</span></td>
                                                <td class='text-center'><span class='badge bg-info'>#{$r['id_pinjam']}</span></td>
                                                <td><strong>" . htmlspecialchars($r['nama_peminjam']) . "</strong></td>
                                                <td>" . htmlspecialchars($r['tindakan']) . "</td>
                                                <td class='text-center'>
                                                    <span class='badge bg-{$badgeClass} badge-status'>" . ucfirst(htmlspecialchars($r['status_tl'])) . "</span>
                                                </td>
                                                <td class='text-center'>
                                                    <span class='badge bg-primary'>
                                                        <i class='fas fa-comments me-1'></i>" . (int)$r['total_chat'] . "
                                                    </span>
                                                </td>
                                                <td class='text-center'>{$tglTL}</td>
                                            </tr>";
                                            $no++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='8' class='text-center text-muted py-4'>
                                            <i class='fas fa-inbox fa-3x mb-3 d-block opacity-25'></i>
                                            Belum ada data tindak lanjut.
                                        </td></tr>";
                                    }

                                    $stmtTL->close();
                                } else {
                                    echo "<tr><td colspan='8' class='text-center text-muted py-4'>
                                        Gagal mengambil data tindak lanjut.
                                    </td></tr>";
                                }
                                ?>
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

<?php include '../includes/admin/footer.php'; ?>

<!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
// DataTables Init
$(document).ready(function() {
    $('#table1, #table3').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json'
        },
        pageLength: 10,
        order: [[0, 'asc']]
    });
});

// Kirim ke laporan_cetak.php dengan jenis + filter tanggal
function cetakLaporan(jenis) {
    const tglAwal  = document.getElementById('filter_tgl_awal').value;
    const tglAkhir = document.getElementById('filter_tgl_akhir').value;

    let url = 'laporan_cetak.php?jenis=' + encodeURIComponent(jenis);
    if (tglAwal)  url += '&tgl_awal=' + encodeURIComponent(tglAwal);
    if (tglAkhir) url += '&tgl_akhir=' + encodeURIComponent(tglAkhir);

    window.open(url, '_blank');
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
