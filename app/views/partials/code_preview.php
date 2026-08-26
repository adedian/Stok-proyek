<?php
/**
 * Partial: info kode otomatis di form Tambah {Entity}.
 * Variabel wajib: $codeConfig (array dari CodeConfig::getConfig(), atau null kalau
 * belum dikonfigurasi), $codeEntityType (mis. 'item'), $codeEntityLabel (mis. 'Barang').
 */
?>
<?php if ($codeConfig === null): ?>
    <div class="alert alert-warning py-2 mb-0">
        <i class="bi bi-exclamation-triangle"></i>
        Prefix kode <?= e($codeEntityLabel) ?> belum dikonfigurasi.
        Silakan konfigurasi melalui
        <a href="<?= BASE_URL ?>/index.php?module=master_kode&action=group&type=<?= e($codeEntityType) ?>" class="alert-link">Master Kode &raquo; <?= e($codeEntityLabel) ?></a>.
    </div>
<?php else: ?>
    <small class="text-muted">
        Kode akan dibuat otomatis:
        <strong><?= e($codeConfig['prefix'] . '-' . str_pad((string) $codeConfig['next_number'], (int) $codeConfig['digit_length'], '0', STR_PAD_LEFT)) ?></strong>
    </small>
<?php endif; ?>
