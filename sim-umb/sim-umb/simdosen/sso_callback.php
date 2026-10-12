<?php
/**
 * sso_callback.php — SIM Mahasiswa mengembalikan Admin ke sini (?code=...&state=...).
 * Kode ditukar dengan token + data pengguna (JSON); hanya peran ADMIN yang diterima.
 */
require __DIR__ . '/inc/init.php';
require __DIR__ . '/inc/sso_klien.php';

function gagal(string $pesan): never
{
    flash('error', $pesan);
    redirect('login.php');
}

$harus = $_SESSION['sso_state'] ?? '';
unset($_SESSION['sso_state']);
if ($harus === '' || !hash_equals($harus, get('state'))) gagal('Permintaan login tidak sah atau sudah kedaluwarsa. Silakan ulangi.');
if (get('code') === '') gagal('Kode login dari SSO tidak diterima.');

$r = sso_admin_tukar(get('code'));
if ($r['error']) gagal('Login SSO SIM Mahasiswa gagal: ' . $r['error']);
$u = $r['json']['user'] ?? null;
if (!$u || ($u['role'] ?? '') !== 'ADMIN') gagal('Hanya akun Admin SIM Mahasiswa yang bisa masuk ke SIM Dosen lewat SSO. Dosen silakan login dengan nomor registrasi.');

session_regenerate_id(true);
$_SESSION['user'] = [
    'sumber' => 'simmhs', 'id' => (int) $u['id_pengguna'], 'username' => $u['username'], 'nama' => $u['nama_lengkap'],
    'email' => $u['email'], 'role' => 'ADMIN', 'nama_role' => 'Admin', 'id_dosen' => null,
];
catat_log('LOGIN', 'Admin masuk lewat SSO SIM Mahasiswa');
flash('success', 'Selamat datang, ' . rtrim($u['nama_lengkap'], '.') . '.');
redirect('dashboard.php');
