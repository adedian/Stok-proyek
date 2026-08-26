<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Tempat Sampah</h4>
        <small class="text-muted">Data yang sudah dihapus dari seluruh modul -- bisa dipulihkan atau dihapus permanen</small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="trash">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Modul</label>
                <select name="module_filter" class="form-select form-select-sm">
                    <option value="">Semua Modul</option>
                    <?php foreach ($moduleOptions as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $moduleFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/index.php?module=trash" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Modul</th>
                        <th>Nama / Data</th>
                        <th>Tanggal Dihapus</th>
                        <th>Dihapus Oleh</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-trash3 empty-icon"></i>
                                <div class="empty-title">Tempat sampah kosong</div>
                                <div class="empty-desc">Data yang dihapus dari modul manapun akan muncul di sini.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <?php $deletedTs = $r['deleted_at'] ? strtotime($r['deleted_at']) : null; ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><span class="badge bg-secondary"><?= e($r['module_label']) ?></span></td>
                            <td><?= e($r['display']) ?></td>
                            <td><?= $deletedTs ? formatTanggal(date('Y-m-d', $deletedTs)) . ' ' . date('H:i', $deletedTs) : '-' ?></td>
                            <td><?= e($r['deleted_by']) ?></td>
                            <td class="text-center no-print">
                                <div class="d-flex gap-1 justify-content-center">
                                    <form method="POST" action="<?= BASE_URL ?>/index.php?module=trash&action=restore" class="js-confirm-restore" data-message="Pulihkan &quot;<?= e($r['display']) ?>&quot; ke data aktif?">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="module" value="<?= e($r['module']) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Restore">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                        </button>
                                    </form>
                                    <form method="POST" action="<?= BASE_URL ?>/index.php?module=trash&action=forceDelete" class="js-confirm-delete" data-message="Hapus permanen &quot;<?= e($r['display']) ?>&quot;? Tindakan ini TIDAK BISA dibatalkan.">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="module" value="<?= e($r['module']) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus Permanen">
                                            <i class="bi bi-trash3"></i> Hapus Permanen
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, hapus permanen').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });
    document.querySelectorAll('.js-confirm-restore').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, pulihkan').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });
});
</script>
