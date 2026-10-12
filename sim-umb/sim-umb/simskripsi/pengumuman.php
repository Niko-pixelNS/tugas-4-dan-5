<?php
/**
 * pengumuman.php
 *   Semua peran : membaca pengumuman yang terbit sesuai sasaran & prodinya
 *   Admin       : kelola semua pengumuman (semua prodi atau prodi tertentu)
 *   Operator    : kelola pengumuman untuk prodinya sendiri
 */
require __DIR__ . '/inc/init.php';
$u = require_login();

const KATEGORI = ['Umum', 'Pengajuan Judul', 'Seminar', 'Sidang', 'Yudisium'];
const SASARAN = ['Semua', 'Mahasiswa', 'Dosen'];

/** Pengumuman yang boleh diubah pengguna ini. */
function pengumuman_milik(int $id): ?array
{
    $p = db_row('SELECT * FROM pengumuman WHERE id_pengumuman = ?', [$id]);
    if (!$p) return null;
    if (is_role('ADMIN')) return $p;
    if (is_role('OPERATOR_PRODI') && (int) $p['id_program_studi'] === (int) user()['id_program_studi']) return $p;
    return null;
}

/* =====================================================================
   AKSI
   ===================================================================== */
if (is_post() && is_pengelola()) {
    csrf_check();
    $aksi = post('aksi');
    $id = (int) post('id');

    if ($aksi === 'simpan') {
        $lama = $id ? pengumuman_milik($id) : null;
        if ($id && !$lama) redirect('pengumuman.php');
        $judul = post('judul');
        $isi = post('isi');
        $kategori = post('kategori');
        $sasaran = post('sasaran');
        $status = post('status') === 'Terbit' ? 'Terbit' : 'Draf';
        $prodi = is_role('ADMIN') ? (post('id_program_studi') !== '' ? (int) post('id_program_studi') : null) : (int) $u['id_program_studi'];
        $berakhir = post('tanggal_berakhir') ?: null;
        $_SESSION['form_pengumuman'] = $_POST;

        if (mb_strlen($judul) < 5 || mb_strlen($isi) < 10 || !in_array($kategori, KATEGORI, true) || !in_array($sasaran, SASARAN, true)) {
            flash('error', 'Lengkapi judul (min. 5 karakter), isi (min. 10 karakter), kategori, dan sasaran.');
            redirect('pengumuman.php?' . ($id ? "edit=$id" : 'aksi=baru'));
        }
        try {
            $f = simpan_unggahan('lampiran', ['pdf', 'jpg', 'jpeg', 'png'], 5120);
        } catch (RuntimeException $ex) {
            flash('error', 'Lampiran: ' . $ex->getMessage());
            redirect('pengumuman.php?' . ($id ? "edit=$id" : 'aksi=baru'));
        }
        $lampiran = $f['path'] ?? ($lama['lampiran'] ?? null);
        if (post('hapus_lampiran') === '1' && !$f) $lampiran = null;
        if ($lama && $lama['lampiran'] && $lampiran !== $lama['lampiran']) hapus_unggahan($lama['lampiran']);

        if ($id) {
            db_exec("UPDATE pengumuman SET judul = ?, isi = ?, kategori = ?, sasaran = ?, id_program_studi = ?, lampiran = ?, status = ?,
                            tanggal_terbit = IF(? = 'Terbit', COALESCE(tanggal_terbit, NOW()), NULL), tanggal_berakhir = ?
                      WHERE id_pengumuman = ?",
                [$judul, $isi, $kategori, $sasaran, $prodi, $lampiran, $status, $status, $berakhir, $id]);
        } else {
            db_q("INSERT INTO pengumuman (judul, isi, kategori, sasaran, id_program_studi, lampiran, status, tanggal_terbit, tanggal_berakhir, dibuat_oleh)
                  VALUES (?, ?, ?, ?, ?, ?, ?, IF(? = 'Terbit', NOW(), NULL), ?, ?)",
                [$judul, $isi, $kategori, $sasaran, $prodi, $lampiran, $status, $status, $berakhir, $u['id']]);
        }
        unset($_SESSION['form_pengumuman']);
        flash('success', $status === 'Terbit' ? 'Pengumuman diterbitkan.' : 'Pengumuman disimpan sebagai draf.');
        redirect('pengumuman.php?kelola=1');
    }

    if ($aksi === 'hapus' && ($p = pengumuman_milik($id))) {
        db_exec('DELETE FROM pengumuman WHERE id_pengumuman = ?', [$id]);
        hapus_unggahan($p['lampiran']);
        flash('success', 'Pengumuman dihapus.');
    }
    redirect('pengumuman.php?kelola=1');
}

/* =====================================================================
   FORM
   ===================================================================== */
if (is_pengelola() && (get('aksi') === 'baru' || get('edit') !== '')) {
    $edit = get('edit') !== '' ? pengumuman_milik((int) get('edit')) : null;
    if (get('edit') !== '' && !$edit) redirect('pengumuman.php?kelola=1');
    $v = $_SESSION['form_pengumuman'] ?? ($edit ?: ['status' => 'Terbit', 'sasaran' => 'Semua', 'kategori' => 'Umum']);
    unset($_SESSION['form_pengumuman']);
    $prodi = is_role('ADMIN') ? daftar_prodi() : [];

    layout_start($edit ? 'Ubah Pengumuman' : 'Buat Pengumuman', 'pengumuman', '<a class="link" href="pengumuman.php?kelola=1">Kelola pengumuman</a> › ' . ($edit ? 'Ubah' : 'Baru'));
    ?>
    <section class="card card-pad">
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= (int) ($edit['id_pengumuman'] ?? 0) ?>">
        <div class="form-grid">
          <div class="form-row full"><label for="judul">Judul</label><input class="input" id="judul" name="judul" maxlength="200" required value="<?= e($v['judul'] ?? '') ?>"></div>
          <div class="form-row full"><label for="isi">Isi pengumuman</label><textarea class="input" id="isi" name="isi" rows="8" required><?= e($v['isi'] ?? '') ?></textarea></div>
          <div class="form-row"><label for="kategori">Kategori</label><select class="select" id="kategori" name="kategori"><?php foreach (KATEGORI as $k): ?><option<?= ($v['kategori'] ?? '') === $k ? ' selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></div>
          <div class="form-row"><label for="sasaran">Ditujukan untuk</label><select class="select" id="sasaran" name="sasaran"><?php foreach (SASARAN as $k): ?><option<?= ($v['sasaran'] ?? '') === $k ? ' selected' : '' ?>><?= $k ?></option><?php endforeach; ?></select></div>
          <div class="form-row"><label for="id_program_studi">Program studi</label>
            <?php if (is_role('ADMIN')): ?>
              <select class="select" id="id_program_studi" name="id_program_studi"><option value="">Semua program studi</option>
                <?php foreach ($prodi as $p): ?><option value="<?= (int) $p['id_program_studi'] ?>"<?= (string) ($v['id_program_studi'] ?? '') === (string) $p['id_program_studi'] ? ' selected' : '' ?>><?= e($p['nama'] . ' (' . $p['jenjang'] . ')') ?></option><?php endforeach; ?>
              </select>
              <?php if (!$prodi): ?><span class="help">Daftar prodi tidak bisa diambil dari API SIM Mahasiswa. Pengumuman akan berlaku untuk semua prodi.</span><?php endif; ?>
            <?php else: ?>
              <input class="input" value="<?= e($u['nama_program_studi']) ?>" disabled>
            <?php endif; ?></div>
          <div class="form-row"><label for="tanggal_berakhir">Tampil sampai <span class="muted">(opsional)</span></label><input class="input" id="tanggal_berakhir" name="tanggal_berakhir" type="date" value="<?= e($v['tanggal_berakhir'] ?? '') ?>"></div>
          <div class="form-row"><label for="lampiran">Lampiran <span class="muted">(PDF/JPG/PNG, maks. 5 MB)</span></label><input class="input" id="lampiran" name="lampiran" type="file" accept=".pdf,.jpg,.jpeg,.png">
            <?php if (!empty($edit['lampiran'])): ?><label class="check small"><input type="checkbox" name="hapus_lampiran" value="1"> Hapus lampiran saat ini (<a class="link" href="unduh.php?jenis=lampiran&amp;id=<?= (int) $edit['id_pengumuman'] ?>">lihat</a>)</label><?php endif; ?></div>
          <div class="form-row"><label for="status">Status</label><select class="select" id="status" name="status"><option value="Terbit"<?= ($v['status'] ?? '') === 'Terbit' ? ' selected' : '' ?>>Terbitkan sekarang</option><option value="Draf"<?= ($v['status'] ?? '') === 'Draf' ? ' selected' : '' ?>>Simpan sebagai draf</option></select></div>
        </div>
        <div class="form-actions"><button class="btn" type="submit"><?= icon('check') ?>Simpan</button><a class="btn btn-outline" href="pengumuman.php?kelola=1">Batal</a></div>
      </form>
    </section>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   KELOLA (daftar semua, termasuk draf)
   ===================================================================== */
if (is_pengelola() && get('kelola') === '1') {
    $where = ' WHERE 1=1';
    $param = [];
    if (is_role('OPERATOR_PRODI')) {
        $where .= ' AND id_program_studi = ?';
        $param[] = (int) $u['id_program_studi'];
    }
    $pg = paginasi((int) db_val("SELECT COUNT(*) FROM pengumuman $where", $param), 15);
    $rows = db_rows("SELECT * FROM pengumuman $where ORDER BY created_at DESC LIMIT ? OFFSET ?", array_merge($param, [$pg['per'], $pg['offset']]));
    layout_start('Kelola Pengumuman', 'pengumuman', '<a class="link" href="pengumuman.php">Pengumuman</a> › Kelola',
        '<a class="btn" href="pengumuman.php?aksi=baru">' . icon('plus') . 'Buat pengumuman</a>');
    ?>
    <section class="card">
      <?php if (!$rows): ?><?= kosong('Belum ada pengumuman.') ?><?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Judul</th><th>Kategori</th><th>Sasaran</th><th>Prodi</th><th>Status</th><th>Terbit</th><th>Aksi</th></tr></thead>
        <tbody><?php foreach ($rows as $p): ?>
          <tr><td><b><?= e($p['judul']) ?></b><?= $p['lampiran'] ? ' <span class="small muted">· lampiran</span>' : '' ?></td><td><?= e($p['kategori']) ?></td><td><?= e($p['sasaran']) ?></td>
            <td><?= e(nama_prodi($p['id_program_studi'])) ?></td><td><?= badge($p['status']) ?></td><td class="nowrap"><?= tgl($p['tanggal_terbit']) ?></td>
            <td><div class="aksi"><a class="icon-btn edit" href="pengumuman.php?edit=<?= (int) $p['id_pengumuman'] ?>" aria-label="Ubah"><?= icon('edit') ?></a>
              <form method="post" data-konfirmasi="Hapus pengumuman ini?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $p['id_pengumuman'] ?>">
                <button class="icon-btn del" type="submit" aria-label="Hapus"><?= icon('trash') ?></button></form></div></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
      <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
    </section>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   BACA
   ===================================================================== */
$pg = paginasi(pengumuman_tampil($u, 0, 0, true), 10);
$rows = pengumuman_tampil($u, $pg['per'], $pg['offset']);
layout_start('Pengumuman', 'pengumuman', 'Informasi terbaru seputar skripsi.',
    is_pengelola() ? '<a class="btn btn-light" href="pengumuman.php?kelola=1">' . icon('settings') . 'Kelola</a><a class="btn" href="pengumuman.php?aksi=baru">' . icon('plus') . 'Buat pengumuman</a>' : '');
?>
<section class="card">
  <?php if (!$rows): ?><?= kosong('Belum ada pengumuman.') ?><?php endif; ?>
  <?php foreach ($rows as $a): ?>
    <article class="ann">
      <h3><?= e($a['judul']) ?></h3>
      <div class="meta"><?= badge($a['kategori']) ?><span><?= tgl_jam($a['tanggal_terbit']) ?></span><span>· <?= e($a['sasaran']) ?></span>
        <?php if ($a['id_program_studi']): ?><span>· <?= e(nama_prodi($a['id_program_studi'])) ?></span><?php endif; ?></div>
      <p><?= e($a['isi']) ?></p>
      <?php if ($a['lampiran']): ?><p class="mt"><a class="file-link" href="unduh.php?jenis=lampiran&amp;id=<?= (int) $a['id_pengumuman'] ?>"><?= icon('download') ?>Unduh lampiran</a></p><?php endif; ?>
    </article>
  <?php endforeach; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
