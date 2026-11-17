<?php
$isSA = hasRole([ROLE_SUPER_ADMIN]);
$activeCount = 0;
$totalCount = 0;
foreach ($backDateCatalog as $items) {
    foreach ($items as $code => $meta) {
        $totalCount++;
        if (!empty($backDateActive[$code])) { $activeCount++; }
    }
}
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <h6 class="mb-0"><i class="bi bi-calendar-x"></i> Pengaturan Back Date</h6>
            <span class="badge bg-secondary" id="bdSummary"><?= (int) $activeCount ?> dari <?= (int) $totalCount ?> menu dikunci</span>
        </div>
        <p class="text-muted small mb-3">
            Atur pembatasan tanggal transaksi. Jika suatu menu diaktifkan, pengguna tidak dapat membuat transaksi
            menggunakan tanggal sebelum tanggal aktual sistem (hari ini = <strong><?= e(formatTanggal(BackDate::today())) ?></strong>,
            zona Asia/Jakarta). Tanggal hari ini dan mendatang tetap mengikuti aturan lama. Menu yang tidak dicentang
            tidak berubah. Berlaku untuk semua pengguna menu tersebut (termasuk Super Admin), dicek di server saat
            menyimpan maupun mengubah tanggal; transaksi lama yang tanggalnya tidak diubah tidak terpengaruh.
        </p>

        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=saveBackDate" id="bdForm">
            <?= csrfField() ?>
            <?php if ($isSA): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="bdAll"><i class="bi bi-check2-all"></i> Pilih Semua</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="bdNone"><i class="bi bi-x-circle"></i> Nonaktifkan Semua</button>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <?php foreach ($backDateCatalog as $group => $items): ?>
                    <div class="col-lg-6">
                        <div class="border rounded h-100">
                            <div class="bg-light px-3 py-2 fw-semibold small border-bottom"><?= e($group) ?></div>
                            <div class="p-2">
                                <?php foreach ($items as $code => $meta): ?>
                                    <?php $on = !empty($backDateActive[$code]); $row = $backDateRows[$code] ?? null; ?>
                                    <div class="d-flex align-items-start justify-content-between gap-2 px-2 py-2 border-bottom bd-row">
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input bd-check" type="checkbox" role="switch"
                                                   name="backdate[<?= e($code) ?>]" value="1" id="bd_<?= e($code) ?>"
                                                   data-name="<?= e($meta[0]) ?>" data-initial="<?= $on ? '1' : '0' ?>"
                                                   <?= $on ? 'checked' : '' ?> <?= $isSA ? '' : 'disabled' ?>>
                                            <label class="form-check-label" for="bd_<?= e($code) ?>">
                                                <strong><?= e($meta[0]) ?></strong><br>
                                                <span class="text-muted small"><?= e($meta[1]) ?></span>
                                                <?php if ($row): ?>
                                                    <br><span class="text-muted small">Terakhir diubah: <?= e(formatTanggal(substr((string) $row['updated_at'], 0, 10)) . ' ' . substr((string) $row['updated_at'], 11, 5)) ?><?= !empty($row['updated_by_name']) ? ' oleh ' . e($row['updated_by_name']) : '' ?></span>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                        <span class="badge bd-state bg-<?= $on ? 'danger' : 'secondary' ?>"><?= $on ? 'Aktif' : 'Nonaktif' ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($isSA): ?>
                <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan Pengaturan</button>
            <?php else: ?>
                <div class="alert alert-warning small mt-3 mb-0">Hanya Super Admin yang dapat mengubah pengaturan ini.</div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="mb-3"><i class="bi bi-clock-history"></i> Riwayat Perubahan Back Date</h6>
        <?php if (empty($backDateHistory)): ?>
            <div class="text-muted small">Belum pernah diubah (semua menu: Nonaktif).</div>
        <?php else: ?>
            <?php $bdNames = BackDate::names(); ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Waktu</th><th>Diubah oleh</th><th>Menu</th><th>Dari</th><th>Menjadi</th></tr></thead>
                    <tbody>
                        <?php foreach ($backDateHistory as $h): ?>
                            <?php $hc = substr((string) $h['setting_key'], strlen(BackDateSetting::HISTORY_PREFIX)); ?>
                            <tr>
                                <td><?= e(formatTanggal(substr((string) $h['changed_at'], 0, 10)) . ' ' . substr((string) $h['changed_at'], 11, 5)) ?></td>
                                <td><?= e($h['actor'] ?? '-') ?></td>
                                <td><?= e($bdNames[$hc] ?? $hc) ?></td>
                                <td><?= ($h['old_value'] ?? '') === '1' ? 'Aktif' : 'Nonaktif' ?></td>
                                <td><strong><?= ($h['new_value'] ?? '') === '1' ? 'Aktif' : 'Nonaktif' ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($isSA): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('bdForm');
    const checks = Array.prototype.slice.call(form.querySelectorAll('.bd-check'));

    function paint() {
        let n = 0;
        checks.forEach(function (c) {
            const badge = c.closest('.bd-row').querySelector('.bd-state');
            badge.textContent = c.checked ? 'Aktif' : 'Nonaktif';
            badge.classList.toggle('bg-danger', c.checked);
            badge.classList.toggle('bg-secondary', !c.checked);
            if (c.checked) { n++; }
        });
        document.getElementById('bdSummary').textContent = n + ' dari ' + checks.length + ' menu dikunci (belum disimpan bila ada perubahan)';
    }
    checks.forEach(function (c) { c.addEventListener('change', paint); });
    document.getElementById('bdAll').addEventListener('click', function () { checks.forEach(function (c) { c.checked = true; }); paint(); });
    document.getElementById('bdNone').addEventListener('click', function () { checks.forEach(function (c) { c.checked = false; }); paint(); });

    // Konfirmasi sebelum menyimpan: tampilkan menu yang akan DIKUNCI / DIBUKA.
    form.addEventListener('submit', function (e) {
        const lock = [], open = [];
        checks.forEach(function (c) {
            const was = c.dataset.initial === '1';
            if (c.checked && !was) { lock.push(c.dataset.name); }
            if (!c.checked && was) { open.push(c.dataset.name); }
        });
        if (!lock.length && !open.length) { return; } // tak ada perubahan: biarkan server membalas "tidak ada perubahan"
        const msg = 'Simpan pengaturan Back Date?\n'
            + (lock.length ? '\nDIKUNCI (tanggal mundur ditolak):\n - ' + lock.join('\n - ') : '')
            + (open.length ? '\n\nDIBUKA (tanggal mundur diizinkan lagi):\n - ' + open.join('\n - ') : '');
        if (!confirm(msg)) { e.preventDefault(); }
    });
    paint();
});
</script>
<?php endif; ?>
