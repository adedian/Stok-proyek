-- ============================================================
-- Uraian Bank hasil "Transfer ke Kas": buang awalan "Transfer ke Kas <PIC> -- ".
--
-- Sebelumnya CashController::transferStore() menulis uraian sisi Bank sebagai
-- "Transfer ke Kas Gusti -- <catatan>". Kini uraian = catatan saja. Migrasi ini
-- menyamakan transaksi lama: hanya baris Bank yang tertaut ke Kas
-- (related_cash_transaction_id terisi) DAN uraiannya berbentuk
-- "Transfer ke Kas ... -- <catatan>" -- bagian setelah ' -- ' pertama dipakai.
-- Yang tanpa catatan ("Transfer ke Kas Gusti") dibiarkan. Idempotent: setelah
-- dijalankan, baris itu tak lagi diawali "Transfer ke Kas ".
-- ============================================================
UPDATE `bank_transaction_items` i
JOIN `bank_transactions` t ON t.id = i.bank_transaction_id
SET i.uraian = TRIM(SUBSTRING(i.uraian, LOCATE(' -- ', i.uraian) + 4))
WHERE t.related_cash_transaction_id IS NOT NULL
  AND i.uraian LIKE 'Transfer ke Kas %'
  AND LOCATE(' -- ', i.uraian) > 0
  AND CHAR_LENGTH(TRIM(SUBSTRING(i.uraian, LOCATE(' -- ', i.uraian) + 4))) > 0;

UPDATE `bank_transactions`
SET `uraian` = TRIM(SUBSTRING(`uraian`, LOCATE(' -- ', `uraian`) + 4))
WHERE `related_cash_transaction_id` IS NOT NULL
  AND `uraian` LIKE 'Transfer ke Kas %'
  AND LOCATE(' -- ', `uraian`) > 0
  AND CHAR_LENGTH(TRIM(SUBSTRING(`uraian`, LOCATE(' -- ', `uraian`) + 4))) > 0;
