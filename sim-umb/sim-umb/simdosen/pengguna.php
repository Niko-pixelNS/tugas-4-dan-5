<?php
/**
 * pengguna.php — akun login dosen & tabel hak akses (RBAC). Khusus admin.
 * Akun Admin tidak dikelola di sini: hanya ada satu, milik SIM Mahasiswa (login lewat SSO).
 */
require __DIR__ . '/inc/init.php';
$u = wajib_izin('pengguna.kelola');

if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    $id = (int) post('id');
    $pw = (string) ($_POST['password'] ?? '');

    if ($aksi === 'buat') {
        $d = db_row('SELECT * FROM v_dosen WHERE id_dosen = ? AND id_pengguna IS NULL', [(int) post('id_dosen')]);
        if (!$d) flash('error', 'Pilih dosen yang belum punya akun.');
        elseif (strlen($pw) < 8) flash('error', 'Password minimal 8 karakter.');
        else {
            try {
                db()->begin_transaction();
                db_q("INSERT INTO pengguna (id_role, username, password_hash, nama_lengkap, email) VALUES ((SELECT id_role FROM roles WHERE kode_role = 'DOSEN'), ?, ?, ?, ?)",
                    [$d['nomor_registrasi'], password_hash($pw, PASSWORD_DEFAULT), $d['nama_lengkap'], $d['email']]);
                db_exec('UPDATE dosen SET id_pengguna = ? WHERE id_dosen = ?', [db_id(), (int) $d['id_dosen']]);
                db()->commit();
                catat_log('INSERT', 'Akun dosen ' . $d['nomor_registrasi']);
                flash('success', 'Akun dibuat. ' . $d['nama_lengkap'] . ' login dengan username ' . $d['nomor_registrasi'] . '.');
            } catch (mysqli_sql_exception $ex) {
                db()->rollback();
                if ($ex->getCode() !== 1062) throw $ex;
                flash('error', 'Username atau email itu sudah dipakai akun lain.');
            }
        }
    } elseif ($aksi === 'reset') {
        if (strlen($pw) < 8) flash('error', 'Password baru minimal 8 karakter.');
        elseif (db_exec('UPDATE pengguna SET password_hash = ? WHERE id_pengguna = ?', [password_hash($pw, PASSWORD_DEFAULT), $id])) {
            catat_log('UPDATE', "Reset password akun #$id");
            flash('success', 'Password direset. Sampaikan ke dosen dan minta diganti di menu Data Diri.');
        }
    } elseif ($aksi === 'status') {
        db_exec("UPDATE pengguna SET status_aktif = IF(status_aktif = 'Aktif', 'Tidak Aktif', 'Aktif') WHERE id_pengguna = ?", [$id]);
        catat_log('UPDATE', "Ubah status akun #$id");
        flash('success', 'Status akun diubah.');
    } elseif ($aksi === 'hapus') {
        db_exec('DELETE FROM pengguna WHERE id_pengguna = ?', [$id]);
        catat_log('DELETE', "Hapus akun #$id");
        flash('success', 'Akun dihapus. Data dosennya tetap ada.');
    }
    redirect('pengguna.php');
}

$tanpaAkun = [];
foreach (db_rows('SELECT id_dosen, nomor_registrasi, nama_lengkap FROM v_dosen WHERE id_pengguna IS NULL ORDER BY nama_dosen') as $d) {
    $tanpaAkun[$d['id_dosen']] = $d['nomor_registrasi'] . ' — ' . $d['nama_lengkap'];
}
$where = '';
$param = [];
if (get('cari') !== '') {
    $where = ' WHERE p.username LIKE ? OR p.nama_lengkap LIKE ?';
    $param = ['%' . get('cari') . '%', '%' . get('cari') . '%'];
}
$pg = paginasi((int) db_val("SELECT COUNT(*) FROM pengguna p $where", $param), 15);
$rows = db_rows("SELECT p.*, d.id_dosen, d.nomor_registrasi FROM pengguna p LEFT JOIN dosen d ON d.id_pengguna = p.id_pengguna
                  $where ORDER BY p.nama_lengkap LIMIT ? OFFSET ?", array_merge($param, [$pg['per'], $pg['offset']]));
$peran = db_rows('SELECT * FROM roles ORDER BY id_role');

layout_start('Pengguna', 'pengguna', 'Akun login dosen. Akun Admin hanya satu, milik SIM Mahasiswa, dan masuk lewat SSO.');
?>
<section class="card mb">
  <div class="card-head card-pad" style="margin:0"><h2>Peran &amp; Hak Akses (RBAC)</h2></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Peran</th><th>Cara login</th><?php foreach (array_keys(IZIN) as $iz): ?><th class="small"><?= e($iz) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($peran as $r): ?><tr><td><b><?= e($r['nama_role']) ?></b></td><td class="small"><?= $r['kode_role'] === 'ADMIN' ? 'SSO SIM Mahasiswa' : 'Akun simdosen' ?></td>
      <?php foreach (IZIN as $boleh): ?><td><?= in_array($r['kode_role'], $boleh, true) ? '✓' : '<span class="muted">–</span>' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
</section>

<?php if ($tanpaAkun): ?>
<section class="card card-pad mb">
  <div class="card-head"><h2>Buat Akun Dosen</h2><span class="card-sub"><?= count($tanpaAkun) ?> dosen belum punya akun</span></div>
  <form method="post" class="form-grid" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="buat">
    <div class="form-row"><label for="id_dosen">Dosen</label><select class="select" id="id_dosen" name="id_dosen" required><?= opsi_html($tanpaAkun) ?></select><span class="help">Username otomatis = nomor registrasi.</span></div>
    <div class="form-row"><label for="password">Password awal</label><input class="input" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required><span class="help">Minimal 8 karakter.</span></div>
    <div class="form-actions full" style="margin-top:0"><button class="btn" type="submit"><?= icon('plus') ?>Buat akun</button></div>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <form class="filters" method="get">
    <div class="search"><?= icon('search') ?><label class="sr-only" for="cari">Cari</label><input class="input" id="cari" name="cari" type="search" value="<?= e(get('cari')) ?>" placeholder="Cari nomor registrasi atau nama…"></div>
    <button class="btn btn-outline" type="submit"><?= icon('search') ?>Cari</button>
  </form>
  <?php if (!$rows): ?><?= kosong('Belum ada akun dosen.') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Username</th><th>Nama</th><th>Login terakhir</th><th>Status</th><th>Reset password</th><th>Aksi</th></tr></thead>
    <tbody><?php foreach ($rows as $p): ?>
      <tr><td><?= e($p['username']) ?></td><td><?= $p['id_dosen'] ? '<a class="link" href="dosen.php?id=' . (int) $p['id_dosen'] . '">' . e($p['nama_lengkap']) . '</a>' : e($p['nama_lengkap']) . ' <span class="small" style="color:var(--red)">(tanpa data dosen)</span>' ?></td>
        <td class="small nowrap"><?= tgl_jam($p['last_login']) ?></td><td><?= badge($p['status_aktif']) ?></td>
        <td><form method="post" class="meter-row" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="aksi" value="reset"><input type="hidden" name="id" value="<?= (int) $p['id_pengguna'] ?>">
          <label class="sr-only" for="pw<?= (int) $p['id_pengguna'] ?>">Password baru</label>
          <input class="input" style="height:34px;width:150px" id="pw<?= (int) $p['id_pengguna'] ?>" name="password" type="password" minlength="8" placeholder="Password baru" autocomplete="new-password" required>
          <button class="btn btn-sm btn-outline" type="submit">Reset</button></form></td>
        <td><div class="aksi">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="status"><input type="hidden" name="id" value="<?= (int) $p['id_pengguna'] ?>">
            <button class="btn btn-sm btn-outline" type="submit"><?= $p['status_aktif'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form>
          <form method="post" data-konfirmasi="Hapus akun <?= e($p['username']) ?>? Data dosennya tetap ada."><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $p['id_pengguna'] ?>">
            <button class="icon-btn del" type="submit" aria-label="Hapus akun"><?= icon('trash') ?></button></form></div></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
