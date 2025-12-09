<?php
include '../config/koneksi.php';
include '../config/notifikasi_helper.php';


/* =========================================================
   PARAMETER JENIS LAPORAN
   ========================================================= */
$jenis = $_GET['jenis'] ?? 'semua';
$allowedJenis = ['semua','peminjaman','riwayat','tindaklanjut'];
if (!in_array($jenis, $allowedJenis, true)) {
    $jenis = 'semua';
}

/* =========================================================
   FILTER TANGGAL (OPSIONAL)
   ========================================================= */
$tgl_awal  = $_GET['tgl_awal']  ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

$pattern = '/^\d{4}-\d{2}-\d{2}$/';
if ($tgl_awal  !== '' && !preg_match($pattern, $tgl_awal))  $tgl_awal  = '';
if ($tgl_akhir !== '' && !preg_match($pattern, $tgl_akhir)) $tgl_akhir = '';

// escape supaya aman
$tgl_awal_sql  = $tgl_awal  !== '' ? mysqli_real_escape_string($conn, $tgl_awal)  : '';
$tgl_akhir_sql = $tgl_akhir !== '' ? mysqli_real_escape_string($conn, $tgl_akhir) : '';

/* =========================================================
   WHERE CLAUSE BERDASARKAN TANGGAL
   ========================================================= */
// WHERE untuk peminjaman & pengembalian (pakai p.tanggal_mulai)
$whereP = "1=1";
if ($tgl_awal_sql !== '') {
    $whereP .= " AND p.tanggal_mulai >= '{$tgl_awal_sql}'";
}
if ($tgl_akhir_sql !== '') {
    $whereP .= " AND p.tanggal_mulai <= '{$tgl_akhir_sql}'";
}

// WHERE untuk tindak lanjut (pakai tl.tanggal)
$whereTL = "1=1";
if ($tgl_awal_sql !== '') {
    $whereTL .= " AND tl.tanggal >= '{$tgl_awal_sql} 00:00:00'";
}
if ($tgl_akhir_sql !== '') {
    $whereTL .= " AND tl.tanggal <= '{$tgl_akhir_sql} 23:59:59'";
}

// Filter tanggal untuk hitung total peminjaman fasilitas
$filterRangeForCount = "";
if ($tgl_awal_sql !== '') {
    $filterRangeForCount .= " AND p2.tanggal_mulai >= '{$tgl_awal_sql}'";
}
if ($tgl_akhir_sql !== '') {
    $filterRangeForCount .= " AND p2.tanggal_mulai <= '{$tgl_akhir_sql}'";
}

/* =========================================================
   QUERY PEMINJAMAN & PENGEMBALIAN (REKAP A)
   ========================================================= */
$qPeminjaman = $conn->query("
    SELECT 
        u.nama, 
        p.id_pinjam,
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
");

/* =========================================================
   QUERY RIWAYAT PER FASILITAS (REKAP B)
   ========================================================= */
$qHist = $conn->query("
    SELECT
        f.nama_fasilitas,
        u.nama AS nama_peminjam,
        p.id_pinjam,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.status,
        pg.tgl_kembali,
        pg.kondisi,
        (
            SELECT COUNT(*)
            FROM peminjaman p2
            JOIN daftar_peminjaman_fasilitas df2 ON p2.id_pinjam = df2.id_pinjam
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
");

/* =========================================================
   QUERY TINDAK LANJUT & KOMUNIKASI (REKAP C)
   ========================================================= */
$qTL = $conn->query("
    SELECT 
        tl.id_tindaklanjut,
        tl.tanggal,
        tl.tindakan,
        tl.status AS status_tl,
        pg.id_pinjam,
        u.nama AS nama_peminjam,
        COUNT(DISTINCT ck.id_chat) AS total_chat
    FROM tindaklanjut tl
    JOIN pengembalian pg       ON tl.id_kembali = pg.id_kembali
    JOIN peminjaman p          ON pg.id_pinjam = p.id_pinjam
    JOIN users u               ON p.id_user = u.id_user
    LEFT JOIN komunikasi_kerusakan ck 
           ON tl.id_tindaklanjut = ck.id_tindaklanjut
    WHERE {$whereTL}
    GROUP BY tl.id_tindaklanjut
    ORDER BY tl.tanggal DESC
");

// Judul kecil sesuai jenis
if ($jenis == 'peminjaman') {
    $subTitle = 'Laporan Peminjaman & Pengembalian Fasilitas';
} elseif ($jenis == 'riwayat') {
    $subTitle = 'Laporan Riwayat Peminjaman per Fasilitas';
} elseif ($jenis == 'tindaklanjut') {
    $subTitle = 'Laporan Tindak Lanjut Kerusakan & Komunikasi Peminjam';
} else {
    $subTitle = 'Laporan Peminjaman, Riwayat per Fasilitas, dan Tindak Lanjut Kerusakan & Komunikasi';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cetak Laporan - E-Fasilitas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #0b2c61;  /* biru utama */
            --accent-color:  #ffb703;  /* kuning aksen */
            --text-main:     #1f2933;
            --text-muted:    #6b7280;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 11.5px;
            background: #ffffff;
            color: var(--text-main);
            line-height: 1.4;
        }

        .container-fluid {
            max-width: 100%;
            padding: 10px 18px 30px;
        }

        .laporan-wrapper {
            background: #ffffff;
            padding: 5px 5px 10px;
            margin-bottom: 10px;
        }

        /* === KOP + LOGO === */
        .kop-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 4px;
        }

        .kop-logo-wrap {
            flex: 0 0 auto;
        }

        .kop-logo {
            height: 60px;
            width: auto;
            object-fit: contain;
        }

        .judul-laporan {
            flex: 1;
            text-align: center;
        }

        .judul-laporan h3 {
            margin-bottom: 2px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--primary-color);
            font-size: 17px;
        }
        .judul-laporan small {
            color: var(--text-muted);
            display: block;
        }
        .judul-laporan .periode-text {
            font-weight: 600;
            color: var(--primary-color);
            margin-top: 2px;
        }

        .laporan-divider {
            border-top: 2px solid var(--primary-color);
            opacity: 0.7;
            margin: 8px 0 10px 0;
        }

        .section-title {
            font-weight: 700;
            font-size: 12px;
            color: var(--primary-color);
            margin: 10px 0 6px;
            padding-left: 8px;
            border-left: 3px solid var(--accent-color);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .table-laporan {
            margin-bottom: 6px;
        }

        .table-laporan thead {
            background: linear-gradient(115deg, #0b2c61, #1b4f9c);
            color: #fff;
        }

        .table-laporan thead th {
            vertical-align: middle;
            border-color: rgba(255,255,255,0.3);
            font-size: 10px;
            padding: 6px 4px;
            white-space: nowrap;
        }

        .table-laporan tbody tr:nth-child(even) {
            background-color: #f9fafc;
        }

        .table-laporan tbody td {
            padding: 5px 4px;
            vertical-align: middle;
            border-color: #d1d5db;
            font-size: 10px;
        }

        .table-laporan tbody td.text-center {
            text-align: center;
        }

        .laporan-badge {
            font-size: 0.68rem;
            padding: 0.16rem 0.5rem;
            border-radius: 999px;
            font-weight: 600;
        }

        .no-print-controls {
            background: #ffffff;
            padding: 8px 10px;
            margin-bottom: 8px;
            border-bottom: 1px solid #e5e7eb;
        }

        .no-print-controls label {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .no-print-controls .form-control,
        .no-print-controls .form-select {
            font-size: 10px;
            padding: 3px 6px;
            border-radius: 6px;
        }

        .no-print-controls .btn-sm {
            font-size: 10px;
            border-radius: 999px;
            padding: 3px 10px;
        }

        .signature-row {
            margin-top: 20px;
            font-size: 10px;
        }

        .signature-name-placeholder {
            margin-top: 40px;
            display: inline-block;
            border-top: 1px solid #4b5563;
            padding-top: 2px;
            min-width: 200px;
        }

        @media print {
            .no-print,
            .no-print-controls {
                display: none !important;
            }
            .container-fluid {
                padding: 0;
            }
            .laporan-wrapper {
                padding: 0;
                margin: 0;
            }
            a[href]:after {
                content: "";
            }
        }
    </style>
</head>
<body>

<div class="container-fluid mt-1">

    <!-- FORM FILTER (HANYA TAMPIL SAAT TIDAK PRINT) -->
    <div class="no-print no-print-controls">
        <form class="row gy-2 gx-2 align-items-end" method="get">
            <div class="col-md-3 col-sm-6">
                <label class="form-label mb-1">Jenis Laporan</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="semua"       <?= $jenis=='semua' ? 'selected' : '' ?>>Semua</option>
                    <option value="peminjaman"  <?= $jenis=='peminjaman' ? 'selected' : '' ?>>Peminjaman & Pengembalian</option>
                    <option value="riwayat"     <?= $jenis=='riwayat' ? 'selected' : '' ?>>Riwayat per Fasilitas</option>
                    <option value="tindaklanjut"<?= $jenis=='tindaklanjut' ? 'selected' : '' ?>>Tindak Lanjut</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label mb-1">Tanggal Mulai</label>
                <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tgl_awal); ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label mb-1">Tanggal Akhir</label>
                <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tgl_akhir); ?>" class="form-control form-control-sm">
            </div>
            <div class="col-md-3 col-sm-6 d-flex align-items-end">
                <div>
                    <button type="submit" class="btn btn-sm btn-secondary me-1">
                        Terapkan Filter
                    </button>
                    <button type="button" onclick="window.location='laporan_cetak.php'" class="btn btn-sm btn-outline-secondary">
                        Reset
                    </button>
                </div>
            </div>
            <div class="col-md-2 col-sm-12 text-md-end text-start mt-2 mt-md-0">
                <button onclick="window.print()" type="button" class="btn btn-sm btn-primary">
                    Cetak Sekarang
                </button>
            </div>
        </form>
    </div>

    <div class="laporan-wrapper">

        <!-- KOP + LOGO -->
        <div class="kop-row">
            <div class="kop-logo-wrap">
                <!-- Bisa taruh logo kampus di sini kalau mau -->
            </div>
            <div class="judul-laporan">
                <h3>LAPORAN E-FASILITAS KAMPUS</h3>
                <small><?= htmlspecialchars($subTitle); ?></small>
                <?php if ($tgl_awal || $tgl_akhir): ?>
                    <small class="periode-text">
                        Periode: 
                        <?= $tgl_awal  ? date('d-m-Y', strtotime($tgl_awal))  : '...' ?> 
                        s/d 
                        <?= $tgl_akhir ? date('d-m-Y', strtotime($tgl_akhir)) : '...' ?>
                    </small>
                <?php endif; ?>
                <small>Dicetak pada: <?= date('d-m-Y H:i'); ?></small>
            </div>
        </div>

        <hr class="laporan-divider">

        <?php if ($jenis == 'semua' || $jenis == 'peminjaman'): ?>
            <!-- A. PEMINJAMAN & PENGEMBALIAN -->
            <div class="section-title">
                <?= ($jenis == 'semua') ? 'A. ' : 'A. '; ?>Rekap Peminjaman & Pengembalian
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle table-laporan">
                    <thead class="text-center">
                        <tr>
                            <th>No</th>
                            <th>Nama Peminjam</th>
                            <th>ID Pinjam</th>
                            <th>Tanggal Pinjam</th>
                            <th>Tanggal Selesai</th>
                            <th>Tanggal Kembali</th>
                            <th>Status Peminjaman</th>
                            <th>Kondisi Saat Kembali</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if ($qPeminjaman && $qPeminjaman->num_rows > 0) {
                            while ($row = $qPeminjaman->fetch_assoc()) {
                                $status = strtolower($row['status']);
                                if     ($status == 'diterima' || $status == 'disetujui') { $statusText = 'Diterima'; $badgeStatus = 'success'; }
                                elseif ($status == 'ditolak')                             { $statusText = 'Ditolak';  $badgeStatus = 'danger';  }
                                elseif ($status == 'selesai')                             { $statusText = 'Selesai';  $badgeStatus = 'primary'; }
                                else                                                      { $statusText = 'Menunggu'; $badgeStatus = 'secondary'; }

                                $kondisiRaw = strtolower($row['kondisi'] ?? '');
                                if     ($kondisiRaw == 'bagus' || $kondisiRaw == 'baik' || $kondisiRaw == 'normal') { $badgeKondisi = 'success'; }
                                elseif ($kondisiRaw == 'rusak')                                                            { $badgeKondisi = 'danger';  }
                                elseif ($kondisiRaw == 'perlu perbaikan')                                                  { $badgeKondisi = 'warning'; }
                                else                                                                                        { $badgeKondisi = 'secondary'; }

                                $kondisi    = $row['kondisi'] ? ucfirst($row['kondisi']) : '-';
                                $tglPinjam  = $row['tanggal_mulai']   ? date('d-m-Y', strtotime($row['tanggal_mulai']))   : '-';
                                $tglSelesai = $row['tanggal_selesai'] ? date('d-m-Y', strtotime($row['tanggal_selesai'])) : '-';
                                $tglKembali = $row['tgl_kembali']     ? date('d-m-Y', strtotime($row['tgl_kembali']))     : '-';

                                echo "
                                <tr>
                                    <td class='text-center'>{$no}</td>
                                    <td>".htmlspecialchars($row['nama'])."</td>
                                    <td class='text-center'>{$row['id_pinjam']}</td>
                                    <td class='text-center'>{$tglPinjam}</td>
                                    <td class='text-center'>{$tglSelesai}</td>
                                    <td class='text-center'>{$tglKembali}</td>
                                    <td class='text-center'>
                                        <span class=\"badge bg-{$badgeStatus} laporan-badge\">{$statusText}</span>
                                    </td>
                                    <td class='text-center'>
                                        <span class=\"badge bg-{$badgeKondisi} laporan-badge\">{$kondisi}</span>
                                    </td>
                                </tr>";
                                $no++;
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center text-muted py-2'>Tidak ada data.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($jenis == 'semua' || $jenis == 'riwayat'): ?>
            <!-- B. RIWAYAT PER FASILITAS -->
            <div class="section-title mt-2">
                <?= ($jenis == 'semua') ? 'B. ' : 'A. '; ?>Riwayat Peminjaman per Fasilitas
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle table-laporan">
                    <thead class="text-center">
                        <tr>
                            <th>No</th>
                            <th>Nama Fasilitas</th>
                            <th>Nama Peminjam</th>
                            <th>ID Pinjam</th>
                            <th>Tanggal Pinjam</th>
                            <th>Tanggal Selesai</th>
                            <th>Tanggal Kembali</th>
                            <th>Status Peminjaman</th>
                            <th>Kondisi</th>
                            <th>Total Peminjaman Fasilitas<br>(periode ini)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if ($qHist && $qHist->num_rows > 0) {
                            while ($h = $qHist->fetch_assoc()) {
                                $status = strtolower($h['status']);
                                if     ($status == 'diterima' || $status == 'disetujui') { $statusText = 'Diterima'; $badgeStatus = 'success'; }
                                elseif ($status == 'ditolak')                             { $statusText = 'Ditolak';  $badgeStatus = 'danger';  }
                                elseif ($status == 'selesai')                             { $statusText = 'Selesai';  $badgeStatus = 'primary'; }
                                else                                                      { $statusText = 'Menunggu'; $badgeStatus = 'secondary'; }

                                $kondisiRaw = strtolower($h['kondisi'] ?? '');
                                if     ($kondisiRaw == 'bagus' || $kondisiRaw == 'baik' || $kondisiRaw == 'normal') { $badgeKondisi = 'success'; }
                                elseif ($kondisiRaw == 'rusak')                                                            { $badgeKondisi = 'danger';  }
                                elseif ($kondisiRaw == 'perlu perbaikan')                                                  { $badgeKondisi = 'warning'; }
                                else                                                                                        { $badgeKondisi = 'secondary'; }

                                $tglPinjam  = $h['tanggal_mulai']   ? date('d-m-Y', strtotime($h['tanggal_mulai']))   : '-';
                                $tglSelesai = $h['tanggal_selesai'] ? date('d-m-Y', strtotime($h['tanggal_selesai'])) : '-';
                                $tglKembali = $h['tgl_kembali']     ? date('d-m-Y', strtotime($h['tgl_kembali']))     : '-';
                                $kondisi    = $h['kondisi']         ? ucfirst($h['kondisi'])                         : '-';
                                $totalFas   = (int)($h['total_peminjaman_fasilitas'] ?? 0);

                                echo "
                                <tr>
                                    <td class='text-center'>{$no}</td>
                                    <td>".htmlspecialchars($h['nama_fasilitas'])."</td>
                                    <td>".htmlspecialchars($h['nama_peminjam'])."</td>
                                    <td class='text-center'>{$h['id_pinjam']}</td>
                                    <td class='text-center'>{$tglPinjam}</td>
                                    <td class='text-center'>{$tglSelesai}</td>
                                    <td class='text-center'>{$tglKembali}</td>
                                    <td class='text-center'>
                                        <span class=\"badge bg-{$badgeStatus} laporan-badge\">{$statusText}</span>
                                    </td>
                                    <td class='text-center'>
                                        <span class=\"badge bg-{$badgeKondisi} laporan-badge\">{$kondisi}</span>
                                    </td>
                                    <td class='text-center'>{$totalFas}</td>
                                </tr>";
                                $no++;
                            }
                        } else {
                            echo "<tr><td colspan='10' class='text-center text-muted py-2'>Tidak ada riwayat peminjaman fasilitas dalam periode ini.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($jenis == 'semua' || $jenis == 'tindaklanjut'): ?>
            <!-- C. TINDAK LANJUT -->
            <div class="section-title mt-2">
                <?= ($jenis == 'semua') ? 'C. ' : 'A. '; ?>Rekap Tindak Lanjut Kerusakan & Komunikasi Peminjam
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle table-laporan">
                    <thead class="text-center">
                        <tr>
                            <th>No</th>
                            <th>ID Tindak Lanjut</th>
                            <th>ID Peminjaman</th>
                            <th>Nama Peminjam</th>
                            <th>Tindakan</th>
                            <th>Status Tindak Lanjut</th>
                            <th>Jumlah Chat</th>
                            <th>Tanggal Tindak Lanjut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        if ($qTL && $qTL->num_rows > 0) {
                            while ($r = $qTL->fetch_assoc()) {

                                $statusTLRaw = strtolower($r['status_tl'] ?? '');
                                if     ($statusTLRaw == 'selesai')        { $badgeTL = 'success'; }
                                elseif ($statusTLRaw == 'proses')         { $badgeTL = 'warning'; }
                                elseif ($statusTLRaw == 'belum ditindak') { $badgeTL = 'secondary'; }
                                else                                      { $badgeTL = 'secondary'; }

                                $statusTL = $r['status_tl'] ?: '-';
                                $tglTL = $r['tanggal'] ? date('d-m-Y H:i', strtotime($r['tanggal'])) : '-';
                                $totalChat = (int)($r['total_chat'] ?? 0);

                                echo "
                                <tr>
                                    <td class='text-center'>{$no}</td>
                                    <td class='text-center'>{$r['id_tindaklanjut']}</td>
                                    <td class='text-center'>{$r['id_pinjam']}</td>
                                    <td>".htmlspecialchars($r['nama_peminjam'])."</td>
                                    <td>".htmlspecialchars($r['tindakan'])."</td>
                                    <td class='text-center'>
                                        <span class=\"badge bg-{$badgeTL} laporan-badge\">".htmlspecialchars($statusTL)."</span>
                                    </td>
                                    <td class='text-center'>{$totalChat}</td>
                                    <td class='text-center'>{$tglTL}</td>
                                </tr>";
                                $no++;
                            }
                        } else {
                            echo "<tr><td colspan='8' class='text-center text-muted py-2'>Tidak ada data tindak lanjut dalam periode ini.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="row signature-row">
            <div class="col-6 text-center">
                <!-- bisa diisi pejabat lain -->
            </div>
            <div class="col-6 text-center">
                <p>Bengkalis, <?= date('d-m-Y'); ?></p>
                <p>Mengetahui,<br>Bagian Umum / Admin</p>
                <div class="signature-name-placeholder">
                    (........................................)
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
