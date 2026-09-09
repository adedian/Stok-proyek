<?php
/**
 * Voucher cetak Kas -- SATU dokumen per transaksi (No Bukti), mengikuti
 * "Gambar 1" (BUKTI KAS KELUAR). Kolom "Perkiraan" & kolom vertikal
 * "KEPERLUAN" DIHILANGKAN -- grid tinggal URAIAN + JUMLAH.
 * Blok tanda tangan Pembukuan / Mengetahui / Kasir / Penerima ada sebagai
 * ruang kosong -- TIDAK ada field input tambahan di form Kas.
 *
 * Partial markup satu voucher. Di-loop oleh app/views/cash/voucher_preview.php
 * (halaman pratinjau di dalam layout aplikasi; CSS + @media print ada di sana),
 * dipanggil dari CashController::printVoucher(). Tiap voucher dipisah page-break.
 *
 * Var yang dipakai (di-set pemanggil sebelum require):
 *   $vHeader   array  baris cash_transactions + created_by_name
 *   $vItems    array  CashTransactionItem::byTransaction()
 *   $vCompany  string nama perusahaan
 *   $vIsLast   bool   voucher terakhir? (kalau tidak -> page-break setelahnya)
 */
$vHeader  = $vHeader  ?? [];
$vItems   = $vItems   ?? [];
$vCompany = $vCompany ?? 'Perusahaan';
$vIsLast  = $vIsLast  ?? true;

$isKeluar = ($vHeader['mutasi'] ?? 'keluar') === 'keluar';
$judul    = $isKeluar ? 'BUKTI KAS KELUAR' : 'BUKTI KAS MASUK';

$rp = static fn($v) => number_format((float) $v, 2, '.', ',');
$qtyFmt = static function ($v) {
    $v = (float) $v;
    return $v == (int) $v ? number_format($v, 0, ',', '.') : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
};

$total = 0.0;
foreach ($vItems as $it) {
    $total += (float) $it['jumlah'];
}
$terbilangStr = ucfirst(trim(terbilang(abs($total)))) . ' rupiah';
if ($total < 0) {
    $terbilangStr = 'Minus ' . lcfirst($terbilangStr);
}

$minRows  = 8;
$padCount = max(0, $minRows - count($vItems));
?>
<div class="voucher print-page">
    <div class="company"><em><?= e($vCompany) ?> &mdash; Kas Project</em></div>

    <table class="head">
        <tr>
            <td class="head-left">
                <div class="lbl">Dibayarkan Kepada :</div>
                <div class="val"><?= e($vHeader['pic'] ?? '-') ?></div>
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
        <thead>
            <tr>
                <th class="c-uraian">U R A I A N</th>
                <th class="c-jumlah">J u m l a h</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vItems as $it): ?>
                <tr>
                    <td class="uraian">
                        <?= e($it['uraian']) ?>
                        <?php
                            $sub = [];
                            if (!empty($it['category_name'])) { $sub[] = e($it['category_name']); }
                            if (!empty($it['project_name'])) { $sub[] = 'Proyek: ' . e($it['project_name']); }
                            if ((float) $it['qty'] != 1.0 || !empty($it['unit'])) {
                                $sub[] = $qtyFmt($it['qty']) . ' ' . e($it['unit'] ?? '') . ' &times; Rp ' . $rp($it['satuan']);
                            }
                        ?>
                        <?php if ($sub): ?><div class="sub"><?= implode(' &nbsp;|&nbsp; ', $sub) ?></div><?php endif; ?>
                    </td>
                    <td class="jumlah <?= (float) $it['jumlah'] < 0 ? 'neg' : '' ?>"><?= $rp($it['jumlah']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $padCount; $i++): ?>
                <tr>
                    <td class="uraian">&nbsp;</td>
                    <td class="jumlah">&nbsp;</td>
                </tr>
            <?php endfor; ?>
            <tr class="row-ch">
                <td class="uraian">Ch/G.B. No. :</td>
                <td class="jumlah">&nbsp;</td>
            </tr>
            <tr class="row-total">
                <td class="uraian">TOTAL :</td>
                <td class="jumlah <?= $total < 0 ? 'neg' : '' ?>">Rp <?= $rp($total) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="terbilang">
        <tr><td class="k">Terbilang :</td><td class="v"><?= e($terbilangStr) ?></td></tr>
    </table>

    <table class="catatan"><tr><td>CATATAN :</td></tr></table>

    <table class="ttd">
        <tr><th>Pembukuan</th><th>Mengetahui</th><th>Kasir</th><th>Penerima</th></tr>
        <tr class="space"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
    </table>

    <div class="foot">Dibuat oleh: <?= e($vHeader['created_by_name'] ?? '-') ?> &nbsp;&middot;&nbsp; <?= e(printedAtLabel()) ?></div>
</div>
<?php if (!$vIsLast): ?><div class="page-break"></div><?php endif; ?>
