<?php
/**
 * master.php?t=... — CRUD tabel master & referensi (khusus admin)
 * Satu file untuk semua tabel; struktur tiap tabel ditulis di konstanta MASTER.
 */
require __DIR__ . '/inc/init.php';
$u = wajib_izin('master.kelola');

$REF = fn ($label) => ['kode' => ['Kode', 'text', false, 20], 'nama' => [$label, 'text', true, 100], 'urutan' => ['Urutan', 'number', false, 0]];
$MASTER = [
    'universitas' => ['Universitas', 'universitas', 'id_universitas', 'nama_universitas', [
        'kode_universitas' => ['Kode', 'text', true, 20], 'nama_universitas' => ['Nama universitas', 'text', true, 200],
        'alamat' => ['Alamat', 'textarea', false, 500], 'kota' => ['Kota', 'text', false, 100], 'provinsi' => ['Provinsi', 'text', false, 100],
        'website' => ['Website', 'text', false, 255], 'email' => ['Email', 'text', false, 150], 'telepon' => ['Telepon', 'text', false, 50]]],
    'fakultas' => ['Fakultas', 'fakultas', 'id_fakultas', 'kode_fakultas', [
        'id_universitas' => ['Universitas', 'fk', true, ['universitas', 'id_universitas', 'nama_universitas']],
        'kode_fakultas' => ['Kode', 'text', true, 20], 'nama_fakultas' => ['Nama fakultas', 'text', true, 200]]],
    'prodi' => ['Program Studi', 'program_studi', 'id_program_studi', 'id_fakultas, nama_program_studi', [
        'id_fakultas' => ['Fakultas', 'fk', true, ['fakultas', 'id_fakultas', 'nama_fakultas']],
        'kode_program_studi' => ['Kode prodi (sama dengan SIM Mahasiswa)', 'text', true, 20], 'kode_pddikti' => ['Kode PDDikti', 'text', false, 10],
        'nama_program_studi' => ['Nama program studi', 'text', true, 200],
        'jenjang' => ['Jenjang', 'enum', true, ['D3', 'D4', 'S1', 'S2', 'S3', 'Profesi']],
        'status_aktif' => ['Status', 'enum', true, ['Aktif', 'Tidak Aktif']]]],
    'jabatan' => ['Jabatan Fungsional', 'jabatan_fungsional', 'id_jabatan_fungsional', 'urutan', $REF('Jabatan fungsional')],
    'pangkat' => ['Kepangkatan', 'pangkat_golongan', 'id_pangkat_golongan', 'urutan', ['kode' => ['Golongan/ruang', 'text', true, 20], 'nama' => ['Pangkat', 'text', true, 100], 'urutan' => ['Urutan', 'number', false, 0]]],
    'pendidikan' => ['Pendidikan', 'jenjang_pendidikan', 'id_jenjang_pendidikan', 'urutan', $REF('Jenjang pendidikan')],
    'rumpun' => ['Rumpun Ilmu', 'rumpun_ilmu', 'id_rumpun_ilmu', 'urutan, nama', $REF('Rumpun ilmu')],
    'pegawai' => ['Status Pegawai', 'status_pegawai', 'id_status_pegawai', 'urutan', $REF('Status pegawai')],
    'ikatan' => ['Ikatan Kerja', 'ikatan_kerja', 'id_ikatan_kerja', 'urutan', $REF('Ikatan kerja')],
    'aktivitas' => ['Status Aktivitas', 'status_aktivitas', 'id_status_aktivitas', 'urutan', $REF('Status aktivitas')],
];

$t = get('t', post('t'));

/* ---------- Beranda master: daftar tabel ---------- */
if (!isset($MASTER[$t])) {
    layout_start('Master Data', 'master', 'Tabel unit kerja dan referensi yang dipakai data dosen.');
    echo '<div class="info-grid" style="margin-top:0">';
    foreach ($MASTER as $k => [$judul, $tabel]) {
        $n = (int) db_val("SELECT COUNT(*) FROM $tabel");
        echo '<a class="info-card" href="master.php?t=' . $k . '"><h3>' . e($judul) . '</h3><p>' . $n . ' data · tabel <code>' . e($tabel) . '</code></p></a>';
    }
    echo '</div>';
    layout_end();
    exit;
}

[$judul, $tabel, $pk, $urut, $kolom] = $MASTER[$t];
$self = "master.php?t=$t";

/* ---------- Aksi ---------- */
if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    if (post('aksi') === 'hapus') {
        try {
            db_exec("DELETE FROM $tabel WHERE $pk = ?", [$id]);
            catat_log('DELETE', "$tabel #$id");
            flash('success', 'Data dihapus.');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== 1451) throw $ex;
            flash('error', 'Data ini masih dipakai di tabel lain, jadi tidak bisa dihapus.');
        }
        redirect($self);
    }
    $nilai = [];
    foreach ($kolom as $k => [$label, $tipe, $wajib, $opsi]) {
        $v = post($k);
        if ($wajib && $v === '') { flash('error', "$label wajib diisi."); redirect($self . ($id ? "&edit=$id" : '')); }
        if ($tipe === 'text' && mb_strlen($v) > $opsi) { flash('error', "$label maksimal $opsi karakter."); redirect($self . ($id ? "&edit=$id" : '')); }
        if ($tipe === 'enum' && $v !== '' && !in_array($v, $opsi, true)) $v = $opsi[0];
        $nilai[$k] = $v === '' ? null : (in_array($tipe, ['number', 'fk'], true) ? (int) $v : $v);
    }
    try {
        if ($id) {
            db_exec("UPDATE $tabel SET " . implode(', ', array_map(fn ($k) => "$k = ?", array_keys($nilai))) . " WHERE $pk = ?", array_merge(array_values($nilai), [$id]));
        } else {
            db_q("INSERT INTO $tabel (" . implode(', ', array_keys($nilai)) . ') VALUES (' . implode(', ', array_fill(0, count($nilai), '?')) . ')', array_values($nilai));
        }
        catat_log($id ? 'UPDATE' : 'INSERT', "$tabel " . ($id ? "#$id" : 'baru'));
        flash('success', "$judul tersimpan.");
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() !== 1062) throw $ex;
        flash('error', 'Kode atau nama itu sudah ada.');
        redirect($self . ($id ? "&edit=$id" : ''));
    }
    redirect($self);
}

/* ---------- Tampilan: form + tabel ---------- */
$edit = get('edit') !== '' ? db_row("SELECT * FROM $tabel WHERE $pk = ?", [(int) get('edit')]) : null;
$rows = db_rows("SELECT * FROM $tabel ORDER BY $urut");
$fk = [];
foreach ($kolom as $k => [$l, $tipe, $w, $opsi]) if ($tipe === 'fk') $fk[$k] = opsi_tabel($opsi[0], $opsi[1], $opsi[2], $opsi[2]);

layout_start($judul, 'master', '<a class="link" href="master.php">Master Data</a> › ' . e($judul));
?>
<section class="card card-pad mb">
  <div class="card-head"><h2><?= $edit ? 'Ubah ' . e(strtolower($judul)) : 'Tambah ' . e(strtolower($judul)) ?></h2></div>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="t" value="<?= e($t) ?>"><input type="hidden" name="id" value="<?= (int) ($edit[$pk] ?? 0) ?>">
    <div class="form-grid">
      <?php foreach ($kolom as $k => [$label, $tipe, $wajib, $opsi]): $v = $edit[$k] ?? ''; ?>
        <div class="form-row<?= $tipe === 'textarea' ? ' full' : '' ?>"><label for="<?= $k ?>"><?= e($label) ?><?= $wajib ? '' : ' <span class="muted">(opsional)</span>' ?></label>
          <?php if ($tipe === 'textarea'): ?><textarea class="input" id="<?= $k ?>" name="<?= $k ?>" rows="2"><?= e($v) ?></textarea>
          <?php elseif ($tipe === 'fk'): ?><select class="select" id="<?= $k ?>" name="<?= $k ?>"><?= opsi_html($fk[$k], $v) ?></select>
          <?php elseif ($tipe === 'enum'): ?><select class="select" id="<?= $k ?>" name="<?= $k ?>"><?= opsi_html(array_combine($opsi, $opsi), $v, '') ?></select>
          <?php else: ?><input class="input" id="<?= $k ?>" name="<?= $k ?>" value="<?= e($v) ?>"<?= $tipe === 'number' ? ' type="number" min="0" max="255"' : ' maxlength="' . (int) $opsi . '"' ?><?= $wajib ? ' required' : '' ?>><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
    <div class="form-actions"><button class="btn" type="submit"><?= icon('check') ?>Simpan</button><?php if ($edit): ?><a class="btn btn-outline" href="<?= $self ?>">Batal</a><?php endif; ?></div>
  </form>
</section>
<section class="card">
  <?php if (!$rows): ?><?= kosong('Belum ada data.') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>No</th><?php foreach ($kolom as $k => [$label]): ?><th><?= e($label) ?></th><?php endforeach; ?><th>Aksi</th></tr></thead>
    <tbody><?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td>
        <?php foreach ($kolom as $k => [$l, $tipe]): ?><td><?= e($tipe === 'fk' ? ($fk[$k][$r[$k]] ?? '-') : ($r[$k] ?? '-')) ?></td><?php endforeach; ?>
        <td><div class="aksi"><a class="icon-btn edit" href="<?= $self ?>&amp;edit=<?= (int) $r[$pk] ?>" aria-label="Ubah"><?= icon('edit') ?></a>
          <form method="post" data-konfirmasi="Hapus data ini?"><?= csrf_field() ?><input type="hidden" name="t" value="<?= e($t) ?>"><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $r[$pk] ?>">
            <button class="icon-btn del" type="submit" aria-label="Hapus"><?= icon('trash') ?></button></form></div></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php
layout_end();
