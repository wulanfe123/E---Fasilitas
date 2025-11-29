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

/* =========================================================
   HANYA TERIMA METODE POST
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: peminjaman");
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
$tanggal_mulai   = $_POST['tanggal_mulai']   ?? '';
$tanggal_selesai = $_POST['tanggal_selesai'] ?? '';

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

/* ---- 3. CATATAN (OPSIONAL, SANITASI) ---- */
$catatan = trim($_POST['catatan'] ?? '');
if (strlen($catatan) > 1000) {
    $errors[] = "Catatan terlalu panjang (maksimal 1000 karakter).";
}

/* ---- 4. FILE DOKUMEN (OPSIONAL, HANYA PDF) ---- */
$uploadDir   = __DIR__ . '/../uploads/dokumen_peminjaman/';
$relativeDir = '../uploads/dokumen_peminjaman/'; // disimpan di DB
$dokumenPath = null;

if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

if (isset($_FILES['dokumen_peminjaman']) && $_FILES['dokumen_peminjaman']['error'] !== UPLOAD_ERR_NO_FILE) {
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

        // Batas ukuran 2MB (bisa diubah)
        $maxSize = 2 * 1024 * 1024;
        if ($fileSize > $maxSize) {
            $errors[] = "Ukuran file maksimal 2MB.";
        }
    }
}

/* =========================================================
   JIKA ADA ERROR VALIDASI → KEMBALIKAN KE FORM
   ========================================================= */
if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", $errors);
    header("Location: form_peminjaman");
    exit;
}

/* =========================================================
   JIKA ADA FILE & VALID, PINDAHKAN KE FOLDER UPLOAD
   ========================================================= */
if (isset($file) && $file['error'] === UPLOAD_ERR_OK && empty($errors)) {
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $fileName);
    $newName  = 'peminjaman_' . $id_user . '_' . time() . '_' . $safeName;
    $target   = $uploadDir . $newName;

    if (!move_uploaded_file($tmpName, $target)) {
        $_SESSION['error'] = "Gagal menyimpan dokumen di server.";
        header("Location: form_peminjaman");
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
    // DISINI PERBAIKAN: HAPUS created_at KARENA TIDAK ADA DI TABEL
    // Pastikan tabel kamu punya kolom:
    // id_pinjam (AI), id_user, tanggal_mulai, tanggal_selesai, status, catatan, dokumen_peminjaman
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

    /* ---------- 3) (OPSIONAL) BUAT NOTIFIKASI UNTUK PEMINJAM ---------- */
    // DI SINI created_at BOLEH, KARENA TABEL notifikasi MEMANG PAKAI KOLUM ITU DI SEMUA FILE KAMU
    $sqlNotif = "
        INSERT INTO notifikasi (id_user, id_pinjam, judul, pesan, tipe, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ";
    $stmtN = $conn->prepare($sqlNotif);
    if ($stmtN) {
        $judulNotif = "Pengajuan Peminjaman Dikirim";
        $pesanNotif = "Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.";
        $tipe       = "peminjaman";

        $stmtN->bind_param("iisss", $id_user, $id_pinjam_baru, $judulNotif, $pesanNotif, $tipe);
        $stmtN->execute();
        $stmtN->close();
    }

    /* ---------- 4) COMMIT TRANSAKSI ---------- */
    $conn->commit();

    $_SESSION['success'] = "Pengajuan peminjaman berhasil dikirim. Silakan pantau status pada menu 'Peminjaman'.";
    header("Location: peminjaman.php");
    exit;

} catch (Exception $e) {
    $conn->rollback();

    // Kalau file sudah terupload, tapi DB gagal → hapus file
    if ($dokumenPath && file_exists($uploadDir . basename($dokumenPath))) {
        @unlink($uploadDir . basename($dokumenPath));
    }

    $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
    header("Location: form_peminjaman.php");
    exit;
}
