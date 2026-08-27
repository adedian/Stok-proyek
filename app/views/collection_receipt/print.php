<style>
    /* Pola cetak dicerminkan dari purchase_order/print.php -- lihat catatan di file itu. */
    .tt-print-page {
        background: #fff;
        max-width: 210mm;
        margin: 0 auto 24px auto;
        padding: 14mm 14mm;
        border: 1px solid #dee2e6;
        color: #212529;
        font-size: 12.5px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
    }
    .tt-print-page + .tt-print-page {
        page-break-before: always;
    }
    .tt-print-header {
        border-bottom: 3px solid var(--brand-700, #1E3C72);
        padding-bottom: 10px;
        margin-bottom: 12px;
    }
    .tt-print-header img.company-logo {
        display: block;
        width: 100%;
        height: auto;
        object-fit: contain;
    }
    .tt-print-company-name {
        font-size: 22px;
        font-weight: 800;
        color: var(--brand-700, #1E3C72);
        margin: 0;
        line-height: 1.15;
    }
    .tt-print-title {
        font-size: 15px;
        font-weight: 700;
        text-decoration: underline;
        text-align: center;
        margin: 4px 0 14px;
    }
    .tt-print-toprow table td {
        padding: 2px 6px 2px 0;
        vertical-align: top;
        font-size: 12.5px;
    }
    .tt-print-toprow table td:first-child {
        white-space: nowrap;
        color: #495057;
        width: 90px;
    }
    .tt-print-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
        font-size: 12px;
    }
    .tt-print-table th, .tt-print-table td {
        border: 1px solid #adb5bd;
        padding: 5px 8px;
    }
    .tt-print-table th {
        background: #fff;
        text-align: left;
        font-weight: 700;
    }
    .tt-print-table td.num, .tt-print-table th.num {
        text-align: right;
    }
    .tt-print-total-row td {
        font-weight: 700;
        background: #fff;
    }
    .tt-print-signoff-row {
        margin-top: auto;
        padding-top: 16px;
        display: flex;
        gap: 0;
        border: 1px solid #adb5bd;
    }
    .tt-print-signoff {
        flex: 1 1 50%;
        padding: 10px 14px 16px;
        text-align: center;
    }
    .tt-print-signoff:first-child {
        border-right: 1px solid #adb5bd;
    }
    .tt-print-signoff-label {
        font-weight: 700;
        text-align: left;
        margin-bottom: 44px;
    }
    .tt-print-signoff-caption {
        text-align: left;
        font-size: 11px;
        color: #495057;
        margin-top: 4px;
    }
    .tt-signature-image-wrap {
        height: 56px;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        margin-bottom: 2px;
    }
    .tt-signature-image-wrap img {
        max-height: 54px;
        max-width: 170px;
        object-fit: contain;
    }
    .tt-signature-name {
        font-weight: 700;
        text-decoration: underline;
    }
    .tt-signature-position {
        color: #495057;
    }
    .tt-print-note {
        margin-top: 16px;
        font-size: 11px;
        color: #495057;
    }
    .tt-print-footer-strip {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        border-top: 1px solid #dee2e6;
        margin-top: 24px;
        padding-top: 8px;
        font-size: 10.5px;
        color: #495057;
        line-height: 1.5;
    }
    .tt-print-footer-strip > div:last-child {
        border-left: 1px solid #adb5bd;
        padding-left: 16px;
    }
    @page {
        size: A4;
        margin: 10mm;
    }
    @media print {
        .tt-print-page {
            border: none;
            margin: 0;
            max-width: none;
            padding: 8mm 12mm;
            min-height: 277mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }
    }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak
    </button>
    <a href="<?= BASE_URL ?>/index.php?module=collection_receipt" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php foreach ($receipts as $r): ?>
    <div class="tt-print-page">
        <div class="tt-print-header">
            <?php if (!empty($company['company_logo'])): ?>
                <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo" class="company-logo">
            <?php else: ?>
                <p class="tt-print-company-name"><?= e($company['company_name'] ?: 'Perusahaan') ?></p>
            <?php endif; ?>
        </div>

        <div class="tt-print-title">Tanda Terima</div>

        <div class="tt-print-toprow">
            <table>
                <tr><td>No.</td><td>: <strong><?= e($r['receipt_number']) ?></strong></td></tr>
                <tr><td>Tanggal</td><td>: <?= date('d/m/Y', strtotime($r['receipt_date'])) ?></td></tr>
            </table>
        </div>

        <div class="tt-print-toprow" style="margin-top: 10px;">
            <table>
                <tr><td>Customer</td><td>: <strong><?= e($r['client_name']) ?></strong></td></tr>
                <tr><td>Alamat</td><td>: <?= nl2br(e($r['client_address'] ?? '-')) ?></td></tr>
            </table>
        </div>

        <table class="tt-print-table">
            <thead>
                <tr>
                    <th style="width: 26px;">No</th>
                    <th>No. Invoice</th>
                    <th>Faktur Pajak</th>
                    <th>Surat Jalan</th>
                    <th class="num" style="width: 120px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $grandTotal = 0; ?>
                <?php foreach ($r['items'] as $i => $item): ?>
                    <?php $grandTotal += (float) $item['total_amount']; ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($item['invoice_number']) ?></td>
                        <td><?= e($item['tax_invoice_number'] ?? '-') ?></td>
                        <td><?= e($item['delivery_number'] ?? '-') ?></td>
                        <td class="num"><?= formatRupiah($item['total_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="tt-print-total-row">
                    <td colspan="4" class="num">Grand Total</td>
                    <td class="num"><?= formatRupiah($grandTotal) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="tt-print-signoff-row">
            <div class="tt-print-signoff">
                <div class="tt-print-signoff-label">Penerima,</div>
                <?php if (!empty($r['recipient_name'])): ?>
                    <div class="tt-signature-name"><?= e($r['recipient_name']) ?></div>
                <?php endif; ?>
                <div class="tt-print-signoff-caption">(Nama, Tanda Tangan dan stempel)</div>
            </div>
            <div class="tt-print-signoff">
                <div class="tt-print-signoff-label">Hormat Kami,</div>
                <?php if (!empty($r['signature_image'])): ?>
                    <div class="tt-signature-image-wrap">
                        <img src="<?= BASE_URL ?>/<?= e($r['signature_image']) ?>" alt="TTD <?= e($r['signature_name']) ?>">
                    </div>
                    <div class="tt-signature-name"><?= e($r['signature_name']) ?></div>
                    <div class="tt-signature-position"><?= e($r['signature_position']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tt-print-note">
            *Tanda Terima ini Mohon di emailkan ke : accounting@hexamultienergi.com
        </div>

        <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
            <div class="tt-print-footer-strip">
                <div><?= nl2br(e($company['company_address'] ?? '')) ?></div>
                <div>
                    <?= e($company['company_phone'] ?? '') ?>
                    <?php if (!empty($company['company_email'])): ?><br><?= e($company['company_email']) ?><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
    </div>
<?php endforeach; ?>
