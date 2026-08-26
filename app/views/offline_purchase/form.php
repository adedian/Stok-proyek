<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$itemsLocked = $itemsLocked ?? false;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Pembelian Offline</h4>
        <small class="text-muted">No. Pembelian: <strong><?= e($purchaseNumber) ?></strong> (otomatis) &middot; Pembelian manual di luar Purchase Order</small>
    </div>
    <a href="<?= BASE_URL ?>/index.php?module=offline_purchase" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php if ($itemsLocked): ?>
    <div class="alert alert-info small">
        <i class="bi bi-info-circle"></i>
        Pembelian offline ini sudah punya penerimaan barang -- daftar item terkunci dan tidak bisa diubah/dihapus.
        Hanya data umum (project/supplier/tanggal/catatan) yang bisa diperbarui.
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=offline_purchase&action=<?= $actionUrl ?>" enctype="multipart/form-data" id="offlinePurchaseForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $purchase['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="project_id" id="project_id" class="form-select" required>
                            <option value="">-- Pilih Project --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"
                                    <?= $isEdit && (int) $purchase['project_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (canQuickAdd('project')): ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddProject" title="Tambah Project Cepat">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                    <input type="text" name="supplier_name" class="form-control"
                           value="<?= e($purchase['supplier_name'] ?? '') ?>" placeholder="Nama toko/supplier" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tanggal Pembelian <span class="text-danger">*</span></label>
                    <input type="date" name="purchase_date" class="form-control"
                           value="<?= e($purchase['purchase_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Bukti Pembelian</label>
                    <input type="file" name="proof_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    <?php if ($isEdit && !empty($purchase['proof_file'])): ?>
                        <div class="form-text">
                            Saat ini: <a href="<?= BASE_URL ?>/<?= e($purchase['proof_file']) ?>" target="_blank">lihat bukti</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Foto Barang</label>
                    <input type="file" name="photo_file" class="form-control" accept=".jpg,.jpeg,.png">
                    <?php if ($isEdit && !empty($purchase['photo_file'])): ?>
                        <div class="form-text">
                            Saat ini: <a href="<?= BASE_URL ?>/<?= e($purchase['photo_file']) ?>" target="_blank">lihat foto</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="notes" class="form-control" value="<?= e($purchase['notes'] ?? '') ?>" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Daftar Item Barang</h6>
                <?php if (!$itemsLocked): ?>
                    <button type="button" id="btnAddItem" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Tambah Barang
                    </button>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="itemTable">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Barang</th>
                            <th>Satuan</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th class="text-end">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="itemTableBody">
                        <?php if ($itemsLocked): ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= e($item['item_name']) ?></td>
                                    <td><?= e($item['unit']) ?></td>
                                    <td><?= number_format((float) $item['qty'], 2, ',', '.') ?></td>
                                    <td><?= formatRupiah($item['price']) ?></td>
                                    <td class="text-end"><?= formatRupiah($item['subtotal']) ?></td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php elseif (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <?php include ROOT_PATH . '/app/views/offline_purchase/_item_row.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php $item = null; include ROOT_PATH . '/app/views/offline_purchase/_item_row.php'; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold" id="grandTotal"><?= $itemsLocked ? formatRupiah($purchase['total_amount']) : 'Rp 0' ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Simpan
        </button>
        <a href="<?= BASE_URL ?>/index.php?module=offline_purchase" class="btn btn-light border">Batal</a>
    </div>
</form>

<?php if (canQuickAdd('project')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_project_modal.php'; ?>
<?php endif; ?>
<?php if (!$itemsLocked && canQuickAdd('item')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_item_modal.php'; ?>
<?php endif; ?>

<?php if (!$itemsLocked): ?>
<script>
(function () {
    const tableBody = document.getElementById('itemTableBody');
    const btnAddItem = document.getElementById('btnAddItem');
    const grandTotalEl = document.getElementById('grandTotal');
    let rowIndex = tableBody.querySelectorAll('.item-row').length;

    function formatRupiah(num) {
        // Format nominal baru: koma ribuan, titik desimal, selalu 2 digit desimal --
        // HARUS sinkron dengan formatRupiah() PHP (app/helpers/functions.php).
        return 'Rp ' + Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcRow(row) {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        // Input harga sekarang format "15,000.73" -- buang koma (ribuan), titik tetap
        // dipertahankan sebagai desimal (lihat currency-input.js).
        const price = parseFloat((row.querySelector('.price-input').value || '').replace(/,/g, '')) || 0;
        const subtotal = qty * price;
        row.querySelector('.subtotal-cell').textContent = formatRupiah(subtotal);
        return subtotal;
    }

    function recalcAll() {
        let total = 0;
        tableBody.querySelectorAll('.item-row').forEach(function (row) {
            total += recalcRow(row);
        });
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
            const rows = tableBody.querySelectorAll('.item-row');
            if (rows.length <= 1) {
                alert('Minimal harus ada 1 item barang.');
                return;
            }
            removeBtn.closest('.item-row').remove();
            recalcAll();
            return;
        }

        const quickAddBtn = e.target.closest('.btn-quick-add-item');
        if (quickAddBtn) {
            window.__quickAddItemTarget = quickAddBtn.closest('.item-row').querySelector('.item-select');
            const modalEl = document.getElementById('modalQuickAddItem');
            if (modalEl) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        }
    });

    tableBody.addEventListener('change', function (e) {
        if (!e.target.classList.contains('item-select')) {
            return;
        }
        const select = e.target;
        const row = select.closest('.item-row');
        const opt = select.options[select.selectedIndex];
        const idInput = row.querySelector('.item-id-input');
        const nameInput = row.querySelector('.item-name-input');
        const unitDisplay = row.querySelector('.unit-display');
        const unitInput = row.querySelector('.unit-input');

        if (opt.dataset.legacy) {
            idInput.value = '';
            nameInput.value = opt.dataset.name || '';
            unitDisplay.value = opt.dataset.unit || '';
            unitInput.value = opt.dataset.unit || '';
        } else if (opt.value) {
            idInput.value = opt.value;
            nameInput.value = opt.textContent.trim();
            unitDisplay.value = opt.dataset.unit || '';
            unitInput.value = opt.dataset.unit || '';
        } else {
            idInput.value = '';
            nameInput.value = '';
            unitDisplay.value = '';
            unitInput.value = '';
        }
    });

    btnAddItem.addEventListener('click', function () {
        rowIndex++;
        fetch('<?= BASE_URL ?>/index.php?module=offline_purchase&action=ajaxItemRow&index=' + rowIndex)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                tableBody.insertAdjacentHTML('beforeend', data.html);
                recalcAll();
            })
            .catch(function () {
                alert('Gagal menambahkan baris item. Silakan coba lagi.');
            });
    });

    recalcAll();
})();
</script>
<?php endif; ?>
