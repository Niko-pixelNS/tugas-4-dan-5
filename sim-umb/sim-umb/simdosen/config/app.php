<?php
/** config/app.php — pengaturan umum SIM Dosen */
require_once __DIR__ . '/lingkungan.php';

const APP_NAME = 'SIM Dosen';
const FOLDER_APP = 'simdosen';
const FOLDER_SIMMHS = 'simmahasiswa';
define('APP_URL', BASE_URL . '/' . FOLDER_APP);

/* ---------- Admin login lewat SSO SIM Mahasiswa ---------- */
define('SIMMHS_URL', BASE_URL . '/' . FOLDER_SIMMHS);          // ubah bila SIM Mahasiswa di domain lain
const SSO_SIMMHS_CLIENT_ID = 'simdosen';
const SSO_SIMMHS_SECRET    = 'GANTI-rahasia-simdosen-di-simmahasiswa-3f8a1c6e'; // = client_secret 'simdosen' di simmahasiswa71/config/sso.php
define('SSO_SIMMHS_REDIRECT', APP_URL . '/sso_callback.php');
