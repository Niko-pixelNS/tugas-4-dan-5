<?php
/**
 * config/app.php — pengaturan SIMSKRIPSI
 * Alamat dihitung otomatis dari config/lingkungan.php (lokal atau online).
 */
require_once __DIR__ . '/lingkungan.php';

const APP_NAME = 'SIMSKRIPSI';

/* Nama folder di htdocs (lokal) dan di htdocs InfinityFree — samakan keduanya */
const FOLDER_SIMSKRIPSI = 'simskripsi';
const FOLDER_SIMMHS     = 'simmahasiswa';
const FOLDER_SIMDOSEN   = 'simdosen';

define('APP_URL', BASE_URL . '/' . FOLDER_SIMSKRIPSI);
define('SIMMHS_URL', BASE_URL . '/' . FOLDER_SIMMHS);     // ubah bila SIM Mahasiswa di domain lain
define('SIMDOSEN_URL', BASE_URL . '/' . FOLDER_SIMDOSEN); // ubah bila SIM Dosen di domain lain

/* ---------- SSO & web service ---------- */
const SSO_CLIENT_ID       = 'simskripsi';
const SSO_CLIENT_SECRET   = 'GANTI-rahasia-simskripsi-4b8e2f6a1c9d3e7b';     // = client_secret di simmahasiswa71/config/sso.php
const SSO_SECRET_SIMDOSEN = 'GANTI-rahasia-simskripsi-di-simdosen-7c2e9a41'; // = client_secret di simdosen/config/sso.php
define('SSO_REDIRECT_URI', APP_URL . '/sso_callback.php');

/* ---------- Unggahan ---------- */
const UPLOAD_DIR = __DIR__ . '/../uploads';
