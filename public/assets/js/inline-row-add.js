/**
 * Tombol "Tambah Item/Barang/Baris/Biaya/Termin" dipindah ke BAWAH tiap baris, di sebelah
 * tombol hapus: setiap baris input mendapat tombol "+" (di samping tombol sampah). Satu modul
 * untuk semua form -- logika tambah baris TETAP milik masing-masing form: tombol "+" hanya
 * men-klik tombol Tambah asli (yang disembunyikan), jadi template baris, nomor indeks, AJAX,
 * dan perhitungan total tiap form tidak berubah.
 *
 * Cara pakai: beri tombol Tambah asli atribut  data-inline-add="#idTbodyBaris".
 * - Baris yang punya tombol hapus (button.btn-outline-danger berisi ikon .bi-trash / .bi-x-lg)
 *   otomatis diberi "+" -- termasuk baris yang ditambah belakangan (MutationObserver).
 * - Tombol Tambah asli disembunyikan selama ada baris; kalau tabel kosong (semua baris
 *   dihapus) tombol asli muncul lagi supaya tetap bisa menambah.
 * - Baris baru ditambahkan oleh form di AKHIR daftar (perilaku asli form); di HP layar
 *   di-scroll ke baris baru.
 * - Status disabled tombol asli (mis. Pengeluaran Barang sebelum barang dipilih) ikut disalin.
 */
(function () {
    'use strict';

    function deleteButtonOf(tr) {
        var icon = tr.querySelector('button.btn-outline-danger .bi-trash, button.btn-outline-danger .bi-x-lg');
        return icon ? icon.closest('button') : null;
    }

    function init(addBtn) {
        var tbody = document.querySelector(addBtn.getAttribute('data-inline-add'));
        if (!tbody) {
            return;
        }
        var label = (addBtn.textContent || '').replace(/\s+/g, ' ').trim() || 'Tambah baris';

        function rows() {
            return Array.prototype.filter.call(tbody.children, function (el) { return el.tagName === 'TR'; });
        }

        function decorate() {
            var withDelete = 0;
            rows().forEach(function (tr) {
                var del = deleteButtonOf(tr);
                if (!del) {
                    return;
                }
                withDelete++;
                if (tr.querySelector('.btn-inline-add')) {
                    return;
                }
                var group = document.createElement('div');
                group.className = 'row-action-group';
                del.parentNode.insertBefore(group, del);
                group.appendChild(del);

                var plus = document.createElement('button');
                plus.type = 'button';
                plus.className = 'btn btn-sm btn-outline-primary btn-inline-add';
                plus.title = label;
                plus.setAttribute('aria-label', label);
                plus.innerHTML = '<i class="bi bi-plus-lg"></i>';
                plus.disabled = addBtn.disabled;
                group.appendChild(plus);
            });
            // Ada baris ber-tombol-hapus -> tombol Tambah asli disembunyikan; kalau tidak ada, tampilkan.
            addBtn.classList.toggle('d-none', withDelete > 0);
        }

        function syncDisabled() {
            tbody.querySelectorAll('.btn-inline-add').forEach(function (b) { b.disabled = addBtn.disabled; });
        }

        tbody.addEventListener('click', function (e) {
            var plus = e.target.closest('.btn-inline-add');
            if (!plus || !tbody.contains(plus) || plus.disabled) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            var before = rows().length;
            addBtn.click();
            // Di HP: scroll ke baris baru (form bisa menambah baris secara async lewat AJAX).
            if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) {
                var started = Date.now();
                var timer = setInterval(function () {
                    var now = rows();
                    if (now.length > before) {
                        clearInterval(timer);
                        now[now.length - 1].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else if (Date.now() - started > 3000) {
                        clearInterval(timer);
                    }
                }, 80);
            }
        });

        new MutationObserver(decorate).observe(tbody, { childList: true });
        new MutationObserver(function () { decorate(); syncDisabled(); }).observe(addBtn, { attributes: true, attributeFilter: ['disabled'] });
        decorate();
    }

    function boot() {
        document.querySelectorAll('[data-inline-add]').forEach(init);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
