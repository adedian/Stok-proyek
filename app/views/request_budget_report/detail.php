<?php
$fmtDt = fn(?string $d) => $d ? formatTanggal(substr($d, 0, 10)) . ' ' . substr($d, 11, 5) : '-';
$fmtDate = fn(?string $d) => $d ? formatTanggal(substr($d, 0, 10)) : '-';
$apprBadge = ['PENDING' => ['warning text-dark', 'Menunggu'], 'APPROVED' => ['success', 'Approved'], 'REJECTED' => ['danger', 'Ditolak']];
$docLink = fn(?string $p) => $p ? '<a href="' . e(fileUrl($p)) . '" target="_blank" class="small text-decoration-none"><i class="bi bi-paperclip"></i> Lihat file</a>' : '<span class="text-muted small">-</span>';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= e($rb['request_number']) ?>
            <span class="badge bg-<?= e(RequestBudget::statusBadge($rb['status'])) ?> fs-6 align-middle"><?= e(RequestBudget::statusLabel($rb['status'])) ?></span>
        </h4>
        <small class="text-muted">Laporan Request Budget &mdash; detail lengkap (hanya lihat)</small>
    </div>
    <a href="<?= BASE_URL ?>/request_budget_report" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Laporan</a>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="card-section-title mb-2">1. Informasi Request &amp; Project</div>
            <div class="row g-3">
                <div class="col-6 col-md-4"><div class="text-muted small">No. Request</div><div class="fw-semibold"><?= e($rb['request_number']) ?></div></div>
                <div class="col-6 col-md-4"><div class="text-muted small">Tanggal</div><div class="fw-semibold"><?= e(formatTanggal($rb['request_date'])) ?></div></div>
                <div class="col-6 col-md-4"><div class="text-muted small">Periode</div><div class="fw-semibold"><?= e($rb['period_label'] ?: '-') ?></div></div>
                <div class="col-6 col-md-4"><div class="text-muted small">Project</div><div class="fw-semibold"><?= e($rb['project_name']) ?></div></div>
                <div class="col-6 col-md-4"><div class="text-muted small">Pengaju</div><div class="fw-semibold"><?= e($rb['requester_name']) ?><?= $rb['requester_role'] ? ' <span class="text-muted fw-normal">(' . e($rb['requester_role']) . ')</span>' : '' ?></div></div>
                <div class="col-12"><div class="text-muted small">Keperluan</div><div class="fw-semibold"><?= e($rb['purpose']) ?></div></div>
                <?php if (!empty($rb['description'])): ?><div class="col-12"><div class="text-muted small">Keterangan</div><div><?= nl2br(e($rb['description'])) ?></div></div><?php endif; ?>
            </div>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="card-section-title mb-2">2. Detail Budget</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0 entry-cards">
                <thead class="table-light"><tr><th>No</th><th>Barang / Kebutuhan</th><th>Spesifikasi</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Total</th><th>Keterangan</th></tr></thead>
                <tbody>
                <?php foreach ($items as $i => $it): ?>
                    <tr><td data-label="No"><?= $i + 1 ?></td><td data-label="Barang" class="fw-semibold"><?= e($it['item_name']) ?></td><td data-label="Spesifikasi"><?= e($it['description'] ?? '-') ?></td>
                        <td data-label="Qty" class="text-end"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2, ',', '.'), '0'), ',')) ?></td><td data-label="Satuan"><?= e($it['unit_name'] ?? '-') ?></td>
                        <td data-label="Harga" class="text-end"><?= formatRupiah($it['estimated_unit_price']) ?></td><td data-label="Total" class="text-end"><?= formatRupiah($it['estimated_total']) ?></td><td data-label="Keterangan"><?= e($it['notes'] ?? '-') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr class="table-light"><td colspan="6" class="text-end fw-bold">TOTAL REQUEST BUDGET</td><td class="text-end fw-bold"><?= formatRupiah($rb['total_amount']) ?></td><td></td></tr></tfoot>
            </table></div>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="card-section-title mb-2">3. Approval</div>
            <div class="row g-2">
                <?php foreach (RequestBudget::APPROVAL_SLOTS as $slot => $def): $ap = $approvals[$slot] ?? null; $bd = $apprBadge[$ap['status'] ?? 'PENDING']; ?>
                    <div class="col-md-6"><div class="border rounded p-2 h-100">
                        <div class="d-flex justify-content-between"><span class="fw-semibold small"><?= e($def['label']) ?></span><span class="badge bg-<?= e($bd[0]) ?>"><?= $ap ? e($bd[1]) : 'Belum diajukan' ?></span></div>
                        <?php if ($ap && $ap['status'] !== 'PENDING'): ?>
                            <div class="small mt-1">Oleh: <strong><?= e($ap['approver_name'] ?? '-') ?></strong> &mdash; <?= e($ap['approver_role'] ?? '-') ?></div>
                            <div class="small text-muted"><?= e($fmtDt($ap['acted_at'])) ?></div>
                            <?php if (!empty($ap['note'])): ?><div class="small">Catatan: <?= nl2br(e($ap['note'])) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </div></div>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($rb['rejection_reason'])): ?><div class="alert alert-danger small mt-2 mb-0"><strong>Ditolak:</strong> <?= nl2br(e($rb['rejection_reason'])) ?></div><?php endif; ?>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="card-section-title mb-2">4. Data Purchase</div>
            <div class="small text-muted mb-2">Dilengkapi oleh: <?= e($rb['purchase_completed_by_name'] ?? '-') ?> &middot; <?= e($fmtDt($rb['purchase_completed_at'])) ?><?= !empty($rb['purchase_notes']) ? ' &middot; Catatan: ' . e($rb['purchase_notes']) : '' ?></div>

            <div class="fw-semibold small">PO</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-2 entry-cards">
                <thead class="table-light"><tr><th>No. PO</th><th>Tanggal</th><th>Vendor</th><th class="text-end">Nominal</th><th>File</th></tr></thead><tbody>
                <?php foreach ($pos as $p): ?><tr><td data-label="No. PO"><?= e($p['po_number']) ?></td><td data-label="Tanggal"><?= e($fmtDate($p['po_date'])) ?></td><td data-label="Vendor"><?= e($p['vendor_name'] ?? '-') ?></td><td data-label="Nominal" class="text-end"><?= formatRupiah($p['amount']) ?></td><td data-label="File"><?= $docLink($p['file_path']) ?></td></tr><?php endforeach; ?>
                <?php if (!$pos): ?><tr><td colspan="5" class="text-muted small">Tidak ada PO.</td></tr><?php endif; ?></tbody></table></div>

            <div class="fw-semibold small">Invoice</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-2 entry-cards">
                <thead class="table-light"><tr><th>No. Invoice</th><th>Tanggal</th><th>Vendor</th><th class="text-end">Nominal</th><th>File</th></tr></thead><tbody>
                <?php foreach ($invoices as $v): ?><tr><td data-label="No. Invoice"><?= e($v['invoice_number']) ?></td><td data-label="Tanggal"><?= e($fmtDate($v['invoice_date'])) ?></td><td data-label="Vendor"><?= e($v['vendor_name'] ?? '-') ?></td><td data-label="Nominal" class="text-end"><?= formatRupiah($v['amount']) ?></td><td data-label="File"><?= $docLink($v['file_path']) ?></td></tr><?php endforeach; ?>
                <?php if (!$invoices): ?><tr><td colspan="5" class="text-muted small">Tidak ada Invoice.</td></tr><?php endif; ?></tbody></table></div>

            <div class="fw-semibold small">Dokumen Pendukung</div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0 entry-cards">
                <thead class="table-light"><tr><th>Nama Dokumen</th><th>Keterangan</th><th>File</th></tr></thead><tbody>
                <?php foreach ($attachments as $a): ?><tr><td data-label="Nama"><?= e($a['doc_name']) ?></td><td data-label="Keterangan"><?= e($a['description'] ?? '-') ?></td><td data-label="File"><?= $docLink($a['file_path']) ?></td></tr><?php endforeach; ?>
                <?php if (!$attachments): ?><tr><td colspan="3" class="text-muted small">Tidak ada dokumen pendukung.</td></tr><?php endif; ?></tbody></table></div>
        </div></div>

        <div class="card border-0 shadow-sm mb-3"><div class="card-body">
            <div class="card-section-title mb-2">5. Pengajuan ke Purwati / Nissa &amp; Status Akhir</div>
            <dl class="row mb-0 small">
                <dt class="col-sm-3 text-muted">Submitted by</dt><dd class="col-sm-9"><?= e($rb['forwarded_by_name'] ?? '-') ?><?= !empty($rb['forwarded_by_role']) ? ' &mdash; ' . e($rb['forwarded_by_role']) : '' ?></dd>
                <dt class="col-sm-3 text-muted">Submitted to</dt><dd class="col-sm-9"><?= e($rb['forwarded_to'] ?? '-') ?></dd>
                <dt class="col-sm-3 text-muted">Submitted at</dt><dd class="col-sm-9"><?= e($fmtDt($rb['forwarded_at'])) ?></dd>
                <dt class="col-sm-3 text-muted">Status akhir</dt><dd class="col-sm-9"><span class="badge bg-<?= e(RequestBudget::statusBadge($rb['status'])) ?>"><?= e(RequestBudget::statusLabel($rb['status'])) ?></span><?= $rb['completed_at'] ? ' &middot; selesai ' . e($fmtDt($rb['completed_at'])) : '' ?></dd>
            </dl>
        </div></div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm"><div class="card-body">
            <div class="card-section-title mb-3">Riwayat (Audit Trail)</div>
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
        </div></div>
    </div>
</div>
