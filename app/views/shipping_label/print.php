<style>
    /* Label Pengiriman: dua kotak bertumpuk (Kepada, lalu Pengirim) per label. */
    .sl-print-sheet {
        background: #fff;
        max-width: 210mm;
        margin: 0 auto 24px auto;
        padding: 12mm 14mm;
        border: 1px solid #dee2e6;
        color: #000;
        box-sizing: border-box;
    }
    .sl-print-label {
        width: 100mm;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .sl-print-label + .sl-print-label {
        margin-top: 12mm;
    }
    .sl-print-box {
        border: 1px solid #000;
        padding: 6mm 8mm;
        font-size: 13px;
        line-height: 1.6;
        font-family: Calibri, "Segoe UI", Arial, sans-serif;
        word-break: break-word;
    }
    .sl-print-box + .sl-print-box {
        margin-top: 7mm;
    }
    .sl-print-box .sl-bold {
        font-weight: 700;
    }
    @page {
        size: A4;
        margin: 10mm;
    }
    @media print {
        .sl-print-sheet {
            border: none;
            margin: 0;
            max-width: none;
            padding: 4mm 6mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak
    </button>
    <a href="<?= BASE_URL ?>/shipping_label" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="sl-print-sheet">
    <?php foreach ($labels as $l): ?>
        <div class="sl-print-label">
            <?php foreach (['to' => 'Kepada', 'from' => 'Pengirim'] as $p => $title): ?>
                <div class="sl-print-box">
                    <div class="sl-bold"><?= e($title) ?> :</div>
                    <div class="sl-bold"><?= e($l[$p . '_name']) ?></div>
                    <div><?= e($l[$p . '_address']) ?></div>
                    <?php if (!empty($l[$p . '_city'])): ?><div><?= e($l[$p . '_city']) ?></div><?php endif; ?>
                    <?php if (!empty($l[$p . '_postal_code'])): ?><div>Kode Pos <?= e($l[$p . '_postal_code']) ?></div><?php endif; ?>
                    <?php if (!empty($l[$p . '_phone'])): ?><div>No. Telp <?= e($l[$p . '_phone']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
