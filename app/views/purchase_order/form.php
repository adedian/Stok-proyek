<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Purchase Order</h4>
        <small class="text-muted">No. PO: <strong><?= e($poNumber) ?></strong> (otomatis)</small>
    </div>
    <a href="<?= BASE_URL ?>/purchase_order" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST"
      action="<?= BASE_URL ?>/index.php?module=purchase_order&action=<?= $actionUrl ?>"
      id="poForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $po['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="supplier_id" id="supplier_id" class="form-select" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    <?= ($po && (int) $po['supplier_id'] === (int) $s['id']) ? 'selected' : '' ?>>
                                    <?= e($s['supplier_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (canQuickAdd('supplier')): ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddSupplier" title="Tambah Supplier Cepat">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="project_id" id="project_id" class="form-select" required>
                            <option value="">-- Pilih Project --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"
                                    <?= ($po && (int) $po['project_id'] === (int) $p['id']) ? 'selected' : '' ?>>
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
                <div class="col-md-4">
                    <label class="form-label">Lokasi Pengiriman</label>
                    <div class="input-group">
                        <select name="delivery_location_id" id="delivery_location_id" class="form-select">
                            <option value="">-- Pilih Lokasi (opsional) --</option>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= (int) $w['id'] ?>"
                                    <?= ($po && (int) ($po['delivery_location_id'] ?? 0) === (int) $w['id']) ? 'selected' : '' ?>>
                                    <?= e($w['warehouse_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (canQuickAdd('warehouse')): ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddWarehouse" title="Tambah Lokasi Cepat">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal PO <span class="text-danger">*</span></label>
                    <input type="date" name="po_date" class="form-control"
                           value="<?= e($po['po_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pembuat PO <span class="text-danger">*</span></label>
                    <input type="text" name="pembuat_po" class="form-control"
                           value="<?= e($po['pembuat_po'] ?? '') ?>" placeholder="Nama orang yang menyusun PO ini" required>
                    <div class="form-text">
                        Diinput manual -- bisa berbeda dari akun yang login
                        (<?= e($po['created_by_name'] ?? currentUserName()) ?>).
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanda Tangan</label>
                    <select name="signature_id" class="form-select">
                        <option value="">-- Tanpa Tanda Tangan --</option>
                        <?php foreach ($signatures as $sig): ?>
                            <option value="<?= (int) $sig['id'] ?>"
                                <?= (string) ($po['signature_id'] ?? '') === (string) $sig['id'] ? 'selected' : '' ?>>
                                <?= e($sig['name']) ?> -- <?= e($sig['position']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">
                        Otomatis dipasang di dokumen cetak PO. Kelola daftarnya di
                        <a href="<?= BASE_URL ?>/signature" target="_blank">Master Data &raquo; Tanda Tangan</a>.
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php
                        $statusOptions = [
                            'draft' => 'Draft',
                            'waiting_approval' => 'Menunggu Approval',
                            'approved' => 'Disetujui',
                            'partial_received' => 'Sebagian Diterima',
                            'completed' => 'Selesai',
                            'cancelled' => 'Dibatalkan',
                        ];
                        $currentStatus = $po['status'] ?? 'draft';
                        foreach ($statusOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $currentStatus === $key ? 'selected' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">No. Quotation</label>
                    <input type="text" name="quote_number" class="form-control"
                           value="<?= e($po['quote_number'] ?? '') ?>" placeholder="Opsional -- No. penawaran dari supplier">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Quotation</label>
                    <input type="date" name="quote_date" class="form-control"
                           value="<?= e($po['quote_date'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control"
                           value="<?= e($po['notes'] ?? '') ?>" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Daftar Item Barang</h6>
                <button type="button" id="btnAddItem" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Tambah Item
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="itemTable">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Barang</th>
                            <th>Kode Barang</th>
                            <th>Kategori</th>
                            <th>Satuan</th>
                            <th>Qty</th>
                            <th>Harga Satuan</th>
                            <th class="text-end">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="itemTableBody">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <?php include ROOT_PATH . '/app/views/purchase_order/_item_row.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php $item = null; include ROOT_PATH . '/app/views/purchase_order/_item_row.php'; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold" id="grandTotal">Rp 0.00</td>
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
        <a href="<?= BASE_URL ?>/purchase_order" class="btn btn-light border">Batal</a>
    </div>
</form>

<?php if (canQuickAdd('supplier')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_supplier_modal.php'; ?>
<?php endif; ?>
<?php if (canQuickAdd('project')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_project_modal.php'; ?>
<?php endif; ?>
<?php if (canQuickAdd('item')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_item_modal.php'; ?>
<?php endif; ?>
<?php if (canQuickAdd('warehouse')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_warehouse_modal.php'; ?>
<?php endif; ?>

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

    // Delegasi event untuk input qty/price yang bisa bertambah secara dinamis
    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
            recalcAll();
        }
    });

    // Hapus baris item (minimal harus tersisa 1 baris) & buka quick-add Barang per baris
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

    // Barang dipilih dari dropdown -> isi item_id[]/item_name[]/unit[] tersembunyi + tampilan satuan
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
        const codeDisplay = row.querySelector('.code-display');
        // Catatan: kolom Kategori (.category-input) TIDAK disentuh di sini --
        // itu catatan teks bebas per baris, tidak terhubung ke master Barang.

        if (opt.dataset.legacy) {
            idInput.value = '';
            nameInput.value = opt.dataset.name || '';
            unitDisplay.value = opt.dataset.unit || '';
            unitInput.value = opt.dataset.unit || '';
            if (codeDisplay) codeDisplay.value = '';
        } else if (opt.value) {
            idInput.value = opt.value;
            nameInput.value = opt.textContent.trim();
            unitDisplay.value = opt.dataset.unit || '';
            unitInput.value = opt.dataset.unit || '';
            if (codeDisplay) codeDisplay.value = opt.dataset.itemcode || '';
        } else {
            idInput.value = '';
            nameInput.value = '';
            unitDisplay.value = '';
            unitInput.value = '';
            if (codeDisplay) codeDisplay.value = '';
        }
    });

    // AJAX: minta baris item baru ke server (partial render dari _item_row.php)
    btnAddItem.addEventListener('click', function () {
        rowIndex++;
        fetch('<?= BASE_URL ?>/index.php?module=purchase_order&action=ajaxItemRow&index=' + rowIndex)
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
