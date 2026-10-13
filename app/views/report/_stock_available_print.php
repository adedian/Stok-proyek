<?php
/**
 * Cetak Stok Tersedia - Laporan Stok Barang (1 baris/barang, tanpa harga).
 * Variabel: $rows (Inventory::stockMutationReport() disaring saldo_akhir > 0),
 *           $companyName. Mirror Excel streamStockAvailableExcel().
 */
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
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 10px; color: #212529; }
    .ttl { text-align: center; font-size: 15px; font-weight: bold; color: #C55A11; margin: 0 0 3px; }
    .co { text-align: center; font-size: 12px; font-weight: bold; color: #0070C0; margin: 0 0 14px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
    th { text-align: center; }
    td.c { text-align: center; }
    td.end { text-align: right; }
    .print-note { position: fixed; bottom: 0; left: 0; right: 0; text-align: right; padding: 4px 14px; font-size: 9px; color: #999; }
</style>
</head>
<body>
    <div class="ttl">Laporan Stok Barang - Rekap</div>
    <div class="co"><?= e($companyName) ?></div>
    <?php if (empty($rows)): ?>
        <p>Tidak ada stok tersedia yang cocok dengan filter ini.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px;">No</th>
                <th rowspan="2" style="width: 100px;">Kode Barang</th>
                <th rowspan="2">Nama Barang</th>
                <th colspan="2">Stock Tersedia</th>
            </tr>
            <tr>
                <th style="width: 50px;">Qty</th>
                <th style="width: 50px;">Satuan</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td class="c"><?= $i + 1 ?></td>
                <td><?= e($r['item_code'] ?: '-') ?></td>
                <td><?= e($r['item_name']) ?></td>
                <td class="end"><?= formatQtyPrint($r['saldo_akhir']) ?></td>
                <td><?= e($r['unit']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <div class="print-note"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
</body>
</html>
