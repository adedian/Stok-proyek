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
    td.end, th.end { text-align: right; white-space: nowrap; }
    tr.total-row td { font-weight: bold; border-top: 2px solid #212529; }
    tr.grp td { font-weight: bold; background-color: #1f4e79; color: #ffffff; border-color: #1f4e79; }
    tr.subtotal-row td { font-weight: bold; background-color: #e7f1ff; }
    .footnote { margin-top: 8px; color: #6c757d; font-size: 9px; }
    .print-note { position: fixed; bottom: 0; left: 0; right: 0; text-align: right; padding: 4px 14px; font-size: 9px; color: #999; }
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
            <?php
            // $groupBy (mis. Invoice Keluar per kategori barang): baris judul kelompok + penomoran
            // mulai 1 + SUBTOTAL per kelompok, lalu TOTAL KESELURUHAN. Tanpa $groupBy: tampilan lama.
            $isGrouped = !empty($groupBy) && !empty($rows);
            $sections = $isGrouped ? reportGroupRows($rows, $groupBy) : [['key' => null, 'name' => null, 'rows' => $rows]];
            $firstSumIdx = null;
            foreach ($columns as $__i => $__c) {
                if (!empty($__c['sum'])) { $firstSumIdx = $__i; break; }
            }
            ?>
            <?php foreach ($sections as $section): ?>
            <?php if ($section['name'] !== null): ?>
                <tr class="grp"><td colspan="<?= count($columns) + 1 ?>"><?= e($groupBy['title'] ?? 'Kelompok') ?>: <?= e($section['name']) ?> (<?= count($section['rows']) ?> <?= e($groupBy['unit'] ?? 'baris') ?>)</td></tr>
            <?php endif; ?>
            <?php foreach ($section['rows'] as $rowIndex => $row): ?>
                <tr>
                    <td><?= $rowIndex + 1 ?></td>
                    <?php foreach ($columns as $col): ?>
                        <td class="<?= ($col['align'] ?? '') === 'end' ? 'end' : '' ?>">
                            <?= e(formatReportValue($row[$col['field']] ?? null, $col['format'] ?? 'text', $row)) ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <?php if ($isGrouped && $firstSumIdx !== null): ?>
                <tr class="subtotal-row">
                    <td colspan="<?= $firstSumIdx + 1 ?>" class="end">Subtotal <?= e($section['name']) ?></td>
                    <?php foreach ($columns as $__i => $col): if ($__i < $firstSumIdx) continue; ?>
                        <?php if (!empty($col['sum'])): ?>
                            <td class="end"><?= e(formatReportTotal($section['rows'], $col['field'], $col['format'] ?? 'rupiah')) ?></td>
                        <?php else: ?>
                            <td></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            <?php
            // Baris TOTAL: SUM tiap kolom nominal yang ditandai 'sum'=>true (bukan semua
            // kolom rupiah -- kolom "Harga Satuan" misalnya rupiah tapi TIDAK boleh
            // dijumlahkan lintas baris) dari $rows yang SAMA dengan yang ditampilkan di
            // atas (sudah kena filter aktif). Posisi tiap sel mengikuti posisi kolom
            // aslinya persis, bukan colspan, supaya nilainya tidak salah kolom.
            $hasSumCols = !empty(array_filter($columns, fn($c) => !empty($c['sum'])));
            ?>
            <?php if ($hasSumCols && !empty($rows)): ?>
                <tr class="total-row">
                    <?php if ($isGrouped && $firstSumIdx !== null): ?>
                        <td colspan="<?= $firstSumIdx + 1 ?>" class="end">TOTAL KESELURUHAN</td>
                        <?php foreach ($columns as $__i => $col): if ($__i < $firstSumIdx) continue; ?>
                            <?php if (!empty($col['sum'])): ?>
                                <td class="end"><?= e(formatReportTotal($rows, $col['field'], $col['format'] ?? 'rupiah')) ?></td>
                            <?php else: ?>
                                <td></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <td class="end">TOTAL</td>
                    <?php foreach ($columns as $col): ?>
                        <?php if (!empty($col['sum'])): ?>
                            <td class="end"><?= e(formatReportTotal($rows, $col['field'], $col['format'] ?? 'rupiah')) ?></td>
                        <?php else: ?>
                            <td></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if (!empty($footnote)): ?>
        <div class="footnote"><?= e($footnote) ?></div>
    <?php endif; ?>
    <div class="print-note"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
</body>
</html>
