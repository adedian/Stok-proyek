<?php
/**
 * Menu Form: Form Pengiriman Dokumen kini bernomor (PD.HME) -- beri nomor pada form pengiriman LAMA
 * yang form_number-nya masih NULL. Pola sama dengan 2026_11_23_b: urut dibuat (created_at, id),
 * bulan/tahun = tanggal dibuat (form pengiriman tak punya tanggal dokumen), counter ikut maju,
 * yang di Tempat Sampah ikut diberi nomor. Idempotent, satu transaksi.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

(function () {
    $pdo = getPDO();
    $numberer = new DocumentNumber();
    [$settingKey, $defaultCode] = DocumentNumber::DOC_TYPE_CODE['form_pengiriman'];

    $upd = $pdo->prepare("UPDATE forms SET form_number = :n, updated_at = updated_at WHERE id = :id AND form_number IS NULL");
    $rows = $pdo->query("SELECT id, created_at FROM forms WHERE form_type = 'pengiriman' AND form_number IS NULL ORDER BY created_at ASC, id ASC")
        ->fetchAll(PDO::FETCH_ASSOC);

    $first = $last = null;
    $pdo->beginTransaction();
    try {
        foreach ($rows as $r) {
            $number = $numberer->next('form_pengiriman', $settingKey, substr((string) $r['created_at'], 0, 10), $defaultCode);
            $upd->execute(['n' => $number, 'id' => $r['id']]);
            $first = $first ?? $number;
            $last = $number;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo 'Form Pengiriman: ' . count($rows) . ' form lama diberi nomor' . ($first ? " ({$first} .. {$last})" : '') . "\n";
})();
