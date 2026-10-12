<?php
/**
 * dashboard.php — isi berbeda untuk staf (admin/prodi/dekanat), dosen, dan mahasiswa.
 */
require __DIR__ . '/inc/init.php';
$u = require_login();

/* =====================================================================
   MAHASISWA
   ===================================================================== */
if ($u['role'] === 'MAHASISWA') {
    $pj = pengajuan_aktif_mahasiswa($u['id_mahasiswa']);
    $tahap = $pj ? tahap($pj) : ['idx' => -1, 'label' => '', 'selesai' => false, 'ket' => ''];
    $pembimbing = $pj ? db_rows('SELECT pb.peran, d.* FROM pembimbing pb JOIN dosen d ON d.id_dosen = pb.id_dosen WHERE pb.id_pengajuan = ? ORDER BY pb.peran', [$pj['id_pengajuan']]) : [];
    $jadwal = $pj ? db_rows(
        "SELECT 'Seminar Proposal' AS jenis, tanggal, jam_mulai, ruangan FROM seminar_proposal WHERE id_pengajuan = ? AND status = 'Dijadwalkan'
         UNION ALL SELECT 'Sidang Skripsi', tanggal, jam_mulai, ruangan FROM sidang_skripsi WHERE id_pengajuan = ? AND status = 'Dijadwalkan'
         ORDER BY tanggal", [$pj['id_pengajuan'], $pj['id_pengajuan']]) : [];
    $dok = db_rows(
        "SELECT jd.tahap, SUM(jd.wajib = 1) AS wajib, SUM(jd.wajib = 1 AND d.status_verifikasi = 'Valid') AS valid
           FROM jenis_dokumen jd LEFT JOIN dokumen d ON d.id_jenis = jd.id_jenis AND d.id_mahasiswa = ?
          WHERE jd.status_aktif = 'Aktif' GROUP BY jd.tahap ORDER BY MIN(jd.urutan)", [$u['id_mahasiswa']]);
    $bimb = $pj ? (int) db_val('SELECT COUNT(*) FROM bimbingan WHERE id_pengajuan = ?', [$pj['id_pengajuan']]) : 0;
    $info = pengumuman_tampil($u, 3);

    layout_start('Dashboard Mahasiswa', 'dashboard', 'Selamat datang, ' . e($u['nama']) . ' · NPM ' . e($u['npm']));
    ?>
    <section class="card card-pad">
      <div class="card-head"><h2>Status Skripsi Saya</h2><?php if ($pj): ?><?= badge($pj['status']) ?><?php endif; ?></div>
      <?php if ($pj): ?>
        <?= stepper_html($tahap) ?>
      <?php else: ?>
        <?= kosong('Anda belum memiliki pengajuan judul yang berjalan.', '<a class="btn" href="pengajuan.php?aksi=baru">' . icon('plus') . 'Ajukan judul</a>') ?>
      <?php endif; ?>
    </section>

    <div class="mhs-grid">
      <section class="card card-pad">
        <div class="card-head"><h2>Informasi Proposal</h2><?php if ($pj): ?><a href="pengajuan.php?id=<?= (int) $pj['id_pengajuan'] ?>">Lihat detail</a><?php endif; ?></div>
        <?php if ($pj): ?>
          <?= info_list([
              'Judul' => e($pj['judul']),
              'Pembimbing' => $pembimbing ? implode('<br>', array_map(fn ($d) => e($d['peran'] . ': ' . nama_dosen($d)), $pembimbing)) : '<span class="muted">Belum ditetapkan</span>',
              'Program Studi' => e($u['nama_program_studi']),
              'Tanggal Pengajuan' => tgl($pj['tanggal_pengajuan'], true),
              'Status' => badge($pj['status']) . ($pj['catatan_prodi'] ? '<div class="note"><b>Catatan prodi</b>' . e($pj['catatan_prodi']) . '</div>' : ''),
          ]) ?>
          <?php if ($jadwal): ?>
            <h3 class="section-title mt">Jadwal Mendatang</h3>
            <?php foreach ($jadwal as $j): ?>
              <p><b><?= e($j['jenis']) ?></b> — <?= hari_tgl($j['tanggal']) ?>, <?= jam($j['jam_mulai']) ?> WIB, <?= e($j['ruangan']) ?></p>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php else: ?>
          <p class="muted">Belum ada data proposal.</p>
        <?php endif; ?>
      </section>

      <section class="card card-pad">
        <div class="card-head"><h2>Menu Cepat</h2></div>
        <nav class="quick" aria-label="Menu cepat">
          <a href="pengajuan.php"><span class="act-ic"><?= icon('fileEdit') ?></span><span><?= $pj ? 'Lihat Proposal Saya' : 'Ajukan Judul' ?></span><?= icon('chevRight') ?></a>
          <a href="bimbingan.php"><span class="act-ic"><?= icon('chat') ?></span><span>Catatan Bimbingan (<?= $bimb ?>)</span><?= icon('chevRight') ?></a>
          <a href="dokumen.php"><span class="act-ic"><?= icon('upload') ?></span><span>Unggah Dokumen</span><?= icon('chevRight') ?></a>
          <a href="ujian.php?jenis=seminar"><span class="act-ic"><?= icon('calendar') ?></span><span>Jadwal Seminar &amp; Sidang</span><?= icon('chevRight') ?></a>
        </nav>
        <h3 class="section-title mt">Kelengkapan Dokumen</h3>
        <?php foreach ($dok as $d): $w = max(1, (int) $d['wajib']); ?>
          <div class="meter-row mb"><span style="flex:1"><?= e($d['tahap']) ?></span><span class="small muted nowrap"><?= (int) $d['valid'] ?>/<?= (int) $d['wajib'] ?> valid</span>
            <div class="meter" style="width:90px"><span style="width:<?= round((int) $d['valid'] / $w * 100) ?>%"></span></div></div>
        <?php endforeach; ?>
      </section>
    </div>

    <?php if ($info): ?>
    <section class="card mt">
      <div class="card-head card-pad" style="margin:0"><h2>Pengumuman Terbaru</h2><a href="pengumuman.php">Lihat semua</a></div>
      <?php foreach ($info as $a): ?>
        <article class="ann"><h3><?= e($a['judul']) ?></h3><div class="meta"><?= badge($a['kategori']) ?><span><?= tgl($a['tanggal_terbit']) ?></span></div><p><?= e(mb_strimwidth($a['isi'], 0, 220, '…')) ?></p></article>
      <?php endforeach; ?>
    </section>
    <?php endif;
    layout_end();
    exit;
}

/* =====================================================================
   DOSEN
   ===================================================================== */
if ($u['role'] === 'DOSEN') {
    $idDosen = $u['id'];
    $jmlBimbingan = (int) db_val(
        "SELECT COUNT(*) FROM pembimbing pb JOIN pengajuan_judul pj ON pj.id_pengajuan = pb.id_pengajuan
          WHERE pb.id_dosen = ? AND NOT EXISTS (SELECT 1 FROM sidang_skripsi s WHERE s.id_pengajuan = pj.id_pengajuan AND s.status = 'Selesai' AND s.hasil <> 'Tidak Lulus')",
        [$idDosen]);
    $menunggu = db_rows(
        "SELECT b.*, m.nama_lengkap, m.npm FROM bimbingan b
           JOIN pengajuan_judul pj ON pj.id_pengajuan = b.id_pengajuan
           LEFT JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
          WHERE b.id_dosen = ? AND b.status = 'Menunggu' ORDER BY b.tanggal LIMIT 6", [$idDosen]);
    $jmlMenunggu = (int) db_val("SELECT COUNT(*) FROM bimbingan WHERE id_dosen = ? AND status = 'Menunggu'", [$idDosen]);
    $ujian = db_rows(
        "SELECT 'seminar' AS jenis, u.id_seminar AS id, u.tanggal, u.jam_mulai, u.ruangan, m.nama_lengkap, pj.judul
           FROM seminar_proposal u JOIN pengajuan_judul pj ON pj.id_pengajuan = u.id_pengajuan LEFT JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
          WHERE u.status = 'Dijadwalkan' AND u.tanggal >= CURDATE() AND ? IN (u.id_penguji_1, IFNULL(u.id_penguji_2, 0))
         UNION ALL
         SELECT 'sidang', u.id_sidang, u.tanggal, u.jam_mulai, u.ruangan, m.nama_lengkap, pj.judul
           FROM sidang_skripsi u JOIN pengajuan_judul pj ON pj.id_pengajuan = u.id_pengajuan LEFT JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
          WHERE u.status = 'Dijadwalkan' AND u.tanggal >= CURDATE() AND ? IN (u.id_penguji_1, u.id_penguji_2, IFNULL(u.id_penguji_3, 0))
         ORDER BY tanggal, jam_mulai LIMIT 6", [$idDosen, $idDosen]);
    $kuota = (int) db_val('SELECT kuota_bimbingan FROM dosen WHERE id_dosen = ?', [$idDosen]);

    layout_start('Dashboard Dosen', 'dashboard', 'Selamat datang, ' . e($u['nama']));
    ?>
    <div class="stats" style="grid-template-columns:repeat(3,1fr)">
      <div class="card stat"><div class="stat-ic bg-blue"><?= icon('users') ?></div><div class="stat-value"><?= $jmlBimbingan ?><small class="muted" style="font-size:15px"> / <?= $kuota ?></small></div><div class="stat-label">Mahasiswa bimbingan aktif</div></div>
      <div class="card stat"><div class="stat-ic bg-amber"><?= icon('chat') ?></div><div class="stat-value"><?= $jmlMenunggu ?></div><div class="stat-label">Bimbingan menunggu respons</div></div>
      <div class="card stat"><div class="stat-ic bg-green"><?= icon('calendar') ?></div><div class="stat-value"><?= count($ujian) ?></div><div class="stat-label">Jadwal menguji mendatang</div></div>
    </div>
    <div class="grid-2">
      <section class="card">
        <div class="card-head card-pad" style="margin:0"><h2>Bimbingan Menunggu Respons</h2><a href="bimbingan.php?status=Menunggu">Lihat semua</a></div>
        <?php if (!$menunggu): ?><?= kosong('Tidak ada bimbingan yang menunggu.') ?><?php else: ?>
        <ul class="activity card-body">
          <?php foreach ($menunggu as $b): ?>
            <li><span class="act-ic st-bimbingan"><?= icon('chat') ?></span><div><p><a class="link" href="bimbingan.php?id=<?= (int) $b['id_bimbingan'] ?>"><?= e($b['nama_lengkap']) ?></a> — <?= e($b['topik']) ?></p><time><?= tgl($b['tanggal']) ?></time></div></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </section>
      <section class="card">
        <div class="card-head card-pad" style="margin:0"><h2>Jadwal Menguji</h2></div>
        <?php if (!$ujian): ?><?= kosong('Belum ada jadwal menguji.') ?><?php else: ?>
        <ul class="activity card-body">
          <?php foreach ($ujian as $j): ?>
            <li><span class="act-ic <?= $j['jenis'] === 'seminar' ? 'st-seminar' : 'st-sidang' ?>"><?= icon($j['jenis'] === 'seminar' ? 'monitor' : 'award') ?></span>
              <div><p><a class="link" href="ujian.php?jenis=<?= $j['jenis'] ?>&amp;id=<?= (int) $j['id'] ?>"><?= $j['jenis'] === 'seminar' ? 'Seminar' : 'Sidang' ?> — <?= e($j['nama_lengkap']) ?></a></p>
              <time><?= hari_tgl($j['tanggal']) ?>, <?= jam($j['jam_mulai']) ?> · <?= e($j['ruangan']) ?></time></div></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </section>
    </div>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   STAF: ADMIN, OPERATOR PRODI, DEKANAT
   ===================================================================== */
[$sc, $sp] = scope_staf('m');
$dari = " FROM pengajuan_judul pj JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa WHERE 1=1 $sc";

$perStatus = array_fill_keys(['Diajukan', 'Revisi', 'Disetujui', 'Ditolak'], 0);
foreach (db_rows('SELECT pj.status, COUNT(*) AS n' . $dari . ' GROUP BY pj.status', $sp) as $r) $perStatus[$r['status']] = (int) $r['n'];
$total = array_sum($perStatus);
$jadwalMendatang = (int) db_val(
    "SELECT (SELECT COUNT(*) FROM seminar_proposal u JOIN pengajuan_judul pj ON pj.id_pengajuan = u.id_pengajuan JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa WHERE u.status = 'Dijadwalkan' AND u.tanggal >= CURDATE() $sc)
          + (SELECT COUNT(*) FROM sidang_skripsi u JOIN pengajuan_judul pj ON pj.id_pengajuan = u.id_pengajuan JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa WHERE u.status = 'Dijadwalkan' AND u.tanggal >= CURDATE() $sc)",
    array_merge($sp, $sp));
$lulus = (int) db_val("SELECT COUNT(DISTINCT u.id_pengajuan) FROM sidang_skripsi u JOIN pengajuan_judul pj ON pj.id_pengajuan = u.id_pengajuan JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
                        WHERE u.status = 'Selesai' AND u.hasil <> 'Tidak Lulus' $sc", $sp);
$dokMenunggu = (int) db_val("SELECT COUNT(*) FROM dokumen d JOIN pengguna_sso m ON m.id_mahasiswa = d.id_mahasiswa WHERE d.status_verifikasi = 'Menunggu' $sc", $sp);
$terbaru = db_rows('SELECT pj.id_pengajuan, pj.judul, pj.status, pj.tanggal_pengajuan, m.nama_lengkap, m.npm, m.nama_program_studi' . $dari . ' ORDER BY pj.tanggal_pengajuan DESC LIMIT 5', $sp);
$aktivitas = db_rows(
    "SELECT * FROM (
        SELECT pj.tanggal_pengajuan AS waktu, 'pengajuan' AS jenis, m.nama_lengkap AS siapa, pj.judul AS isi, pj.id_pengajuan AS id
          FROM pengajuan_judul pj JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa WHERE 1=1 $sc
        UNION ALL
        SELECT d.tanggal_upload, 'dokumen', m.nama_lengkap, jd.nama_dokumen, d.id_mahasiswa
          FROM dokumen d JOIN jenis_dokumen jd ON jd.id_jenis = d.id_jenis JOIN pengguna_sso m ON m.id_mahasiswa = d.id_mahasiswa WHERE 1=1 $sc
        UNION ALL
        SELECT b.created_at, 'bimbingan', m.nama_lengkap, b.topik, b.id_bimbingan
          FROM bimbingan b JOIN pengajuan_judul pj ON pj.id_pengajuan = b.id_pengajuan JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa WHERE 1=1 $sc
     ) x ORDER BY waktu DESC LIMIT 5", array_merge($sp, $sp, $sp));

$warna = ['Diajukan' => '#2f6fe4', 'Revisi' => '#f0ac00', 'Disetujui' => '#1f9d55', 'Ditolak' => '#d9363b'];
$stop = [];
$mulai = 0;
foreach ($perStatus as $s => $n) {
    if (!$total) break;
    $akhir = $mulai + $n / $total * 360;
    $stop[] = $warna[$s] . ' ' . round($mulai, 2) . 'deg ' . round($akhir, 2) . 'deg';
    $mulai = $akhir;
}
$cakupan = match ($u['role']) {
    'OPERATOR_PRODI' => 'Program Studi ' . e($u['nama_program_studi']),
    'DEKANAT' => e($u['nama_fakultas'] ?? 'Fakultas'),
    default => 'Seluruh program studi',
};

layout_start('Dashboard', 'dashboard', 'Selamat datang di Sistem Informasi Skripsi · ' . $cakupan);
?>
<div class="stats">
  <div class="card stat"><div class="stat-ic bg-blue"><?= icon('file') ?></div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total pengajuan judul</div></div>
  <a class="card stat" href="pengajuan.php?status=Diajukan"><div class="stat-ic bg-amber"><?= icon('fileEdit') ?></div><div class="stat-value"><?= $perStatus['Diajukan'] ?></div><div class="stat-label">Menunggu diproses</div></a>
  <a class="card stat" href="dokumen.php"><div class="stat-ic bg-green"><?= icon('folder') ?></div><div class="stat-value"><?= $dokMenunggu ?></div><div class="stat-label">Dokumen perlu verifikasi</div></a>
  <div class="card stat"><div class="stat-ic bg-red"><?= icon('award') ?></div><div class="stat-value"><?= $lulus ?></div><div class="stat-label">Lulus sidang · <?= $jadwalMendatang ?> jadwal mendatang</div></div>
</div>

<div class="grid-2">
  <section class="card card-pad">
    <div class="card-head"><h2>Status Pengajuan Judul</h2></div>
    <div class="donut-wrap">
      <div class="donut" role="img" aria-label="<?= e(implode(', ', array_map(fn ($s, $n) => "$s $n", array_keys($perStatus), $perStatus))) ?>"
           style="background:<?= $total ? 'conic-gradient(' . implode(', ', $stop) . ')' : 'var(--gray-soft)' ?>">
        <div class="donut-hole"><div><strong><?= $total ?></strong><span>Pengajuan</span></div></div>
      </div>
      <ul class="legend">
        <?php foreach ($perStatus as $s => $n): ?><li><i style="background:<?= $warna[$s] ?>"></i><?= $s ?><b><?= $n ?></b></li><?php endforeach; ?>
      </ul>
    </div>
  </section>
  <section class="card card-pad">
    <div class="card-head"><h2>Aktivitas Terbaru</h2></div>
    <?php if (!$aktivitas): ?><?= kosong('Belum ada aktivitas.') ?><?php else: ?>
    <ul class="activity">
      <?php foreach ($aktivitas as $a):
          [$ikon, $kelas, $teks] = match ($a['jenis']) {
              'pengajuan' => ['fileEdit', 'st-diajukan', 'mengajukan judul'],
              'dokumen'   => ['upload', 'st-disetujui', 'mengunggah ' . $a['isi']],
              default     => ['chat', 'st-bimbingan', 'mengisi bimbingan'],
          }; ?>
        <li><span class="act-ic <?= $kelas ?>"><?= icon($ikon) ?></span><div><p><b><?= e($a['siapa']) ?></b> <?= e($teks) ?></p><time><?= tgl_jam($a['waktu']) ?></time></div></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
</div>

<section class="card">
  <div class="card-head card-pad" style="margin:0;padding-bottom:14px"><h2>Pengajuan Terbaru</h2><a href="pengajuan.php">Lihat semua</a></div>
  <?php if (!$terbaru): ?><?= kosong('Belum ada pengajuan judul.') ?><?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>No</th><th>Nama</th><th>NPM</th><th>Judul</th><th>Prodi</th><th>Status</th><th>Tanggal</th></tr></thead>
      <tbody>
        <?php foreach ($terbaru as $i => $p): ?>
          <tr><td><?= $i + 1 ?></td><td><?= e($p['nama_lengkap']) ?></td><td><?= e($p['npm']) ?></td>
            <td class="judul" title="<?= e($p['judul']) ?>"><a class="link" href="pengajuan.php?id=<?= (int) $p['id_pengajuan'] ?>"><?= e($p['judul']) ?></a></td>
            <td><?= e($p['nama_program_studi']) ?></td><td><?= badge($p['status']) ?></td><td class="nowrap"><?= tgl($p['tanggal_pengajuan']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php
layout_end();
