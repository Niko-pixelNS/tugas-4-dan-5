<?php
/**
 * profil.php — data diri (dosen) / akun (peran lain): ubah kontak & ganti password.
 */
require __DIR__ . '/inc/init.php';
$u = wajib_izin('profil');

if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    if ($aksi === 'kontak' && $u['role'] === 'DOSEN') {
        $email = post('email') ?: null;
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Format email tidak sah.');
        } else {
            try {
                db()->begin_transaction();
                db_exec('UPDATE dosen SET email = ?, no_hp = ? WHERE id_dosen = ?', [$email, post('no_hp') ?: null, $u['id_dosen']]);
                db_exec('UPDATE pengguna SET email = ? WHERE id_pengguna = ?', [$email, $u['id']]);
                db()->commit();
                catat_log('UPDATE', 'Dosen memperbarui kontak');
                flash('success', 'Kontak diperbarui.');
            } catch (mysqli_sql_exception $ex) {
                db()->rollback();
                if ($ex->getCode() !== 1062) throw $ex;
                flash('error', 'Email itu sudah dipakai akun lain.');
            }
        }
    }
    if ($aksi === 'password' && $u['sumber'] === 'lokal') {
        $hash = db_val('SELECT password_hash FROM pengguna WHERE id_pengguna = ?', [$u['id']]);
        $baru = (string) ($_POST['password_baru'] ?? '');
        if (!password_verify((string) ($_POST['password_lama'] ?? ''), $hash)) flash('error', 'Password lama salah.');
        elseif (strlen($baru) < 8) flash('error', 'Password baru minimal 8 karakter.');
        elseif ($baru !== (string) ($_POST['password_ulang'] ?? '')) flash('error', 'Ulangi password baru dengan benar.');
        else {
            db_exec('UPDATE pengguna SET password_hash = ? WHERE id_pengguna = ?', [password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            catat_log('UPDATE', 'Mengganti password');
            flash('success', 'Password diganti.');
        }
    }
    redirect('profil.php');
}

$form_password = function () { ?>
    <section class="card card-pad">
      <div class="card-head"><h2>Ganti Password</h2></div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="password">
        <div class="form-row"><label for="password_lama">Password lama</label><input class="input" id="password_lama" name="password_lama" type="password" autocomplete="current-password" required></div>
        <div class="form-row mt"><label for="password_baru">Password baru</label><input class="input" id="password_baru" name="password_baru" type="password" minlength="8" autocomplete="new-password" required><span class="help">Minimal 8 karakter. Password ini juga dipakai untuk SSO ke SIMSKRIPSI.</span></div>
        <div class="form-row mt"><label for="password_ulang">Ulangi password baru</label><input class="input" id="password_ulang" name="password_ulang" type="password" minlength="8" autocomplete="new-password" required></div>
        <div class="form-actions"><button class="btn" type="submit"><?= icon('key') ?>Ganti password</button></div>
      </form>
    </section>
<?php };

if ($u['role'] === 'DOSEN') {
    $d = db_row('SELECT * FROM v_dosen WHERE id_dosen = ?', [$u['id_dosen']]);
    layout_start('Data Diri Saya', 'profil', 'Data jabatan, kepangkatan, dan penempatan dikelola operator prodi. Anda dapat memperbarui kontak dan password.');
    ?>
    <div class="detail-grid">
      <section class="card card-pad">
        <div class="card-head"><h2><?= e($d['nama_lengkap']) ?></h2><?= badge($d['status_aktivitas']) ?></div>
        <?= info_list([
            'Nomor registrasi' => e($d['jenis_registrasi'] . ' ' . $d['nomor_registrasi']), 'NUPTK' => e($d['nuptk'] ?: '-'), 'NIP' => e($d['nip'] ?: '-'),
            'Jenis kelamin' => $d['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
            'Tempat, tgl lahir' => e(($d['tempat_lahir'] ?: '-') . ', ' . tgl($d['tanggal_lahir'], true)),
            'Jabatan fungsional' => e($d['jabatan_fungsional']), 'Kepangkatan' => e($d['kepangkatan'] ?: '-'),
            'Pendidikan terakhir' => e($d['pendidikan_terakhir']), 'Rumpun ilmu' => e($d['rumpun_ilmu'] ?: '-'),
            'Status serdos' => badge($d['status_serdos']), 'Status pegawai' => e($d['status_pegawai']), 'Ikatan kerja' => e($d['ikatan_kerja']),
            'Penempatan' => e($d['penempatan'] . ' · ' . $d['nama_fakultas']),
        ]) ?>
      </section>
      <div class="stack">
        <section class="card card-pad">
          <div class="card-head"><h2>Kontak</h2></div>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="kontak">
            <div class="form-row"><label for="email">Email</label><input class="input" id="email" name="email" type="email" value="<?= e($d['email']) ?>"></div>
            <div class="form-row mt"><label for="no_hp">No. HP</label><input class="input" id="no_hp" name="no_hp" value="<?= e($d['no_hp']) ?>"></div>
            <div class="form-actions"><button class="btn" type="submit">Simpan kontak</button></div>
          </form>
        </section>
        <?php $form_password(); ?>
      </div>
    </div>
    <?php
    layout_end();
    exit;
}

layout_start('Profil Saya', 'profil');
?>
<div class="detail-grid">
  <section class="card card-pad">
    <div class="card-head"><h2>Akun Admin</h2><?= badge('Aktif') ?></div>
    <?= info_list(['Nama' => e($u['nama']), 'Username' => e($u['username']), 'Email' => e($u['email'] ?: '-'), 'Peran' => 'Admin']) ?>
  </section>
  <section class="card card-pad">
    <div class="card-head"><h2>Sumber Akun</h2></div>
    <p>Anda masuk lewat <b>SSO SIM Mahasiswa</b>. Akun Admin hanya ada satu, yaitu milik SIM Mahasiswa. Nama, email, dan password diubah di sana.</p>
    <a class="btn btn-outline mt" href="<?= e(SIMMHS_URL) ?>" target="_blank" rel="noopener">Buka SIM Mahasiswa</a>
  </section>
</div>
<?php
layout_end();
