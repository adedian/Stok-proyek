<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= e($pageTitle) ?></h4>
        <?php if ($reportKey === 'inventory'): ?>
            <div class="text-muted small mt-1">
                Periode:
                <?= !empty($filters['dateFrom']) ? formatTanggal($filters['dateFrom']) : '(seluruh riwayat)' ?>
                &ndash;
                <?= !empty($filters['dateTo']) ? formatTanggal($filters['dateTo']) : 'Sekarang' ?>
            </div>
        <?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/report" class="btn btn-outline-secondary no-print">
        <i class="bi bi-arrow-left"></i> Daftar Laporan
    </a>
</div>

<div class="card border-0 shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/report/<?= e($reportKey) ?>" class="row g-2 align-items-end">

            <?php if (!empty($filterForm['date'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                    <input type="date" id="reportDateFrom" name="date_from" class="form-control form-control-sm"
                           value="<?= e($filters['dateFrom'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" id="reportDateTo" name="date_to" class="form-control form-control-sm" value="<?= e($filters['dateTo'] ?? '') ?>">
                </div>
                <?php if ($reportKey === 'inventory'): ?>
                    <div class="col-md-2 d-flex align-items-center">
                        <div class="form-check mt-4">
                            <input type="checkbox" class="form-check-input" id="reportAllData">
                            <label class="form-check-label small" for="reportAllData">Cetak Semua Data</label>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($filterForm['project'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Project</label>
                    <select name="project_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (string) ($filters['projectId'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['project_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterForm['status']) && is_array($filterForm['status'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($filterForm['status'] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterForm['stockStatus'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status Stok</label>
                    <select name="stock_filter" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="low" <?= ($filters['stockFilter'] ?? '') === 'low' ? 'selected' : '' ?>>Stok Minimum</option>
                        <option value="zero" <?= ($filters['stockFilter'] ?? '') === 'zero' ? 'selected' : '' ?>>Stok = 0</option>
                        <option value="nonzero" <?= ($filters['stockFilter'] ?? '') === 'nonzero' ? 'selected' : '' ?>>Stok &ne; 0</option>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterForm['itemStatus'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status Barang</label>
                    <select name="item_status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="active" <?= ($filters['itemStatus'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="discontinue" <?= ($filters['itemStatus'] ?? '') === 'discontinue' ? 'selected' : '' ?>>Discontinue</option>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterForm['stockType']) && is_array($filterForm['stockType'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Kategori</label>
                    <select name="stock_type" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($filterForm['stockType'] as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= ($filters['stockType'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Hanya menyaring tampilan; Cetak &amp; Export tetap semua kategori.</div>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterForm['priceMode'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Harga</label>
                    <select name="show_price" class="form-select form-select-sm">
                        <option value="1" <?= ($filters['showPrice'] ?? '1') !== '0' ? 'selected' : '' ?>>Tampilkan harga</option>
                        <option value="0" <?= ($filters['showPrice'] ?? '1') === '0' ? 'selected' : '' ?>>Tanpa harga</option>
                    </select>
                    <div class="form-text">Berlaku untuk Cetak &amp; Export (tabel layar tetap tanpa harga).</div>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterForm['user']) && is_array($filterForm['user'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($filterForm['user'] as $u): ?>
                            <option value="<?= (int) $u['id'] ?>" <?= (string) ($filters['userId'] ?? '') === (string) $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterForm['module']) && is_array($filterForm['module'])): ?>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Modul</label>
                    <select name="log_module" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <?php foreach ($filterForm['module'] as $mSlug => $mLabel): ?>
                            <?php // Kompat: kalau nilainya list biasa (bukan slug=>label), pakai nilainya utk dua-duanya.
                                  $optVal = is_int($mSlug) ? $mLabel : $mSlug; ?>
                            <option value="<?= e($optVal) ?>" <?= ($filters['module'] ?? '') === $optVal ? 'selected' : '' ?>><?= e($mLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterForm['keyword'])): ?>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Cari</label>
                    <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword'] ?? '') ?>">
                </div>
            <?php endif; ?>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/report/<?= e($reportKey) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<?php require ROOT_PATH . '/app/views/report/_table.php'; ?>

<?php if ($reportKey === 'inventory'): ?>
<script>
(function () {
    // "Cetak Semua Data": mengosongkan filter tanggal supaya saldo dihitung dari
    // SELURUH histori transaksi (bukan dipotong periode) -- backend (Inventory::
    // stockMutationReport dkk.) sudah mendukung ini kalau date_from/date_to kosong,
    // checkbox ini cuma memudahkan UI-nya + auto-submit ulang filter.
    var checkbox = document.getElementById('reportAllData');
    var dateFrom = document.getElementById('reportDateFrom');
    var dateTo = document.getElementById('reportDateTo');
    if (!checkbox || !dateFrom || !dateTo) return;

    function applyState() {
        dateFrom.disabled = checkbox.checked;
        dateTo.disabled = checkbox.checked;
    }

    if (!dateFrom.value && !dateTo.value) {
        checkbox.checked = true;
    }
    applyState();

    checkbox.addEventListener('change', function () {
        applyState();
        if (checkbox.checked) {
            // Centang -> langsung tampilkan semua data (kosongkan filter tanggal & submit).
            dateFrom.value = '';
            dateTo.value = '';
            checkbox.closest('form').submit();
        }
        // Uncheck -> HANYA aktifkan lagi field tanggal, JANGAN auto-submit di sini.
        // ROOT CAUSE bug lama: uncheck ikut auto-submit selagi field tanggal masih
        // kosong (user belum sempat mengetik apa-apa) -> halaman reload -> logic di
        // atas ("kalau field kosong, checkbox dipaksa checked lagi") langsung
        // menyalakan checkbox ini lagi -> user tidak pernah bisa benar-benar
        // meng-uncentang untuk memilih rentang tanggal sendiri.
    });
})();
</script>
<?php endif; ?>
