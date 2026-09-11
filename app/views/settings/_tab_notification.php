<?php
$notifItems = [
    'notify_selisih_barang'     => ['label' => 'Selisih Barang', 'desc' => 'Banner peringatan di dashboard saat ada penerimaan barang dengan selisih yang belum divalidasi.'],
    'notify_cash_validation'    => ['label' => 'Validasi Kas', 'desc' => 'Peringatan (lonceng + dashboard) saat ada transaksi Kas menunggu validasi -- hanya tampil ke pengguna yang berwenang memvalidasi divisi terkait.'],
    'notify_invoice_pending'    => ['label' => 'Invoice Belum Tertagih', 'desc' => 'Kartu jumlah Invoice Keluar yang belum ada Tanda Terima (belum tertagih) di dashboard.'],
    'notify_stok_minimum'       => ['label' => 'Stok Minimum', 'desc' => 'Banner peringatan saat ada barang dengan stok di bawah batas minimum.'],
    'notify_po_belum_diproses'  => ['label' => 'PO Belum Diproses', 'desc' => 'Banner peringatan saat ada Purchase Order yang masih menunggu approval.'],
];
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=saveNotifications">
            <?= csrfField() ?>
            <?php foreach ($notifItems as $key => $item): ?>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" name="<?= e($key) ?>" id="<?= e($key) ?>"
                           <?= ($notification[$key] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($key) ?>">
                        <strong><?= e($item['label']) ?></strong><br>
                        <span class="text-muted small"><?= e($item['desc']) ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary mt-2"><i class="bi bi-save"></i> Simpan Notifikasi</button>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="mb-1"><i class="bi bi-bell-fill"></i> Notifikasi Push di Perangkat Ini</h6>
        <p class="text-muted small mb-3">
            Munculkan notifikasi di HP/komputer ini walau aplikasinya sedang tertutup, untuk jenis
            notifikasi yang aktif di atas. Ini pengaturan PER PERANGKAT (bukan per akun) -- kalau login
            di HP lain, aktifkan lagi di sana.
        </p>

        <div id="pushUnsupportedNotice" class="alert alert-secondary small mb-3" hidden>
            Browser/perangkat ini tidak mendukung notifikasi push.
        </div>
        <div id="pushIosNotice" class="alert alert-warning small mb-3" hidden>
            <i class="bi bi-info-circle"></i>
            Khusus iPhone/iPad: buka menu <strong>Share</strong> di Safari &rarr;
            <strong>"Tambah ke Layar Utama"</strong> dulu, lalu buka aplikasinya dari ikon yang
            terpasang. Notifikasi push di iOS hanya berfungsi dari aplikasi yang sudah terpasang,
            bukan dari tab Safari biasa.
        </div>
        <div id="pushDeniedNotice" class="alert alert-danger small mb-3" hidden>
            Notifikasi diblokir di pengaturan browser. Aktifkan lewat pengaturan situs browser
            (ikon gembok di address bar) kalau ingin menyalakannya lagi.
        </div>

        <div id="pushControls" class="d-flex flex-wrap align-items-center gap-2" hidden>
            <span id="pushStatusBadge" class="badge bg-secondary">Memeriksa status...</span>
            <button type="button" id="pushEnableBtn" class="btn btn-primary btn-sm" hidden>
                <i class="bi bi-bell"></i> Aktifkan Notifikasi
            </button>
            <button type="button" id="pushDisableBtn" class="btn btn-outline-secondary btn-sm" hidden>
                <i class="bi bi-bell-slash"></i> Nonaktifkan di Perangkat Ini
            </button>
            <button type="button" id="pushTestBtn" class="btn btn-outline-primary btn-sm" hidden>
                <i class="bi bi-send"></i> Kirim Uji Coba
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.PushNotif) {
        document.getElementById('pushUnsupportedNotice').hidden = false;
        return;
    }

    var badge = document.getElementById('pushStatusBadge');
    var enableBtn = document.getElementById('pushEnableBtn');
    var disableBtn = document.getElementById('pushDisableBtn');
    var testBtn = document.getElementById('pushTestBtn');
    var controls = document.getElementById('pushControls');
    var iosNotice = document.getElementById('pushIosNotice');
    var deniedNotice = document.getElementById('pushDeniedNotice');
    var unsupportedNotice = document.getElementById('pushUnsupportedNotice');

    function toast(icon, text) {
        if (window.Swal) { Swal.fire({ icon: icon, title: text, timer: 2200, showConfirmButton: false }); }
        else { alert(text); }
    }

    function render(status) {
        [iosNotice, deniedNotice, unsupportedNotice, controls].forEach(function (el) { el.hidden = true; });
        [enableBtn, disableBtn, testBtn].forEach(function (el) { el.hidden = true; });

        if (status === 'ios_need_install') { iosNotice.hidden = false; return; }
        if (status === 'unsupported') { unsupportedNotice.hidden = false; return; }
        if (status === 'denied') { deniedNotice.hidden = false; return; }

        controls.hidden = false;
        if (status === 'subscribed') {
            badge.textContent = 'Aktif di perangkat ini';
            badge.className = 'badge bg-success';
            disableBtn.hidden = false;
            testBtn.hidden = false;
        } else {
            badge.textContent = 'Belum aktif';
            badge.className = 'badge bg-secondary';
            enableBtn.hidden = false;
        }
    }

    function refresh() {
        window.PushNotif.getStatus().then(render);
    }

    enableBtn.addEventListener('click', function () {
        enableBtn.disabled = true;
        window.PushNotif.enable()
            .then(function () { toast('success', 'Notifikasi push diaktifkan.'); refresh(); })
            .catch(function (e) { toast('error', e.message || 'Gagal mengaktifkan notifikasi.'); })
            .finally(function () { enableBtn.disabled = false; });
    });

    disableBtn.addEventListener('click', function () {
        disableBtn.disabled = true;
        window.PushNotif.disable()
            .then(function () { toast('success', 'Notifikasi push dinonaktifkan di perangkat ini.'); refresh(); })
            .catch(function () { toast('error', 'Gagal menonaktifkan notifikasi.'); })
            .finally(function () { disableBtn.disabled = false; });
    });

    testBtn.addEventListener('click', function () {
        testBtn.disabled = true;
        window.PushNotif.sendTest()
            .then(function () { toast('success', 'Uji coba dikirim -- cek notifikasi HP/browser Anda.'); })
            .catch(function () { toast('error', 'Gagal mengirim uji coba.'); })
            .finally(function () { testBtn.disabled = false; });
    });

    refresh();
});
</script>
