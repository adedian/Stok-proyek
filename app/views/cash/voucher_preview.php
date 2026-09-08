<?php
/**
 * Halaman PRATINJAU + Cetak voucher Kas terpilih (BUKTI KAS KELUAR / MASUK).
 * Pola sama dengan Cetak Purchase Order: dirender di dalam layout aplikasi
 * (ada sidebar/topbar di layar), tombol "Cetak" memanggil window.print(),
 * dan @media print (global + di sini) menyisakan hanya lembar voucher.
 *
 * Var dari CashController::printVoucher():
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
    .kas-voucher-print { max-width: 210mm; margin: 0 auto; }
    .kas-voucher-print .voucher {
        background: #fff;
        border: 1px solid #dee2e6;
        padding: 12mm 12mm 10mm;
        margin: 0 auto 20px;
        color: #1a1a1a;
        font-size: 11px;
        box-sizing: border-box;
    }
    /* _voucher.php menyisipkan <div class="page-break"> di antara voucher,
       jadi voucher ke-2 dst bukan sibling langsung -> pakai selektor "~". */
    .kas-voucher-print .voucher ~ .voucher { page-break-before: always; break-before: page; }
    .kas-voucher-print .page-break { page-break-after: always; break-after: page; }

    .kas-voucher-print .company { text-align: center; font-size: 11px; margin-bottom: 6px; color: #843C0C; }

    .kas-voucher-print table.head { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    .kas-voucher-print table.head > tbody > tr > td { border: 1.5px solid #843C0C; vertical-align: top; padding: 5px 7px; }
    .kas-voucher-print .head-left { width: 33%; }
    .kas-voucher-print .head-left .lbl { font-size: 10px; color: #555; }
    .kas-voucher-print .head-left .val { font-weight: bold; font-size: 12px; margin-top: 8px; }
    .kas-voucher-print .head-mid { width: 34%; text-align: center; font-size: 17px; font-weight: bold; letter-spacing: 1px; color: #843C0C; vertical-align: middle; }
    .kas-voucher-print .head-right { width: 33%; padding: 3px 6px; }
    .kas-voucher-print table.nomor { width: 100%; border-collapse: collapse; }
    .kas-voucher-print table.nomor td { border: 0; padding: 2px 3px; font-size: 11px; }
    .kas-voucher-print table.nomor td.k { width: 52px; }
    .kas-voucher-print table.nomor td.s { width: 8px; }
    .kas-voucher-print table.nomor td.v { font-weight: bold; }

    .kas-voucher-print table.grid { width: 100%; border-collapse: collapse; }
    .kas-voucher-print table.grid th, .kas-voucher-print table.grid td { border: 1px solid #843C0C; padding: 4px 7px; vertical-align: top; }
    .kas-voucher-print table.grid thead th { background: #C55A11; color: #fff; text-align: center; font-weight: bold; letter-spacing: 2px; }
    .kas-voucher-print table.grid .c-keperluan { width: 26px; padding: 0; }
    .kas-voucher-print table.grid .c-jumlah { width: 130px; }
    .kas-voucher-print table.grid td.keperluan { text-align: center; padding: 2px 0; background: #F7CBAC; }
    .kas-voucher-print table.grid td.keperluan .vert { font-size: 8px; line-height: 1.15; letter-spacing: 0; color: #843C0C; font-weight: bold; }
    .kas-voucher-print table.grid td.uraian .sub { font-size: 9px; color: #666; margin-top: 2px; }
    .kas-voucher-print table.grid td.jumlah { text-align: right; white-space: nowrap; }
    .kas-voucher-print table.grid td.jumlah.neg { color: #b00020; }
    .kas-voucher-print table.grid tr.row-ch td.uraian,
    .kas-voucher-print table.grid tr.row-total td.uraian { text-align: right; font-weight: bold; }
    .kas-voucher-print table.grid tr.row-total td { background: #F7CBAC; font-weight: bold; }
    .kas-voucher-print table.grid tbody tr:nth-child(odd) td.uraian { background: #FDF1E8; }

    .kas-voucher-print table.terbilang, .kas-voucher-print table.catatan { width: 100%; border-collapse: collapse; }
    .kas-voucher-print table.terbilang td { border: 1px solid #843C0C; padding: 4px 7px; }
    .kas-voucher-print table.terbilang td.k { width: 70px; font-weight: bold; background: #F7CBAC; white-space: nowrap; }
    .kas-voucher-print table.terbilang td.v { font-style: italic; }
    .kas-voucher-print table.catatan td { border: 1px solid #843C0C; border-top: 0; padding: 5px 7px 22px; font-weight: bold; }

    .kas-voucher-print table.ttd { width: 100%; border-collapse: collapse; margin-top: 6px; }
    .kas-voucher-print table.ttd th, .kas-voucher-print table.ttd td { border: 1px solid #843C0C; text-align: center; padding: 4px; width: 25%; }
    .kas-voucher-print table.ttd th { background: #C55A11; color: #fff; font-weight: bold; }
    .kas-voucher-print table.ttd tr.space td { height: 66px; }

    .kas-voucher-print .foot { margin-top: 8px; font-size: 9px; color: #999; text-align: right; }

    @page { size: A4; margin: 10mm; }
    @media print {
        .kas-voucher-print { max-width: none; }
        .kas-voucher-print .voucher {
            border: none;
            margin: 0;
            padding: 4mm 8mm;
        }
        /* Paksa warna latar (header oranye) tetap tercetak walau "Background
           graphics" tidak dicentang di dialog cetak Chrome/Edge. */
        .kas-voucher-print, .kas-voucher-print * {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
        <h4 class="mb-0">Cetak Voucher Kas</h4>
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

<div class="kas-voucher-print">
    <?php
    $last = count($vouchers) - 1;
    foreach ($vouchers as $i => $v):
        $vHeader = $v['header'];
        $vItems  = $v['items'];
        $vIsLast = ($i === $last);
        require ROOT_PATH . '/app/views/cash/_voucher.php';
    endforeach;
    ?>
</div>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
