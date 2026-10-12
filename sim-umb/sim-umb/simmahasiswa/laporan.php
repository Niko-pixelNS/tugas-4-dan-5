<?php
/**
 * laporan.php — Rekap laporan mahasiswa sesuai cakupan akses pengguna.
 * Filter: fakultas, program studi, status, jenis kelamin, NPM/nama. Tombol Cetak memakai tampilan cetak (CSS @media print).
 */
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/layout.php';

$user = wajib_login($koneksi);
wajib_izin($user, 'laporan');
const BATAS_LAPORAN = 5000;

/* Program studi & fakultas dalam cakupan (untuk pilihan filter) */
[$wP, $pP] = cakupan_sql($user['cakupan'], 'f.id_universitas', 'ps.id_fakultas', 'ps.id_program_studi');
$prodiCakupan = db_rows($koneksi, "SELECT ps.id_program_studi, ps.kode_program_studi, ps.nama_program_studi, f.id_fakultas, f.kode_fakultas, f.nama_fakultas
                                     FROM program_studi ps JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
                                    WHERE $wP ORDER BY f.kode_fakultas, ps.kode_program_studi", $pP);
$fakultasOpt = [];
foreach ($prodiCakupan as $p) {
    $fakultasOpt[(int) $p['id_fakultas']] = $p['kode_fakultas'] . ' - ' . $p['nama_fakultas'];
}

/* Filter (nilai di luar cakupan diabaikan) */
$fFak    = (int) ($_GET['fakultas'] ?? 0);
$fProdi  = (int) ($_GET['prodi'] ?? 0);
$fStatus = in_array($_GET['status'] ?? '', STATUS_MHS, true) ? (string) $_GET['status'] : '';
$fJk     = in_array($_GET['jk'] ?? '', ['L', 'P'], true) ? (string) $_GET['jk'] : '';
$fQ      = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
if (!isset($fakultasOpt[$fFak])) $fFak = 0;
if (!in_array($fProdi, array_map('intval', array_column($prodiCakupan, 'id_program_studi')), true)) $fProdi = 0;

[$w, $params] = cakupan_sql($user['cakupan'], 'v.id_universitas', 'v.id_fakultas', 'v.id_program_studi');
if ($fFak)          { $w .= ' AND v.id_fakultas = ?';      $params[] = $fFak; }
if ($fProdi)        { $w .= ' AND v.id_program_studi = ?'; $params[] = $fProdi; }
if ($fStatus !== '') { $w .= ' AND v.status_mahasiswa = ?'; $params[] = $fStatus; }
if ($fJk !== '')    { $w .= ' AND v.jenis_kelamin = ?';    $params[] = $fJk; }
if ($fQ !== '')     { $w .= ' AND (v.npm LIKE ? OR v.nama_mahasiswa LIKE ?)'; $l = '%' . like_escape($fQ) . '%'; array_push($params, $l, $l); }

$ring = db_row($koneksi, "SELECT COUNT(*) AS total,
                                 COALESCE(SUM(v.jenis_kelamin = 'L'), 0) AS laki,
                                 COALESCE(SUM(v.jenis_kelamin = 'P'), 0) AS perempuan,
                                 COALESCE(SUM(v.status_mahasiswa = 'Aktif'), 0) AS aktif
                            FROM v_mahasiswa_per_prodi v WHERE $w", $params);
$rows = db_rows($koneksi, "SELECT v.npm, v.nama_mahasiswa, v.jenis_kelamin, v.kode_program_studi, v.nama_program_studi,
                                  v.kode_fakultas, v.nama_fakultas, v.tanggal_masuk, v.status_mahasiswa
                             FROM v_mahasiswa_per_prodi v WHERE $w
                            ORDER BY v.kode_fakultas, v.kode_program_studi, v.npm LIMIT " . BATAS_LAPORAN, $params);
$total = (int) $ring['total'];

/* Ringkasan filter untuk judul tabel & kop cetak */
$filterTeks = [];
if ($fFak) $filterTeks[] = $fakultasOpt[$fFak];
if ($fProdi) foreach ($prodiCakupan as $p) if ((int) $p['id_program_studi'] === $fProdi) $filterTeks[] = $p['kode_program_studi'] . ' - ' . $p['nama_program_studi'];
if ($fStatus !== '') $filterTeks[] = 'Status ' . $fStatus;
if ($fJk !== '') $filterTeks[] = $fJk === 'L' ? 'Laki-laki' : 'Perempuan';
if ($fQ !== '') $filterTeks[] = 'Kata kunci "' . $fQ . '"';
$judulData = $filterTeks ? implode(' · ', $filterTeks) : ($user['cakupan']['jenis'] === 'semua' ? 'Seluruh Data Mahasiswa' : $user['cakupan']['label']);
$univ = db_row($koneksi, 'SELECT nama_universitas, alamat, kota FROM universitas ORDER BY id_universitas LIMIT 1') ?? ['nama_universitas' => APP_NAME, 'alamat' => '', 'kota' => ''];
$waktu = date('d-m-Y H:i');

layout_awal($user, 'Laporan rekap', 'laporan', ['lebar' => true]);
?>
  <!-- Kop khusus cetak -->
  <div class="print-only print-kop">
    <strong><?= e($univ['nama_universitas']) ?></strong>
    <span><?= e(implode(', ', array_filter([$univ['alamat'], $univ['kota']]))) ?></span>
    <h1>Rekap Laporan Mahasiswa</h1>
    <span>Cakupan: <?= e($user['cakupan']['label']) ?> · Filter: <?= e($filterTeks ? implode(', ', $filterTeks) : 'Semua') ?></span>
    <span>Dicetak <?= e($waktu) ?> oleh <?= e($user['nama_lengkap']) ?> (<?= e($user['nama_role']) ?>)</span>
  </div>

  <div class="lap-head no-print">
    <span class="lap-eyebrow">Laporan Akademik</span>
    <h1 class="lap-h1">Rekap Laporan Mahasiswa</h1>
    <p class="lap-sub">Rekap data mahasiswa berdasarkan ruang lingkup akses pengguna.</p>
  </div>

  <section class="lap-card no-print" aria-labelledby="judul-filter">
    <h2 class="lap-h2" id="judul-filter">Filter Laporan</h2>
    <p class="lap-scope">Scope akses: <span class="lap-badge"><?= e($user['cakupan']['jenis'] === 'semua' ? 'Seluruh Data Mahasiswa' : $user['cakupan']['label']) ?></span></p>
    <form class="lap-form" method="get" action="laporan.php">
      <div class="lap-grid">
        <div class="lap-fld">
          <label for="f-fakultas">Fakultas</label>
          <select id="f-fakultas" name="fakultas">
            <option value="">-- Semua Fakultas --</option>
            <?php foreach ($fakultasOpt as $id => $lbl): ?><option value="<?= $id ?>"<?= $fFak === $id ? ' selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="lap-fld">
          <label for="f-prodi">Program Studi</label>
          <select id="f-prodi" name="prodi" data-induk-dari="f-fakultas">
            <option value="">-- Semua Program Studi --</option>
            <?php foreach ($prodiCakupan as $p): ?><option value="<?= (int) $p['id_program_studi'] ?>" data-induk="<?= (int) $p['id_fakultas'] ?>"<?= $fProdi === (int) $p['id_program_studi'] ? ' selected' : '' ?>><?= e($p['kode_program_studi'] . ' - ' . $p['nama_program_studi']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="lap-fld">
          <label for="f-status">Status Mahasiswa</label>
          <select id="f-status" name="status">
            <option value="">-- Semua Status --</option>
            <?php foreach (STATUS_MHS as $s): ?><option<?= $fStatus === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="lap-fld">
          <label for="f-jk">Jenis Kelamin</label>
          <select id="f-jk" name="jk">
            <option value="">-- Semua --</option>
            <option value="L"<?= $fJk === 'L' ? ' selected' : '' ?>>Laki-laki</option>
            <option value="P"<?= $fJk === 'P' ? ' selected' : '' ?>>Perempuan</option>
          </select>
        </div>
        <div class="lap-fld">
          <label for="f-q">Cari NPM / Nama</label>
          <input id="f-q" name="q" type="search" value="<?= e($fQ) ?>" placeholder="NPM atau nama">
        </div>
      </div>
      <div class="lap-btns">
        <button class="lap-btn lap-btn-primary" type="submit">Tampilkan Laporan</button>
        <a class="lap-btn lap-btn-line" href="laporan.php">Reset</a>
        <button class="lap-btn lap-btn-line" type="button" data-print><?= ikon('print') ?>Cetak</button>
      </div>
    </form>
  </section>

  <div class="lap-stats">
    <div class="lap-stat"><span>Total Mahasiswa</span><b><?= angka($total) ?></b></div>
    <div class="lap-stat"><span>Laki-laki</span><b><?= angka($ring['laki']) ?></b></div>
    <div class="lap-stat"><span>Perempuan</span><b><?= angka($ring['perempuan']) ?></b></div>
    <div class="lap-stat"><span>Mahasiswa Aktif</span><b><?= angka($ring['aktif']) ?></b></div>
  </div>

  <section class="lap-card lap-table-card" aria-labelledby="judul-daftar">
    <h2 class="lap-h2" id="judul-daftar">Daftar Mahasiswa</h2>
    <p class="lap-meta"><?= e($judulData) ?> · <?= e($waktu) ?></p>
    <?php if (!$rows): ?>
      <p class="empty-note">Tidak ada data mahasiswa untuk filter ini.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="lap-tbl">
          <thead><tr><th scope="col">No</th><th scope="col">NPM</th><th scope="col">Nama Mahasiswa</th><th scope="col">Program Studi</th><th scope="col">Fakultas</th><th scope="col">JK</th><th scope="col">Tanggal Masuk</th><th scope="col">Status</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $i => $m): ?>
              <tr>
                <td class="lap-no"><?= $i + 1 ?></td>
                <td><?= e($m['npm']) ?></td>
                <td><?= e($m['nama_mahasiswa']) ?></td>
                <td><?= e($m['kode_program_studi'] . ' - ' . $m['nama_program_studi']) ?></td>
                <td><?= e($m['kode_fakultas'] . ' - ' . $m['nama_fakultas']) ?></td>
                <td><?= e($m['jenis_kelamin']) ?></td>
                <td class="nowrap"><?= e($m['tanggal_masuk']) ?></td>
                <td><span class="lap-st lap-st-<?= e(slug_status($m['status_mahasiswa'])) ?>"><?= e($m['status_mahasiswa']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
    <p class="lap-foot">Menampilkan <?= angka(count($rows)) ?> data mahasiswa<?= $total > count($rows) ? ' dari ' . angka($total) . ' (batas ' . angka(BATAS_LAPORAN) . ' baris; persempit filter)' : '' ?>.</p>
  </section>
<?php
layout_akhir();
