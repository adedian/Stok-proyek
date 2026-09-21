<?php
/**
 * Voucher cetak Bank -- SATU dokumen per transaksi (No Bukti), mengikuti
 * contoh "BUKTI BANK KELUAR" (merah) / "BUKTI BANK MASUK" (hijau) yang
 * diberikan user. BEDA dari voucher Kas (cash/_voucher.php): ADA kolom
 * "Perkiraan" + kolom vertikal "KEPERLUAN INTERN" di sisi kiri grid (kolom
 * itu kosong -- tidak ada field kode perkiraan di data Bank saat ini, murni
 * mengikuti tata letak contoh).
 *
 * "Dibayarkan Kepada" (keluar) / "Diterima dari" (masuk) memakai kolom
 * `pic` (field paling dekat maknanya yang sudah ada di data Bank -- tidak
 * ada kolom "pihak eksternal" terpisah).
 *
 * Partial markup satu voucher. Di-loop oleh app/views/bank/voucher_preview.php,
 * dipanggil dari BankController::printVoucher(). Tiap voucher dipisah page-break.
 *
 * Var yang dipakai (di-set pemanggil sebelum require):
 *   $vHeader   array  baris bank_transactions + relasi (findWithRelations())
 *   $vItems    array  BankTransactionItem::byTransaction()
 *   $vCompany  string nama perusahaan
 *   $vIsLast   bool   voucher terakhir? (kalau tidak -> page-break setelahnya)
 */
$vHeader  = $vHeader  ?? [];
$vItems   = $vItems   ?? [];
$vCompany = $vCompany ?? 'Perusahaan';
$vIsLast  = $vIsLast  ?? true;

$isKeluar = ($vHeader['mutasi'] ?? 'keluar') === 'keluar';
$judul    = $isKeluar ? 'BUKTI BANK KELUAR' : 'BUKTI BANK MASUK';
$pihakLbl = $isKeluar ? 'Dibayarkan Kepada :' : 'Diterima dari :';
$penyerahLbl = $isKeluar ? 'Penerima' : 'Penyetor';

$rp = static fn($v) => number_format((float) $v, 2, '.', ',');

$total = 0.0;
foreach ($vItems as $it) {
    $total += (float) $it['amount'];
}
if ($total == 0.0 && empty($vItems)) {
    $total = (float) ($vHeader['amount'] ?? 0);
}
$terbilangStr = ucfirst(trim(terbilang(abs($total)))) . ' rupiah';
if ($total < 0) {
    $terbilangStr = 'Minus ' . lcfirst($terbilangStr);
}

$minRows  = 8;
$padCount = max(0, $minRows - count($vItems));
$bodyRowCount = count($vItems) + $padCount + 2; // + baris Ch/G.B. + TOTAL
$labelRowspan = $bodyRowCount + 1; // + baris header grid sendiri
?>
<div class="voucher print-page <?= $isKeluar ? 'is-keluar' : 'is-masuk' ?>">
    <div class="company"><em><?= e($vCompany) ?></em></div>

    <table class="head">
        <tr>
            <td class="head-left">
                <div class="lbl"><?= e($pihakLbl) ?></div>
                <div class="val"><?= e($vHeader['pic'] ?: '-') ?></div>
            </td>
            <td class="head-mid"><?= e($judul) ?></td>
            <td class="head-right">
                <table class="nomor">
                    <tr><td class="k">Nomor</td><td class="s">:</td><td class="v"><?= e($vHeader['no_bukti'] ?? '-') ?></td></tr>
                    <tr><td class="k">Tanggal</td><td class="s">:</td><td class="v"><?= e(formatTanggal($vHeader['trx_date'] ?? null)) ?></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr class="grid-head">
            <td class="c-label" rowspan="<?= $labelRowspan ?>"><span class="vert">KEPERLUAN INTERN</span></td>
            <td class="c-perkiraan">Perkiraan</td>
            <td class="c-uraian">U R A I A N</td>
            <td class="c-jumlah">J u m l a h</td>
        </tr>
        <?php foreach ($vItems as $it): ?>
            <tr>
                <td class="perkiraan">&nbsp;</td>
                <td class="uraian"><?= e($it['uraian']) ?></td>
                <td class="jumlah <?= (float) $it['amount'] < 0 ? 'neg' : '' ?>"><?= $rp($it['amount']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($vItems)): ?>
            <tr>
                <td class="perkiraan">&nbsp;</td>
                <td class="uraian"><?= e($vHeader['uraian'] ?? '') ?></td>
                <td class="jumlah <?= $total < 0 ? 'neg' : '' ?>"><?= $rp($total) ?></td>
            </tr>
            <?php $padCount = max(0, $minRows - 1); ?>
        <?php endif; ?>
        <?php for ($i = 0; $i < $padCount; $i++): ?>
            <tr>
                <td class="perkiraan">&nbsp;</td>
                <td class="uraian">&nbsp;</td>
                <td class="jumlah">&nbsp;</td>
            </tr>
        <?php endfor; ?>
        <tr class="row-ch">
            <td class="perkiraan">&nbsp;</td>
            <td class="uraian">Ch/G.B. No. :</td>
            <td class="jumlah">&nbsp;</td>
        </tr>
        <tr class="row-total">
            <td class="perkiraan">&nbsp;</td>
            <td class="uraian">TOTAL :</td>
            <td class="jumlah <?= $total < 0 ? 'neg' : '' ?>">Rp <?= $rp($total) ?></td>
        </tr>
    </table>

    <table class="terbilang">
        <tr><td class="k">Terbilang :</td><td class="v"><?= e($terbilangStr) ?></td></tr>
    </table>

    <table class="catatan"><tr><td>CATATAN :</td></tr></table>

    <table class="ttd">
        <tr><th>Pembukuan</th><th>Mengetahui</th><th>Kasir</th><th><?= e($penyerahLbl) ?></th></tr>
        <tr class="space"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
    </table>

    <div class="foot">Dibuat oleh: <?= e($vHeader['created_by_name'] ?? '-') ?> &nbsp;&middot;&nbsp; <?= e(printedAtLabel()) ?></div>
</div>
<?php if (!$vIsLast): ?><div class="page-break"></div><?php endif; ?>
