<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$items = $items ?? [];
if (empty($items)) {
    $items = [['description' => '', 'qty' => '', 'unit' => '', 'unit_price' => '']];
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Invoice Keluar</h4>
        <small class="text-muted">
            <?php if ($isEdit): ?>
                No. Invoice: <strong><?= e($invoice['invoice_number']) ?></strong>
            <?php else: ?>
                Nomor invoice dibuat otomatis saat disimpan
            <?php endif; ?>
        </small>
    </div>
    <a href="<?= BASE_URL ?>/sales_invoice" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=sales_invoice&action=<?= $actionUrl ?>" id="salesInvoiceForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $invoice['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Jenis Invoice <span class="text-danger">*</span></label>
                    <?php $invoiceType = $invoice['invoice_type'] ?? 'project'; ?>
                    <select name="invoice_type" id="invoiceTypeSelect" class="form-select" <?= $isEdit ? 'disabled' : '' ?> required>
                        <option value="project" <?= $invoiceType === 'project' ? 'selected' : '' ?>>Project (INV.HME)</option>
                        <option value="lampu" <?= $invoiceType === 'lampu' ? 'selected' : '' ?>>Lampu (FKT.HME)</option>
                    </select>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="invoice_type" value="<?= e($invoiceType) ?>">
                        <div class="form-text">Jenis invoice tidak bisa diganti setelah dibuat (nomor sudah mengikuti jenis awal).</div>
                    <?php else: ?>
                        <div class="form-text">Menentukan format nomor: Project = .../INV.HME/..., Lampu = .../FKT.HME/... (urutan terpisah).</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Client <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="client_id" id="client_id" class="form-select" required>
                            <option value="">-- Pilih Client --</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) ($invoice['client_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['client_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (canQuickAdd('client')): ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddClient" title="Tambah Client Cepat">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Project <span class="text-muted small">(opsional)</span></label>
                    <div class="input-group">
                        <select name="project_id" id="si_project_id" class="form-select">
                            <option value="">-- Tanpa Project --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= (int) ($invoice['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
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
                <div class="col-md-3">
                    <label class="form-label">Tanggal Invoice <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" class="form-control"
                           value="<?= e($invoice['invoice_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Kontrak <span class="text-muted small">(opsional)</span></label>
                    <input type="text" name="contract_number" class="form-control" value="<?= e($invoice['contract_number'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Kontrak</label>
                    <input type="date" name="contract_date" class="form-control" value="<?= e($invoice['contract_date'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Faktur Pajak <span class="text-muted small">(opsional)</span></label>
                    <input type="text" name="tax_invoice_number" class="form-control" value="<?= e($invoice['tax_invoice_number'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tagihan DP <span class="text-danger">*</span></label>
                    <select name="dp_percentage_id" id="dpPercentageSelect" class="form-select" required>
                        <option value="">-- Pilih DP --</option>
                        <?php foreach ($dpPercentages as $dp): ?>
                            <option value="<?= (int) $dp['id'] ?>" data-percentage="<?= e($dp['percentage']) ?>"
                                <?= (int) ($invoice['dp_percentage_id'] ?? 0) === (int) $dp['id'] ? 'selected' : '' ?>>
                                <?= e($dp['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit && empty($dpPercentages)): ?>
                        <div class="form-text text-danger">Belum ada persentase DP aktif di Master Data.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">PPN (%)</label>
                    <input type="number" name="ppn_percent" id="ppnPercent" class="form-control"
                           value="<?= e($invoice['ppn_percent'] ?? '11.00') ?>" min="0" step="0.01">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0 fw-semibold">Baris Item</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRow">
                    <i class="bi bi-plus-lg"></i> Tambah Baris
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 260px;">Barang / Deskripsi</th>
                            <th style="width: 100px;">Qty</th>
                            <th style="width: 110px;">Satuan</th>
                            <th style="width: 160px;">Harga Satuan</th>
                            <th style="width: 160px;" class="text-end">Subtotal</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemTableBody">
                        <?php $itemCatalog = $itemCatalog ?? []; ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                                $selectedItemId = (int) ($item['item_id'] ?? 0);
                                $selectedItemCode = '';
                                if ($selectedItemId) {
                                    foreach ($itemCatalog as $ic) {
                                        if ((int) $ic['id'] === $selectedItemId) {
                                            $selectedItemCode = $ic['item_code'];
                                            break;
                                        }
                                    }
                                }
                            ?>
                            <tr class="item-row">
                                <td>
                                    <div class="input-group input-group-sm mb-1">
                                        <select class="form-select item-select">
                                            <option value="">-- Pilih dari Master Barang (opsional) --</option>
                                            <?php foreach ($itemCatalog as $ic): ?>
                                                <option value="<?= (int) $ic['id'] ?>" data-unit="<?= e($ic['unit_name']) ?>" data-itemcode="<?= e($ic['item_code']) ?>"
                                                    <?= $selectedItemId === (int) $ic['id'] ? 'selected' : '' ?>>
                                                    <?= e($ic['item_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (canQuickAdd('item')): ?>
                                            <button type="button" class="btn btn-outline-secondary btn-quick-add-item" title="Tambah Barang Baru">
                                                <i class="bi bi-plus-lg"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted item-code-hint mb-1"><?= $selectedItemCode !== '' ? 'Kode: ' . e($selectedItemCode) : '' ?></div>
                                    <input type="hidden" name="item_id[]" class="item-id-input" value="<?= $selectedItemId ?: '' ?>">
                                    <input type="text" name="description[]" class="form-control form-control-sm description-input" value="<?= e($item['description']) ?>" placeholder="Deskripsi pekerjaan/barang (boleh diedit bebas)" required>
                                </td>
                                <td><input type="number" name="qty[]" class="form-control form-control-sm qty-input" value="<?= e($item['qty']) ?>" min="0.01" step="0.01" required></td>
                                <td><input type="text" name="unit[]" class="form-control form-control-sm unit-input" list="unitOptions" value="<?= e($item['unit']) ?>" placeholder="Pilih/ketik satuan" required></td>
                                <td><input type="text" name="unit_price[]" class="form-control form-control-sm price-input currency-input" inputmode="numeric"
                                           value="<?= $item['unit_price'] !== '' ? number_format((float) $item['unit_price'], 2, '.', ',') : '' ?>" placeholder="0" required></td>
                                <td class="text-end subtotal-cell">Rp 0.00</td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-semibold">Jumlah</td>
                            <td class="text-end fw-semibold" id="sumSubtotal">Rp 0.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end fw-semibold" id="sumDpLabel">Tagihan (DP)</td>
                            <td class="text-end fw-semibold" id="sumDp">Rp 0.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end fw-semibold">PPN</td>
                            <td class="text-end fw-semibold" id="sumPpn">Rp 0.00</td>
                            <td></td>
                        </tr>
                        <tr class="table-light">
                            <td colspan="4" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold" id="sumTotal">Rp 0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <datalist id="unitOptions">
                <?php
                    // Satuan barang fisik dari Master Satuan + satuan jasa yang umum
                    // dipakai di Invoice Keluar (bukan barang gudang, jadi tidak masuk
                    // Master Satuan) -- dropdown-with-free-text (bukan <select> kaku)
                    // supaya "ls" dkk tetap bisa diketik walau belum ada di master.
                    $serviceUnits = ['ls', 'jam', 'hari', 'bulan', 'paket'];
                    $unitNames = array_unique(array_merge(array_column($units, 'unit_name'), $serviceUnits));
                ?>
                <?php foreach ($unitNames as $u): ?>
                    <option value="<?= e($u) ?>">
                <?php endforeach; ?>
            </datalist>
            <template id="itemSelectOptionsTemplate">
                <option value="">-- Pilih dari Master Barang (opsional) --</option>
                <?php foreach ($itemCatalog as $ic): ?>
                    <option value="<?= (int) $ic['id'] ?>" data-unit="<?= e($ic['unit_name']) ?>" data-itemcode="<?= e($ic['item_code']) ?>"><?= e($ic['item_name']) ?></option>
                <?php endforeach; ?>
            </template>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tanda Tangan</label>
                    <select name="signature_id" class="form-select">
                        <option value="">-- Tanpa Tanda Tangan Gambar --</option>
                        <?php foreach ($signatures as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) ($invoice['signature_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= e($s['name']) ?> &middot; <?= e($s['position']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2"><?= e($invoice['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Simpan
        </button>
        <a href="<?= BASE_URL ?>/sales_invoice" class="btn btn-light border">Batal</a>
    </div>
</form>

<?php if (canQuickAdd('client')): ?>
    <?php $quickAddClientTargetId = 'client_id'; ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_client_modal.php'; ?>
<?php endif; ?>

<?php if (canQuickAdd('project')): ?>
    <?php $quickAddProjectTargetId = 'si_project_id'; ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_project_modal.php'; ?>
<?php endif; ?>

<?php if (canQuickAdd('item')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_item_modal.php'; ?>
<?php endif; ?>

<script>
(function () {
    const tableBody = document.getElementById('itemTableBody');
    const ppnInput = document.getElementById('ppnPercent');
    const dpSelect = document.getElementById('dpPercentageSelect');

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

    // Rumus (lihat SalesInvoiceController::calculateTotals() -- backend menghitung
    // ulang persis rumus yang sama saat save, angka di sini murni preview UX):
    //   Jumlah = SUM(subtotal item)
    //   Tagihan DP = Jumlah x DP%
    //   PPN = Tagihan DP x PPN%   (BUKAN dari Jumlah)
    //   Total = Tagihan DP + PPN
    function recalcAll() {
        let subtotal = 0;
        tableBody.querySelectorAll('.item-row').forEach(function (row) {
            subtotal += recalcRow(row);
        });

        const dpOption = dpSelect.options[dpSelect.selectedIndex];
        const dpPercent = dpOption ? parseFloat(dpOption.dataset.percentage || '0') : 0;
        const dpAmount = subtotal * dpPercent / 100;

        const ppnPercent = parseFloat(ppnInput.value) || 0;
        const ppnAmount = dpAmount * ppnPercent / 100;

        // Label SELALU dibangun dari angka persentase ("Tagihan (DP 50%)"), bukan
        // dari nama bebas master (admin bisa menamai baris masternya apa saja) --
        // sinkron dengan cara print.php/detail.php membangun label yang sama.
        document.getElementById('sumSubtotal').textContent = formatRupiah(subtotal);
        document.getElementById('sumDpLabel').textContent = dpOption && dpOption.value
            ? 'Tagihan (DP ' + (dpPercent % 1 === 0 ? dpPercent : dpPercent.toFixed(2)) + '%)'
            : 'Tagihan (DP)';
        document.getElementById('sumDp').textContent = formatRupiah(dpAmount);
        document.getElementById('sumPpn').textContent = formatRupiah(ppnAmount);
        document.getElementById('sumTotal').textContent = formatRupiah(dpAmount + ppnAmount);
    }

    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
            recalcAll();
        }
    });
    ppnInput.addEventListener('input', recalcAll);
    dpSelect.addEventListener('change', recalcAll);

    const itemSelectOptionsHtml = document.getElementById('itemSelectOptionsTemplate').innerHTML;
    const hasQuickAddItem = document.getElementById('modalQuickAddItem') !== null;

    // Barang dipilih dari dropdown -> isi item_id[]/description[]/unit[] +
    // hint kode barang. Deskripsi & satuan tetap boleh diedit manual setelahnya
    // (dropdown ini cuma prefill, bukan field wajib -- baris jasa freetext tetap
    // valid tanpa memilih apapun di sini).
    tableBody.addEventListener('change', function (e) {
        if (!e.target.classList.contains('item-select')) {
            return;
        }
        const select = e.target;
        const row = select.closest('.item-row');
        const opt = select.options[select.selectedIndex];
        const idInput = row.querySelector('.item-id-input');
        const descInput = row.querySelector('.description-input');
        const unitInput = row.querySelector('.unit-input');
        const codeHint = row.querySelector('.item-code-hint');

        if (opt.value) {
            idInput.value = opt.value;
            descInput.value = opt.textContent.trim();
            unitInput.value = opt.dataset.unit || '';
            codeHint.textContent = opt.dataset.itemcode ? ('Kode: ' + opt.dataset.itemcode) : '';
        } else {
            idInput.value = '';
            codeHint.textContent = '';
        }
    });

    tableBody.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.btn-remove-row');
        if (removeBtn) {
            const rows = tableBody.querySelectorAll('.item-row');
            if (rows.length <= 1) {
                alert('Minimal harus ada 1 baris item.');
                return;
            }
            removeBtn.closest('.item-row').remove();
            recalcAll();
            return;
        }

        const quickAddBtn = e.target.closest('.btn-quick-add-item');
        if (quickAddBtn && hasQuickAddItem) {
            window.__quickAddItemTarget = quickAddBtn.closest('.item-row').querySelector('.item-select');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalQuickAddItem')).show();
        }
    });

    document.getElementById('btnAddRow').addEventListener('click', function () {
        const tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML =
            '<td>' +
                '<div class="input-group input-group-sm mb-1">' +
                    '<select class="form-select item-select">' + itemSelectOptionsHtml + '</select>' +
                    (hasQuickAddItem ? '<button type="button" class="btn btn-outline-secondary btn-quick-add-item" title="Tambah Barang Baru"><i class="bi bi-plus-lg"></i></button>' : '') +
                '</div>' +
                '<div class="small text-muted item-code-hint mb-1"></div>' +
                '<input type="hidden" name="item_id[]" class="item-id-input" value="">' +
                '<input type="text" name="description[]" class="form-control form-control-sm description-input" placeholder="Deskripsi pekerjaan/barang (boleh diedit bebas)" required>' +
            '</td>' +
            '<td><input type="number" name="qty[]" class="form-control form-control-sm qty-input" min="0.01" step="0.01" required></td>' +
            '<td><input type="text" name="unit[]" class="form-control form-control-sm unit-input" list="unitOptions" placeholder="Pilih/ketik satuan" required></td>' +
            '<td><input type="text" name="unit_price[]" class="form-control form-control-sm price-input currency-input" inputmode="numeric" placeholder="0" required></td>' +
            '<td class="text-end subtotal-cell">Rp 0.00</td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-trash"></i></button></td>';
        tableBody.appendChild(tr);
    });

    recalcAll();
})();
</script>
