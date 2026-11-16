<?php
/**
 * Cetak "PERMINTAAN OTORISASI" -- landscape, warna latar tercetak. Variabel: $filters, $report, $company,
 * $filterNote, $autoprint, $backQuery.
 */
?>
<style>
    .po-sheet { background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 11px; max-width: 297mm; margin: 0 auto 24px; padding: 6mm; box-sizing: border-box; }
    .po-sheet .po-title { font-weight: 700; font-size: 13px; }
    .po-sheet .po-sub { font-size: 10px; color: #444; margin: 2px 0 8px; }
    .po-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .po-table th { background: #B4E5A2; border: 1px solid #000; padding: 3px 4px; text-align: center; font-weight: 700; font-size: 10.5px; }
    .po-table td { border: 1px solid #000; padding: 2px 4px; vertical-align: top; font-size: 10.5px; word-break: break-word; }
    .po-table td.c { text-align: center; }
    .po-table td.num { text-align: right; white-space: nowrap; }
    .po-table td.nob { border: 0; background: transparent; }
    .po-table tr.po-gap td { border: 0; height: 6px; padding: 0; }
    .po-table .po-red { color: #C00000 !important; font-weight: 600; }
    .po-table tr.po-grand td:not(.nob) { border: 2px solid #000; font-size: 12px; }
    .po-table tr { page-break-inside: avoid; }
    .po-foot { margin-top: 8px; text-align: right; font-size: 8px; color: #888; }
    .po-sheet, .po-sheet * { -webkit-print-color-adjust: exact; print-color-adjust: exact; color-adjust: exact; }
    @page { size: A4 landscape; margin: 8mm; }
    @media print {
        .po-sheet { max-width: none; margin: 0; padding: 0; }
    }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
    <a href="<?= BASE_URL ?>/index.php?<?= e(http_build_query(['module' => 'pengajuan_budget_report'] + $backQuery)) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="po-sheet print-page">
    <div class="po-title">PERMINTAAN OTORISASI</div>
    <div class="po-title"><?= e(PengajuanBudgetReportController::periodLabel($filters)) ?></div>
    <div class="po-sub"><?= e($company['company_name'] ?? '') ?> &middot; <?= e($filterNote) ?></div>
    <?php $print = true; require __DIR__ . '/_report_table.php'; ?>
    <div class="po-foot"><?= e(printedAtLabel() . ', ' . printedByLabel()) ?></div>
</div>

<?php if (!empty($autoprint)): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
<?php endif; ?>
