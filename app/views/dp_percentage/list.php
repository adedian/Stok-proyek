<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Persentase Tagihan DP</h4>
        <small class="text-muted">Pilihan persentase DP untuk Invoice Keluar</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/master_data" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Master Data
        </a>
        <a href="<?= BASE_URL ?>/dp_percentage/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Persentase
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th class="text-end">Persentase</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-percent empty-icon"></i>
                                <div class="empty-title">Belum ada persentase DP</div>
                                <div class="empty-desc">Tambahkan pilihan persentase supaya bisa dipakai di Invoice Keluar.</div>
                                <a href="<?= BASE_URL ?>/dp_percentage/create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah Persentase
                                </a>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['name']) ?></td>
                            <td class="text-end"><?= rtrim(rtrim(number_format((float) $r['percentage'], 2), '0'), '.') ?>%</td>
                            <td class="text-center">
                                <span class="badge bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= e($statusLabels[$r['status']] ?? $r['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/dp_percentage/edit/<?= (int) $r['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=dp_percentage&action=toggleStatus">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                <button type="submit" class="dropdown-item">
                                                    <?php if ($r['status'] === 'active'): ?>
                                                        <i class="bi bi-toggle-off"></i> Nonaktifkan
                                                    <?php else: ?>
                                                        <i class="bi bi-toggle-on"></i> Aktifkan
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=dp_percentage&action=delete"
                                                  class="js-confirm-delete" data-message="Hapus persentase DP <?= e($r['name']) ?>? Invoice yang sudah dibuat dengan persentase ini tidak akan berubah.">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
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
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });
});
</script>
