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
    tr.total-row td { font-weight: bold; border-top: 2px solid #212529; }
    .print-note { margin-top: 16px; text-align: right; font-size: 9px; color: #999; }
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
