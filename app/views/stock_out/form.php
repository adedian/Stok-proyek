<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
// Tujuan "Client (Invoice)" hanya untuk Super Admin/Accounting/Purchase
// (ditegakkan juga di StockOutController). Role lain: hanya Project.
$canClientDest = $canClientDest ?? true;
$destType = $selectedDestinationType ?? 'project';
if (!$canClientDest) {
    $destType = 'project';
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Pengeluaran Barang</h4>
        <small class="text-muted">
            <?php if ($isEdit && !empty($stockOut['stock_out_number'])): ?>
                No. Dokumen: <strong><?= e($stockOut['stock_out_number']) ?></strong> &middot;
            <?php endif; ?>
            Stok akan otomatis berkurang setelah disimpan
        </small>
    </div>
    <a href="<?= BASE_URL ?>/stock_out" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=stock_out&action=<?= $actionUrl ?>" id="stockOutForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $stockOut['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label d-block">Tujuan Pengeluaran <span class="text-danger">*</span></label>
                    <?php if ($canClientDest): ?>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="destination_type" id="destTypeProject" value="project" autocomplete="off"
                                   <?= $destType === 'project' ? 'checked' : '' ?> <?= $isEdit ? 'disabled' : '' ?>>
                            <label class="btn btn-outline-primary" for="destTypeProject">Project</label>

                            <input type="radio" class="btn-check" name="destination_type" id="destTypeClient" value="client" autocomplete="off"
                                   <?= $destType === 'client' ? 'checked' : '' ?> <?= $isEdit ? 'disabled' : '' ?>>
                            <label class="btn btn-outline-primary" for="destTypeClient">Client (Invoice)</label>
                        </div>
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="destination_type" value="<?= e($destType) ?>">
                        <?php endif; ?>
                    <?php else: ?>
                        <input type="hidden" name="destination_type" value="project">
                        <div class="form-control bg-light d-flex align-items-center"><i class="bi bi-diagram-3 me-2"></i> Project</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4" id="projectFieldWrap" style="<?= $destType === 'client' ? 'display:none;' : '' ?>">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="project_id" id="projectSelect" class="form-select"
                                <?= $destType === 'project' ? 'required' : '' ?> <?= $isEdit ? 'disabled' : '' ?>>
                            <option value="">-- Pilih Project --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= (string) $selectedProjectId === (string) $p['id'] ? 'selected' : '' ?>>
                                    <?= e($p['project_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$isEdit && canQuickAdd('project')): ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddProject" title="Tambah Project Cepat">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="project_id" value="<?= (int) $selectedProjectId ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-4" id="invoiceFieldWrap" style="<?= $destType === 'project' ? 'display:none;' : '' ?>">
                    <label class="form-label">Client (Invoice) <span class="text-danger">*</span></label>
                    <select name="sales_invoice_id" id="invoiceSelect" class="form-select"
                            <?= $destType === 'client' ? 'required' : '' ?> <?= $isEdit ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Invoice --</option>
                        <?php foreach ($clientInvoices as $inv): ?>
                            <option value="<?= (int) $inv['id'] ?>" <?= (string) $selectedSalesInvoiceId === (string) $inv['id'] ? 'selected' : '' ?>>
                                <?= e($inv['invoice_number']) ?> &mdash; <?= e($inv['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($isEdit && !empty($stockOut['sales_invoice_id']) && !in_array((int) $stockOut['sales_invoice_id'], array_column($clientInvoices, 'id'))): ?>
                            <option value="<?= (int) $stockOut['sales_invoice_id'] ?>" selected>
                                <?= e($stockOut['invoice_number']) ?> &mdash; <?= e($stockOut['client_name']) ?>
                            </option>
                        <?php endif; ?>
                    </select>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="sales_invoice_id" value="<?= (int) $selectedSalesInvoiceId ?>">
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tanggal Keluar <span class="text-danger">*</span></label>
                    <input type="date" name="out_date" class="form-control"
                           value="<?= e($stockOut['out_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">PIC <span class="text-danger">*</span></label>
                    <input type="text" name="pic_name" class="form-control"
                           value="<?= e($stockOut['pic_name'] ?? '') ?>" placeholder="Nama penanggung jawab" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tujuan <span class="text-danger">*</span></label>
                    <input type="text" name="destination" class="form-control"
                           value="<?= e($stockOut['destination'] ?? '') ?>" placeholder="Lokasi/area tujuan" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="notes" class="form-control"
                           value="<?= e($stockOut['notes'] ?? '') ?>" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <?php /* ============ EDIT: tetap 1 barang, field terkunci ============ */ ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Barang <span class="text-danger">*</span></label>
                        <select name="inventory_id" id="itemSelect" class="form-select" required disabled>
                            <option value="">-- Pilih Barang --</option>
                            <?php foreach ($inventoryItems as $item): ?>
                                <option value="<?= (int) $item['id'] ?>"
                                    data-qty="<?= e((string) $item['qty_available']) ?>"
                                    data-unit="<?= e($item['unit']) ?>"
                                    <?= ((int) $stockOut['inventory_id'] === (int) $item['id']) ? 'selected' : '' ?>>
                                    <?= e($item['item_name']) ?> (stok: <?= number_format((float) $item['qty_available'], 2, ',', '.') ?> <?= e($item['unit']) ?><?php if (array_key_exists('project_name', $item)): ?>, <?= e($item['project_name'] ?: 'Stok Kantor') ?><?php endif; ?>)
                                </option>
                            <?php endforeach; ?>
                            <?php if (!in_array($stockOut['inventory_id'], array_column($inventoryItems, 'id'))): ?>
                                <option value="<?= (int) $stockOut['inventory_id'] ?>" selected
                                    data-qty="<?= e((string) ($stockOut['qty_available'] + $stockOut['qty'])) ?>"
                                    data-unit="<?= e($stockOut['unit']) ?>">
                                    <?= e($stockOut['item_name']) ?> (stok saat ini: <?= number_format((float) $stockOut['qty_available'], 2, ',', '.') ?> <?= e($stockOut['unit']) ?>)
                                </option>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" name="inventory_id" value="<?= (int) $stockOut['inventory_id'] ?>">
                        <div class="form-text" id="stockInfo"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Qty Keluar <span class="text-danger">*</span></label>
                        <input type="number" name="qty" id="qtyInput" class="form-control"
                               value="<?= e($stockOut['qty'] ?? '') ?>" min="0.01" step="0.01" required>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php /* ============ TAMBAH: bisa >1 barang ============ */ ?>
        <style>
        /* Daftar Barang (form Tambah Pengeluaran) -- di HP tabel entri diubah
           jadi kartu supaya kolom Qty & tombol hapus tidak terpotong. Hanya
           menyasar #soItemsTable form ini. Pola sama dgn cash/form.php. */
        @media (max-width: 767.98px) {
            .so-items-head { flex-wrap: wrap; }
            .so-items-head > #soAddRowBtn { flex: 1 1 100%; margin-top: .4rem; }

            #soItemsTable { border: 0; }
            #soItemsTable thead { display: none; }
            #soItemsTable tbody,
            #soItemsTable tr.so-item-row { display: block; width: 100%; }

            #soItemsTable tr.so-item-row {
                border: 1px solid #dee2e6;
                border-radius: .5rem;
                padding: .6rem .75rem .75rem;
                margin-bottom: .625rem;
                background: #fff;
            }
            #soItemsTable tr.so-item-row > td {
                display: block;
                width: 100% !important;
                border: 0;
                padding: .4rem 0 0;
                text-align: left !important;
            }
            #soItemsTable tr.so-item-row > td::before {
                content: attr(data-label);
                display: block;
                font-size: .7rem;
                font-weight: 600;
                letter-spacing: .02em;
                text-transform: uppercase;
                color: #6c757d;
                margin-bottom: .15rem;
            }
            #soItemsTable tr.so-item-row > td .so-qty-input { text-align: left !important; }
            #soItemsTable tr.so-item-row > td.cell-remove { padding-top: .6rem; }
            #soItemsTable tr.so-item-row > td.cell-remove::before { content: none; }
            #soItemsTable tr.so-item-row > td.cell-remove .so-remove-row { width: 100%; }
        }
        </style>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2 so-items-head">
                    <label class="form-label mb-0">Daftar Barang <span class="text-danger">*</span></label>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="soAddRowBtn" disabled>
                        <i class="bi bi-plus-lg"></i> Tambah Baris
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="soItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 220px;">Barang</th>
                                <th style="width: 150px;" class="text-end">Qty Keluar</th>
                                <th style="width: 44px;"></th>
                            </tr>
                        </thead>
                        <tbody id="soItemsBody">
                            <tr class="so-item-row">
                                <td data-label="Barang">
                                    <select name="inventory_id[]" class="form-select form-select-sm so-item-select" required disabled>
                                        <option value="">-- Pilih Barang --</option>
                                    </select>
                                    <div class="form-text so-stock-info small"></div>
                                </td>
                                <td data-label="Qty Keluar">
                                    <input type="number" name="qty[]" class="form-control form-control-sm text-end so-qty-input"
                                           min="0.01" step="0.01" required disabled>
                                </td>
                                <td class="text-center cell-remove">
                                    <button type="button" class="btn btn-sm btn-outline-danger so-remove-row" title="Hapus baris" tabindex="-1">
                                        <i class="bi bi-x-lg"></i><span class="d-md-none ms-1">Hapus baris</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="form-text mt-2" id="soItemsHint">
                    Pilih <?= $canClientDest ? 'Project atau Client (Invoice)' : 'Project' ?> dulu untuk melihat barang yang tersedia.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Simpan
        </button>
        <a href="<?= BASE_URL ?>/stock_out" class="btn btn-light border">Batal</a>
    </div>
</form>

<?php if (!$isEdit && canQuickAdd('project')): ?>
    <?php $quickAddProjectTargetId = 'projectSelect'; ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_project_modal.php'; ?>
<?php endif; ?>

<?php if ($isEdit): ?>
<script>
(function () {
    const itemSelect = document.getElementById('itemSelect');
    const qtyInput = document.getElementById('qtyInput');
    const stockInfo = document.getElementById('stockInfo');

    function updateStockInfo() {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        if (!selected || !selected.value) {
            stockInfo.textContent = '';
            qtyInput.removeAttribute('max');
            return;
        }
        const qtyAvailable = parseFloat(selected.dataset.qty || '0');
        const unit = selected.dataset.unit || '';
        stockInfo.innerHTML = 'Stok tersedia: <strong>' + qtyAvailable.toLocaleString('id-ID') + ' ' + unit + '</strong>';
        qtyInput.setAttribute('max', qtyAvailable);
    }

    document.getElementById('stockOutForm').addEventListener('submit', function (e) {
        const max = parseFloat(qtyInput.getAttribute('max') || 'Infinity');
        const qty = parseFloat(qtyInput.value || '0');
        if (qty > max) {
            e.preventDefault();
            alert('Qty keluar (' + qty + ') melebihi stok tersedia (' + max + ').');
        }
    });

    updateStockInfo();
})();
</script>
<?php else: ?>
<script>
(function () {
    const projectSelect = document.getElementById('projectSelect');
    const invoiceSelect = document.getElementById('invoiceSelect');
    const projectFieldWrap = document.getElementById('projectFieldWrap');
    const invoiceFieldWrap = document.getElementById('invoiceFieldWrap');
    const destTypeRadios = document.querySelectorAll('input[name="destination_type"]');
    const itemsBody = document.getElementById('soItemsBody');
    const addRowBtn = document.getElementById('soAddRowBtn');
    const itemsHint = document.getElementById('soItemsHint');
    const form = document.getElementById('stockOutForm');

    // Daftar barang tersedia saat ini (di-refresh via AJAX tiap ganti Project/Invoice).
    let currentItems = [];

    function optionLabel(item) {
        const origin = ('project_name' in item) ? (', ' + (item.project_name || 'Stok Kantor')) : '';
        return item.item_name + ' (stok: '
            + parseFloat(item.qty_available).toLocaleString('id-ID') + ' ' + item.unit + origin + ')';
    }

    function selectedElsewhere(exceptSelect) {
        const chosen = [];
        itemsBody.querySelectorAll('.so-item-select').forEach(function (sel) {
            if (sel !== exceptSelect && sel.value) { chosen.push(sel.value); }
        });
        return chosen;
    }

    // Isi ulang <option> sebuah <select> baris dari currentItems, jaga pilihan lama
    // kalau masih ada, dan disable opsi yang sudah dipakai baris lain.
    function renderRowOptions(sel) {
        const prev = sel.value;
        const taken = selectedElsewhere(sel);
        sel.innerHTML = '<option value="">-- Pilih Barang --</option>';
        currentItems.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.dataset.qty = item.qty_available;
            opt.dataset.unit = item.unit;
            opt.textContent = optionLabel(item);
            if (String(item.id) === String(prev)) { opt.selected = true; }
            if (taken.indexOf(String(item.id)) !== -1) { opt.disabled = true; }
            sel.appendChild(opt);
        });
        sel.disabled = currentItems.length === 0;
        updateRowStock(sel.closest('.so-item-row'));
    }

    function renderAllRows() {
        itemsBody.querySelectorAll('.so-item-select').forEach(renderRowOptions);
        addRowBtn.disabled = currentItems.length === 0;
    }

    function updateRowStock(row) {
        const sel = row.querySelector('.so-item-select');
        const qtyInput = row.querySelector('.so-qty-input');
        const info = row.querySelector('.so-stock-info');
        const opt = sel.options[sel.selectedIndex];
        qtyInput.disabled = sel.disabled;
        if (!opt || !opt.value) {
            info.textContent = '';
            qtyInput.removeAttribute('max');
            return;
        }
        const avail = parseFloat(opt.dataset.qty || '0');
        info.innerHTML = 'Stok tersedia: <strong>' + avail.toLocaleString('id-ID') + ' ' + (opt.dataset.unit || '') + '</strong>';
        qtyInput.setAttribute('max', avail);
    }

    function newRow() {
        const first = itemsBody.querySelector('.so-item-row');
        const row = first.cloneNode(true);
        row.querySelector('.so-item-select').value = '';
        row.querySelector('.so-qty-input').value = '';
        row.querySelector('.so-stock-info').textContent = '';
        wireRow(row);
        itemsBody.appendChild(row);
        renderRowOptions(row.querySelector('.so-item-select'));
        return row;
    }

    function wireRow(row) {
        const sel = row.querySelector('.so-item-select');
        sel.addEventListener('change', function () {
            updateRowStock(row);
            renderAllRows(); // segarkan opsi disabled di baris lain
        });
        row.querySelector('.so-remove-row').addEventListener('click', function () {
            if (itemsBody.querySelectorAll('.so-item-row').length <= 1) {
                sel.value = '';
                row.querySelector('.so-qty-input').value = '';
                updateRowStock(row);
                renderAllRows();
                return;
            }
            row.remove();
            renderAllRows();
        });
    }

    function resetItems(message) {
        currentItems = [];
        // buang semua baris kecuali satu
        itemsBody.querySelectorAll('.so-item-row').forEach(function (r, i) {
            if (i === 0) {
                r.querySelector('.so-item-select').value = '';
                r.querySelector('.so-qty-input').value = '';
            } else {
                r.remove();
            }
        });
        renderAllRows();
        itemsHint.textContent = message || '';
    }

    function loadItems(url, emptyMessage) {
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                currentItems = (data.items || []).map(function (it) {
                    return {
                        id: it.id, item_name: it.item_name,
                        qty_available: it.qty_available, unit: it.unit,
                        project_name: ('project_name' in it) ? it.project_name : undefined
                    };
                });
                // hapus key project_name kalau memang tidak ada (biar 'in' akurat)
                currentItems.forEach(function (it) { if (it.project_name === undefined) delete it.project_name; });
                renderAllRows();
                itemsHint.textContent = currentItems.length ? '' : emptyMessage;
            })
            .catch(function () { itemsHint.textContent = 'Gagal memuat daftar barang.'; });
    }

    function loadOfficeItems() {
        let url = '<?= BASE_URL ?>/index.php?module=stock_out&action=ajaxItemsByOffice';
        if (invoiceSelect.value) { url += '&sales_invoice_id=' + invoiceSelect.value; }
        loadItems(url, 'Tidak ada barang dengan stok tersedia.');
    }

    function applyDestinationType(type) {
        const isProject = type !== 'client';
        if (projectFieldWrap) projectFieldWrap.style.display = isProject ? '' : 'none';
        if (invoiceFieldWrap) invoiceFieldWrap.style.display = isProject ? 'none' : '';
        projectSelect.required = isProject;
        invoiceSelect.required = !isProject;
    }

    destTypeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            applyDestinationType(this.value);
            if (this.value === 'client') {
                projectSelect.value = '';
                if (invoiceSelect.value) { loadOfficeItems(); } else { resetItems('Pilih Client (Invoice) dulu.'); }
            } else {
                invoiceSelect.value = '';
                resetItems('Pilih Project dulu untuk melihat barang yang tersedia.');
            }
        });
    });

    projectSelect.addEventListener('change', function () {
        if (!this.value) { resetItems('Pilih Project dulu untuk melihat barang yang tersedia.'); return; }
        loadItems('<?= BASE_URL ?>/index.php?module=stock_out&action=ajaxItemsByProject&project_id=' + this.value,
            'Tidak ada barang dengan stok tersedia di project ini.');
    });

    invoiceSelect.addEventListener('change', function () {
        if (!this.value) { resetItems('Pilih Client (Invoice) dulu.'); return; }
        loadOfficeItems();
    });

    addRowBtn.addEventListener('click', function () { newRow(); });

    form.addEventListener('submit', function (e) {
        const rows = Array.prototype.slice.call(itemsBody.querySelectorAll('.so-item-row'));
        let filled = 0;
        for (let i = 0; i < rows.length; i++) {
            const sel = rows[i].querySelector('.so-item-select');
            const qtyInput = rows[i].querySelector('.so-qty-input');
            if (!sel.value) { continue; }
            filled++;
            const max = parseFloat(qtyInput.getAttribute('max') || 'Infinity');
            const qty = parseFloat(qtyInput.value || '0');
            if (qty <= 0) {
                e.preventDefault();
                alert('Qty keluar harus lebih dari 0 untuk "' + sel.options[sel.selectedIndex].textContent + '".');
                return;
            }
            if (qty > max) {
                e.preventDefault();
                alert('Qty keluar (' + qty + ') melebihi stok tersedia (' + max + ') untuk "' + sel.options[sel.selectedIndex].textContent + '".');
                return;
            }
        }
        if (filled === 0) {
            e.preventDefault();
            alert('Pilih minimal 1 barang.');
        }
    });

    // init
    wireRow(itemsBody.querySelector('.so-item-row'));
    applyDestinationType('<?= e($destType) ?>');
    <?php if ($selectedProjectId): ?>
    if (projectSelect.value) {
        loadItems('<?= BASE_URL ?>/index.php?module=stock_out&action=ajaxItemsByProject&project_id=' + projectSelect.value,
            'Tidak ada barang dengan stok tersedia di project ini.');
    }
    <?php elseif ($selectedSalesInvoiceId): ?>
    if (invoiceSelect.value) { loadOfficeItems(); }
    <?php endif; ?>
})();
</script>
<?php endif; ?>

<?php if ($isEdit): ?>
    <p class="text-muted small mt-3">
        <i class="bi bi-info-circle"></i>
        Tujuan Pengeluaran, Project/Client, & Barang tidak bisa diganti saat edit untuk menjaga konsistensi histori kartu stok.
        Hapus data ini dan buat pengeluaran baru jika tujuannya salah.
    </p>
<?php endif; ?>
