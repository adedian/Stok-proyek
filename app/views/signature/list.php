<?php
function signatureSortLink(string $col, string $label, string $sort, string $dir): string
{
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = route('signature', 'index', ['sort' => $col, 'dir' => $nextDir]);
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . sortIndicator($col, $sort, $dir) . '</a>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Tanda Tangan</h4>
        <small class="text-muted">Master tanda tangan untuk dokumen cetak (PO, Penerimaan Barang)</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/master_data" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Master Data
        </a>
        <a href="<?= BASE_URL ?>/signature/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Tanda Tangan
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/signature" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Cari (Nama / Jabatan)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/signature" class="btn btn-sm btn-outline-secondary">
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
                        <th style="width: 90px;">Gambar</th>
                        <th><?= signatureSortLink('name', 'Nama', $sort, $dir) ?></th>
                        <th><?= signatureSortLink('position', 'Jabatan', $sort, $dir) ?></th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($signatures)): ?>
                        <tr><td colspan="5" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-pen empty-icon"></i>
                                <div class="empty-title">Belum ada tanda tangan</div>
                                <div class="empty-desc">Tambahkan tanda tangan untuk dipakai di dokumen cetak PO & Penerimaan Barang.</div>
                                <a href="<?= BASE_URL ?>/signature/create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah Tanda Tangan
                                </a>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($signatures as $s): ?>
                        <tr>
                            <td>
                                <img src="<?= BASE_URL ?>/<?= e($s['signature_image']) ?>" alt="TTD <?= e($s['name']) ?>"
                                     style="max-width: 70px; max-height: 40px; object-fit: contain;">
                            </td>
                            <td class="fw-semibold"><?= e($s['name']) ?></td>
                            <td><?= e($s['position']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $s['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= e($statusLabels[$s['status']] ?? $s['status']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/signature/edit/<?= (int) $s['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=signature&action=delete"
                                                  class="js-confirm-delete" data-message="Hapus tanda tangan <?= e($s['name']) ?>?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
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
