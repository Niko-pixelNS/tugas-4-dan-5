<?php
/**
 * config/sso.php — pengaturan SSO & web service SIM Mahasiswa
 *
 * Tidak memakai tabel tambahan: kode login dan token berupa JWT (HS256)
 * yang ditandatangani dengan SSO_KUNCI. Ganti SSO_KUNCI dan client_secret
 * dengan teks acak milik Anda, lalu salin client_secret yang sama ke
 * simskripsi/config/app.php.
 *
 * Alamat aplikasi klien dihitung otomatis dari config/lingkungan.php,
 * jadi sama-sama berlaku di XAMPP lokal maupun di InfinityFree.
 */
require_once __DIR__ . '/lingkungan.php';

// Kunci penandatangan token. Minimal 32 karakter acak. JANGAN dibagikan.
const SSO_KUNCI = 'GANTI-dengan-teks-acak-panjang-9f3c1a7e5b2d4c6e8a0f1b3d5e7c9a2b';

const SSO_MASA_KODE  = 60;    // detik: kode login sekali pakai
const SSO_MASA_TOKEN = 1800;  // detik: token akses (30 menit)

// Aplikasi yang boleh memakai SSO & API (folder SIMSKRIPSI di htdocs: "simskripsi")
define('SSO_CLIENT', [
    'simskripsi' => [
        'nama'            => 'SIMSKRIPSI',
        'client_secret'   => 'GANTI-rahasia-simskripsi-4b8e2f6a1c9d3e7b',
        'redirect_uri'    => BASE_URL . '/simskripsi/sso_callback.php',
        'logout_redirect' => BASE_URL . '/simskripsi/login.php',
        // Peran SIM Mahasiswa yang boleh masuk ke aplikasi ini
        'role'            => ['ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'MAHASISWA'],
    ],
    // SIM Dosen: hanya Admin (satu akun Admin SIM Mahasiswa) yang masuk lewat SSO
    'simdosen' => [
        'nama'            => 'SIM Dosen',
        'client_secret'   => 'GANTI-rahasia-simdosen-di-simmahasiswa-3f8a1c6e', // = SSO_SIMMHS_SECRET di simdosen/config/app.php
        'redirect_uri'    => BASE_URL . '/simdosen/sso_callback.php',
        'logout_redirect' => BASE_URL . '/simdosen/login.php',
        'role'            => ['ADMIN'],
    ],
]);
