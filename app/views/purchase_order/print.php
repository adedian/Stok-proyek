<style>
    .po-print-page {
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
    .po-print-page + .po-print-page {
        page-break-before: always;
    }
    .po-print-header {
        border-bottom: 3px solid var(--brand-700, #1E3C72);
        padding-bottom: 10px;
        margin-bottom: 4px;
    }
    .po-print-header img.company-logo {
        display: block;
        width: 100%;
        height: auto;
        object-fit: contain;
    }
    .po-print-company-name {
        font-size: 22px;
        font-weight: 800;
        color: var(--brand-700, #1E3C72);
        margin: 0;
        line-height: 1.15;
    }
    .po-print-company-meta {
        font-size: 11px;
        color: #495057;
        margin-top: 2px;
    }
    .po-print-title {
        font-size: 17px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin: 14px 0 10px 0;
    }
    .po-print-toprow table td {
        padding: 1px 6px 1px 0;
        vertical-align: top;
        font-size: 12.5px;
    }
    .po-print-toprow table td:first-child {
        white-space: nowrap;
        color: #495057;
    }
    .po-print-parties {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
    }
    .po-print-party {
        flex: 1 1 50%;
        font-size: 12.5px;
    }
    .po-print-party-label {
        font-weight: 700;
        margin-bottom: 3px;
    }
    .po-print-party div.line {
        line-height: 1.4;
    }
    .po-print-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
        font-size: 12px;
    }
    .po-print-table th, .po-print-table td {
        border: 1px solid #adb5bd;
        padding: 4px 7px;
    }
    .po-print-table th {
        background: #FFC000;
        text-align: left;
        font-weight: 700;
    }
    .po-print-table td.num, .po-print-table th.num {
        text-align: right;
        white-space: nowrap;
    }
    .po-print-total-row td {
        font-weight: 700;
        background: var(--brand-50, #F3F6FB);
    }
    .po-print-notes {
        margin-top: 12px;
        font-size: 12px;
    }
    .po-print-notes-title {
        font-weight: 700;
        margin-bottom: 2px;
    }
    /* Dorong blok TTD (+ footer alamat yang mengikutinya, lihat .po-print-footer-strip)
       ke bagian paling bawah halaman saat dicetak, bukan menempel tepat di bawah
       tabel item. "auto" di sini tidak berefek apa-apa saat dilihat di layar biasa
       (wadahnya setinggi konten saja) -- baru aktif saat cetak, ketika .po-print-page
       diberi tinggi minimum setinggi 1 halaman A4 (lihat @media print di bawah). */
    .po-print-signoff {
        margin-top: auto;
        padding-top: 34px;
    }
    .po-print-signoff-company {
        font-weight: 700;
        margin-bottom: 4px;
    }
    .po-signature-image-wrap {
        height: 64px;
        display: flex;
        align-items: flex-end;
        margin-bottom: 2px;
    }
    .po-signature-image-wrap img {
        max-height: 62px;
        max-width: 200px;
        object-fit: contain;
    }
    .po-signature-placeholder {
        height: 64px;
        display: flex;
        align-items: flex-end;
        margin-bottom: 2px;
        color: #adb5bd;
        font-size: 12px;
    }
    .po-signature-name {
        font-weight: 700;
        text-decoration: underline;
    }
    .po-signature-position {
        color: #495057;
    }
    .po-print-footer-strip {
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
    .po-print-footer-strip > div:last-child {
        border-left: 1px solid #adb5bd;
        padding-left: 16px;
    }
    @page {
        size: A4;
        margin: 10mm;
    }
    @media print {
        .po-print-page {
            border: none;
            margin: 0;
            max-width: none;
            padding: 8mm 12mm;
            /* Tinggi 1 halaman A4 dikurangi margin @page (10mm atas+bawah) --
               supaya .po-print-signoff (margin-top: auto) punya ruang kosong
               untuk didorong ke bawah kalau isi PO pendek. PO yang isinya
               panjang (banyak item) otomatis tetap bisa melebihi tinggi ini
               (min-height, bukan height) dan lanjut ke halaman berikutnya
               seperti biasa, tidak terpotong.
            */
            min-height: 277mm;
            /* Browser (Chrome/Edge) secara default TIDAK mencetak warna
               background sama sekali kecuali user mencentang "Background
               graphics" di dialog cetak -- makanya header tabel kuning
               (#FFC000) hilang jadi putih polos di hasil cetak. Baris ini
               memaksa background tetap tercetak apa pun setting itu. */
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
    <a href="<?= BASE_URL ?>/index.php?module=purchase_order" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php foreach ($purchaseOrders as $po): ?>
    <div class="po-print-page">
        <div class="po-print-header">
            <?php if (!empty($company['company_logo'])): ?>
                <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo" class="company-logo">
            <?php else: ?>
                <div>
                    <p class="po-print-company-name"><?= e($company['company_name'] ?: 'Perusahaan') ?></p>
                    <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
                        <div class="po-print-company-meta">
                            <?= e($company['company_address'] ?? '') ?>
                            <?php if (!empty($company['company_phone'])): ?> &middot; <?= e($company['company_phone']) ?><?php endif; ?>
                            <?php if (!empty($company['company_email'])): ?> &middot; <?= e($company['company_email']) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="po-print-title">PURCHASE ORDER</div>

        <div class="po-print-toprow">
            <table>
                <tr><td>No. PO</td><td>: <strong><?= e($po['po_number']) ?></strong></td></tr>
                <tr><td>Tanggal</td><td>: <?= formatTanggal($po['po_date']) ?></td></tr>
            </table>
        </div>

        <div class="po-print-parties">
            <div class="po-print-party">
                <div class="po-print-party-label">Supplier</div>
                <div class="line"><?= e($po['supplier_name']) ?></div>
                <?php if (!empty($po['supplier_address'])): ?><div class="line"><?= nl2br(e($po['supplier_address'])) ?></div><?php endif; ?>
                <?php if (!empty($po['supplier_contact_person'])): ?><div class="line">Bpk/Ibu <?= e($po['supplier_contact_person']) ?></div><?php endif; ?>
                <?php if (!empty($po['supplier_phone'])): ?><div class="line"><?= e($po['supplier_phone']) ?></div><?php endif; ?>
            </div>
            <div class="po-print-party">
                <div class="po-print-party-label">Dikirim Ke</div>
                <?php if (!empty($po['delivery_location_name'])): ?>
                    <div class="line"><?= e($po['delivery_location_name']) ?></div>
                    <?php if (!empty($po['delivery_location_address'])): ?><div class="line"><?= nl2br(e($po['delivery_location_address'])) ?></div><?php endif; ?>
                <?php endif; ?>
                <div class="line">Project <?= e($po['project_name']) ?></div>
                <?php if (!empty($po['project_location'])): ?><div class="line"><?= e($po['project_location']) ?></div><?php endif; ?>
            </div>
        </div>

        <?php if (!empty($po['quote_number']) || !empty($po['quote_date'])): ?>
            <div class="po-print-toprow" style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #dee2e6;">
                <table>
                    <?php if (!empty($po['quote_number'])): ?>
                        <tr><td>Quote Number</td><td>: <?= e($po['quote_number']) ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($po['quote_date'])): ?>
                        <tr><td>Date</td><td>: <?= formatTanggal($po['quote_date']) ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        <?php endif; ?>

        <table class="po-print-table">
            <thead>
                <tr>
                    <th style="width: 26px;">No</th>
                    <th>Nama Barang / Spesifikasi</th>
                    <th class="num" style="width: 55px;">Qty</th>
                    <th style="width: 55px;">Satuan</th>
                    <th class="num" style="width: 95px;">Harga Satuan</th>
                    <th class="num" style="width: 105px;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($po['items'] as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($item['item_name']) ?></td>
                        <td class="num"><?= number_format((float) $item['qty_order'], 2, ',', '.') ?></td>
                        <td><?= e($item['unit']) ?></td>
                        <td class="num"><?= formatRupiah($item['price']) ?></td>
                        <td class="num"><?= formatRupiah($item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="po-print-total-row">
                    <td colspan="5" class="num">TOTAL</td>
                    <td class="num"><?= formatRupiah($po['total_amount']) ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!empty($po['notes'])): ?>
            <div class="po-print-notes">
                <div class="po-print-notes-title">Catatan:</div>
                <div><?= nl2br(e($po['notes'])) ?></div>
            </div>
        <?php endif; ?>

        <div class="po-print-signoff">
            <div class="po-print-signoff-company"><?= e($company['company_name'] ?: 'Perusahaan') ?></div>
            <?php if (!empty($po['signature_image'])): ?>
                <div class="po-signature-image-wrap">
                    <img src="<?= BASE_URL ?>/<?= e($po['signature_image']) ?>" alt="TTD <?= e($po['signature_name']) ?>">
                </div>
                <div class="po-signature-name"><?= e($po['signature_name']) ?></div>
                <div class="po-signature-position"><?= e($po['signature_position']) ?></div>
            <?php else: ?>
                <div class="po-signature-placeholder">(.....................)</div>
                <div class="po-signature-name"><?= e($po['pembuat_po'] ?: '-') ?></div>
                <div class="po-signature-position">Pembuat PO</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
            <div class="po-print-footer-strip">
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
