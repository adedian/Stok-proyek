<?php
$bonItems = $d['items'] ?? [];
$bonTotal = 0.0;
foreach ($bonItems as $bi) {
    $bonTotal += (float) $bi['amount'];
}
$bonRows = max(8, count($bonItems)); // minimal 8 baris, seperti form kosong
$bonMoney = fn($v) => number_format((float) $v, 2, '.', ',');
$bonPlace = (($d['city'] ?? '') !== '' ? $d['city'] . ', ' : '') . formatTanggal($d['request_date'] ?? null);
?>
<div class="bon-logo">
    <?php if (!empty($company['company_logo'])): ?>
        <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo">
    <?php endif; ?>
</div>

<div class="bon-title">PENGAJUAN BON SEMENTARA</div>

<div class="bon-head">
    <table>
        <tr><td>Nama</td><td>:</td><td><?= e($d['name'] ?? '') ?></td></tr>
        <tr><td>Jabatan</td><td>:</td><td><?= e($d['position'] ?? '') ?></td></tr>
        <tr><td>Keperluan</td><td>:</td><td><?= e($d['purpose'] ?? '') ?></td></tr>
    </table>
    <table>
        <tr><td>Tgl. Pengajuan :</td><td style="text-align: right;"><?= e(formatTanggal($d['request_date'] ?? null)) ?></td></tr>
        <tr><td>Bon Sementara :</td><td><strong>Rp &nbsp; <?= e($bonMoney($bonTotal)) ?></strong></td></tr>
    </table>
</div>

<table class="bon-table">
    <tr>
        <th style="width: 10mm;">No.</th>
        <th>Estimasi Rincian Pengeluaran</th>
        <th style="width: 42mm;">Total</th>
    </tr>
    <?php for ($i = 0; $i < $bonRows; $i++): $it = $bonItems[$i] ?? null; ?>
        <tr class="bon-row">
            <td class="bon-no"><?= $it ? $i + 1 : '' ?></td>
            <td class="bon-desc"><?= $it ? e($it['description']) : '' ?></td>
            <td class="bon-amt"><?= $it ? '<span><span>Rp</span><span>' . e($bonMoney($it['amount'])) . '</span></span>' : '' ?></td>
        </tr>
    <?php endfor; ?>
    <tr class="bon-total">
        <td colspan="2">TOTAL PENGELUARAN</td>
        <td class="bon-amt"><span><span>Rp</span><span><?= e($bonMoney($bonTotal)) ?></span></span></td>
    </tr>
</table>

<div class="bon-place"><?= e($bonPlace) ?></div>

<div class="bon-sign">
    <?php foreach (['Disetujui' => 'approved_by', 'Mengetahui' => 'known_by', 'Dibuat' => 'made_by'] as $label => $key): ?>
        <div class="bon-sign-col">
            <div><?= e($label) ?>,</div>
            <div class="bon-sign-space"></div>
            <div>( <?= !empty($d[$key]) ? e($d[$key]) : str_repeat('&nbsp;', 24) ?> )</div>
        </div>
    <?php endforeach; ?>
</div>
