<?php
$id = (int) $order['id'];
$st = $order['display_status'];
$isCancelled = $order['status'] === SalesOrder::CANCELLED;
$isDraft = $order['status'] === SalesOrder::DRAFT;
$isOpen = !$isCancelled && !$isDraft;
$fq = fn($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',') ?: '0';
$hasRemaining = (float) $order['remaining_qty'] > SalesOrder::EPS;
$hasInvoiceable = (float) $order['invoice_gap'] > SalesOrder::EPS;
$invStatus = SalesOrder::invoiceStatus($order);
$dnStatus = SalesOrder::deliveryStatus($order);
$noActiveDocs = true;
foreach ($fulfillments as $f) {
    if ($f['status'] === 'aktif') { $noActiveDocs = false; break; }
}
if ($invoices) { $noActiveDocs = false; }
$canProcess = can('sales_order', 'process');
$canDnCreate = $canProcess && can('delivery_note', 'create');

$postForm = function (string $action, array $fields, string $label, string $icon, string $confirm, string $class = 'btn btn-outline-secondary btn-sm') {
    echo '<form method="POST" action="' . e(BASE_URL . '/index.php?module=sales_order&action=' . $action) . '" class="d-inline so-confirm" data-message="' . e($confirm) . '">' . csrfField();
    foreach ($fields as $k => $v) {
        echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    echo '<button type="submit" class="' . e($class) . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form>';
};
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">Order Penjualan <?= e($order['order_number']) ?></h4>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge bg-<?= e(SalesOrder::statusBadge($st)) ?> fs-6"><?= e(SalesOrder::statusLabel($st)) ?></span>
            <small class="text-muted">dibuat <?= e(formatTanggal($order['order_date'])) ?> oleh <?= e($order['created_by_name'] ?? '-') ?></small>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/sales_order" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
        <?php if (can('sales_order', 'print')): ?>
            <a href="<?= BASE_URL ?>/sales_order/print/<?= $id ?>" target="_blank" class="btn btn-outline-dark btn-sm"><i class="bi bi-printer"></i> Cetak</a>
        <?php endif; ?>
        <?php if ($canEditNow): ?>
            <a href="<?= BASE_URL ?>/sales_order/edit/<?= $id ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <?php if ($isDraft && can('sales_order', 'edit')): ?>
            <?php $postForm('release', ['id' => $id], 'Kirim ke Accounting', 'bi-send', "Kirim order {$order['order_number']} ke Accounting untuk diproses?", 'btn btn-success btn-sm'); ?>
        <?php endif; ?>
        <?php if ($isOpen && $canProcess && $hasRemaining): ?>
            <a href="<?= BASE_URL ?>/sales_order/process/<?= $id ?>" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-up"></i> Proses Pemenuhan</a>
        <?php endif; ?>
        <?php if ($isOpen && $canProcess && $hasInvoiceable): ?>
            <a href="<?= BASE_URL ?>/sales_order/invoice/<?= $id ?>" class="btn btn-warning btn-sm"><i class="bi bi-receipt"></i> Terbitkan Invoice</a>
        <?php endif; ?>
        <?php if (!$isCancelled && can('sales_order', 'cancel') && $noActiveDocs): ?>
            <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#soCancelModal"><i class="bi bi-x-circle"></i> Batalkan Order</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($isCancelled): ?>
    <div class="alert alert-secondary"><i class="bi bi-slash-circle"></i> Order ini <strong>dibatalkan</strong><?= $order['cancelled_at'] ? ' pada ' . e(formatTanggal($order['cancelled_at'])) : '' ?>.
        Alasan: <?= e($order['cancel_reason'] ?? '-') ?></div>
<?php elseif ($isDraft): ?>
    <div class="alert alert-info"><i class="bi bi-file-earmark"></i> Order masih <strong>Draft</strong> &mdash; belum bisa diproses Accounting sampai dikirim.</div>
<?php endif; ?>

<?php if (!empty($shortages)): ?>
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle"></i> Stok belum mencukupi untuk sebagian pesanan</div>
        <ul class="mb-1 ps-3">
            <?php foreach ($shortages as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
        </ul>
        <div class="small">Pesanan tetap tersimpan dan dapat diproses setelah stok tersedia. Stok di atas dibaca langsung dari database saat halaman ini dibuka.</div>
    </div>
<?php elseif ($isOpen && !$hasRemaining): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Seluruh barang pada order ini telah terpenuhi.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
        <div class="small text-muted">Total Item Dipesan</div>
        <div class="fs-5 fw-bold"><?= e($fq($order['ordered_qty'])) ?></div>
    </div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
        <div class="small text-muted">Total Item Terpenuhi</div>
        <div class="fs-5 fw-bold text-success"><?= e($fq($order['fulfilled_qty'])) ?></div>
    </div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
        <div class="small text-muted">Total Item Belum Terpenuhi</div>
        <div class="fs-5 fw-bold <?= $hasRemaining && !$isCancelled ? 'text-danger' : '' ?>"><?= e($fq($order['remaining_qty'])) ?></div>
    </div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
        <div class="small text-muted">Status Stok</div>
        <?php if ($order['stock_status']): ?>
            <span class="badge bg-<?= e(SalesOrder::stockBadge($order['stock_status'])) ?>"><?= e(SalesOrder::stockLabel($order['stock_status'])) ?></span>
        <?php else: ?><span class="text-muted">-</span><?php endif; ?>
    </div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
        <div class="small text-muted">Status Invoice</div>
        <span class="badge bg-<?= e(SalesOrder::DOC_BADGES[$invStatus]) ?>"><?= e(SalesOrder::DOC_LABELS[$invStatus]) ?></span>
        <div class="small text-muted mt-1">ditagih <?= e($fq($order['invoiced_qty'])) ?> unit</div>
    </div></div></div>
    <div class="col-6 col-md-4 col-xl-2"><div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
        <div class="small text-muted">Status Surat Jalan</div>
        <span class="badge bg-<?= e(SalesOrder::DOC_BADGES[$dnStatus]) ?>"><?= e(SalesOrder::DOC_LABELS[$dnStatus]) ?></span>
        <div class="small text-muted mt-1"><?= (int) $order['sj_rows_on_dn'] ?>/<?= (int) $order['sj_rows_total'] ?> baris keluar</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="card-section-title mb-3">Informasi Order</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="small text-muted">Customer</div>
                <div class="fw-semibold"><?= e($order['client_name']) ?></div>
                <div class="small"><?= nl2br(e($order['customer_address'] ?? '')) ?></div>
                <?php if (!empty($order['customer_phone'])): ?><div class="small text-muted">Telp. <?= e($order['customer_phone']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Nomor PO Customer</div>
                <div class="fw-semibold"><?= e($order['po_number']) ?></div>
                <div class="small text-muted mt-2">Tanggal PO Customer</div>
                <div><?= e(formatTanggal($order['po_date'])) ?></div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">No. Order Internal / Tanggal</div>
                <div class="fw-semibold"><?= e($order['order_number']) ?> &middot; <?= e(formatTanggal($order['order_date'])) ?></div>
                <div class="small text-muted mt-2">Catatan</div>
                <div class="small"><?= $order['notes'] ? nl2br(e($order['notes'])) : '<span class="text-muted">-</span>' ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-0">
        <div class="p-3 pb-2"><div class="card-section-title mb-0">Barang Pesanan</div></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">No</th>
                        <th>Kode</th>
                        <th>Nama Barang / Spesifikasi</th>
                        <th>Satuan</th>
                        <th class="text-end">Dipesan</th>
                        <th class="text-end">Stok Tersedia</th>
                        <th class="text-end">Terpenuhi</th>
                        <th class="text-end">Belum Terpenuhi</th>
                        <th class="text-end">Ditagih</th>
                        <th class="text-end">Harga</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $it):
                        $rem = (float) $it['remaining_qty'];
                        $stock = (float) $it['stock_qty'];
                        $short = $rem > SalesOrder::EPS && $stock + SalesOrder::EPS < $rem;
                    ?>
                        <tr>
                            <td data-label="No"><?= $i + 1 ?></td>
                            <td data-label="Kode"><?= e($it['item_code']) ?></td>
                            <td data-label="Nama Barang">
                                <div class="fw-semibold"><?= e($it['item_name']) ?></div>
                                <?php if (trim((string) $it['specification']) !== ''): ?><div class="small text-muted"><?= e(preg_replace('/\s+/', ' ', trim($it['specification']))) ?></div><?php endif; ?>
                            </td>
                            <td data-label="Satuan"><?= e($it['unit']) ?></td>
                            <td data-label="Dipesan" class="text-end"><?= e($fq($it['qty'])) ?></td>
                            <td data-label="Stok Tersedia" class="text-end <?= $short && !$isCancelled ? 'text-danger fw-semibold' : '' ?>">
                                <?php if ($isCancelled || $rem <= SalesOrder::EPS): ?><span class="text-muted"><?= e($fq($stock)) ?></span><?php else: ?><?= e($fq($stock)) ?><?php endif; ?>
                            </td>
                            <td data-label="Terpenuhi" class="text-end text-success"><?= e($fq($it['fulfilled_qty'])) ?></td>
                            <td data-label="Belum Terpenuhi" class="text-end <?= $rem > SalesOrder::EPS && !$isCancelled ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                <?= e($fq($rem)) ?>
                                <?php if ($short && !$isCancelled): ?>
                                    <div class="small fw-normal">kurang <?= e($fq($rem - $stock)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Ditagih" class="text-end"><?= e($fq($it['invoiced_qty'])) ?></td>
                            <td data-label="Harga" class="text-end">
                                <?= e(formatRupiah($it['net_price'])) ?>
                                <?php if ((float) $it['discount_percent'] > 0): ?><div class="small text-muted">diskon <?= e($fq($it['discount_percent'])) ?>%</div><?php endif; ?>
                            </td>
                            <td data-label="Subtotal" class="text-end"><?= e(formatRupiah($it['subtotal'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="10" class="text-end">Subtotal</td><td class="text-end"><?= e(formatRupiah($order['subtotal'])) ?></td></tr>
                    <tr><td colspan="10" class="text-end">PPN (<?= e($fq($order['ppn_percent'])) ?>%)</td><td class="text-end"><?= e(formatRupiah($order['ppn_amount'])) ?></td></tr>
                    <tr class="table-light"><td colspan="10" class="text-end fw-bold">Total Pesanan</td><td class="text-end fw-bold"><?= e(formatRupiah($order['total_amount'])) ?></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="card-section-title mb-3">Pemenuhan &amp; Pengeluaran Barang</div>
                <?php if (!$fulfillments): ?>
                    <div class="text-muted small">Belum ada barang yang dikeluarkan. Stok fisik baru berkurang setelah Accounting memproses pemenuhan.</div>
                <?php endif; ?>
                <?php foreach ($fulfillments as $f):
                    $cancelledF = $f['status'] === 'dibatalkan';
                    $rowsNoDn = array_filter($f['rows'], fn($r) => empty($r['delivery_note_id']));
                ?>
                    <div class="border rounded p-2 mb-2 <?= $cancelledF ? 'bg-light text-muted' : '' ?>">
                        <div class="d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <span class="fw-semibold <?= $cancelledF ? 'text-decoration-line-through' : '' ?>"><?= e($f['stock_out_number'] ?? '-') ?></span>
                                <?php if ($cancelledF): ?><span class="badge bg-secondary">Dikoreksi</span><?php endif; ?>
                                <div class="small"><?= e(formatTanggal($f['fulfillment_date'])) ?> &middot; <?= e($f['created_by_name'] ?? '-') ?></div>
                            </div>
                            <?php if (!$cancelledF): ?>
                                <div class="d-flex gap-1 flex-wrap">
                                    <?php if ($canDnCreate && $rowsNoDn): ?>
                                        <a href="<?= BASE_URL ?>/index.php?module=sales_order&action=deliveryNote&fulfillment_id=<?= (int) $f['id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-truck"></i> Buat Surat Jalan</a>
                                    <?php endif; ?>
                                    <?php if (can('sales_order', 'reverse')): ?>
                                        <button type="button" class="btn btn-outline-danger btn-sm so-reverse-btn" data-id="<?= (int) $f['id'] ?>" data-number="<?= e($f['stock_out_number'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#soReverseModal"><i class="bi bi-arrow-counterclockwise"></i> Koreksi</button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <ul class="small mb-0 mt-1 ps-3">
                            <?php foreach ($f['rows'] as $r): ?>
                                <li>
                                    <?= e($r['item_name']) ?> &times; <?= e($fq($r['qty'])) ?> <?= e($r['unit']) ?>
                                    <span class="text-muted">(sumber: <?= $r['stock_scope'] === 'kantor' ? 'Stok Kantor' : e($r['source_project'] ?: 'Stok Proyek') ?>)</span>
                                    <?php if (!empty($r['delivery_number'])): ?>
                                        <a href="<?= BASE_URL ?>/delivery_note/print/<?= (int) $r['delivery_note_id'] ?>" target="_blank" class="badge bg-info text-dark text-decoration-none"><?= e($r['delivery_number']) ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($cancelledF): ?><li>Alasan koreksi: <?= e($f['cancel_reason'] ?? '-') ?> (oleh <?= e($f['cancelled_by_name'] ?? '-') ?>)</li><?php endif; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="card-section-title mb-2">Invoice Keluar</div>
                <?php if (!$invoices): ?>
                    <div class="text-muted small">Belum ada invoice dari order ini.</div>
                <?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-1 gap-2 flex-wrap">
                        <div>
                            <a href="<?= BASE_URL ?>/sales_invoice/detail/<?= (int) $inv['id'] ?>" class="fw-semibold text-decoration-none"><?= e($inv['invoice_number']) ?></a>
                            <div class="small text-muted"><?= e(formatTanggal($inv['invoice_date'])) ?> &middot; <?= e(formatRupiah($inv['total_amount'])) ?>
                                <?php if ($inv['is_billed']): ?><span class="badge bg-success">Sudah tertagih</span><?php endif; ?></div>
                        </div>
                        <?php if ($canProcess && !$inv['is_billed']): ?>
                            <?php $postForm('voidInvoice', ['invoice_id' => (int) $inv['id']], 'Batalkan', 'bi-x-circle', "Batalkan invoice {$inv['invoice_number']}? Jumlahnya kembali bisa ditagih.", 'btn btn-outline-danger btn-sm'); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="card-section-title mb-2">Surat Jalan</div>
                <?php if (!$deliveryNotes): ?>
                    <div class="text-muted small">Belum ada Surat Jalan dari order ini.</div>
                <?php endif; ?>
                <?php foreach ($deliveryNotes as $dn): ?>
                    <div class="d-flex justify-content-between border-bottom py-1">
                        <a href="<?= BASE_URL ?>/delivery_note/print/<?= (int) $dn['id'] ?>" target="_blank" class="fw-semibold text-decoration-none"><?= e($dn['delivery_number']) ?></a>
                        <span class="small text-muted"><?= e(formatTanggal($dn['delivery_date'])) ?> &middot; <?= (int) $dn['item_count'] ?> baris</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="card-section-title mb-3">Riwayat Order</div>
        <?php if (!$history): ?>
            <div class="text-muted small">Belum ada riwayat.</div>
        <?php endif; ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($history as $h): ?>
                <li class="border-bottom py-2">
                    <div class="d-flex justify-content-between flex-wrap gap-1">
                        <span class="fw-semibold"><?= e(SalesOrder::HISTORY_LABELS[$h['action']] ?? $h['action']) ?></span>
                        <span class="small text-muted"><?= e(formatTanggal($h['created_at'])) ?> <?= e(substr((string) $h['created_at'], 11, 5)) ?> &middot; <?= e($h['full_name'] ?? '-') ?><?= !empty($h['role_name']) ? ' (' . e($h['role_name']) . ')' : '' ?></span>
                    </div>
                    <?php if (!empty($h['notes'])): ?><div class="small"><?= e($h['notes']) ?></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<?php if (!$isCancelled && can('sales_order', 'cancel') && $noActiveDocs): ?>
<div class="modal fade" id="soCancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=sales_order&action=cancel" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="modal-header"><h5 class="modal-title">Batalkan Order <?= e($order['order_number']) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label">Alasan pembatalan <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" maxlength="255" required></textarea>
                <div class="form-text">Order yang dibatalkan tidak bisa diproses lagi. Stok tidak terpengaruh karena belum ada barang yang dikeluarkan.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button><button type="submit" class="btn btn-danger">Batalkan Order</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (can('sales_order', 'reverse')): ?>
<div class="modal fade" id="soReverseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=sales_order&action=reverse" class="modal-content">
            <?= csrfField() ?>
            <input type="hidden" name="fulfillment_id" id="soReverseId" value="">
            <div class="modal-header"><h5 class="modal-title">Koreksi Pemenuhan <span id="soReverseNumber"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-warning small">Stok seluruh barang pada pemenuhan ini <strong>dikembalikan</strong> dan jumlahnya kembali menjadi sisa pesanan.
                    Ditolak otomatis bila barang sudah masuk Surat Jalan atau sudah ditagih di Invoice Keluar.</div>
                <label class="form-label">Alasan koreksi <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" maxlength="255" required></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Tutup</button><button type="submit" class="btn btn-danger">Koreksi &amp; Kembalikan Stok</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.so-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, lanjutkan').then(function (ok) { if (ok) { form.submit(); } });
        });
    });
    document.querySelectorAll('.so-reverse-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('soReverseId').value = btn.dataset.id;
            document.getElementById('soReverseNumber').textContent = btn.dataset.number;
        });
    });
});
</script>
