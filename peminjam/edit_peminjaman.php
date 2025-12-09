<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/notifikasi_helper.php';

// Validasi ID user dari session dengan ketat
$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

$nama_user = htmlspecialchars($_SESSION['nama'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8');

// Set page variables
$pageTitle = 'Edit Peminjaman';
$currentPage = 'peminjaman';

// Validasi ID peminjaman dari URL
$id_pinjam = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if ($id_pinjam === false || $id_pinjam <= 0) {
    $_SESSION['error'] = "ID peminjaman tidak valid.";
    header("Location: peminjaman_saya.php");
    exit;
}

$errors = [];
$success = [];

/* ==========================================================
   1) AMBIL DATA PEMINJAMAN (PREPARED STATEMENT)
   ========================================================== */
$sqlDetail = "
    SELECT 
        p.*,
        COALESCE(GROUP_CONCAT(DISTINCT f.nama_fasilitas ORDER BY f.nama_fasilitas SEPARATOR ', '), '-') AS fasilitas_list
    FROM peminjaman p
    LEFT JOIN daftar_peminjaman_fasilitas df ON p.id_pinjam = df.id_pinjam
    LEFT JOIN fasilitas f ON df.id_fasilitas = f.id_fasilitas
    WHERE p.id_pinjam = ? AND p.id_user = ?
    GROUP BY p.id_pinjam
    LIMIT 1
";

$stmtDetail = $conn->prepare($sqlDetail);
if (!$stmtDetail) {
    die("Query error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}

$stmtDetail->bind_param("ii", $id_pinjam, $id_user);
$stmtDetail->execute();
$resDetail = $stmtDetail->get_result();

if ($resDetail->num_rows === 0) {
    $_SESSION['error'] = "Data peminjaman tidak ditemukan atau Anda tidak memiliki akses.";
    $stmtDetail->close();
    header("Location: peminjaman_saya.php");
    exit;
}

$data = $resDetail->fetch_assoc();
$stmtDetail->close();

// Cek status - hanya usulan yang bisa diedit
$status_peminjaman = strtolower($data['status'] ?? '');
if ($status_peminjaman !== 'usulan') {
    $_SESSION['error'] = "Hanya peminjaman dengan status 'Usulan' yang dapat diedit.";
    header("Location: detail_peminjaman.php?id=" . $id_pinjam);
    exit;
}

/* ==========================================================
   2) PROSES UPDATE PEMINJAMAN (VALIDASI + PREPARED STATEMENT)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Token Validation (opsional tapi disarankan)
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     $errors[] = "Invalid CSRF token.";
    // }

    // --- Ambil input & sanitasi ---
    $tanggal_mulai = filter_input(INPUT_POST, 'tanggal_mulai', FILTER_SANITIZE_STRING);
    $tanggal_selesai = filter_input(INPUT_POST, 'tanggal_selesai', FILTER_SANITIZE_STRING);
    $catatan = trim($_POST['catatan'] ?? '');

    // Sanitasi catatan
    $catatan = htmlspecialchars($catatan, ENT_QUOTES, 'UTF-8');

    // --- Validasi tanggal (format & logika) ---
    if (empty($tanggal_mulai) || empty($tanggal_selesai)) {
        $errors[] = "Tanggal mulai dan tanggal selesai wajib diisi.";
    } else {
        $dMulai = DateTime::createFromFormat('Y-m-d', $tanggal_mulai);
        $dSelesai = DateTime::createFromFormat('Y-m-d', $tanggal_selesai);
        $isValidMulai = $dMulai && $dMulai->format('Y-m-d') === $tanggal_mulai;
        $isValidSelesai = $dSelesai && $dSelesai->format('Y-m-d') === $tanggal_selesai;

        if (!$isValidMulai || !$isValidSelesai) {
            $errors[] = "Format tanggal tidak valid. Gunakan format YYYY-MM-DD.";
        } else {
            // Tidak boleh tanggal selesai < tanggal mulai
            if ($tanggal_selesai < $tanggal_mulai) {
                $errors[] = "Tanggal selesai tidak boleh lebih awal dari tanggal mulai.";
            }

            // Validasi: tidak boleh sebelum hari ini
            $today = date('Y-m-d');
            if ($tanggal_mulai < $today) {
                $errors[] = "Tanggal mulai tidak boleh sebelum hari ini.";
            }

            // Validasi: maksimal peminjaman 30 hari
            $diff = $dMulai->diff($dSelesai);
            if ($diff->days > 30) {
                $errors[] = "Durasi peminjaman maksimal 30 hari.";
            }
        }
    }

    // Validasi panjang catatan
    if (mb_strlen($catatan) > 500) {
        $errors[] = "Catatan maksimal 500 karakter.";
    }

    // --- Validasi file (jika ada) -> hanya PDF ---
    $dokumen_name = $data['dokumen_peminjaman']; // nilai lama
    $old_dokumen = $dokumen_name; // Simpan untuk dihapus jika ada upload baru

    if (!empty($_FILES['dokumen_peminjaman']['name'])) {
        $file = $_FILES['dokumen_peminjaman'];
        $fileName = $file['name'];
        $fileTmp = $file['tmp_name'];
        $fileSize = $file['size'];
        $fileError = $file['error'];

        if ($fileError === UPLOAD_ERR_OK) {
            // Validasi extension
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf'];
            
            if (!in_array($ext, $allowedExt)) {
                $errors[] = "Dokumen peminjaman harus berformat PDF.";
            } else {
                // Validasi MIME type untuk keamanan ekstra
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $fileTmp);
                finfo_close($finfo);
                
                if ($mimeType !== 'application/pdf') {
                    $errors[] = "File yang diupload bukan PDF valid.";
                } else {
                    // Validasi ukuran (2MB)
                    $maxSize = 2 * 1024 * 1024;
                    if ($fileSize > $maxSize) {
                        $errors[] = "Ukuran file maksimal 2MB.";
                    } else {
                        $targetDir = "../uploads/dokumen/";
                        if (!is_dir($targetDir)) {
                            if (!@mkdir($targetDir, 0755, true)) {
                                $errors[] = "Gagal membuat direktori upload.";
                            }
                        }

                        // Generate nama file yang aman
                        $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                        $newName = time() . "_" . $id_user . "_" . $safeName . "." . $ext;
                        $targetFilePath = $targetDir . $newName;

                        if (move_uploaded_file($fileTmp, $targetFilePath)) {
                            // Hapus file lama jika ada
                            if (!empty($old_dokumen) && file_exists($targetDir . $old_dokumen)) {
                                @unlink($targetDir . $old_dokumen);
                            }
                            $dokumen_name = $newName;
                        } else {
                            $errors[] = "Gagal mengupload dokumen peminjaman.";
                        }
                    }
                }
            }
        } elseif ($fileError !== UPLOAD_ERR_NO_FILE) {
            $errors[] = "Terjadi kesalahan saat mengupload file (kode: $fileError).";
        }
    }

    // --- Jika tidak ada error -> update ke database ---
    if (empty($errors)) {
        $sqlUpdate = "
            UPDATE peminjaman 
            SET 
                tanggal_mulai = ?, 
                tanggal_selesai = ?, 
                catatan = ?, 
                dokumen_peminjaman = ?,
                updated_at = NOW()
            WHERE id_pinjam = ? AND id_user = ? AND status = 'usulan'
            LIMIT 1
        ";
        
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if (!$stmtUpdate) {
            $errors[] = "Gagal menyiapkan query update: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8');
        } else {
            $stmtUpdate->bind_param(
                "ssssii",
                $tanggal_mulai,
                $tanggal_selesai,
                $catatan,
                $dokumen_name,
                $id_pinjam,
                $id_user
            );

            if ($stmtUpdate->execute()) {
                if ($stmtUpdate->affected_rows > 0) {
                    $stmtUpdate->close();
                    $_SESSION['success'] = "Data peminjaman berhasil diperbarui.";
                    header("Location: detail_peminjaman.php?id=" . $id_pinjam);
                    exit;
                } else {
                    $errors[] = "Tidak ada perubahan data atau peminjaman sudah tidak bisa diedit.";
                }
            } else {
                $errors[] = "Gagal memperbarui data peminjaman: " . htmlspecialchars($stmtUpdate->error, ENT_QUOTES, 'UTF-8');
            }
            $stmtUpdate->close();
        }
    }

    // Kalau ada error, refresh data dengan input terakhir
    if (!empty($errors)) {
        $data['tanggal_mulai'] = $tanggal_mulai;
        $data['tanggal_selesai'] = $tanggal_selesai;
        $data['catatan'] = $catatan;
        $data['dokumen_peminjaman'] = $dokumen_name;
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sanitasi data untuk ditampilkan
$fasilitas_list = htmlspecialchars($data['fasilitas_list'] ?? '-', ENT_QUOTES, 'UTF-8');
$tanggal_mulai_display = htmlspecialchars($data['tanggal_mulai'], ENT_QUOTES, 'UTF-8');
$tanggal_selesai_display = htmlspecialchars($data['tanggal_selesai'], ENT_QUOTES, 'UTF-8');
$catatan_display = htmlspecialchars($data['catatan'] ?? '', ENT_QUOTES, 'UTF-8');
$dokumen_display = htmlspecialchars($data['dokumen_peminjaman'] ?? '', ENT_QUOTES, 'UTF-8');

/* ==========================================================
   3) LOAD HEADER & NAVBAR
   ========================================================== */
include '../includes/peminjam/header.php';
include '../includes/peminjam/navbar.php';
?>

<style>
    /* ======== HERO SECTION ======== */
    .hero-section {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        padding: 60px 0;
        color: white;
        margin-bottom: 40px;
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 50%;
        height: 100%;
        background: url('../assets/img/gedung.jpg') center/cover no-repeat;
        opacity: 0.1;
    }

    .hero-section h2 {
        color: white !important;
        font-size: 2.5rem;
        font-weight: 800;
        text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.2);
        position: relative;
        z-index: 2;
    }

    .hero-section p {
        color: rgba(255, 255, 255, 0.95) !important;
        font-size: 1.1rem;
        font-weight: 300;
        position: relative;
        z-index: 2;
    }

    /* ======== FORM CARD ======== */
    .card-form {
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(11, 44, 97, 0.1);
        border: none;
        padding: 35px !important;
    }

    .form-label {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .form-control,
    .form-select {
        border: 2px solid var(--border-color);
        border-radius: 12px;
        padding: 12px 18px;
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

    /* ======== FILE INPUT ======== */
    .form-control[type="file"] {
        padding: 10px;
    }

    .form-control[type="file"]::file-selector-button {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        margin-right: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .form-control[type="file"]::file-selector-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(11, 44, 97, 0.2);
    }

    /* ======== ALERTS ======== */
    .alert {
        border-radius: 15px;
        border: none;
        padding: 18px 24px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .alert-danger {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        border-left: 4px solid var(--danger-color);
    }

    .alert-danger ul {
        padding-left: 20px;
        margin-bottom: 0;
    }

    .alert-info {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1e40af;
        border-left: 4px solid #3b82f6;
    }

    /* ======== BUTTONS ======== */
    .btn-outline-secondary {
        border: 2px solid var(--border-color);
        color: var(--muted-text);
        border-radius: 12px;
        padding: 12px 28px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        border: none;
        border-radius: 12px;
        padding: 12px 32px;
        font-weight: 700;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(11, 44, 97, 0.2);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 18px rgba(11, 44, 97, 0.3);
    }

    /* ======== INFO BOX ======== */
    .info-box {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 2px solid #bae6fd;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .info-box h6 {
        color: var(--primary-color);
        font-weight: 700;
        margin-bottom: 10px;
    }

    .info-box p {
        margin-bottom: 5px;
        color: var(--dark-text);
    }

    /* ======== RESPONSIVE ======== */
    @media (max-width: 768px) {
        .hero-section h2 {
            font-size: 2rem;
        }

        .hero-section p {
            font-size: 1rem;
        }

        .card-form {
            padding: 25px !important;
        }

        .d-flex.justify-content-between {
            flex-direction: column;
            gap: 10px;
        }

        .d-flex.justify-content-between .btn {
            width: 100%;
        }
    }
</style>

<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-3" data-aos="fade-up">
            <i class="bi bi-pencil-square me-2"></i>Edit Peminjaman
        </h2>
        <p class="mb-0" data-aos="fade-up" data-aos-delay="100">
            Perbarui data peminjaman fasilitas yang sudah kamu ajukan
        </p>
    </div>
</section>

<div class="container mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8" data-aos="fade-up">
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h6 class="mb-2">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Terjadi Kesalahan:
                    </h6>
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Info Fasilitas -->
            <div class="info-box">
                <h6>
                    <i class="bi bi-building me-2"></i>Fasilitas yang Dipinjam
                </h6>
                <p class="mb-0">
                    <strong><?= $fasilitas_list ?></strong>
                </p>
            </div>

            <div class="card card-form">
                <form method="post" enctype="multipart/form-data" novalidate id="editForm">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-calendar-event me-1"></i>Tanggal Mulai
                        </label>
                        <input type="date" 
                               name="tanggal_mulai" 
                               id="tanggal_mulai"
                               class="form-control" 
                               value="<?= $tanggal_mulai_display ?>" 
                               required>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Pilih tanggal mulai peminjaman (tidak boleh sebelum hari ini)
                        </small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-calendar-check me-1"></i>Tanggal Selesai
                        </label>
                        <input type="date" 
                               name="tanggal_selesai" 
                               id="tanggal_selesai"
                               class="form-control" 
                               value="<?= $tanggal_selesai_display ?>" 
                               required>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Pilih tanggal selesai peminjaman (maksimal 30 hari dari tanggal mulai)
                        </small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-chat-left-text me-1"></i>Catatan
                        </label>
                        <textarea 
                            name="catatan" 
                            id="catatan"
                            class="form-control" 
                            rows="4"
                            maxlength="500"
                            placeholder="Tambahkan keterangan kegiatan atau kebutuhan peminjaman (opsional)"><?= $catatan_display ?></textarea>
                        <small class="text-muted" id="charCount">
                            <i class="bi bi-info-circle me-1"></i>
                            Keterangan tambahan tentang peminjaman (maksimal 500 karakter)
                        </small>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            <i class="bi bi-file-earmark-pdf me-1"></i>Dokumen Peminjaman (PDF)
                        </label>
                        
                        <?php if (!empty($dokumen_display)): ?>
                            <div class="alert alert-info mb-2">
                                <i class="bi bi-file-earmark-pdf-fill me-2"></i>
                                <strong>File saat ini:</strong>
                                <a href="../uploads/dokumen/<?= $dokumen_display ?>" target="_blank" class="text-decoration-none">
                                    <?= $dokumen_display ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <input type="file" 
                               name="dokumen_peminjaman" 
                               id="dokumen_peminjaman" 
                               class="form-control" 
                               accept="application/pdf">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Kosongkan jika tidak ingin mengganti dokumen. Format: <strong>PDF</strong>, maksimal <strong>2MB</strong>
                        </small>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <a href="detail_peminjaman.php?id=<?= $id_pinjam ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Kembali
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/peminjam/footer.php'; ?>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    // Initialize AOS
    AOS.init({ 
        duration: 900, 
        once: true,
        offset: 100
    });

    document.addEventListener('DOMContentLoaded', function() {
        const tglMulai = document.getElementById('tanggal_mulai');
        const tglSelesai = document.getElementById('tanggal_selesai');
        const fileInput = document.getElementById('dokumen_peminjaman');
        const catatan = document.getElementById('catatan');
        const charCount = document.getElementById('charCount');
        const form = document.getElementById('editForm');

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        if (tglMulai) {
            tglMulai.setAttribute('min', today);
        }
        if (tglSelesai) {
            tglSelesai.setAttribute('min', today);
        }

        // Update tanggal selesai min ketika tanggal mulai berubah
        if (tglMulai && tglSelesai) {
            tglMulai.addEventListener('change', function() {
                if (tglMulai.value) {
                    tglSelesai.min = tglMulai.value;
                    
                    // Auto-adjust jika tanggal selesai < tanggal mulai
                    if (tglSelesai.value && tglSelesai.value < tglMulai.value) {
                        tglSelesai.value = tglMulai.value;
                    }

                    // Validasi maksimal 30 hari
                    if (tglSelesai.value) {
                        const start = new Date(tglMulai.value);
                        const end = new Date(tglSelesai.value);
                        const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                        
                        if (diffDays > 30) {
                            alert('⚠️ Durasi peminjaman maksimal 30 hari!');
                            const maxDate = new Date(start);
                            maxDate.setDate(maxDate.getDate() + 30);
                            tglSelesai.value = maxDate.toISOString().split('T')[0];
                        }
                    }
                }
            });

            tglSelesai.addEventListener('change', function() {
                if (tglMulai.value && tglSelesai.value) {
                    const start = new Date(tglMulai.value);
                    const end = new Date(tglSelesai.value);
                    const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                    
                    if (diffDays > 30) {
                        alert('⚠️ Durasi peminjaman maksimal 30 hari!');
                        const maxDate = new Date(start);
                        maxDate.setDate(maxDate.getDate() + 30);
                        tglSelesai.value = maxDate.toISOString().split('T')[0];
                    }
                }
            });
        }

        // Character counter untuk catatan
        if (catatan && charCount) {
            function updateCharCount() {
                const length = catatan.value.length;
                const baseText = 'Keterangan tambahan tentang peminjaman ';
                charCount.innerHTML = `<i class="bi bi-info-circle me-1"></i>${baseText}(<strong>${length}/500 karakter</strong>)`;
                
                if (length > 450) {
                    charCount.style.color = '#dc3545';
                } else if (length > 350) {
                    charCount.style.color = '#f59e0b';
                } else {
                    charCount.style.color = '#6b7280';
                }
            }
            
            catatan.addEventListener('input', updateCharCount);
            updateCharCount();
        }

        // Validasi file PDF
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (!file) return;

                const fileName = file.name.toLowerCase();
                const ext = fileName.split('.').pop();

                // Validasi extension
                if (ext !== 'pdf') {
                    alert('⚠️ Dokumen peminjaman harus berformat PDF!');
                    this.value = '';
                    return;
                }

                // Validasi ukuran (2MB)
                const maxSize = 2 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('⚠️ Ukuran file maksimal 2MB!');
                    this.value = '';
                    return;
                }

                // Validasi MIME type
                if (file.type !== 'application/pdf') {
                    alert('⚠️ File yang dipilih bukan PDF valid!');
                    this.value = '';
                    return;
                }
            });
        }

        // Form validation sebelum submit
        if (form) {
            form.addEventListener('submit', function(e) {
                let isValid = true;
                const errors = [];

                // Validasi tanggal
                if (!tglMulai.value || !tglSelesai.value) {
                    errors.push('Tanggal mulai dan selesai harus diisi');
                    isValid = false;
                } else {
                    const start = new Date(tglMulai.value);
                    const end = new Date(tglSelesai.value);
                    const todayDate = new Date(today);

                    if (start < todayDate) {
                        errors.push('Tanggal mulai tidak boleh sebelum hari ini');
                        isValid = false;
                    }

                    if (end < start) {
                        errors.push('Tanggal selesai tidak boleh lebih awal dari tanggal mulai');
                        isValid = false;
                    }

                    const diffDays = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                    if (diffDays > 30) {
                        errors.push('Durasi peminjaman maksimal 30 hari');
                        isValid = false;
                    }
                }

                // Validasi catatan
                if (catatan.value.length > 500) {
                    errors.push('Catatan maksimal 500 karakter');
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    alert('⚠️ Validasi Gagal:\n\n' + errors.join('\n'));
                }
            });
        }
    });

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (navbar) {
            navbar.classList.toggle('scrolled', window.scrollY > 50);
        }
    });
</script>
