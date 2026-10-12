<?php
/**
 * inc/init.php — dimuat di awal setiap halaman web SIM Dosen.
 * Isi: sesi, koneksi DB, CSRF, pesan flash, helper tampilan, autentikasi & RBAC, tata letak.
 */
declare(strict_types=1);
ob_start();
require_once __DIR__ . '/../config/app.php';

session_name('SIMDOSEN');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
ini_set('session.use_strict_mode', '1');
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

try {
    require_once __DIR__ . '/db.php';
} catch (mysqli_sql_exception $ex) {
    error_log('Koneksi simdosen gagal: ' . $ex->getMessage());
    http_response_code(500);
    exit('<p style="font-family:sans-serif;padding:24px">Tidak bisa terhubung ke database SIM Dosen. '
        . (LINGKUNGAN === 'lokal' ? 'Pastikan MySQL di XAMPP berjalan dan <b>simdosen.sql</b> sudah diimpor.'
                                  : 'Periksa <b>DB_ONLINE</b> di config/lingkungan.php.') . '</p>');
}

/* ---------- Bantuan umum ---------- */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
function post(string $k, string $d = ''): string { return trim((string) ($_POST[$k] ?? $d)); }
function get(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }
function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

const BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const BULAN_PANJANG = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
function tgl(?string $d, bool $panjang = false): string
{
    if (!$d) return '-';
    $t = strtotime($d);
    return date('j', $t) . ' ' . ($panjang ? BULAN_PANJANG : BULAN)[(int) date('n', $t)] . ' ' . date('Y', $t);
}
function tgl_jam(?string $d): string { return $d ? tgl($d) . ', ' . date('H.i', strtotime($d)) : '-'; }
function inisial(string $nama): string
{
    $k = preg_split('/\s+/', trim(preg_replace('/^((Prof|Dr|Ir)\.?\s+)+/i', '', $nama)));
    return strtoupper(mb_substr($k[0] ?? '', 0, 1) . mb_substr($k[1] ?? '', 0, 1));
}
function badge(?string $s): string
{
    $peta = ['Aktif' => 'disetujui', 'Sudah' => 'disetujui', 'Belum' => 'bimbingan', 'Cuti' => 'bimbingan',
             'Tugas Belajar' => 'diajukan', 'Ijin Belajar' => 'diajukan', 'Tidak Aktif' => 'ditolak',
             'Pensiun' => 'selesai', 'Almarhum' => 'selesai'];
    return '<span class="badge st-' . ($peta[$s ?? ''] ?? 'selesai') . '">' . e($s ?? '-') . '</span>';
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        flash('error', 'Sesi formulir kedaluwarsa. Silakan ulangi.');
        redirect($_SERVER['HTTP_REFERER'] ?? 'dashboard.php');
    }
}

/* ---------- Pesan flash ---------- */
function flash(string $tipe, string $pesan): void { $_SESSION['flash'][] = [$tipe, $pesan]; }
function flash_render(): string
{
    $h = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $p]) $h .= '<div class="alert alert-' . e($t) . '" role="' . ($t === 'error' ? 'alert' : 'status') . '">' . e($p) . '</div>';
    unset($_SESSION['flash']);
    return $h;
}

/* ---------- Paginasi ---------- */
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
    $h = '<nav class="pager" aria-label="Halaman">' . ($p['hal'] > 1 ? '<a href="' . $link($p['hal'] - 1) . '" aria-label="Sebelumnya">‹</a>' : '<span class="disabled">‹</span>');
    for ($i = max(1, $p['hal'] - 2); $i <= min($p['jml'], $p['hal'] + 2); $i++) $h .= $i === $p['hal'] ? '<span aria-current="page">' . $i . '</span>' : '<a href="' . $link($i) . '">' . $i . '</a>';
    return $h . ($p['hal'] < $p['jml'] ? '<a href="' . $link($p['hal'] + 1) . '" aria-label="Berikutnya">›</a>' : '<span class="disabled">›</span>') . '</nav>';
}
function info_paginasi(array $p): string
{
    return $p['total'] ? 'Menampilkan ' . ($p['offset'] + 1) . '–' . min($p['total'], $p['offset'] + $p['per']) . ' dari ' . $p['total'] . ' data' : 'Tidak ada data';
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/layout.php';
