<?php
/**
 * inc/sso.php — klien SSO & web service SIM Mahasiswa.
 * Semua data dari SIM Mahasiswa diterima dalam bentuk JSON lewat HTTP.
 */

/**
 * Kirim permintaan HTTP dan baca jawaban JSON.
 * Mengembalikan ['kode' => status HTTP, 'json' => array|null, 'error' => string|null].
 */
function http_json(string $metode, string $url, ?array $form = null, ?string $token = null): array
{
    $header = ['Accept: application/json'];
    if ($token) $header[] = 'Authorization: Bearer ' . $token;
    $isi = '';
    if ($form !== null) {
        $isi = http_build_query($form);
        $header[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $ctx = stream_context_create(['http' => [
        'method'        => $metode,
        'header'        => implode("\r\n", $header),
        'content'       => $isi,
        'timeout'       => 10,
        'ignore_errors' => true, // tetap baca isi JSON walau status 4xx/5xx
    ]]);
    $badan = @file_get_contents($url, false, $ctx);
    if ($badan === false) {
        return ['kode' => 0, 'json' => null, 'error' => 'Server SSO/API tidak dapat dihubungi. Periksa alamat di config/app.php.'];
    }
    $kode = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $kode = (int) $m[1];
    }
    $json = json_decode($badan, true);
    if (!is_array($json)) {
        // Hosting gratis seperti InfinityFree membalas permintaan API dengan halaman cek JavaScript
        $diblokir = stripos($badan, 'aes.js') !== false || stripos($badan, 'requires javascript') !== false;
        return ['kode' => $kode, 'json' => null, 'error' => $diblokir
            ? 'Permintaan API diblokir sistem keamanan hosting (InfinityFree gratis tidak mengizinkan akses API). Jalankan di XAMPP lokal atau hosting yang mengizinkan API.'
            : 'Jawaban server bukan JSON yang sah.'];
    }
    return ['kode' => $kode, 'json' => $json, 'error' => $kode >= 400 ? ($json['message'] ?? 'Permintaan ditolak.') : null];
}

/** Dua sumber SSO: SIM Mahasiswa (admin, prodi, dekanat, mahasiswa) dan SIM Dosen (dosen). */
function sso_sumber(string $sumber): array
{
    return $sumber === 'simdosen'
        ? ['url' => SIMDOSEN_URL, 'secret' => SSO_SECRET_SIMDOSEN, 'nama' => 'SIM Dosen']
        : ['url' => SIMMHS_URL, 'secret' => SSO_CLIENT_SECRET, 'nama' => 'SIM Mahasiswa'];
}

/** URL halaman login SSO, dengan state acak (dan asal SSO) untuk mencegah pemalsuan. */
function sso_url_login(string $sumber = 'simmhs'): string
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['sso_state'] = ['state' => $state, 'sumber' => $sumber === 'simdosen' ? 'simdosen' : 'simmhs'];
    return sso_sumber($sumber)['url'] . '/sso/authorize.php?' . http_build_query([
        'client_id' => SSO_CLIENT_ID, 'redirect_uri' => SSO_REDIRECT_URI, 'state' => $state,
    ]);
}

/** Tukar kode login dengan token + data pengguna (JSON). */
function sso_tukar_kode(string $kode, string $sumber = 'simmhs'): array
{
    $s = sso_sumber($sumber);
    return http_json('POST', $s['url'] . '/api/token.php', [
        'grant_type' => 'authorization_code', 'code' => $kode, 'redirect_uri' => SSO_REDIRECT_URI,
        'client_id' => SSO_CLIENT_ID, 'client_secret' => $s['secret'],
    ]);
}

/** Token aplikasi (antarserver) per sumber, disimpan di sesi sampai hampir kedaluwarsa. */
function sso_token_aplikasi(string $sumber = 'simmhs'): ?string
{
    $kunci = 'token_aplikasi_' . $sumber;
    $c = $_SESSION[$kunci] ?? null;
    if ($c && $c['exp'] > time() + 30) return $c['token'];
    $s = sso_sumber($sumber);
    $r = http_json('POST', $s['url'] . '/api/token.php', [
        'grant_type' => 'client_credentials', 'client_id' => SSO_CLIENT_ID, 'client_secret' => $s['secret'],
    ]);
    if ($r['error'] || empty($r['json']['access_token'])) return null;
    $_SESSION[$kunci] = ['token' => $r['json']['access_token'], 'exp' => time() + (int) $r['json']['expires_in']];
    return $r['json']['access_token'];
}

/**
 * Panggil API (GET) di SIM Mahasiswa ($sumber = 'simmhs') atau SIM Dosen ('simdosen').
 * Memakai token pengguna bila ia login dari sumber yang sama, selain itu token aplikasi.
 */
function api_get(string $path, array $query = [], bool $sebagaiAplikasi = false, string $sumber = 'simmhs'): array
{
    $u = user();
    $token = null;
    if (!$sebagaiAplikasi && ($u['sumber'] ?? '') === $sumber && ($u['token_exp'] ?? 0) > time() + 10) $token = $u['token'];
    $token = $token ?? sso_token_aplikasi($sumber);
    if (!$token) return ['kode' => 0, 'json' => null, 'error' => 'Tidak bisa mendapatkan token dari ' . sso_sumber($sumber)['nama'] . '.'];
    return http_json('GET', sso_sumber($sumber)['url'] . '/api/' . $path . ($query ? '?' . http_build_query($query) : ''), null, $token);
}

/** Daftar prodi dari API (disimpan 10 menit di sesi). */
function daftar_prodi(): array
{
    $c = $_SESSION['cache_prodi'] ?? null;
    if ($c && $c['exp'] > time()) return $c['data'];
    $r = api_get('prodi.php', [], true);
    if ($r['error']) {
        // simpan hasil kosong sebentar supaya halaman tidak menunggu API berulang kali
        $_SESSION['cache_prodi'] = ['data' => [], 'exp' => time() + 60];
        return [];
    }
    $_SESSION['cache_prodi'] = ['data' => $r['json']['data'], 'exp' => time() + 600];
    return $r['json']['data'];
}
function nama_prodi($id): string
{
    foreach (daftar_prodi() as $p) {
        if ((int) $p['id_program_studi'] === (int) $id) return $p['nama'];
    }
    return $id ? 'Prodi #' . (int) $id : 'Semua prodi';
}

/** Simpan / perbarui salinan data pengguna dari JSON SSO ke tabel pengguna_sso. */
function simpan_pengguna_sso(array $u): void
{
    $m = $u['mahasiswa'] ?? null;
    db_q(
        "INSERT INTO pengguna_sso (id_pengguna, username, nama_lengkap, email, kode_role, id_fakultas,
                                   id_program_studi, nama_program_studi, id_mahasiswa, npm)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE username = VALUES(username), nama_lengkap = VALUES(nama_lengkap),
                email = VALUES(email), kode_role = VALUES(kode_role), id_fakultas = VALUES(id_fakultas),
                id_program_studi = VALUES(id_program_studi), nama_program_studi = VALUES(nama_program_studi),
                id_mahasiswa = VALUES(id_mahasiswa), npm = VALUES(npm), terakhir_sinkron = NOW()",
        [
            (int) $u['id_pengguna'], (string) $u['username'],
            (string) ($m['nama_mahasiswa'] ?? $u['nama_lengkap']), $u['email'] ?? null, (string) $u['role'],
            $u['id_fakultas'] ?? null, $u['id_program_studi'] ?? null, $u['nama_program_studi'] ?? null,
            $m ? (int) $m['id_mahasiswa'] : null, $m['npm'] ?? null,
        ]
    );
}

/** Perbarui cache satu mahasiswa dari API (dipakai tombol "Perbarui data"). */
function sinkron_mahasiswa(int $idMahasiswa): ?string
{
    $r = api_get('mahasiswa.php', ['id' => $idMahasiswa], is_staf());
    if ($r['error']) return $r['error'];
    $m = $r['json']['data'];
    db_q(
        'UPDATE pengguna_sso SET nama_lengkap = ?, npm = ?, id_program_studi = ?, nama_program_studi = ?,
                                 id_fakultas = ?, terakhir_sinkron = NOW()
          WHERE id_mahasiswa = ?',
        [$m['nama_mahasiswa'], $m['npm'], (int) $m['program_studi']['id_program_studi'],
         $m['program_studi']['nama'], (int) $m['fakultas']['id_fakultas'], $idMahasiswa]
    );
    return null;
}

/* ---------- Data dosen dari SIM Dosen ---------- */

/** Peta kode prodi -> id prodi di SIM Mahasiswa, supaya dosen & mahasiswa memakai prodi yang sama. */
function peta_prodi(): array
{
    $peta = [];
    foreach (daftar_prodi() as $p) $peta[strtoupper((string) $p['kode'])] = (int) $p['id_program_studi'];
    return $peta;
}

/** Simpan / perbarui satu dosen (JSON dari SIM Dosen) ke tabel dosen. Mengembalikan id_dosen lokal. */
function simpan_dosen(array $d, ?array $peta = null): int
{
    $peta = $peta ?? peta_prodi();
    $kode = strtoupper((string) ($d['program_studi']['kode'] ?? ''));
    db_q(
        "INSERT INTO dosen (id_simdosen, id_pengguna_simdosen, nomor_registrasi, nuptk, nip, nama_dosen, gelar_depan, gelar_belakang, email,
                            kode_program_studi, nama_program_studi, id_program_studi, jabatan_fungsional, pendidikan_terakhir,
                            status_aktivitas, status_aktif, terakhir_sinkron)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE id_pengguna_simdosen = VALUES(id_pengguna_simdosen), nomor_registrasi = VALUES(nomor_registrasi),
                nuptk = VALUES(nuptk), nip = VALUES(nip), nama_dosen = VALUES(nama_dosen), gelar_depan = VALUES(gelar_depan),
                gelar_belakang = VALUES(gelar_belakang), email = VALUES(email), kode_program_studi = VALUES(kode_program_studi),
                nama_program_studi = VALUES(nama_program_studi), id_program_studi = VALUES(id_program_studi),
                jabatan_fungsional = VALUES(jabatan_fungsional), pendidikan_terakhir = VALUES(pendidikan_terakhir),
                status_aktivitas = VALUES(status_aktivitas), status_aktif = VALUES(status_aktif), terakhir_sinkron = NOW()",
        [
            (int) $d['id_dosen'], $d['id_pengguna'] ?? null, $d['nomor_registrasi'], $d['nuptk'] ?? null, $d['nip'] ?? null,
            $d['nama_dosen'], $d['gelar_depan'] ?? null, $d['gelar_belakang'] ?? null, $d['email'] ?? null,
            $kode ?: null, $d['program_studi']['nama'] ?? null, $peta[$kode] ?? null,
            $d['jabatan_fungsional'] ?? null, $d['pendidikan_terakhir'] ?? null, $d['status_aktivitas'] ?? null,
            ($d['status_aktivitas'] ?? '') === 'Aktif' ? 'Aktif' : 'Nonaktif',
        ]
    );
    return (int) db_val('SELECT id_dosen FROM dosen WHERE id_simdosen = ?', [(int) $d['id_dosen']]);
}

/** Ambil semua dosen dari api/dosen.php (per 100 baris). Mengembalikan [jumlah, pesan error|null]. */
function sinkron_semua_dosen(): array
{
    $peta = peta_prodi();
    $hal = 1;
    $n = 0;
    do {
        $r = api_get('dosen.php', ['halaman' => $hal, 'per_halaman' => 100], true, 'simdosen');
        if ($r['error']) return [$n, $r['error']];
        foreach ($r['json']['data'] as $d) {
            simpan_dosen($d, $peta);
            $n++;
        }
        $total = (int) $r['json']['meta']['total'];
    } while ($hal++ * 100 < $total);
    return [$n, null];
}
