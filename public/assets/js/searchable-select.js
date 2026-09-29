/**
 * Searchable Select -- aktifkan search bar di SEMUA <select> di aplikasi
 * (lewat Choices.js, dimuat via CDN di footer.php) supaya dropdown panjang
 * (Supplier, Client, Barang, dst) gampang dicari, bukan cuma di-scroll.
 *
 * KENAPA PERLU LEBIH DARI SEKADAR `new Choices(select)`:
 * Choices.js MENGGANTI tampilan <select> asli dengan widget-nya sendiri, tapi
 * <select> aslinya tetap ada di DOM (disembunyikan, dipindah jadi anak wrapper
 * `.choices`) supaya submit form biasa tetap jalan. Masalahnya: banyak script
 * lain di aplikasi ini memanipulasi <select> secara LANGSUNG lewat DOM/JS
 * property (quick-add.js nambah <option> + set .value setelah "Tambah Cepat",
 * beberapa form reset .value pilihan terkait, stock_out/form.php membangun
 * ulang daftar <option> dinamis) -- perubahan itu TIDAK otomatis kelihatan di
 * widget Choices (dia punya salinan state sendiri), jadi harus di-resync.
 *
 * DUA GOTCHA Choices.js v11 yang bikin resync naif gagal (sudah diuji manual
 * satu-satu sebelum kode ini ditulis):
 *   1. `instance.clearStore()` ikut MENGOSONGKAN <option> di <select> ASLI-nya
 *      (bukan cuma state internal widget) -- JANGAN PERNAH dipakai untuk
 *      resync. Solusi yang aman: `instance.destroy()` (mengembalikan <select>
 *      ke kondisi native, tidak menyentuh isi <option>) lalu `new Choices()`
 *      lagi -- fungsi enhance() di sini melakukan itu.
 *   2. Choices membaca ATRIBUT HTML `selected` tiap <option> (bukan properti
 *      JS `.selected`) untuk menentukan pilihan awal saat dikonstruksi. Kode
 *      lain di app ini SELALU set lewat properti (`sel.value = ...`), yang
 *      mengubah `.selected` tapi TIDAK ikut menulis atributnya -- tanpa
 *      `syncSelectedAttribute()` di bawah, widget akan terus menampilkan
 *      placeholder walau <select> aslinya sudah benar.
 *
 * Resync SENGAJA sinkron (bukan di-batch lewat requestAnimationFrame) --
 * rAF ditangguhkan total oleh browser di tab yang tidak sedang aktif/visible,
 * jadi kalau dipakai, resync bisa tidak pernah kejalan sama sekali.
 *
 * Pemicu resync otomatis:
 *   1. MutationObserver -- <select> BARU (baris dinamis "+ Tambah Baris" dll)
 *      langsung di-enhance.
 *   2. Event 'reset' (bubbling, termasuk form.reset() programatik) -> semua
 *      <select> di form itu di-resync ke kondisi hasil reset.
 *   3. window.resyncSearchableSelect(select) -- dipanggil manual di
 *      pemanggil yang mengubah <option>/`.value` <select> lewat JS (quick-
 *      add.js, payment/form.php, stock_out/form.php). Aman dipanggil pada
 *      <select> apa pun, no-op kalau belum di-enhance.
 *
 * Opt-out: tambah atribut `data-no-search` pada <select> untuk membiarkannya
 * sebagai dropdown native biasa (tidak dipakai di mana pun saat ini, tersedia
 * untuk kasus mendatang).
 */
(function () {
    if (typeof window.Choices === 'undefined') {
        return; // CDN gagal dimuat -- <select> tetap berfungsi native, cuma tanpa search.
    }

    var registry = new WeakMap(); // <select> -> instance Choices

    function isEligible(select) {
        return select instanceof HTMLSelectElement
            && !select.closest('.choices') // sudah dibungkus Choices (termasuk yang sedang di-init)
            && !select.hasAttribute('data-no-search');
    }

    function syncSelectedAttribute(select) {
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.selected) {
                opt.setAttribute('selected', '');
            } else {
                opt.removeAttribute('selected');
            }
        });
    }

    function bindOverflowFix(select) {
        // Choices.js dropdown posisinya `position: absolute` relatif ke wrapper
        // `.choices` -- kalau baris ini ada di dalam .table-responsive (overflow-x
        // Bootstrap bawaan), dropdown yang melebar/flip ke atas bisa ke-clip.
        // Pola & class persis sama dengan solusi yang sudah ada untuk dropdown
        // aksi titik-tiga (lihat layout.js + tables.css .dropdown-open-overflow),
        // cuma event trigger-nya event kustom Choices, bukan show.bs.dropdown.
        // dataset flag: listener cuma dipasang SEKALI per elemen <select>, walau
        // enhance() dipanggil ulang (destroy+reinit) berkali-kali lewat resync.
        if (select.dataset.ssOverflowBound) {
            return;
        }
        select.dataset.ssOverflowBound = '1';
        select.addEventListener('showDropdown', function () {
            var wrapper = select.closest('.table-responsive');
            if (wrapper) {
                wrapper.classList.add('dropdown-open-overflow');
            }
        });
        select.addEventListener('hideDropdown', function () {
            var wrapper = select.closest('.table-responsive');
            if (wrapper) {
                wrapper.classList.remove('dropdown-open-overflow');
            }
        });
    }

    function enhance(select) {
        if (!isEligible(select)) {
            return;
        }
        syncSelectedAttribute(select);
        var isSmall = select.classList.contains('form-select-sm') || select.classList.contains('form-control-sm');
        var instance = new window.Choices(select, {
            searchEnabled: true,
            shouldSort: false, // urutan <option> dari server dipertahankan (jangan diacak alfabetis)
            itemSelectText: '',
            searchPlaceholderValue: 'Cari...',
            noResultsText: 'Tidak ditemukan',
            noChoicesText: 'Tidak ada pilihan',
            allowHTML: false,
            searchResultLimit: 50,
            renderChoiceLimit: -1,
            position: 'auto',
        });
        if (isSmall) {
            // Choices.js MEMINDAHKAN <select> asli jadi ANAK dari wrapper `.choices`
            // (bukan mempertahankannya sebagai saudara sebelum `.choices`), jadi
            // selector CSS `.form-select-sm + .choices` di searchable-select.css
            // TIDAK PERNAH cocok -- dropdown form-select-sm (baris tabel Kas/PO/dst)
            // selalu jatuh ke ukuran Choices NORMAL, jadi tidak sinkron dengan
            // input .form-control-sm di sebelahnya. Tandai wrapper-nya langsung di
            // sini supaya aturan `.choices.choices--small` (sudah ada di CSS) benar-
            // benar terpasang.
            instance.containerOuter.element.classList.add('choices--small');
        }
        registry.set(select, instance);
        bindOverflowFix(select);
    }

    /**
     * Bangun ulang widget Choices dari kondisi <select> NATIVE saat ini.
     * destroy() lalu enhance() lagi -- lihat catatan gotcha #1 di atas kenapa
     * BUKAN clearStore()+setChoices(). Sinkron, idempoten (aman dipanggil
     * berkali-kali beruntun, mis. quick-add.js DAN MutationObserver sama-sama
     * memicu untuk perubahan yang sama).
     */
    function resync(select) {
        var instance = registry.get(select);
        if (!instance) {
            return; // belum pernah di-enhance -- bukan tanggung jawab modul ini
        }
        try {
            instance.destroy();
            registry.delete(select);
            enhance(select);
        } catch (e) {
            /* select mungkin sudah lepas dari DOM (baris dihapus) -- abaikan */
        }
    }

    /** Dipanggil manual dari script lain setelah set `.value`/`<option>` lewat JS. */
    window.resyncSearchableSelect = function (select) {
        if (select) {
            resync(select);
        }
    };

    function enhanceWithin(root) {
        if (root instanceof HTMLSelectElement) {
            enhance(root);
        }
        if (root.querySelectorAll) {
            root.querySelectorAll('select').forEach(enhance);
        }
    }

    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            if (m.type !== 'childList') {
                return;
            }
            m.addedNodes.forEach(function (node) {
                if (node.nodeType === 1) {
                    enhanceWithin(node);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Reset native (tombol reset ATAU form.reset() programatik, mis. quick-add.js
    // setelah submit) -- <select> di dalam form itu balik ke default, sync widgetnya.
    document.addEventListener('reset', function (e) {
        if (!(e.target instanceof HTMLFormElement)) {
            return;
        }
        e.target.querySelectorAll('select').forEach(resync);
    }, true);

    function init() {
        enhanceWithin(document.body);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
