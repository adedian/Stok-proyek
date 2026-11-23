<?php
/**
 * Partial: peringatan "PO ini sudah punya penerimaan" di form Tambah Penerimaan.
 * Variabel: $existingReceipts (GoodsReceiptItem::existingReceiptsForPo), $poItems (poItemsForReceipt).
 * Tidak menampilkan apa-apa bila PO belum punya penerimaan. Hanya peringatan -- penerimaan bertahap
 * yang sah tetap bisa disimpan; tujuannya mencegah input dobel (satu barang masuk dua kali).
 */
$existingReceipts = $existingReceipts ?? [];
if (empty($existingReceipts)) {
    return;
}
$poItems = $poItems ?? [];
$fullyReceived = !empty($poItems);
foreach ($poItems as $pi) {
    if ((float) $pi['qty_remaining'] > 0) {
        $fullyReceived = false;
        break;
    }
}
?>
<div class="alert <?= $fullyReceived ? 'alert-danger' : 'alert-warning' ?> mt-3 mb-0" role="alert" id="poDupWarning" data-full="<?= $fullyReceived ? '1' : '0' ?>">
    <div class="fw-semibold mb-1">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?php if ($fullyReceived): ?>
            Semua barang PO ini sudah tercatat diterima penuh. Pastikan ini bukan input dobel.
        <?php else: ?>
            PO ini sudah punya <?= count($existingReceipts) ?> penerimaan sebelumnya. Pastikan ini bukan input dobel.
        <?php endif; ?>
    </div>
    <ul class="mb-1 small">
        <?php foreach ($existingReceipts as $r): ?>
            <?php
            $validated = (int) $r['validated_count'];
            $total = (int) $r['item_count'];
            ?>
            <li>
                <a href="<?= BASE_URL ?>/index.php?module=goods_receipt&amp;action=detail&amp;id=<?= (int) $r['id'] ?>" target="_blank" rel="noopener" class="fw-semibold"><?= e($r['receipt_number']) ?></a>
                &mdash; <?= e(date('d/m/Y', strtotime($r['receipt_date']))) ?>,
                penerima <?= e($r['receiver_name'] ?: '-') ?>,
                diinput oleh <?= e($r['created_by_name'] ?: '-') ?>
                (<?= $validated >= $total ? 'sudah divalidasi' : 'menunggu validasi: ' . ($total - $validated) . ' dari ' . $total . ' item' ?>)
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="small">Kolom &ldquo;Sudah Diterima&rdquo; di bawah sudah menghitung penerimaan di atas, termasuk yang belum divalidasi.</div>
</div>
