<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

$id_user   = (int) $_SESSION['id_user'];
$nama_user = $_SESSION['nama_user'] ?? 'Peminjam';

/* =========================================================
   1) AMBIL RIWAYAT NOTIFIKASI (10 terbaru)
   ========================================================= */
$notifikasiList = [];
$stmt = $conn->prepare("
    SELECT id_notifikasi, id_pinjam, judul, pesan, tipe, is_read, created_at
    FROM notifikasi
    WHERE id_user = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $id_user);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $notifikasiList[] = $row;
}

/* =========================================================
   2) NOTIFIKASI BELUM DIBACA (untuk popup)
   ========================================================= */
$popupNotifs = [];
$stmt2 = $conn->prepare("
    SELECT id_notifikasi, id_pinjam, judul, pesan, tipe, created_at
    FROM notifikasi
    WHERE id_user = ?
      AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt2->bind_param("i", $id_user);
$stmt2->execute();
$res2 = $stmt2->get_result();

while ($row = $res2->fetch_assoc()) {
    // tentukan link berdasarkan tipe
    $linkTujuan = '#';
    if ($row['tipe'] === 'peminjaman') {
        $linkTujuan = 'peminjaman_saya.php#pinjam' . $row['id_pinjam'];
    } elseif ($row['tipe'] === 'pengembalian') {
        $linkTujuan = 'riwayat.php#kembali' . $row['id_pinjam'];
    }

    $popupNotifs[] = [
        'judul' => $row['judul'],
        'pesan' => $row['pesan'],
        'tipe'  => strtolower($row['tipe']),
        'link'  => $linkTujuan,
        'waktu' => date('d-m-Y H:i', strtotime($row['created_at']))
    ];
}

/* Hitung jumlah popup notif */
$jumlahNotif = count($popupNotifs);

/* Setelah popup selesai diambil → tandai sebagai dibaca */
if ($jumlahNotif > 0) {
    $upd = $conn->prepare("UPDATE notifikasi SET is_read = 1 WHERE id_user = ? AND is_read = 0");
    $upd->bind_param("i", $id_user);
    $upd->execute();
}

/* =========================================================
   MEMUAT TEMPLATE HEADER & NAVBAR
   ========================================================= */
include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>

<!-- Tambahan CSS khusus halaman ini (hero & kartu) -->
<style>
    /* ======== HERO ======== */
    .hero {
        height: 70vh;
        background: url('../assets/img/gedung.jpg') center/cover no-repeat;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #ffffff;
    }
    .hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.55);
    }
    .hero-content {
        position: relative;
        z-index: 2;
        animation: fadeIn 1.5s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(30px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .btn-main {
        background-color: #ffb703;
        color: #0b2c61;
        font-weight: 600;
        border-radius: 30px;
        padding: 10px 24px;
        transition: 0.3s;
    }
    .btn-main:hover {
        background-color: #ffc933;
        transform: translateY(-2px);
    }

    .card {
        border: none;
        border-radius: 15px;
        padding: 0;
        background: #fff;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .card:hover {
        transform: translateY(-8px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.12);
    }
    .card-img-top {
        border-radius: 15px 15px 0 0;
    }

    /* popup notif (container ada di footer.css, ini opsional kalau belum) */
    #notifPopup {
        position: fixed;
        top: 80px;
        right: 20px;
        z-index: 1050;
        max-width: 320px;
        width: 100%;
    }
    .notif-popup-item {
        background: #fff;
        border-left: 4px solid #0b2c61;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        border-radius: 8px;
        padding: 12px 14px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }
    .notif-popup-item.success { border-left-color: #16a34a; }
    .notif-popup-item.warning { border-left-color: #f59e0b; }
    .notif-popup-item.info    { border-left-color: #0b2c61; }
    .notif-popup-item .title { font-weight: 600; margin-bottom: 4px; color: #0b2c61; }
    .notif-popup-item .time  { font-size: 11px; color: #6b7280; }
</style>

<!-- ========================================================= -->
<!--                     HERO SECTION                          -->
<!-- ========================================================= -->
<section class="hero">
    <div class="hero-content">
        <h1 class="fw-bold">Selamat Datang, <?= htmlspecialchars($nama_user); ?> 👋</h1>
        <p class="lead">Kelola dan ajukan peminjaman fasilitas kampus secara digital.</p>
        <a href="fasilitas.php" class="btn btn-main mt-3">
            <i class="bi bi-building me-2"></i>Lihat Fasilitas
        </a>
    </div>
</section>

<!-- ========================================================= -->
<!--                  SECTION INFORMASI                        -->
<!-- ========================================================= -->
<section class="py-5 bg-light" data-aos="fade-up">
    <div class="container">

        <div class="text-center mb-5">
            <h2 class="fw-bold text-primary">E-Fasilitas Kampus</h2>
            <p class="text-muted mt-3 fs-5">
                E-Fasilitas Politeknik Negeri Bengkalis merupakan sistem digital yang memudahkan
                mahasiswa dan dosen dalam proses peminjaman fasilitas kampus.
            </p>
        </div>

        <div class="text-center mb-4">
            <h3 class="fw-bold text-dark">Fasilitas Populer</h3>
            <p class="text-muted">Beberapa fasilitas favorit pengguna.</p>
        </div>

        <div class="row g-4 justify-content-center">
            <?php
            $fasilitas = [
                ["img" => "aula.jpg",          "nama" => "Aula Serbaguna",     "desc" => "Tempat seminar & acara kampus."],
                ["img" => "lab_komputer.jpg", "nama" => "Lab Komputer",       "desc" => "Perangkat modern untuk praktikum."],
                ["img" => "ruang_rapat.jpg",  "nama" => "Ruang Rapat",        "desc" => "Tempat pertemuan resmi."],
                ["img" => "lapangan.jpg",     "nama" => "Lapangan Olahraga",  "desc" => "Kegiatan olahraga & event."]
            ];

            foreach ($fasilitas as $f): ?>
                <div class="col-lg-3 col-md-4 col-sm-6" data-aos="zoom-in">
                    <div class="card h-100">
                        <!-- PASTIKAN file ini ada di: /assets/img/aula.jpg, lab_komputer.jpg, dst -->
                        <img src="../assets/img/<?= htmlspecialchars($f['img']); ?>" 
                             class="card-img-top"
                             alt="<?= htmlspecialchars($f['nama']); ?>" 
                             style="height:200px; object-fit:cover;">
                        <div class="card-body text-center">
                            <h6 class="fw-bold"><?= htmlspecialchars($f['nama']); ?></h6>
                            <p class="text-muted small mb-0"><?= htmlspecialchars($f['desc']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5">
            <a href="fasilitas.php" class="btn btn-primary px-4 py-2 shadow-sm">
                <i class="bi bi-eye me-1"></i> Lihat Semua Fasilitas
            </a>
        </div>

    </div>
</section>

<!-- ========================================================= -->
<!--                  MODAL DETAIL NOTIFIKASI                  -->
<!-- ========================================================= -->
<?php foreach ($notifikasiList as $n): ?>
    <?php
    // Link "Detail"
    $linkTujuan = '#';
    if ($n['tipe'] === 'peminjaman') {
        $linkTujuan = 'peminjaman_saya.php#pinjam' . $n['id_pinjam'];
    } elseif ($n['tipe'] === 'pengembalian') {
        $linkTujuan = 'riwayat.php#kembali' . $n['id_pinjam'];
    }
    ?>

    <div class="modal fade" id="modalNotif<?= $n['id_notifikasi']; ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><?= htmlspecialchars($n['judul']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?= nl2br(htmlspecialchars($n['pesan'])); ?></p>
                    <small class="text-muted">Diterima: <?= date('d-m-Y H:i', strtotime($n['created_at'])); ?></small>
                </div>
                <div class="modal-footer">
                    <a href="<?= $linkTujuan; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Buka Halaman
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- ========================================================= -->
<!--            CONTAINER POPUP NOTIFIKASI BARU                -->
<!-- ========================================================= -->
<div id="notifPopup"></div>

<?php include '../includes/peminjam/footer.php'; ?>

<script>
AOS.init({ duration: 1000, once: true });

document.addEventListener("DOMContentLoaded", function () {
    const popup = document.getElementById("notifPopup");
    const notifs = <?= json_encode($popupNotifs); ?>;

    notifs.forEach((n, i) => {
        const div = document.createElement("div");
        let tipeClass = n.tipe || 'info';

        div.className = "notif-popup-item " + tipeClass;
        div.innerHTML = `
            <div class="title">${n.judul}</div>
            <div>${n.pesan}</div>
            <div class="time">${n.waktu}</div>
        `;
        div.onclick = () => {
            if (n.link && n.link !== '#') {
                window.location.href = n.link;
            }
        };

        popup.appendChild(div);
        setTimeout(() => div.remove(), 8000 + i * 1200);
    });
});
</script>
