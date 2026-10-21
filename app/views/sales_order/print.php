<?php
/**
 * Cetak Order Penjualan -- mengikuti contoh "Format Order Penjualan.pdf" dari perusahaan.
 *
 * Susunan: kop (logo + identitas) kiri; judul + kotak Tanggal/No. Nota, kotak Tanggal/No. PO Customer,
 * kotak Operator di kanan; kotak "Kepada Yth."; tabel barang; Keterangan + Subtotal/Diskon/Pajak/Total;
 * kolom tanda tangan Gudang / Di Check oleh / Tanda Terima.
 *
 * Menyesuaikan nanti (tanpa menyentuh logika bisnis):
 *   $signatureBoxes -- judul kolom tanda tangan.   $columns -- kolom tabel barang (urutan/judul/lebar/isi).
 *   Blok <style>    -- ukuran kertas (@page), font, garis.
 */
$months = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];
$d = function (?string $date) use ($months) {
    $t = $date ? strtotime($date) : false;
    return $t ? date('d', $t) . '-' . $months[(int) date('n', $t)] . '-' . date('y', $t) : '-';
};
// Format angka seperti contoh: ribuan koma, tanpa desimal bila bilangan bulat.
$n = fn($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 2, '.', ',');
$q = fn($v) => number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 2, '.', ',');

$signatureBoxes = ['Gudang,', 'Di Check oleh,', 'Tanda Terima,'];

$columns = [
    ['th' => 'No',        'w' => '26px',  'class' => 'ctr', 'cell' => fn($it, $i) => $i + 1],
    ['th' => 'Item',      'w' => '86px',  'class' => '',    'cell' => fn($it, $i) => e($it['item_code'])],
    ['th' => 'Deskripsi', 'w' => '',      'class' => '',    'cell' => fn($it, $i) => e($it['item_name'])
        . (trim((string) $it['specification']) !== '' ? '<div class="op-spec">' . e(preg_replace('/\s+/', ' ', trim($it['specification']))) . '</div>' : '')],
    ['th' => 'Qty',       'w' => '48px',  'class' => 'ctr', 'cell' => fn($it, $i) => e($q($it['qty']))],
    ['th' => 'Unit',      'w' => '48px',  'class' => 'ctr', 'cell' => fn($it, $i) => e($it['unit'])],
    ['th' => 'Harga',     'w' => '78px',  'class' => 'num', 'cell' => fn($it, $i) => e($n($it['unit_price']))],
    ['th' => 'Disc',      'w' => '48px',  'class' => 'num', 'cell' => fn($it, $i) => (float) $it['discount_percent'] > 0 ? e($q($it['discount_percent'])) . '%' : ''],
    ['th' => 'Subtotal',  'w' => '90px',  'class' => 'num', 'cell' => fn($it, $i) => e($n((float) $it['qty'] * (float) $it['unit_price']))],
];
$colCount = count($columns);

// Subtotal = jumlah harga sebelum diskon; Diskon = potongan semua baris; Total = Subtotal - Diskon + Pajak.
$grossTotal = 0.0;
foreach ($items as $it) {
    $grossTotal += round((float) $it['qty'] * (float) $it['unit_price'], 2);
}
$discountTotal = round($grossTotal - (float) $order['subtotal'], 2);
?>
<style>
    .op-print-page {
        background: #fff;
        max-width: 210mm;
        margin: 0 auto 24px auto;
        padding: 10mm 10mm;
        border: 1px solid #dee2e6;
        color: #000;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        box-sizing: border-box;
    }
    .op-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
    .op-company { display: flex; gap: 12px; align-items: flex-start; flex: 1 1 52%; }
    .op-company img { width: 44px; height: auto; flex: 0 0 auto; }
    .op-company-name { font-weight: 700; font-size: 13px; margin-bottom: 2px; }
    .op-company-text { font-size: 12px; line-height: 1.35; }
    .op-right { flex: 0 0 245px; text-align: right; }
    .op-title { font-size: 24px; font-weight: 700; margin: 0 0 6px 0; line-height: 1.1; }
    .op-box { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .op-box th, .op-box td { border: 1px solid #000; padding: 3px 6px; text-align: center; font-size: 12px; }
    .op-box th { font-weight: 700; }
    .op-party { margin-top: 8px; width: 46%; border: 1px solid #000; }
    .op-party-head { border-bottom: 1px solid #000; padding: 3px 6px; font-weight: 700; }
    .op-party-body { padding: 6px; min-height: 64px; line-height: 1.4; }
    .op-items { width: 100%; border-collapse: collapse; margin-top: 14px; }
    .op-items th, .op-items td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
    .op-items th { font-weight: 700; text-align: left; background: #fff; }
    .op-items .ctr { text-align: center; }
    .op-items th.ctr { text-align: center; }
    .op-items .num { text-align: right; white-space: nowrap; }
    .op-items th.num { text-align: right; }
    .op-items tr { page-break-inside: avoid; }
    .op-items td.op-filler { height: 18px; }
    .op-spec { font-size: 10.5px; color: #333; }
    .op-bottom { width: 100%; border-collapse: collapse; }
    .op-bottom td { border: 1px solid #000; padding: 4px 6px; }
    .op-bottom .op-note { vertical-align: top; width: 55%; }
    .op-bottom .lbl { text-align: right; }
    .op-bottom .val { text-align: right; width: 22%; white-space: nowrap; }
    .op-bottom .tot td { font-weight: 700; }
    .op-sign { display: flex; justify-content: flex-start; margin-top: 4px; font-weight: 700; position: relative; }
    .op-sign div { flex: 0 0 auto; }
    .op-sign-gap { min-height: 90px; }
    .op-taxnote { position: absolute; right: 0; top: 0; font-size: 8px; font-weight: 400; }
    .op-pageno { text-align: right; font-weight: 700; margin-top: 28px; }
    /* "Halaman X dari Y" dihitung browser saat cetak (Chrome/Edge terbaru); tidak tampil di layar. */
    @page { size: A4; margin: 8mm 8mm 12mm 8mm; @bottom-right { content: "Halaman " counter(page) " dari " counter(pages); font: bold 11px Arial, Helvetica, sans-serif; } }
    @media print {
        .op-print-page { border: none; margin: 0; max-width: none; padding: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
    <a href="<?= BASE_URL ?>/sales_order/detail/<?= (int) $order['id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="op-print-page print-page">
    <!-- KOP: logo + identitas perusahaan (kiri) | judul + Tanggal/No. Nota + Tanggal/No. PO Customer + Operator (kanan) -->
    <div class="op-head">
        <div class="op-company">
            <img src="<?= assetUrl('/assets/img/logo-hme.png') ?>" alt="Logo">
            <div class="op-company-text">
                <div class="op-company-name"><?= e(strtoupper($company['company_name'] ?: 'Perusahaan')) ?></div>
                <div><?= nl2br(e($company['company_address'] ?? '')) ?></div>
                <div style="margin-top:10px">Telp: <?= e($company['company_phone'] ?? '') ?><?php if (!empty($company['company_email'])): ?> &nbsp;|&nbsp; <?= e($company['company_email']) ?><?php endif; ?></div>
            </div>
        </div>
        <div class="op-right">
            <h1 class="op-title">Order Penjualan</h1>
            <table class="op-box">
                <tr><th style="width:42%">Tanggal</th><th>No.Nota</th></tr>
                <tr><td><?= e($d($order['order_date'])) ?></td><td><?= e($order['order_number']) ?></td></tr>
            </table>
            <table class="op-box">
                <tr><th style="width:42%">Tgl. PO Customer</th><th>No. PO Customer</th></tr>
                <tr><td><?= e($d($order['po_date'])) ?></td><td><?= e($order['po_number']) ?></td></tr>
            </table>
            <table class="op-box">
                <tr><th>Operator</th></tr>
                <tr><td><?= e(strtoupper($order['created_by_name'] ?? '-')) ?></td></tr>
            </table>
        </div>
    </div>

    <!-- KEPADA YTH. -->
    <div class="op-party">
        <div class="op-party-head">Kepada Yth. :</div>
        <div class="op-party-body">
            <?= e($order['client_name']) ?>
            <?php if (!empty($order['customer_address'])): ?><br><?= nl2br(e($order['customer_address'])) ?><?php endif; ?>
            <?php if (!empty($order['customer_phone'])): ?><br>Telp. <?= e($order['customer_phone']) ?><?php endif; ?>
        </div>
    </div>

    <!-- TABEL BARANG -->
    <table class="op-items">
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th class="<?= e($col['class']) ?>" <?= $col['w'] !== '' ? 'style="width:' . e($col['w']) . '"' : '' ?>><?= e($col['th']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $it): ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <td class="<?= e($col['class']) ?>"><?= ($col['cell'])($it, $i) /* sudah di-escape di definisi kolom */ ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <tr><?php for ($c = 0; $c < $colCount; $c++): ?><td class="op-filler"></td><?php endfor; ?></tr>
        </tbody>
    </table>

    <!-- KETERANGAN + TOTAL -->
    <table class="op-bottom">
        <tr>
            <td class="op-note" rowspan="4"><strong>Keterangan</strong><br><?= $order['notes'] ? nl2br(e($order['notes'])) : '' ?></td>
            <td class="lbl">Subtotal Rp.</td><td class="val"><?= e($n($grossTotal)) ?></td>
        </tr>
        <tr><td class="lbl">Diskon Rp.</td><td class="val"><?= e($n($discountTotal)) ?></td></tr>
        <tr><td class="lbl">Pajak Rp.<?= (float) $order['ppn_percent'] > 0 ? ' (' . e($q($order['ppn_percent'])) . '%)' : '' ?></td><td class="val"><?= e($n($order['ppn_amount'])) ?></td></tr>
        <tr class="tot"><td class="lbl">Total Rp.</td><td class="val"><?= e($n($order['total_amount'])) ?></td></tr>
    </table>

    <!-- TANDA TANGAN -->
    <div class="op-sign">
        <?php foreach ($signatureBoxes as $i => $label): ?>
            <div style="width: <?= $i === 0 ? '190px' : '225px' ?>"><?= e($label) ?></div>
        <?php endforeach; ?>
        <?php if ((float) $order['ppn_percent'] > 0): ?><span class="op-taxnote">* Harga belum termasuk pajak</span><?php endif; ?>
    </div>
    <div class="op-sign-gap"></div>
</div>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
