<?php
/**
 * login.php — semua pengguna masuk lewat SSO:
 *   "Masuk dengan SSO SIM Mahasiswa" -> Admin, Operator Prodi, Dekanat, Mahasiswa
 *   "Masuk dengan SSO SIM Dosen"     -> Dosen
 * Juga menangani logout (keluar dari SIMSKRIPSI sekaligus dari SSO asalnya).
 */
require __DIR__ . '/inc/init.php';

if (is_post() && post('aksi') === 'logout') {
    csrf_check();
    $sumber = user()['sumber'] ?? '';
    $_SESSION = [];
    session_regenerate_id(true);
    if (in_array($sumber, ['simmhs', 'simdosen'], true)) {
        redirect(sso_sumber($sumber)['url'] . '/sso/logout.php?client_id=' . urlencode(SSO_CLIENT_ID));
    }
    flash('success', 'Anda sudah keluar.');
    redirect('login.php');
}
if (in_array(user()['sumber'] ?? '', ['simmhs', 'simdosen'], true)) redirect('dashboard.php');

if (get('aksi') === 'sso') redirect(sso_url_login(get('dari') === 'simdosen' ? 'simdosen' : 'simmhs'));
if (get('keluar') === '1') flash('success', 'Anda sudah keluar dari SIMSKRIPSI dan SSO.');

head_html('Masuk');
?>
<body>
<main class="login-page">
  <div class="login-card">
    <div class="login-head">
      <span class="logo"><img src="assets/img/logo-umb.png" alt="Logo Universitas Muhammadiyah Bengkulu"></span>
      <h1>SIMSKRIPSI</h1>
      <p>Universitas Muhammadiyah Bengkulu</p>
    </div>
    <div class="mt"><?= flash_render() ?></div>

    <a class="btn btn-lg btn-block sso-btn" href="login.php?aksi=sso&amp;dari=simmhs"><?= icon('lock') ?>Masuk dengan SSO SIM Mahasiswa</a>
    <p class="help" style="text-align:center;margin:8px 0 18px">Admin, Operator Prodi, Dekanat, dan Mahasiswa</p>

    <a class="btn btn-lg btn-block sso-btn btn-green" href="login.php?aksi=sso&amp;dari=simdosen"><?= icon('teacher') ?>Masuk dengan SSO SIM Dosen</a>
    <p class="help" style="text-align:center;margin-top:8px">Dosen — pakai nomor registrasi (NIDN/NIDK/NUP) dan password SIM Dosen</p>

    <div class="hint-box">
      <strong>Akun yang dipakai:</strong>
      <dl>
        <dt>Mahasiswa</dt><dd>: akun SIM Mahasiswa</dd>
        <dt>Prodi</dt><dd>: akun SIM Mahasiswa</dd>
        <dt>Dekanat</dt><dd>: akun SIM Mahasiswa</dd>
        <dt>Admin</dt><dd>: akun SIM Mahasiswa</dd>
        <dt>Dosen</dt><dd>: akun SIM Dosen</dd>
      </dl>
    </div>
    <a class="login-back" href="index.php">Kembali ke beranda</a>
  </div>
</main>
<script src="assets/js/script.js"></script>
</body>
</html>
