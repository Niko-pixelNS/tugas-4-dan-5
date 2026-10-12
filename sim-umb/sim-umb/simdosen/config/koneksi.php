<?php
/**
 * config/koneksi.php — koneksi ke database simdosen (MySQLi).
 * Lokal (XAMPP) atau InfinityFree dipilih otomatis oleh config/lingkungan.php.
 */
require_once __DIR__ . '/lingkungan.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$koneksi = mysqli_connect(DB_CFG['host'], DB_CFG['user'], DB_CFG['pass'], DB_CFG['nama'], DB_CFG['port']);
mysqli_set_charset($koneksi, 'utf8mb4');
mysqli_query($koneksi, "SET time_zone = '+07:00'");
date_default_timezone_set('Asia/Jakarta');
