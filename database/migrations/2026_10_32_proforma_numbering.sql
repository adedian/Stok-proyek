-- ============================================================
-- Invoice Keluar: Jenis Dokumen = Proforma Invoice memakai penomoran sendiri,
-- TERPISAH dari INV.HME (Project) dan FKT.HME (Lampu):
--   Project -> "049/ProINV.HME/X/2026"   (counter 'sales_invoice_proforma_project')
--   Lampu   -> "049/ProFKT.HME/X/2026"   (counter 'sales_invoice_proforma_lampu')
-- Counter dibuat otomatis saat proforma pertama disimpan; No. Urut awal (mis. 049)
-- diatur di Pengaturan > Penomoran Dokumen > Reset Nomor Urut. Migration ini hanya
-- menyiapkan kode dokumennya.
-- Additive only: 2 baris system_settings.
-- ============================================================

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
SELECT 'prefix_proinv', 'ProINV.HME', 'numbering'
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'prefix_proinv');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
SELECT 'prefix_profkt', 'ProFKT.HME', 'numbering'
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'prefix_profkt');
