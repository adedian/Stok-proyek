<?php
/**
 * Menu "Label Pengiriman" menjadi menu "Form" dengan beberapa jenis
 * (Pengiriman Dokumen, Kwitansi, Order Form, Pengajuan Bon Sementara).
 *
 * - Tabel `forms` : 1 baris per form; isian spesifik jenis disimpan sebagai JSON di kolom `data`.
 * - Baris lama `shipping_labels` dipindah jadi form jenis 'pengiriman', lalu tabel lamanya dibuang.
 * - Izin modul 'shipping_label' (role_permissions, user_permissions) dan Riwayat Aktivitas
 *   dipindah ke modul 'form'; izin yang belum ada di-seed dari config/permissions.php.
 *
 * Idempotent: aman dijalankan ulang.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

// Dibungkus closure: bin/migrate.php me-require file ini di scope-nya sendiri, jadi variabel lokal
// (mis. $ins) TIDAK boleh bocor dan menimpa variabel runner.
(function () {
$pdo = getPDO();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `forms` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `form_type` VARCHAR(30) NOT NULL COMMENT 'pengiriman | kwitansi | order_form | bon_sementara',
      `summary` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Ringkasan satu baris untuk daftar',
      `data` LONGTEXT NOT NULL COMMENT 'Isian form (JSON), skema tergantung form_type',
      `deleted_at` DATETIME NULL,
      `deleted_by` INT UNSIGNED NULL,
      `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `created_by` INT UNSIGNED NULL,
      KEY `idx_forms_type` (`form_type`),
      KEY `idx_forms_deleted` (`deleted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// ---- pindahkan data Label Pengiriman lama ----
$moved = 0;
$hasOld = $pdo->query("SHOW TABLES LIKE 'shipping_labels'")->fetchColumn();
if ($hasOld) {
    $ins = $pdo->prepare(
        "INSERT INTO forms (form_type, summary, data, deleted_at, deleted_by, created_at, updated_at, created_by)
         VALUES ('pengiriman', :summary, :data, :deleted_at, :deleted_by, :created_at, :updated_at, :created_by)"
    );
    $hasCompany = $pdo->query("SHOW COLUMNS FROM shipping_labels LIKE 'to_company'")->fetchColumn();
    foreach ($pdo->query("SELECT * FROM shipping_labels ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $data = [];
        foreach (['to', 'from'] as $p) {
            $data["{$p}_name"]        = $r["{$p}_name"];
            $data["{$p}_company"]     = $hasCompany ? $r["{$p}_company"] : null;
            $data["{$p}_address"]     = $r["{$p}_address"];
            $data["{$p}_city"]        = $r["{$p}_city"];
            $data["{$p}_postal_code"] = $r["{$p}_postal_code"];
            $data["{$p}_phone"]       = $r["{$p}_phone"];
        }
        $ins->execute([
            'summary'    => mb_substr('Kepada ' . $r['to_name'] . ' - dari ' . $r['from_name'], 0, 255),
            'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
            'deleted_at' => $r['deleted_at'],
            'deleted_by' => $r['deleted_by'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at'],
            'created_by' => $r['created_by'],
        ]);
        $moved++;
    }
    $pdo->exec("DROP TABLE shipping_labels");
}

// ---- izin & log: shipping_label -> form ----
$pdo->exec("UPDATE IGNORE role_permissions SET module = 'form' WHERE module = 'shipping_label'");
$pdo->exec("DELETE FROM role_permissions WHERE module = 'shipping_label'");
$pdo->exec("UPDATE IGNORE user_permissions SET module = 'form' WHERE module = 'shipping_label'");
$pdo->exec("DELETE FROM user_permissions WHERE module = 'shipping_label'");
$pdo->exec("UPDATE activity_logs SET module = 'form' WHERE module = 'shipping_label'");

$editableRoles = ['purchase', 'accounting', 'pic_project', 'admin_project', 'project_manager'];
$matrix = require ROOT_PATH . '/config/permissions.php';
$seed = $pdo->prepare(
    "INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed)
     VALUES (:role_slug, 'form', :action, :allowed)"
);
$seeded = 0;
foreach (($matrix['form'] ?? []) as $action => $allowedRoles) {
    foreach ($editableRoles as $roleSlug) {
        $seed->execute([
            'role_slug' => $roleSlug,
            'action'    => $action,
            'allowed'   => in_array($roleSlug, $allowedRoles, true) ? 1 : 0,
        ]);
        $seeded += $seed->rowCount();
    }
}

echo "Form: tabel forms siap. Label lama dipindah: {$moved}. Izin baru di-seed: {$seeded}\n";
})();
