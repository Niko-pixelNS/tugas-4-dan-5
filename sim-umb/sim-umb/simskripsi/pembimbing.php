<?php
/**
 * pembimbing.php
 *   Staf : rekap beban bimbingan per dosen + daftar penetapan pembimbing
 *   Dosen: daftar mahasiswa yang dibimbing
 * Penetapan pembimbing dilakukan di halaman detail pengajuan.
 */
require __DIR__ . '/inc/init.php';
$u = require_login('ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'DOSEN');

const SQL_AKTIF = "NOT EXISTS (SELECT 1 FROM sidang_skripsi s WHERE s.id_pengajuan = pb.id_pengajuan AND s.status = 'Selesai' AND s.hasil <> 'Tidak Lulus')";

/* =====================================================================
   DOSEN
   ===================================================================== */
if ($u['role'] === 'DOSEN') {
    $rows = db_rows(
        "SELECT pb.peran, pj.*, m.nama_lengkap, m.npm, m.nama_program_studi,
                (SELECT MAX(b.tanggal) FROM bimbingan b WHERE b.id_pengajuan = pj.id_pengajuan AND b.id_dosen = pb.id_dosen) AS bimbingan_terakhir,
                (SELECT COUNT(*) FROM bimbingan b WHERE b.id_pengajuan = pj.id_pengajuan AND b.id_dosen = pb.id_dosen) AS jml_bimbingan,
                (SELECT COUNT(*) FROM bimbingan b WHERE b.id_pengajuan = pj.id_pengajuan AND b.id_dosen = pb.id_dosen AND b.status = 'Menunggu') AS menunggu
           FROM pembimbing pb
           JOIN pengajuan_judul pj ON pj.id_pengajuan = pb.id_pengajuan
           LEFT JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
          WHERE pb.id_dosen = ?
          ORDER BY " . SQL_AKTIF . " DESC, m.nama_lengkap", [$u['id']]);

    layout_start('Mahasiswa Bimbingan', 'pembimbing', 'Mahasiswa yang Anda bimbing beserta tahapannya.');
    echo '<section class="card">';
    if (!$rows) {
        echo kosong('Anda belum ditetapkan sebagai pembimbing.');
    } else { ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>No</th><th>Mahasiswa</th><th>Judul</th><th>Peran</th><th>Tahap</th><th>Bimbingan</th><th>Aksi</th></tr></thead>
        <tbody><?php foreach ($rows as $i => $r): $t = tahap($r); ?>
          <tr><td><?= $i + 1 ?></td><td><b><?= e($r['nama_lengkap']) ?></b><br><span class="small muted"><?= e($r['npm']) ?> · <?= e($r['nama_program_studi']) ?></span></td>
            <td class="judul" title="<?= e($r['judul']) ?>"><?= e($r['judul']) ?></td><td class="nowrap"><?= e($r['peran']) ?></td><td><?= badge($t['label']) ?></td>
            <td class="nowrap"><?= (int) $r['jml_bimbingan'] ?>× <?= $r['menunggu'] ? '<span class="badge st-bimbingan">' . (int) $r['menunggu'] . ' menunggu</span>' : '' ?><br><span class="small muted">Terakhir: <?= tgl($r['bimbingan_terakhir']) ?></span></td>
            <td><div class="aksi"><a class="icon-btn view" href="pengajuan.php?id=<?= (int) $r['id_pengajuan'] ?>" aria-label="Detail"><?= icon('eye') ?></a>
              <a class="icon-btn edit" href="bimbingan.php?id_pengajuan=<?= (int) $r['id_pengajuan'] ?>" aria-label="Bimbingan"><?= icon('chat') ?></a></div></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    <?php }
    echo '</section>';
    layout_end();
    exit;
}

/* =====================================================================
   STAF
   ===================================================================== */
[$sc, $sp] = scope_staf('m');
$idDosen = (int) get('dosen');

$beban = db_rows(
    "SELECT d.id_dosen, d.gelar_depan, d.nama_dosen, d.gelar_belakang, d.nip, d.kuota_bimbingan, d.status_aktif,
            COUNT(CASE WHEN pb.id_pembimbing IS NOT NULL AND " . SQL_AKTIF . " THEN 1 END) AS aktif,
            SUM(pb.peran = 'Pembimbing 1') AS p1, SUM(pb.peran = 'Pembimbing 2') AS p2, COUNT(pb.id_pembimbing) AS total
       FROM dosen d LEFT JOIN pembimbing pb ON pb.id_dosen = d.id_dosen
      GROUP BY d.id_dosen ORDER BY d.status_aktif, aktif DESC, d.nama_dosen");

$where = "FROM pembimbing pb JOIN pengajuan_judul pj ON pj.id_pengajuan = pb.id_pengajuan
          JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa JOIN dosen d ON d.id_dosen = pb.id_dosen WHERE 1=1 $sc";
$param = $sp;
if ($idDosen) {
    $where .= ' AND pb.id_dosen = ?';
    $param[] = $idDosen;
}
$pg = paginasi((int) db_val("SELECT COUNT(*) $where", $param), 15);
$penetapan = db_rows("SELECT pb.*, pj.judul, pj.id_pengajuan, m.nama_lengkap, m.npm, m.nama_program_studi, d.gelar_depan, d.nama_dosen, d.gelar_belakang
                      $where ORDER BY pb.created_at DESC LIMIT ? OFFSET ?", array_merge($param, [$pg['per'], $pg['offset']]));
$belum = (int) db_val("SELECT COUNT(*) FROM pengajuan_judul pj JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
                       WHERE pj.status = 'Disetujui' AND NOT EXISTS (SELECT 1 FROM pembimbing pb WHERE pb.id_pengajuan = pj.id_pengajuan) $sc", $sp);

layout_start('Pembimbing', 'pembimbing', 'Beban bimbingan dosen dan penetapan pembimbing. Penetapan dilakukan dari detail pengajuan.');
if ($belum) echo '<div class="alert alert-warning">' . $belum . ' judul sudah disetujui tetapi belum punya pembimbing. <a class="link" href="pengajuan.php?status=Disetujui">Tetapkan sekarang</a>.</div>';
?>
<section class="card mb">
  <div class="card-head card-pad" style="margin:0"><h2>Beban Bimbingan Dosen</h2><span class="card-sub">Aktif = belum lulus sidang</span></div>
  <?php if (!$beban): ?><?= kosong('Belum ada data dosen.', is_pengelola() ? '<a class="btn" href="dosen.php">Tambah dosen</a>' : '') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Dosen</th><th>NIP</th><th>Pemb. 1</th><th>Pemb. 2</th><th>Bimbingan aktif / kuota</th><th></th></tr></thead>
    <tbody><?php foreach ($beban as $d): $pct = $d['kuota_bimbingan'] ? min(100, round($d['aktif'] / $d['kuota_bimbingan'] * 100)) : 100; ?>
      <tr><td><?= e(nama_dosen($d)) ?><?= $d['status_aktif'] !== 'Aktif' ? ' ' . badge('Nonaktif') : '' ?></td><td><?= e($d['nip'] ?: '-') ?></td><td><?= (int) $d['p1'] ?></td><td><?= (int) $d['p2'] ?></td>
        <td><div class="meter-row"><span class="nowrap"><?= (int) $d['aktif'] ?> / <?= (int) $d['kuota_bimbingan'] ?></span>
          <div class="meter" style="flex:1;max-width:160px"><span style="width:<?= $pct ?>%;<?= $pct >= 100 ? 'background:var(--red)' : '' ?>"></span></div></div></td>
        <td><a class="link small" href="?dosen=<?= (int) $d['id_dosen'] ?>#penetapan">Lihat mahasiswa</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="card" id="penetapan">
  <div class="card-head card-pad" style="margin:0"><h2>Penetapan Pembimbing<?= $idDosen ? ' — ' . e(nama_dosen(db_row('SELECT * FROM dosen WHERE id_dosen = ?', [$idDosen]) ?? [])) : '' ?></h2>
    <?php if ($idDosen): ?><a href="pembimbing.php#penetapan">Tampilkan semua</a><?php endif; ?></div>
  <?php if (!$penetapan): ?><?= kosong('Belum ada penetapan pembimbing.') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Mahasiswa</th><th>Judul</th><th>Dosen</th><th>Peran</th><th>No. SK</th><th>Ditetapkan</th></tr></thead>
    <tbody><?php foreach ($penetapan as $p): ?>
      <tr><td><b><?= e($p['nama_lengkap']) ?></b><br><span class="small muted"><?= e($p['npm']) ?></span></td>
        <td class="judul" title="<?= e($p['judul']) ?>"><a class="link" href="pengajuan.php?id=<?= (int) $p['id_pengajuan'] ?>#pembimbing"><?= e($p['judul']) ?></a></td>
        <td><?= e(nama_dosen($p)) ?></td><td class="nowrap"><?= e($p['peran']) ?></td><td><?= e($p['nomor_sk'] ?: '-') ?></td><td class="nowrap"><?= tgl($p['created_at']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
