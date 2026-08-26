<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 11px; color: #212529; }
    h2 { margin-bottom: 4px; }
    .meta { color: #6c757d; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #dee2e6; padding: 5px 8px; text-align: left; }
    th { background-color: #f1f3f5; }
    td.end, th.end { text-align: right; }
</style>
</head>
<body>
    <h2><?= e($title) ?></h2>
    <div class="meta">Dicetak: <?= formatTanggal(date('Y-m-d')) ?> <?= date('H:i') ?></div>
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
        </tbody>
    </table>
</body>
</html>
