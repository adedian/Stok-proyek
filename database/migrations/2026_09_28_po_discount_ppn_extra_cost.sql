-- Revisi PO: diskon per item, PPN opsional per item, dan biaya tambahan
-- (multi baris, mis. ongkir/biaya bongkar) yang ikut menambah Grand Total.
--
-- Formula per baris barang (subtotal SUDAH termasuk diskon & PPN baris itu):
--   after_discount = qty * price * (1 - discount_percent/100)
--   ppn_amount     = ppn_enabled ? after_discount * ppn_percent/100 : 0
--   subtotal       = after_discount + ppn_amount
-- Grand Total PO = SUM(purchase_order_items.subtotal) + SUM(purchase_order_extra_costs.amount)
-- (lihat PurchaseOrder::recalculateTotal()).

ALTER TABLE purchase_order_items
    ADD COLUMN discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0 AFTER price,
    ADD COLUMN ppn_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER discount_percent,
    ADD COLUMN ppn_percent DECIMAL(5,2) NULL AFTER ppn_enabled;

CREATE TABLE IF NOT EXISTS purchase_order_extra_costs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    cost_name VARCHAR(150) NOT NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_po_extra_cost_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
