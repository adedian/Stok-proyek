<?php
/**
 * bin/fix_receipt_stock_project.php  --  PINDAHKAN STOK PENERIMAAN YANG TERTINGGAL DI PROJECT LAMA (CLI SAJA)
 * =========================================================================
 * Masalah: sebelum perbaikan PurchaseOrderController/OfflinePurchaseController::update(),
 * mengganti Project sebuah PO / Pembelian Offline SETELAH barangnya divalidasi masuk stok
 * tidak ikut memindahkan stoknya. Laporan Penerimaan sudah membaca project baru (dari PO),
 * tetapi stok tetap di bucket project lama -> Pengeluaran Barang memperingatkan
 * "bukan milik project ini".
 *
 * Tool ini mencari setiap penerimaan (dari PO / Pembelian Offline) yang stoknya masih
 * berada di project selain project PO/Pembelian saat ini, lalu memindahkannya lewat
 * Inventory::moveCredit() -- sama persis dengan yang kini dilakukan saat edit PO:
 * kartu stok project lama mendapat koreksi keluar, project baru mendapat stok masuk,
 * referensi penerimaan sama. Qty yang dipindah = qty bersih penerimaan itu di bucket lama.
 *
 * PEMAKAIAN
 *   php bin/fix_receipt_stock_project.php                 DRY-RUN: tampilkan rencana saja.
 *   php bin/fix_receipt_stock_project.php --apply --user=ade
 *       Terapkan dalam SATU transaksi (ada yang gagal -> semua dibatalkan).
 *
 * PENGAMAN: bila sebagian stok di project lama sudah dikeluarkan, baris itu menolak
 * (InsufficientStockException) dan SELURUH perbaikan dibatalkan.
 *
 * Exit code: 0 sukses | 1 argumen salah | 3 error
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/app/models/Inventory.php';

$apply = in_array('--apply', $argv, true);
$username = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--user=') === 0) {
        $username = substr($arg, 7);
    }
}

$pdo = getPDO();

$userId = null;
if ($apply) {
    if (!$username) {
        fwrite(STDERR, "--apply butuh --user=<username> (dicatat sebagai pembuat koreksi).\n");
        exit(1);
    }
    $st = $pdo->prepare('SELECT id FROM users WHERE username = :u AND deleted_at IS NULL');
    $st->execute(['u' => $username]);
    $userId = $st->fetchColumn();
    if (!$userId) {
        fwrite(STDERR, "User '{$username}' tidak ditemukan.\n");
        exit(1);
    }
    $userId = (int) $userId;
}

$rows = $pdo->query(
    "SELECT gr.id AS gr_id, gr.receipt_number, gr.stock_scope,
            COALESCE(po.po_number, op.purchase_number) AS source_number,
            COALESCE(po.project_id, op.project_id) AS now_project_id, pn.project_name AS now_project_name,
            i.project_id AS stock_project_id, ps.project_name AS stock_project_name,
            i.item_name, i.unit, SUM(st.qty) AS qty
     FROM stock_transactions st
     JOIN inventory i ON i.id = st.inventory_id
     JOIN goods_receipts gr ON gr.id = st.reference_id
     LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
     LEFT JOIN offline_purchases op ON op.id = gr.offline_purchase_id
     LEFT JOIN projects pn ON pn.id = COALESCE(po.project_id, op.project_id)
     LEFT JOIN projects ps ON ps.id = i.project_id
     WHERE st.reference_type IN ('goods_receipt', 'goods_receipt_validation')
       AND gr.deleted_at IS NULL
       AND (gr.purchase_order_id IS NOT NULL OR gr.offline_purchase_id IS NOT NULL)
       AND COALESCE(po.is_non_project, 0) = 0
       AND NOT (COALESCE(po.project_id, op.project_id) <=> i.project_id)
     GROUP BY gr.id, i.id
     HAVING qty > 0.005
     ORDER BY gr.id, i.item_name"
)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "Tidak ada stok penerimaan yang tertinggal di project lama.\n";
    exit(0);
}

echo ($apply ? 'TERAPKAN' : 'DRY-RUN') . ': ' . count($rows) . " baris stok akan dipindah\n";
foreach ($rows as $r) {
    printf(
        "  %s (%s) | %s %s %s | %s -> %s\n",
        $r['receipt_number'],
        $r['source_number'],
        $r['item_name'],
        rtrim(rtrim(number_format((float) $r['qty'], 2, '.', ''), '0'), '.'),
        $r['unit'],
        $r['stock_project_name'] ?? 'Kantor',
        $r['now_project_name'] ?? 'Kantor'
    );
}

if (!$apply) {
    echo "\nDry-run selesai. Jalankan dengan --apply --user=<username> untuk menerapkan.\n";
    exit(0);
}

$inventory = new Inventory();
try {
    $pdo->beginTransaction();
    foreach ($rows as $r) {
        $inventory->moveCredit(
            $r['item_name'],
            $r['unit'],
            $r['stock_project_id'] !== null ? (int) $r['stock_project_id'] : null,
            $r['now_project_id'] !== null ? (int) $r['now_project_id'] : null,
            (float) $r['qty'],
            'goods_receipt_validation',
            (int) $r['gr_id'],
            $userId,
            $r['stock_scope'],
            "Pindah project {$r['source_number']}: " . ($r['stock_project_name'] ?? 'Kantor') . ' -> ' . ($r['now_project_name'] ?? 'Kantor')
        );
    }
    $pdo->commit();
    echo "\nSelesai: " . count($rows) . " baris stok dipindah.\n";
    exit(0);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "\nDIBATALKAN, tidak ada yang diubah: " . $e->getMessage() . "\n");
    exit(3);
}
