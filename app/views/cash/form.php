<?php
/** @var string $mode @var array|null $cash @var array $items @var array $categories @var array $picOptions */
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$val = static fn(string $k, $d = '') => e($cash[$k] ?? $d);
$curPic = $cash['pic'] ?? '';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Kas</h4>
    <a href="<?= BASE_URL ?>/index.php?module=cash" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=<?= $actionUrl ?>" id="cashForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $cash['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" class="form-control"
                           value="<?= $val('trx_date', date('Y-m-d')) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">PIC <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="pic" id="cash_pic" class="form-select" required>
                            <option value="">-- Pilih PIC --</option>
                            <?php foreach ($picOptions as $p): ?>
                                <option value="<?= e($p) ?>" <?= $curPic === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddPic" title="Tambah PIC Cepat">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="form-text">Sumber: Master Data &rarr; PIC Mapping.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">No Bukti <span class="text-danger">*</span></label>
                    <input type="text" name="no_bukti" class="form-control" value="<?= $val('no_bukti') ?>"
                           placeholder="mis. KB-001" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Mutasi <span class="text-danger">*</span></label>
                    <select name="mutasi" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <option value="masuk"  <?= ($cash['mutasi'] ?? '') === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                        <option value="keluar" <?= ($cash['mutasi'] ?? '') === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= (int) ($cash['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Rincian (Uraian / Qty / Satuan)</h6>
                <button type="button" id="btnAddItem" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Tambah Barang
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="itemTable">
                    <thead class="table-light">
                        <tr>
                            <th>Uraian</th>
                            <th>Qty</th>
                            <th>Satuan (Rp)</th>
                            <th class="text-end">Jumlah</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="itemTableBody">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <?php include ROOT_PATH . '/app/views/cash/_item_row.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php $item = null; include ROOT_PATH . '/app/views/cash/_item_row.php'; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Total Nominal</td>
                            <td class="text-end fw-bold" id="grandTotal">Rp 0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/index.php?module=cash" class="btn btn-light border">Batal</a>
    </div>
</form>

<?php require ROOT_PATH . '/app/views/partials/quick_add_pic_modal.php'; ?>

<script>
(function () {
    const tableBody = document.getElementById('itemTableBody');
    const btnAddItem = document.getElementById('btnAddItem');
    const grandTotalEl = document.getElementById('grandTotal');
    let rowIndex = tableBody.querySelectorAll('.item-row').length;

    function formatRupiah(num) {
        return 'Rp ' + Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function recalcRow(row) {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat((row.querySelector('.price-input').value || '').replace(/,/g, '')) || 0;
        const subtotal = qty * price;
        row.querySelector('.subtotal-cell').textContent = formatRupiah(subtotal);
        return subtotal;
    }
    function recalcAll() {
        let total = 0;
        tableBody.querySelectorAll('.item-row').forEach(function (row) { total += recalcRow(row); });
        grandTotalEl.textContent = formatRupiah(total);
    }

    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
            recalcAll();
        }
    });
    tableBody.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.btn-remove-row');
        if (removeBtn) {
            if (tableBody.querySelectorAll('.item-row').length <= 1) {
                alert('Minimal harus ada 1 baris rincian.');
                return;
            }
            removeBtn.closest('.item-row').remove();
            recalcAll();
        }
    });
    btnAddItem.addEventListener('click', function () {
        rowIndex++;
        fetch('<?= BASE_URL ?>/index.php?module=cash&action=ajaxItemRow&index=' + rowIndex)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                tableBody.insertAdjacentHTML('beforeend', data.html);
                recalcAll();
            })
            .catch(function () { alert('Gagal menambahkan baris. Silakan coba lagi.'); });
    });

    recalcAll();
})();
</script>
