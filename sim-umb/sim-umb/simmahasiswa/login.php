<?php
/**
 * SIM Mahasiswa — Login & Logout
 * Login memakai email + kata sandi dari tabel `pengguna` (kolom email, password_hash).
 * Logout: POST ke file ini dengan aksi=logout (dipakai tombol "Keluar" di dashboard.php & index.php).
 * Koneksi database: config/koneksi.php · Tampilan: assets/css/style.css · Skrip: assets/js/script.js
 */
declare(strict_types=1);
ob_start(); // cegah error "headers already sent" bila ada spasi/baris kosong di akhir koneksi.php

const APP_NAME          = 'SIM Mahasiswa';
const SETELAH_LOGIN     = 'dashboard.php'; // halaman tujuan setelah berhasil masuk
const MAKS_PERCOBAAN    = 5;             // percobaan gagal sebelum dikunci
const LAMA_KUNCI_DETIK  = 300;           // lama kunci (5 menit)
const PESAN_GAGAL       = 'Email atau kata sandi salah.';

/* ---------- Sesi ---------- */
session_name('SIMMHS');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

/* ---------- Bantuan ---------- */
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pindah(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}
function csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? '';
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
/** Catat ke audit_log; kegagalan mencatat tidak menghentikan login. */
function catat(mysqli $koneksi, int $idPengguna, string $aksi): void
{
    try {
        db_jalankan(
            $koneksi,
            'INSERT INTO audit_log (id_pengguna, tabel_nama, record_id, aksi, ip_address, user_agent, waktu)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $idPengguna, 'pengguna', (string) $idPengguna, $aksi,
                substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]
        );
    } catch (mysqli_sql_exception $ex) {
        error_log('Gagal mencatat audit_log: ' . $ex->getMessage());
    }
}
function gagal_bertambah(): void
{
    $_SESSION['login_gagal'] = (int) ($_SESSION['login_gagal'] ?? 0) + 1;
    if ($_SESSION['login_gagal'] >= MAKS_PERCOBAAN) {
        $_SESSION['login_kunci'] = time() + LAMA_KUNCI_DETIK;
        $_SESSION['login_gagal'] = 0;
    }
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
/* ---------- Query MySQLi dengan prepared statement (aman dari SQL injection) ---------- */
function db_jalankan(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $st = $db->prepare($sql);
    if ($params) {
        $nilai = array_values($params);
        $tipe = '';
        foreach ($nilai as $v) {
            $tipe .= is_int($v) ? 'i' : 's';
        }
        $st->bind_param($tipe, ...$nilai);
    }
    $st->execute();
    return $st;
}
/** Semua baris hasil query (array asosiatif). */
function db_rows(mysqli $db, string $sql, array $params = []): array
{
    $hasil = db_jalankan($db, $sql, $params)->get_result();
    return $hasil ? $hasil->fetch_all(MYSQLI_ASSOC) : [];
}
/** Satu baris pertama, atau null. */
function db_row(mysqli $db, string $sql, array $params = []): ?array
{
    $hasil = db_jalankan($db, $sql, $params)->get_result();
    return ($hasil ? $hasil->fetch_assoc() : null) ?: null;
}
/** Nilai kolom pertama dari baris pertama, atau null. */
function db_val(mysqli $db, string $sql, array $params = [])
{
    $baris = db_jalankan($db, $sql, $params)->get_result()->fetch_row();
    return $baris[0] ?? null;
}

/* ---------- Koneksi ---------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // error MySQL jadi exception
try {
    require __DIR__ . '/config/koneksi.php'; // membuat $koneksi (MySQLi)
    mysqli_query($koneksi, "SET time_zone = '+07:00'"); // jam WIB untuk last_login & audit_log
} catch (mysqli_sql_exception $ex) {
    error_log('Koneksi database gagal: ' . $ex->getMessage());
    http_response_code(500);
    exit('<p style="font-family:sans-serif;padding:24px">Tidak bisa terhubung ke database. Pastikan MySQL di XAMPP berjalan dan pengaturan di config/koneksi.php sudah benar.</p>');
}

$aksi = $_POST['aksi'] ?? '';

/* ---------- Logout ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $aksi === 'logout') {
    if (csrf_ok() && !empty($_SESSION['id_pengguna'])) {
        catat($koneksi, (int) $_SESSION['id_pengguna'], 'LOGOUT');
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        session_destroy();
        pindah('login.php?keluar=1');
    }
    pindah(SETELAH_LOGIN);
}

/* ---------- Sudah login? langsung ke beranda ---------- */
if (!empty($_SESSION['id_pengguna'])) {
    pindah(SETELAH_LOGIN);
}

/* ---------- Proses login ---------- */
$error  = null;
$email  = '';
$notice = isset($_GET['keluar']) ? 'Kamu sudah keluar.' : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notice   = null;
    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $sisaKunci = (int) ($_SESSION['login_kunci'] ?? 0) - time();

    if (!csrf_ok()) {
        $error = 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif ($sisaKunci > 0) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam ' . (int) ceil($sisaKunci / 60) . ' menit.';
    } elseif ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        gagal_bertambah();
        $error = PESAN_GAGAL;
    } else {
        // Email belum UNIQUE di tabel, jadi ambil sampai 2 baris untuk mendeteksi duplikat.
        $rows = db_rows($koneksi, 'SELECT id_pengguna, password_hash, status_aktif FROM pengguna WHERE email = ? LIMIT 2', [$email]);
        $akun = count($rows) === 1 ? $rows[0] : null;

        // password_verify tetap dijalankan walau email tidak ada, agar waktu respons sama.
        $hash  = $akun['password_hash'] ?? '$2y$10$BAZ5IyczkbPqEGO.2Gz31OPkABy7yAtc3Jxgg.6g8AGGJsri19XfC';
        $cocok = password_verify($password, $hash);

        if (count($rows) > 1) {
            $error = 'Email ini terdaftar di lebih dari satu akun. Hubungi admin.';
        } elseif (!$akun || !$cocok) {
            gagal_bertambah();
            $error = PESAN_GAGAL;
        } elseif ($akun['status_aktif'] !== 'Aktif') {
            $error = 'Akun kamu tidak aktif. Hubungi admin.';
        } else {
            $id = (int) $akun['id_pengguna'];

            if (password_needs_rehash($akun['password_hash'], PASSWORD_DEFAULT)) {
                db_jalankan($koneksi, 'UPDATE pengguna SET password_hash = ? WHERE id_pengguna = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }

            session_regenerate_id(true);           // cegah session fixation
            $_SESSION['id_pengguna'] = $id;
            unset($_SESSION['login_gagal'], $_SESSION['login_kunci']);

            db_jalankan($koneksi, 'UPDATE pengguna SET last_login = NOW() WHERE id_pengguna = ?', [$id]);
            catat($koneksi, $id, 'LOGIN');

            pindah(SETELAH_LOGIN);
        }
    }
}

$univ = db_row($koneksi, 'SELECT kode_universitas, nama_universitas FROM universitas ORDER BY id_universitas LIMIT 1')
    ?: ['kode_universitas' => 'SIM', 'nama_universitas' => 'Universitas'];
$invalid = $error ? ' aria-invalid="true" aria-describedby="login-error"' : '';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400..700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/script.js" defer></script>
</head>
<body>
<div class="auth">
  <div class="auth-col">
    <div class="auth-top">
      <a class="logo" href="index.php"><span class="logo-mark"><?= e($univ['kode_universitas'] ?: 'SIM') ?></span><?= e(APP_NAME) ?></a>
      <a class="back" href="index.php">← Kembali ke beranda</a>
    </div>

    <main class="auth-main">
      <h1>Selamat datang <em>kembali.</em></h1>
      <p class="auth-lead">Masuk dengan email dan kata sandi akun <?= e(APP_NAME) ?>.</p>

      <?php if ($error): ?>
        <div class="alert" role="alert" id="login-error"><?= e($error) ?></div>
      <?php elseif ($notice): ?>
        <div class="alert alert-ok" role="status"><?= e($notice) ?></div>
      <?php endif; ?>

      <form class="form" method="post" action="login.php" novalidate>
        <input type="hidden" name="aksi" value="login">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" inputmode="email" autocomplete="username" required
                 value="<?= e($email) ?>" placeholder="nama@umb.ac.id"<?= $invalid ?><?= $email === '' ? ' autofocus' : '' ?>>
        </div>
        <div class="field">
          <label for="password">Kata sandi</label>
          <div class="pw">
            <input id="password" name="password" type="password" autocomplete="current-password" required<?= $invalid ?><?= $email !== '' ? ' autofocus' : '' ?>>
            <button type="button" id="toggle-pw" aria-controls="password" aria-pressed="false">Tampilkan</button>
          </div>
        </div>
        <button class="btn" type="submit">Masuk</button>
      </form>

      <p class="auth-help">Lupa kata sandi atau akun belum aktif? Hubungi admin <?= e(APP_NAME) ?>.</p>
    </main>

    <div class="auth-foot">© <?= date('Y') ?> <?= e($univ['nama_universitas']) ?></div>
  </div>

  <aside class="auth-art" aria-hidden="true">
    <svg class="art-shapes" viewBox="0 0 600 520" preserveAspectRatio="xMaxYMin slice">
      <circle class="art-ring" cx="520" cy="80" r="220"/>
      <circle class="art-ring" cx="520" cy="80" r="140"/>
      <rect class="night" x="300" y="-20" width="190" height="300" rx="16"/>
      <circle class="leaf" cx="300" cy="300" r="110"/>
      <rect class="soft" x="440" y="250" width="150" height="110" rx="16" transform="rotate(6 515 305)"/>
    </svg>
    <h2>Masuk ke <?= e(APP_NAME) ?> <em><?= e($univ['nama_universitas']) ?></em></h2>
    <p>Data mahasiswa per program studi, fakultas, dan universitas — sesuai peran akunmu.</p>
  </aside>
</div>
</body>
</html>
