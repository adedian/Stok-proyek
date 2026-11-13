<div class="sl-print-label">
    <?php foreach (['to' => 'Kepada', 'from' => 'Pengirim'] as $p => $title): ?>
        <div class="sl-print-box">
            <div class="fm-bold"><?= e($title) ?> :</div>
            <div class="fm-bold"><?= e($d[$p . '_name'] ?? '') ?></div>
            <?php if (!empty($d[$p . '_company'])): ?><div class="fm-bold"><?= e($d[$p . '_company']) ?></div><?php endif; ?>
            <div><?= e($d[$p . '_address'] ?? '') ?></div>
            <?php if (!empty($d[$p . '_city'])): ?><div><?= e($d[$p . '_city']) ?></div><?php endif; ?>
            <?php if (!empty($d[$p . '_postal_code'])): ?><div>Kode Pos <?= e($d[$p . '_postal_code']) ?></div><?php endif; ?>
            <?php if (!empty($d[$p . '_phone'])): ?><div>No. Telp <?= e($d[$p . '_phone']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
