<?php
/**
 * config/lingkungan.php — SIMSKRIPSI
 * Memilih pengaturan otomatis sesuai tempat aplikasi dibuka:
 *   LOKAL  : XAMPP (localhost, 127.0.0.1, atau IP jaringan lokal 192.168.x.x / 10.x.x.x)
 *   ONLINE : domain InfinityFree Anda
 * Cukup isi bagian "ISI DI SINI". File lain tidak perlu diubah saat pindah.
 */

/* ======================= ISI DI SINI ======================= */

// Alamat situs InfinityFree (folder induk ketiga aplikasi), tanpa garis miring di akhir.
// Contoh bila diunggah ke htdocs/sim-umb: 'https://namaanda.infinityfreeapp.com/sim-umb'
const ONLINE_URL = 'https://namaanda.infinityfreeapp.com';

// Database XAMPP lokal
const DB_LOKAL = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'nama' => 'sim_skripsi',
    'port' => 3306,
];

// Database InfinityFree — salin dari panel: Accounts > MySQL Databases
const DB_ONLINE = [
    'host' => 'sql000.infinityfree.com',   // MySQL Hostname
    'user' => 'if0_00000000',              // MySQL Username
    'pass' => 'password-vpanel-anda',      // MySQL Password (lihat di panel)
    'nama' => 'if0_00000000_simskripsi',   // nama database yang Anda buat di panel
    'port' => 3306,
];

/* ============================================================ */

if (!defined('LINGKUNGAN')) {
    $hostAkses = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $tanpaPort = preg_replace('/:\d+$/', '', $hostAkses);
    $ipLokal = filter_var($tanpaPort, FILTER_VALIDATE_IP)
        && !filter_var($tanpaPort, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    $lokal = PHP_SAPI === 'cli' || in_array($tanpaPort, ['localhost', '[::1]'], true) || $ipLokal;

    define('LINGKUNGAN', $lokal ? 'lokal' : 'online');
    // Folder induk ketiga aplikasi di htdocs (mis. /sim-umb), dihitung otomatis:
    // aplikasi -> config -> ../.. = folder yang berisi simdosen, simskripsi, simmahasiswa.
    $induk = '';
    $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $folder = realpath(__DIR__ . '/../..');
    if ($root && $folder) {
        $r = rtrim(str_replace('\\', '/', $root), '/');
        $f = rtrim(str_replace('\\', '/', $folder), '/');
        if (stripos($f . '/', $r . '/') === 0) {
            $induk = implode('/', array_map('rawurlencode', explode('/', substr($f, strlen($r)))));
        }
    }
    define('FOLDER_INDUK', $induk);   // '' bila langsung di htdocs, '/sim-umb' bila di htdocs/sim-umb
    // Di lokal, alamat mengikuti host yang dipakai browser (localhost atau IP laptop).
    define('BASE_URL', $lokal ? 'http://' . $hostAkses . $induk : ONLINE_URL);
    define('DB_CFG', $lokal ? DB_LOKAL : DB_ONLINE);

    if (!$lokal) {
        ini_set('display_errors', '0'); // jangan tampilkan detail error ke pengunjung
        ini_set('log_errors', '1');
    }
}
