<?php
/** sso/logout.php?client_id=simskripsi — keluar dari SSO SIM Dosen lalu kembali ke aplikasi klien. */
chdir(__DIR__ . '/..');
require __DIR__ . '/../inc/init.php';
require __DIR__ . '/../inc/jwt.php';
$client = sso_client($_GET['client_id'] ?? null);
if (user()) catat_log('LOGOUT', 'Keluar lewat SSO');
$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
session_destroy();
redirect($client ? $client['logout_redirect'] . '?keluar=1' : '../index.php');
