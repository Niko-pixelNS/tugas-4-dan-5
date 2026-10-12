<?php
/**
 * dashboard.php — Beranda setelah login, log aktivitas (Admin), dan profil.
 * Kerangka (sidebar, menu sesuai hak akses) dari includes/layout.php.
 *   dashboard.php                 beranda
 *   dashboard.php?page=aktivitas  log aktivitas (Admin)
 *   dashboard.php?page=profil     profil saya
 *   dashboard.php?export=1        unduh CSV mahasiswa sesuai cakupan
 */
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/layout.php';

$user = wajib_login($koneksi);
$role = $user['kode_role'];
$isMhs = $role === 'MAHASISWA';
$bisaLihat = izin($user, 'mahasiswa');

/* Halaman lama dashboard.php?page=mahasiswa / prodi kini punya modul sendiri */
$page = (string) ($_GET['page'] ?? 'beranda');
if ($page === 'mahasiswa') pindah(url_ke('mahasiswa.php', ['q' => $_GET['q'] ?? '', 'prodi' => (int) ($_GET['prodi'] ?? 0), 'status' => $_GET['status'] ?? '']));
if ($page === 'prodi') pindah('prodi.php');
if (!in_array($page, ['beranda', 'aktivitas', 'profil'], true) || ($page === 'aktivitas' && !izin($user, 'aktivitas'))) $page = 'beranda';

$judul = ['beranda' => 'Beranda', 'aktivitas' => 'Aktivitas', 'profil' => 'Profil saya'][$page];
$cakupan = $user['cakupan']['label'] ?? ($isMhs ? 'Data pribadimu' : 'Belum ada akses data');
$hal = max(1, (int) ($_GET['hal'] ?? 1));
$saya = (int) $user['id_pengguna'];
$univ = db_row($koneksi, 'SELECT kode_universitas, nama_universitas FROM universitas ORDER BY id_universitas LIMIT 1') ?? ['kode_universitas' => 'SIM', 'nama_universitas' => ''];

/* ---------- Ganti kata sandi (Profil saya) ---------- */
$errSandi = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'ganti_sandi') {
    wajib_csrf();
    $sandiLama  = (string) ($_POST['sandi_lama'] ?? '');
    $sandiBaru  = (string) ($_POST['sandi_baru'] ?? '');
    $sandiUlang = (string) ($_POST['sandi_ulang'] ?? '');
    $hash = (string) db_val($koneksi, 'SELECT password_hash FROM pengguna WHERE id_pengguna = ?', [$saya]);
    if (!password_verify($sandiLama, $hash)) $errSandi['sandi_lama'] = 'Kata sandi saat ini salah.';
    if (strlen($sandiBaru) < 8 || strlen($sandiBaru) > 72) $errSandi['sandi_baru'] = 'Kata sandi baru 8–72 karakter.';
    elseif ($sandiBaru === $sandiLama) $errSandi['sandi_baru'] = 'Kata sandi baru harus berbeda dari yang lama.';
    elseif ($sandiBaru !== $sandiUlang) $errSandi['sandi_ulang'] = 'Ulangi kata sandi baru yang sama.';
    if (!$errSandi) {
        db_jalankan($koneksi, 'UPDATE pengguna SET password_hash = ? WHERE id_pengguna = ?', [password_hash($sandiBaru, PASSWORD_DEFAULT), $saya]);
        audit($koneksi, $saya, 'UPDATE', 'pengguna', (string) $saya, null, ['password_diubah' => true]);
        session_regenerate_id(true);
        flash('ok', 'Kata sandi berhasil diganti. Gunakan kata sandi baru saat login berikutnya.');
        pindah('dashboard.php?page=profil');
    }
    $page = 'profil';
    $judul = 'Profil saya';
}

/* ---------- Profil mahasiswa (akun MAHASISWA) ---------- */
$profil = $isMhs ? db_row($koneksi, 'SELECT v.* FROM v_profil_mahasiswa v JOIN mahasiswa m ON m.id_mahasiswa = v.id_mahasiswa WHERE m.id_pengguna = ? LIMIT 1', [$saya]) : null;
$kp = kelengkapan_profil($koneksi, $user);
$kosong = $kp['kosong'];
$terisi = $kp['terisi'];
$isian = array_fill(0, $kp['total'], 1);
$persenProfil = (int) round($terisi / max(1, $kp['total']) * 100);

/* ---------- Ekspor CSV (sebelum HTML apa pun) ---------- */
if (isset($_GET['export']) && $bisaLihat) {
    [$w, $params] = cakupan_sql($user['cakupan'], 'v.id_universitas', 'v.id_fakultas', 'v.id_program_studi');
    $hasil = db_jalankan(
        $koneksi,
        "SELECT v.npm, v.nama_mahasiswa, v.jenis_kelamin_text, v.tempat_lahir, v.tanggal_lahir, v.jenjang,
                v.nama_program_studi, v.nama_fakultas, v.tanggal_masuk, v.status_mahasiswa, v.alamat
           FROM v_mahasiswa_per_prodi v WHERE $w ORDER BY v.nama_program_studi, v.npm LIMIT 20000",
        $params
    )->get_result();
    audit($koneksi, $saya, 'VIEW', 'mahasiswa', 'export_csv');
    while (ob_get_level() > 0) {
        ob_end_clean(); // buang buffer agar file CSV bersih
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mahasiswa-' . date('Ymd-His') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, ['NPM', 'Nama', 'Jenis kelamin', 'Tempat lahir', 'Tanggal lahir', 'Jenjang', 'Program studi', 'Fakultas', 'Tanggal masuk', 'Status', 'Alamat'], ';', '"', '');
    while ($row = $hasil->fetch_row()) {
        fputcsv($out, array_map('csv_aman', $row), ';', '"', '');
    }
    fclose($out);
    exit;
}

/* ---------- Ringkasan data dalam cakupan ---------- */
$warnaStatus = [
    'Aktif' => '#4f7c83', 'Lulus' => '#9cbcc1', 'Cuti' => '#d9b54a',
    'Tidak Aktif' => '#b7bbb4', 'Mengundurkan Diri' => '#c98a6b', 'Drop Out' => '#9e2531',
];
$perStatus = array_fill_keys(STATUS_MHS, 0);
$prodiList = [];
$totalMhs  = 0;
if ($bisaLihat) {
    [$w, $params] = cakupan_sql($user['cakupan'], 'v.id_universitas', 'v.id_fakultas', 'v.id_program_studi');
    foreach (db_rows($koneksi, "SELECT v.status_mahasiswa, COUNT(*) n FROM v_mahasiswa_per_prodi v WHERE $w GROUP BY v.status_mahasiswa", $params) as $r) {
        $perStatus[$r['status_mahasiswa']] = (int) $r['n'];
    }
    $totalMhs = array_sum($perStatus);
    $prodiList = db_rows(
        $koneksi,
        "SELECT v.id_program_studi, v.kode_program_studi, v.nama_program_studi, v.jenjang, v.nama_fakultas,
                COUNT(m.id_mahasiswa) AS total
           FROM (SELECT ps.id_program_studi, ps.kode_program_studi, ps.nama_program_studi, ps.jenjang,
                        f.id_fakultas, f.nama_fakultas, f.id_universitas
                   FROM program_studi ps JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
                  WHERE ps.status_aktif = 'Aktif') v
           LEFT JOIN mahasiswa m ON m.id_program_studi = v.id_program_studi
          WHERE $w
          GROUP BY v.id_program_studi, v.kode_program_studi, v.nama_program_studi, v.jenjang, v.nama_fakultas
          ORDER BY total DESC, v.nama_program_studi",
        $params
    );
}

/* ---------- Aktivitas ---------- */
function ambil_aktivitas(mysqli $koneksi, ?int $hanyaPengguna, string $aksi, int $limit, int $offset = 0): array
{
    $where = [];
    $params = [];
    if ($hanyaPengguna) { $where[] = 'a.id_pengguna = ?'; $params[] = $hanyaPengguna; }
    if ($aksi !== '')   { $where[] = 'a.aksi = ?';        $params[] = $aksi; }
    $sql = 'SELECT a.*, p.nama_lengkap, r.nama_role
              FROM audit_log a
              LEFT JOIN pengguna p ON p.id_pengguna = a.id_pengguna
              LEFT JOIN roles r ON r.id_role = p.id_role'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY a.waktu DESC, a.id_audit DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    return db_rows($koneksi, $sql, $params);
}
function teks_aktivitas(array $a): array
{
    $nama = ['program_studi' => 'program studi', 'roles' => 'role'];
    $tabel = $nama[$a['tabel_nama']] ?? ($a['tabel_nama'] ?: 'data');
    $rec   = $a['record_id'] ? ' #' . $a['record_id'] : '';
    switch ($a['aksi']) {
        case 'LOGIN':  return ['login', 'Masuk ke sistem'];
        case 'LOGOUT': return ['logout', 'Keluar dari sistem'];
        case 'VIEW':   return ['eye', $a['record_id'] === 'export_csv' ? 'Mengunduh data mahasiswa (CSV)' : 'Melihat data ' . $tabel . $rec];
        case 'INSERT': return ['plus', 'Menambah data ' . $tabel . $rec];
        case 'UPDATE': return ['pencil', 'Mengubah data ' . $tabel . $rec];
        case 'DELETE': return ['trash', 'Menghapus data ' . $tabel . $rec];
    }
    return ['activity', $a['aksi']];
}

$adminLog = izin($user, 'aktivitas');
$aktivitas = [];
$mhsTerbaru = [];
$jumlahDaftar = 0; $jumlahHal = 1;
$fAksi = in_array($_GET['aksi'] ?? '', ['LOGIN', 'LOGOUT', 'INSERT', 'UPDATE', 'DELETE', 'VIEW'], true) ? (string) $_GET['aksi'] : '';

if ($page === 'beranda') {
    $aktivitas = ambil_aktivitas($koneksi, $adminLog ? null : $saya, '', 4);
    if ($bisaLihat) {
        [$w, $params] = cakupan_sql($user['cakupan'], 'v.id_universitas', 'v.id_fakultas', 'v.id_program_studi');
        $mhsTerbaru = db_rows($koneksi, "SELECT v.id_mahasiswa, v.npm, v.nama_mahasiswa, v.kode_program_studi, v.nama_program_studi, v.jenjang, v.tanggal_masuk, v.status_mahasiswa
                              FROM v_mahasiswa_per_prodi v WHERE $w ORDER BY v.tanggal_masuk DESC, v.id_mahasiswa DESC LIMIT 5", $params);
    }
} elseif ($page === 'aktivitas') {
    $jumlahDaftar = (int) db_val($koneksi, 'SELECT COUNT(*) FROM audit_log' . ($fAksi !== '' ? ' WHERE aksi = ?' : ''), $fAksi !== '' ? [$fAksi] : []);
    [$hal, $jumlahHal, $offset] = hitung_halaman($jumlahDaftar, $hal, 20);
    $aktivitas = ambil_aktivitas($koneksi, null, $fAksi, 20, $offset);
}

$segStatus = [];
foreach (['Aktif', 'Lulus', 'Cuti', 'Tidak Aktif', 'Mengundurkan Diri', 'Drop Out'] as $s) {
    if ($perStatus[$s] > 0 || in_array($s, ['Aktif', 'Lulus', 'Cuti'], true)) {
        $segStatus[] = ['label' => $s, 'nilai' => $perStatus[$s], 'warna' => $warnaStatus[$s]];
    }
}
$dominan = $totalMhs ? array_search(max($perStatus), $perStatus, true) : 'Belum ada data';
$namaDepan = explode(' ', trim($user['nama_lengkap']))[0];

/* ---------- Tampilan ---------- */
$unduh = $bisaLihat ? '<a class="icon-btn" href="dashboard.php?export=1" title="Unduh CSV" aria-label="Unduh data mahasiswa (CSV)">' . ikon('download') . '</a>' : '';
layout_awal($user, $judul, $page, ['rail' => true, 'aksi_html' => $unduh]);
?>
      <?php if ($bisaLihat && $page === 'beranda'): ?>
        <!-- Kotak pencarian (pengganti kotak "Share something" di referensi) -->
        <form class="composer" action="mahasiswa.php" method="get" role="search">
          <label class="sr-only" for="main-q">Cari mahasiswa berdasarkan NPM atau nama</label>
          <input class="composer-input" id="main-q" name="q" type="search" placeholder="Cari mahasiswa berdasarkan NPM atau nama…" value="" autocomplete="off">
          <div class="composer-row">
            <label class="pill-select">
              <span class="sr-only">Program studi</span>
              <?= ikon('cap') ?>
              <select name="prodi">
                <option value="">Semua program studi</option>
                <?php foreach ($prodiList as $p): ?>
                  <option value="<?= (int) $p['id_program_studi'] ?>"><?= e($p['nama_program_studi']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="pill-select">
              <span class="sr-only">Status</span>
              <?= ikon('filter') ?>
              <select name="status">
                <option value="">Semua status</option>
                <?php foreach (STATUS_MHS as $s): ?>
                  <option><?= e($s) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <button class="btn-dark" type="submit">Cari</button>
          </div>
        </form>
      <?php endif; ?>

      <?php if ($page === 'beranda'): ?>
        <?php if ($bisaLihat): ?>
          <!-- Ringkasan (pengganti post dengan video) -->
          <article class="post">
            <header class="post-head">
              <span class="av av-logo" aria-hidden="true"><?= e($univ['kode_universitas'] ?: 'SIM') ?></span>
              <span class="post-name"><?= e(APP_NAME) ?></span>
              <span class="post-time">Hari ini</span>
              <a class="post-link" href="<?= 'prodi.php' ?>">Per prodi</a>
            </header>
            <p class="post-text">Halo <?= e($namaDepan) ?>, ada <strong><?= angka($totalMhs) ?> mahasiswa</strong> dalam cakupanmu (<?= e($cakupan) ?>), <?= angka($perStatus['Aktif']) ?> di antaranya berstatus aktif.</p>
            <div class="post-media stats-media">
              <div class="sm-tile"><span>Total mahasiswa</span><b><?= angka($totalMhs) ?></b></div>
              <div class="sm-tile sm-dark"><span>Aktif</span><b><?= angka($perStatus['Aktif']) ?></b></div>
              <div class="sm-tile"><span>Cuti</span><b><?= angka($perStatus['Cuti']) ?></b></div>
              <div class="sm-tile"><span>Lulus</span><b><?= angka($perStatus['Lulus']) ?></b></div>
            </div>
            <footer class="post-foot">
              <span class="meta-i" title="Program studi"><?= ikon('cap') ?><?= count($prodiList) ?> prodi</span>
              <span class="meta-i" title="Tidak aktif, mengundurkan diri, atau drop out"><?= ikon('layers') ?><?= angka($perStatus['Tidak Aktif'] + $perStatus['Mengundurkan Diri'] + $perStatus['Drop Out']) ?> nonaktif</span>
              <a class="post-action" href="<?= 'mahasiswa.php' ?>"><?= ikon('sparkle') ?>Lihat semua mahasiswa</a>
            </footer>
          </article>

          <article class="post">
            <header class="post-head">
              <span class="post-title">Mahasiswa terbaru</span>
              <a class="post-link" href="<?= 'mahasiswa.php' ?>">Lihat semua</a>
            </header>
            <?php if (!$mhsTerbaru): ?>
              <p class="post-text muted">Belum ada data mahasiswa dalam cakupanmu.</p>
            <?php else: ?>
              <ul class="rows">
                <?php foreach ($mhsTerbaru as $m): ?>
                  <li class="row">
                    <?= avatar($m['nama_mahasiswa']) ?>
                    <span class="row-main"><span class="row-name"><?= e($m['nama_mahasiswa']) ?></span><span class="row-sub"><?= e($m['npm']) ?> · <?= e($m['jenjang'] . ' ' . $m['nama_program_studi']) ?> · masuk <?= e(tanggal_id($m['tanggal_masuk'])) ?></span></span>
                    <?= chip_status($m['status_mahasiswa']) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>

        <?php elseif ($isMhs): ?>
          <article class="post">
            <header class="post-head">
              <?= avatar($user['nama_lengkap']) ?>
              <span class="post-name"><?= e($user['nama_lengkap']) ?></span>
              <span class="post-time"><?= $profil ? e($profil['npm']) : 'Mahasiswa' ?></span>
              <?php if ($profil): ?><?= chip_status($profil['status_mahasiswa']) ?><?php endif; ?>
            </header>
            <?php if (!$profil): ?>
              <p class="post-text">Akunmu belum terhubung ke data mahasiswa. Minta operator program studi menghubungkan akun ini.</p>
            <?php else: ?>
              <p class="post-text">Halo <?= e($namaDepan) ?>, ini ringkasan data akademikmu.</p>
              <div class="post-media stats-media">
                <div class="sm-tile sm-wide"><span>Program studi</span><b class="sm-text"><?= e($profil['jenjang'] . ' ' . $profil['nama_program_studi']) ?></b></div>
                <div class="sm-tile"><span>Fakultas</span><b class="sm-text"><?= e($profil['nama_fakultas']) ?></b></div>
                <div class="sm-tile sm-dark"><span>Tanggal masuk</span><b class="sm-text"><?= e(tanggal_id($profil['tanggal_masuk'])) ?></b></div>
              </div>
              <footer class="post-foot">
                <span class="meta-i"><?= ikon('pin') ?><?= e($profil['alamat'] ?: '—') ?></span>
                <a class="post-action" href="<?= 'dashboard.php?page=profil' ?>"><?= ikon('sparkle') ?>Lihat profil lengkap</a>
              </footer>
            <?php endif; ?>
          </article>

        <?php else: ?>
          <article class="post">
            <header class="post-head"><?= avatar($user['nama_lengkap']) ?><span class="post-name"><?= e($user['nama_lengkap']) ?></span><span class="post-time"><?= e($user['nama_role']) ?></span></header>
            <p class="post-text">Peran <strong><?= e($user['nama_role']) ?></strong> belum punya akses ke data mahasiswa. Minta admin mengatur peran akunmu.</p>
          </article>
        <?php endif; ?>

        <!-- Aktivitas (pengganti postingan feed) -->
        <section aria-labelledby="judul-aktivitas">
          <div class="feed-head">
            <h2 id="judul-aktivitas"><?= $adminLog ? 'Aktivitas terbaru' : 'Aktivitas akunmu' ?></h2>
            <?php if ($adminLog): ?><a class="post-link" href="<?= 'dashboard.php?page=aktivitas' ?>">Lihat log</a><?php endif; ?>
          </div>
          <?php if (!$aktivitas): ?>
            <p class="post-text muted">Belum ada aktivitas.</p>
          <?php endif; ?>
          <?php foreach ($aktivitas as $a): [$ic, $teks] = teks_aktivitas($a); $nm = $a['nama_lengkap'] ?: 'Pengguna terhapus'; ?>
            <article class="post post-sm">
              <header class="post-head">
                <?= avatar($nm) ?>
                <span class="row-main"><span class="post-name"><?= e($nm) ?></span><span class="row-sub"><?= e($a['nama_role'] ?: '—') ?> · <?= e(waktu_lalu($a['waktu'])) ?></span></span>
              </header>
              <p class="post-text"><span class="act-ic"><?= ikon($ic) ?></span><?= e($teks) ?></p>
              <footer class="post-foot">
                <span class="meta-i"><?= ikon('activity') ?><?= e(tanggal_id($a['waktu'], true)) ?></span>
                <?php if ($adminLog && $a['ip_address']): ?><span class="meta-i"><?= ikon('pin') ?>IP <?= e($a['ip_address']) ?></span><?php endif; ?>
              </footer>
            </article>
          <?php endforeach; ?>
        </section>


      <?php elseif ($page === 'aktivitas'): ?>
        <h1 class="page-title">Log aktivitas</h1>
        <div class="tabs" role="list">
          <?php foreach (['' => 'Semua', 'LOGIN' => 'Masuk', 'LOGOUT' => 'Keluar', 'VIEW' => 'Unduh/lihat', 'INSERT' => 'Tambah', 'UPDATE' => 'Ubah', 'DELETE' => 'Hapus'] as $k => $lbl): ?>
            <a role="listitem" class="tab<?= $fAksi === $k ? ' is-active' : '' ?>" href="<?= e(url_ke('dashboard.php', ['page' => 'aktivitas', 'aksi' => $k])) ?>"<?= $fAksi === $k ? ' aria-current="true"' : '' ?>><?= e($lbl) ?></a>
          <?php endforeach; ?>
        </div>
        <section class="post">
          <?php if (!$aktivitas): ?>
            <p class="post-text muted">Belum ada aktivitas.</p>
          <?php else: ?>
            <ul class="rows">
              <?php foreach ($aktivitas as $a): [$ic, $teks] = teks_aktivitas($a); $nm = $a['nama_lengkap'] ?: 'Pengguna terhapus'; ?>
                <li class="row">
                  <?= avatar($nm) ?>
                  <span class="row-main"><span class="row-name"><?= e($nm) ?> <span class="row-act"><?= e($teks) ?></span></span><span class="row-sub"><?= e(tanggal_id($a['waktu'], true)) ?> · IP <?= e($a['ip_address'] ?: '—') ?></span></span>
                  <span class="act-badge"><?= ikon($ic) ?><?= e($a['aksi']) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
            <?= paginasi('dashboard.php', $hal, $jumlahHal, ['page' => 'aktivitas', 'aksi' => $fAksi]) ?>
          <?php endif; ?>
        </section>

      <?php elseif ($page === 'profil'): ?>
        <h1 class="page-title">Profil saya</h1>
        <section class="post">
          <header class="post-head"><?= avatar($user['nama_lengkap']) ?><span class="post-name"><?= e($user['nama_lengkap']) ?></span><span class="post-time">@<?= e($user['username']) ?></span></header>
          <dl class="dl">
            <div><dt>Email</dt><dd><?= e($user['email'] ?: '—') ?></dd></div>
            <div><dt>Nomor HP</dt><dd><?= e($user['no_hp'] ?: '—') ?></dd></div>
            <div><dt>Peran</dt><dd><?= e($user['nama_role']) ?></dd></div>
            <div><dt>Cakupan data</dt><dd><?= e($cakupan) ?></dd></div>
            <div><dt>Status akun</dt><dd><?= chip_status($user['status_aktif']) ?></dd></div>
            <div><dt>Login terakhir</dt><dd><?= e(tanggal_id($user['last_login'], true)) ?></dd></div>
          </dl>
        </section>
        <section class="post" id="ganti-sandi">
          <header class="post-head"><span class="post-title">Ganti kata sandi</span></header>
          <form class="form-sandi" method="post" action="dashboard.php?page=profil#ganti-sandi" novalidate>
            <?= csrf_field() ?><input type="hidden" name="op" value="ganti_sandi">
            <?= f_input('sandi_lama', 'Kata sandi saat ini', '', $errSandi, ['type' => 'password', 'attr' => ['required' => true, 'autocomplete' => 'current-password']]) ?>
            <?= f_input('sandi_baru', 'Kata sandi baru', '', $errSandi, ['type' => 'password', 'attr' => ['required' => true, 'minlength' => 8, 'maxlength' => 72, 'autocomplete' => 'new-password'], 'bantuan' => 'Minimal 8 karakter.']) ?>
            <?= f_input('sandi_ulang', 'Ulangi kata sandi baru', '', $errSandi, ['type' => 'password', 'attr' => ['required' => true, 'minlength' => 8, 'maxlength' => 72, 'autocomplete' => 'new-password']]) ?>
            <div><button class="btn-primary" type="submit">Simpan kata sandi</button></div>
          </form>
        </section>
        <?php if ($profil): ?>
          <section class="post">
            <header class="post-head"><span class="post-title">Data mahasiswa</span><?= chip_status($profil['status_mahasiswa']) ?></header>
            <dl class="dl">
              <div><dt>NPM</dt><dd><?= e($profil['npm']) ?></dd></div>
              <div><dt>Program studi</dt><dd><?= e($profil['jenjang'] . ' ' . $profil['nama_program_studi']) ?></dd></div>
              <div><dt>Fakultas</dt><dd><?= e($profil['nama_fakultas']) ?></dd></div>
              <div><dt>Jenis kelamin</dt><dd><?= $profil['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></dd></div>
              <div><dt>Tempat, tanggal lahir</dt><dd><?= e(trim(($profil['tempat_lahir'] ?? '') . ', ' . tanggal_id($profil['tanggal_lahir']), ', ')) ?></dd></div>
              <div><dt>Tanggal masuk</dt><dd><?= e(tanggal_id($profil['tanggal_masuk'])) ?></dd></div>
              <div><dt>Alamat</dt><dd><?= e($profil['alamat'] ?: '—') ?></dd></div>
            </dl>
          </section>
        <?php endif; ?>
        <section class="post">
          <header class="post-head"><span class="post-title">Kelengkapan profil</span><span class="post-time"><?= $terisi ?>/<?= count($isian) ?> terisi</span></header>
          <?php if ($kosong): ?>
            <p class="post-text">Data yang masih kosong: <strong><?= e(implode(', ', $kosong)) ?></strong>. Minta admin atau operator untuk melengkapinya.</p>
          <?php else: ?>
            <p class="post-text">Semua data profilmu sudah lengkap.</p>
          <?php endif; ?>
        </section>
      <?php endif; ?>
<?php
ob_start(); // panel kanan ditampung dulu, lalu dicetak oleh layout_akhir()
?>
  <aside class="rail" aria-label="Ringkasan">
    <?php if ($bisaLihat): ?>
      <section class="rail-sec">
        <div class="rail-head"><h2>Status mahasiswa</h2></div>
        <?= donut($segStatus, (string) $dominan, 'Komposisi status mahasiswa') ?>
      </section>

      <section class="rail-sec">
        <div class="rail-head"><h2>Program studi</h2><a class="rail-link" href="<?= 'prodi.php' ?>">Lihat semua</a></div>
        <?php if (!$prodiList): ?>
          <p class="muted small">Belum ada program studi.</p>
        <?php else: ?>
          <ul class="trend">
            <?php foreach (array_slice($prodiList, 0, 5) as $p): ?>
              <li><a href="<?= e(url_ke('mahasiswa.php', ['prodi' => (int) $p['id_program_studi']])) ?>">
                <span class="trend-name">#<?= e($p['kode_program_studi']) ?> <span><?= e($p['nama_program_studi']) ?></span></span>
                <span class="trend-n"><?= angka($p['total']) ?></span><?= ikon('arrow') ?>
              </a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

      <section class="promo">
        <span class="promo-tag">Ekspor data</span>
        <h2>Unduh data mahasiswa</h2>
        <p>Format CSV, bisa dibuka di Excel. Sesuai cakupanmu.</p>
        <a class="promo-btn" href="<?= 'dashboard.php?export=1' ?>" aria-label="Unduh data mahasiswa (CSV)"><?= ikon('download') ?></a>
        <svg class="promo-art" viewBox="0 0 160 160" aria-hidden="true"><rect x="40" y="18" width="96" height="120" rx="16" class="pa-1"/><rect x="56" y="38" width="64" height="9" rx="4.5" class="pa-2"/><rect x="56" y="58" width="44" height="9" rx="4.5" class="pa-2"/><rect x="56" y="78" width="54" height="9" rx="4.5" class="pa-2"/><rect x="56" y="98" width="34" height="9" rx="4.5" class="pa-3"/><circle cx="128" cy="30" r="14" class="pa-5"/></svg>
      </section>

    <?php else: ?>
      <section class="rail-sec">
        <div class="rail-head"><h2>Kelengkapan profil</h2><span class="rail-sub"><?= $persenProfil ?>%</span></div>
        <?= donut([
            ['label' => 'Terisi', 'nilai' => $terisi, 'warna' => '#4f7c83'],
            ['label' => 'Belum diisi', 'nilai' => count($kosong), 'warna' => '#d9b54a'],
        ], $persenProfil === 100 ? 'Lengkap' : $persenProfil . '% lengkap', 'Kelengkapan profil') ?>
      </section>

      <?php if ($profil): ?>
        <section class="rail-sec">
          <div class="rail-head"><h2>Program studimu</h2></div>
          <ul class="trend trend-static">
            <li><span><span class="trend-name">#<?= e($profil['kode_program_studi']) ?> <span><?= e($profil['nama_program_studi']) ?></span></span></span></li>
            <li><span><span class="trend-name"><?= e($profil['nama_fakultas']) ?></span></span></li>
            <li><span><span class="trend-name"><?= e($profil['nama_universitas']) ?></span></span></li>
          </ul>
        </section>
      <?php endif; ?>

      <section class="promo">
        <span class="promo-tag">Bantuan</span>
        <h2>Ada data yang salah?</h2>
        <p>Hubungi <?= $isMhs ? 'operator program studimu' : 'admin ' . e(APP_NAME) ?> untuk memperbaikinya.</p>
        <svg class="promo-art" viewBox="0 0 160 160" aria-hidden="true"><rect x="40" y="18" width="96" height="120" rx="16" class="pa-1"/><rect x="56" y="38" width="64" height="9" rx="4.5" class="pa-2"/><rect x="56" y="58" width="44" height="9" rx="4.5" class="pa-2"/><rect x="56" y="78" width="54" height="9" rx="4.5" class="pa-2"/><rect x="56" y="98" width="34" height="9" rx="4.5" class="pa-3"/><circle cx="128" cy="30" r="14" class="pa-5"/></svg>
      </section>
    <?php endif; ?>
<?php
layout_akhir(ob_get_clean());
