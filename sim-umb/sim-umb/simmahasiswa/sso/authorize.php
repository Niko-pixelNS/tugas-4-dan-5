<?php
/**
 * sso/authorize.php — pintu login SSO SIM Mahasiswa
 *
 * Dipanggil aplikasi lain (mis. SIMSKRIPSI):
 *   sso/authorize.php?client_id=simskripsi&redirect_uri=...&state=...
 * Bila pengguna sudah login di SIM Mahasiswa, langsung dikembalikan
 * dengan ?code=...&state=...; bila belum, tampil form login.
 */
declare(strict_types=1);
require __DIR__ . '/sso_lib.php';

/* Sesi yang SAMA dengan SIM Mahasiswa, supaya cukup login sekali */
session_name('SIMMHS');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
ini_set('session.use_strict_mode', '1');
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function halaman_error(string $pesan): void
{
    http_response_code(400);
    echo '<!doctype html><meta charset="utf-8"><title>SSO</title><p style="font-family:sans-serif;padding:24px">' . h($pesan) . '</p>';
    exit;
}

/* ---------- Validasi permintaan dari aplikasi klien ---------- */
$clientId = (string) ($_REQUEST['client_id'] ?? '');
$redirect = (string) ($_REQUEST['redirect_uri'] ?? '');
$state    = (string) ($_REQUEST['state'] ?? '');
$client   = sso_client($clientId);
if (!$client) halaman_error('Aplikasi tidak terdaftar di SSO.');
if (!hash_equals($client['redirect_uri'], $redirect)) halaman_error('Alamat kembali (redirect_uri) tidak sesuai.');
if ($state === '' || strlen($state) > 128) halaman_error('Parameter state tidak sah.');

/** Buat kode sekali pakai lalu kembalikan pengguna ke aplikasi klien. */
function kirim_kode(array $client, int $idPengguna, string $state): void
{
    $kode = jwt_buat([
        'iss' => 'simmahasiswa', 'typ' => 'code', 'aud' => $client['client_id'],
        'sub' => $idPengguna, 'uri' => $client['redirect_uri'],
        'iat' => time(), 'exp' => time() + SSO_MASA_KODE, 'jti' => bin2hex(random_bytes(8)),
    ]);
    $sep = str_contains($client['redirect_uri'], '?') ? '&' : '?';
    header('Location: ' . $client['redirect_uri'] . $sep . http_build_query(['code' => $kode, 'state' => $state]));
    exit;
}

/** Pengguna boleh masuk ke klien ini? Mengembalikan pesan error atau null. */
function cek_izin(array $client, ?array $u): ?string
{
    if (!$u || $u['status_aktif'] !== 'Aktif') return 'Akun tidak ditemukan atau tidak aktif.';
    if (!in_array($u['role'], $client['role'], true)) return 'Peran ' . $u['nama_role'] . ' tidak memiliki akses ke ' . $client['nama'] . '.';
    if ($u['role'] === 'MAHASISWA' && !$u['mahasiswa']) return 'Akun mahasiswa ini belum terhubung dengan data mahasiswa.';
    return null;
}

$error = '';

/* ---------- Sudah login di SIM Mahasiswa? ---------- */
if (!empty($_SESSION['id_pengguna']) && ($_GET['ganti'] ?? '') !== '1') {
    $u = sso_data_pengguna((int) $_SESSION['id_pengguna']);
    $error = cek_izin($client, $u) ?? '';
    if ($error === '') kirim_kode($client, (int) $_SESSION['id_pengguna'], $state);
}

/* ---------- Form login ---------- */
if (empty($_SESSION['sso_csrf'])) $_SESSION['sso_csrf'] = bin2hex(random_bytes(32));
$_SESSION['sso_gagal'] = $_SESSION['sso_gagal'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if (!hash_equals($_SESSION['sso_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'Sesi formulir kedaluwarsa. Silakan coba lagi.';
    } elseif ($_SESSION['sso_gagal'] >= 5 && time() - ($_SESSION['sso_gagal_waktu'] ?? 0) < 300) {
        $error = 'Terlalu banyak percobaan gagal. Tunggu 5 menit lalu coba lagi.';
    } elseif ($user === '' || $pass === '') {
        $error = 'Isi username dan password.';
    } else {
        $row = sso_row('SELECT id_pengguna, password_hash FROM pengguna WHERE username = ? OR email = ? LIMIT 1', [$user, $user]);
        if (!$row || !password_verify($pass, $row['password_hash'])) {
            $_SESSION['sso_gagal']++;
            $_SESSION['sso_gagal_waktu'] = time();
            $error = 'Username atau password salah.';
        } else {
            $u = sso_data_pengguna((int) $row['id_pengguna']);
            $error = cek_izin($client, $u) ?? '';
            if ($error === '') {
                session_regenerate_id(true);
                $_SESSION['id_pengguna'] = (int) $row['id_pengguna'];
                $_SESSION['sso_gagal'] = 0;
                sso_q('UPDATE pengguna SET last_login = NOW() WHERE id_pengguna = ?', [(int) $row['id_pengguna']]);
                try {
                    sso_q(
                        "INSERT INTO audit_log (id_pengguna, tabel_nama, record_id, aksi, data_baru, ip_address, user_agent)
                         VALUES (?, 'pengguna', ?, 'LOGIN', ?, ?, ?)",
                        [(int) $row['id_pengguna'], (string) $row['id_pengguna'],
                         json_encode(['via' => 'sso', 'aplikasi' => $client['client_id']]),
                         $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]
                    );
                } catch (mysqli_sql_exception $e) {
                    error_log('audit_log SSO: ' . $e->getMessage());
                }
                kirim_kode($client, (int) $row['id_pengguna'], $state);
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk SSO — SIM Mahasiswa UMB</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#eef3fb;font:15px/1.5 Roboto,"Segoe UI",Arial,sans-serif;color:#182132}
  .box{width:100%;max-width:400px;background:#fff;border:1px solid #e3e8f0;border-radius:14px;padding:32px 28px;box-shadow:0 4px 14px rgba(20,35,60,.06)}
  h1{margin:0;font-size:22px} p{margin:6px 0 0;color:#687389}
  .app{margin:18px 0 20px;padding:12px 14px;border-radius:8px;background:#e7effc;font-size:14px}
  label{display:block;margin:14px 0 6px;font-weight:500;font-size:14px}
  input{width:100%;height:44px;padding:0 12px;border:1px solid #d5dce7;border-radius:8px;font:inherit}
  input:focus{outline:none;border-color:#1767e0;box-shadow:0 0 0 3px #e7effc}
  button{width:100%;height:46px;margin-top:20px;border:0;border-radius:8px;background:#1767e0;color:#fff;font-family:inherit;font-size:15px;font-weight:600;cursor:pointer}
  button:hover{background:#0f50b4}
  .err{margin-top:14px;padding:10px 12px;border-radius:8px;background:#fde4e4;color:#b42318;font-size:14px}
  .ket{margin-top:18px;font-size:13px;color:#687389;text-align:center}
</style>
</head>
<body>
<main class="box">
  <h1>SSO Universitas Muhammadiyah Bengkulu</h1>
  <p>Masuk dengan akun SIM Mahasiswa.</p>
  <div class="app">Anda akan masuk ke <b><?= h($client['nama']) ?></b>.</div>

  <?php if ($error): ?><div class="err" role="alert"><?= h($error) ?></div><?php endif; ?>

  <form method="post" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['sso_csrf']) ?>">
    <input type="hidden" name="client_id" value="<?= h($clientId) ?>">
    <input type="hidden" name="redirect_uri" value="<?= h($redirect) ?>">
    <input type="hidden" name="state" value="<?= h($state) ?>">
    <label for="username">Username atau email</label>
    <input id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Masuk</button>
  </form>
  <p class="ket">Password tidak dikirim ke <?= h($client['nama']) ?>.</p>
</main>
</body>
</html>
