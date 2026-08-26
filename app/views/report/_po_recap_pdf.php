<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 11px; color: #212529; }
    .po-recap-title { text-align: center; font-size: 18px; font-weight: bold; color: #C55A11; margin: 0 0 2px; }
    .po-recap-company { text-align: center; font-size: 13px; font-weight: bold; color: #0070C0; margin: 0 0 2px; }
    .po-recap-period { text-align: center; font-size: 12px; color: #0070C0; margin: 0 0 14px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #000; padding: 5px 8px; text-align: left; }
    th { font-weight: bold; text-align: center; }
    td.end, th.end { text-align: right; }
    tr.total-row td { font-weight: bold; border-top: 2px solid #000; }
    .print-note { margin-top: 16px; text-align: right; font-size: 9px; color: #999; }
</style>
</head>
<body>
    <p class="po-recap-title"><?= e($title) ?></p>
    <p class="po-recap-company"><?= e($companyName) ?></p>
    <p class="po-recap-period"><?= e($periodText) ?></p>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <?php foreach ($columns as $col): ?>
                    <th class="<?= ($col['align'] ?? '') === 'end' ? 'end' : '' ?>"><?= e($col['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="<?= count($columns) + 1 ?>">Tidak ada data.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $rowIndex => $row): ?>
                <tr>
                    <td><?= $rowIndex + 1 ?></td>
                    <?php foreach ($columns as $col): ?>
                        <td class="<?= ($col['align'] ?? '') === 'end' ? 'end' : '' ?>">
                            <?= e(formatReportValue($row[$col['field']] ?? null, $col['format'] ?? 'text')) ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php $hasSumCols = !empty(array_filter($columns, fn($c) => !empty($c['sum']))); ?>
            <?php if ($hasSumCols && !empty($rows)): ?>
                <tr class="total-row">
                    <td class="end">TOTAL</td>
                    <?php foreach ($columns as $col): ?>
                        <?php if (!empty($col['sum'])): ?>
                            <td class="end"><?= e(formatRupiah(array_sum(array_map(fn($r) => (float) ($r[$col['field']] ?? 0), $rows)))) ?></td>
                        <?php else: ?>
                            <td></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="print-note">
        Tanggal &amp; Jam: <?= e(printedAtLabel()) ?><br>
        Dicetak oleh: <?= e(printedByLabel()) ?>
    </div>
</body>
</html>
