/**
 * Draft otomatis form "Tambah" (semua menu).
 *
 *  - Form tambah = form POST di luar modal yang action-nya  action=store*  (mis. store, storeFromOrder).
 *    Form edit (action=update) dan form lain TIDAK ikut. Form boleh dikecualikan dengan atribut data-no-draft.
 *  - Isian disimpan otomatis ke localStorage (per akun + per form) tiap berubah. Kalau halaman ter-refresh,
 *    tab tertutup, browser/sesi mati mendadak, atau error validasi mengembalikan user ke form, isian
 *    DIPULIHKAN saat form dibuka lagi (ada banner + tombol "Hapus draft").
 *  - Pindah menu (klik tautan) saat form sudah diisi -> muncul pilihan "Lanjutkan Mengisi" / "Tinggalkan Form".
 *    Tinggalkan Form = draft dibuang. Menutup tab/refresh/back TIDAK membuang draft.
 *  - Draft dibuang otomatis setelah simpan berhasil (flash sukses), setelah logout, atau lewat 7 hari.
 *
 * Yang TIDAK disimpan: password, file upload (dan field tersembunyi/readonly/disabled). Baris dinamis dipulihkan
 * lewat tombol tambah-baris [data-inline-add]; opsi dropdown yang tak ada lagi (mis. hasil Tambah Cepat) dibuat
 * ulang dari teks yang tersimpan. Pemulihan bersifat best-effort: dilakukan beberapa tahap karena sebagian form
 * mengisi ulang field lewat AJAX setelah dropdown berubah.
 */
(function () {
    'use strict';

    var PREFIX = 'hxs_draft:';
    var PENDING = 'hxs_draft_pending';
    var TTL = 7 * 24 * 3600 * 1000;
    var MAX_BYTES = 500000;
    var uid = window.HXS_UID || 0;

    var ls, ss;
    try { ls = window.localStorage; ls.getItem(PREFIX); } catch (e) { ls = null; }
    try { ss = window.sessionStorage; ss.getItem(PENDING); } catch (e) { ss = null; }

    // Dipakai handler logout (header.php): bersihkan semua draft di perangkat ini.
    window.HXS_clearDrafts = function () {
        if (!ls) { return; }
        Object.keys(ls).forEach(function (k) { if (k.indexOf(PREFIX) === 0) { ls.removeItem(k); } });
    };

    if (!ls || !uid) { return; }

    function readDraft(key) {
        try { return JSON.parse(ls.getItem(key) || 'null'); } catch (e) { return null; }
    }

    // Buang draft kedaluwarsa.
    Object.keys(ls).forEach(function (k) {
        if (k.indexOf(PREFIX) !== 0) { return; }
        var d = readDraft(k);
        if (!d || !d.t || Date.now() - d.t > TTL) { ls.removeItem(k); }
    });

    function isStoreAction(a) {
        return /[?&]action=store[A-Za-z]*(?:&|#|$)/.test(a) || /\/store[A-Za-z]*(?:[?#\/]|$)/.test(a);
    }

    function findForm() {
        var list = document.querySelectorAll('form');
        for (var i = 0; i < list.length; i++) {
            var f = list[i];
            if (f.closest('.modal') || f.hasAttribute('data-no-draft')) { continue; }
            if ((f.getAttribute('method') || 'get').toLowerCase() !== 'post') { continue; }
            if (isStoreAction(f.getAttribute('action') || '')) { return f; }
        }
        return null;
    }

    // Kunci draft: akun + modul + aksi simpan + parameter halaman (mis. ?order_id=3). Tidak bergantung
    // pada URL cantik vs ?module=&action=, supaya redirect balik dari error validasi menemukan draft yang sama.
    function draftKey(form) {
        var a = form.getAttribute('action') || '';
        var mod = (/[?&]module=([^&#]+)/.exec(a) || [])[1];
        var act = (/[?&]action=([^&#]+)/.exec(a) || [])[1] || '';
        var extra = [];
        if (!mod) {
            var seg = location.pathname.split('/').filter(Boolean);
            mod = seg[seg.length - 2] || seg[seg.length - 1] || 'page';
        }
        new URLSearchParams(location.search).forEach(function (v, k) {
            if (k !== 'module' && k !== 'action') { extra.push(k + '=' + v); }
        });
        extra.sort();
        var m = /\/(?:create\w*|edit)\/(\d+)/.exec(location.pathname);
        if (m) { extra.push('id=' + m[1]); }
        return PREFIX + uid + ':' + mod + ':' + act + ':' + extra.join('&');
    }

    // ---------- Penyelesaian submit sebelumnya (jalan di semua halaman) ----------
    var form = null; // diisi di DOMContentLoaded
    function resolvePending() {
        if (!ss) { return; }
        var pend = ss.getItem(PENDING);
        if (!pend) { return; }
        ss.removeItem(PENDING);
        var flash = window.HXS_FLASH_TYPE || null;
        var sameForm = form && draftKey(form) === pend;
        // Sukses (flash sukses) -> draft dibuang. Gagal (flash error/warning, atau form yang sama tampil lagi) -> draft tetap.
        if (flash === 'success' || (!flash && !sameForm)) { ls.removeItem(pend); }
    }

    document.addEventListener('DOMContentLoaded', function () {
        form = findForm();
        resolvePending();
        if (form) { init(form); }
    });

    // ---------- Inti ----------
    function init(form) {
        var KEY = draftKey(form);
        var restoring = true;
        var dirty = false;
        var saveTimer = null;

        function controls() {
            var counts = {}, out = [];
            Array.prototype.forEach.call(form.elements, function (el) {
                var tag = el.tagName;
                if ((tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'TEXTAREA') || !el.name || el.closest('.modal')) { return; }
                var n = counts[el.name] = (counts[el.name] || 0) + 1;
                var type = (el.type || '').toLowerCase();
                var skip = ['password', 'file', 'hidden', 'submit', 'button', 'reset', 'image'].indexOf(type) >= 0
                    || el.name === 'csrf_token' || el.disabled || el.readOnly;
                out.push({ el: el, key: el.name + '#' + (n - 1), type: type, skip: skip });
            });
            return out;
        }
        function trCount(tb) {
            return Array.prototype.filter.call(tb.children, function (c) { return c.tagName === 'TR'; }).length;
        }
        function addButtons() {
            return Array.prototype.slice.call(form.querySelectorAll('[data-inline-add]'));
        }
        function capture() {
            var d = { v: {}, c: {}, rows: {} };
            controls().forEach(function (o) {
                if (o.skip) { return; }
                var el = o.el;
                if (o.type === 'checkbox' || o.type === 'radio') {
                    d.c[o.key] = !!el.checked;
                } else if (el.tagName === 'SELECT') {
                    if (el.multiple) {
                        d.v[o.key] = { m: Array.prototype.filter.call(el.options, function (op) { return op.selected; })
                            .map(function (op) { return { v: op.value, t: op.text }; }) };
                    } else {
                        var op = el.options[el.selectedIndex];
                        d.v[o.key] = { v: el.value, t: op ? op.text : '' };
                    }
                } else {
                    d.v[o.key] = el.value;
                }
            });
            addButtons().forEach(function (b) {
                var sel = b.getAttribute('data-inline-add');
                var tb = document.querySelector(sel);
                if (tb) { d.rows[sel] = trCount(tb); }
            });
            return d;
        }
        var initialSig = JSON.stringify(capture());

        function save() {
            if (restoring) { return; }
            var d = capture();
            if (JSON.stringify(d) === initialSig) { ls.removeItem(KEY); return; }
            d.t = Date.now();
            try {
                var s = JSON.stringify(d);
                if (s.length <= MAX_BYTES) { ls.setItem(KEY, s); }
            } catch (e) { /* kuota penuh / mode privat -- abaikan */ }
        }
        function saveSoon() {
            if (restoring) { return; }
            clearTimeout(saveTimer);
            saveTimer = setTimeout(save, 400);
        }

        // ---------- Pemulihan ----------
        function fire(el, type) { el.dispatchEvent(new Event(type, { bubbles: true })); }
        function ensureOption(sel, v, t) {
            var has = Array.prototype.some.call(sel.options, function (o) { return o.value === v; });
            if (!has && v !== '') {
                var o = document.createElement('option');
                o.value = v; o.textContent = t || v;
                sel.appendChild(o);
            }
        }
        function applySelect(el, s, force) {
            if (el.multiple) {
                var want = (s.m || []).map(function (x) { return x.v; });
                (s.m || []).forEach(function (x) { ensureOption(el, x.v, x.t); });
                var same = Array.prototype.every.call(el.options, function (o) { return o.selected === (want.indexOf(o.value) >= 0); });
                if (same && !force) { return; }
                Array.prototype.forEach.call(el.options, function (o) { o.selected = want.indexOf(o.value) >= 0; });
            } else {
                if (el.value === s.v && !force) { return; }
                ensureOption(el, s.v, s.t);
                el.value = s.v;
            }
            if (window.resyncSearchableSelect) { window.resyncSearchableSelect(el); }
            fire(el, 'change');
        }
        function applyText(el, v) {
            if (el.value === v) { return; }
            el.value = v;
            fire(el, 'input');
            fire(el, 'change');
        }
        function applyCheck(el, on) {
            if (el.checked === on) { return; }
            if (el.type === 'radio' && !on) { return; }
            el.checked = on;
            fire(el, 'change');
        }
        function pass(d, kinds) {
            controls().forEach(function (o) {
                if (o.skip) { return; }
                if (kinds.select && o.el.tagName === 'SELECT' && d.v[o.key]) { applySelect(o.el, d.v[o.key]); }
                if (kinds.check && (o.type === 'checkbox' || o.type === 'radio') && o.key in d.c) { applyCheck(o.el, d.c[o.key]); }
                if (kinds.text && o.el.tagName !== 'SELECT' && o.type !== 'checkbox' && o.type !== 'radio' && typeof d.v[o.key] === 'string') {
                    applyText(o.el, d.v[o.key]);
                }
            });
        }
        function restoreRows(d) {
            Object.keys(d.rows || {}).forEach(function (sel) {
                var tb = document.querySelector(sel);
                var btn = form.querySelector('[data-inline-add="' + sel + '"]');
                if (!tb || !btn) { return; }
                var guard = 0;
                while (trCount(tb) < d.rows[sel] && guard++ < 300) {
                    var before = trCount(tb);
                    btn.click();
                    if (trCount(tb) === before) { break; } // tombol disabled / form menolak -> berhenti
                }
            });
        }
        function banner(d) {
            var box = document.createElement('div');
            box.className = 'alert alert-info d-flex align-items-center gap-2 flex-wrap py-2 no-print';
            box.innerHTML = '<i class="bi bi-clock-history"></i><span class="flex-grow-1">Isian yang belum disimpan dipulihkan'
                + (d.t ? ' (disimpan ' + new Date(d.t).toLocaleString('id-ID') + ')' : '') + '.</span>'
                + '<button type="button" class="btn btn-sm btn-outline-secondary">Hapus draft &amp; kosongkan form</button>';
            box.querySelector('button').addEventListener('click', function () {
                ls.removeItem(KEY);
                dirty = false;
                location.replace(location.href);
            });
            form.parentNode.insertBefore(box, form);
        }

        var draft = readDraft(KEY);
        var y0 = window.scrollY;
        if (draft && draft.v) {
            setTimeout(function () {
                restoreRows(draft);
                // Beri waktu widget pencarian (Choices) memasang diri di baris baru.
                setTimeout(function () {
                    pass(draft, { select: true, check: true });
                    setTimeout(function () { pass(draft, { text: true }); }, 450);
                    setTimeout(function () { pass(draft, { select: true, check: true, text: true }); }, 1500);
                    setTimeout(function () {
                        window.scrollTo(0, y0);
                        restoring = false;
                        if (JSON.stringify(capture()) !== initialSig) { dirty = true; banner(draft); }
                        save();
                    }, 2000);
                }, 200);
            }, 0);
        } else {
            restoring = false;
        }

        // ---------- Pantau perubahan ----------
        function mine(e) { return form.contains(e.target); }
        ['input', 'change'].forEach(function (t) {
            form.addEventListener(t, function (e) {
                if (restoring) { return; }
                if (e.isTrusted) { dirty = true; }
                saveSoon();
            });
        });
        // Tambah/hapus baris, Tambah Cepat, dsb. mengubah isi tanpa event input.
        form.addEventListener('click', function () { saveSoon(); });
        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('.modal')) { saveSoon(); }
        });

        // ---------- Submit: tandai supaya halaman berikutnya tahu draft ini boleh dibuang bila sukses ----------
        function markPending() { if (ss) { ss.setItem(PENDING, KEY); } save(); }
        document.addEventListener('submit', function (e) {
            if (e.target === form && !e.defaultPrevented) { markPending(); }
        });
        var origSubmit = HTMLFormElement.prototype.submit;
        HTMLFormElement.prototype.submit = function () {
            if (this === form) { markPending(); }
            return origSubmit.apply(this, arguments);
        };

        // ---------- Pindah menu: tanya lanjut mengisi atau tidak ----------
        var leaving = false;
        document.addEventListener('click', function (e) {
            if (!dirty || leaving) { return; }
            if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) { return; }
            var a = e.target.closest && e.target.closest('a[href]');
            if (!a || a.id === 'topbarLogoutLink') { return; }
            var href = a.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) { return; }
            if (a.target === '_blank' || a.hasAttribute('download') || a.hasAttribute('data-bs-toggle') || a.hasAttribute('data-bs-dismiss')) { return; }
            var url;
            try { url = new URL(a.href, location.href); } catch (err) { return; }
            if (url.origin !== location.origin) { return; }
            if (url.pathname === location.pathname && url.search === location.search) { return; }

            e.preventDefault();
            e.stopImmediatePropagation();
            var go = function () {
                leaving = true;
                ls.removeItem(KEY);
                window.location.href = a.href;
            };
            var msg = 'Data yang sudah Anda isi belum disimpan. Lanjutkan mengisi form ini?';
            if (window.Swal) {
                window.Swal.fire({
                    icon: 'question',
                    title: 'Form belum disimpan',
                    text: msg,
                    showCancelButton: true,
                    confirmButtonText: 'Lanjutkan Mengisi',
                    cancelButtonText: 'Tinggalkan Form',
                    allowOutsideClick: false,
                    focusConfirm: true,
                }).then(function (r) {
                    if (r.dismiss === window.Swal.DismissReason.cancel) { go(); }
                });
            } else if (!window.confirm(msg + '\n\nOK = Lanjutkan Mengisi, Batal = Tinggalkan Form (draft dibuang).')) {
                go();
            }
        }, true);
    }
})();
