-- ============================================================
-- Akhiran Nomor PO: pemisah antara nomor otomatis dan akhiran diganti dari "/"
-- menjadi SPASI, teks akhiran persis seperti yang diketik
-- (147/PO.HME/X/2026 rev1, bukan .../2026/rev1).
--
-- Menyesuaikan PO yang SUDAH tersimpan dengan format lama: hanya baris yang
-- po_number-nya benar-benar berakhiran '/<akhiran>'. Idempotent -- dijalankan
-- ulang tidak mengubah apa pun (baris yang sudah memakai spasi tidak cocok).
-- ============================================================
UPDATE `purchase_orders`
SET `po_number` = CONCAT(
        LEFT(`po_number`, CHAR_LENGTH(`po_number`) - CHAR_LENGTH(`po_number_suffix`) - 1),
        ' ',
        `po_number_suffix`
    )
WHERE `po_number_suffix` IS NOT NULL
  AND `po_number_suffix` <> ''
  AND RIGHT(`po_number`, CHAR_LENGTH(`po_number_suffix`) + 1) = CONCAT('/', `po_number_suffix`);
