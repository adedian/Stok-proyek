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
                    <label class="form-label">Barang <span class="text-danger">*</span></label>
                    <select name="inventory_id" id="itemSelect" class="form-select" required
                            <?= ($isEdit || empty($inventoryItems)) ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Barang --</option>
                        <?php foreach ($inventoryItems as $item): ?>
                            <option value="<?= (int) $item['id'] ?>"
                                data-qty="<?= e((string) $item['qty_available']) ?>"
                                data-unit="<?= e($item['unit']) ?>"
                                <?= ($isEdit && (int) $stockOut['inventory_id'] === (int) $item['id']) ? 'selected' : '' ?>>
                                <?= e($item['item_name']) ?> (stok: <?= number_format((float) $item['qty_available'], 2, ',', '.') ?> <?= e($item['unit']) ?><?php if (array_key_exists('project_name', $item)): ?>, <?= e($item['project_name'] ?: 'Stok Kantor') ?><?php endif; ?>)
                            </option>
                        <?php endforeach; ?>
                        <?php if ($isEdit && !in_array($stockOut['inventory_id'], array_column($inventoryItems, 'id'))): ?>
                            <!-- item sedang diedit tapi stoknya sekarang 0 / sudah tidak masuk listByProject -->
                            <option value="<?= (int) $stockOut['inventory_id'] ?>" selected
                                data-qty="<?= e((string) ($stockOut['qty_available'] + $stockOut['qty'])) ?>"
                                data-unit="<?= e($stockOut['unit']) ?>">
                                <?= e($stockOut['item_name']) ?> (stok saat ini: <?= number_format((float) $stockOut['qty_available'], 2, ',', '.') ?> <?= e($stockOut['unit']) ?>)
                            </option>
                        <?php endif; ?>
                    </select>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="inventory_id" value="<?= (int) $stockOut['inventory_id'] ?>">
                    <?php endif; ?>
                    <div class="form-text" id="stockInfo">
                        <?php if (empty($inventoryItems) && !$isEdit): ?>
                            Pilih <?= $canClientDest ? 'Project atau Client (Invoice)' : 'Project' ?> dulu untuk melihat barang yang tersedia.
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Qty Keluar <span class="text-danger">*</span></label>
                    <input type="number" name="qty" id="qtyInput" class="form-control"
                           value="<?= e($stockOut['qty'] ?? '') ?>" min="0.01" step="0.01" required>
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

<script>
(function () {
    const projectSelect = document.getElementById('projectSelect');
    const invoiceSelect = document.getElementById('invoiceSelect');
    const projectFieldWrap = document.getElementById('projectFieldWrap');
    const invoiceFieldWrap = document.getElementById('invoiceFieldWrap');
    const destTypeRadios = document.querySelectorAll('input[name="destination_type"]');
    const itemSelect = document.getElementById('itemSelect');
    const qtyInput = document.getElementById('qtyInput');
    const stockInfo = document.getElementById('stockInfo');
    const isEdit = <?= $isEdit ? 'true' : 'false' ?>;

    function applyDestinationType(type) {
        const isProject = type !== 'client';
        if (projectFieldWrap) projectFieldWrap.style.display = isProject ? '' : 'none';
        if (invoiceFieldWrap) invoiceFieldWrap.style.display = isProject ? 'none' : '';
        if (!isEdit) {
            projectSelect.required = isProject;
            invoiceSelect.required = !isProject;
        }
    }

    function resetItemSelect() {
        itemSelect.innerHTML = '<option value="">-- Pilih Barang --</option>';
        itemSelect.disabled = true;
        stockInfo.textContent = '';
    }

    function loadItems(url, emptyMessage) {
        resetItemSelect();
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.items || data.items.length === 0) {
                    stockInfo.textContent = emptyMessage;
                    return;
                }
                data.items.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.id;
                    opt.dataset.qty = item.qty_available;
                    opt.dataset.unit = item.unit;
                    const origin = ('project_name' in item) ? (', ' + (item.project_name || 'Stok Kantor')) : '';
                    opt.textContent = item.item_name + ' (stok: '
                        + parseFloat(item.qty_available).toLocaleString('id-ID') + ' ' + item.unit + origin + ')';
                    itemSelect.appendChild(opt);
                });
                itemSelect.disabled = false;
            })
            .catch(function () {
                stockInfo.textContent = 'Gagal memuat daftar barang.';
            });
    }

    // Barang untuk tujuan Client MUNCUL SEMUA barang yang ada stoknya, LINTAS
    // project & Kantor (bukan cuma Stok Kantor -- barang dari PO biasanya
    // masuk ke bucket project saat diterima, lihat Inventory::listAllWithStock()),
    // tapi yang namanya cocok dengan item di invoice terpilih DIDAHULUKAN
    // urutannya (lihat StockOutController::markInvoiceMatches) -- sengaja tidak
    // difilter ketat karena mayoritas baris invoice teks bebas/jasa, filter
    // ketat bikin dropdown kosong untuk hampir semua invoice.
    function loadOfficeItems() {
        var url = '<?= BASE_URL ?>/index.php?module=stock_out&action=ajaxItemsByOffice';
        if (invoiceSelect.value) {
            url += '&sales_invoice_id=' + invoiceSelect.value;
        }
        loadItems(url, 'Tidak ada barang dengan stok tersedia.');
    }

    invoiceSelect.addEventListener('change', function () {
        if (isEdit) return;
        loadOfficeItems();
    });

    // Ganti Tujuan Pengeluaran (Project <-> Client/Invoice): tukar field mana
    // yang ditampilkan+wajib, kosongkan pilihan satunya, dan muat ulang Barang --
    // barang untuk Client diambil dari Stok Kantor, beda dari Project yang
    // barangnya per-project.
    destTypeRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (isEdit) return;
            applyDestinationType(this.value);

            if (this.value === 'client') {
                projectSelect.value = '';
                loadOfficeItems();
            } else {
                invoiceSelect.value = '';
                resetItemSelect();
            }
        });
    });

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

    // AJAX: setiap ganti project, muat ulang daftar barang yang ada stoknya di project itu
    projectSelect.addEventListener('change', function () {
        // Saat edit, PO/project & item sengaja tidak boleh diganti supaya konsisten
        // dengan histori stok yang sudah tercatat -- lihat catatan di bawah form.
        if (isEdit) return;

        const projectId = this.value;
        if (!projectId) {
            resetItemSelect();
            return;
        }
        loadItems('<?= BASE_URL ?>/index.php?module=stock_out&action=ajaxItemsByProject&project_id=' + projectId,
            'Tidak ada barang dengan stok tersedia di project ini.');
    });

    itemSelect.addEventListener('change', updateStockInfo);

    // Validasi akhir di client: qty tidak boleh melebihi stok (validasi SEBENARNYA tetap di server)
    document.getElementById('stockOutForm').addEventListener('submit', function (e) {
        const max = parseFloat(qtyInput.getAttribute('max') || 'Infinity');
        const qty = parseFloat(qtyInput.value || '0');
        if (qty > max) {
            e.preventDefault();
            alert('Qty keluar (' + qty + ') melebihi stok tersedia (' + max + ').');
        }
    });

    applyDestinationType('<?= e($destType) ?>');
    updateStockInfo();
})();
</script>

<?php if ($isEdit): ?>
    <p class="text-muted small mt-3">
        <i class="bi bi-info-circle"></i>
        Tujuan Pengeluaran, Project/Client, & Barang tidak bisa diganti saat edit untuk menjaga konsistensi histori kartu stok.
        Hapus data ini dan buat pengeluaran baru jika tujuannya salah.
    </p>
<?php endif; ?>
