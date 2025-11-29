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

$id_pinjam = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id_pinjam <= 0) {
    $_SESSION['error'] = "Peminjaman tidak valid.";
    header("Location: peminjaman_saya.php");
    exit;
}

$errors = [];

/* ==========================================================
   1) AMBIL DATA PEMINJAMAN (PREPARED STATEMENT)
   ========================================================== */
$sqlDetail = "SELECT * FROM peminjaman WHERE id_pinjam = ? AND id_user = ? LIMIT 1";
$stmtDetail = $conn->prepare($sqlDetail);
if (!$stmtDetail) {
    die("Query error: " . $conn->error);
}
$stmtDetail->bind_param("ii", $id_pinjam, $id_user);
$stmtDetail->execute();
$resDetail = $stmtDetail->get_result();

if ($resDetail->num_rows === 0) {
    $_SESSION['error'] = "Data peminjaman tidak ditemukan.";
    $stmtDetail->close();
    header("Location: peminjaman_saya.php");
    exit;
}
$data = $resDetail->fetch_assoc();
$stmtDetail->close();

/* ==========================================================
   2) PROSES UPDATE PEMINJAMAN (VALIDASI + PREPARED STATEMENT)
   ========================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Ambil input & trim ---
    $tanggal_mulai   = trim($_POST['tanggal_mulai']   ?? '');
    $tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');
    $catatan         = trim($_POST['catatan']         ?? '');

    // --- Validasi tanggal (format & logika) ---
    if ($tanggal_mulai === '' || $tanggal_selesai === '') {
        $errors[] = "Tanggal mulai dan tanggal selesai wajib diisi.";
    } else {
        $dMulai   = DateTime::createFromFormat('Y-m-d', $tanggal_mulai);
        $dSelesai = DateTime::createFromFormat('Y-m-d', $tanggal_selesai);
        $isValidMulai   = $dMulai && $dMulai->format('Y-m-d') === $tanggal_mulai;
        $isValidSelesai = $dSelesai && $dSelesai->format('Y-m-d') === $tanggal_selesai;

        if (!$isValidMulai || !$isValidSelesai) {
            $errors[] = "Format tanggal tidak valid.";
        } else {
            // Tidak boleh tanggal selesai < tanggal mulai
            if ($tanggal_selesai < $tanggal_mulai) {
                $errors[] = "Tanggal selesai tidak boleh lebih awal dari tanggal mulai.";
            }

            // (opsional) Tidak boleh sebelum hari ini
            $today = date('Y-m-d');
            if ($tanggal_mulai < $today) {
                $errors[] = "Tanggal mulai tidak boleh sebelum hari ini.";
            }
        }
    }

    // --- Validasi file (jika ada) -> hanya PDF ---
    $dokumen_name = $data['dokumen_peminjaman']; // nilai lama (bisa null/empty)
    if (!empty($_FILES['dokumen_peminjaman']['name'])) {
        $file      = $_FILES['dokumen_peminjaman'];
        $fileName  = $file['name'];
        $fileTmp   = $file['tmp_name'];
        $fileSize  = $file['size'];
        $fileError = $file['error'];

        if ($fileError === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                $errors[] = "Dokumen peminjaman harus berformat PDF.";
            } else {
                // Batas ukuran misal 2MB
                if ($fileSize > 2 * 1024 * 1024) {
                    $errors[] = "Ukuran file maksimal 2MB.";
                } else {
                    $targetDir = "../uploads/dokumen/";
                    if (!is_dir($targetDir)) {
                        @mkdir($targetDir, 0775, true);
                    }

                    $newName = time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $fileName);
                    $targetFilePath = $targetDir . $newName;

                    if (move_uploaded_file($fileTmp, $targetFilePath)) {
                        $dokumen_name = $newName;
                    } else {
                        $errors[] = "Gagal mengupload dokumen peminjaman.";
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
            SET tanggal_mulai = ?, 
                tanggal_selesai = ?, 
                catatan = ?, 
                dokumen_peminjaman = ?
            WHERE id_pinjam = ? AND id_user = ?
            LIMIT 1
        ";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if (!$stmtUpdate) {
            $errors[] = "Gagal menyiapkan query update: " . $conn->error;
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
                $stmtUpdate->close();
                $_SESSION['success'] = "Data peminjaman berhasil diperbarui.";
                header("Location: detail_peminjaman.php?id=" . $id_pinjam);
                exit;
            } else {
                $errors[] = "Gagal memperbarui data peminjaman. Silakan coba lagi.";
                $stmtUpdate->close();
            }
        }
    }

    // Kalau ada error, variabel $data di-refresh supaya form tetap berisi input terakhir
    $data['tanggal_mulai']      = $tanggal_mulai;
    $data['tanggal_selesai']    = $tanggal_selesai;
    $data['catatan']            = $catatan;
    $data['dokumen_peminjaman'] = $dokumen_name;
}

/* ==========================================================
   3) LOAD HEADER & NAVBAR KHUSUS PEMINJAM
   ========================================================== */
include '../includes/peminjam/header.php';   // sudah ada <head>, link CSS, dan <body>
include '../includes/peminjam/navbar.php';   // navbar peminjam (home/fasilitas/dll)
?>

<section class="hero-section text-center">
    <div class="container">
        <h2 class="fw-bold mb-1">Edit Peminjaman</h2>
        <p class="mb-1 text-muted">
            Perbarui data peminjaman fasilitas yang sudah kamu ajukan.
        </p>
    </div>
</section>

<div class="container mb-5 edit-peminjaman-wrapper">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card card-form p-4">
                <form method="post" enctype="multipart/form-data" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" 
                               name="tanggal_mulai" 
                               class="form-control" 
                               value="<?= htmlspecialchars($data['tanggal_mulai']); ?>" 
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" 
                               name="tanggal_selesai" 
                               class="form-control" 
                               value="<?= htmlspecialchars($data['tanggal_selesai']); ?>" 
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="catatan" class="form-control" rows="3"><?= htmlspecialchars($data['catatan'] ?? ''); ?></textarea>
                        <small class="text-muted">Tambahkan keterangan kegiatan atau kebutuhan peminjaman (opsional).</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Dokumen Peminjaman (PDF)</label>
                        <?php if (!empty($data['dokumen_peminjaman'])): ?>
                            <p class="small text-muted mb-1">
                                File saat ini: 
                                <a href="../uploads/dokumen/<?= htmlspecialchars($data['dokumen_peminjaman']); ?>" target="_blank">
                                    <?= htmlspecialchars($data['dokumen_peminjaman']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <input type="file" 
                               name="dokumen_peminjaman" 
                               id="dokumen_peminjaman" 
                               class="form-control" 
                               accept="application/pdf">
                        <small class="text-muted">
                            Kosongkan jika tidak ingin mengganti dokumen. Format diizinkan: <strong>PDF</strong>, maks. 2MB.
                        </small>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="detail_peminjaman.php?id=<?= (int)$id_pinjam; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tglMulai   = document.querySelector('input[name="tanggal_mulai"]');
    const tglSelesai = document.querySelector('input[name="tanggal_selesai"]');
    const fileInput  = document.getElementById('dokumen_peminjaman');

    const today = new Date().toISOString().split('T')[0];
    if (tglMulai && !tglMulai.value)   tglMulai.setAttribute('min', today);
    if (tglSelesai && !tglSelesai.value) tglSelesai.setAttribute('min', today);

    if (tglMulai && tglSelesai) {
        tglMulai.addEventListener('change', function () {
            if (tglMulai.value) {
                tglSelesai.min = tglMulai.value;
                if (tglSelesai.value && tglSelesai.value < tglMulai.value) {
                    tglSelesai.value = tglMulai.value;
                }
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            const name = file.name.toLowerCase();
            const ext  = name.split('.').pop();

            if (ext !== 'pdf') {
                alert('Dokumen peminjaman harus berformat PDF.');
                this.value = '';
                return;
            }

            const maxSize = 2 * 1024 * 1024; // 2MB
            if (file.size > maxSize) {
                alert('Ukuran file maksimal 2MB.');
                this.value = '';
            }
        });
    }
});
</script>

<?php include '../includes/peminjam/footer.php'; ?>
