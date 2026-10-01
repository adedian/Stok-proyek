<?php
/**
 * Akun Purwati (role Finance) -- 2026-10-05.
 *
 * Role Finance sama persis dengan Accounting (alias di roleAlias(), lihat auth_helper.php).
 * Sandi awal sesuai permintaan user; yang disimpan di sini HANYA hash bcrypt-nya
 * (bukan teks asli). SEGERA ganti lewat Akun Saya setelah login pertama.
 * Idempotent: kalau username 'purwati' sudah ada -> dilewati (tidak menimpa sandi).
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$fnPdo = getPDO();
$fnRole = $fnPdo->prepare("SELECT id FROM roles WHERE role_slug = 'finance'");
$fnRole->execute();
$fnRoleId = (int) $fnRole->fetchColumn();
if (!$fnRoleId) {
    throw new RuntimeException("Role 'finance' tidak ditemukan di tabel roles.");
}

$fnExists = $fnPdo->prepare("SELECT id FROM users WHERE username = 'purwati'");
$fnExists->execute();
if ($fnExists->fetchColumn()) {
    echo "Akun 'purwati' sudah ada -- dilewati.\n";
    return;
}

$fnIns = $fnPdo->prepare(
    "INSERT INTO users (role_id, full_name, username, email, password, status)
     VALUES (:role_id, 'Purwati', 'purwati', 'purwati@hexamultienergi.com', :pw, 'active')"
);
$fnIns->execute(['role_id' => $fnRoleId, 'pw' => '$2y$10$N5VWfZ31PrHmRpNxOXe0nubYhhKFew8cpTprS7hKNSjuIVNLHrSIi']);
echo "Akun 'purwati' (role Finance) dibuat.\n";
