<?php
/**
 * sso_callback.php — SIM Mahasiswa atau SIM Dosen mengembalikan pengguna ke sini (?code=...&state=...).
 * Kode ditukar (antarserver) dengan token + data pengguna dalam JSON, lalu sesi SIMSKRIPSI dibuat.
 *   SIM Mahasiswa -> Admin, Operator Prodi, Dekanat, Mahasiswa (disalin ke pengguna_sso)
 *   SIM Dosen     -> Dosen (disalin ke tabel dosen)
 */
require __DIR__ . '/inc/init.php';

const PERAN_SIMMHS = ['ADMIN', 'OPERATOR_PRODI', 'DEKANAT', 'MAHASISWA'];

function gagal(string $pesan): never
{
    flash('error', $pesan);
    redirect('login.php');
}

$st = $_SESSION['sso_state'] ?? null;
unset($_SESSION['sso_state']);
if (!is_array($st) || !hash_equals($st['state'], get('state'))) gagal('Permintaan login tidak sah atau sudah kedaluwarsa. Silakan ulangi.');
if (get('code') === '') gagal('Kode login dari SSO tidak diterima.');
$sumber = $st['sumber'];

$r = sso_tukar_kode(get('code'), $sumber);
if ($r['error']) gagal('Login SSO ' . sso_sumber($sumber)['nama'] . ' gagal: ' . $r['error']);
$json = $r['json'];
$u = $json['user'] ?? null;
if (!$u || empty($json['access_token'])) gagal('Jawaban SSO tidak lengkap.');

/* ---------- Dosen dari SIM Dosen ---------- */
if ($sumber === 'simdosen') {
    if ($u['role'] !== 'DOSEN' || empty($u['dosen'])) gagal('Akun SIM Dosen ini bukan akun dosen.');
    $idDosen = simpan_dosen($u['dosen']);
    $d = db_row('SELECT * FROM dosen WHERE id_dosen = ?', [$idDosen]);
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'sumber' => 'simdosen', 'role' => 'DOSEN', 'id' => $idDosen, 'username' => $u['username'],
        'nama' => nama_dosen($d), 'email' => $u['email'], 'id_program_studi' => $d['id_program_studi'] !== null ? (int) $d['id_program_studi'] : null,
        'id_simdosen' => (int) $d['id_simdosen'], 'token' => $json['access_token'], 'token_exp' => time() + (int) $json['expires_in'],
    ];
    db_exec('UPDATE dosen SET last_login = NOW() WHERE id_dosen = ?', [$idDosen]);
    flash('success', 'Selamat datang, ' . rtrim($_SESSION['user']['nama'], '.') . '.' . ($d['status_aktif'] !== 'Aktif' ? ' Status Anda di SIM Dosen: ' . $d['status_aktivitas'] . ', jadi tidak bisa ditetapkan sebagai pembimbing/penguji baru.' : ''));
    redirect('dashboard.php');
}

/* ---------- Admin, prodi, dekanat, mahasiswa dari SIM Mahasiswa ---------- */
if (!in_array($u['role'], PERAN_SIMMHS, true)) gagal('Peran ' . ($u['nama_role'] ?? $u['role']) . ' tidak memiliki akses ke SIMSKRIPSI.');
if ($u['role'] === 'MAHASISWA' && empty($u['mahasiswa'])) gagal('Akun ini belum terhubung dengan data mahasiswa di SIM Mahasiswa.');
if ($u['role'] === 'OPERATOR_PRODI' && empty($u['id_program_studi'])) gagal('Akun operator ini belum diberi program studi di SIM Mahasiswa.');
if ($u['role'] === 'DEKANAT' && empty($u['id_fakultas'])) gagal('Akun dekanat ini belum diberi fakultas di SIM Mahasiswa.');

simpan_pengguna_sso($u);
session_regenerate_id(true);
$m = $u['mahasiswa'];
$_SESSION['user'] = [
    'sumber' => 'simmhs', 'role' => $u['role'], 'id' => (int) $u['id_pengguna'], 'username' => $u['username'],
    'nama' => $m['nama_mahasiswa'] ?? $u['nama_lengkap'], 'email' => $u['email'],
    'id_program_studi' => $u['id_program_studi'] ? (int) $u['id_program_studi'] : null, 'nama_program_studi' => $u['nama_program_studi'],
    'id_fakultas' => $u['id_fakultas'] ? (int) $u['id_fakultas'] : null, 'nama_fakultas' => $u['nama_fakultas'],
    'id_mahasiswa' => $m ? (int) $m['id_mahasiswa'] : null, 'npm' => $m['npm'] ?? null,
    'token' => $json['access_token'], 'token_exp' => time() + (int) $json['expires_in'],
];
flash('success', 'Selamat datang, ' . rtrim($_SESSION['user']['nama'], '.') . '.');
redirect('dashboard.php');
