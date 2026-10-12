<?php
/**
 * inc/auth.php — autentikasi & RBAC (Role-Based Access Control) SIM Dosen.
 *
 * Dua peran:
 *   ADMIN : login lewat SSO SIM Mahasiswa (satu akun Admin milik SIM Mahasiswa)
 *   DOSEN : login dengan akun di tabel pengguna database simdosen (username = nomor registrasi)
 * Hak akses ditulis di satu tempat (IZIN) supaya mudah dibaca dan diubah.
 */

const LABEL_PERAN = ['ADMIN' => 'Admin', 'DOSEN' => 'Dosen'];

const IZIN = [
    'dashboard'       => ['ADMIN', 'DOSEN'],
    'dosen.lihat'     => ['ADMIN'],
    'dosen.kelola'    => ['ADMIN'],
    'dosen.hapus'     => ['ADMIN'],
    'laporan.lihat'   => ['ADMIN'],
    'master.kelola'   => ['ADMIN'],
    'pengguna.kelola' => ['ADMIN'],
    'profil'          => ['ADMIN', 'DOSEN'],
];

function user(): ?array { return $_SESSION['user'] ?? null; }
function peran(): string { return $_SESSION['user']['role'] ?? ''; }
function boleh(string $izin): bool { return in_array(peran(), IZIN[$izin] ?? [], true); }

/** Akun dosen lokal + id dosen yang terhubung. */
function muat_pengguna(int $id): ?array
{
    return db_row(
        "SELECT p.id_pengguna, p.username, p.nama_lengkap, p.email, p.status_aktif, r.kode_role, r.nama_role, d.id_dosen
           FROM pengguna p JOIN roles r ON r.id_role = p.id_role LEFT JOIN dosen d ON d.id_pengguna = p.id_pengguna
          WHERE p.id_pengguna = ?", [$id]);
}

/** Simpan akun dosen ke sesi setelah login berhasil. */
function sesi_masuk(array $p): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'sumber' => 'lokal', 'id' => (int) $p['id_pengguna'], 'username' => $p['username'], 'nama' => $p['nama_lengkap'],
        'email' => $p['email'], 'role' => $p['kode_role'], 'nama_role' => $p['nama_role'],
        'id_dosen' => $p['id_dosen'] !== null ? (int) $p['id_dosen'] : null,
    ];
    db_exec('UPDATE pengguna SET last_login = NOW() WHERE id_pengguna = ?', [(int) $p['id_pengguna']]);
    catat_log('LOGIN', 'Dosen masuk ke SIM Dosen');
}

/**
 * Login dosen: nomor registrasi/email + password. Mengembalikan data akun, atau pesan error (string).
 * Membatasi 5 percobaan gagal per 5 menit per sesi.
 */
function cek_login(string $user, string $pass)
{
    $_SESSION['gagal'] = $_SESSION['gagal'] ?? 0;
    if ($_SESSION['gagal'] >= 5 && time() - ($_SESSION['gagal_waktu'] ?? 0) < 300) return 'Terlalu banyak percobaan gagal. Tunggu 5 menit.';
    if ($user === '' || $pass === '') return 'Isi nomor registrasi dan password.';
    $row = db_row('SELECT id_pengguna, password_hash FROM pengguna WHERE username = ? OR email = ? LIMIT 1', [$user, $user]);
    if (!$row || !password_verify($pass, $row['password_hash'])) {
        $_SESSION['gagal']++;
        $_SESSION['gagal_waktu'] = time();
        return 'Nomor registrasi atau password salah. Admin silakan masuk lewat tombol SSO SIM Mahasiswa.';
    }
    $p = muat_pengguna((int) $row['id_pengguna']);
    if ($p['status_aktif'] !== 'Aktif') return 'Akun ini dinonaktifkan. Hubungi admin.';
    if ($p['kode_role'] !== 'DOSEN' || !$p['id_dosen']) return 'Akun ini belum terhubung dengan data dosen. Hubungi admin.';
    $_SESSION['gagal'] = 0;
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE pengguna SET password_hash = ? WHERE id_pengguna = ?', [password_hash($pass, PASSWORD_DEFAULT), (int) $row['id_pengguna']]);
    }
    return $p;
}

/**
 * Wajib login. Sesi diperiksa ulang setiap halaman:
 *   - Admin  : harus berasal dari SSO SIM Mahasiswa
 *   - Dosen  : akunnya harus masih ada, aktif, berperan DOSEN, dan terhubung ke data dosen
 * Sesi lama/tidak sah (mis. dari versi sebelumnya) langsung dibuang dan diminta login ulang.
 */
function wajib_login(): array
{
    $u = user();
    $sumber = $u['sumber'] ?? '';
    if ($sumber === 'simmhs' && ($u['role'] ?? '') === 'ADMIN') return $u;
    if ($sumber === 'lokal') {
        $p = muat_pengguna((int) ($u['id'] ?? 0));
        if ($p && $p['status_aktif'] === 'Aktif' && $p['kode_role'] === 'DOSEN' && $p['id_dosen']
            && $p['username'] === ($u['username'] ?? null) && (int) $p['id_dosen'] === (int) ($u['id_dosen'] ?? 0)) {
            return $u;
        }
    }
    if ($u) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Sesi Anda sudah tidak berlaku. Silakan masuk lagi.');
    } else {
        flash('info', 'Silakan masuk terlebih dahulu.');
    }
    redirect('login.php');
}

/** Wajib login + punya izin tertentu. */
function wajib_izin(string $izin): array
{
    $u = wajib_login();
    if (!boleh($izin)) {
        http_response_code(403);
        flash('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        redirect('dashboard.php');
    }
    return $u;
}

/** Syarat SQL cakupan data dosen: admin semua, dosen hanya dirinya. $v = alias v_dosen. */
function scope_dosen(string $v = 'v'): array
{
    $u = user();
    return match ($u['role'] ?? '') {
        'ADMIN' => ['', []],
        'DOSEN' => [" AND $v.id_dosen = ?", [(int) $u['id_dosen']]],
        default => [' AND 1=0', []],
    };
}

function catat_log(string $aksi, string $ket = ''): void
{
    $u = user();
    try {
        db_q('INSERT INTO audit_log (id_pengguna, pelaku, aksi, keterangan, ip_address) VALUES (?, ?, ?, ?, ?)', [
            ($u['sumber'] ?? '') === 'lokal' ? $u['id'] : null,
            $u ? mb_substr(($u['nama'] ?? '-') . (($u['sumber'] ?? '') === 'simmhs' ? ' (SSO SIM Mahasiswa)' : ' (dosen)'), 0, 150) : null,
            $aksi, mb_substr($ket, 0, 255), $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (mysqli_sql_exception $e) {
        error_log('audit_log: ' . $e->getMessage());
    }
}

/** Opsi <select> dari tabel: [id => label]. */
function opsi_tabel(string $tabel, string $pk, string $label = 'nama', string $urut = 'urutan, nama'): array
{
    $hasil = [];
    foreach (db_rows("SELECT $pk AS id, $label AS label FROM $tabel ORDER BY $urut") as $r) $hasil[$r['id']] = $r['label'];
    return $hasil;
}
function opsi_html(array $opsi, $terpilih = null, string $kosong = '— Pilih —'): string
{
    $h = $kosong !== '' ? '<option value="">' . e($kosong) . '</option>' : '';
    foreach ($opsi as $id => $label) $h .= '<option value="' . e($id) . '"' . ((string) $terpilih === (string) $id ? ' selected' : '') . '>' . e($label) . '</option>';
    return $h;
}
