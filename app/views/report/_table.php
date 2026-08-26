<?php $isInventory = $reportKey === 'inventory'; $isPo = $reportKey === 'po'; ?>
<div class="d-flex justify-content-end align-items-center mb-3 no-print">
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
            <i class="bi bi-printer"></i> Cetak
        </button>
        <?php if ($isPo): ?>
            <a href="<?= BASE_URL ?>/index.php?module=report&action=exportPoDetail<?= $exportQuery ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export Detail Barang
            </a>
            <a href="<?= BASE_URL ?>/index.php?module=report&action=exportPoRecap<?= $exportQuery ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export Rekap Bayar
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/index.php?module=report&action=exportExcel&type=<?= e($reportKey) ?><?= $exportQuery ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
            </a>
        <?php endif; ?>
        <?php if ($isInventory): ?>
            <a href="<?= BASE_URL ?>/index.php?module=report&action=printStockDetail<?= $exportQuery ?>" id="btnCetakStockDetail" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Cetak Detail
            </a>
            <a href="<?= BASE_URL ?>/index.php?module=report&action=printStockRecap<?= $exportQuery ?>" id="btnCetakStockRecap" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Cetak Rekap
            </a>
        <?php elseif ($isPo): ?>
            <a href="<?= BASE_URL ?>/index.php?module=report&action=printPoDetail<?= $exportQuery ?>" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> PDF Detail Barang
            </a>
            <a href="<?= BASE_URL ?>/index.php?module=report&action=printPoRecap<?= $exportQuery ?>" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> PDF Rekap Bayar
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/index.php?module=report&action=exportPdf&type=<?= e($reportKey) ?><?= $exportQuery ?>" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isInventory && !empty($rows)): ?>
    <div class="alert alert-light border small no-print mb-3">
        <i class="bi bi-info-circle"></i>
        Centang barang tertentu untuk membatasi <strong>Cetak Detail</strong>/<strong>Cetak Rekap</strong> hanya ke data
        yang dipilih. Kalau tidak ada yang dicentang, cetak mengikuti filter di atas seperti biasa.
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php if ($isInventory): ?>
                            <th style="width: 36px;" class="no-print"></th>
                        <?php endif; ?>
                        <th style="width: 48px;">No</th>
                        <?php foreach ($columns as $col): ?>
                            <th class="<?= ($col['align'] ?? '') === 'end' ? 'text-end' : '' ?>"><?= e($col['label']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="<?= count($columns) + 1 + ($isInventory ? 1 : 0) ?>" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-bar-chart-line empty-icon"></i>
                                <div class="empty-title">Tidak ada data</div>
                                <div class="empty-desc mb-0">Tidak ada data yang cocok dengan filter laporan ini.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $rowIndex => $row): ?>
                        <tr>
                            <?php if ($isInventory): ?>
                                <td class="no-print">
                                    <input type="checkbox" class="form-check-input stock-row-check" name="ids[]" value="<?= (int) $row['id'] ?>">
                                </td>
                            <?php endif; ?>
                            <td><?= $rowIndex + 1 ?></td>
                            <?php foreach ($columns as $col): ?>
                                <td class="<?= ($col['align'] ?? '') === 'end' ? 'text-end' : '' ?>">
                                    <?= e(formatReportValue($row[$col['field']] ?? null, $col['format'] ?? 'text')) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($isInventory): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Pemilihan barang HANYA per baris (tidak ada lagi "Centang Semua" -- lihat
    // requirement penghapusan Select All). Kalau ada baris yang dicentang,
    // sisipkan ids[]=... ke link Cetak Detail/Rekap
    // sesaat sebelum navigasi -- backend (Inventory::listWithFilters) yang tetap
    // memvalidasi ID tsb, bukan cuma percaya nilai dari checkbox di frontend.
    // Tidak ada yang dicentang -> link jalan apa adanya (ikut filter aktif seperti biasa).
    function wireSelectedIdsLink(linkId) {
        var link = document.getElementById(linkId);
        if (!link) return;
        var baseHref = link.getAttribute('href');
        link.addEventListener('click', function () {
            var checked = Array.prototype.filter.call(
                document.querySelectorAll('.stock-row-check'),
                function (c) { return c.checked; }
            ).map(function (c) { return c.value; });

            if (checked.length === 0) {
                link.setAttribute('href', baseHref);
                return;
            }
            var params = checked.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
            var sep = baseHref.indexOf('?') === -1 ? '?' : '&';
            link.setAttribute('href', baseHref + sep + params);
        });
    }

    wireSelectedIdsLink('btnCetakStockDetail');
    wireSelectedIdsLink('btnCetakStockRecap');
});
</script>
<?php endif; ?>
