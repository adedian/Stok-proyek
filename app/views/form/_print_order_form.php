<?php
$ofItems = $d['items'] ?? [];
$ofSubtotal = 0.0;
foreach ($ofItems as &$ofIt) {
    $ofIt['amount'] = round((float) $ofIt['qty'] * (float) $ofIt['price'], 2);
    $ofSubtotal += $ofIt['amount'];
}
unset($ofIt);
$ofTax = round($ofSubtotal * (float) ($d['tax_percent'] ?? 0) / 100, 2);
$ofTotal = $ofSubtotal + $ofTax;
$ofRows = max(10, count($ofItems)); // minimal 10 baris, seperti form kosong
$ofQty = fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$ofMoney = fn($v) => number_format((float) $v, 2, '.', ',');
?>
<div class="bon-logo">
    <?php if (!empty($company['company_logo'])): ?>
        <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo">
    <?php endif; ?>
</div>

<div class="of-title">ORDER FORM</div>

<div class="of-meta">
    <div>DATE : <?= !empty($d['order_date']) ? e(date('d/m/Y', strtotime($d['order_date']))) : '' ?></div>
    <div style="width: 50%;">ORDER NUMBER : <?= e($d['order_number'] ?? '') ?></div>
</div>

<table class="of-table">
    <tr>
        <td rowspan="2" style="width: 62%; height: 10mm;"><div class="of-field"><span class="of-label of-lbl">NAME</span><span class="of-col">:</span><span class="of-val"><?= e($d['name'] ?? '') ?></span></div></td>
        <td><div class="of-field"><span class="of-label of-lbl-sm">PHONE</span><span class="of-col">:</span><span class="of-val"><?= e($d['phone'] ?? '') ?></span></div></td>
    </tr>
    <tr>
        <td><div class="of-field"><span class="of-label of-lbl-sm">EMAIL</span><span class="of-col">:</span><span class="of-val"><?= e($d['email'] ?? '') ?></span></div></td>
    </tr>
    <tr>
        <td colspan="2" style="height: 20mm;"><div class="of-field"><span class="of-label of-lbl">ADDRESS</span><span class="of-col">:</span><span class="of-val"><?= nl2br(e($d['address'] ?? '')) ?></span></div></td>
    </tr>
</table>
<div class="of-gap"></div>
<table class="of-table">
    <tr>
        <td style="width: 29%;"><span class="of-label">PAYMENT</span></td>
        <td colspan="2">
            <span style="display: inline-block; width: 50%;"><span class="of-radio <?= ($d['payment'] ?? '') === 'cash' ? 'on' : '' ?>"></span>CASH</span>
            <span class="of-radio <?= ($d['payment'] ?? '') === 'transfer' ? 'on' : '' ?>"></span>TRANSFER
        </td>
    </tr>
    <tr>
        <td><span class="of-label">NOTES</span></td>
        <td colspan="2" style="height: 8mm;"><?= nl2br(e($d['notes'] ?? '')) ?></td>
    </tr>
</table>
<div class="of-gap"></div>
<table class="of-table">
    <tr class="of-head">
        <th colspan="2" style="width: 26mm;">QTY</th>
        <th>DESCRIPTION</th>
        <th style="width: 28mm;">PRICE</th>
        <th style="width: 32mm;">AMOUNT</th>
    </tr>
    <?php for ($i = 0; $i < $ofRows; $i++): $it = $ofItems[$i] ?? null; ?>
        <tr class="of-row">
            <td class="of-num"><?= $i + 1 ?></td>
            <td class="of-right"><?= $it ? e($ofQty($it['qty'])) : '' ?></td>
            <td><?= $it ? e($it['description']) : '' ?></td>
            <td class="of-right"><?= $it ? e($ofMoney($it['price'])) : '' ?></td>
            <td class="of-right"><?= $it ? e($ofMoney($it['amount'])) : '' ?></td>
        </tr>
    <?php endfor; ?>
    <tr>
        <td colspan="3" rowspan="2" style="vertical-align: top;">
            <div style="position: relative; height: 36mm;">
                <span class="of-label">SIGNED</span>
                <div style="position: absolute; left: 0; right: 0; bottom: 0; display: flex; justify-content: space-between; font-weight: 700;">
                    <span>SALES</span><span style="margin-right: 30%;">CUSTOMER</span>
                </div>
            </div>
        </td>
        <td style="height: 7mm;"><span class="of-label">TAX</span><?= (float) ($d['tax_percent'] ?? 0) > 0 ? ' (' . e($ofQty($d['tax_percent'])) . '%)' : '' ?></td>
        <td class="of-right"><?= $ofTax > 0 ? e($ofMoney($ofTax)) : '' ?></td>
    </tr>
    <tr>
        <td style="height: 29mm;"><span class="of-label">TOTAL</span></td>
        <td class="of-right"><?= e($ofMoney($ofTotal)) ?></td>
    </tr>
</table>
