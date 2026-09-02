<style>
    .gr-print-page {
        background: #fff;
        max-width: 210mm;
        margin: 0 auto;
        padding: 16mm 14mm;
        border: 1px solid #dee2e6;
        color: #212529;
        font-size: 13px;
    }
    .gr-print-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #212529;
        padding-bottom: 10px;
        margin-bottom: 14px;
    }
    .gr-print-title {
        font-size: 20px;
        font-weight: 700;
        letter-spacing: 1px;
        margin: 0;
    }
    .gr-print-meta table {
        font-size: 12.5px;
    }
    .gr-print-meta table td {
        padding: 1px 6px;
        vertical-align: top;
    }
    .gr-print-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 12px;
        font-size: 12.5px;
    }
    .gr-print-table th, .gr-print-table td {
        border: 1px solid #adb5bd;
        padding: 5px 8px;
    }
    .gr-print-table th {
        background: #f1f3f5;
        text-align: left;
    }
    .gr-print-table td.num, .gr-print-table th.num {
        text-align: right;
        white-space: nowrap;
    }
    .gr-print-notes {
        margin-top: 10px;
        font-size: 12.5px;
    }
    .gr-print-signatures {
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        margin-top: 40px;
    }
    .gr-signature-block {
        flex: 1 1 150px;
        max-width: 220px;
        text-align: center;
        font-size: 12.5px;
    }
    .gr-signature-image-wrap {
        height: 70px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 4px;
    }
    .gr-signature-image-wrap img {
        max-height: 68px;
        max-width: 100%;
        object-fit: contain;
    }
    .gr-signature-name {
        font-weight: 700;
        border-top: 1px solid #212529;
        padding-top: 2px;
        margin-top: 2px;
    }
    .gr-signature-position {
        color: #495057;
    }
    @media print {
        .gr-print-page {
            border: none;
            margin: 0;
            max-width: none;
            padding: 10mm 12mm;
        }
    }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak
    </button>
    <a href="<?= BASE_URL ?>/goods_receipt/detail/<?= (int) $receipt['id'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="gr-print-page">
    <div class="gr-print-header">
        <div>
            <p class="gr-print-title">
                <?= $receipt['receipt_type'] === 'pemakai' ? 'PENERIMAAN BARANG (BY USER)' : 'PENERIMAAN BARANG' ?>
            </p>
            <?php if ($receipt['receipt_type'] === 'offline_purchase'): ?>
                <p class="gr-print-subtitle small text-muted mb-0">Dari Pembelian Offline</p>
            <?php endif; ?>
            <div>No. Penerimaan: <strong><?= e($receipt['receipt_number']) ?></strong></div>
        </div>
        <div class="gr-print-meta">
            <table>
                <tr><td>Tanggal Datang</td><td>: <?= formatTanggal($receipt['receipt_date']) ?></td></tr>
                <?php if ($receipt['receipt_type'] === 'pemakai'): ?>
                    <tr><td>Sumber</td><td>: <?= e($receipt['source_detail'] ?? '-') ?> (Pemakai/Internal)</td></tr>
                    <tr><td>Kategori Stok</td><td>: <?= $receipt['stock_scope'] === 'kantor' ? 'Stok Kantor' : 'Stok Proyek' ?></td></tr>
                    <?php if (!empty($receipt['project_name'])): ?>
                        <tr><td>Project</td><td>: <?= e($receipt['project_name']) ?></td></tr>
                    <?php endif; ?>
                <?php elseif ($receipt['receipt_type'] === 'offline_purchase'): ?>
                    <tr><td>No. Pembelian Offline</td><td>: <?= e($receipt['po_number'] ?? '-') ?></td></tr>
                    <tr><td>Supplier</td><td>: <?= e($receipt['supplier_name'] ?? '-') ?></td></tr>
                    <tr><td>Project</td><td>: <?= e($receipt['project_name'] ?? '-') ?></td></tr>
                <?php else: ?>
                    <tr><td>No. PO</td><td>: <?= e($receipt['po_number'] ?? '-') ?></td></tr>
                    <tr><td>Pembuat PO</td><td>: <?= e($receipt['pembuat_po'] ?? '-') ?></td></tr>
                    <tr><td>Supplier</td><td>: <?= e($receipt['supplier_name'] ?? '-') ?></td></tr>
                    <tr><td>Project</td><td>: <?= e($receipt['project_name'] ?? '-') ?></td></tr>
                <?php endif; ?>
                <tr><td>Nama Penerima</td><td>: <?= e($receipt['received_by_name'] ?? '-') ?></td></tr>
            </table>
        </div>
    </div>

    <table class="gr-print-table">
        <thead>
            <tr>
                <th style="width: 28px;">No</th>
                <th>Nama Barang</th>
                <th style="width: 70px;">Satuan</th>
                <th class="num" style="width: 80px;">Qty Diterima</th>
                <th style="width: 100px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($item['item_name']) ?></td>
                    <td><?= e($item['unit']) ?></td>
                    <td class="num"><?= number_format((float) $item['qty_received'], 2, ',', '.') ?></td>
                    <td><?= e($statusLabels[$item['comparison_status']] ?? $item['comparison_status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($receipt['notes'])): ?>
        <div class="gr-print-notes">
            <strong>Catatan:</strong> <?= e($receipt['notes']) ?>
        </div>
    <?php endif; ?>

    <div class="gr-print-signatures">
        <div class="gr-signature-block">
            <div>Diterima Oleh,</div>
            <div class="gr-signature-image-wrap">
                <?php if ($receiverSignature): ?>
                    <img src="<?= BASE_URL ?>/<?= e($receiverSignature['signature_image']) ?>" alt="TTD <?= e($receipt['received_by_name']) ?>">
                <?php endif; ?>
            </div>
            <div class="gr-signature-name"><?= e($receipt['received_by_name'] ?? '-') ?></div>
            <div class="gr-signature-position"><?= e($receiverSignature['position'] ?? '') ?></div>
            <div class="gr-signature-position"><?= formatTanggal($receipt['receipt_date']) ?></div>
        </div>
    </div>
</div>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
