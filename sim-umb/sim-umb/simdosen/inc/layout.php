<?php
/**
 * inc/layout.php — kerangka halaman SIM Dosen (sidebar, topbar) & ikon SVG.
 */

const IKON = [
    'home' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
    'file' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
    'fileEdit' => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h5"/><path d="M14 3v5h5v3M9 9h2M9 13h4"/><path d="m18.5 14.5 2 2L15 22h-2v-2z"/>',
    'chat' => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/><path d="M8.5 12h.01M12 12h.01M15.5 12h.01"/>',
    'monitor' => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4M8 10l2.5 2.5L16 8"/>',
    'award' => '<circle cx="12" cy="9" r="5.5"/><path d="m8.5 13.5-1.5 7.5 5-2.5 5 2.5-1.5-7.5"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'teacher' => '<circle cx="9" cy="7" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 11.6-4"/><rect x="14" y="11" width="8" height="6" rx="1"/><path d="M18 17v3"/>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'folder' => '<path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 13l2 2 4-4"/>',
    'database' => '<ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v13c0 1.7 3.6 3 8 3s8-1.3 8-3v-13M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
    'printer' => '<path d="M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/>',
    'megaphone' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/>',
    'bell' => '<path d="M6 9a6 6 0 0 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9"/><path d="M10 20a2 2 0 0 0 4 0"/>',
    'logout' => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l-5-5 5-5M5 12h11"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'eyeOff' => '<path d="M3 3l18 18M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
    'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
    'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
    'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    'upload' => '<path d="M12 15V4M7.5 8.5 12 4l4.5 4.5M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
    'download' => '<path d="M12 4v11M7.5 10.5 12 15l4.5-4.5M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
    'arrowLeft' => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
    'arrowRight' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    'chevDown' => '<path d="m6 9 6 6 6-6"/>',
    'chevRight' => '<path d="m9 6 6 6-6 6"/>',
    'lock' => '<rect x="4.5" y="10.5" width="15" height="10.5" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
    'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 6l3 3M15 8l2 2"/>',
    'refresh' => '<path d="M20 11a8 8 0 0 0-14.9-3M4 4v4h4M4 13a8 8 0 0 0 14.9 3M20 20v-4h-4"/>',
    'check2' => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 11h6M9 15h4"/>',
    'progress' => '<path d="M4 19h16"/><rect x="5" y="11" width="3" height="6" rx=".5"/><rect x="10.5" y="7" width="3" height="10" rx=".5"/><rect x="16" y="4" width="3" height="13" rx=".5"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
];
function icon(string $nama, string $kelas = ''): string
{
    return '<svg class="ic' . ($kelas ? ' ' . $kelas : '') . '" viewBox="0 0 24 24" aria-hidden="true">' . (IKON[$nama] ?? '') . '</svg>';
}

/** Menu sidebar: hanya yang diizinkan untuk peran pengguna. [kunci, label, ikon, url, izin] */
function menu_peran(string $peran): array
{
    $semua = [
        ['dashboard', 'Dashboard', 'home', 'dashboard.php', 'dashboard'],
        ['dosen', 'Data Dosen', 'users', 'dosen.php', 'dosen.lihat'],
        ['laporan', 'Laporan', 'clipboard', 'laporan.php', 'laporan.lihat'],
        ['master', 'Master Data', 'database', 'master.php', 'master.kelola'],
        ['pengguna', 'Pengguna', 'key', 'pengguna.php', 'pengguna.kelola'],
        ['profil', $peran === 'DOSEN' ? 'Data Diri Saya' : 'Profil Saya', 'user', 'profil.php', 'profil'],
    ];
    return array_values(array_filter($semua, fn ($m) => in_array($peran, IZIN[$m[4]], true)));
}

function head_html(string $judul, string $dasar = ''): void
{
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($judul) . ' — ' . APP_NAME . ' UMB</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . $dasar . 'assets/css/style.css"></head>';
}

/** Awal halaman aplikasi: sidebar + topbar + judul halaman. */
function layout_start(string $judul, string $menu, string $sub = '', string $aksi = ''): void
{
    $u = user();
    head_html($judul);
    echo '<body><div class="app"><aside class="sidebar" id="sidebar">';
    echo '<a class="side-brand" href="dashboard.php"><span class="logo"><img src="assets/img/logo-umb.png" alt=""></span><strong>SIM DOSEN<br>UMB</strong></a>';
    echo '<nav class="side-nav" aria-label="Menu utama">';
    foreach (menu_peran($u['role']) as [$kunci, $label, $ikon, $url]) {
        $aktif = $kunci === $menu;
        echo '<a class="side-link' . ($aktif ? ' active' : '') . '" href="' . e($url) . '"' . ($aktif ? ' aria-current="page"' : '') . '>' . icon($ikon) . '<span>' . e($label) . '</span></a>';
    }
    echo '</nav></aside><div class="scrim" id="side-scrim"></div><div class="main">';

    echo '<header class="topbar"><button class="menu-btn" type="button" id="menu-btn" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">' . icon('menu') . '</button>';
    echo '<div class="topbar-right"><div class="user">';
    echo '<button class="user-btn" type="button" id="user-btn" aria-haspopup="true" aria-expanded="false" aria-controls="user-menu">'
        . '<span class="avatar">' . e(inisial($u['nama'])) . '</span>'
        . '<span class="user-text"><span class="user-name">' . e($u['nama']) . '</span><br><span class="user-role">' . e(LABEL_PERAN[$u['role']] ?? $u['role']) . '</span></span>'
        . icon('chevDown') . '</button>';
    echo '<div class="user-menu" id="user-menu" hidden><a href="profil.php">' . icon('user') . 'Profil saya</a>'
        . '<form method="post" action="login.php">' . csrf_field() . '<input type="hidden" name="aksi" value="logout">'
        . '<button type="submit" class="menu-item">' . icon('logout') . 'Keluar</button></form></div>';
    echo '</div></div></header><main class="content">';

    echo '<div class="page-head"><div><h1>' . e($judul) . '</h1>' . ($sub ? '<p>' . $sub . '</p>' : '') . '</div>'
        . ($aksi ? '<div class="page-actions">' . $aksi . '</div>' : '') . '</div>';
    echo flash_render();
}

function layout_end(): void
{
    echo '</main></div></div><script src="assets/js/script.js"></script></body></html>';
}

/** Kartu pesan kosong. */
function kosong(string $pesan, string $aksi = ''): string
{
    return '<div class="empty">' . icon('clipboard') . '<p>' . e($pesan) . '</p>' . $aksi . '</div>';
}

/** Daftar <dl> label : nilai. Nilai sudah dalam HTML aman. */
function info_list(array $baris): string
{
    $html = '<dl class="info-list">';
    foreach ($baris as $label => $nilai) $html .= '<dt>' . e($label) . '</dt><dd>' . $nilai . '</dd>';
    return $html . '</dl>';
}
