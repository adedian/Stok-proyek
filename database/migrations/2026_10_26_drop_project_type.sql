-- Batalkan 2026_10_24: sistem Jenis (Project / Non-Project) di Master Project dihapus.
-- Filter Project di Kas/Bank kembali menampilkan seluruh isi Master Project.
ALTER TABLE projects DROP COLUMN project_type;
