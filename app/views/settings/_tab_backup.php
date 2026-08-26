<div class="row g-3">
    <div class="col-md-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-2">Backup Manual</h6>
                <p class="text-muted small">Buat salinan seluruh database saat ini dalam bentuk file SQL, tersimpan di server (di luar folder publik).</p>
                <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=backupCreate" class="js-confirm-backup">
                    <?= csrfField() ?>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-download"></i> Backup Sekarang</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>File</th>
                                <th>Ukuran</th>
                                <th>Dibuat</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($backups)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">Belum ada riwayat backup.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($backups as $b): ?>
                                <tr>
                                    <td class="small"><?= e($b['filename']) ?></td>
                                    <td class="small"><?= number_format($b['file_size'] / 1024, 0, ',', '.') ?> KB</td>
                                    <td class="small">
                                        <?= formatTanggal(substr($b['created_at'], 0, 10)) ?>
                                        <?php if (!empty($b['full_name'])): ?>
                                            &middot; <?= e($b['full_name']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/index.php?module=settings&action=backupDownload&id=<?= (int) $b['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-confirm-backup').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction('Buat backup database sekarang? Prosesnya mungkin butuh beberapa detik.', 'Ya, backup').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });
});
</script>
