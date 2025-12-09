<?php
// batalkan_peminjaman.php
session_start();

// ==========================
// CEK LOGIN
// ==========================
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

include '../config/koneksi.php';
include '../config/notifikasi_helper.php';

// Validasi ID user dari session
$id_user = filter_var($_SESSION['id_user'], FILTER_VALIDATE_INT);
if ($id_user === false || $id_user <= 0) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Hanya terima metode GET (sesuai tombol link di peminjaman_saya.php)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $_SESSION['error'] = "Metode permintaan tidak valid.";
    header("Location: peminjaman_saya.php");
    exit;
}

// ==========================
// AMBIL & VALIDASI id_pinjam
// ==========================
$id_raw    = $_GET['id'] ?? '';
$id_pinjam = filter_var($id_raw, FILTER_VALIDATE_INT);

if ($id_pinjam === false || $id_pinjam <= 0) {
    $_SESSION['error'] = "ID peminjaman tidak valid.";
    header("Location: peminjaman_saya.php");
    exit;
}

try {
    // Mulai transaksi supaya update + notifikasi konsisten
    if (method_exists($conn, 'begin_transaction')) {
        $conn->begin_transaction();
    }
    $sqlCek = "
        SELECT 
            id_pinjam,
            id_user,
            status
        FROM peminjaman
        WHERE id_pinjam = ?
        LIMIT 1
    ";

    $stmtCek = $conn->prepare($sqlCek);
    if (!$stmtCek) {
        throw new Exception("Gagal menyiapkan query cek peminjaman.");
    }

    $stmtCek->bind_param("i", $id_pinjam);
    $stmtCek->execute();
    $resCek = $stmtCek->get_result();
    $data   = $resCek ? $resCek->fetch_assoc() : null;
    $stmtCek->close();

    if (!$data) {
        throw new Exception("Data peminjaman tidak ditemukan.");
    }

    // Pastikan peminjaman milik user yang login
    if ((int)$data['id_user'] !== $id_user) {
        throw new Exception("Anda tidak berhak membatalkan peminjaman ini.");
    }

    $status = strtolower(trim($data['status'] ?? ''));

    // Hanya boleh batal jika masih USULAN
    if ($status !== 'usulan') {
        throw new Exception(
            "Peminjaman tidak dapat dibatalkan karena sudah diproses "
            . "(status saat ini: " . strtoupper($status) . ")."
        );
    }

    // ==========================
    // UPDATE STATUS MENJADI 'dibatalkan'
    // (kalau mau dihapus total, bisa ganti dengan DELETE)
    // ==========================
    $sqlUpd = "
        UPDATE peminjaman
        SET status = 'dibatalkan'
        WHERE id_pinjam = ?
        LIMIT 1
    ";

    $stmtUpd = $conn->prepare($sqlUpd);
    if (!$stmtUpd) {
        throw new Exception("Gagal menyiapkan query pembatalan peminjaman.");
    }

    $stmtUpd->bind_param("i", $id_pinjam);
    if (!$stmtUpd->execute()) {
        $stmtUpd->close();
        throw new Exception("Gagal membatalkan peminjaman.");
    }
    $stmtUpd->close();

    // ==========================
    // KIRIM NOTIFIKASI KE ADMIN
    // ==========================
    // Beri tahu semua admin bahwa peminjam membatalkan pengajuan ini
    if (function_exists('notif_get_admin_ids') && function_exists('tambah_notif')) {
        $adminIds = notif_get_admin_ids($conn);
        $judul    = "Pengajuan Peminjaman Dibatalkan";
        $pesan    = "Pengajuan peminjaman fasilitas dengan ID #{$id_pinjam} telah dibatalkan oleh peminjam sebelum diproses.";

        foreach ($adminIds as $id_admin) {
            // tipe 'peminjaman' supaya diarahkan ke halaman peminjaman di admin
            tambah_notif($conn, (int)$id_admin, $id_pinjam, $judul, $pesan, 'peminjaman');
        }
    }

    // Commit transaksi
    if (method_exists($conn, 'commit')) {
        $conn->commit();
    }

    $_SESSION['success'] = "Pengajuan peminjaman #{$id_pinjam} berhasil dibatalkan.";

} catch (Exception $e) {
    // Rollback kalau ada error
    if (method_exists($conn, 'rollback')) {
        $conn->rollback();
    }

    // Simpan pesan error ke session
    $_SESSION['error'] = $e->getMessage();
}
header("Location: peminjaman_saya.php");
exit;
