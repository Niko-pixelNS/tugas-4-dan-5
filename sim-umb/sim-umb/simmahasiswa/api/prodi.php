<?php
/**
 * api/prodi.php — daftar program studi & fakultas (JSON)
 * GET  (opsional: ?id_fakultas=1)
 * Header: Authorization: Bearer <token>
 */
declare(strict_types=1);
require __DIR__ . '/../sso/sso_lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_gagal(405, 'Gunakan metode GET.');
api_auth();

$sql = "SELECT ps.id_program_studi, ps.kode_program_studi, ps.nama_program_studi, ps.jenjang,
               f.id_fakultas, f.kode_fakultas, f.nama_fakultas
          FROM program_studi ps
          JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
         WHERE ps.status_aktif = 'Aktif'";
$param = [];
if (isset($_GET['id_fakultas'])) {
    $sql .= ' AND f.id_fakultas = ?';
    $param[] = (int) $_GET['id_fakultas'];
}
$rows = sso_rows($sql . ' ORDER BY f.nama_fakultas, ps.nama_program_studi', $param);

json_keluar(200, [
    'status' => 'ok',
    'data'   => array_map(fn ($r) => [
        'id_program_studi' => (int) $r['id_program_studi'],
        'kode'             => $r['kode_program_studi'],
        'nama'             => $r['nama_program_studi'],
        'jenjang'          => $r['jenjang'],
        'fakultas'         => [
            'id_fakultas' => (int) $r['id_fakultas'],
            'kode'        => $r['kode_fakultas'],
            'nama'        => $r['nama_fakultas'],
        ],
    ], $rows),
]);
