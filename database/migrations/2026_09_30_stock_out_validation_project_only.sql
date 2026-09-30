-- Validasi Pengeluaran Barang: HANYA untuk Pengeluaran Barang Project.
--
-- Aturan: transaksi stock_out yang punya project_id WAJIB divalidasi
-- (menunggu -> tervalidasi/ditolak). Transaksi TANPA project (mis. tujuan
-- Client via Invoice) TIDAK butuh validasi -- statusnya 'tidak_perlu',
-- tidak masuk daftar/hitungan Validasi Pengeluaran Barang, dan tidak
-- terkunci setelah dibuat. Penentu murni data project_id (bukan nama
-- kategori/project), jadi project baru dari Master Data otomatis ikut aturan.
--
-- Tidak ada kolom baru: hanya menambah 1 nilai enum. Stok TIDAK disentuh --
-- stock_out sudah mendebit stok saat dibuat, validasi hanya lapisan approval.

ALTER TABLE stock_out
    MODIFY COLUMN validation_status ENUM('menunggu','tervalidasi','ditolak','tidak_perlu') NOT NULL DEFAULT 'menunggu';

-- Baris non-project yang tidak pernah diputuskan orang (masih 'menunggu' atau
-- hasil grandfather migrasi 2026_09_29) -> 'tidak_perlu'. Baris non-project
-- yang PERNAH diputuskan user sungguhan (validated_by terisi) dibiarkan
-- sebagai catatan riwayat.
UPDATE stock_out
   SET validation_status = 'tidak_perlu',
       validated_by      = NULL,
       validated_at      = NULL,
       validation_note   = 'Bukan pengeluaran project, tidak perlu validasi'
 WHERE project_id IS NULL
   AND (validation_status = 'menunggu' OR validated_by IS NULL);

-- Beri Gusti hak melihat + memvalidasi lewat override per-user (mekanisme
-- user_permissions yang sudah ada, TANPA hardcode nama di kode aplikasi).
-- Dilewati otomatis kalau user dengan username 'gusti' tidak ada di DB ini.
INSERT INTO user_permissions (user_id, module, action, effect)
SELECT u.id, 'stock_out_validation', a.action, 'allow'
  FROM users u
  JOIN (SELECT 'view' AS action UNION ALL SELECT 'validate') a
 WHERE u.username = 'gusti'
   AND NOT EXISTS (
       SELECT 1 FROM user_permissions up
        WHERE up.user_id = u.id AND up.module = 'stock_out_validation' AND up.action = a.action
   );
