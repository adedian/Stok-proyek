-- Pengajuan Budget: Project opsional per baris (di samping Kategori).
ALTER TABLE `pengajuan_budget_items`
  ADD COLUMN `project_id` int(10) unsigned DEFAULT NULL AFTER `category_id`,
  ADD KEY `idx_pbi_project` (`project_id`),
  ADD CONSTRAINT `fk_pbi_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;
