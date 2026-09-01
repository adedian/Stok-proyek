<?php
/**
 * Cetak Rekap - Laporan Stok Barang (1 baris/barang).
 * Variabel: $rows (dari Inventory::stockRecapReport()), $dateFrom, $dateTo,
 *           $showPrice (bool -- tampilkan kolom "Dengan Harga" + baris total nilai).
 *
 * Saat $showPrice = false, seluruh kolom/baris harga dihilangkan (11 kolom).
 * Sub-blok "Dengan Harga": Harga Satuan = harga beli terakhir; Total (In) =
 * total masuk x harga; Total (Saldo Akhir) = saldo akhir x harga.
 */
$showPrice = $showPrice ?? true;
if (!function_exists('formatQtyPrint')) {
    function formatQtyPrint($value): string
    {
        $value = (float) $value;
        if ($value == (int) $value) {
            return number_format($value, 0, ',', '.');
        }
        return number_format($value, 2, ',', '.');
    }
}
if (!function_exists('formatMoneyPrint')) {
    function formatMoneyPrint($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return number_format((float) $value, 0, ',', '.');
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 9px; color: #212529; }
    h2 { margin-bottom: 4px; }
    .meta { color: #6c757d; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #dee2e6; padding: 3px 5px; text-align: left; }
    th { background-color: #f1f3f5; text-align: center; }
    td.end, th.end { text-align: right; }
    .grp-awal { background-color: #fde3d3; }
    .grp-awal-h { background-color: #fef1e9; }
    .grp-in { background-color: #d9f2e3; }
    .grp-in-h { background-color: #ecf9f1; }
    .grp-out { background-color: #d6e8fb; }
    .grp-out-h { background-color: #ebf4fd; }
    .grp-akhir { background-color: #fff3a3; }
    .grp-akhir-h { background-color: #fff9d1; }
    tr.grand-row td { font-weight: bold; background-color: #fff3a3; border-top: 2px solid #adb5bd; }
    .print-note { position: fixed; bottom: 0; left: 0; right: 0; text-align: right; padding: 4px 14px; font-size: 9px; color: #999; }
</style>
</head>
<body>
    <h2>Laporan Stok Barang - Cetak Rekap</h2>
    <div class="meta">
        Periode: <?= $dateFrom !== '' ? formatTanggal($dateFrom) : '(seluruh riwayat)' ?> &ndash; <?= $dateTo !== '' ? formatTanggal($dateTo) : 'Sekarang' ?>
        &nbsp;|&nbsp; Dicetak: <?= formatTanggal(date('Y-m-d')) ?> <?= date('H:i') ?>
    </div>
    <?php if (empty($rows)): ?>
        <p>Tidak ada data barang yang cocok dengan filter ini.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th rowspan="2">No</th>
                <th rowspan="2">Kode Barang</th>
                <th rowspan="2">Nama Barang</th>
                <th colspan="2" class="grp-awal">Saldo Awal</th>
                <?php if ($showPrice): ?><th colspan="2" class="grp-awal-h">Dengan Harga</th><?php endif; ?>
                <th colspan="2" class="grp-in">In</th>
                <?php if ($showPrice): ?><th colspan="2" class="grp-in-h">Dengan Harga</th><?php endif; ?>
                <th colspan="2" class="grp-out">Out</th>
                <?php if ($showPrice): ?><th colspan="2" class="grp-out-h">Dengan Harga</th><?php endif; ?>
                <th colspan="2" class="grp-akhir">Saldo Akhir</th>
                <?php if ($showPrice): ?><th colspan="2" class="grp-akhir-h">Dengan Harga</th><?php endif; ?>
            </tr>
            <tr>
                <th class="end grp-awal">Qty</th><th class="grp-awal">Satuan</th>
                <?php if ($showPrice): ?><th class="end grp-awal-h">Harga Satuan</th><th class="end grp-awal-h">Total</th><?php endif; ?>
                <th class="end grp-in">Qty</th><th class="grp-in">Satuan</th>
                <?php if ($showPrice): ?><th class="end grp-in-h">Harga Satuan</th><th class="end grp-in-h">Total</th><?php endif; ?>
                <th class="end grp-out">Qty</th><th class="grp-out">Satuan</th>
                <?php if ($showPrice): ?><th class="end grp-out-h">Harga Satuan</th><th class="end grp-out-h">Total</th><?php endif; ?>
                <th class="end grp-akhir">Qty</th><th class="grp-akhir">Satuan</th>
                <?php if ($showPrice): ?><th class="end grp-akhir-h">Harga Satuan</th><th class="end grp-akhir-h">Total</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php $grandIn = 0.0; $grandOut = 0.0; $grandAkhir = 0.0; ?>
        <?php foreach ($rows as $i => $r): ?>
            <?php
                $priced = ($r['in_unit_price'] ?? null) !== null;
                $akhirPriced = ($r['saldo_akhir_price'] ?? null) !== null;
                $grandIn += (float) ($r['in_value'] ?? 0);
                $grandOut += (float) ($r['out_value'] ?? 0);
                $grandAkhir += (float) ($r['saldo_akhir_value'] ?? 0);
            ?>
            <tr>
                <td class="end"><?= $i + 1 ?></td>
                <td><?= e($r['item_code'] ?: '-') ?></td>
                <td><?= e($r['item_name']) ?></td>

                <td class="end"><?= formatQtyPrint($r['saldo_awal']) ?></td>
                <td><?= e($r['unit']) ?></td>
                <?php if ($showPrice): ?>
                    <td class="end"></td>
                    <td class="end"></td>
                <?php endif; ?>

                <td class="end"><?= formatQtyPrint($r['mutasi_masuk']) ?></td>
                <td><?= e($r['unit']) ?></td>
                <?php if ($showPrice): ?>
                    <td class="end"><?= $priced ? formatMoneyPrint($r['in_unit_price']) : '' ?></td>
                    <td class="end"><?= $priced ? formatMoneyPrint($r['in_value']) : '' ?></td>
                <?php endif; ?>

                <td class="end"><?= formatQtyPrint($r['mutasi_keluar']) ?></td>
                <td><?= e($r['unit']) ?></td>
                <?php if ($showPrice): ?>
                    <td class="end"><?= $akhirPriced && (float) $r['mutasi_keluar'] > 0 ? formatMoneyPrint($r['saldo_akhir_price']) : '' ?></td>
                    <td class="end"><?= $akhirPriced && (float) $r['mutasi_keluar'] > 0 ? formatMoneyPrint($r['out_value']) : '' ?></td>
                <?php endif; ?>

                <td class="end"><?= formatQtyPrint($r['saldo_akhir']) ?></td>
                <td><?= e($r['unit']) ?></td>
                <?php if ($showPrice): ?>
                    <td class="end"><?= $akhirPriced ? formatMoneyPrint($r['saldo_akhir_price']) : '' ?></td>
                    <td class="end"><?= $akhirPriced ? formatMoneyPrint($r['saldo_akhir_value']) : '' ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
            <?php if ($showPrice): ?>
            <tr class="grand-row">
                <td colspan="10" class="end">TOTAL SALDO KESELURUHAN</td>
                <td class="end"><?= formatMoneyPrint($grandIn) ?></td>
                <td colspan="3"></td>
                <td class="end"><?= formatMoneyPrint($grandOut) ?></td>
                <td colspan="3"></td>
                <td class="end"><?= formatMoneyPrint($grandAkhir) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <div class="print-note"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
</body>
</html>
