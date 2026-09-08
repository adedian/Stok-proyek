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
    function formatCurrencyValue(raw) {
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
        decPart = decPart.replace(/[^\d]/g, '').slice(0, 2);
        return sign + intPart + '.' + decPart;
    }

    document.addEventListener('input', function (e) {
        if (!e.target.matches('.currency-input')) {
            return;
        }
        var input = e.target;
        input.value = formatCurrencyValue(input.value);
    });
})();
