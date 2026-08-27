<style>
    /* Struktur & CSS print di sini SENGAJA dicerminkan dari purchase_order/print.php
       (pola cetak multi-dokumen yang sudah terbukti jalan: page-break aman, A4,
       print-color-adjust) -- lihat catatan di file itu untuk detail tiap aturan. */
    .inv-print-page {
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
    .inv-print-page + .inv-print-page {
        page-break-before: always;
    }
    .inv-print-header {
        border-bottom: 3px solid var(--brand-700, #1E3C72);
        padding-bottom: 10px;
        margin-bottom: 4px;
    }
    .inv-print-header img.company-logo {
        display: block;
        width: 100%;
        height: auto;
        object-fit: contain;
    }
    .inv-print-company-name {
        font-size: 22px;
        font-weight: 800;
        color: var(--brand-700, #1E3C72);
        margin: 0;
        line-height: 1.15;
    }
    .inv-print-company-meta {
        font-size: 11px;
        color: #495057;
        margin-top: 2px;
    }
    .inv-print-title {
        font-size: 17px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin: 14px 0 10px 0;
    }
    .inv-print-toprow table td {
        padding: 1px 6px 1px 0;
        vertical-align: top;
        font-size: 12.5px;
    }
    .inv-print-toprow table td:first-child {
        white-space: nowrap;
        color: #495057;
        width: 110px;
    }
    .inv-print-party {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
        font-size: 12.5px;
    }
    .inv-print-party-label {
        font-weight: 700;
        margin-bottom: 3px;
    }
    .inv-print-party div.line {
        line-height: 1.4;
    }
    .inv-print-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
        font-size: 12px;
    }
    .inv-print-table th, .inv-print-table td {
        border: 1px solid #adb5bd;
        padding: 4px 7px;
    }
    .inv-print-table th {
        background: #FFC000;
        text-align: left;
        font-weight: 700;
    }
    .inv-print-table td.num, .inv-print-table th.num {
        text-align: right;
    }
    /* "Rp" sempat turun ke baris sendiri (terpisah dari nominalnya) di kolom yang
       sempit (Harga Satuan, Harga Jumlah, dan baris Jumlah/Tagihan DP/PPN/Total)
       -- paksa satu baris di SEMUA sel angka supaya "Rp" selalu sejajar/rapi
       dengan nominalnya, konsisten di seluruh tabel. */
    .inv-print-table td.num {
        white-space: nowrap;
    }
    .inv-print-total-row td {
        font-weight: 700;
        background: #FFF3CD;
    }
    .inv-print-total-row.grand td {
        background: #FFC000;
    }
    .inv-print-notes {
        margin-top: 14px;
        font-size: 12px;
    }
    .inv-print-notes-title {
        font-weight: 700;
        margin-bottom: 2px;
    }
    /* Isi Note (blok "Transfer ke" dkk) digeser ke kanan relatif ke judul "Note:"
       -- sesuai template, cuma posisi yang berubah, isi/teks tetap sama. Digeser
       1 langkah lagi dari revisi sebelumnya (28px -> 40px) atas permintaan user,
       masih aman dari mepet tabel/keluar margin halaman A4. */
    .inv-print-notes-body {
        margin-left: 40px;
    }
    .inv-print-signoff {
        margin-top: auto;
        padding-top: 34px;
    }
    .inv-print-signoff-company {
        font-weight: 700;
        margin-bottom: 4px;
    }
    .inv-signature-image-wrap {
        height: 64px;
        display: flex;
        align-items: flex-end;
        margin-bottom: 2px;
    }
    .inv-signature-image-wrap img {
        max-height: 62px;
        max-width: 200px;
        object-fit: contain;
    }
    .inv-signature-placeholder {
        height: 64px;
        display: flex;
        align-items: flex-end;
        margin-bottom: 2px;
        color: #adb5bd;
        font-size: 12px;
    }
    .inv-signature-name {
        font-weight: 700;
        text-decoration: underline;
    }
    .inv-signature-position {
        color: #495057;
    }
    .inv-print-footer-strip {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        border-top: 1px solid #dee2e6;
        margin-top: 30px;
        padding-top: 8px;
        font-size: 10.5px;
        color: #495057;
        line-height: 1.5;
    }
    .inv-print-footer-strip > div:last-child {
        border-left: 1px solid #adb5bd;
        padding-left: 16px;
    }
    @page {
        size: A4;
        margin: 10mm;
    }
    @media print {
        .inv-print-page {
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
    <a href="<?= BASE_URL ?>/index.php?module=sales_invoice" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php foreach ($invoices as $inv): ?>
    <div class="inv-print-page">
        <div class="inv-print-header">
            <?php if (!empty($company['company_logo'])): ?>
                <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo" class="company-logo">
            <?php else: ?>
                <div>
                    <p class="inv-print-company-name"><?= e($company['company_name'] ?: 'Perusahaan') ?></p>
                    <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
                        <div class="inv-print-company-meta">
                            <?= e($company['company_address'] ?? '') ?>
                            <?php if (!empty($company['company_phone'])): ?> &middot; <?= e($company['company_phone']) ?><?php endif; ?>
                            <?php if (!empty($company['company_email'])): ?> &middot; <?= e($company['company_email']) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="inv-print-title">INVOICE</div>

        <div class="inv-print-toprow">
            <table>
                <tr><td>No. Invoice</td><td>: <strong><?= e($inv['invoice_number']) ?></strong></td></tr>
                <tr><td>Tanggal</td><td>: <?= formatTanggal($inv['invoice_date']) ?></td></tr>
            </table>
        </div>

        <div class="inv-print-party">
            <div class="inv-print-party-label">Kepada</div>
            <div class="line"><?= e($inv['client_name']) ?></div>
            <?php if (!empty($inv['client_address'])): ?><div class="line"><?= nl2br(e($inv['client_address'])) ?></div><?php endif; ?>
        </div>

        <div class="inv-print-toprow" style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #dee2e6;">
            <table>
                <tr><td>No. Kontrak</td><td>: <?= e($inv['contract_number'] ?: '-') ?></td></tr>
                <tr><td>Tanggal</td><td>: <?= !empty($inv['contract_date']) ? formatTanggal($inv['contract_date']) : '-' ?></td></tr>
            </table>
        </div>

        <table class="inv-print-table">
            <thead>
                <tr>
                    <th style="width: 26px;">No</th>
                    <th>Deskripsi</th>
                    <th class="num" style="width: 55px;">Qty</th>
                    <th style="width: 55px;">Unit</th>
                    <th class="num" style="width: 110px;">Harga Satuan</th>
                    <th class="num" style="width: 125px;">Harga Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inv['items'] as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= nl2br(e($item['description'])) ?></td>
                        <td class="num"><?= number_format((float) $item['qty'], 2, ',', '.') ?></td>
                        <td><?= e($item['unit']) ?></td>
                        <td class="num"><?= formatRupiah($item['unit_price']) ?></td>
                        <td class="num"><?= formatRupiah($item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="inv-print-total-row">
                    <td colspan="5" class="num">Jumlah</td>
                    <td class="num"><?= formatRupiah($inv['subtotal']) ?></td>
                </tr>
                <tr class="inv-print-total-row">
                    <td colspan="5" class="num">Tagihan (DP <?= formatPercent($inv['dp_percentage']) ?>%)</td>
                    <td class="num"><?= formatRupiah($inv['dp_amount']) ?></td>
                </tr>
                <tr class="inv-print-total-row">
                    <td colspan="5" class="num">PPN<?= (float) $inv['ppn_percent'] > 0 ? ' (' . formatPercent($inv['ppn_percent']) . '%)' : '' ?></td>
                    <td class="num"><?= formatRupiah($inv['ppn_amount']) ?></td>
                </tr>
                <tr class="inv-print-total-row grand">
                    <td colspan="5" class="num">Total</td>
                    <td class="num"><?= formatRupiah($inv['total_amount']) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="inv-print-notes">
            <div class="inv-print-notes-title">Note:</div>
            <div class="inv-print-notes-body">
                1. Transfer ke:<br>
                <strong><?= e($bankAccount['account_holder_name'] ?? $company['company_name'] ?? '') ?></strong><br>
                <?php if (!empty($bankAccount['bank_name'])): ?><?= e($bankAccount['bank_name']) ?><br><?php endif; ?>
                <?php if (!empty($bankAccount['account_number'])): ?>No Rek: <?= e($bankAccount['account_number']) ?><?php endif; ?>
            </div>
            <?php if (!empty($inv['notes'])): ?>
                <div class="mt-2 inv-print-notes-body">2. <?= nl2br(e($inv['notes'])) ?></div>
            <?php endif; ?>
        </div>

        <div class="inv-print-signoff">
            <div class="inv-print-signoff-company"><?= e($company['company_name'] ?: 'Perusahaan') ?></div>
            <?php if (!empty($inv['signature_image'])): ?>
                <div class="inv-signature-image-wrap">
                    <img src="<?= BASE_URL ?>/<?= e($inv['signature_image']) ?>" alt="TTD <?= e($inv['signature_name']) ?>">
                </div>
                <div class="inv-signature-name"><?= e($inv['signature_name']) ?></div>
                <div class="inv-signature-position"><?= e($inv['signature_position']) ?></div>
            <?php else: ?>
                <div class="inv-signature-placeholder">(.....................)</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
            <div class="inv-print-footer-strip">
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
