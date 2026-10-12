<?php
/**
 * sso/logout.php — keluar dari SSO (sesi SIM Mahasiswa) lalu kembali ke aplikasi klien.
 *   sso/logout.php?client_id=simskripsi
 */
declare(strict_types=1);
require __DIR__ . '/sso_lib.php';

session_name('SIMMHS');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

$client = sso_client($_GET['client_id'] ?? null);

$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();

header('Location: ' . ($client ? $client['logout_redirect'] . '?keluar=1' : '../index.php'));
exit;
