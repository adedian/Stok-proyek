<?php
$kwAmount = (float) ($d['amount'] ?? 0);
$kwWords = ucwords(terbilang($kwAmount)) . ' Rupiah';
$kwPlace = (($d['city'] ?? '') !== '' ? $d['city'] . ', ' : '') . formatTanggal($d['form_date'] ?? null);
?>
<div class="kw-box">
    <?php $kwHasNo = !empty($f['form_number']); ?>
    <div class="kw-title"<?= $kwHasNo ? ' style="margin-bottom: 3mm;"' : '' ?>>KWITANSI</div>
    <?php if ($kwHasNo): ?>
        <div class="fm-docno">No. : <?= e($f['form_number']) ?></div>
    <?php endif; ?>

    <div class="kw-row">
        <div class="kw-label">Sudah terima dari</div><div class="kw-sep">:</div>
        <div class="kw-val"><?= e($d['received_from'] ?? '') ?></div>
    </div>
    <div class="kw-row" style="margin-top: 4mm;">
        <div class="kw-label">Banyaknya uang</div><div class="kw-sep">:</div>
        <div class="kw-val kw-italic kw-shade"><?= e($kwWords) ?></div>
    </div>
    <div class="kw-row" style="margin-top: 4mm;">
        <div class="kw-label">Untuk pembayaran</div><div class="kw-sep">:</div>
        <div class="kw-val kw-italic"><?= e($d['purpose'] ?? '') ?></div>
    </div>

    <div class="kw-place"><?= e($kwPlace) ?></div>

    <div class="kw-bottom">
        <div class="kw-amount-box">
            <span>Jumlah &nbsp; Rp.</span>
            <span class="kw-num"><?= number_format($kwAmount, 2, '.', ',') ?></span>
        </div>
        <div><?= e($d['signer_name'] ?? '') ?></div>
    </div>
</div>
