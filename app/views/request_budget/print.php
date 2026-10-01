<?php
/**
 * Cetak Request Budget -- mengikuti template "Request Budget Operasional Proyek"
 * (PDF contoh dari Accounting). Hanya LAYOUT di file ini; data & alur ada di
 * RequestBudgetController::print(). Kalau template berubah, cukup ubah file ini.
 *
 * Catatan template:
 *  - Judul = "REQUEST BUDGET " + Keperluan (huruf besar); kanan = "PERIODE ..." + tanggal.
 *  - Kolom: No | Keterangan | Qty | Unit | Harga | Total, minimal 10 baris.
 *  - Sub Total = jumlah seluruh Total. Pembulatan = Sub Total (belum ada aturan
 *    pembulatan lain yang diberikan).
 *  - Tanda tangan: "Disetujui" (pemberi approval) & "Dibuat" (pengaju) -- gambar
 *    diambil dari Tanda Tangan Saya masing-masing user bila ada.
 */
$minRows = 10;
$subTotal = (float) $rb['total_amount'];
$rounded = $subTotal;
$fmt = fn($n) => number_format((float) $n, 0, '.', ',');
$qtyFmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
$periode = trim((string) ($rb['period_label'] ?? ''));
?>
<style>
    .rb-print-page {
        background: #fff; color: #000; max-width: 210mm; margin: 0 auto 24px auto;
        padding: 8mm; box-sizing: border-box; font-family: Arial, Helvetica, sans-serif; font-size: 11px;
    }
    .rb-box { border: 2px solid #000; }
    .rb-head { display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 10px 6px; min-height: 80px; }
    .rb-head img.rb-logo { height: 62px; width: auto; max-width: 62%; object-fit: contain; }
    .rb-head .rb-company-name { font-size: 20px; font-weight: 800; color: #1E3C72; }
    .rb-company-meta { text-align: right; font-size: 8.5px; line-height: 1.35; max-width: 38%; }
    .rb-company-meta strong { display: block; font-size: 9px; }
    .rb-titlebar { display: flex; justify-content: space-between; align-items: flex-end; padding: 18px 10px 4px; }
    .rb-title { flex: 1; text-align: center; font-weight: 700; font-size: 12px; padding-right: 6%; }
    .rb-period { font-size: 9px; font-weight: 700; text-align: left; min-width: 24%; }
    .rb-period .rb-date { font-weight: 400; margin-top: 8px; }
    table.rb-table { width: 100%; border-collapse: collapse; }
    table.rb-table th { background: #d9d9d9; border: 1.5px solid #000; border-left: 0; border-right: 0; padding: 3px 4px; font-size: 10px; text-align: center; }
    table.rb-table td { padding: 2px 5px; height: 14px; border-left: 1px solid #000; font-size: 10px; }
    table.rb-table td:first-child, table.rb-table th:first-child { border-left: 0; }
    table.rb-table th + th { border-left: 1px solid #000; }
    table.rb-table td.c { text-align: center; }
    table.rb-table td.num { text-align: right; white-space: nowrap; }
    table.rb-table .rp { float: left; }
    table.rb-table tr.rb-last td { border-bottom: 1.5px solid #000; }
    .rb-sum { display: flex; justify-content: flex-end; }
    .rb-sum table { border-collapse: collapse; width: 34%; }
    .rb-sum td { padding: 1px 6px; font-size: 10px; font-weight: 700; }
    .rb-sum td.lbl { text-align: right; }
    .rb-sum td.val { text-align: right; white-space: nowrap; }
    .rb-sum tr.sub td { background: #92d050; }
    .rb-sum tr.round td { background: #ffff00; }
    .rb-sign { display: flex; justify-content: space-between; margin: 10px 8% 0 28%; text-align: center; font-size: 10px; }
    .rb-sign .col { min-width: 130px; }
    .rb-sign .sig { height: 54px; display: flex; align-items: center; justify-content: center; }
    .rb-sign .sig img { max-height: 54px; max-width: 120px; }
    .rb-sign .sig-ph { color: #999; }
    .rb-status-note { margin-top: 10px; font-size: 9px; color: #555; }
    @page { size: A4; margin: 10mm; }
    @media print {
        .rb-print-page { border: none; margin: 0; max-width: none; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; color-adjust: exact; }
    }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
    <a href="<?= BASE_URL ?>/request_budget/detail/<?= (int) $rb['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="rb-print-page print-page">
    <div class="rb-box">
        <div class="rb-head">
            <?php if (!empty($company['company_logo'])): ?>
                <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo" class="rb-logo">
            <?php else: ?>
                <div class="rb-company-name"><?= e($company['company_name'] ?: 'Perusahaan') ?></div>
            <?php endif; ?>
            <div class="rb-company-meta">
                <strong><?= e($company['company_name'] ?: '') ?></strong>
                <?= nl2br(e($company['company_address'] ?? '')) ?>
                <?php if (!empty($company['company_phone'])): ?><br><?= e($company['company_phone']) ?><?php endif; ?>
            </div>
        </div>

        <div class="rb-titlebar">
            <div class="rb-title">REQUEST BUDGET <?= e(mb_strtoupper($rb['purpose'])) ?></div>
            <div class="rb-period">
                <?= $periode !== '' ? 'PERIODE ' . e(mb_strtoupper($periode)) : '&nbsp;' ?>
                <div class="rb-date"><?= e(formatTanggal($rb['request_date'])) ?></div>
            </div>
        </div>

        <table class="rb-table">
            <thead>
                <tr><th style="width:5%">No</th><th>Keterangan</th><th style="width:12%">Qty</th><th style="width:12%">Unit</th><th style="width:13%">Harga</th><th style="width:16%">Total</th></tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < max($minRows, count($items)); $i++):
                    $it = $items[$i] ?? null; ?>
                    <tr<?= $i === max($minRows, count($items)) - 1 ? ' class="rb-last"' : '' ?>>
                        <td class="c"><?= $i + 1 ?></td>
                        <td><?= $it ? e($it['item_name'] . (!empty($it['description']) ? ' - ' . $it['description'] : '')) : '' ?></td>
                        <td class="c"><?= $it ? e($qtyFmt($it['qty'])) : '' ?></td>
                        <td class="c"><?= $it ? e(mb_strtoupper((string) $it['unit_name'])) : '' ?></td>
                        <td class="num"><?= $it ? e($fmt($it['estimated_unit_price'])) : '' ?></td>
                        <td class="num"><?= $it ? '<span class="rp">Rp</span>' . e($fmt($it['estimated_total'])) : '' ?></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <div class="rb-sum">
            <table>
                <tr class="sub"><td class="lbl">Sub Total</td><td>Rp</td><td class="val"><?= e($fmt($subTotal)) ?></td></tr>
                <tr class="round"><td class="lbl">Pembulatan</td><td>Rp</td><td class="val"><?= e($fmt($rounded)) ?></td></tr>
            </table>
        </div>
    </div>

    <div class="rb-sign">
        <div class="col">
            <div>Disetujui</div>
            <div class="sig">
                <?php if (!empty($rb['approver_signature'])): ?>
                    <img src="<?= BASE_URL ?>/<?= e($rb['approver_signature']) ?>" alt="TTD Disetujui">
                <?php else: ?><span class="sig-ph"><?= $rb['approved_by_name'] ? '' : '(.....................)' ?></span><?php endif; ?>
            </div>
            <div><?= e($rb['approved_by_name'] ?? '') ?></div>
        </div>
        <div class="col">
            <div>Dibuat</div>
            <div class="sig">
                <?php if (!empty($rb['requester_signature'])): ?>
                    <img src="<?= BASE_URL ?>/<?= e($rb['requester_signature']) ?>" alt="TTD Dibuat">
                <?php else: ?><span class="sig-ph">(.....................)</span><?php endif; ?>
            </div>
            <div><?= e($rb['requester_name']) ?></div>
        </div>
    </div>
    <div class="rb-status-note no-print">No. <?= e($rb['request_number']) ?> &middot; Status: <?= e(RequestBudget::statusLabel($rb['status'])) ?> &middot; Project: <?= e($rb['project_name']) ?></div>
</div>

<?php if (!empty($autoprint)): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
<?php endif; ?>
