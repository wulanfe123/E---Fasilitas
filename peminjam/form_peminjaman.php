<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';

// Validasi ID user dari session dengan ketat
$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$nama_user = htmlspecialchars($_SESSION['nama'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8');

// Set page variables
$pageTitle = 'Form Peminjaman';
$currentPage = 'fasilitas';

/* ==========================================================
   AMBIL ID FASILITAS DARI URL (VALIDASI KETAT)
   ========================================================== */
$initial_id_fasilitas = 0;
if (isset($_GET['id'])) {
    $initial_id_fasilitas = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($initial_id_fasilitas === false || $initial_id_fasilitas < 0) {
        $initial_id_fasilitas = 0;
    }
}

/* ==========================================================
   AMBIL DATA FASILITAS DENGAN PREPARED STATEMENT
   ========================================================== */
$fasilitas_list = [];

if ($initial_id_fasilitas > 0) {
    // Query untuk fasilitas spesifik
    $sqlFas = "SELECT f.id_fasilitas, f.nama_fasilitas, f.kategori, f.lokasi,
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
                      END AS status_aktual
               FROM fasilitas f 
               WHERE f.id_fasilitas = ?
               ORDER BY f.nama_fasilitas ASC";
    
    $stmtFas = $conn->prepare($sqlFas);
    if (!$stmtFas) {
        die("Query fasilitas error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
    }
    $stmtFas->bind_param("i", $initial_id_fasilitas);
} else {
    // Query untuk semua fasilitas
    $sqlFas = "SELECT f.id_fasilitas, f.nama_fasilitas, f.kategori, f.lokasi,
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
                      END AS status_aktual
               FROM fasilitas f 
               ORDER BY f.nama_fasilitas ASC";
    
    $stmtFas = $conn->prepare($sqlFas);
    if (!$stmtFas) {
        die("Query fasilitas error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
    }
}

$stmtFas->execute();
$fasilitas_result = $stmtFas->get_result();

while ($row = $fasilitas_result->fetch_assoc()) {
    $fasilitas_list[] = [
        'id_fasilitas' => (int)$row['id_fasilitas'],
        'nama_fasilitas' => htmlspecialchars($row['nama_fasilitas'], ENT_QUOTES, 'UTF-8'),
        'kategori' => htmlspecialchars($row['kategori'] ?? '', ENT_QUOTES, 'UTF-8'),
        'lokasi' => htmlspecialchars($row['lokasi'] ?? '', ENT_QUOTES, 'UTF-8'),
        'status_aktual' => htmlspecialchars($row['status_aktual'], ENT_QUOTES, 'UTF-8')
    ];
}
$stmtFas->close();

/* ==========================================================
   HEADER & NAVBAR
   ========================================================== */
include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>
<br><br>

<style>
    /* ======== HERO SECTION ======== */
    .content-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        padding: 60px 0;
        color: white;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }

    .content-header::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 50%;
        height: 100%;
        background: url('../assets/img/gedung.jpg') center/cover no-repeat;
        opacity: 0.1;
    }

    .content-header h2 {
        color: white !important;
        font-size: 2.5rem;
        font-weight: 800;
        text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
        position: relative;
        z-index: 2;
    }

    .content-header .lead {
        color: rgba(255, 255, 255, 0.95) !important;
        font-size: 1.1rem;
        font-weight: 300;
        position: relative;
        z-index: 2;
    }

    /* ======== CARD FORM ======== */
    .form-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(11, 44, 97, 0.15);
        border: none;
        overflow: hidden;
    }

    .form-card .card-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        color: white;
        padding: 20px 25px;
        border: none;
        font-size: 1.2rem;
        font-weight: 700;
    }

    .form-card .card-body {
        padding: 35px;
    }

    /* ======== FORM ELEMENTS ======== */
    .form-label {
        font-weight: 600;
        color: var(--dark-text);
        margin-bottom: 10px;
        font-size: 0.95rem;
    }

    .form-label .text-danger {
        color: var(--danger-color) !important;
    }

    .form-control,
    .form-select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(11, 44, 97, 0.15);
    }

    textarea.form-control {
        resize: vertical;
        min-height: 100px;
    }

    .form-control[type="file"] {
        padding: 10px 16px;
    }

    .form-text {
        color: var(--muted-text);
        font-size: 0.85rem;
        margin-top: 6px;
        display: block;
    }

    /* ======== SELECT MULTIPLE STYLING ======== */
    .select-multiple-wrapper {
        position: relative;
    }

    .select-multiple-wrapper select[multiple] {
        min-height: 150px;
    }

    .select-multiple-wrapper option {
        padding: 10px;
        border-bottom: 1px solid var(--border-color);
    }

    .select-multiple-wrapper option:hover {
        background: rgba(11, 44, 97, 0.05);
    }

    /* ======== FACILITY INFO BADGE ======== */
    .facility-info {
        background: rgba(11, 44, 97, 0.05);
        border-left: 4px solid var(--primary-color);
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .facility-info .facility-name {
        font-weight: 700;
        color: var(--primary-color);
        font-size: 1.1rem;
        margin-bottom: 5px;
    }

    .facility-info .facility-detail {
        color: var(--muted-text);
        font-size: 0.9rem;
        margin-bottom: 3px;
    }

    .badge-available {
        background: linear-gradient(135deg, var(--success-color), #22c55e);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-unavailable {
        background: linear-gradient(135deg, var(--danger-color), #ef4444);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    /* ======== ALERTS ======== */
    .alert {
        border-radius: 12px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 25px;
    }

    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        border-left: 4px solid var(--danger-color);
    }

    .alert-success {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
        border-left: 4px solid var(--success-color);
    }

    .alert-info {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1e40af;
        border-left: 4px solid var(--primary-color);
    }

    /* ======== BUTTONS ======== */
    .btn-submit {
        background: linear-gradient(135deg, var(--success-color), #22c55e);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 12px 32px;
        font-weight: 700;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(22, 163, 74, 0.4);
        color: white;
    }

    .btn-cancel {
        background: white;
        color: var(--muted-text);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 12px 32px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .btn-cancel:hover {
        background: var(--light-bg);
        border-color: var(--muted-text);
        color: var(--dark-text);
        transform: translateY(-2px);
    }

    /* ======== CHARACTER COUNTER ======== */
    .char-counter {
        text-align: right;
        color: var(--muted-text);
        font-size: 0.8rem;
        margin-top: 5px;
    }

    .char-counter.warning {
        color: var(--warning-color);
        font-weight: 600;
    }

    /* ======== FILE UPLOAD PREVIEW ======== */
    .file-preview {
        background: var(--light-bg);
        border-radius: 8px;
        padding: 10px;
        margin-top: 10px;
        display: none;
    }

    .file-preview.active {
        display: block;
    }

    .file-preview .file-icon {
        color: var(--danger-color);
        font-size: 1.5rem;
        margin-right: 10px;
    }

    .file-preview .file-name {
        font-weight: 600;
        color: var(--dark-text);
    }

    .file-preview .file-size {
        color: var(--muted-text);
        font-size: 0.85rem;
    }

    /* ======== RESPONSIVE ======== */
    @media (max-width: 768px) {
        .content-header h2 {
            font-size: 2rem;
        }

        .content-header .lead {
            font-size: 1rem;
        }

        .form-card .card-body {
            padding: 25px 20px;
        }

        .btn-submit,
        .btn-cancel {
            width: 100%;
            margin-bottom: 10px;
        }
    }
</style>

<!-- HERO SECTION -->
<section class="content-header text-center">
    <div class="container">
        <h2 class="fw-bold mb-3" data-aos="fade-up">Formulir Peminjaman Fasilitas</h2>
        <p class="lead mb-0" data-aos="fade-up" data-aos-delay="100">
            Lengkapi detail untuk mengajukan permohonan peminjaman fasilitas kampus dengan benar.
        </p>
    </div>
</section>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8" data-aos="fade-up">

            <div class="card form-card">
                <div class="card-header">
                    <i class="bi bi-file-earmark-text me-2"></i>Form Pengajuan Peminjaman
                </div>

                <div class="card-body">

                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($initial_id_fasilitas > 0 && !empty($fasilitas_list)): ?>
                        <div class="facility-info">
                            <div class="facility-name">
                                <i class="bi bi-building me-2"></i><?= $fasilitas_list[0]['nama_fasilitas'] ?>
                            </div>
                            <div class="facility-detail">
                                <i class="bi bi-tag me-1"></i>Kategori: <?= ucfirst($fasilitas_list[0]['kategori']) ?>
                            </div>
                            <div class="facility-detail">
                                <i class="bi bi-geo-alt me-1"></i>Lokasi: <?= $fasilitas_list[0]['lokasi'] ?>
                            </div>
                            <div class="mt-2">
                                <?php if ($fasilitas_list[0]['status_aktual'] === 'tersedia'): ?>
                                    <span class="badge-available">
                                        <i class="bi bi-check-circle me-1"></i>Tersedia
                                    </span>
                                <?php else: ?>
                                    <span class="badge-unavailable">
                                        <i class="bi bi-x-circle me-1"></i>Tidak Tersedia
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="proses_peminjaman.php" method="POST" enctype="multipart/form-data" id="formPeminjaman">

                        <!-- HIDDEN: ID USER -->
                        <input type="hidden" name="id_user" value="<?= $id_user ?>">

                        <!-- PILIH FASILITAS -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-building me-1"></i>Pilih Fasilitas 
                                <span class="text-danger">*</span>
                            </label>

                            <div class="<?= $initial_id_fasilitas === 0 ? 'select-multiple-wrapper' : '' ?>">
                                <select 
                                    name="fasilitas[]" 
                                    id="selectFasilitas"
                                    class="form-select"
                                    <?= $initial_id_fasilitas === 0 ? 'multiple' : '' ?>
                                    required
                                >
                                    <?php if (!empty($fasilitas_list)): ?>
                                        <?php foreach ($fasilitas_list as $f): ?>
                                            <option value="<?= $f['id_fasilitas'] ?>"
                                                <?= ($initial_id_fasilitas > 0 && $initial_id_fasilitas == $f['id_fasilitas']) ? 'selected' : '' ?>
                                                <?= $f['status_aktual'] === 'tidak_tersedia' ? 'disabled' : '' ?>>
                                                <?= $f['nama_fasilitas'] ?> 
                                                <?= $f['status_aktual'] === 'tidak_tersedia' ? '(Sedang Dipinjam)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">(Belum ada data fasilitas)</option>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <?php if ($initial_id_fasilitas === 0): ?>
                                <small class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Tahan <strong>CTRL</strong> (Windows) / <strong>Command ⌘</strong> (Mac) untuk memilih lebih dari satu fasilitas.
                                </small>
                            <?php else: ?>
                                <small class="form-text">
                                    <i class="bi bi-lock me-1"></i>
                                    Fasilitas dikunci sesuai pilihan sebelumnya.
                                </small>
                            <?php endif; ?>
                        </div>

                        <!-- TANGGAL MULAI -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-calendar-event me-1"></i>Tanggal Mulai Peminjaman 
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="tanggal_mulai" 
                                id="tanggal_mulai"
                                class="form-control" 
                                required 
                                min="<?= date('Y-m-d') ?>">
                            <small class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Tanggal pertama fasilitas akan digunakan.
                            </small>
                        </div>

                        <!-- TANGGAL SELESAI -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-calendar-check me-1"></i>Tanggal Selesai Peminjaman 
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="date" 
                                name="tanggal_selesai" 
                                id="tanggal_selesai"
                                class="form-control" 
                                required 
                                min="<?= date('Y-m-d') ?>">
                            <small class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Tanggal terakhir fasilitas digunakan (harus sama atau setelah tanggal mulai).
                            </small>
                        </div>

                        <!-- UPLOAD DOKUMEN -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-file-pdf me-1"></i>Upload Dokumen Peminjaman (PDF) 
                                <span class="text-danger">*</span>
                            </label>
                            <input 
                                type="file" 
                                name="dokumen_peminjaman" 
                                id="fileUpload" 
                                class="form-control"
                                accept="application/pdf"
                                required
                            >
                            <small class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Unggah proposal atau surat resmi dalam format <strong>PDF</strong> (maksimal 5MB).
                            </small>
                            <div class="file-preview" id="filePreview">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-pdf file-icon"></i>
                                    <div>
                                        <div class="file-name" id="fileName"></div>
                                        <div class="file-size" id="fileSize"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CATATAN -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-chat-left-text me-1"></i>Catatan Peminjaman 
                                <span class="text-danger">*</span>
                            </label>
                            <textarea 
                                name="catatan" 
                                id="catatan"
                                class="form-control" 
                                rows="4"
                                maxlength="500"
                                placeholder="Jelaskan jenis kegiatan, jumlah peserta, dan kebutuhan khusus lainnya..."
                                required
                            ></textarea>
                            <div class="char-counter" id="charCounter">0 / 500 karakter</div>
                            <small class="form-text">
                                <i class="bi bi-info-circle me-1"></i>
                                Berikan informasi lengkap tentang kegiatan Anda (maksimal 500 karakter).
                            </small>
                        </div>

                        <!-- TOMBOL AKSI -->
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" class="btn btn-submit">
                                <i class="bi bi-send-check me-2"></i>Ajukan Peminjaman
                            </button>
                            <a href="fasilitas.php" class="btn btn-cancel">
                                <i class="bi bi-x-circle me-2"></i>Batal
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/peminjam/footer.php'; ?>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
// Initialize AOS
AOS.init({ duration: 900, once: true, offset: 100 });

document.addEventListener('DOMContentLoaded', function() {
    const tglMulai = document.getElementById('tanggal_mulai');
    const tglSelesai = document.getElementById('tanggal_selesai');
    const fileInput = document.getElementById('fileUpload');
    const form = document.getElementById('formPeminjaman');
    const selectFas = document.getElementById('selectFasilitas');
    const catatanInput = document.getElementById('catatan');
    const charCounter = document.getElementById('charCounter');
    const filePreview = document.getElementById('filePreview');

    // ================== VALIDASI TANGGAL ==================
    const today = new Date().toISOString().split('T')[0];
    if (tglMulai) tglMulai.setAttribute('min', today);
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

        tglSelesai.addEventListener('change', function() {
            if (tglMulai.value && tglSelesai.value < tglMulai.value) {
                alert('Tanggal selesai tidak boleh sebelum tanggal mulai!');
                tglSelesai.value = tglMulai.value;
            }
        });
    }

    // ================== CHARACTER COUNTER ==================
    if (catatanInput && charCounter) {
        catatanInput.addEventListener('input', function() {
            const length = this.value.length;
            const maxLength = 500;
            charCounter.textContent = `${length} / ${maxLength} karakter`;
            
            if (length > 450) {
                charCounter.classList.add('warning');
            } else {
                charCounter.classList.remove('warning');
            }
        });
    }

    // ================== FILE UPLOAD VALIDATION & PREVIEW ==================
    if (fileInput && filePreview) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if (file) {
                // Validasi tipe file
                const fileName = file.name.toLowerCase();
                if (!fileName.endsWith('.pdf')) {
                    alert('❌ Hanya file PDF yang diperbolehkan!');
                    this.value = '';
                    filePreview.classList.remove('active');
                    return;
                }

                // Validasi ukuran file (5MB)
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                if (file.size > maxSize) {
                    alert('❌ Ukuran file terlalu besar! Maksimal 5MB.');
                    this.value = '';
                    filePreview.classList.remove('active');
                    return;
                }

                // Tampilkan preview
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = `Ukuran: ${(file.size / 1024).toFixed(2)} KB`;
                filePreview.classList.add('active');
            } else {
                filePreview.classList.remove('active');
            }
        });
    }

    // ================== FORM VALIDATION BEFORE SUBMIT ==================
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validasi fasilitas terpilih
            if (!selectFas || selectFas.options.length === 0) {
                alert('❌ Data fasilitas tidak tersedia. Silakan hubungi admin.');
                e.preventDefault();
                return;
            }

            let selectedCount = 0;
            for (let i = 0; i < selectFas.options.length; i++) {
                if (selectFas.options[i].selected && !selectFas.options[i].disabled) {
                    selectedCount++;
                }
            }

            if (selectedCount === 0) {
                alert('❌ Pilih minimal satu fasilitas yang tersedia!');
                selectFas.focus();
                e.preventDefault();
                return;
            }

            // Validasi tanggal
            if (!tglMulai.value || !tglSelesai.value) {
                alert('❌ Tanggal mulai dan tanggal selesai wajib diisi!');
                e.preventDefault();
                return;
            }

            if (tglSelesai.value < tglMulai.value) {
                alert('❌ Tanggal selesai tidak boleh sebelum tanggal mulai!');
                tglSelesai.focus();
                e.preventDefault();
                return;
            }

            // Validasi tanggal tidak boleh masa lalu
            if (tglMulai.value < today) {
                alert('❌ Tanggal mulai tidak boleh di masa lalu!');
                tglMulai.focus();
                e.preventDefault();
                return;
            }

            // Validasi catatan
            if (catatanInput && catatanInput.value.trim() === '') {
                alert('❌ Catatan peminjaman wajib diisi!');
                catatanInput.focus();
                e.preventDefault();
                return;
            }

            if (catatanInput && catatanInput.value.trim().length < 20) {
                alert('❌ Catatan terlalu pendek! Minimal 20 karakter.');
                catatanInput.focus();
                e.preventDefault();
                return;
            }

            // Validasi dokumen
            if (!fileInput || !fileInput.value) {
                alert('❌ Dokumen peminjaman wajib diupload (PDF)!');
                fileInput.focus();
                e.preventDefault();
                return;
            }

            // Konfirmasi submit
            const confirmed = confirm('✅ Apakah Anda yakin data yang diisi sudah benar dan ingin mengajukan peminjaman?');
            if (!confirmed) {
                e.preventDefault();
                return;
            }
        });
    }

    // ================== NAVBAR SCROLL EFFECT ==================
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        }
    });
});
</script>
