<?php
/**
 * profil.php
 *   Akun SSO : data berasal dari SIM Mahasiswa (JSON). Mahasiswa bisa memperbarui datanya lewat API.
 *   Dosen    : data dari SIM Dosen (JSON); bisa diperbarui lewat API SIM Dosen.
 */
require __DIR__ . '/inc/init.php';
$u = require_login();

/* =====================================================================
   AKSI
   ===================================================================== */
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');

    if ($aksi === 'sinkron' && $u['role'] === 'MAHASISWA') {
        $r = api_get('mahasiswa.php', ['id' => $u['id_mahasiswa']]);
        if ($r['error']) {
            flash('error', 'Gagal mengambil data: ' . $r['error']);
        } else {
            $m = $r['json']['data'];
            db_exec('UPDATE pengguna_sso SET nama_lengkap = ?, npm = ?, id_program_studi = ?, nama_program_studi = ?, id_fakultas = ?, terakhir_sinkron = NOW() WHERE id_mahasiswa = ?',
                [$m['nama_mahasiswa'], $m['npm'], (int) $m['program_studi']['id_program_studi'], $m['program_studi']['nama'], (int) $m['fakultas']['id_fakultas'], $u['id_mahasiswa']]);
            $_SESSION['user']['nama'] = $m['nama_mahasiswa'];
            $_SESSION['user']['nama_program_studi'] = $m['program_studi']['nama'];
            $_SESSION['json_terakhir'] = $r['json'];
            flash('success', 'Data diperbarui dari SIM Mahasiswa.');
        }
        redirect('profil.php');
    }

    if ($aksi === 'sinkron_dosen' && $u['role'] === 'DOSEN') {
        $r = api_get('dosen.php', ['id' => $u['id_simdosen']], false, 'simdosen');
        if ($r['error']) {
            flash('error', 'Gagal mengambil data: ' . $r['error']);
        } else {
            simpan_dosen($r['json']['data']);
            $d = db_row('SELECT * FROM dosen WHERE id_dosen = ?', [$u['id']]);
            $_SESSION['user']['nama'] = nama_dosen($d);
            $_SESSION['json_terakhir'] = $r['json'];
            flash('success', 'Data diperbarui dari SIM Dosen.');
        }
    }
    redirect('profil.php');
}

/* =====================================================================
   TAMPILAN
   ===================================================================== */
layout_start('Profil Saya', 'profil');

if ($u['role'] !== 'DOSEN'):
    $cache = db_row('SELECT * FROM pengguna_sso WHERE id_pengguna = ?', [$u['id']]);
    $json = $_SESSION['json_terakhir'] ?? null;
    unset($_SESSION['json_terakhir']); ?>
  <div class="detail-grid">
    <section class="card card-pad">
      <div class="card-head"><h2>Data Akun</h2><?= badge(LABEL_PERAN[$u['role']]) ?></div>
      <?= info_list(array_filter([
          'Nama' => e($u['nama']),
          'Username' => e($u['username']),
          'NPM' => $u['npm'] ? e($u['npm']) : null,
          'Email' => e($u['email'] ?: '-'),
          'Program Studi' => $u['nama_program_studi'] ? e($u['nama_program_studi']) : null,
          'Fakultas' => $u['nama_fakultas'] ? e($u['nama_fakultas']) : null,
          'Sinkron terakhir' => $cache ? tgl_jam($cache['terakhir_sinkron']) : '-',
      ])) ?>
      <?php if ($u['role'] === 'MAHASISWA'): ?>
        <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="aksi" value="sinkron">
          <button class="btn btn-light" type="submit"><?= icon('refresh') ?>Perbarui dari SIM Mahasiswa</button></form>
      <?php endif; ?>
    </section>
    <section class="card card-pad">
      <div class="card-head"><h2>Sumber Data</h2></div>
      <p>Akun ini masuk lewat <b>SSO SIM Mahasiswa</b>. Nama, NPM, program studi, dan password dikelola di <b>SIM Mahasiswa</b>, lalu dikirim ke SIMSKRIPSI dalam bentuk JSON.</p>
      <p class="mt muted">Untuk mengubah data atau password, buka SIM Mahasiswa. Token akses berlaku sampai <?= date('H.i', $u['token_exp']) ?> WIB.</p>
      <a class="btn btn-outline mt" href="<?= e(SIMMHS_URL) ?>" target="_blank" rel="noopener">Buka SIM Mahasiswa</a>
    </section>
  </div>
  <?php if ($json): ?>
    <section class="card card-pad">
      <div class="card-head"><h2>JSON dari api/mahasiswa.php</h2></div>
      <pre style="margin:0;overflow-x:auto;background:var(--navy);color:#e6edf7;padding:16px;border-radius:8px;font-size:12.5px"><?= e(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    </section>
  <?php endif;

else:
    $d = db_row('SELECT * FROM dosen WHERE id_dosen = ?', [$u['id']]);
    $json = $_SESSION['json_terakhir'] ?? null;
    unset($_SESSION['json_terakhir']); ?>
  <div class="detail-grid">
    <section class="card card-pad">
      <div class="card-head"><h2>Data Dosen</h2><?= badge($d['status_aktif']) ?></div>
      <?= info_list([
          'Nama' => e(nama_dosen($d)),
          'Nomor registrasi' => e($d['nomor_registrasi']),
          'NUPTK' => e($d['nuptk'] ?: '-'),
          'NIP' => e($d['nip'] ?: '-'),
          'Program Studi' => e($d['nama_program_studi'] ?: '-'),
          'Jabatan' => e($d['jabatan_fungsional'] ?: '-'),
          'Pendidikan' => e($d['pendidikan_terakhir'] ?: '-'),
          'Status (SIM Dosen)' => e($d['status_aktivitas'] ?: '-'),
          'Kuota bimbingan' => (int) $d['kuota_bimbingan'] . ' mahasiswa',
          'Sinkron terakhir' => tgl_jam($d['terakhir_sinkron']),
      ]) ?>
      <form method="post" class="mt"><?= csrf_field() ?><input type="hidden" name="aksi" value="sinkron_dosen">
        <button class="btn btn-light" type="submit"><?= icon('refresh') ?>Perbarui dari SIM Dosen</button></form>
    </section>
    <section class="card card-pad">
      <div class="card-head"><h2>Sumber Data</h2></div>
      <p>Akun ini masuk lewat <b>SSO SIM Dosen</b>. Data diri, jabatan, kontak, dan password dikelola di <b>SIM Dosen</b>, lalu dikirim ke SIMSKRIPSI dalam bentuk JSON.</p>
      <p class="mt muted">Token akses berlaku sampai <?= date('H.i', $u['token_exp']) ?> WIB.</p>
      <a class="btn btn-outline mt" href="<?= e(SIMDOSEN_URL) ?>/profil.php" target="_blank" rel="noopener">Ubah data di SIM Dosen</a>
    </section>
  </div>
  <?php if ($json): ?>
    <section class="card card-pad">
      <div class="card-head"><h2>JSON dari SIM Dosen api/dosen.php</h2></div>
      <pre style="margin:0;overflow-x:auto;background:var(--navy);color:#e6edf7;padding:16px;border-radius:8px;font-size:12.5px"><?= e(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    </section>
  <?php endif;
endif;
layout_end();
