<?php
/**
 * config/sso.php — SSO & web service SIM Dosen
 * Kode login dan token berupa JWT (HS256) yang ditandatangani SSO_KUNCI; tanpa tabel tambahan.
 * Ganti SSO_KUNCI dan client_secret dengan teks acak Anda sendiri, lalu salin client_secret
 * yang sama ke simskripsi/config/app.php (SSO_SECRET_SIMDOSEN).
 */
require_once __DIR__ . '/lingkungan.php';

const SSO_KUNCI      = 'GANTI-kunci-simdosen-acak-panjang-5d8b2e7f1a9c4e6b3d0a8f2c7e1b9d4a';
const SSO_MASA_KODE  = 60;     // detik
const SSO_MASA_TOKEN = 1800;   // detik

define('SSO_CLIENT', [
    'simskripsi' => [
        'nama'            => 'SIMSKRIPSI',
        'client_secret'   => 'GANTI-rahasia-simskripsi-di-simdosen-7c2e9a41',
        'redirect_uri'    => BASE_URL . '/simskripsi/sso_callback.php',
        'logout_redirect' => BASE_URL . '/simskripsi/login.php',
        'role'            => ['DOSEN'],   // hanya dosen yang masuk SIMSKRIPSI lewat SIM Dosen
    ],
]);
