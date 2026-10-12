<?php
/**
 * includes/app.php
 * Fondasi bersama untuk halaman setelah login (dashboard, CRUD, laporan):
 * sesi, CSRF, pesan flash, query MySQLi aman, hak akses per peran, cakupan data, audit_log.
 */
declare(strict_types=1);
ob_start(); // cegah error "headers already sent" bila ada spasi/baris kosong di akhir koneksi.php

const APP_NAME    = 'SIM Mahasiswa';
const PER_HALAMAN = 15;
const STATUS_MHS  = ['Aktif', 'Cuti', 'Lulus', 'Mengundurkan Diri', 'Drop Out', 'Tidak Aktif'];
const JENJANG     = ['D3', 'D4', 'S1', 'S2', 'S3', 'Profesi'];
/** Kode peran yang dipakai logika aplikasi — kodenya tidak boleh diubah/dihapus. */
const ROLE_SISTEM = ['ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'REKTORAT', 'MAHASISWA'];

/**
 * Hak akses per peran: 'lihat' = hanya baca, 'kelola' = tambah/ubah/hapus.
 * Data yang terlihat tetap dibatasi cakupan (universitas / fakultas / prodi).
 */
const HAK_AKSES = [
    'ADMIN'          => ['universitas' => 'kelola', 'fakultas' => 'kelola', 'prodi' => 'kelola', 'role' => 'kelola',
                         'pengguna' => 'kelola', 'mahasiswa' => 'kelola', 'laporan' => 'lihat', 'aktivitas' => 'lihat'],
    'REKTORAT'       => ['fakultas' => 'lihat', 'prodi' => 'lihat', 'mahasiswa' => 'lihat', 'laporan' => 'lihat'],
    'DEKANAT'        => ['fakultas' => 'lihat', 'prodi' => 'lihat', 'mahasiswa' => 'lihat', 'laporan' => 'lihat'],
    'OPERATOR_PRODI' => ['prodi' => 'lihat', 'mahasiswa' => 'kelola', 'laporan' => 'lihat'],
];

date_default_timezone_set('Asia/Jakarta');

/* ---------- Sesi (sama dengan login.php) ---------- */
session_name('SIMMHS');
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

/* =========================================================
   BANTUAN TAMPILAN
   ========================================================= */
function e($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function pindah(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}
function angka($n): string
{
    return number_format((int) $n, 0, ',', '.');
}
function inisial(string $nama): string
{
    $kata = preg_split('/\s+/', trim($nama)) ?: [];
    $huruf = mb_substr($kata[0] ?? '?', 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : '');
    return mb_strtoupper($huruf);
}
/** Warna avatar yang tetap untuk nama yang sama. */
function warna_avatar(string $nama): string
{
    $warna = ['#dfe8e9', '#f3e7bf', '#e4ecd5', '#efdcd8', '#e3e0f0', '#dde6f1'];
    return $warna[abs(crc32($nama)) % count($warna)];
}
function tanggal_id(?string $dt, bool $jam = false): string
{
    if (!$dt || ($ts = strtotime($dt)) === false) {
        return '—';
    }
    $bulan = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts) . ($jam ? ', ' . date('H.i', $ts) : '');
}
function waktu_lalu(?string $dt): string
{
    if (!$dt || ($ts = strtotime($dt)) === false) {
        return '—';
    }
    $d = time() - $ts;
    if ($d < 60) return 'baru saja';
    if ($d < 3600) return floor($d / 60) . ' mnt';
    if ($d < 86400) return floor($d / 3600) . ' jam';
    if ($d < 86400 * 7) return floor($d / 86400) . ' hari';
    return tanggal_id($dt);
}
/** Escape karakter wildcard LIKE agar pencarian "50%" tidak jadi pola. */
function like_escape(string $s): string
{
    return addcslashes($s, '\\%_');
}
/** Cegah formula injection saat CSV dibuka di Excel. */
function csv_aman($v): string
{
    $v = (string) ($v ?? '');
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}
function slug_status(string $s): string
{
    return strtolower(str_replace(' ', '-', $s));
}

/** Ikon garis 24×24 (stroke = currentColor). */
function ikon(string $n): string
{
    $p = [
        'home'     => '<path d="M3.5 10.5 12 3.5l8.5 7V20a1 1 0 0 1-1 1h-5v-6h-5v6h-5a1 1 0 0 1-1-1z"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.6 3.4-5.5 6.5-5.5s5.7 1.9 6.5 5.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.8c2 .7 3.2 2.5 3.6 5.2"/>',
        'cap'      => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5M22 9v5"/>',
        'activity' => '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.2-6 8-6s7 2 8 6"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'chevron'  => '<path d="m6 9 6 6 6-6"/>',
        'arrow'    => '<path d="M7 17 17 7M8 7h9v9"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
        'logout'   => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
        'login'    => '<path d="M9 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h3M14 17l5-5-5-5M19 12H8"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'back'     => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'filter'   => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'layers'   => '<path d="M12 3 2 8l10 5 10-5z"/><path d="m2 13 10 5 10-5"/>',
        'pin'      => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'building' => '<path d="M4 21V8l8-5 8 5v13"/><path d="M9 21v-6h6v6M8 10h.01M12 10h.01M16 10h.01"/>',
        'shield'   => '<path d="M12 3 4.5 6v6c0 4.4 3.1 7.9 7.5 9 4.4-1.1 7.5-4.6 7.5-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'idcard'   => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.5-1.5 1.8-2.3 3.2-2.3s2.7.8 3.2 2.3M14.5 10h3.5M14.5 13.5h3.5"/>',
        'report'   => '<path d="M7 3h7l5 5v12a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M14 3v5h5M9 17v-3M12 17v-5M15 17v-2"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'pencil'   => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'print'    => '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
    ];
    if ($n === 'more') {
        return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>';
    }
    if ($n === 'sparkle') {
        return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.5l1.9 5.6 5.6 1.9-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.9z"/><path d="M19 15.5l.9 2.6 2.6.9-2.6.9-.9 2.6-.9-2.6-2.6-.9 2.6-.9z"/></svg>';
    }
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}

function avatar(string $nama, string $kelas = ''): string
{
    return '<span class="av ' . e($kelas) . '" style="background:' . e(warna_avatar($nama)) . '" aria-hidden="true">' . e(inisial($nama)) . '</span>';
}

/**
 * Donut tersegmentasi seperti referensi.
 * $seg: [['label'=>..., 'nilai'=>int, 'warna'=>'#hex'], ...]
 */
function donut(array $seg, string $judulTengah, string $labelAria): string
{
    $total = array_sum(array_column($seg, 'nilai'));
    $cx = 120; $cy = 112; $r = 66; $sw = 22;
    $C = 2 * M_PI * $r;
    $svg = '<svg class="donut-svg" viewBox="0 0 240 224" role="img" aria-label="' . e($labelAria) . '">';
    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r + 22) . '" class="donut-halo"/>';
    $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" class="donut-track" stroke-width="' . ($sw + 14) . '"/>';

    $aktif = array_values(array_filter($seg, static fn($s) => $s['nilai'] > 0));
    if ($total > 0) {
        $gap = count($aktif) > 1 ? $sw + 7 : 0;
        $start = 0.0;
        foreach ($aktif as $s) {
            $frac = $s['nilai'] / $total;
            $len  = max(0.1, $frac * $C - $gap);
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="' . e($s['warna']) . '" stroke-width="' . $sw
                . '" stroke-linecap="round" stroke-dasharray="' . round($len, 2) . ' ' . round($C, 2) . '" stroke-dashoffset="' . round(-($start * $C + $gap / 2), 2)
                . '" transform="rotate(-90 ' . $cx . ' ' . $cy . ')"/>';
            if ($frac >= 0.06) {
                $ang = ($start + $frac / 2) * 2 * M_PI - M_PI / 2;
                $lx = $cx + cos($ang) * ($r + 40);
                $ly = $cy + sin($ang) * ($r + 40) + 4;
                $anchor = cos($ang) > 0.25 ? 'start' : (cos($ang) < -0.25 ? 'end' : 'middle');
                $svg .= '<text x="' . round($lx, 1) . '" y="' . round($ly, 1) . '" text-anchor="' . $anchor . '" class="donut-pct">' . round($frac * 100) . '%</text>';
            }
            $start += $frac;
        }
    }
    $svg .= '</svg>';

    $html = '<div class="donut">' . $svg . '<span class="donut-pill">' . e($judulTengah) . '</span></div><ul class="legend">';
    foreach ($seg as $s) {
        $pct = $total > 0 ? round($s['nilai'] / $total * 100) : 0;
        $html .= '<li><span class="legend-dot" style="background:' . e($s['warna']) . '"></span><span>' . e($s['label'])
            . '</span><b>' . $pct . '%</b><span class="legend-n">' . angka($s['nilai']) . '</span></li>';
    }
    return $html . '</ul>';
}

/** URL ke sebuah file dengan parameter (nilai kosong dibuang). */
function url_ke(string $file, array $p = []): string
{
    $p = array_filter($p, static fn($v) => $v !== '' && $v !== null && $v !== 0);
    return $file . ($p ? '?' . http_build_query($p) : '');
}
function chip_status(string $s): string
{
    return '<span class="st st-' . e(slug_status($s)) . '">' . e($s) . '</span>';
}
function paginasi(string $file, int $hal, int $jumlahHal, array $param): string
{
    if ($jumlahHal <= 1) {
        return '';
    }
    $h = '<nav class="pager" aria-label="Halaman">';
    $h .= $hal > 1 ? '<a class="pager-btn" href="' . e(url_ke($file, $param + ['hal' => $hal - 1])) . '">← Sebelumnya</a>' : '<span class="pager-btn is-off">← Sebelumnya</span>';
    $h .= '<span class="pager-info">Halaman ' . $hal . ' dari ' . $jumlahHal . '</span>';
    $h .= $hal < $jumlahHal ? '<a class="pager-btn" href="' . e(url_ke($file, $param + ['hal' => $hal + 1])) . '">Berikutnya →</a>' : '<span class="pager-btn is-off">Berikutnya →</span>';
    return $h . '</nav>';
}
/** Ambil teks dari POST, dipangkas, dibatasi panjangnya. */
function post_teks(string $kunci, int $maks = 255): string
{
    $v = $_POST[$kunci] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $maks) : '';
}
function post_int(string $kunci): int
{
    return max(0, (int) ($_POST[$kunci] ?? 0));
}
/** Tanggal Y-m-d yang valid, atau '' */
function tanggal_valid(string $s): string
{
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : '';
}

/* ---------- Pesan flash (muncul sekali setelah redirect) ---------- */
function flash(string $jenis, string $pesan): void
{
    $_SESSION['flash'] = [$jenis, $pesan];
}
function flash_html(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    [$jenis, $pesan] = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="flash flash-' . e($jenis) . '" role="' . ($jenis === 'error' ? 'alert' : 'status') . '">' . e($pesan) . '</div>';
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function wajib_csrf(): void
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'], $t)) {
        http_response_code(400);
        exit('<p style="font-family:sans-serif;padding:24px">Sesi formulir kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.</p>');
    }
}

/* =========================================================
   DATABASE (MySQLi, prepared statement)
   ========================================================= */
function db_jalankan(mysqli $db, string $sql, array $params = []): mysqli_stmt
{
    $st = $db->prepare($sql);
    if ($params) {
        $nilai = array_values($params);
        $tipe = '';
        foreach ($nilai as $v) {
            $tipe .= is_int($v) ? 'i' : 's';
        }
        $st->bind_param($tipe, ...$nilai);
    }
    $st->execute();
    return $st;
}
/** Semua baris hasil query (array asosiatif). */
function db_rows(mysqli $db, string $sql, array $params = []): array
{
    $hasil = db_jalankan($db, $sql, $params)->get_result();
    return $hasil ? $hasil->fetch_all(MYSQLI_ASSOC) : [];
}
/** Satu baris pertama, atau null. */
function db_row(mysqli $db, string $sql, array $params = []): ?array
{
    $hasil = db_jalankan($db, $sql, $params)->get_result();
    return ($hasil ? $hasil->fetch_assoc() : null) ?: null;
}
/** Nilai kolom pertama dari baris pertama, atau null. */
function db_val(mysqli $db, string $sql, array $params = [])
{
    $baris = db_jalankan($db, $sql, $params)->get_result()->fetch_row();
    return $baris[0] ?? null;
}
/** Pesan ramah untuk error MySQL yang umum (duplikat, relasi). */
function pesan_db(mysqli_sql_exception $ex, string $duplikat = 'Data dengan kode/nama tersebut sudah ada.'): string
{
    switch ($ex->getCode()) {
        case 1062: return $duplikat;
        case 1451: return 'Data ini masih dipakai oleh data lain, jadi tidak bisa dihapus. Hapus atau pindahkan data terkait dulu.';
        case 1452: return 'Data induk yang dipilih tidak ditemukan.';
    }
    error_log('Kesalahan database: ' . $ex->getMessage());
    return 'Terjadi kesalahan database. Coba lagi.';
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // error MySQL jadi exception
try {
    require __DIR__ . '/../config/koneksi.php'; // membuat $koneksi (MySQLi)
    mysqli_query($koneksi, "SET time_zone = '+07:00'"); // jam WIB untuk last_login & audit_log
} catch (mysqli_sql_exception $ex) {
    error_log('Koneksi database gagal: ' . $ex->getMessage());
    http_response_code(500);
    exit('<p style="font-family:sans-serif;padding:24px">Tidak bisa terhubung ke database. Pastikan MySQL di XAMPP berjalan dan pengaturan di config/koneksi.php sudah benar.</p>');
}

/* =========================================================
   AUDIT LOG
   ========================================================= */
/** Catat INSERT/UPDATE/DELETE/VIEW ke audit_log (password_hash tidak pernah ikut dicatat). */
function audit(mysqli $db, int $idPengguna, string $aksi, string $tabel, ?string $recordId, ?array $lama = null, ?array $baru = null): void
{
    $bersih = static function (?array $d): ?string {
        if ($d === null) {
            return null;
        }
        unset($d['password_hash']);
        return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    };
    try {
        db_jalankan(
            $db,
            'INSERT INTO audit_log (id_pengguna, tabel_nama, record_id, aksi, data_lama, data_baru, ip_address, user_agent, waktu)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$idPengguna, $tabel, $recordId, $aksi, $bersih($lama), $bersih($baru),
             substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500)]
        );
    } catch (mysqli_sql_exception $ex) {
        error_log('Gagal mencatat audit_log: ' . $ex->getMessage());
    }
}

/* =========================================================
   LOGIN, HAK AKSES, CAKUPAN DATA
   ========================================================= */
/** Pengguna yang login (lengkap dengan peran). Belum login / nonaktif → ke login.php. */
function wajib_login(mysqli $db): array
{
    if (empty($_SESSION['id_pengguna'])) {
        pindah('login.php');
    }
    $user = db_row(
        $db,
        'SELECT p.*, r.kode_role, r.nama_role
           FROM pengguna p JOIN roles r ON r.id_role = p.id_role
          WHERE p.id_pengguna = ?',
        [(int) $_SESSION['id_pengguna']]
    );
    if (!$user || $user['status_aktif'] !== 'Aktif') {
        $_SESSION = [];
        session_destroy();
        pindah('login.php');
    }
    $user['cakupan'] = cakupan($db, $user);
    return $user;
}

/**
 * Cakupan data peran:
 *   ['jenis' => 'semua'|'univ'|'fak'|'prodi', 'id' => int, 'label' => string]  atau null (tidak ada akses data).
 */
function cakupan(mysqli $db, array $u): ?array
{
    $idU = (int) ($u['id_universitas'] ?? 0);
    $idF = (int) ($u['id_fakultas'] ?? 0);
    $idP = (int) ($u['id_program_studi'] ?? 0);
    switch ($u['kode_role']) {
        case 'ADMIN': // "Mengelola seluruh data dan konfigurasi aplikasi"
            return ['jenis' => 'semua', 'id' => 0, 'label' => 'Seluruh data'];
        case 'REKTORAT':
            if (!$idU) return null;
            $n = db_val($db, 'SELECT nama_universitas FROM universitas WHERE id_universitas = ?', [$idU]);
            return ['jenis' => 'univ', 'id' => $idU, 'label' => (string) ($n ?: 'Universitas')];
        case 'DEKANAT':
            if (!$idF) return null;
            $n = db_val($db, "SELECT CONCAT(kode_fakultas, ' - ', nama_fakultas) FROM fakultas WHERE id_fakultas = ?", [$idF]);
            return ['jenis' => 'fak', 'id' => $idF, 'label' => (string) ($n ?: 'Fakultas')];
        case 'OPERATOR_PRODI':
            if (!$idP) return null;
            $n = db_val($db, "SELECT CONCAT(kode_program_studi, ' - ', nama_program_studi) FROM program_studi WHERE id_program_studi = ?", [$idP]);
            return ['jenis' => 'prodi', 'id' => $idP, 'label' => (string) ($n ?: 'Program studi')];
    }
    return null;
}

/**
 * Kondisi SQL cakupan. Isi nama kolom universitas/fakultas/prodi pada query yang dipakai
 * (kolom yang tidak tersedia diisi null).
 */
function cakupan_sql(?array $c, ?string $kolUniv, ?string $kolFak, ?string $kolProdi): array
{
    if (!$c) return ['1 = 0', []];
    switch ($c['jenis']) {
        case 'semua': return ['1 = 1', []];
        case 'univ':  return $kolUniv  ? [$kolUniv . ' = ?', [$c['id']]]  : ['1 = 0', []];
        case 'fak':   return $kolFak   ? [$kolFak . ' = ?', [$c['id']]]   : ['1 = 0', []];
        case 'prodi': return $kolProdi ? [$kolProdi . ' = ?', [$c['id']]] : ['1 = 0', []];
    }
    return ['1 = 0', []];
}

function izin(array $u, string $modul, string $perlu = 'lihat'): bool
{
    $hak = HAK_AKSES[$u['kode_role']][$modul] ?? null;
    if ($hak === null || ($modul !== 'aktivitas' && $u['cakupan'] === null)) {
        return false;
    }
    return $perlu === 'lihat' || $hak === 'kelola';
}
function wajib_izin(array $u, string $modul, string $perlu = 'lihat'): void
{
    if (!izin($u, $modul, $perlu)) {
        http_response_code(403);
        require_once __DIR__ . '/layout.php';
        layout_awal($u, 'Akses ditolak', '', ['lebar' => true]);
        echo '<div class="card"><h1 class="page-h1">Akses ditolak</h1><p class="muted">Peran <strong>' . e($u['nama_role'])
            . '</strong> tidak punya izin untuk membuka halaman ini.</p><p><a class="btn-line" href="dashboard.php">Kembali ke beranda</a></p></div>';
        layout_akhir();
        exit;
    }
}

/* =========================================================
   KOMPONEN FORMULIR
   ========================================================= */
/** Atribut tambahan: ['required'=>true, 'maxlength'=>20, ...] → string HTML. */
function attr_html(array $a): string
{
    $h = '';
    foreach ($a as $k => $v) {
        if ($v === false || $v === null) continue;
        $h .= ' ' . e($k) . ($v === true ? '' : '="' . e($v) . '"');
    }
    return $h;
}
function f_bungkus(string $name, string $label, string $kontrol, array $err, array $o): string
{
    $wajib = !empty($o['attr']['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '';
    $h = '<div class="fld' . (!empty($o['lebar']) ? ' fld-wide' : '') . (isset($err[$name]) ? ' has-err' : '') . '">';
    $h .= '<label for="f-' . e($name) . '">' . e($label) . $wajib . '</label>' . $kontrol;
    if (isset($err[$name])) $h .= '<p class="fld-err" id="e-' . e($name) . '">' . e($err[$name]) . '</p>';
    elseif (!empty($o['bantuan'])) $h .= '<p class="fld-help">' . e($o['bantuan']) . '</p>';
    return $h . '</div>';
}
function f_aria(string $name, array $err): array
{
    return isset($err[$name]) ? ['aria-invalid' => 'true', 'aria-describedby' => 'e-' . $name] : [];
}
/** Input teks/email/date/password. $o: type, attr[], bantuan, lebar */
function f_input(string $name, string $label, $nilai, array $err, array $o = []): string
{
    $attr = ($o['attr'] ?? []) + f_aria($name, $err);
    $type = $o['type'] ?? 'text';
    $val = $type === 'password' ? '' : ' value="' . e($nilai) . '"';
    $k = '<input id="f-' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '"' . $val . attr_html($attr) . '>';
    return f_bungkus($name, $label, $k, $err, $o);
}
function f_textarea(string $name, string $label, $nilai, array $err, array $o = []): string
{
    $attr = ($o['attr'] ?? []) + f_aria($name, $err) + ['rows' => 3];
    $k = '<textarea id="f-' . e($name) . '" name="' . e($name) . '"' . attr_html($attr) . '>' . e($nilai) . '</textarea>';
    return f_bungkus($name, $label, $k, $err, $o + ['lebar' => true]);
}
/**
 * Select. $pilihan: [['v' => nilai, 'l' => label, 'induk' => id induk (opsional)], ...]
 * $o['kosong'] = teks pilihan kosong; $o['induk'] = id elemen select induk (pilihan disaring JS).
 */
function f_select(string $name, string $label, array $pilihan, $nilai, array $err, array $o = []): string
{
    $attr = ($o['attr'] ?? []) + f_aria($name, $err);
    if (!empty($o['induk'])) $attr['data-induk-dari'] = 'f-' . $o['induk'];
    $k = '<select id="f-' . e($name) . '" name="' . e($name) . '"' . attr_html($attr) . '>';
    if (isset($o['kosong'])) $k .= '<option value="">' . e($o['kosong']) . '</option>';
    foreach ($pilihan as $p) {
        $sel = (string) $p['v'] === (string) $nilai ? ' selected' : '';
        $ind = isset($p['induk']) ? ' data-induk="' . e($p['induk']) . '"' : '';
        $k .= '<option value="' . e($p['v']) . '"' . $ind . $sel . '>' . e($p['l']) . '</option>';
    }
    return f_bungkus($name, $label, $k . '</select>', $err, $o);
}
/** Pilihan dari array sederhana ['A','B'] → format f_select. */
function pilihan_sederhana(array $list): array
{
    return array_map(static fn($x) => ['v' => $x, 'l' => $x], $list);
}
/** Ringkasan error di atas formulir. */
function ringkasan_error(array $err): string
{
    if (!$err) return '';
    $pesan = $err['_'] ?? 'Periksa kembali isian yang ditandai.';
    return '<div class="flash flash-error" role="alert">' . e($pesan) . '</div>';
}
/** Tombol hapus (form POST + konfirmasi). */
function tombol_hapus(string $file, int $id, string $nama): string
{
    return '<form method="post" action="' . e($file) . '" class="inline" data-konfirmasi="Hapus ' . e($nama) . '? Tindakan ini tidak bisa dibatalkan.">'
        . csrf_field() . '<input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="icon-btn btn-del" type="submit" aria-label="Hapus ' . e($nama) . '" title="Hapus">' . ikon('trash') . '</button></form>';
}
function tombol_edit(string $file, int $id, string $nama): string
{
    return '<a class="icon-btn" href="' . e($file . '?aksi=edit&id=' . $id) . '" aria-label="Ubah ' . e($nama) . '" title="Ubah">' . ikon('pencil') . '</a>';
}
/** Hitung paginasi: [halaman sekarang, jumlah halaman, offset]. */
function hitung_halaman(int $total, int $hal, int $per = PER_HALAMAN): array
{
    $jml = max(1, (int) ceil($total / $per));
    $hal = min(max(1, $hal), $jml);
    return [$hal, $jml, ($hal - 1) * $per];
}
/** Kepala halaman modul: eyebrow, judul, keterangan, tombol kanan. */
function kepala_halaman(string $eyebrow, string $judul, string $ket, string $tombol = ''): string
{
    return '<div class="page-top"><div><span class="eyebrow">' . e($eyebrow) . '</span><h1 class="page-h1">' . e($judul) . '</h1>'
        . ($ket !== '' ? '<p class="page-sub">' . e($ket) . '</p>' : '') . '</div>' . ($tombol !== '' ? '<div class="page-top-act">' . $tombol . '</div>' : '') . '</div>';
}
function tidak_ditemukan(string $file, string $apa): void
{
    flash('error', $apa . ' tidak ditemukan atau di luar cakupanmu.');
    pindah($file);
}
