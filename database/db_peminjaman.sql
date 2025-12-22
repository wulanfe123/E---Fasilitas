-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 22, 2025 at 06:50 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_peminjaman`
--

-- --------------------------------------------------------

--
-- Table structure for table `daftar_peminjaman_fasilitas`
--

CREATE TABLE `daftar_peminjaman_fasilitas` (
  `id` int(11) NOT NULL,
  `id_pinjam` int(11) NOT NULL,
  `id_fasilitas` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daftar_peminjaman_fasilitas`
--

INSERT INTO `daftar_peminjaman_fasilitas` (`id`, `id_pinjam`, `id_fasilitas`) VALUES
(23, 24, 6),
(28, 29, 6),
(30, 31, 6),
(31, 32, 8),
(37, 38, 8),
(38, 39, 9),
(39, 40, 6),
(40, 41, 9),
(49, 50, 9),
(53, 54, 10);

-- --------------------------------------------------------

--
-- Table structure for table `fasilitas`
--

CREATE TABLE `fasilitas` (
  `id_fasilitas` int(11) NOT NULL,
  `nama_fasilitas` varchar(100) NOT NULL,
  `kategori` enum('ruangan','kendaraan','pendukung') NOT NULL,
  `lokasi` varchar(20) NOT NULL,
  `ketersediaan` varchar(20) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `gambar` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fasilitas`
--

INSERT INTO `fasilitas` (`id_fasilitas`, `nama_fasilitas`, `kategori`, `lokasi`, `ketersediaan`, `keterangan`, `gambar`) VALUES
(6, 'Lapangan', 'pendukung', 'Rusunawa', 'tersedia', 'Lapangan olahraga', 0x6661735f313736343530353235375f393734312e6a7067),
(8, 'Mini Conference', 'ruangan', 'Gedung GKT1 Lantai 3', 'tersedia', 'Aula ADM', 0x6661735f313736343530353634365f393634372e6a7067),
(9, 'Aula TI', 'ruangan', 'Gedung Teknik Inform', 'tersedia', 'Aula Teknik Informatika', 0x6661735f313736343530353632375f393338392e6a706567),
(10, 'Aula Bahasa', 'ruangan', 'Gedung GKT2 Lantai 2', 'tidak_tersedia', 'Aula kegiatan bahasa.', 0x6661735f313736343530353631355f323936372e6a7067),
(11, 'Ruang Rapat GKT3', 'ruangan', 'Gedung GKT3 Lantai 2', 'tersedia', 'Ruang rapat prodi.', 0x6661735f313736343530353237335f313532322e6a7067),
(12, 'Lapangan Basket', '', 'Rusunawa', 'tersedia', 'Lapangan basket mahasiswa.', ''),
(13, 'Mushalla', 'pendukung', 'Gedung GKT4 Lantai 1', 'tersedia', 'Mushalla untuk ibadah mahasiswa.', 0x6661735f313736343530353234365f353331302e6a7067),
(14, 'BM 6008 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Honda/AFX12U21C08 M/T, Tahun 2016, Kondisi Baik, Operasional', ''),
(15, 'BM 3850 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Honda/GL15B1DF M/T, Tahun 2015, Kondisi Baik, Operasional', ''),
(16, 'BM 3851 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Honda/GL15B1DF M/T, Tahun 2015, Kondisi Baik, Operasional', ''),
(17, 'BM 1055 E', 'kendaraan', 'Bagian Umum', 'tersedia', 'Toyota/New Avanza 1.5G M/T, Tahun 2012, Kondisi Baik, Operasional', ''),
(18, 'BM 1075 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Toyota/Rush 1.5 G M/T, Tahun 2015, Kondisi Baik, Operasional', ''),
(19, 'BM 1096 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Suzuki/AV1414F DX (4X2) MT, Tahun 2016, Kondisi Baik, Operasional', ''),
(20, 'BM 8338 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Suzuki/GC 415 T (4X2) M/T, Tahun 2015, Kondisi Baik, Operasional', ''),
(21, 'BM 8356 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Suzuki/GC415T 4X2 MT, Tahun 2016, Kondisi Baik, Operasional Kemaritiman', ''),
(22, 'BM 8357 D', 'kendaraan', 'Bagian Umum1', 'tersedia', 'Suzuki/GC415T 4X2 MT, Tahun 2016, Kondisi Baik, Operasional', ''),
(23, 'BM 7135 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Toyota/Hiace Commuter M/T (KDH222R-LEMDY), Tahun 2018, Kondisi Baik, Operasional', ''),
(24, 'BM 7136 D', 'kendaraan', 'Bagian Umum', 'tersedia', 'Toyota/Hiace Commuter M/T (KDH222R-LEMDY), Tahun 2018, Kondisi Baik, Operasional', 0x6661735f313736343134393138345f353634352e6a7067);

-- --------------------------------------------------------

--
-- Table structure for table `komunikasi_kerusakan`
--

CREATE TABLE `komunikasi_kerusakan` (
  `id_chat` int(11) NOT NULL,
  `id_pinjam` int(11) NOT NULL,
  `id_tindaklanjut` int(11) DEFAULT NULL,
  `id_kembali` int(11) DEFAULT NULL,
  `id_user` int(11) NOT NULL,
  `peran_pengirim` enum('peminjam','bagian_umum','super_admin') NOT NULL,
  `pesan` text NOT NULL,
  `dibaca_admin` tinyint(1) NOT NULL DEFAULT 0,
  `dibaca_peminjam` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `komunikasi_kerusakan`
--

INSERT INTO `komunikasi_kerusakan` (`id_chat`, `id_pinjam`, `id_tindaklanjut`, `id_kembali`, `id_user`, `peran_pengirim`, `pesan`, `dibaca_admin`, `dibaca_peminjam`, `created_at`) VALUES
(1, 25, 8, NULL, 13, 'super_admin', 'bagaimana?', 0, 0, '2025-12-02 15:27:32'),
(2, 25, 8, NULL, 18, 'peminjam', 'sudah diperbaiki pak', 0, 0, '2025-12-02 19:01:25'),
(3, 38, 11, NULL, 7, 'peminjam', 'y', 0, 1, '2025-12-08 14:00:30'),
(4, 38, 11, NULL, 16, 'bagian_umum', 'y', 0, 0, '2025-12-08 14:39:26'),
(5, 32, 10, NULL, 18, 'peminjam', 'sudah', 0, 1, '2025-12-09 00:50:48'),
(6, 50, 13, NULL, 18, 'peminjam', 'axas', 0, 1, '2025-12-10 15:06:24'),
(7, 50, 13, NULL, 18, 'peminjam', 'h12jbwer', 0, 1, '2025-12-10 16:04:02');

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id_notifikasi` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_pinjam` int(11) DEFAULT NULL,
  `judul` varchar(100) NOT NULL,
  `pesan` text NOT NULL,
  `tipe` enum('success','warning','info') NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `dibaca` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifikasi`
--

INSERT INTO `notifikasi` (`id_notifikasi`, `id_user`, `id_pinjam`, `judul`, `pesan`, `tipe`, `is_read`, `created_at`, `dibaca`) VALUES
(103, 7, 24, 'Peminjaman Diajukan', 'Pengajuan peminjaman fasilitas Anda telah dikirim dan menunggu verifikasi Bagian Umum.', 'info', 1, '2025-12-01 04:22:25', 0),
(106, 13, 24, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman baru dari mildaa (ID: 24) yang menunggu verifikasi.', 'warning', 1, '2025-12-01 04:22:25', 0),
(107, 16, 24, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman baru dari mildaa (ID: 24) yang menunggu verifikasi.', 'warning', 1, '2025-12-01 04:22:25', 0),
(116, 18, 29, 'Pengajuan Peminjaman Dikirim', 'Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.', '', 1, '2025-12-02 18:25:39', 0),
(118, 18, 31, 'Pengajuan Peminjaman Dikirim', 'Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.', '', 1, '2025-12-03 06:59:03', 0),
(119, 18, 32, 'Pengajuan Peminjaman Dikirim', 'Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.', '', 1, '2025-12-03 12:15:44', 0),
(120, 18, 32, 'Tindak Lanjut Kerusakan Peminjaman #32', 'Petugas telah membuat/ memperbarui tindak lanjut kerusakan untuk peminjaman #32. Status tindak lanjut: proses.', 'info', 1, '2025-12-03 12:17:22', 0),
(127, 7, 38, 'Pengajuan Peminjaman Dikirim', 'Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.', '', 1, '2025-12-08 05:22:08', 0),
(128, 13, 38, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #38 dari Peminjam. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-08 05:22:08', 0),
(129, 16, 38, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #38 dari Peminjam. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-08 05:22:08', 0),
(130, 13, 38, 'Pengembalian Fasilitas', 'Peminjam mildaa telah mengkonfirmasi pengembalian fasilitas (ID Peminjaman: #38). Silakan verifikasi kondisi fasilitas.', '', 1, '2025-12-08 05:27:33', 0),
(131, 16, 38, 'Pengembalian Fasilitas', 'Peminjam mildaa telah mengkonfirmasi pengembalian fasilitas (ID Peminjaman: #38). Silakan verifikasi kondisi fasilitas.', '', 1, '2025-12-08 05:27:33', 0),
(132, 7, 38, 'Pengembalian Dikonfirmasi', 'Konfirmasi pengembalian fasilitas Anda telah diterima. Bagian Umum akan memverifikasi kondisi fasilitas. Status peminjaman telah dipindahkan ke riwayat.', '', 1, '2025-12-08 05:27:33', 0),
(133, 18, 39, 'Pengajuan Peminjaman Dikirim', 'Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.', '', 1, '2025-12-08 17:29:28', 127),
(134, 13, 39, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #39. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-08 17:29:28', 0),
(135, 16, 39, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #39. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-08 17:29:28', 0),
(136, 7, 40, 'Pengajuan Peminjaman Dikirim', 'Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.', '', 1, '2025-12-09 00:49:48', 0),
(137, 13, 40, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #40 dari Peminjam. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-09 00:49:48', 0),
(138, 16, 40, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #40 dari Peminjam. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-09 00:49:48', 0),
(139, 13, 40, 'Pengembalian Fasilitas', 'Peminjam mildaa telah mengkonfirmasi pengembalian fasilitas (ID Peminjaman: #40). Silakan verifikasi kondisi fasilitas.', '', 1, '2025-12-09 00:52:57', 0),
(140, 16, 40, 'Pengembalian Fasilitas', 'Peminjam mildaa telah mengkonfirmasi pengembalian fasilitas (ID Peminjaman: #40). Silakan verifikasi kondisi fasilitas.', '', 1, '2025-12-09 00:52:57', 0),
(141, 7, 40, 'Pengembalian Dikonfirmasi', 'Konfirmasi pengembalian fasilitas Anda telah diterima. Bagian Umum akan memverifikasi kondisi fasilitas. Status peminjaman telah dipindahkan ke riwayat.', '', 1, '2025-12-09 00:52:57', 0),
(142, 18, 41, 'Pengajuan Peminjaman Dikirim', 'Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.', '', 1, '2025-12-09 01:19:59', 0),
(143, 13, 41, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #41. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-09 01:19:59', 0),
(144, 16, 41, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #41. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-09 01:19:59', 0),
(185, 18, 50, 'Pengajuan Peminjaman Dikirim', 'Permohonan peminjaman fasilitas Anda telah berhasil dikirim dan berstatus USULAN. Silakan menunggu persetujuan dari Bagian Umum.', '', 1, '2025-12-09 02:13:20', 0),
(186, 13, 50, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #50. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-09 02:13:20', 0),
(187, 16, 50, 'Pengajuan Peminjaman Baru', 'Terdapat pengajuan peminjaman fasilitas baru dengan ID #50. Silakan lakukan review dan proses persetujuan.', '', 1, '2025-12-09 02:13:20', 0),
(201, 18, 54, 'Pengembalian Fasilitas Dikirim', 'Pengajuan pengembalian fasilitas untuk peminjaman ID #54 telah tersimpan. Menunggu verifikasi dari Bagian Umum.', '', 1, '2025-12-09 19:24:19', NULL),
(202, 13, 54, 'Pengembalian Fasilitas', 'Terdapat pengembalian fasilitas untuk peminjaman ID #54. Silakan cek dan verifikasi kondisi fasilitas.', '', 1, '2025-12-09 19:24:19', 127),
(203, 16, 54, 'Pengembalian Fasilitas', 'Terdapat pengembalian fasilitas untuk peminjaman ID #54. Silakan cek dan verifikasi kondisi fasilitas.', '', 1, '2025-12-09 19:24:19', 127),
(205, 18, 41, 'Tindak Lanjut Kerusakan Selesai', 'Tindak lanjut kerusakan pada peminjaman #41 telah dinyatakan SELESAI. Fasilitas telah diperbaiki dan peminjaman dinyatakan selesai. Rincian tindak lanjut: Fasilitas mengalami kerusakan dan perlu diperbaiki.', '', 0, '2025-12-12 08:40:34', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id_pinjam` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `tanggal_mulai` date DEFAULT NULL,
  `tanggal_selesai` date DEFAULT NULL,
  `status` enum('draft','usulan','diterima','ditolak','selesai') DEFAULT 'draft',
  `dokumen_peminjaman` varchar(255) DEFAULT NULL,
  `alasan_penolakan` text DEFAULT NULL,
  `tanggal_usulan` timestamp NOT NULL DEFAULT current_timestamp(),
  `catatan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id_pinjam`, `id_user`, `tanggal_mulai`, `tanggal_selesai`, `status`, `dokumen_peminjaman`, `alasan_penolakan`, `tanggal_usulan`, `catatan`) VALUES
(24, 7, '2025-12-01', '2025-12-02', 'selesai', NULL, NULL, '2025-12-01 04:22:25', NULL),
(29, 18, '2025-12-03', '2025-12-04', 'selesai', NULL, NULL, '2025-12-02 18:25:39', 'untuk kegiatan pkm'),
(31, 18, '2025-12-03', '2025-12-04', 'selesai', NULL, NULL, '2025-12-03 06:59:03', ''),
(32, 18, '2025-12-03', '2025-12-04', 'selesai', '../uploads/dokumen_peminjaman/peminjaman_18_1764764144_surat_kuasa_putri.pdf', NULL, '2025-12-03 12:15:44', 'zvvd'),
(38, 7, '2025-12-08', '2025-12-09', 'selesai', 'uploads/dokumen_peminjaman/peminjaman_7_1765171328_Artikel_Terkait_SSDLC.pdf', NULL, '2025-12-08 05:22:08', 'dsasd'),
(39, 18, '2025-12-09', '2025-12-10', 'selesai', '../uploads/dokumen_peminjaman/peminjaman_18_1765214968_Manajemen_Proyek_Wulan.pdf', NULL, '2025-12-08 17:29:28', 'semiar gebdsmdjkzfbm as,sdx'),
(40, 7, '2025-12-09', '2025-12-10', 'selesai', 'uploads/dokumen_peminjaman/peminjaman_7_1765241388_Artikel_Terkait_SSDLC.pdf', NULL, '2025-12-09 00:49:48', 'skdnaksdnkasd'),
(41, 18, '2025-12-16', '2025-12-17', 'selesai', '../uploads/dokumen_peminjaman/peminjaman_18_1765243199_Artikel_Terkait_SSDLC.pdf', NULL, '2025-12-09 01:19:59', 'untuk NASdjaskfmdf1213dxz'),
(50, 18, '2025-12-09', '2025-12-10', 'selesai', '../uploads/dokumen_peminjaman/peminjaman_18_1765246400_Artikel_Terkait_SSDLC.pdf', NULL, '2025-12-09 02:13:20', 'asjdasjfkjasdfmsadahsjdfhlasdkfls'),
(54, 18, '2025-12-09', '2025-12-09', 'selesai', '../uploads/dokumen_peminjaman/peminjaman_18_1765279229_Cetak_Laporan_-_E-Fasilitas.pdf', NULL, '2025-12-09 11:20:29', 'hfjshdsfdfkkshdffjsnsvdfjdskdfskfdhsf');

-- --------------------------------------------------------

--
-- Table structure for table `pengembalian`
--

CREATE TABLE `pengembalian` (
  `id_kembali` int(11) NOT NULL,
  `id_pinjam` int(11) NOT NULL,
  `kondisi` enum('bagus','rusak') DEFAULT 'bagus',
  `catatan` text DEFAULT NULL,
  `tgl_kembali` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengembalian`
--

INSERT INTO `pengembalian` (`id_kembali`, `id_pinjam`, `kondisi`, `catatan`, `tgl_kembali`) VALUES
(17, 32, 'rusak', '', '2025-12-03'),
(21, 38, 'rusak', NULL, '2025-12-08'),
(22, 39, 'rusak', NULL, '2025-12-09'),
(24, 41, 'bagus', NULL, '2025-12-09'),
(25, 50, 'rusak', NULL, '2025-12-09');

-- --------------------------------------------------------

--
-- Table structure for table `tindaklanjut`
--

CREATE TABLE `tindaklanjut` (
  `id_tindaklanjut` int(11) NOT NULL,
  `id_kembali` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `tindakan` varchar(255) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `status` enum('proses','selesai') DEFAULT 'proses',
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tindaklanjut`
--

INSERT INTO `tindaklanjut` (`id_tindaklanjut`, `id_kembali`, `id_user`, `tindakan`, `deskripsi`, `status`, `tanggal`) VALUES
(10, 17, 16, 'Perbaikan fasilitas', 'Fasilitas mengalami kerusakan dan perlu diperbaiki.', 'proses', '2025-12-03 12:17:22'),
(11, 21, 13, 'Perbaikan fasilitass', 'Fasilitas mengalami kerusakan dan perlu diperbaiki.', 'proses', '2025-12-08 05:28:03'),
(12, 22, 13, 'Perbaikan fasilitas', 'Fasilitas mengalami kerusakan dan perlu diperbaiki.', 'proses', '2025-12-09 00:14:58'),
(13, 25, 13, 'Perbaikan fasilitas', 'Fasilitas mengalami kerusakan dan perlu diperbaiki.', 'proses', '2025-12-09 03:34:22'),
(14, 24, 13, 'Perbaikan fasilitas', 'Fasilitas mengalami kerusakan dan perlu diperbaiki.', 'selesai', '2025-12-09 04:54:41');

-- --------------------------------------------------------

--
-- Table structure for table `unit`
--

CREATE TABLE `unit` (
  `id_unit` int(11) NOT NULL,
  `nama_unit` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `unit`
--

INSERT INTO `unit` (`id_unit`, `nama_unit`) VALUES
(1, 'BEM'),
(2, 'Himania'),
(3, 'HMTI');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','bagian_umum','peminjam') DEFAULT 'peminjam',
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `id_unit` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `nama`, `username`, `password`, `email`, `role`, `created`, `last_login`, `id_unit`) VALUES
(7, 'mildaa', 'melda', '$2y$10$XWZldt6vFYw4krOr7WWCSevidRPirAyT/z8nfz6nC1FYYA3gZgeIe', 'melda@gmail.com', 'peminjam', '2025-10-31 11:01:06', '2025-12-15 08:58:13', NULL),
(13, 'yayan', 'yayan', '$2y$10$fdSr3v3QOWev6wHL8oAimOSC4iSqzZvvwM3LM3sSxiMVpr2nI6Gxm', 'yayan@gmail.com', 'super_admin', '2025-11-27 05:44:53', '2025-12-14 19:07:23', NULL),
(16, 'febii1', 'febii', '$2y$10$Vbrj6m30QeglL.iZ.T7t7OUMVPi0PgyqhSZMZkgSFArqxUv8UNVpe', 'febii@gmail.com', 'bagian_umum', '2025-11-27 16:49:39', '2025-12-15 12:00:49', NULL),
(18, 'ayunda', 'ayunda', '$2y$10$n/VfKJSoTXPx8I32VXLmBOdpC7.4m7ysxZMc5UkPNfB.5CRErY5Nu', 'ayunda@gmail.com', 'peminjam', '2025-11-27 17:17:11', '2025-12-14 19:06:57', NULL),
(24, 'Joanda', 'Joanda', '$2y$10$UDAdiQdWKYJagXlKUI05zekvGZ12p.lH4AQInIPrsN4/fe/5Ye3hy', 'joanda@gmail.com', 'peminjam', '2025-12-12 08:43:32', NULL, 3);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `daftar_peminjaman_fasilitas`
--
ALTER TABLE `daftar_peminjaman_fasilitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_fasilitas` (`id_fasilitas`),
  ADD KEY `daftar_peminjaman_fasilitas_ibfk_1` (`id_pinjam`);

--
-- Indexes for table `fasilitas`
--
ALTER TABLE `fasilitas`
  ADD PRIMARY KEY (`id_fasilitas`);

--
-- Indexes for table `komunikasi_kerusakan`
--
ALTER TABLE `komunikasi_kerusakan`
  ADD PRIMARY KEY (`id_chat`),
  ADD KEY `idx_pinjam_tl` (`id_pinjam`,`id_tindaklanjut`),
  ADD KEY `idx_kembali` (`id_kembali`),
  ADD KEY `idx_user` (`id_user`);

--
-- Indexes for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id_notifikasi`),
  ADD KEY `fk_notif_user` (`id_user`),
  ADD KEY `fk_notif_pinjam` (`id_pinjam`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id_pinjam`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD PRIMARY KEY (`id_kembali`),
  ADD KEY `id_pinjam` (`id_pinjam`);

--
-- Indexes for table `tindaklanjut`
--
ALTER TABLE `tindaklanjut`
  ADD PRIMARY KEY (`id_tindaklanjut`),
  ADD KEY `id_kembali` (`id_kembali`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `unit`
--
ALTER TABLE `unit`
  ADD PRIMARY KEY (`id_unit`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `id_unit` (`id_unit`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `daftar_peminjaman_fasilitas`
--
ALTER TABLE `daftar_peminjaman_fasilitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `fasilitas`
--
ALTER TABLE `fasilitas`
  MODIFY `id_fasilitas` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `komunikasi_kerusakan`
--
ALTER TABLE `komunikasi_kerusakan`
  MODIFY `id_chat` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id_notifikasi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=206;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id_pinjam` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `pengembalian`
--
ALTER TABLE `pengembalian`
  MODIFY `id_kembali` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `tindaklanjut`
--
ALTER TABLE `tindaklanjut`
  MODIFY `id_tindaklanjut` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `unit`
--
ALTER TABLE `unit`
  MODIFY `id_unit` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `daftar_peminjaman_fasilitas`
--
ALTER TABLE `daftar_peminjaman_fasilitas`
  ADD CONSTRAINT `daftar_peminjaman_fasilitas_ibfk_1` FOREIGN KEY (`id_pinjam`) REFERENCES `peminjaman` (`id_pinjam`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `daftar_peminjaman_fasilitas_ibfk_2` FOREIGN KEY (`id_fasilitas`) REFERENCES `fasilitas` (`id_fasilitas`);

--
-- Constraints for table `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notif_pinjam` FOREIGN KEY (`id_pinjam`) REFERENCES `peminjaman` (`id_pinjam`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`);

--
-- Constraints for table `pengembalian`
--
ALTER TABLE `pengembalian`
  ADD CONSTRAINT `pengembalian_ibfk_1` FOREIGN KEY (`id_pinjam`) REFERENCES `peminjaman` (`id_pinjam`);

--
-- Constraints for table `tindaklanjut`
--
ALTER TABLE `tindaklanjut`
  ADD CONSTRAINT `tindaklanjut_ibfk_1` FOREIGN KEY (`id_kembali`) REFERENCES `pengembalian` (`id_kembali`) ON DELETE CASCADE,
  ADD CONSTRAINT `tindaklanjut_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_unit`) REFERENCES `unit` (`id_unit`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
