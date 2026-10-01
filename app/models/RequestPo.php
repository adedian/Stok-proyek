<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * RequestPo -- permintaan pembuatan PO (modul MANDIRI).
 *
 * Alur (HANYA 4 status, nilai DB konsisten, label Indonesia hanya di UI):
 *   DRAFT -> PENDING_APPROVAL -> APPROVED | REJECTED   (lalu BERHENTI)
 *
 * Tidak ada relasi/transaksi otomatis ke Request Budget / PO / Invoice / Kas / Stok.
 * Item hanya Nama Barang + Qty.
 *
 * Perpindahan status memakai transition() -- UPDATE bersyarat
 * (WHERE status = status_lama) sehingga validasi status atomik di DB, bukan
 * dipercaya dari form/frontend.
 */
class RequestPo extends Model
{
    protected string $table = 'request_pos';
    protected bool $softDelete = true;

    public const DRAFT = 'DRAFT';
    public const PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draft',
        self::PENDING_APPROVAL => 'Menunggu Approval',
        self::APPROVED => 'Disetujui',
        self::REJECTED => 'Ditolak',
    ];

    public const STATUS_BADGES = [
        self::DRAFT => 'secondary',
        self::PENDING_APPROVAL => 'warning text-dark',
        self::APPROVED => 'success',
        self::REJECTED => 'danger',
    ];

    /** Batas wajar input -- cegah overflow DECIMAL(15,2) & payload berlebihan. */
    public const MAX_ITEMS = 200;
    public const MAX_QTY = 1000000000;

    public static function statusLabel(?string $s): string
    {
        return self::STATUS_LABELS[$s] ?? (string) $s;
    }

    public static function statusBadge(?string $s): string
    {
        return self::STATUS_BADGES[$s] ?? 'secondary';
    }

    // ------------------------------------------------------------------
    // Nomor otomatis (RPO-0001) -- atomik, aman dari 2 user bersamaan
    // ------------------------------------------------------------------

    /** Nomor berikutnya TANPA menaikkan counter (pratinjau di form). */
    public function previewNumber(): string
    {
        $row = $this->db->fetchOne(
            "SELECT next_number FROM document_number_counters WHERE doc_type = 'request_po' AND year = 0"
        );
        return self::formatNumber($row ? (int) $row['next_number'] : 1);
    }

    /**
     * Ambil + naikkan counter. SELECT ... FOR UPDATE mengunci baris counter, jadi dua
     * request bersamaan selalu dapat nomor berbeda. Nesting-safe (pola RequestBudget).
     */
    public function nextNumber(): string
    {
        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $row = $this->db->fetchOne(
                "SELECT id, next_number FROM document_number_counters
                  WHERE doc_type = 'request_po' AND year = 0 FOR UPDATE"
            );
            if ($row) {
                $number = (int) $row['next_number'];
                $this->db->query(
                    "UPDATE document_number_counters SET next_number = :n WHERE id = :id",
                    ['n' => $number + 1, 'id' => $row['id']]
                );
            } else {
                $number = 1;
                $this->db->insert('document_number_counters', [
                    'doc_type' => 'request_po', 'year' => 0, 'next_number' => 2,
                ]);
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
        return self::formatNumber($number);
    }

    public static function formatNumber(int $n): string
    {
        return 'RPO-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Query daftar
    // ------------------------------------------------------------------

    private function baseFrom(): string
    {
        return "FROM request_pos rp
                JOIN projects p ON p.id = rp.project_id";
    }

    /** @return array [where-sql, params] */
    private function filterWhere(array $filters): array
    {
        $sql = " WHERE rp.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['number'])) {
            [$kwSql, $kwParams] = SmartSearch::clause(
                $filters['number'],
                ['rp.purpose', "(SELECT GROUP_CONCAT(i.item_name SEPARATOR ' ') FROM request_po_items i WHERE i.request_po_id = rp.id)"],
                ['rp.request_po_number'],
                'rpokw'
            );
            if ($kwSql !== '') {
                $sql .= " AND {$kwSql}";
                $params += $kwParams;
            }
        }
        if (!empty($filters['requester'])) {
            $sql .= " AND rp.requester_name LIKE :f_req";
            $params['f_req'] = '%' . addcslashes($filters['requester'], '%_\\') . '%';
        }
        if (!empty($filters['project_id'])) {
            $sql .= " AND rp.project_id = :f_project";
            $params['f_project'] = (int) $filters['project_id'];
        }
        if (!empty($filters['status']) && isset(self::STATUS_LABELS[$filters['status']])) {
            $sql .= " AND rp.status = :f_status";
            $params['f_status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND rp.request_date >= :f_from";
            $params['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND rp.request_date <= :f_to";
            $params['f_to'] = $filters['date_to'];
        }
        return [$sql, $params];
    }

    public function countFiltered(array $filters): int
    {
        [$where, $params] = $this->filterWhere($filters);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS n " . $this->baseFrom() . $where, $params);
        return (int) ($row['n'] ?? 0);
    }

    public function listPaginated(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = $this->filterWhere($filters);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT rp.*, p.project_name,
                    (SELECT COUNT(*) FROM request_po_items i WHERE i.request_po_id = rp.id) AS item_count "
            . $this->baseFrom() . $where
            . " ORDER BY rp.request_date DESC, rp.id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public function findWithRelations(int $id)
    {
        return $this->db->fetchOne(
            "SELECT rp.*, p.project_name, p.project_code,
                    cr.full_name AS creator_name,
                    ap.full_name AS approved_by_name, rj.full_name AS rejected_by_name
               FROM request_pos rp
               JOIN projects p ON p.id = rp.project_id
               LEFT JOIN users cr ON cr.id = rp.created_by
               LEFT JOIN users ap ON ap.id = rp.approved_by
               LEFT JOIN users rj ON rj.id = rp.rejected_by
              WHERE rp.id = :id AND rp.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function items(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM request_po_items WHERE request_po_id = :id ORDER BY id ASC",
            ['id' => $id]
        );
    }

    public function history(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, u.full_name AS user_name
               FROM request_po_history h
               LEFT JOIN users u ON u.id = h.user_id
              WHERE h.request_po_id = :id ORDER BY h.created_at ASC, h.id ASC",
            ['id' => $id]
        );
    }

    /** Nama role pelaku (snapshot untuk riwayat/approval), mis. 'Purchase'. */
    public function roleNameOf(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }
        $row = $this->db->fetchOne(
            "SELECT r.role_name FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = :id",
            ['id' => $userId]
        );
        return $row['role_name'] ?? null;
    }

    public function addHistory(int $id, ?int $userId, string $action, ?string $old, ?string $new, ?string $notes = null): void
    {
        $this->db->insert('request_po_history', [
            'request_po_id' => $id,
            'user_id'       => $userId,
            'role_name'     => $this->roleNameOf($userId),
            'action'        => $action,
            'old_status'    => $old,
            'new_status'    => $new,
            'notes'         => $notes,
        ]);
    }

    // ------------------------------------------------------------------
    // Item (hanya Nama Barang + Qty)
    // ------------------------------------------------------------------

    /**
     * Normalisasi baris item dari form. Baris yang SEMUANYA kosong dibuang; baris
     * setengah terisi dipertahankan supaya validasi bisa menegur.
     * @param array $rows [['item_name' => , 'qty' => float], ...]
     */
    public static function normalizeItems(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) ($r['item_name'] ?? ''));
            $qty = (float) ($r['qty'] ?? 0);
            if ($name === '' && $qty == 0.0) {
                continue;
            }
            $out[] = ['item_name' => $name, 'qty' => $qty];
        }
        return $out;
    }

    public function replaceItems(int $id, array $items): void
    {
        $this->db->query("DELETE FROM request_po_items WHERE request_po_id = :id", ['id' => $id]);
        foreach ($items as $it) {
            $this->db->insert('request_po_items', ['request_po_id' => $id] + $it);
        }
    }

    /**
     * Pindah status secara ATOMIK: hanya berhasil kalau status saat ini masih salah
     * satu dari $fromStatuses. false = sudah diproses user lain / status tidak valid.
     */
    public function transition(int $id, array $fromStatuses, string $toStatus, array $extra = []): bool
    {
        $marks = [];
        $params = ['id' => $id, 'to_status' => $toStatus];
        foreach (array_values($fromStatuses) as $i => $st) {
            $marks[] = ":from{$i}";
            $params["from{$i}"] = $st;
        }
        $sets = ['status = :to_status'];
        foreach ($extra as $col => $val) {
            if (!preg_match('/^[a-z_]+$/', $col)) {
                continue;
            }
            $sets[] = "{$col} = :x_{$col}";
            $params["x_{$col}"] = $val;
        }
        $stmt = $this->db->query(
            "UPDATE request_pos SET " . implode(', ', $sets)
            . " WHERE id = :id AND deleted_at IS NULL AND status IN (" . implode(', ', $marks) . ")",
            $params
        );
        return $stmt->rowCount() > 0;
    }

    /** Kunci baris (FOR UPDATE, di dalam transaksi) dan kembalikan status terkini; null = tidak ada. */
    public function lockStatus(int $id): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT status FROM request_pos WHERE id = :id AND deleted_at IS NULL FOR UPDATE",
            ['id' => $id]
        );
        return $row['status'] ?? null;
    }

    /** Soft delete BERSYARAT: hanya kalau MASIH Draft. false = status sudah berubah / sudah terhapus. */
    public function softDeleteDraft(int $id, int $userId): bool
    {
        $stmt = $this->db->query(
            "UPDATE request_pos SET deleted_at = NOW(), deleted_by = :u WHERE id = :id AND status = :st AND deleted_at IS NULL",
            ['u' => $userId, 'id' => $id, 'st' => self::DRAFT]
        );
        return $stmt->rowCount() > 0;
    }

    /** user_id aktif yang berhak (can) melakukan $action -- penerima notifikasi. */
    public function userIdsWithPermission(string $action): array
    {
        require_once ROOT_PATH . '/app/models/User.php';
        $ids = [];
        foreach ((new User())->activeListWithRole() as $u) {
            if (canForUser((int) $u['id'], $u['role_slug'], 'request_po', $action)) {
                $ids[] = (int) $u['id'];
            }
        }
        return $ids;
    }
}
