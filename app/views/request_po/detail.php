<?php
$id = (int) $rp['id'];
$postUrl = fn(string $action) => BASE_URL . '/index.php?module=request_po&action=' . $action;
$status = $rp['status'];
$fmtDt = fn(?string $d) => $d ? formatTanggal(substr($d, 0, 10)) . ' ' . substr($d, 11, 5) : '-';

// Tombol POST: form kecil + CSRF (konfirmasi lewat confirmAction di script bawah).
$btn = function (string $action, string $label, string $icon, string $class, ?string $confirm = null) use ($id, $postUrl) {
    echo '<form method="POST" action="' . e($postUrl($action)) . '" class="d-inline' . ($confirm ? ' rpo-confirm' : '') . '"'
        . ($confirm ? ' data-message="' . e($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn ' . e($class) . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form> ';
};
$hasAction = !empty($actions);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">REQUEST PO #<?= e($rp['request_po_number']) ?>
            <span class="badge bg-<?= e(RequestPo::statusBadge($status)) ?> fs-6 align-middle"><?= e(RequestPo::statusLabel($status)) ?></span>
        </h4>
        <small class="text-muted">Detail Request PO</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!empty($actions['edit'])): ?>
            <a href="<?= BASE_URL ?>/request_po/edit/<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/request_po" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<?php if ($status === RequestPo::REJECTED): ?>
    <div class="alert alert-danger">
        <strong>Ditolak</strong> oleh <?= e($rp['rejected_by_name'] ?? '-') ?> pada <?= e($fmtDt($rp['rejected_at'])) ?>.
        <div class="mt-1"><strong>Alasan:</strong> <?= nl2br(e($rp['rejection_reason'])) ?></div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-3">Informasi Request</div>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Tanggal</dt><dd class="col-sm-8"><?= e(formatTanggal($rp['request_date'])) ?></dd>
                    <dt class="col-sm-4 text-muted">Requester</dt><dd class="col-sm-8"><?= e($rp['requester_name']) ?></dd>
                    <dt class="col-sm-4 text-muted">Project</dt><dd class="col-sm-8"><?= e($rp['project_name']) ?></dd>
                    <dt class="col-sm-4 text-muted">Keperluan</dt><dd class="col-sm-8"><?= e($rp['purpose']) ?></dd>
                    <dt class="col-sm-4 text-muted">Catatan</dt><dd class="col-sm-8"><?= $rp['notes'] ? nl2br(e($rp['notes'])) : '-' ?></dd>
                    <dt class="col-sm-4 text-muted">Status</dt><dd class="col-sm-8"><span class="badge bg-<?= e(RequestPo::statusBadge($status)) ?>"><?= e(RequestPo::statusLabel($status)) ?></span></dd>
                    <dt class="col-sm-4 text-muted">Dibuat oleh</dt><dd class="col-sm-8"><?= e($rp['creator_name'] ?? '-') ?></dd>
                </dl>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-3">Daftar Barang</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th style="width:60px">No</th><th>Nama Barang</th><th class="text-end" style="width:160px">Qty</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">Belum ada barang.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($items as $i => $it): ?>
                                <tr>
                                    <td data-label="No"><?= $i + 1 ?></td>
                                    <td data-label="Nama Barang"><?= e($it['item_name']) ?></td>
                                    <td data-label="Qty" class="text-end"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2, ',', '.'), '0'), ',')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-3">Approval</div>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Status</dt><dd class="col-sm-8"><span class="badge bg-<?= e(RequestPo::statusBadge($status)) ?>"><?= e(RequestPo::statusLabel($status)) ?></span></dd>
                    <?php if ($status === RequestPo::APPROVED): ?>
                        <dt class="col-sm-4 text-muted">Approved By</dt><dd class="col-sm-8"><?= e($rp['approved_by_name'] ?? '-') ?></dd>
                        <dt class="col-sm-4 text-muted">Role</dt><dd class="col-sm-8"><?= e($rp['approved_by_role'] ?? '-') ?></dd>
                        <dt class="col-sm-4 text-muted">Approved At</dt><dd class="col-sm-8"><?= e($fmtDt($rp['approved_at'])) ?></dd>
                        <dt class="col-sm-4 text-muted">Catatan Approval</dt><dd class="col-sm-8"><?= $rp['approval_notes'] ? nl2br(e($rp['approval_notes'])) : '-' ?></dd>
                    <?php elseif ($status === RequestPo::REJECTED): ?>
                        <dt class="col-sm-4 text-muted">Rejected By</dt><dd class="col-sm-8"><?= e($rp['rejected_by_name'] ?? '-') ?></dd>
                        <dt class="col-sm-4 text-muted">Role</dt><dd class="col-sm-8"><?= e($rp['rejected_by_role'] ?? '-') ?></dd>
                        <dt class="col-sm-4 text-muted">Rejected At</dt><dd class="col-sm-8"><?= e($fmtDt($rp['rejected_at'])) ?></dd>
                        <dt class="col-sm-4 text-muted">Alasan</dt><dd class="col-sm-8"><?= nl2br(e($rp['rejection_reason'])) ?></dd>
                    <?php else: ?>
                        <dt class="col-sm-4 text-muted">Approver</dt><dd class="col-sm-8"><?= $approverNames ? e(implode(', ', $approverNames)) : 'Super Admin' ?></dd>
                        <dt class="col-sm-4 text-muted">Disubmit</dt><dd class="col-sm-8"><?= e($fmtDt($rp['submitted_at'])) ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <?php if ($hasAction): ?>
            <div class="card border-0 shadow-sm mb-3" id="aksi">
                <div class="card-body">
                    <div class="card-section-title mb-2">Tindakan</div>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <?php if (!empty($actions['submit'])) { $btn('submit', 'Submit Request PO', 'bi-send', 'btn-success', 'Submit Request PO ini untuk approval? Setelah disubmit data tidak bisa diedit.'); } ?>
                        <?php if (!empty($actions['approve'])): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#rpoApproveModal"><i class="bi bi-check-circle"></i> Setujui</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['reject'])): ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rpoRejectModal"><i class="bi bi-x-circle"></i> Tolak</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['delete'])) { $btn('delete', 'Hapus', 'bi-trash', 'btn-outline-danger', 'Hapus Request PO ' . $rp['request_po_number'] . '?'); } ?>
                    </div>
                </div>
            </div>
        <?php elseif ($status === RequestPo::PENDING_APPROVAL): ?>
            <div class="alert alert-info small mb-3">Menunggu approval<?= $approverNames ? ' ' . e(implode(', ', $approverNames)) : '' ?>. Data tidak bisa diedit selama menunggu approval.</div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="card-section-title mb-3">Riwayat</div>
                <?php if (empty($history)): ?><div class="text-muted small">Belum ada riwayat.</div><?php endif; ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($history as $h): ?>
                        <li class="mb-3">
                            <div class="small text-muted"><?= e($fmtDt($h['created_at'])) ?></div>
                            <div class="fw-semibold small"><?= e($h['user_name'] ?? 'Sistem') ?><?= !empty($h['role_name']) ? ' <span class="text-muted fw-normal">(' . e($h['role_name']) . ')</span>' : '' ?></div>
                            <div class="small"><?= e($h['notes'] ?? activityLogActionLabel($h['action'])) ?></div>
                            <?php if (!empty($h['new_status']) && $h['old_status'] !== $h['new_status']): ?>
                                <span class="badge bg-<?= e(RequestPo::statusBadge($h['new_status'])) ?> mt-1"><?= e(RequestPo::statusLabel($h['new_status'])) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($actions['approve'])): ?>
    <div class="modal fade" id="rpoApproveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="<?= e($postUrl('approve')) ?>" class="modal-content">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="modal-header"><h5 class="modal-title">Setujui Request PO</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Catatan approval (opsional)</label>
                    <textarea name="note" class="form-control" rows="2" maxlength="500"></textarea>
                    <div class="form-text">Approval dicatat sebagai: <?= e(currentUserName()) ?>. Proses Request PO berhenti di status Disetujui &mdash; tidak membuat PO otomatis.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Setujui</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php if (!empty($actions['reject'])): ?>
    <div class="modal fade" id="rpoRejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="<?= e($postUrl('reject')) ?>" class="modal-content rpo-reason-form">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="modal-header"><h5 class="modal-title">Tolak Request PO</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required maxlength="1000" placeholder="mis. Barang belum sesuai kebutuhan project."></textarea>
                    <div class="invalid-feedback">Alasan wajib diisi.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Tolak Request</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rpo-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, lanjutkan').then(function (ok) { if (ok) { form.submit(); } });
        });
    });
    document.querySelectorAll('.rpo-reason-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const ta = form.querySelector('textarea[name="reason"]');
            if (!ta.value.trim()) { e.preventDefault(); ta.classList.add('is-invalid'); }
        });
    });
});
</script>
