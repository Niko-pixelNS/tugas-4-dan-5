<?php
/**
 * inc/sso_klien.php — SIM Dosen sebagai KLIEN SSO SIM Mahasiswa (khusus login Admin).
 * Kode login ditukar antarserver dengan token + data pengguna dalam JSON.
 */

function http_json(string $metode, string $url, ?array $form = null): array
{
    $header = ['Accept: application/json'];
    $isi = '';
    if ($form !== null) {
        $isi = http_build_query($form);
        $header[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $badan = @file_get_contents($url, false, stream_context_create(['http' => [
        'method' => $metode, 'header' => implode("\r\n", $header), 'content' => $isi, 'timeout' => 10, 'ignore_errors' => true,
    ]]));
    if ($badan === false) return ['json' => null, 'error' => 'SIM Mahasiswa tidak dapat dihubungi. Periksa SIMMHS_URL di config/app.php.'];
    $json = json_decode($badan, true);
    if (!is_array($json)) {
        $blok = stripos($badan, 'aes.js') !== false || stripos($badan, 'requires javascript') !== false;
        return ['json' => null, 'error' => $blok ? 'Permintaan API diblokir sistem keamanan hosting (InfinityFree gratis). Jalankan di XAMPP lokal.' : 'Jawaban SIM Mahasiswa bukan JSON yang sah.'];
    }
    $kode = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $kode = (int) $m[1];
    return ['json' => $json, 'error' => $kode >= 400 ? ($json['message'] ?? 'Permintaan ditolak.') : null];
}

function sso_admin_url(): string
{
    $_SESSION['sso_state'] = bin2hex(random_bytes(16));
    return SIMMHS_URL . '/sso/authorize.php?' . http_build_query([
        'client_id' => SSO_SIMMHS_CLIENT_ID, 'redirect_uri' => SSO_SIMMHS_REDIRECT, 'state' => $_SESSION['sso_state'],
    ]);
}

function sso_admin_tukar(string $kode): array
{
    return http_json('POST', SIMMHS_URL . '/api/token.php', [
        'grant_type' => 'authorization_code', 'code' => $kode, 'redirect_uri' => SSO_SIMMHS_REDIRECT,
        'client_id' => SSO_SIMMHS_CLIENT_ID, 'client_secret' => SSO_SIMMHS_SECRET,
    ]);
}
