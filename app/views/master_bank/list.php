<?php
function masterBankSortLink(string $col, string $label, string $sort, string $dir): string
{
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = route('master_bank', 'index', ['sort' => $col, 'dir' => $nextDir]);
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . sortIndicator($col, $sort, $dir) . '</a>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Master Bank</h4>
        <small class="text-muted">Sumber dropdown Bank (Loan / HR) untuk modul Bank</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/master_data" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Master Data
        </a>
        <?php if (can('master_bank', 'create')): ?>
        <a href="<?= BASE_URL ?>/master_bank/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Bank
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/master_bank" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Cari Bank</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="ketik nama/kode...">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Jenis</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($jenisLabels as $k => $l): ?>
                        <option value="<?= e($k) ?>" <?= $filters['jenis'] === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
                <a href="<?= BASE_URL ?>/master_bank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
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
                        <th><?= masterBankSortLink('bank_code', 'Kode', $sort, $dir) ?></th>
                        <th><?= masterBankSortLink('bank_name', 'Nama Bank', $sort, $dir) ?></th>
                        <th>Jenis</th>
                        <th class="text-center">Status</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="5" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-bank2 empty-icon"></i>
                                <div class="empty-title">Belum ada Bank</div>
                                <div class="empty-desc">Tambahkan Bank (Loan/HR) untuk dipakai di modul Bank.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e($r['bank_code']) ?></td>
                            <td><?= e($r['bank_name']) ?></td>
                            <td><span class="badge bg-info-subtle text-info-emphasis"><?= e($jenisLabels[$r['jenis']] ?? $r['jenis']) ?></span></td>
                            <td class="text-center">
                                <?php if ((int) $r['is_active'] === 1): ?>
                                    <span class="badge bg-success-subtle text-success-emphasis">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center no-print">
                                <?php if (can('master_bank', 'edit') || can('master_bank', 'delete')): ?>
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (can('master_bank', 'edit')): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/master_bank/edit/<?= (int) $r['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (can('master_bank', 'delete')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=master_bank&action=delete"
                                                  class="js-confirm-delete" data-message="Hapus bank <?= e($r['bank_name']) ?>?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
