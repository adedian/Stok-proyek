/**
 * Sinkronisasi checkbox "Select All" <-> checkbox baris individual, dua arah.
 *
 * ROOT CAUSE bug lama (purchase_order/list.php): listener Select All SELALU
 * memaksa semua baris ikut nilai Select All (itu benar), TAPI listener baris
 * individual hanya menangani 1 arah -- "kalau ada yang di-uncheck, Select All
 * ikut ter-uncheck" -- dan TIDAK PERNAH mengecek "kalau semua baris kebetulan
 * sudah tercentang manual satu-satu, Select All harus ikut tercentang". Akibatnya
 * state Select All bisa nyangkut tidak sinkron dengan kondisi baris yang sebenarnya.
 * Helper ini menghitung ulang status Select All (checked/unchecked/indeterminate)
 * dari kondisi baris yang SEBENARNYA setiap kali ada baris berubah, bukan cuma
 * menebak satu arah.
 *
 * Delegated di document supaya otomatis jalan untuk baris yang ditambah dinamis
 * (mis. hasil AJAX/pagination), tanpa perlu re-bind listener manual.
 *
 * @param {string} selectAllSelector  CSS selector checkbox "Select All" (harus unik di halaman)
 * @param {string} rowSelector        CSS selector checkbox baris individual
 * @param {function} [onChange]       dipanggil setiap kali selection berubah (opsional)
 */
(function () {
    function wireSelectAllCheckbox(selectAllSelector, rowSelector, onChange) {
        var selectAllEl = document.querySelector(selectAllSelector);
        if (!selectAllEl) {
            return;
        }

        function rows() {
            return Array.prototype.slice.call(document.querySelectorAll(rowSelector));
        }

        function syncSelectAllFromRows() {
            var all = rows();
            var checkedCount = all.filter(function (c) { return c.checked; }).length;
            selectAllEl.checked = all.length > 0 && checkedCount === all.length;
            selectAllEl.indeterminate = checkedCount > 0 && checkedCount < all.length;
        }

        // Select All berubah -> paksa semua baris ikut (satu-satunya tempat
        // yang boleh "memaksa" checked=true/false ke semua baris sekaligus).
        selectAllEl.addEventListener('change', function () {
            selectAllEl.indeterminate = false;
            rows().forEach(function (c) { c.checked = selectAllEl.checked; });
            if (onChange) onChange();
        });

        // Baris individual berubah (dicentang ATAU dibatalkan) -> hitung ulang
        // status Select All dari kondisi baris yang sebenarnya, dua arah.
        document.addEventListener('change', function (e) {
            if (!e.target.matches(rowSelector)) {
                return;
            }
            syncSelectAllFromRows();
            if (onChange) onChange();
        });

        // State awal saat halaman dimuat (mis. hasil validasi server / filter ulang).
        syncSelectAllFromRows();
    }

    window.wireSelectAllCheckbox = wireSelectAllCheckbox;
})();
