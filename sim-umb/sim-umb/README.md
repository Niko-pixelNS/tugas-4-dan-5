# SIM Mahasiswa · SIM Dosen · SIMSKRIPSI — Universitas Muhammadiyah Bengkulu

Tiga aplikasi, tiga database, terhubung lewat **SSO** dan **web service JSON**:

| Aplikasi | Database | Akun yang login di sini | Folder di zip |
|---|---|---|---|
| SIM Mahasiswa (sudah ada) | `simmahasiswa71` | Admin, Operator Prodi, Dekanat, Mahasiswa | `simmahasiswa71-tambahan/` (file baru saja) |
| **SIM Dosen** (tugas Praktikum 2345) | `simdosen` | Admin, Operator Prodi, Dekanat, Rektorat, **Dosen** | `simdosen/` |
| SIMSKRIPSI | `sim_skripsi` | Semua lewat SSO: SIM Mahasiswa atau SIM Dosen | `simskripsi/` |

SIMSKRIPSI tidak pernah membuka database lain. Data mahasiswa diambil dari API SIM Mahasiswa, data dosen dari API SIM Dosen.
Kunci penghubung antar sistem: **NPM** (mahasiswa), **nomor registrasi** (dosen), **kode prodi** (mis. `TI`).

## Cara pasang (XAMPP)

1. Salin `simdosen/` dan `simskripsi/` ke `htdocs/`. Salin **isi** `simmahasiswa71-tambahan/` (`config/`, `sso/`, `api/`) ke `htdocs/simmahasiswa71/` — `config/koneksi.php` lama diganti versi baru.
2. phpMyAdmin → Import, berurutan: `simdosen/simdosen.sql`, lalu `simskripsi/sim_skripsi.sql`.
3. Ganti kunci rahasia (teks acak Anda sendiri):
   - `SSO_KUNCI` di `simmahasiswa71/config/sso.php` dan di `simdosen/config/sso.php` (boleh berbeda).
   - `client_secret` di `simmahasiswa71/config/sso.php` = `SSO_CLIENT_SECRET` di `simskripsi/config/app.php`.
   - `client_secret` di `simdosen/config/sso.php` = `SSO_SECRET_SIMDOSEN` di `simskripsi/config/app.php`.
4. Taruh `logo-umb.png` dan `kampus.jpg` di `assets/img/` tiap aplikasi.
5. Buka `http://localhost/simdosen/` dan `http://localhost/simskripsi/`.
6. Di SIMSKRIPSI, login sebagai Admin → **Data Dosen → Sinkron dari SIM Dosen** untuk mengambil semua dosen.

## Akun contoh

| Aplikasi | Username | Password |
|---|---|---|
| SIM Dosen | `admin`, `operator.ti`, `dekanat.ft`, `rektorat` | `simdosen123` |
| SIM Dosen (dosen) | `0201018501` (Dr. Ahmad), `0215038802` (Rina Wati), `0220029001` (Hendra Saputra) | `simdosen123` |
| SIM Mahasiswa | `Admin`, `operator.ti`, `dekanat.ft`, `20260001` | atur dulu di SIM Mahasiswa (sebagian masih placeholder) |

Ganti semua password setelah login pertama.

## SIM Dosen (sesuai tugas)

| File | Isi |
|---|---|
| `index.php` | Landing page + statistik dosen |
| `login.php` | Login & logout |
| `inc/db.php`, `inc/init.php`, `inc/auth.php` | Koneksi, sesi, CSRF, **autentikasi & RBAC** (`IZIN` per peran + cakupan data) |
| `dashboard.php` | Ringkasan sesuai peran |
| `dosen.php` | CRUD dosen + akun login dosen |
| `master.php` | CRUD universitas, fakultas, prodi, dan 7 tabel referensi |
| `pengguna.php` | CRUD akun & peran, tabel hak akses |
| `laporan.php` | "Daftar Dosen Aktif Universitas" — layar, cetak (A4 landscape), unduh Excel |
| `profil.php` | Data diri dosen, kontak, ganti password |
| `sso/`, `api/` | SSO untuk SIMSKRIPSI & web service JSON |

Tabel `simdosen` (hasil normalisasi laporan): `universitas`, `fakultas`, `program_studi`, `jabatan_fungsional`, `pangkat_golongan`,
`jenjang_pendidikan`, `rumpun_ilmu`, `status_pegawai`, `ikatan_kerja`, `status_aktivitas`, `dosen`, `roles`, `pengguna`, `audit_log`,
dan view `v_dosen` (satu baris = satu baris laporan).

Hak akses: Admin semua · Operator Prodi kelola dosen prodinya · Dekanat lihat fakultasnya · Rektorat lihat semua · Dosen lihat & ubah kontak dirinya.

### Web service SIM Dosen (JSON, header `Authorization: Bearer <token>`)

| Endpoint | Fungsi |
|---|---|
| `POST api/token.php` | `grant_type=client_credentials` (antarserver) atau `authorization_code` (SSO) |
| `GET api/dosen.php?nomor_registrasi=0201018501` / `?id=1` | Satu dosen |
| `GET api/dosen.php?kode_prodi=TI&status=Aktif&halaman=1&per_halaman=20` | Daftar dosen |
| `GET api/prodi.php` | Daftar prodi & fakultas |

```
curl -X POST http://localhost/simdosen/api/token.php -d "grant_type=client_credentials&client_id=simskripsi&client_secret=RAHASIA"
curl -H "Authorization: Bearer TOKEN" "http://localhost/simdosen/api/dosen.php?kode_prodi=TI"
```

## Alur login SIMSKRIPSI

- **Masuk dengan SSO SIM Mahasiswa** → admin, prodi, dekanat, mahasiswa.
- **Masuk dengan SSO SIM Dosen** → dosen (hanya peran Dosen yang diterima). Bila dosen sudah login di SIM Dosen, langsung masuk tanpa mengetik password.
- Keluar dari SIMSKRIPSI juga mengakhiri sesi SSO asalnya.

## XAMPP lokal & InfinityFree

Setiap aplikasi punya `config/lingkungan.php`: `localhost`/IP Wi-Fi → `DB_LOKAL`, domain lain → `DB_ONLINE`.
Isi bagian "ISI DI SINI". File SQL versi hosting: `*_hosting.sql` (tanpa `CREATE DATABASE`/`DEFINER`).

Batasan InfinityFree gratis: database tidak bisa diakses dari luar akun hosting, dan **panggilan API antar aplikasi diblokir**
sistem keamanannya — halaman web tetap jalan, tetapi SSO & sinkron data tidak. Untuk demo SSO dan JSON, gunakan XAMPP lokal.
