<?php
/**
 * prodi.php — CRUD tabel `program_studi`.
 * Admin: kelola. Rektorat/Dekanat/Operator prodi: lihat sesuai cakupan.
 */
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/layout.php';

$user = wajib_login($koneksi);
wajib_izin($user, 'prodi');
$kelola = izin($user, 'prodi', 'kelola');
$file = 'prodi.php';

$aksi = (string) ($_GET['aksi'] ?? '');
$data = ['id_program_studi' => 0, 'id_fakultas' => '', 'kode_program_studi' => '', 'nama_program_studi' => '', 'jenjang' => 'S1', 'status_aktif' => 'Aktif'];
$err  = [];
$fakList = array_map(
    static fn($r) => ['v' => $r['id_fakultas'], 'l' => $r['kode_fakultas'] . ' - ' . $r['nama_fakultas'] . ' (' . $r['kode_universitas'] . ')'],
    db_rows($koneksi, 'SELECT f.id_fakultas, f.kode_fakultas, f.nama_fakultas, u.kode_universitas
                         FROM fakultas f JOIN universitas u ON u.id_universitas = f.id_universitas ORDER BY u.kode_universitas, f.nama_fakultas')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    wajib_izin($user, 'prodi', 'kelola');
    $id = post_int('id');

    if (($_POST['op'] ?? '') === 'hapus') {
        $lama = db_row($koneksi, 'SELECT * FROM program_studi WHERE id_program_studi = ?', [$id]);
        if ($lama) {
            try {
                db_jalankan($koneksi, 'DELETE FROM program_studi WHERE id_program_studi = ?', [$id]);
                audit($koneksi, (int) $user['id_pengguna'], 'DELETE', 'program_studi', (string) $id, $lama);
                flash('ok', 'Program studi "' . $lama['nama_program_studi'] . '" dihapus.');
            } catch (mysqli_sql_exception $ex) {
                flash('error', pesan_db($ex));
            }
        }
        pindah($file);
    }

    $data = [
        'id_program_studi'   => $id,
        'id_fakultas'        => post_int('id_fakultas'),
        'kode_program_studi' => strtoupper(post_teks('kode_program_studi', 20)),
        'nama_program_studi' => post_teks('nama_program_studi', 200),
        'jenjang'            => post_teks('jenjang', 20),
        'status_aktif'       => post_teks('status_aktif', 20),
    ];
    if (!db_val($koneksi, 'SELECT 1 FROM fakultas WHERE id_fakultas = ?', [$data['id_fakultas']])) $err['id_fakultas'] = 'Pilih fakultas.';
    if (!preg_match('/^[A-Z0-9._-]{1,20}$/', $data['kode_program_studi'])) $err['kode_program_studi'] = 'Isi 1–20 karakter: huruf, angka, titik, strip, atau garis bawah.';
    if ($data['nama_program_studi'] === '') $err['nama_program_studi'] = 'Nama program studi wajib diisi.';
    if (!in_array($data['jenjang'], JENJANG, true)) $err['jenjang'] = 'Pilih jenjang.';
    if (!in_array($data['status_aktif'], ['Aktif', 'Tidak Aktif'], true)) $err['status_aktif'] = 'Pilih status.';

    if (!$err) {
        $nilai = [$data['id_fakultas'], $data['kode_program_studi'], $data['nama_program_studi'], $data['jenjang'], $data['status_aktif']];
        try {
            if ($id) {
                $lama = db_row($koneksi, 'SELECT * FROM program_studi WHERE id_program_studi = ?', [$id]) ?? tidak_ditemukan($file, 'Program studi');
                db_jalankan($koneksi, 'UPDATE program_studi SET id_fakultas = ?, kode_program_studi = ?, nama_program_studi = ?, jenjang = ?, status_aktif = ?
                                       WHERE id_program_studi = ?', array_merge($nilai, [$id]));
                audit($koneksi, (int) $user['id_pengguna'], 'UPDATE', 'program_studi', (string) $id, $lama, $data);
                flash('ok', 'Data program studi diperbarui.');
            } else {
                db_jalankan($koneksi, 'INSERT INTO program_studi (id_fakultas, kode_program_studi, nama_program_studi, jenjang, status_aktif) VALUES (?, ?, ?, ?, ?)', $nilai);
                $baru = (int) $koneksi->insert_id;
                audit($koneksi, (int) $user['id_pengguna'], 'INSERT', 'program_studi', (string) $baru, null, $data);
                flash('ok', 'Program studi "' . $data['nama_program_studi'] . '" ditambahkan.');
            }
            pindah($file);
        } catch (mysqli_sql_exception $ex) {
            $err['_'] = pesan_db($ex, 'Kode program studi sudah dipakai di fakultas ini.');
            if ($ex->getCode() === 1062) $err['kode_program_studi'] = 'Kode ini sudah dipakai di fakultas yang sama.';
        }
    }
    $aksi = $id ? 'edit' : 'tambah';
} elseif ($aksi === 'edit') {
    $data = db_row($koneksi, 'SELECT * FROM program_studi WHERE id_program_studi = ?', [(int) ($_GET['id'] ?? 0)]) ?? tidak_ditemukan($file, 'Program studi');
}

layout_awal($user, 'Program studi', 'prodi', ['lebar' => true]);

if ($kelola && in_array($aksi, ['tambah', 'edit'], true)): ?>
  <?= kepala_halaman('Master data', $aksi === 'edit' ? 'Ubah program studi' : 'Tambah program studi', 'Kolom bertanda * wajib diisi.') ?>
  <form class="card form" method="post" action="<?= e($file) ?>" novalidate>
    <?= csrf_field() ?><?= ringkasan_error($err) ?>
    <input type="hidden" name="op" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $data['id_program_studi'] ?>">
    <div class="form-grid">
      <?= f_select('id_fakultas', 'Fakultas', $fakList, $data['id_fakultas'], $err, ['kosong' => '— Pilih fakultas —', 'attr' => ['required' => true], 'lebar' => true]) ?>
      <?= f_input('kode_program_studi', 'Kode program studi', $data['kode_program_studi'], $err, ['attr' => ['required' => true, 'maxlength' => 20], 'bantuan' => 'Contoh: TI']) ?>
      <?= f_input('nama_program_studi', 'Nama program studi', $data['nama_program_studi'], $err, ['attr' => ['required' => true, 'maxlength' => 200]]) ?>
      <?= f_select('jenjang', 'Jenjang', pilihan_sederhana(JENJANG), $data['jenjang'], $err, ['attr' => ['required' => true]]) ?>
      <?= f_select('status_aktif', 'Status', pilihan_sederhana(['Aktif', 'Tidak Aktif']), $data['status_aktif'], $err, ['attr' => ['required' => true]]) ?>
    </div>
    <div class="form-act">
      <button class="btn-primary" type="submit">Simpan</button>
      <a class="btn-line" href="<?= e($file) ?>">Batal</a>
    </div>
  </form>
<?php else:
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    [$w, $params] = cakupan_sql($user['cakupan'], 'f.id_universitas', 'ps.id_fakultas', 'ps.id_program_studi');
    if ($q !== '') { $w .= ' AND (ps.kode_program_studi LIKE ? OR ps.nama_program_studi LIKE ?)'; $l = '%' . like_escape($q) . '%'; $params[] = $l; $params[] = $l; }
    $total = (int) db_val($koneksi, "SELECT COUNT(*) FROM program_studi ps JOIN fakultas f ON f.id_fakultas = ps.id_fakultas WHERE $w", $params);
    [$hal, $jmlHal, $offset] = hitung_halaman($total, (int) ($_GET['hal'] ?? 1));
    $rows = db_rows($koneksi, "SELECT ps.*, f.kode_fakultas, f.nama_fakultas,
                                      (SELECT COUNT(*) FROM mahasiswa m WHERE m.id_program_studi = ps.id_program_studi) AS jml_mhs,
                                      (SELECT COUNT(*) FROM mahasiswa m WHERE m.id_program_studi = ps.id_program_studi AND m.status_mahasiswa = 'Aktif') AS jml_aktif
                                 FROM program_studi ps JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
                                WHERE $w ORDER BY f.nama_fakultas, ps.nama_program_studi LIMIT " . PER_HALAMAN . " OFFSET $offset", $params);
?>
  <?= kepala_halaman('Master data', 'Program studi', 'Program studi per fakultas beserta jumlah mahasiswanya.',
        $kelola ? '<a class="btn-primary" href="prodi.php?aksi=tambah">' . ikon('plus') . 'Tambah program studi</a>' : '') ?>
  <section class="card">
    <form class="toolbar" method="get" action="<?= e($file) ?>" role="search">
      <label class="sr-only" for="q">Cari program studi</label>
      <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari kode atau nama program studi…">
      <button class="btn-dark" type="submit">Cari</button>
      <?php if ($q !== ''): ?><a class="btn-line" href="<?= e($file) ?>">Reset</a><?php endif; ?>
      <span class="toolbar-info"><?= angka($total) ?> data</span>
    </form>
    <?php if (!$rows): ?>
      <p class="empty-note">Belum ada data program studi<?= $q !== '' ? ' yang cocok' : '' ?>.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th scope="col">Kode</th><th scope="col">Program studi</th><th scope="col">Jenjang</th><th scope="col">Fakultas</th><th scope="col" class="num">Mahasiswa</th><th scope="col">Status</th><th scope="col" class="act">Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><span class="code"><?= e($r['kode_program_studi']) ?></span></td>
                <td><span class="row-name"><?= e($r['nama_program_studi']) ?></span></td>
                <td><?= e($r['jenjang']) ?></td>
                <td><?= e($r['kode_fakultas'] . ' - ' . $r['nama_fakultas']) ?></td>
                <td class="num"><?= angka($r['jml_mhs']) ?> <span class="row-sub">(<?= angka($r['jml_aktif']) ?> aktif)</span></td>
                <td><?= chip_status($r['status_aktif']) ?></td>
                <td class="act">
                  <a class="icon-btn" href="<?= e(url_ke('mahasiswa.php', ['prodi' => (int) $r['id_program_studi']])) ?>" title="Lihat mahasiswa" aria-label="Lihat mahasiswa <?= e($r['nama_program_studi']) ?>"><?= ikon('users') ?></a>
                  <?php if ($kelola): ?><?= tombol_edit($file, (int) $r['id_program_studi'], $r['nama_program_studi']) ?><?= tombol_hapus($file, (int) $r['id_program_studi'], $r['nama_program_studi']) ?><?php endif; ?>
                </td>
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
