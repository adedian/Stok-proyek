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
        /* Nama perusahaan diturunkan 3 baris lagi (3 x ~18px = 54px; sebelumnya top: -18px -> 0 -> 54px);
           area TTD/"(.....)" & footer tetap di posisi semula. */
        position: relative;
        top: 54px;
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
    .inv-print-footer-strip > div:last-child {
        border-left: 1px solid #adb5bd;
        padding-left: 16px;
    }
    /* size: auto = halaman mengikuti kertas dialog cetak (A4 dipaksa + kertas dialog lebih tinggi => Chrome menaruh
       halaman di tengah lembar & logo turun jauh). Margin atas 15mm = jarak logo dari tepi atas kertas (sama dgn PO). */
    @page {
        size: auto;
        margin: 15mm 10mm 10mm 10mm;
    }
    @media print {
        .inv-print-page {
            border: none;
            margin: 0;
            max-width: none;
            padding: 0 12mm 0 12mm;     /* atas 0: jarak atas = margin @page saja; bawah 0: footer rapat ke tepi area cetak */
            min-height: 271mm;           /* A4 297 - 15 (atas) - 10 (bawah) = 272mm, dikurangi 1mm cadangan */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
        }
        /* Blok tanda tangan + footer ditekan ke dasar kertas (halaman = kolom flex setinggi min-height di atas):
           "PT Hexa Multi Energi"/TTD dan strip footer menempel di bawah, dengan jarak ~2 baris (margin @page bawah 10mm) dari tepi kertas. */
        .inv-print-signoff {
            margin-top: auto;
        }
        .inv-print-footer-strip {
            margin-top: 14px;
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
                <?php foreach ($inv['items'] as $i => $item): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= nl2br(e($item['description'])) ?></td>
                        <td class="num"><?= number_format((float) $item['qty'], 2, ',', '.') ?></td>
                        <td class="num"><?= formatRupiah($item['unit_price']) ?></td>
                        <td class="num"><?= formatRupiah($item['subtotal']) ?></td>
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
                <?php if ($showPpn): ?>
                    <tr class="inv-print-summary-row">
                        <td colspan="4">Jumlah</td>
                        <td class="num"><?= formatRupiah($inv['subtotal']) ?></td>
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
            <div class="inv-print-footer-strip">
                <div><?= nl2br(e($company['company_address'] ?? '')) ?></div>
                <div>
                    <?= e($company['company_phone'] ?? '') ?>
                    <?php if (!empty($company['company_email'])): ?><br><?= e($company['company_email']) ?><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="doc-print-meta"><?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?></div>
