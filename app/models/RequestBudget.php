<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/ProjectUserAccess.php';

/**
 * RequestBudget -- pengajuan budget project (modul mandiri).
 *
 * Alur status (nilai DB konsisten, label Indonesia hanya di UI):
 *   DRAFT -> PENDING_APPROVAL -> APPROVED -> SUBMITTED_ACCOUNTING
 *         -> ACCOUNTING_PROCESS -> FUNDS_RECEIVED -> COMPLETED
 *   PENDING_APPROVAL -> REJECTED -> (revisi) DRAFT
 *   SUBMITTED_ACCOUNTING -> ACCOUNTING_REJECTED -> (revisi) DRAFT / (ajukan ulang) SUBMITTED_ACCOUNTING
 *
 * Semua perpindahan status lewat transition() yang memakai UPDATE bersyarat
 * (WHERE status = status_lama) sehingga validasi status dilakukan atomik di DB,
 * bukan dipercaya dari form/frontend.
 *
 * Modul ini TIDAK menyentuh PO / Pembayaran / Kas / Invoice / Stok.
 */
class RequestBudget extends Model
{
    protected string $table = 'request_budgets';
    protected bool $softDelete = true;

    public const DRAFT = 'DRAFT';
    public const PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';
    public const SUBMITTED_ACCOUNTING = 'SUBMITTED_ACCOUNTING';
    public const ACCOUNTING_PROCESS = 'ACCOUNTING_PROCESS';
    public const FUNDS_RECEIVED = 'FUNDS_RECEIVED';
    public const COMPLETED = 'COMPLETED';
    public const ACCOUNTING_REJECTED = 'ACCOUNTING_REJECTED';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draft',
        self::PENDING_APPROVAL => 'Menunggu Approval',
        self::APPROVED => 'Disetujui',
        self::REJECTED => 'Ditolak',
        self::SUBMITTED_ACCOUNTING => 'Diajukan ke Accounting',
        self::ACCOUNTING_PROCESS => 'Diproses Accounting',
        self::FUNDS_RECEIVED => 'Dana Diterima',
        self::COMPLETED => 'Selesai',
        self::ACCOUNTING_REJECTED => 'Ditolak Accounting',
    ];

    public const STATUS_BADGES = [
        self::DRAFT => 'secondary',
        self::PENDING_APPROVAL => 'warning text-dark',
        self::APPROVED => 'success',
        self::REJECTED => 'danger',
        self::SUBMITTED_ACCOUNTING => 'info text-dark',
        self::ACCOUNTING_PROCESS => 'primary',
        self::FUNDS_RECEIVED => 'success',
        self::COMPLETED => 'dark',
        self::ACCOUNTING_REJECTED => 'danger',
    ];

    /** Status yang sudah masuk ranah Accounting (daftar "Request Budget Masuk"). */
    public const ACCOUNTING_STAGES = [
        self::SUBMITTED_ACCOUNTING, self::ACCOUNTING_PROCESS, self::FUNDS_RECEIVED,
        self::COMPLETED, self::ACCOUNTING_REJECTED,
    ];

    public static function statusLabel(?string $s): string
    {
        return self::STATUS_LABELS[$s] ?? (string) $s;
    }

    public static function statusBadge(?string $s): string
    {
        return self::STATUS_BADGES[$s] ?? 'secondary';
    }

    // ------------------------------------------------------------------
    // Nomor otomatis (RB-0001) -- atomik, aman dari 2 user bersamaan
    // ------------------------------------------------------------------

    /** Nomor berikutnya TANPA menaikkan counter (pratinjau di form). */
    public function previewNumber(): string
    {
        $row = $this->db->fetchOne(
            "SELECT next_number FROM document_number_counters WHERE doc_type = 'request_budget' AND year = 0"
        );
        return self::formatNumber($row ? (int) $row['next_number'] : 1);
    }

    /**
     * Ambil + naikkan counter. SELECT ... FOR UPDATE mengunci baris counter,
     * jadi dua request bersamaan selalu dapat nomor berbeda. Aman dipanggil di
     * dalam transaction caller (nesting-safe, pola CashNumber::next()).
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
                  WHERE doc_type = 'request_budget' AND year = 0 FOR UPDATE"
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
                    'doc_type' => 'request_budget', 'year' => 0, 'next_number' => 2,
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
        return 'RB-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Scope akses (siapa boleh melihat request mana)
    // ------------------------------------------------------------------

    /**
     * Cakupan lihat untuk user yang login:
     *  - mode 'all'        : semua request (Super Admin, PM/Purchase dgn view_all + hak approve/ajukan)
     *  - mode 'accounting' : hanya yang sudah diajukan ke Accounting (view_all tanpa hak approve)
     *  - mode 'project'    : request buatan sendiri ATAU di project yang di-assign (Project > Akses)
     */
    public function scopeForCurrentUser(): array
    {
        $uid = (int) currentUserId();
        if (currentUserRole() === ROLE_SUPER_ADMIN) {
            return ['mode' => 'all', 'user_id' => $uid, 'project_ids' => []];
        }
        if (can('request_budget', 'view_all')) {
            $approver = can('request_budget', 'approve') || can('request_budget', 'submit_accounting');
            return ['mode' => $approver ? 'all' : 'accounting', 'user_id' => $uid, 'project_ids' => []];
        }
        $ids = array_map(fn($p) => (int) $p['id'], (new ProjectUserAccess())->projectsForUser($uid));
        return ['mode' => 'project', 'user_id' => $uid, 'project_ids' => $ids];
    }

    /** @return array [sql-fragment (diawali AND), params] untuk alias tabel $a. */
    public function scopeSql(array $scope, string $a = 'rb'): array
    {
        if ($scope['mode'] === 'all') {
            return ['', []];
        }
        if ($scope['mode'] === 'accounting') {
            $marks = [];
            $params = [];
            foreach (self::ACCOUNTING_STAGES as $i => $st) {
                $marks[] = ":scst{$i}";
                $params["scst{$i}"] = $st;
            }
            return [" AND {$a}.status IN (" . implode(', ', $marks) . ")", $params];
        }
        $sql = " AND ({$a}.requester_user_id = :scuid";
        $params = ['scuid' => $scope['user_id']];
        if (!empty($scope['project_ids'])) {
            $marks = [];
            foreach ($scope['project_ids'] as $i => $pid) {
                $marks[] = ":scpj{$i}";
                $params["scpj{$i}"] = (int) $pid;
            }
            $sql .= " OR {$a}.project_id IN (" . implode(', ', $marks) . ")";
        }
        return [$sql . ")", $params];
    }

    /** Guard satu baris (detail/print/aksi) -- logika identik scopeSql(). */
    public function canView(array $rb, ?array $scope = null): bool
    {
        $scope = $scope ?? $this->scopeForCurrentUser();
        if ($scope['mode'] === 'all') {
            return true;
        }
        if ($scope['mode'] === 'accounting') {
            return in_array($rb['status'], self::ACCOUNTING_STAGES, true);
        }
        return (int) $rb['requester_user_id'] === (int) $scope['user_id']
            || in_array((int) $rb['project_id'], $scope['project_ids'], true);
    }

    /** Project yang boleh dipilih saat membuat request: Super Admin semua, lainnya hanya yang di-assign. */
    public function selectableProjects(): array
    {
        if (currentUserRole() === ROLE_SUPER_ADMIN) {
            return $this->db->fetchAll(
                "SELECT id, project_code, project_name FROM projects
                  WHERE deleted_at IS NULL AND status != 'closed' ORDER BY project_name ASC"
            );
        }
        return array_values(array_filter(
            (new ProjectUserAccess())->projectsForUser((int) currentUserId()),
            fn($p) => true
        ));
    }

    public function projectAllowedForCreate(int $projectId): bool
    {
        foreach ($this->selectableProjects() as $p) {
            if ((int) $p['id'] === $projectId) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Query
    // ------------------------------------------------------------------

    private function baseFrom(): string
    {
        return "FROM request_budgets rb
                JOIN projects p ON p.id = rb.project_id
                JOIN users rq ON rq.id = rb.requester_user_id
                LEFT JOIN users ap ON ap.id = rb.approved_by
                LEFT JOIN users ac ON ac.id = rb.accounting_processed_by";
    }

    /** @return array [where-sql, params] */
    private function filterWhere(array $filters, array $scope): array
    {
        $sql = " WHERE rb.deleted_at IS NULL";
        $params = [];

        [$ssql, $sparams] = $this->scopeSql($scope);
        $sql .= $ssql;
        $params += $sparams;

        if (!empty($filters['keyword'])) {
            // Smart Search: nomor (kode), pengaju, project, keperluan, dan nama barang/kebutuhan.
            [$kwSql, $kwParams] = SmartSearch::clause(
                $filters['keyword'],
                [
                    'rq.full_name', 'p.project_name', 'rb.purpose',
                    "(SELECT GROUP_CONCAT(i.item_name SEPARATOR ' ') FROM request_budget_items i WHERE i.request_budget_id = rb.id)",
                ],
                ['rb.request_number'],
                'rbkw'
            );
            if ($kwSql !== '') {
                $sql .= " AND {$kwSql}";
                $params += $kwParams;
            }
        }
        if (!empty($filters['project_id'])) {
            $sql .= " AND rb.project_id = :f_project";
            $params['f_project'] = (int) $filters['project_id'];
        }
        if (!empty($filters['requester_id'])) {
            $sql .= " AND rb.requester_user_id = :f_req";
            $params['f_req'] = (int) $filters['requester_id'];
        }
        if (!empty($filters['status']) && isset(self::STATUS_LABELS[$filters['status']])) {
            $sql .= " AND rb.status = :f_status";
            $params['f_status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND rb.request_date >= :f_from";
            $params['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND rb.request_date <= :f_to";
            $params['f_to'] = $filters['date_to'];
        }
        return [$sql, $params];
    }

    public function countFiltered(array $filters, array $scope): int
    {
        [$where, $params] = $this->filterWhere($filters, $scope);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS n " . $this->baseFrom() . $where, $params);
        return (int) ($row['n'] ?? 0);
    }

    public function listPaginated(array $filters, array $scope, int $limit, int $offset): array
    {
        [$where, $params] = $this->filterWhere($filters, $scope);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT rb.*, p.project_name, rq.full_name AS requester_name,
                    ap.full_name AS approved_by_name, ac.full_name AS accounting_by_name "
            . $this->baseFrom() . $where
            . " ORDER BY rb.request_date DESC, rb.id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public function findWithRelations(int $id)
    {
        return $this->db->fetchOne(
            "SELECT rb.*, p.project_name, p.project_code, rq.full_name AS requester_name,
                    ap.full_name AS approved_by_name, rj.full_name AS rejected_by_name,
                    sa.full_name AS submitted_accounting_by_name,
                    ac.full_name AS accounting_by_name, ar.full_name AS accounting_rejected_by_name,
                    fr.full_name AS funds_received_by_name, cp.full_name AS completed_by_name,
                    sg_rq.signature_image AS requester_signature, sg_rq.name AS requester_signature_name,
                    sg_ap.signature_image AS approver_signature, sg_ap.name AS approver_signature_name
               FROM request_budgets rb
               JOIN projects p ON p.id = rb.project_id
               JOIN users rq ON rq.id = rb.requester_user_id
               LEFT JOIN users ap ON ap.id = rb.approved_by
               LEFT JOIN users rj ON rj.id = rb.rejected_by
               LEFT JOIN users sa ON sa.id = rb.submitted_accounting_by
               LEFT JOIN users ac ON ac.id = rb.accounting_processed_by
               LEFT JOIN users ar ON ar.id = rb.accounting_rejected_by
               LEFT JOIN users fr ON fr.id = rb.funds_received_by
               LEFT JOIN users cp ON cp.id = rb.completed_by
               LEFT JOIN signatures sg_rq ON sg_rq.user_id = rb.requester_user_id AND sg_rq.deleted_at IS NULL
               LEFT JOIN signatures sg_ap ON sg_ap.user_id = rb.approved_by AND sg_ap.deleted_at IS NULL
              WHERE rb.id = :id AND rb.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function items(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM request_budget_items WHERE request_budget_id = :id ORDER BY id ASC",
            ['id' => $id]
        );
    }

    public function history(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, u.full_name AS user_name, r.role_name
               FROM request_budget_history h
               LEFT JOIN users u ON u.id = h.user_id
               LEFT JOIN roles r ON r.id = u.role_id
              WHERE h.request_budget_id = :id ORDER BY h.created_at ASC, h.id ASC",
            ['id' => $id]
        );
    }

    public function addHistory(int $id, ?int $userId, string $action, ?string $old, ?string $new, ?string $notes = null): void
    {
        $this->db->insert('request_budget_history', [
            'request_budget_id' => $id,
            'user_id'           => $userId,
            'action'            => $action,
            'old_status'        => $old,
            'new_status'        => $new,
            'notes'             => $notes,
        ]);
    }

    // ------------------------------------------------------------------
    // Simpan header + item (total DIHITUNG ULANG di sini, bukan dari JS)
    // ------------------------------------------------------------------

    /**
     * Normalisasi baris item & hitung total di backend.
     * @param array $rows [['item_name','description','qty','unit_name','price','notes'], ...]
     * @return array [items-yang-sudah-dihitung, total]
     */
    public static function computeItems(array $rows): array
    {
        $items = [];
        $total = 0.0;
        foreach ($rows as $r) {
            $name = trim((string) ($r['item_name'] ?? ''));
            $qty = (float) ($r['qty'] ?? 0);
            $price = (float) ($r['price'] ?? 0);
            if ($name === '' && $qty <= 0 && $price <= 0) {
                continue; // baris kosong diabaikan
            }
            $line = round($qty * $price, 2);
            $total += $line;
            $items[] = [
                'item_name'            => $name,
                'description'          => trim((string) ($r['description'] ?? '')) ?: null,
                'qty'                  => $qty,
                'unit_name'            => trim((string) ($r['unit_name'] ?? '')) ?: null,
                'estimated_unit_price' => $price,
                'estimated_total'      => $line,
                'notes'                => trim((string) ($r['notes'] ?? '')) ?: null,
            ];
        }
        return [$items, round($total, 2)];
    }

    public function replaceItems(int $id, array $items): void
    {
        $this->db->query("DELETE FROM request_budget_items WHERE request_budget_id = :id", ['id' => $id]);
        foreach ($items as $it) {
            $this->db->insert('request_budget_items', ['request_budget_id' => $id] + $it);
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
            "UPDATE request_budgets SET " . implode(', ', $sets)
            . " WHERE id = :id AND deleted_at IS NULL AND status IN (" . implode(', ', $marks) . ")",
            $params
        );
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Hitungan untuk Dashboard / notifikasi
    // ------------------------------------------------------------------

    public function countByStatus(string $status, array $scope): int
    {
        [$ssql, $sparams] = $this->scopeSql($scope);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM request_budgets rb
              WHERE rb.deleted_at IS NULL AND rb.status = :st" . $ssql,
            ['st' => $status] + $sparams
        );
        return (int) ($row['n'] ?? 0);
    }

    public function countVisible(array $scope): int
    {
        [$ssql, $sparams] = $this->scopeSql($scope);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM request_budgets rb WHERE rb.deleted_at IS NULL" . $ssql,
            $sparams
        );
        return (int) ($row['n'] ?? 0);
    }

    /** Pengaju-pengaju yang pernah membuat request (dropdown filter), dibatasi scope. */
    public function requesterOptions(array $scope): array
    {
        [$ssql, $sparams] = $this->scopeSql($scope);
        return $this->db->fetchAll(
            "SELECT DISTINCT rq.id, rq.full_name
               FROM request_budgets rb JOIN users rq ON rq.id = rb.requester_user_id
              WHERE rb.deleted_at IS NULL" . $ssql . " ORDER BY rq.full_name ASC",
            $sparams
        );
    }

    /** user_id aktif yang berhak (can) melakukan $action -- penerima notifikasi. */
    public function userIdsWithPermission(string $action): array
    {
        require_once ROOT_PATH . '/app/models/User.php';
        $ids = [];
        foreach ((new User())->activeListWithRole() as $u) {
            if (canForUser((int) $u['id'], $u['role_slug'], 'request_budget', $action)) {
                $ids[] = (int) $u['id'];
            }
        }
        return $ids;
    }
}
