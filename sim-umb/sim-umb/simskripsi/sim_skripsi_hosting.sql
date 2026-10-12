-- =====================================================================
--  DATABASE  : sim_skripsi
--  APLIKASI  : SIMSKRIPSI — Universitas Muhammadiyah Bengkulu
--  SERVER    : MySQL / MariaDB (XAMPP), satu server dengan simmahasiswa71
--
--  Pembagian data:
--  - simmahasiswa71 (SIM Mahasiswa, tidak diubah): universitas, fakultas,
--    program_studi, roles, pengguna, mahasiswa. Admin, Operator Prodi,
--    Dekanat, dan Mahasiswa login lewat SSO SIM Mahasiswa; datanya
--    dikirim ke SIMSKRIPSI sebagai JSON melalui web service.
--  - simdosen (SIM Dosen): akun & data dosen; dosen login lewat SSO SIM Dosen.
--  - sim_skripsi (file ini): data proses skripsi + salinan data dosen & mahasiswa.
--
--  SIMSKRIPSI TIDAK membaca database SIM Mahasiswa secara langsung.
--  Kolom yang menunjuk ke SIM Mahasiswa (id_mahasiswa, id_program_studi,
--  id_pengguna) memakai BIGINT UNSIGNED, sama dengan tipe di sana, tanpa
--  FOREIGN KEY lintas database.
--
--  VERSI HOSTING (InfinityFree): tanpa CREATE DATABASE / USE.
--  1. Buat database di panel InfinityFree (mis. if0_xxxxxxxx_simskripsi).
--  2. Buka phpMyAdmin, KLIK nama database itu di kiri, lalu tab Import > file ini > Go.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW  IF EXISTS v_pengajuan_mahasiswa, v_kelengkapan_dokumen;
DROP TABLE IF EXISTS pengguna_sso, pengumuman, dokumen, jenis_dokumen, sidang_skripsi,
                     seminar_proposal, bimbingan, pembimbing, pengajuan_judul, dosen;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. DOSEN — SALINAN data dari SIM Dosen (web service JSON api/dosen.php)
--    Dosen login ke SIMSKRIPSI lewat SSO SIM Dosen, jadi TIDAK ada password di sini.
--    Diperbarui otomatis saat dosen login dan lewat tombol "Sinkron dari SIM Dosen".
--    Hanya kuota_bimbingan yang milik SIMSKRIPSI sendiri.
-- ---------------------------------------------------------------------
CREATE TABLE dosen (
  id_dosen             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_simdosen          BIGINT UNSIGNED NOT NULL COMMENT '-> SIM Dosen: dosen.id_dosen',
  id_pengguna_simdosen BIGINT UNSIGNED DEFAULT NULL COMMENT '-> SIM Dosen: pengguna.id_pengguna (akun SSO)',
  nomor_registrasi     VARCHAR(20)  NOT NULL COMMENT 'NIDN/NIDK/NUP',
  nuptk                VARCHAR(20)  DEFAULT NULL,
  nip                  VARCHAR(30)  DEFAULT NULL,
  nama_dosen           VARCHAR(150) NOT NULL,
  gelar_depan          VARCHAR(30)  DEFAULT NULL,
  gelar_belakang       VARCHAR(60)  DEFAULT NULL,
  email                VARCHAR(150) DEFAULT NULL,
  kode_program_studi   VARCHAR(20)  DEFAULT NULL COMMENT 'kunci penghubung antar sistem, mis. TI',
  nama_program_studi   VARCHAR(200) DEFAULT NULL,
  id_program_studi     BIGINT UNSIGNED DEFAULT NULL COMMENT 'id prodi di SIM Mahasiswa (dicocokkan lewat kode)',
  jabatan_fungsional   VARCHAR(100) DEFAULT NULL,
  pendidikan_terakhir  VARCHAR(100) DEFAULT NULL,
  status_aktivitas     VARCHAR(100) DEFAULT NULL COMMENT 'dari SIM Dosen, mis. Aktif / Tugas Belajar',
  status_aktif         ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif' COMMENT 'Aktif bila status_aktivitas = Aktif',
  kuota_bimbingan      TINYINT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'diatur di SIMSKRIPSI',
  last_login           DATETIME DEFAULT NULL,
  terakhir_sinkron     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_dosen),
  UNIQUE KEY uk_dosen_simdosen (id_simdosen),
  UNIQUE KEY uk_dosen_noreg (nomor_registrasi),
  UNIQUE KEY uk_dosen_pengguna (id_pengguna_simdosen),
  KEY idx_dosen_prodi (id_program_studi)
) ENGINE=InnoDB COMMENT='Cache data dosen dari SIM Dosen';

-- ---------------------------------------------------------------------
-- 2. PENGAJUAN JUDUL — diajukan mahasiswa, diproses operator prodi
--    Satu mahasiswa hanya boleh punya SATU pengajuan yang masih berjalan
--    (Diajukan / Revisi / Disetujui). Yang Ditolak boleh mengajukan lagi.
-- ---------------------------------------------------------------------
CREATE TABLE pengajuan_judul (
  id_pengajuan        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_mahasiswa        BIGINT UNSIGNED NOT NULL COMMENT '-> SIM Mahasiswa: mahasiswa',
  judul               VARCHAR(300) NOT NULL,
  bidang_kajian       VARCHAR(100) DEFAULT NULL,
  latar_belakang      TEXT         DEFAULT NULL COMMENT 'ringkasan masalah & alasan memilih judul',
  usulan_dosen        INT UNSIGNED DEFAULT NULL COMMENT 'calon pembimbing yang diusulkan mahasiswa',
  status              ENUM('Diajukan','Revisi','Disetujui','Ditolak') NOT NULL DEFAULT 'Diajukan',
  catatan_prodi       TEXT         DEFAULT NULL,
  diproses_oleh       BIGINT UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna (operator prodi)',
  tanggal_pengajuan   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tanggal_diproses    DATETIME     DEFAULT NULL,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- bernilai id_mahasiswa selama pengajuan masih berjalan, NULL bila ditolak
  kunci_aktif         BIGINT UNSIGNED AS (IF(status = 'Ditolak', NULL, id_mahasiswa)) STORED,
  PRIMARY KEY (id_pengajuan),
  UNIQUE KEY uk_pengajuan_aktif (kunci_aktif),
  KEY idx_pengajuan_mhs (id_mahasiswa),
  KEY idx_pengajuan_status (status),
  CONSTRAINT fk_pengajuan_usulan FOREIGN KEY (usulan_dosen)
    REFERENCES dosen (id_dosen) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Pengajuan judul skripsi';

-- ---------------------------------------------------------------------
-- 3. PEMBIMBING — ditetapkan prodi setelah judul disetujui
--    Maksimal Pembimbing 1 dan Pembimbing 2 per pengajuan.
-- ---------------------------------------------------------------------
CREATE TABLE pembimbing (
  id_pembimbing       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pengajuan        INT UNSIGNED NOT NULL,
  id_dosen            INT UNSIGNED NOT NULL,
  peran               ENUM('Pembimbing 1','Pembimbing 2') NOT NULL,
  nomor_sk            VARCHAR(100) DEFAULT NULL,
  tanggal_sk          DATE         DEFAULT NULL,
  ditetapkan_oleh     BIGINT UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pembimbing),
  UNIQUE KEY uk_pembimbing_peran (id_pengajuan, peran),
  UNIQUE KEY uk_pembimbing_dosen (id_pengajuan, id_dosen),
  KEY idx_pembimbing_dosen (id_dosen),
  CONSTRAINT fk_pembimbing_pengajuan FOREIGN KEY (id_pengajuan)
    REFERENCES pengajuan_judul (id_pengajuan) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_pembimbing_dosen FOREIGN KEY (id_dosen)
    REFERENCES dosen (id_dosen) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB COMMENT='Dosen pembimbing skripsi';

-- ---------------------------------------------------------------------
-- 4. BIMBINGAN (tambahan) — catatan konsultasi, sesuai mockup halaman
--    "Detail Proposal dan Bimbingan".
-- ---------------------------------------------------------------------
CREATE TABLE bimbingan (
  id_bimbingan        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pengajuan        INT UNSIGNED NOT NULL,
  id_dosen            INT UNSIGNED NOT NULL,
  tanggal             DATE         NOT NULL,
  topik               VARCHAR(200) NOT NULL COMMENT 'yang dikonsultasikan mahasiswa',
  file_mahasiswa      VARCHAR(255) DEFAULT NULL,
  catatan_dosen       TEXT         DEFAULT NULL,
  file_dosen          VARCHAR(255) DEFAULT NULL,
  status              ENUM('Menunggu','Direspons') NOT NULL DEFAULT 'Menunggu',
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_bimbingan),
  KEY idx_bimbingan_pengajuan (id_pengajuan, tanggal),
  KEY idx_bimbingan_dosen (id_dosen, status),
  CONSTRAINT fk_bimbingan_pengajuan FOREIGN KEY (id_pengajuan)
    REFERENCES pengajuan_judul (id_pengajuan) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_bimbingan_dosen FOREIGN KEY (id_dosen)
    REFERENCES dosen (id_dosen) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB COMMENT='Catatan bimbingan';

-- ---------------------------------------------------------------------
-- 5. SEMINAR PROPOSAL — jadwal, penguji, dan hasil
--    Satu pengajuan bisa seminar lebih dari sekali (bila Tidak Lulus).
-- ---------------------------------------------------------------------
CREATE TABLE seminar_proposal (
  id_seminar          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pengajuan        INT UNSIGNED NOT NULL,
  tanggal             DATE         NOT NULL,
  jam_mulai           TIME         NOT NULL,
  jam_selesai         TIME         NOT NULL,
  ruangan             VARCHAR(100) NOT NULL,
  id_penguji_1        INT UNSIGNED NOT NULL,
  id_penguji_2        INT UNSIGNED DEFAULT NULL,
  status              ENUM('Dijadwalkan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Dijadwalkan',
  hasil               ENUM('Lulus','Lulus dengan Revisi','Tidak Lulus') DEFAULT NULL,
  nilai               DECIMAL(5,2) DEFAULT NULL,
  catatan_revisi      TEXT         DEFAULT NULL,
  dijadwalkan_oleh    BIGINT UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_seminar),
  KEY idx_seminar_pengajuan (id_pengajuan),
  KEY idx_seminar_jadwal (tanggal, ruangan),
  CONSTRAINT fk_seminar_pengajuan FOREIGN KEY (id_pengajuan)
    REFERENCES pengajuan_judul (id_pengajuan) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_seminar_penguji1 FOREIGN KEY (id_penguji_1)
    REFERENCES dosen (id_dosen) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_seminar_penguji2 FOREIGN KEY (id_penguji_2)
    REFERENCES dosen (id_dosen) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_seminar_jam     CHECK (jam_selesai > jam_mulai),
  CONSTRAINT ck_seminar_penguji CHECK (id_penguji_2 IS NULL OR id_penguji_2 <> id_penguji_1),
  CONSTRAINT ck_seminar_nilai   CHECK (nilai IS NULL OR nilai BETWEEN 0 AND 100)
) ENGINE=InnoDB COMMENT='Seminar proposal';

-- ---------------------------------------------------------------------
-- 6. SIDANG SKRIPSI — jadwal, tiga penguji, nilai akhir
-- ---------------------------------------------------------------------
CREATE TABLE sidang_skripsi (
  id_sidang           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_pengajuan        INT UNSIGNED NOT NULL,
  judul_akhir         VARCHAR(300) DEFAULT NULL COMMENT 'diisi bila judul berubah setelah seminar',
  tanggal             DATE         NOT NULL,
  jam_mulai           TIME         NOT NULL,
  jam_selesai         TIME         NOT NULL,
  ruangan             VARCHAR(100) NOT NULL,
  id_penguji_1        INT UNSIGNED NOT NULL,
  id_penguji_2        INT UNSIGNED NOT NULL,
  id_penguji_3        INT UNSIGNED DEFAULT NULL,
  status              ENUM('Dijadwalkan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Dijadwalkan',
  hasil               ENUM('Lulus','Lulus dengan Revisi','Tidak Lulus') DEFAULT NULL,
  nilai_angka         DECIMAL(5,2) DEFAULT NULL,
  nilai_huruf         ENUM('A','A-','B+','B','B-','C+','C','D','E') DEFAULT NULL,
  catatan_revisi      TEXT         DEFAULT NULL,
  batas_revisi        DATE         DEFAULT NULL,
  dijadwalkan_oleh    BIGINT UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_sidang),
  KEY idx_sidang_pengajuan (id_pengajuan),
  KEY idx_sidang_jadwal (tanggal, ruangan),
  CONSTRAINT fk_sidang_pengajuan FOREIGN KEY (id_pengajuan)
    REFERENCES pengajuan_judul (id_pengajuan) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_sidang_penguji1 FOREIGN KEY (id_penguji_1)
    REFERENCES dosen (id_dosen) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_sidang_penguji2 FOREIGN KEY (id_penguji_2)
    REFERENCES dosen (id_dosen) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_sidang_penguji3 FOREIGN KEY (id_penguji_3)
    REFERENCES dosen (id_dosen) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_sidang_jam     CHECK (jam_selesai > jam_mulai),
  CONSTRAINT ck_sidang_penguji CHECK (id_penguji_1 <> id_penguji_2
                                      AND (id_penguji_3 IS NULL OR id_penguji_3 NOT IN (id_penguji_1, id_penguji_2))),
  CONSTRAINT ck_sidang_nilai   CHECK (nilai_angka IS NULL OR nilai_angka BETWEEN 0 AND 100)
) ENGINE=InnoDB COMMENT='Sidang skripsi';

-- ---------------------------------------------------------------------
-- 7. JENIS DOKUMEN — daftar berkas yang harus diunggah per tahap.
--    Inilah acuan "kelengkapan": prodi cukup mengatur daftar ini.
-- ---------------------------------------------------------------------
CREATE TABLE jenis_dokumen (
  id_jenis            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama_dokumen        VARCHAR(100) NOT NULL,
  tahap               ENUM('Pengajuan Judul','Seminar Proposal','Sidang Skripsi') NOT NULL,
  wajib               TINYINT(1)   NOT NULL DEFAULT 1,
  format_file         VARCHAR(50)  NOT NULL DEFAULT 'pdf' COMMENT 'ekstensi diizinkan, pisahkan koma',
  maks_ukuran_kb      INT UNSIGNED NOT NULL DEFAULT 2048,
  urutan              TINYINT UNSIGNED NOT NULL DEFAULT 0,
  status_aktif        ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  PRIMARY KEY (id_jenis),
  UNIQUE KEY uk_jenis_dokumen (tahap, nama_dokumen)
) ENGINE=InnoDB COMMENT='Master persyaratan dokumen';

-- ---------------------------------------------------------------------
-- 8. DOKUMEN — file yang diunggah mahasiswa + hasil verifikasi.
--    Satu jenis dokumen = satu file per mahasiswa (unggah ulang menimpa).
-- ---------------------------------------------------------------------
CREATE TABLE dokumen (
  id_dokumen          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_mahasiswa        BIGINT UNSIGNED NOT NULL COMMENT '-> SIM Mahasiswa: mahasiswa',
  id_jenis            INT UNSIGNED NOT NULL,
  nama_file_asli      VARCHAR(255) NOT NULL,
  path_file           VARCHAR(255) NOT NULL COMMENT 'nama file acak di folder uploads/',
  ukuran_kb           INT UNSIGNED NOT NULL,
  status_verifikasi   ENUM('Menunggu','Valid','Ditolak') NOT NULL DEFAULT 'Menunggu',
  catatan_verifikasi  VARCHAR(255) DEFAULT NULL,
  diverifikasi_oleh   BIGINT UNSIGNED DEFAULT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  tanggal_upload      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  tanggal_verifikasi  DATETIME     DEFAULT NULL,
  PRIMARY KEY (id_dokumen),
  UNIQUE KEY uk_dokumen_mhs_jenis (id_mahasiswa, id_jenis),
  KEY idx_dokumen_status (status_verifikasi),
  CONSTRAINT fk_dokumen_jenis FOREIGN KEY (id_jenis)
    REFERENCES jenis_dokumen (id_jenis) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB COMMENT='Berkas unggahan mahasiswa';

-- ---------------------------------------------------------------------
-- 9. PENGUMUMAN — dibuat admin/prodi, tampil di landing & dashboard
-- ---------------------------------------------------------------------
CREATE TABLE pengumuman (
  id_pengumuman       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  judul               VARCHAR(200) NOT NULL,
  isi                 TEXT         NOT NULL,
  kategori            ENUM('Umum','Pengajuan Judul','Seminar','Sidang','Yudisium') NOT NULL DEFAULT 'Umum',
  sasaran             ENUM('Semua','Mahasiswa','Dosen') NOT NULL DEFAULT 'Semua',
  id_program_studi    BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULL = semua prodi; -> SIM Mahasiswa: program_studi',
  lampiran            VARCHAR(255) DEFAULT NULL,
  status              ENUM('Draf','Terbit') NOT NULL DEFAULT 'Draf',
  tanggal_terbit      DATETIME     DEFAULT NULL,
  tanggal_berakhir    DATE         DEFAULT NULL COMMENT 'NULL = tampil terus',
  dibuat_oleh         BIGINT UNSIGNED NOT NULL COMMENT '-> SIM Mahasiswa: pengguna',
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengumuman),
  KEY idx_pengumuman_tampil (status, tanggal_terbit),
  KEY idx_pengumuman_prodi (id_program_studi)
) ENGINE=InnoDB COMMENT='Pengumuman';

-- ---------------------------------------------------------------------
-- 9b. PENGGUNA SSO — salinan data dari JSON SIM Mahasiswa.
--     Diisi/diperbarui otomatis setiap kali pengguna login lewat SSO,
--     supaya daftar proposal tidak memanggil API untuk setiap baris.
--     Sumber kebenaran tetap SIM Mahasiswa.
-- ---------------------------------------------------------------------
CREATE TABLE pengguna_sso (
  id_pengguna         BIGINT UNSIGNED NOT NULL COMMENT 'sama dengan id_pengguna di SIM Mahasiswa',
  username            VARCHAR(100) NOT NULL,
  nama_lengkap        VARCHAR(200) NOT NULL,
  email               VARCHAR(150) DEFAULT NULL,
  kode_role           VARCHAR(30)  NOT NULL COMMENT 'ADMIN, OPERATOR_PRODI, DEKANAT, MAHASISWA',
  id_fakultas         BIGINT UNSIGNED DEFAULT NULL,
  id_program_studi    BIGINT UNSIGNED DEFAULT NULL,
  nama_program_studi  VARCHAR(200) DEFAULT NULL,
  id_mahasiswa        BIGINT UNSIGNED DEFAULT NULL COMMENT 'diisi bila kode_role = MAHASISWA',
  npm                 VARCHAR(30)  DEFAULT NULL,
  terakhir_sinkron    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pengguna),
  UNIQUE KEY uk_sso_mahasiswa (id_mahasiswa),
  UNIQUE KEY uk_sso_npm (npm),
  KEY idx_sso_role (kode_role),
  KEY idx_sso_prodi (id_program_studi)
) ENGINE=InnoDB COMMENT='Cache data pengguna dari SSO SIM Mahasiswa';

-- ---------------------------------------------------------------------
-- 10. VIEW
-- ---------------------------------------------------------------------
-- Kelengkapan dokumen: setiap mahasiswa yang punya pengajuan aktif x
-- setiap jenis dokumen aktif. Baris dengan status "Belum diunggah"
-- berarti berkas itu masih kurang.
CREATE VIEW v_kelengkapan_dokumen AS
SELECT pj.id_mahasiswa,
       pj.id_pengajuan,
       jd.id_jenis,
       jd.tahap,
       jd.nama_dokumen,
       jd.wajib,
       d.id_dokumen,
       d.nama_file_asli,
       d.tanggal_upload,
       COALESCE(d.status_verifikasi, 'Belum diunggah') AS status_dokumen
FROM pengajuan_judul pj
CROSS JOIN jenis_dokumen jd
LEFT JOIN dokumen d ON d.id_mahasiswa = pj.id_mahasiswa AND d.id_jenis = jd.id_jenis
WHERE pj.status <> 'Ditolak' AND jd.status_aktif = 'Aktif';

-- Pengajuan lengkap dengan NPM, nama, prodi (dari salinan JSON SSO)
-- dan nama pembimbing. Tidak lagi membaca database SIM Mahasiswa.
CREATE VIEW v_pengajuan_mahasiswa AS
SELECT pj.id_pengajuan,
       pj.id_mahasiswa,
       ps.npm,
       ps.nama_lengkap      AS nama_mahasiswa,
       ps.id_program_studi,
       ps.nama_program_studi,
       pj.judul,
       pj.status,
       pj.tanggal_pengajuan,
       (SELECT CONCAT_WS(', ', CONCAT_WS(' ', d.gelar_depan, d.nama_dosen), d.gelar_belakang)
          FROM pembimbing pb JOIN dosen d ON d.id_dosen = pb.id_dosen
         WHERE pb.id_pengajuan = pj.id_pengajuan AND pb.peran = 'Pembimbing 1') AS pembimbing_1,
       (SELECT CONCAT_WS(', ', CONCAT_WS(' ', d.gelar_depan, d.nama_dosen), d.gelar_belakang)
          FROM pembimbing pb JOIN dosen d ON d.id_dosen = pb.id_dosen
         WHERE pb.id_pengajuan = pj.id_pengajuan AND pb.peran = 'Pembimbing 2') AS pembimbing_2
FROM pengajuan_judul pj
LEFT JOIN pengguna_sso ps ON ps.id_mahasiswa = pj.id_mahasiswa;

-- ---------------------------------------------------------------------
-- 11. DATA AWAL
-- ---------------------------------------------------------------------
-- Data dosen TIDAK diisi di sini: diambil dari SIM Dosen lewat tombol
-- "Sinkron dari SIM Dosen" (menu Data Dosen) atau otomatis saat dosen login SSO.

INSERT INTO jenis_dokumen (nama_dokumen, tahap, wajib, format_file, maks_ukuran_kb, urutan) VALUES
('Transkrip nilai sementara',          'Pengajuan Judul',  1, 'pdf',      2048,  1),
('Kartu Rencana Studi (KRS) skripsi',  'Pengajuan Judul',  1, 'pdf',      1024,  2),
('Draf proposal',                      'Seminar Proposal', 1, 'pdf',      5120,  3),
('Kartu bimbingan proposal',           'Seminar Proposal', 1, 'pdf',      2048,  4),
('Lembar persetujuan seminar',         'Seminar Proposal', 1, 'pdf',      1024,  5),
('Naskah skripsi lengkap',             'Sidang Skripsi',   1, 'pdf',     10240,  6),
('Kartu bimbingan skripsi',            'Sidang Skripsi',   1, 'pdf',      2048,  7),
('Bukti bebas pustaka',                'Sidang Skripsi',   1, 'pdf,jpg',  1024,  8),
('Bukti lunas administrasi',           'Sidang Skripsi',   1, 'pdf,jpg',  1024,  9),
('Sertifikat TOEFL',                   'Sidang Skripsi',   0, 'pdf,jpg',  1024, 10);

-- dibuat_oleh = 6 -> akun Admin di SIM Mahasiswa
INSERT INTO pengumuman (judul, isi, kategori, sasaran, status, tanggal_terbit, dibuat_oleh) VALUES
('Pembukaan pengajuan judul skripsi semester ganjil',
 'Pengajuan judul skripsi dibuka melalui SIMSKRIPSI. Pastikan transkrip dan KRS skripsi sudah diunggah sebelum mengajukan judul.',
 'Pengajuan Judul', 'Mahasiswa', 'Terbit', NOW(), 6),
('Jadwal seminar proposal gelombang I',
 'Jadwal seminar proposal gelombang I dapat dilihat di menu Seminar. Dosen penguji diharapkan mengonfirmasi kehadiran.',
 'Seminar', 'Semua', 'Terbit', NOW(), 6);
