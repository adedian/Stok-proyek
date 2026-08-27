<?php
/**
 * ============================================================
 * MIGRASI NOMOR DOKUMEN LAMA -> FORMAT BARU "001/PO.HME/VIII/2026"
 *
 * Semua modul SUDAH generate nomor baru lewat DocumentNumber::next()
 * (urut/kode/bulan-romawi/tahun, reset per tahun, tabel
 * document_number_counters). Skrip ini menyesuaikan dokumen LAMA yang masih
 * berformat "PO/2026/08/0016" dkk ke format baru itu.
 *
 * AMAN untuk relasi: audit skema (2026-08-27) memastikan setiap nomor dokumen
 * hanya ada di SATU kolom (UNIQUE) di tabel pemiliknya. TIDAK ADA tabel anak
 * yang menyimpan salinan nomor dokumen lain sebagai teks -- semua rujukan
 * antar-dokumen lewat *_id (FK). Kolom pencarian pakai LIKE (ikut nilai baru).
 * Jadi UPDATE kolom nomor di tabel pemilik otomatis diikuti semua tampilan/
 * laporan/cetak tanpa replace teks di seluruh DB.
 *
 * ATURAN (keputusan user 2026-08-27):
 *  - HANYA dokumen berformat LAMA yang diganti. Dokumen yang SUDAH berformat
 *    baru & valid DIBIARKAN (nomor & posisi urutnya) supaya nomor yang sudah
 *    terbit/terkirim ke klien (Invoice/Tanda Terima) tidak berubah.
 *  - Baris yang sudah dihapus (Tempat Sampah) IKUT dikonversi kalau formatnya
 *    lama -- supaya konsisten kalau di-restore/dilihat. Nomor urut baris apa
 *    pun (aktif/terhapus, lama/baru) tetap dihitung supaya tidak bentrok.
 *  - Dokumen lama diberi nomor urut TERENDAH yang masih bebas untuk
 *    (jenis dokumen, tahun) tsb, diproses kronologis (tanggal, id).
 *  - Bulan romawi & tahun diambil dari TANGGAL dokumen (sama seperti
 *    DocumentNumber::next()). Kode (PO.HME dst) dari system_settings LIVE.
 *  - Nomor "004/INV.HME//2026" (bulan kosong, bug lama) -> bulan diisi ulang
 *    dari tanggal, nomor urut dipertahankan.
 *  - Bentrok (nomor target sudah dipakai baris lain) -> baris dilewati &
 *    dilaporkan, TIDAK di-overwrite.
 *  - document_number_counters.next_number di-set = (urut tertinggi setelah
 *    migrasi) + 1 per (doc_type, tahun) supaya dokumen berikutnya lanjut
 *    tanpa gap/tabrakan.
 *
 * PEMAKAIAN:
 *   php database/migrations/2026_08_27_document_number_migration.php
 *       -> DRY-RUN (tampilkan mapping, tidak mengubah apa pun)
 *   php database/migrations/2026_08_27_document_number_migration.php --apply
 *       -> backup CSV -> transaction -> update -> sync counter -> commit/rollback
 * ============================================================
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

$apply = in_array('--apply', $argv, true);
$pdo = getPDO();

$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
function code_for(array $settings, string $key, string $fallback): string
{
    $v = trim((string) ($settings[$key] ?? ''));
    return $v !== '' ? $v : $fallback;
}

/* ---------- Definisi tiap kelompok dokumen ---------- */
$groups = [
    ['label' => 'Purchase Order',            'table' => 'purchase_orders',   'num' => 'po_number',        'date' => 'po_date',       'doc_type' => 'purchase_order',      'code' => code_for($settings, 'prefix_po',      'PO.HME')],
    ['label' => 'Penerimaan Barang',         'table' => 'goods_receipts',    'num' => 'receipt_number',   'date' => 'receipt_date',   'doc_type' => 'goods_receipt',       'code' => code_for($settings, 'prefix_gr',      'LPB.HME')],
    ['label' => 'Stok Opname',               'table' => 'stock_opname',      'num' => 'opname_number',    'date' => 'opname_date',    'doc_type' => 'stock_opname',        'code' => code_for($settings, 'prefix_opn',     'SO.HME')],
    ['label' => 'Pengeluaran Barang',        'table' => 'stock_out',         'num' => 'stock_out_number', 'date' => 'out_date',       'doc_type' => 'stock_out',           'code' => code_for($settings, 'prefix_sto',     'LBK.HME')],
    ['label' => 'Pembelian Offline',         'table' => 'offline_purchases', 'num' => 'purchase_number',  'date' => 'purchase_date',  'doc_type' => 'offline_purchase',    'code' => code_for($settings, 'prefix_off',     'LPB-OFF.HME')],
    ['label' => 'Invoice Keluar - Project',  'table' => 'sales_invoices',    'num' => 'invoice_number',   'date' => 'invoice_date',   'doc_type' => 'sales_invoice',       'code' => code_for($settings, 'prefix_sls',     'INV.HME'), 'where' => "invoice_type = 'project'"],
    ['label' => 'Invoice Keluar - Lampu',    'table' => 'sales_invoices',    'num' => 'invoice_number',   'date' => 'invoice_date',   'doc_type' => 'sales_invoice_lampu', 'code' => code_for($settings, 'prefix_fkt',     'FKT.HME'), 'where' => "invoice_type = 'lampu'"],
    ['label' => 'Surat Jalan',               'table' => 'delivery_notes',    'num' => 'delivery_number',  'date' => 'delivery_date',  'doc_type' => 'delivery_note',       'code' => code_for($settings, 'prefix_sj',      'SJ.HME')],
    ['label' => 'Tanda Terima',              'table' => 'collection_receipts','num' => 'receipt_number',   'date' => 'receipt_date',   'doc_type' => 'collection_receipt',  'code' => code_for($settings, 'prefix_tt',      'TT.HME')],
    ['label' => 'Pembayaran - Bank',         'table' => 'payments',          'num' => 'payment_number',   'date' => 'payment_date',   'doc_type' => 'payment_bk',          'code' => code_for($settings, 'prefix_pay_bk',  'BK.HME'),  'where' => "funding_source = 'bank'"],
    ['label' => 'Pembayaran - Kas Kecil',    'table' => 'payments',          'num' => 'payment_number',   'date' => 'payment_date',   'doc_type' => 'payment_kk',          'code' => code_for($settings, 'prefix_pay_kk',  'KK.HME'),  'where' => "funding_source = 'kas_kecil'"],
    ['label' => 'Pembayaran - Kas Project',  'table' => 'payments',          'num' => 'payment_number',   'date' => 'payment_date',   'doc_type' => 'payment_kkp',         'code' => code_for($settings, 'prefix_pay_kkp', 'KKP.HME'), 'where' => "funding_source = 'kas_project'"],
];

const ROMAN_ALT = '(XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)';

/** Sudah berformat baru & lengkap? "NNN/CODE/ROMAN/YYYY". */
function parse_new_format(string $number): ?array
{
    if (preg_match('#^(\d{1,4})/(.+)/' . ROMAN_ALT . '/(\d{4})$#', trim($number), $m)) {
        return ['seq' => (int) $m[1], 'code' => $m[2], 'roman' => $m[3], 'year' => (int) $m[4]];
    }
    return null;
}

/** Format baru TAPI bagian bulan kosong/tidak valid (bug lama): "004/INV.HME//2026". */
function parse_broken_new_format(string $number): ?array
{
    if (preg_match('#^(\d{1,4})/(.+?)/([^/]*)/(\d{4})$#', trim($number), $m)) {
        // hanya kalau bagian bulan BUKAN romawi valid (kalau valid sudah kena parse_new_format)
        if (!preg_match('#^' . ROMAN_ALT . '$#', $m[3])) {
            return ['seq' => (int) $m[1], 'code' => $m[2], 'raw_month' => $m[3], 'year' => (int) $m[4]];
        }
    }
    return null;
}

$allPlan = [];
$updates = [];         // [table, numCol, id, old, new, deletedFlag]
$counterTargets = [];  // "doc_type|year" => maxSeq
$conflicts = 0;
$migrateCount = 0;

foreach ($groups as $g) {
    $where = isset($g['where']) ? "WHERE {$g['where']}" : '';
    $rows = $pdo->query(
        "SELECT id, `{$g['num']}` AS num, `{$g['date']}` AS dt, deleted_at
         FROM `{$g['table']}` {$where}
         ORDER BY `{$g['date']}` ASC, id ASC"
    )->fetchAll();

    $usedSeqByYear = [];    // year => [seq => true]  (dari baris yang format-nya valid & dibiarkan)
    $rowsToMigrate = [];

    foreach ($rows as $r) {
        $ts = strtotime($r['dt']);
        $y = (int) date('Y', $ts);
        $roman = DocumentNumber::romanMonth((int) date('n', $ts));
        $parsed = parse_new_format($r['num']);

        if ($parsed && $parsed['year'] === $y && $parsed['code'] === $g['code'] && $parsed['roman'] === $roman) {
            // valid & sinkron -> BIARKAN
            $usedSeqByYear[$y][$parsed['seq']] = true;
            $counterTargets["{$g['doc_type']}|{$y}"] = max($counterTargets["{$g['doc_type']}|{$y}"] ?? 0, $parsed['seq']);
            $allPlan[] = [$g['label'], $r['num'], $r['num'], $r['deleted_at'] ? 'OK (baru, di Trash)' : 'OK (sudah format baru)'];
            continue;
        }

        $broken = parse_broken_new_format($r['num']);
        if ($broken && $broken['year'] === $y && $broken['code'] === $g['code']) {
            // format baru tapi bulan rusak -> perbaiki bulan, PERTAHANKAN nomor urut
            $rowsToMigrate[] = ['id' => $r['id'], 'num' => $r['num'], 'y' => $y, 'roman' => $roman, 'keepSeq' => $broken['seq'], 'deleted' => (bool) $r['deleted_at'], 'reason' => 'PERBAIKI (bulan kosong)'];
            continue;
        }

        // format lama sepenuhnya
        $rowsToMigrate[] = ['id' => $r['id'], 'num' => $r['num'], 'y' => $y, 'roman' => $roman, 'keepSeq' => null, 'deleted' => (bool) $r['deleted_at'], 'reason' => 'MIGRASI'];
    }

    // reserve dulu semua keepSeq (bugfix rows) supaya tidak dipakai baris lain
    foreach ($rowsToMigrate as $r) {
        if ($r['keepSeq'] !== null) {
            $usedSeqByYear[$r['y']][$r['keepSeq']] = true;
        }
    }

    $nextFreeByYear = [];
    foreach ($rowsToMigrate as $r) {
        $y = $r['y'];
        if ($r['keepSeq'] !== null) {
            $seq = $r['keepSeq'];
        } else {
            $nf = $nextFreeByYear[$y] ?? 1;
            while (!empty($usedSeqByYear[$y][$nf])) {
                $nf++;
            }
            $usedSeqByYear[$y][$nf] = true;
            $nextFreeByYear[$y] = $nf + 1;
            $seq = $nf;
        }

        $newNum = str_pad((string) $seq, 3, '0', STR_PAD_LEFT) . '/' . $g['code'] . '/' . $r['roman'] . '/' . $y;
        $counterTargets["{$g['doc_type']}|{$y}"] = max($counterTargets["{$g['doc_type']}|{$y}"] ?? 0, $seq);

        if ($newNum === $r['num']) {
            $allPlan[] = [$g['label'], $r['num'], $newNum, 'OK (tidak berubah)'];
            continue;
        }

        $chk = $pdo->prepare("SELECT COUNT(*) FROM `{$g['table']}` WHERE `{$g['num']}` = :n AND id <> :id");
        $chk->execute(['n' => $newNum, 'id' => $r['id']]);
        if ((int) $chk->fetchColumn() > 0) {
            $allPlan[] = [$g['label'], $r['num'], $newNum, 'BENTROK -- dilewati'];
            $conflicts++;
            continue;
        }

        $allPlan[] = [$g['label'], $r['num'] . ($r['deleted'] ? ' [Trash]' : ''), $newNum, $r['reason']];
        $updates[] = [$g['table'], $g['num'], $r['id'], $r['num'], $newNum];
        $migrateCount++;
    }
}

/* ---------- Tampilkan mapping ---------- */
echo "=== MAPPING NOMOR DOKUMEN (Lama -> Baru) ===\n";
$fmt = "  %-26s | %-28s | %-28s | %s\n";
printf($fmt, 'JENIS', 'NOMOR LAMA', 'NOMOR BARU', 'STATUS');
printf($fmt, str_repeat('-', 26), str_repeat('-', 28), str_repeat('-', 28), str_repeat('-', 22));
foreach ($allPlan as $p) {
    printf($fmt, $p[0], $p[1], $p[2], $p[3]);
}
echo "\n";
printf("RINGKASAN: %d dokumen diubah, %d bentrok/dilewati.\n\n", $migrateCount, $conflicts);

echo "=== TARGET COUNTER (document_number_counters.next_number) ===\n";
ksort($counterTargets);
foreach ($counterTargets as $k => $maxSeq) {
    [$dt, $y] = explode('|', $k);
    printf("  %-22s %s : next_number -> %d\n", $dt, $y, $maxSeq + 1);
}
echo "\n";

if ($conflicts > 0) {
    echo "!! Ada bentrok -- selesaikan manual dulu sebelum --apply. Berhenti.\n";
    exit(1);
}
if (!$apply) {
    echo ">> DRY-RUN selesai. Tidak ada perubahan. Jalankan ulang dengan --apply.\n";
    exit(0);
}
if (empty($updates)) {
    echo ">> Tidak ada dokumen lama. Semua sudah format baru. Selesai.\n";
    exit(0);
}

/* ---------- APPLY ---------- */
@mkdir(BACKUP_PATH, 0775, true);
$backup = BACKUP_PATH . '/document_number_backup_' . date('Ymd_His') . '.csv';
$fh = fopen($backup, 'w');
fputcsv($fh, ['table', 'num_col', 'id', 'nomor_lama', 'nomor_baru']);
foreach ($updates as $u) {
    fputcsv($fh, $u);
}
fclose($fh);
echo "Backup: {$backup}\n";

try {
    $pdo->beginTransaction();

    foreach ($updates as [$table, $numCol, $id, $old, $new]) {
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$numCol}` = :n AND id <> :id");
        $chk->execute(['n' => $new, 'id' => $id]);
        if ((int) $chk->fetchColumn() > 0) {
            throw new RuntimeException("Nomor {$new} sudah dipakai baris lain di {$table}.");
        }
        $upd = $pdo->prepare("UPDATE `{$table}` SET `{$numCol}` = :new, updated_at = NOW() WHERE id = :id AND `{$numCol}` = :guard");
        $upd->execute(['new' => $new, 'id' => $id, 'guard' => $old]);
        if ($upd->rowCount() !== 1) {
            throw new RuntimeException("Baris {$table}.id={$id} tidak ter-update (nomor berubah di tengah jalan?).");
        }
    }

    foreach ($counterTargets as $k => $maxSeq) {
        [$docType, $year] = explode('|', $k);
        $target = $maxSeq + 1;
        $row = $pdo->prepare("SELECT id FROM document_number_counters WHERE doc_type = :t AND year = :y");
        $row->execute(['t' => $docType, 'y' => $year]);
        $existing = $row->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE document_number_counters SET next_number = :n, updated_at = NOW() WHERE id = :id")
                ->execute(['n' => $target, 'id' => $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO document_number_counters (doc_type, year, next_number) VALUES (:t, :y, :n)")
                ->execute(['t' => $docType, 'y' => $year, 'n' => $target]);
        }
    }

    // Validasi: tidak ada nomor duplikat di kolom yang disentuh.
    $touched = [];
    foreach ($updates as [$table, $numCol]) {
        $touched["{$table}|{$numCol}"] = true;
    }
    foreach (array_keys($touched) as $tk) {
        [$table, $numCol] = explode('|', $tk);
        $d = $pdo->query("SELECT `{$numCol}` v, COUNT(*) c FROM `{$table}` GROUP BY `{$numCol}` HAVING c > 1")->fetchAll();
        if ($d) {
            throw new RuntimeException("Duplikat {$numCol} di {$table}: " . json_encode($d));
        }
    }

    $pdo->commit();
    echo ">> APPLY sukses. " . count($updates) . " nomor dokumen dimigrasi, " . count($counterTargets) . " counter disinkron.\n";
    echo ">> Relasi PO/GR/Pembayaran/Invoice/Surat Jalan/Tanda Terima tetap utuh (via *_id, bukan teks).\n";
    echo ">> Catatan: deskripsi di activity_logs (audit trail) sengaja TIDAK diubah -- itu catatan historis.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "!! ERROR -- ROLLBACK. Tidak ada perubahan tersimpan.\n!! " . $e->getMessage() . "\n!! Backup: {$backup}\n";
    exit(1);
}
