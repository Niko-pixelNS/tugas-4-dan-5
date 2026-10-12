<?php
/**
 * pengguna.php — CRUD tabel `pengguna` (khusus Admin).
 * - Kata sandi disimpan sebagai hash (password_hash), tidak pernah ditampilkan.
 * - Email dipakai untuk login, jadi harus unik.
 * - Universitas/fakultas/prodi diisi sesuai peran (Dekanat → fakultas, Operator prodi → prodi, dst.).
 * - Admin tidak bisa menghapus/menonaktifkan dirinya sendiri, dan admin aktif terakhir tidak bisa dihilangkan.
 */
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/layout.php';

$user = wajib_login($koneksi);
wajib_izin($user, 'pengguna');
$kelola = izin($user, 'pengguna', 'kelola');
$file = 'pengguna.php';
$saya = (int) $user['id_pengguna'];

$aksi = (string) ($_GET['aksi'] ?? '');
$data = ['id_pengguna' => 0, 'id_role' => '', 'id_universitas' => '', 'id_fakultas' => '', 'id_program_studi' => '',
         'username' => '', 'nama_lengkap' => '', 'email' => '', 'no_hp' => '', 'status_aktif' => 'Aktif'];
$err  = [];

$roles = db_rows($koneksi, 'SELECT id_role, kode_role, nama_role FROM roles ORDER BY id_role');
$kodeRole = array_column($roles, 'kode_role', 'id_role');
$univOpt = array_map(static fn($r) => ['v' => $r['id_universitas'], 'l' => $r['kode_universitas'] . ' - ' . $r['nama_universitas']],
    db_rows($koneksi, 'SELECT id_universitas, kode_universitas, nama_universitas FROM universitas ORDER BY nama_universitas'));
$fakOpt = array_map(static fn($r) => ['v' => $r['id_fakultas'], 'l' => $r['kode_fakultas'] . ' - ' . $r['nama_fakultas'], 'induk' => $r['id_universitas']],
    db_rows($koneksi, 'SELECT id_fakultas, id_universitas, kode_fakultas, nama_fakultas FROM fakultas ORDER BY nama_fakultas'));
$prodiOpt = array_map(static fn($r) => ['v' => $r['id_program_studi'], 'l' => $r['kode_program_studi'] . ' - ' . $r['nama_program_studi'], 'induk' => $r['id_fakultas']],
    db_rows($koneksi, 'SELECT id_program_studi, id_fakultas, kode_program_studi, nama_program_studi FROM program_studi ORDER BY nama_program_studi'));

/** Jumlah admin aktif selain $kecuali. */
function admin_aktif_lain(mysqli $db, int $kecuali): int
{
    return (int) db_val($db, "SELECT COUNT(*) FROM pengguna p JOIN roles r ON r.id_role = p.id_role
                               WHERE r.kode_role = 'ADMIN' AND p.status_aktif = 'Aktif' AND p.id_pengguna <> ?", [$kecuali]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    wajib_izin($user, 'pengguna', 'kelola');
    $id = post_int('id');
    $lama = $id ? db_row($koneksi, 'SELECT p.*, r.kode_role FROM pengguna p JOIN roles r ON r.id_role = p.id_role WHERE p.id_pengguna = ?', [$id]) : null;
    if ($id && !$lama) tidak_ditemukan($file, 'Pengguna');

    if (($_POST['op'] ?? '') === 'hapus') {
        if ($id === $saya) {
            flash('error', 'Kamu tidak bisa menghapus akunmu sendiri.');
        } elseif ($lama['kode_role'] === 'ADMIN' && $lama['status_aktif'] === 'Aktif' && admin_aktif_lain($koneksi, $id) === 0) {
            flash('error', 'Ini admin aktif terakhir, jadi tidak bisa dihapus.');
        } else {
            try {
                db_jalankan($koneksi, 'DELETE FROM pengguna WHERE id_pengguna = ?', [$id]);
                audit($koneksi, $saya, 'DELETE', 'pengguna', (string) $id, $lama);
                flash('ok', 'Pengguna "' . $lama['nama_lengkap'] . '" dihapus.');
            } catch (mysqli_sql_exception $ex) {
                flash('error', pesan_db($ex));
            }
        }
        pindah($file);
    }

    $data = [
        'id_pengguna'      => $id,
        'id_role'          => post_int('id_role'),
        'id_universitas'   => post_int('id_universitas'),
        'id_fakultas'      => post_int('id_fakultas'),
        'id_program_studi' => post_int('id_program_studi'),
        'username'         => post_teks('username', 100),
        'nama_lengkap'     => post_teks('nama_lengkap', 200),
        'email'            => strtolower(post_teks('email', 150)),
        'no_hp'            => post_teks('no_hp', 30),
        'status_aktif'     => post_teks('status_aktif', 20),
    ];
    $sandi  = (string) ($_POST['password'] ?? '');
    $sandi2 = (string) ($_POST['password2'] ?? '');
    $kode = $kodeRole[$data['id_role']] ?? null;

    if (!$kode) $err['id_role'] = 'Pilih role.';
    if (!preg_match('/^[A-Za-z0-9._-]{3,100}$/', $data['username'])) $err['username'] = 'Isi 3–100 karakter: huruf, angka, titik, strip, garis bawah.';
    if ($data['nama_lengkap'] === '') $err['nama_lengkap'] = 'Nama lengkap wajib diisi.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $err['email'] = 'Email wajib diisi dengan format yang benar (dipakai untuk login).';
    } elseif (db_val($koneksi, 'SELECT COUNT(*) FROM pengguna WHERE email = ? AND id_pengguna <> ?', [$data['email'], $id])) {
        $err['email'] = 'Email ini sudah dipakai akun lain.';
    }
    if ($data['no_hp'] !== '' && !preg_match('/^[0-9 +().-]{6,30}$/', $data['no_hp'])) $err['no_hp'] = 'Hanya angka, spasi, +, -, ( ).';
    if (!in_array($data['status_aktif'], ['Aktif', 'Tidak Aktif'], true)) $err['status_aktif'] = 'Pilih status.';
    if (!$id || $sandi !== '' || $sandi2 !== '') {
        if (strlen($sandi) < 8) $err['password'] = 'Kata sandi minimal 8 karakter.';
        elseif ($sandi !== $sandi2) $err['password2'] = 'Ulangi kata sandi yang sama.';
    }

    // Lengkapi universitas/fakultas dari pilihan paling spesifik.
    if ($data['id_program_studi']) {
        $h = db_row($koneksi, 'SELECT ps.id_fakultas, f.id_universitas FROM program_studi ps JOIN fakultas f ON f.id_fakultas = ps.id_fakultas WHERE ps.id_program_studi = ?', [$data['id_program_studi']]);
        if ($h) { $data['id_fakultas'] = (int) $h['id_fakultas']; $data['id_universitas'] = (int) $h['id_universitas']; }
        else $err['id_program_studi'] = 'Program studi tidak ditemukan.';
    } elseif ($data['id_fakultas']) {
        $u = db_val($koneksi, 'SELECT id_universitas FROM fakultas WHERE id_fakultas = ?', [$data['id_fakultas']]);
        if ($u) $data['id_universitas'] = (int) $u; else $err['id_fakultas'] = 'Fakultas tidak ditemukan.';
    } elseif ($data['id_universitas'] && !db_val($koneksi, 'SELECT 1 FROM universitas WHERE id_universitas = ?', [$data['id_universitas']])) {
        $err['id_universitas'] = 'Universitas tidak ditemukan.';
    }
    if ($kode === 'REKTORAT' && !$data['id_universitas']) $err['id_universitas'] = 'Rektorat wajib punya universitas.';
    if ($kode === 'DEKANAT' && !$data['id_fakultas']) $err['id_fakultas'] = 'Dekanat wajib punya fakultas.';
    if ($kode === 'OPERATOR_PRODI' && !$data['id_program_studi']) $err['id_program_studi'] = 'Operator prodi wajib punya program studi.';

    // Jangan sampai admin mengunci dirinya sendiri / sistem kehilangan admin.
    if ($id === $saya) {
        if ($data['status_aktif'] !== 'Aktif') $err['status_aktif'] = 'Kamu tidak bisa menonaktifkan akunmu sendiri.';
        if ($kode !== $lama['kode_role']) $err['id_role'] = 'Kamu tidak bisa mengganti role akunmu sendiri.';
    } elseif ($lama && $lama['kode_role'] === 'ADMIN' && $lama['status_aktif'] === 'Aktif'
              && ($kode !== 'ADMIN' || $data['status_aktif'] !== 'Aktif') && admin_aktif_lain($koneksi, $id) === 0) {
        $err['id_role'] = 'Ini admin aktif terakhir. Tambahkan admin lain dulu.';
    }

    if (!$err) {
        $nilai = [$data['id_role'], $data['id_universitas'] ?: null, $data['id_fakultas'] ?: null, $data['id_program_studi'] ?: null,
                  $data['username'], $data['nama_lengkap'], $data['email'], $data['no_hp'] === '' ? null : $data['no_hp'], $data['status_aktif']];
        try {
            if ($id) {
                $sql = 'UPDATE pengguna SET id_role=?, id_universitas=?, id_fakultas=?, id_program_studi=?, username=?, nama_lengkap=?, email=?, no_hp=?, status_aktif=?';
                if ($sandi !== '') { $sql .= ', password_hash=?'; $nilai[] = password_hash($sandi, PASSWORD_DEFAULT); }
                db_jalankan($koneksi, $sql . ' WHERE id_pengguna = ?', array_merge($nilai, [$id]));
                audit($koneksi, $saya, 'UPDATE', 'pengguna', (string) $id, $lama, $data + ['password_diubah' => $sandi !== '']);
                flash('ok', 'Data pengguna diperbarui' . ($sandi !== '' ? ', termasuk kata sandi.' : '.'));
            } else {
                $nilai[] = password_hash($sandi, PASSWORD_DEFAULT);
                db_jalankan($koneksi, 'INSERT INTO pengguna (id_role, id_universitas, id_fakultas, id_program_studi, username, nama_lengkap, email, no_hp, status_aktif, password_hash)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $nilai);
                $baru = (int) $koneksi->insert_id;
                audit($koneksi, $saya, 'INSERT', 'pengguna', (string) $baru, null, $data);
                flash('ok', 'Pengguna "' . $data['nama_lengkap'] . '" ditambahkan. Dia bisa login dengan email ' . $data['email'] . '.');
            }
            pindah($file);
        } catch (mysqli_sql_exception $ex) {
            $err['_'] = pesan_db($ex, 'Username atau email sudah dipakai.');
            if ($ex->getCode() === 1062) $err['username'] = 'Username ini sudah dipakai.';
        }
    }
    $aksi = $id ? 'edit' : 'tambah';
} elseif ($aksi === 'edit') {
    $data = db_row($koneksi, 'SELECT * FROM pengguna WHERE id_pengguna = ?', [(int) ($_GET['id'] ?? 0)]) ?? tidak_ditemukan($file, 'Pengguna');
}

layout_awal($user, 'Pengguna', 'pengguna', ['lebar' => true]);

if ($kelola && in_array($aksi, ['tambah', 'edit'], true)):
    $edit = $aksi === 'edit';
?>
  <?= kepala_halaman('Master data', $edit ? 'Ubah pengguna' : 'Tambah pengguna', 'Kolom bertanda * wajib diisi.') ?>
  <form class="card form" method="post" action="<?= e($file) ?>" novalidate>
    <?= csrf_field() ?><?= ringkasan_error($err) ?>
    <input type="hidden" name="op" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $data['id_pengguna'] ?>">
    <h2 class="form-h2">Akun</h2>
    <div class="form-grid">
      <?= f_input('nama_lengkap', 'Nama lengkap', $data['nama_lengkap'], $err, ['attr' => ['required' => true, 'maxlength' => 200]]) ?>
      <?= f_input('username', 'Username', $data['username'], $err, ['attr' => ['required' => true, 'maxlength' => 100, 'autocomplete' => 'off']]) ?>
      <?= f_input('email', 'Email', $data['email'], $err, ['type' => 'email', 'attr' => ['required' => true, 'maxlength' => 150, 'autocomplete' => 'off'], 'bantuan' => 'Dipakai untuk login.']) ?>
      <?= f_input('no_hp', 'Nomor HP', $data['no_hp'], $err, ['type' => 'tel', 'attr' => ['maxlength' => 30]]) ?>
      <?= f_input('password', $edit ? 'Kata sandi baru' : 'Kata sandi', '', $err, ['type' => 'password', 'attr' => ['required' => !$edit, 'minlength' => 8, 'autocomplete' => 'new-password'],
            'bantuan' => $edit ? 'Kosongkan bila tidak diubah. Minimal 8 karakter.' : 'Minimal 8 karakter.']) ?>
      <?= f_input('password2', 'Ulangi kata sandi', '', $err, ['type' => 'password', 'attr' => ['required' => !$edit, 'minlength' => 8, 'autocomplete' => 'new-password']]) ?>
    </div>
    <h2 class="form-h2">Peran &amp; cakupan</h2>
    <div class="form-grid">
      <?= f_select('id_role', 'Role', array_map(static fn($r) => ['v' => $r['id_role'], 'l' => $r['nama_role'] . ' (' . $r['kode_role'] . ')'], $roles), $data['id_role'], $err, ['kosong' => '— Pilih role —', 'attr' => ['required' => true]]) ?>
      <?= f_select('status_aktif', 'Status akun', pilihan_sederhana(['Aktif', 'Tidak Aktif']), $data['status_aktif'], $err, ['attr' => ['required' => true]]) ?>
      <?= f_select('id_universitas', 'Universitas', $univOpt, $data['id_universitas'], $err, ['kosong' => '— Tidak ada —', 'bantuan' => 'Wajib untuk Rektorat.']) ?>
      <?= f_select('id_fakultas', 'Fakultas', $fakOpt, $data['id_fakultas'], $err, ['kosong' => '— Tidak ada —', 'induk' => 'id_universitas', 'bantuan' => 'Wajib untuk Dekanat.']) ?>
      <?= f_select('id_program_studi', 'Program studi', $prodiOpt, $data['id_program_studi'], $err, ['kosong' => '— Tidak ada —', 'induk' => 'id_fakultas', 'bantuan' => 'Wajib untuk Operator prodi. Fakultas & universitas ikut terisi otomatis.']) ?>
    </div>
    <div class="form-act">
      <button class="btn-primary" type="submit">Simpan</button>
      <a class="btn-line" href="<?= e($file) ?>">Batal</a>
    </div>
  </form>
<?php else:
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $fRole = (int) ($_GET['role'] ?? 0);
    $fStat = in_array($_GET['status'] ?? '', ['Aktif', 'Tidak Aktif'], true) ? (string) $_GET['status'] : '';
    $w = '1 = 1'; $params = [];
    if ($q !== '') { $w .= ' AND (p.username LIKE ? OR p.nama_lengkap LIKE ? OR p.email LIKE ?)'; $l = '%' . like_escape($q) . '%'; array_push($params, $l, $l, $l); }
    if ($fRole) { $w .= ' AND p.id_role = ?'; $params[] = $fRole; }
    if ($fStat !== '') { $w .= ' AND p.status_aktif = ?'; $params[] = $fStat; }
    $total = (int) db_val($koneksi, "SELECT COUNT(*) FROM pengguna p WHERE $w", $params);
    [$hal, $jmlHal, $offset] = hitung_halaman($total, (int) ($_GET['hal'] ?? 1));
    $rows = db_rows($koneksi, "SELECT p.id_pengguna, p.username, p.nama_lengkap, p.email, p.status_aktif, p.last_login, r.kode_role, r.nama_role,
                                      COALESCE(CONCAT(ps.kode_program_studi, ' - ', ps.nama_program_studi), CONCAT(f.kode_fakultas, ' - ', f.nama_fakultas), u.kode_universitas) AS cakupan
                                 FROM pengguna p
                                 JOIN roles r ON r.id_role = p.id_role
                                 LEFT JOIN universitas u ON u.id_universitas = p.id_universitas
                                 LEFT JOIN fakultas f ON f.id_fakultas = p.id_fakultas
                                 LEFT JOIN program_studi ps ON ps.id_program_studi = p.id_program_studi
                                WHERE $w ORDER BY r.id_role, p.nama_lengkap LIMIT " . PER_HALAMAN . " OFFSET $offset", $params);
?>
  <?= kepala_halaman('Master data', 'Pengguna', 'Akun yang bisa login ke sistem beserta peran dan cakupannya.',
        $kelola ? '<a class="btn-primary" href="pengguna.php?aksi=tambah">' . ikon('plus') . 'Tambah pengguna</a>' : '') ?>
  <section class="card">
    <form class="toolbar" method="get" action="<?= e($file) ?>" role="search">
      <label class="sr-only" for="q">Cari pengguna</label>
      <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari nama, username, atau email…">
      <label class="sr-only" for="f-role">Role</label>
      <select id="f-role" name="role"><option value="">Semua role</option><?php foreach ($roles as $r): ?><option value="<?= (int) $r['id_role'] ?>"<?= $fRole === (int) $r['id_role'] ? ' selected' : '' ?>><?= e($r['nama_role']) ?></option><?php endforeach; ?></select>
      <label class="sr-only" for="f-stat">Status</label>
      <select id="f-stat" name="status"><option value="">Semua status</option><?php foreach (['Aktif', 'Tidak Aktif'] as $s): ?><option<?= $fStat === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
      <button class="btn-dark" type="submit">Cari</button>
      <?php if ($q !== '' || $fRole || $fStat !== ''): ?><a class="btn-line" href="<?= e($file) ?>">Reset</a><?php endif; ?>
      <span class="toolbar-info"><?= angka($total) ?> data</span>
    </form>
    <?php if (!$rows): ?>
      <p class="empty-note">Tidak ada pengguna yang cocok.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th scope="col">Pengguna</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Cakupan</th><th scope="col">Status</th><th scope="col">Login terakhir</th><th scope="col" class="act">Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><span class="cell-person"><?= avatar($r['nama_lengkap']) ?><span><span class="row-name"><?= e($r['nama_lengkap']) ?><?= (int) $r['id_pengguna'] === $saya ? ' <span class="tag">Kamu</span>' : '' ?></span><span class="row-sub">@<?= e($r['username']) ?></span></span></span></td>
                <td><?= e($r['email'] ?: '—') ?></td>
                <td><?= e($r['nama_role']) ?></td>
                <td><span class="row-sub"><?= e($r['cakupan'] ?: '—') ?></span></td>
                <td><?= chip_status($r['status_aktif']) ?></td>
                <td class="nowrap"><span class="row-sub"><?= e(tanggal_id($r['last_login'], true)) ?></span></td>
                <td class="act"><?= tombol_edit($file, (int) $r['id_pengguna'], $r['nama_lengkap']) ?><?php if ((int) $r['id_pengguna'] !== $saya): ?><?= tombol_hapus($file, (int) $r['id_pengguna'], $r['nama_lengkap']) ?><?php endif; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= paginasi($file, $hal, $jmlHal, ['q' => $q, 'role' => $fRole, 'status' => $fStat]) ?>
    <?php endif; ?>
  </section>
<?php endif;

layout_akhir();
