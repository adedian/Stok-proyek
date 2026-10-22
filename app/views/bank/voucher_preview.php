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
        padding: 12mm 12mm 10mm 18mm; /* margin KIRI lebih lebar dari kanan */
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

    /* ---- HEADER (3 kolom): kiri Diterima dari/Dibayarkan Kepada + Rekening | tengah perusahaan + judul | kanan Nomor/Tanggal ---- */
    .bank-voucher-print .vh { display: grid; grid-template-columns: 29.25% 42% 28.75%; border: 1px solid var(--v-color); }
    .bank-voucher-print .vh-left, .bank-voucher-print .vh-mid, .bank-voucher-print .vh-right { display: flex; flex-direction: column; min-width: 0; }
    .bank-voucher-print .vh-mid { border-left: 1px solid var(--v-color); border-right: 1px solid var(--v-color); }
    .bank-voucher-print .vh-left .vh-cell, .bank-voucher-print .vh-right .vh-cell { flex: 1; display: flex; align-items: center; padding: 4px 8px; min-height: 24px; }
    .bank-voucher-print .vh-right .vh-cell + .vh-cell { border-top: 1px solid var(--v-color); }
    .bank-voucher-print .vh-right .k { width: 56px; }
    .bank-voucher-print .vh-right .s { width: 14px; }
    .bank-voucher-print .vh-company { text-align: center; font-size: 11px; color: var(--v-color); padding: 3px 4px 0; }
    .bank-voucher-print .vh-title { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 17px; font-weight: bold; letter-spacing: 1px; color: var(--v-color); padding: 4px; }

    /* ---- GRID: kolom kosong | KEPERLUAN INTERN | Perkiraan | Uraian (putih) | Jumlah ---- */
    .bank-voucher-print col.g-blank { width: 10.7%; }
    .bank-voucher-print col.g-label { width: 2.5%; }
    .bank-voucher-print col.g-perkiraan { width: 12.3%; }
    .bank-voucher-print col.g-uraian { width: 57.4%; }
    .bank-voucher-print col.g-jumlah { width: 17.1%; }
    .bank-voucher-print table.grid, .bank-voucher-print table.terbilang, .bank-voucher-print table.bawah { width: 100%; table-layout: fixed; border-collapse: collapse; margin-top: -1px; }
    .bank-voucher-print table.grid td { border: 1px solid var(--v-color); padding: 2px 7px; line-height: 1.45; vertical-align: top; overflow: hidden; }
    .bank-voucher-print table.grid tr.grid-head td.c-perkiraan,
    .bank-voucher-print table.grid tr.grid-head td.c-uraian,
    .bank-voucher-print table.grid tr.grid-head td.c-jumlah { background: var(--v-color); color: #fff; text-align: center; font-weight: bold; letter-spacing: 2px; }
    .bank-voucher-print table.grid td.c-blank { background: #fff; }
    .bank-voucher-print table.grid td.c-label { background: #fff; padding: 0; text-align: center; vertical-align: middle; } /* KEPERLUAN INTERN di tengah tinggi tabel */
    .bank-voucher-print table.grid td.c-label .vert {
        display: block;
        writing-mode: vertical-rl;
        transform: rotate(180deg);
        letter-spacing: 3px;
        color: var(--v-color);
        white-space: nowrap;
        margin: 0 auto;
    }
    .bank-voucher-print table.grid td.perkiraan, .bank-voucher-print table.grid td.jumlah { background: var(--v-fill); }
    .bank-voucher-print table.grid td.uraian { background: #fff; }
    .bank-voucher-print .amt { display: flex; justify-content: space-between; white-space: nowrap; } /* "Rp" kiri, angka kanan */
    .bank-voucher-print table.grid td.jumlah.neg { color: #b00020; }
    .bank-voucher-print table.grid tr.row-last td { background: #fff; font-weight: bold; }
    .bank-voucher-print table.grid tr.row-last td.tot-label { text-align: right; }

    .bank-voucher-print table.terbilang td { border: 1px solid var(--v-color); padding: 4px 7px; background: var(--v-fill); }
    .bank-voucher-print table.terbilang td.k { font-weight: bold; white-space: nowrap; }
    .bank-voucher-print table.terbilang td.v { font-style: italic; }

    /* CATATAN (kiri) + tanda tangan (kanan) */
    .bank-voucher-print col.b-catatan { width: 29.25%; }
    .bank-voucher-print table.bawah th, .bank-voucher-print table.bawah td { border: 1px solid var(--v-color); text-align: center; padding: 4px; }
    .bank-voucher-print table.bawah th { background: var(--v-color); color: #fff; font-weight: bold; }
    .bank-voucher-print table.bawah tr.space td { height: 66px; background: var(--v-fill); }
    .bank-voucher-print table.bawah td.catatan { text-align: left; vertical-align: top; font-weight: bold; text-decoration: underline; color: var(--v-color); background: #fff; padding: 5px 7px; }

    .bank-voucher-print .foot { margin-top: 8px; font-size: 9px; color: #999; text-align: right; }

    /* Pratinjau di HP: TIDAK direflow (sama pola purchase_order/print.php &
       cash voucher) -- responsive-tables.js (scalePrintPreviews) menangani
       .print-page otomatis. @media print mengembalikan zoom:1. */

    /* Halaman POTRET penuh; ukuran kertas (A4 / Folio) mengikuti pilihan di dialog cetak. Voucher menempati
       setengah bagian ATAS halaman (sisanya kosong, untuk dipotong). */
    @page { size: portrait; margin: 2mm 10mm 10mm 10mm; } /* margin atas minimal: voucher mepet ke atas lembar */
    @media print {
        .bank-voucher-print { max-width: none; }
        .bank-voucher-print .voucher {
            border: none;
            margin: 0;
            padding: 1mm 0 4mm 14mm; /* kiri lebih lebar; kanan 0 -> tepi kanan tabel = margin @page 10mm (1 cm dari tepi kertas) */
            break-inside: avoid;
            page-break-inside: avoid;
        }
        /* Voucher mengisi SETENGAH kertas FOLIO (330mm -> setengah 165mm): mulai 3mm dari atas, tinggi 157mm,
           jadi isi berhenti 10mm (1 cm) sebelum garis tengah supaya aman dipotong. Tinggi tetap dalam mm
           (vh tidak mengikuti ukuran kertas pilihan). min-height: bila baris banyak, voucher tetap boleh memanjang.
           Sisa tinggi dibagi ke tabel isi (baris diperbesar, 3 bagian) dan area tanda tangan (1 bagian). */
        .bank-voucher-print .voucher { display: flex; flex-direction: column; min-height: 157mm; }
        .bank-voucher-print .voucher > .vh, .bank-voucher-print .voucher > table.terbilang, .bank-voucher-print .voucher > .foot { flex: 0 0 auto; }
        .bank-voucher-print .voucher > table.grid { flex: 3 1 auto; }
        .bank-voucher-print .voucher > table.bawah { flex: 1 1 auto; }
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
