<?php
/**
 * sso/authorize.php — pintu login SSO SIM Dosen
 *   sso/authorize.php?client_id=simskripsi&redirect_uri=...&state=...
 * Memakai sesi yang SAMA dengan aplikasi SIM Dosen: bila dosen sudah login di SIM Dosen,
 * ia langsung dikembalikan ke SIMSKRIPSI tanpa mengetik password lagi.
 */
chdir(__DIR__ . '/..');
require __DIR__ . '/../inc/init.php';
require __DIR__ . '/../inc/jwt.php';
require __DIR__ . '/../inc/data.php';

function halaman_error(string $pesan): never
{
    http_response_code(400);
    exit('<!doctype html><meta charset="utf-8"><title>SSO</title><p style="font-family:sans-serif;padding:24px">' . e($pesan) . '</p>');
}

$clientId = (string) ($_REQUEST['client_id'] ?? '');
$redirect = (string) ($_REQUEST['redirect_uri'] ?? '');
$state = (string) ($_REQUEST['state'] ?? '');
$client = sso_client($clientId);
if (!$client) halaman_error('Aplikasi tidak terdaftar di SSO SIM Dosen.');
if (!hash_equals($client['redirect_uri'], $redirect)) halaman_error('Alamat kembali (redirect_uri) tidak sesuai.');
if ($state === '' || strlen($state) > 128) halaman_error('Parameter state tidak sah.');

function cek_izin_client(array $client, ?array $u): ?string
{
    if (!$u || $u['status_aktif'] !== 'Aktif') return 'Akun tidak aktif.';
    if (!in_array($u['role'], $client['role'], true)) return 'Peran ' . $u['nama_role'] . ' tidak bisa masuk ke ' . $client['nama'] . ' lewat SIM Dosen. Hanya akun dosen.';
    if ($u['role'] === 'DOSEN' && !$u['dosen']) return 'Akun dosen ini belum terhubung dengan data dosen.';
    return null;
}
function kirim_kode(array $client, int $id, string $state): never
{
    $kode = jwt_buat(['iss' => 'simdosen', 'typ' => 'code', 'aud' => $client['client_id'], 'sub' => $id,
                      'uri' => $client['redirect_uri'], 'iat' => time(), 'exp' => time() + SSO_MASA_KODE, 'jti' => bin2hex(random_bytes(8))]);
    redirect($client['redirect_uri'] . (str_contains($client['redirect_uri'], '?') ? '&' : '?') . http_build_query(['code' => $kode, 'state' => $state]));
}

$error = '';
if (user() && (user()['sumber'] ?? '') === 'lokal' && ($_GET['ganti'] ?? '') !== '1') {
    $error = cek_izin_client($client, pengguna_json(user()['id'])) ?? '';
    if ($error === '') kirim_kode($client, user()['id'], $state);
}
if (is_post()) {
    csrf_check();
    $hasil = cek_login(post('username'), (string) ($_POST['password'] ?? ''));
    if (is_array($hasil)) {
        $error = cek_izin_client($client, pengguna_json((int) $hasil['id_pengguna'])) ?? '';
        if ($error === '') {
            sesi_masuk($hasil);
            catat_log('SSO', 'Masuk ke ' . $client['nama'] . ' lewat SSO');
            kirim_kode($client, (int) $hasil['id_pengguna'], $state);
        }
    } else {
        $error = $hasil;
    }
}
head_html('Masuk SSO', '../');
?>
<body>
<main class="login-page">
  <div class="login-card">
    <div class="login-head">
      <span class="logo"><img src="../assets/img/logo-umb.png" alt=""></span>
      <h1>SSO SIM Dosen</h1>
      <p>Universitas Muhammadiyah Bengkulu</p>
    </div>
    <div class="alert alert-info mt">Anda akan masuk ke <b><?= e($client['nama']) ?></b> dengan akun dosen SIM Dosen.</div>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="client_id" value="<?= e($clientId) ?>"><input type="hidden" name="redirect_uri" value="<?= e($redirect) ?>"><input type="hidden" name="state" value="<?= e($state) ?>">
      <div class="field"><?= icon('user') ?><label class="sr-only" for="username">Nomor registrasi</label>
        <input class="input" id="username" name="username" placeholder="Nomor registrasi (NIDN/NIDK/NUP) atau email" value="<?= e(post('username')) ?>" autocomplete="username" autofocus></div>
      <div class="field"><?= icon('lock') ?><label class="sr-only" for="password">Password</label>
        <input class="input" id="password" name="password" type="password" placeholder="Password SIM Dosen" autocomplete="current-password"></div>
      <p class="form-error" role="alert"><?= e($error) ?></p>
      <button class="btn btn-lg btn-block" type="submit">Masuk</button>
    </form>
    <p class="login-back">Password tidak dikirim ke <?= e($client['nama']) ?>.</p>
  </div>
</main>
<script src="../assets/js/script.js"></script>
</body>
</html>
