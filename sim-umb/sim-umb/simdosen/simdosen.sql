-- =====================================================================
--  DATABASE : simdosen
--  APLIKASI : SIM Dosen — Sistem Informasi Dosen Universitas Muhammadiyah Bengkulu
--  Sumber   : normalisasi laporan "Daftar Dosen Aktif Universitas"
--             (No, Nomor Registrasi, NUPTK, Nama, NIP, Jabatan Fungsional, Kepangkatan,
--              Pendidikan Terakhir, Rumpun Ilmu, Status Serdos, Status Pegawai,
--              Ikatan Kerja, Status Aktivitas, Penempatan + kop Universitas/Fakultas/Prodi)
--
--  Normalisasi:
--   - Kop laporan            -> universitas, fakultas, program_studi
--   - Kolom berulang/terbatas -> tabel referensi (jabatan_fungsional, pangkat_golongan,
--                               jenjang_pendidikan, rumpun_ilmu, status_pegawai,
--                               ikatan_kerja, status_aktivitas)
--   - Penempatan             -> dosen.id_program_studi (homebase)
--   - Autentikasi & RBAC     -> roles (ADMIN, DOSEN), pengguna (akun dosen) + audit_log
--                               Admin TIDAK disimpan di sini: login lewat SSO SIM Mahasiswa
--                               memakai satu akun Admin milik SIM Mahasiswa.
--
--  Cara impor (XAMPP): phpMyAdmin > tab Import > pilih file ini > Go.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS simdosen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simdosen;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS v_dosen;
DROP TABLE IF EXISTS audit_log, dosen, pengguna, roles, status_aktivitas, ikatan_kerja, status_pegawai,
                     rumpun_ilmu, jenjang_pendidikan, pangkat_golongan, jabatan_fungsional,
                     program_studi, fakultas, universitas;
SET FOREIGN_KEY_CHECKS = 1;

/* ---------------- Unit kerja (kop laporan) ---------------- */
CREATE TABLE universitas (
  id_universitas   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_universitas VARCHAR(20)  NOT NULL,
  nama_universitas VARCHAR(200) NOT NULL,
  alamat           TEXT         DEFAULT NULL,
  kota             VARCHAR(100) DEFAULT NULL,
  provinsi         VARCHAR(100) DEFAULT NULL,
  website          VARCHAR(255) DEFAULT NULL,
  email            VARCHAR(150) DEFAULT NULL,
  telepon          VARCHAR(50)  DEFAULT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_universitas),
  UNIQUE KEY uk_universitas_kode (kode_universitas)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fakultas (
  id_fakultas    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_universitas BIGINT UNSIGNED NOT NULL,
  kode_fakultas  VARCHAR(20)  NOT NULL,
  nama_fakultas  VARCHAR(200) NOT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_fakultas),
  UNIQUE KEY uk_fakultas_kode (id_universitas, kode_fakultas),
  CONSTRAINT fk_fakultas_universitas FOREIGN KEY (id_universitas) REFERENCES universitas (id_universitas) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kode_program_studi dipakai sebagai KUNCI PENGHUBUNG dengan SIM Mahasiswa & SIMSKRIPSI
CREATE TABLE program_studi (
  id_program_studi   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_fakultas        BIGINT UNSIGNED NOT NULL,
  kode_program_studi VARCHAR(20)  NOT NULL COMMENT 'sama dengan kode di SIM Mahasiswa, mis. TI',
  kode_pddikti       VARCHAR(10)  DEFAULT NULL COMMENT 'kode prodi PDDikti, mis. 55201',
  nama_program_studi VARCHAR(200) NOT NULL,
  jenjang            ENUM('D3','D4','S1','S2','S3','Profesi') NOT NULL DEFAULT 'S1',
  status_aktif       ENUM('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_program_studi),
  UNIQUE KEY uk_prodi_kode (kode_program_studi),
  UNIQUE KEY uk_prodi_pddikti (kode_pddikti),
  CONSTRAINT fk_prodi_fakultas FOREIGN KEY (id_fakultas) REFERENCES fakultas (id_fakultas) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------- Tabel referensi (hasil normalisasi kolom laporan) ---------------- */
CREATE TABLE jabatan_fungsional (
  id_jabatan_fungsional SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode   VARCHAR(20)  DEFAULT NULL,
  nama   VARCHAR(100) NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_jabatan_fungsional), UNIQUE KEY uk_jabfung_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pangkat_golongan (
  id_pangkat_golongan SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode   VARCHAR(20)  DEFAULT NULL COMMENT 'golongan/ruang, mis. III/b',
  nama   VARCHAR(100) NOT NULL     COMMENT 'pangkat, mis. Penata Muda Tingkat I',
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_pangkat_golongan), UNIQUE KEY uk_pangkat_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jenjang_pendidikan (
  id_jenjang_pendidikan SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode   VARCHAR(20)  DEFAULT NULL,
  nama   VARCHAR(100) NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_jenjang_pendidikan), UNIQUE KEY uk_pendidikan_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rumpun_ilmu (
  id_rumpun_ilmu SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode   VARCHAR(20)  DEFAULT NULL,
  nama   VARCHAR(100) NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_rumpun_ilmu), UNIQUE KEY uk_rumpun_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE status_pegawai (
  id_status_pegawai SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode   VARCHAR(20)  DEFAULT NULL,
  nama   VARCHAR(100) NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_status_pegawai), UNIQUE KEY uk_stpeg_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ikatan_kerja (
  id_ikatan_kerja SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode   VARCHAR(20)  DEFAULT NULL,
  nama   VARCHAR(100) NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_ikatan_kerja), UNIQUE KEY uk_ikatan_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE status_aktivitas (
  id_status_aktivitas SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode   VARCHAR(20)  DEFAULT NULL,
  nama   VARCHAR(100) NOT NULL,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id_status_aktivitas), UNIQUE KEY uk_staktiv_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------- Autentikasi & RBAC ---------------- */
CREATE TABLE roles (
  id_role    SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kode_role  VARCHAR(30)  NOT NULL,
  nama_role  VARCHAR(100) NOT NULL,
  keterangan VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_role), UNIQUE KEY uk_role_kode (kode_role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pengguna (
  id_pengguna      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_role          SMALLINT UNSIGNED NOT NULL COMMENT 'akun lokal = DOSEN',
  username         VARCHAR(100) NOT NULL COMMENT 'nomor registrasi dosen',
  password_hash    VARCHAR(255) NOT NULL,
  nama_lengkap     VARCHAR(200) NOT NULL,
  email            VARCHAR(150) DEFAULT NULL,
  status_aktif     ENUM('Aktif','Tidak Aktif') NOT NULL DEFAULT 'Aktif',
  last_login       DATETIME DEFAULT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengguna),
  UNIQUE KEY uk_pengguna_username (username),
  UNIQUE KEY uk_pengguna_email (email),
  KEY idx_pengguna_role (id_role),
  CONSTRAINT fk_pengguna_role FOREIGN KEY (id_role) REFERENCES roles (id_role) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------- Data dosen (baris laporan) ---------------- */
CREATE TABLE dosen (
  id_dosen              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pengguna           BIGINT UNSIGNED DEFAULT NULL COMMENT 'akun login dosen',
  id_program_studi      BIGINT UNSIGNED NOT NULL COMMENT 'penempatan / homebase',
  nomor_registrasi      VARCHAR(20)  NOT NULL,
  jenis_registrasi      ENUM('NIDN','NIDK','NUP') NOT NULL DEFAULT 'NIDN',
  nuptk                 VARCHAR(20)  DEFAULT NULL,
  nip                   VARCHAR(30)  DEFAULT NULL,
  nama_dosen            VARCHAR(150) NOT NULL COMMENT 'tanpa gelar',
  gelar_depan           VARCHAR(30)  DEFAULT NULL,
  gelar_belakang        VARCHAR(60)  DEFAULT NULL,
  jenis_kelamin         ENUM('L','P') NOT NULL,
  tempat_lahir          VARCHAR(100) DEFAULT NULL,
  tanggal_lahir         DATE         DEFAULT NULL,
  email                 VARCHAR(150) DEFAULT NULL,
  no_hp                 VARCHAR(30)  DEFAULT NULL,
  id_jabatan_fungsional SMALLINT UNSIGNED NOT NULL,
  id_pangkat_golongan   SMALLINT UNSIGNED DEFAULT NULL,
  id_jenjang_pendidikan SMALLINT UNSIGNED NOT NULL,
  id_rumpun_ilmu        SMALLINT UNSIGNED DEFAULT NULL,
  status_serdos         ENUM('Sudah','Belum') NOT NULL DEFAULT 'Belum',
  id_status_pegawai     SMALLINT UNSIGNED NOT NULL,
  id_ikatan_kerja       SMALLINT UNSIGNED NOT NULL,
  id_status_aktivitas   SMALLINT UNSIGNED NOT NULL,
  created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_dosen),
  UNIQUE KEY uk_dosen_noreg (nomor_registrasi),
  UNIQUE KEY uk_dosen_nuptk (nuptk),
  UNIQUE KEY uk_dosen_nip (nip),
  UNIQUE KEY uk_dosen_email (email),
  UNIQUE KEY uk_dosen_pengguna (id_pengguna),
  KEY idx_dosen_prodi (id_program_studi),
  KEY idx_dosen_nama (nama_dosen),
  CONSTRAINT fk_dosen_pengguna  FOREIGN KEY (id_pengguna)           REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_dosen_prodi     FOREIGN KEY (id_program_studi)      REFERENCES program_studi (id_program_studi) ON UPDATE CASCADE,
  CONSTRAINT fk_dosen_jabfung   FOREIGN KEY (id_jabatan_fungsional) REFERENCES jabatan_fungsional (id_jabatan_fungsional) ON UPDATE CASCADE,
  CONSTRAINT fk_dosen_pangkat   FOREIGN KEY (id_pangkat_golongan)   REFERENCES pangkat_golongan (id_pangkat_golongan) ON UPDATE CASCADE,
  CONSTRAINT fk_dosen_pend      FOREIGN KEY (id_jenjang_pendidikan) REFERENCES jenjang_pendidikan (id_jenjang_pendidikan) ON UPDATE CASCADE,
  CONSTRAINT fk_dosen_rumpun    FOREIGN KEY (id_rumpun_ilmu)        REFERENCES rumpun_ilmu (id_rumpun_ilmu) ON UPDATE CASCADE,
  CONSTRAINT fk_dosen_stpeg     FOREIGN KEY (id_status_pegawai)     REFERENCES status_pegawai (id_status_pegawai) ON UPDATE CASCADE,
  CONSTRAINT fk_dosen_ikatan    FOREIGN KEY (id_ikatan_kerja)       REFERENCES ikatan_kerja (id_ikatan_kerja) ON UPDATE CASCADE,
  CONSTRAINT fk_dosen_staktiv   FOREIGN KEY (id_status_aktivitas)   REFERENCES status_aktivitas (id_status_aktivitas) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id_audit    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pengguna BIGINT UNSIGNED DEFAULT NULL COMMENT 'akun dosen lokal; NULL bila admin SSO',
  pelaku      VARCHAR(150) DEFAULT NULL COMMENT 'nama & asal akun, mis. Admin (SSO SIM Mahasiswa)',
  aksi        VARCHAR(30)  NOT NULL,
  keterangan  VARCHAR(255) DEFAULT NULL,
  ip_address  VARCHAR(45)  DEFAULT NULL,
  waktu       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_audit),
  KEY idx_audit_waktu (waktu),
  CONSTRAINT fk_audit_pengguna FOREIGN KEY (id_pengguna) REFERENCES pengguna (id_pengguna) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------- View: satu baris = satu baris laporan ---------------- */
CREATE VIEW v_dosen AS
SELECT d.id_dosen, d.id_pengguna, d.nomor_registrasi, d.jenis_registrasi, d.nuptk, d.nip,
       d.nama_dosen, d.gelar_depan, d.gelar_belakang,
       CONCAT_WS(', ', CONCAT_WS(' ', d.gelar_depan, d.nama_dosen), NULLIF(d.gelar_belakang, '')) AS nama_lengkap,
       d.jenis_kelamin, d.tempat_lahir, d.tanggal_lahir, d.email, d.no_hp, d.status_serdos,
       jf.nama AS jabatan_fungsional, d.id_jabatan_fungsional,
       CONCAT_WS(' - ', pg.kode, pg.nama) AS kepangkatan, d.id_pangkat_golongan,
       jp.nama AS pendidikan_terakhir, d.id_jenjang_pendidikan,
       ri.nama AS rumpun_ilmu, d.id_rumpun_ilmu,
       sp.nama AS status_pegawai, d.id_status_pegawai,
       ik.nama AS ikatan_kerja, d.id_ikatan_kerja,
       sa.nama AS status_aktivitas, d.id_status_aktivitas,
       ps.id_program_studi, ps.kode_program_studi, ps.kode_pddikti, ps.nama_program_studi, ps.jenjang,
       f.id_fakultas, f.kode_fakultas, f.nama_fakultas,
       u.id_universitas, u.kode_universitas, u.nama_universitas,
       CONCAT(ps.jenjang, ' ', ps.nama_program_studi) AS penempatan,
       d.updated_at
  FROM dosen d
  JOIN program_studi ps       ON ps.id_program_studi = d.id_program_studi
  JOIN fakultas f             ON f.id_fakultas = ps.id_fakultas
  JOIN universitas u          ON u.id_universitas = f.id_universitas
  JOIN jabatan_fungsional jf  ON jf.id_jabatan_fungsional = d.id_jabatan_fungsional
  LEFT JOIN pangkat_golongan pg ON pg.id_pangkat_golongan = d.id_pangkat_golongan
  JOIN jenjang_pendidikan jp  ON jp.id_jenjang_pendidikan = d.id_jenjang_pendidikan
  LEFT JOIN rumpun_ilmu ri    ON ri.id_rumpun_ilmu = d.id_rumpun_ilmu
  JOIN status_pegawai sp      ON sp.id_status_pegawai = d.id_status_pegawai
  JOIN ikatan_kerja ik        ON ik.id_ikatan_kerja = d.id_ikatan_kerja
  JOIN status_aktivitas sa    ON sa.id_status_aktivitas = d.id_status_aktivitas;

/* ======================= DATA AWAL ======================= */
INSERT INTO universitas (id_universitas, kode_universitas, nama_universitas, alamat, kota, provinsi, website) VALUES
(1, 'UMB', 'Universitas Muhammadiyah Bengkulu', 'Jl. Bali, Kampung Bali', 'Bengkulu', 'Bengkulu', 'https://umb.ac.id');

INSERT INTO fakultas (id_fakultas, id_universitas, kode_fakultas, nama_fakultas) VALUES
(1, 1, 'FT', 'Fakultas Teknik'),
(2, 1, 'FEB', 'Fakultas Ekonomi dan Bisnis'),
(3, 1, 'FH', 'Fakultas Hukum'),
(4, 1, 'FKIP', 'Fakultas Keguruan dan Ilmu Pendidikan');

-- kode TI sama dengan SIM Mahasiswa (dipakai SIMSKRIPSI untuk mencocokkan prodi)
INSERT INTO program_studi (id_program_studi, id_fakultas, kode_program_studi, kode_pddikti, nama_program_studi, jenjang) VALUES
(1, 1, 'TI',  '55201', 'Teknik Informatika', 'S1'),
(2, 1, 'TS',  '22201', 'Teknik Sipil', 'S1'),
(3, 2, 'MNJ', '61201', 'Manajemen', 'S1'),
(4, 2, 'AKT', '62201', 'Akuntansi', 'S1'),
(5, 3, 'HK',  '74201', 'Ilmu Hukum', 'S1'),
(6, 4, 'PBI', '88203', 'Pendidikan Bahasa Inggris', 'S1');

INSERT INTO jabatan_fungsional (id_jabatan_fungsional, kode, nama, urutan) VALUES
(1, 'TP', 'Tenaga Pengajar', 1), (2, 'AA', 'Asisten Ahli', 2), (3, 'L', 'Lektor', 3), (4, 'LK', 'Lektor Kepala', 4), (5, 'GB', 'Profesor', 5);

INSERT INTO pangkat_golongan (id_pangkat_golongan, kode, nama, urutan) VALUES
(1, 'III/a', 'Penata Muda', 1), (2, 'III/b', 'Penata Muda Tingkat I', 2), (3, 'III/c', 'Penata', 3),
(4, 'III/d', 'Penata Tingkat I', 4), (5, 'IV/a', 'Pembina', 5), (6, 'IV/b', 'Pembina Tingkat I', 6),
(7, 'IV/c', 'Pembina Utama Muda', 7), (8, 'IV/d', 'Pembina Utama Madya', 8), (9, 'IV/e', 'Pembina Utama', 9);

INSERT INTO jenjang_pendidikan (id_jenjang_pendidikan, kode, nama, urutan) VALUES
(1, 'S1', 'S1', 1), (2, 'S2', 'S2', 2), (3, 'S3', 'S3', 3), (4, 'Sp-1', 'Spesialis 1', 4), (5, 'Sp-2', 'Spesialis 2', 5), (6, 'Pr', 'Profesi', 6);

INSERT INTO rumpun_ilmu (id_rumpun_ilmu, nama, urutan) VALUES
(1, 'Ilmu Komputer', 1), (2, 'Teknik Sipil', 2), (3, 'Manajemen', 3), (4, 'Akuntansi', 4), (5, 'Ilmu Hukum', 5), (6, 'Pendidikan Bahasa', 6);

INSERT INTO status_pegawai (id_status_pegawai, kode, nama, urutan) VALUES
(1, 'PNS', 'PNS', 1), (2, 'PPPK', 'PPPK', 2), (3, 'NON', 'Non PNS', 3);

INSERT INTO ikatan_kerja (id_ikatan_kerja, kode, nama, urutan) VALUES
(1, 'DT', 'Dosen Tetap', 1), (2, 'DTT', 'Dosen Tidak Tetap', 2), (3, 'DPK', 'Dosen PNS DPK', 3);

INSERT INTO status_aktivitas (id_status_aktivitas, kode, nama, urutan) VALUES
(1, 'A', 'Aktif', 1), (2, 'C', 'Cuti', 2), (3, 'TB', 'Tugas Belajar', 3), (4, 'IB', 'Ijin Belajar', 4),
(5, 'N', 'Tidak Aktif', 5), (6, 'P', 'Pensiun', 6), (7, 'M', 'Almarhum', 7);

INSERT INTO roles (id_role, kode_role, nama_role, keterangan) VALUES
(1, 'ADMIN', 'Admin', 'Mengelola seluruh data, master, laporan, dan akun dosen. Login lewat SSO SIM Mahasiswa (akun Admin SIM Mahasiswa).'),
(2, 'DOSEN', 'Dosen', 'Melihat data dirinya, memperbarui kontak dan password. Login dengan akun di database simdosen.');

-- Akun dosen (username = nomor registrasi). Password awal: simdosen123 (segera ganti).
-- Admin tidak ada di sini: gunakan akun Admin SIM Mahasiswa lewat tombol SSO.
INSERT INTO pengguna (id_pengguna, id_role, username, password_hash, nama_lengkap, email) VALUES
(1, 2, '0201018501', '$2y$10$E4iA4SVfCN3BW45cP.ooZuqWqlJgz00n5W4NAhyAiH9EG2/F7z.Gq', 'Dr. Ahmad, M.Kom.', 'ahmad@umb.ac.id'),
(2, 2, '0215038802', '$2y$10$E4iA4SVfCN3BW45cP.ooZuqWqlJgz00n5W4NAhyAiH9EG2/F7z.Gq', 'Rina Wati, M.T.', 'rina@umb.ac.id'),
(3, 2, '0220029001', '$2y$10$E4iA4SVfCN3BW45cP.ooZuqWqlJgz00n5W4NAhyAiH9EG2/F7z.Gq', 'Hendra Saputra, M.Kom.', 'hendra@umb.ac.id');

INSERT INTO dosen (id_pengguna, id_program_studi, nomor_registrasi, jenis_registrasi, nuptk, nip, nama_dosen, gelar_depan, gelar_belakang,
                   jenis_kelamin, tempat_lahir, tanggal_lahir, email, id_jabatan_fungsional, id_pangkat_golongan, id_jenjang_pendidikan,
                   id_rumpun_ilmu, status_serdos, id_status_pegawai, id_ikatan_kerja, id_status_aktivitas) VALUES
(1,    1, '0201018501', 'NIDN', '3533763664130123', '198501012010121001', 'Ahmad', 'Dr.', 'M.Kom.', 'L', 'Bengkulu', '1985-01-01', 'ahmad@umb.ac.id', 4, 5, 3, 1, 'Sudah', 3, 1, 1),
(2,    1, '0215038802', 'NIDN', '7547766667230152', NULL, 'Rina Wati', NULL, 'M.T.', 'P', 'Curup', '1988-03-15', 'rina@umb.ac.id', 3, 3, 2, 1, 'Sudah', 3, 1, 1),
(3,    1, '0220029001', 'NIDN', '1552768669130093', NULL, 'Hendra Saputra', NULL, 'M.Kom.', 'L', 'Manna', '1990-02-20', 'hendra@umb.ac.id', 2, 2, 2, 1, 'Belum', 3, 1, 1),
(NULL, 4, '0207078703', 'NIDN', '2039765666230113', NULL, 'Yuliana', NULL, 'S.E., M.Ak.', 'P', 'Bengkulu', '1987-07-07', 'yuliana@umb.ac.id', 3, 3, 2, 4, 'Sudah', 3, 1, 1),
(NULL, 6, '0210119004', 'NIDN', '4442768669230082', NULL, 'Dewi Sartika', NULL, 'M.Pd.', 'P', 'Kepahiang', '1990-11-10', 'dewi@umb.ac.id', 2, 2, 2, 6, 'Belum', 3, 1, 3),
(NULL, 5, '8812345601', 'NIDK', '9733745647110042', '196801121993031002', 'Bambang Irawan', 'Prof. Dr.', 'S.H., M.H.', 'L', 'Lahat', '1968-01-12', 'bambang@umb.ac.id', 5, 7, 3, 5, 'Sudah', 1, 3, 1);
