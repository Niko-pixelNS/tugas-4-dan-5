<?php
/**
 * bimbingan.php — catatan konsultasi skripsi
 *   Mahasiswa: kirim topik bimbingan (+ berkas) ke pembimbing, lihat tanggapan
 *   Dosen    : tanggapi bimbingan yang masuk (+ berkas koreksi)
 *   Staf     : pantau saja
 */
require __DIR__ . '/inc/init.php';
$u = require_login('ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'MAHASISWA', 'DOSEN');

const EKS_BIMBINGAN = ['pdf', 'doc', 'docx'];
const MAKS_BIMBINGAN_KB = 10240;

$pjMhs = $u['role'] === 'MAHASISWA' ? pengajuan_aktif_mahasiswa($u['id_mahasiswa']) : null;
$pembimbingMhs = $pjMhs ? db_rows('SELECT pb.peran, d.* FROM pembimbing pb JOIN dosen d ON d.id_dosen = pb.id_dosen WHERE pb.id_pengajuan = ? ORDER BY pb.peran', [$pjMhs['id_pengajuan']]) : [];

/* =====================================================================
   AKSI
   ===================================================================== */
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');

    if ($aksi === 'kirim' && $u['role'] === 'MAHASISWA') {
        $idDosen = (int) post('id_dosen');
        $tanggal = post('tanggal');
        $topik = post('topik');
        if (!$pjMhs || !in_array($idDosen, array_map('intval', array_column($pembimbingMhs, 'id_dosen')), true)) {
            flash('error', 'Pilih salah satu pembimbing Anda.');
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) || $tanggal > date('Y-m-d')) {
            flash('error', 'Tanggal bimbingan tidak boleh di masa depan.');
        } elseif (mb_strlen($topik) < 5) {
            flash('error', 'Tuliskan topik yang dikonsultasikan.');
        } else {
            try {
                $f = simpan_unggahan('berkas', EKS_BIMBINGAN, MAKS_BIMBINGAN_KB);
                db_q('INSERT INTO bimbingan (id_pengajuan, id_dosen, tanggal, topik, file_mahasiswa) VALUES (?, ?, ?, ?, ?)',
                    [(int) $pjMhs['id_pengajuan'], $idDosen, $tanggal, mb_substr($topik, 0, 200), $f['path'] ?? null]);
                flash('success', 'Bimbingan terkirim ke dosen pembimbing.');
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
            }
        }
        redirect('bimbingan.php');
    }

    if ($aksi === 'hapus' && $u['role'] === 'MAHASISWA') {
        $b = db_row("SELECT b.* FROM bimbingan b JOIN pengajuan_judul pj ON pj.id_pengajuan = b.id_pengajuan
                      WHERE b.id_bimbingan = ? AND pj.id_mahasiswa = ? AND b.status = 'Menunggu'", [(int) post('id'), $u['id_mahasiswa']]);
        if ($b) {
            db_exec('DELETE FROM bimbingan WHERE id_bimbingan = ?', [(int) $b['id_bimbingan']]);
            hapus_unggahan($b['file_mahasiswa']);
            flash('success', 'Bimbingan dihapus.');
        } else {
            flash('error', 'Bimbingan yang sudah ditanggapi tidak bisa dihapus.');
        }
        redirect('bimbingan.php');
    }

    if ($aksi === 'tanggapi' && $u['role'] === 'DOSEN') {
        $id = (int) post('id');
        $b = db_row('SELECT * FROM bimbingan WHERE id_bimbingan = ? AND id_dosen = ?', [$id, $u['id']]);
        $catatan = post('catatan_dosen');
        if (!$b) {
            flash('error', 'Bimbingan tidak ditemukan.');
            redirect('bimbingan.php');
        }
        if (mb_strlen($catatan) < 3) {
            flash('error', 'Tuliskan tanggapan untuk mahasiswa.');
            redirect("bimbingan.php?id=$id");
        }
        try {
            $f = simpan_unggahan('berkas', EKS_BIMBINGAN, MAKS_BIMBINGAN_KB);
            if ($f) hapus_unggahan($b['file_dosen']);
            db_exec("UPDATE bimbingan SET catatan_dosen = ?, file_dosen = ?, status = 'Direspons' WHERE id_bimbingan = ?",
                [$catatan, $f['path'] ?? $b['file_dosen'], $id]);
            flash('success', 'Tanggapan tersimpan.');
            redirect('bimbingan.php?status=Menunggu');
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
            redirect("bimbingan.php?id=$id");
        }
    }
    redirect('bimbingan.php');
}

/* ---------- Kolom & tabel umum ---------- */
$select = "SELECT b.*, d.gelar_depan, d.nama_dosen, d.gelar_belakang, pj.judul, pj.id_mahasiswa,
                  m.nama_lengkap, m.npm, m.nama_program_studi
             FROM bimbingan b
             JOIN dosen d ON d.id_dosen = b.id_dosen
             JOIN pengajuan_judul pj ON pj.id_pengajuan = b.id_pengajuan
             LEFT JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa";

function berkas_link(?string $path, int $id, string $jenis, string $label): string
{
    return $path ? '<a class="file-link" href="unduh.php?jenis=' . $jenis . '&amp;id=' . $id . '">' . icon('download') . e($label) . '</a>' : '';
}

/* =====================================================================
   DOSEN: form tanggapan
   ===================================================================== */
if ($u['role'] === 'DOSEN' && get('id') !== '') {
    $b = db_row("$select WHERE b.id_bimbingan = ? AND b.id_dosen = ?", [(int) get('id'), $u['id']]);
    if (!$b) {
        flash('error', 'Bimbingan tidak ditemukan.');
        redirect('bimbingan.php');
    }
    $riwayat = db_rows("$select WHERE b.id_pengajuan = ? AND b.id_dosen = ? AND b.id_bimbingan <> ? ORDER BY b.tanggal DESC LIMIT 5", [$b['id_pengajuan'], $u['id'], $b['id_bimbingan']]);
    layout_start('Tanggapi Bimbingan', 'bimbingan', '<a class="link" href="bimbingan.php">Bimbingan</a> › ' . e($b['nama_lengkap']));
    ?>
    <div class="detail-grid">
      <section class="card card-pad">
        <div class="card-head"><h2>Bimbingan dari Mahasiswa</h2><?= badge($b['status']) ?></div>
        <?= info_list([
            'Mahasiswa' => e($b['nama_lengkap']) . ' (' . e($b['npm']) . ')',
            'Judul' => e($b['judul']),
            'Tanggal' => tgl($b['tanggal'], true),
            'Topik' => '<b>' . e($b['topik']) . '</b>',
            'Berkas' => berkas_link($b['file_mahasiswa'], (int) $b['id_bimbingan'], 'bimbingan_mhs', 'Unduh berkas mahasiswa') ?: '<span class="muted">Tidak ada</span>',
        ]) ?>
        <form method="post" enctype="multipart/form-data" class="mt">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="tanggapi"><input type="hidden" name="id" value="<?= (int) $b['id_bimbingan'] ?>">
          <div class="form-row"><label for="catatan_dosen">Tanggapan / catatan perbaikan</label>
            <textarea class="input" id="catatan_dosen" name="catatan_dosen" rows="6" required><?= e($b['catatan_dosen']) ?></textarea></div>
          <div class="form-row mt"><label for="berkas">Berkas koreksi <span class="muted">(opsional · PDF/DOC/DOCX, maks. 10 MB)</span></label>
            <input class="input" id="berkas" name="berkas" type="file" accept=".pdf,.doc,.docx">
            <?php if ($b['file_dosen']): ?><span class="help">Berkas sebelumnya: <?= berkas_link($b['file_dosen'], (int) $b['id_bimbingan'], 'bimbingan_dosen', 'unduh') ?> (akan diganti bila mengunggah baru)</span><?php endif; ?></div>
          <div class="form-actions"><button class="btn" type="submit"><?= icon('check') ?>Simpan tanggapan</button><a class="btn btn-outline" href="pengajuan.php?id=<?= (int) $b['id_pengajuan'] ?>">Lihat pengajuan</a></div>
        </form>
      </section>
      <section class="card card-pad">
        <div class="card-head"><h2>Bimbingan Sebelumnya</h2></div>
        <?php if (!$riwayat): ?><p class="muted">Ini bimbingan pertama mahasiswa ini dengan Anda.</p><?php endif; ?>
        <ul class="activity">
          <?php foreach ($riwayat as $r): ?>
            <li><span class="act-ic st-<?= $r['status'] === 'Menunggu' ? 'diajukan' : 'disetujui' ?>"><?= icon('chat') ?></span><div><p><b><?= e($r['topik']) ?></b></p>
              <time><?= tgl($r['tanggal']) ?></time><?php if ($r['catatan_dosen']): ?><div class="note"><?= e(mb_strimwidth($r['catatan_dosen'], 0, 160, '…')) ?></div><?php endif; ?></div></li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   DAFTAR
   ===================================================================== */
$where = ' WHERE 1=1';
$param = [];
if ($u['role'] === 'MAHASISWA') {
    $where .= ' AND pj.id_mahasiswa = ?';
    $param[] = $u['id_mahasiswa'];
} elseif ($u['role'] === 'DOSEN') {
    $where .= ' AND b.id_dosen = ?';
    $param[] = $u['id'];
} else {
    [$sc, $sp] = scope_staf('m');
    $where .= $sc;
    $param = $sp;
}
if (get('id_pengajuan') !== '') {
    $where .= ' AND b.id_pengajuan = ?';
    $param[] = (int) get('id_pengajuan');
}
$status = get('status');
if (in_array($status, ['Menunggu', 'Direspons'], true)) {
    $where .= ' AND b.status = ?';
    $param[] = $status;
}
$pg = paginasi((int) db_val("SELECT COUNT(*) FROM ($select $where) x", $param), 10);
$rows = db_rows("$select $where ORDER BY b.status = 'Menunggu' DESC, b.tanggal DESC, b.id_bimbingan DESC LIMIT ? OFFSET ?", array_merge($param, [$pg['per'], $pg['offset']]));

layout_start('Bimbingan', 'bimbingan', match ($u['role']) {
    'MAHASISWA' => 'Kirim topik bimbingan ke dosen pembimbing dan lihat tanggapannya.',
    'DOSEN' => 'Bimbingan dari mahasiswa yang Anda bimbing.',
    default => 'Pantauan bimbingan mahasiswa (lihat saja).',
});

if ($u['role'] === 'MAHASISWA'):
    if (!$pjMhs || $pjMhs['status'] !== 'Disetujui'): ?>
      <div class="alert alert-info">Bimbingan dimulai setelah judul Anda disetujui dan pembimbing ditetapkan. <a class="link" href="pengajuan.php">Lihat pengajuan</a>.</div>
    <?php elseif (!$pembimbingMhs): ?>
      <div class="alert alert-info">Judul sudah disetujui. Tunggu program studi menetapkan dosen pembimbing.</div>
    <?php else: ?>
      <section class="card mb">
        <details class="inline-form" open style="border-top:0">
          <summary><?= icon('plus') ?>Kirim bimbingan baru</summary>
          <form method="post" enctype="multipart/form-data" class="card-body">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="kirim">
            <div class="form-grid">
              <div class="form-row"><label for="id_dosen">Kepada</label><select class="select" id="id_dosen" name="id_dosen" required>
                <?php foreach ($pembimbingMhs as $d): ?><option value="<?= (int) $d['id_dosen'] ?>"><?= e($d['peran'] . ' — ' . nama_dosen($d)) ?></option><?php endforeach; ?></select></div>
              <div class="form-row"><label for="tanggal">Tanggal bimbingan</label><input class="input" id="tanggal" name="tanggal" type="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
              <div class="form-row full"><label for="topik">Topik yang dikonsultasikan</label><input class="input" id="topik" name="topik" maxlength="200" required placeholder="mis. Revisi Bab 2 — kajian pustaka"></div>
              <div class="form-row full"><label for="berkas">Berkas <span class="muted">(opsional · PDF/DOC/DOCX, maks. 10 MB)</span></label><input class="input" id="berkas" name="berkas" type="file" accept=".pdf,.doc,.docx"></div>
            </div>
            <div class="form-actions"><button class="btn" type="submit"><?= icon('upload') ?>Kirim</button></div>
          </form>
        </details>
      </section>
    <?php endif;
endif; ?>

<section class="card">
  <form class="filters" method="get" data-auto>
    <?php if (get('id_pengajuan') !== ''): ?><input type="hidden" name="id_pengajuan" value="<?= (int) get('id_pengajuan') ?>"><?php endif; ?>
    <label class="sr-only" for="f-status">Status</label>
    <select class="select" id="f-status" name="status"><option value="">Semua status</option>
      <?php foreach (['Menunggu', 'Direspons'] as $s): ?><option<?= $status === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <?php if (get('id_pengajuan') !== ''): ?><a class="btn btn-outline" href="bimbingan.php">Tampilkan semua mahasiswa</a><?php endif; ?>
  </form>
  <?php if (!$rows): ?><?= kosong('Belum ada catatan bimbingan.') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Tanggal</th><?php if ($u['role'] !== 'MAHASISWA'): ?><th>Mahasiswa</th><?php endif; ?><?php if ($u['role'] !== 'DOSEN'): ?><th>Dosen</th><?php endif; ?>
      <th>Topik &amp; tanggapan</th><th>Berkas</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody><?php foreach ($rows as $b): ?>
      <tr><td class="nowrap"><?= tgl($b['tanggal']) ?></td>
        <?php if ($u['role'] !== 'MAHASISWA'): ?><td><b><?= e($b['nama_lengkap']) ?></b><br><span class="small muted"><?= e($b['npm']) ?></span></td><?php endif; ?>
        <?php if ($u['role'] !== 'DOSEN'): ?><td><?= e(nama_dosen($b)) ?></td><?php endif; ?>
        <td style="min-width:260px"><b><?= e($b['topik']) ?></b><?php if ($b['catatan_dosen']): ?><div class="note"><b>Tanggapan dosen</b><?= e($b['catatan_dosen']) ?></div><?php endif; ?></td>
        <td class="small"><?= berkas_link($b['file_mahasiswa'], (int) $b['id_bimbingan'], 'bimbingan_mhs', 'Mahasiswa') ?><br><?= berkas_link($b['file_dosen'], (int) $b['id_bimbingan'], 'bimbingan_dosen', 'Koreksi dosen') ?></td>
        <td><?= badge($b['status']) ?></td>
        <td><div class="aksi">
          <?php if ($u['role'] === 'DOSEN'): ?><a class="icon-btn edit" href="bimbingan.php?id=<?= (int) $b['id_bimbingan'] ?>" aria-label="Tanggapi"><?= icon('edit') ?></a><?php endif; ?>
          <?php if ($u['role'] === 'MAHASISWA' && $b['status'] === 'Menunggu'): ?>
            <form method="post" data-konfirmasi="Hapus bimbingan ini?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int) $b['id_bimbingan'] ?>">
              <button class="icon-btn del" type="submit" aria-label="Hapus"><?= icon('trash') ?></button></form>
          <?php endif; ?>
          <?php if (is_staf()): ?><a class="icon-btn view" href="pengajuan.php?id=<?= (int) $b['id_pengajuan'] ?>" aria-label="Detail pengajuan"><?= icon('eye') ?></a><?php endif; ?>
        </div></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
