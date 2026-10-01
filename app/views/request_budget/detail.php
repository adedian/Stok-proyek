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

<?php if ($status === RequestBudget::REJECTED): ?>
    <div class="alert alert-danger">
        <strong>Ditolak</strong> oleh <?= e($rb['rejected_by_name'] ?? '-') ?> pada <?= e($fmtDt($rb['rejected_at'])) ?>.
        <div class="mt-1"><strong>Alasan:</strong> <?= nl2br(e($rb['rejection_reason'])) ?></div>
    </div>
<?php endif; ?>

<?php
// Stepper proses: selesai (✓) / sedang menunggu (●) / belum (○) -- dari data request, bukan dari frontend.
$st = $status;
$done = fn(string ...$list) => in_array($st, $list, true);
$steps = [];
$steps[] = ['done', 'Request dibuat', 'Oleh: ' . $rb['requester_name'] . ($rb['requester_role'] ? ' — ' . $rb['requester_role'] : '')];
if ($st === RequestBudget::DRAFT) {
    $steps[] = ['wait', 'Submit', 'Belum diajukan'];
} else {
    $steps[] = ['done', 'Request submitted', $fmtDt($rb['submitted_at'])];
}
if ($st === RequestBudget::REJECTED) {
    $steps[] = ['fail', 'Approval — Ditolak', 'Oleh: ' . ($rb['rejected_by_name'] ?? '-') . ' · ' . $fmtDt($rb['rejected_at'])];
} elseif ($done(RequestBudget::APPROVED, RequestBudget::FORWARDED, RequestBudget::COMPLETED)) {
    $steps[] = ['done', 'Approval', 'Approved by: ' . ($rb['approved_by_name'] ?? '-') . ' — ' . ($rb['approved_by_role'] ?? '-') . ' · ' . $fmtDt($rb['approved_at'])];
} else {
    $steps[] = [$st === RequestBudget::PENDING_APPROVAL ? 'wait' : 'todo', 'Approval', $st === RequestBudget::PENDING_APPROVAL ? 'Menunggu Andy / Vicky' : 'Belum diajukan'];
}
if ($done(RequestBudget::FORWARDED, RequestBudget::COMPLETED)) {
    $steps[] = ['done', 'Diproses Purchase', 'Oleh: ' . ($rb['forwarded_by_name'] ?? '-') . ' — ' . ($rb['forwarded_by_role'] ?? '-')];
    $steps[] = ['done', 'Diajukan ke ' . ($rb['forwarded_to'] ?: 'Purwati/Nissa'), $fmtDt($rb['forwarded_at'])];
} else {
    $steps[] = [$st === RequestBudget::APPROVED ? 'wait' : 'todo', 'Pengajuan Purchase', $st === RequestBudget::APPROVED ? 'Menunggu Andy' : 'Belum'];
    $steps[] = ['todo', 'Purwati / Nissa', 'Belum diajukan'];
}
$steps[] = $st === RequestBudget::COMPLETED
    ? ['done', 'Selesai', ($rb['completed_by_name'] ?? '-') . ' · ' . $fmtDt($rb['completed_at'])]
    : [$st === RequestBudget::FORWARDED ? 'wait' : 'todo', 'Selesai', $st === RequestBudget::FORWARDED ? 'Menunggu hasil akhir' : 'Belum'];
$icon = ['done' => '✓', 'wait' => '●', 'todo' => '○', 'fail' => '✕'];
$color = ['done' => 'text-success', 'wait' => 'text-warning', 'todo' => 'text-muted', 'fail' => 'text-danger'];
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-2">Proses Request <?= e($rb['request_number']) ?></div>
        <ul class="list-unstyled mb-0 rb-steps">
            <?php foreach ($steps as [$state, $title, $sub]): ?>
                <li class="d-flex gap-2 mb-2 <?= $state === 'todo' ? 'opacity-75' : '' ?>">
                    <span class="fw-bold <?= $color[$state] ?>" style="width:1.2rem"><?= $icon[$state] ?></span>
                    <span><span class="fw-semibold"><?= e($title) ?></span><br><span class="small text-muted"><?= e($sub) ?></span></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
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

                <?php $hasAction = array_intersect_key($actions, array_flip(['submit', 'approve', 'reject', 'revise', 'forward', 'complete', 'delete'])); ?>
        <?php if ($hasAction): ?>
            <div class="card border-0 shadow-sm mb-3" id="aksi-tolak">
                <div class="card-body" id="aksi-teruskan">
                    <div class="card-section-title mb-2">Tindakan</div>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <?php if (!empty($actions['submit'])) { $btn('submit', 'Submit untuk Approval', 'bi-send', 'btn-success', 'Ajukan Request Budget ini untuk approval?'); } ?>
                        <?php if (!empty($actions['revise'])) { $btn('revise', 'Revisi', 'bi-arrow-counterclockwise', 'btn-warning', 'Kembalikan ke Draft untuk direvisi?'); } ?>
                        <?php if (!empty($actions['approve'])) { $btn('approve', 'Setujui', 'bi-check-circle', 'btn-success', 'Setujui Request Budget ini?'); } ?>
                        <?php if (!empty($actions['reject'])): ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rbRejectModal"><i class="bi bi-x-circle"></i> Tolak</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['forward'])): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rbForwardModal"><i class="bi bi-box-arrow-in-right"></i> Teruskan ke Purwati/Nissa</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['complete'])) { $btn('complete', 'Selesaikan', 'bi-check2-all', 'btn-dark', 'Selesaikan Request Budget ini?'); } ?>
                        <?php if (!empty($actions['delete'])) { $btn('delete', 'Hapus', 'bi-trash', 'btn-outline-danger', 'Hapus Request Budget ' . $rb['request_number'] . '?'); } ?>
                    </div>
                    <?php if ($status === RequestBudget::APPROVED && empty($actions['forward'])): ?>
                        <div class="form-text mt-2">Request ini sudah disetujui dan menunggu Andy meneruskannya ke Purwati/Nissa.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-2">Approval &amp; Pengajuan</div>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Diajukan</dt><dd class="col-7"><?= e($fmtDt($rb['submitted_at'])) ?></dd>
                    <dt class="col-5 text-muted">Approved by</dt><dd class="col-7"><?= e($rb['approved_by_name'] ?? '-') ?><?= !empty($rb['approved_by_role']) ? '<br><span class="text-muted">Role: ' . e($rb['approved_by_role']) . '</span>' : '' ?><br><span class="text-muted"><?= e($fmtDt($rb['approved_at'])) ?></span></dd>
                    <dt class="col-5 text-muted">Diteruskan oleh</dt><dd class="col-7"><?= e($rb['forwarded_by_name'] ?? '-') ?><?= !empty($rb['forwarded_by_role']) ? '<br><span class="text-muted">Role: ' . e($rb['forwarded_by_role']) . '</span>' : '' ?><br><span class="text-muted"><?= e($fmtDt($rb['forwarded_at'])) ?></span></dd>
                    <dt class="col-5 text-muted">Tujuan</dt><dd class="col-7"><?= e($rb['forwarded_to'] ?? '-') ?></dd>
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

<?php if (!empty($actions['reject'])): ?>
    <div class="modal fade" id="rbRejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="<?= e($postUrl('reject')) ?>" class="modal-content rb-reason-form">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="modal-header"><h5 class="modal-title">Tolak Request Budget</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="mis. Harga estimasi terlalu tinggi, mohon revisi quantity."></textarea>
                    <div class="invalid-feedback">Alasan wajib diisi.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Tolak</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php if (!empty($actions['forward'])): ?>
    <div class="modal fade" id="rbForwardModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="<?= e($postUrl('forward')) ?>" class="modal-content">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="modal-header"><h5 class="modal-title">Teruskan ke Purwati / Nissa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Tujuan Pengajuan <span class="text-danger">*</span></label>
                    <select name="forward_to" class="form-select" required>
                        <option value="">-- Pilih tujuan --</option>
                        <?php foreach (RequestBudget::FORWARD_DESTINATIONS as $dest): ?>
                            <option value="<?= e($dest) ?>"><?= e($dest) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Yang meneruskan: <?= e(currentUserName()) ?>. Pengajuan ini hanya mencatat alur &mdash; tidak membuat transaksi Kas/Pembayaran.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Teruskan</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
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
