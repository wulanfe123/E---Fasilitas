<?php
if (!function_exists('tambah_notif')) {

    function tambah_notif(mysqli $conn, int $id_user, int $id_pinjam, string $judul, string $pesan, string $tipe): void
    {
        $sql = "
            INSERT INTO notifikasi
                (id_user, id_pinjam, judul, pesan, tipe, is_read, created_at, dibaca)
            VALUES
                (?, ?, ?, ?, ?, 0, NOW(), NULL)
        ";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("iisss", $id_user, $id_pinjam, $judul, $pesan, $tipe);
            $stmt->execute();
            $stmt->close();
        }
    }

    /* -------------------------------------------------------
     * AMBIL ID PEMINJAM DARI TABEL PEMINJAMAN
     * ----------------------------------------------------- */
    function notif_get_id_peminjam(mysqli $conn, int $id_pinjam): int
    {
        $sql = "SELECT id_user FROM peminjaman WHERE id_pinjam = ? LIMIT 1";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $id_pinjam);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if ($row && isset($row['id_user'])) {
                return (int)$row['id_user'];
            }
        }
        return 0;
    }

    /* -------------------------------------------------------
     * AMBIL SEMUA ID ADMIN (super_admin + bagian_umum)
     * ----------------------------------------------------- */
    function notif_get_admin_ids(mysqli $conn): array
    {
        $ids = [];
        $sql = "SELECT id_user FROM users WHERE role IN ('super_admin','bagian_umum')";
        if ($res = $conn->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)$row['id_user'];
            }
            $res->free();
        }
        return $ids;
    }

    /* =======================================================
     * 1. PENGAJUAN PEMINJAMAN BARU (oleh PEMINJAM)
     *    tipe: 'peminjaman'
     * ===================================================== */
    function notif_peminjaman_baru(mysqli $conn, int $id_pinjam, int $id_peminjam = 0): void
    {
        if ($id_peminjam <= 0) {
            $id_peminjam = notif_get_id_peminjam($conn, $id_pinjam);
        }

        $tipe = 'peminjaman';

        // Notif ke PEMINJAM
        if ($id_peminjam > 0) {
            $judulPeminjam = "Pengajuan Peminjaman Dikirim";
            $pesanPeminjam = "Permohonan peminjaman fasilitas Anda (ID #{$id_pinjam}) telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.";
            tambah_notif($conn, $id_peminjam, $id_pinjam, $judulPeminjam, $pesanPeminjam, $tipe);
        }

        // Notif ke semua ADMIN
        $adminIds = notif_get_admin_ids($conn);
        foreach ($adminIds as $id_admin) {
            $judulAdmin = "Pengajuan Peminjaman Baru";
            $pesanAdmin = "Terdapat pengajuan peminjaman fasilitas baru dengan ID #{$id_pinjam}. Silakan lakukan review dan proses persetujuan.";
            tambah_notif($conn, $id_admin, $id_pinjam, $judulAdmin, $pesanAdmin, $tipe);
        }
    }

    /* =======================================================
     * 2. PEMINJAMAN DISETUJUI (oleh ADMIN)
     *    tipe: 'peminjaman'
     * ===================================================== */
    function notif_peminjaman_disetujui(mysqli $conn, int $id_pinjam, int $id_peminjam = 0): void
    {
        if ($id_peminjam <= 0) {
            $id_peminjam = notif_get_id_peminjam($conn, $id_pinjam);
        }

        if ($id_peminjam > 0) {
            $tipe  = 'peminjaman';
            $judul = "Peminjaman Disetujui";
            $pesan = "Peminjaman fasilitas dengan ID #{$id_pinjam} telah DISETUJUI. Silakan cek jadwal dan ketentuan penggunaan fasilitas.";
            tambah_notif($conn, $id_peminjam, $id_pinjam, $judul, $pesan, $tipe);
        }
    }

    /* =======================================================
     * 3. PEMINJAMAN DITOLAK (oleh ADMIN)
     *    tipe: 'peminjaman'
     * ===================================================== */
    function notif_peminjaman_ditolak(mysqli $conn, int $id_pinjam, string $alasan = '', int $id_peminjam = 0): void
    {
        if ($id_peminjam <= 0) {
            $id_peminjam = notif_get_id_peminjam($conn, $id_pinjam);
        }

        if ($id_peminjam > 0) {
            $tipe  = 'peminjaman';
            $judul = "Peminjaman Ditolak";
            $pesan = "Peminjaman fasilitas dengan ID #{$id_pinjam} DITOLAK.";
            if ($alasan !== '') {
                $pesan .= " Alasan: {$alasan}";
            }
            tambah_notif($conn, $id_peminjam, $id_pinjam, $judul, $pesan, $tipe);
        }
    }

    /* =======================================================
     * 4. PENGEMBALIAN FASILITAS (oleh PEMINJAM)
     *    tipe: 'pengembalian'
     * ===================================================== */
    function notif_pengembalian_baru(mysqli $conn, int $id_pinjam, int $id_peminjam = 0): void
    {
        if ($id_peminjam <= 0) {
            $id_peminjam = notif_get_id_peminjam($conn, $id_pinjam);
        }

        $tipe = 'pengembalian';

        // Notif ke PEMINJAM (opsional)
        if ($id_peminjam > 0) {
            $judulPeminjam = "Pengembalian Fasilitas Dikirim";
            $pesanPeminjam = "Pengajuan pengembalian fasilitas untuk peminjaman ID #{$id_pinjam} telah tersimpan. Menunggu verifikasi dari Bagian Umum.";
            tambah_notif($conn, $id_peminjam, $id_pinjam, $judulPeminjam, $pesanPeminjam, $tipe);
        }

        // Notif ke semua ADMIN
        $adminIds = notif_get_admin_ids($conn);
        foreach ($adminIds as $id_admin) {
            $judulAdmin = "Pengembalian Fasilitas";
            $pesanAdmin = "Terdapat pengembalian fasilitas untuk peminjaman ID #{$id_pinjam}. Silakan cek dan verifikasi kondisi fasilitas.";
            tambah_notif($conn, $id_admin, $id_pinjam, $judulAdmin, $pesanAdmin, $tipe);
        }
    }

    /* =======================================================
     * 5. PENGEMBALIAN DICEK & DINYATAKAN RUSAK
     *    tipe: 'tindaklanjut'
     * ===================================================== */
    function notif_pengembalian_rusak(mysqli $conn, int $id_pinjam, string $catatanRusak = '', int $id_peminjam = 0): void
    {
        if ($id_peminjam <= 0) {
            $id_peminjam = notif_get_id_peminjam($conn, $id_pinjam);
        }

        $tipe  = 'tindaklanjut';
        $judul = "Tindak Lanjut Pengembalian (Kerusakan)";

        $pesan = "Pada peminjaman ID #{$id_pinjam}, fasilitas dinyatakan RUSAK dan memerlukan tindak lanjut.";
        if ($catatanRusak !== '') {
            $pesan .= " Keterangan: {$catatanRusak}";
        }

        // Notif ke PEMINJAM
        if ($id_peminjam > 0) {
            tambah_notif($conn, $id_peminjam, $id_pinjam, $judul, $pesan, $tipe);
        }

        // Notif ke semua ADMIN
        $adminIds = notif_get_admin_ids($conn);
        foreach ($adminIds as $id_admin) {
            tambah_notif($conn, $id_admin, $id_pinjam, $judul, $pesan, $tipe);
        }
    }

    /* =======================================================
     * 6. KOMPLAIN / LAPORAN KERUSAKAN BARU DARI PEMINJAM
     *    tipe: 'tindaklanjut'
     * ===================================================== */
    function notif_komplain_baru(mysqli $conn, int $id_pinjam, int $id_peminjam = 0): void
    {
        if ($id_peminjam <= 0) {
            $id_peminjam = notif_get_id_peminjam($conn, $id_pinjam);
        }

        $tipe  = 'tindaklanjut';
        $judul = "Komplain / Laporan Kerusakan Baru";
        $pesan = "Terdapat komplain atau laporan kerusakan terkait peminjaman ID #{$id_pinjam} dari peminjam.";

        // Notif ke semua ADMIN
        $adminIds = notif_get_admin_ids($conn);
        foreach ($adminIds as $id_admin) {
            tambah_notif($conn, $id_admin, $id_pinjam, $judul, $pesan, $tipe);
        }
    }

    /* =======================================================
     * 7. UPDATE TINDAK LANJUT / BALASAN ADMIN KE PEMINJAM
     *    tipe: 'tindaklanjut'
     * ===================================================== */
    function notif_tindaklanjut_update(mysqli $conn, int $id_pinjam, string $pesanUpdate, int $id_peminjam = 0): void
    {
        if ($id_peminjam <= 0) {
            $id_peminjam = notif_get_id_peminjam($conn, $id_pinjam);
        }

        if ($id_peminjam > 0) {
            $tipe  = 'tindaklanjut';
            $judul = "Update Tindak Lanjut Peminjaman";

            $pesan = "Ada pembaruan tindak lanjut untuk peminjaman ID #{$id_pinjam}.";
            if ($pesanUpdate !== '') {
                $pesan .= " Keterangan: {$pesanUpdate}";
            }

            tambah_notif($conn, $id_peminjam, $id_pinjam, $judul, $pesan, $tipe);
        }
    }

    /* =======================================================
     * 8. FUNGSI TAMBAHAN UNTUK ICON & POPUP (BADGE ANGKA)
     * ===================================================== */

    function notif_hitung_belum_dibaca(mysqli $conn, int $id_user): int
    {
        $sql = "SELECT COUNT(*) AS jml 
                FROM notifikasi 
                WHERE id_user = ? AND is_read = 0";

        $jml = 0;
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $id_user);
            $stmt->execute();
            $stmt->bind_result($jml);
            $stmt->fetch();
            $stmt->close();
        }
        return (int)$jml;
    }

    function notif_list_user(mysqli $conn, int $id_user, int $limit = 10): array
    {
        $data = [];
        $sql = "
            SELECT 
                id_notifikasi,
                id_pinjam,
                judul,
                pesan,
                tipe,
                is_read,
                created_at
            FROM notifikasi
            WHERE id_user = ?
            ORDER BY created_at DESC
            LIMIT ?
        ";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ii", $id_user, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }
    function notif_tandai_sudah_dibaca(mysqli $conn, int $id_user): void
    {
        $sql = "
            UPDATE notifikasi
            SET is_read = 1, dibaca = NOW()
            WHERE id_user = ? AND is_read = 0
        ";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $id_user);
            $stmt->execute();
            $stmt->close();
        }
    }
}
