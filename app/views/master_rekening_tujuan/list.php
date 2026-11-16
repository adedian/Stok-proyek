<?php
function masterRekeningTujuanSortLink(string $col, string $label, string $sort, string $dir): string
{
    $nextDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $url = route('master_rekening_tujuan', 'index', ['sort' => $col, 'dir' => $nextDir]);
    return '<a href="' . e($url) . '" class="text-dark text-decoration-none">' . e($label) . sortIndicator($col, $sort, $dir) . '</a>';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Master Rekening Tujuan</h4>
        <small class="text-muted">Daftar penerima pembayaran untuk kolom Rekening Tujuan di Pengajuan Budget</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/master_data" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Master Data</a>
        <?php if (can('master_rekening_tujuan', 'create')): ?>
            <a href="<?= BASE_URL ?>/master_rekening_tujuan/create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Rekening Tujuan</a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/master_rekening_tujuan" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small text-muted mb-1">Cari</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="nama penerima / bank / no. rekening...">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> Filter</button>
                <a href="<?= BASE_URL ?>/master_rekening_tujuan" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
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
                        <th><?= masterRekeningTujuanSortLink('nama_penerima', 'Nama Penerima', $sort, $dir) ?></th>
                        <th><?= masterRekeningTujuanSortLink('bank_tujuan', 'Bank', $sort, $dir) ?></th>
                        <th><?= masterRekeningTujuanSortLink('no_rekening', 'No. Rekening', $sort, $dir) ?></th>
                        <th>Keterangan</th>
                        <th class="text-center">Status</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-person-vcard empty-icon"></i>
                                <div class="empty-title">Belum ada Rekening Tujuan</div>
                                <div class="empty-desc">Tambahkan penerima pembayaran agar bisa dipilih di Pengajuan Budget.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="Nama Penerima"><?= e($r['nama_penerima']) ?></td>
                            <td data-label="Bank"><?= e($r['bank_tujuan'] ?: '-') ?></td>
                            <td data-label="No. Rekening"><?= e($r['no_rekening'] ?: '-') ?></td>
                            <td data-label="Keterangan"><?= e($r['keterangan'] ?: '-') ?></td>
                            <td data-label="Status" class="text-center">
                                <?php if ((int) $r['is_active'] === 1): ?>
                                    <span class="badge bg-success-subtle text-success-emphasis">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center no-print" data-label="Aksi">
                                <?php if (can('master_rekening_tujuan', 'edit') || can('master_rekening_tujuan', 'delete')): ?>
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (can('master_rekening_tujuan', 'edit')): ?>
                                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/master_rekening_tujuan/edit/<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                        <?php endif; ?>
                                        <?php if (can('master_rekening_tujuan', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=master_rekening_tujuan&action=delete" class="js-confirm-delete" data-message="Hapus rekening tujuan <?= e($r['nama_penerima']) ?>?">
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
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) { if (ok) form.submit(); });
        });
    });
});
</script>
