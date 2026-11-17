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

    /**
     * Simpan setting SEKALIGUS mencatat histori perubahan (siapa, kapan, nilai lama -> baru) ke
     * system_setting_history. Histori hanya ditulis bila nilainya BENAR-BENAR berubah. Atomik:
     * setting & histori disimpan dalam satu transaksi. Mengembalikan true bila ada perubahan.
     */
    public function setLogged(string $key, string $value, string $group, ?int $userId, ?string $userName): bool
    {
        $old = $this->db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = :k", ['k' => $key]);
        $oldValue = $old['setting_value'] ?? null;
        if ($oldValue !== null && (string) $oldValue === $value) {
            return false;
        }
        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $this->set($key, $value, $group, $userId);
            $this->db->insert('system_setting_history', [
                'setting_key'     => $key,
                'old_value'       => $oldValue,
                'new_value'       => $value,
                'changed_by'      => $userId,
                'changed_by_name' => $userName,
            ]);
            if ($manageTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return true;
    }

    /** Riwayat perubahan satu setting (terbaru dulu). */
    public function history(string $key, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, COALESCE(u.full_name, h.changed_by_name) AS actor
               FROM system_setting_history h
               LEFT JOIN users u ON u.id = h.changed_by
              WHERE h.setting_key = :k
              ORDER BY h.changed_at DESC, h.id DESC
              LIMIT " . (int) $limit,
            ['k' => $key]
        );
    }
}
