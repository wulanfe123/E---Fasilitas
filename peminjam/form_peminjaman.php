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
   AMBIL ID FASILITAS (JIKA DATANG DARI fasilitas.php?id=xx)
   ========================================================== */
$initial_id_fasilitas = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/* ==========================================================
   AMBIL DATA FASILITAS DENGAN PREPARED STATEMENT
   - Kalau ada id di URL → hanya fasilitas itu (untuk single select)
   - Kalau tidak ada → ambil semua (untuk multi select)
   ========================================================== */
if ($initial_id_fasilitas > 0) {
    $sqlFas = "SELECT id_fasilitas, nama_fasilitas 
               FROM fasilitas 
               WHERE id_fasilitas = ?
               ORDER BY nama_fasilitas ASC";
    $stmtFas = $conn->prepare($sqlFas);
    if (!$stmtFas) {
        die("Query fasilitas error: " . $conn->error);
    }
    $stmtFas->bind_param("i", $initial_id_fasilitas);
} else {
    $sqlFas = "SELECT id_fasilitas, nama_fasilitas 
               FROM fasilitas 
               ORDER BY nama_fasilitas ASC";
    $stmtFas = $conn->prepare($sqlFas);
    if (!$stmtFas) {
        die("Query fasilitas error: " . $conn->error);
    }
}
$stmtFas->execute();
$fasilitas_result = $stmtFas->get_result();

/* ==========================================================
   HEADER & NAVBAR (PAKAI CSS peminjam.css)
   ========================================================== */
include '../includes/peminjam/header.php';   // sudah ada <html><head> + link peminjam.css + <body>
include '../includes/peminjam/navbar.php';   // navbar peminjam
?>

<!-- ===================================================== -->
<!--                  HEADER KONTEN                        -->
<!-- ===================================================== -->
<section class="content-header text-center">
    <div class="container">
        <h2 class="fw-bold mb-2 text-primary">Formulir Peminjaman</h2>
        <p class="lead text-muted mb-0">
            Lengkapi detail untuk mengajukan permohonan peminjaman fasilitas kampus.
        </p>
    </div>
</section>

<!-- ===================================================== -->
<!--                  FORM PEMINJAMAN                      -->
<!-- ===================================================== -->
<div class="container mt-4 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white fw-semibold py-3">
                    <i class="bi bi-plus-circle me-2"></i> Ajukan Peminjaman Fasilitas
                </div>

                <div class="card-body">

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="proses_peminjaman.php" method="POST" enctype="multipart/form-data" id="formPeminjaman">

                        <!-- HIDDEN: ID USER -->
                        <input type="hidden" name="id_user" value="<?= (int)$id_user; ?>">

                        <!-- PILIH FASILITAS -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Pilih Fasilitas</label>

                            <select 
                                name="fasilitas[]" 
                                id="selectFasilitas"
                                class="form-control"
                                <?= $initial_id_fasilitas > 0 ? '' : 'multiple'; ?>
                                required
                            >
                                <?php if ($fasilitas_result && $fasilitas_result->num_rows > 0): ?>
                                    <?php while ($f = $fasilitas_result->fetch_assoc()): ?>
                                        <option value="<?= (int)$f['id_fasilitas']; ?>"
                                            <?= ($initial_id_fasilitas > 0 && $initial_id_fasilitas == (int)$f['id_fasilitas']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($f['nama_fasilitas']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option value="">(Belum ada data fasilitas)</option>
                                <?php endif; ?>
                            </select>

                            <?php if ($initial_id_fasilitas === 0): ?>
                                <small class="text-muted">
                                    Tahan <strong>CTRL</strong> (di PC) / <strong>Command</strong> (di Mac) untuk memilih lebih dari satu fasilitas.
                                </small>
                            <?php else: ?>
                                <small class="text-muted">
                                    Form ini dikunci pada fasilitas yang kamu pilih sebelumnya.
                                </small>
                            <?php endif; ?>
                        </div>

                        <!-- TANGGAL MULAI -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tanggal Mulai</label>
                            <input 
                                type="date" 
                                name="tanggal_mulai" 
                                id="tanggal_mulai"
                                class="form-control" 
                                required 
                                min="<?= date('Y-m-d'); ?>">
                            <small class="text-muted">
                                Tanggal pertama kali fasilitas akan digunakan.
                            </small>
                        </div>

                        <!-- TANGGAL SELESAI -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tanggal Selesai</label>
                            <input 
                                type="date" 
                                name="tanggal_selesai" 
                                id="tanggal_selesai"
                                class="form-control" 
                                required 
                                min="<?= date('Y-m-d'); ?>">
                            <small class="text-muted">
                                Tanggal terakhir fasilitas digunakan.  
                                <br>Pastikan lebih besar atau sama dengan tanggal mulai.
                            </small>
                        </div>

                        <!-- UPLOAD DOKUMEN (OPSIONAL, PDF SAJA) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Upload Dokumen (opsional)</label>
                            <input 
                                type="file" 
                                name="dokumen_peminjaman" 
                                id="fileUpload" 
                                class="form-control"
                                accept="application/pdf"
                            >
                            <small class="text-muted">
                                Jika diperlukan, unggah proposal / surat resmi dalam format <strong>PDF</strong>.
                            </small>
                        </div>

                        <!-- CATATAN -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Catatan (opsional)</label>
                            <textarea 
                                name="catatan" 
                                id="catatan"
                                class="form-control" 
                                rows="3"
                                maxlength="500"
                            ></textarea>
                            <small class="text-muted">
                                Jelaskan jenis kegiatan, jumlah peserta, dan kebutuhan khusus lain (maks. 500 karakter).
                            </small>
                        </div>

                        <!-- TOMBOL AKSI -->
                        <button type="submit" class="btn btn-success px-4 mt-3">
                            <i class="bi bi-send-check me-1"></i> Ajukan Peminjaman
                        </button>
                        <a href="fasilitas.php" class="btn btn-secondary px-4 mt-3">
                            <i class="bi bi-x-circle me-1"></i> Batal
                        </a>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/peminjam/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tglMulai   = document.getElementById('tanggal_mulai');
    const tglSelesai = document.getElementById('tanggal_selesai');
    const fileInput  = document.getElementById('fileUpload');
    const form       = document.getElementById('formPeminjaman');
    const selectFas  = document.getElementById('selectFasilitas');

    // ================== VALIDASI TANGGAL (CLIENT SIDE) ==================
    const today = new Date().toISOString().split('T')[0];
    if (tglMulai)  tglMulai.setAttribute('min', today);
    if (tglSelesai) tglSelesai.setAttribute('min', today);

    if (tglMulai && tglSelesai) {
        tglMulai.addEventListener('change', function() {
            if (tglMulai.value) {
                tglSelesai.setAttribute('min', tglMulai.value);
                if (tglSelesai.value && tglSelesai.value < tglMulai.value) {
                    tglSelesai.value = tglMulai.value;
                }
            }
        });
    }

    // ================== VALIDASI FILE PDF ==================
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const fileName = file.name.toLowerCase();
                if (!fileName.endsWith('.pdf')) {
                    alert('Jenis file harus PDF!');
                    this.value = '';
                }
            }
        });
    }

    // ================== VALIDASI SEBELUM SUBMIT ==================
    if (form) {
        form.addEventListener('submit', function(e) {
            // cek fasilitas terpilih
            if (!selectFas || selectFas.options.length === 0) {
                alert('Data fasilitas tidak tersedia. Silakan hubungi admin.');
                e.preventDefault();
                return;
            }

            let selectedCount = 0;
            for (let i = 0; i < selectFas.options.length; i++) {
                if (selectFas.options[i].selected) {
                    selectedCount++;
                }
            }

            if (selectedCount === 0) {
                alert('Pilih minimal satu fasilitas terlebih dahulu.');
                e.preventDefault();
                return;
            }

            // validasi tanggal
            if (!tglMulai.value || !tglSelesai.value) {
                alert('Tanggal mulai dan tanggal selesai wajib diisi.');
                e.preventDefault();
                return;
            }
            if (tglSelesai.value < tglMulai.value) {
                alert('Tanggal selesai tidak boleh sebelum tanggal mulai.');
                e.preventDefault();
                return;
            }
        });
    }
});
</script>
