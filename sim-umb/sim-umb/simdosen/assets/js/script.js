/* SIMSKRIPSI — script.js
   Halaman tetap bisa dipakai walau JavaScript dimatikan. */
(function () {
  'use strict';

  /* Logo: bila assets/img/logo-umb.png belum ada, tampilkan lambang pengganti */
  document.querySelectorAll('.logo img').forEach(function (img) {
    function gagal() { img.parentNode.classList.add('no-img'); img.remove(); }
    if (img.complete && !img.naturalWidth) gagal(); else img.addEventListener('error', gagal);
  });

  /* Sidebar di HP */
  var side = document.getElementById('sidebar');
  var scrim = document.getElementById('side-scrim');
  var menuBtn = document.getElementById('menu-btn');
  function tutupSide() {
    if (!side) return;
    side.classList.remove('open'); scrim.classList.remove('show');
    menuBtn.setAttribute('aria-expanded', 'false');
  }
  if (side && menuBtn) {
    menuBtn.addEventListener('click', function () {
      side.classList.add('open'); scrim.classList.add('show');
      menuBtn.setAttribute('aria-expanded', 'true');
      var a = side.querySelector('a'); if (a) a.focus();
    });
    scrim.addEventListener('click', tutupSide);
  }

  /* Menu akun */
  var userBtn = document.getElementById('user-btn');
  var userMenu = document.getElementById('user-menu');
  function tutupUser() { if (userMenu) { userMenu.hidden = true; userBtn.setAttribute('aria-expanded', 'false'); } }
  if (userBtn && userMenu) {
    userBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var buka = userMenu.hidden;
      userMenu.hidden = !buka;
      userBtn.setAttribute('aria-expanded', String(buka));
    });
    document.addEventListener('click', function (e) { if (!userMenu.contains(e.target)) tutupUser(); });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (userMenu && !userMenu.hidden) { tutupUser(); userBtn.focus(); }
    else if (side && side.classList.contains('open')) { tutupSide(); menuBtn.focus(); }
  });

  /* Menu landing page di HP */
  var lpBtn = document.getElementById('lp-menu-btn');
  var lpLinks = document.getElementById('lp-links');
  if (lpBtn && lpLinks) {
    lpBtn.addEventListener('click', function () {
      var buka = lpLinks.hidden;
      lpLinks.hidden = !buka;
      lpBtn.setAttribute('aria-expanded', String(buka));
    });
  }

  /* Tampilkan / sembunyikan password */
  document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
    var input = document.getElementById(btn.getAttribute('data-toggle-pw'));
    btn.addEventListener('click', function () {
      var tampil = input.type === 'password';
      input.type = tampil ? 'text' : 'password';
      btn.setAttribute('aria-pressed', String(tampil));
      btn.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
      input.focus();
    });
  });

  /* Konfirmasi sebelum aksi penting */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var pesan = f.getAttribute && f.getAttribute('data-konfirmasi');
    if (pesan && !window.confirm(pesan)) e.preventDefault();
  });

  /* Tab */
  document.querySelectorAll('[role="tablist"]').forEach(function (list) {
    var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
    function pilih(tab) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
    }
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { pilih(t); history.replaceState(null, '', '#' + t.id.replace('tab-', '')); });
      t.addEventListener('keydown', function (e) {
        var n = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (!n) return;
        var next = tabs[(i + n + tabs.length) % tabs.length];
        pilih(next); next.focus();
      });
    });
    var awal = location.hash && document.getElementById('tab-' + location.hash.slice(1));
    if (awal && tabs.indexOf(awal) > -1) pilih(awal);
  });

  /* Kirim form filter otomatis saat pilihan berubah */
  document.querySelectorAll('form[data-auto] select').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });
})();
