<?php
/**
 * Halaman PRATINJAU + Cetak voucher Bank terpilih (BUKTI BANK KELUAR / MASUK).
 * Pola sama persis dengan cash/voucher_preview.php (Cetak Voucher Kas) --
 * dirender di dalam layout aplikasi, tombol "Cetak" memanggil window.print().
 * Beda tema warna per arah mutasi (merah = keluar, hijau = masuk) lewat CSS
 * variable --v-color/--v-fill yang di-set oleh class .is-keluar/.is-masuk
 * pada tiap <div class="voucher"> (lihat bank/_voucher.php).
 *
 * Var dari BankController::printVoucher():
 *   $vouchers  array<int, array{header: array, items: array}>
 *   $vCompany  string
 *   $backUrl   string  tujuan tombol "Kembali"
 */
$vouchers = $vouchers ?? [];
$vCompany = $vCompany ?? 'Perusahaan';
$backUrl  = $backUrl ?? (BASE_URL . '/cash');
?>
<style>
    /* ---- Kontainer lembar voucher (scoped, tidak menyentuh halaman lain) ---- */
    .bank-voucher-print { max-width: 210mm; margin: 0 auto; }
    .bank-voucher-print .voucher {
        background: #fff;
        border: 1px solid #dee2e6;
        padding: 12mm 12mm 10mm;
        margin: 0 auto 20px;
        color: #1a1a1a;
        font-size: 11px;
        box-sizing: border-box;
    }
    .bank-voucher-print .voucher.is-keluar { --v-color: #FF0000; --v-fill: #EDE1E0; --v-total-bg: #FBE5E3; }
    .bank-voucher-print .voucher.is-masuk { --v-color: #00A651; --v-fill: #E2EFDA; --v-total-bg: #C6E8CF; }

    /* _voucher.php menyisipkan <div class="page-break"> di antara voucher,
       jadi voucher ke-2 dst bukan sibling langsung -> pakai selektor "~". */
    .bank-voucher-print .voucher ~ .voucher { page-break-before: always; break-before: page; }
    .bank-voucher-print .page-break { page-break-after: always; break-after: page; }

    .bank-voucher-print .company { text-align: center; font-size: 11px; margin-bottom: 6px; color: var(--v-color); }

    .bank-voucher-print table.head { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .bank-voucher-print table.head > tbody > tr > td { border: 1.5px solid var(--v-color); vertical-align: top; padding: 5px 7px; }
    .bank-voucher-print .head-left { width: 33%; }
    .bank-voucher-print .head-left .lbl { font-size: 10px; color: #555; }
    .bank-voucher-print .head-left .val { font-weight: bold; font-size: 12px; margin-top: 8px; }
    .bank-voucher-print .head-mid { width: 34%; text-align: center; font-size: 17px; font-weight: bold; letter-spacing: 1px; color: var(--v-color); vertical-align: middle; }
    .bank-voucher-print .head-right { width: 33%; padding: 3px 6px; }
    .bank-voucher-print table.nomor { width: 100%; border-collapse: collapse; }
    .bank-voucher-print table.nomor td { border: 0; padding: 2px 3px; font-size: 11px; }
    .bank-voucher-print table.nomor td.k { width: 52px; }
    .bank-voucher-print table.nomor td.s { width: 8px; }
    .bank-voucher-print table.nomor td.v { font-weight: bold; }

    .bank-voucher-print table.grid { width: 100%; border-collapse: collapse; }
    .bank-voucher-print table.grid td { border: 1px solid var(--v-color); padding: 4px 7px; vertical-align: top; }
    .bank-voucher-print table.grid tr.grid-head td { background: var(--v-color); color: #fff; text-align: center; font-weight: bold; letter-spacing: 2px; }
    .bank-voucher-print table.grid tr.grid-head td.c-label { background: #fff; padding: 0; }
    .bank-voucher-print table.grid td.c-label, .bank-voucher-print table.grid td.perkiraan { background: var(--v-fill); }
    .bank-voucher-print table.grid td.c-label { width: 22px; text-align: center; }
    .bank-voucher-print table.grid td.c-label .vert {
        display: block;
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        font-weight: bold;
        letter-spacing: 3px;
        color: #333;
        margin: 6px auto;
    }
    .bank-voucher-print table.grid td.perkiraan { width: 90px; }
    .bank-voucher-print table.grid td.uraian, .bank-voucher-print table.grid td.jumlah { background: var(--v-fill); }
    .bank-voucher-print table.grid .c-jumlah { width: 130px; }
    .bank-voucher-print table.grid td.jumlah { text-align: right; white-space: nowrap; }
    .bank-voucher-print table.grid td.jumlah.neg { color: #b00020; }
    .bank-voucher-print table.grid tr.row-ch td.uraian,
    .bank-voucher-print table.grid tr.row-total td.uraian { text-align: right; font-weight: bold; }
    .bank-voucher-print table.grid tr.row-ch td.uraian, .bank-voucher-print table.grid tr.row-ch td.jumlah,
    .bank-voucher-print table.grid tr.row-total td.uraian, .bank-voucher-print table.grid tr.row-total td.jumlah {
        background: var(--v-total-bg); font-weight: bold;
    }

    .bank-voucher-print table.terbilang, .bank-voucher-print table.catatan { width: 100%; border-collapse: collapse; }
    .bank-voucher-print table.terbilang td { border: 1px solid var(--v-color); padding: 4px 7px; }
    .bank-voucher-print table.terbilang td.k { width: 70px; font-weight: bold; background: var(--v-total-bg); white-space: nowrap; }
    .bank-voucher-print table.terbilang td.v { font-style: italic; }
    .bank-voucher-print table.catatan td { border: 1px solid var(--v-color); border-top: 0; padding: 5px 7px 22px; font-weight: bold; }

    .bank-voucher-print table.ttd { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .bank-voucher-print table.ttd th, .bank-voucher-print table.ttd td { border: 1px solid var(--v-color); text-align: center; padding: 4px; width: 25%; }
    .bank-voucher-print table.ttd th { background: var(--v-color); color: #fff; font-weight: bold; }
    .bank-voucher-print table.ttd tr.space td { height: 66px; }

    .bank-voucher-print .foot { margin-top: 8px; font-size: 9px; color: #999; text-align: right; }

    /* Pratinjau di HP: TIDAK direflow (sama pola purchase_order/print.php &
       cash voucher) -- responsive-tables.js (scalePrintPreviews) menangani
       .print-page otomatis. @media print mengembalikan zoom:1. */

    @page { size: A4; margin: 10mm; }
    @media print {
        .bank-voucher-print { max-width: none; }
        .bank-voucher-print .voucher {
            border: none;
            margin: 0;
            padding: 4mm 8mm;
        }
        .bank-voucher-print, .bank-voucher-print * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-0">Cetak Voucher Bank</h4>
        <small class="text-muted"><?= count($vouchers) ?> transaksi &mdash; satu lembar per No Bukti</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak
        </button>
        <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="bank-voucher-print">
    <?php
    $last = count($vouchers) - 1;
    foreach ($vouchers as $i => $v):
        $vHeader = $v['header'];
        $vItems  = $v['items'];
        $vIsLast = ($i === $last);
        require ROOT_PATH . '/app/views/bank/_voucher.php';
    endforeach;
    ?>
</div>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
