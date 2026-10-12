<?php
/**
 * universitas.php — CRUD tabel `universitas` (khusus Admin).
 *   universitas.php               daftar
 *   universitas.php?aksi=tambah   formulir tambah
 *   universitas.php?aksi=edit&id= formulir ubah
 *   POST op=simpan / op=hapus
 */
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/layout.php';

$user = wajib_login($koneksi);
wajib_izin($user, 'universitas');
$kelola = izin($user, 'universitas', 'kelola');
$file = 'universitas.php';

$kosong = ['id_universitas' => 0, 'kode_universitas' => '', 'nama_universitas' => '', 'slogan' => '', 'alamat' => '', 'kota' => '',
           'provinsi' => '', 'kode_pos' => '', 'website' => '', 'email' => '', 'telepon' => ''];
$aksi = (string) ($_GET['aksi'] ?? '');
$data = $kosong;
$err  = [];

/* ---------- Proses POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    wajib_izin($user, 'universitas', 'kelola');
    $id = post_int('id');

    if (($_POST['op'] ?? '') === 'hapus') {
        $lama = db_row($koneksi, 'SELECT * FROM universitas WHERE id_universitas = ?', [$id]);
        if ($lama) {
            try {
                db_jalankan($koneksi, 'DELETE FROM universitas WHERE id_universitas = ?', [$id]);
                audit($koneksi, (int) $user['id_pengguna'], 'DELETE', 'universitas', (string) $id, $lama);
                flash('ok', 'Universitas "' . $lama['nama_universitas'] . '" dihapus.');
            } catch (mysqli_sql_exception $ex) {
                flash('error', pesan_db($ex));
            }
        }
        pindah($file);
    }

    $data = [
        'id_universitas'   => $id,
        'kode_universitas' => strtoupper(post_teks('kode_universitas', 20)),
        'nama_universitas' => post_teks('nama_universitas', 200),
        'slogan'           => post_teks('slogan', 255),
        'alamat'           => post_teks('alamat', 1000),
        'kota'             => post_teks('kota', 100),
        'provinsi'         => post_teks('provinsi', 100),
        'kode_pos'         => post_teks('kode_pos', 10),
        'website'          => post_teks('website', 255),
        'email'            => post_teks('email', 150),
        'telepon'          => post_teks('telepon', 50),
    ];
    if (!preg_match('/^[A-Z0-9._-]{1,20}$/', $data['kode_universitas'])) $err['kode_universitas'] = 'Isi 1–20 karakter: huruf, angka, titik, strip, atau garis bawah.';
    if ($data['nama_universitas'] === '') $err['nama_universitas'] = 'Nama universitas wajib diisi.';
    if ($data['kode_pos'] !== '' && !preg_match('/^\d{3,10}$/', $data['kode_pos'])) $err['kode_pos'] = 'Kode pos hanya angka.';
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Format email tidak valid.';
    if ($data['website'] !== '' && !(preg_match('~^https?://~i', $data['website']) && filter_var($data['website'], FILTER_VALIDATE_URL))) $err['website'] = 'Tulis lengkap dengan http:// atau https://';
    if ($data['telepon'] !== '' && !preg_match('/^[0-9 +().-]{5,50}$/', $data['telepon'])) $err['telepon'] = 'Hanya angka, spasi, +, -, ( ).';

    if (!$err) {
        $nilai = [$data['kode_universitas'], $data['nama_universitas']];
        foreach (['slogan', 'alamat', 'kota', 'provinsi', 'kode_pos', 'website', 'email', 'telepon'] as $k) {
            $nilai[] = $data[$k] === '' ? null : $data[$k];
        }
        try {
            if ($id) {
                $lama = db_row($koneksi, 'SELECT * FROM universitas WHERE id_universitas = ?', [$id]) ?? tidak_ditemukan($file, 'Universitas');
                db_jalankan($koneksi, 'UPDATE universitas SET kode_universitas=?, nama_universitas=?, slogan=?, alamat=?, kota=?, provinsi=?, kode_pos=?, website=?, email=?, telepon=?
                                       WHERE id_universitas = ?', array_merge($nilai, [$id]));
                audit($koneksi, (int) $user['id_pengguna'], 'UPDATE', 'universitas', (string) $id, $lama, $data);
                flash('ok', 'Data universitas diperbarui.');
            } else {
                db_jalankan($koneksi, 'INSERT INTO universitas (kode_universitas, nama_universitas, slogan, alamat, kota, provinsi, kode_pos, website, email, telepon)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $nilai);
                $baru = (int) $koneksi->insert_id;
                audit($koneksi, (int) $user['id_pengguna'], 'INSERT', 'universitas', (string) $baru, null, $data);
                flash('ok', 'Universitas "' . $data['nama_universitas'] . '" ditambahkan.');
            }
            pindah($file);
        } catch (mysqli_sql_exception $ex) {
            $err['_'] = pesan_db($ex, 'Kode universitas sudah dipakai.');
            if ($ex->getCode() === 1062) $err['kode_universitas'] = 'Kode ini sudah dipakai universitas lain.';
        }
    }
    $aksi = $id ? 'edit' : 'tambah';
} elseif ($aksi === 'edit') {
    $data = db_row($koneksi, 'SELECT * FROM universitas WHERE id_universitas = ?', [(int) ($_GET['id'] ?? 0)]) ?? tidak_ditemukan($file, 'Universitas');
}

/* ---------- Tampilan ---------- */
layout_awal($user, 'Universitas', 'universitas', ['lebar' => true]);

if ($kelola && in_array($aksi, ['tambah', 'edit'], true)): ?>
  <?= kepala_halaman('Master data', $aksi === 'edit' ? 'Ubah universitas' : 'Tambah universitas', 'Kolom bertanda * wajib diisi.') ?>
  <form class="card form" method="post" action="<?= e($file) ?>" novalidate>
    <?= csrf_field() ?><?= ringkasan_error($err) ?>
    <input type="hidden" name="op" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $data['id_universitas'] ?>">
    <div class="form-grid">
      <?= f_input('kode_universitas', 'Kode universitas', $data['kode_universitas'], $err, ['attr' => ['required' => true, 'maxlength' => 20], 'bantuan' => 'Contoh: UMB']) ?>
      <?= f_input('nama_universitas', 'Nama universitas', $data['nama_universitas'], $err, ['attr' => ['required' => true, 'maxlength' => 200]]) ?>
      <?= f_input('slogan', 'Slogan', $data['slogan'], $err, ['attr' => ['maxlength' => 255], 'lebar' => true]) ?>
      <?= f_textarea('alamat', 'Alamat', $data['alamat'], $err, ['attr' => ['maxlength' => 1000]]) ?>
      <?= f_input('kota', 'Kota', $data['kota'], $err, ['attr' => ['maxlength' => 100]]) ?>
      <?= f_input('provinsi', 'Provinsi', $data['provinsi'], $err, ['attr' => ['maxlength' => 100]]) ?>
      <?= f_input('kode_pos', 'Kode pos', $data['kode_pos'], $err, ['attr' => ['maxlength' => 10, 'inputmode' => 'numeric']]) ?>
      <?= f_input('telepon', 'Telepon', $data['telepon'], $err, ['attr' => ['maxlength' => 50, 'inputmode' => 'tel']]) ?>
      <?= f_input('email', 'Email', $data['email'], $err, ['type' => 'email', 'attr' => ['maxlength' => 150]]) ?>
      <?= f_input('website', 'Website', $data['website'], $err, ['type' => 'url', 'attr' => ['maxlength' => 255, 'placeholder' => 'https://']]) ?>
    </div>
    <div class="form-act">
      <button class="btn-primary" type="submit">Simpan</button>
      <a class="btn-line" href="<?= e($file) ?>">Batal</a>
    </div>
  </form>
<?php else:
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $where = '1 = 1'; $params = [];
    if ($q !== '') { $where = '(u.kode_universitas LIKE ? OR u.nama_universitas LIKE ? OR u.kota LIKE ?)'; $l = '%' . like_escape($q) . '%'; $params = [$l, $l, $l]; }
    $total = (int) db_val($koneksi, "SELECT COUNT(*) FROM universitas u WHERE $where", $params);
    [$hal, $jmlHal, $offset] = hitung_halaman($total, (int) ($_GET['hal'] ?? 1));
    $rows = db_rows($koneksi, "SELECT u.*, (SELECT COUNT(*) FROM fakultas f WHERE f.id_universitas = u.id_universitas) AS jml_fakultas
                                 FROM universitas u WHERE $where ORDER BY u.nama_universitas LIMIT " . PER_HALAMAN . " OFFSET $offset", $params);
?>
  <?= kepala_halaman('Master data', 'Universitas', 'Data universitas yang menaungi fakultas dan program studi.',
        $kelola ? '<a class="btn-primary" href="universitas.php?aksi=tambah">' . ikon('plus') . 'Tambah universitas</a>' : '') ?>
  <section class="card">
    <form class="toolbar" method="get" action="<?= e($file) ?>" role="search">
      <label class="sr-only" for="q">Cari universitas</label>
      <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari kode, nama, atau kota…">
      <button class="btn-dark" type="submit">Cari</button>
      <?php if ($q !== ''): ?><a class="btn-line" href="<?= e($file) ?>">Reset</a><?php endif; ?>
      <span class="toolbar-info"><?= angka($total) ?> data</span>
    </form>
    <?php if (!$rows): ?>
      <p class="empty-note">Belum ada data universitas<?= $q !== '' ? ' yang cocok' : '' ?>.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th scope="col">Kode</th><th scope="col">Nama universitas</th><th scope="col">Kota</th><th scope="col">Kontak</th><th scope="col" class="num">Fakultas</th><?php if ($kelola): ?><th scope="col" class="act">Aksi</th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><span class="code"><?= e($r['kode_universitas']) ?></span></td>
                <td><span class="row-name"><?= e($r['nama_universitas']) ?></span><?php if ($r['slogan']): ?><span class="row-sub"><?= e($r['slogan']) ?></span><?php endif; ?></td>
                <td><?= e(implode(', ', array_filter([$r['kota'], $r['provinsi']])) ?: '—') ?></td>
                <td><span class="row-sub"><?= e(implode(' · ', array_filter([$r['telepon'], $r['email']])) ?: '—') ?></span></td>
                <td class="num"><?= angka($r['jml_fakultas']) ?></td>
                <?php if ($kelola): ?><td class="act"><?= tombol_edit($file, (int) $r['id_universitas'], $r['nama_universitas']) ?><?= tombol_hapus($file, (int) $r['id_universitas'], $r['nama_universitas']) ?></td><?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= paginasi($file, $hal, $jmlHal, ['q' => $q]) ?>
    <?php endif; ?>
  </section>
<?php endif;

layout_akhir();
