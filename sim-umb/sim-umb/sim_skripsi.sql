-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 11, 2026 at 03:08 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sim_skripsi`
--

-- --------------------------------------------------------

--
-- Table structure for table `bimbingan`
--

CREATE TABLE `bimbingan` (
  `id_bimbingan` int(10) UNSIGNED NOT NULL,
  `id_pengajuan` int(10) UNSIGNED NOT NULL,
  `id_dosen` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `topik` varchar(200) NOT NULL COMMENT 'yang dikonsultasikan mahasiswa',
  `file_mahasiswa` varchar(255) DEFAULT NULL,
  `catatan_dosen` text DEFAULT NULL,
  `file_dosen` varchar(255) DEFAULT NULL,
  `status` enum('Menunggu','Direspons') NOT NULL DEFAULT 'Menunggu',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan bimbingan';

--
-- Dumping data for table `bimbingan`
--

INSERT INTO `bimbingan` (`id_bimbingan`, `id_pengajuan`, `id_dosen`, `tanggal`, `topik`, `file_mahasiswa`, `catatan_dosen`, `file_dosen`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-10-11', 'Revisi bab 2', '20261011_2f3a4f045be4c86dd6fffcbf.pdf', 'sudah sesuai', NULL, 'Direspons', '2026-10-11 21:55:08', '2026-10-11 21:57:20');

-- --------------------------------------------------------

--
-- Table structure for table `dokumen`
--

CREATE TABLE `dokumen` (
  `id_dokumen` int(10) UNSIGNED NOT NULL,
  `id_mahasiswa` bigint(20) UNSIGNED NOT NULL COMMENT '-> SIM Mahasiswa: mahasiswa',
  `id_jenis` int(10) UNSIGNED NOT NULL,
  `nama_file_asli` varchar(255) NOT NULL,
  `path_file` varchar(255) NOT NULL COMMENT 'nama file acak di folder uploads/',
  `ukuran_kb` int(10) UNSIGNED NOT NULL,
  `status_verifikasi` enum('Menunggu','Valid','Ditolak') NOT NULL DEFAULT 'Menunggu',
  `catatan_verifikasi` varchar(255) DEFAULT NULL,
  `diverifikasi_oleh` bigint(20) UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  `tanggal_upload` datetime NOT NULL DEFAULT current_timestamp(),
  `tanggal_verifikasi` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Berkas unggahan mahasiswa';

--
-- Dumping data for table `dokumen`
--

INSERT INTO `dokumen` (`id_dokumen`, `id_mahasiswa`, `id_jenis`, `nama_file_asli`, `path_file`, `ukuran_kb`, `status_verifikasi`, `catatan_verifikasi`, `diverifikasi_oleh`, `tanggal_upload`, `tanggal_verifikasi`) VALUES
(1, 1, 1, '59efa69aa08f78530ecafeeefb1296dfef05-n.pdf', '20261011_87fa501539b499f27fbca058.pdf', 482, 'Valid', NULL, 2, '2026-10-11 22:02:56', '2026-10-11 22:04:26'),
(2, 1, 2, '[Hlm+1-9]+novia.pdf', '20261011_3c583176701eaa1ccd0e6b41.pdf', 452, 'Valid', NULL, 2, '2026-10-11 22:03:05', '2026-10-11 22:04:28'),
(3, 1, 3, 'MJEE-Vol18-Issue2-182430.pdf', '20261011_b50934bfe91ad4fe5eb3bbb0.pdf', 925, 'Valid', NULL, 2, '2026-10-11 22:03:21', '2026-10-11 22:04:29'),
(4, 1, 4, '442025-none-9b622158-n.pdf', '20261011_8d550eb4750b07572214d865.pdf', 436, 'Valid', NULL, 2, '2026-10-11 22:03:30', '2026-10-11 22:04:31'),
(5, 1, 5, '59efa69aa08f78530ecafeeefb1296dfef05-n.pdf', '20261011_a4af3edcb024b751c99c73a4.pdf', 482, 'Valid', NULL, 2, '2026-10-11 22:03:56', '2026-10-11 22:04:35');

-- --------------------------------------------------------

--
-- Table structure for table `dosen`
--

CREATE TABLE `dosen` (
  `id_dosen` int(10) UNSIGNED NOT NULL,
  `id_simdosen` bigint(20) UNSIGNED NOT NULL COMMENT '-> SIM Dosen: dosen.id_dosen',
  `id_pengguna_simdosen` bigint(20) UNSIGNED DEFAULT NULL COMMENT '-> SIM Dosen: pengguna.id_pengguna (akun SSO)',
  `nomor_registrasi` varchar(20) NOT NULL COMMENT 'NIDN/NIDK/NUP',
  `nuptk` varchar(20) DEFAULT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `nama_dosen` varchar(150) NOT NULL,
  `gelar_depan` varchar(30) DEFAULT NULL,
  `gelar_belakang` varchar(60) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `kode_program_studi` varchar(20) DEFAULT NULL COMMENT 'kunci penghubung antar sistem, mis. TI',
  `nama_program_studi` varchar(200) DEFAULT NULL,
  `id_program_studi` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'id prodi di SIM Mahasiswa (dicocokkan lewat kode)',
  `jabatan_fungsional` varchar(100) DEFAULT NULL,
  `pendidikan_terakhir` varchar(100) DEFAULT NULL,
  `status_aktivitas` varchar(100) DEFAULT NULL COMMENT 'dari SIM Dosen, mis. Aktif / Tugas Belajar',
  `status_aktif` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif' COMMENT 'Aktif bila status_aktivitas = Aktif',
  `kuota_bimbingan` tinyint(3) UNSIGNED NOT NULL DEFAULT 10 COMMENT 'diatur di SIMSKRIPSI',
  `last_login` datetime DEFAULT NULL,
  `terakhir_sinkron` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache data dosen dari SIM Dosen';

--
-- Dumping data for table `dosen`
--

INSERT INTO `dosen` (`id_dosen`, `id_simdosen`, `id_pengguna_simdosen`, `nomor_registrasi`, `nuptk`, `nip`, `nama_dosen`, `gelar_depan`, `gelar_belakang`, `email`, `kode_program_studi`, `nama_program_studi`, `id_program_studi`, `jabatan_fungsional`, `pendidikan_terakhir`, `status_aktivitas`, `status_aktif`, `kuota_bimbingan`, `last_login`, `terakhir_sinkron`, `created_at`) VALUES
(1, 1, 1, '0201018501', '3533763664130123', '198501012010121001', 'Ahmad', 'Dr.', 'M.Kom.', 'ahmad@umb.ac.id', 'TI', 'Teknik Informatika', 1, 'Lektor Kepala', 'S3', 'Aktif', 'Aktif', 10, '2026-10-11 21:54:14', '2026-10-11 21:54:14', '2026-10-11 21:29:23');

-- --------------------------------------------------------

--
-- Table structure for table `jenis_dokumen`
--

CREATE TABLE `jenis_dokumen` (
  `id_jenis` int(10) UNSIGNED NOT NULL,
  `nama_dokumen` varchar(100) NOT NULL,
  `tahap` enum('Pengajuan Judul','Seminar Proposal','Sidang Skripsi') NOT NULL,
  `wajib` tinyint(1) NOT NULL DEFAULT 1,
  `format_file` varchar(50) NOT NULL DEFAULT 'pdf' COMMENT 'ekstensi diizinkan, pisahkan koma',
  `maks_ukuran_kb` int(10) UNSIGNED NOT NULL DEFAULT 2048,
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `status_aktif` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master persyaratan dokumen';

--
-- Dumping data for table `jenis_dokumen`
--

INSERT INTO `jenis_dokumen` (`id_jenis`, `nama_dokumen`, `tahap`, `wajib`, `format_file`, `maks_ukuran_kb`, `urutan`, `status_aktif`) VALUES
(1, 'Transkrip nilai sementara', 'Pengajuan Judul', 1, 'pdf', 2048, 1, 'Aktif'),
(2, 'Kartu Rencana Studi (KRS) skripsi', 'Pengajuan Judul', 1, 'pdf', 1024, 2, 'Aktif'),
(3, 'Draf proposal', 'Seminar Proposal', 1, 'pdf', 5120, 3, 'Aktif'),
(4, 'Kartu bimbingan proposal', 'Seminar Proposal', 1, 'pdf', 2048, 4, 'Aktif'),
(5, 'Lembar persetujuan seminar', 'Seminar Proposal', 1, 'pdf', 1024, 5, 'Aktif'),
(6, 'Naskah skripsi lengkap', 'Sidang Skripsi', 1, 'pdf', 10240, 6, 'Aktif'),
(7, 'Kartu bimbingan skripsi', 'Sidang Skripsi', 1, 'pdf', 2048, 7, 'Aktif'),
(8, 'Bukti bebas pustaka', 'Sidang Skripsi', 1, 'pdf,jpg', 1024, 8, 'Aktif'),
(9, 'Bukti lunas administrasi', 'Sidang Skripsi', 1, 'pdf,jpg', 1024, 9, 'Aktif'),
(10, 'Sertifikat TOEFL', 'Sidang Skripsi', 0, 'pdf,jpg', 1024, 10, 'Aktif');

-- --------------------------------------------------------

--
-- Table structure for table `pembimbing`
--

CREATE TABLE `pembimbing` (
  `id_pembimbing` int(10) UNSIGNED NOT NULL,
  `id_pengajuan` int(10) UNSIGNED NOT NULL,
  `id_dosen` int(10) UNSIGNED NOT NULL,
  `peran` enum('Pembimbing 1','Pembimbing 2') NOT NULL,
  `nomor_sk` varchar(100) DEFAULT NULL,
  `tanggal_sk` date DEFAULT NULL,
  `ditetapkan_oleh` bigint(20) UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dosen pembimbing skripsi';

--
-- Dumping data for table `pembimbing`
--

INSERT INTO `pembimbing` (`id_pembimbing`, `id_pengajuan`, `id_dosen`, `peran`, `nomor_sk`, `tanggal_sk`, `ditetapkan_oleh`, `created_at`) VALUES
(1, 2, 1, 'Pembimbing 1', NULL, '2026-10-11', 2, '2026-10-11 21:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `pengajuan_judul`
--

CREATE TABLE `pengajuan_judul` (
  `id_pengajuan` int(10) UNSIGNED NOT NULL,
  `id_mahasiswa` bigint(20) UNSIGNED NOT NULL COMMENT '-> SIM Mahasiswa: mahasiswa',
  `judul` varchar(300) NOT NULL,
  `bidang_kajian` varchar(100) DEFAULT NULL,
  `latar_belakang` text DEFAULT NULL COMMENT 'ringkasan masalah & alasan memilih judul',
  `usulan_dosen` int(10) UNSIGNED DEFAULT NULL COMMENT 'calon pembimbing yang diusulkan mahasiswa',
  `status` enum('Diajukan','Revisi','Disetujui','Ditolak') NOT NULL DEFAULT 'Diajukan',
  `catatan_prodi` text DEFAULT NULL,
  `diproses_oleh` bigint(20) UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna (operator prodi)',
  `tanggal_pengajuan` datetime NOT NULL DEFAULT current_timestamp(),
  `tanggal_diproses` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `kunci_aktif` bigint(20) UNSIGNED GENERATED ALWAYS AS (if(`status` = 'Ditolak',NULL,`id_mahasiswa`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengajuan judul skripsi';

--
-- Dumping data for table `pengajuan_judul`
--

INSERT INTO `pengajuan_judul` (`id_pengajuan`, `id_mahasiswa`, `judul`, `bidang_kajian`, `latar_belakang`, `usulan_dosen`, `status`, `catatan_prodi`, `diproses_oleh`, `tanggal_pengajuan`, `tanggal_diproses`, `updated_at`) VALUES
(2, 1, 'Analisis Sentimen Masyarakat', 'Data Mining', 'lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem', NULL, 'Disetujui', NULL, 2, '2026-10-11 21:52:29', '2026-10-11 21:52:42', '2026-10-11 21:52:42');

-- --------------------------------------------------------

--
-- Table structure for table `pengguna_sso`
--

CREATE TABLE `pengguna_sso` (
  `id_pengguna` bigint(20) UNSIGNED NOT NULL COMMENT 'sama dengan id_pengguna di SIM Mahasiswa',
  `username` varchar(100) NOT NULL,
  `nama_lengkap` varchar(200) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `kode_role` varchar(30) NOT NULL COMMENT 'ADMIN, OPERATOR_PRODI, DEKANAT, MAHASISWA',
  `id_fakultas` bigint(20) UNSIGNED DEFAULT NULL,
  `id_program_studi` bigint(20) UNSIGNED DEFAULT NULL,
  `nama_program_studi` varchar(200) DEFAULT NULL,
  `id_mahasiswa` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'diisi bila kode_role = MAHASISWA',
  `npm` varchar(30) DEFAULT NULL,
  `terakhir_sinkron` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cache data pengguna dari SSO SIM Mahasiswa';

--
-- Dumping data for table `pengguna_sso`
--

INSERT INTO `pengguna_sso` (`id_pengguna`, `username`, `nama_lengkap`, `email`, `kode_role`, `id_fakultas`, `id_program_studi`, `nama_program_studi`, `id_mahasiswa`, `npm`, `terakhir_sinkron`) VALUES
(2, 'operator.ti', 'Operator Program Studi Teknik Informatika', 'operator.ti@umb.ac.id', 'OPERATOR_PRODI', 1, 1, 'Teknik Informatika', NULL, NULL, '2026-10-11 21:50:13'),
(5, '20260001', 'Contoh Mahasiswa', 'sento@gmail.com', 'MAHASISWA', 1, 1, 'Teknik Informatika', 1, '20260001', '2026-10-11 21:34:38'),
(6, 'Admin', 'Admin SIM Mhs UMB', 'admin@univ.ac.id', 'ADMIN', NULL, NULL, NULL, NULL, NULL, '2026-10-11 21:32:35');

-- --------------------------------------------------------

--
-- Table structure for table `pengumuman`
--

CREATE TABLE `pengumuman` (
  `id_pengumuman` int(10) UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `isi` text NOT NULL,
  `kategori` enum('Umum','Pengajuan Judul','Seminar','Sidang','Yudisium') NOT NULL DEFAULT 'Umum',
  `sasaran` enum('Semua','Mahasiswa','Dosen') NOT NULL DEFAULT 'Semua',
  `id_program_studi` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL = semua prodi; -> SIM Mahasiswa: program_studi',
  `lampiran` varchar(255) DEFAULT NULL,
  `status` enum('Draf','Terbit') NOT NULL DEFAULT 'Draf',
  `tanggal_terbit` datetime DEFAULT NULL,
  `tanggal_berakhir` date DEFAULT NULL COMMENT 'NULL = tampil terus',
  `dibuat_oleh` bigint(20) UNSIGNED NOT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengumuman';

--
-- Dumping data for table `pengumuman`
--

INSERT INTO `pengumuman` (`id_pengumuman`, `judul`, `isi`, `kategori`, `sasaran`, `id_program_studi`, `lampiran`, `status`, `tanggal_terbit`, `tanggal_berakhir`, `dibuat_oleh`, `created_at`, `updated_at`) VALUES
(1, 'Pembukaan pengajuan judul skripsi semester ganjil', 'Pengajuan judul skripsi dibuka melalui SIMSKRIPSI. Pastikan transkrip dan KRS skripsi sudah diunggah sebelum mengajukan judul.', 'Pengajuan Judul', 'Mahasiswa', NULL, NULL, 'Terbit', '2026-10-11 20:29:01', NULL, 6, '2026-10-11 20:29:01', '2026-10-11 20:29:01'),
(2, 'Jadwal seminar proposal gelombang I', 'Jadwal seminar proposal gelombang I dapat dilihat di menu Seminar. Dosen penguji diharapkan mengonfirmasi kehadiran.', 'Seminar', 'Semua', NULL, NULL, 'Terbit', '2026-10-11 20:29:01', NULL, 6, '2026-10-11 20:29:01', '2026-10-11 20:29:01');

-- --------------------------------------------------------

--
-- Table structure for table `seminar_proposal`
--

CREATE TABLE `seminar_proposal` (
  `id_seminar` int(10) UNSIGNED NOT NULL,
  `id_pengajuan` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `ruangan` varchar(100) NOT NULL,
  `id_penguji_1` int(10) UNSIGNED NOT NULL,
  `id_penguji_2` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Dijadwalkan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Dijadwalkan',
  `hasil` enum('Lulus','Lulus dengan Revisi','Tidak Lulus') DEFAULT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `catatan_revisi` text DEFAULT NULL,
  `dijadwalkan_oleh` bigint(20) UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `seminar_proposal`
--

INSERT INTO `seminar_proposal` (`id_seminar`, `id_pengajuan`, `tanggal`, `jam_mulai`, `jam_selesai`, `ruangan`, `id_penguji_1`, `id_penguji_2`, `status`, `hasil`, `nilai`, `catatan_revisi`, `dijadwalkan_oleh`, `created_at`, `updated_at`) VALUES
(1, 2, '2026-10-14', '09:00:00', '11:00:00', 'G4', 1, NULL, 'Dijadwalkan', NULL, NULL, NULL, 2, '2026-10-11 22:06:25', '2026-10-11 22:06:25');

-- --------------------------------------------------------

--
-- Table structure for table `sidang_skripsi`
--

CREATE TABLE `sidang_skripsi` (
  `id_sidang` int(10) UNSIGNED NOT NULL,
  `id_pengajuan` int(10) UNSIGNED NOT NULL,
  `judul_akhir` varchar(300) DEFAULT NULL COMMENT 'diisi bila judul berubah setelah seminar',
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `ruangan` varchar(100) NOT NULL,
  `id_penguji_1` int(10) UNSIGNED NOT NULL,
  `id_penguji_2` int(10) UNSIGNED NOT NULL,
  `id_penguji_3` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Dijadwalkan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Dijadwalkan',
  `hasil` enum('Lulus','Lulus dengan Revisi','Tidak Lulus') DEFAULT NULL,
  `nilai_angka` decimal(5,2) DEFAULT NULL,
  `nilai_huruf` enum('A','A-','B+','B','B-','C+','C','D','E') DEFAULT NULL,
  `catatan_revisi` text DEFAULT NULL,
  `batas_revisi` date DEFAULT NULL,
  `dijadwalkan_oleh` bigint(20) UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_kelengkapan_dokumen`
-- (See below for the actual view)
--
CREATE TABLE `v_kelengkapan_dokumen` (
`id_mahasiswa` bigint(20) unsigned
,`id_pengajuan` int(10) unsigned
,`id_jenis` int(10) unsigned
,`tahap` enum('Pengajuan Judul','Seminar Proposal','Sidang Skripsi')
,`nama_dokumen` varchar(100)
,`wajib` tinyint(1)
,`id_dokumen` int(10) unsigned
,`nama_file_asli` varchar(255)
,`tanggal_upload` datetime
,`status_dokumen` varchar(14)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pengajuan_mahasiswa`
-- (See below for the actual view)
--
CREATE TABLE `v_pengajuan_mahasiswa` (
`id_pengajuan` int(10) unsigned
,`id_mahasiswa` bigint(20) unsigned
,`npm` varchar(30)
,`nama_mahasiswa` varchar(200)
,`id_program_studi` bigint(20) unsigned
,`nama_program_studi` varchar(200)
,`judul` varchar(300)
,`status` enum('Diajukan','Revisi','Disetujui','Ditolak')
,`tanggal_pengajuan` datetime
,`pembimbing_1` varchar(243)
,`pembimbing_2` varchar(243)
);

-- --------------------------------------------------------

--
-- Structure for view `v_kelengkapan_dokumen`
--
DROP TABLE IF EXISTS `v_kelengkapan_dokumen`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_kelengkapan_dokumen`  AS SELECT `pj`.`id_mahasiswa` AS `id_mahasiswa`, `pj`.`id_pengajuan` AS `id_pengajuan`, `jd`.`id_jenis` AS `id_jenis`, `jd`.`tahap` AS `tahap`, `jd`.`nama_dokumen` AS `nama_dokumen`, `jd`.`wajib` AS `wajib`, `d`.`id_dokumen` AS `id_dokumen`, `d`.`nama_file_asli` AS `nama_file_asli`, `d`.`tanggal_upload` AS `tanggal_upload`, coalesce(`d`.`status_verifikasi`,'Belum diunggah') AS `status_dokumen` FROM ((`pengajuan_judul` `pj` join `jenis_dokumen` `jd`) left join `dokumen` `d` on(`d`.`id_mahasiswa` = `pj`.`id_mahasiswa` and `d`.`id_jenis` = `jd`.`id_jenis`)) WHERE `pj`.`status` <> 'Ditolak' AND `jd`.`status_aktif` = 'Aktif' ;

-- --------------------------------------------------------

--
-- Structure for view `v_pengajuan_mahasiswa`
--
DROP TABLE IF EXISTS `v_pengajuan_mahasiswa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pengajuan_mahasiswa`  AS SELECT `pj`.`id_pengajuan` AS `id_pengajuan`, `pj`.`id_mahasiswa` AS `id_mahasiswa`, `ps`.`npm` AS `npm`, `ps`.`nama_lengkap` AS `nama_mahasiswa`, `ps`.`id_program_studi` AS `id_program_studi`, `ps`.`nama_program_studi` AS `nama_program_studi`, `pj`.`judul` AS `judul`, `pj`.`status` AS `status`, `pj`.`tanggal_pengajuan` AS `tanggal_pengajuan`, (select concat_ws(', ',concat_ws(' ',`d`.`gelar_depan`,`d`.`nama_dosen`),`d`.`gelar_belakang`) from (`pembimbing` `pb` join `dosen` `d` on(`d`.`id_dosen` = `pb`.`id_dosen`)) where `pb`.`id_pengajuan` = `pj`.`id_pengajuan` and `pb`.`peran` = 'Pembimbing 1') AS `pembimbing_1`, (select concat_ws(', ',concat_ws(' ',`d`.`gelar_depan`,`d`.`nama_dosen`),`d`.`gelar_belakang`) from (`pembimbing` `pb` join `dosen` `d` on(`d`.`id_dosen` = `pb`.`id_dosen`)) where `pb`.`id_pengajuan` = `pj`.`id_pengajuan` and `pb`.`peran` = 'Pembimbing 2') AS `pembimbing_2` FROM (`pengajuan_judul` `pj` left join `pengguna_sso` `ps` on(`ps`.`id_mahasiswa` = `pj`.`id_mahasiswa`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bimbingan`
--
ALTER TABLE `bimbingan`
  ADD PRIMARY KEY (`id_bimbingan`),
  ADD KEY `idx_bimbingan_pengajuan` (`id_pengajuan`,`tanggal`),
  ADD KEY `idx_bimbingan_dosen` (`id_dosen`,`status`);

--
-- Indexes for table `dokumen`
--
ALTER TABLE `dokumen`
  ADD PRIMARY KEY (`id_dokumen`),
  ADD UNIQUE KEY `uk_dokumen_mhs_jenis` (`id_mahasiswa`,`id_jenis`),
  ADD KEY `idx_dokumen_status` (`status_verifikasi`),
  ADD KEY `fk_dokumen_jenis` (`id_jenis`);

--
-- Indexes for table `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id_dosen`),
  ADD UNIQUE KEY `uk_dosen_simdosen` (`id_simdosen`),
  ADD UNIQUE KEY `uk_dosen_noreg` (`nomor_registrasi`),
  ADD UNIQUE KEY `uk_dosen_pengguna` (`id_pengguna_simdosen`),
  ADD KEY `idx_dosen_prodi` (`id_program_studi`);

--
-- Indexes for table `jenis_dokumen`
--
ALTER TABLE `jenis_dokumen`
  ADD PRIMARY KEY (`id_jenis`),
  ADD UNIQUE KEY `uk_jenis_dokumen` (`tahap`,`nama_dokumen`);

--
-- Indexes for table `pembimbing`
--
ALTER TABLE `pembimbing`
  ADD PRIMARY KEY (`id_pembimbing`),
  ADD UNIQUE KEY `uk_pembimbing_peran` (`id_pengajuan`,`peran`),
  ADD UNIQUE KEY `uk_pembimbing_dosen` (`id_pengajuan`,`id_dosen`),
  ADD KEY `idx_pembimbing_dosen` (`id_dosen`);

--
-- Indexes for table `pengajuan_judul`
--
ALTER TABLE `pengajuan_judul`
  ADD PRIMARY KEY (`id_pengajuan`),
  ADD UNIQUE KEY `uk_pengajuan_aktif` (`kunci_aktif`),
  ADD KEY `idx_pengajuan_mhs` (`id_mahasiswa`),
  ADD KEY `idx_pengajuan_status` (`status`),
  ADD KEY `fk_pengajuan_usulan` (`usulan_dosen`);

--
-- Indexes for table `pengguna_sso`
--
ALTER TABLE `pengguna_sso`
  ADD PRIMARY KEY (`id_pengguna`),
  ADD UNIQUE KEY `uk_sso_mahasiswa` (`id_mahasiswa`),
  ADD UNIQUE KEY `uk_sso_npm` (`npm`),
  ADD KEY `idx_sso_role` (`kode_role`),
  ADD KEY `idx_sso_prodi` (`id_program_studi`);

--
-- Indexes for table `pengumuman`
--
ALTER TABLE `pengumuman`
  ADD PRIMARY KEY (`id_pengumuman`),
  ADD KEY `idx_pengumuman_tampil` (`status`,`tanggal_terbit`),
  ADD KEY `idx_pengumuman_prodi` (`id_program_studi`);

--
-- Indexes for table `seminar_proposal`
--
ALTER TABLE `seminar_proposal`
  ADD PRIMARY KEY (`id_seminar`),
  ADD KEY `idx_seminar_pengajuan` (`id_pengajuan`),
  ADD KEY `idx_seminar_jadwal` (`tanggal`,`ruangan`),
  ADD KEY `fk_seminar_penguji1` (`id_penguji_1`),
  ADD KEY `fk_seminar_penguji2` (`id_penguji_2`);

--
-- Indexes for table `sidang_skripsi`
--
ALTER TABLE `sidang_skripsi`
  ADD PRIMARY KEY (`id_sidang`),
  ADD KEY `idx_sidang_pengajuan` (`id_pengajuan`),
  ADD KEY `idx_sidang_jadwal` (`tanggal`,`ruangan`),
  ADD KEY `fk_sidang_penguji1` (`id_penguji_1`),
  ADD KEY `fk_sidang_penguji2` (`id_penguji_2`),
  ADD KEY `fk_sidang_penguji3` (`id_penguji_3`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bimbingan`
--
ALTER TABLE `bimbingan`
  MODIFY `id_bimbingan` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `dokumen`
--
ALTER TABLE `dokumen`
  MODIFY `id_dokumen` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `dosen`
--
ALTER TABLE `dosen`
  MODIFY `id_dosen` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `jenis_dokumen`
--
ALTER TABLE `jenis_dokumen`
  MODIFY `id_jenis` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `pembimbing`
--
ALTER TABLE `pembimbing`
  MODIFY `id_pembimbing` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pengajuan_judul`
--
ALTER TABLE `pengajuan_judul`
  MODIFY `id_pengajuan` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pengumuman`
--
ALTER TABLE `pengumuman`
  MODIFY `id_pengumuman` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `seminar_proposal`
--
ALTER TABLE `seminar_proposal`
  MODIFY `id_seminar` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sidang_skripsi`
--
ALTER TABLE `sidang_skripsi`
  MODIFY `id_sidang` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bimbingan`
--
ALTER TABLE `bimbingan`
  ADD CONSTRAINT `fk_bimbingan_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bimbingan_pengajuan` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_judul` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dokumen`
--
ALTER TABLE `dokumen`
  ADD CONSTRAINT `fk_dokumen_jenis` FOREIGN KEY (`id_jenis`) REFERENCES `jenis_dokumen` (`id_jenis`) ON UPDATE CASCADE;

--
-- Constraints for table `pembimbing`
--
ALTER TABLE `pembimbing`
  ADD CONSTRAINT `fk_pembimbing_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pembimbing_pengajuan` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_judul` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pengajuan_judul`
--
ALTER TABLE `pengajuan_judul`
  ADD CONSTRAINT `fk_pengajuan_usulan` FOREIGN KEY (`usulan_dosen`) REFERENCES `dosen` (`id_dosen`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `seminar_proposal`
--
ALTER TABLE `seminar_proposal`
  ADD CONSTRAINT `fk_seminar_pengajuan` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_judul` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_seminar_penguji1` FOREIGN KEY (`id_penguji_1`) REFERENCES `dosen` (`id_dosen`),
  ADD CONSTRAINT `fk_seminar_penguji2` FOREIGN KEY (`id_penguji_2`) REFERENCES `dosen` (`id_dosen`);

--
-- Constraints for table `sidang_skripsi`
--
ALTER TABLE `sidang_skripsi`
  ADD CONSTRAINT `fk_sidang_pengajuan` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_judul` (`id_pengajuan`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_sidang_penguji1` FOREIGN KEY (`id_penguji_1`) REFERENCES `dosen` (`id_dosen`),
  ADD CONSTRAINT `fk_sidang_penguji2` FOREIGN KEY (`id_penguji_2`) REFERENCES `dosen` (`id_dosen`),
  ADD CONSTRAINT `fk_sidang_penguji3` FOREIGN KEY (`id_penguji_3`) REFERENCES `dosen` (`id_dosen`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
