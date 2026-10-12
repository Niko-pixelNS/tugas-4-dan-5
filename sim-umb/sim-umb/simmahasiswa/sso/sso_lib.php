<?php
/**
 * sso/sso_lib.php — fungsi bersama untuk SSO dan web service (JSON)
 * Dipakai oleh sso/authorize.php, sso/logout.php, dan api/*.php.
 */
declare(strict_types=1);
ob_start(); // jaga-jaga bila koneksi.php mengeluarkan spasi setelah tag penutup

require_once __DIR__ . '/../config/sso.php';

/* ---------- JWT HS256 (tanpa library) ---------- */
function b64u_enc(string $d): string
{
    return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
}
function b64u_dec(string $d): string
{
    return (string) base64_decode(strtr($d, '-_', '+/') . str_repeat('=', (4 - strlen($d) % 4) % 4), true);
}
function jwt_buat(array $isi): string
{
    $h = b64u_enc(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = b64u_enc(json_encode($isi, JSON_UNESCAPED_UNICODE));
    $s = b64u_enc(hash_hmac('sha256', "$h.$p", SSO_KUNCI, true));
    return "$h.$p.$s";
}
/** Mengembalikan isi token bila tanda tangan sah dan belum kedaluwarsa, selain itu null. */
function jwt_baca(string $jwt): ?array
{
    $bagian = explode('.', $jwt);
    if (count($bagian) !== 3) return null;
    [$h, $p, $s] = $bagian;
    $harus = b64u_enc(hash_hmac('sha256', "$h.$p", SSO_KUNCI, true));
    if (!hash_equals($harus, $s)) return null;
    $isi = json_decode(b64u_dec($p), true);
    if (!is_array($isi) || ($isi['exp'] ?? 0) < time()) return null;
    return $isi;
}

/* ---------- Klien ---------- */
function sso_client(?string $id): ?array
{
    return ($id !== null && isset(SSO_CLIENT[$id])) ? SSO_CLIENT[$id] + ['client_id' => $id] : null;
}
function sso_cek_rahasia(array $client, string $rahasia): bool
{
    return hash_equals($client['client_secret'], $rahasia);
}

/* ---------- Database (pakai config/koneksi.php milik SIM Mahasiswa) ---------- */
function sso_db(): mysqli
{
    static $db = null;
    if ($db === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require __DIR__ . '/../config/koneksi.php';
        $db = $koneksi;
    }
    return $db;
}
function sso_q(string $sql, array $params = []): mysqli_stmt
{
    $st = sso_db()->prepare($sql);
    if ($params) {
        $tipe = '';
        foreach ($params as $v) $tipe .= is_int($v) ? 'i' : 's';
        $st->bind_param($tipe, ...array_values($params));
    }
    $st->execute();
    return $st;
}
function sso_row(string $sql, array $params = []): ?array
{
    return sso_q($sql, $params)->get_result()->fetch_assoc() ?: null;
}
function sso_rows(string $sql, array $params = []): array
{
    return sso_q($sql, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}

/** Data pengguna untuk dikirim sebagai JSON (tanpa password, alamat, tgl lahir). */
function sso_data_pengguna(int $idPengguna): ?array
{
    $p = sso_row(
        "SELECT p.id_pengguna, p.username, p.nama_lengkap, p.email, p.status_aktif,
                p.id_fakultas, p.id_program_studi, r.kode_role, r.nama_role,
                ps.kode_program_studi, ps.nama_program_studi, f.nama_fakultas
           FROM pengguna p
           JOIN roles r ON r.id_role = p.id_role
           LEFT JOIN program_studi ps ON ps.id_program_studi = p.id_program_studi
           LEFT JOIN fakultas f ON f.id_fakultas = COALESCE(p.id_fakultas, ps.id_fakultas)
          WHERE p.id_pengguna = ?",
        [$idPengguna]
    );
    if (!$p) return null;

    $data = [
        'id_pengguna'      => (int) $p['id_pengguna'],
        'username'         => $p['username'],
        'nama_lengkap'     => $p['nama_lengkap'],
        'email'            => $p['email'],
        'status_aktif'     => $p['status_aktif'],
        'role'             => $p['kode_role'],
        'nama_role'        => $p['nama_role'],
        'id_fakultas'      => $p['id_fakultas'] !== null ? (int) $p['id_fakultas'] : null,
        'id_program_studi' => $p['id_program_studi'] !== null ? (int) $p['id_program_studi'] : null,
        'nama_program_studi' => $p['nama_program_studi'],
        'nama_fakultas'    => $p['nama_fakultas'],
        'mahasiswa'        => null,
    ];

    if ($p['kode_role'] === 'MAHASISWA') {
        $m = sso_row('SELECT id_mahasiswa FROM mahasiswa WHERE id_pengguna = ?', [$idPengguna]);
        if ($m) $data['mahasiswa'] = sso_data_mahasiswa((int) $m['id_mahasiswa']);
        if ($data['mahasiswa']) {
            // prodi & fakultas mahasiswa diambil dari data mahasiswa, bukan akun
            $data['id_program_studi']   = $data['mahasiswa']['program_studi']['id_program_studi'];
            $data['nama_program_studi'] = $data['mahasiswa']['program_studi']['nama'];
            $data['id_fakultas']        = $data['mahasiswa']['fakultas']['id_fakultas'];
            $data['nama_fakultas']      = $data['mahasiswa']['fakultas']['nama'];
        }
    }
    return $data;
}

function sso_format_mahasiswa(array $m): array
{
    return [
        'id_mahasiswa'     => (int) $m['id_mahasiswa'],
        'id_pengguna'      => $m['id_pengguna'] !== null ? (int) $m['id_pengguna'] : null,
        'npm'              => $m['npm'],
        'nama_mahasiswa'   => $m['nama_mahasiswa'],
        'jenis_kelamin'    => $m['jenis_kelamin'],
        'status_mahasiswa' => $m['status_mahasiswa'],
        'angkatan'         => (int) substr((string) $m['tanggal_masuk'], 0, 4),
        'program_studi'    => [
            'id_program_studi' => (int) $m['id_program_studi'],
            'kode'             => $m['kode_program_studi'],
            'nama'             => $m['nama_program_studi'],
            'jenjang'          => $m['jenjang'],
        ],
        'fakultas'         => [
            'id_fakultas' => (int) $m['id_fakultas'],
            'nama'        => $m['nama_fakultas'],
        ],
    ];
}

const SSO_SQL_MAHASISWA = "SELECT m.id_mahasiswa, m.id_pengguna, m.npm, m.nama_mahasiswa, m.jenis_kelamin,
                                  m.status_mahasiswa, m.tanggal_masuk, ps.id_program_studi,
                                  ps.kode_program_studi, ps.nama_program_studi, ps.jenjang,
                                  f.id_fakultas, f.nama_fakultas
                             FROM mahasiswa m
                             JOIN program_studi ps ON ps.id_program_studi = m.id_program_studi
                             JOIN fakultas f ON f.id_fakultas = ps.id_fakultas";

function sso_data_mahasiswa(int $idMahasiswa): ?array
{
    $m = sso_row(SSO_SQL_MAHASISWA . ' WHERE m.id_mahasiswa = ?', [$idMahasiswa]);
    return $m ? sso_format_mahasiswa($m) : null;
}

/* ---------- Respons JSON ---------- */
function json_keluar(int $kode, array $isi): void
{
    while (ob_get_level()) ob_end_clean();
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
function json_gagal(int $kode, string $pesan): void
{
    json_keluar($kode, ['status' => 'error', 'message' => $pesan]);
}

/* ---------- Otorisasi API: header Authorization: Bearer <token> ---------- */
function api_auth(): array
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) {
        $semua = array_change_key_case(apache_request_headers());
        $h = $semua['authorization'] ?? '';
    }
    if (!preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) json_gagal(401, 'Token tidak dikirim.');
    $isi = jwt_baca($m[1]);
    if (!$isi || !in_array($isi['typ'] ?? '', ['access', 'client'], true) || !sso_client($isi['aud'] ?? null)) {
        json_gagal(401, 'Token tidak sah atau sudah kedaluwarsa.');
    }
    if ($isi['typ'] === 'access') {
        // akun yang dinonaktifkan langsung ditolak walau tokennya belum habis
        $u = sso_data_pengguna((int) $isi['sub']);
        if (!$u || $u['status_aktif'] !== 'Aktif') json_gagal(401, 'Akun tidak aktif.');
        $isi['pengguna'] = $u;
    }
    return $isi;
}
