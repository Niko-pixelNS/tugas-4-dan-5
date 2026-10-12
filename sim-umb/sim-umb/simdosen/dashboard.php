<?php
/**
 * dashboard.php — ringkasan data dosen sesuai cakupan peran.
 */
require __DIR__ . '/inc/init.php';
$u = wajib_izin('dashboard');

/* ----- Dosen: ringkasan data diri ----- */
if ($u['role'] === 'DOSEN') {
    $d = db_row('SELECT * FROM v_dosen WHERE id_dosen = ?', [$u['id_dosen']]);
    layout_start('Dashboard Dosen', 'dashboard', 'Selamat datang, ' . e($d['nama_lengkap'] ?? $u['nama']));
    ?>
    <div class="stats">
      <div class="card stat"><div class="stat-ic bg-blue"><?= icon('award') ?></div><div class="stat-value" style="font-size:20px"><?= e($d['jabatan_fungsional']) ?></div><div class="stat-label">Jabatan fungsional</div></div>
      <div class="card stat"><div class="stat-ic bg-green"><?= icon('check2') ?></div><div class="stat-value" style="font-size:20px"><?= e($d['pendidikan_terakhir']) ?></div><div class="stat-label">Pendidikan terakhir</div></div>
      <div class="card stat"><div class="stat-ic bg-amber"><?= icon('clipboard') ?></div><div class="stat-value" style="font-size:20px"><?= e($d['status_serdos']) ?></div><div class="stat-label">Sertifikasi dosen</div></div>
      <div class="card stat"><div class="stat-ic bg-red"><?= icon('user') ?></div><div class="stat-value" style="font-size:20px"><?= e($d['status_aktivitas']) ?></div><div class="stat-label">Status aktivitas</div></div>
    </div>
    <section class="card card-pad">
      <div class="card-head"><h2>Data Saya</h2><a href="profil.php">Lihat lengkap &amp; perbarui kontak</a></div>
      <?= info_list([
          'Nomor registrasi' => e($d['jenis_registrasi'] . ' ' . $d['nomor_registrasi']),
          'NUPTK' => e($d['nuptk'] ?: '-'),
          'Kepangkatan' => e($d['kepangkatan'] ?: '-'),
          'Penempatan' => e($d['penempatan'] . ' · ' . $d['nama_fakultas']),
      ]) ?>
    </section>
    <?php
    layout_end();
    exit;
}

/* ----- Staf: statistik dalam cakupannya ----- */
[$sc, $sp] = scope_dosen('v');
$dari = "FROM v_dosen v WHERE 1=1 $sc";
$total = (int) db_val("SELECT COUNT(*) $dari", $sp);
$aktif = (int) db_val("SELECT COUNT(*) $dari AND v.status_aktivitas = 'Aktif'", $sp);
$serdos = (int) db_val("SELECT COUNT(*) $dari AND v.status_aktivitas = 'Aktif' AND v.status_serdos = 'Sudah'", $sp);
$s3 = (int) db_val("SELECT COUNT(*) $dari AND v.status_aktivitas = 'Aktif' AND v.pendidikan_terakhir = 'S3'", $sp);
$perJabatan = db_rows("SELECT jf.nama, (SELECT COUNT(*) $dari AND v.status_aktivitas = 'Aktif' AND v.id_jabatan_fungsional = jf.id_jabatan_fungsional) AS n
                         FROM jabatan_fungsional jf ORDER BY jf.urutan", $sp);
$perStatus = db_rows("SELECT v.status_aktivitas AS nama, COUNT(*) AS n $dari GROUP BY v.status_aktivitas ORDER BY n DESC", $sp);
$perProdi = db_rows("SELECT v.nama_program_studi, v.nama_fakultas, COUNT(*) AS n, SUM(v.status_serdos = 'Sudah') AS serdos
                     $dari AND v.status_aktivitas = 'Aktif' GROUP BY v.id_program_studi ORDER BY v.nama_fakultas, v.nama_program_studi", $sp);
$terbaru = db_rows("SELECT * $dari ORDER BY v.updated_at DESC LIMIT 5", $sp);
$cakupan = 'Seluruh universitas';
$maks = max(1, ...array_map(fn ($r) => (int) $r['n'], $perJabatan ?: [['n' => 1]]));

layout_start('Dashboard', 'dashboard', 'Ringkasan data dosen · ' . $cakupan);
?>
<div class="stats">
  <div class="card stat"><div class="stat-ic bg-blue"><?= icon('users') ?></div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total dosen tercatat</div></div>
  <div class="card stat"><div class="stat-ic bg-green"><?= icon('check') ?></div><div class="stat-value"><?= $aktif ?></div><div class="stat-label">Dosen aktif</div></div>
  <div class="card stat"><div class="stat-ic bg-amber"><?= icon('award') ?></div><div class="stat-value"><?= $serdos ?></div><div class="stat-label">Sudah serdos (<?= $aktif ? round($serdos / $aktif * 100) : 0 ?>%)</div></div>
  <div class="card stat"><div class="stat-ic bg-red"><?= icon('check2') ?></div><div class="stat-value"><?= $s3 ?></div><div class="stat-label">Berpendidikan S3</div></div>
</div>
<div class="grid-2">
  <section class="card card-pad">
    <div class="card-head"><h2>Dosen Aktif per Jabatan Fungsional</h2></div>
    <?php foreach ($perJabatan as $j): ?>
      <div class="meter-row mb"><span style="width:130px"><?= e($j['nama']) ?></span><div class="meter" style="flex:1"><span style="width:<?= round($j['n'] / $maks * 100) ?>%;background:var(--primary)"></span></div><b style="width:28px;text-align:right"><?= (int) $j['n'] ?></b></div>
    <?php endforeach; ?>
  </section>
  <section class="card card-pad">
    <div class="card-head"><h2>Status Aktivitas</h2></div>
    <?php if (!$perStatus): ?><?= kosong('Belum ada data dosen.') ?><?php else: ?>
    <ul class="legend"><?php foreach ($perStatus as $s): ?><li><?= badge($s['nama']) ?><b><?= (int) $s['n'] ?></b></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
</div>
<?php if ($perProdi): ?>
<section class="card mb">
  <div class="card-head card-pad" style="margin:0"><h2>Dosen Aktif per Program Studi</h2><a href="laporan.php">Buka laporan</a></div>
  <div class="table-wrap"><table class="table"><thead><tr><th>Fakultas</th><th>Program Studi</th><th>Dosen aktif</th><th>Sudah serdos</th></tr></thead><tbody>
    <?php foreach ($perProdi as $p): ?><tr><td><?= e($p['nama_fakultas']) ?></td><td><?= e($p['nama_program_studi']) ?></td><td><?= (int) $p['n'] ?></td><td><?= (int) $p['serdos'] ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>
<section class="card">
  <div class="card-head card-pad" style="margin:0"><h2>Terakhir Diperbarui</h2><a href="dosen.php">Lihat semua</a></div>
  <?php if (!$terbaru): ?><?= kosong('Belum ada data dosen.') ?><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Nomor Registrasi</th><th>Nama</th><th>Jabatan</th><th>Penempatan</th><th>Status</th><th>Diperbarui</th></tr></thead><tbody>
    <?php foreach ($terbaru as $d): ?><tr><td><?= e($d['nomor_registrasi']) ?></td><td><a class="link" href="dosen.php?id=<?= (int) $d['id_dosen'] ?>"><?= e($d['nama_lengkap']) ?></a></td><td><?= e($d['jabatan_fungsional']) ?></td><td><?= e($d['penempatan']) ?></td><td><?= badge($d['status_aktivitas']) ?></td><td class="nowrap"><?= tgl_jam($d['updated_at']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</section>
<?php
layout_end();
