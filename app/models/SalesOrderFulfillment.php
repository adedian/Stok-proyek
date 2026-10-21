<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SalesOrder.php';
require_once ROOT_PATH . '/app/models/Inventory.php';
require_once ROOT_PATH . '/app/models/StockOut.php';

/**
 * SalesOrderFulfillment -- PEMENUHAN Order Penjualan.
 *
 * Satu pemenuhan = SATU dokumen Pengeluaran Barang (nomor stock_out biasa) berisi satu atau
 * lebih baris stock_out yang menunjuk ke baris order. Stok berkurang HANYA lewat
 * Inventory::debitStock() (jalur Pengeluaran Barang yang sudah ada), sehingga Kartu Stok,
 * laporan, dan notifikasi stok minimum tetap konsisten.
 *
 * Pengamanan (spec bagian 4D / skenario F):
 *   1. Seluruh proses dalam SATU transaksi database; gagal di mana pun = rollback total.
 *   2. Baris sales_orders dikunci (FOR UPDATE) -> dua pengguna yang memproses order yang
 *      sama diserialkan; sisa pesanan dihitung ULANG setelah kunci didapat.
 *   3. Baris inventory dikunci (FOR UPDATE) sebelum dibaca/dikurangi -> dua order berbeda
 *      yang memperebutkan stok yang sama tidak bisa sama-sama lolos.
 *   4. process_token UNIQUE: klik ganda / refresh / replay POST yang sama gagal di level
 *      database ("sudah diproses"), tidak membuat pemenuhan ganda.
 *   5. Jumlah per baris dibatasi sisa pesanan DAN stok aktual -> stok tidak pernah negatif,
 *      pesanan tidak pernah terpenuhi melebihi qty-nya.
 */
class SalesOrderFulfillment extends Model
{
    protected string $table = 'sales_order_fulfillments';

    private SalesOrder $orders;
    private Inventory $inventory;
    private StockOut $stockOut;

    public function __construct()
    {
        parent::__construct();
        $this->orders = new SalesOrder();
        $this->inventory = new Inventory();
        $this->stockOut = new StockOut();
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param array<int, array{order_item_id:int, qty:float, inventory_id:int}> $lines
     *        inventory_id 0 = otomatis (Stok Kantor dulu, lalu stok terbanyak); >0 = sumber stok dipilih Accounting.
     * @return array{fulfillment_id:int, stock_out_number:string, rows:int, qty_total:float, remaining:float}
     * @throws DomainException pesan aman ditampilkan ke user
     */
    public function fulfill(int $orderId, array $lines, string $date, string $token, string $picName, ?string $notes, int $userId): array
    {
        $lines = array_values(array_filter($lines, fn($l) => $l['qty'] > SalesOrder::EPS));
        if (!$lines) {
            throw new DomainException('Isi jumlah pemenuhan minimal untuk satu barang.');
        }
        if (!preg_match('/^[0-9a-f]{32}$/', $token)) {
            throw new DomainException('Sesi form tidak valid. Muat ulang halaman Proses lalu coba lagi.');
        }

        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            // (2) Serialkan semua proses atas order ini.
            $order = $this->orders->lockForUpdate($orderId);
            if (!$order) {
                throw new DomainException('Order Penjualan tidak ditemukan.');
            }
            if ($order['status'] === SalesOrder::CANCELLED) {
                throw new DomainException('Order sudah dibatalkan -- tidak bisa diproses.');
            }
            if ($order['status'] !== SalesOrder::ACTIVE) {
                throw new DomainException('Order masih berstatus Draft. Purchase harus mengirimkannya ke Accounting terlebih dahulu.');
            }

            // (4) Token sekali-pakai. Duplikat = permintaan yang sama dikirim ulang.
            try {
                $fulfillmentId = $this->db->insert('sales_order_fulfillments', [
                    'sales_order_id'   => $orderId,
                    'fulfillment_date' => $date,
                    'process_token'    => $token,
                    'notes'            => $notes,
                    'created_by'       => $userId,
                ]);
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    throw new DomainException('Permintaan ini sudah diproses sebelumnya (klik ganda atau halaman dimuat ulang). Periksa daftar pemenuhan pada detail order.');
                }
                throw $e;
            }

            $clientName = (string) ($this->db->fetchOne("SELECT client_name FROM clients WHERE id = :id", ['id' => $order['client_id']])['client_name'] ?? '');
            $destination = mb_substr($clientName . ' (PO ' . $order['po_number'] . ')', 0, 200);

            $docNumber = null;
            $docId = null;
            $rowCount = 0;
            $qtyTotal = 0.0;
            $seenLines = [];

            foreach ($lines as $line) {
                $lineId = (int) $line['order_item_id'];
                if (isset($seenLines[$lineId])) {
                    throw new DomainException('Ada baris barang yang terkirim dua kali.');
                }
                $seenLines[$lineId] = true;

                $item = $this->db->fetchOne(
                    "SELECT soi.*, it.item_name AS master_name
                       FROM sales_order_items soi JOIN items it ON it.id = soi.item_id
                      WHERE soi.id = :id AND soi.sales_order_id = :oid",
                    ['id' => $lineId, 'oid' => $orderId]
                );
                if (!$item) {
                    throw new DomainException('Ada baris barang yang bukan bagian dari order ini.');
                }

                // Sisa pesanan dihitung ULANG di bawah kunci order.
                $remaining = round((float) $item['qty'] - $this->orders->fulfilledQty($lineId), 2);
                $qty = round((float) $line['qty'], 2);
                if ($qty > INPUT_MAX_QTY) {
                    throw new DomainException('Jumlah melebihi batas wajar.');
                }
                if ($remaining <= SalesOrder::EPS) {
                    throw new DomainException("Barang '{$item['item_name']}' sudah terpenuhi seluruhnya.");
                }
                if ($qty > $remaining + SalesOrder::EPS) {
                    throw new DomainException(
                        "Jumlah pemenuhan '{$item['item_name']}' (" . self::fmt($qty) . ") melebihi sisa pesanan ("
                        . self::fmt($remaining) . " {$item['unit']})."
                    );
                }

                // (3) Kunci & alokasikan stok.
                foreach ($this->allocate($item, $qty, (int) $line['inventory_id']) as [$bucket, $take]) {
                    if ($docNumber === null) {
                        $docNumber = $this->stockOut->generateStockOutNumber($date);
                    }
                    $stockOutId = $this->stockOut->create([
                        'stock_out_number'           => $docNumber,
                        'document_id'                => $docId,
                        'inventory_id'               => (int) $bucket['id'],
                        'project_id'                 => null,
                        'destination_type'           => 'client',
                        'sales_invoice_id'           => null,
                        'sales_order_id'             => $orderId,
                        'sales_order_item_id'        => $lineId,
                        'sales_order_fulfillment_id' => $fulfillmentId,
                        'pic_name'                   => $picName,
                        'destination'                => $destination,
                        'qty'                        => $take,
                        'out_date'                   => $date,
                        'notes'                      => $notes,
                        'validation_status'          => StockOut::initialValidationStatus(null),
                        'created_by'                 => $userId,
                    ]);
                    if ($docId === null) {
                        $docId = $stockOutId;
                        $this->stockOut->updateById($stockOutId, ['document_id' => $docId]);
                    }
                    // Debit stok (menguncinya lagi + menolak bila melebihi stok) dgn reference_id SUDAH final.
                    $this->inventory->debitStock(
                        (int) $bucket['id'], $take, 'stock_out', $stockOutId, $date, $userId,
                        "Pemenuhan Order Penjualan {$order['order_number']} (PO {$order['po_number']})"
                    );
                    $rowCount++;
                    $qtyTotal += $take;
                }
            }

            $this->db->update('sales_order_fulfillments', ['stock_out_number' => $docNumber], 'id = :pk', ['pk' => $fulfillmentId]);

            if ($manageTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $remainingNow = (float) ($this->db->fetchOne(
            "SELECT COALESCE(SUM(GREATEST(soi.qty - COALESCE((SELECT SUM(so.qty) FROM stock_out so
                       WHERE so.sales_order_item_id = soi.id AND so.deleted_at IS NULL), 0), 0)), 0) AS r
               FROM sales_order_items soi WHERE soi.sales_order_id = :o",
            ['o' => $orderId]
        )['r'] ?? 0);

        return [
            'fulfillment_id'   => $fulfillmentId,
            'stock_out_number' => (string) $docNumber,
            'rows'             => $rowCount,
            'qty_total'        => round($qtyTotal, 2),
            'remaining'        => round($remainingNow, 2),
        ];
    }

    /**
     * Pilih baris inventory sumber & jumlah yang diambil dari masing-masing. Baris dikunci
     * (FOR UPDATE) sehingga angka qty_available yang dibaca adalah angka terkini.
     *
     * @return array<int, array{0:array,1:float}> [[barisInventory, jumlahDiambil], ...]
     */
    private function allocate(array $item, float $qty, int $preferredInventoryId): array
    {
        $buckets = $this->db->fetchAll(
            "SELECT * FROM inventory
              WHERE item_name = :n AND deleted_at IS NULL AND qty_available > 0
              ORDER BY (stock_scope = 'kantor') DESC, qty_available DESC, id ASC
              FOR UPDATE",
            ['n' => $item['master_name']]
        );
        $total = 0.0;
        foreach ($buckets as $b) {
            $total += (float) $b['qty_available'];
        }

        if ($preferredInventoryId > 0) {
            foreach ($buckets as $b) {
                if ((int) $b['id'] === $preferredInventoryId) {
                    if ((float) $b['qty_available'] + SalesOrder::EPS < $qty) {
                        throw new DomainException(
                            "Stok pada sumber yang dipilih untuk '{$item['item_name']}' tidak mencukupi: tersedia "
                            . self::fmt((float) $b['qty_available']) . " {$b['unit']}, diminta " . self::fmt($qty) . '. '
                            . 'Kurangi jumlah, atau pilih "Otomatis" agar stok diambil dari beberapa sumber.'
                        );
                    }
                    return [[$b, $qty]];
                }
            }
            throw new DomainException("Sumber stok yang dipilih untuk '{$item['item_name']}' tidak valid atau sudah habis.");
        }

        if ($total + SalesOrder::EPS < $qty) {
            $short = round($qty - $total, 2);
            throw new DomainException(
                "Stok barang tidak mencukupi. '{$item['item_name']}': pesanan diproses " . self::fmt($qty) . " {$item['unit']}, "
                . 'stok tersedia ' . self::fmt($total) . " {$item['unit']}, kekurangan " . self::fmt($short) . " {$item['unit']}."
            );
        }

        $out = [];
        $need = $qty;
        foreach ($buckets as $b) {
            if ($need <= SalesOrder::EPS) {
                break;
            }
            $take = round(min($need, (float) $b['qty_available']), 2);
            if ($take > 0) {
                $out[] = [$b, $take];
                $need = round($need - $take, 2);
            }
        }
        return $out;
    }

    /**
     * KOREKSI pemenuhan (Super Admin): stok dikembalikan lewat Inventory::restoreStock() (tercatat
     * di Kartu Stok sebagai penyesuaian), baris Pengeluaran Barang dipindah ke Tempat Sampah, dan
     * pemenuhan ditandai dibatalkan lengkap dengan alasan. Ditolak bila baris sudah masuk Surat Jalan
     * atau jumlahnya sudah ditagih (batalkan dokumen tersebut dulu) -- dokumen tidak boleh
     * mengklaim barang yang stoknya sudah dikembalikan.
     *
     * @throws DomainException
     */
    public function reverse(int $fulfillmentId, string $reason, int $userId): array
    {
        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $ful = $this->db->fetchOne("SELECT * FROM sales_order_fulfillments WHERE id = :id", ['id' => $fulfillmentId]);
            if (!$ful) {
                throw new DomainException('Pemenuhan tidak ditemukan.');
            }
            $order = $this->orders->lockForUpdate((int) $ful['sales_order_id']);
            $ful = $this->db->fetchOne("SELECT * FROM sales_order_fulfillments WHERE id = :id FOR UPDATE", ['id' => $fulfillmentId]);
            if ($ful['status'] !== 'aktif') {
                throw new DomainException('Pemenuhan ini sudah dikoreksi sebelumnya.');
            }

            $rows = $this->db->fetchAll(
                "SELECT so.*, dn.delivery_number
                   FROM stock_out so
                   LEFT JOIN delivery_notes dn ON dn.id = so.delivery_note_id AND dn.deleted_at IS NULL
                  WHERE so.sales_order_fulfillment_id = :f AND so.deleted_at IS NULL",
                ['f' => $fulfillmentId]
            );
            $perLine = [];
            foreach ($rows as $r) {
                if (!empty($r['delivery_number'])) {
                    throw new DomainException("Barang pada pemenuhan ini sudah masuk Surat Jalan {$r['delivery_number']}. Hapus Surat Jalan tersebut terlebih dahulu.");
                }
                $perLine[(int) $r['sales_order_item_id']] = ($perLine[(int) $r['sales_order_item_id']] ?? 0.0) + (float) $r['qty'];
            }
            foreach ($perLine as $lineId => $reverseQty) {
                $fulfilledAfter = $this->orders->fulfilledQty($lineId) - $reverseQty;
                $invoiced = $this->orders->invoicedQty($lineId);
                if ($invoiced > $fulfilledAfter + SalesOrder::EPS) {
                    $name = (string) ($this->db->fetchOne("SELECT item_name FROM sales_order_items WHERE id = :i", ['i' => $lineId])['item_name'] ?? '');
                    throw new DomainException("Barang '{$name}' sudah ditagih di Invoice Keluar melebihi jumlah yang tersisa setelah koreksi. Batalkan invoice terkait terlebih dahulu.");
                }
            }

            foreach ($rows as $r) {
                $this->inventory->restoreStock(
                    (int) $r['inventory_id'], (float) $r['qty'], 'stock_out', (int) $r['id'], $userId,
                    "Koreksi pemenuhan Order Penjualan {$order['order_number']}: {$reason}"
                );
                $this->stockOut->deleteById((int) $r['id']);
            }

            $this->db->update('sales_order_fulfillments', [
                'status'        => 'dibatalkan',
                'cancel_reason' => mb_substr($reason, 0, 255),
                'cancelled_at'  => date('Y-m-d H:i:s'),
                'cancelled_by'  => $userId,
            ], 'id = :pk', ['pk' => $fulfillmentId]);

            if ($manageTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return ['rows' => count($rows), 'stock_out_number' => (string) ($ful['stock_out_number'] ?? '')];
    }

    private static function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',') ?: '0';
    }
}
