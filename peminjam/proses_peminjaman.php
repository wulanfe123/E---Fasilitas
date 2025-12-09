<?php
session_start();
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/notifikasi_helper.php';

/* =========================================================
   VALIDASI SESSION USER DENGAN KETAT
   ========================================================= */
$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Nama user untuk keperluan display (opsional)
$nama_user = htmlspecialchars($_SESSION['nama_user'] ?? $_SESSION['nama'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8');

/* =========================================================
   HANYA TERIMA METODE POST
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: form_peminjaman.php");
    exit;
}

/* =========================================================
   AMBIL & VALIDASI INPUT (SERVER SIDE)
   ========================================================= */
$errors = [];

/* ---- 1. FASILITAS (WAJIB, MIN 1) ---- */
$fasilitas = isset($_POST['fasilitas']) ? $_POST['fasilitas'] : [];
if (!is_array($fasilitas) || count($fasilitas) === 0) {
    $errors[] = "Pilih minimal satu fasilitas.";
} else {
    // Pastikan semua id_fasilitas adalah integer positif
    $fasilitas = array_filter(array_map('intval', $fasilitas), function($v) {
        return $v > 0;
    });
    if (count($fasilitas) === 0) {
        $errors[] = "Data fasilitas tidak valid.";
    }
}

/* ---- 2. TANGGAL MULAI & SELESAI (WAJIB) ---- */
$tanggal_mulai   = trim($_POST['tanggal_mulai']   ?? '');
$tanggal_selesai = trim($_POST['tanggal_selesai'] ?? '');

$patternDate = '/^\d{4}-\d{2}-\d{2}$/';

if ($tanggal_mulai === '' || !preg_match($patternDate, $tanggal_mulai)) {
    $errors[] = "Tanggal mulai tidak valid.";
}
if ($tanggal_selesai === '' || !preg_match($patternDate, $tanggal_selesai)) {
    $errors[] = "Tanggal selesai tidak valid.";
}

$today = date('Y-m-d');

if ($tanggal_mulai !== '' && $tanggal_mulai < $today) {
    $errors[] = "Tanggal mulai tidak boleh sebelum hari ini.";
}
if ($tanggal_mulai !== '' && $tanggal_selesai !== '' && $tanggal_selesai < $tanggal_mulai) {
    $errors[] = "Tanggal selesai tidak boleh sebelum tanggal mulai.";
}

/* ---- 3. CATATAN (WAJIB, MIN 20 KARAKTER) ---- */
$catatan = trim($_POST['catatan'] ?? '');

if ($catatan === '') {
    $errors[] = "Catatan wajib diisi.";
} elseif (mb_strlen($catatan) < 20) {
    $errors[] = "Catatan terlalu pendek (minimal 20 karakter).";
} elseif (mb_strlen($catatan) > 500) {
    $errors[] = "Catatan terlalu panjang (maksimal 500 karakter).";
}

/* ---- 4. FILE DOKUMEN (WAJIB, HANYA PDF) ---- */
$uploadDir   = __DIR__ . '/../uploads/dokumen_peminjaman/';
$relativeDir = '../uploads/dokumen_peminjaman/';
$dokumenPath = null;

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

$file = null;
if (!isset($_FILES['dokumen_peminjaman']) || $_FILES['dokumen_peminjaman']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors[] = "Dokumen peminjaman wajib diupload (format PDF).";
} else {
    $file      = $_FILES['dokumen_peminjaman'];
    $err       = $file['error'];
    $fileName  = $file['name'];
    $tmpName   = $file['tmp_name'];
    $fileSize  = $file['size'];

    if ($err !== UPLOAD_ERR_OK) {
        $errors[] = "Terjadi kesalahan saat mengupload dokumen (error code: $err).";
    } else {
        $fileNameLower = strtolower($fileName);
        $ext = pathinfo($fileNameLower, PATHINFO_EXTENSION);

        if ($ext !== 'pdf') {
            $errors[] = "Jenis file harus PDF.";
        }

        // Batas ukuran 5MB
        $maxSize = 5 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            $errors[] = "Ukuran file maksimal 5MB.";
        }
        
        // Validasi MIME type untuk keamanan
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        
        if ($mimeType !== 'application/pdf') {
            $errors[] = "File bukan PDF asli. Upload ditolak.";
        }
    }
}

/* =========================================================
   JIKA ADA ERROR VALIDASI → KEMBALIKAN KE FORM
   ========================================================= */
if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", $errors);
    header("Location: form_peminjaman.php");
    exit;
}

/* =========================================================
   JIKA ADA FILE & VALID, PINDAHKAN KE FOLDER UPLOAD
   ========================================================= */
if ($file && $file['error'] === UPLOAD_ERR_OK) {
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
    $newName  = 'peminjaman_' . $id_user . '_' . time() . '_' . $safeName;
    $target   = $uploadDir . $newName;

    if (!move_uploaded_file($tmpName, $target)) {
        $_SESSION['error'] = "Gagal menyimpan dokumen di server.";
        header("Location: form_peminjaman.php");
        exit;
    }

    $dokumenPath = $relativeDir . $newName;
}

/* =========================================================
   SIMPAN KE DATABASE (TRANSAKSI + PREPARED STATEMENT)
   TABEL: peminjaman & daftar_peminjaman_fasilitas
   ========================================================= */
$conn->begin_transaction();

try {
    /* ---------- 1) INSERT KE TABEL peminjaman ---------- */
    $statusAwal = 'usulan';

    $sqlInsertP = "
        INSERT INTO peminjaman 
        (id_user, tanggal_mulai, tanggal_selesai, status, catatan, dokumen_peminjaman)
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmtP = $conn->prepare($sqlInsertP);
    if (!$stmtP) {
        throw new Exception("Gagal mempersiapkan query peminjaman: " . $conn->error);
    }

    $stmtP->bind_param(
        "isssss",
        $id_user,
        $tanggal_mulai,
        $tanggal_selesai,
        $statusAwal,
        $catatan,
        $dokumenPath
    );

    if (!$stmtP->execute()) {
        throw new Exception("Gagal menyimpan data peminjaman: " . $stmtP->error);
    }

    $id_pinjam_baru = $stmtP->insert_id;
    $stmtP->close();

    /* ---------- 2) INSERT KE TABEL daftar_peminjaman_fasilitas ---------- */
    $sqlInsertDF = "
        INSERT INTO daftar_peminjaman_fasilitas (id_pinjam, id_fasilitas)
        VALUES (?, ?)
    ";
    $stmtDF = $conn->prepare($sqlInsertDF);
    if (!$stmtDF) {
        throw new Exception("Gagal mempersiapkan query daftar_peminjaman_fasilitas: " . $conn->error);
    }

    foreach ($fasilitas as $id_fas) {
        $id_fas_int = (int)$id_fas;
        if ($id_fas_int <= 0) continue;

        $stmtDF->bind_param("ii", $id_pinjam_baru, $id_fas_int);
        if (!$stmtDF->execute()) {
            throw new Exception("Gagal menyimpan fasilitas yang dipinjam: " . $stmtDF->error);
        }
    }
    $stmtDF->close();

    /* ---------- 3) COMMIT TRANSAKSI ---------- */
    $conn->commit();

    /* ========================================================= 
       4) KIRIM NOTIFIKASI SETELAH DATA BERHASIL TERSIMPAN
       ========================================================= */
    notif_peminjaman_baru($conn, $id_pinjam_baru, $id_user);

    $_SESSION['success'] = "Pengajuan peminjaman berhasil dikirim. Silakan pantau status pada menu 'Peminjaman Saya'.";
    header("Location: peminjaman_saya.php");
    exit;

} catch (Exception $e) {
    $conn->rollback();

    // Kalau file sudah terupload, tapi DB gagal → hapus file
    if ($dokumenPath && file_exists($uploadDir . basename($dokumenPath))) {
        @unlink($uploadDir . basename($dokumenPath));
    }

    $_SESSION['error'] = "Terjadi kesalahan: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    header("Location: form_peminjaman.php");
    exit;
}
