<?php
/**
 * Cetak "LAPORAN BIAYA REIMBURSE" -- mengikuti Reimbursement.xlsx: judul, Nama/Divisi/Tugas | Tanggal/Kota/B S,
 * PENGELUARAN (Tanggal | Keterangan | JUMLAH, baris dikelompokkan per kategori, mis. "Akomodasi :"),
 * TOTAL PENGELUARAN, TOTAL KASBON - PENGELUARAN, lokasi+tanggal, tanda tangan Menyetujui / Diperiksa / Dibuat oleh.
 * Variabel: $r, $items, $autoprint
 */
$t = Reimbursement::totals(array_column($items, 'amount'), $r['kasbon_amount']);
$kbMinus = Reimbursement::kasbonMinusExpense($t['total'], $t['kasbon']);
$nf = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
// kelompok per kategori (urutan kemunculan pertama); baris tanpa kategori di awal tanpa judul kelompok
$groups = [];
foreach ($items as $it) {
    $k = $it['category_name'] ?? '';
    $groups[$k][] = $it;
}
uksort($groups, fn($a, $b) => ($a === '' ? 0 : 1) <=> ($b === '' ? 0 : 1));
$city = $r['city'] ?: '';
$dateLong = formatTanggalLengkap($r['reimb_date']);
?>
<style>
    .rb-sheet { background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; font-size: 12px; max-width: 210mm; margin: 0 auto 24px; padding: 12mm; box-sizing: border-box; }
    .rb-sheet h2 { text-align: center; font-size: 16px; font-weight: 700; margin: 0 0 14px; text-decoration: underline; }
    .rb-head { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    .rb-head td { padding: 1px 4px; vertical-align: top; }
    .rb-head td.lbl { width: 70px; }
    .rb-head td.sep { width: 10px; }
    .rb-tbl { width: 100%; border-collapse: collapse; }
    .rb-tbl th, .rb-tbl td { border: 1px solid #000; padding: 3px 6px; }
    .rb-tbl th { text-align: center; background: #f2f2f2; }
    .rb-tbl td.num { text-align: right; white-space: nowrap; }
    .rb-tbl td.date { white-space: nowrap; width: 95px; }
    .rb-tbl tr.grp td { font-weight: 700; border-top: 1px solid #000; }
    .rb-tbl tr.tot td { font-weight: 700; }
    .rb-sign { width: 100%; margin-top: 18px; border-collapse: collapse; text-align: center; }
    .rb-sign td { width: 33%; vertical-align: top; padding-top: 4px; }
    .rb-sign .space { height: 70px; }
    .rb-foot { margin-top: 10px; font-size: 8px; color: #888; text-align: right; }
    @page { size: A4 portrait; margin: 10mm; }
    @media print { .rb-sheet { max-width: none; margin: 0; padding: 0; } }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
    <a href="<?= BASE_URL ?>/reimbursement/detail/<?= (int) $r['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="rb-sheet print-page">
    <h2>LAPORAN BIAYA REIMBURSE</h2>
    <table class="rb-head">
        <tr><td class="lbl">Nama</td><td class="sep">:</td><td><?= e($r['employee_name']) ?></td><td class="lbl">Tanggal</td><td class="sep">:</td><td><?= e(formatTanggal($r['reimb_date'])) ?></td></tr>
        <tr><td class="lbl">Divisi</td><td class="sep">:</td><td><?= e($r['division'] ?? '') ?></td><td class="lbl">Kota</td><td class="sep">:</td><td><?= e($city) ?></td></tr>
        <tr><td class="lbl">Tugas</td><td class="sep">:</td><td><?= e($r['task'] ?? '') ?></td><td class="lbl">B S</td><td class="sep">:</td><td><?= $t['kasbon'] > 0 ? e($nf($t['kasbon'])) : '' ?></td></tr>
    </table>

    <div style="font-weight:700;margin-bottom:3px">PENGELUARAN :</div>
    <table class="rb-tbl">
        <thead><tr><th>Tanggal</th><th>Keterangan</th><th style="width:130px">JUMLAH</th></tr></thead>
        <tbody>
            <?php foreach ($groups as $cat => $rows): ?>
                <?php if ($cat !== ''): ?><tr class="grp"><td></td><td colspan="2"><?= e($cat) ?> :</td></tr><?php endif; ?>
                <?php foreach ($rows as $it): ?>
                    <tr>
                        <td class="date"><?= e(formatTanggal($it['expense_date'])) ?></td>
                        <td><?= e($it['description']) ?><?php if (!empty($it['amount_expr'])): ?> <span style="color:#555;font-size:10px">(<?= e($it['amount_expr']) ?>)</span><?php endif; ?></td>
                        <td class="num"><?= e($nf($it['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <tr class="tot"><td colspan="2">TOTAL PENGELUARAN</td><td class="num"><?= e($nf($t['total'])) ?></td></tr>
        </tbody>
    </table>
    <table class="rb-tbl" style="margin-top:8px">
        <tr class="tot"><td>TOTAL KASBON - PENGELUARAN :</td><td class="num" style="width:130px"><?= e(($kbMinus < 0 ? '-' : '') . $nf(abs($kbMinus))) ?></td></tr>
    </table>

    <div style="text-align:right;margin-top:14px"><?= e(($city !== '' ? $city . ', ' : '') . $dateLong) ?></div>
    <table class="rb-sign">
        <tr><td>Menyetujui,</td><td>Diperiksa,</td><td>Dibuat oleh,</td></tr>
        <tr><td class="space"></td><td class="space"></td><td class="space"></td></tr>
        <tr>
            <td>(&nbsp;..............................&nbsp;)</td>
            <td>(<?= e($r['review_decision'] === 'setuju' ? ($r['reviewed_by_name'] ?? '') : '') ?: '&nbsp;..............................&nbsp;' ?>)</td>
            <td>(<?= e($r['employee_name']) ?>)</td>
        </tr>
    </table>
    <div class="rb-foot"><?= e($r['reimb_number']) ?> &middot; <?= e(printedAtLabel() . ', ' . printedByLabel()) ?></div>
</div>

<?php if (!empty($autoprint)): ?>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
<?php endif; ?>
