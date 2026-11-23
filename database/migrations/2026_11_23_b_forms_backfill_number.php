<?php
/**
 * Menu Form: beri nomor dokumen pada form LAMA (dibuat sebelum penomoran otomatis, form_number masih NULL)
 * untuk jenis bernomor: Kwitansi (KWT.HME), Bon Sementara (BS.HME), Tanda Terima Dokumen (TD.HME),
 * Form Order Barang (PB.HME). Form Pengiriman Dokumen tidak bernomor.
 *
 * - Urutan pemberian nomor = urutan dibuat (created_at, id) per jenis, jadi form tertua dapat nomor terkecil.
 * - Bulan/tahun pada nomor = tanggal dokumen (form_date / order_date / request_date); Tanda Terima tak punya
 *   tanggal dokumen -> pakai tanggal dibuat. Counter ikut maju, jadi form baru lanjut dari nomor berikutnya.
 * - Form yang sudah masuk Tempat Sampah ikut diberi nomor (supaya kalau dipulihkan tidak tanpa nomor).
 *
 * Idempotent: hanya menyentuh baris yang form_number-nya NULL. Satu transaksi -- gagal = tidak ada yang berubah.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

// Dibungkus closure: bin/migrate.php me-require file ini di scope-nya sendiri (lihat 2026_11_14_d_forms.php).
(function () {
    $pdo = getPDO();
    $numberer = new DocumentNumber();

    // form_type => [doc_type counter, kunci setting kode, kode bawaan]
    $types = [
        'kwitansi'      => ['form_kwitansi',      'prefix_form_kwt', 'KWT.HME'],
        'bon_sementara' => ['form_bon_sementara', 'prefix_form_bs',  'BS.HME'],
        'tanda_terima'  => ['form_tanda_terima',  'prefix_form_td',  'TD.HME'],
        'order_form'    => ['form_order_barang',  'prefix_form_pb',  'PB.HME'],
    ];

    $upd = $pdo->prepare("UPDATE forms SET form_number = :n, updated_at = updated_at WHERE id = :id AND form_number IS NULL");
    $total = 0;
    $summary = [];

    $pdo->beginTransaction();
    try {
        foreach ($types as $formType => [$docType, $settingKey, $defaultCode]) {
            $sel = $pdo->prepare("SELECT id, data, created_at FROM forms WHERE form_type = :t AND form_number IS NULL ORDER BY created_at ASC, id ASC");
            $sel->execute(['t' => $formType]);
            $first = $last = null;
            $count = 0;
            foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $data = json_decode((string) $r['data'], true);
                $date = null;
                foreach (['form_date', 'order_date', 'request_date'] as $k) {
                    if (is_array($data) && !empty($data[$k]) && is_string($data[$k]) && strtotime($data[$k]) !== false) {
                        $date = $data[$k];
                        break;
                    }
                }
                $date = $date ?: substr((string) $r['created_at'], 0, 10);

                // next() ikut transaksi yang sedang berjalan (tidak membuka yang baru).
                $number = $numberer->next($docType, $settingKey, $date, $defaultCode);
                $upd->execute(['n' => $number, 'id' => $r['id']]);
                $first = $first ?? $number;
                $last = $number;
                $count++;
            }
            $total += $count;
            if ($count > 0) {
                $summary[] = "{$formType}: {$count} ({$first} .. {$last})";
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo "Form: {$total} form lama diberi nomor dokumen" . ($summary ? ' -- ' . implode('; ', $summary) : '') . "\n";
})();
