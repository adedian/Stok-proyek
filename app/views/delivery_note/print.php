<style>
    /* Pola cetak dicerminkan dari purchase_order/print.php -- lihat catatan di file itu. */
    .sj-print-page {
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
    .sj-print-page + .sj-print-page {
        page-break-before: always;
    }
    .sj-print-header {
        border-bottom: 3px solid var(--brand-700, #1E3C72);
        padding-bottom: 10px;
        margin-bottom: 4px;
    }
    .sj-print-header img.company-logo {
        display: block;
        width: 100%;
        height: auto;
        object-fit: contain;
    }
    .sj-print-company-name {
        font-size: 22px;
        font-weight: 800;
        color: var(--brand-700, #1E3C72);
        margin: 0;
        line-height: 1.15;
    }
    .sj-print-company-meta {
        font-size: 11px;
        color: #495057;
        margin-top: 2px;
    }
    .sj-print-title {
        font-size: 17px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin: 14px 0 10px 0;
    }
    .sj-print-toprow table td {
        padding: 1px 6px 1px 0;
        vertical-align: top;
        font-size: 12.5px;
    }
    .sj-print-toprow table td:first-child {
        white-space: nowrap;
        color: #495057;
        width: 110px;
    }
    .sj-print-party {
        margin-top: 10px;
        padding-top: 8px;
        border-top: 1px solid #dee2e6;
        font-size: 12.5px;
    }
    .sj-print-party-label {
        font-weight: 700;
        margin-bottom: 3px;
    }
    .sj-print-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
        font-size: 12px;
    }
    .sj-print-table th, .sj-print-table td {
        border: 1px solid #adb5bd;
        padding: 4px 7px;
    }
    .sj-print-table th {
        background: #FFC000;
        text-align: left;
        font-weight: 700;
    }
    .sj-print-table td.num, .sj-print-table th.num {
        text-align: right;
        white-space: nowrap;
    }
    .sj-print-table tr {
        page-break-inside: avoid;
    }
    .sj-print-received {
        margin-top: 16px;
        font-size: 12px;
        font-weight: 700;
    }
    .sj-print-received-loc {
        margin-top: 6px;
        font-size: 12px;
        text-align: right;
    }
    .sj-print-signoff-row {
        margin-top: auto;
        padding-top: 20px;
        display: flex;
        justify-content: space-between;
        gap: 20px;
    }
    .sj-print-signoff {
        flex: 1 1 50%;
        text-align: center;
    }
    .sj-print-signoff-label {
        margin-bottom: 44px;
    }
    .sj-signature-image-wrap {
        height: 56px;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        margin-bottom: 2px;
    }
    .sj-signature-image-wrap img {
        max-height: 54px;
        max-width: 170px;
        object-fit: contain;
    }
    .sj-signature-name {
        font-weight: 700;
        text-decoration: underline;
    }
    .sj-signature-position {
        color: #495057;
    }
    .sj-print-footer-strip {
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
    .sj-print-footer-strip > div:last-child {
        border-left: 1px solid #adb5bd;
        padding-left: 16px;
    }
    @page {
        size: A4;
        margin: 10mm;
    }
    @media print {
        .sj-print-page {
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
    <a href="<?= BASE_URL ?>/index.php?module=stock_out" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php foreach ($notes as $note): ?>
    <?php
        $tujuan = $note['destination_name'] ?: ($note['destination_type'] === 'client' ? ($note['client_name'] ?? '-') : ($note['project_name'] ?? '-'));
        // Kota tempat serah terima ditandatangani -- field TERPISAH dari $tujuan
        // (nama project/client), lihat catatan di DeliveryNoteController::store().
        // Kalau kota belum diisi, jangan tampilkan nama project sebagai fallback
        // (itu justru bug yang diperbaiki) -- cukup tanggal saja tanpa kota.
        $kota = $note['city'] ?? '';
    ?>
    <div class="sj-print-page">
        <div class="sj-print-header">
            <?php if (!empty($company['company_logo'])): ?>
                <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo" class="company-logo">
            <?php else: ?>
                <div>
                    <p class="sj-print-company-name"><?= e($company['company_name'] ?: 'Perusahaan') ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="sj-print-title">SURAT JALAN</div>

        <div class="sj-print-toprow">
            <table>
                <tr><td>Number</td><td>: <strong><?= e($note['delivery_number']) ?></strong></td></tr>
                <tr><td>Date</td><td>: <?= formatTanggal($note['delivery_date']) ?></td></tr>
            </table>
        </div>

        <div class="sj-print-party">
            <div class="sj-print-party-label">Kepada</div>
            <div class="line"><?= e($tujuan) ?></div>
        </div>

        <table class="sj-print-table">
            <thead>
                <tr>
                    <th style="width: 26px;">No</th>
                    <th>Part No / Description / Specification</th>
                    <th class="num" style="width: 55px;">Qty</th>
                    <th style="width: 55px;">Unit</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($note['items'] as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($item['item_name']) ?></td>
                        <td class="num"><?= number_format((float) $item['qty'], 2, ',', '.') ?></td>
                        <td><?= e($item['unit']) ?></td>
                        <td><?= e($item['notes'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="sj-print-received">
            BARANG SUDAH DITERIMA DALAM KEADAAN BAIK DAN LENGKAP oleh:
        </div>
        <div class="sj-print-received-loc">
            <?= $kota !== '' ? e($kota) . ', ' : '' ?><?= formatTanggal($note['delivery_date']) ?>
        </div>

        <div class="sj-print-signoff-row">
            <div class="sj-print-signoff">
                <div class="sj-print-signoff-label">Penerima / Pembeli</div>
                <div class="sj-signature-name"><?= e($note['recipient_name'] ?: '.....................') ?></div>
            </div>
            <div class="sj-print-signoff">
                <div class="sj-print-signoff-label"><?= e($company['company_name'] ?: 'Perusahaan') ?></div>
                <?php if (!empty($note['signature_image'])): ?>
                    <div class="sj-signature-image-wrap">
                        <img src="<?= BASE_URL ?>/<?= e($note['signature_image']) ?>" alt="TTD <?= e($note['signature_name']) ?>">
                    </div>
                    <div class="sj-signature-name"><?= e($note['signature_name']) ?></div>
                    <div class="sj-signature-position"><?= e($note['signature_position']) ?></div>
                <?php else: ?>
                    <div class="sj-signature-name"><?= e($note['sender_name'] ?: '.....................') ?></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
            <div class="sj-print-footer-strip">
                <div><?= nl2br(e($company['company_address'] ?? '')) ?></div>
                <div>
                    <?= e($company['company_phone'] ?? '') ?>
                    <?php if (!empty($company['company_email'])): ?><br><?= e($company['company_email']) ?><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
