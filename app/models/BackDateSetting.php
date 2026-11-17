<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * BackDateSetting -- konfigurasi penguncian tanggal mundur PER MENU TRANSAKSI.
 *
 * Satu baris per kode menu (lihat BackDate::catalog()). is_active = 1 berarti transaksi menu itu
 * tidak boleh bertanggal sebelum tanggal aktual sistem. Kode tanpa baris = nonaktif (aturan lama).
 * Riwayat perubahan memakai tabel system_setting_history (setting_key = 'backdate:<kode>').
 */
class BackDateSetting extends Model
{
    protected string $table = 'backdate_settings';

    public const HISTORY_PREFIX = 'backdate:';

    /** [menu_code => bool aktif]. Selalu dibaca dari database (tanpa cache lintas-request). */
    public function activeMap(): array
    {
        $rows = $this->db->fetchAll("SELECT menu_code, is_active FROM backdate_settings");
        $map = [];
        foreach ($rows as $r) {
            $map[$r['menu_code']] = (int) $r['is_active'] === 1;
        }
        return $map;
    }

    /** Semua baris (untuk tampilan: siapa & kapan terakhir mengubah). */
    public function rowsByCode(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT b.*, u.full_name AS updated_by_name FROM backdate_settings b LEFT JOIN users u ON u.id = b.updated_by"
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['menu_code']] = $r;
        }
        return $out;
    }

    /**
     * Simpan keadaan SEMUA menu sekaligus dalam satu transaksi. $desired = [kode => bool] untuk
     * seluruh kode katalog (yang tidak dicentang = false). Hanya menu yang BERUBAH yang ditulis
     * & dicatat ke histori. Mengembalikan daftar perubahan [[code, name, old(bool), new(bool)], ...].
     */
    public function saveAll(array $desired, array $names, ?int $userId, ?string $userName): array
    {
        $current = $this->activeMap();
        $changes = [];

        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            foreach ($desired as $code => $want) {
                $had = $current[$code] ?? false;
                if ($had === (bool) $want) {
                    continue;
                }
                $this->db->query(
                    "INSERT INTO backdate_settings (menu_code, menu_name, is_active, updated_by, updated_at)
                     VALUES (:c, :n, :a, :u, NOW())
                     ON DUPLICATE KEY UPDATE menu_name = VALUES(menu_name), is_active = VALUES(is_active),
                                             updated_by = VALUES(updated_by), updated_at = NOW()",
                    ['c' => $code, 'n' => $names[$code] ?? $code, 'a' => $want ? 1 : 0, 'u' => $userId]
                );
                $this->db->insert('system_setting_history', [
                    'setting_key'     => self::HISTORY_PREFIX . $code,
                    'old_value'       => $had ? '1' : '0',
                    'new_value'       => $want ? '1' : '0',
                    'changed_by'      => $userId,
                    'changed_by_name' => $userName,
                ]);
                $changes[] = ['code' => $code, 'name' => $names[$code] ?? $code, 'old' => $had, 'new' => (bool) $want];
            }
            if ($manageTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return $changes;
    }

    /** Riwayat perubahan seluruh menu Back Date (terbaru dulu). */
    public function history(int $limit = 50): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, COALESCE(u.full_name, h.changed_by_name) AS actor
               FROM system_setting_history h
               LEFT JOIN users u ON u.id = h.changed_by
              WHERE h.setting_key LIKE 'backdate:%'
              ORDER BY h.changed_at DESC, h.id DESC
              LIMIT " . (int) $limit
        );
    }
}
