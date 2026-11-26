<?php
/**
 * Cetak "PENGAJUAN FEE" -- mengikuti sheet "Pengajuan Fee": judul, Invoice no./Sales/Status Tagihan | Customer/Penerima Fee/NPWP-NIK,
 * tabel BARANG | QTY | SATUAN | HARGA | TOTAL | FEE, TOTAL, Potong Pph, Total Fee diterima, NOTE, NB, kota + tanggal,
 * tanda tangan Disetujui / Diperiksa / Dibuat. Variabel: $f, $items, $autoprint
 */
$nf = fn($v) => number_format((float) $v, 0, '.', ',');
$pct = fn($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
$nm = fn($v) => $v ? e($v) : '&nbsp;..............................&nbsp;';
?>
<style>
    .fee-sheet { background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 12px; max-width: 210mm; margin: 0 auto 24px; padding: 12mm; box-sizing: border-box; }
    .fee-sheet h2 { text-align: center; font-size: 16px; font-weight: 700; margin: 0 0 14px; text-decoration: underline; }
    .fee-head { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .fee-head td { padding: 1px 4px; vertical-align: top; }
    .fee-head td.lbl { width: 90px; white-space: nowrap; }
    .fee-head td.sep { width: 8px; }
    .fee-tbl { width: 100%; border-collapse: collapse; }
    .fee-tbl th, .fee-tbl td { border: 1px solid #000; padding: 3px 6px; }
    .fee-tbl th { text-align: center; background: #f2f2f2; }
    .fee-tbl td.num { text-align: right; white-space: nowrap; }
    .fee-tbl tr.tot td { font-weight: 700; }
    .fee-sign { width: 100%; margin-top: 18px; border-collapse: collapse; text-align: center; }
    .fee-sign td { width: 33%; vertical-align: top; padding-top: 4px; }
    .fee-sign .space { height: 70px; }
    .fee-foot { margin-top: 10px; font-size: 8px; color: #888; text-align: right; }
    @page { size: A4 portrait; margin: 10mm; }
    @media print { .fee-sheet { max-width: none; margin: 0; padding: 0; } }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
    <a href="<?= BASE_URL ?>/pengajuan_fee/detail/<?= (int) $f['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="fee-sheet print-page">
    <h2>PENGAJUAN FEE</h2>
    <table class="fee-head">
        <tr><td class="lbl">Invoice no.</td><td class="sep">:</td><td><?= e($f['invoice_number'] ?? '') ?></td><td class="lbl">Customer</td><td class="sep">:</td><td><?= e($f['customer_name'] ?? '') ?></td></tr>
        <tr><td class="lbl">Sales</td><td class="sep">:</td><td><?= e($f['sales_name'] ?? '') ?></td><td class="lbl">Penerima Fee</td><td class="sep">:</td><td><?= e($f['recipient_name'] ?? '') ?></td></tr>
        <tr><td class="lbl">Status Tagihan</td><td class="sep">:</td><td><?= e($f['billing_status'] ?? '') ?></td><td class="lbl">NPWP / NIK</td><td class="sep">:</td><td><?= e($f['recipient_tax_id'] ?? '') ?></td></tr>
    </table>

    <table class="fee-tbl">
        <thead><tr><th>BARANG</th><th style="width:55px">QTY</th><th style="width:60px">SATUAN</th><th style="width:90px">HARGA</th><th style="width:100px">TOTAL</th><th style="width:100px">FEE</th></tr></thead>
        <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= e($it['item_name']) ?></td>
                    <td class="num"><?= e(rtrim(rtrim(number_format((float) $it['qty'], 2, '.', ','), '0'), '.')) ?></td>
                    <td><?= e($it['unit'] ?? '') ?></td>
                    <td class="num"><?= e($nf($it['price'])) ?></td>
                    <td class="num"><?= e($nf($it['total'])) ?></td>
                    <td class="num"><?= e($nf($it['fee'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="tot"><td colspan="4" class="num">TOTAL</td><td class="num"><?= e($nf($f['total_amount'])) ?></td><td class="num"><?= e($nf($f['total_fee'])) ?></td></tr>
            <tr><td colspan="4" class="num">Potong Pph <?= e($pct($f['pph_percent'])) ?>%</td><td></td><td class="num">-<?= e($nf($f['pph_amount'])) ?></td></tr>
            <tr class="tot"><td colspan="4" class="num">Total Fee diterima</td><td></td><td class="num"><?= e($nf($f['net_fee'])) ?></td></tr>
        </tbody>
    </table>

    <div style="margin-top:10px">
        <?php if (!empty($f['note'])): ?><div><strong>NOTE</strong> = <?= e($f['note']) ?></div><?php endif; ?>
        <?php if (!empty($f['nb'])): ?><div><strong>NB</strong> : <?= e($f['nb']) ?></div><?php endif; ?>
    </div>

    <div style="text-align:right;margin-top:14px"><?= e(($f['city'] ? $f['city'] . ', ' : '') . formatTanggalLengkap($f['fee_date'])) ?></div>
    <table class="fee-sign">
        <tr><td>Disetujui,</td><td>Diperiksa,</td><td>Dibuat,</td></tr>
        <tr><td class="space"></td><td class="space"></td><td class="space"></td></tr>
        <tr>
            <td>(<?= $nm($f['approved_by_name'] ?? null) ?>)</td>
            <td>(<?= $nm($f['checked_by_name'] ?? null) ?>)</td>
            <td>(<?= $nm($f['created_by_name'] ?? null) ?>)</td>
        </tr>
    </table>
    <div class="fee-foot"><?= e($f['fee_number']) ?> &middot; <?= e(printedAtLabel() . ', ' . printedByLabel()) ?></div>
</div>

<?php if (!empty($autoprint)): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
<?php endif; ?>
