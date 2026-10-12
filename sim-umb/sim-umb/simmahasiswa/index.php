<?php
/**
 * SIM Mahasiswa — Beranda (landing page)
 * Data universitas, fakultas, program studi, dan peran diambil dari database sim_mahasiswa.
 * Koneksi database: config/koneksi.php · Tampilan: assets/css/style.css · Skrip: assets/js/script.js
 */
declare(strict_types=1);
ob_start(); // cegah error "headers already sent" bila ada spasi/baris kosong di akhir koneksi.php

const APP_NAME = 'SIM Mahasiswa';

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
function arrow(): string
{
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>';
}
function nomor(int $i): string
{
    return str_pad((string) $i, 2, '0', STR_PAD_LEFT);
}
function angka_kata(int $n): string
{
    $k = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh'];
    return $k[$n] ?? (string) $n;
}
function safe_url(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') return null;
    if (!preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
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

/* ---------- Pengguna yang sedang login (bila ada) ---------- */
$user = null;
if (!empty($_SESSION['id_pengguna'])) {
    $user = db_row(
        $koneksi,
        'SELECT p.nama_lengkap, p.status_aktif, r.nama_role
           FROM pengguna p JOIN roles r ON r.id_role = p.id_role
          WHERE p.id_pengguna = ?',
        [(int) $_SESSION['id_pengguna']]
    );
    if (!$user || $user['status_aktif'] !== 'Aktif') {
        $user = null;
        unset($_SESSION['id_pengguna']);
    }
}

/* ---------- Data halaman ---------- */
$univ = db_row($koneksi, 'SELECT * FROM universitas ORDER BY id_universitas LIMIT 1') ?: [
    'id_universitas' => 0, 'kode_universitas' => 'SIM', 'nama_universitas' => 'Universitas',
    'slogan' => null, 'alamat' => null, 'kota' => null, 'provinsi' => null, 'kode_pos' => null,
    'website' => null, 'email' => null, 'telepon' => null,
];
$idUniv = (int) $univ['id_universitas'];

$fakultas = array_column(
    db_rows($koneksi, 'SELECT nama_fakultas FROM fakultas WHERE id_universitas = ? ORDER BY nama_fakultas', [$idUniv]),
    'nama_fakultas'
);

$prodi = db_rows(
    $koneksi,
    "SELECT ps.kode_program_studi, ps.nama_program_studi, ps.jenjang, f.nama_fakultas,
            (SELECT COUNT(*) FROM mahasiswa m
              WHERE m.id_program_studi = ps.id_program_studi AND m.status_mahasiswa = 'Aktif') AS jumlah_aktif
       FROM program_studi ps
       JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
      WHERE ps.status_aktif = 'Aktif' AND f.id_universitas = ?
      ORDER BY f.nama_fakultas, ps.nama_program_studi",
    [$idUniv]
);

$totalAktif = array_sum(array_map('intval', array_column($prodi, 'jumlah_aktif')));

$roles = db_rows(
    $koneksi,
    "SELECT nama_role, keterangan FROM roles
      WHERE keterangan IS NOT NULL AND keterangan <> '' ORDER BY id_role"
);

$kodeUniv = $univ['kode_universitas'] ?: 'SIM';
$alamat   = array_filter([$univ['alamat'], $univ['kota'], $univ['provinsi'], $univ['kode_pos']]);
$lokasi   = implode(', ', array_filter([$univ['alamat'], $univ['kota']]));
$website  = safe_url($univ['website']);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(APP_NAME . ' — ' . $univ['nama_universitas']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;1,400;1,500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<section class="hero" aria-label="Pembuka">
  <div class="hero-bg" aria-hidden="true"></div>
  <div class="hero-wash"></div>

  <nav class="nav" aria-label="Utama">
    <a class="logo" href="index.php"><span class="logo-mark"><?= e($kodeUniv) ?></span><?= e(APP_NAME) ?></a>
    <div class="nav-links">
      <a href="#tentang">Tentang UMB</a><a href="#prodi">Program Studi</a><a href="#peran">Peran Pengguna</a><a href="#kontak">Kontak</a>
    </div>
    <?php if ($user): ?>
      <div class="nav-user">
        <div class="who-text">
          <div class="who-name"><?= e($user['nama_lengkap']) ?></div>
          <div class="who-role"><?= e($user['nama_role']) ?></div>
        </div>
        <a class="btn btn-sm" href="dashboard.php">Dashboard <?= arrow() ?></a>
        <form method="post" action="login.php">
          <input type="hidden" name="aksi" value="logout">
          <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
          <button class="btn btn-sm btn-ghost" type="submit">Keluar</button>
        </form>
      </div>
    <?php else: ?>
      <a class="btn btn-sm" href="login.php">Masuk <?= arrow() ?></a>
    <?php endif; ?>
  </nav>

  <h1 class="hero-title">
    <span class="display"><?= $user ? 'Halo, ' . e(explode(' ', trim($user['nama_lengkap']))[0]) : 'Selamat datang di' ?></span>
    <span class="display-serif"><?= $user ? 'selamat datang kembali.' : 'Universitas Muhammadiyah Bengkulu' ?></span>
  </h1>
  <p class="hero-sub">
    Kampus Muhammadiyah di Kota Bengkulu yang terdepan dan mencerahkan.
    Lewat SIM Mahasiswa, data akademik mahasiswa dikelola rapi dalam satu pintu.
  </p>
  <div class="hero-actions">
    <a class="btn" href="<?= $user ? 'dashboard.php' : 'login.php' ?>"><?= $user ? 'Buka dashboard' : 'Masuk ke sistem' ?> <?= arrow() ?></a>
    <a class="btn btn-ghost" href="#tentang">Kenali UMB</a>
  </div>

  <div class="stage">
    <div class="frame">
      <img src="assets/img/kampus.png" alt="Lima mahasiswa berdiri di depan gedung <?= e($univ['nama_universitas']) ?>" width="1931" height="814">
      <?php if ($lokasi): ?>
        <span class="pin"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg><?= e($lokasi) ?></span>
      <?php endif; ?>
    </div>
    <div class="float l"><div class="stat"><div class="stat-label">Mahasiswa aktif</div><div class="stat-value"><?= number_format($totalAktif, 0, ',', '.') ?></div><div class="stat-note">Tercatat di sistem</div></div></div>
    <div class="float r"><div class="stat stat-leaf"><div class="stat-label">Program studi aktif</div><div class="stat-value"><?= count($prodi) ?></div><div class="stat-note"><?= count($fakultas) ?> fakultas</div></div></div>
  </div>
</section>

<section class="sec" id="tentang">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="heading">Mengenal <em>UM Bengkulu</em></h2>
      <p>Perguruan tinggi swasta milik Persyarikatan Muhammadiyah di Kota Bengkulu, berdiri resmi sebagai universitas pada 20 Juni 1991.</p>
    </div>
    <div class="prodi-grid">
      <article class="prodi">
        <div class="prodi-top"><span class="num">1970</span><span class="chip">Awal</span></div>
        <h3>FKIS IKIP Muhammadiyah</h3>
        <p class="meta">Pada 1 Agustus 1970 IKIP Muhammadiyah Jakarta membuka Fakultas Keguruan dan Ilmu Sosial cabang Bengkulu, dengan jurusan Pendidikan Ilmu Administrasi dan Pendidikan Ekonomi Perusahaan.</p>
      </article>
      <article class="prodi">
        <div class="prodi-top"><span class="num">1973</span><span class="chip">Mandiri</span></div>
        <h3>STKIP Muhammadiyah Bengkulu</h3>
        <p class="meta">Mulai 1 Juni 1973 cabang tersebut berkembang menjadi sekolah tinggi keguruan yang berdiri sendiri.</p>
      </article>
      <article class="prodi">
        <div class="prodi-top"><span class="num">1991</span><span class="chip">Universitas</span></div>
        <h3>Universitas Muhammadiyah Bengkulu</h3>
        <p class="meta">Lewat SK Mendikbud No. 0367/O/1991, STKIP berubah bentuk menjadi universitas dan diresmikan pada 31 Agustus 1991.</p>
      </article>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="heading">Visi &amp; <em>Catur Dharma</em></h2>
      <p>UMB bercita-cita menjadi perguruan tinggi unggul dalam IPTEKS, kewirausahaan, dan peradaban kampus yang Islami pada 2028.</p>
    </div>
    <ol class="roles">
      <li class="role"><span class="n">01</span><h3>Pendidikan</h3><p>Menyiapkan lulusan yang berilmu, kompeten, dan siap bersaing.</p></li>
      <li class="role"><span class="n">02</span><h3>Penelitian</h3><p>Mengembangkan ilmu pengetahuan, teknologi, dan seni yang bermanfaat.</p></li>
      <li class="role"><span class="n">03</span><h3>Pengabdian masyarakat</h3><p>Menghadirkan ilmu kampus untuk kemajuan masyarakat Bengkulu.</p></li>
      <li class="role"><span class="n">04</span><h3>Al-Islam &amp; Kemuhammadiyahan</h3><p>Menanamkan nilai Islami dan semangat Muhammadiyah dalam kehidupan kampus.</p></li>
    </ol>
  </div>
</section>

<section class="sec" id="prodi">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="heading">Program studi <em>di UMB</em></h2>
      <p>Program studi aktif di Universitas Muhammadiyah Bengkulu beserta jumlah mahasiswa aktifnya.</p>
    </div>
    <?php if (!$prodi): ?>
      <div class="empty">Belum ada program studi aktif di tabel <code>program_studi</code>.</div>
    <?php else: ?>
      <div class="prodi-grid">
        <?php foreach ($prodi as $i => $p): ?>
          <article class="prodi">
            <div class="prodi-top"><span class="num"><?= nomor($i + 1) ?></span><span class="chip"><?= e($p['kode_program_studi']) ?></span></div>
            <h3><?= e($p['nama_program_studi']) ?></h3>
            <p class="meta"><?= e($p['jenjang']) ?> · <?= e($p['nama_fakultas']) ?></p>
            <div class="count"><b><?= number_format((int) $p['jumlah_aktif'], 0, ',', '.') ?></b><span>mahasiswa aktif</span></div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($roles): ?>
<section class="sec" id="peran">
  <div class="wrap">
    <div class="sec-head">
      <h2 class="heading">Satu sistem, <em><?= e(angka_kata(count($roles))) ?> peran</em></h2>
      <p>Setiap akun melihat data sesuai kewenangannya.</p>
    </div>
    <ol class="roles">
      <?php foreach ($roles as $i => $r): ?>
        <li class="role"><span class="n"><?= nomor($i + 1) ?></span><h3><?= e($r['nama_role']) ?></h3><p><?= e($r['keterangan']) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
<?php endif; ?>

<section class="sec" id="masuk">
  <div class="wrap">
    <div class="steps-panel">
      <h2 class="heading">Cara <em>masuk</em></h2>
      <ol class="steps">
        <li class="step"><span class="n">1</span><h3>Pakai email akunmu</h3><p>Email yang terdaftar untuk akunmu di sistem ini.</p></li>
        <li class="step"><span class="n">2</span><h3>Masukkan kata sandi</h3><p>Belum punya kata sandi atau lupa? Hubungi admin sistem.</p></li>
        <li class="step"><span class="n">3</span><h3>Lihat data sesuai peran</h3><p>Setiap peran melihat data sesuai kewenangannya.</p></li>
      </ol>
      <?php if (!$user): ?><div class="steps-cta"><a class="btn" href="login.php">Masuk sekarang <?= arrow() ?></a></div><?php endif; ?>
    </div>
  </div>
</section>

<section class="closing">
  <span class="ring" aria-hidden="true"></span><span class="ring two" aria-hidden="true"></span>
  <h2>Kampus mencerahkan, <em>data yang tertata.</em></h2>
  <p>Mahasiswa, program studi, dan pimpinan Universitas Muhammadiyah Bengkulu terhubung dalam satu sistem.</p>
  <?php if (!$user): ?><a class="btn btn-cream" href="login.php">Masuk ke <?= e(APP_NAME) ?> <?= arrow() ?></a><?php endif; ?>
</section>

<footer class="foot" id="kontak">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <a class="logo" href="index.php"><span class="logo-mark"><?= e($kodeUniv) ?></span><?= e(APP_NAME) ?></a>
        <p>Sistem Informasi Mahasiswa Universitas Muhammadiyah Bengkulu. <?= e($univ['slogan'] ?: 'Terdepan dan Mencerahkan.') ?></p>
      </div>
      <nav aria-label="Navigasi bawah"><strong>Navigasi</strong>
        <a href="#tentang">Tentang UMB</a><a href="#prodi">Program studi</a><a href="#peran">Peran pengguna</a><a href="#masuk">Cara masuk</a><a href="login.php">Masuk</a>
      </nav>
      <div><strong>Kontak</strong>
        <address>
          <?= e($univ['nama_universitas']) ?>
          <?php if ($alamat): ?><br><?= e(implode(', ', $alamat)) ?><?php endif; ?>
          <?php if ($univ['telepon']): ?><br>Telp. <?= e($univ['telepon']) ?><?php endif; ?>
          <?php if ($univ['email']): ?><br><a href="mailto:<?= e($univ['email']) ?>"><?= e($univ['email']) ?></a><?php endif; ?>
          <?php if ($website): ?><br><a href="<?= e($website) ?>" target="_blank" rel="noopener"><?= e($univ['website']) ?></a><?php endif; ?>
        </address>
      </div>
    </div>
  </div>
</footer>

</body>
</html>