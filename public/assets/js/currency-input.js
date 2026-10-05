/**
 * Format-as-you-type untuk input harga/nominal: format nominal baru --
 * KOMA sebagai pemisah ribuan, TITIK sebagai pemisah desimal, maksimal 2 digit
 * desimal (mis. "15,000.73"). Event-delegated di document supaya otomatis jalan
 * untuk input .currency-input yang ditambah dinamis (mis. baris item PO baru
 * lewat "+ Tambah Barang").
 * Nilai yang disubmit ke server tetap string berformat ini -- PHP-side
 * membersihkannya lewat parseCurrencyInput() (app/helpers/functions.php)
 * sebelum disimpan ke database sebagai angka murni. Aturan parsing di kedua sisi
 * (JS di sini, PHP di parseCurrencyInput()) HARUS selalu sinkron: koma = ribuan,
 * titik = desimal -- jangan ubah salah satu tanpa mengubah yang lain.
 */
(function () {
    function formatCurrencyValue(raw, maxDecimals) {
        if (raw === '') {
            return '';
        }
        // Tanda minus di AWAL dipertahankan (nominal boleh negatif, mis. koreksi
        // Kas "-5,000.00"). Minus di tengah/lebih dari satu diabaikan.
        var negative = raw.charAt(0) === '-';
        var sign = negative ? '-' : '';

        // Titik pertama yang ditemukan dianggap batas desimal; titik lain (kalau
        // ada, dari input yang tidak rapi) ikut dibuang bersama karakter non-digit.
        var dotIndex = raw.indexOf('.');
        var intPart = dotIndex === -1 ? raw : raw.slice(0, dotIndex);
        var decPart = dotIndex === -1 ? '' : raw.slice(dotIndex + 1);

        intPart = intPart.replace(/[^\d]/g, '');
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        // "-" sendiri (user baru mengetik tanda minus) -> biarkan apa adanya.
        if (intPart === '' && decPart === '') {
            return sign;
        }

        if (dotIndex === -1) {
            return sign + intPart;
        }
        decPart = decPart.replace(/[^\d]/g, '').slice(0, maxDecimals);
        return sign + intPart + '.' + decPart;
    }

    // Format INDONESIA untuk field Kurs (data-format="id"): TITIK = ribuan, KOMA = desimal
    // ("16500" -> "16.500", "12,75" tetap "12,75"). Titik yang diketik user dianggap pemisah
    // ribuan (dibuang lalu dipasang ulang otomatis). HARUS sinkron dengan parseKursInput()
    // di app/helpers/functions.php.
    function formatIdValue(raw, maxDecimals) {
        var commaIndex = raw.indexOf(',');
        var intPart = commaIndex === -1 ? raw : raw.slice(0, commaIndex);
        var decPart = commaIndex === -1 ? '' : raw.slice(commaIndex + 1);
        intPart = intPart.replace(/[^\d]/g, '');
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        if (commaIndex === -1) {
            return intPart;
        }
        decPart = decPart.replace(/[^\d]/g, '').slice(0, maxDecimals);
        return intPart + ',' + decPart;
    }

    document.addEventListener('input', function (e) {
        if (!e.target.matches('.currency-input')) {
            return;
        }
        var input = e.target;
        if (input.getAttribute('data-format') === 'id') {
            var maxDecId = parseInt(input.getAttribute('data-decimals'), 10);
            input.value = formatIdValue(input.value, isNaN(maxDecId) ? 6 : maxDecId);
            return;
        }
        // Default 2 desimal (nominal). Field khusus (mis. Kurs) boleh menaikkan lewat data-decimals.
        var maxDec = parseInt(input.getAttribute('data-decimals'), 10);
        input.value = formatCurrencyValue(input.value, isNaN(maxDec) ? 2 : maxDec);
    });
})();
