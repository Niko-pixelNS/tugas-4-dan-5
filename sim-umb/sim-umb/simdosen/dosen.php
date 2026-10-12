<?php
/**
 * dosen.php — CRUD data dosen (+ akun login dosen)
 *   Khusus admin. Dosen melihat datanya sendiri lewat menu Data Diri.
 */
require __DIR__ . '/inc/init.php';
$u = wajib_izin('dosen.lihat');

const REF = [
    'id_jabatan_fungsional' => ['jabatan_fungsional', 'Jabatan fungsional', true],
    'id_pangkat_golongan'   => ['pangkat_golongan', 'Kepangkatan', false],
    'id_jenjang_pendidikan' => ['jenjang_pendidikan', 'Pendidikan terakhir', true],
    'id_rumpun_ilmu'        => ['rumpun_ilmu', 'Rumpun ilmu', false],
    'id_status_pegawai'     => ['status_pegawai', 'Status pegawai', true],
    'id_ikatan_kerja'       => ['ikatan_kerja', 'Ikatan kerja', true],
    'id_status_aktivitas'   => ['status_aktivitas', 'Status aktivitas', true],
];

/** Program studi yang boleh dipilih sesuai cakupan peran. */
function prodi_cakupan(): array
{
    $w = ['', []]; // admin: semua program studi
    $hasil = [];
    foreach (db_rows("SELECT ps.id_program_studi, ps.jenjang, ps.nama_program_studi, f.kode_fakultas FROM program_studi ps
                       JOIN fakultas f ON f.id_fakultas = ps.id_fakultas {$w[0]} ORDER BY f.kode_fakultas, ps.nama_program_studi", $w[1]) as $r) {
        $hasil[$r['id_program_studi']] = $r['kode_fakultas'] . ' — ' . $r['jenjang'] . ' ' . $r['nama_program_studi'];
    }
    return $hasil;
}
/** Satu dosen dalam cakupan pengguna, atau null. */
function dosen_cakupan(int $id): ?array
{
    [$sc, $sp] = scope_dosen('v');
    return db_row("SELECT v.*, d.id_pengguna AS akun, p.username, p.status_aktif AS status_akun, p.last_login
                     FROM v_dosen v JOIN dosen d ON d.id_dosen = v.id_dosen LEFT JOIN pengguna p ON p.id_pengguna = d.id_pengguna
                    WHERE v.id_dosen = ? $sc", array_merge([$id], $sp));
}

/* =====================================================================
   AKSI
   ===================================================================== */
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    $id = (int) post('id');

    if ($aksi === 'simpan' && boleh('dosen.kelola')) {
        $lama = $id ? dosen_cakupan($id) : null;
        if ($id && !$lama) redirect('dosen.php');
        $f = [];
        foreach (['nomor_registrasi', 'nuptk', 'nip', 'nama_dosen', 'gelar_depan', 'gelar_belakang', 'tempat_lahir', 'email', 'no_hp'] as $k) {
            $f[$k] = post($k) !== '' ? post($k) : null;
        }
        foreach (['nomor_registrasi', 'nuptk', 'nip'] as $k) if ($f[$k]) $f[$k] = preg_replace('/\s+/', '', $f[$k]);
        $f['jenis_registrasi'] = in_array(post('jenis_registrasi'), ['NIDN', 'NIDK', 'NUP'], true) ? post('jenis_registrasi') : 'NIDN';
        $f['jenis_kelamin'] = in_array(post('jenis_kelamin'), ['L', 'P'], true) ? post('jenis_kelamin') : null;
        $f['tanggal_lahir'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('tanggal_lahir')) ? post('tanggal_lahir') : null;
        $f['status_serdos'] = post('status_serdos') === 'Sudah' ? 'Sudah' : 'Belum';
        $f['id_program_studi'] = (int) post('id_program_studi');
        foreach (REF as $k => [$tabel]) $f[$k] = post($k) !== '' ? (int) post($k) : null;
        $_SESSION['form_dosen'] = $_POST;
        $balik = 'dosen.php?' . ($id ? "edit=$id" : 'aksi=baru');

        $err = null;
        if (!$f['nomor_registrasi'] || !preg_match('/^\d{8,20}$/', $f['nomor_registrasi'])) $err = 'Nomor registrasi wajib diisi, 8–20 digit angka.';
        elseif ($f['nuptk'] && !preg_match('/^\d{16}$/', $f['nuptk'])) $err = 'NUPTK harus 16 digit angka.';
        elseif ($f['nip'] && !preg_match('/^\d{18}$/', $f['nip'])) $err = 'NIP harus 18 digit angka.';
        elseif (mb_strlen((string) $f['nama_dosen']) < 3) $err = 'Isi nama dosen tanpa gelar.';
        elseif (!$f['jenis_kelamin']) $err = 'Pilih jenis kelamin.';
        elseif ($f['email'] && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err = 'Format email tidak sah.';
        elseif (!isset(prodi_cakupan()[$f['id_program_studi']])) $err = 'Pilih penempatan program studi.';
        else foreach (REF as $k => [$tabel, $label, $wajib]) if ($wajib && !$f[$k]) { $err = "Pilih $label."; break; }

        $buatAkun = !$id || !$lama['akun'] ? post('buat_akun') === '1' : false;
        $pw = (string) ($_POST['password'] ?? '');
        if (!$err && $buatAkun && strlen($pw) < 8) $err = 'Password akun minimal 8 karakter.';
        if ($err) {
            flash('error', $err);
            redirect($balik);
        }

        try {
            db()->begin_transaction();
            $kolom = array_keys($f);
            if ($id) {
                db_exec('UPDATE dosen SET ' . implode(', ', array_map(fn ($k) => "$k = ?", $kolom)) . ' WHERE id_dosen = ?', array_merge(array_values($f), [$id]));
            } else {
                db_q('INSERT INTO dosen (' . implode(', ', $kolom) . ') VALUES (' . implode(', ', array_fill(0, count($kolom), '?')) . ')', array_values($f));
                $id = db_id();
            }
            $namaLengkap = trim(implode(' ', array_filter([$f['gelar_depan'], $f['nama_dosen']]))) . ($f['gelar_belakang'] ? ', ' . $f['gelar_belakang'] : '');
            if ($buatAkun) {
                $idRole = (int) db_val("SELECT id_role FROM roles WHERE kode_role = 'DOSEN'");
                db_q('INSERT INTO pengguna (id_role, username, password_hash, nama_lengkap, email) VALUES (?, ?, ?, ?, ?)',
                    [$idRole, $f['nomor_registrasi'], password_hash($pw, PASSWORD_DEFAULT), $namaLengkap, $f['email']]);
                db_exec('UPDATE dosen SET id_pengguna = ? WHERE id_dosen = ?', [db_id(), $id]);
            } elseif ($lama && $lama['akun']) {
                // akun ikut diperbarui supaya username = nomor registrasi dan nama/email sama
                db_exec('UPDATE pengguna SET username = ?, nama_lengkap = ?, email = ? WHERE id_pengguna = ?', [$f['nomor_registrasi'], $namaLengkap, $f['email'], (int) $lama['akun']]);
            }
            db()->commit();
        } catch (mysqli_sql_exception $ex) {
            db()->rollback();
            if ($ex->getCode() !== 1062) throw $ex;
            flash('error', 'Nomor registrasi, NUPTK, NIP, atau email sudah dipakai dosen/akun lain.');
            redirect($balik);
        }
        unset($_SESSION['form_dosen']);
        catat_log($lama ? 'UPDATE' : 'INSERT', 'Dosen ' . $f['nomor_registrasi'] . ' — ' . $f['nama_dosen']);
        flash('success', 'Data dosen tersimpan.' . ($buatAkun ? ' Akun login dibuat dengan username ' . $f['nomor_registrasi'] . '.' : ''));
        redirect("dosen.php?id=$id");
    }

    if ($aksi === 'hapus' && boleh('dosen.hapus') && ($d = dosen_cakupan($id))) {
        db()->begin_transaction();
        db_exec('DELETE FROM dosen WHERE id_dosen = ?', [$id]);
        if ($d['akun']) db_exec('DELETE FROM pengguna WHERE id_pengguna = ?', [(int) $d['akun']]);
        db()->commit();
        catat_log('DELETE', 'Dosen ' . $d['nomor_registrasi'] . ' — ' . $d['nama_dosen']);
        flash('success', 'Data dosen ' . $d['nama_lengkap'] . ' dihapus' . ($d['akun'] ? ' beserta akun loginnya.' : '.'));
    }
    redirect('dosen.php');
}

/* =====================================================================
   FORM TAMBAH / UBAH
   ===================================================================== */
if (boleh('dosen.kelola') && (get('aksi') === 'baru' || get('edit') !== '')) {
    $edit = get('edit') !== '' ? dosen_cakupan((int) get('edit')) : null;
    if (get('edit') !== '' && !$edit) redirect('dosen.php');
    $v = $_SESSION['form_dosen'] ?? ($edit ?: ['jenis_registrasi' => 'NIDN', 'status_serdos' => 'Belum']);
    unset($_SESSION['form_dosen']);
    $prodi = prodi_cakupan();
    if (!$edit && count($prodi) === 1) $v['id_program_studi'] = array_key_first($prodi);

    $in = fn ($k, $label, $attr = '') => '<div class="form-row"><label for="' . $k . '">' . $label . '</label><input class="input" id="' . $k . '" name="' . $k . '" value="' . e($v[$k] ?? '') . '" ' . $attr . '></div>';
    $sel = fn ($k, $label, $opsi, $kosong = '— Pilih —') => '<div class="form-row"><label for="' . $k . '">' . $label . '</label><select class="select" id="' . $k . '" name="' . $k . '">' . opsi_html($opsi, $v[$k] ?? null, $kosong) . '</select></div>';

    layout_start($edit ? 'Ubah Data Dosen' : 'Tambah Dosen', 'dosen', '<a class="link" href="dosen.php">Data Dosen</a> › ' . ($edit ? e($edit['nama_lengkap']) : 'Baru'));
    ?>
    <form method="post" class="stack">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int) ($edit['id_dosen'] ?? 0) ?>">
      <section class="card card-pad">
        <div class="card-head"><h2>Identitas</h2></div>
        <div class="form-grid">
          <?= $sel('jenis_registrasi', 'Jenis nomor registrasi', ['NIDN' => 'NIDN', 'NIDK' => 'NIDK', 'NUP' => 'NUP'], '') ?>
          <?= $in('nomor_registrasi', 'Nomor registrasi', 'inputmode="numeric" maxlength="20" required') ?>
          <?= $in('nuptk', 'NUPTK <span class="muted">(16 digit)</span>', 'inputmode="numeric" maxlength="16"') ?>
          <?= $in('nip', 'NIP <span class="muted">(18 digit, bila ada)</span>', 'inputmode="numeric" maxlength="18"') ?>
          <?= $in('gelar_depan', 'Gelar depan', 'maxlength="30" placeholder="Dr."') ?>
          <?= $in('gelar_belakang', 'Gelar belakang', 'maxlength="60" placeholder="M.Kom."') ?>
          <div class="full"><?= $in('nama_dosen', 'Nama lengkap (tanpa gelar)', 'maxlength="150" required') ?></div>
          <?= $sel('jenis_kelamin', 'Jenis kelamin', ['L' => 'Laki-laki', 'P' => 'Perempuan']) ?>
          <?= $in('tempat_lahir', 'Tempat lahir', 'maxlength="100"') ?>
          <?= $in('tanggal_lahir', 'Tanggal lahir', 'type="date"') ?>
          <?= $in('email', 'Email', 'type="email" maxlength="150"') ?>
          <?= $in('no_hp', 'No. HP', 'maxlength="30"') ?>
        </div>
      </section>
      <section class="card card-pad">
        <div class="card-head"><h2>Kepegawaian &amp; Penempatan</h2></div>
        <div class="form-grid">
          <?= $sel('id_program_studi', 'Penempatan (program studi homebase)', $prodi) ?>
          <?php foreach (REF as $k => [$tabel, $label]): ?>
            <?= $sel($k, $label, opsi_tabel($tabel, $k, $tabel === 'pangkat_golongan' ? "CONCAT(kode, ' - ', nama)" : 'nama')) ?>
          <?php endforeach; ?>
          <?= $sel('status_serdos', 'Status sertifikasi dosen', ['Sudah' => 'Sudah', 'Belum' => 'Belum'], '') ?>
        </div>
      </section>
      <section class="card card-pad">
        <div class="card-head"><h2>Akun Login</h2></div>
        <?php if ($edit && $edit['akun']): ?>
          <p>Dosen ini sudah punya akun dengan username <b><?= e($edit['username']) ?></b> (<?= e($edit['status_akun']) ?>). Username ikut berubah bila nomor registrasi diubah. Reset password lewat menu Pengguna.</p>
        <?php else: ?>
          <label class="check"><input type="checkbox" name="buat_akun" value="1"<?= !empty($v['buat_akun']) || !$edit ? ' checked' : '' ?>> Buat akun login (username = nomor registrasi). Akun ini juga dipakai untuk SSO ke SIMSKRIPSI.</label>
          <div class="form-row mt" style="max-width:360px"><label for="password">Password awal</label><input class="input" id="password" name="password" type="password" minlength="8" autocomplete="new-password"><span class="help">Minimal 8 karakter. Sampaikan ke dosen, lalu minta diganti di menu Data Diri.</span></div>
        <?php endif; ?>
      </section>
      <div class="form-actions" style="margin-top:0"><button class="btn" type="submit"><?= icon('check') ?>Simpan</button><a class="btn btn-outline" href="<?= $edit ? 'dosen.php?id=' . (int) $edit['id_dosen'] : 'dosen.php' ?>">Batal</a></div>
    </form>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   DETAIL
   ===================================================================== */
if (get('id') !== '') {
    $d = dosen_cakupan((int) get('id'));
    if (!$d) {
        flash('error', 'Data dosen tidak ditemukan atau di luar kewenangan Anda.');
        redirect('dosen.php');
    }
    $aksi = '<a class="btn btn-outline" href="dosen.php">' . icon('arrowLeft') . 'Kembali</a>';
    if (boleh('dosen.kelola')) $aksi .= '<a class="btn btn-green" href="dosen.php?edit=' . (int) $d['id_dosen'] . '">' . icon('edit') . 'Ubah</a>';
    if (boleh('dosen.hapus')) $aksi .= '<form method="post" class="inline" data-konfirmasi="Hapus data ' . e($d['nama_lengkap']) . '' . ($d['akun'] ? ' beserta akun loginnya' : '') . '?">' . csrf_field()
        . '<input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="' . (int) $d['id_dosen'] . '"><button class="btn btn-red" type="submit">' . icon('trash') . 'Hapus</button></form>';
    layout_start($d['nama_lengkap'], 'dosen', '<a class="link" href="dosen.php">Data Dosen</a> › ' . e($d['jenis_registrasi'] . ' ' . $d['nomor_registrasi']), $aksi);
    ?>
    <div class="detail-grid">
      <section class="card card-pad">
        <div class="card-head"><h2>Identitas &amp; Kepegawaian</h2><?= badge($d['status_aktivitas']) ?></div>
        <?= info_list([
            'Nomor registrasi' => e($d['jenis_registrasi'] . ' ' . $d['nomor_registrasi']), 'NUPTK' => e($d['nuptk'] ?: '-'), 'NIP' => e($d['nip'] ?: '-'),
            'Nama' => '<b>' . e($d['nama_lengkap']) . '</b>', 'Jenis kelamin' => $d['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan',
            'Tempat, tgl lahir' => e(($d['tempat_lahir'] ?: '-') . ', ' . tgl($d['tanggal_lahir'], true)),
            'Jabatan fungsional' => e($d['jabatan_fungsional']), 'Kepangkatan' => e($d['kepangkatan'] ?: '-'),
            'Pendidikan terakhir' => e($d['pendidikan_terakhir']), 'Rumpun ilmu' => e($d['rumpun_ilmu'] ?: '-'),
            'Status serdos' => badge($d['status_serdos']), 'Status pegawai' => e($d['status_pegawai']), 'Ikatan kerja' => e($d['ikatan_kerja']),
        ]) ?>
      </section>
      <div class="stack">
        <section class="card card-pad">
          <div class="card-head"><h2>Penempatan</h2></div>
          <?= info_list(['Program studi' => e($d['penempatan']), 'Fakultas' => e($d['nama_fakultas']), 'Universitas' => e($d['nama_universitas'])]) ?>
        </section>
        <section class="card card-pad">
          <div class="card-head"><h2>Kontak &amp; Akun</h2></div>
          <?= info_list([
              'Email' => e($d['email'] ?: '-'), 'No. HP' => e($d['no_hp'] ?: '-'),
              'Akun login' => $d['akun'] ? e($d['username']) . ' ' . badge($d['status_akun'] === 'Aktif' ? 'Aktif' : 'Tidak Aktif') : '<span class="muted">Belum ada</span>',
              'Login terakhir' => tgl_jam($d['last_login'] ?? null), 'Diperbarui' => tgl_jam($d['updated_at']),
          ]) ?>
        </section>
      </div>
    </div>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   DAFTAR
   ===================================================================== */
[$sc, $sp] = scope_dosen('v');
$where = "FROM v_dosen v WHERE 1=1 $sc";
$param = $sp;
$cari = get('cari');
if ($cari !== '') {
    $where .= ' AND (v.nama_dosen LIKE ? OR v.nomor_registrasi LIKE ? OR v.nuptk LIKE ? OR v.nip LIKE ?)';
    array_push($param, "%$cari%", "%$cari%", "%$cari%", "%$cari%");
}
$filter = ['prodi' => 'id_program_studi', 'jabatan' => 'id_jabatan_fungsional', 'status' => 'id_status_aktivitas'];
foreach ($filter as $g => $kol) if (get($g) !== '') { $where .= " AND v.$kol = ?"; $param[] = (int) get($g); }
$pg = paginasi((int) db_val("SELECT COUNT(*) $where", $param), 10);
$rows = db_rows("SELECT v.* $where ORDER BY v.nama_dosen LIMIT ? OFFSET ?", array_merge($param, [$pg['per'], $pg['offset']]));

layout_start('Data Dosen', 'dosen', boleh('dosen.kelola') ? 'Kelola data dosen dan akun loginnya.' : 'Mode lihat saja.',
    boleh('dosen.kelola') ? '<a class="btn" href="dosen.php?aksi=baru">' . icon('plus') . 'Tambah dosen</a>' : '');
?>
<section class="card">
  <form class="filters" method="get" data-auto>
    <div class="search"><?= icon('search') ?><label class="sr-only" for="cari">Cari</label><input class="input" id="cari" name="cari" type="search" value="<?= e($cari) ?>" placeholder="Cari nama, nomor registrasi, NUPTK, atau NIP…"></div>
    <?php if (count(prodi_cakupan()) > 1): ?><label class="sr-only" for="f-prodi">Prodi</label><select class="select" id="f-prodi" name="prodi"><?= opsi_html(prodi_cakupan(), get('prodi'), 'Semua prodi') ?></select><?php endif; ?>
    <label class="sr-only" for="f-jab">Jabatan</label><select class="select" id="f-jab" name="jabatan"><?= opsi_html(opsi_tabel('jabatan_fungsional', 'id_jabatan_fungsional'), get('jabatan'), 'Semua jabatan') ?></select>
    <label class="sr-only" for="f-st">Status</label><select class="select" id="f-st" name="status"><?= opsi_html(opsi_tabel('status_aktivitas', 'id_status_aktivitas'), get('status'), 'Semua status') ?></select>
    <button class="btn btn-outline" type="submit"><?= icon('search') ?>Cari</button>
  </form>
  <?php if (!$rows): ?><?= kosong('Tidak ada data dosen yang cocok.', boleh('dosen.kelola') ? '<a class="btn" href="dosen.php?aksi=baru">' . icon('plus') . 'Tambah dosen</a>' : '') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>No</th><th>Nomor Registrasi</th><th>Nama</th><th>Jabatan Fungsional</th><th>Pendidikan</th><th>Penempatan</th><th>Serdos</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody><?php foreach ($rows as $i => $d): ?>
      <tr><td><?= $pg['offset'] + $i + 1 ?></td><td><?= e($d['nomor_registrasi']) ?><br><span class="small muted"><?= e($d['jenis_registrasi']) ?></span></td>
        <td><b><?= e($d['nama_lengkap']) ?></b></td><td><?= e($d['jabatan_fungsional']) ?></td><td><?= e($d['pendidikan_terakhir']) ?></td>
        <td><?= e($d['penempatan']) ?></td><td><?= badge($d['status_serdos']) ?></td><td><?= badge($d['status_aktivitas']) ?></td>
        <td><div class="aksi"><a class="icon-btn view" href="dosen.php?id=<?= (int) $d['id_dosen'] ?>" aria-label="Detail <?= e($d['nama_dosen']) ?>"><?= icon('eye') ?></a>
          <?php if (boleh('dosen.kelola')): ?><a class="icon-btn edit" href="dosen.php?edit=<?= (int) $d['id_dosen'] ?>" aria-label="Ubah <?= e($d['nama_dosen']) ?>"><?= icon('edit') ?></a><?php endif; ?></div></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
