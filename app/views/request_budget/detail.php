<?php
$id = (int) $rb['id'];
$postUrl = fn(string $action) => BASE_URL . '/index.php?module=request_budget&action=' . $action;
$status = $rb['status'];
$fmtDt = fn(?string $d) => $d ? formatTanggal(substr($d, 0, 10)) . ' ' . substr($d, 11, 5) : '-';
$fmtDate = fn(?string $d) => $d ? formatTanggal(substr($d, 0, 10)) : '-';

// Tombol POST: form kecil + CSRF (konfirmasi lewat confirmAction di script bawah).
$btn = function (string $action, string $label, string $icon, string $class, ?string $confirm = null) use ($id, $postUrl) {
    echo '<form method="POST" action="' . e($postUrl($action)) . '" class="d-inline' . ($confirm ? ' rb-confirm' : '') . '"'
        . ($confirm ? ' data-message="' . e($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn ' . e($class) . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form> ';
};

$apprBadge = ['PENDING' => ['warning text-dark', 'Menunggu'], 'APPROVED' => ['success', 'Approved'], 'REJECTED' => ['danger', 'Ditolak']];
$slotDefs = RequestBudget::APPROVAL_SLOTS;
$allApproved = !empty($approvals) && count(array_filter($approvals, fn($a) => $a['status'] !== 'APPROVED')) === 0;

// ---- Stepper proses (dari data request, bukan dari frontend) ----
$st = $status;
$after = fn(string ...$l) => in_array($st, $l, true);
$steps = [];
$steps[] = ['done', 'Request dibuat', 'Oleh: ' . $rb['requester_name'] . ($rb['requester_role'] ? ' — ' . $rb['requester_role'] : '')];
$steps[] = $st === RequestBudget::DRAFT ? ['wait', 'Submit', 'Belum diajukan'] : ['done', 'Request submitted', $fmtDt($rb['submitted_at'])];
foreach ($slotDefs as $slot => $def) {
    $ap = $approvals[$slot] ?? null;
    if ($ap && $ap['status'] === 'APPROVED') {
        $steps[] = ['done', $def['label'], 'Approved by: ' . ($ap['approver_name'] ?? '-') . ' — ' . ($ap['approver_role'] ?? '-') . ' · ' . $fmtDt($ap['acted_at'])];
    } elseif ($ap && $ap['status'] === 'REJECTED') {
        $steps[] = ['fail', $def['label'] . ' — Ditolak', ($ap['approver_name'] ?? '-') . ' · ' . $fmtDt($ap['acted_at'])];
    } else {
        $steps[] = [$st === RequestBudget::PENDING_APPROVAL ? 'wait' : 'todo', $def['label'], $st === RequestBudget::PENDING_APPROVAL ? 'Menunggu' : 'Belum'];
    }
}
if ($after(RequestBudget::PURCHASE_COMPLETED, RequestBudget::FORWARDED, RequestBudget::COMPLETED)) {
    $steps[] = ['done', 'Proses Purchase', 'Dilengkapi: ' . ($rb['purchase_completed_by_name'] ?? $rb['forwarded_by_name'] ?? '-') . ' · ' . $fmtDt($rb['purchase_completed_at'])];
} else {
    $steps[] = [$st === RequestBudget::APPROVED ? 'wait' : 'todo', 'Proses Purchase', $st === RequestBudget::APPROVED ? 'Menunggu Andy melengkapi PO/Invoice/dokumen' : 'Belum'];
}
$steps[] = $after(RequestBudget::FORWARDED, RequestBudget::COMPLETED)
    ? ['done', 'Diajukan ke ' . ($rb['forwarded_to'] ?: 'Purwati/Nissa'), 'Submitted by: ' . ($rb['forwarded_by_name'] ?? '-') . ' — ' . ($rb['forwarded_by_role'] ?? '-') . ' · ' . $fmtDt($rb['forwarded_at'])]
    : [$st === RequestBudget::PURCHASE_COMPLETED ? 'wait' : 'todo', 'Purwati / Nissa', $st === RequestBudget::PURCHASE_COMPLETED ? 'Menunggu Andy mengajukan' : 'Belum diajukan'];
$steps[] = $st === RequestBudget::COMPLETED
    ? ['done', 'Selesai', ($rb['completed_by_name'] ?? '-') . ' · ' . $fmtDt($rb['completed_at'])]
    : [$st === RequestBudget::FORWARDED ? 'wait' : 'todo', 'Selesai', $st === RequestBudget::FORWARDED ? 'Menunggu hasil akhir' : 'Belum'];
if ($st === RequestBudget::REJECTED) {
    $steps[] = ['fail', 'Request ditolak', 'Alasan: ' . ($rb['rejection_reason'] ?? '-')];
}
$icon = ['done' => '✓', 'wait' => '●', 'todo' => '○', 'fail' => '✕'];
$color = ['done' => 'text-success', 'wait' => 'text-warning', 'todo' => 'text-muted', 'fail' => 'text-danger'];

$docLink = function (?string $path, string $label = 'Lihat file') {
    return $path ? '<a href="' . e(fileUrl($path)) . '" target="_blank" class="small text-decoration-none"><i class="bi bi-paperclip"></i> ' . e($label) . '</a>' : '<span class="text-muted small">-</span>';
};
$delDoc = function (string $type, int $docId) use ($actions, $postUrl, $id) {
    if (empty($actions['purchase_edit'])) { return ''; }
    return '<form method="POST" action="' . e($postUrl('deleteDoc')) . '" class="d-inline rb-confirm" data-message="Hapus dokumen ini?">'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="type" value="' . e($type) . '"><input type="hidden" name="doc_id" value="' . $docId . '">'
        . '<button type="submit" class="btn btn-sm btn-outline-danger py-0" title="Hapus"><i class="bi bi-trash"></i></button></form>';
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

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-2">Approval</div>
                <div class="row g-2">
                    <?php foreach ($slotDefs as $slot => $def): $ap = $approvals[$slot] ?? null; $bd = $apprBadge[$ap['status'] ?? 'PENDING'] ?? $apprBadge['PENDING']; ?>
                        <div class="col-md-6">
                            <div class="border rounded p-2 h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold small"><?= e($def['label']) ?></span>
                                    <span class="badge bg-<?= e($bd[0]) ?>"><?= $ap ? e($bd[1]) : 'Belum diajukan' ?></span>
                                </div>
                                <?php if ($ap && $ap['status'] !== 'PENDING'): ?>
                                    <div class="small mt-1">Oleh: <strong><?= e($ap['approver_name'] ?? '-') ?></strong> &mdash; <?= e($ap['approver_role'] ?? '-') ?></div>
                                    <div class="small text-muted"><?= e($fmtDt($ap['acted_at'])) ?></div>
                                    <?php if (!empty($ap['note'])): ?><div class="small">Catatan: <?= nl2br(e($ap['note'])) ?></div><?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($status === RequestBudget::PENDING_APPROVAL && !$allApproved): ?>
                    <div class="form-text mt-2">Urutan approval: Project Manager dahulu, baru Purchase. Request baru masuk proses Purchase setelah KEDUA approval selesai.</div>
                <?php endif; ?>
            </div>
        </div>

        <?php $showPurchase = in_array($status, [RequestBudget::APPROVED, RequestBudget::PURCHASE_COMPLETED, RequestBudget::FORWARDED, RequestBudget::COMPLETED], true); ?>
        <?php if ($showPurchase): ?>
        <div class="card border-0 shadow-sm mb-3" id="proses-purchase">
            <div class="card-body">
                <div class="card-section-title mb-2">Data Purchase</div>

                <div class="fw-semibold small mt-2">Purchase Order</div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-2 entry-cards">
                    <thead class="table-light"><tr><th>No. PO</th><th>Tanggal</th><th>Vendor/Supplier</th><th class="text-end">Nominal</th><th>File</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($pos as $p): ?>
                        <tr><td data-label="No. PO"><?= e($p['po_number']) ?></td><td data-label="Tanggal"><?= e($fmtDate($p['po_date'])) ?></td><td data-label="Vendor"><?= e($p['vendor_name'] ?? '-') ?></td><td data-label="Nominal" class="text-end"><?= formatRupiah($p['amount']) ?></td><td data-label="File"><?= $docLink($p['file_path']) ?></td><td class="text-end"><?= $delDoc('po', (int) $p['id']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$pos): ?><tr><td colspan="6" class="text-muted small">Belum ada PO.</td></tr><?php endif; ?>
                    </tbody></table></div>

                <div class="fw-semibold small mt-2">Invoice</div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-2 entry-cards">
                    <thead class="table-light"><tr><th>No. Invoice</th><th>Tanggal</th><th>Vendor/Supplier</th><th class="text-end">Nominal</th><th>File</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $v): ?>
                        <tr><td data-label="No. Invoice"><?= e($v['invoice_number']) ?></td><td data-label="Tanggal"><?= e($fmtDate($v['invoice_date'])) ?></td><td data-label="Vendor"><?= e($v['vendor_name'] ?? '-') ?></td><td data-label="Nominal" class="text-end"><?= formatRupiah($v['amount']) ?></td><td data-label="File"><?= $docLink($v['file_path']) ?></td><td class="text-end"><?= $delDoc('invoice', (int) $v['id']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$invoices): ?><tr><td colspan="6" class="text-muted small">Belum ada Invoice.</td></tr><?php endif; ?>
                    </tbody></table></div>

                <div class="fw-semibold small mt-2">Dokumen Pendukung</div>
                <div class="table-responsive"><table class="table table-sm align-middle mb-2 entry-cards">
                    <thead class="table-light"><tr><th>Nama Dokumen</th><th>Keterangan</th><th>File</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($attachments as $a): ?>
                        <tr><td data-label="Nama Dokumen"><?= e($a['doc_name']) ?></td><td data-label="Keterangan"><?= e($a['description'] ?? '-') ?></td><td data-label="File"><?= $docLink($a['file_path']) ?></td><td class="text-end"><?= $delDoc('attachment', (int) $a['id']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$attachments): ?><tr><td colspan="4" class="text-muted small">Belum ada dokumen pendukung.</td></tr><?php endif; ?>
                    </tbody></table></div>

                <?php if (!empty($rb['purchase_notes'])): ?><div class="small"><strong>Catatan Purchase:</strong> <?= nl2br(e($rb['purchase_notes'])) ?></div><?php endif; ?>

                <?php if (!empty($actions['purchase_edit'])): ?>
                    <hr>
                    <div class="row g-3">
                        <div class="col-lg-12">
                            <form method="POST" action="<?= e($postUrl('addPo')) ?>" enctype="multipart/form-data" class="row g-2 border rounded p-2">
                                <?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>">
                                <div class="col-12 fw-semibold small">Tambah PO</div>
                                <div class="col-md-3"><input type="text" name="po_number" class="form-control form-control-sm" placeholder="No. PO *" required maxlength="80"></div>
                                <div class="col-md-3"><input type="date" name="po_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>"></div>
                                <div class="col-md-3"><input type="text" name="vendor_name" class="form-control form-control-sm" placeholder="Vendor/Supplier" maxlength="200"></div>
                                <div class="col-md-3"><input type="text" inputmode="decimal" name="amount" class="form-control form-control-sm text-end currency-input" placeholder="Nominal"></div>
                                <div class="col-md-9"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
                                <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-plus-circle"></i> Tambah PO</button></div>
                            </form>
                        </div>
                        <div class="col-lg-12">
                            <form method="POST" action="<?= e($postUrl('addInvoice')) ?>" enctype="multipart/form-data" class="row g-2 border rounded p-2">
                                <?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>">
                                <div class="col-12 fw-semibold small">Tambah Invoice</div>
                                <div class="col-md-3"><input type="text" name="invoice_number" class="form-control form-control-sm" placeholder="No. Invoice *" required maxlength="80"></div>
                                <div class="col-md-3"><input type="date" name="invoice_date" class="form-control form-control-sm" required value="<?= date('Y-m-d') ?>"></div>
                                <div class="col-md-3"><input type="text" name="vendor_name" class="form-control form-control-sm" placeholder="Vendor/Supplier" maxlength="200"></div>
                                <div class="col-md-3"><input type="text" inputmode="decimal" name="amount" class="form-control form-control-sm text-end currency-input" placeholder="Nominal"></div>
                                <div class="col-md-9"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
                                <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-plus-circle"></i> Tambah Invoice</button></div>
                            </form>
                        </div>
                        <div class="col-lg-12">
                            <form method="POST" action="<?= e($postUrl('addAttachment')) ?>" enctype="multipart/form-data" class="row g-2 border rounded p-2">
                                <?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>">
                                <div class="col-12 fw-semibold small">Tambah Dokumen Pendukung</div>
                                <div class="col-md-4"><input type="text" name="doc_name" class="form-control form-control-sm" placeholder="Nama dokumen *" required maxlength="200"></div>
                                <div class="col-md-8"><input type="text" name="description" class="form-control form-control-sm" placeholder="Keterangan" maxlength="500"></div>
                                <div class="col-md-9"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
                                <div class="col-md-3"><button class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-plus-circle"></i> Tambah Dokumen</button></div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php $hasAction = array_intersect_key($actions, array_flip(['submit', 'approve', 'reject', 'revise', 'purchase_complete', 'purchase_reopen', 'forward', 'complete', 'delete'])); ?>
        <?php if ($hasAction): ?>
            <div class="card border-0 shadow-sm mb-3" id="aksi-tolak">
                <div class="card-body" id="aksi-teruskan">
                    <div class="card-section-title mb-2">Tindakan</div>
                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <?php if (!empty($actions['submit'])) { $btn('submit', 'Submit untuk Approval', 'bi-send', 'btn-success', 'Ajukan Request Budget ini untuk approval?'); } ?>
                        <?php if (!empty($actions['revise'])) { $btn('revise', 'Revisi', 'bi-arrow-counterclockwise', 'btn-warning', 'Kembalikan ke Draft untuk direvisi?'); } ?>
                        <?php if (!empty($actions['approve'])): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#rbApproveModal"><i class="bi bi-check-circle"></i> Setujui</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['reject'])): ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rbRejectModal"><i class="bi bi-x-circle"></i> Tolak</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['purchase_complete'])) { $btn('purchaseComplete', 'Tandai Data Purchase Lengkap', 'bi-clipboard-check', 'btn-info', 'Tandai data Purchase sudah lengkap?'); } ?>
                        <?php if (!empty($actions['purchase_reopen'])) { $btn('purchaseReopen', 'Buka Kembali Data Purchase', 'bi-unlock', 'btn-outline-secondary', 'Buka kembali untuk mengubah dokumen?'); } ?>
                        <?php if (!empty($actions['forward'])): ?>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rbForwardModal"><i class="bi bi-box-arrow-in-right"></i> Ajukan ke Purwati / Nissa</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['complete'])) { $btn('complete', 'Selesaikan', 'bi-check2-all', 'btn-dark', 'Selesaikan Request Budget ini?'); } ?>
                        <?php if (!empty($actions['delete'])) { $btn('delete', 'Hapus', 'bi-trash', 'btn-outline-danger', 'Hapus Request Budget ' . $rb['request_number'] . '?'); } ?>
                    </div>
                    <?php if ($status === RequestBudget::APPROVED && empty($actions['purchase_complete'])): ?>
                        <div class="form-text mt-2">Seluruh approval selesai. Menunggu Andy melengkapi PO/Invoice/dokumen.</div>
                    <?php elseif ($status === RequestBudget::PURCHASE_COMPLETED && empty($actions['forward'])): ?>
                        <div class="form-text mt-2">Data sudah dilengkapi. Hanya Andy yang dapat mengajukannya ke Purwati/Nissa.</div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($status === RequestBudget::APPROVED): ?>
            <div class="alert alert-info small mb-3">Seluruh approval selesai. Menunggu Andy melengkapi PO/Invoice/dokumen lalu mengajukannya ke Purwati/Nissa.</div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-2">Pengajuan</div>
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Diajukan</dt><dd class="col-7"><?= e($fmtDt($rb['submitted_at'])) ?></dd>
                    <dt class="col-5 text-muted">Seluruh approval</dt><dd class="col-7"><?= e($rb['approved_by_name'] ?? '-') ?><?= !empty($rb['approved_by_role']) ? '<br><span class="text-muted">Role: ' . e($rb['approved_by_role']) . '</span>' : '' ?><br><span class="text-muted"><?= e($fmtDt($rb['approved_at'])) ?></span></dd>
                    <dt class="col-5 text-muted">Submitted by</dt><dd class="col-7"><?= e($rb['forwarded_by_name'] ?? '-') ?><?= !empty($rb['forwarded_by_role']) ? '<br><span class="text-muted">Role: ' . e($rb['forwarded_by_role']) . '</span>' : '' ?></dd>
                    <dt class="col-5 text-muted">Submitted to</dt><dd class="col-7"><?= e($rb['forwarded_to'] ?? '-') ?></dd>
                    <dt class="col-5 text-muted">Submitted at</dt><dd class="col-7"><?= e($fmtDt($rb['forwarded_at'])) ?></dd>
                    <dt class="col-5 text-muted">Selesai</dt><dd class="col-7"><?= e($rb['completed_by_name'] ?? '-') ?><br><span class="text-muted"><?= e($fmtDt($rb['completed_at'])) ?></span></dd>
                </dl>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="card-section-title mb-3">Riwayat</div>
                <?php if (empty($history)): ?><div class="text-muted small">Belum ada riwayat.</div><?php endif; ?>
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

<?php if (!empty($actions['approve'])): ?>
    <div class="modal fade" id="rbApproveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="<?= e($postUrl('approve')) ?>" class="modal-content">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="modal-header"><h5 class="modal-title">Setujui Request Budget</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Catatan approval (opsional)</label>
                    <textarea name="note" class="form-control" rows="2" maxlength="500"></textarea>
                    <div class="form-text">Approval Anda dicatat sebagai: <?= e(currentUserName()) ?> (<?= e((new RequestBudget())->roleNameOf(currentUserId())) ?>).</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-success">Setujui</button></div>
            </form>
        </div>
    </div>
<?php endif; ?>
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
                <div class="modal-header"><h5 class="modal-title">Ajukan ke Purwati / Nissa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <label class="form-label">Tujuan Pengajuan <span class="text-danger">*</span></label>
                    <?php foreach (RequestBudget::FORWARD_DESTINATIONS as $i => $dest): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="forward_to" id="fwd<?= $i ?>" value="<?= e($dest) ?>" required>
                            <label class="form-check-label" for="fwd<?= $i ?>"><?= e($dest) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($forwardProblems)): ?>
                        <div class="alert alert-warning small mt-3 mb-0">Belum bisa diajukan: <?= e(implode(' ', $forwardProblems)) ?></div>
                    <?php endif; ?>
                    <div class="form-text mt-2">Diajukan oleh: <?= e(currentUserName()) ?>. Hanya mencatat alur &mdash; tidak membuat transaksi Kas/Pembayaran.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Ajukan</button></div>
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
