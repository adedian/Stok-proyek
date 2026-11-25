-- ============================================================
-- Invoice Keluar: dasar harga mode Exclude PPN.
--   price_basis = 'net'   : harga = NET, PPN ditambahkan di atas harga (invoice lama & invoice dari Order Penjualan).
--                           Cetak: baris "Jumlah" = Jumlah item.
--   price_basis = 'gross' : harga = BRUTO (rumus Excel user): DPP = harga / (1 + PPN%), PPN = DPP x PPN%,
--                           Total = harga. Cetak: baris "Jumlah" paling atas = DPP.
-- Hanya bermakna untuk ppn_mode = 'tidak_termasuk'. DEFAULT 'net' => semua invoice yang sudah ada tetap
-- dicetak persis seperti semula. Additive only.
--
-- Backfill: invoice Exclude non-Order yang tersimpan sejak rumus bruto (total termin = Jumlah x %, bukan
-- Jumlah x % x 1,11) ditandai 'gross'. Selisih Total vs Jumlah x % hanya pembulatan, jauh di bawah 11%.
-- ============================================================
ALTER TABLE `sales_invoices`
  ADD COLUMN `price_basis` ENUM('net','gross') NOT NULL DEFAULT 'net' AFTER `ppn_mode`;

UPDATE `sales_invoices`
   SET `price_basis` = 'gross'
 WHERE `ppn_mode` = 'tidak_termasuk'
   AND `sales_order_id` IS NULL
   AND `subtotal` > 0
   AND `ppn_percent` > 0
   AND ABS(`total_amount` - `subtotal` * `dp_percentage` / 100) <= (`subtotal` * 0.00005 + 0.05);
