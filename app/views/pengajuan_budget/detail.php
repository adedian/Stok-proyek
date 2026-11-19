<?php
/** Detail pengajuan. Variabel: $pb, $items, $history, $actions */
$id = (int) $pb['id'];
$postUrl = fn(string $a) => BASE_URL . '/index.php?module=pengajuan_budget&action=' . $a;
$total = array_sum(array_map(fn($r) => (float) $r['amount'], $items));
$byBank = [];
foreach ($items as $it) {
    $byBank[$it['source_bank_id']]['name'] = MasterBank::reportLabel($it);
    $byBank[$it['source_bank_id']]['total'] = ($byBank[$it['source_bank_id']]['total'] ?? 0) + (float) $it['amount'];
}
$canViewPo = can('purchase_order', 'view');
$poLink = fn(int $poId, string $no) => $canViewPo
    ? '<a href="' . BASE_URL . '/purchase_order/detail/' . $poId . '" target="_blank" rel="noopener">' . e($no) . '</a>'
    : e($no);
// Ringkasan PO terkait = SNAPSHOT saat pengajuan dibuat (tidak berubah walau PO diedit sesudahnya).
$poGroups = [];
foreach ($items as $it) {
    if (empty($it['purchase_order_id'])) {
        continue;
    }
    $k = (int) $it['purchase_order_id'];
    if (!isset($poGroups[$k])) {
        $snap = !empty($it['po_snapshot']) ? json_decode($it['po_snapshot'], true) : null;
        $poGroups[$k] = ['no' => $it['po_number'], 'snap' => is_array($snap) ? $snap : null, 'amount' => 0.0, 'lines' => []];
    }
    $poGroups[$k]['amount'] += (float) $it['amount'];
    $poGroups[$k]['lines'][] = (int) $it['line_no'];
}
$actionForm = function (string $action, string $label, string $icon, string $btnClass, ?string $confirm) use ($postUrl, $id) {
    echo '<form method="POST" action="' . e($postUrl($action)) . '" class="d-inline pb-confirm"' . ($confirm ? ' data-message="' . e($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn ' . $btnClass . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form> ';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Pengajuan Budget <?= e($pb['pengajuan_number']) ?></h4>
        <small class="text-muted">Dibuat oleh <?= e($pb['created_by_name']) ?> &middot; <?= e(formatTanggal($pb['pengajuan_date'])) ?></small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="badge bg-<?= e(PengajuanBudget::statusBadge($pb['status'])) ?> fs-6 align-self-center"><?= e(PengajuanBudget::statusLabel($pb['status'])) ?></span>
        <a href="<?= BASE_URL ?>/pengajuan_budget" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<?php if ($pb['status'] === PengajuanBudget::DIREALISASIKAN): ?>
    <div class="alert alert-success py-2"><i class="bi bi-lock"></i> Pengajuan sudah <strong>Direalisasikan</strong> (<?= e(formatTanggal(substr((string) $pb['realized_at'], 0, 10))) ?><?= $pb['realized_by_name'] ? ', oleh ' . e($pb['realized_by_name']) : '' ?>) &mdash; terkunci, tidak bisa diedit, dibatalkan, atau dihapus.</div>
<?php elseif ($pb['status'] === PengajuanBudget::DIBATALKAN): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-x-octagon"></i> Dibatalkan<?= $pb['cancelled_by_name'] ? ' oleh ' . e($pb['cancelled_by_name']) : '' ?>. Alasan: <?= e($pb['cancel_reason']) ?></div>
<?php endif; ?>

<?php if (!empty($pb['notes'])): ?>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="card-section-title mb-1">Catatan Umum</div><div style="white-space:pre-line"><?= e($pb['notes']) ?></div></div></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-2">Daftar Pembayaran</div>
        <style>
            /* Baris ringkas satu baris; kolom panjang digeser ke kanan (scroll horizontal), bukan menumpuk */
            .pb-detail-table { min-width: 1650px; }
            .pb-detail-table th, .pb-detail-table td { white-space: nowrap; }
            .pb-detail-table td { padding-top: .3rem; padding-bottom: .3rem; }
            .pb-detail-table td.pb-keterangan { white-space: nowrap; }
            .pb-detail-table thead th:first-child, .pb-detail-table tbody td:first-child { position: sticky; left: 0; z-index: 1; background: var(--bs-body-bg, #fff); }
            .pb-detail-table thead th:first-child { background: var(--bs-light, #f8f9fa); }
        </style>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 pb-detail-table" data-no-cards>
                <thead class="table-light">
                    <tr><th style="width:40px">No</th><th>Rekening Sumber</th><th>Jenis Transaksi</th><th>Rekening Tujuan</th><th class="text-end">Jumlah</th><th>Keterangan</th><th>Kategori</th><th>Project</th><th>No. PO</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td data-label="No"><?= $i + 1 ?></td>
                            <td data-label="Rekening Sumber"><?= e(MasterBank::reportLabel($it)) ?></td>
                            <td data-label="Jenis"><?= e($it['jenis_transaksi']) ?></td>
                            <td data-label="Tujuan"><?= e($it['tujuan']) ?></td>
                            <td data-label="Jumlah" class="text-end"><?= e(formatRupiah($it['amount'])) ?></td>
                            <td data-label="Keterangan" class="pb-keterangan"><?= e($it['keterangan']) ?></td>
                            <td data-label="Kategori"><?= e($it['category_name'] ?? '-') ?></td>
                            <td data-label="Project"><?= e($it['project_name'] ?? '-') ?></td>
                            <td data-label="No. PO"><?= !empty($it['purchase_order_id']) ? $poLink((int) $it['purchase_order_id'], (string) $it['po_number']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <?php foreach ($byBank as $b): ?>
                        <tr><td colspan="4" class="text-end">Subtotal <?= e($b['name']) ?></td><td class="text-end"><?= e(formatRupiah($b['total'])) ?></td><td colspan="4"></td></tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold"><td colspan="4" class="text-end">TOTAL KESELURUHAN</td><td class="text-end"><?= e(formatRupiah($total)) ?></td><td colspan="4"></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if ($poGroups): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-1">PO Terkait</div>
        <div class="small text-muted mb-2">Data PO di bawah adalah <strong>snapshot saat pengajuan dibuat</strong>; perubahan PO sesudahnya tidak mengubah pengajuan ini.</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" data-no-cards>
                <thead class="table-light"><tr><th>No. PO</th><th>Tgl. PO</th><th>Supplier</th><th>Project</th><th class="text-end">Total PO</th><th class="text-end">Diajukan di Pengajuan Ini</th><th>Baris</th></tr></thead>
                <tbody>
                <?php foreach ($poGroups as $poId => $g): $sn = $g['snap']; $cur = $sn['currency'] ?? 'IDR'; ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= $poLink((int) $poId, (string) $g['no']) ?></td>
                        <td class="text-nowrap"><?= $sn ? e(formatTanggal($sn['po_date'])) : '-' ?></td>
                        <td><?= e($sn['supplier'] ?? '-') ?></td>
                        <td><?= e($sn['project'] ?? '-') ?></td>
                        <td class="text-end text-nowrap"><?= $sn ? e($cur === 'IDR' ? formatRupiah($sn['total']) : $cur . ' ' . number_format((float) $sn['total'], 2)) : '-' ?></td>
                        <td class="text-end text-nowrap"><?= e(formatRupiah($g['amount'])) ?></td>
                        <td class="text-nowrap"><?= e(implode(', ', $g['lines'])) ?></td>
                    </tr>
                    <?php if ($sn && !empty($sn['items'])): ?>
                    <tr><td colspan="7" class="p-0 border-0">
                        <details class="px-2 pb-2"><summary class="small text-muted">Rincian item PO (<?= count($sn['items']) ?> item)</summary>
                            <table class="table table-sm table-bordered mb-0 mt-1 small" data-no-cards>
                                <thead class="table-light"><tr><th>Item</th><th class="text-end">Qty</th><th>Sat.</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
                                <tbody>
                                <?php foreach ($sn['items'] as $pi): ?>
                                    <tr><td><?= e($pi['name']) ?></td><td class="text-end"><?= e(rtrim(rtrim(number_format((float) $pi['qty'], 2, '.', ','), '0'), '.')) ?></td><td><?= e($pi['unit']) ?></td><td class="text-end"><?= e(number_format((float) $pi['price'], 2, '.', ',')) ?></td><td class="text-end"><?= e(number_format((float) $pi['subtotal'], 2, '.', ',')) ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </details>
                    </td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($actions): ?>
<div class="card border-0 shadow-sm mb-3" id="aksi">
    <div class="card-body">
        <div class="card-section-title mb-2">Aksi</div>
        <?php
        if (!empty($actions['edit'])) { echo '<a href="' . BASE_URL . '/pengajuan_budget/edit/' . $id . '" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a> '; }
        if (!empty($actions['submit'])) { $actionForm('submit', 'Ajukan', 'bi-send', 'btn-primary', 'Ajukan pengajuan ' . $pb['pengajuan_number'] . '?'); }
        if (!empty($actions['revert'])) { $actionForm('revert', 'Kembalikan ke Draft', 'bi-arrow-counterclockwise', 'btn-outline-secondary', 'Kembalikan ' . $pb['pengajuan_number'] . ' ke Draft agar bisa diedit?'); }
        if (!empty($actions['realize'])) { $actionForm('realize', 'Tandai Direalisasikan', 'bi-check2-circle', 'btn-success', 'Tandai ' . $pb['pengajuan_number'] . ' sebagai Direalisasikan? Setelah ini pengajuan terkunci (tidak bisa diedit/dibatalkan/dihapus).'); }
        if (!empty($actions['delete'])) { $actionForm('delete', 'Hapus', 'bi-trash', 'btn-outline-danger', 'Hapus pengajuan ' . $pb['pengajuan_number'] . '?'); }
        if (!empty($actions['unrealize'])) { echo '<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#unrealizeModal"><i class="bi bi-arrow-counterclockwise"></i> Batalkan Realisasi</button> '; }
        ?>
        <?php if (!empty($actions['cancel'])): ?>
            <form method="POST" action="<?= e($postUrl('cancel')) ?>" class="mt-3 row g-2 align-items-end pb-confirm" data-message="Batalkan pengajuan <?= e($pb['pengajuan_number']) ?>?">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <div class="col-12 col-md-8">
                    <label class="form-label small text-muted mb-1">Alasan Pembatalan <span class="text-danger">*</span></label>
                    <input type="text" name="cancel_reason" class="form-control form-control-sm" maxlength="500" required placeholder="Wajib diisi">
                </div>
                <div class="col-12 col-md-4"><button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-octagon"></i> Batalkan Pengajuan</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="card-section-title mb-2">Riwayat Perubahan</div>
        <?php if (empty($history)): ?><div class="text-muted small">Belum ada riwayat.</div><?php endif; ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($history as $h): ?>
                <li class="border-bottom py-2">
                    <span class="text-muted small"><?= e(date('d/m/Y H:i', strtotime($h['created_at']))) ?></span>
                    <strong><?= e($h['user_name'] ?? '-') ?></strong>
                    <span class="badge bg-light text-dark border"><?= e(activityLogActionLabel($h['action'])) ?></span>
                    <?php if ($h['old_status'] !== $h['new_status'] && $h['new_status']): ?>
                        <span class="small text-muted"><?= e(PengajuanBudget::statusLabel($h['old_status'])) ?> &rarr; <?= e(PengajuanBudget::statusLabel($h['new_status'])) ?></span>
                    <?php endif; ?>
                    <?php if ($h['notes']): ?><div class="small"><?= e($h['notes']) ?></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if (!empty($actions['unrealize'])): ?>
<div class="modal fade" id="unrealizeModal" tabindex="-1" aria-labelledby="unrealizeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= e($postUrl('unrealize')) ?>" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="modal-header">
                <h5 class="modal-title" id="unrealizeModalLabel">Batalkan Realisasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Batalkan status <strong>Direalisasikan</strong> pada pengajuan <strong><?= e($pb['pengajuan_number']) ?></strong>? Status akan kembali ke <strong>Diajukan</strong> dan pengajuan bisa diproses ulang.</p>
                <ul class="small text-muted ps-3 mb-3">
                    <li>Pengajuan &amp; catatan realisasi lama <strong>tidak dihapus</strong> &mdash; tercatat di Riwayat Perubahan.</li>
                    <li>Modul ini tidak membuat transaksi Kas/Bank, jadi saldo Bank tidak berubah. Jika Anda sudah mencatat Bank Keluar secara manual untuk realisasi ini, koreksi lewat menu Kas / Bank.</li>
                </ul>
                <label class="form-label" for="unrealizeReason">Alasan pembatalan <span class="text-danger">*</span></label>
                <textarea id="unrealizeReason" name="unrealize_reason" class="form-control" rows="3" minlength="3" maxlength="500" required placeholder="Wajib diisi"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-arrow-counterclockwise"></i> Ya, Batalkan Realisasi</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.pb-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed) { return; }
            e.preventDefault();
            if (!form.checkValidity()) { form.reportValidity(); return; }
            confirmAction(form.dataset.message, 'Ya, lanjutkan').then(function (ok) {
                if (ok) { form.dataset.confirmed = '1'; form.submit(); }
            });
        });
    });
});
</script>
