<?php
/**
 * login.php — masuk ke SIM Dosen:
 *   Admin : tombol SSO SIM Mahasiswa (akun Admin SIM Mahasiswa)
 *   Dosen : nomor registrasi + password (akun di database simdosen)
 * Juga menangani logout.
 */
require __DIR__ . '/inc/init.php';
require __DIR__ . '/inc/sso_klien.php';

if (is_post() && post('aksi') === 'logout') {
    csrf_check();
    $sumber = user()['sumber'] ?? '';
    if (user()) catat_log('LOGOUT', 'Keluar dari SIM Dosen');
    $_SESSION = [];
    session_regenerate_id(true);
    if ($sumber === 'simmhs') redirect(SIMMHS_URL . '/sso/logout.php?client_id=' . urlencode(SSO_SIMMHS_CLIENT_ID));
    flash('success', 'Anda sudah keluar.');
    redirect('login.php');
}
if (in_array(user()['sumber'] ?? '', ['simmhs', 'lokal'], true)) redirect('dashboard.php');
if (get('aksi') === 'sso') redirect(sso_admin_url());
if (get('keluar') === '1') flash('success', 'Anda sudah keluar dari SIM Dosen dan SSO SIM Mahasiswa.');

$error = '';
if (is_post()) {
    csrf_check();
    $hasil = cek_login(post('username'), (string) ($_POST['password'] ?? ''));
    if (is_array($hasil)) {
        sesi_masuk($hasil);
        redirect('dashboard.php');
    }
    $error = $hasil;
}
head_html('Masuk');
?>
<body>
<main class="login-page">
  <div class="login-card">
    <div class="login-head">
      <span class="logo"><img src="assets/img/logo-umb.png" alt="Logo Universitas Muhammadiyah Bengkulu"></span>
      <h1>SIM DOSEN</h1>
      <p>Universitas Muhammadiyah Bengkulu</p>
    </div>
    <div class="mt"><?= flash_render() ?></div>
    <a class="btn btn-lg btn-block sso-btn" href="login.php?aksi=sso"><?= icon('lock') ?>Masuk sebagai Admin (SSO SIM Mahasiswa)</a>
    <p class="help" style="text-align:center;margin-top:8px">Memakai akun Admin SIM Mahasiswa</p>
    <div class="divider">atau, khusus dosen</div>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field"><?= icon('user') ?><label class="sr-only" for="username">Nomor registrasi</label>
        <input class="input<?= $error ? ' is-invalid' : '' ?>" id="username" name="username" placeholder="Nomor registrasi (NIDN/NIDK/NUP) atau email" value="<?= e(post('username')) ?>" autocomplete="username"></div>
      <div class="field"><?= icon('lock') ?><label class="sr-only" for="password">Password</label>
        <input class="input has-toggle<?= $error ? ' is-invalid' : '' ?>" id="password" name="password" type="password" placeholder="Password" autocomplete="current-password">
        <button class="pw-toggle" type="button" data-toggle-pw="password" aria-label="Tampilkan password" aria-pressed="false"><?= icon('eye') ?></button></div>
      <p class="form-error" role="alert"><?= e($error) ?></p>
      <button class="btn btn-lg btn-block btn-outline" type="submit">Masuk sebagai Dosen</button>
    </form>
    <a class="login-back" href="index.php">Kembali ke beranda</a>
  </div>
</main>
<script src="assets/js/script.js"></script>
</body>
</html>
