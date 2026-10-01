<?php
$id = (int) $rb['id'];
$postUrl = fn(string $action) => BASE_URL . '/index.php?module=request_budget&action=' . $action;
$status = $rb['status'];
$fmtDt = fn(?string $d) => $d ? formatTanggal(substr($d, 0, 10)) . ' ' . substr($d, 11, 5) : '-';

// Tiap tombol POST: form kecil + CSRF (konfirmasi lewat confirmAction di script bawah).
$btn = function (string $action, string $label, string $icon, string $class, ?string $confirm = null) use ($id, $postUrl) {
    echo '<form method="POST" action="' . e($postUrl($action)) . '" class="d-inline' . ($confirm ? ' rb-confirm' : '') . '"'
        . ($confirm ? ' data-message="' . e($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn ' . e($class) . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form> ';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= e($rb['request_number']) ?>
            <span class="badge bg-<?= e(RequestBudget::statusBadge($status)) ?> fs-6 align-middle"><?= e(RequestBudget::statusLabel($status)) ?></span>
        </h4>
        <small class="text-muted">Detail Request Budget</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (!empty($actions['edit'])): ?>
            <a href="<?= BASE_URL ?>/request_budget/edit/<?= $id ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <?php if (!empty($actions['print'])): ?>
            <a href="<?= BASE_URL ?>/request_budget/print?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-eye"></i> Preview</a>
            <a href="<?= BASE_URL ?>/request_budget/print?id=<?= $id ?>&autoprint=1" target="_blank" class="btn btn-dark"><i class="bi bi-printer"></i> Cetak</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/request_budget" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<?php if (in_array($status, [RequestBudget::REJECTED, RequestBudget::ACCOUNTING_REJECTED], true)): ?>
    <div class="alert alert-danger">
        <strong><?= $status === RequestBudget::REJECTED ? 'Ditolak' : 'Ditolak Accounting' ?></strong>
        oleh <?= e($status === RequestBudget::REJECTED ? ($rb['rejected_by_name'] ?? '-') : ($rb['accounting_rejected_by_name'] ?? '-')) ?>
        pada <?= e($fmtDt($status === RequestBudget::REJECTED ? $rb['rejected_at'] : $rb['accounting_rejected_at'])) ?>.
        <div class="mt-1"><strong>Alasan:</strong> <?= nl2br(e($status === RequestBudget::REJECTED ? $rb['rejection_reason'] : $rb['accounting_rejection_reason'])) ?></div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4"><div class="text-muted small">No. Request</div><div class="fw-semibold"><?= e($rb['request_number']) ?></div></div>
                    <div class="col-6 col-md-4"><div class="text-muted small">Tanggal</div><div class="fw-semibold"><?= e(formatTanggal($rb['request_date'])) ?></div></div>
                    <div class="col-6 col-md-4"><div class="text-muted small">Status</div><span class="badge bg-<?= e(RequestBudget::statusBadge($status)) ?>"><?= e(RequestBudget::statusLabel($status)) ?></span></div>
                    <div class="col-6 col-md-4"><div class="text-muted small">Pengaju</div><div class="fw-semibold"><?= e($rb['requester_name']) ?></div></div>
                    <div class="col-6 col-md-4"><div class="text-muted small">Project</div><div class="fw-semibold"><?= e($rb['project_name']) ?></div></div>
                    <div class="col-6 col-md-4"><div class="text-muted small">Periode</div><div class="fw-semibold"><?= e($rb['period_label'] ?: '-') ?></div></div>
                    <div class="col-12"><div class="text-muted small">Keperluan</div><div class="fw-semibold"><?= e($rb['purpose']) ?></div></div>
                    <?php if (!empty($rb['description'])): ?>
                        <div class="col-12"><div class="text-muted small">Keterangan</div><div><?= nl2br(e($rb['description'])) ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-2">Detail Kebutuhan</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 entry-cards">
                        <thead class="table-light">
                            <tr><th>No</th><th>Barang / Kebutuhan</th><th>Spesifikasi</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Total</th><th>Keterangan</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $i => $it): ?>
                                <tr>
                                    <td data-label="No"><?= $i + 1 ?></td>
                                    <td data-label="Barang / Kebutuhan" class="fw-semibold"><?= e($it['item_name']) ?></td>
                                    <td data-label="Spesifikasi"><?= e($it['description'] ?? '-') ?></td>
                                    <td data-label="Qty" class="text-end"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2, ',', '.'), '0'), ',')) ?></td>
                                    <td data-label="Satuan"><?= e($it['unit_name'] ?? '-') ?></td>
                                    <td data-label="Harga" class="text-end"><?= formatRupiah($it['estimated_unit_price']) ?></td>
                                    <td data-label="Total" class="text-end"><?= formatRupiah($it['estimated_total']) ?></td>
                                    <td data-label="Keterangan"><?= e($it['notes'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-light"><td colspan="6" class="text-end fw-bold">TOTAL REQUEST BUDGET</td><td class="text-end fw-bold"><?= formatRupiah($rb['total_amount']) ?></td><td></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <?php $hasAction = array_intersect_key($actions, array_flip(['submit', 'approve', 'reject', 'revise', 'submit_accounting', 'accounting_process', 'accounting_reject', 'mark_received', 'complete', 'delete'])); ?>
        <?php if ($hasAction): ?>
            <div class="card border-0 shadow-sm mb-3" id="aksi-tolak">
                <div class="card-body">
                    <div class="card-section-title mb-2">Tindakan</div>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <?php if (!empty($actions['submit'])) { $btn('submit', 'Submit untuk Approval', 'bi-send', 'btn-success', 'Ajukan Request Budget ini untuk approval?'); } ?>
                        <?php if (!empty($actions['revise'])) { $btn('revise', 'Revisi', 'bi-arrow-counterclockwise', 'btn-warning', 'Kembalikan ke Draft untuk direvisi?'); } ?>
                        <?php if (!empty($actions['approve'])) { $btn('approve', 'Setujui', 'bi-check-circle', 'btn-success', 'Setujui Request Budget ini?'); } ?>
                        <?php if (!empty($actions['reject'])): ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rbRejectModal"><i class="bi bi-x-circle"></i> Tolak</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['submit_accounting'])) { $btn('submitAccounting', 'Ajukan ke Accounting', 'bi-box-arrow-in-right', 'btn-primary', 'Ajukan Request Budget ini ke Accounting?'); } ?>
                        <?php if (!empty($actions['accounting_process'])) { $btn('accountingProcess', 'Proses', 'bi-gear', 'btn-primary', 'Proses Request Budget ini?'); } ?>
                        <?php if (!empty($actions['accounting_reject'])): ?>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rbAccRejectModal"><i class="bi bi-x-circle"></i> Tolak (Accounting)</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['mark_received'])) { $btn('markReceived', 'Dana Diterima', 'bi-cash-coin', 'btn-success', 'Tandai dana sudah diterima?'); } ?>
                        <?php if (!empty($actions['complete'])) { $btn('complete', 'Selesaikan', 'bi-check2-all', 'btn-dark', 'Selesaikan Request Budget ini?'); } ?>
                        <?php if (!empty($actions['delete'])) { $btn('delete', 'Hapus', 'bi-trash', 'btn-outline-danger', 'Hapus Request Budget ' . $rb['request_number'] . '?'); } ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-2">Approval &amp; Accounting</div>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Diajukan</dt><dd class="col-7"><?= e($fmtDt($rb['submitted_at'])) ?></dd>
                    <dt class="col-5 text-muted">Disetujui oleh</dt><dd class="col-7"><?= e($rb['approved_by_name'] ?? '-') ?><br><span class="text-muted"><?= e($fmtDt($rb['approved_at'])) ?></span></dd>
                    <dt class="col-5 text-muted">Diajukan ke Accounting</dt><dd class="col-7"><?= e($rb['submitted_accounting_by_name'] ?? '-') ?><br><span class="text-muted"><?= e($fmtDt($rb['submitted_accounting_at'])) ?></span></dd>
                    <dt class="col-5 text-muted">Diproses Accounting</dt><dd class="col-7"><?= e($rb['accounting_by_name'] ?? '-') ?><br><span class="text-muted"><?= e($fmtDt($rb['accounting_processed_at'])) ?></span></dd>
                    <dt class="col-5 text-muted">Dana diterima</dt><dd class="col-7"><?= e($fmtDt($rb['funds_received_at'])) ?></dd>
                    <dt class="col-5 text-muted">Selesai</dt><dd class="col-7"><?= e($rb['completed_by_name'] ?? '-') ?><br><span class="text-muted"><?= e($fmtDt($rb['completed_at'])) ?></span></dd>
                </dl>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="card-section-title mb-3">Riwayat</div>
                <?php if (empty($history)): ?>
                    <div class="text-muted small">Belum ada riwayat.</div>
                <?php endif; ?>
                <ul class="list-unstyled mb-0 rb-timeline">
                    <?php foreach ($history as $h): ?>
                        <li class="mb-3">
                            <div class="small text-muted"><?= e($fmtDt($h['created_at'])) ?></div>
                            <div class="fw-semibold small"><?= e($h['user_name'] ?? 'Sistem') ?><?= !empty($h['role_name']) ? ' <span class="text-muted fw-normal">(' . e($h['role_name']) . ')</span>' : '' ?></div>
                            <div class="small"><?= e($h['notes'] ?? activityLogActionLabel($h['action'])) ?></div>
                            <?php if (!empty($h['new_status']) && $h['old_status'] !== $h['new_status']): ?>
                                <span class="badge bg-<?= e(RequestBudget::statusBadge($h['new_status'])) ?> mt-1"><?= e(RequestBudget::statusLabel($h['new_status'])) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php foreach ([
    'rbRejectModal'    => ['reject', 'Tolak Request Budget', 'Alasan Penolakan'],
    'rbAccRejectModal' => ['accountingReject', 'Tolak Request Budget (Accounting)', 'Alasan Penolakan Accounting'],
] as $modalId => [$act, $title, $label]): ?>
    <?php if (($act === 'reject' && !empty($actions['reject'])) || ($act === 'accountingReject' && !empty($actions['accounting_reject']))): ?>
        <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="<?= e($postUrl($act)) ?>" class="modal-content rb-reason-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="modal-header"><h5 class="modal-title"><?= e($title) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label"><?= e($label) ?> <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="mis. Harga estimasi terlalu tinggi, mohon revisi quantity."></textarea>
                        <div class="invalid-feedback">Alasan wajib diisi.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Tolak</button></div>
                </form>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rb-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, lanjutkan').then(function (ok) { if (ok) { form.submit(); } });
        });
    });
    document.querySelectorAll('.rb-reason-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const ta = form.querySelector('textarea[name="reason"]');
            if (!ta.value.trim()) { e.preventDefault(); ta.classList.add('is-invalid'); }
        });
    });
});
</script>
