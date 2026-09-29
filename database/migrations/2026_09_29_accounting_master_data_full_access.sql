-- Role Accounting: FULL ACCESS (view/create/edit, TIDAK delete -- aturan
-- existing "accounting tidak pernah delete" tetap dipertahankan) ke Master
-- Data operasional. role_permissions sudah punya baris untuk kombinasi ini
-- (dibuat otomatis saat matrix Hak Akses pertama kali di-generate), tinggal
-- di-aktifkan -- BUKAN INSERT baru.
--
-- Scope: hanya master data "operasional" yang eksplisit diminta (dipakai
-- langsung di transaksi yang sudah Accounting kerjakan -- Invoice, PO,
-- Payment). SENGAJA TIDAK termasuk master_kode/master_bank/master_rekening/
-- user_pic (PIC Kas) -- itu konfigurasi penomoran & rekening yang lebih
-- sensitif, tidak diminta eksplisit, dan saat ini SA-only untuk SEMUA role
-- (bukan cuma Accounting) -- mengubahnya butuh keputusan terpisah.
--
-- Kas Accounting TIDAK disentuh sama sekali oleh migrasi ini (permission
-- module 'cash'/'cash_validation' tidak termasuk daftar di bawah).

UPDATE role_permissions SET allowed = 1
 WHERE role_slug = 'accounting'
   AND action IN ('view', 'create', 'edit')
   AND module IN (
     'supplier', 'project', 'client', 'item', 'item_category',
     'unit', 'warehouse', 'payment_method', 'signature', 'dp_percentage'
   );
