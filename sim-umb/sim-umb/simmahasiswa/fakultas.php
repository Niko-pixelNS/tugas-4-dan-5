<?php
/**
 * fakultas.php — CRUD tabel `fakultas`.
 * Admin: kelola. Rektorat/Dekanat: lihat sesuai cakupan.
 */
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/layout.php';

$user = wajib_login($koneksi);
wajib_izin($user, 'fakultas');
$kelola = izin($user, 'fakultas', 'kelola');
$file = 'fakultas.php';

$aksi = (string) ($_GET['aksi'] ?? '');
$data = ['id_fakultas' => 0, 'id_universitas' => '', 'kode_fakultas' => '', 'nama_fakultas' => ''];
$err  = [];
$univList = array_map(
    static fn($r) => ['v' => $r['id_universitas'], 'l' => $r['kode_universitas'] . ' - ' . $r['nama_universitas']],
    db_rows($koneksi, 'SELECT id_universitas, kode_universitas, nama_universitas FROM universitas ORDER BY nama_universitas')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    wajib_izin($user, 'fakultas', 'kelola');
    $id = post_int('id');

    if (($_POST['op'] ?? '') === 'hapus') {
        $lama = db_row($koneksi, 'SELECT * FROM fakultas WHERE id_fakultas = ?', [$id]);
        if ($lama) {
            try {
                db_jalankan($koneksi, 'DELETE FROM fakultas WHERE id_fakultas = ?', [$id]);
                audit($koneksi, (int) $user['id_pengguna'], 'DELETE', 'fakultas', (string) $id, $lama);
                flash('ok', 'Fakultas "' . $lama['nama_fakultas'] . '" dihapus.');
            } catch (mysqli_sql_exception $ex) {
                flash('error', pesan_db($ex));
            }
        }
        pindah($file);
    }

    $data = [
        'id_fakultas'    => $id,
        'id_universitas' => post_int('id_universitas'),
        'kode_fakultas'  => strtoupper(post_teks('kode_fakultas', 20)),
        'nama_fakultas'  => post_teks('nama_fakultas', 200),
    ];
    if (!db_val($koneksi, 'SELECT 1 FROM universitas WHERE id_universitas = ?', [$data['id_universitas']])) $err['id_universitas'] = 'Pilih universitas.';
    if (!preg_match('/^[A-Z0-9._-]{1,20}$/', $data['kode_fakultas'])) $err['kode_fakultas'] = 'Isi 1–20 karakter: huruf, angka, titik, strip, atau garis bawah.';
    if ($data['nama_fakultas'] === '') $err['nama_fakultas'] = 'Nama fakultas wajib diisi.';

    if (!$err) {
        try {
            if ($id) {
                $lama = db_row($koneksi, 'SELECT * FROM fakultas WHERE id_fakultas = ?', [$id]) ?? tidak_ditemukan($file, 'Fakultas');
                db_jalankan($koneksi, 'UPDATE fakultas SET id_universitas = ?, kode_fakultas = ?, nama_fakultas = ? WHERE id_fakultas = ?',
                    [$data['id_universitas'], $data['kode_fakultas'], $data['nama_fakultas'], $id]);
                audit($koneksi, (int) $user['id_pengguna'], 'UPDATE', 'fakultas', (string) $id, $lama, $data);
                flash('ok', 'Data fakultas diperbarui.');
            } else {
                db_jalankan($koneksi, 'INSERT INTO fakultas (id_universitas, kode_fakultas, nama_fakultas) VALUES (?, ?, ?)',
                    [$data['id_universitas'], $data['kode_fakultas'], $data['nama_fakultas']]);
                $baru = (int) $koneksi->insert_id;
                audit($koneksi, (int) $user['id_pengguna'], 'INSERT', 'fakultas', (string) $baru, null, $data);
                flash('ok', 'Fakultas "' . $data['nama_fakultas'] . '" ditambahkan.');
            }
            pindah($file);
        } catch (mysqli_sql_exception $ex) {
            $err['_'] = pesan_db($ex, 'Kode fakultas sudah dipakai di universitas ini.');
            if ($ex->getCode() === 1062) $err['kode_fakultas'] = 'Kode ini sudah dipakai di universitas yang sama.';
        }
    }
    $aksi = $id ? 'edit' : 'tambah';
} elseif ($aksi === 'edit') {
    $data = db_row($koneksi, 'SELECT * FROM fakultas WHERE id_fakultas = ?', [(int) ($_GET['id'] ?? 0)]) ?? tidak_ditemukan($file, 'Fakultas');
}

layout_awal($user, 'Fakultas', 'fakultas', ['lebar' => true]);

if ($kelola && in_array($aksi, ['tambah', 'edit'], true)): ?>
  <?= kepala_halaman('Master data', $aksi === 'edit' ? 'Ubah fakultas' : 'Tambah fakultas', 'Kolom bertanda * wajib diisi.') ?>
  <form class="card form" method="post" action="<?= e($file) ?>" novalidate>
    <?= csrf_field() ?><?= ringkasan_error($err) ?>
    <input type="hidden" name="op" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $data['id_fakultas'] ?>">
    <div class="form-grid">
      <?= f_select('id_universitas', 'Universitas', $univList, $data['id_universitas'], $err, ['kosong' => '— Pilih universitas —', 'attr' => ['required' => true], 'lebar' => true]) ?>
      <?= f_input('kode_fakultas', 'Kode fakultas', $data['kode_fakultas'], $err, ['attr' => ['required' => true, 'maxlength' => 20], 'bantuan' => 'Contoh: FT']) ?>
      <?= f_input('nama_fakultas', 'Nama fakultas', $data['nama_fakultas'], $err, ['attr' => ['required' => true, 'maxlength' => 200]]) ?>
    </div>
    <div class="form-act">
      <button class="btn-primary" type="submit">Simpan</button>
      <a class="btn-line" href="<?= e($file) ?>">Batal</a>
    </div>
  </form>
<?php else:
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    [$w, $params] = cakupan_sql($user['cakupan'], 'f.id_universitas', 'f.id_fakultas', null);
    if ($q !== '') { $w .= ' AND (f.kode_fakultas LIKE ? OR f.nama_fakultas LIKE ?)'; $l = '%' . like_escape($q) . '%'; $params[] = $l; $params[] = $l; }
    $total = (int) db_val($koneksi, "SELECT COUNT(*) FROM fakultas f WHERE $w", $params);
    [$hal, $jmlHal, $offset] = hitung_halaman($total, (int) ($_GET['hal'] ?? 1));
    $rows = db_rows($koneksi, "SELECT f.*, u.kode_universitas, u.nama_universitas,
                                      (SELECT COUNT(*) FROM program_studi ps WHERE ps.id_fakultas = f.id_fakultas) AS jml_prodi,
                                      (SELECT COUNT(*) FROM mahasiswa m JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi WHERE ps.id_fakultas = f.id_fakultas) AS jml_mhs
                                 FROM fakultas f JOIN universitas u ON u.id_universitas = f.id_universitas
                                WHERE $w ORDER BY u.nama_universitas, f.nama_fakultas LIMIT " . PER_HALAMAN . " OFFSET $offset", $params);
?>
  <?= kepala_halaman('Master data', 'Fakultas', 'Fakultas per universitas beserta jumlah program studi dan mahasiswanya.',
        $kelola ? '<a class="btn-primary" href="fakultas.php?aksi=tambah">' . ikon('plus') . 'Tambah fakultas</a>' : '') ?>
  <section class="card">
    <form class="toolbar" method="get" action="<?= e($file) ?>" role="search">
      <label class="sr-only" for="q">Cari fakultas</label>
      <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari kode atau nama fakultas…">
      <button class="btn-dark" type="submit">Cari</button>
      <?php if ($q !== ''): ?><a class="btn-line" href="<?= e($file) ?>">Reset</a><?php endif; ?>
      <span class="toolbar-info"><?= angka($total) ?> data</span>
    </form>
    <?php if (!$rows): ?>
      <p class="empty-note">Belum ada data fakultas<?= $q !== '' ? ' yang cocok' : '' ?>.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th scope="col">Kode</th><th scope="col">Nama fakultas</th><th scope="col">Universitas</th><th scope="col" class="num">Prodi</th><th scope="col" class="num">Mahasiswa</th><?php if ($kelola): ?><th scope="col" class="act">Aksi</th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><span class="code"><?= e($r['kode_fakultas']) ?></span></td>
                <td><span class="row-name"><?= e($r['nama_fakultas']) ?></span></td>
                <td><?= e($r['kode_universitas'] . ' - ' . $r['nama_universitas']) ?></td>
                <td class="num"><?= angka($r['jml_prodi']) ?></td>
                <td class="num"><?= angka($r['jml_mhs']) ?></td>
                <?php if ($kelola): ?><td class="act"><?= tombol_edit($file, (int) $r['id_fakultas'], $r['nama_fakultas']) ?><?= tombol_hapus($file, (int) $r['id_fakultas'], $r['nama_fakultas']) ?></td><?php endif; ?>
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
