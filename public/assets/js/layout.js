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
 * Dropdown aksi (.row-actions "⋮") di dalam tabel terpotong oleh overflow-x:auto
 * bawaan .table-responsive (menu yang keluar dari batas bawah container ikut ter-clip).
 * Solusi lama -- membuat container overflow:visible selagi menu terbuka -- membuat
 * tabel lebar (mis. PO, 15 kolom) meluber keluar kartu sehingga seluruh halaman melebar
 * dan layout (sidebar/topbar) bergeser. Solusi sekarang: menu memakai Popper
 * strategy 'fixed' sehingga posisinya relatif ke viewport dan tidak pernah ter-clip,
 * tanpa menyentuh overflow container. Berlaku ke SEMUA tabel di seluruh modul tanpa
 * ubah view.
 *
 * Instance harus sudah ada SEBELUM klik: handler data-api Bootstrap bersifat delegated
 * (didaftarkan di fase capture) sehingga jalan lebih dulu dari listener click kita dan
 * membuat instance tanpa config. Maka instance dibuat saat halaman siap, dan sebagai
 * cadangan (baris yang muncul belakangan) saat pointer/fokus/keyboard menyentuh tombol.
 */
(function () {
    function fixedPopper(defaultConfig) {
        return Object.assign({}, defaultConfig, { strategy: 'fixed' });
    }

    function prepare(toggle) {
        if (!toggle || !window.bootstrap || !toggle.closest('.table-responsive, .rt-card')) {
            return;
        }
        window.bootstrap.Dropdown.getOrCreateInstance(toggle, { popperConfig: fixedPopper });
    }

    function prepareFromEvent(e) {
        var el = e.target;
        prepare(el && el.closest ? el.closest('[data-bs-toggle="dropdown"]') : null);
    }

    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'), prepare);
    });
    ['pointerdown', 'mouseover', 'focusin', 'keydown', 'touchstart'].forEach(function (type) {
        document.addEventListener(type, prepareFromEvent, true);
    });
})();

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
