<?php
/**
 * api/token.php — menukar kode SSO menjadi token akses (JSON)
 *
 * POST (dipanggil server SIMSKRIPSI, bukan browser):
 *   grant_type=authorization_code & code & redirect_uri & client_id & client_secret
 *     -> token atas nama pengguna yang login + data pengguna
 *   grant_type=client_credentials & client_id & client_secret
 *     -> token aplikasi untuk panggilan antarserver (mis. daftar prodi)
 */
declare(strict_types=1);
require __DIR__ . '/../sso/sso_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_gagal(405, 'Gunakan metode POST.');

$client = sso_client($_POST['client_id'] ?? null);
if (!$client || !sso_cek_rahasia($client, (string) ($_POST['client_secret'] ?? ''))) {
    json_gagal(401, 'client_id atau client_secret salah.');
}

$grant = (string) ($_POST['grant_type'] ?? '');

if ($grant === 'authorization_code') {
    $kode = jwt_baca((string) ($_POST['code'] ?? ''));
    if (!$kode || ($kode['typ'] ?? '') !== 'code' || ($kode['aud'] ?? '') !== $client['client_id']) {
        json_gagal(400, 'Kode login tidak sah atau sudah kedaluwarsa.');
    }
    if (!hash_equals($client['redirect_uri'], (string) ($_POST['redirect_uri'] ?? '')) || $kode['uri'] !== $client['redirect_uri']) {
        json_gagal(400, 'redirect_uri tidak sesuai.');
    }

    $user = sso_data_pengguna((int) $kode['sub']);
    if (!$user || $user['status_aktif'] !== 'Aktif' || !in_array($user['role'], $client['role'], true)) {
        json_gagal(403, 'Akun tidak aktif atau tidak memiliki akses.');
    }

    $token = jwt_buat([
        'iss' => 'simmahasiswa', 'typ' => 'access', 'aud' => $client['client_id'],
        'sub' => $user['id_pengguna'], 'role' => $user['role'],
        'iat' => time(), 'exp' => time() + SSO_MASA_TOKEN,
    ]);
    json_keluar(200, [
        'status'       => 'ok',
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'expires_in'   => SSO_MASA_TOKEN,
        'user'         => $user,
    ]);
}

if ($grant === 'client_credentials') {
    $token = jwt_buat([
        'iss' => 'simmahasiswa', 'typ' => 'client', 'aud' => $client['client_id'],
        'sub' => 'client:' . $client['client_id'],
        'iat' => time(), 'exp' => time() + SSO_MASA_TOKEN,
    ]);
    json_keluar(200, [
        'status'       => 'ok',
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'expires_in'   => SSO_MASA_TOKEN,
    ]);
}

json_gagal(400, 'grant_type harus authorization_code atau client_credentials.');
