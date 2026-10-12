<?php
/**
 * includes/layout.php
 * Kerangka halaman setelah login: sidebar (menu sesuai hak akses), kepala halaman, panel kanan opsional.
 *
 *   layout_awal($user, 'Judul', 'kunci-menu', ['lebar' => true]);
 *   ... isi halaman ...
 *   layout_akhir($htmlPanelKanan);   // panel kanan opsional (dipakai dashboard)
 */
declare(strict_types=1);

/** Isian profil yang dihitung untuk "Kelengkapan profil". */
function kelengkapan_profil(mysqli $db, array $u): array
{
    $isian = [
        'Nama lengkap' => $u['nama_lengkap'],
        'Username'     => $u['username'],
        'Email'        => $u['email'],
        'Nomor HP'     => $u['no_hp'],
    ];
    if ($u['kode_role'] === 'MAHASISWA') {
        $m = db_row($db, 'SELECT tempat_lahir, tanggal_lahir, alamat FROM mahasiswa WHERE id_pengguna = ? LIMIT 1', [(int) $u['id_pengguna']]);
        if ($m) {
            $isian += ['Tempat lahir' => $m['tempat_lahir'], 'Tanggal lahir' => $m['tanggal_lahir'], 'Alamat' => $m['alamat']];
        }
    }
    $kosong = array_keys(array_filter($isian, static fn($v) => trim((string) $v) === ''));
    return ['total' => count($isian), 'terisi' => count($isian) - count($kosong), 'kosong' => $kosong];
}

/** Daftar menu sidebar sesuai hak akses: [grup => [[kunci, label, ikon, url], ...]] */
function menu_untuk(array $u): array
{
    $m = ['' => [['beranda', 'Beranda', 'home', 'dashboard.php']]];
    if (izin($u, 'mahasiswa')) $m['Akademik'][] = ['mahasiswa', 'Mahasiswa', 'users', 'mahasiswa.php'];
    if (izin($u, 'laporan'))   $m['Akademik'][] = ['laporan', 'Laporan rekap', 'report', 'laporan.php'];
    if (izin($u, 'universitas')) $m['Master data'][] = ['universitas', 'Universitas', 'building', 'universitas.php'];
    if (izin($u, 'fakultas'))    $m['Master data'][] = ['fakultas', 'Fakultas', 'layers', 'fakultas.php'];
    if (izin($u, 'prodi'))       $m['Master data'][] = ['prodi', 'Program studi', 'cap', 'prodi.php'];
    if (izin($u, 'pengguna'))    $m['Master data'][] = ['pengguna', 'Pengguna', 'idcard', 'pengguna.php'];
    if (izin($u, 'role'))        $m['Master data'][] = ['role', 'Role', 'shield', 'role.php'];
    if (izin($u, 'aktivitas'))   $m['Akun'][] = ['aktivitas', 'Aktivitas', 'activity', 'dashboard.php?page=aktivitas'];
    $m['Akun'][] = ['profil', 'Profil saya', 'user', 'dashboard.php?page=profil'];
    $m['Akun'][] = ['situs', 'Situs utama', 'external', 'index.php'];
    return $m;
}

function layout_awal(array $u, string $judul, string $aktif, array $opsi = []): void
{
    global $koneksi;
    $univ = db_row($koneksi, 'SELECT kode_universitas FROM universitas ORDER BY id_universitas LIMIT 1');
    $kode = $univ['kode_universitas'] ?? 'SIM';
    $prof = kelengkapan_profil($koneksi, $u);
    $persen = (int) round($prof['terisi'] / max(1, $prof['total']) * 100);
    $kelasApp = 'app' . (empty($opsi['rail']) ? ' no-rail' : '');
    $kelasMain = 'main-in' . (!empty($opsi['lebar']) ? ' is-wide' : '');
    $cakupan = $u['cakupan']['label'] ?? ($u['kode_role'] === 'MAHASISWA' ? 'Data pribadimu' : 'Belum ada akses data');
    ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> — <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/script.js" defer></script>
</head>
<body class="dash-body">
<div class="<?= e($kelasApp) ?>" id="app">

  <aside class="side" id="side" aria-label="Navigasi">
    <div class="side-top">
      <a class="side-logo" href="dashboard.php"><span class="side-mark"><?= e($kode) ?></span><span class="side-name"><?= e(APP_NAME) ?></span></a>
      <button class="icon-btn side-collapse" id="side-collapse" type="button" aria-label="Ciutkan menu" aria-expanded="true"><?= ikon('back') ?></button>
    </div>

    <?php if (izin($u, 'mahasiswa')): ?>
      <form class="side-search" action="mahasiswa.php" method="get" role="search">
        <?= ikon('search') ?>
        <label class="sr-only" for="side-q">Cari mahasiswa</label>
        <input id="side-q" name="q" type="search" placeholder="Cari NPM atau nama…" autocomplete="off">
      </form>
    <?php endif; ?>

    <nav class="side-nav">
      <?php foreach (menu_untuk($u) as $grup => $items): ?>
        <?php if ($grup !== ''): ?><span class="side-group"><?= e($grup) ?></span><?php endif; ?>
        <?php foreach ($items as [$kunci, $label, $ic, $href]): $on = $kunci === $aktif; ?>
          <a class="side-link<?= $on ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $on ? ' aria-current="page"' : '' ?> title="<?= e($label) ?>">
            <?= ikon($ic) ?><span class="side-label"><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="side-bottom">
      <a class="strength" href="dashboard.php?page=profil">
        <span class="strength-head"><span>Kelengkapan profil</span><span><?= $prof['terisi'] ?>/<?= $prof['total'] ?></span></span>
        <span class="strength-bar"><span style="width:<?= $persen ?>%"></span></span>
      </a>
      <div class="side-user">
        <?= avatar($u['nama_lengkap'], 'av-md') ?>
        <span class="side-user-text">
          <span class="side-user-name"><?= e($u['nama_lengkap']) ?></span>
          <span class="side-user-handle">@<?= e($u['username']) ?></span>
        </span>
        <button class="icon-btn" id="user-menu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="user-menu" aria-label="Menu akun"><?= ikon('more') ?></button>
        <div class="user-menu" id="user-menu" hidden>
          <a href="dashboard.php?page=profil"><?= ikon('user') ?>Profil saya</a>
          <form method="post" action="login.php">
            <input type="hidden" name="aksi" value="logout">
            <?= csrf_field() ?>
            <button type="submit"><?= ikon('logout') ?>Keluar</button>
          </form>
        </div>
      </div>
    </div>
  </aside>
  <div class="side-scrim" id="side-scrim" hidden></div>

  <main class="main" id="main">
    <div class="<?= e($kelasMain) ?>">
      <header class="main-head">
        <button class="icon-btn menu-btn" id="menu-btn" type="button" aria-label="Buka menu" aria-controls="side" aria-expanded="false"><?= ikon('menu') ?></button>
        <div class="scope" title="Cakupan data yang bisa kamu lihat">
          <span class="scope-label"><?= e($judul) ?></span>
          <span class="scope-sep">·</span>
          <span class="scope-val"><?= e($cakupan) ?></span>
        </div>
        <div class="head-actions">
          <?= $opsi['aksi_html'] ?? '' ?>
          <span class="head-role"><?= e($u['nama_role']) ?></span>
        </div>
      </header>
      <?= flash_html() ?>
<?php
}

function layout_akhir(string $rail = ''): void
{
    ?>
    </div>
  </main>
<?php if ($rail !== ''): ?>
  <aside class="rail" aria-label="Ringkasan">
    <?= $rail ?>
  </aside>
<?php endif; ?>
</div>
</body>
</html>
<?php
}
