<?php
/**
 * index.php — landing page SIM Dosen (publik)
 */
require __DIR__ . '/inc/init.php';
$u = in_array(user()['sumber'] ?? '', ['simmhs', 'lokal'], true) ? user() : null;
$univ = db_row('SELECT * FROM universitas ORDER BY id_universitas LIMIT 1');
$aktif = "status_aktivitas = 'Aktif'";
$total = (int) db_val("SELECT COUNT(*) FROM v_dosen WHERE $aktif");
$serdos = (int) db_val("SELECT COUNT(*) FROM v_dosen WHERE $aktif AND status_serdos = 'Sudah'");
$s3 = (int) db_val("SELECT COUNT(*) FROM v_dosen WHERE $aktif AND pendidikan_terakhir = 'S3'");
$prodi = (int) db_val("SELECT COUNT(*) FROM program_studi WHERE status_aktif = 'Aktif'");
$jabatan = db_rows("SELECT jf.nama, COUNT(v.id_dosen) AS n FROM jabatan_fungsional jf
                     LEFT JOIN v_dosen v ON v.id_jabatan_fungsional = jf.id_jabatan_fungsional AND v.$aktif
                     GROUP BY jf.id_jabatan_fungsional ORDER BY jf.urutan");
$fakultas = db_rows("SELECT f.nama_fakultas, COUNT(DISTINCT ps.id_program_studi) AS prodi, COUNT(v.id_dosen) AS dosen
                      FROM fakultas f LEFT JOIN program_studi ps ON ps.id_fakultas = f.id_fakultas
                      LEFT JOIN v_dosen v ON v.id_program_studi = ps.id_program_studi AND v.$aktif
                     GROUP BY f.id_fakultas ORDER BY f.nama_fakultas");
head_html('Beranda');
?>
<body>
<header class="lp-header">
  <div class="container lp-nav">
    <a class="brand" href="index.php"><span class="logo"><img src="assets/img/logo-umb.png" alt="Logo UMB"></span>
      <span class="brand-text"><strong>SIM DOSEN</strong><small><?= e($univ['nama_universitas'] ?? 'Universitas Muhammadiyah Bengkulu') ?></small></span></a>
    <button class="lp-menu-btn" id="lp-menu-btn" type="button" aria-label="Menu" aria-controls="lp-links" aria-expanded="false"><?= icon('menu') ?></button>
    <nav class="lp-links" id="lp-links" aria-label="Utama" hidden>
      <a href="#beranda">Beranda</a><a href="#statistik">Statistik</a><a href="#fakultas">Fakultas</a><a href="#kontak">Kontak</a>
      <a class="btn btn-sm" href="<?= $u ? 'dashboard.php' : 'login.php' ?>"><?= $u ? 'Dashboard' : 'Login' ?></a>
    </nav>
  </div>
</header>
<main>
  <section class="hero" id="beranda">
    <div class="container hero-inner">
      <h1>Sistem Informasi Dosen</h1>
      <p>Data dosen <?= e($univ['nama_universitas'] ?? 'Universitas Muhammadiyah Bengkulu') ?> dalam satu tempat: jabatan fungsional, kepangkatan, pendidikan, sertifikasi, dan penempatan — lengkap dengan laporan dan web service.</p>
      <a class="btn btn-lg" href="<?= $u ? 'dashboard.php' : 'login.php' ?>"><?= $u ? 'Buka Dashboard' : 'Login ke Sistem' ?> <?= icon('arrowRight') ?></a>
    </div>
  </section>
  <div class="container">
    <div class="features" id="statistik">
      <article class="feature"><div class="feature-ic"><?= icon('users') ?></div><h3><?= $total ?> dosen aktif</h3><p>Tercatat di seluruh program studi.</p></article>
      <article class="feature"><div class="feature-ic"><?= icon('award') ?></div><h3><?= $serdos ?> bersertifikat</h3><p>Sudah memiliki sertifikat pendidik (serdos).</p></article>
      <article class="feature"><div class="feature-ic"><?= icon('check2') ?></div><h3><?= $s3 ?> bergelar doktor</h3><p>Pendidikan terakhir jenjang S3.</p></article>
      <article class="feature"><div class="feature-ic"><?= icon('folder') ?></div><h3><?= $prodi ?> program studi</h3><p>Tempat penempatan (homebase) dosen.</p></article>
    </div>
  </div>
  <section class="lp-section" id="fakultas">
    <div class="container">
      <h2>Dosen Aktif per Jabatan Fungsional</h2>
      <div class="info-grid" style="grid-template-columns:repeat(<?= max(1, count($jabatan)) ?>,1fr)">
        <?php foreach ($jabatan as $j): ?><div class="info-card" style="text-align:center"><h3 style="font-size:28px"><?= (int) $j['n'] ?></h3><p><?= e($j['nama']) ?></p></div><?php endforeach; ?>
      </div>
      <h2 style="margin-top:56px">Fakultas</h2>
      <div class="info-grid">
        <?php foreach ($fakultas as $f): ?><div class="info-card"><h3><?= e($f['nama_fakultas']) ?></h3><p><?= (int) $f['prodi'] ?> program studi · <?= (int) $f['dosen'] ?> dosen aktif</p></div><?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
<footer class="lp-foot" id="kontak">
  <div class="container">
    <div class="foot-grid">
      <div><div class="brand"><span class="logo"><img src="assets/img/logo-umb.png" alt=""></span><span class="brand-text"><strong>SIM DOSEN</strong><small>Universitas Muhammadiyah Bengkulu</small></span></div>
        <p style="margin-top:14px">Sistem informasi manajemen data dosen dan web service (API) untuk aplikasi kampus lain.</p></div>
      <div><h4>Navigasi</h4><a href="#beranda">Beranda</a><a href="#statistik">Statistik</a><a href="#fakultas">Fakultas</a><a href="login.php">Login</a></div>
      <div><h4>Kontak</h4><p><?= e($univ['alamat'] ?? '') ?></p><p><?= e(trim(($univ['kota'] ?? '') . ', ' . ($univ['provinsi'] ?? ''), ', ')) ?></p>
        <?php if (!empty($univ['website'])): ?><a href="<?= e($univ['website']) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $univ['website'])) ?></a><?php endif; ?></div>
    </div>
    <p class="foot-copy">© <?= date('Y') ?> <?= e($univ['nama_universitas'] ?? 'Universitas Muhammadiyah Bengkulu') ?> · SIM Dosen</p>
  </div>
</footer>
<script src="assets/js/script.js"></script>
</body>
</html>
