<?php
/**
 * api/mahasiswa.php — data mahasiswa dalam JSON
 *
 * GET  ?npm=20260001          satu mahasiswa berdasarkan NPM
 * GET  ?id=1                  satu mahasiswa berdasarkan id_mahasiswa
 * GET  ?id_program_studi=1    daftar (opsional: &cari=nama/npm &status=Aktif &halaman=1 &per_halaman=20)
 * Header: Authorization: Bearer <token dari api/token.php>
 *
 * Batas data per peran (token pengguna):
 *   MAHASISWA -> hanya dirinya; OPERATOR_PRODI -> prodinya;
 *   DEKANAT -> fakultasnya; ADMIN & token aplikasi -> semua.
 */
declare(strict_types=1);
require __DIR__ . '/../sso/sso_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_gagal(405, 'Gunakan metode GET.');
$auth = api_auth();

/* Syarat tambahan sesuai peran pemilik token */
$batas = '';
$batasParam = [];
if ($auth['typ'] === 'access') {
    $u = $auth['pengguna'];
    switch ($u['role']) {
        case 'ADMIN':
            break;
        case 'OPERATOR_PRODI':
            $batas = ' AND ps.id_program_studi = ?';
            $batasParam = [(int) $u['id_program_studi']];
            break;
        case 'DEKANAT':
            $batas = ' AND f.id_fakultas = ?';
            $batasParam = [(int) $u['id_fakultas']];
            break;
        case 'MAHASISWA':
            $batas = ' AND m.id_pengguna = ?';
            $batasParam = [(int) $u['id_pengguna']];
            break;
        default:
            json_gagal(403, 'Peran ini tidak boleh membaca data mahasiswa.');
    }
}

/* ---------- Satu mahasiswa ---------- */
if (isset($_GET['npm']) || isset($_GET['id'])) {
    [$kolom, $nilai] = isset($_GET['npm'])
        ? ['m.npm', trim((string) $_GET['npm'])]
        : ['m.id_mahasiswa', (int) $_GET['id']];
    $m = sso_row(SSO_SQL_MAHASISWA . " WHERE $kolom = ?" . $batas, array_merge([$nilai], $batasParam));
    if (!$m) json_gagal(404, 'Mahasiswa tidak ditemukan atau di luar kewenangan Anda.');
    json_keluar(200, ['status' => 'ok', 'data' => sso_format_mahasiswa($m)]);
}

/* ---------- Daftar mahasiswa ---------- */
$where = ' WHERE 1=1' . $batas;
$param = $batasParam;
if (isset($_GET['id_program_studi'])) {
    $where .= ' AND ps.id_program_studi = ?';
    $param[] = (int) $_GET['id_program_studi'];
}
if (!empty($_GET['status'])) {
    $where .= ' AND m.status_mahasiswa = ?';
    $param[] = (string) $_GET['status'];
}
if (!empty($_GET['cari'])) {
    $where .= ' AND (m.nama_mahasiswa LIKE ? OR m.npm LIKE ?)';
    $kata = '%' . trim((string) $_GET['cari']) . '%';
    array_push($param, $kata, $kata);
}

$per = max(1, min(100, (int) ($_GET['per_halaman'] ?? 20)));
$hal = max(1, (int) ($_GET['halaman'] ?? 1));
$total = (int) sso_row('SELECT COUNT(*) AS n FROM (' . SSO_SQL_MAHASISWA . $where . ') x', $param)['n'];
$rows = sso_rows(
    SSO_SQL_MAHASISWA . $where . ' ORDER BY m.nama_mahasiswa LIMIT ? OFFSET ?',
    array_merge($param, [$per, ($hal - 1) * $per])
);

json_keluar(200, [
    'status' => 'ok',
    'data'   => array_map('sso_format_mahasiswa', $rows),
    'meta'   => ['total' => $total, 'halaman' => $hal, 'per_halaman' => $per],
]);
