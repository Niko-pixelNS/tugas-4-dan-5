<?php
/**
 * ujian.php?jenis=seminar | ujian.php?jenis=sidang
 * Seminar proposal & sidang skripsi memakai alur yang sama:
 *   Prodi/Admin: jadwalkan (cek syarat, dokumen, bentrok ruang/penguji), ubah, batalkan, isi hasil
 *   Dosen      : lihat jadwal sebagai penguji/pembimbing; Penguji 1 (ketua) mengisi hasil
 *   Mahasiswa  : lihat jadwal & hasil miliknya
 *   Dekanat    : lihat saja
 */
require __DIR__ . '/inc/init.php';
$u = require_login('ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'MAHASISWA', 'DOSEN');

const UJIAN = [
    'seminar' => ['tabel' => 'seminar_proposal', 'pk' => 'id_seminar', 'judul' => 'Seminar Proposal', 'tahap' => 'Seminar Proposal',
                  'penguji' => 2, 'penguji_wajib' => 1, 'nilai' => 'nilai'],
    'sidang'  => ['tabel' => 'sidang_skripsi', 'pk' => 'id_sidang', 'judul' => 'Sidang Skripsi', 'tahap' => 'Sidang Skripsi',
                  'penguji' => 3, 'penguji_wajib' => 2, 'nilai' => 'nilai_angka'],
];
$jenis = get('jenis', post('jenis', 'seminar'));
if (!isset(UJIAN[$jenis])) $jenis = 'seminar';
$J = UJIAN[$jenis];
$T = $J['tabel'];
$PK = $J['pk'];
$self = 'ujian.php?jenis=' . $jenis;

/** Konversi nilai angka ke huruf (sesuaikan dengan pedoman akademik UMB). */
function nilai_huruf(float $n): string
{
    foreach ([85 => 'A', 80 => 'A-', 75 => 'B+', 70 => 'B', 65 => 'B-', 60 => 'C+', 55 => 'C', 45 => 'D'] as $batas => $h) {
        if ($n >= $batas) return $h;
    }
    return 'E';
}

/** Syarat sebuah pengajuan boleh dijadwalkan. Mengembalikan alasan bila belum memenuhi, atau null. */
function syarat_ujian(string $jenis, array $pj): ?string
{
    $id = (int) $pj['id_pengajuan'];
    if ($pj['status'] !== 'Disetujui') return 'Judul belum disetujui';
    if (!db_val('SELECT COUNT(*) FROM pembimbing WHERE id_pengajuan = ?', [$id])) return 'Belum punya pembimbing';
    if ($jenis === 'seminar') {
        if (db_val("SELECT 1 FROM seminar_proposal WHERE id_pengajuan = ? AND (status = 'Dijadwalkan' OR (status = 'Selesai' AND hasil <> 'Tidak Lulus'))", [$id])) {
            return 'Seminar sudah dijadwalkan atau sudah lulus';
        }
    } else {
        if (!db_val("SELECT 1 FROM seminar_proposal WHERE id_pengajuan = ? AND status = 'Selesai' AND hasil <> 'Tidak Lulus'", [$id])) return 'Belum lulus seminar proposal';
        if (db_val("SELECT 1 FROM sidang_skripsi WHERE id_pengajuan = ? AND (status = 'Dijadwalkan' OR (status = 'Selesai' AND hasil <> 'Tidak Lulus'))", [$id])) {
            return 'Sidang sudah dijadwalkan atau sudah lulus';
        }
    }
    $kurang = dokumen_kurang((int) $pj['id_mahasiswa'], UJIAN[$jenis]['tahap']);
    return $kurang ? "$kurang dokumen wajib belum valid" : null;
}

/** Cek bentrok ruangan & penguji dengan seminar/sidang lain yang masih dijadwalkan. */
function cek_bentrok(string $jenis, int $kecuali, string $tgl, string $mulai, string $selesai, string $ruang, array $penguji): ?string
{
    $rows = db_rows(
        "SELECT 'seminar' AS j, id_seminar AS id, ruangan, id_penguji_1 AS p1, id_penguji_2 AS p2, NULL AS p3, jam_mulai, jam_selesai
           FROM seminar_proposal WHERE status = 'Dijadwalkan' AND tanggal = ? AND jam_mulai < ? AND jam_selesai > ?
         UNION ALL
         SELECT 'sidang', id_sidang, ruangan, id_penguji_1, id_penguji_2, id_penguji_3, jam_mulai, jam_selesai
           FROM sidang_skripsi WHERE status = 'Dijadwalkan' AND tanggal = ? AND jam_mulai < ? AND jam_selesai > ?",
        [$tgl, $selesai, $mulai, $tgl, $selesai, $mulai]);
    foreach ($rows as $r) {
        if ($r['j'] === $jenis && (int) $r['id'] === $kecuali) continue;
        $waktu = jam($r['jam_mulai']) . '–' . jam($r['jam_selesai']);
        if (mb_strtolower(trim($r['ruangan'])) === mb_strtolower($ruang)) return "Ruangan $ruang sudah dipakai pukul $waktu.";
        $sama = array_intersect($penguji, array_filter([(int) $r['p1'], (int) $r['p2'], (int) $r['p3']]));
        if ($sama) {
            $d = db_row('SELECT * FROM dosen WHERE id_dosen = ?', [reset($sama)]);
            return nama_dosen($d) . " sudah menguji di jadwal lain pukul $waktu.";
        }
    }
    return null;
}

/** Ambil satu ujian + data pengajuan, dan pastikan boleh dilihat. */
function ujian_ambil(string $T, string $PK, int $id): ?array
{
    $r = db_row("SELECT u.*, pj.judul, pj.id_mahasiswa, pj.status AS status_pengajuan, m.nama_lengkap, m.npm, m.nama_program_studi
                   FROM $T u JOIN pengajuan_judul pj ON pj.id_pengajuan = u.id_pengajuan
                   LEFT JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa WHERE u.$PK = ?", [$id]);
    if (!$r) return null;
    $pj = pengajuan_ambil((int) $r['id_pengajuan']);
    return boleh_lihat_pengajuan($pj) ? $r : null;
}

function penguji_ids(array $r, int $n): array
{
    $ids = [];
    for ($i = 1; $i <= $n; $i++) if (!empty($r["id_penguji_$i"])) $ids[] = (int) $r["id_penguji_$i"];
    return $ids;
}

/* =====================================================================
   AKSI
   ===================================================================== */
if (is_post()) {
    csrf_check();
    $aksi = post('aksi');
    $id = (int) post('id');

    /* ----- Jadwalkan / ubah jadwal ----- */
    if ($aksi === 'simpan' && is_pengelola()) {
        $idPj = (int) post('id_pengajuan');
        $tgl = post('tanggal');
        $mulai = post('jam_mulai');
        $selesai = post('jam_selesai');
        $ruang = mb_substr(post('ruangan'), 0, 100);
        $penguji = [];
        for ($i = 1; $i <= $J['penguji']; $i++) $penguji[$i] = post("id_penguji_$i") !== '' ? (int) post("id_penguji_$i") : null;
        $diisi = array_values(array_filter($penguji));
        $_SESSION['form_ujian'] = $_POST;
        $kembali = $self . ($id ? "&edit=$id" : '&aksi=baru');

        $lama = $id ? ujian_ambil($T, $PK, $id) : null;
        if ($id && (!$lama || $lama['status'] !== 'Dijadwalkan')) {
            flash('error', 'Jadwal ini tidak bisa diubah lagi.');
            redirect($self);
        }
        if ($id) $idPj = (int) $lama['id_pengajuan'];
        $pj = pengajuan_ambil($idPj);

        $err = null;
        if (!$pj || !boleh_lihat_pengajuan($pj)) $err = 'Pilih mahasiswa.';
        elseif (!$id && ($alasan = syarat_ujian($jenis, $pj))) $err = 'Belum bisa dijadwalkan: ' . $alasan . '.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) || $tgl < date('Y-m-d')) $err = 'Tanggal tidak boleh sebelum hari ini.';
        elseif (!preg_match('/^\d{2}:\d{2}$/', $mulai) || !preg_match('/^\d{2}:\d{2}$/', $selesai) || $selesai <= $mulai) $err = 'Jam selesai harus setelah jam mulai.';
        elseif ($ruang === '') $err = 'Isi ruangan.';
        elseif (count($diisi) < $J['penguji_wajib'] || !$penguji[1]) $err = 'Isi minimal ' . $J['penguji_wajib'] . ' penguji, dimulai dari Penguji 1.';
        elseif (count($diisi) !== count(array_unique($diisi))) $err = 'Satu dosen tidak boleh menjadi dua penguji.';
        else $err = cek_bentrok($jenis, $id, $tgl, $mulai, $selesai, $ruang, $diisi);
        if ($err) {
            flash('error', $err);
            redirect($kembali);
        }

        $kolom = ['tanggal' => $tgl, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai, 'ruangan' => $ruang];
        foreach ($penguji as $i => $p) $kolom["id_penguji_$i"] = $p;
        if ($jenis === 'sidang') $kolom['judul_akhir'] = post('judul_akhir') ?: null;

        if ($id) {
            $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($kolom)));
            db_exec("UPDATE $T SET $set WHERE $PK = ?", array_merge(array_values($kolom), [$id]));
            flash('success', 'Jadwal diperbarui.');
        } else {
            $kolom = ['id_pengajuan' => $idPj, 'dijadwalkan_oleh' => $u['id']] + $kolom;
            db_q("INSERT INTO $T (" . implode(', ', array_keys($kolom)) . ') VALUES (' . implode(', ', array_fill(0, count($kolom), '?')) . ')', array_values($kolom));
            $id = db_id();
            flash('success', $J['judul'] . ' dijadwalkan.');
        }
        unset($_SESSION['form_ujian']);
        redirect("$self&id=$id");
    }

    $r = ujian_ambil($T, $PK, $id);
    if (!$r) {
        flash('error', 'Data tidak ditemukan.');
        redirect($self);
    }

    /* ----- Batalkan ----- */
    if ($aksi === 'batal' && is_pengelola() && $r['status'] === 'Dijadwalkan') {
        db_exec("UPDATE $T SET status = 'Dibatalkan' WHERE $PK = ?", [$id]);
        flash('success', 'Jadwal dibatalkan.');
        redirect("$self&id=$id");
    }

    /* ----- Isi hasil: pengelola, atau dosen Penguji 1 (ketua) ----- */
    $bolehHasil = is_pengelola() || ($u['role'] === 'DOSEN' && (int) $r['id_penguji_1'] === $u['id']);
    if ($aksi === 'hasil' && $bolehHasil && $r['status'] === 'Dijadwalkan') {
        $hasil = post('hasil');
        $nilai = post('nilai') !== '' ? (float) str_replace(',', '.', post('nilai')) : null;
        if (!in_array($hasil, ['Lulus', 'Lulus dengan Revisi', 'Tidak Lulus'], true)) {
            flash('error', 'Pilih hasil.');
        } elseif ($nilai === null || $nilai < 0 || $nilai > 100) {
            flash('error', 'Nilai harus angka 0–100.');
        } elseif ($r['tanggal'] > date('Y-m-d')) {
            flash('error', 'Hasil baru bisa diisi pada atau setelah tanggal pelaksanaan.');
        } else {
            $set = ['status' => 'Selesai', 'hasil' => $hasil, $J['nilai'] => $nilai, 'catatan_revisi' => post('catatan_revisi') ?: null];
            if ($jenis === 'sidang') {
                $set['nilai_huruf'] = nilai_huruf($nilai);
                $set['batas_revisi'] = post('batas_revisi') ?: null;
            }
            $sql = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($set)));
            db_exec("UPDATE $T SET $sql WHERE $PK = ?", array_merge(array_values($set), [$id]));
            flash('success', 'Hasil ' . strtolower($J['judul']) . ' tersimpan.');
        }
        redirect("$self&id=$id");
    }
    redirect($self);
}

$dosenAktif = daftar_dosen_aktif();

/* =====================================================================
   FORM JADWAL (baru / ubah)
   ===================================================================== */
if (is_pengelola() && (get('aksi') === 'baru' || get('edit') !== '')) {
    $edit = get('edit') !== '' ? ujian_ambil($T, $PK, (int) get('edit')) : null;
    if (get('edit') !== '' && (!$edit || $edit['status'] !== 'Dijadwalkan')) redirect($self);
    $v = $_SESSION['form_ujian'] ?? ($edit ?: []);
    unset($_SESSION['form_ujian']);

    $siap = [];
    $belum = [];
    if (!$edit) {
        [$sc, $sp] = scope_staf('m');
        $calon = db_rows("SELECT pj.*, m.nama_lengkap, m.npm FROM pengajuan_judul pj JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
                           WHERE pj.status = 'Disetujui' $sc ORDER BY m.nama_lengkap", $sp);
        foreach ($calon as $c) {
            $alasan = syarat_ujian($jenis, $c);
            if ($alasan === null) $siap[] = $c;
            elseif (!str_contains($alasan, 'sudah')) $belum[] = $c + ['alasan' => $alasan];
        }
        if (get('id_pengajuan') !== '' && empty($v['id_pengajuan'])) $v['id_pengajuan'] = get('id_pengajuan');
    }
    $nilaiJam = fn ($k, $def) => e(isset($v[$k]) ? substr((string) $v[$k], 0, 5) : $def);

    layout_start(($edit ? 'Ubah Jadwal ' : 'Jadwalkan ') . $J['judul'], $jenis, '<a class="link" href="' . $self . '">' . $J['judul'] . '</a> › ' . ($edit ? e($edit['nama_lengkap']) : 'Jadwal baru'));
    if (!$edit && !$siap) {
        echo '<div class="alert alert-info">Belum ada mahasiswa yang memenuhi syarat ' . strtolower($J['judul']) . '.</div>';
    }
    ?>
    <section class="card card-pad mb">
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="jenis" value="<?= $jenis ?>"><input type="hidden" name="id" value="<?= (int) ($edit[$PK] ?? 0) ?>">
        <div class="form-grid">
          <div class="form-row full"><label for="id_pengajuan">Mahasiswa</label>
            <?php if ($edit): ?>
              <input class="input" value="<?= e($edit['nama_lengkap'] . ' (' . $edit['npm'] . ') — ' . $edit['judul']) ?>" disabled>
            <?php else: ?>
              <select class="select" id="id_pengajuan" name="id_pengajuan" required><option value="">— Pilih mahasiswa yang memenuhi syarat —</option>
                <?php foreach ($siap as $c): ?><option value="<?= (int) $c['id_pengajuan'] ?>"<?= (string) ($v['id_pengajuan'] ?? '') === (string) $c['id_pengajuan'] ? ' selected' : '' ?>><?= e($c['nama_lengkap'] . ' (' . $c['npm'] . ') — ' . mb_strimwidth($c['judul'], 0, 70, '…')) ?></option><?php endforeach; ?>
              </select>
            <?php endif; ?></div>
          <?php if ($jenis === 'sidang'): ?>
            <div class="form-row full"><label for="judul_akhir">Judul akhir <span class="muted">(isi bila berubah setelah seminar)</span></label>
              <input class="input" id="judul_akhir" name="judul_akhir" maxlength="300" value="<?= e($v['judul_akhir'] ?? '') ?>"></div>
          <?php endif; ?>
          <div class="form-row"><label for="tanggal">Tanggal</label><input class="input" id="tanggal" name="tanggal" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($v['tanggal'] ?? '') ?>" required></div>
          <div class="form-row"><label for="ruangan">Ruangan</label><input class="input" id="ruangan" name="ruangan" maxlength="100" value="<?= e($v['ruangan'] ?? '') ?>" placeholder="mis. Ruang Sidang FT" required></div>
          <div class="form-row"><label for="jam_mulai">Jam mulai</label><input class="input" id="jam_mulai" name="jam_mulai" type="time" value="<?= $nilaiJam('jam_mulai', '09:00') ?>" required></div>
          <div class="form-row"><label for="jam_selesai">Jam selesai</label><input class="input" id="jam_selesai" name="jam_selesai" type="time" value="<?= $nilaiJam('jam_selesai', '11:00') ?>" required></div>
          <?php for ($i = 1; $i <= $J['penguji']; $i++): ?>
            <div class="form-row"><label for="id_penguji_<?= $i ?>">Penguji <?= $i ?><?= $i === 1 ? ' (ketua)' : ($i > $J['penguji_wajib'] ? ' <span class="muted">(opsional)</span>' : '') ?></label>
              <select class="select" id="id_penguji_<?= $i ?>" name="id_penguji_<?= $i ?>"<?= $i <= $J['penguji_wajib'] ? ' required' : '' ?>><?= opsi_dosen($dosenAktif, $v["id_penguji_$i"] ?? null, $i <= $J['penguji_wajib'] ? '— Pilih dosen —' : '— Tidak ada —') ?></select></div>
          <?php endfor; ?>
        </div>
        <p class="help mt">Sistem menolak jadwal yang bentrok ruangan atau pengujinya dengan seminar/sidang lain pada jam yang sama.</p>
        <div class="form-actions"><button class="btn" type="submit"<?= !$edit && !$siap ? ' disabled' : '' ?>><?= icon('calendar') ?><?= $edit ? 'Simpan perubahan' : 'Jadwalkan' ?></button><a class="btn btn-outline" href="<?= $self ?>">Batal</a></div>
      </form>
    </section>
    <?php if ($belum): ?>
    <section class="card">
      <div class="card-head card-pad" style="margin:0"><h2>Belum Memenuhi Syarat</h2></div>
      <div class="table-wrap"><table class="table"><thead><tr><th>Mahasiswa</th><th>Judul</th><th>Alasan</th></tr></thead><tbody>
        <?php foreach ($belum as $b): ?><tr><td><?= e($b['nama_lengkap']) ?><br><span class="small muted"><?= e($b['npm']) ?></span></td>
          <td class="judul"><a class="link" href="pengajuan.php?id=<?= (int) $b['id_pengajuan'] ?>"><?= e($b['judul']) ?></a></td><td><?= e($b['alasan']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </section>
    <?php endif;
    layout_end();
    exit;
}

/* =====================================================================
   DETAIL
   ===================================================================== */
if (get('id') !== '') {
    $r = ujian_ambil($T, $PK, (int) get('id'));
    if (!$r) {
        flash('error', 'Data tidak ditemukan atau di luar kewenangan Anda.');
        redirect($self);
    }
    $id = (int) $r[$PK];
    $penguji = [];
    for ($i = 1; $i <= $J['penguji']; $i++) {
        if ($r["id_penguji_$i"]) $penguji[$i] = db_row('SELECT * FROM dosen WHERE id_dosen = ?', [$r["id_penguji_$i"]]);
    }
    $pembimbing = db_rows('SELECT pb.peran, d.* FROM pembimbing pb JOIN dosen d ON d.id_dosen = pb.id_dosen WHERE pb.id_pengajuan = ? ORDER BY pb.peran', [$r['id_pengajuan']]);
    $bolehHasil = is_pengelola() || ($u['role'] === 'DOSEN' && (int) $r['id_penguji_1'] === $u['id']);

    $aksiAtas = '<a class="btn btn-outline" href="' . $self . '">' . icon('arrowLeft') . 'Kembali</a>';
    if (is_pengelola() && $r['status'] === 'Dijadwalkan') {
        $aksiAtas .= '<a class="btn btn-light" href="' . $self . '&amp;edit=' . $id . '">' . icon('edit') . 'Ubah jadwal</a>'
            . '<form method="post" class="inline" data-konfirmasi="Batalkan jadwal ini?">' . csrf_field()
            . '<input type="hidden" name="aksi" value="batal"><input type="hidden" name="jenis" value="' . $jenis . '"><input type="hidden" name="id" value="' . $id . '">'
            . '<button class="btn btn-red" type="submit">' . icon('trash') . 'Batalkan</button></form>';
    }
    layout_start($J['judul'], $jenis, '<a class="link" href="' . $self . '">' . $J['judul'] . '</a> › ' . e($r['nama_lengkap']), $aksiAtas);
    ?>
    <div class="detail-grid">
      <section class="card card-pad">
        <div class="card-head"><h2>Jadwal</h2><?= badge($r['status']) ?></div>
        <?= info_list([
            'Mahasiswa' => e($r['nama_lengkap']) . ' (' . e($r['npm']) . ')',
            'Program Studi' => e($r['nama_program_studi']),
            'Judul' => '<a class="link" href="pengajuan.php?id=' . (int) $r['id_pengajuan'] . '">' . e($jenis === 'sidang' && $r['judul_akhir'] ? $r['judul_akhir'] : $r['judul']) . '</a>',
            'Hari, tanggal' => hari_tgl($r['tanggal']),
            'Waktu' => jam($r['jam_mulai']) . '–' . jam($r['jam_selesai']) . ' WIB',
            'Ruangan' => e($r['ruangan']),
        ]) ?>
      </section>
      <section class="card card-pad">
        <div class="card-head"><h2>Penguji &amp; Pembimbing</h2></div>
        <?php $baris = [];
        foreach ($penguji as $i => $d) $baris['Penguji ' . $i . ($i === 1 ? ' (ketua)' : '')] = e(nama_dosen($d));
        foreach ($pembimbing as $d) $baris[$d['peran']] = e(nama_dosen($d));
        echo info_list($baris); ?>
      </section>
    </div>

    <section class="card card-pad">
      <div class="card-head"><h2>Hasil</h2><?= $r['hasil'] ? badge($r['hasil']) : '' ?></div>
      <?php if ($r['status'] === 'Selesai'): ?>
        <?= info_list(array_filter([
            'Hasil' => badge($r['hasil']),
            'Nilai' => '<b>' . e($r[$J['nilai']]) . '</b>' . ($jenis === 'sidang' && $r['nilai_huruf'] ? ' (' . e($r['nilai_huruf']) . ')' : ''),
            'Batas revisi' => $jenis === 'sidang' && $r['batas_revisi'] ? tgl($r['batas_revisi'], true) : null,
        ])) ?>
        <?php if ($r['catatan_revisi']): ?><div class="note mt"><b>Catatan revisi</b><?= e($r['catatan_revisi']) ?></div><?php endif; ?>
      <?php elseif ($r['status'] === 'Dibatalkan'): ?>
        <p class="muted">Jadwal ini dibatalkan.</p>
      <?php elseif ($bolehHasil): ?>
        <?php if ($r['tanggal'] > date('Y-m-d')): ?><p class="muted">Hasil bisa diisi mulai <?= tgl($r['tanggal'], true) ?>.</p><?php else: ?>
        <form method="post" data-konfirmasi="Simpan hasil? Hasil tidak bisa diubah lagi.">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="hasil"><input type="hidden" name="jenis" value="<?= $jenis ?>"><input type="hidden" name="id" value="<?= $id ?>">
          <div class="form-grid">
            <div class="form-row"><label for="hasil">Hasil</label><select class="select" id="hasil" name="hasil" required><option value="">— Pilih —</option><option>Lulus</option><option>Lulus dengan Revisi</option><option>Tidak Lulus</option></select></div>
            <div class="form-row"><label for="nilai">Nilai akhir (0–100)</label><input class="input" id="nilai" name="nilai" type="number" min="0" max="100" step="0.01" required></div>
            <?php if ($jenis === 'sidang'): ?><div class="form-row"><label for="batas_revisi">Batas revisi <span class="muted">(opsional)</span></label><input class="input" id="batas_revisi" name="batas_revisi" type="date"></div><?php endif; ?>
            <div class="form-row full"><label for="catatan_revisi">Catatan revisi</label><textarea class="input" id="catatan_revisi" name="catatan_revisi" rows="4"></textarea></div>
          </div>
          <?php if ($jenis === 'sidang'): ?><p class="help mt">Nilai huruf dihitung otomatis: A ≥ 85, A- ≥ 80, B+ ≥ 75, B ≥ 70, B- ≥ 65, C+ ≥ 60, C ≥ 55, D ≥ 45.</p><?php endif; ?>
          <div class="form-actions"><button class="btn" type="submit"><?= icon('check') ?>Simpan hasil</button></div>
        </form>
        <?php endif; ?>
      <?php else: ?>
        <p class="muted">Hasil diisi oleh Penguji 1 atau program studi setelah pelaksanaan.</p>
      <?php endif; ?>
    </section>
    <?php
    layout_end();
    exit;
}

/* =====================================================================
   DAFTAR
   ===================================================================== */
$select = "SELECT u.*, pj.judul, m.nama_lengkap, m.npm, m.nama_program_studi,
                  d1.gelar_depan AS p1_gelar_depan, d1.nama_dosen AS p1_nama_dosen, d1.gelar_belakang AS p1_gelar_belakang
             FROM $T u JOIN pengajuan_judul pj ON pj.id_pengajuan = u.id_pengajuan
             LEFT JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
             JOIN dosen d1 ON d1.id_dosen = u.id_penguji_1";
$where = ' WHERE 1=1';
$param = [];
if ($u['role'] === 'MAHASISWA') {
    $where .= ' AND pj.id_mahasiswa = ?';
    $param[] = $u['id_mahasiswa'];
} elseif ($u['role'] === 'DOSEN') {
    $kol = $jenis === 'seminar' ? 'u.id_penguji_1, IFNULL(u.id_penguji_2, 0)' : 'u.id_penguji_1, u.id_penguji_2, IFNULL(u.id_penguji_3, 0)';
    $where .= " AND (? IN ($kol) OR EXISTS (SELECT 1 FROM pembimbing pb WHERE pb.id_pengajuan = u.id_pengajuan AND pb.id_dosen = ?))";
    array_push($param, $u['id'], $u['id']);
} else {
    [$sc, $sp] = scope_staf('m');
    $where .= $sc;
    $param = $sp;
}
$status = get('status');
if (in_array($status, ['Dijadwalkan', 'Selesai', 'Dibatalkan'], true)) {
    $where .= ' AND u.status = ?';
    $param[] = $status;
}
$pg = paginasi((int) db_val("SELECT COUNT(*) FROM ($select $where) x", $param), 10);
$rows = db_rows("$select $where ORDER BY u.status = 'Dijadwalkan' DESC, u.tanggal DESC, u.jam_mulai LIMIT ? OFFSET ?", array_merge($param, [$pg['per'], $pg['offset']]));

layout_start($J['judul'], $jenis, match ($u['role']) {
    'MAHASISWA' => 'Jadwal dan hasil ' . strtolower($J['judul']) . ' Anda.',
    'DOSEN' => 'Jadwal di mana Anda menjadi penguji atau pembimbing.',
    default => 'Jadwal, penguji, dan hasil ' . strtolower($J['judul']) . '.',
}, is_pengelola() ? '<a class="btn" href="' . $self . '&amp;aksi=baru">' . icon('plus') . 'Jadwalkan</a>' : '');

if ($u['role'] === 'MAHASISWA' && !$rows) {
    $pj = pengajuan_aktif_mahasiswa($u['id_mahasiswa']);
    $alasan = $pj ? syarat_ujian($jenis, $pj) : 'Belum ada pengajuan judul';
    if ($alasan) echo '<div class="alert alert-info">Belum bisa dijadwalkan: ' . e($alasan) . '. ' . (str_contains($alasan, 'dokumen') ? '<a class="link" href="dokumen.php">Lengkapi dokumen</a>.' : '') . '</div>';
}
?>
<section class="card">
  <form class="filters" method="get" data-auto>
    <input type="hidden" name="jenis" value="<?= $jenis ?>">
    <label class="sr-only" for="f-status">Status</label>
    <select class="select" id="f-status" name="status"><option value="">Semua status</option>
      <?php foreach (['Dijadwalkan', 'Selesai', 'Dibatalkan'] as $s): ?><option<?= $status === $s ? ' selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
  </form>
  <?php if (!$rows): ?><?= kosong('Belum ada jadwal ' . strtolower($J['judul']) . '.') ?><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Jadwal</th><th>Mahasiswa</th><th>Judul</th><th>Ruangan</th><th>Penguji 1</th><th>Status</th><th>Hasil</th><th>Aksi</th></tr></thead>
    <tbody><?php foreach ($rows as $r): ?>
      <tr><td class="nowrap"><?= tgl($r['tanggal']) ?><br><span class="small muted"><?= jam($r['jam_mulai']) ?>–<?= jam($r['jam_selesai']) ?></span></td>
        <td><b><?= e($r['nama_lengkap']) ?></b><br><span class="small muted"><?= e($r['npm']) ?></span></td>
        <td class="judul" title="<?= e($r['judul']) ?>"><?= e($r['judul']) ?></td><td><?= e($r['ruangan']) ?></td>
        <td><?= e(nama_dosen($r, 'p1_')) ?></td><td><?= badge($r['status']) ?></td>
        <td><?= $r['hasil'] ? badge($r['hasil']) . '<br><span class="small muted">Nilai ' . e($r[$J['nilai']]) . '</span>' : '-' ?></td>
        <td><a class="icon-btn view" href="<?= $self ?>&amp;id=<?= (int) $r[$PK] ?>" aria-label="Detail"><?= icon('eye') ?></a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <div class="table-foot"><span><?= info_paginasi($pg) ?></span><?= paginasi_html($pg) ?></div>
</section>
<?php
layout_end();
