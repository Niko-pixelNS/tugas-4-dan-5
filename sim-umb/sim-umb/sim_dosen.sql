-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 11, 2026 at 03:07 PM
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
-- Database: `sim_dosen`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id_audit` bigint(20) UNSIGNED NOT NULL,
  `id_pengguna` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'akun dosen lokal; NULL bila admin SSO',
  `pelaku` varchar(150) DEFAULT NULL COMMENT 'nama & asal akun, mis. Admin (SSO SIM Mahasiswa)',
  `aksi` varchar(30) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `waktu` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id_audit`, `id_pengguna`, `pelaku`, `aksi`, `keterangan`, `ip_address`, `waktu`) VALUES
(1, NULL, 'Admin SIM Dosen (dosen)', 'LOGOUT', 'Keluar dari SIM Dosen', '::1', '2026-10-11 21:24:37'),
(2, NULL, 'Admin SIM Mhs UMB (SSO SIM Mahasiswa)', 'LOGIN', 'Admin masuk lewat SSO SIM Mahasiswa', '::1', '2026-10-11 21:24:39'),
(3, NULL, 'Admin SIM Mhs UMB (SSO SIM Mahasiswa)', 'LOGOUT', 'Keluar dari SIM Dosen', '::1', '2026-10-11 21:27:35'),
(4, 1, 'Dr. Ahmad, M.Kom. (dosen)', 'LOGIN', 'Dosen masuk ke SIM Dosen', '::1', '2026-10-11 21:27:54'),
(5, 1, 'Dr. Ahmad, M.Kom. (dosen)', 'LOGOUT', 'Keluar lewat SSO', '::1', '2026-10-11 21:30:29'),
(6, NULL, 'Admin SIM Mhs UMB (SSO SIM Mahasiswa)', 'LOGIN', 'Admin masuk lewat SSO SIM Mahasiswa', '::1', '2026-10-11 21:32:29'),
(7, 1, 'Dr. Ahmad, M.Kom. (dosen)', 'LOGIN', 'Dosen masuk ke SIM Dosen', '::1', '2026-10-11 21:38:46'),
(8, 1, 'Dr. Ahmad, M.Kom. (dosen)', 'LOGOUT', 'Keluar lewat SSO', '::1', '2026-10-11 21:49:32'),
(9, 1, 'Dr. Ahmad, M.Kom. (dosen)', 'LOGIN', 'Dosen masuk ke SIM Dosen', '::1', '2026-10-11 21:54:04');

-- --------------------------------------------------------

--
-- Table structure for table `dosen`
--

CREATE TABLE `dosen` (
  `id_dosen` bigint(20) UNSIGNED NOT NULL,
  `id_pengguna` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'akun login dosen',
  `id_program_studi` bigint(20) UNSIGNED NOT NULL COMMENT 'penempatan / homebase',
  `nomor_registrasi` varchar(20) NOT NULL,
  `jenis_registrasi` enum('NIDN','NIDK','NUP') NOT NULL DEFAULT 'NIDN',
  `nuptk` varchar(20) DEFAULT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `nama_dosen` varchar(150) NOT NULL COMMENT 'tanpa gelar',
  `gelar_depan` varchar(30) DEFAULT NULL,
  `gelar_belakang` varchar(60) DEFAULT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `id_jabatan_fungsional` smallint(5) UNSIGNED NOT NULL,
  `id_pangkat_golongan` smallint(5) UNSIGNED DEFAULT NULL,
  `id_jenjang_pendidikan` smallint(5) UNSIGNED NOT NULL,
  `id_rumpun_ilmu` smallint(5) UNSIGNED DEFAULT NULL,
  `status_serdos` enum('Sudah','Belum') NOT NULL DEFAULT 'Belum',
  `id_status_pegawai` smallint(5) UNSIGNED NOT NULL,
  `id_ikatan_kerja` smallint(5) UNSIGNED NOT NULL,
  `id_status_aktivitas` smallint(5) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dosen`
--

INSERT INTO `dosen` (`id_dosen`, `id_pengguna`, `id_program_studi`, `nomor_registrasi`, `jenis_registrasi`, `nuptk`, `nip`, `nama_dosen`, `gelar_depan`, `gelar_belakang`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `email`, `no_hp`, `id_jabatan_fungsional`, `id_pangkat_golongan`, `id_jenjang_pendidikan`, `id_rumpun_ilmu`, `status_serdos`, `id_status_pegawai`, `id_ikatan_kerja`, `id_status_aktivitas`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '0201018501', 'NIDN', '3533763664130123', '198501012010121001', 'Ahmad', 'Dr.', 'M.Kom.', 'L', 'Bengkulu', '1985-01-01', 'ahmad@umb.ac.id', NULL, 4, 5, 3, 1, 'Sudah', 3, 1, 1, '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(2, 2, 1, '0215038802', 'NIDN', '7547766667230152', NULL, 'Rina Wati', NULL, 'M.T.', 'P', 'Curup', '1988-03-15', 'rina@umb.ac.id', NULL, 3, 3, 2, 1, 'Sudah', 3, 1, 1, '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(3, 3, 1, '0220029001', 'NIDN', '1552768669130093', NULL, 'Hendra Saputra', NULL, 'M.Kom.', 'L', 'Manna', '1990-02-20', 'hendra@umb.ac.id', NULL, 2, 2, 2, 1, 'Belum', 3, 1, 1, '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(4, NULL, 4, '0207078703', 'NIDN', '2039765666230113', NULL, 'Yuliana', NULL, 'S.E., M.Ak.', 'P', 'Bengkulu', '1987-07-07', 'yuliana@umb.ac.id', NULL, 3, 3, 2, 4, 'Sudah', 3, 1, 1, '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(5, NULL, 6, '0210119004', 'NIDN', '4442768669230082', NULL, 'Dewi Sartika', NULL, 'M.Pd.', 'P', 'Kepahiang', '1990-11-10', 'dewi@umb.ac.id', NULL, 2, 2, 2, 6, 'Belum', 3, 1, 3, '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(6, NULL, 5, '8812345601', 'NIDK', '9733745647110042', '196801121993031002', 'Bambang Irawan', 'Prof. Dr.', 'S.H., M.H.', 'L', 'Lahat', '1968-01-12', 'bambang@umb.ac.id', NULL, 5, 7, 3, 5, 'Sudah', 1, 3, 1, '2026-10-11 14:10:02', '2026-10-11 14:10:02');

-- --------------------------------------------------------

--
-- Table structure for table `fakultas`
--

CREATE TABLE `fakultas` (
  `id_fakultas` bigint(20) UNSIGNED NOT NULL,
  `id_universitas` bigint(20) UNSIGNED NOT NULL,
  `kode_fakultas` varchar(20) NOT NULL,
  `nama_fakultas` varchar(200) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `fakultas`
--

INSERT INTO `fakultas` (`id_fakultas`, `id_universitas`, `kode_fakultas`, `nama_fakultas`, `created_at`, `updated_at`) VALUES
(1, 1, 'FT', 'Fakultas Teknik', '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(2, 1, 'FEB', 'Fakultas Ekonomi dan Bisnis', '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(3, 1, 'FH', 'Fakultas Hukum', '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(4, 1, 'FKIP', 'Fakultas Keguruan dan Ilmu Pendidikan', '2026-10-11 14:10:02', '2026-10-11 14:10:02');

-- --------------------------------------------------------

--
-- Table structure for table `ikatan_kerja`
--

CREATE TABLE `ikatan_kerja` (
  `id_ikatan_kerja` smallint(5) UNSIGNED NOT NULL,
  `kode` varchar(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ikatan_kerja`
--

INSERT INTO `ikatan_kerja` (`id_ikatan_kerja`, `kode`, `nama`, `urutan`) VALUES
(1, 'DT', 'Dosen Tetap', 1),
(2, 'DTT', 'Dosen Tidak Tetap', 2),
(3, 'DPK', 'Dosen PNS DPK', 3);

-- --------------------------------------------------------

--
-- Table structure for table `jabatan_fungsional`
--

CREATE TABLE `jabatan_fungsional` (
  `id_jabatan_fungsional` smallint(5) UNSIGNED NOT NULL,
  `kode` varchar(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jabatan_fungsional`
--

INSERT INTO `jabatan_fungsional` (`id_jabatan_fungsional`, `kode`, `nama`, `urutan`) VALUES
(1, 'TP', 'Tenaga Pengajar', 1),
(2, 'AA', 'Asisten Ahli', 2),
(3, 'L', 'Lektor', 3),
(4, 'LK', 'Lektor Kepala', 4),
(5, 'GB', 'Profesor', 5);

-- --------------------------------------------------------

--
-- Table structure for table `jenjang_pendidikan`
--

CREATE TABLE `jenjang_pendidikan` (
  `id_jenjang_pendidikan` smallint(5) UNSIGNED NOT NULL,
  `kode` varchar(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jenjang_pendidikan`
--

INSERT INTO `jenjang_pendidikan` (`id_jenjang_pendidikan`, `kode`, `nama`, `urutan`) VALUES
(1, 'S1', 'S1', 1),
(2, 'S2', 'S2', 2),
(3, 'S3', 'S3', 3),
(4, 'Sp-1', 'Spesialis 1', 4),
(5, 'Sp-2', 'Spesialis 2', 5),
(6, 'Pr', 'Profesi', 6);

-- --------------------------------------------------------

--
-- Table structure for table `pangkat_golongan`
--

CREATE TABLE `pangkat_golongan` (
  `id_pangkat_golongan` smallint(5) UNSIGNED NOT NULL,
  `kode` varchar(20) DEFAULT NULL COMMENT 'golongan/ruang, mis. III/b',
  `nama` varchar(100) NOT NULL COMMENT 'pangkat, mis. Penata Muda Tingkat I',
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pangkat_golongan`
--

INSERT INTO `pangkat_golongan` (`id_pangkat_golongan`, `kode`, `nama`, `urutan`) VALUES
(1, 'III/a', 'Penata Muda', 1),
(2, 'III/b', 'Penata Muda Tingkat I', 2),
(3, 'III/c', 'Penata', 3),
(4, 'III/d', 'Penata Tingkat I', 4),
(5, 'IV/a', 'Pembina', 5),
(6, 'IV/b', 'Pembina Tingkat I', 6),
(7, 'IV/c', 'Pembina Utama Muda', 7),
(8, 'IV/d', 'Pembina Utama Madya', 8),
(9, 'IV/e', 'Pembina Utama', 9);

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `id_pengguna` bigint(20) UNSIGNED NOT NULL,
  `id_role` smallint(5) UNSIGNED NOT NULL COMMENT 'akun lokal = DOSEN',
  `username` varchar(100) NOT NULL COMMENT 'nomor registrasi dosen',
  `password_hash` varchar(255) NOT NULL,
  `nama_lengkap` varchar(200) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status_aktif` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`id_pengguna`, `id_role`, `username`, `password_hash`, `nama_lengkap`, `email`, `status_aktif`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 2, '0201018501', '$2y$10$J0yi0q1mJzSMx3.9eT/A5OgV1i8XE82a94mxx8WOGeNVRP.IZFfCK', 'Dr. Ahmad, M.Kom.', 'ahmad@umb.ac.id', 'Aktif', '2026-10-11 21:54:04', '2026-10-11 14:10:02', '2026-10-11 14:54:04'),
(2, 2, '0215038802', '$2a$12$MOHpQRWg3zrT8aTBp1Ljaewev3ddiUxIJVK8Hd01T5Mobjbge4pt6', 'Rina Wati, M.T.', 'rina@umb.ac.id', 'Aktif', NULL, '2026-10-11 14:10:02', '2026-10-11 14:10:52'),
(3, 2, '0220029001', '$2a$12$MOHpQRWg3zrT8aTBp1Ljaewev3ddiUxIJVK8Hd01T5Mobjbge4pt6', 'Hendra Saputra, M.Kom.', 'hendra@umb.ac.id', 'Aktif', NULL, '2026-10-11 14:10:02', '2026-10-11 14:10:56');

-- --------------------------------------------------------

--
-- Table structure for table `program_studi`
--

CREATE TABLE `program_studi` (
  `id_program_studi` bigint(20) UNSIGNED NOT NULL,
  `id_fakultas` bigint(20) UNSIGNED NOT NULL,
  `kode_program_studi` varchar(20) NOT NULL COMMENT 'sama dengan kode di SIM Mahasiswa, mis. TI',
  `kode_pddikti` varchar(10) DEFAULT NULL COMMENT 'kode prodi PDDikti, mis. 55201',
  `nama_program_studi` varchar(200) NOT NULL,
  `jenjang` enum('D3','D4','S1','S2','S3','Profesi') NOT NULL DEFAULT 'S1',
  `status_aktif` enum('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `program_studi`
--

INSERT INTO `program_studi` (`id_program_studi`, `id_fakultas`, `kode_program_studi`, `kode_pddikti`, `nama_program_studi`, `jenjang`, `status_aktif`, `created_at`, `updated_at`) VALUES
(1, 1, 'TI', '55201', 'Teknik Informatika', 'S1', 'Aktif', '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(2, 1, 'TS', '22201', 'Teknik Sipil', 'S1', 'Aktif', '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(3, 2, 'MNJ', '61201', 'Manajemen', 'S1', 'Aktif', '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(4, 2, 'AKT', '62201', 'Akuntansi', 'S1', 'Aktif', '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(5, 3, 'HK', '74201', 'Ilmu Hukum', 'S1', 'Aktif', '2026-10-11 14:10:02', '2026-10-11 14:10:02'),
(6, 4, 'PBI', '88203', 'Pendidikan Bahasa Inggris', 'S1', 'Aktif', '2026-10-11 14:10:02', '2026-10-11 14:10:02');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id_role` smallint(5) UNSIGNED NOT NULL,
  `kode_role` varchar(30) NOT NULL,
  `nama_role` varchar(100) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id_role`, `kode_role`, `nama_role`, `keterangan`) VALUES
(1, 'ADMIN', 'Admin', 'Mengelola seluruh data, master, laporan, dan akun dosen. Login lewat SSO SIM Mahasiswa (akun Admin SIM Mahasiswa).'),
(2, 'DOSEN', 'Dosen', 'Melihat data dirinya, memperbarui kontak dan password. Login dengan akun di database simdosen.');

-- --------------------------------------------------------

--
-- Table structure for table `rumpun_ilmu`
--

CREATE TABLE `rumpun_ilmu` (
  `id_rumpun_ilmu` smallint(5) UNSIGNED NOT NULL,
  `kode` varchar(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rumpun_ilmu`
--

INSERT INTO `rumpun_ilmu` (`id_rumpun_ilmu`, `kode`, `nama`, `urutan`) VALUES
(1, NULL, 'Ilmu Komputer', 1),
(2, NULL, 'Teknik Sipil', 2),
(3, NULL, 'Manajemen', 3),
(4, NULL, 'Akuntansi', 4),
(5, NULL, 'Ilmu Hukum', 5),
(6, NULL, 'Pendidikan Bahasa', 6);

-- --------------------------------------------------------

--
-- Table structure for table `status_aktivitas`
--

CREATE TABLE `status_aktivitas` (
  `id_status_aktivitas` smallint(5) UNSIGNED NOT NULL,
  `kode` varchar(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `status_aktivitas`
--

INSERT INTO `status_aktivitas` (`id_status_aktivitas`, `kode`, `nama`, `urutan`) VALUES
(1, 'A', 'Aktif', 1),
(2, 'C', 'Cuti', 2),
(3, 'TB', 'Tugas Belajar', 3),
(4, 'IB', 'Ijin Belajar', 4),
(5, 'N', 'Tidak Aktif', 5),
(6, 'P', 'Pensiun', 6),
(7, 'M', 'Almarhum', 7);

-- --------------------------------------------------------

--
-- Table structure for table `status_pegawai`
--

CREATE TABLE `status_pegawai` (
  `id_status_pegawai` smallint(5) UNSIGNED NOT NULL,
  `kode` varchar(20) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `status_pegawai`
--

INSERT INTO `status_pegawai` (`id_status_pegawai`, `kode`, `nama`, `urutan`) VALUES
(1, 'PNS', 'PNS', 1),
(2, 'PPPK', 'PPPK', 2),
(3, 'NON', 'Non PNS', 3);

-- --------------------------------------------------------

--
-- Table structure for table `universitas`
--

CREATE TABLE `universitas` (
  `id_universitas` bigint(20) UNSIGNED NOT NULL,
  `kode_universitas` varchar(20) NOT NULL,
  `nama_universitas` varchar(200) NOT NULL,
  `alamat` text DEFAULT NULL,
  `kota` varchar(100) DEFAULT NULL,
  `provinsi` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `telepon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `universitas`
--

INSERT INTO `universitas` (`id_universitas`, `kode_universitas`, `nama_universitas`, `alamat`, `kota`, `provinsi`, `website`, `email`, `telepon`, `created_at`, `updated_at`) VALUES
(1, 'UMB', 'Universitas Muhammadiyah Bengkulu', 'Jl. Bali, Kampung Bali', 'Bengkulu', 'Bengkulu', 'https://umb.ac.id', NULL, NULL, '2026-10-11 14:10:02', '2026-10-11 14:10:02');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_dosen`
-- (See below for the actual view)
--
CREATE TABLE `v_dosen` (
`id_dosen` bigint(20) unsigned
,`id_pengguna` bigint(20) unsigned
,`nomor_registrasi` varchar(20)
,`jenis_registrasi` enum('NIDN','NIDK','NUP')
,`nuptk` varchar(20)
,`nip` varchar(30)
,`nama_dosen` varchar(150)
,`gelar_depan` varchar(30)
,`gelar_belakang` varchar(60)
,`nama_lengkap` varchar(243)
,`jenis_kelamin` enum('L','P')
,`tempat_lahir` varchar(100)
,`tanggal_lahir` date
,`email` varchar(150)
,`no_hp` varchar(30)
,`status_serdos` enum('Sudah','Belum')
,`jabatan_fungsional` varchar(100)
,`id_jabatan_fungsional` smallint(5) unsigned
,`kepangkatan` varchar(123)
,`id_pangkat_golongan` smallint(5) unsigned
,`pendidikan_terakhir` varchar(100)
,`id_jenjang_pendidikan` smallint(5) unsigned
,`rumpun_ilmu` varchar(100)
,`id_rumpun_ilmu` smallint(5) unsigned
,`status_pegawai` varchar(100)
,`id_status_pegawai` smallint(5) unsigned
,`ikatan_kerja` varchar(100)
,`id_ikatan_kerja` smallint(5) unsigned
,`status_aktivitas` varchar(100)
,`id_status_aktivitas` smallint(5) unsigned
,`id_program_studi` bigint(20) unsigned
,`kode_program_studi` varchar(20)
,`kode_pddikti` varchar(10)
,`nama_program_studi` varchar(200)
,`jenjang` enum('D3','D4','S1','S2','S3','Profesi')
,`id_fakultas` bigint(20) unsigned
,`kode_fakultas` varchar(20)
,`nama_fakultas` varchar(200)
,`id_universitas` bigint(20) unsigned
,`kode_universitas` varchar(20)
,`nama_universitas` varchar(200)
,`penempatan` varchar(208)
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `v_dosen`
--
DROP TABLE IF EXISTS `v_dosen`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_dosen`  AS SELECT `d`.`id_dosen` AS `id_dosen`, `d`.`id_pengguna` AS `id_pengguna`, `d`.`nomor_registrasi` AS `nomor_registrasi`, `d`.`jenis_registrasi` AS `jenis_registrasi`, `d`.`nuptk` AS `nuptk`, `d`.`nip` AS `nip`, `d`.`nama_dosen` AS `nama_dosen`, `d`.`gelar_depan` AS `gelar_depan`, `d`.`gelar_belakang` AS `gelar_belakang`, concat_ws(', ',concat_ws(' ',`d`.`gelar_depan`,`d`.`nama_dosen`),nullif(`d`.`gelar_belakang`,'')) AS `nama_lengkap`, `d`.`jenis_kelamin` AS `jenis_kelamin`, `d`.`tempat_lahir` AS `tempat_lahir`, `d`.`tanggal_lahir` AS `tanggal_lahir`, `d`.`email` AS `email`, `d`.`no_hp` AS `no_hp`, `d`.`status_serdos` AS `status_serdos`, `jf`.`nama` AS `jabatan_fungsional`, `d`.`id_jabatan_fungsional` AS `id_jabatan_fungsional`, concat_ws(' - ',`pg`.`kode`,`pg`.`nama`) AS `kepangkatan`, `d`.`id_pangkat_golongan` AS `id_pangkat_golongan`, `jp`.`nama` AS `pendidikan_terakhir`, `d`.`id_jenjang_pendidikan` AS `id_jenjang_pendidikan`, `ri`.`nama` AS `rumpun_ilmu`, `d`.`id_rumpun_ilmu` AS `id_rumpun_ilmu`, `sp`.`nama` AS `status_pegawai`, `d`.`id_status_pegawai` AS `id_status_pegawai`, `ik`.`nama` AS `ikatan_kerja`, `d`.`id_ikatan_kerja` AS `id_ikatan_kerja`, `sa`.`nama` AS `status_aktivitas`, `d`.`id_status_aktivitas` AS `id_status_aktivitas`, `ps`.`id_program_studi` AS `id_program_studi`, `ps`.`kode_program_studi` AS `kode_program_studi`, `ps`.`kode_pddikti` AS `kode_pddikti`, `ps`.`nama_program_studi` AS `nama_program_studi`, `ps`.`jenjang` AS `jenjang`, `f`.`id_fakultas` AS `id_fakultas`, `f`.`kode_fakultas` AS `kode_fakultas`, `f`.`nama_fakultas` AS `nama_fakultas`, `u`.`id_universitas` AS `id_universitas`, `u`.`kode_universitas` AS `kode_universitas`, `u`.`nama_universitas` AS `nama_universitas`, concat(`ps`.`jenjang`,' ',`ps`.`nama_program_studi`) AS `penempatan`, `d`.`updated_at` AS `updated_at` FROM ((((((((((`dosen` `d` join `program_studi` `ps` on(`ps`.`id_program_studi` = `d`.`id_program_studi`)) join `fakultas` `f` on(`f`.`id_fakultas` = `ps`.`id_fakultas`)) join `universitas` `u` on(`u`.`id_universitas` = `f`.`id_universitas`)) join `jabatan_fungsional` `jf` on(`jf`.`id_jabatan_fungsional` = `d`.`id_jabatan_fungsional`)) left join `pangkat_golongan` `pg` on(`pg`.`id_pangkat_golongan` = `d`.`id_pangkat_golongan`)) join `jenjang_pendidikan` `jp` on(`jp`.`id_jenjang_pendidikan` = `d`.`id_jenjang_pendidikan`)) left join `rumpun_ilmu` `ri` on(`ri`.`id_rumpun_ilmu` = `d`.`id_rumpun_ilmu`)) join `status_pegawai` `sp` on(`sp`.`id_status_pegawai` = `d`.`id_status_pegawai`)) join `ikatan_kerja` `ik` on(`ik`.`id_ikatan_kerja` = `d`.`id_ikatan_kerja`)) join `status_aktivitas` `sa` on(`sa`.`id_status_aktivitas` = `d`.`id_status_aktivitas`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id_audit`),
  ADD KEY `idx_audit_waktu` (`waktu`),
  ADD KEY `fk_audit_pengguna` (`id_pengguna`);

--
-- Indexes for table `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id_dosen`),
  ADD UNIQUE KEY `uk_dosen_noreg` (`nomor_registrasi`),
  ADD UNIQUE KEY `uk_dosen_nuptk` (`nuptk`),
  ADD UNIQUE KEY `uk_dosen_nip` (`nip`),
  ADD UNIQUE KEY `uk_dosen_email` (`email`),
  ADD UNIQUE KEY `uk_dosen_pengguna` (`id_pengguna`),
  ADD KEY `idx_dosen_prodi` (`id_program_studi`),
  ADD KEY `idx_dosen_nama` (`nama_dosen`),
  ADD KEY `fk_dosen_jabfung` (`id_jabatan_fungsional`),
  ADD KEY `fk_dosen_pangkat` (`id_pangkat_golongan`),
  ADD KEY `fk_dosen_pend` (`id_jenjang_pendidikan`),
  ADD KEY `fk_dosen_rumpun` (`id_rumpun_ilmu`),
  ADD KEY `fk_dosen_stpeg` (`id_status_pegawai`),
  ADD KEY `fk_dosen_ikatan` (`id_ikatan_kerja`),
  ADD KEY `fk_dosen_staktiv` (`id_status_aktivitas`);

--
-- Indexes for table `fakultas`
--
ALTER TABLE `fakultas`
  ADD PRIMARY KEY (`id_fakultas`),
  ADD UNIQUE KEY `uk_fakultas_kode` (`id_universitas`,`kode_fakultas`);

--
-- Indexes for table `ikatan_kerja`
--
ALTER TABLE `ikatan_kerja`
  ADD PRIMARY KEY (`id_ikatan_kerja`),
  ADD UNIQUE KEY `uk_ikatan_nama` (`nama`);

--
-- Indexes for table `jabatan_fungsional`
--
ALTER TABLE `jabatan_fungsional`
  ADD PRIMARY KEY (`id_jabatan_fungsional`),
  ADD UNIQUE KEY `uk_jabfung_nama` (`nama`);

--
-- Indexes for table `jenjang_pendidikan`
--
ALTER TABLE `jenjang_pendidikan`
  ADD PRIMARY KEY (`id_jenjang_pendidikan`),
  ADD UNIQUE KEY `uk_pendidikan_nama` (`nama`);

--
-- Indexes for table `pangkat_golongan`
--
ALTER TABLE `pangkat_golongan`
  ADD PRIMARY KEY (`id_pangkat_golongan`),
  ADD UNIQUE KEY `uk_pangkat_nama` (`nama`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id_pengguna`),
  ADD UNIQUE KEY `uk_pengguna_username` (`username`),
  ADD UNIQUE KEY `uk_pengguna_email` (`email`),
  ADD KEY `idx_pengguna_role` (`id_role`);

--
-- Indexes for table `program_studi`
--
ALTER TABLE `program_studi`
  ADD PRIMARY KEY (`id_program_studi`),
  ADD UNIQUE KEY `uk_prodi_kode` (`kode_program_studi`),
  ADD UNIQUE KEY `uk_prodi_pddikti` (`kode_pddikti`),
  ADD KEY `fk_prodi_fakultas` (`id_fakultas`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_role`),
  ADD UNIQUE KEY `uk_role_kode` (`kode_role`);

--
-- Indexes for table `rumpun_ilmu`
--
ALTER TABLE `rumpun_ilmu`
  ADD PRIMARY KEY (`id_rumpun_ilmu`),
  ADD UNIQUE KEY `uk_rumpun_nama` (`nama`);

--
-- Indexes for table `status_aktivitas`
--
ALTER TABLE `status_aktivitas`
  ADD PRIMARY KEY (`id_status_aktivitas`),
  ADD UNIQUE KEY `uk_staktiv_nama` (`nama`);

--
-- Indexes for table `status_pegawai`
--
ALTER TABLE `status_pegawai`
  ADD PRIMARY KEY (`id_status_pegawai`),
  ADD UNIQUE KEY `uk_stpeg_nama` (`nama`);

--
-- Indexes for table `universitas`
--
ALTER TABLE `universitas`
  ADD PRIMARY KEY (`id_universitas`),
  ADD UNIQUE KEY `uk_universitas_kode` (`kode_universitas`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id_audit` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `dosen`
--
ALTER TABLE `dosen`
  MODIFY `id_dosen` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `fakultas`
--
ALTER TABLE `fakultas`
  MODIFY `id_fakultas` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ikatan_kerja`
--
ALTER TABLE `ikatan_kerja`
  MODIFY `id_ikatan_kerja` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `jabatan_fungsional`
--
ALTER TABLE `jabatan_fungsional`
  MODIFY `id_jabatan_fungsional` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `jenjang_pendidikan`
--
ALTER TABLE `jenjang_pendidikan`
  MODIFY `id_jenjang_pendidikan` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `pangkat_golongan`
--
ALTER TABLE `pangkat_golongan`
  MODIFY `id_pangkat_golongan` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id_pengguna` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `program_studi`
--
ALTER TABLE `program_studi`
  MODIFY `id_program_studi` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id_role` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rumpun_ilmu`
--
ALTER TABLE `rumpun_ilmu`
  MODIFY `id_rumpun_ilmu` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `status_aktivitas`
--
ALTER TABLE `status_aktivitas`
  MODIFY `id_status_aktivitas` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `status_pegawai`
--
ALTER TABLE `status_pegawai`
  MODIFY `id_status_pegawai` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `universitas`
--
ALTER TABLE `universitas`
  MODIFY `id_universitas` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `fk_dosen_ikatan` FOREIGN KEY (`id_ikatan_kerja`) REFERENCES `ikatan_kerja` (`id_ikatan_kerja`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dosen_jabfung` FOREIGN KEY (`id_jabatan_fungsional`) REFERENCES `jabatan_fungsional` (`id_jabatan_fungsional`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dosen_pangkat` FOREIGN KEY (`id_pangkat_golongan`) REFERENCES `pangkat_golongan` (`id_pangkat_golongan`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dosen_pend` FOREIGN KEY (`id_jenjang_pendidikan`) REFERENCES `jenjang_pendidikan` (`id_jenjang_pendidikan`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dosen_pengguna` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id_pengguna`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dosen_prodi` FOREIGN KEY (`id_program_studi`) REFERENCES `program_studi` (`id_program_studi`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dosen_rumpun` FOREIGN KEY (`id_rumpun_ilmu`) REFERENCES `rumpun_ilmu` (`id_rumpun_ilmu`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dosen_staktiv` FOREIGN KEY (`id_status_aktivitas`) REFERENCES `status_aktivitas` (`id_status_aktivitas`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dosen_stpeg` FOREIGN KEY (`id_status_pegawai`) REFERENCES `status_pegawai` (`id_status_pegawai`) ON UPDATE CASCADE;

--
-- Constraints for table `fakultas`
--
ALTER TABLE `fakultas`
  ADD CONSTRAINT `fk_fakultas_universitas` FOREIGN KEY (`id_universitas`) REFERENCES `universitas` (`id_universitas`) ON UPDATE CASCADE;

--
-- Constraints for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD CONSTRAINT `fk_pengguna_role` FOREIGN KEY (`id_role`) REFERENCES `roles` (`id_role`) ON UPDATE CASCADE;

--
-- Constraints for table `program_studi`
--
ALTER TABLE `program_studi`
  ADD CONSTRAINT `fk_prodi_fakultas` FOREIGN KEY (`id_fakultas`) REFERENCES `fakultas` (`id_fakultas`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
