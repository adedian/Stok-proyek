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
        var body = table.tBodies[0];

        Array.prototype.forEach.call(body.rows, function (tr) {
            var cells = Array.prototype.slice.call(tr.cells);
            if (!cells.length) return;
            if (cells.some(function (c) { return c.hasAttribute('colspan'); })) return; // empty-state / TOTAL

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
            Array.prototype.forEach.call(body.rows, function (tr) {
                if (emptyCell) return;
                var c = Array.prototype.filter.call(tr.cells, function (x) { return x.hasAttribute('colspan'); })[0];
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
       --------------------------------------------------------------------- */
    var MQ_PRINT_PREVIEW = window.matchMedia('(max-width: 767.98px)');

    function scalePrintPreviews() {
        var mobile = MQ_PRINT_PREVIEW.matches;
        var pages = document.querySelectorAll('.main-content [class*="print-page"]');
        Array.prototype.forEach.call(pages, function (pg) {
            pg.style.zoom = '';
            pg.style.width = '';
            if (!mobile) return;

            // lebar blok natural di kontainer (mis. 343 di layar 375)
            var target = pg.offsetWidth;
            // paksa ke lebar A4 desain lalu ukur (mis. 794 = 210mm @96dpi)
            pg.style.width = '210mm';
            var natW = pg.offsetWidth;
            if (!natW || !target) { pg.style.width = ''; return; }

            var scale = target / natW;
            if (scale >= 0.999) { pg.style.width = ''; return; }
            pg.style.zoom = scale;
        });
    }

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

    /* Saat dialog cetak dibuka dari HP: kembalikan dokumen ke ukuran penuh
       (CSS @media print juga sudah memaksa zoom:1, ini jaring pengaman). */
    function unscaleForPrint() {
        var pages = document.querySelectorAll('.main-content [class*="print-page"]');
        Array.prototype.forEach.call(pages, function (pg) { pg.style.zoom = ''; pg.style.width = ''; });
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
