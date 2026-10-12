<?php
/** api/prodi.php — daftar program studi & fakultas (JSON). Header: Authorization: Bearer <token> */
declare(strict_types=1);
ob_start();
require __DIR__ . '/../inc/db.php';
require __DIR__ . '/../inc/jwt.php';
require __DIR__ . '/../inc/data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_gagal(405, 'Gunakan metode GET.');
api_auth();
$rows = db_rows("SELECT ps.*, f.kode_fakultas, f.nama_fakultas FROM program_studi ps JOIN fakultas f ON f.id_fakultas = ps.id_fakultas
                  WHERE ps.status_aktif = 'Aktif' ORDER BY f.nama_fakultas, ps.nama_program_studi");
json_keluar(200, ['status' => 'ok', 'data' => array_map(fn ($r) => [
    'id_program_studi' => (int) $r['id_program_studi'], 'kode' => $r['kode_program_studi'], 'kode_pddikti' => $r['kode_pddikti'],
    'nama' => $r['nama_program_studi'], 'jenjang' => $r['jenjang'],
    'fakultas' => ['id_fakultas' => (int) $r['id_fakultas'], 'kode' => $r['kode_fakultas'], 'nama' => $r['nama_fakultas']],
], $rows)]);
