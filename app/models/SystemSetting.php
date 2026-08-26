<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * SystemSetting
 * Penyimpanan key-value generik untuk Pengaturan Sistem (profil perusahaan,
 * prefix nomor dokumen, timeout session, toggle notifikasi). Model lain
 * (PurchaseOrder, GoodsReceipt, dst) cukup panggil get() dengan fallback
 * default supaya perilaku tidak berubah kalau admin belum pernah mengatur.
 */
class SystemSetting extends Model
{
    protected string $table = 'system_settings';

    public function get(string $key, string $default = ''): string
    {
        $row = $this->db->fetchOne(
            "SELECT setting_value FROM system_settings WHERE setting_key = :key",
            ['key' => $key]
        );
        if ($row === null || $row['setting_value'] === null || $row['setting_value'] === '') {
            return $default;
        }
        return (string) $row['setting_value'];
    }

    public function set(string $key, string $value, string $group, ?int $userId = null): void
    {
        $existing = $this->db->fetchOne(
            "SELECT id FROM system_settings WHERE setting_key = :key",
            ['key' => $key]
        );
        if ($existing) {
            $this->updateById((int) $existing['id'], ['setting_value' => $value, 'updated_by' => $userId]);
        } else {
            $this->create([
                'setting_key'   => $key,
                'setting_value' => $value,
                'setting_group' => $group,
                'updated_by'    => $userId,
            ]);
        }
    }

    /**
     * Ambil semua setting dalam satu grup sekaligus (mis. 'company', 'numbering')
     * sebagai array asosiatif key => value -- dipakai untuk mengisi tiap tab form Pengaturan.
     */
    public function getGroup(string $group): array
    {
        $rows = $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM system_settings WHERE setting_group = :group",
            ['group' => $group]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    public function getBool(string $key, bool $default = true): bool
    {
        $row = $this->db->fetchOne(
            "SELECT setting_value FROM system_settings WHERE setting_key = :key",
            ['key' => $key]
        );
        if ($row === null || $row['setting_value'] === null || $row['setting_value'] === '') {
            return $default;
        }
        return $row['setting_value'] === '1';
    }
}
