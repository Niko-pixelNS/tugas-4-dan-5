<?php
/**
 * role.php — CRUD tabel `roles` (khusus Admin).
 * Kode role sistem (ADMIN, OPERATOR_PRODI, DEKANAT, REKTORAT, MAHASISWA) dipakai logika hak akses,
 * jadi kodenya dikunci dan role-nya tidak bisa dihapus. Nama & keterangannya tetap bisa diubah.
 */
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/layout.php';

$user = wajib_login($koneksi);
wajib_izin($user, 'role');
$kelola = izin($user, 'role', 'kelola');
$file = 'role.php';

$aksi = (string) ($_GET['aksi'] ?? '');
$data = ['id_role' => 0, 'kode_role' => '', 'nama_role' => '', 'keterangan' => ''];
$err  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    wajib_izin($user, 'role', 'kelola');
    $id = post_int('id');
    $lama = $id ? db_row($koneksi, 'SELECT * FROM roles WHERE id_role = ?', [$id]) : null;

    if (($_POST['op'] ?? '') === 'hapus') {
        if ($lama && in_array($lama['kode_role'], ROLE_SISTEM, true)) {
            flash('error', 'Role sistem "' . $lama['nama_role'] . '" tidak bisa dihapus.');
        } elseif ($lama) {
            try {
                db_jalankan($koneksi, 'DELETE FROM roles WHERE id_role = ?', [$id]);
                audit($koneksi, (int) $user['id_pengguna'], 'DELETE', 'roles', (string) $id, $lama);
                flash('ok', 'Role "' . $lama['nama_role'] . '" dihapus.');
            } catch (mysqli_sql_exception $ex) {
                flash('error', $ex->getCode() === 1451 ? 'Role ini masih dipakai pengguna. Pindahkan pengguna ke role lain dulu.' : pesan_db($ex));
            }
        }
        pindah($file);
    }

    if ($id && !$lama) tidak_ditemukan($file, 'Role');
    $sistem = $lama && in_array($lama['kode_role'], ROLE_SISTEM, true);
    $data = [
        'id_role'    => $id,
        'kode_role'  => $sistem ? $lama['kode_role'] : strtoupper(post_teks('kode_role', 30)),
        'nama_role'  => post_teks('nama_role', 100),
        'keterangan' => post_teks('keterangan', 255),
    ];
    if (!preg_match('/^[A-Z0-9_]{2,30}$/', $data['kode_role'])) $err['kode_role'] = 'Isi 2–30 karakter: huruf besar, angka, atau garis bawah.';
    if ($data['nama_role'] === '') $err['nama_role'] = 'Nama role wajib diisi.';

    if (!$err) {
        $ket = $data['keterangan'] === '' ? null : $data['keterangan'];
        try {
            if ($id) {
                db_jalankan($koneksi, 'UPDATE roles SET kode_role = ?, nama_role = ?, keterangan = ? WHERE id_role = ?', [$data['kode_role'], $data['nama_role'], $ket, $id]);
                audit($koneksi, (int) $user['id_pengguna'], 'UPDATE', 'roles', (string) $id, $lama, $data);
                flash('ok', 'Data role diperbarui.');
            } else {
                db_jalankan($koneksi, 'INSERT INTO roles (kode_role, nama_role, keterangan) VALUES (?, ?, ?)', [$data['kode_role'], $data['nama_role'], $ket]);
                $baru = (int) $koneksi->insert_id;
                audit($koneksi, (int) $user['id_pengguna'], 'INSERT', 'roles', (string) $baru, null, $data);
                flash('ok', 'Role "' . $data['nama_role'] . '" ditambahkan.');
            }
            pindah($file);
        } catch (mysqli_sql_exception $ex) {
            $err['_'] = pesan_db($ex, 'Kode role sudah dipakai.');
            if ($ex->getCode() === 1062) $err['kode_role'] = 'Kode ini sudah dipakai role lain.';
        }
    }
    $aksi = $id ? 'edit' : 'tambah';
} elseif ($aksi === 'edit') {
    $data = db_row($koneksi, 'SELECT * FROM roles WHERE id_role = ?', [(int) ($_GET['id'] ?? 0)]) ?? tidak_ditemukan($file, 'Role');
}

layout_awal($user, 'Role', 'role', ['lebar' => true]);

if ($kelola && in_array($aksi, ['tambah', 'edit'], true)):
    $sistem = in_array($data['kode_role'], ROLE_SISTEM, true) && $data['id_role'];
?>
  <?= kepala_halaman('Master data', $aksi === 'edit' ? 'Ubah role' : 'Tambah role', 'Kolom bertanda * wajib diisi.') ?>
  <form class="card form" method="post" action="<?= e($file) ?>" novalidate>
    <?= csrf_field() ?><?= ringkasan_error($err) ?>
    <input type="hidden" name="op" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $data['id_role'] ?>">
    <div class="form-grid">
      <?= f_input('kode_role', 'Kode role', $data['kode_role'], $err, ['attr' => ['required' => true, 'maxlength' => 30, 'readonly' => $sistem],
            'bantuan' => $sistem ? 'Role sistem: kodenya dipakai aturan hak akses, jadi tidak bisa diubah.' : 'Huruf besar, angka, garis bawah. Contoh: KAPRODI']) ?>
      <?= f_input('nama_role', 'Nama role', $data['nama_role'], $err, ['attr' => ['required' => true, 'maxlength' => 100]]) ?>
      <?= f_textarea('keterangan', 'Keterangan', $data['keterangan'], $err, ['attr' => ['maxlength' => 255], 'bantuan' => 'Tampil di bagian "Peran pengguna" pada beranda situs.']) ?>
    </div>
    <?php if (!$sistem): ?>
      <p class="note"><?= ikon('info') ?>Role baru belum punya hak akses ke data. Hak akses diatur di <code>includes/app.php</code> (konstanta <code>HAK_AKSES</code>).</p>
    <?php endif; ?>
    <div class="form-act">
      <button class="btn-primary" type="submit">Simpan</button>
      <a class="btn-line" href="<?= e($file) ?>">Batal</a>
    </div>
  </form>
<?php else:
    $rows = db_rows($koneksi, 'SELECT r.*, (SELECT COUNT(*) FROM pengguna p WHERE p.id_role = r.id_role) AS jml_pengguna FROM roles r ORDER BY r.id_role');
?>
  <?= kepala_halaman('Master data', 'Role', 'Peran pengguna dan keterangannya. Role sistem ditandai dan tidak bisa dihapus.',
        $kelola ? '<a class="btn-primary" href="role.php?aksi=tambah">' . ikon('plus') . 'Tambah role</a>' : '') ?>
  <section class="card">
    <div class="table-wrap">
      <table class="tbl">
        <thead><tr><th scope="col">Kode</th><th scope="col">Nama role</th><th scope="col">Keterangan</th><th scope="col" class="num">Pengguna</th><th scope="col">Hak akses</th><th scope="col" class="act">Aksi</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): $sis = in_array($r['kode_role'], ROLE_SISTEM, true); $hak = HAK_AKSES[$r['kode_role']] ?? []; ?>
            <tr>
              <td><span class="code"><?= e($r['kode_role']) ?></span><?php if ($sis): ?> <span class="tag">Sistem</span><?php endif; ?></td>
              <td><span class="row-name"><?= e($r['nama_role']) ?></span></td>
              <td><span class="row-sub"><?= e($r['keterangan'] ?: '—') ?></span></td>
              <td class="num"><?= angka($r['jml_pengguna']) ?></td>
              <td><span class="row-sub"><?php
                if ($r['kode_role'] === 'MAHASISWA') echo 'Profil sendiri';
                elseif (!$hak) echo 'Belum ada akses data';
                else echo e(implode(', ', array_map(static fn($m, $h) => $m . ($h === 'kelola' ? ' (kelola)' : ''), array_keys($hak), $hak)));
              ?></span></td>
              <td class="act">
                <?= tombol_edit($file, (int) $r['id_role'], $r['nama_role']) ?>
                <?php if (!$sis): ?><?= tombol_hapus($file, (int) $r['id_role'], $r['nama_role']) ?><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif;

layout_akhir();
