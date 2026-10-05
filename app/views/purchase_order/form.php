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
                    <label class="form-label">Mata Uang <span class="text-danger">*</span></label>
                    <?php $poCurrency = normalizeCurrency($po['currency'] ?? 'IDR'); ?>
                    <select name="currency" id="po_currency" class="form-select" required>
                        <?php foreach (($currencies ?? poCurrencies()) as $cur): ?>
                            <option value="<?= e($cur) ?>" <?= $poCurrency === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Hanya label mata uang &mdash; nominal tidak dikonversi.</div>
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
                    <label class="form-label">Penerima Barang <span class="text-danger">*</span></label>
                    <select name="receiver_user_id" id="receiver_user_id" class="form-select">
                        <option value="">-- Pilih Penerima Barang --</option>
                        <?php foreach (($receivers ?? []) as $r): ?>
                            <option value="<?= (int) $r['id'] ?>"
                                <?= ($po && (int) ($po['receiver_user_id'] ?? 0) === (int) $r['id']) ? 'selected' : '' ?>>
                                <?= e($r['full_name']) ?> (<?= e($r['role_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback d-block text-danger small" id="receiverError" style="display:none !important;">Penerima Barang wajib dipilih.</div>
                    <div class="form-text">Hanya user ini yang melihat PO ini di Tambah Penerimaan Barang.</div>
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
                    <?php if (!empty($mySignature)): ?>
                        <div class="form-control-plaintext small">
                            <i class="bi bi-pen text-success"></i>
                            Otomatis: <strong><?= e($mySignature['name']) ?></strong> (<?= e($mySignature['position']) ?>)
                        </div>
                    <?php else: ?>
                        <div class="form-control-plaintext small text-muted">
                            <i class="bi bi-exclamation-circle"></i> Anda belum mengatur tanda tangan pribadi.
                        </div>
                    <?php endif; ?>
                    <div class="form-text">
                        Diambil otomatis dari tanda tangan akun Anda &mdash; atur di
                        <a href="<?= BASE_URL ?>/account" target="_blank">Profile &raquo; Tanda Tangan Saya</a>.
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <?php
                    $statusLocked = $po && (!empty($po['approved_at']) || in_array($po['status'] ?? '', ['approved', 'partial_received', 'completed'], true))
                        && !hasRole([ROLE_SUPER_ADMIN]);
                    ?>
                    <select name="status" class="form-select" <?= $statusLocked ? 'disabled' : '' ?>>
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
                        $canApprove = can('purchase_order', 'approve');
                        foreach ($statusOptions as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $currentStatus === $key ? 'selected' : '' ?>
                                <?= ($key === 'approved' && !$canApprove) ? 'disabled' : '' ?>>
                                <?= e($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($statusLocked): ?>
                        <div class="form-text text-warning">
                            <i class="bi bi-lock-fill"></i> PO sudah disetujui &mdash; status hanya bisa diubah Super Admin.
                        </div>
                    <?php elseif (!$canApprove): ?>
                        <div class="form-text">Ubah ke "Disetujui" lewat tombol Setujui PO di halaman Detail.</div>
                    <?php endif; ?>
                    <?php if (!empty($po['approved_at']) && hasRole([ROLE_SUPER_ADMIN])): ?>
                        <div class="form-text text-warning">
                            <i class="bi bi-lock-fill"></i> PO ini sudah disetujui &mdash; Anda mengubah status sebagai Super Admin.
                        </div>
                    <?php endif; ?>
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
                    <textarea name="notes" class="form-control" rows="3"
                              placeholder="Opsional -- tekan Enter untuk baris baru (mis. catatan bernomor 1, 2, 3)"><?= e($po['notes'] ?? '') ?></textarea>
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
                <table class="table table-sm align-middle entry-cards" id="itemTable">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Barang</th>
                            <th>Kode Barang</th>
                            <th>Kategori</th>
                            <th>Satuan</th>
                            <th>Qty</th>
                            <th>Harga Satuan</th>
                            <th>Diskon (%)</th>
                            <th>PPN</th>
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
                            <td colspan="8" class="text-end fw-bold">Subtotal Barang</td>
                            <td class="text-end fw-bold" id="itemsSubtotal"><?= e(formatMoney(0, $po['currency'] ?? 'IDR')) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php
            // Opsi kategori untuk dropdown per baris item (input list + datalist):
            // gabungan master "Kategori Barang" + beberapa contoh bawaan, tetap
            // bisa diketik sendiri (free text).
            $categoryOptions = array_map(fn($c) => $c['category_name'], $itemCategories ?? []);
            foreach (['Stok Kantor', 'Stok Lampu', 'Inventory Kantor'] as $default) {
                if (!in_array($default, $categoryOptions, true)) {
                    $categoryOptions[] = $default;
                }
            }
            sort($categoryOptions, SORT_NATURAL | SORT_FLAG_CASE);
            ?>
            <datalist id="poCategoryOptions">
                <?php foreach ($categoryOptions as $opt): ?>
                    <option value="<?= e($opt) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Biaya Tambahan</h6>
                <button type="button" id="btnAddExtraCost" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Tambah Biaya
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards" id="extraCostTable">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Biaya</th>
                            <th class="text-end" style="width: 200px;">Jumlah</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="extraCostTableBody">
                        <?php foreach ($extraCosts as $cost): ?>
                            <tr class="extra-cost-row">
                                <td>
                                    <input type="text" name="extra_cost_name[]" class="form-control form-control-sm"
                                           value="<?= e($cost['cost_name']) ?>" placeholder="mis. Ongkir, Biaya Bongkar">
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text po-currency-label"><?= e($poCurrency) ?></span>
                                        <input type="text" name="extra_cost_amount[]" class="form-control form-control-sm extra-cost-amount-input currency-input"
                                               inputmode="numeric" value="<?= e(number_format((float) $cost['amount'], 2, '.', ',')) ?>" placeholder="0">
                                    </div>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-extra-cost">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($extraCosts)): ?>
                    <p class="text-muted small mb-0" id="extraCostEmptyHint">Tidak ada biaya tambahan.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Grand Total</h6>
            <div class="fs-5 fw-bold" id="grandTotal"><?= e(formatMoney(0, $po['currency'] ?? 'IDR')) ?></div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Simpan
        </button>
        <a href="<?= BASE_URL ?>/purchase_order" class="btn btn-light border">Batal</a>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('poForm');
    var sel = document.getElementById('receiver_user_id');
    var err = document.getElementById('receiverError');
    if (!form || !sel || !err) { return; }
    function showErr(show) { err.style.setProperty('display', show ? 'block' : 'none', 'important'); }
    form.addEventListener('submit', function (e) {
        if (!sel.value) {
            e.preventDefault();
            e.stopImmediatePropagation();
            showErr(true);
            sel.scrollIntoView({ block: 'center' });
        }
    }, true);
    sel.addEventListener('change', function () { if (sel.value) { showErr(false); } });
})();
</script>

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
    const itemsSubtotalEl = document.getElementById('itemsSubtotal');
    const grandTotalEl = document.getElementById('grandTotal');
    const extraCostBody = document.getElementById('extraCostTableBody');
    const btnAddExtraCost = document.getElementById('btnAddExtraCost');
    let rowIndex = tableBody.querySelectorAll('.item-row').length;

    // Mata uang PO = LABEL saja: nilai input/total TIDAK pernah dikonversi, hanya
    // prefix yang berganti saat dropdown Mata Uang diubah (realtime, tanpa reload).
    const currencyEl = document.getElementById('po_currency');
    function currentCurrency() {
        return (currencyEl && currencyEl.value) ? currencyEl.value : 'IDR';
    }
    function formatMoney(num) {
        // Format nominal: koma ribuan, titik desimal, selalu 2 digit desimal --
        // HARUS sinkron dengan formatMoney() PHP (app/helpers/functions.php).
        return currentCurrency() + ' ' + Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function syncCurrencyLabels() {
        document.querySelectorAll('.po-currency-label').forEach(function (el) { el.textContent = currentCurrency(); });
    }

    // Qty/Diskon/PPN% qty-style: user boleh ketik koma desimal ("0,5") -- server
    // sudah dinormalisasi lewat parseQtyInput(), di sini cukup terima keduanya
    // supaya preview subtotal live tidak salah tampil (lihat catatan bug qty koma).
    function parseQtyLike(str) {
        str = (str || '').trim();
        if (str === '') return 0;
        if (str.indexOf(',') !== -1) {
            str = str.replace(/\./g, '').replace(',', '.');
        }
        const n = parseFloat(str);
        return isNaN(n) ? 0 : n;
    }

    function recalcRow(row) {
        const qty = parseQtyLike(row.querySelector('.qty-input').value);
        // Input harga sekarang format "15,000.73" -- buang koma (ribuan), titik tetap
        // dipertahankan sebagai desimal (lihat currency-input.js).
        const price = parseFloat((row.querySelector('.price-input').value || '').replace(/,/g, '')) || 0;
        const discountPercent = Math.min(100, Math.max(0, parseQtyLike(row.querySelector('.discount-input').value)));
        const ppnEnabled = row.querySelector('.ppn-toggle-visible').checked;
        const ppnPercent = ppnEnabled ? Math.min(100, Math.max(0, parseQtyLike(row.querySelector('.ppn-percent-input').value))) : 0;

        const afterDiscount = qty * price * (1 - discountPercent / 100);
        const ppnAmount = ppnEnabled ? afterDiscount * (ppnPercent / 100) : 0;
        const subtotal = afterDiscount + ppnAmount;
        row.querySelector('.subtotal-cell').textContent = formatMoney(subtotal);
        return subtotal;
    }

    function extraCostTotal() {
        let total = 0;
        if (extraCostBody) {
            extraCostBody.querySelectorAll('.extra-cost-amount-input').forEach(function (input) {
                total += parseFloat((input.value || '').replace(/,/g, '')) || 0;
            });
        }
        return total;
    }

    function recalcAll() {
        let itemsTotal = 0;
        tableBody.querySelectorAll('.item-row').forEach(function (row) {
            itemsTotal += recalcRow(row);
        });
        itemsSubtotalEl.textContent = formatMoney(itemsTotal);
        grandTotalEl.textContent = formatMoney(itemsTotal + extraCostTotal());
        syncCurrencyLabels();
    }

    // Delegasi event untuk input qty/price/diskon/ppn yang bisa bertambah secara dinamis
    // PPN per baris: input persen HANYA aktif kalau checkbox dicentang, hanya
    // menerima angka (boleh 1 pemisah desimal), dan nilainya disalin ke hidden
    // ppn_percent[] yang selalu ter-submit (kosong kalau PPN mati).
    function syncPpnRow(row) {
        const toggle = row.querySelector('.ppn-toggle-visible');
        const enabledHidden = row.querySelector('.ppn-enabled-input');
        const percentInput = row.querySelector('.ppn-percent-input');
        const percentHidden = row.querySelector('.ppn-percent-hidden');
        if (!toggle || !percentInput || !percentHidden) return;
        if (toggle.checked) {
            percentInput.disabled = false;
            enabledHidden.value = '1';
            percentHidden.value = percentInput.value;
        } else {
            percentInput.value = '';
            percentInput.disabled = true;
            enabledHidden.value = '';
            percentHidden.value = '';
        }
    }
    function sanitizePpn(str) {
        str = (str || '').replace(/[^0-9.,]/g, '');
        const m = str.match(/[.,]/);
        if (!m) return str;
        const idx = m.index;
        return str.slice(0, idx + 1) + str.slice(idx + 1).replace(/[.,]/g, '');
    }
    tableBody.querySelectorAll('.item-row').forEach(syncPpnRow);

    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('ppn-percent-input')) {
            const clean = sanitizePpn(e.target.value);
            if (clean !== e.target.value) e.target.value = clean;
            e.target.closest('tr').querySelector('.ppn-percent-hidden').value = clean;
        }
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')
            || e.target.classList.contains('discount-input') || e.target.classList.contains('ppn-percent-input')) {
            recalcAll();
        }
    });
    tableBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('ppn-toggle-visible')) {
            const row = e.target.closest('tr');
            syncPpnRow(row);
            if (e.target.checked) row.querySelector('.ppn-percent-input').focus();
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

    // Biaya tambahan: tambah/hapus baris + ikut pengaruhi Grand Total
    if (btnAddExtraCost) {
        btnAddExtraCost.addEventListener('click', function () {
            const hint = document.getElementById('extraCostEmptyHint');
            if (hint) hint.style.display = 'none';
            const tr = document.createElement('tr');
            tr.className = 'extra-cost-row';
            tr.innerHTML =
                '<td><input type="text" name="extra_cost_name[]" class="form-control form-control-sm" placeholder="mis. Ongkir, Biaya Bongkar"></td>'
                + '<td><div class="input-group input-group-sm"><span class="input-group-text po-currency-label"></span><input type="text" name="extra_cost_amount[]" class="form-control form-control-sm extra-cost-amount-input currency-input" inputmode="numeric" placeholder="0"></div></td>'
                + '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-extra-cost"><i class="bi bi-trash"></i></button></td>';
            extraCostBody.appendChild(tr);
            syncCurrencyLabels();
        });
    }
    if (extraCostBody) {
        extraCostBody.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.btn-remove-extra-cost');
            if (!removeBtn) return;
            removeBtn.closest('.extra-cost-row').remove();
            recalcAll();
        });
        extraCostBody.addEventListener('input', function (e) {
            if (e.target.classList.contains('extra-cost-amount-input')) {
                recalcAll();
            }
        });
    }

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

    if (currencyEl) {
        currencyEl.addEventListener('change', recalcAll);
    }
    recalcAll();
})();
</script>
