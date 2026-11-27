<style>
    /* Struktur & CSS print di sini SENGAJA dicerminkan dari purchase_order/print.php
       (pola cetak multi-dokumen yang sudah terbukti jalan: page-break aman, A4,
       print-color-adjust) -- lihat catatan di file itu untuk detail tiap aturan. */
    .inv-print-page {
        background: #fff;
        max-width: 210mm;
        margin: 0 auto 24px auto;
        padding: 14mm 14mm;
        border: 1px solid #dee2e6;
        color: #212529;
        font-size: 12.5px;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
    }
    .inv-print-page + .inv-print-page {
        page-break-before: always;
    }
    /* Isi invoice tetap kolom flex (margin antar-blok tidak saling menyatu) -- sama seperti saat .inv-print-page
       sendiri yang flex, sebelum dibungkus .inv-sheet (penyangga zona footer saat cetak). */
    .inv-sheet {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }
    .inv-sheet > tbody > tr > td, .inv-sheet > tfoot > tr > td {
        padding: 0;
        border: 0;
    }
    .inv-sheet-flow {
        display: flex;
        flex-direction: column;
    }
    .inv-sheet-foot {
        display: none;   /* hanya dipakai saat cetak */
    }
    .inv-print-header {
        border-bottom: 3px solid var(--brand-700, #1E3C72);
        padding-bottom: 10px;
        margin-bottom: 4px;
    }
    .inv-print-header img.company-logo {
        display: block;
        width: 100%;
        height: auto;
        object-fit: contain;
    }
    .inv-print-company-name {
        font-size: 22px;
        font-weight: 800;
        color: var(--brand-700, #1E3C72);
        margin: 0;
        line-height: 1.15;
    }
    .inv-print-company-meta {
        font-size: 11px;
        color: #495057;
        margin-top: 2px;
    }
    .inv-print-title {
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin: 14px 0 10px 0;
    }
    .inv-print-toprow table td {
        padding: 1px 6px 1px 0;
        vertical-align: top;
        font-size: 12.5px;
    }
    .inv-print-toprow table td:first-child {
        white-space: nowrap;
        color: #495057;
        width: 110px;
    }
    .inv-print-party {
        margin-top: 12px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
        font-size: 12.5px;
    }
    .inv-print-party-label {
        font-weight: 700;
        margin-bottom: 3px;
    }
    .inv-print-party div.line {
        line-height: 1.4;
    }
    .inv-print-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 14px;
        font-size: 12px;
    }
    .inv-print-table th, .inv-print-table td {
        border: 1px solid #adb5bd;
        padding: 4px 7px;
    }
    .inv-print-table th {
        background: #FFC000;
        text-align: left;
        font-weight: 700;
    }
    .inv-print-table td.num, .inv-print-table th.num {
        text-align: right;
    }
    /* "Rp" sempat turun ke baris sendiri (terpisah dari nominalnya) di kolom yang
       sempit (Harga Satuan, Harga Jumlah) -- paksa satu baris di SEMUA sel angka
       supaya "Rp" selalu sejajar/rapi dengan nominalnya. */
    .inv-print-table td.num {
        white-space: nowrap;
    }
    /* Ringkasan nominal (Jumlah/Termin/PPN/Total) -- baris colspan MENYATU di
       tabel item yang sama (border menyambung), label rata KANAN (nempel ke
       kolom nominal). Warna dua-tingkat sesuai contoh: baris "antara"
       (Jumlah/Termin/PPN) kuning pucat, baris Total kuning terang SAMA
       PERSIS dengan header tabel di atas -- supaya Total menonjol sebagai
       hasil akhir, sisanya tetap terlihat sebagai rincian/breakdown. */
    .inv-print-summary-row td {
        background: #FFF3CD;
        font-weight: 700;
        text-align: right;
    }
    .inv-print-summary-row.grand td {
        background: #FFC000;
        border-top: 2px solid #adb5bd;
    }
    .inv-print-notes {
        margin-top: 14px;
        font-size: 12px;
    }
    .inv-print-notes-title {
        font-weight: 700;
        margin-bottom: 2px;
    }
    /* Isi Note (blok "Transfer ke" dkk) digeser ke kanan relatif ke judul "Note:"
       -- sesuai template, cuma posisi yang berubah, isi/teks tetap sama. Digeser
       1 langkah lagi dari revisi sebelumnya (28px -> 40px) atas permintaan user,
       masih aman dari mepet tabel/keluar margin halaman A4. */
    .inv-print-notes-body {
        margin-left: 40px;
    }
    /* Blok tanda tangan mengalir tepat di bawah Note (tidak lagi ditekan ke dasar halaman), footer menyusul di bawahnya. */
    .inv-print-signoff {
        margin-top: 0;
        padding-top: 52px;
    }
    .inv-print-signoff-company {
        /* Nama perusahaan: sebelumnya top -18px -> 0 -> 54px (turun 3 baris) -> 18px (naik 2 baris), kini naik 1 baris lagi (~18px) -> 0;
           area TTD/"(.....)" & footer tetap di posisi semula. */
        position: relative;
        top: 0;
        font-weight: 700;
        margin-bottom: 4px;
    }
    /* Jarak nama perusahaan -> nama penandatangan dilebarkan (ruang TTD/stempel): 64px -> 131px
       (112px + 1 spasi ~19px: titik-titik/nama penandatangan & footer di bawahnya turun 1 spasi). */
    .inv-signature-image-wrap {
        height: 131px;
        display: flex;
        align-items: flex-end;
        margin-bottom: 2px;
    }
    .inv-signature-image-wrap img {
        max-height: 127px;
        max-width: 200px;
        object-fit: contain;
    }
    .inv-signature-placeholder {
        height: 131px;
        display: flex;
        align-items: flex-end;
        margin-bottom: 2px;
        color: #adb5bd;
        font-size: 12px;
    }
    .inv-signature-name {
        font-weight: 700;
        text-decoration: underline;
    }
    .inv-signature-position {
        color: #495057;
    }
    .inv-print-footer-strip {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        border-top: 1px solid #dee2e6;
        margin-top: 30px;
        padding-top: 8px;
        font-size: 10.5px;
        color: #495057;
        line-height: 1.5;
    }
    .inv-print-bottom-spacer { display: none; }
    .inv-footer-fixed { display: none; }
    .inv-print-footer-strip > div:last-child {
        border-left: 1px solid #adb5bd;
        padding-left: 16px;
    }
    /* size: auto = halaman mengikuti kertas dialog cetak (A4 dipaksa + kertas dialog lebih tinggi => Chrome menaruh
       halaman di tengah lembar & logo turun jauh). Margin atas 15mm = jarak logo dari tepi atas kertas (sama dgn PO). */
    /* Posisi footer (alamat/kontak + tanggal cetak) MENGIKUTI tinggi kertas lewat margin BAWAH @page: persen pada margin
       @page dihitung terhadap tinggi halaman. Footer & tanggal cetak dipasang position: fixed menempel di dasar area cetak
       (offset konstan 8,1mm / 0), jadi tepi bawah area cetak = posisi footer.
       Dikalibrasi supaya Legal PERSIS seperti sebelumnya (margin bawah 31,7mm => footer 39,8mm & tanggal cetak 31,7mm dari
       tepi bawah kertas); kertas lebih pendek mendapat footer lebih turun (A4 ~24mm, Letter ~19mm) -> jarak ke tanda tangan lega.
       Rumus: margin-bawah = 26,96% tinggi kertas - 64,18mm (Legal 31,7mm, F4 ~25mm, A4 15,9mm, Letter 11,2mm), minimal 10mm.
       Baris pertama = cadangan bila browser tak mengenali calc()/max() pada margin @page. */
    @page {
        size: auto;
        margin: 15mm 10mm 31.7mm 10mm;
        margin: 15mm 10mm max(10mm, calc(26.96% - 64.18mm)) 10mm;
    }
    @media print {
        .inv-print-page {
            border: none;
            margin: 0;
            max-width: none;
            padding: 0 12mm;   /* atas & bawah 0: jarak atas = margin @page; zona footer dijaga penyangga .inv-sheet-foot */
            display: block;    /* pembungkus saja (kolom flex-nya ada di .inv-sheet-flow): tabel penyangga boleh terpecah antar halaman */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }
        /* Penyangga zona footer di SETIAP lembar: .inv-sheet = <table> pembungkus isi, <tfoot> diulang Chrome di dasar
           tiap halaman => isi (tabel/total/tanda tangan) tak pernah menyentuh footer, bahkan invoice panjang lintas halaman;
           bila tak muat, blok pindah ke halaman berikut (bukan tertimpa/terpotong). Tinggi = footer 10,6mm (naik 8,1mm dari
           dasar area) + celah 8mm = 26.7mm. */
        .inv-sheet-foot {
            display: table-footer-group;
        }
        .inv-sheet-foot-spacer {
            height: 27mm;
        }
        /* Footer alamat/kontak: SATU elemen position: fixed menempel di dasar area cetak (bawah 8,1mm = ruang tanggal cetak),
           muncul di setiap lembar cetak. Salinan footer di dalam tiap invoice (.inv-footer-flow, hanya untuk layar)
           disembunyikan saat cetak. */
        .inv-footer-flow {
            display: none !important;
        }
        .inv-footer-fixed {
            display: flex !important;
            position: fixed;
            left: 12mm;
            right: 12mm;
            bottom: 8.1mm;     /* 8,1mm = 1 baris (4,8mm) + tinggi teks tanggal cetak (3,3mm) */
            margin: 0;
            background: #fff;
        }
        .inv-print-bottom-spacer {
            display: none !important;
        }
        /* Baris tabel & blok Note juga tidak boleh terbelah di tengah antar halaman. */
        .inv-print-table tr,
        .inv-print-notes {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        /* Tanda tangan tidak boleh terbelah antar halaman: bila tidak muat, seluruh blok pindah ke halaman berikut. */
        .inv-print-signoff {
            break-inside: avoid;
            page-break-inside: avoid;
        }
        /* Teks kecil "tanggal cetak, pencetak" (utilities.css: fixed): tepat 1 baris (~4,8mm) di bawah footer,
           menempel di dasar area cetak. */
        .doc-print-meta {
            bottom: 0;
        }
    }
</style>

<div class="d-flex justify-content-end gap-2 mb-3 no-print">
    <button type="button" class="btn btn-dark btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Cetak
    </button>
    <a href="<?= BASE_URL ?>/sales_invoice" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php foreach ($invoices as $inv): ?>
    <div class="inv-print-page">
      <table class="inv-sheet"><tbody><tr><td class="inv-sheet-body">
       <div class="inv-sheet-flow">
        <div class="inv-print-header">
            <?php if (!empty($company['company_logo'])): ?>
                <img src="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" alt="Logo" class="company-logo">
            <?php else: ?>
                <div>
                    <p class="inv-print-company-name"><?= e($company['company_name'] ?: 'Perusahaan') ?></p>
                    <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
                        <div class="inv-print-company-meta">
                            <?= e($company['company_address'] ?? '') ?>
                            <?php if (!empty($company['company_phone'])): ?> &middot; <?= e($company['company_phone']) ?><?php endif; ?>
                            <?php if (!empty($company['company_email'])): ?> &middot; <?= e($company['company_email']) ?><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="inv-print-title"><?= ($inv['document_kind'] ?? 'invoice') === 'proforma' ? 'PROFORMA INVOICE' : 'INVOICE' ?></div>

        <div class="inv-print-toprow">
            <table>
                <tr><td>No. Invoice</td><td>: <strong><?= e($inv['invoice_number']) ?></strong></td></tr>
                <tr><td>Tanggal</td><td>: <?= formatTanggal($inv['invoice_date']) ?></td></tr>
            </table>
        </div>

        <div class="inv-print-party">
            <div class="inv-print-party-label">Kepada</div>
            <div class="line"><?= e($inv['client_name']) ?></div>
            <?php if (!empty($inv['client_address'])): ?><div class="line"><?= nl2br(e($inv['client_address'])) ?></div><?php endif; ?>
        </div>

        <div class="inv-print-toprow" style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #dee2e6;">
            <table>
                <tr><td>No. Kontrak</td><td>: <?= e($inv['contract_number'] ?: '-') ?></td></tr>
                <tr><td>Tanggal</td><td>: <?= !empty($inv['contract_date']) ? formatTanggal($inv['contract_date']) : '-' ?></td></tr>
            </table>
        </div>

        <table class="inv-print-table">
            <thead>
                <tr>
                    <th style="width: 26px;">No</th>
                    <th>Deskripsi</th>
                    <th class="num" style="width: 50px;">Qty</th>
                    <th class="num" style="width: 125px;">Harga Satuan</th>
                    <th class="num" style="width: 135px;">Harga Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    // Exclude PPN dasar bruto (price_basis = 'gross'): harga item DICETAK sebagai harga Exclude (DPP) =
                    // harga / (1 + PPN%), mis. / 1,11 -- sehingga Harga Jumlah item sama dengan baris "Jumlah" di bawah.
                    // Nilai tersimpan (harga bruto) tidak berubah; invoice lama/dari Order ('net') & Include dicetak apa adanya.
                    $dppDivisor = (($inv['ppn_mode'] ?? 'tidak_termasuk') !== 'termasuk' && ($inv['price_basis'] ?? 'net') === 'gross')
                        ? 1 + ((float) $inv['ppn_percent']) / 100 : 1.0;
                    $dppItemsTotal = 0.0;
                ?>
                <?php foreach ($inv['items'] as $i => $item): ?>
                    <?php
                        $itemUnit = $dppDivisor > 1 ? round((float) $item['unit_price'] / $dppDivisor, 2) : $item['unit_price'];
                        $itemSub  = $dppDivisor > 1 ? round((float) $item['subtotal'] / $dppDivisor, 2) : $item['subtotal'];
                        $dppItemsTotal += (float) $itemSub;
                    ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= nl2br(e($item['description'])) ?></td>
                        <td class="num"><?= number_format((float) $item['qty'], 2, ',', '.') ?></td>
                        <td class="num"><?= formatRupiah($itemUnit) ?></td>
                        <td class="num"><?= formatRupiah($itemSub) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php
                    // Ringkasan nominal -- SATU tabel yang sama dengan item (bukan
                    // kotak terpisah lagi, atas permintaan user supaya tampilan
                    // persis seperti contoh: border/warna menyatu dengan tabel item).
                    // Urutan: Jumlah -> tiap Termin (NOMINAL saja) -> PPN (agregat) ->
                    // Total. Semua angka LANGSUNG dari kolom yang sudah dihitung
                    // backend (SalesInvoiceController::calculateTotals()) -- tidak
                    // ada perhitungan ulang di view ini.
                ?>
                <?php
                    // Perlakuan PPN tersimpan per invoice (sales_invoices.ppn_mode) -- cetak SELALU mengikutinya:
                    //   'tidak_termasuk' = PPN dihitung & ditambahkan: Jumlah, rincian termin, PPN (%), Total.
                    //   'termasuk'       = harga sudah memuat PPN: hanya Subtotal & Total -- TIDAK ada baris/persen/
                    //                      nominal/keterangan PPN sama sekali.
                    $showPpn = ($inv['ppn_mode'] ?? 'tidak_termasuk') !== 'termasuk';
                    // Termin tanpa persentase (nominal manual) SELALU dirinci di bawah subtotal, walau cuma satu & nilainya = Jumlah.
                    $singleFullTerm = count($inv['terms']) === 1 && (int) ($inv['terms'][0]['use_percentage'] ?? 1) === 1
                        && abs((float) $inv['terms'][0]['percentage'] - 100) < 0.01;
                    $termLabel = static function (array $t): string {
                        return e($t['label']) . ((int) ($t['use_percentage'] ?? 1) === 1 ? ' (' . formatPercent($t['percentage']) . '%)' : '');
                    };
                ?>
                <?php
                    // Exclude PPN dasar bruto (price_basis = 'gross'): baris "Jumlah" paling atas = DPP (= jumlah Harga Jumlah item
                    // yang dicetak sebagai DPP = harga / (1 + PPN%)), bukan harga bruto. Invoice lama/dari Order ('net') tetap memakai Jumlah item.
                    $topAmount = $inv['subtotal'];
                    if ($showPpn && ($inv['price_basis'] ?? 'net') === 'gross') {
                        $topAmount = $dppItemsTotal;   // = jumlah Harga Jumlah item (sudah dicetak sebagai DPP) -> kedua angka selalu sama
                    }
                ?>
                <?php if ($showPpn): ?>
                    <tr class="inv-print-summary-row">
                        <td colspan="4">Jumlah</td>
                        <td class="num"><?= formatRupiah($topAmount) ?></td>
                    </tr>
                    <?php foreach ($inv['terms'] as $t): ?>
                        <tr class="inv-print-summary-row">
                            <td colspan="4"><?= $termLabel($t) ?></td>
                            <td class="num"><?= formatRupiah($t['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="inv-print-summary-row">
                        <td colspan="4">PPN (<?= formatPercent($inv['ppn_percent']) ?>%)</td>
                        <td class="num"><?= formatRupiah($inv['ppn_amount']) ?></td>
                    </tr>
                <?php elseif ($singleFullTerm): ?>
                    <?php /* Satu termin 100%: Jumlah & termin identik -> cukup satu baris Subtotal. */ ?>
                    <tr class="inv-print-summary-row">
                        <td colspan="4">Subtotal</td>
                        <td class="num"><?= formatRupiah($inv['subtotal']) ?></td>
                    </tr>
                <?php else: ?>
                    <tr class="inv-print-summary-row">
                        <td colspan="4">Subtotal</td>
                        <td class="num"><?= formatRupiah($inv['subtotal']) ?></td>
                    </tr>
                    <?php foreach ($inv['terms'] as $t): ?>
                        <tr class="inv-print-summary-row">
                            <td colspan="4"><?= $termLabel($t) ?></td>
                            <td class="num"><?= formatRupiah($t['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                <tr class="inv-print-summary-row grand">
                    <td colspan="4">Total</td>
                    <td class="num"><?= formatRupiah($inv['total_amount']) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="inv-print-notes">
            <div class="inv-print-notes-title">Note:</div>
            <div class="inv-print-notes-body">
                1. Transfer ke:<br>
                <strong><?= e($bankAccount['account_holder_name'] ?? $company['company_name'] ?? '') ?></strong><br>
                <?php if (!empty($bankAccount['bank_name'])): ?><?= e($bankAccount['bank_name']) ?><br><?php endif; ?>
                <?php if (!empty($bankAccount['account_number'])): ?>No Rek: <?= e($bankAccount['account_number']) ?><?php endif; ?>
            </div>
            <?php if (!empty($inv['notes'])): ?>
                <div class="mt-2 inv-print-notes-body">2. <?= nl2br(e($inv['notes'])) ?></div>
            <?php endif; ?>
        </div>

        <div class="inv-print-signoff">
            <div class="inv-print-signoff-company"><?= e($company['company_name'] ?: 'Perusahaan') ?></div>
            <?php if (!empty($inv['signature_image'])): ?>
                <div class="inv-signature-image-wrap">
                    <img src="<?= BASE_URL ?>/<?= e($inv['signature_image']) ?>" alt="TTD <?= e($inv['signature_name']) ?>">
                </div>
                <div class="inv-signature-name"><?= e($inv['signature_name']) ?></div>
                <div class="inv-signature-position"><?= e($inv['signature_position']) ?></div>
            <?php else: ?>
                <div class="inv-signature-placeholder">(.....................)</div>
            <?php endif; ?>
        </div>

        <?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
            <div class="inv-print-footer-strip inv-footer-flow">
                <div><?= nl2br(e($company['company_address'] ?? '')) ?></div>
                <div>
                    <?= e($company['company_phone'] ?? '') ?>
                    <?php if (!empty($company['company_email'])): ?><br><?= e($company['company_email']) ?><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
       </div>
      </td></tr></tbody>
      <?php /* Penyangga zona footer: footer-group TABEL ASLI (div display:table-footer-group tidak diulang Chrome) => diulang di dasar tiap lembar cetak, supaya isi tak pernah menyentuh footer di halaman mana pun. Kosong/tak terlihat di layar. */ ?>
      <tfoot class="inv-sheet-foot"><tr><td><div class="inv-sheet-foot-spacer"></div></td></tr></tfoot>
      </table>
    </div>
<?php endforeach; ?>

<?php /* Footer cetak: SATU elemen fixed di dasar area cetak (hanya tampil saat cetak; di layar tiap invoice punya footer sendiri). */ ?>
<?php if (!empty($company['company_address']) || !empty($company['company_phone']) || !empty($company['company_email'])): ?>
    <div class="inv-print-footer-strip inv-footer-fixed">
        <div><?= nl2br(e($company['company_address'] ?? '')) ?></div>
        <div>
            <?= e($company['company_phone'] ?? '') ?>
            <?php if (!empty($company['company_email'])): ?><br><?= e($company['company_email']) ?><?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
