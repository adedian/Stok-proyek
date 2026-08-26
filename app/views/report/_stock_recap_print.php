<?php
/**
 * Cetak Rekap - Laporan Stok Barang (mengikuti Gambar 3 referensi).
 * Variabel yang tersedia: $rows (dari Inventory::stockRecapReport()), $dateFrom, $dateTo.
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
    h2 { margin-bottom: 4px; }
    .meta { color: #6c757d; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #dee2e6; padding: 4px 6px; text-align: left; }
    th { background-color: #f1f3f5; text-align: center; }
    td.end, th.end { text-align: right; }
    .grp-awal { background-color: #fde3d3; }
    .grp-in { background-color: #d9f2e3; }
    .grp-out { background-color: #d6e8fb; }
    .grp-akhir { background-color: #e6e6e6; }
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
                <th colspan="2" class="grp-in">In</th>
                <th colspan="2" class="grp-out">Out</th>
                <th colspan="2" class="grp-akhir">Saldo Akhir</th>
            </tr>
            <tr>
                <th class="end grp-awal">Qty</th><th class="grp-awal">Satuan</th>
                <th class="end grp-in">Qty</th><th class="grp-in">Satuan</th>
                <th class="end grp-out">Qty</th><th class="grp-out">Satuan</th>
                <th class="end grp-akhir">Qty</th><th class="grp-akhir">Satuan</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $r): ?>
            <tr>
                <td class="end"><?= $i + 1 ?></td>
                <td><?= e($r['item_code'] ?: '-') ?></td>
                <td><?= e($r['item_name']) ?></td>
                <td class="end"><?= formatQtyPrint($r['saldo_awal']) ?></td>
                <td><?= e($r['unit']) ?></td>
                <td class="end"><?= formatQtyPrint($r['mutasi_masuk']) ?></td>
                <td><?= e($r['unit']) ?></td>
                <td class="end"><?= formatQtyPrint($r['mutasi_keluar']) ?></td>
                <td><?= e($r['unit']) ?></td>
                <td class="end"><?= formatQtyPrint($r['saldo_akhir']) ?></td>
                <td><?= e($r['unit']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</body>
</html>
