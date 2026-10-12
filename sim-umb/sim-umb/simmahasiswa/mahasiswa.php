<?php
/**
 * mahasiswa.php — CRUD tabel `mahasiswa`.
 * Admin: kelola semua. Operator prodi: kelola mahasiswa di prodinya saja.
 * Rektorat/Dekanat: lihat sesuai cakupan. Semua query dibatasi cakupan peran (lihat includes/app.php).
 *
 * Akun login mahasiswa (tabel `pengguna`):
 *   - "Buat akun baru": akun role MAHASISWA dibuat otomatis (username = NPM, email diisi, kata sandi diketik
 *     atau dibuat acak) lalu ditautkan ke mahasiswa.id_pengguna — dalam SATU transaksi bersama data mahasiswa.
 *   - "Tautkan akun yang ada" / "Tanpa akun" juga tersedia.
 *   - Nama & program studi akun ikut diperbarui saat data mahasiswa diubah; akun ikut dihapus saat mahasiswa dihapus.
 */
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/layout.php';

$user = wajib_login($koneksi);
wajib_izin($user, 'mahasiswa');
$kelola = izin($user, 'mahasiswa', 'kelola');
$file = 'mahasiswa.php';
$saya = (int) $user['id_pengguna'];

$aksi = (string) ($_GET['aksi'] ?? '');
$data = ['id_mahasiswa' => 0, 'id_pengguna' => '', 'id_program_studi' => '', 'npm' => '', 'nama_mahasiswa' => '', 'jenis_kelamin' => '',
         'tempat_lahir' => '', 'tanggal_lahir' => '', 'tanggal_masuk' => date('Y-m-d'), 'alamat' => '', 'status_mahasiswa' => 'Aktif'];
$err  = [];

/* Program studi dalam cakupan pengguna */
[$wP, $pP] = cakupan_sql($user['cakupan'], 'f.id_universitas', 'ps.id_fakultas', 'ps.id_program_studi');
$prodiCakupan = db_rows($koneksi, "SELECT ps.id_program_studi, ps.kode_program_studi, ps.nama_program_studi, ps.jenjang, f.kode_fakultas
                                     FROM program_studi ps JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
                                    WHERE $wP ORDER BY f.kode_fakultas, ps.nama_program_studi", $pP);
$prodiIds = array_map('intval', array_column($prodiCakupan, 'id_program_studi'));
$prodiOpt = array_map(static fn($r) => ['v' => $r['id_program_studi'], 'l' => $r['kode_fakultas'] . ' · ' . $r['jenjang'] . ' ' . $r['nama_program_studi']], $prodiCakupan);

/** Mahasiswa milik cakupan pengguna (atau null). */
function mhs_dalam_cakupan(mysqli $db, array $user, int $id): ?array
{
    [$w, $p] = cakupan_sql($user['cakupan'], 'v.id_universitas', 'v.id_fakultas', 'v.id_program_studi');
    $ada = db_val($db, "SELECT 1 FROM v_mahasiswa_per_prodi v WHERE v.id_mahasiswa = ? AND $w", array_merge([$id], $p));
    return $ada ? db_row($db, 'SELECT * FROM mahasiswa WHERE id_mahasiswa = ?', [$id]) : null;
}

/** Akun berperan MAHASISWA yang bisa ditautkan (belum tertaut ke mahasiswa lain). */
function akun_mahasiswa(mysqli $db, array $user, int $idMhs): array
{
    $w = "r.kode_role = 'MAHASISWA' AND (m.id_mahasiswa IS NULL OR m.id_mahasiswa = ?)";
    $p = [$idMhs];
    if (($user['cakupan']['jenis'] ?? '') === 'prodi') { $w .= ' AND (p.id_program_studi IS NULL OR p.id_program_studi = ?)'; $p[] = $user['cakupan']['id']; }
    return db_rows($db, "SELECT p.id_pengguna, p.nama_lengkap, p.email FROM pengguna p JOIN roles r ON r.id_role = p.id_role
                           LEFT JOIN mahasiswa m ON m.id_pengguna = p.id_pengguna WHERE $w ORDER BY p.nama_lengkap", $p);
}

/** Kata sandi acak untuk akun baru (tanpa huruf yang mirip: 0/O, 1/l/I). */
function sandi_acak(int $n = 10): string
{
    $huruf = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $huruf[random_int(0, strlen($huruf) - 1)];
    return $s;
}

/** Samakan nama & program studi akun (khusus role MAHASISWA) dengan data mahasiswanya. */
function sinkron_akun(mysqli $db, int $idAkun, string $nama, int $idProdi): void
{
    db_jalankan($db, "UPDATE pengguna p
                        JOIN roles r ON r.id_role = p.id_role
                        JOIN program_studi ps ON ps.id_program_studi = ?
                        JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
                         SET p.nama_lengkap = ?, p.id_program_studi = ps.id_program_studi, p.id_fakultas = f.id_fakultas, p.id_universitas = f.id_universitas
                       WHERE p.id_pengguna = ? AND r.kode_role = 'MAHASISWA'", [$idProdi, $nama, $idAkun]);
}

$idRoleMhs = (int) db_val($koneksi, "SELECT id_role FROM roles WHERE kode_role = 'MAHASISWA' LIMIT 1");
$akunLama  = 0;                                    // id akun yang sudah tertaut (saat edit)
$akun      = ['mode' => 'baru', 'email' => ''];    // isian bagian "Akun login"

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wajib_csrf();
    wajib_izin($user, 'mahasiswa', 'kelola');
    $id = post_int('id');
    $lama = $id ? mhs_dalam_cakupan($koneksi, $user, $id) : null;
    if ($id && !$lama) tidak_ditemukan($file, 'Mahasiswa');
    $akunLama = (int) ($lama['id_pengguna'] ?? 0);

    /* ---- Hapus: akun login ber-role MAHASISWA milik mahasiswa ini ikut dihapus ---- */
    if (($_POST['op'] ?? '') === 'hapus') {
        $akunMhs = $akunLama ? db_row($koneksi, "SELECT p.id_pengguna, p.username, p.nama_lengkap, p.email, r.kode_role
                                                    FROM pengguna p JOIN roles r ON r.id_role = p.id_role WHERE p.id_pengguna = ?", [$akunLama]) : null;
        $hapusAkun = $akunMhs && $akunMhs['kode_role'] === 'MAHASISWA' && (int) $akunMhs['id_pengguna'] !== $saya;
        $koneksi->begin_transaction();
        try {
            db_jalankan($koneksi, 'DELETE FROM mahasiswa WHERE id_mahasiswa = ?', [$id]);
            audit($koneksi, $saya, 'DELETE', 'mahasiswa', (string) $id, $lama);
            if ($hapusAkun) {
                db_jalankan($koneksi, 'DELETE FROM pengguna WHERE id_pengguna = ?', [$akunLama]);
                audit($koneksi, $saya, 'DELETE', 'pengguna', (string) $akunLama, $akunMhs);
            }
            $koneksi->commit();
            flash('ok', 'Mahasiswa "' . $lama['nama_mahasiswa'] . '" (' . $lama['npm'] . ') dihapus' . ($hapusAkun ? ' beserta akun login-nya.' : '.'));
        } catch (mysqli_sql_exception $ex) {
            $koneksi->rollback();
            flash('error', pesan_db($ex));
        }
        pindah($file);
    }

    $data = [
        'id_mahasiswa'     => $id,
        'id_pengguna'      => 0,
        'id_program_studi' => post_int('id_program_studi'),
        'npm'              => strtoupper(post_teks('npm', 30)),
        'nama_mahasiswa'   => post_teks('nama_mahasiswa', 200),
        'jenis_kelamin'    => post_teks('jenis_kelamin', 1),
        'tempat_lahir'     => post_teks('tempat_lahir', 100),
        'tanggal_lahir'    => post_teks('tanggal_lahir', 10),
        'tanggal_masuk'    => post_teks('tanggal_masuk', 10),
        'alamat'           => post_teks('alamat', 1000),
        'status_mahasiswa' => post_teks('status_mahasiswa', 30),
    ];

    /* ---- Mode akun: baru (buat otomatis) / tautkan (akun yang ada) / tanpa ---- */
    $akun['mode'] = (string) ($_POST['akun_mode'] ?? '');
    if ($akunLama) {
        $akun['mode'] = 'tautkan';                        // sudah tertaut: diatur lewat pilihan akun
    } elseif (!in_array($akun['mode'], ['baru', 'tautkan', 'tanpa'], true)) {
        $akun['mode'] = post_int('id_pengguna') ? 'tautkan' : 'tanpa';
    }
    if ($akun['mode'] === 'tautkan') $data['id_pengguna'] = post_int('id_pengguna');
    $akun['email'] = $akun['mode'] === 'baru' ? strtolower(post_teks('akun_email', 150)) : '';
    $sandiKetik    = $akun['mode'] === 'baru' ? (string) ($_POST['akun_password'] ?? '') : '';

    if (!in_array($data['id_program_studi'], $prodiIds, true)) $err['id_program_studi'] = 'Pilih program studi (dalam cakupanmu).';
    if (!preg_match('/^[A-Z0-9.-]{3,30}$/', $data['npm'])) $err['npm'] = 'NPM 3–30 karakter: angka/huruf, titik, atau strip.';
    elseif (db_val($koneksi, 'SELECT COUNT(*) FROM mahasiswa WHERE npm = ? AND id_mahasiswa <> ?', [$data['npm'], $id])) $err['npm'] = 'NPM ini sudah terdaftar.';
    if ($data['nama_mahasiswa'] === '') $err['nama_mahasiswa'] = 'Nama mahasiswa wajib diisi.';
    if (!in_array($data['jenis_kelamin'], ['L', 'P'], true)) $err['jenis_kelamin'] = 'Pilih jenis kelamin.';
    if ($data['tanggal_lahir'] !== '' && (tanggal_valid($data['tanggal_lahir']) === '' || $data['tanggal_lahir'] > date('Y-m-d'))) $err['tanggal_lahir'] = 'Tanggal lahir tidak valid.';
    if (tanggal_valid($data['tanggal_masuk']) === '') $err['tanggal_masuk'] = 'Tanggal masuk wajib diisi.';
    if (!in_array($data['status_mahasiswa'], STATUS_MHS, true)) $err['status_mahasiswa'] = 'Pilih status.';
    if ($data['id_pengguna'] && !in_array($data['id_pengguna'], array_map('intval', array_column(akun_mahasiswa($koneksi, $user, $id), 'id_pengguna')), true)) {
        $err['id_pengguna'] = 'Akun tidak tersedia (bukan akun mahasiswa atau sudah tertaut).';
    }
    if ($akun['mode'] === 'tautkan' && !$akunLama && !$data['id_pengguna']) $err['id_pengguna'] = 'Pilih akun yang akan ditautkan.';
    if ($akun['mode'] === 'baru') {
        if (!$idRoleMhs) {
            $err['akun_email'] = 'Role MAHASISWA tidak ada di tabel roles, jadi akun belum bisa dibuat.';
        } elseif (!filter_var($akun['email'], FILTER_VALIDATE_EMAIL)) {
            $err['akun_email'] = 'Email wajib diisi dengan format yang benar (dipakai untuk login).';
        } elseif (db_val($koneksi, 'SELECT COUNT(*) FROM pengguna WHERE email = ?', [$akun['email']])) {
            $err['akun_email'] = 'Email ini sudah dipakai akun lain.';
        } elseif (!isset($err['npm']) && db_val($koneksi, 'SELECT COUNT(*) FROM pengguna WHERE username = ?', [$data['npm']])) {
            $err['akun_email'] = 'Sudah ada akun dengan username ' . $data['npm'] . '. Pilih "Tautkan akun yang ada".';
        }
        if ($sandiKetik !== '' && (strlen($sandiKetik) < 8 || strlen($sandiKetik) > 72)) {
            $err['akun_password'] = 'Kata sandi 8–72 karakter, atau kosongkan agar dibuat otomatis.';
        }
    }

    if (!$err) {
        $koneksi->begin_transaction(); // akun + mahasiswa tersimpan bersama, atau tidak sama sekali
        try {
            $infoAkun = '';
            if ($akun['mode'] === 'baru') {
                $sandiAwal = $sandiKetik !== '' ? $sandiKetik : sandi_acak();
                $pr = db_row($koneksi, 'SELECT ps.id_fakultas, f.id_universitas FROM program_studi ps JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
                                         WHERE ps.id_program_studi = ?', [$data['id_program_studi']]);
                $akunBaru = ['id_role' => $idRoleMhs, 'id_universitas' => (int) $pr['id_universitas'], 'id_fakultas' => (int) $pr['id_fakultas'],
                             'id_program_studi' => $data['id_program_studi'], 'username' => $data['npm'], 'nama_lengkap' => $data['nama_mahasiswa'],
                             'email' => $akun['email'], 'status_aktif' => 'Aktif'];
                db_jalankan($koneksi, 'INSERT INTO pengguna (id_role, id_universitas, id_fakultas, id_program_studi, username, nama_lengkap, email, status_aktif, password_hash)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge(array_values($akunBaru), [password_hash($sandiAwal, PASSWORD_DEFAULT)]));
                $data['id_pengguna'] = (int) $koneksi->insert_id;
                audit($koneksi, $saya, 'INSERT', 'pengguna', (string) $data['id_pengguna'], null, $akunBaru);
                $infoAkun = ' Akun login dibuat — email: ' . $akun['email'] . ', kata sandi awal: ' . $sandiAwal
                          . ' (catat sekarang, tidak ditampilkan lagi; bisa diganti di menu Profil saya).';
            }

            $nilai = [$data['id_pengguna'] ?: null, $data['id_program_studi'], $data['npm'], $data['nama_mahasiswa'], $data['jenis_kelamin'],
                      $data['tempat_lahir'] ?: null, $data['tanggal_lahir'] ?: null, $data['tanggal_masuk'], $data['alamat'] ?: null, $data['status_mahasiswa']];
            if ($id) {
                db_jalankan($koneksi, 'UPDATE mahasiswa SET id_pengguna=?, id_program_studi=?, npm=?, nama_mahasiswa=?, jenis_kelamin=?, tempat_lahir=?,
                                       tanggal_lahir=?, tanggal_masuk=?, alamat=?, status_mahasiswa=? WHERE id_mahasiswa = ?', array_merge($nilai, [$id]));
                audit($koneksi, $saya, 'UPDATE', 'mahasiswa', (string) $id, $lama, $data);
                $pesan = 'Data mahasiswa ' . $data['npm'] . ' diperbarui.';
            } else {
                db_jalankan($koneksi, 'INSERT INTO mahasiswa (id_pengguna, id_program_studi, npm, nama_mahasiswa, jenis_kelamin, tempat_lahir, tanggal_lahir, tanggal_masuk, alamat, status_mahasiswa)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $nilai);
                $baru = (int) $koneksi->insert_id;
                audit($koneksi, $saya, 'INSERT', 'mahasiswa', (string) $baru, null, $data);
                $pesan = 'Mahasiswa "' . $data['nama_mahasiswa'] . '" (' . $data['npm'] . ') ditambahkan.';
            }
            if ($akun['mode'] === 'tautkan' && $data['id_pengguna']) {
                sinkron_akun($koneksi, $data['id_pengguna'], $data['nama_mahasiswa'], $data['id_program_studi']);
            }
            $koneksi->commit();
            flash('ok', $pesan . $infoAkun);
            pindah($file);
        } catch (mysqli_sql_exception $ex) {
            $koneksi->rollback();
            if ($akun['mode'] === 'baru') $data['id_pengguna'] = 0;
            $err['_'] = pesan_db($ex, 'NPM sudah terdaftar, atau akun sudah tertaut ke mahasiswa lain.');
            if ($ex->getCode() === 1062) {
                $npmAda = db_val($koneksi, 'SELECT COUNT(*) FROM mahasiswa WHERE npm = ? AND id_mahasiswa <> ?', [$data['npm'], $id]);
                if ($npmAda) $err['npm'] = 'NPM ini sudah terdaftar.';
                elseif ($akun['mode'] === 'baru') $err['akun_email'] = 'Username atau email akun sudah dipakai.';
                else $err['id_pengguna'] = 'Akun ini sudah tertaut ke mahasiswa lain.';
            }
        }
    }
    $aksi = $id ? 'edit' : 'tambah';
} elseif ($aksi === 'edit') {
    $data = mhs_dalam_cakupan($koneksi, $user, (int) ($_GET['id'] ?? 0)) ?? tidak_ditemukan($file, 'Mahasiswa');
    $akunLama = (int) $data['id_pengguna'];
    $akun['mode'] = $akunLama ? 'tautkan' : 'tanpa';
}

layout_awal($user, 'Mahasiswa', 'mahasiswa', ['lebar' => true]);

if ($kelola && in_array($aksi, ['tambah', 'edit'], true)):
    $akunOpt = array_map(static fn($r) => ['v' => $r['id_pengguna'], 'l' => $r['nama_lengkap'] . ' (' . $r['email'] . ')'], akun_mahasiswa($koneksi, $user, (int) $data['id_mahasiswa']));
?>
  <?= kepala_halaman('Akademik', $aksi === 'edit' ? 'Ubah data mahasiswa' : 'Tambah mahasiswa', 'Kolom bertanda * wajib diisi.') ?>
  <form class="card form" method="post" action="<?= e($file) ?>" novalidate>
    <?= csrf_field() ?><?= ringkasan_error($err) ?>
    <input type="hidden" name="op" value="simpan">
    <input type="hidden" name="id" value="<?= (int) $data['id_mahasiswa'] ?>">
    <h2 class="form-h2">Data akademik</h2>
    <div class="form-grid">
      <?= f_input('npm', 'NPM', $data['npm'], $err, ['attr' => ['required' => true, 'maxlength' => 30]]) ?>
      <?= f_select('id_program_studi', 'Program studi', $prodiOpt, $data['id_program_studi'], $err, ['kosong' => '— Pilih program studi —', 'attr' => ['required' => true]]) ?>
      <?= f_input('tanggal_masuk', 'Tanggal masuk', $data['tanggal_masuk'], $err, ['type' => 'date', 'attr' => ['required' => true]]) ?>
      <?= f_select('status_mahasiswa', 'Status', pilihan_sederhana(STATUS_MHS), $data['status_mahasiswa'], $err, ['attr' => ['required' => true]]) ?>
    </div>
    <h2 class="form-h2">Data pribadi</h2>
    <div class="form-grid">
      <?= f_input('nama_mahasiswa', 'Nama lengkap', $data['nama_mahasiswa'], $err, ['attr' => ['required' => true, 'maxlength' => 200]]) ?>
      <?= f_select('jenis_kelamin', 'Jenis kelamin', [['v' => 'L', 'l' => 'Laki-laki'], ['v' => 'P', 'l' => 'Perempuan']], $data['jenis_kelamin'], $err, ['kosong' => '— Pilih —', 'attr' => ['required' => true]]) ?>
      <?= f_input('tempat_lahir', 'Tempat lahir', $data['tempat_lahir'], $err, ['attr' => ['maxlength' => 100]]) ?>
      <?= f_input('tanggal_lahir', 'Tanggal lahir', $data['tanggal_lahir'], $err, ['type' => 'date', 'attr' => ['max' => date('Y-m-d')]]) ?>
      <?= f_textarea('alamat', 'Alamat', $data['alamat'], $err, ['attr' => ['maxlength' => 1000]]) ?>
    </div>
    <h2 class="form-h2">Akun login</h2>
    <?php if ($akunLama): ?>
      <div class="form-grid">
        <?= f_select('id_pengguna', 'Akun mahasiswa', $akunOpt, $data['id_pengguna'], $err, ['kosong' => '— Lepas tautan akun —', 'lebar' => true,
              'bantuan' => 'Nama dan program studi akun ini ikut diperbarui setiap data mahasiswa disimpan.']) ?>
      </div>
    <?php else: ?>
      <div class="akun-pilih" role="radiogroup" aria-label="Akun login" data-akun-pilih>
        <?php foreach (['baru' => 'Buat akun baru', 'tautkan' => 'Tautkan akun yang ada', 'tanpa' => 'Tanpa akun'] as $v => $l): ?>
          <label class="akun-opt"><input type="radio" name="akun_mode" value="<?= $v ?>"<?= $akun['mode'] === $v ? ' checked' : '' ?>><span><?= e($l) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="form-grid" data-akun-bagian="baru">
        <?= f_input('akun_email', 'Email', $akun['email'], $err, ['type' => 'email', 'attr' => ['required' => true, 'maxlength' => 150, 'autocomplete' => 'off'],
              'bantuan' => 'Dipakai mahasiswa untuk login. Username otomatis = NPM, role = Mahasiswa.']) ?>
        <?= f_input('akun_password', 'Kata sandi awal', '', $err, ['type' => 'password', 'attr' => ['minlength' => 8, 'maxlength' => 72, 'autocomplete' => 'new-password'],
              'bantuan' => 'Opsional. Kosongkan agar dibuat otomatis; kata sandi ditampilkan sekali setelah disimpan.']) ?>
      </div>
      <div class="form-grid" data-akun-bagian="tautkan">
        <?= f_select('id_pengguna', 'Akun mahasiswa', $akunOpt, $data['id_pengguna'], $err, ['kosong' => '— Pilih akun —', 'lebar' => true,
              'bantuan' => 'Akun ber-role Mahasiswa yang belum tertaut ke mahasiswa lain.']) ?>
      </div>
      <p class="fld-help akun-tanpa" data-akun-bagian="tanpa">Mahasiswa ini belum bisa login. Akun bisa dibuat nanti lewat tombol ubah.</p>
    <?php endif; ?>
    <div class="form-act">
      <button class="btn-primary" type="submit">Simpan</button>
      <a class="btn-line" href="<?= e($file) ?>">Batal</a>
    </div>
  </form>
<?php else:
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $fProdi = (int) ($_GET['prodi'] ?? 0);
    $fStatus = in_array($_GET['status'] ?? '', STATUS_MHS, true) ? (string) $_GET['status'] : '';
    [$w, $params] = cakupan_sql($user['cakupan'], 'v.id_universitas', 'v.id_fakultas', 'v.id_program_studi');
    if ($q !== '') { $w .= ' AND (v.npm LIKE ? OR v.nama_mahasiswa LIKE ?)'; $l = '%' . like_escape($q) . '%'; array_push($params, $l, $l); }
    if ($fProdi) { $w .= ' AND v.id_program_studi = ?'; $params[] = $fProdi; }
    if ($fStatus !== '') { $w .= ' AND v.status_mahasiswa = ?'; $params[] = $fStatus; }
    $total = (int) db_val($koneksi, "SELECT COUNT(*) FROM v_mahasiswa_per_prodi v WHERE $w", $params);
    [$hal, $jmlHal, $offset] = hitung_halaman($total, (int) ($_GET['hal'] ?? 1));
    $rows = db_rows($koneksi, "SELECT v.id_mahasiswa, v.npm, v.nama_mahasiswa, v.jenis_kelamin_text, v.kode_program_studi, v.nama_program_studi, v.jenjang,
                                      v.tanggal_masuk, v.status_mahasiswa, m.id_pengguna, pg.email AS akun_email
                                 FROM v_mahasiswa_per_prodi v JOIN mahasiswa m ON m.id_mahasiswa = v.id_mahasiswa
                                 LEFT JOIN pengguna pg ON pg.id_pengguna = m.id_pengguna
                                WHERE $w ORDER BY v.nama_program_studi, v.npm LIMIT " . PER_HALAMAN . " OFFSET $offset", $params);
    $tombol = '<a class="btn-line" href="laporan.php">' . ikon('report') . 'Laporan rekap</a>'
            . ($kelola ? '<a class="btn-primary" href="mahasiswa.php?aksi=tambah">' . ikon('plus') . 'Tambah mahasiswa</a>' : '');
?>
  <?= kepala_halaman('Akademik', 'Data mahasiswa', $kelola ? 'Tambah, ubah, dan hapus data mahasiswa dalam cakupanmu.' : 'Data mahasiswa dalam cakupanmu (hanya lihat).', $tombol) ?>
  <section class="card">
    <form class="toolbar" method="get" action="<?= e($file) ?>" role="search">
      <label class="sr-only" for="q">Cari mahasiswa</label>
      <input id="q" name="q" type="search" value="<?= e($q) ?>" placeholder="Cari NPM atau nama…">
      <label class="sr-only" for="f-prodi">Program studi</label>
      <select id="f-prodi" name="prodi"><option value="">Semua program studi</option><?php foreach ($prodiCakupan as $p): ?><option value="<?= (int) $p['id_program_studi'] ?>"<?= $fProdi === (int) $p['id_program_studi'] ? ' selected' : '' ?>><?= e($p['nama_program_studi']) ?></option><?php endforeach; ?></select>
      <label class="sr-only" for="f-status">Status</label>
      <select id="f-status" name="status"><option value="">Semua status</option><?php foreach (STATUS_MHS as $s): ?><option<?= $fStatus === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
      <button class="btn-dark" type="submit">Cari</button>
      <?php if ($q !== '' || $fProdi || $fStatus !== ''): ?><a class="btn-line" href="<?= e($file) ?>">Reset</a><?php endif; ?>
      <span class="toolbar-info"><?= angka($total) ?> data</span>
    </form>
    <?php if (!$rows): ?>
      <p class="empty-note">Tidak ada mahasiswa yang cocok.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th scope="col">Mahasiswa</th><th scope="col">Program studi</th><th scope="col">Masuk</th><th scope="col">Status</th><th scope="col">Akun</th><?php if ($kelola): ?><th scope="col" class="act">Aksi</th><?php endif; ?></tr></thead>
          <tbody>
            <?php foreach ($rows as $m): ?>
              <tr>
                <td><span class="cell-person"><?= avatar($m['nama_mahasiswa']) ?><span><span class="row-name"><?= e($m['nama_mahasiswa']) ?></span><span class="row-sub"><?= e($m['npm']) ?> · <?= e($m['jenis_kelamin_text']) ?></span></span></span></td>
                <td><?= e($m['jenjang'] . ' ' . $m['nama_program_studi']) ?></td>
                <td class="nowrap"><?= e(tanggal_id($m['tanggal_masuk'])) ?></td>
                <td><?= chip_status($m['status_mahasiswa']) ?></td>
                <td><span class="row-sub"><?= $m['id_pengguna'] ? e($m['akun_email'] ?: 'Tertaut') : 'Belum ada' ?></span></td>
                <?php if ($kelola): ?><td class="act"><?= tombol_edit($file, (int) $m['id_mahasiswa'], $m['nama_mahasiswa']) ?><?= tombol_hapus($file, (int) $m['id_mahasiswa'], $m['nama_mahasiswa'] . ' (' . $m['npm'] . ')' . ($m['id_pengguna'] ? ' beserta akun login-nya' : '')) ?></td><?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= paginasi($file, $hal, $jmlHal, ['q' => $q, 'prodi' => $fProdi, 'status' => $fStatus]) ?>
    <?php endif; ?>
  </section>
<?php endif;

layout_akhir();
