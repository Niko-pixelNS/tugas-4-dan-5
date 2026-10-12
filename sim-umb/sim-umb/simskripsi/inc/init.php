<?php
/**
 * inc/init.php — dimuat di awal setiap halaman SIMSKRIPSI.
 * Isi: sesi, koneksi DB, fungsi query, CSRF, pesan flash, hak akses, helper tampilan.
 */
declare(strict_types=1);
ob_start();

require __DIR__ . '/../config/app.php';
date_default_timezone_set('Asia/Jakarta');

/* ---------- Sesi ---------- */
session_name('SIMSKRIPSI');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

/* ---------- Database ---------- */
try {
    require __DIR__ . '/../config/koneksi.php';
} catch (mysqli_sql_exception $ex) {
    error_log('Koneksi sim_skripsi gagal: ' . $ex->getMessage());
    http_response_code(500);
    exit('<p style="font-family:sans-serif;padding:24px">Tidak bisa terhubung ke database SIMSKRIPSI. '
        . (LINGKUNGAN === 'lokal'
            ? 'Pastikan MySQL di XAMPP berjalan dan <b>sim_skripsi.sql</b> sudah diimpor (pengaturan: <b>DB_LOKAL</b> di config/lingkungan.php).'
            : 'Periksa <b>DB_ONLINE</b> di config/lingkungan.php dan pastikan <b>sim_skripsi_hosting.sql</b> sudah diimpor.') . '</p>');
}

function db(): mysqli
{
    global $koneksi;
    return $koneksi;
}
function db_q(string $sql, array $params = []): mysqli_stmt
{
    $st = db()->prepare($sql);
    if ($params) {
        $params = array_values($params);
        $tipe = '';
        foreach ($params as $v) $tipe .= is_int($v) ? 'i' : (is_float($v) ? 'd' : 's');
        $st->bind_param($tipe, ...$params);
    }
    $st->execute();
    return $st;
}
function db_rows(string $sql, array $params = []): array
{
    return db_q($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}
function db_row(string $sql, array $params = []): ?array
{
    return db_q($sql, $params)->get_result()->fetch_assoc() ?: null;
}
function db_val(string $sql, array $params = [])
{
    $r = db_q($sql, $params)->get_result()->fetch_row();
    return $r[0] ?? null;
}
function db_exec(string $sql, array $params = []): int
{
    return db_q($sql, $params)->affected_rows;
}
function db_id(): int
{
    return (int) db()->insert_id;
}

/* ---------- Bantuan umum ---------- */
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
function post(string $k, string $default = ''): string
{
    return trim((string) ($_POST[$k] ?? $default));
}
function get(string $k, string $default = ''): string
{
    return trim((string) ($_GET[$k] ?? $default));
}
function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

const BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const BULAN_PANJANG = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function tgl(?string $d, bool $panjang = false): string
{
    if (!$d) return '-';
    $t = strtotime($d);
    $b = $panjang ? BULAN_PANJANG : BULAN;
    return date('j', $t) . ' ' . $b[(int) date('n', $t)] . ' ' . date('Y', $t);
}
function tgl_jam(?string $d): string
{
    return $d ? tgl($d) . ', ' . date('H.i', strtotime($d)) : '-';
}
function hari_tgl(?string $d): string
{
    return $d ? HARI[(int) date('w', strtotime($d))] . ', ' . tgl($d, true) : '-';
}
function jam(?string $t): string
{
    return $t ? substr($t, 0, 5) : '-';
}
function nama_dosen(array $d, string $prefix = ''): string
{
    $nama = trim(($d[$prefix . 'gelar_depan'] ?? '') . ' ' . ($d[$prefix . 'nama_dosen'] ?? ''));
    $gb = $d[$prefix . 'gelar_belakang'] ?? '';
    return $nama === '' ? '-' : $nama . ($gb ? ', ' . $gb : '');
}
function inisial(string $nama): string
{
    $kata = preg_split('/\s+/', trim(preg_replace('/^(Dr|Prof|Ir)\.?\s+/i', '', $nama)));
    return strtoupper(mb_substr($kata[0] ?? '', 0, 1) . mb_substr($kata[1] ?? '', 0, 1));
}

/** Kelas warna badge berdasarkan teks status. */
function badge(?string $status): string
{
    $peta = [
        'Diajukan' => 'diajukan', 'Menunggu' => 'diajukan', 'Dijadwalkan' => 'diajukan', 'Draf' => 'selesai',
        'Disetujui' => 'disetujui', 'Valid' => 'disetujui', 'Lulus' => 'disetujui', 'Direspons' => 'disetujui', 'Terbit' => 'disetujui', 'Aktif' => 'disetujui',
        'Revisi' => 'bimbingan', 'Lulus dengan Revisi' => 'bimbingan', 'Bimbingan' => 'bimbingan',
        'Seminar' => 'seminar', 'Sidang' => 'sidang', 'Selesai' => 'selesai', 'Belum diunggah' => 'selesai', 'Nonaktif' => 'selesai',
        'Ditolak' => 'ditolak', 'Tidak Lulus' => 'ditolak', 'Dibatalkan' => 'ditolak',
    ];
    $kelas = $peta[$status ?? ''] ?? 'selesai';
    return '<span class="badge st-' . $kelas . '">' . e($status ?? '-') . '</span>';
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        flash('error', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

/* ---------- Pesan flash ---------- */
function flash(string $tipe, string $pesan): void
{
    $_SESSION['flash'][] = [$tipe, $pesan];
}
function flash_render(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as [$tipe, $pesan]) {
        $html .= '<div class="alert alert-' . e($tipe) . '" role="' . ($tipe === 'error' ? 'alert' : 'status') . '">' . e($pesan) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- Pengguna & hak akses ----------
   Peran: ADMIN, OPERATOR_PRODI, DEKANAT, MAHASISWA (dari SSO SIM Mahasiswa)
          DOSEN (login lokal, tabel dosen)                                   */
const LABEL_PERAN = [
    'ADMIN' => 'Admin', 'OPERATOR_PRODI' => 'Operator Prodi', 'DEKANAT' => 'Dekanat',
    'MAHASISWA' => 'Mahasiswa', 'DOSEN' => 'Dosen',
];

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}
function peran(): string
{
    return $_SESSION['user']['role'] ?? '';
}
function is_role(string ...$r): bool
{
    return in_array(peran(), $r, true);
}
/** Admin, operator prodi, dekanat: melihat data banyak mahasiswa. */
function is_staf(): bool
{
    return is_role('ADMIN', 'OPERATOR_PRODI', 'DEKANAT');
}
/** Admin & operator prodi: boleh mengubah data proses skripsi. */
function is_pengelola(): bool
{
    return is_role('ADMIN', 'OPERATOR_PRODI');
}
function require_login(string ...$roles): array
{
    $u = user();
    if ($u && !in_array($u['sumber'] ?? '', ['simmhs', 'simdosen'], true)) {
        // sesi dari versi lama (sebelum semua login lewat SSO) tidak berlaku lagi
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'Sesi Anda sudah tidak berlaku. Silakan masuk lagi.');
        redirect('login.php');
    }
    if (!$u) {
        flash('info', 'Silakan masuk terlebih dahulu.');
        redirect('login.php');
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        http_response_code(403);
        flash('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        redirect('dashboard.php');
    }
    return $u;
}

/**
 * Syarat SQL agar staf hanya melihat mahasiswa dalam kewenangannya.
 * $m = alias tabel pengguna_sso (data mahasiswa).
 */
function scope_staf(string $m = 'm'): array
{
    $u = user();
    return match ($u['role'] ?? '') {
        'OPERATOR_PRODI' => [" AND $m.id_program_studi = ?", [(int) $u['id_program_studi']]],
        'DEKANAT'        => [" AND $m.id_fakultas = ?", [(int) $u['id_fakultas']]],
        'ADMIN'          => ['', []],
        default          => [' AND 1=0', []],
    };
}

/** Ambil satu pengajuan + data mahasiswa (dari cache SSO). */
function pengajuan_ambil(int $id): ?array
{
    return db_row(
        "SELECT pj.*, m.npm, m.nama_lengkap AS nama_mahasiswa, m.email AS email_mahasiswa,
                m.id_program_studi, m.nama_program_studi, m.id_fakultas
           FROM pengajuan_judul pj
           LEFT JOIN pengguna_sso m ON m.id_mahasiswa = pj.id_mahasiswa
          WHERE pj.id_pengajuan = ?",
        [$id]
    );
}

/** Bolehkah pengguna saat ini melihat pengajuan ini? */
function boleh_lihat_pengajuan(array $pj): bool
{
    $u = user();
    switch ($u['role']) {
        case 'ADMIN':
            return true;
        case 'OPERATOR_PRODI':
            return (int) $pj['id_program_studi'] === (int) $u['id_program_studi'];
        case 'DEKANAT':
            return (int) $pj['id_fakultas'] === (int) $u['id_fakultas'];
        case 'MAHASISWA':
            return (int) $pj['id_mahasiswa'] === (int) $u['id_mahasiswa'];
        case 'DOSEN':
            return (bool) db_val(
                'SELECT 1 FROM pembimbing WHERE id_pengajuan = ? AND id_dosen = ?
                 UNION SELECT 1 FROM seminar_proposal WHERE id_pengajuan = ? AND ? IN (id_penguji_1, IFNULL(id_penguji_2,0))
                 UNION SELECT 1 FROM sidang_skripsi WHERE id_pengajuan = ? AND ? IN (id_penguji_1, id_penguji_2, IFNULL(id_penguji_3,0))',
                [$pj['id_pengajuan'], $u['id'], $pj['id_pengajuan'], $u['id'], $pj['id_pengajuan'], $u['id']]
            );
    }
    return false;
}

/** Pengajuan yang sedang berjalan milik mahasiswa (bukan yang ditolak). */
function pengajuan_aktif_mahasiswa(int $idMahasiswa): ?array
{
    $id = db_val("SELECT id_pengajuan FROM pengajuan_judul WHERE id_mahasiswa = ? AND status <> 'Ditolak' LIMIT 1", [$idMahasiswa]);
    return $id ? pengajuan_ambil((int) $id) : null;
}

/* ---------- Tahapan skripsi ---------- */
const ALUR = ['Diajukan', 'Disetujui', 'Bimbingan', 'Seminar', 'Sidang', 'Selesai'];

/**
 * Posisi pengajuan pada alur: ['idx' => 0..5, 'label' => ..., 'selesai' => bool, 'ket' => ...]
 * idx = tahap yang sedang berjalan; semua tahap sebelum idx sudah dilewati.
 */
function tahap(array $pj): array
{
    if ($pj['status'] === 'Ditolak') return ['idx' => 0, 'label' => 'Ditolak', 'selesai' => false, 'ket' => 'Judul ditolak'];
    if ($pj['status'] !== 'Disetujui') {
        return ['idx' => 0, 'label' => 'Diajukan', 'selesai' => false, 'ket' => $pj['status'] === 'Revisi' ? 'Perlu revisi judul' : 'Menunggu persetujuan prodi'];
    }
    $id = (int) $pj['id_pengajuan'];
    $sidang = db_row("SELECT status, hasil FROM sidang_skripsi WHERE id_pengajuan = ? AND status <> 'Dibatalkan' ORDER BY id_sidang DESC LIMIT 1", [$id]);
    if ($sidang) {
        if ($sidang['status'] === 'Selesai' && $sidang['hasil'] !== 'Tidak Lulus') return ['idx' => 5, 'label' => 'Selesai', 'selesai' => true, 'ket' => 'Lulus sidang'];
        return ['idx' => 4, 'label' => 'Sidang', 'selesai' => false, 'ket' => $sidang['hasil'] === 'Tidak Lulus' ? 'Tidak lulus, menunggu sidang ulang' : 'Sidang dijadwalkan'];
    }
    $sem = db_row("SELECT status, hasil FROM seminar_proposal WHERE id_pengajuan = ? AND status <> 'Dibatalkan' ORDER BY id_seminar DESC LIMIT 1", [$id]);
    if ($sem) {
        if ($sem['status'] === 'Selesai' && $sem['hasil'] !== 'Tidak Lulus') return ['idx' => 4, 'label' => 'Sidang', 'selesai' => false, 'ket' => 'Siap dijadwalkan sidang'];
        return ['idx' => 3, 'label' => 'Seminar', 'selesai' => false, 'ket' => $sem['hasil'] === 'Tidak Lulus' ? 'Tidak lulus, menunggu seminar ulang' : 'Seminar dijadwalkan'];
    }
    if (db_val('SELECT COUNT(*) FROM pembimbing WHERE id_pengajuan = ?', [$id])) {
        return ['idx' => 2, 'label' => 'Bimbingan', 'selesai' => false, 'ket' => 'Sedang bimbingan'];
    }
    return ['idx' => 1, 'label' => 'Disetujui', 'selesai' => false, 'ket' => 'Menunggu penetapan pembimbing'];
}

/* ---------- Kelengkapan dokumen ---------- */
/** Jumlah dokumen wajib yang belum Valid pada tahap tertentu. */
function dokumen_kurang(int $idMahasiswa, string $tahap): int
{
    return (int) db_val(
        "SELECT COUNT(*) FROM jenis_dokumen jd
          LEFT JOIN dokumen d ON d.id_jenis = jd.id_jenis AND d.id_mahasiswa = ?
          WHERE jd.tahap = ? AND jd.wajib = 1 AND jd.status_aktif = 'Aktif'
            AND (d.status_verifikasi IS NULL OR d.status_verifikasi <> 'Valid')",
        [$idMahasiswa, $tahap]
    );
}

/* ---------- Dosen ---------- */
function daftar_dosen_aktif(): array
{
    return db_rows("SELECT id_dosen, nip, nomor_registrasi, gelar_depan, nama_dosen, gelar_belakang, kuota_bimbingan
                      FROM dosen WHERE status_aktif = 'Aktif' ORDER BY nama_dosen");
}
function opsi_dosen(array $dosen, $terpilih = null, string $kosong = '— Pilih dosen —'): string
{
    $html = '<option value="">' . e($kosong) . '</option>';
    foreach ($dosen as $d) {
        $sel = (string) $terpilih === (string) $d['id_dosen'] ? ' selected' : '';
        $html .= '<option value="' . (int) $d['id_dosen'] . '"' . $sel . '>' . e(nama_dosen($d)) . '</option>';
    }
    return $html;
}

/* ---------- Unggahan berkas ---------- */
/**
 * Simpan berkas dari $_FILES[$field] ke folder uploads/.
 * Mengembalikan ['path','nama','kb'] atau null bila tidak ada berkas.
 * Melempar RuntimeException dengan pesan yang bisa ditampilkan bila tidak valid.
 */
function simpan_unggahan(string $field, array $ekstensi, int $maksKb, bool $wajib = false): ?array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        if ($wajib) throw new RuntimeException('Pilih berkas yang akan diunggah.');
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Berkas gagal diunggah (kode ' . $f['error'] . '). Periksa ukuran berkas.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ekstensi, true)) throw new RuntimeException('Format berkas harus ' . strtoupper(implode('/', $ekstensi)) . '.');
    $kb = (int) ceil($f['size'] / 1024);
    if ($kb > $maksKb) throw new RuntimeException('Ukuran berkas maksimal ' . number_format($maksKb / 1024, 1, ',', '.') . ' MB.');

    $mimeBoleh = [
        'pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    ];
    if (function_exists('finfo_open') && isset($mimeBoleh[$ext])) {
        $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']);
        if (!in_array($mime, $mimeBoleh[$ext], true)) throw new RuntimeException('Isi berkas tidak sesuai dengan format ' . strtoupper($ext) . '.');
    }

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
    $nama = date('Ymd') . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $nama)) throw new RuntimeException('Berkas tidak bisa disimpan di server.');
    return ['path' => $nama, 'nama' => mb_substr(basename($f['name']), 0, 200), 'kb' => $kb];
}
function hapus_unggahan(?string $path): void
{
    if ($path && preg_match('/^[\w.-]+$/', $path) && is_file(UPLOAD_DIR . '/' . $path)) unlink(UPLOAD_DIR . '/' . $path);
}

/* ---------- Halaman (paginasi) ---------- */
function paginasi(int $total, int $per = 10): array
{
    $jml = max(1, (int) ceil($total / $per));
    $hal = min($jml, max(1, (int) ($_GET['hal'] ?? 1)));
    return ['hal' => $hal, 'jml' => $jml, 'per' => $per, 'offset' => ($hal - 1) * $per, 'total' => $total];
}
function paginasi_html(array $p): string
{
    if ($p['jml'] <= 1) return '';
    $q = $_GET;
    $link = function (int $h) use ($q) { $q['hal'] = $h; return '?' . e(http_build_query($q)); };
    $html = '<nav class="pager" aria-label="Halaman">';
    $html .= $p['hal'] > 1 ? '<a href="' . $link($p['hal'] - 1) . '" aria-label="Sebelumnya">‹</a>' : '<span class="disabled">‹</span>';
    for ($i = max(1, $p['hal'] - 2); $i <= min($p['jml'], $p['hal'] + 2); $i++) {
        $html .= $i === $p['hal'] ? '<span aria-current="page">' . $i . '</span>' : '<a href="' . $link($i) . '">' . $i . '</a>';
    }
    $html .= $p['hal'] < $p['jml'] ? '<a href="' . $link($p['hal'] + 1) . '" aria-label="Berikutnya">›</a>' : '<span class="disabled">›</span>';
    return $html . '</nav>';
}
function info_paginasi(array $p): string
{
    if (!$p['total']) return 'Tidak ada data';
    return 'Menampilkan ' . ($p['offset'] + 1) . '–' . min($p['total'], $p['offset'] + $p['per']) . ' dari ' . $p['total'] . ' data';
}

/* ---------- Pengumuman ---------- */
/**
 * Pengumuman terbit yang boleh dilihat. $u = null untuk pengunjung (landing page).
 * Staf melihat semua sasaran; mahasiswa/dosen sesuai sasaran & prodinya.
 */
function pengumuman_tampil(?array $u, int $limit = 20, int $offset = 0, bool $hitung = false)
{
    $sql = " FROM pengumuman WHERE status = 'Terbit' AND tanggal_terbit <= NOW()
              AND (tanggal_berakhir IS NULL OR tanggal_berakhir >= CURDATE())";
    $p = [];
    if (!$u) {
        $sql .= " AND sasaran = 'Semua' AND id_program_studi IS NULL";
    } elseif (in_array($u['role'], ['MAHASISWA', 'DOSEN'], true)) {
        $sql .= ' AND sasaran IN (\'Semua\', ?) AND (id_program_studi IS NULL OR id_program_studi = ?)';
        $p = [$u['role'] === 'DOSEN' ? 'Dosen' : 'Mahasiswa', (int) $u['id_program_studi']];
    } elseif ($u['role'] === 'OPERATOR_PRODI') {
        $sql .= ' AND (id_program_studi IS NULL OR id_program_studi = ?)';
        $p = [(int) $u['id_program_studi']];
    }
    if ($hitung) return (int) db_val('SELECT COUNT(*)' . $sql, $p);
    return db_rows('SELECT *' . $sql . ' ORDER BY tanggal_terbit DESC LIMIT ? OFFSET ?', array_merge($p, [$limit, $offset]));
}

require __DIR__ . '/sso.php';
require __DIR__ . '/layout.php';
