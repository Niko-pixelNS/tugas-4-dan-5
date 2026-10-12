<?php
/**
 * config/koneksi.php — SIM Mahasiswa (pengganti file lama)
 * Koneksi MySQLi; lokal (XAMPP) atau InfinityFree dipilih otomatis oleh config/lingkungan.php.
 * Variabel $koneksi tetap sama, jadi halaman lain tidak perlu diubah.
 * Sengaja tanpa tag penutup PHP agar tidak ada spasi yang merusak header/JSON.
 */
require_once __DIR__ . '/lingkungan.php';

// Variabel lama tetap disediakan bila ada halaman yang memakainya
$host     = DB_CFG['host'];
$username = DB_CFG['user'];
$password = DB_CFG['pass'];
$database = DB_CFG['nama'];
$port     = DB_CFG['port'];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$koneksi = mysqli_connect($host, $username, $password, $database, $port);
mysqli_set_charset($koneksi, 'utf8mb4');

mysqli_query($koneksi, "SET time_zone = '+07:00'");
date_default_timezone_set('Asia/Jakarta');
