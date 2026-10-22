<?php
/**
 * Voucher cetak Bank -- SATU dokumen per transaksi (No Bukti), mengikuti contoh Excel
 * "BUKTI BANK KELUAR" (merah) / "BUKTI BANK MASUK" (hijau) dari user: header 3 kolom (kiri pihak,
 * tengah perusahaan + judul, kanan Nomor/Tanggal); grid = kolom kosong | KEPERLUAN INTERN (vertikal)
 * | Perkiraan | Uraian (putih) | Jumlah ("Rp" kiri, angka kanan); 14 baris isi + 1 baris
 * "Ch/G.B. No. | TOTAL" = 15 baris; Terbilang; CATATAN di kiri + tanda tangan di kanan.
 * Kolom Perkiraan kosong (tidak ada field kode perkiraan di data Bank saat ini).
 *
 * "Dibayarkan Kepada" (keluar) / "Diterima dari" (masuk) memakai Rekening
 * (master_rekening.nama_rekening, field "Rekening" di form Transaksi Bank),
 * BUKAN PIC.
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

$minRows  = 14; // 14 baris isi + 1 baris "Ch/G.B. No. | TOTAL" = 15 baris dari uraian pertama sampai TOTAL
$padCount = max(0, $minRows - max(1, count($vItems)));
$bodyRowCount = max(1, count($vItems)) + $padCount + 1; // + baris Ch/G.B. & TOTAL
$labelRowspan = $bodyRowCount + 1; // + baris header grid sendiri

/** Sel Jumlah gaya akuntansi: "Rp" rata kiri, angka rata kanan (mengikuti contoh Excel). */
$amtCell = static function ($v, string $extraCls = '') use ($rp) {
    $neg = (float) $v < 0 ? ' neg' : '';
    return '<td class="jumlah' . $neg . $extraCls . '"><div class="amt"><span class="cur">Rp</span><span class="num">' . $rp($v) . '</span></div></td>';
};
?>
<div class="voucher print-page <?= $isKeluar ? 'is-keluar' : 'is-masuk' ?>">
    <!-- HEADER: kiri (Diterima dari / Dibayarkan Kepada + Rekening) | tengah (perusahaan + judul) | kanan (Nomor / Tanggal) -->
    <div class="vh">
        <div class="vh-left">
            <div class="vh-cell"><?= e($pihakLbl) ?></div>
            <div class="vh-cell vh-name"><?= e($vHeader['nama_rekening'] ?? '' ?: '-') ?></div>
        </div>
        <div class="vh-mid">
            <div class="vh-company"><em><?= e($vCompany) ?></em></div>
            <div class="vh-title"><?= e($judul) ?></div>
        </div>
        <div class="vh-right">
            <div class="vh-cell"><span class="k">Nomor</span><span class="s">:</span><span class="v"><?= e($vHeader['no_bukti'] ?? '-') ?></span></div>
            <div class="vh-cell"><span class="k">Tanggal</span><span class="s">:</span><span class="v"><?= e(formatTanggal($vHeader['trx_date'] ?? null)) ?></span></div>
        </div>
    </div>

    <!-- GRID: kolom kosong | KEPERLUAN INTERN (vertikal) | Perkiraan | Uraian | Jumlah -->
    <table class="grid">
        <colgroup>
            <col class="g-blank"><col class="g-label"><col class="g-perkiraan"><col class="g-uraian"><col class="g-jumlah">
        </colgroup>
        <tr class="grid-head">
            <td class="c-blank" rowspan="<?= $labelRowspan ?>"></td>
            <td class="c-label" rowspan="<?= $labelRowspan ?>"><span class="vert">KEPERLUAN INTERN</span></td>
            <td class="c-perkiraan">Perkiraan</td>
            <td class="c-uraian">U R A I A N</td>
            <td class="c-jumlah">J u m l a h</td>
        </tr>
        <?php foreach ($vItems as $it): ?>
            <tr>
                <td class="perkiraan">&nbsp;</td>
                <td class="uraian"><?= e($it['uraian']) ?></td>
                <?= $amtCell($it['amount']) ?>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($vItems)): ?>
            <tr>
                <td class="perkiraan">&nbsp;</td>
                <td class="uraian"><?= e($vHeader['uraian'] ?? '') ?></td>
                <?= $amtCell($total) ?>
            </tr>
        <?php endif; ?>
        <?php for ($i = 0; $i < $padCount; $i++): ?>
            <tr>
                <td class="perkiraan">&nbsp;</td>
                <td class="uraian">&nbsp;</td>
                <td class="jumlah">&nbsp;</td>
            </tr>
        <?php endfor; ?>
        <tr class="row-last">
            <td class="ch">Ch/G.B. No. :</td>
            <td class="tot-label">TOTAL :</td>
            <?= $amtCell($total, ' tot-amt') ?>
        </tr>
    </table>

    <table class="terbilang">
        <colgroup><col class="g-blank"><col></colgroup>
        <tr><td class="k">Terbilang :</td><td class="v"><?= e($terbilangStr) ?></td></tr>
    </table>

    <!-- CATATAN (kiri) + tanda tangan (kanan) -->
    <table class="bawah">
        <colgroup><col class="b-catatan"><col><col><col><col></colgroup>
        <tr>
            <td class="catatan" rowspan="2">CATATAN :</td>
            <th>Pembukuan</th><th>Mengetahui</th><th>Kasir</th><th><?= e($penyerahLbl) ?></th>
        </tr>
        <tr class="space"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
    </table>

    <div class="foot">Dibuat oleh: <?= e($vHeader['created_by_name'] ?? '-') ?> &nbsp;&middot;&nbsp; <?= e(printedAtLabel()) ?></div>
</div>
<?php if (!$vIsLast): ?><div class="page-break"></div><?php endif; ?>
