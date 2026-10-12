<?php
$categoryBadge = [
    'umum'        => 'secondary',
    'pengumuman'  => 'info',
    'sistem'      => 'primary',
    'prosedur'    => 'warning',
    'maintenance' => 'danger',
    'lainnya'     => 'light text-dark',
];
$statusBadge = ['aktif' => 'success', 'tidak_aktif' => 'secondary'];
$isExpired = !empty($info['end_date']) && $info['end_date'] < date('Y-m-d');
$isScheduled = $info['publish_date'] > date('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= e($info['title']) ?></h4>
        <small class="text-muted">Detail Informasi</small>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('information', 'edit')): ?>
            <a href="<?= BASE_URL ?>/information/edit/<?= (int) $info['id'] ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil"></i> Edit
            </a>
        <?php endif; ?>
        <?php if (can('information', 'delete')): ?>
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=information&action=delete"
                  class="js-confirm-delete" data-message="Hapus informasi '<?= e($info['title']) ?>'?">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $info['id'] ?>">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Hapus</button>
            </form>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/information" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-sm-3">
                <div class="text-muted small">Kategori</div>
                <span class="badge bg-<?= e($categoryBadge[$info['category']] ?? 'secondary') ?>"><?= e(Information::categoryLabel($info['category'])) ?></span>
            </div>
            <div class="col-6 col-sm-3">
                <div class="text-muted small">Status</div>
                <span class="badge bg-<?= e($statusBadge[$info['status']] ?? 'secondary') ?>"><?= e(Information::statusOptions()[$info['status']] ?? $info['status']) ?></span>
                <?php if ($info['status'] === 'aktif' && $isExpired): ?>
                    <span class="badge bg-secondary" title="Sudah melewati tanggal berakhir, tidak tampil sebagai warning Dashboard">Kedaluwarsa</span>
                <?php elseif ($info['status'] === 'aktif' && $isScheduled): ?>
                    <span class="badge bg-secondary" title="Belum masuk tanggal publikasi">Terjadwal</span>
                <?php endif; ?>
            </div>
            <div class="col-6 col-sm-3">
                <div class="text-muted small">Tanggal Publikasi</div>
                <div class="fw-semibold"><?= e(formatTanggal($info['publish_date'])) ?></div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="text-muted small">Tanggal Berakhir</div>
                <div class="fw-semibold"><?= !empty($info['end_date']) ? e(formatTanggal($info['end_date'])) : '-' ?></div>
            </div>
        </div>

        <hr>

        <div class="text-muted small mb-1">Informasi</div>
        <div class="information-content" style="white-space: pre-line; line-height: 1.7;"><?= nl2br(e($info['content'])) ?></div>

        <hr>

        <div class="text-muted small">
            Dibuat oleh <span class="fw-semibold text-dark"><?= e($info['created_by_name'] ?? 'Sistem') ?></span>
            &middot; <?= e(formatTanggalLengkap($info['created_at'])) ?>
            <?php if ($info['updated_at'] && $info['updated_at'] !== $info['created_at']): ?>
                &middot; diperbarui <?= e(waktuLalu($info['updated_at'])) ?>
            <?php endif; ?>
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
