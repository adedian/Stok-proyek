<?php
/**
 * PO: pilihan project "Non Project" + Kategori Biaya (2026-10-13).
 *
 *  - purchase_orders.project_id boleh NULL (PO Non Project TIDAK memakai project palsu).
 *  - purchase_orders.is_non_project  : penanda eksplisit (1 = Non Project <=> project_id NULL).
 *  - purchase_orders.cost_category_id: relasi ke Master Kategori Biaya (tabel lama cash_categories,
 *    namanya tidak diubah), ON DELETE SET NULL; opsional (PO lama = NULL).
 *  - purchase_orders.cost_category_name: snapshot nama saat PO disimpan -- cadangan tampilan bila
 *    kategori master dihapus permanen (Tempat Sampah > Kosongkan).
 * Idempotent: tiap langkah dicek dulu lewat information_schema, aman dijalankan ulang.
 * Data PO lama tidak diubah (is_non_project = 0, kategori NULL).
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();

$hasColumn = static function (string $col) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = :c"
    );
    $st->execute(['c' => $col]);
    return (int) $st->fetchColumn() > 0;
};
$hasFk = static function (string $name) use ($pdo): bool {
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders'
            AND CONSTRAINT_NAME = :n AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );
    $st->execute(['n' => $name]);
    return (int) $st->fetchColumn() > 0;
};

// 1) project_id boleh NULL (FK ke projects tetap berlaku untuk nilai yang terisi).
$st = $pdo->prepare(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'project_id'"
);
$st->execute();
if ($st->fetchColumn() === 'NO') {
    $pdo->exec("ALTER TABLE purchase_orders MODIFY COLUMN project_id INT UNSIGNED NULL");
    echo "project_id -> NULL diperbolehkan\n";
}

// 2) Penanda Non Project.
if (!$hasColumn('is_non_project')) {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN is_non_project TINYINT(1) NOT NULL DEFAULT 0 AFTER project_id");
    echo "kolom is_non_project ditambahkan\n";
}

// 3) Kategori Biaya (relasi + snapshot nama).
if (!$hasColumn('cost_category_id')) {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN cost_category_id INT UNSIGNED NULL AFTER is_non_project");
    echo "kolom cost_category_id ditambahkan\n";
}
if (!$hasColumn('cost_category_name')) {
    $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN cost_category_name VARCHAR(100) NULL AFTER cost_category_id");
    echo "kolom cost_category_name ditambahkan\n";
}
if (!$hasFk('fk_po_cost_category')) {
    $pdo->exec(
        "ALTER TABLE purchase_orders
           ADD CONSTRAINT fk_po_cost_category FOREIGN KEY (cost_category_id)
           REFERENCES cash_categories (id) ON DELETE SET NULL"
    );
    echo "FK fk_po_cost_category ditambahkan\n";
}
echo "Migrasi PO Non Project + Kategori Biaya selesai.\n";
