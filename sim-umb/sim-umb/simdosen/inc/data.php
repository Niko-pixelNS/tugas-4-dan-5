<?php
/**
 * inc/data.php — bentuk data JSON yang dikirim web service SIM Dosen.
 * Hanya kolom yang perlu; tanggal lahir & no. HP tidak dikirim.
 */

/** Satu baris v_dosen -> JSON dosen. */
function dosen_json(array $v): array
{
    return [
        'id_dosen'           => (int) $v['id_dosen'],
        'id_pengguna'        => $v['id_pengguna'] !== null ? (int) $v['id_pengguna'] : null,
        'nomor_registrasi'   => $v['nomor_registrasi'],
        'jenis_registrasi'   => $v['jenis_registrasi'],
        'nuptk'              => $v['nuptk'],
        'nip'                => $v['nip'],
        'nama_dosen'         => $v['nama_dosen'],
        'gelar_depan'        => $v['gelar_depan'],
        'gelar_belakang'     => $v['gelar_belakang'],
        'nama_lengkap'       => $v['nama_lengkap'],
        'jenis_kelamin'      => $v['jenis_kelamin'],
        'email'              => $v['email'],
        'jabatan_fungsional' => $v['jabatan_fungsional'],
        'kepangkatan'        => $v['kepangkatan'],
        'pendidikan_terakhir'=> $v['pendidikan_terakhir'],
        'rumpun_ilmu'        => $v['rumpun_ilmu'],
        'status_serdos'      => $v['status_serdos'],
        'status_pegawai'     => $v['status_pegawai'],
        'ikatan_kerja'       => $v['ikatan_kerja'],
        'status_aktivitas'   => $v['status_aktivitas'],
        'program_studi'      => ['id_program_studi' => (int) $v['id_program_studi'], 'kode' => $v['kode_program_studi'],
                                 'kode_pddikti' => $v['kode_pddikti'], 'nama' => $v['nama_program_studi'], 'jenjang' => $v['jenjang']],
        'fakultas'           => ['id_fakultas' => (int) $v['id_fakultas'], 'kode' => $v['kode_fakultas'], 'nama' => $v['nama_fakultas']],
        'diperbarui'         => $v['updated_at'],
    ];
}

/** Data akun untuk SSO (tanpa password) + data dosen bila akun dosen. */
function pengguna_json(int $id): ?array
{
    $p = db_row("SELECT p.id_pengguna, p.username, p.nama_lengkap, p.email, p.status_aktif, r.kode_role, r.nama_role FROM pengguna p JOIN roles r ON r.id_role = p.id_role WHERE p.id_pengguna = ?", [$id]);
    if (!$p) return null;
    $v = db_row('SELECT * FROM v_dosen WHERE id_pengguna = ?', [$id]);
    return [
        'id_pengguna'      => (int) $p['id_pengguna'],
        'username'         => $p['username'],
        'nama_lengkap'     => $p['nama_lengkap'],
        'email'            => $p['email'],
        'status_aktif'     => $p['status_aktif'],
        'role'             => $p['kode_role'],
        'nama_role'        => $p['nama_role'],
        'dosen'            => $v ? dosen_json($v) : null,
    ];
}

/** Cek header Authorization: Bearer <token>. Mengembalikan isi token (+ data pengguna bila token pengguna). */
function api_auth(): array
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) $h = array_change_key_case(apache_request_headers())['authorization'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) json_gagal(401, 'Token tidak dikirim. Minta token di api/token.php.');
    $isi = jwt_baca($m[1]);
    if (!$isi || !in_array($isi['typ'] ?? '', ['access', 'client'], true) || !sso_client($isi['aud'] ?? null)) json_gagal(401, 'Token tidak sah atau sudah kedaluwarsa.');
    if ($isi['typ'] === 'access') {
        $u = pengguna_json((int) $isi['sub']);
        if (!$u || $u['status_aktif'] !== 'Aktif') json_gagal(401, 'Akun tidak aktif.');
        $isi['pengguna'] = $u;
    }
    return $isi;
}
