<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Langganan Web Push -- 1 baris = 1 perangkat/browser (bukan 1 akun).
 * Dipakai app/helpers/push_helper.php untuk kirim notifikasi & membersihkan
 * langganan yang sudah tidak valid (browser push service balas 404/410).
 */
class PushSubscription extends Model
{
    protected string $table = 'push_subscriptions';

    /** Simpan/ganti langganan (endpoint sama = perangkat sama -> upsert). */
    public function upsert(int $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent): void
    {
        $hash = hash('sha256', $endpoint);
        $existing = $this->db->fetchOne(
            'SELECT id FROM push_subscriptions WHERE endpoint_hash = :h',
            ['h' => $hash]
        );

        if ($existing) {
            $this->db->query(
                'UPDATE push_subscriptions
                    SET user_id = :uid, p256dh = :p256dh, auth = :auth, user_agent = :ua
                  WHERE id = :id',
                ['uid' => $userId, 'p256dh' => $p256dh, 'auth' => $auth, 'ua' => $userAgent, 'id' => $existing['id']]
            );
            return;
        }

        $this->db->query(
            'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent)
             VALUES (:uid, :endpoint, :hash, :p256dh, :auth, :ua)',
            ['uid' => $userId, 'endpoint' => $endpoint, 'hash' => $hash, 'p256dh' => $p256dh, 'auth' => $auth, 'ua' => $userAgent]
        );
    }

    /** Hapus langganan milik user (dipanggil saat user menonaktifkan notifikasi di perangkat ini). */
    public function deleteByEndpoint(int $userId, string $endpoint): void
    {
        $this->db->query(
            'DELETE FROM push_subscriptions WHERE user_id = :uid AND endpoint_hash = :h',
            ['uid' => $userId, 'h' => hash('sha256', $endpoint)]
        );
    }

    /** @return array<int,array> semua langganan aktif milik satu user (bisa >1 perangkat). */
    public function forUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM push_subscriptions WHERE user_id = :uid',
            ['uid' => $userId]
        );
    }

    /** @return array<int,array> semua langganan milik banyak user sekaligus (batch kirim notifikasi). */
    public function forUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (empty($userIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        return $this->db->fetchAll(
            "SELECT * FROM push_subscriptions WHERE user_id IN ({$placeholders})",
            $userIds
        );
    }

    // deleteById() dipakai apa adanya dari Model (hard delete, push_subscriptions
    // tidak soft-delete) -- push_helper.php memanggilnya saat browser push
    // service balas 404/410 Gone (langganan sudah kadaluarsa).

    public function hasAnyForUser(int $userId): bool
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM push_subscriptions WHERE user_id = :uid LIMIT 1',
            ['uid' => $userId]
        );
        return $row !== null;
    }
}
