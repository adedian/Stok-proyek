<?php
/**
 * Cetak PDF Laporan Kas "Tarik Semua" (Dompdf) -- BARU, Revisi Kas/Bank.
 * Var: $groups (['pic_name' => ['pic','rows'=>[...],'masuk','keluar']]),
 * $grandMasuk, $grandKeluar, $company, $periodText, $reportTitle.
 *
 * Beda dari _report_pdf.php (buku kas kronologis dengan saldo berjalan):
 * di sini dikelompokkan per PIC, tiap kelompok subtotal Masuk/Keluar, lalu
 * Grand Total di akhir. Kolom Project (bukan Qty/Satuan/Saldo Akhir) --
 * mengikuti contoh struktur yang diminta user.
 */
$reportTitle = $reportTitle ?? 'Laporan Kas';
$rp = static fn($v) => number_format((float) $v, 0, ',', '.');
$grandSaldo = (float) $grandMasuk - (float) $grandKeluar;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: sans-serif; font-size: 10px; color: #212529; }
    h2 { margin: 0 0 2px; text-align: center; }
    .sub { text-align: center; color: #0070C0; margin-bottom: 2px; font-weight: bold; }
    .meta { text-align: center; color: #0070C0; margin-bottom: 4px; }
    .tarik-note { text-align: center; color: #6c757d; margin-bottom: 12px; font-style: italic; }
    .pic-title { background-color: #212529; color: #fff; font-weight: bold; padding: 5px 8px; margin-top: 14px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 2px; }
    th, td { border: 1px solid #dee2e6; padding: 4px 6px; text-align: left; }
    th { background-color: #f1f3f5; text-align: center; }
    td.end, th.end { text-align: right; }
    tr.subtotal td { font-weight: bold; background-color: #f8f9fa; }
    .grand-total { margin-top: 18px; border: 1px solid #212529; }
    .grand-total th { background-color: #212529; color: #fff; }
    .grand-total td { font-weight: bold; }
    .print-note { position: fixed; bottom: 0; left: 0; right: 0; text-align: right; padding: 4px 14px; font-size: 9px; color: #999; }
</style>
</head>
<body>
    <h2><?= e($reportTitle) ?></h2>
    <div class="sub"><?= e($company) ?></div>
    <div class="meta"><?= e($periodText) ?></div>
    <div class="tarik-note">Tarik Semua &mdash; dikelompokkan per PIC</div>

    <?php if (empty($groups)): ?>
        <table><tr><td style="text-align:center;">Tidak ada transaksi Kas pada periode/filter ini.</td></tr></table>
    <?php endif; ?>

    <?php foreach ($groups as $g): ?>
        <div class="pic-title">PIC: <?= e(mb_strtoupper($g['pic'])) ?></div>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No Bukti</th>
                    <th>Project</th>
                    <th>Uraian</th>
                    <th class="end">Masuk</th>
                    <th class="end">Keluar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($g['rows'] as $row): ?>
                    <tr>
                        <td><?= $row['trx_date'] !== '' ? e(date('j-M-y', strtotime($row['trx_date']))) : '' ?></td>
                        <td><?= e($row['no_bukti']) ?></td>
                        <td><?= e($row['project_name'] ?? '') ?: '-' ?></td>
                        <td><?= e($row['uraian']) ?></td>
                        <td class="end"><?= $row['masuk'] != 0 ? $rp($row['masuk']) : '' ?></td>
                        <td class="end"><?= $row['keluar'] != 0 ? $rp($row['keluar']) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="subtotal">
                    <td colspan="4" class="end">Total PIC <?= e(mb_strtoupper($g['pic'])) ?></td>
                    <td class="end"><?= $rp($g['masuk']) ?></td>
                    <td class="end"><?= $rp($g['keluar']) ?></td>
                </tr>
            </tbody>
        </table>
    <?php endforeach; ?>

    <?php if (!empty($groups)): ?>
    <table class="grand-total">
        <thead>
            <tr>
                <th colspan="4">GRAND TOTAL</th>
                <th class="end">Masuk</th>
                <th class="end">Keluar</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="4">Total Masuk / Keluar / Saldo</td>
                <td class="end"><?= $rp($grandMasuk) ?></td>
                <td class="end"><?= $rp($grandKeluar) ?></td>
            </tr>
            <tr>
                <td colspan="6" class="end">Saldo: <?= $rp($grandSaldo) ?></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="print-note"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
</body>
</html>
