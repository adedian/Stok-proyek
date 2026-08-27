<?php
/**
 * ============================================================
 * REVISI 8 #14-#23 -- MIGRASI KODE BARANG LAMA KE MASTER KODE TERBARU
 *
 * Menyesuaikan items.item_code SEMUA barang existing supaya mengikuti
 * konfigurasi Master Kode (code_configs) yang benar-benar tersimpan --
 * satu baris config per kategori/stock_type: item_stok_proyek /
 * item_stok_lampu / item_inventory_kantor.
 *
 * AMAN untuk transaksi lama: audit struktur (2026-08-27) mengonfirmasi
 * items.item_code TIDAK PERNAH disimpan sebagai snapshot teks di tabel
 * transaksi manapun. purchase_order_items / goods_receipt_items /
 * offline_purchase_items / sales_invoice_items menyimpan item_id (FK ke
 * items.id); inventory/stock_opname dicocokkan lewat item_name. Kode
 * selalu di-JOIN live dari items.item_code (lihat PurchaseOrder::
 * listItemsForReport(), StockOpnameItem::reportRows(), Inventory::
 * listWithFilters()). Jadi meng-UPDATE items.item_code otomatis diikuti
 * SEMUA modul tanpa merusak relationship -- TIDAK ada replace teks di
 * seluruh database.
 *
 * Barang yang KODE-nya sudah sesuai prefix/format config kategorinya
 * DIBIARKAN (tidak di-renumber, tidak menutup gap dari barang terhapus) --
 * hanya barang yang kodenya TIDAK cocok konfigurasi yang diberi kode baru
 * dari counter (next_number) config-nya, dengan cek collision ke seluruh
 * items.item_code (aktif + soft-deleted, keunikan kode bersifat GLOBAL).
 *
 * PEMAKAIAN:
 *   php database/migrations/2026_08_27_item_code_migration.php
 *       -> DRY-RUN: tampilkan mapping + rencana, TIDAK mengubah apa pun.
 *   php database/migrations/2026_08_27_item_code_migration.php --apply
 *       -> Backup CSV -> BEGIN -> update kode -> sinkron next_number ->
 *          validasi tidak ada duplikat -> COMMIT (ROLLBACK bila error).
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

$apply = in_array('--apply', $argv, true);
$pdo = getPDO();

/* ---------- 1. AUDIT: konfigurasi Master Kode per kategori barang ---------- */
$stockTypes = ['stok_proyek', 'stok_lampu', 'inventory_kantor'];
$configs = [];
foreach ($stockTypes as $st) {
    $et = 'item_' . $st;
    $row = $pdo->prepare("SELECT * FROM code_configs WHERE entity_type = :et");
    $row->execute(['et' => $et]);
    $cfg = $row->fetch();
    $configs[$st] = $cfg ?: null;
}

echo "=== KONFIGURASI MASTER KODE BARANG (code_configs) ===\n";
foreach ($stockTypes as $st) {
    $c = $configs[$st];
    if ($c) {
        printf("  %-18s -> prefix=%-6s digit=%d next_number=%d status=%s\n",
            $st, $c['prefix'], $c['digit_length'], $c['next_number'], $c['status']);
    } else {
        printf("  %-18s -> (belum dikonfigurasi -- barang kategori ini akan di-SKIP)\n", $st);
    }
}
echo "\n";

/* ---------- 2. AUDIT: semua barang existing ---------- */
$items = $pdo->query(
    "SELECT i.id, i.item_code, i.item_name, i.stock_type, i.deleted_at,
            c.category_name
     FROM items i
     LEFT JOIN item_categories c ON c.id = i.category_id
     ORDER BY i.stock_type, i.id"
)->fetchAll();

/* Semua kode yang SUDAH terpakai (aktif + soft-deleted) -- keunikan GLOBAL. */
$usedCodes = [];
foreach ($items as $it) {
    $usedCodes[strtoupper($it['item_code'])] = true;
}

/* ---------- 3. MIGRATION PLAN: bangun mapping Kode Lama -> Kode Baru ---------- */
$seqByType = [];
foreach ($stockTypes as $st) {
    $seqByType[$st] = $configs[$st] ? (int) $configs[$st]['next_number'] : 1;
}

$plan = [];          // baris rencana untuk ditampilkan
$toUpdate = [];      // id => kode baru (hanya yang MIGRATE)
$maxSeqSeen = [];    // stock_type => sequence tertinggi yang dipakai (untuk sinkron counter)

foreach ($items as $it) {
    $st = $it['stock_type'];
    $cfg = $configs[$st] ?? null;
    $oldCode = $it['item_code'];
    $deletedTag = $it['deleted_at'] ? ' (terhapus)' : '';

    if (!$cfg || $cfg['status'] !== 'active') {
        $plan[] = [$oldCode, $it['item_name'] . $deletedTag, $it['category_name'] ?? '-', '-', $oldCode, 'SKIP (config kategori belum ada/nonaktif)'];
        continue;
    }

    $prefix = strtoupper($cfg['prefix']);
    $digit  = (int) $cfg['digit_length'];

    // Sudah sesuai prefix kategori? -> biarkan, tapi catat sequence-nya untuk sinkron counter.
    if (preg_match('/^' . preg_quote($prefix, '/') . '-0*(\d+)$/i', $oldCode, $m)) {
        $seq = (int) $m[1];
        $maxSeqSeen[$st] = max($maxSeqSeen[$st] ?? 0, $seq);
        $plan[] = [$oldCode, $it['item_name'] . $deletedTag, $it['category_name'] ?? '-', $prefix, $oldCode, 'OK (sudah sesuai)'];
        continue;
    }

    // Perlu kode baru dari counter config, cek collision GLOBAL ke items.item_code.
    $newCode = null;
    for ($guard = 0; $guard < 100000; $guard++) {
        $candidate = $prefix . '-' . str_pad((string) $seqByType[$st], $digit, '0', STR_PAD_LEFT);
        $seqByType[$st]++;
        if (!isset($usedCodes[strtoupper($candidate)])) {
            $newCode = $candidate;
            break;
        }
    }

    if ($newCode === null) {
        $plan[] = [$oldCode, $it['item_name'] . $deletedTag, $it['category_name'] ?? '-', $prefix, '-', 'CONFLICT (tidak ada kode bebas)'];
        continue;
    }

    $usedCodes[strtoupper($newCode)] = true;
    $toUpdate[$it['id']] = $newCode;
    if (preg_match('/-0*(\d+)$/', $newCode, $mm)) {
        $maxSeqSeen[$st] = max($maxSeqSeen[$st] ?? 0, (int) $mm[1]);
    }
    $plan[] = [$oldCode, $it['item_name'] . $deletedTag, $it['category_name'] ?? '-', $prefix, $newCode, 'MIGRATE'];
}

/* ---------- 4. Tampilkan MIGRATION MAPPING ---------- */
echo "=== MIGRATION MAPPING (Kode Lama -> Nama -> Kategori -> Prefix -> Kode Baru -> Status) ===\n";
$fmt = "  %-12s | %-40s | %-18s | %-6s | %-12s | %s\n";
printf($fmt, 'KODE LAMA', 'NAMA BARANG', 'KATEGORI', 'PREFIX', 'KODE BARU', 'STATUS');
printf($fmt, str_repeat('-', 12), str_repeat('-', 40), str_repeat('-', 18), str_repeat('-', 6), str_repeat('-', 12), str_repeat('-', 20));
$counts = ['OK' => 0, 'MIGRATE' => 0, 'CONFLICT' => 0, 'SKIP' => 0];
foreach ($plan as $p) {
    printf($fmt, $p[0], mb_strimwidth($p[1], 0, 40), mb_strimwidth($p[2], 0, 18), $p[3], $p[4], $p[5]);
    if (str_starts_with($p[5], 'OK')) $counts['OK']++;
    elseif (str_starts_with($p[5], 'MIGRATE')) $counts['MIGRATE']++;
    elseif (str_starts_with($p[5], 'CONFLICT')) $counts['CONFLICT']++;
    else $counts['SKIP']++;
}
echo "\n";
printf("RINGKASAN: %d sudah sesuai, %d akan dimigrasi, %d konflik, %d di-skip. Total %d barang.\n\n",
    $counts['OK'], $counts['MIGRATE'], $counts['CONFLICT'], $counts['SKIP'], count($plan));

/* ---------- 5. Rencana sinkron sequence Master Kode ---------- */
echo "=== RENCANA SINKRON SEQUENCE (code_configs.next_number, hanya dinaikkan) ===\n";
$seqUpdates = [];
foreach ($stockTypes as $st) {
    $cfg = $configs[$st] ?? null;
    if (!$cfg) continue;
    $target = max((int) $cfg['next_number'], ($maxSeqSeen[$st] ?? 0) + 1);
    if ($target !== (int) $cfg['next_number']) {
        $seqUpdates['item_' . $st] = $target;
        printf("  %-22s : %d -> %d\n", 'item_' . $st, $cfg['next_number'], $target);
    } else {
        printf("  %-22s : %d (sudah sinkron)\n", 'item_' . $st, $cfg['next_number']);
    }
}
echo "\n";

if ($counts['CONFLICT'] > 0) {
    echo "!! ADA CONFLICT -- perbaiki dulu (rename manual barang yang bentrok) sebelum --apply. Berhenti.\n";
    exit(1);
}

if (!$apply) {
    echo ">> DRY-RUN selesai. Tidak ada perubahan. Jalankan ulang dengan --apply untuk menerapkan.\n";
    exit(0);
}

/* ---------- 6. APPLY: backup -> transaction -> update -> validasi -> commit ---------- */
if (empty($toUpdate) && empty($seqUpdates)) {
    echo ">> Tidak ada yang perlu diubah. Database sudah konsisten dengan Master Kode. Selesai.\n";
    exit(0);
}

@mkdir(BACKUP_PATH, 0775, true);
$backupFile = BACKUP_PATH . '/item_code_backup_' . date('Ymd_His') . '.csv';
$fh = fopen($backupFile, 'w');
fputcsv($fh, ['item_id', 'item_code_lama', 'stock_type', 'deleted_at']);
foreach ($items as $it) {
    fputcsv($fh, [$it['id'], $it['item_code'], $it['stock_type'], $it['deleted_at']]);
}
fclose($fh);
echo "Backup kode lama: {$backupFile}\n";

try {
    $pdo->beginTransaction();

    $upd = $pdo->prepare(
        "UPDATE items SET item_code = :new, updated_at = NOW() WHERE id = :id AND item_code = :old_guard"
    );
    $check = $pdo->prepare("SELECT COUNT(*) FROM items WHERE UPPER(item_code) = UPPER(:c) AND id <> :id");

    $applied = 0;
    foreach ($toUpdate as $id => $newCode) {
        // guard: kode target masih bebas?
        $check->execute(['c' => $newCode, 'id' => $id]);
        if ((int) $check->fetchColumn() > 0) {
            throw new RuntimeException("Kode target {$newCode} ternyata sudah dipakai barang lain (id {$id}).");
        }
        $old = null;
        foreach ($items as $it) {
            if ((int) $it['id'] === (int) $id) { $old = $it['item_code']; break; }
        }
        $upd->execute(['new' => $newCode, 'id' => $id, 'old_guard' => $old]);
        if ($upd->rowCount() !== 1) {
            throw new RuntimeException("Baris items id {$id} tidak ter-update (kode berubah di tengah jalan?).");
        }
        $applied++;
    }

    // Sinkron counter Master Kode (raise-only).
    $seqStmt = $pdo->prepare("UPDATE code_configs SET next_number = :n, updated_at = NOW() WHERE entity_type = :et");
    foreach ($seqUpdates as $et => $n) {
        $seqStmt->execute(['n' => $n, 'et' => $et]);
    }

    // Validasi: tidak boleh ada item_code duplikat.
    $dupe = $pdo->query(
        "SELECT item_code, COUNT(*) c FROM items GROUP BY item_code HAVING c > 1"
    )->fetchAll();
    if ($dupe) {
        throw new RuntimeException('Terdeteksi item_code duplikat setelah update: ' . json_encode($dupe));
    }

    $pdo->commit();
    echo ">> APPLY sukses. {$applied} kode barang dimigrasi, " . count($seqUpdates) . " counter Master Kode disinkron.\n";
    echo ">> Semua relationship (PO/GR/Validasi/Inventory/Stock Transaction/Opname/Invoice) tetap utuh -- via item_id, bukan teks kode.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "!! ERROR -- ROLLBACK. Tidak ada perubahan tersimpan.\n";
    echo '!! ' . $e->getMessage() . "\n";
    echo "!! Restore manual bila perlu dari: {$backupFile}\n";
    exit(1);
}
