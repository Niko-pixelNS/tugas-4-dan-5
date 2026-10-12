<?php
/**
 * unduh.php?jenis=dokumen|bimbingan_mhs|bimbingan_dosen|lampiran&id=...
 * Semua berkas di uploads/ hanya bisa diambil lewat sini, setelah hak akses dicek.
 */
require __DIR__ . '/inc/init.php';

$jenis = get('jenis');
$id = (int) get('id');
$path = null;
$nama = 'berkas';

function tolak(): never
{
    http_response_code(404);
    exit('Berkas tidak ditemukan atau Anda tidak berhak mengunduhnya.');
}

if ($jenis === 'lampiran') {
    $p = db_row("SELECT * FROM pengumuman WHERE id_pengumuman = ?", [$id]);
    if (!$p || !$p['lampiran']) tolak();
    $u = user();
    $publik = $p['status'] === 'Terbit' && $p['sasaran'] === 'Semua' && $p['id_program_studi'] === null;
    $bolehLihat = $u && (is_pengelola() || is_role('DEKANAT') || in_array((int) $id, array_map(fn ($a) => (int) $a['id_pengumuman'], pengumuman_tampil($u, 500)), true));
    if (!$publik && !$bolehLihat) tolak();
    $path = $p['lampiran'];
    $nama = 'lampiran-pengumuman-' . $id . '.' . pathinfo($path, PATHINFO_EXTENSION);
} else {
    require_login();
    if ($jenis === 'dokumen') {
        $d = db_row('SELECT d.*, jd.nama_dokumen FROM dokumen d JOIN jenis_dokumen jd ON jd.id_jenis = d.id_jenis WHERE d.id_dokumen = ?', [$id]);
        if (!$d) tolak();
        $u = user();
        $boleh = ($u['role'] === 'MAHASISWA' && (int) $d['id_mahasiswa'] === (int) $u['id_mahasiswa']);
        if (!$boleh && is_staf()) {
            [$sc, $sp] = scope_staf('m');
            $boleh = (bool) db_val("SELECT 1 FROM pengguna_sso m WHERE m.id_mahasiswa = ? $sc", array_merge([(int) $d['id_mahasiswa']], $sp));
        }
        if (!$boleh && $u['role'] === 'DOSEN') {
            $pj = pengajuan_aktif_mahasiswa((int) $d['id_mahasiswa']);
            $boleh = $pj && boleh_lihat_pengajuan($pj);
        }
        if (!$boleh) tolak();
        $path = $d['path_file'];
        $nama = $d['nama_file_asli'];
    } elseif (in_array($jenis, ['bimbingan_mhs', 'bimbingan_dosen'], true)) {
        $b = db_row('SELECT * FROM bimbingan WHERE id_bimbingan = ?', [$id]);
        $pj = $b ? pengajuan_ambil((int) $b['id_pengajuan']) : null;
        if (!$pj || !boleh_lihat_pengajuan($pj)) tolak();
        $path = $jenis === 'bimbingan_mhs' ? $b['file_mahasiswa'] : $b['file_dosen'];
        $nama = ($jenis === 'bimbingan_mhs' ? 'bimbingan-' : 'koreksi-dosen-') . $id . '.' . pathinfo((string) $path, PATHINFO_EXTENSION);
    }
}

if (!$path || !preg_match('/^[\w.-]+$/', $path) || !is_file(UPLOAD_DIR . '/' . $path)) tolak();

$mime = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), UPLOAD_DIR . '/' . $path) : 'application/octet-stream';
$tampil = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true);
while (ob_get_level()) ob_end_clean();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize(UPLOAD_DIR . '/' . $path));
header('Content-Disposition: ' . ($tampil ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $nama) . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, no-store');
readfile(UPLOAD_DIR . '/' . $path);
