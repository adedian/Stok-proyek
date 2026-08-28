<?php
/**
 * Cetak PDF Laporan Kas (Dompdf). Var: $ledger (dari CashTransaction::reportLedger()),
 * $company (nama perusahaan), $periodText.
 * Format buku kas: Saldo Awal + kolom Masuk / Keluar / Saldo Akhir berjalan.
 */
$rp = static fn($v) => number_format((float) $v, 0, ',', '.');
$qtyFmt = static function ($v) {
    $v = (float) $v;
    return $v == (int) $v ? number_format($v, 0, ',', '.') : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
};
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 10px; color: #212529; }
    h2 { margin: 0 0 2px; text-align: center; }
    .sub { text-align: center; color: #0070C0; margin-bottom: 2px; font-weight: bold; }
    .meta { text-align: center; color: #0070C0; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #dee2e6; padding: 4px 6px; text-align: left; }
    th { background-color: #f1f3f5; text-align: center; }
    td.end, th.end { text-align: right; }
    tr.awal td, tr.akhir td { font-weight: bold; background-color: #f1f3f5; }
    .print-note { position: fixed; bottom: 0; left: 0; right: 0; text-align: right; padding: 4px 14px; font-size: 9px; color: #999; }
</style>
</head>
<body>
    <h2>Laporan Kas</h2>
    <div class="sub"><?= e($company) ?></div>
    <div class="meta"><?= e($periodText) ?></div>

    <table>
        <thead>
            <tr>
                <th>Tgl</th>
                <th>No Bukti</th>
                <th>Uraian</th>
                <th class="end">Qty</th>
                <th class="end">Satuan</th>
                <th class="end">Masuk</th>
                <th class="end">Keluar</th>
                <th class="end">Saldo Akhir</th>
            </tr>
        </thead>
        <tbody>
            <tr class="awal">
                <td colspan="7" style="text-align:center;">Saldo Awal</td>
                <td class="end"><?= $rp($ledger['saldo_awal']) ?></td>
            </tr>
            <?php if (empty($ledger['rows'])): ?>
                <tr><td colspan="8" style="text-align:center;">Tidak ada transaksi Kas pada periode ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($ledger['rows'] as $row): ?>
                <tr>
                    <td><?= $row['trx_date'] !== '' ? e(date('j-M-y', strtotime($row['trx_date']))) : '' ?></td>
                    <td><?= e($row['no_bukti']) ?></td>
                    <td><?= e($row['uraian']) ?></td>
                    <td class="end"><?= $qtyFmt($row['qty']) ?></td>
                    <td class="end"><?= $rp($row['satuan']) ?></td>
                    <td class="end"><?= $row['masuk'] > 0 ? $rp($row['masuk']) : '' ?></td>
                    <td class="end"><?= $row['keluar'] > 0 ? $rp($row['keluar']) : '' ?></td>
                    <td class="end"><?= $rp($row['saldo']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="akhir">
                <td colspan="7" class="end">Saldo Akhir</td>
                <td class="end"><?= $rp($ledger['saldo_akhir']) ?></td>
            </tr>
        </tbody>
    </table>
    <div class="print-note"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
</body>
</html>
