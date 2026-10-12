<?php
/**
 * api/token.php — tukar kode SSO jadi token (JSON), atau token aplikasi.
 *   POST grant_type=authorization_code & code & redirect_uri & client_id & client_secret
 *   POST grant_type=client_credentials & client_id & client_secret
 */
declare(strict_types=1);
ob_start();
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/jwt.php';
require __DIR__ . '/../inc/data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_gagal(405, 'Gunakan metode POST.');
$client = sso_client($_POST['client_id'] ?? null);
if (!$client || !hash_equals($client['client_secret'], (string) ($_POST['client_secret'] ?? ''))) json_gagal(401, 'client_id atau client_secret salah.');

$grant = (string) ($_POST['grant_type'] ?? '');
if ($grant === 'authorization_code') {
    $k = jwt_baca((string) ($_POST['code'] ?? ''));
    if (!$k || ($k['typ'] ?? '') !== 'code' || ($k['aud'] ?? '') !== $client['client_id']) json_gagal(400, 'Kode login tidak sah atau sudah kedaluwarsa.');
    if (!hash_equals($client['redirect_uri'], (string) ($_POST['redirect_uri'] ?? '')) || $k['uri'] !== $client['redirect_uri']) json_gagal(400, 'redirect_uri tidak sesuai.');
    $u = pengguna_json((int) $k['sub']);
    if (!$u || $u['status_aktif'] !== 'Aktif' || !in_array($u['role'], $client['role'], true)) json_gagal(403, 'Akun tidak aktif atau tidak memiliki akses.');
    json_keluar(200, [
        'status' => 'ok', 'token_type' => 'Bearer', 'expires_in' => SSO_MASA_TOKEN,
        'access_token' => jwt_buat(['iss' => 'simdosen', 'typ' => 'access', 'aud' => $client['client_id'], 'sub' => $u['id_pengguna'],
                                    'role' => $u['role'], 'iat' => time(), 'exp' => time() + SSO_MASA_TOKEN]),
        'user' => $u,
    ]);
}
if ($grant === 'client_credentials') {
    json_keluar(200, [
        'status' => 'ok', 'token_type' => 'Bearer', 'expires_in' => SSO_MASA_TOKEN,
        'access_token' => jwt_buat(['iss' => 'simdosen', 'typ' => 'client', 'aud' => $client['client_id'], 'sub' => 'client:' . $client['client_id'],
                                    'iat' => time(), 'exp' => time() + SSO_MASA_TOKEN]),
    ]);
}
json_gagal(400, 'grant_type harus authorization_code atau client_credentials.');
