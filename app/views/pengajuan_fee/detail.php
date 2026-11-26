<?php
/** Detail Pengajuan Fee. Variabel: $f, $items, $history, $actions */
$id = (int) $f['id'];
$postUrl = fn(string $a) => BASE_URL . '/index.php?module=pengajuan_fee&action=' . $a;
$nf = fn($v) => number_format((float) $v, 2, '.', ',');
$pct = fn($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
$actionForm = function (string $action, string $label, string $icon, string $btnClass, ?string $confirm) use ($postUrl, $id) {
    echo '<form method="POST" action="' . e($postUrl($action)) . '" class="d-inline fee-confirm"' . ($confirm ? ' data-message="' . e($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn ' . $btnClass . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form> ';
};
$dt = fn($v) => $v ? date('d/m/Y H:i', strtotime((string) $v)) : '-';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Pengajuan Fee <?= e($f['fee_number']) ?></h4>
        <small class="text-muted">Dibuat oleh <?= e($f['created_by_name']) ?> &middot; <?= e(formatTanggal($f['fee_date'])) ?></small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="badge bg-<?= e(PengajuanFee::statusBadge($f['status'])) ?> fs-6 align-self-center"><?= e(PengajuanFee::statusLabel($f['status'])) ?></span>
        <a href="<?= BASE_URL ?>/pengajuan_fee/print/<?= $id ?>" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer"></i> Cetak</a>
        <a href="<?= BASE_URL ?>/pengajuan_fee" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
    </div>
</div>

<?php if ($f['status'] === PengajuanFee::DISETUJUI): ?>
    <div class="alert alert-success py-2"><i class="bi bi-lock"></i> Disetujui oleh <strong><?= e($f['approved_by_name'] ?? '-') ?></strong> (<?= e($dt($f['approved_at'])) ?>) &mdash; terkunci.</div>
<?php elseif ($f['status'] === PengajuanFee::DITOLAK): ?>
    <div class="alert alert-danger py-2"><i class="bi bi-x-octagon"></i> Ditolak oleh <strong><?= e($f['rejected_by_name'] ?? '-') ?></strong> (<?= e($dt($f['rejected_at'])) ?>). Alasan: <?= e($f['reject_note']) ?></div>
<?php elseif ($f['status'] === PengajuanFee::DIAJUKAN): ?>
    <div class="alert alert-warning py-2"><i class="bi bi-hourglass-split"></i> Menunggu pemeriksaan.</div>
<?php elseif ($f['status'] === PengajuanFee::DIPERIKSA): ?>
    <div class="alert alert-info py-2"><i class="bi bi-hourglass-split"></i> Sudah diperiksa oleh <strong><?= e($f['checked_by_name'] ?? '-') ?></strong> (<?= e($dt($f['checked_at'])) ?>), menunggu persetujuan.</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-2">Pengajuan Fee</div>
        <div class="row g-2">
            <div class="col-md-4"><span class="text-muted small d-block">Invoice no.</span><?= e($f['invoice_number'] ?: '-') ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Customer</span><?= e($f['customer_name'] ?: '-') ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Sales</span><?= e($f['sales_name'] ?: '-') ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Status Tagihan</span><?= e($f['billing_status'] ?: '-') ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Penerima Fee</span><?= e($f['recipient_name'] ?: '-') ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">NPWP / NIK</span><?= e($f['recipient_tax_id'] ?: '-') ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Tanggal / Kota</span><?= e(formatTanggal($f['fee_date'])) ?><?= $f['city'] ? ' &middot; ' . e($f['city']) : '' ?></div>
            <div class="col-md-4"><span class="text-muted small d-block">Tarif</span>Fee <?= e($pct($f['fee_percent'])) ?>% dari DPP &middot; PPN <?= e($pct($f['ppn_percent'])) ?>% &middot; PPh <?= e($pct($f['pph_percent'])) ?>%</div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-2">Barang</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" data-no-cards>
                <thead class="table-light">
                    <tr><th style="width:40px">No</th><th>Barang</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Total</th><th class="text-end">Fee</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $it): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= e($it['item_name']) ?></td>
                            <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2, '.', ','), '0'), '.')) ?></td>
                            <td><?= e($it['unit'] ?? '') ?></td>
                            <td class="text-end"><?= e($nf($it['price'])) ?></td>
                            <td class="text-end"><?= e($nf($it['total'])) ?></td>
                            <td class="text-end"><?= e($nf($it['fee'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold"><td colspan="5" class="text-end">TOTAL</td><td class="text-end"><?= e($nf($f['total_amount'])) ?></td><td class="text-end"><?= e($nf($f['total_fee'])) ?></td></tr>
                    <tr><td colspan="6" class="text-end">Potong Pph <?= e($pct($f['pph_percent'])) ?>%</td><td class="text-end">-<?= e($nf($f['pph_amount'])) ?></td></tr>
                    <tr class="fw-bold"><td colspan="6" class="text-end">Total Fee diterima</td><td class="text-end"><?= e($nf($f['net_fee'])) ?></td></tr>
                </tfoot>
            </table>
        </div>
        <?php if ($f['note'] || $f['nb']): ?>
            <div class="mt-2 small">
                <?php if ($f['note']): ?><div><strong>NOTE</strong> = <?= e($f['note']) ?></div><?php endif; ?>
                <?php if ($f['nb']): ?><div><strong>NB</strong> : <?= e($f['nb']) ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($actions): ?>
<div class="card border-0 shadow-sm mb-3" id="aksi">
    <div class="card-body">
        <div class="card-section-title mb-2">Aksi</div>
        <?php
        if (!empty($actions['edit'])) { echo '<a href="' . BASE_URL . '/pengajuan_fee/edit/' . $id . '" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a> '; }
        if (!empty($actions['submit'])) { $actionForm('submit', 'Ajukan', 'bi-send', 'btn-primary', 'Ajukan ' . $f['fee_number'] . ' untuk diperiksa?'); }
        if (!empty($actions['check'])) { $actionForm('check', 'Tandai Diperiksa', 'bi-check2-square', 'btn-primary', 'Tandai ' . $f['fee_number'] . ' sudah diperiksa dan teruskan untuk persetujuan?'); }
        if (!empty($actions['approve'])) { $actionForm('approve', 'Setujui', 'bi-check2-circle', 'btn-success', 'Setujui ' . $f['fee_number'] . '? Setelah ini pengajuan terkunci.'); }
        if (!empty($actions['revise'])) { $actionForm('revise', 'Revisi', 'bi-arrow-counterclockwise', 'btn-outline-primary', 'Kembalikan ' . $f['fee_number'] . ' ke Draft untuk direvisi?'); }
        if (!empty($actions['reject'])) { echo '<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#feeRejectModal"><i class="bi bi-x-circle"></i> Tolak</button> '; }
        if (!empty($actions['delete'])) { $actionForm('delete', 'Hapus', 'bi-trash', 'btn-outline-danger', 'Hapus ' . $f['fee_number'] . '?'); }
        ?>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="card-section-title mb-2">Riwayat</div>
        <?php if (empty($history)): ?><div class="text-muted small">Belum ada riwayat.</div><?php endif; ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($history as $hrow): ?>
                <li class="border-bottom py-2">
                    <span class="text-muted small"><?= e(date('d/m/Y H:i', strtotime($hrow['created_at']))) ?></span>
                    <strong><?= e($hrow['user_name'] ?? '-') ?></strong>
                    <span class="badge bg-light text-dark border"><?= e(activityLogActionLabel($hrow['action'])) ?></span>
                    <?php if ($hrow['old_status'] !== $hrow['new_status'] && $hrow['new_status']): ?>
                        <span class="small text-muted"><?= e(PengajuanFee::statusLabel($hrow['old_status'])) ?> &rarr; <?= e(PengajuanFee::statusLabel($hrow['new_status'])) ?></span>
                    <?php endif; ?>
                    <?php if ($hrow['notes']): ?><div class="small"><?= e($hrow['notes']) ?></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if (!empty($actions['reject'])): ?>
<div class="modal fade" id="feeRejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= e($postUrl('reject')) ?>" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="modal-header">
                <h5 class="modal-title">Tolak Pengajuan Fee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Pengajuan <strong><?= e($f['fee_number']) ?></strong> ditolak. Pembuat dapat merevisi lalu mengajukan ulang.</p>
                <label class="form-label">Alasan penolakan <span class="text-danger">*</span></label>
                <textarea name="note_action" class="form-control" rows="3" minlength="3" maxlength="500" required placeholder="Wajib diisi"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> Tolak</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.fee-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed) { return; }
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, lanjutkan').then(function (ok) {
                if (ok) { form.dataset.confirmed = '1'; form.submit(); }
            });
        });
    });
});
</script>
