<style>
    .fm-print-sheet {
        background: #fff;
        max-width: 210mm;
        margin: 0 auto 24px auto;
        padding: 12mm 14mm;
        border: 1px solid #dee2e6;
        color: #000;
        box-sizing: border-box;
        font-family: Calibri, "Segoe UI", Arial, sans-serif;
    }
    .fm-item { break-inside: avoid; page-break-inside: avoid; }
    .fm-item + .fm-item { margin-top: 12mm; }
    /* Order Form & Bon Sementara = 1 form per halaman; Kwitansi/Pengiriman boleh berbagi halaman. */
    .fm-item-order_form { break-before: page; }
    .fm-item-order_form + .fm-item { break-before: page; }
    .fm-print-sheet > .fm-item:first-child { break-before: auto; }

    /* ---------- Pengiriman Dokumen ---------- */
    .sl-print-label { width: 100mm; }
    .sl-print-box {
        border: 1px solid #000; padding: 6mm 8mm; font-size: 13px; line-height: 1.6; word-break: break-word;
    }
    .sl-print-box + .sl-print-box { margin-top: 7mm; }
    .fm-bold { font-weight: 700; }

    /* ---------- Kwitansi ---------- */
    .kw-box { border: 1px solid #000; padding: 6mm 8mm 5mm; font-size: 13px; }
    .kw-title { text-align: center; font-size: 17px; font-weight: 700; margin-bottom: 7mm; }
    .kw-row { display: flex; align-items: flex-start; border-bottom: 1px solid #000; padding: 4px 0 3px; min-height: 8mm; }
    .kw-row .kw-label { flex: 0 0 38mm; }
    .kw-row .kw-sep { flex: 0 0 5mm; }
    .kw-row .kw-val { flex: 1 1 auto; font-weight: 700; white-space: pre-line; }
    .kw-row .kw-val.kw-italic { font-style: italic; }
    .kw-shade { background: #e4e4e4; }
    .kw-place { text-align: right; margin-top: 12mm; }
    .kw-bottom { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 14mm; }
    .kw-amount-box { border: 1px solid #000; background: #e4e4e4; padding: 3px 10px; font-weight: 700; font-size: 14px; min-width: 62mm; display: flex; justify-content: space-between; gap: 12px; }
    .kw-amount-box .kw-num { font-style: italic; font-size: 16px; }

    /* ---------- Order Form ---------- */
    .of-title { text-align: center; font-size: 19px; font-weight: 700; margin-bottom: 4mm; }
    .of-meta { display: flex; justify-content: space-between; font-weight: 700; font-size: 12.5px; margin-bottom: 1mm; }
    .of-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .of-table td, .of-table th { border: 1px solid #000; padding: 2px 5px; vertical-align: top; }
    .of-table .of-label { font-weight: 400; }
    .of-gap { height: 3mm; }
    .of-head th { background: #000; color: #fff; text-align: center; font-weight: 400; padding: 3px 5px; }
    .of-num { width: 7mm; text-align: center; }
    .of-right { text-align: right; white-space: nowrap; }
    .of-row td { height: 7mm; }
    .of-sign { height: 36mm; }
    .of-sign-labels { display: flex; justify-content: space-between; font-weight: 700; margin-top: 24mm; }
    .of-radio { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; border-radius: 50%; vertical-align: middle; margin-right: 4px; }
    .of-radio.on { background: #000; }

    /* ---------- Pengajuan Bon Sementara ---------- */
    .bon-logo { height: 14mm; margin-bottom: 3mm; }
    .bon-logo img { max-height: 14mm; max-width: 60mm; object-fit: contain; }
    .bon-title { text-align: center; font-weight: 700; text-decoration: underline; font-size: 14px; margin-bottom: 8mm; }
    .bon-head { display: flex; justify-content: space-between; font-size: 12.5px; margin-bottom: 7mm; }
    .bon-head table td { padding: 1px 8px 1px 0; vertical-align: top; }
    .bon-head table td:first-child { white-space: nowrap; }
    .bon-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .bon-table th, .bon-table td { border: 1px solid #000; padding: 2px 6px; vertical-align: top; }
    .bon-table th { text-align: center; font-weight: 700; }
    .bon-table td.bon-no { text-align: center; width: 10mm; }
    .bon-table td.bon-amt { width: 42mm; white-space: nowrap; }
    .bon-table td.bon-amt span { display: flex; justify-content: space-between; }
    .bon-table td.bon-desc { white-space: pre-line; }
    .bon-table tr.bon-row td { height: 6.5mm; }
    .bon-table tr.bon-total td { background: #f8b58a; font-weight: 700; text-align: center; }
    .bon-table tr.bon-total td.bon-amt { text-align: left; }
    .bon-sign { display: flex; justify-content: space-between; margin-top: 4mm; font-size: 12.5px; }
    .bon-sign-col { flex: 1; text-align: center; }
    .bon-sign-space { height: 22mm; }
    .bon-place { text-align: right; margin-top: 8mm; font-size: 12.5px; }

    @page { size: A4; margin: 10mm; }
    @media print {
        .fm-print-sheet {
            border: none; margin: 0; max-width: none; padding: 4mm 6mm;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
    }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak
    </button>
    <a href="<?= BASE_URL ?>/form" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="fm-print-sheet">
    <?php foreach ($forms as $f): ?>
        <?php
            $d = $f['d'];
            $partial = ROOT_PATH . '/app/views/form/_print_' . $f['form_type'] . '.php';
            if (!is_file($partial)) {
                continue;
            }
        ?>
        <div class="fm-item fm-item-<?= e($f['form_type']) ?>">
            <?php require $partial; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
