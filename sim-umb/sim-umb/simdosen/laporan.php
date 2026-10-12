<?php
/**
 * laporan.php — "Daftar Dosen Aktif Universitas" sesuai format laporan tugas.
 *   Tampilan layar + cetak (Ctrl+P / tombol Cetak) + unduh Excel (?format=xls)
 */
require __DIR__ . '/inc/init.php';
$u = wajib_izin('laporan.lihat');

[$sc, $sp] = scope_dosen('v');
$where = "FROM v_dosen v WHERE 1=1 $sc";
$param = $sp;
$idFak = get('fakultas') !== '' ? (int) get('fakultas') : null;
$idProdi = get('prodi') !== '' ? (int) get('prodi') : null;
$status = get('status', 'Aktif');
if ($idFak) { $where .= ' AND v.id_fakultas = ?'; $param[] = $idFak; }
if ($idProdi) { $where .= ' AND v.id_program_studi = ?'; $param[] = $idProdi; }
if ($status !== 'semua') { $where .= ' AND v.status_aktivitas = ?'; $param[] = $status; }
$rows = db_rows("SELECT v.* $where ORDER BY v.nama_fakultas, v.nama_program_studi, v.nama_dosen", $param);

$univ = db_val('SELECT nama_universitas FROM universitas ORDER BY id_universitas LIMIT 1') ?? 'Universitas Muhammadiyah Bengkulu';
$namaFak = $idFak ? db_val('SELECT nama_fakultas FROM fakultas WHERE id_fakultas = ?', [$idFak]) : null;
$namaProdi = $idProdi ? db_val("SELECT CONCAT(jenjang, ' ', nama_program_studi) FROM program_studi WHERE id_program_studi = ?", [$idProdi]) : null;
$judul = $status === 'Aktif' ? 'Daftar Dosen Aktif Universitas' : 'Daftar Dosen Universitas' . ($status !== 'semua' ? ' — ' . $status : '');
$kolom = ['No', 'Nomor Registrasi', 'NUPTK', 'Nama', 'NIP', 'Jabatan Fungsional', 'Kepangkatan', 'Pendidikan Terakhir', 'Rumpun Ilmu', 'Status Serdos', 'Status Pegawai', 'Ikatan Kerja', 'Status Aktivitas', 'Penempatan'];

function isi_tabel(array $rows, array $kolom, bool $excel = false): string
{
    $teks = $excel ? ' style="mso-number-format:\'\\@\'"' : ''; // agar angka 0 di depan tidak hilang di Excel
    $h = '<table class="table laporan-table" border="1"><thead><tr>';
    foreach ($kolom as $k) $h .= '<th>' . e($k) . '</th>';
    $h .= '</tr></thead><tbody>';
    if (!$rows) $h .= '<tr><td colspan="' . count($kolom) . '" style="text-align:center">Tidak ada data.</td></tr>';
    foreach ($rows as $i => $d) {
        $h .= '<tr><td>' . ($i + 1) . '</td><td' . $teks . '>' . e($d['nomor_registrasi']) . '</td><td' . $teks . '>' . e($d['nuptk']) . '</td><td>' . e($d['nama_lengkap'])
            . '</td><td' . $teks . '>' . e($d['nip']) . '</td><td>' . e($d['jabatan_fungsional']) . '</td><td>' . e($d['kepangkatan']) . '</td><td>' . e($d['pendidikan_terakhir'])
            . '</td><td>' . e($d['rumpun_ilmu']) . '</td><td>' . e($d['status_serdos']) . '</td><td>' . e($d['status_pegawai']) . '</td><td>' . e($d['ikatan_kerja'])
            . '</td><td>' . e($d['status_aktivitas']) . '</td><td>' . e($d['penempatan']) . '</td></tr>';
    }
    return $h . '</tbody></table>';
}
$kop = '<b>' . e($judul) . '</b><br><br>Universitas : ' . e($univ) . '<br>Fakultas : ' . e($namaFak ?? 'Semua fakultas') . '<br>Program Studi : ' . e($namaProdi ?? 'Semua program studi');

/* ---------- Unduh Excel ---------- */
if (get('format') === 'xls') {
    catat_log('EXPORT', 'Unduh laporan dosen (' . count($rows) . ' baris)');
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="daftar-dosen-' . date('Ymd') . '.xls"');
    echo "\xEF\xBB\xBF<html><head><meta charset=\"utf-8\"></head><body><p>$kop</p>" . isi_tabel($rows, $kolom, true)
        . '<p>Dicetak ' . e(tgl_jam(date('Y-m-d H:i:s'))) . '</p></body></html>';
    exit;
}

/* ---------- Tampilan ---------- */
$fakOpsi = opsi_tabel('fakultas', 'id_fakultas', 'nama_fakultas', 'nama_fakultas');
$prodiW = $idFak ? [' WHERE id_fakultas = ?', [$idFak]] : ['', []];
$prodiOpsi = [];
foreach (db_rows("SELECT id_program_studi, CONCAT(jenjang, ' ', nama_program_studi) AS n FROM program_studi{$prodiW[0]} ORDER BY nama_program_studi", $prodiW[1]) as $r) $prodiOpsi[$r['id_program_studi']] = $r['n'];
$q = $_GET;
$q['format'] = 'xls';

layout_start('Laporan Dosen', 'laporan', 'Format laporan "Daftar Dosen Aktif Universitas".',
    '<button class="btn btn-outline no-print" type="button" onclick="window.print()">' . icon('printer') . 'Cetak</button>'
    . '<a class="btn btn-green no-print" href="laporan.php?' . e(http_build_query($q)) . '">' . icon('download') . 'Unduh Excel</a>');
?>
<section class="card no-print mb">
  <form class="filters" method="get" data-auto style="border-bottom:0">
    <?php if ($fakOpsi): ?><label class="sr-only" for="f-fak">Fakultas</label><select class="select" id="f-fak" name="fakultas"><?= opsi_html($fakOpsi, $idFak, 'Semua fakultas') ?></select><?php endif; ?>
    <?php if (count($prodiOpsi) > 1): ?><label class="sr-only" for="f-prodi">Prodi</label><select class="select" id="f-prodi" name="prodi"><?= opsi_html($prodiOpsi, get('prodi'), 'Semua program studi') ?></select><?php endif; ?>
    <label class="sr-only" for="f-status">Status</label>
    <select class="select" id="f-status" name="status"><?= opsi_html(array_merge(['semua' => 'Semua status'], array_combine($a = array_values(opsi_tabel('status_aktivitas', 'id_status_aktivitas')), $a)), $status, '') ?></select>
  </form>
</section>
<section class="card card-pad laporan">
  <p class="laporan-kop"><?= $kop ?></p>
  <div class="table-wrap"><?= isi_tabel($rows, $kolom) ?></div>
  <p class="small muted mt">Jumlah: <?= count($rows) ?> dosen · Dicetak <?= tgl_jam(date('Y-m-d H:i:s')) ?> oleh <?= e($u['nama']) ?></p>
</section>
<?php
layout_end();
