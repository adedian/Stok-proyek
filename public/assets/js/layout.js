/**
 * Toggle sidebar: di desktop (>=992px) collapse jadi icon-only (state disimpan
 * localStorage supaya bertahan antar halaman), di mobile/tablet (<992px) buka
 * offcanvas drawer bawaan Bootstrap (.offcanvas-lg pada #appSidebar).
 */
(function () {
    var STORAGE_KEY = 'sidebarCollapsed';
    var DESKTOP_BREAKPOINT = 992;

    document.addEventListener('DOMContentLoaded', function () {
        var toggleBtn = document.getElementById('btnSidebarToggle');
        var sidebarEl = document.getElementById('appSidebar');
        if (!toggleBtn || !sidebarEl) {
            return;
        }

        toggleBtn.addEventListener('click', function () {
            if (window.innerWidth >= DESKTOP_BREAKPOINT) {
                var collapsed = document.body.classList.toggle('sidebar-collapsed');
                try {
                    localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
                } catch (e) {
                    // localStorage tidak tersedia (mode privat dsb) -- abaikan, cukup toggle visualnya
                }
            } else if (window.bootstrap) {
                window.bootstrap.Offcanvas.getOrCreateInstance(sidebarEl).toggle();
            }
        });
    });
})();

/**
 * Loading state tombol submit -- supaya user tidak mengira sistem "diam" saat
 * form sedang diproses. Hanya untuk form POST (form GET/filter tidak perlu).
 * Dicek e.defaultPrevented supaya tombol TIDAK terkunci loading kalau submit
 * dibatalkan (mis. user klik "Batal" di confirm() bawaan). Form yang pakai
 * pola confirmAction() (quick-add.js) sudah aman dengan sendirinya karena
 * form.submit() terprogram tidak memicu event 'submit' lagi.
 */
/**
 * Dropdown aksi (.row-actions "⋮") di dalam tabel sering terpotong/"tenggelam" di
 * bagian bawah kartu. Penyebabnya: .table-responsive Bootstrap men-set overflow-x:auto,
 * dan menurut spesifikasi CSS begitu salah satu sumbu overflow diset non-visible,
 * browser otomatis menghitung sumbu satunya (overflow-y) jadi auto juga -- efek
 * samping standar, bukan disengaja -- sehingga dropdown-menu yang keluar dari batas
 * bawah container ikut ter-clip. Solusinya: begitu dropdown di dalam tabel dibuka,
 * izinkan container itu overflow (visible) sementara, lalu kembalikan begitu ditutup.
 * Delegasi event di document supaya otomatis berlaku ke SEMUA tabel .row-actions
 * di seluruh modul tanpa perlu mengubah satupun file view.
 */
document.addEventListener('show.bs.dropdown', function (e) {
    var wrapper = e.target.closest('.table-responsive');
    if (wrapper) {
        wrapper.classList.add('dropdown-open-overflow');
    }
});
document.addEventListener('hide.bs.dropdown', function (e) {
    var wrapper = e.target.closest('.table-responsive');
    if (wrapper) {
        wrapper.classList.remove('dropdown-open-overflow');
    }
});

document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) {
        return;
    }
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }
    if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post' || form.hasAttribute('data-no-loading')) {
        return;
    }
    var btn = form.querySelector('button[type="submit"]');
    if (btn) {
        btn.classList.add('is-loading');
        btn.disabled = true;
    }
});
