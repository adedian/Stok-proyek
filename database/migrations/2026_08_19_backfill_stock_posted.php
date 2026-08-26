<?php
/**
 * One-time data correction (run sekali lewat CLI, lalu boleh diarsipkan/dihapus).
 * Konteks: sebelum perbaikan ini, SEMUA goods_receipt_items dikreditkan ke inventory
 * secara tidak bersyarat saat penerimaan disimpan -- termasuk item yang belakangan
 * divalidasi "Salah Barang" atau yang belum pernah divalidasi sama sekali. Ini
 * menyesuaikan data existing supaya konsisten dengan aturan baru: stok hanya
 * dianggap valid (stock_posted_at terisi) kalau hasil validasinya sesuai/kurang/lebih.
 *
 * - comparison_status = 'salah_barang'  -> reverse kredit lama, stock_posted_at tetap NULL
 * - validated_at IS NULL (belum divalidasi) -> reverse kredit lama, stock_posted_at tetap NULL
 * - selain itu (sudah divalidasi & bukan salah_barang) -> stock_posted_at diisi (data stok
 *   di inventory SUDAH benar, cuma perlu ditandai supaya tidak diproses ulang / di-reverse)
 *
 * Jalankan: php database/migrations/2026_08_19_backfill_stock_posted.php
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$pdo = getPDO();

$items = $pdo->query(
    "SELECT gri.id, gri.qty_received, gri.comparison_status, gri.validated_at, gri.created_at,
            COALESCE(gri.actual_item_name, poi.item_name) AS item_name,
            COALESCE(gri.actual_unit, poi.unit) AS unit,
            po.project_id
     FROM goods_receipt_items gri
     JOIN purchase_order_items poi ON poi.id = gri.purchase_order_item_id
     JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
     JOIN purchase_orders po ON po.id = gr.purchase_order_id
     WHERE gri.stock_posted_at IS NULL"
)->fetchAll(PDO::FETCH_ASSOC);

echo "Ditemukan " . count($items) . " baris goods_receipt_items untuk diproses.\n\n";

$reversed = 0;
$posted = 0;

foreach ($items as $item) {
    $needsReverse = $item['comparison_status'] === 'salah_barang' || empty($item['validated_at']);

    $inv = $pdo->prepare(
        "SELECT * FROM inventory WHERE item_name = :item_name AND unit = :unit
         AND project_id = :project_id AND stock_scope = 'proyek' AND deleted_at IS NULL LIMIT 1"
    );
    $inv->execute([
        'item_name' => $item['item_name'],
        'unit' => $item['unit'],
        'project_id' => $item['project_id'],
    ]);
    $invRow = $inv->fetch(PDO::FETCH_ASSOC);

    if ($needsReverse) {
        if ($invRow) {
            $qtyBefore = (float) $invRow['qty_available'];
            $qtyAfter = max(0, $qtyBefore - (float) $item['qty_received']);

            $upd = $pdo->prepare("UPDATE inventory SET qty_available = :qty WHERE id = :id");
            $upd->execute(['qty' => $qtyAfter, 'id' => $invRow['id']]);

            $log = $pdo->prepare(
                "INSERT INTO stock_transactions
                    (inventory_id, transaction_type, reference_type, reference_id, qty, qty_before, qty_after, transaction_date, notes, created_by)
                 VALUES (:inv_id, 'adjustment', 'data_correction_audit_2026_08_19', :ref_id, :qty, :qty_before, :qty_after, NOW(), :notes, NULL)"
            );
            $log->execute([
                'inv_id' => $invRow['id'],
                'ref_id' => $item['id'],
                'qty' => -(float) $item['qty_received'],
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'notes' => 'Koreksi data audit 2026-08-19: item belum/tidak lolos validasi sempat kepalang dikredit sebagai stok valid.',
            ]);

            echo "REVERSE  gri#{$item['id']} '{$item['item_name']}' -{$item['qty_received']} {$item['unit']} (inv qty: {$qtyBefore} -> {$qtyAfter})\n";
            $reversed++;
        } else {
            echo "SKIP     gri#{$item['id']} '{$item['item_name']}' -- baris inventory tidak ditemukan (mungkin sudah dihapus manual).\n";
        }
        // stock_posted_at TETAP NULL -- akan dikreditkan ulang otomatis saat divalidasi via UI.
    } else {
        $mark = $pdo->prepare("UPDATE goods_receipt_items SET stock_posted_at = :ts WHERE id = :id");
        $mark->execute(['ts' => $item['validated_at'] ?: $item['created_at'], 'id' => $item['id']]);
        echo "POSTED   gri#{$item['id']} '{$item['item_name']}' -- ditandai sudah valid (stok tidak diubah).\n";
        $posted++;
    }
}

echo "\nSelesai. {$reversed} baris di-reverse (stok dikoreksi turun), {$posted} baris ditandai stock_posted_at (stok tidak berubah).\n";
