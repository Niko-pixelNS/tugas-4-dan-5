<?php
/**
 * api/dosen.php — data dosen dalam JSON (web service SIM Dosen)
 *
 *   GET ?id=1                       satu dosen (id_dosen)
 *   GET ?nomor_registrasi=0201018501 satu dosen
 *   GET (daftar) opsional: &kode_prodi=TI &id_program_studi=1 &status=Aktif &cari=nama &halaman=1 &per_halaman=20
 *   Header: Authorization: Bearer <token dari api/token.php>
 *
 * Batas data: token dosen (SSO) -> dirinya; token aplikasi -> semua dosen.
 */
declare(strict_types=1);
ob_start();
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/jwt.php';
require __DIR__ . '/../inc/data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_gagal(405, 'Gunakan metode GET.');
$auth = api_auth();

$where = ' WHERE 1=1';
$param = [];
if ($auth['typ'] === 'access') {
    $u = $auth['pengguna'];
    [$w, $p] = $u['role'] === 'DOSEN' ? [' AND id_pengguna = ?', [(int) $u['id_pengguna']]] : [' AND 1=0', []];
    $where .= $w;
    $param = $p;
}

/* ---------- Satu dosen ---------- */
if (isset($_GET['id']) || isset($_GET['nomor_registrasi'])) {
    [$kol, $nilai] = isset($_GET['id']) ? ['id_dosen', (int) $_GET['id']] : ['nomor_registrasi', trim((string) $_GET['nomor_registrasi'])];
    $v = db_row("SELECT * FROM v_dosen $where AND $kol = ?", array_merge($param, [$nilai]));
    if (!$v) json_gagal(404, 'Dosen tidak ditemukan atau di luar kewenangan Anda.');
    json_keluar(200, ['status' => 'ok', 'data' => dosen_json($v)]);
}

/* ---------- Daftar ---------- */
if (!empty($_GET['kode_prodi']))       { $where .= ' AND kode_program_studi = ?'; $param[] = (string) $_GET['kode_prodi']; }
if (isset($_GET['id_program_studi']))  { $where .= ' AND id_program_studi = ?';   $param[] = (int) $_GET['id_program_studi']; }
if (!empty($_GET['status']))           { $where .= ' AND status_aktivitas = ?';   $param[] = (string) $_GET['status']; }
if (!empty($_GET['cari'])) {
    $where .= ' AND (nama_dosen LIKE ? OR nomor_registrasi LIKE ?)';
    $kata = '%' . trim((string) $_GET['cari']) . '%';
    array_push($param, $kata, $kata);
}
$per = max(1, min(100, (int) ($_GET['per_halaman'] ?? 20)));
$hal = max(1, (int) ($_GET['halaman'] ?? 1));
$total = (int) db_val("SELECT COUNT(*) FROM v_dosen $where", $param);
$rows = db_rows("SELECT * FROM v_dosen $where ORDER BY nama_dosen LIMIT ? OFFSET ?", array_merge($param, [$per, ($hal - 1) * $per]));

json_keluar(200, ['status' => 'ok', 'data' => array_map('dosen_json', $rows), 'meta' => ['total' => $total, 'halaman' => $hal, 'per_halaman' => $per]]);
