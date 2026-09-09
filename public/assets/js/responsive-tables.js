/**
 * responsive-tables.js
 * -----------------------------------------------------------------------------
 * Di layar mobile (<576px), tabel DAFTAR (read-only) yang terlalu lebar untuk
 * viewport diubah jadi daftar KARTU -- supaya tidak perlu geser horizontal
 * terus-menerus untuk membaca kolom penting.
 *
 * Aman & reversible:
 *  - Hanya tabel di dalam .main-content .table-responsive.
 *  - Dilewati bila tabel punya [data-no-cards] ATAU <tbody> berisi field entri
 *    (<input> non-checkbox / <select> / <textarea>) -> itu tabel input, bukan daftar.
 *  - Dilewati bila tabel masih muat layar (tidak meluber).
 *  - Sel interaktif (checkbox pilih-baris & sel "Aksi") DIPINDAH (bukan diklon)
 *    ke kartu, dan DIKEMBALIKAN persis saat kembali ke desktop -> event listener,
 *    Bootstrap dropdown, dan form CSRF tetap berfungsi, tidak pernah terduplikasi.
 *  - Tidak mengubah satu pun file view. Idempoten.
 */
(function () {
    'use strict';

    var MQ = window.matchMedia('(max-width: 767.98px)');
    var entries = [];

    function norm(el) {
        return (el.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function isEditableTable(table) {
        return !!table.querySelector(
            'tbody input:not([type=checkbox]):not([type=hidden]), tbody select, tbody textarea'
        );
    }

    function classifyColumns(headCells) {
        return headCells.map(function (th) {
            var label = norm(th).toLowerCase();
            if (label === '') {
                return th.querySelector('input[type=checkbox]') ? 'select' : 'control';
            }
            if (label === 'aksi' || label === 'action' || label === 'opsi') return 'actions';
            return 'data';
        });
    }

    function buildEntry(wrapper) {
        var table = wrapper.querySelector('table');
        if (!table || table.hasAttribute('data-no-cards')) return null;
        if (table.closest('[class*="print-page"]')) return null; // pratinjau cetak: jangan diubah
        if (!table.tHead || !table.tBodies.length) return null;
        if (isEditableTable(table)) return null;

        var headRow = table.tHead.rows[table.tHead.rows.length - 1];
        if (!headRow) return null;
        var headCells = Array.prototype.slice.call(headRow.cells);
        if (headCells.length < 4) return null; // tabel sempit -> biarkan jadi tabel

        // Jumlah kolom "data" (tanpa kolom kontrol: checkbox / aksi)
        var dataCols = headCells.filter(function (th) {
            var l = norm(th).toLowerCase();
            return l !== '' && l !== 'aksi' && l !== 'action' && l !== 'opsi';
        }).length;

        var kinds = classifyColumns(headCells);
        var labels = headCells.map(norm);

        var cards = document.createElement('div');
        cards.className = 'rt-cards';
        cards.hidden = true;

        var slots = []; // { td, container, nodes }
        var count = 0;

        // Semua <tbody> -- tabel daftar bisa punya >1 tbody (mis. User Management
        // dikelompokkan per role). Dulu hanya tBodies[0] yang diproses -> di HP
        // hanya grup pertama yang muncul.
        var allRows = [];
        Array.prototype.forEach.call(table.tBodies, function (tb) {
            Array.prototype.forEach.call(tb.rows, function (tr) { allRows.push(tr); });
        });

        allRows.forEach(function (tr) {
            var cells = Array.prototype.slice.call(tr.cells);
            if (!cells.length) return;
            if (cells.some(function (c) { return c.hasAttribute('colspan'); })) {
                // Baris judul grup (<th colspan> -- mis. nama role di User
                // Management) -> jadikan sub-judul daftar kartu. Baris kosong /
                // empty-state (<td colspan>) tetap dilewati (ditangani di bawah).
                var span = cells[0];
                if (span.tagName === 'TH' && norm(span)) {
                    var gh = document.createElement('div');
                    gh.className = 'rt-card-group';
                    gh.innerHTML = span.innerHTML;
                    cards.appendChild(gh);
                }
                return;
            }

            var card = document.createElement('div');
            card.className = 'rt-card';
            var selectContainer = null;
            var actionsContainer = null;
            var titleSet = false;

            cells.forEach(function (td, i) {
                var kind = kinds[i] || 'data';
                var hasCheckbox = !!td.querySelector('input[type=checkbox]');

                if (kind === 'select' || (kind === 'control' && hasCheckbox)) {
                    selectContainer = document.createElement('label');
                    selectContainer.className = 'rt-card-select';
                    var cap = document.createElement('span');
                    cap.textContent = 'Pilih baris';
                    selectContainer.appendChild(cap);
                    slots.push({ td: td, container: selectContainer, nodes: Array.prototype.slice.call(td.childNodes) });
                    return;
                }

                if (kind === 'actions' || (kind === 'control' && !hasCheckbox && td.children.length)) {
                    actionsContainer = document.createElement('div');
                    actionsContainer.className = 'rt-card-actions';
                    slots.push({ td: td, container: actionsContainer, nodes: Array.prototype.slice.call(td.childNodes) });
                    return;
                }

                if (kind === 'control') return; // kontrol kosong tanpa isi -> abaikan

                var val = norm(td);
                var lab = (labels[i] || '').toLowerCase().replace(/[^a-z]/g, '');

                // Kolom nomor urut ("No" / "#") -> tidak berguna sebagai baris kartu
                if ((lab === 'no' || lab === '' || lab === 'nourut') && /^\d{1,4}$/.test(val)) return;

                if (!titleSet && val && val !== '-') {
                    var title = document.createElement('div');
                    title.className = 'rt-card-title';
                    title.innerHTML = td.innerHTML;
                    card.appendChild(title);
                    titleSet = true;
                    return;
                }
                if (!val || val === '-') return;

                var row = document.createElement('div');
                row.className = 'rt-card-row';
                var l = document.createElement('span');
                l.className = 'rt-label';
                l.textContent = labels[i] || '';
                var v = document.createElement('span');
                v.className = 'rt-value';
                v.innerHTML = td.innerHTML;
                row.appendChild(l);
                row.appendChild(v);
                card.appendChild(row);
            });

            if (selectContainer) card.insertBefore(selectContainer, card.firstChild);
            if (actionsContainer) card.appendChild(actionsContainer);
            cards.appendChild(card);
            count++;
        });

        // Tidak ada baris data. Kalau ada baris "kosong" (colspan, mis. empty-state
        // atau "Tidak ada transaksi ...") DAN tabelnya termasuk lebar, tetap
        // sembunyikan tabel di HP lalu tampilkan pesannya sebagai satu kartu --
        // supaya header tabel yang meluber tidak terpotong di layar sempit.
        if (!count) {
            if (dataCols < 4 && headCells.length < 5) return null;
            var emptyCell = null;
            allRows.forEach(function (tr) {
                if (emptyCell) return;
                var c = Array.prototype.filter.call(tr.cells, function (x) { return x.hasAttribute('colspan') && x.tagName === 'TD'; })[0];
                if (c) emptyCell = c;
            });
            if (!emptyCell) return null;
            var ecard = document.createElement('div');
            ecard.className = 'rt-card rt-card--empty';
            ecard.innerHTML = emptyCell.innerHTML;
            cards.appendChild(ecard);
            wrapper.parentNode.insertBefore(cards, wrapper.nextSibling);
            return {
                wrapper: wrapper, table: table, cards: cards, slots: [],
                mode: 'table', forceCards: true
            };
        }

        wrapper.parentNode.insertBefore(cards, wrapper.nextSibling);
        // >=4 kolom data = sempit di HP -> selalu kartu. <4 kolom -> kartu hanya
        // kalau tabelnya benar-benar meluber (diukur di apply()).
        return {
            wrapper: wrapper, table: table, cards: cards, slots: slots,
            mode: 'table', forceCards: dataCols >= 4
        };
    }

    function setMode(entry, mode) {
        if (entry.mode === mode) return;
        entry.slots.forEach(function (s) {
            var dest = mode === 'cards' ? s.container : s.td;
            s.nodes.forEach(function (n) { dest.appendChild(n); });
        });
        entry.wrapper.classList.toggle('rt-hide-table', mode === 'cards');
        entry.cards.hidden = mode !== 'cards';
        entry.mode = mode;
    }

    function apply() {
        var mobile = MQ.matches;
        entries.forEach(function (entry) {
            var toCards = mobile && (
                entry.forceCards ||
                entry.table.scrollWidth > (entry.wrapper.clientWidth || entry.wrapper.offsetWidth) + 4
            );
            setMode(entry, toCards ? 'cards' : 'table');
        });
    }

    /* Bungkus tabel "telanjang" (tanpa .table-responsive) di dalam .main-content
       supaya (a) tidak terpotong oleh overflow-x:hidden pada body di mobile,
       (b) ikut diproses jadi kartu. Template cetak (di luar .main-content) tidak
       tersentuh. */
    function wrapBareTables() {
        var tables = document.querySelectorAll('.main-content table');
        Array.prototype.forEach.call(tables, function (t) {
            if (t.closest('.table-responsive') || t.closest('.rt-cards')) return;
            // JANGAN sentuh tabel di dalam pratinjau dokumen cetak
            // (purchase_order/print.php dkk -- wrapper *-print-page). Layout cetak
            // resmi harus utuh, tidak dibungkus / tidak diubah jadi kartu.
            if (t.closest('[class*="print-page"]')) return;
            var wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        });
    }

    /* ---------------------------------------------------------------------
       Pratinjau dokumen cetak (*-print-page) di layar HP: render di lebar
       A4 aslinya lalu perkecil UTUH pakai CSS zoom, supaya identik dengan
       hasil cetak. Tidak mereflow apa pun. Di-reset saat >=768px & saat
       benar-benar mencetak.

       Aplikasi mematikan zoom di seluruh halaman (viewport user-scalable=no),
       JADI pratinjau cetak diberi kontrol zoom SENDIRI: toolbar - / % / +
       plus double-tap. Saat 100% ("pas layar") perilaku persis seperti dulu;
       saat di-zoom, halaman dibungkus .print-zoom-viewport yang bisa digeser
       (pan) dua arah.
       --------------------------------------------------------------------- */
    var MQ_PRINT_PREVIEW = window.matchMedia('(max-width: 767.98px)');
    var printUserZoom = 1;
    var PRINT_ZOOM_MIN = 1;
    var PRINT_ZOOM_MAX = 4;
    var PRINT_ZOOM_STEP = 1.25;
    var printZoomBar = null;

    function printPreviewPages() {
        return document.querySelectorAll('.main-content [class*="print-page"]');
    }

    // Bungkus SEMUA halaman pratinjau dalam SATU viewport pannable (mereka
    // tetap adjacent sibling di dalamnya -> selector "A + A" di CSS cetak
    // tiap modul tetap jalan). Idempoten.
    function ensurePrintZoomViewport(pages) {
        if (!pages.length) return null;
        var existing = pages[0].parentNode;
        if (existing && existing.classList.contains('print-zoom-viewport')) return existing;
        var vp = document.createElement('div');
        vp.className = 'print-zoom-viewport';
        pages[0].parentNode.insertBefore(vp, pages[0]);
        Array.prototype.forEach.call(pages, function (pg) { vp.appendChild(pg); });
        return vp;
    }

    function currentPrintViewport() {
        return document.querySelector('.main-content .print-zoom-viewport');
    }

    function removePrintZoomViewport() {
        var vp = currentPrintViewport();
        if (!vp) return;
        var parent = vp.parentNode;
        while (vp.firstChild) parent.insertBefore(vp.firstChild, vp);
        parent.removeChild(vp);
    }

    function scalePrintPreviews() {
        var mobile = MQ_PRINT_PREVIEW.matches;
        var pages = printPreviewPages();

        if (!mobile || !pages.length) {
            Array.prototype.forEach.call(pages, function (pg) {
                pg.style.zoom = '';
                pg.style.width = '';
            });
            if (!pages.length || !mobile) removePrintZoomViewport();
            updatePrintZoomBar(false);
            return;
        }

        var vp = ensurePrintZoomViewport(pages);

        Array.prototype.forEach.call(pages, function (pg) {
            pg.style.zoom = '';
            pg.style.width = '';
            var target = pg.offsetWidth;              // lebar terbatas di kontainer
            pg.style.width = '210mm';                 // paksa ke lebar A4 lalu ukur
            var natW = pg.offsetWidth;
            if (!natW || !target) { pg.style.width = ''; return; }

            var fit = target / natW;
            pg.dataset.fitScale = fit;
            var z = fit * printUserZoom;
            if (printUserZoom === 1 && z >= 0.999) {
                pg.style.width = '';                  // muat pas -> biarkan natural
            } else {
                pg.style.zoom = z;
            }
        });

        if (vp) vp.classList.toggle('is-zoomed', printUserZoom > 1);
        updatePrintZoomBar(true);
    }

    function setPrintZoom(z, resetScroll) {
        printUserZoom = Math.min(PRINT_ZOOM_MAX, Math.max(PRINT_ZOOM_MIN, z));
        scalePrintPreviews();
        if (resetScroll) {
            var vp = currentPrintViewport();
            if (vp && vp.scrollTo) { vp.scrollTo(0, 0); }
        }
    }

    function buildPrintZoomBar() {
        if (printZoomBar) return printZoomBar;
        var bar = document.createElement('div');
        bar.className = 'print-zoom-bar no-print';
        bar.innerHTML =
            '<button type="button" data-act="out" aria-label="Perkecil">−</button>'
            + '<span class="print-zoom-val">100%</span>'
            + '<button type="button" data-act="in" aria-label="Perbesar">+</button>'
            + '<button type="button" data-act="fit" aria-label="Pas layar">'
            + '<i class="bi bi-arrows-angle-contract"></i></button>';
        bar.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('button') : null;
            if (!b) return;
            if (b.dataset.act === 'in') setPrintZoom(printUserZoom * PRINT_ZOOM_STEP, false);
            else if (b.dataset.act === 'out') setPrintZoom(printUserZoom / PRINT_ZOOM_STEP, printUserZoom / PRINT_ZOOM_STEP <= 1);
            else setPrintZoom(1, true);
        });
        document.body.appendChild(bar);
        printZoomBar = bar;
        return bar;
    }

    function updatePrintZoomBar(show) {
        if (!show) { if (printZoomBar) printZoomBar.hidden = true; return; }
        var bar = buildPrintZoomBar();
        bar.hidden = false;
        bar.querySelector('.print-zoom-val').textContent = Math.round(printUserZoom * 100) + '%';
        bar.querySelector('[data-act=out]').disabled = printUserZoom <= PRINT_ZOOM_MIN + 0.001;
        bar.querySelector('[data-act=in]').disabled = printUserZoom >= PRINT_ZOOM_MAX - 0.001;
    }

    // Double-tap di area pratinjau -> toggle pas-layar <-> 2.5x.
    (function () {
        var lastTap = 0;
        document.addEventListener('touchend', function (e) {
            var vp = e.target.closest ? e.target.closest('.print-zoom-viewport') : null;
            if (!vp) { lastTap = 0; return; }
            var now = Date.now();
            if (now - lastTap > 0 && now - lastTap < 300) {
                setPrintZoom(printUserZoom > 1 ? 1 : 2.5, printUserZoom > 1);
                e.preventDefault();
                lastTap = 0;
            } else {
                lastTap = now;
            }
        }, { passive: false });
    })();

    /* ---------------------------------------------------------------------
       Tabel entri ber-class .entry-cards: isi atribut data-label tiap <td>
       di <tbody> dari teks header <thead> yang bersesuaian, supaya CSS
       (responsive.css bagian 7b) bisa menampilkan label kolom saat baris
       ditumpuk jadi kartu di HP. Idempoten -- td yang sudah punya data-label
       (mis. dari view) tidak disentuh. MutationObserver menjaga baris yang
       ditambah lewat JS/AJAX ikut ter-label.
       --------------------------------------------------------------------- */
    function labelEntryRows(table) {
        var headRow = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
        if (!headRow) return;
        var heads = Array.prototype.map.call(headRow.cells, norm);
        Array.prototype.forEach.call(table.tBodies, function (tb) {
            Array.prototype.forEach.call(tb.rows, function (tr) {
                var i = 0;
                Array.prototype.forEach.call(tr.cells, function (td) {
                    var span = td.colSpan || 1;
                    if (span > 1) { i += span; return; } // baris total/kosong -> lewati
                    if (!td.hasAttribute('data-label')) {
                        td.setAttribute('data-label', heads[i] || '');
                    }
                    i += 1;
                });
            });
        });
    }

    function initEntryCards() {
        var tables = document.querySelectorAll('.main-content table.entry-cards');
        Array.prototype.forEach.call(tables, function (table) {
            labelEntryRows(table);
            if (!window.MutationObserver || !table.tBodies.length) return;
            var mo = new MutationObserver(function () { labelEntryRows(table); });
            Array.prototype.forEach.call(table.tBodies, function (tb) {
                mo.observe(tb, { childList: true });
            });
        });
    }

    function init() {
        wrapBareTables();
        initEntryCards();
        var wrappers = document.querySelectorAll('.main-content .table-responsive');
        Array.prototype.forEach.call(wrappers, function (w) {
            try {
                var e = buildEntry(w);
                if (e) entries.push(e);
            } catch (err) {
                if (window.console) console.warn('responsive-tables: lewati tabel', err);
            }
        });
        // Ukur beberapa kali: saat DCL layout mobile kadang belum stabil
        // (viewport emulator / font belum ter-load) -> clientWidth bisa keliru.
        apply();
        scalePrintPreviews();
        if (window.requestAnimationFrame) requestAnimationFrame(function () { apply(); scalePrintPreviews(); });
        setTimeout(function () { apply(); scalePrintPreviews(); }, 120);
        setTimeout(function () { apply(); scalePrintPreviews(); }, 400);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener('load', function () { apply(); scalePrintPreviews(); });

    if (MQ.addEventListener) {
        MQ.addEventListener('change', apply);
    } else if (MQ.addListener) {
        MQ.addListener(apply);
    }
    if (MQ_PRINT_PREVIEW.addEventListener) {
        MQ_PRINT_PREVIEW.addEventListener('change', scalePrintPreviews);
    } else if (MQ_PRINT_PREVIEW.addListener) {
        MQ_PRINT_PREVIEW.addListener(scalePrintPreviews);
    }
    window.addEventListener('orientationchange', function () {
        setTimeout(function () { apply(); scalePrintPreviews(); }, 150);
    });
    window.addEventListener('resize', (function () {
        var t;
        return function () {
            clearTimeout(t);
            t = setTimeout(function () { apply(); scalePrintPreviews(); }, 200);
        };
    })());

    /* Saat dialog cetak dibuka dari HP: kembalikan dokumen ke ukuran penuh &
       lepas mode zoom (CSS @media print juga sudah menetralkan, ini jaring
       pengaman supaya hasil cetak tidak pernah terpotong / ter-skala). */
    function unscaleForPrint() {
        printUserZoom = 1;
        var vp = currentPrintViewport();
        if (vp) { vp.classList.remove('is-zoomed'); }
        Array.prototype.forEach.call(printPreviewPages(), function (pg) {
            pg.style.zoom = '';
            pg.style.width = '';
        });
        if (printZoomBar) { printZoomBar.hidden = true; }
    }
    window.addEventListener('beforeprint', unscaleForPrint);
    window.addEventListener('afterprint', scalePrintPreviews);
    if (window.matchMedia) {
        var mqp = window.matchMedia('print');
        var mqpHandler = function (e) { if (e.matches) unscaleForPrint(); else scalePrintPreviews(); };
        if (mqp.addEventListener) mqp.addEventListener('change', mqpHandler);
        else if (mqp.addListener) mqp.addListener(mqpHandler);
    }
})();
