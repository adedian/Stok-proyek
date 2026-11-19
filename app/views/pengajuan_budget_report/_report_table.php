<?php
/**
 * Tabel "PERMINTAAN OTORISASI" -- dipakai layar & cetak (data identik).
 * Variabel: $report (PengajuanBudget::buildReport()), $print (bool, opsional).
 * Warna per rekening dari Master Bank (kolom warna / palet otomatis); latar tercetak lewat
 * print-color-adjust:exact di CSS halaman cetak.
 */
$print = !empty($print);
$nf = function ($v) {
    $v = (float) $v;
    $s = number_format(abs($v), fmod(abs($v), 1) == 0.0 ? 0 : 2, '.', ',');
    return ($v < 0 ? '-' : '') . $s;
};
$saldoDateLabel = date('d/m/Y', strtotime($report['saldo_date']));
// Tombol Edit Saldo / Riwayat hanya di layar (tidak ikut Cetak & Excel).
$canEditSaldo = !$print && can('pengajuan_budget_report', 'edit_saldo');
$rq = [];
foreach (['tanggal', 'date_from', 'date_to'] as $k) {
    if (!empty($filters[$k])) { $rq['r_' . $k] = $filters[$k]; }
}
$saldoBtns = function (array $s) use ($print, $canEditSaldo, $rq, $report) {
    if ($print) { return ''; }
    $h = '<div class="d-flex gap-1 justify-content-end align-items-center po-btns no-print">';
    if (!empty($s['manual_date'])) {
        $h .= '<span class="badge bg-light text-secondary border" title="Saldo dari catatan manual">manual ' . e(date('d/m/Y', strtotime($s['manual_date']))) . '</span>';
    }
    if ($canEditSaldo && in_array($s['jenis'], ['loan', 'hr'], true)) {
        $h .= '<a class="btn btn-sm btn-outline-primary py-0" href="' . e(BASE_URL . '/index.php?' . http_build_query(['module' => 'pengajuan_budget_report', 'action' => 'editSaldo', 'bank_id' => $s['bank_id'], 'date' => $report['saldo_date']] + $rq)) . '"><i class="bi bi-pencil-square"></i> Edit Saldo</a>';
    }
    $h .= '<a class="btn btn-sm btn-outline-secondary py-0" href="' . e(BASE_URL . '/index.php?' . http_build_query(['module' => 'pengajuan_budget_report', 'action' => 'saldoHistory', 'bank_id' => $s['bank_id']] + $rq)) . '"><i class="bi bi-clock-history"></i> Riwayat</a>';
    return $h . '</div>';
};
$bg = fn(string $c, string $t) => 'background-color:' . e($c) . ';color:' . e($t) . ';';
?>
<table class="po-table<?= $print ? ' po-print' : '' ?>">
    <thead>
        <tr>
            <?php /* Lebar kolom (total 100%): Keterangan paling lebar karena paling banyak teks;
                     Rekening Sumber / Jenis / Jumlah dibuat ringkas mengikuti isinya. */ ?>
            <th style="width:3.5%">No</th>
            <th style="width:9%">Rekening Sumber</th>
            <th style="width:8%">Jenis Transaksi</th>
            <th style="width:19%">Rekening Tujuan</th>
            <th style="width:11%">Jumlah Pembayaran</th>
            <th style="width:30%">Keterangan</th>
            <th style="width:9.5%">Kategori</th>
            <th style="width:10%">Project</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$report['groups']): ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding:18px">Tidak ada Pengajuan Budget pada filter ini.</td></tr>
        <?php endif; ?>

        <?php foreach ($report['groups'] as $g): $style = $bg($g['color'], $g['text_color']); ?>
            <?php foreach ($g['rows'] as $r): ?>
                <tr>
                    <td class="c"><?= (int) $r['no'] ?></td>
                    <td style="<?= $style ?>"><?= e($g['label']) ?></td>
                    <td style="<?= $style ?>"><?= e($r['jenis_transaksi']) ?></td>
                    <td><?= e($r['tujuan']) ?></td>
                    <td class="num"><?= e($nf($r['amount'])) ?></td>
                    <td class="wrap"><?= nl2br(e((string) $r['keterangan'])) ?></td>
                    <td class="wrap"><?= e((string) ($r['category_name'] ?? '')) ?></td>
                    <td class="wrap"><?= e((string) ($r['project_name'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="po-total" style="<?= $style ?>">
                <td></td><td></td><td></td>
                <td class="num" style="font-weight:700">TOTAL</td>
                <td class="num" style="font-weight:700"><?= e($nf($g['total'])) ?></td>
                <td></td><td></td><td></td>
            </tr>
            <tr class="po-gap"><td colspan="8"></td></tr>
        <?php endforeach; ?>

        <?php if ($report['groups']): ?>
            <tr class="po-grand">
                <td colspan="3" class="nob"></td>
                <td class="num" style="font-weight:700">TOTAL KESELURUHAN</td>
                <td class="num" style="font-weight:700"><?= e($nf($report['grand'])) ?></td>
                <td colspan="3" class="nob"></td>
            </tr>
        <?php endif; ?>

        <?php if ($report['saldo']): ?>
            <tr class="po-gap"><td colspan="8"></td></tr>
            <tr>
                <td colspan="3" class="nob"></td>
                <td colspan="2" class="nob" style="font-weight:700;font-size:1.05em">Saldo Bank sebelum pembayaran</td>
                <td colspan="3" class="nob"></td>
            </tr>
            <?php foreach ($report['saldo'] as $s): $style = $bg($s['color'], $s['text_color']); ?>
                <tr class="po-saldo">
                    <td colspan="3" class="nob"><?= $saldoBtns($s) ?></td>
                    <td style="<?= $style ?>">Saldo <?= e($s['bank_name']) ?> tgl. <?= e($saldoDateLabel) ?></td>
                    <td class="num po-red" style="<?= $style ?>"><?= e($nf($s['saldo'])) ?></td>
                    <?php if ($s['note'] !== ''): ?>
                        <td colspan="3" style="<?= $style ?>font-size:.85em"><?= e($s['note']) ?></td>
                    <?php else: ?>
                        <td colspan="3" class="nob"></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <tr class="po-gap"><td colspan="8"></td></tr>
            <tr>
                <td colspan="3" class="nob"></td>
                <td colspan="2" class="nob" style="font-weight:700;font-size:1.05em">Sisa Saldo setelah pembayaran</td>
                <td colspan="3" class="nob"></td>
            </tr>
            <?php foreach ($report['saldo'] as $s): $style = $bg($s['color'], $s['text_color']); ?>
                <tr class="po-saldo">
                    <td colspan="3" class="nob"></td>
                    <td style="<?= $style ?>">Saldo <?= e($s['bank_name']) ?> tgl. <?= e($saldoDateLabel) ?></td>
                    <td class="num po-red" style="<?= $style ?>"><?= e($nf($s['sisa'])) ?></td>
                    <td colspan="3" class="nob" style="font-size:.8em;color:#666">
                        <?php if ($s['realized'] > 0): ?>Rp <?= e($nf($s['realized'])) ?> sudah direalisasikan (tercermin di saldo aktual, tidak dikurangkan lagi)<?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
