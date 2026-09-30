<?php
require_once ROOT_PATH . '/core/Model.php';

class PurchaseOrderHistory extends Model
{
    protected string $table = 'purchase_order_history';

    /**
     * Peta event riwayat PO -> [modul, aksi] di Riwayat Aktivitas (audit log
     * global). Riwayat per-PO tetap seperti semula; event yang sama SEKARANG
     * juga dicatat ke audit log supaya PO/Penerimaan/Validasi/Pembayaran
     * muncul di Laporan > Riwayat Aktivitas (sebelumnya cuma di timeline PO).
     */
    private const AUDIT_MAP = [
        'created'                => ['purchase_order', 'create'],
        'updated'                => ['purchase_order', 'update'],
        'status_changed'         => ['purchase_order', 'update'],
        'deleted'                => ['purchase_order', 'delete'],
        'goods_received'         => ['goods_receipt',  'create'],
        'goods_receipt_updated'  => ['goods_receipt',  'update'],
        'goods_receipt_deleted'  => ['goods_receipt',  'delete'],
        'validation'             => ['validation',     'validate'],
        'payment_added'          => ['payment',        'create'],
        'payment_updated'        => ['payment',        'update'],
        'payment_deleted'        => ['payment',        'delete'],
    ];

    public function log(int $poId, string $action, string $description, ?int $userId): void
    {
        $this->create([
            'purchase_order_id' => $poId,
            'action'      => $action,
            'description' => $description,
            'created_by'  => $userId,
        ]);

        // Audit log global -- best-effort, tidak boleh menggagalkan aksi utama
        // (ActivityLog::log() sudah menelan exception-nya sendiri).
        if (isset(self::AUDIT_MAP[$action])) {
            require_once ROOT_PATH . '/app/models/ActivityLog.php';
            [$module, $auditAction] = self::AUDIT_MAP[$action];
            try {
                $po = $this->db->fetchOne("SELECT po_number FROM purchase_orders WHERE id = :id", ['id' => $poId]);
                $poNo = $po['po_number'] ?? ('#' . $poId);
            } catch (Throwable $e) {
                $poNo = '#' . $poId;
            }
            (new ActivityLog())->log($userId, $module, $auditAction, "PO {$poNo}: {$description}");
        }
    }

    public function timelineByPo(int $poId): array
    {
        $sql = "SELECT h.*, u.full_name
                FROM purchase_order_history h
                LEFT JOIN users u ON u.id = h.created_by
                WHERE h.purchase_order_id = :po_id
                ORDER BY h.created_at DESC";
        return $this->db->fetchAll($sql, ['po_id' => $poId]);
    }
}
