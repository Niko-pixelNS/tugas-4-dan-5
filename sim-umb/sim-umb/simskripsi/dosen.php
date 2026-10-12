<?php
/**
 * dosen.php — data dosen di SIMSKRIPSI
 * Sumber data: SIM Dosen (web service JSON api/dosen.php). Di sini hanya dibaca,
 * disinkronkan, dan diatur kuota bimbingannya (milik SIMSKRIPSI).
 *   Admin    : semua dosen
 *   Operator : dosen dengan homebase di prodinya
 */
require __DIR__ . '/inc/init.php';
$u = require_login('ADMIN', 'OPERATOR_PRODI');

if (is_post()) {
    csrf_check();
    if (post('aksi') === 'sinkron') {
        [$n, $err] = sinkron_semua_dosen();
        flash($err ? 'error' : 'success', $err ? "Sinkron gagal setelah $n data: $err" : "$n data dosen diperbarui dari SIM Dosen.");
    }
    if (post('aksi') === 'kuota') {
        $kuota = max(1, min(30, (int) post('kuota_bimbingan')));
        $w = is_role('OPERATOR_PRODI') ? ' AND id_program_studi = ?' : '';
        $p = is_role('OPERATOR_PRODI') ? [(int) $u['id_program_studi']] : [];
        db_exec("UPDATE dosen SET kuota_bimbingan = ? WHERE id_dosen = ?$w", array_merge([$kuota, (int) post('id')], $p));
        flash('success', 'Kuota bimbingan disimpan.');
    }
    redirect('dosen.php?' . http_build_query(array_filter(['cari' => post('cari'), 'hal' => post('hal')])));
}

$where = ' WHERE 1=1';
$param = [];
if (is_role('OPERATOR_PRODI')) {
    $where .= ' AND d.id_program_studi = ?';
    $param[] = (int) $u['id_program_studi'];
}
$cari = get('cari');
if ($cari !== '') {
    $where .= ' AND (d.nama_dosen LIKE ? OR d.nomor_registrasi LIKE ? OR d.nip LIKE ?)';
    array_push($param, "%$cari%", "%$cari%", "%$cari%");
}
$pg = paginasi((int) db_val("SELECT COUNT(*) FROM dosen d $where", $param), 15);
$rows = db_rows("SELECT d.*, (SELECT COUNT(*) FROM pembimbing pb WHERE pb.id_dosen = d.id_dosen
                    AND NOT EXISTS (SELECT 1 FROM sidang_skripsi s WHERE s.id_pengajuan = pb.id_pengajuan AND s.status = 'Selesai' AND s.hasil <> 'Tidak Lulus')) AS aktif
                   FROM dosen d $where ORDER BY d.status_aktif, d.nama_dosen LIMIT ? OFFSET ?", array_merge($param, [$pg['per'], $pg['offset']]));
$terakhir = db_val('SELECT MAX(terakhir_sinkron) FROM dosen');

layout_start('Data Dosen', 'dosen', 'Data dosen berasal dari <b>SIM Dosen</b> lewat web service JSON. Ubah data dosen di SIM Dosen, lalu sinkronkan di sini.',
    '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="aksi" value="sinkron"><button class="btn" type="submit">' . icon('refresh') . 'Sinkron dari SIM Dosen</button></form>'
    . '<a class="btn btn-outline" href="' . e(SIMDOSEN_URL) . '" target="_blank" rel="noopener">Buka SIM Dosen</a>');
?>
<section class="card">
  <form class="filters" method="get">
    <div class="search"><?= icon('search') ?><label class="sr-only" for="cari">Cari</label><input class="input" id="cari" name="cari" type="search" value="<?= e($cari) ?>" placeholder="Cari nama, nomor registrasi, atau NIP…"></div>
    <button class="btn btn-outline" type="submit"><?= icon('search') ?>Cari</button>
    <span class="small muted" style="align-self:center">Sinkron terakhir: <?= tgl_jam($terakhir) ?></span>
  </form>
  <?php if (!$rows): ?>
    <?= kosong($cari ? 'Tidak ada dosen yang cocok.' : 'Belum ada data dosen. Tekan "Sinkron dari SIM Dosen" untuk mengambil data.') ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Nama</th><th>Nomor Registrasi</th><th>Prodi</th><th>Jabatan</th><th>Status (SIM Dosen)</th><th>Bimbingan aktif / kuota</th><th>Login terakhir</th></tr></thead>
    <tbody><?php foreach ($rows as $d): ?>
      <tr><td><b><?= e(nama_dosen($d)) ?></b><br><span class="small muted"><?= e($d['email'] ?: '-') ?></span></td>
        <td class="small"><?= e($d['nomor_registrasi']) ?><?= $d['nip'] ? '<br>NIP ' . e($d['nip']) : '' ?></td>
        <td><?= e($d['nama_program_studi'] ?: '-') ?><?= $d['kode_program_studi'] && !$d['id_program_studi'] ? '<br><span class="small" style="color:var(--red)">kode ' . e($d['kode_program_studi']) . ' tidak ada di SIM Mahasiswa</span>' : '' ?></td>
        <td><?= e($d['jabatan_fungsional'] ?: '-') ?></td>
        <td><?= badge($d['status_aktif']) ?><?= $d['status_aktivitas'] !== 'Aktif' ? '<br><span class="small muted">' . e($d['status_aktivitas']) . '</span>' : '' ?></td>
        <td><form method="post" class="meter-row"><?= csrf_field() ?><input type="hidden" name="aksi" value="kuota"><input type="hidden" name="id" value="<?= (int) $d['id_dosen'] ?>">
            <input type="hidden" name="cari" value="<?= e($cari) ?>"><input type="hidden" name="hal" value="<?= (int) $pg['hal'] ?>">
            <span class="nowrap"><?= (int) $d['aktif'] ?> /</span>
            <label class="sr-only" for="k<?= (int) $d['id_dosen'] ?>">Kuota <?= e($d['nama_dosen']) ?></label>
            <input class="input" style="width:70px;height:34px" id="k<?= (int) $d['id_dosen'] ?>" name="kuota_bimbingan" type="number" min="1" max="30" value="<?= (int) $d['kuota_bimbingan'] ?>">
            <button class="btn btn-sm btn-outline" type="submit">Simpan</button></form></td>
        <td class="small nowrap"><?= tgl_jam($d['last_login']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
