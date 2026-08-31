<?php
function cashCatSortLink(string $col, string $label, string $sort, string $dir): string
{
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = route('cash_category', 'index', ['sort' => $col, 'dir' => $nextDir]);
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . sortIndicator($col, $sort, $dir) . '</a>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Kategori Kas</h4>
        <small class="text-muted">Master kategori transaksi Kas</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/master_data" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Master Data
        </a>
        <?php if (can('cash_category', 'create')): ?>
        <a href="<?= BASE_URL ?>/cash_category/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Kategori
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/cash_category" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Cari Kategori</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
                <a href="<?= BASE_URL ?>/cash_category" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
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
                        <th><?= cashCatSortLink('category_name', 'Nama Kategori', $sort, $dir) ?></th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="2" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-cash-stack empty-icon"></i>
                                <div class="empty-title">Belum ada kategori Kas</div>
                                <div class="empty-desc">Tambahkan kategori untuk mengelompokkan transaksi Kas.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td><?= e($c['category_name']) ?></td>
                            <td class="text-center no-print">
                                <?php if (can('cash_category', 'edit') || can('cash_category', 'delete')): ?>
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (can('cash_category', 'edit')): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/cash_category/edit/<?= (int) $c['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (can('cash_category', 'delete')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=cash_category&action=delete"
                                                  class="js-confirm-delete" data-message="Hapus kategori <?= e($c['category_name']) ?>?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Hapus</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/views/partials/pagination.php'; ?>

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
