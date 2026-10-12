<?php
function informationSortLink(string $col, string $label, string $sort, string $dir, array $filters): string
{
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = route('information', 'index', array_filter(array_merge($filters, ['sort' => $col, 'dir' => $nextDir])));
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . sortIndicator($col, $sort, $dir) . '</a>';
}

$categoryBadge = [
    'umum'        => 'secondary',
    'pengumuman'  => 'info',
    'sistem'      => 'primary',
    'prosedur'    => 'warning',
    'maintenance' => 'danger',
    'lainnya'     => 'light text-dark',
];
$statusBadge = ['aktif' => 'success', 'tidak_aktif' => 'secondary'];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Pusat Informasi</h4>
        <small class="text-muted">Pengumuman &amp; keterangan untuk seluruh pengguna aplikasi</small>
    </div>
    <?php if ($canManage): ?>
        <a href="<?= BASE_URL ?>/information/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Informasi
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/information" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Cari Informasi</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="judul, isi, pembuat...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Kategori</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $filters['category'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($canManage): ?>
                <div class="col-6 col-md-2">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <?php foreach ($statuses as $slug => $label): ?>
                            <option value="<?= e($slug) ?>" <?= $filters['status'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Filter">
                    <i class="bi bi-search"></i>
                </button>
                <a href="<?= BASE_URL ?>/information" class="btn btn-sm btn-outline-secondary w-100" title="Reset">
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
                        <th><?= informationSortLink('title', 'Judul Informasi', $sort, $dir, $filters) ?></th>
                        <th>Kategori</th>
                        <th><?= informationSortLink('status', 'Status', $sort, $dir, $filters) ?></th>
                        <th><?= informationSortLink('publish_date', 'Tanggal', $sort, $dir, $filters) ?></th>
                        <th>Dibuat Oleh</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-info-circle empty-icon"></i>
                                <?php $statusFilterActive = $canManage && ($filters['status'] ?? '') !== ''; ?>
                                <?php if ($filters['keyword'] !== '' || $filters['category'] !== '' || $statusFilterActive || $filters['date_from'] !== '' || $filters['date_to'] !== ''): ?>
                                    <div class="empty-title">Tidak ada informasi yang sesuai dengan pencarian.</div>
                                <?php else: ?>
                                    <div class="empty-title">Belum ada informasi.</div>
                                    <div class="empty-desc">Informasi/pengumuman yang ditambahkan akan tampil di sini.</div>
                                <?php endif; ?>
                                <?php if ($canManage): ?>
                                    <a href="<?= BASE_URL ?>/information/create" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Tambah Informasi
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL ?>/information/detail/<?= (int) $r['id'] ?>" class="text-dark text-decoration-none fw-semibold">
                                    <?= e($r['title']) ?>
                                </a>
                            </td>
                            <td><span class="badge bg-<?= e($categoryBadge[$r['category']] ?? 'secondary') ?>"><?= e(Information::categoryLabel($r['category'])) ?></span></td>
                            <td><span class="badge bg-<?= e($statusBadge[$r['status']] ?? 'secondary') ?>"><?= e(Information::statusOptions()[$r['status']] ?? $r['status']) ?></span></td>
                            <td>
                                <?= e(formatTanggal($r['publish_date'])) ?>
                                <?php if (!empty($r['end_date'])): ?>
                                    <div class="small text-muted">s/d <?= e(formatTanggal($r['end_date'])) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($r['created_by_name'] ?? '-') ?></td>
                            <td class="text-center no-print">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/information/detail/<?= (int) $r['id'] ?>">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </li>
                                        <?php if ($canManage && can('information', 'edit')): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/information/edit/<?= (int) $r['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <?php if ($canManage && can('information', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=information&action=delete"
                                                      class="js-confirm-delete" data-message="Hapus informasi '<?= e($r['title']) ?>'?">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
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
