-- Jenis entri di Master Project: 'project' (project sungguhan) / 'non_project' (biaya / kantong stok
-- yang dulu didaftarkan sebagai project, mis. Operasional Kantor, Pajak). Dipakai HANYA untuk
-- menyaring dropdown Project di filter Kas/Bank & Laporan Kas -- form transaksi & modul lain tidak berubah.
ALTER TABLE projects
  ADD COLUMN project_type ENUM('project','non_project') NOT NULL DEFAULT 'project' AFTER project_name;

-- Tandai entri biaya/stok yang sudah ada (cocok nama persis; bisa diubah lagi di Master Project).
UPDATE projects
   SET project_type = 'non_project'
 WHERE project_name IN ('Operasional Kantor', 'Pembayaran Angsuran', 'Biaya Perjalanan Dinas', 'Pajak',
                        'Stok Project', 'Stok HME');
