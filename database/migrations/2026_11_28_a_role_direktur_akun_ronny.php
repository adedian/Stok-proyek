<?php
/**
 * Role baru DIREKTUR (VIEW ONLY lintas semua menu) + akun Pak Ronny.
 *
 * 1. roles: tambah 'direktur' bila belum ada.
 * 2. role_permissions: tiap modul TAK TERKUNCI di config/permissions.php x tiap aksi -> allowed=1 untuk aksi BACA
 *    (view, view_all, view_all_purchase, view_balance, print, export, print_voucher, stock_price, stock_report), allowed=0
 *    untuk sisanya (tambah/ubah/hapus/validasi/approve/dst). Modul khusus Super Admin (settings, user, trash, period_lock) tidak
 *    diberi apa pun. INSERT IGNORE: tidak menimpa pengaturan yang sudah ada. Admin bisa menyesuaikan di Pengaturan > Hak Akses.
 * 3. users: akun 'ronny' (nama "Ronny", role Direktur) bila belum ada. Password awal ACAK dicetak SEKALI di keluaran migrasi
 *    (tidak disimpan di berkas mana pun); segera ganti lewat menu Akun Saya. Email awal placeholder -- ubah di User Management.
 * Idempotent.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();
$matrix = require ROOT_PATH . '/config/permissions.php';
$locked = ['settings', 'user', 'trash', 'period_lock'];
$read = ['view', 'view_all', 'view_all_purchase', 'view_balance', 'print', 'export', 'print_voucher', 'stock_price', 'stock_report'];

// 1. role
$roleId = (int) $pdo->query("SELECT id FROM roles WHERE role_slug = 'direktur'")->fetchColumn();
if (!$roleId) {
    $pdo->exec("INSERT INTO roles (role_name, role_slug) VALUES ('Direktur', 'direktur')");
    $roleId = (int) $pdo->lastInsertId();
    echo "Role Direktur dibuat (id {$roleId}).\n";
}

// 2. izin
$permIns = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed) VALUES ('direktur', :m, :a, :v)");
$nOn = 0;
$nOff = 0;
foreach ($matrix as $module => $actions) {
    if (in_array($module, $locked, true)) {
        continue;
    }
    foreach (array_keys($actions) as $action) {
        $v = in_array($action, $read, true) ? 1 : 0;
        $permIns->execute(["m" => $module, 'a' => $action, 'v' => $v]);
        if ($permIns->rowCount()) {
            $v ? $nOn++ : $nOff++;
        }
    }
}
echo "role_permissions Direktur: {$nOn} aksi baca diizinkan, {$nOff} aksi lain tidak diizinkan.\n";

// 3. akun
$exists = $pdo->prepare("SELECT id FROM users WHERE username = 'ronny' AND deleted_at IS NULL");
$exists->execute();
if ($exists->fetchColumn()) {
    echo "Akun 'ronny' sudah ada -- tidak diubah.\n";
} else {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $pw = '';
    for ($i = 0; $i < 14; $i++) {
        $pw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    $pw .= random_int(2, 9) . '!';
    $pdo->prepare(
        "INSERT INTO users (role_id, full_name, username, email, password, status)
         VALUES (:r, 'Ronny', 'ronny', 'ronny@hexa.local', :p, 'active')"
    )->execute(['r' => $roleId, 'p' => password_hash($pw, PASSWORD_DEFAULT)]);
    echo "Akun dibuat -- username: ronny | password awal: {$pw}  (catat sekarang, tidak ditampilkan lagi; segera ganti di Akun Saya)\n";
}
