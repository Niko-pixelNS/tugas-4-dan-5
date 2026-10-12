/* SIM Mahasiswa — script.js
   Tombol "Tampilkan / Sembunyikan" kata sandi di login.php.
   Halaman tetap bisa dipakai walau JavaScript dimatikan. */
(function () {
  var tombol = document.getElementById('toggle-pw');
  var sandi = document.getElementById('password');
  if (!tombol || !sandi) return;

  tombol.addEventListener('click', function () {
    var tampil = sandi.type === 'password';
    sandi.type = tampil ? 'text' : 'password';
    tombol.textContent = tampil ? 'Sembunyikan' : 'Tampilkan';
    tombol.setAttribute('aria-pressed', String(tampil));
    sandi.focus();
  });
})();

/* ---------------------------------------------------------
   Dashboard (dashboard.php)
   - Desktop: tombol ← menciutkan sidebar jadi ikon saja (diingat di browser).
   - HP/tablet: tombol ☰ membuka sidebar; klik di luar / Esc menutupnya.
   - Menu akun (⋮) di pojok kiri bawah.
   --------------------------------------------------------- */
(function () {
  var app = document.getElementById('app');
  var side = document.getElementById('side');
  if (!app || !side) return;

  var scrim = document.getElementById('side-scrim');
  var openBtn = document.getElementById('menu-btn');
  var collapseBtn = document.getElementById('side-collapse');
  var desktop = window.matchMedia('(min-width: 900px)');
  var KEY = 'sim-sidebar-ciut';

  function simpan(nilai) { try { localStorage.setItem(KEY, nilai ? '1' : '0'); } catch (e) {} }
  function baca() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }

  function setCiut(ciut) {
    app.classList.toggle('is-collapsed', ciut);
    collapseBtn.setAttribute('aria-expanded', String(!ciut));
    collapseBtn.setAttribute('aria-label', ciut ? 'Lebarkan menu' : 'Ciutkan menu');
  }
  function buka() {
    side.classList.add('is-open');
    scrim.hidden = false;
    openBtn.setAttribute('aria-expanded', 'true');
    var first = side.querySelector('a, button, input');
    if (first) first.focus();
  }
  function tutup() {
    side.classList.remove('is-open');
    scrim.hidden = true;
    openBtn.setAttribute('aria-expanded', 'false');
  }

  if (desktop.matches && baca()) setCiut(true);

  collapseBtn.addEventListener('click', function () {
    if (desktop.matches) {
      var ciut = !app.classList.contains('is-collapsed');
      setCiut(ciut);
      simpan(ciut);
    } else {
      tutup();
      openBtn.focus();
    }
  });
  openBtn.addEventListener('click', buka);
  scrim.addEventListener('click', tutup);
  desktop.addEventListener('change', function (e) {
    tutup();
    setCiut(e.matches && baca());
  });

  /* Menu akun */
  var menuBtn = document.getElementById('user-menu-btn');
  var menu = document.getElementById('user-menu');
  function tutupMenu() { menu.hidden = true; menuBtn.setAttribute('aria-expanded', 'false'); }
  if (menuBtn && menu) {
    menuBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var buka = menu.hidden;
      menu.hidden = !buka;
      menuBtn.setAttribute('aria-expanded', String(buka));
      if (buka) { var a = menu.querySelector('a, button'); if (a) a.focus(); }
    });
    document.addEventListener('click', function (e) { if (!menu.hidden && !menu.contains(e.target)) tutupMenu(); });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (menu && !menu.hidden) { tutupMenu(); menuBtn.focus(); }
    else if (side.classList.contains('is-open')) { tutup(); openBtn.focus(); }
  });
})();

/* ---------------------------------------------------------
   Modul CRUD & laporan
   - form[data-konfirmasi]: minta konfirmasi sebelum hapus
   - select[data-induk-dari]: saring pilihan sesuai select induk
     (mis. Program studi mengikuti Fakultas)
   - [data-print]: cetak halaman (laporan)
   - [data-akun-pilih]: form mahasiswa, isian akun sesuai pilihan
   --------------------------------------------------------- */
(function () {
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.matches && f.matches('form[data-konfirmasi]') && !window.confirm(f.getAttribute('data-konfirmasi'))) {
      e.preventDefault();
    }
  });

  /* Form mahasiswa: tampilkan isian sesuai pilihan akun (baru / tautkan / tanpa) */
  document.querySelectorAll('[data-akun-pilih]').forEach(function (grup) {
    var form = grup.closest('form');
    function atur() {
      var pilih = form.querySelector('input[name="akun_mode"]:checked');
      var v = pilih ? pilih.value : '';
      form.querySelectorAll('[data-akun-bagian]').forEach(function (b) {
        var tampil = b.getAttribute('data-akun-bagian') === v;
        b.hidden = !tampil;
        b.querySelectorAll('input, select').forEach(function (el) { el.disabled = !tampil; });
      });
    }
    grup.addEventListener('change', atur);
    atur();
  });

  document.querySelectorAll('[data-print]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  document.querySelectorAll('select[data-induk-dari]').forEach(function (anak) {
    var induk = document.getElementById(anak.getAttribute('data-induk-dari'));
    if (!induk) return;
    function saring(reset) {
      var v = induk.value;
      Array.prototype.forEach.call(anak.options, function (o) {
        var milik = o.getAttribute('data-induk');
        var tampil = !milik || !v || milik === v;
        o.hidden = !tampil;
        o.disabled = !tampil;
        if (!tampil && o.selected && reset) anak.value = '';
      });
      anak.dispatchEvent(new Event('saring'));
    }
    induk.addEventListener('change', function () { saring(true); });
    induk.addEventListener('saring', function () { saring(true); });
    saring(false);
  });
})();
