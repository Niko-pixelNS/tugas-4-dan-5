<?php
/**
 * inc/jwt.php — token JWT HS256 (tanpa library) + respons JSON untuk SSO & web service.
 */
require_once __DIR__ . '/../config/sso.php';

function b64u_enc(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64u_dec(string $d): string { return (string) base64_decode(strtr($d, '-_', '+/') . str_repeat('=', (4 - strlen($d) % 4) % 4), true); }

function jwt_buat(array $isi): string
{
    $h = b64u_enc(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = b64u_enc(json_encode($isi, JSON_UNESCAPED_UNICODE));
    return "$h.$p." . b64u_enc(hash_hmac('sha256', "$h.$p", SSO_KUNCI, true));
}
/** Isi token bila tanda tangan sah dan belum kedaluwarsa, selain itu null. */
function jwt_baca(string $jwt): ?array
{
    $b = explode('.', $jwt);
    if (count($b) !== 3) return null;
    if (!hash_equals(b64u_enc(hash_hmac('sha256', "$b[0].$b[1]", SSO_KUNCI, true)), $b[2])) return null;
    $isi = json_decode(b64u_dec($b[1]), true);
    return is_array($isi) && ($isi['exp'] ?? 0) >= time() ? $isi : null;
}

function sso_client(?string $id): ?array
{
    return ($id !== null && isset(SSO_CLIENT[$id])) ? SSO_CLIENT[$id] + ['client_id' => $id] : null;
}

function json_keluar(int $kode, array $isi): never
{
    while (ob_get_level()) ob_end_clean();
    http_response_code($kode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($isi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
function json_gagal(int $kode, string $pesan): never
{
    json_keluar($kode, ['status' => 'error', 'message' => $pesan]);
}
