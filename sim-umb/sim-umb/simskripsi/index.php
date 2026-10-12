<?php
/**
 * index.php — beranda publik SIMSKRIPSI
 */
require __DIR__ . '/inc/init.php';
$u = user();
$info = pengumuman_tampil(null, 3);
head_html('Beranda');
?>
<body>
<header class="lp-header">
  <div class="container lp-nav">
    <a class="brand" href="index.php">
      <span class="logo"><img src="assets/img/logo-umb.png" alt="Logo Universitas Muhammadiyah Bengkulu"></span>
      <span class="brand-text"><strong>SIMSKRIPSI</strong><small>Universitas Muhammadiyah Bengkulu</small></span>
    </a>
    <button class="lp-menu-btn" id="lp-menu-btn" type="button" aria-label="Menu" aria-controls="lp-links" aria-expanded="false"><?= icon('menu') ?></button>
    <nav class="lp-links" id="lp-links" aria-label="Utama" hidden>
      <a href="#beranda">Beranda</a>
      <a href="#informasi">Informasi</a>
      <a href="#panduan">Panduan</a>
      <a href="#kontak">Kontak</a>
      <a class="btn btn-sm" href="<?= $u ? 'dashboard.php' : 'login.php' ?>"><?= $u ? 'Dashboard' : 'Login' ?></a>
    </nav>
  </div>
</header>

<main>
  <section class="hero" id="beranda">
    <div class="container hero-inner">
      <h1>Sistem Informasi Skripsi Mahasiswa</h1>
      <p>Mengelola proses pengajuan judul, bimbingan, seminar proposal, hingga sidang skripsi secara online, cepat, transparan, dan terintegrasi.</p>
      <a class="btn btn-lg" href="<?= $u ? 'dashboard.php' : 'login.php' ?>"><?= $u ? 'Buka Dashboard' : 'Login ke Sistem' ?> <?= icon('arrowRight') ?></a>
    </div>
  </section>

  <div class="container">
    <div class="features" id="informasi">
      <article class="feature"><div class="feature-ic"><?= icon('fileEdit') ?></div><h3>Pengajuan Judul Online</h3><p>Ajukan judul dan pantau keputusan prodi tanpa datang ke kampus.</p></article>
      <article class="feature"><div class="feature-ic"><?= icon('check2') ?></div><h3>Bimbingan Terstruktur</h3><p>Topik konsultasi dan tanggapan pembimbing tersimpan rapi.</p></article>
      <article class="feature"><div class="feature-ic"><?= icon('progress') ?></div><h3>Monitoring Progress</h3><p>Pantau tahap skripsi dari pengajuan hingga lulus sidang.</p></article>
      <article class="feature"><div class="feature-ic"><?= icon('folder') ?></div><h3>Dokumen Terintegrasi</h3><p>Unggah berkas persyaratan dan lihat hasil verifikasinya.</p></article>
    </div>
  </div>

  <section class="lp-section" id="panduan">
    <div class="container">
      <h2>Alur Proses Skripsi</h2>
      <p class="lead">Enam tahap yang dilalui mahasiswa, semuanya tercatat di SIMSKRIPSI.</p>
      <ol class="flow">
        <li><div class="flow-ic"><?= icon('fileEdit') ?></div><strong>Pengajuan</strong><span>Mahasiswa mengajukan judul skripsi.</span></li>
        <li><div class="flow-ic"><?= icon('check2') ?></div><strong>Persetujuan Judul</strong><span>Prodi menyetujui judul dan menetapkan pembimbing.</span></li>
        <li><div class="flow-ic"><?= icon('users') ?></div><strong>Bimbingan</strong><span>Konsultasi dan revisi bersama dosen pembimbing.</span></li>
        <li><div class="flow-ic"><?= icon('monitor') ?></div><strong>Seminar Proposal</strong><span>Mempresentasikan proposal di depan penguji.</span></li>
        <li><div class="flow-ic"><?= icon('award') ?></div><strong>Sidang Skripsi</strong><span>Ujian akhir hasil penelitian.</span></li>
        <li><div class="flow-ic"><?= icon('clipboard') ?></div><strong>Selesai</strong><span>Revisi akhir dan pengajuan yudisium.</span></li>
      </ol>

      <div class="info-grid">
        <div class="info-card"><h3>Mahasiswa, prodi &amp; dekanat</h3><p>Masuk dengan tombol <b>SSO UMB</b> memakai akun SIM Mahasiswa. Tidak perlu akun baru.</p></div>
        <div class="info-card"><h3>Dosen</h3><p>Masuk dengan NIP atau NIDN dan password dari admin SIMSKRIPSI.</p></div>
        <div class="info-card"><h3>Berkas persyaratan</h3><p>Siapkan berkas dalam format PDF. Daftar lengkapnya ada di menu Dokumen setelah login.</p></div>
      </div>

      <?php if ($info): ?>
        <h2 class="mt" style="margin-top:64px">Pengumuman</h2>
        <div class="lp-ann">
          <?php foreach ($info as $a): ?>
            <article><time><?= tgl($a['tanggal_terbit'], true) ?></time><h3><?= e($a['judul']) ?></h3><p><?= e($a['isi']) ?></p>
              <?php if ($a['lampiran']): ?><a class="file-link small" href="unduh.php?jenis=lampiran&amp;id=<?= (int) $a['id_pengumuman'] ?>"><?= icon('download') ?>Lampiran</a><?php endif; ?></article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<footer class="lp-foot" id="kontak">
  <div class="container">
    <div class="foot-grid">
      <div>
        <div class="brand"><span class="logo"><img src="assets/img/logo-umb.png" alt=""></span>
          <span class="brand-text"><strong>SIMSKRIPSI</strong><small>Universitas Muhammadiyah Bengkulu</small></span></div>
        <p style="margin-top:14px">Sistem informasi pengelolaan skripsi mahasiswa Universitas Muhammadiyah Bengkulu.</p>
      </div>
      <div><h4>Navigasi</h4><a href="#beranda">Beranda</a><a href="#informasi">Informasi</a><a href="#panduan">Panduan</a><a href="login.php">Login</a></div>
      <div><h4>Kontak</h4><p>Jl. Bali, Kampung Bali</p><p>Kota Bengkulu, Bengkulu</p><a href="https://umb.ac.id" target="_blank" rel="noopener">umb.ac.id</a></div>
    </div>
    <p class="foot-copy">© <?= date('Y') ?> Universitas Muhammadiyah Bengkulu · SIMSKRIPSI</p>
  </div>
</footer>
<script src="assets/js/script.js"></script>
</body>
</html>
