<?php
/**
 * pengajuan.php — pengajuan judul skripsi
 *   Mahasiswa : ajukan judul, perbaiki bila diminta revisi, batalkan selama belum diproses
 *   Prodi/Admin: setujui / minta revisi / tolak, tetapkan pembimbing
 *   Dekanat   : lihat saja
 *   ?id=...   : detail (juga untuk dosen pembimbing/penguji)
 */
require __DIR__ . '/inc/init.php';
$u = require_login('ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'MAHASISWA', 'DOSEN');

/** Ambil pengajuan dan pastikan boleh dilihat, atau kembali dengan pesan. */
function pengajuan_atau_keluar(int $id): array
{
    $pj = pengajuan_ambil($id);
    if (!$pj || !boleh_lihat_pengajuan($pj)) {
        flash('error', 'Pengajuan tidak ditemukan atau di luar kewenangan Anda.');
        redirect('pengajuan.php');
    }
    return $pj;
}

/* =====================================================================
   AKSI (POST)
   ===================================================================== */
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    $id = (int) post('id');

    /* ----- Mahasiswa: ajukan / perbaiki judul ----- */
    if (in_array($aksi, ['ajukan', 'perbaiki'], true) && $u['role'] === 'MAHASISWA') {
        $judul = preg_replace('/\s+/', ' ', post('judul'));
        $bidang = post('bidang_kajian');
        $latar = post('latar_belakang');
        $usulan = post('usulan_dosen') !== '' ? (int) post('usulan_dosen') : null;
        $_SESSION['form_pengajuan'] = $_POST;

        if (mb_strlen($judul) < 10 || mb_strlen($judul) > 300) {
            flash('error', 'Judul harus 10–300 karakter.');
            redirect($aksi === 'ajukan' ? 'pengajuan.php?aksi=baru' : "pengajuan.php?id=$id");
        }
        if (mb_strlen($latar) < 50) {
            flash('error', 'Tuliskan latar belakang singkat minimal 50 karakter.');
            redirect($aksi === 'ajukan' ? 'pengajuan.php?aksi=baru' : "pengajuan.php?id=$id");
        }
        if ($usulan && !db_val("SELECT 1 FROM dosen WHERE id_dosen = ? AND status_aktif = 'Aktif'", [$usulan])) $usulan = null;

        if ($aksi === 'ajukan') {
            try {
                db_q('INSERT INTO pengajuan_judul (id_mahasiswa, judul, bidang_kajian, latar_belakang, usulan_dosen) VALUES (?, ?, ?, ?, ?)',
                    [$u['id_mahasiswa'], $judul, $bidang ?: null, $latar, $usulan]);
            } catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() !== 1062) throw $ex;
                flash('error', 'Anda masih punya pengajuan yang berjalan. Selesaikan atau tunggu keputusan prodi.');
                redirect('pengajuan.php');
            }
            unset($_SESSION['form_pengajuan']);
            flash('success', 'Judul berhasil diajukan. Tunggu keputusan program studi.');
            redirect('pengajuan.php?id=' . db_id());
        }

        $pj = pengajuan_atau_keluar($id);
        if ($pj['status'] !== 'Revisi') {
            flash('error', 'Judul hanya bisa diubah saat prodi meminta revisi.');
            redirect("pengajuan.php?id=$id");
        }
        db_exec("UPDATE pengajuan_judul SET judul = ?, bidang_kajian = ?, latar_belakang = ?, usulan_dosen = ?, status = 'Diajukan'
                  WHERE id_pengajuan = ? AND id_mahasiswa = ?", [$judul, $bidang ?: null, $latar, $usulan, $id, $u['id_mahasiswa']]);
        unset($_SESSION['form_pengajuan']);
        flash('success', 'Perbaikan judul dikirim. Status kembali menjadi Diajukan.');
        redirect("pengajuan.php?id=$id");
    }

    /* ----- Mahasiswa: batalkan pengajuan yang belum diproses ----- */
    if ($aksi === 'batal' && $u['role'] === 'MAHASISWA') {
        $n = db_exec("DELETE FROM pengajuan_judul WHERE id_pengajuan = ? AND id_mahasiswa = ? AND status = 'Diajukan'", [$id, $u['id_mahasiswa']]);
        flash($n ? 'success' : 'error', $n ? 'Pengajuan dibatalkan.' : 'Pengajuan yang sudah diproses tidak bisa dibatalkan.');
        redirect('pengajuan.php');
    }

    /* ----- Staf: perbarui data mahasiswa dari API SIM Mahasiswa ----- */
    if ($aksi === 'sinkron' && is_staf()) {
        $pj = pengajuan_atau_keluar($id);
        $err = sinkron_mahasiswa((int) $pj['id_mahasiswa']);
        flash($err ? 'error' : 'success', $err ?: 'Data mahasiswa diperbarui dari SIM Mahasiswa.');
        redirect("pengajuan.php?id=$id");
    }

    if (!is_pengelola()) {
        flash('error', 'Anda tidak berwenang melakukan aksi ini.');
        redirect('pengajuan.php');
    }
    $pj = pengajuan_atau_keluar($id);

    /* ----- Prodi: proses pengajuan ----- */
    if ($aksi === 'proses') {
        $status = post('status');
        $catatan = post('catatan_prodi');
        if (!in_array($pj['status'], ['Diajukan', 'Revisi'], true)) {
            flash('error', 'Pengajuan ini sudah diputuskan.');
        } elseif (!in_array($status, ['Disetujui', 'Revisi', 'Ditolak'], true)) {
            flash('error', 'Pilih keputusan.');
        } elseif ($status !== 'Disetujui' && mb_strlen($catatan) < 5) {
            flash('error', 'Tuliskan catatan untuk mahasiswa bila meminta revisi atau menolak.');
        } else {
            db_exec('UPDATE pengajuan_judul SET status = ?, catatan_prodi = ?, diproses_oleh = ?, tanggal_diproses = NOW() WHERE id_pengajuan = ?',
                [$status, $catatan ?: null, $u['id'], $id]);
            flash('success', 'Pengajuan ' . strtolower($status) . '.' . ($status === 'Disetujui' ? ' Lanjutkan dengan menetapkan pembimbing.' : ''));
        }
        redirect("pengajuan.php?id=$id");
    }

    /* ----- Prodi: tetapkan / hapus pembimbing ----- */
    if ($aksi === 'pembimbing_tambah') {
        $idDosen = (int) post('id_dosen');
        $peranPb = post('peran');
        $dosen = db_row("SELECT * FROM dosen WHERE id_dosen = ? AND status_aktif = 'Aktif'", [$idDosen]);
        $beban = (int) db_val(
            "SELECT COUNT(*) FROM pembimbing pb WHERE pb.id_dosen = ?
               AND NOT EXISTS (SELECT 1 FROM sidang_skripsi s WHERE s.id_pengajuan = pb.id_pengajuan AND s.status = 'Selesai' AND s.hasil <> 'Tidak Lulus')",
            [$idDosen]);
        if ($pj['status'] !== 'Disetujui') {
            flash('error', 'Pembimbing ditetapkan setelah judul disetujui.');
        } elseif (!$dosen || !in_array($peranPb, ['Pembimbing 1', 'Pembimbing 2'], true)) {
            flash('error', 'Pilih dosen dan peran pembimbing.');
        } elseif ($beban >= (int) $dosen['kuota_bimbingan']) {
            flash('error', nama_dosen($dosen) . ' sudah mencapai kuota bimbingan (' . $dosen['kuota_bimbingan'] . ' mahasiswa).');
        } else {
            try {
                db_q('INSERT INTO pembimbing (id_pengajuan, id_dosen, peran, nomor_sk, tanggal_sk, ditetapkan_oleh) VALUES (?, ?, ?, ?, ?, ?)',
                    [$id, $idDosen, $peranPb, post('nomor_sk') ?: null, post('tanggal_sk') ?: null, $u['id']]);
                flash('success', nama_dosen($dosen) . ' ditetapkan sebagai ' . $peranPb . '.');
            } catch (mysqli_sql_exception $ex) {
                if ($ex->getCode() !== 1062) throw $ex;
                flash('error', 'Peran itu sudah terisi, atau dosen tersebut sudah menjadi pembimbing mahasiswa ini.');
            }
        }
        redirect("pengajuan.php?id=$id#pembimbing");
    }
    if ($aksi === 'pembimbing_hapus') {
        db_exec('DELETE FROM pembimbing WHERE id_pembimbing = ? AND id_pengajuan = ?', [(int) post('id_pembimbing'), $id]);
        flash('success', 'Pembimbing dilepas dari pengajuan ini.');
        redirect("pengajuan.php?id=$id#pembimbing");
    }

    redirect('pengajuan.php');
}

/* =====================================================================
   FORM PENGAJUAN BARU (mahasiswa)
   ===================================================================== */
$dosenAktif = daftar_dosen_aktif();

function form_judul(array $nilai, array $dosen, string $aksi, int $id = 0): string
{
    $v = fn ($k) => e($nilai[$k] ?? '');
    ob_start(); ?>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="<?= $aksi ?>"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="form-grid">
        <div class="form-row full"><label for="judul">Judul skripsi</label>
          <textarea class="input" id="judul" name="judul" rows="2" maxlength="300" required style="min-height:64px"><?= $v('judul') ?></textarea>
          <span class="help">10–300 karakter. Tulis judul lengkap, bukan topik.</span></div>
        <div class="form-row"><label for="bidang_kajian">Bidang kajian</label>
          <input class="input" id="bidang_kajian" name="bidang_kajian" maxlength="100" value="<?= $v('bidang_kajian') ?>" placeholder="mis. Rekayasa Perangkat Lunak"></div>
        <!-- <div class="form-row"><label for="usulan_dosen">Usulan pembimbing <span class="muted">(opsional)</span></label> -->
          <!-- <select class="select" id="usulan_dosen" name="usulan_dosen"><?= opsi_dosen($dosen, $nilai['usulan_dosen'] ?? null, '— Tidak ada usulan —') ?></select></div> -->
        <div class="form-row full"><label for="latar_belakang">Latar belakang singkat</label>
          <textarea class="input" id="latar_belakang" name="latar_belakang" rows="5" required><?= $v('latar_belakang') ?></textarea>
          <span class="help">Masalah yang diteliti dan alasan memilih judul ini (minimal 50 karakter).</span></div>
      </div>
      <div class="form-actions"><button class="btn" type="submit"><?= icon('upload') ?><?= $aksi === 'ajukan' ? 'Ajukan judul' : 'Kirim perbaikan' ?></button></div>
    </form>
    <?php return ob_get_clean();
}

if (get('aksi') === 'baru') {
    if ($u['role'] !== 'MAHASISWA') redirect('pengajuan.php');
    if ($aktif = pengajuan_aktif_mahasiswa($u['id_mahasiswa'])) {
        flash('info', 'Anda masih punya pengajuan yang berjalan.');
        redirect('pengajuan.php?id=' . $aktif['id_pengajuan']);
    }
    $kurang = dokumen_kurang($u['id_mahasiswa'], 'Pengajuan Judul');
    $nilai = $_SESSION['form_pengajuan'] ?? [];
    unset($_SESSION['form_pengajuan']);
    layout_start('Ajukan Judul', 'pengajuan', '<a class="link" href="pengajuan.php">Pengajuan Judul</a> › Ajukan baru');
    if ($kurang) echo '<div class="alert alert-warning">' . $kurang . ' dokumen wajib tahap Pengajuan Judul belum valid. Prodi bisa menunda keputusan sampai dokumen lengkap. <a class="link" href="dokumen.php">Unggah dokumen</a>.</div>';
    echo '<section class="card card-pad">' . form_judul($nilai, $dosenAktif, 'ajukan') . '</section>';
    layout_end();
    exit;
}

/* =====================================================================
   DETAIL
   ===================================================================== */
if (get('id') !== '') {
    $pj = pengajuan_atau_keluar((int) get('id'));
    $id = (int) $pj['id_pengajuan'];
    $tahap = tahap($pj);
    $pembimbing = db_rows('SELECT pb.*, d.gelar_depan, d.nama_dosen, d.gelar_belakang, d.nip FROM pembimbing pb JOIN dosen d ON d.id_dosen = pb.id_dosen WHERE pb.id_pengajuan = ? ORDER BY pb.peran', [$id]);
    $usulan = $pj['usulan_dosen'] ? db_row('SELECT * FROM dosen WHERE id_dosen = ?', [$pj['usulan_dosen']]) : null;
    $bimbingan = db_rows('SELECT b.*, d.gelar_depan, d.nama_dosen, d.gelar_belakang FROM bimbingan b JOIN dosen d ON d.id_dosen = b.id_dosen WHERE b.id_pengajuan = ? ORDER BY b.tanggal DESC, b.id_bimbingan DESC', [$id]);
    $dokumen = db_rows('SELECT * FROM v_kelengkapan_dokumen WHERE id_pengajuan = ? ORDER BY id_jenis', [$id]);
    $ujian = db_rows(
        "SELECT 'Seminar Proposal' AS jenis, 'seminar' AS kode, id_seminar AS id, tanggal, jam_mulai, ruangan, status, hasil, nilai FROM seminar_proposal WHERE id_pengajuan = ?
         UNION ALL SELECT 'Sidang Skripsi', 'sidang', id_sidang, tanggal, jam_mulai, ruangan, status, hasil, nilai_angka FROM sidang_skripsi WHERE id_pengajuan = ?
         ORDER BY tanggal", [$id, $id]);
    $kembali = $u['role'] === 'MAHASISWA' ? 'dashboard.php' : ($u['role'] === 'DOSEN' ? 'pembimbing.php' : 'pengajuan.php');
    $dokValid = count(array_filter($dokumen, fn ($d) => $d['wajib'] && $d['status_dokumen'] === 'Valid'));
    $dokWajib = count(array_filter($dokumen, fn ($d) => $d['wajib']));

    $aksiAtas = '<a class="btn btn-outline" href="' . $kembali . '">' . icon('arrowLeft') . 'Kembali</a>';
    if (is_staf()) {
        $aksiAtas .= '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="aksi" value="sinkron"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-light" type="submit" title="Ambil data terbaru lewat API JSON SIM Mahasiswa">' . icon('refresh') . 'Perbarui data mahasiswa</button></form>';
    }
    layout_start('Detail Pengajuan', $u['role'] === 'DOSEN' ? 'pembimbing' : 'pengajuan',
        '<a class="link" href="' . $kembali . '">' . ($u['role'] === 'MAHASISWA' ? 'Dashboard' : ($u['role'] === 'DOSEN' ? 'Mahasiswa Bimbingan' : 'Pengajuan Judul')) . '</a> › ' . e($pj['nama_mahasiswa']),
        $aksiAtas);
    ?>
    <section class="card card-pad mb">
      <div class="card-head"><h2>Tahapan Skripsi</h2><?= badge($tahap['label']) ?></div>
      <?= stepper_html($tahap) ?>
    </section>

    <div class="detail-grid">
      <section class="card card-pad">
        <div class="card-head"><h2>Informasi Mahasiswa &amp; Judul</h2></div>
        <?= info_list([
            'Nama' => e($pj['nama_mahasiswa']),
            'NPM' => e($pj['npm']),
            'Program Studi' => e($pj['nama_program_studi']),
            'Judul' => '<b>' . e($pj['judul']) . '</b>',
            'Bidang Kajian' => e($pj['bidang_kajian'] ?: '-'),
            'Usulan Pembimbing' => $usulan ? e(nama_dosen($usulan)) : '-',
            'Diajukan' => tgl_jam($pj['tanggal_pengajuan']),
        ]) ?>
        <?php if ($pj['latar_belakang']): ?><div class="note mt"><b>Latar belakang</b><?= e($pj['latar_belakang']) ?></div><?php endif; ?>
      </section>

      <section class="card card-pad">
        <div class="card-head"><h2>Keputusan Prodi</h2><?= badge($pj['status']) ?></div>
        <?= info_list([
            'Diproses' => $pj['tanggal_diproses'] ? tgl_jam($pj['tanggal_diproses']) : '<span class="muted">Belum</span>',
            'Dokumen wajib' => $dokValid . ' dari ' . $dokWajib . ' valid',
        ]) ?>
        <?php if ($pj['catatan_prodi']): ?><div class="note mt"><b>Catatan prodi</b><?= e($pj['catatan_prodi']) ?></div><?php endif; ?>

        <?php if (is_pengelola() && in_array($pj['status'], ['Diajukan', 'Revisi'], true)): ?>
          <form method="post" class="mt">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="proses"><input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-row"><label for="status">Keputusan</label>
              <select class="select" id="status" name="status" required>
                <option value="">— Pilih —</option><option>Disetujui</option><option value="Revisi">Minta revisi</option><option>Ditolak</option>
              </select></div>
            <div class="form-row mt"><label for="catatan_prodi">Catatan untuk mahasiswa</label>
              <textarea class="input" id="catatan_prodi" name="catatan_prodi" rows="3" placeholder="Wajib diisi bila minta revisi atau menolak"></textarea></div>
            <div class="form-actions"><button class="btn" type="submit"><?= icon('check') ?>Simpan keputusan</button></div>
          </form>
        <?php elseif ($u['role'] === 'MAHASISWA' && $pj['status'] === 'Diajukan'): ?>
          <form method="post" class="mt" data-konfirmasi="Batalkan pengajuan judul ini?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-outline" type="submit"><?= icon('trash') ?>Batalkan pengajuan</button>
          </form>
        <?php elseif ($u['role'] === 'MAHASISWA' && $pj['status'] === 'Ditolak'): ?>
          <a class="btn mt" href="pengajuan.php?aksi=baru"><?= icon('plus') ?>Ajukan judul baru</a>
        <?php endif; ?>
      </section>
    </div>

    <?php if ($u['role'] === 'MAHASISWA' && $pj['status'] === 'Revisi'):
        $nilai = $_SESSION['form_pengajuan'] ?? $pj; unset($_SESSION['form_pengajuan']); ?>
      <section class="card card-pad mb">
        <div class="card-head"><h2>Perbaiki Judul</h2></div>
        <?= form_judul($nilai, $dosenAktif, 'perbaiki', $id) ?>
      </section>
    <?php endif; ?>

    <section class="card mb" id="pembimbing">
      <div class="card-head card-pad" style="margin:0"><h2>Dosen Pembimbing</h2></div>
      <?php if (!$pembimbing): ?>
        <p class="card-body muted"><?= $pj['status'] === 'Disetujui' ? 'Belum ada pembimbing yang ditetapkan.' : 'Pembimbing ditetapkan setelah judul disetujui prodi.' ?></p>
      <?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Peran</th><th>Dosen</th><th>NIP</th><th>No. SK</th><th>Tanggal SK</th><?php if (is_pengelola()): ?><th>Aksi</th><?php endif; ?></tr></thead>
          <tbody><?php foreach ($pembimbing as $pb): ?>
            <tr><td><?= e($pb['peran']) ?></td><td><?= e(nama_dosen($pb)) ?></td><td><?= e($pb['nip'] ?: '-') ?></td><td><?= e($pb['nomor_sk'] ?: '-') ?></td><td><?= tgl($pb['tanggal_sk']) ?></td>
              <?php if (is_pengelola()): ?><td>
                <form method="post" data-konfirmasi="Lepas <?= e(nama_dosen($pb)) ?> dari pembimbing?"><?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="pembimbing_hapus"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="id_pembimbing" value="<?= (int) $pb['id_pembimbing'] ?>">
                  <button class="icon-btn del" type="submit" aria-label="Lepas pembimbing"><?= icon('trash') ?></button></form></td><?php endif; ?></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
      <?php if (is_pengelola() && $pj['status'] === 'Disetujui' && count($pembimbing) < 2): ?>
        <details class="inline-form" <?= $pembimbing ? '' : 'open' ?>>
          <summary><?= icon('plus') ?>Tetapkan pembimbing</summary>
          <form method="post" class="card-body">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="pembimbing_tambah"><input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-grid">
              <div class="form-row"><label for="id_dosen">Dosen</label><select class="select" id="id_dosen" name="id_dosen" required><?= opsi_dosen($dosenAktif, $pembimbing ? null : $pj['usulan_dosen']) ?></select></div>
              <div class="form-row"><label for="peran">Peran</label><select class="select" id="peran" name="peran">
                <?php foreach (['Pembimbing 1', 'Pembimbing 2'] as $pr): if (in_array($pr, array_column($pembimbing, 'peran'), true)) continue; ?><option><?= $pr ?></option><?php endforeach; ?>
              </select></div>
              <div class="form-row"><label for="nomor_sk">Nomor SK <span class="muted">(opsional)</span></label><input class="input" id="nomor_sk" name="nomor_sk" maxlength="100"></div>
              <div class="form-row"><label for="tanggal_sk">Tanggal SK</label><input class="input" id="tanggal_sk" name="tanggal_sk" type="date"></div>
            </div>
            <div class="form-actions"><button class="btn" type="submit"><?= icon('check') ?>Tetapkan</button></div>
          </form>
        </details>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="subtabs" role="tablist" aria-label="Rincian">
        <button class="tab" type="button" role="tab" id="tab-bimbingan" aria-controls="p-bimbingan" aria-selected="true">Bimbingan (<?= count($bimbingan) ?>)</button>
        <button class="tab" type="button" role="tab" id="tab-dokumen" aria-controls="p-dokumen" aria-selected="false" tabindex="-1">Dokumen (<?= $dokValid ?>/<?= $dokWajib ?>)</button>
        <button class="tab" type="button" role="tab" id="tab-ujian" aria-controls="p-ujian" aria-selected="false" tabindex="-1">Seminar &amp; Sidang (<?= count($ujian) ?>)</button>
      </div>
      <div class="subpanel" id="p-bimbingan" role="tabpanel" aria-labelledby="tab-bimbingan">
        <?php if (!$bimbingan): ?><?= kosong('Belum ada catatan bimbingan.') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Tanggal</th><th>Dosen</th><th>Topik</th><th>Catatan Dosen</th><th>Status</th></tr></thead>
          <tbody><?php foreach ($bimbingan as $b): ?>
            <tr><td class="nowrap"><?= tgl($b['tanggal']) ?></td><td><?= e(nama_dosen($b)) ?></td><td><?= e($b['topik']) ?></td>
              <td><?= $b['catatan_dosen'] ? e(mb_strimwidth($b['catatan_dosen'], 0, 120, '…')) : '<span class="muted">-</span>' ?></td><td><?= badge($b['status']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
        <div class="subpanel-foot"><a class="link" href="bimbingan.php<?= $u['role'] === 'MAHASISWA' ? '' : '?id_pengajuan=' . $id ?>">Buka halaman bimbingan →</a></div>
      </div>
      <div class="subpanel" id="p-dokumen" role="tabpanel" aria-labelledby="tab-dokumen" hidden>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Tahap</th><th>Dokumen</th><th>Wajib</th><th>Berkas</th><th>Status</th></tr></thead>
          <tbody><?php foreach ($dokumen as $d): ?>
            <tr><td><?= e($d['tahap']) ?></td><td><?= e($d['nama_dokumen']) ?></td><td><?= $d['wajib'] ? 'Ya' : 'Tidak' ?></td>
              <td><?= $d['id_dokumen'] ? '<a class="file-link" href="unduh.php?jenis=dokumen&amp;id=' . (int) $d['id_dokumen'] . '">' . icon('download') . e($d['nama_file_asli']) . '</a>' : '<span class="muted">-</span>' ?></td>
              <td><?= badge($d['status_dokumen']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <div class="subpanel-foot"><a class="link" href="dokumen.php<?= is_staf() ? '?mhs=' . (int) $pj['id_mahasiswa'] : '' ?>"><?= is_pengelola() ? 'Verifikasi dokumen →' : 'Buka halaman dokumen →' ?></a></div>
      </div>
      <div class="subpanel" id="p-ujian" role="tabpanel" aria-labelledby="tab-ujian" hidden>
        <?php if (!$ujian): ?><?= kosong('Belum ada jadwal seminar atau sidang.') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Jenis</th><th>Jadwal</th><th>Ruangan</th><th>Status</th><th>Hasil</th><th>Nilai</th></tr></thead>
          <tbody><?php foreach ($ujian as $j): ?>
            <tr><td><a class="link" href="ujian.php?jenis=<?= $j['kode'] ?>&amp;id=<?= (int) $j['id'] ?>"><?= e($j['jenis']) ?></a></td><td class="nowrap"><?= tgl($j['tanggal']) ?>, <?= jam($j['jam_mulai']) ?></td>
              <td><?= e($j['ruangan']) ?></td><td><?= badge($j['status']) ?></td><td><?= $j['hasil'] ? badge($j['hasil']) : '-' ?></td><td><?= $j['nilai'] ?? '-' ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
      </div>
    </section>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   DAFTAR
   ===================================================================== */
if ($u['role'] === 'MAHASISWA') {
    if ($aktif = pengajuan_aktif_mahasiswa($u['id_mahasiswa'])) redirect('pengajuan.php?id=' . $aktif['id_pengajuan']);
    $riwayat = db_rows('SELECT * FROM pengajuan_judul WHERE id_mahasiswa = ? ORDER BY tanggal_pengajuan DESC', [$u['id_mahasiswa']]);
    layout_start('Pengajuan Judul', 'pengajuan', 'Ajukan judul skripsi untuk disetujui program studi.',
        '<a class="btn" href="pengajuan.php?aksi=baru">' . icon('plus') . 'Ajukan judul</a>');
    echo '<section class="card">';
    if (!$riwayat) {
        echo kosong('Anda belum pernah mengajukan judul.', '<a class="btn" href="pengajuan.php?aksi=baru">' . icon('plus') . 'Ajukan judul sekarang</a>');
    } else {
        echo '<div class="card-head card-pad" style="margin:0"><h2>Riwayat pengajuan</h2></div><div class="table-wrap"><table class="table"><thead><tr><th>Tanggal</th><th>Judul</th><th>Status</th><th>Catatan Prodi</th></tr></thead><tbody>';
        foreach ($riwayat as $r) {
            echo '<tr><td class="nowrap">' . tgl($r['tanggal_pengajuan']) . '</td><td><a class="link" href="pengajuan.php?id=' . (int) $r['id_pengajuan'] . '">' . e($r['judul']) . '</a></td><td>' . badge($r['status']) . '</td><td>' . e($r['catatan_prodi'] ?: '-') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    layout_end();
    exit;
}

if ($u['role'] === 'DOSEN') redirect('pembimbing.php');

/* ----- Staf ----- */
[$sc, $sp] = scope_staf('m');
$where = "FROM pengajuan_judul pj JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa WHERE 1=1 $sc";
$param = $sp;
$cari = get('cari');
$status = get('status');
if ($cari !== '') {
    $where .= ' AND (m.nama_lengkap LIKE ? OR m.npm LIKE ? OR pj.judul LIKE ?)';
    array_push($param, "%$cari%", "%$cari%", "%$cari%");
}
if (in_array($status, ['Diajukan', 'Revisi', 'Disetujui', 'Ditolak'], true)) {
    $where .= ' AND pj.status = ?';
    $param[] = $status;
}
$pg = paginasi((int) db_val("SELECT COUNT(*) $where", $param), 10);
$rows = db_rows("SELECT pj.*, m.nama_lengkap, m.npm, m.nama_program_studi,
                        (SELECT COUNT(*) FROM pembimbing pb WHERE pb.id_pengajuan = pj.id_pengajuan) AS jml_pembimbing
                 $where ORDER BY FIELD(pj.status, 'Diajukan', 'Revisi', 'Disetujui', 'Ditolak'), pj.tanggal_pengajuan DESC LIMIT ? OFFSET ?",
    array_merge($param, [$pg['per'], $pg['offset']]));

layout_start('Pengajuan Judul', 'pengajuan', is_role('DEKANAT') ? 'Mode lihat saja.' : 'Proses judul yang diajukan mahasiswa dan tetapkan pembimbing.');
?>
<section class="card">
  <form class="filters" method="get" data-auto>
    <div class="search"><?= icon('search') ?><label class="sr-only" for="cari">Cari</label>
      <input class="input" id="cari" name="cari" type="search" value="<?= e($cari) ?>" placeholder="Cari nama, NPM, atau judul…"></div>
    <label class="sr-only" for="f-status">Status</label>
    <select class="select" id="f-status" name="status"><option value="">Semua status</option>
      <?php foreach (['Diajukan', 'Revisi', 'Disetujui', 'Ditolak'] as $s): ?><option<?= $status === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
    <button class="btn btn-outline" type="submit"><?= icon('search') ?>Cari</button>
  </form>
  <?php if (!$rows): ?><?= kosong($cari || $status ? 'Tidak ada pengajuan yang cocok dengan pencarian.' : 'Belum ada pengajuan judul.') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>No</th><th>Nama</th><th>NPM</th><th>Judul</th><th>Prodi</th><th>Pembimbing</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody><?php foreach ($rows as $i => $r): ?>
      <tr><td><?= $pg['offset'] + $i + 1 ?></td><td><?= e($r['nama_lengkap']) ?></td><td><?= e($r['npm']) ?></td>
        <td class="judul" title="<?= e($r['judul']) ?>"><?= e($r['judul']) ?></td><td><?= e($r['nama_program_studi']) ?></td>
        <td><?= $r['status'] === 'Disetujui' ? (int) $r['jml_pembimbing'] . '/2' : '-' ?></td><td><?= badge($r['status']) ?></td>
        <td><a class="icon-btn view" href="pengajuan.php?id=<?= (int) $r['id_pengajuan'] ?>" aria-label="Detail <?= e($r['nama_lengkap']) ?>"><?= icon('eye') ?></a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
