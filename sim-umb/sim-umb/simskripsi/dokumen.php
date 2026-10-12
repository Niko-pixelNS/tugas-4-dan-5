<?php
/**
 * dokumen.php — kelengkapan berkas skripsi
 *   Mahasiswa  : unggah / ganti berkas sesuai daftar jenis dokumen
 *   Prodi/Admin: lihat kelengkapan per mahasiswa, verifikasi (Valid / Ditolak)
 *   Admin      : kelola daftar jenis dokumen (?tab=jenis)
 *   Dekanat    : lihat saja
 */
require __DIR__ . '/inc/init.php';
$u = require_login('ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'MAHASISWA');

const TAHAP = ['Pengajuan Judul', 'Seminar Proposal', 'Sidang Skripsi'];

/** Data mahasiswa (dari cache SSO) bila dalam kewenangan staf. */
function mahasiswa_staf(int $idMhs): ?array
{
    [$sc, $sp] = scope_staf('m');
    return db_row("SELECT * FROM pengguna_sso m WHERE m.id_mahasiswa = ? $sc", array_merge([$idMhs], $sp));
}

/* =====================================================================
   AKSI
   ===================================================================== */
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');

    /* ----- Mahasiswa: unggah / ganti berkas ----- */
    if ($aksi === 'unggah' && $u['role'] === 'MAHASISWA') {
        $jenis = db_row("SELECT * FROM jenis_dokumen WHERE id_jenis = ? AND status_aktif = 'Aktif'", [(int) post('id_jenis')]);
        $lama = $jenis ? db_row('SELECT * FROM dokumen WHERE id_mahasiswa = ? AND id_jenis = ?', [$u['id_mahasiswa'], $jenis['id_jenis']]) : null;
        if (!$jenis) {
            flash('error', 'Jenis dokumen tidak dikenal.');
        } elseif ($lama && $lama['status_verifikasi'] === 'Valid') {
            flash('error', 'Dokumen yang sudah valid tidak bisa diganti. Hubungi prodi bila perlu.');
        } else {
            try {
                $eks = array_map('trim', explode(',', strtolower($jenis['format_file'])));
                $f = simpan_unggahan('berkas', $eks, (int) $jenis['maks_ukuran_kb'], true);
                if ($lama) {
                    db_exec("UPDATE dokumen SET nama_file_asli = ?, path_file = ?, ukuran_kb = ?, status_verifikasi = 'Menunggu',
                                    catatan_verifikasi = NULL, diverifikasi_oleh = NULL, tanggal_verifikasi = NULL, tanggal_upload = NOW()
                              WHERE id_dokumen = ?", [$f['nama'], $f['path'], $f['kb'], $lama['id_dokumen']]);
                    hapus_unggahan($lama['path_file']);
                } else {
                    db_q('INSERT INTO dokumen (id_mahasiswa, id_jenis, nama_file_asli, path_file, ukuran_kb) VALUES (?, ?, ?, ?, ?)',
                        [$u['id_mahasiswa'], (int) $jenis['id_jenis'], $f['nama'], $f['path'], $f['kb']]);
                }
                flash('success', $jenis['nama_dokumen'] . ' terunggah. Menunggu verifikasi prodi.');
            } catch (RuntimeException $ex) {
                flash('error', $jenis['nama_dokumen'] . ': ' . $ex->getMessage());
            }
        }
        redirect('dokumen.php');
    }

    /* ----- Prodi/Admin: verifikasi ----- */
    if ($aksi === 'verifikasi' && is_pengelola()) {
        $d = db_row('SELECT * FROM dokumen WHERE id_dokumen = ?', [(int) post('id_dokumen')]);
        $status = post('status');
        $catatan = post('catatan');
        if (!$d || !mahasiswa_staf((int) $d['id_mahasiswa'])) {
            flash('error', 'Dokumen tidak ditemukan.');
            redirect('dokumen.php');
        }
        if (!in_array($status, ['Valid', 'Ditolak'], true)) {
            flash('error', 'Pilih Valid atau Ditolak.');
        } elseif ($status === 'Ditolak' && mb_strlen($catatan) < 3) {
            flash('error', 'Tuliskan alasan penolakan agar mahasiswa bisa memperbaiki.');
        } else {
            db_exec('UPDATE dokumen SET status_verifikasi = ?, catatan_verifikasi = ?, diverifikasi_oleh = ?, tanggal_verifikasi = NOW() WHERE id_dokumen = ?',
                [$status, $catatan ?: null, $u['id'], (int) $d['id_dokumen']]);
            flash('success', 'Dokumen ditandai ' . strtolower($status) . '.');
        }
        redirect('dokumen.php?mhs=' . (int) $d['id_mahasiswa']);
    }

    /* ----- Admin: kelola jenis dokumen ----- */
    if ($aksi === 'jenis_simpan' && is_role('ADMIN')) {
        $idJenis = (int) post('id_jenis');
        $nama = post('nama_dokumen');
        $tahap = post('tahap');
        $format = strtolower(preg_replace('/[^a-z,]/i', '', post('format_file')));
        $maks = (int) post('maks_ukuran_kb');
        if ($nama === '' || !in_array($tahap, TAHAP, true) || $format === '' || $maks < 100) {
            flash('error', 'Lengkapi nama, tahap, format (mis. pdf,jpg), dan ukuran maksimal (≥ 100 KB).');
            redirect('dokumen.php?tab=jenis' . ($idJenis ? "&ubah=$idJenis" : ''));
        }
        $data = [$nama, $tahap, post('wajib') === '1' ? 1 : 0, $format, $maks, (int) post('urutan')];
        try {
            if ($idJenis) {
                db_exec('UPDATE jenis_dokumen SET nama_dokumen = ?, tahap = ?, wajib = ?, format_file = ?, maks_ukuran_kb = ?, urutan = ? WHERE id_jenis = ?', array_merge($data, [$idJenis]));
            } else {
                db_q('INSERT INTO jenis_dokumen (nama_dokumen, tahap, wajib, format_file, maks_ukuran_kb, urutan) VALUES (?, ?, ?, ?, ?, ?)', $data);
            }
            flash('success', 'Jenis dokumen tersimpan.');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== 1062) throw $ex;
            flash('error', 'Nama dokumen itu sudah ada pada tahap yang sama.');
        }
        redirect('dokumen.php?tab=jenis');
    }
    if ($aksi === 'jenis_status' && is_role('ADMIN')) {
        db_exec("UPDATE jenis_dokumen SET status_aktif = IF(status_aktif = 'Aktif', 'Nonaktif', 'Aktif') WHERE id_jenis = ?", [(int) post('id_jenis')]);
        flash('success', 'Status jenis dokumen diubah.');
        redirect('dokumen.php?tab=jenis');
    }
    redirect('dokumen.php');
}

/** Tabel dokumen satu mahasiswa, dikelompokkan per tahap. */
function tabel_dokumen(int $idMhs, bool $modeMahasiswa): string
{
    $rows = db_rows(
        "SELECT jd.*, d.id_dokumen, d.nama_file_asli, d.ukuran_kb, d.status_verifikasi, d.catatan_verifikasi, d.tanggal_upload, d.tanggal_verifikasi
           FROM jenis_dokumen jd LEFT JOIN dokumen d ON d.id_jenis = jd.id_jenis AND d.id_mahasiswa = ?
          WHERE jd.status_aktif = 'Aktif' ORDER BY FIELD(jd.tahap, 'Pengajuan Judul', 'Seminar Proposal', 'Sidang Skripsi'), jd.urutan", [$idMhs]);
    ob_start();
    foreach (TAHAP as $tahap):
        $grup = array_filter($rows, fn ($r) => $r['tahap'] === $tahap);
        if (!$grup) continue;
        $wajib = count(array_filter($grup, fn ($r) => $r['wajib']));
        $valid = count(array_filter($grup, fn ($r) => $r['wajib'] && $r['status_verifikasi'] === 'Valid')); ?>
      <section class="card mb">
        <div class="card-head card-pad" style="margin:0"><h2><?= e($tahap) ?></h2>
          <div class="meter-row"><span class="small muted nowrap"><?= $valid ?>/<?= $wajib ?> wajib valid</span><div class="meter" style="width:100px"><span style="width:<?= $wajib ? round($valid / $wajib * 100) : 100 ?>%"></span></div></div></div>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Dokumen</th><th>Ketentuan</th><th>Berkas</th><th>Status</th><th><?= $modeMahasiswa ? 'Unggah' : (is_pengelola() ? 'Verifikasi' : '') ?></th></tr></thead>
          <tbody><?php foreach ($grup as $r): $st = $r['status_verifikasi'] ?? 'Belum diunggah'; ?>
            <tr><td><b><?= e($r['nama_dokumen']) ?></b><?= $r['wajib'] ? '' : ' <span class="small muted">(opsional)</span>' ?></td>
              <td class="small muted nowrap"><?= e(strtoupper(str_replace(',', ', ', $r['format_file']))) ?> · maks <?= number_format($r['maks_ukuran_kb'] / 1024, 1, ',', '.') ?> MB</td>
              <td class="small"><?php if ($r['id_dokumen']): ?><a class="file-link" href="unduh.php?jenis=dokumen&amp;id=<?= (int) $r['id_dokumen'] ?>"><?= icon('download') ?><?= e($r['nama_file_asli']) ?></a>
                <br><span class="muted"><?= tgl_jam($r['tanggal_upload']) ?> · <?= number_format((int) $r['ukuran_kb'], 0, ',', '.') ?> KB</span><?php else: ?><span class="muted">-</span><?php endif; ?></td>
              <td><?= badge($st) ?><?php if ($r['catatan_verifikasi']): ?><div class="note"><?= e($r['catatan_verifikasi']) ?></div><?php endif; ?></td>
              <td style="min-width:240px">
                <?php if ($modeMahasiswa && $st !== 'Valid'): ?>
                  <form method="post" enctype="multipart/form-data" class="meter-row">
                    <?= csrf_field() ?><input type="hidden" name="aksi" value="unggah"><input type="hidden" name="id_jenis" value="<?= (int) $r['id_jenis'] ?>">
                    <label class="sr-only" for="f<?= (int) $r['id_jenis'] ?>">Berkas <?= e($r['nama_dokumen']) ?></label>
                    <input class="input" style="height:38px;font-size:13px" id="f<?= (int) $r['id_jenis'] ?>" name="berkas" type="file" required
                           accept="<?= e(implode(',', array_map(fn ($x) => '.' . trim($x), explode(',', $r['format_file'])))) ?>">
                    <button class="btn btn-sm" type="submit"><?= icon('upload') ?><?= $r['id_dokumen'] ? 'Ganti' : 'Unggah' ?></button>
                  </form>
                <?php elseif (!$modeMahasiswa && is_pengelola() && $r['id_dokumen'] && $st === 'Menunggu'): ?>
                  <form method="post" class="meter-row" style="flex-wrap:wrap">
                    <?= csrf_field() ?><input type="hidden" name="aksi" value="verifikasi"><input type="hidden" name="id_dokumen" value="<?= (int) $r['id_dokumen'] ?>">
                    <label class="sr-only" for="c<?= (int) $r['id_dokumen'] ?>">Catatan</label>
                    <input class="input" style="height:36px;flex:1;min-width:120px" id="c<?= (int) $r['id_dokumen'] ?>" name="catatan" placeholder="Catatan (wajib bila ditolak)">
                    <button class="btn btn-sm btn-green" type="submit" name="status" value="Valid"><?= icon('check') ?>Valid</button>
                    <button class="btn btn-sm btn-red" type="submit" name="status" value="Ditolak">Tolak</button>
                  </form>
                <?php elseif ($r['tanggal_verifikasi']): ?>
                  <span class="small muted">Diverifikasi <?= tgl($r['tanggal_verifikasi']) ?></span>
                <?php endif; ?>
              </td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      </section>
    <?php endforeach;
    return ob_get_clean();
}

/* =====================================================================
   MAHASISWA
   ===================================================================== */
if ($u['role'] === 'MAHASISWA') {
    layout_start('Dokumen', 'dokumen', 'Unggah berkas persyaratan. Berkas yang ditolak bisa diganti; yang sudah valid terkunci.');
    echo tabel_dokumen($u['id_mahasiswa'], true);
    layout_end();
    exit;
}

/* =====================================================================
   ADMIN: jenis dokumen
   ===================================================================== */
if (get('tab') === 'jenis' && is_role('ADMIN')) {
    $ubah = get('ubah') !== '' ? db_row('SELECT * FROM jenis_dokumen WHERE id_jenis = ?', [(int) get('ubah')]) : null;
    $jenis = db_rows("SELECT jd.*, (SELECT COUNT(*) FROM dokumen d WHERE d.id_jenis = jd.id_jenis) AS dipakai
                        FROM jenis_dokumen jd ORDER BY FIELD(jd.tahap, 'Pengajuan Judul', 'Seminar Proposal', 'Sidang Skripsi'), jd.urutan");
    layout_start('Jenis Dokumen', 'dokumen', '<a class="link" href="dokumen.php">Dokumen</a> › Daftar persyaratan berkas per tahap');
    ?>
    <section class="card card-pad mb">
      <div class="card-head"><h2><?= $ubah ? 'Ubah jenis dokumen' : 'Tambah jenis dokumen' ?></h2></div>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="jenis_simpan"><input type="hidden" name="id_jenis" value="<?= (int) ($ubah['id_jenis'] ?? 0) ?>">
        <div class="form-grid">
          <div class="form-row"><label for="nama_dokumen">Nama dokumen</label><input class="input" id="nama_dokumen" name="nama_dokumen" maxlength="100" required value="<?= e($ubah['nama_dokumen'] ?? '') ?>"></div>
          <div class="form-row"><label for="tahap">Tahap</label><select class="select" id="tahap" name="tahap"><?php foreach (TAHAP as $t): ?><option<?= ($ubah['tahap'] ?? '') === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
          <div class="form-row"><label for="format_file">Format diizinkan</label><input class="input" id="format_file" name="format_file" value="<?= e($ubah['format_file'] ?? 'pdf') ?>" placeholder="pdf,jpg"><span class="help">Pisahkan dengan koma: pdf, jpg, png, doc, docx.</span></div>
          <div class="form-row"><label for="maks_ukuran_kb">Ukuran maksimal (KB)</label><input class="input" id="maks_ukuran_kb" name="maks_ukuran_kb" type="number" min="100" value="<?= (int) ($ubah['maks_ukuran_kb'] ?? 2048) ?>"></div>
          <div class="form-row"><label for="urutan">Urutan</label><input class="input" id="urutan" name="urutan" type="number" min="0" max="255" value="<?= (int) ($ubah['urutan'] ?? 0) ?>"></div>
          <div class="form-row"><span class="form-label">Sifat</span><label class="check"><input type="checkbox" name="wajib" value="1"<?= ($ubah['wajib'] ?? 1) ? ' checked' : '' ?>> Wajib dipenuhi sebelum tahap ini</label></div>
        </div>
        <div class="form-actions"><button class="btn" type="submit"><?= icon('check') ?>Simpan</button><?php if ($ubah): ?><a class="btn btn-outline" href="dokumen.php?tab=jenis">Batal</a><?php endif; ?></div>
      </form>
    </section>
    <section class="card">
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Urutan</th><th>Tahap</th><th>Dokumen</th><th>Wajib</th><th>Format</th><th>Maks</th><th>Terunggah</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody><?php foreach ($jenis as $j): ?>
          <tr><td><?= (int) $j['urutan'] ?></td><td><?= e($j['tahap']) ?></td><td><?= e($j['nama_dokumen']) ?></td><td><?= $j['wajib'] ? 'Ya' : 'Tidak' ?></td>
            <td><?= e($j['format_file']) ?></td><td class="nowrap"><?= number_format($j['maks_ukuran_kb'], 0, ',', '.') ?> KB</td><td><?= (int) $j['dipakai'] ?></td><td><?= badge($j['status_aktif']) ?></td>
            <td><div class="aksi"><a class="icon-btn edit" href="dokumen.php?tab=jenis&amp;ubah=<?= (int) $j['id_jenis'] ?>" aria-label="Ubah"><?= icon('edit') ?></a>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="jenis_status"><input type="hidden" name="id_jenis" value="<?= (int) $j['id_jenis'] ?>">
                <button class="btn btn-sm btn-outline" type="submit"><?= $j['status_aktif'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?></button></form></div></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </section>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   STAF: detail satu mahasiswa
   ===================================================================== */
if (get('mhs') !== '') {
    $m = mahasiswa_staf((int) get('mhs'));
    if (!$m) {
        flash('error', 'Mahasiswa tidak ditemukan atau di luar kewenangan Anda.');
        redirect('dokumen.php');
    }
    $pj = pengajuan_aktif_mahasiswa((int) $m['id_mahasiswa']);
    layout_start('Dokumen ' . $m['nama_lengkap'], 'dokumen', '<a class="link" href="dokumen.php">Dokumen</a> › NPM ' . e($m['npm']) . ' · ' . e($m['nama_program_studi']),
        '<a class="btn btn-outline" href="dokumen.php">' . icon('arrowLeft') . 'Kembali</a>' . ($pj ? '<a class="btn btn-light" href="pengajuan.php?id=' . (int) $pj['id_pengajuan'] . '">Lihat pengajuan</a>' : ''));
    echo tabel_dokumen((int) $m['id_mahasiswa'], false);
    layout_end();
    exit;
}

/* =====================================================================
   STAF: rekap kelengkapan
   ===================================================================== */
[$sc, $sp] = scope_staf('m');
$where = "FROM pengajuan_judul pj JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa WHERE pj.status <> 'Ditolak' $sc";
$param = $sp;
$cari = get('cari');
if ($cari !== '') {
    $where .= ' AND (m.nama_lengkap LIKE ? OR m.npm LIKE ?)';
    array_push($param, "%$cari%", "%$cari%");
}
$hanyaMenunggu = get('menunggu') === '1';
if ($hanyaMenunggu) $where .= " AND EXISTS (SELECT 1 FROM dokumen d WHERE d.id_mahasiswa = pj.id_mahasiswa AND d.status_verifikasi = 'Menunggu')";

$pg = paginasi((int) db_val("SELECT COUNT(*) $where", $param), 15);
$jmlWajib = [];
foreach (db_rows("SELECT tahap, COUNT(*) AS n FROM jenis_dokumen WHERE wajib = 1 AND status_aktif = 'Aktif' GROUP BY tahap") as $r) $jmlWajib[$r['tahap']] = (int) $r['n'];
$rows = db_rows(
    "SELECT m.id_mahasiswa, m.nama_lengkap, m.npm, m.nama_program_studi, pj.status,
            (SELECT COUNT(*) FROM dokumen d WHERE d.id_mahasiswa = m.id_mahasiswa AND d.status_verifikasi = 'Menunggu') AS menunggu,
            (SELECT COUNT(*) FROM dokumen d JOIN jenis_dokumen jd ON jd.id_jenis = d.id_jenis WHERE d.id_mahasiswa = m.id_mahasiswa AND d.status_verifikasi = 'Valid' AND jd.wajib = 1 AND jd.status_aktif = 'Aktif' AND jd.tahap = 'Pengajuan Judul') AS v1,
            (SELECT COUNT(*) FROM dokumen d JOIN jenis_dokumen jd ON jd.id_jenis = d.id_jenis WHERE d.id_mahasiswa = m.id_mahasiswa AND d.status_verifikasi = 'Valid' AND jd.wajib = 1 AND jd.status_aktif = 'Aktif' AND jd.tahap = 'Seminar Proposal') AS v2,
            (SELECT COUNT(*) FROM dokumen d JOIN jenis_dokumen jd ON jd.id_jenis = d.id_jenis WHERE d.id_mahasiswa = m.id_mahasiswa AND d.status_verifikasi = 'Valid' AND jd.wajib = 1 AND jd.status_aktif = 'Aktif' AND jd.tahap = 'Sidang Skripsi') AS v3
     $where ORDER BY menunggu DESC, m.nama_lengkap LIMIT ? OFFSET ?", array_merge($param, [$pg['per'], $pg['offset']]));

layout_start('Dokumen', 'dokumen', 'Kelengkapan berkas mahasiswa yang punya pengajuan berjalan.',
    is_role('ADMIN') ? '<a class="btn btn-light" href="dokumen.php?tab=jenis">' . icon('settings') . 'Kelola jenis dokumen</a>' : '');
?>
<section class="card">
  <form class="filters" method="get">
    <div class="search"><?= icon('search') ?><label class="sr-only" for="cari">Cari</label><input class="input" id="cari" name="cari" type="search" value="<?= e($cari) ?>" placeholder="Cari nama atau NPM…"></div>
    <label class="check"><input type="checkbox" name="menunggu" value="1"<?= $hanyaMenunggu ? ' checked' : '' ?> onchange="this.form.submit()"> Hanya yang perlu diverifikasi</label>
    <button class="btn btn-outline" type="submit"><?= icon('search') ?>Cari</button>
  </form>
  <?php if (!$rows): ?><?= kosong('Tidak ada mahasiswa yang cocok.') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Mahasiswa</th><th>Prodi</th><th>Pengajuan Judul</th><th>Seminar</th><th>Sidang</th><th>Perlu verifikasi</th><th>Aksi</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr><td><b><?= e($r['nama_lengkap']) ?></b><br><span class="small muted"><?= e($r['npm']) ?></span></td><td><?= e($r['nama_program_studi']) ?></td>
        <?php foreach (['v1' => 'Pengajuan Judul', 'v2' => 'Seminar Proposal', 'v3' => 'Sidang Skripsi'] as $k => $t): $w = $jmlWajib[$t] ?? 0; ?>
          <td class="nowrap"><?= (int) $r[$k] ?>/<?= $w ?> <?= $w && (int) $r[$k] >= $w ? '<span class="badge st-disetujui">Lengkap</span>' : '' ?></td>
        <?php endforeach; ?>
        <td><?= $r['menunggu'] ? '<span class="badge st-bimbingan">' . (int) $r['menunggu'] . ' berkas</span>' : '-' ?></td>
        <td><a class="icon-btn view" href="dokumen.php?mhs=<?= (int) $r['id_mahasiswa'] ?>" aria-label="Lihat dokumen <?= e($r['nama_lengkap']) ?>"><?= icon('eye') ?></a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
