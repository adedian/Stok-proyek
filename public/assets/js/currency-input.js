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
        // Titik pertama yang ditemukan dianggap batas desimal; titik lain (kalau
        // ada, dari input yang tidak rapi) ikut dibuang bersama karakter non-digit.
        var dotIndex = raw.indexOf('.');
        var intPart = dotIndex === -1 ? raw : raw.slice(0, dotIndex);
        var decPart = dotIndex === -1 ? '' : raw.slice(dotIndex + 1);

        intPart = intPart.replace(/[^\d]/g, '');
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

        if (dotIndex === -1) {
            return intPart;
        }
        decPart = decPart.replace(/[^\d]/g, '').slice(0, 2);
        return intPart + '.' + decPart;
    }

    document.addEventListener('input', function (e) {
        if (!e.target.matches('.currency-input')) {
            return;
        }
        var input = e.target;
        input.value = formatCurrencyValue(input.value);
    });
})();
