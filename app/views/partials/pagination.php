<?php
/**
 * Partial pagination reusable untuk semua list Master Data.
 * Variabel yang dibutuhkan: $pagination (dari paginationInfo()), $baseQuery
 * (query string filter+sort TANPA 'page', boleh kosong string).
 */
if (($pagination['totalPages'] ?? 1) <= 1) {
    return;
}
$cur = $pagination['currentPage'];
$total = $pagination['totalPages'];
$qs = $baseQuery !== '' ? $baseQuery . '&' : '';
$start = max(1, $cur - 2);
$end = min($total, $cur + 2);
?>
<div class="d-flex justify-content-between align-items-center mt-3">
    <small class="text-muted">
        Halaman <?= $cur ?> dari <?= $total ?> (<?= (int) $pagination['totalRows'] ?> data)
    </small>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $cur <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= BASE_URL ?>/index.php?<?= $qs ?>page=<?= max(1, $cur - 1) ?>">&laquo;</a>
        </li>
        <?php for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?= $i === $cur ? 'active' : '' ?>">
                <a class="page-link" href="<?= BASE_URL ?>/index.php?<?= $qs ?>page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $cur >= $total ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= BASE_URL ?>/index.php?<?= $qs ?>page=<?= min($total, $cur + 1) ?>">&raquo;</a>
        </li>
    </ul>
</div>
