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
    /* Setiap form = dokumen sendiri: SELALU di halaman baru (tidak boleh berbagi halaman dengan form lain). */

    /* ---------- Pengiriman Dokumen ---------- */
    .sl-print-label { width: 120mm; margin-left: 10mm; } /* kotak diperlebar & digeser ke kanan */
    .sl-print-box {
        border: 1px solid #000; padding: 6mm 8mm; font-size: 14px; line-height: 1.6; word-break: break-word;
    }
    .sl-print-box + .sl-print-box { margin-top: 18mm; } /* jarak Kepada -> Pengirim */
    .fm-item-pengiriman + .fm-item { margin-top: 23mm; } /* jarak antar label pengiriman */
    .sl-print-box .sl-name-main { font-size: 18px; font-weight: 700; line-height: 1.5; text-decoration: underline; }
    .sl-print-box .sl-name-company { font-size: 16px; font-weight: 700; line-height: 1.5; }
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
    .kw-place { text-align: right; margin-top: 3mm; } /* naik ~2 baris */
    .kw-bottom { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 23mm; }
    .kw-amount-box { border: 1px solid #000; background: #e4e4e4; padding: 3px 10px; font-weight: 700; font-size: 14px; min-width: 62mm; display: flex; justify-content: space-between; gap: 12px; }
    .kw-amount-box .kw-num { font-style: italic; font-size: 16px; }

    /* ---------- Order Form ---------- */
    .of-title { text-align: center; font-size: 19px; font-weight: 700; margin-bottom: 4mm; }
    .of-meta { display: flex; justify-content: space-between; font-weight: 700; font-size: 12.5px; margin-bottom: 1mm; }
    .of-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    .of-table td, .of-table th { border: 1px solid #000; padding: 2px 5px; vertical-align: top; }
    .of-table .of-label { font-weight: 400; }
    .of-gap { height: 3mm; }
    /* Baris isian: label | titik dua | isi -- NAME & ADDRESS memakai lebar label sama, jadi isinya sejajar. */
    .of-field { display: flex; align-items: flex-start; }
    .of-field .of-lbl { flex: 0 0 22mm; }
    .of-field .of-lbl-sm { flex: 0 0 15mm; }   /* PHONE / EMAIL (kolom lebih sempit) */
    .of-field .of-col { flex: 0 0 3mm; }
    .of-field .of-val { flex: 1 1 auto; min-width: 0; }
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

    /* ---------- Tanda Terima Dokumen (format 1/2 F4 = 215 x 165mm: tinggi isi dijaga <= ~145mm) ---------- */
    .tt-doc { display: flex; flex-direction: column; min-height: 140mm; font-size: 12px; }
    .tt-header { border-bottom: 3px solid #1E3C72; padding-bottom: 6px; margin-bottom: 8px; }
    .tt-header img { display: block; height: 20mm; max-width: 100%; object-fit: contain; object-position: left; }
    .tt-company-name { font-size: 20px; font-weight: 800; color: #1E3C72; margin: 0; }
    .tt-title { text-align: center; font-size: 14px; font-weight: 700; text-decoration: underline; margin: 2px 0 8px; }
    .tt-info td { padding: 1px 6px 1px 0; vertical-align: top; }
    .tt-info td:first-child { width: 24mm; }
    .tt-info td:nth-child(2) { width: 4mm; }
    .tt-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .tt-table th, .tt-table td { border: 1px solid #adb5bd; padding: 3px 8px; }
    .tt-table th { text-align: left; font-weight: 700; }
    .tt-table tr.tt-row td { height: 6.5mm; }
    .tt-spacer { flex: 1 1 auto; min-height: 4mm; }
    .tt-signoff { display: flex; border: 1px solid #adb5bd; }
    .tt-signoff > div { flex: 1 1 50%; padding: 6px 12px 8px; }
    .tt-signoff > div:first-child { border-right: 1px solid #adb5bd; }
    .tt-signoff-label { font-weight: 700; margin-bottom: 12mm; }
    .tt-signoff-caption { font-size: 11px; color: #495057; }
    .tt-note { margin-top: 6px; font-size: 11px; color: #495057; }
    .tt-footer { display: flex; justify-content: space-between; gap: 20px; border-top: 1px solid #dee2e6; margin-top: 6px; padding-top: 5px; font-size: 10.5px; color: #495057; line-height: 1.45; }
    .tt-footer > div:last-child { border-left: 1px solid #adb5bd; padding-left: 14px; white-space: nowrap; }

    /* Semua cetak Form mulai di paling atas kertas: margin atas halaman 0, jarak ~1 baris (5mm) diberi lewat padding.
       Ukuran kertas SENGAJA tidak dipaksa (tanpa "size: A4") supaya mengikuti kertas yang dipilih di dialog cetak
       (A4/F4/Legal) -- dulu dipaksa A4 sehingga di kertas lebih besar isinya jadi turun/tidak di paling atas. */
    @page { margin: 0 10mm 10mm 10mm; }
    @media print {
        .fm-print-sheet {
            border: none; margin: 0; max-width: none; padding: 0 6mm 4mm;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }
        .fm-print-sheet > .fm-item { padding-top: 5mm; }
        .fm-print-sheet > .fm-item + .fm-item { margin-top: 0; break-before: page; page-break-before: always; }
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
