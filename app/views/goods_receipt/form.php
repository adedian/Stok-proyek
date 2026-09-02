<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$selectedPoId = $selectedPo['id'] ?? ($selectedPo['purchase_order_id'] ?? '');
$selectedOfflinePurchaseId = $selectedOfflinePurchase['id'] ?? ($selectedOfflinePurchase['offline_purchase_id'] ?? '');
$receiptType = $receipt['receipt_type'] ?? ($defaultReceiptType ?? 'purchase_order');
$offlinePurchaseList = $offlinePurchaseList ?? [];
$offlineItems = $offlineItems ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Penerimaan Barang</h4>
        <small class="text-muted">No. Penerimaan: <strong><?= e($receiptNumber) ?></strong> (otomatis)</small>
    </div>
    <a href="<?= BASE_URL ?>/goods_receipt" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST"
      action="<?= BASE_URL ?>/index.php?module=goods_receipt&action=<?= $actionUrl ?>"
      enctype="multipart/form-data" id="grForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $receipt['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <?php if (!$isEdit): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">Sumber Penerimaan <span class="text-danger">*</span></label>
                    <select name="receipt_type" id="receiptTypeSelect" class="form-select">
                        <option value="purchase_order" <?= $receiptType === 'purchase_order' ? 'selected' : '' ?>>Dari Purchase Order (Supplier)</option>
                        <option value="offline_purchase" <?= $receiptType === 'offline_purchase' ? 'selected' : '' ?>>Dari Pembelian Offline</option>
                        <option value="pemakai" <?= $receiptType === 'pemakai' ? 'selected' : '' ?>>Dari Pemakai/Internal</option>
                    </select>
                    <div class="form-text">Barang dari Pembelian Offline (di luar mekanisme PO) dan yang dikembalikan/diserahkan oleh pemakai internal dicatat lewat 2 opsi terakhir.</div>
                </div>
                <div class="col-md-6" id="stockScopeWrapper" style="display:none;">
                    <label class="form-label">Kategori Stok <span class="text-danger">*</span></label>
                    <select name="stock_scope" class="form-select">
                        <option value="proyek">Stok Proyek</option>
                        <option value="kantor">Stok Kantor</option>
                    </select>
                </div>
            </div>
            <?php else: ?>
                <input type="hidden" name="receipt_type" value="<?= e($receiptType) ?>">
            <?php endif; ?>

            <div class="row g-3" id="poFields" <?= (!$isEdit && $receiptType !== 'purchase_order') ? 'style="display:none;"' : '' ?>>
                <div class="col-md-5">
                    <label class="form-label">Purchase Order <span class="text-danger" id="poRequiredMark">*</span></label>
                    <select name="purchase_order_id" id="poSelect" class="form-select"
                            <?= $isEdit ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Purchase Order --</option>
                        <?php foreach ($poList as $po): ?>
                            <option value="<?= (int) $po['id'] ?>" <?= (string) $selectedPoId === (string) $po['id'] ? 'selected' : '' ?>>
                                <?= e($po['po_number']) ?> &mdash; <?= e($po['supplier_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="purchase_order_id" value="<?= (int) $receipt['purchase_order_id'] ?>">
                        <div class="form-text">PO tidak bisa diganti saat edit. Hapus penerimaan ini dan buat baru jika salah PO.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3" id="offlinePurchaseFields" <?= (!$isEdit && $receiptType !== 'offline_purchase') ? 'style="display:none;"' : '' ?>>
                <div class="col-md-5">
                    <label class="form-label">Pembelian Offline <span class="text-danger" id="offlinePurchaseRequiredMark">*</span></label>
                    <select name="offline_purchase_id" id="offlinePurchaseSelect" class="form-select"
                            <?= $isEdit ? 'disabled' : '' ?>>
                        <option value="">-- Pilih Pembelian Offline --</option>
                        <?php foreach ($offlinePurchaseList as $op): ?>
                            <option value="<?= (int) $op['id'] ?>" <?= (string) $selectedOfflinePurchaseId === (string) $op['id'] ? 'selected' : '' ?>>
                                <?= e($op['purchase_number']) ?> &mdash; <?= e($op['supplier_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="offline_purchase_id" value="<?= (int) $receipt['offline_purchase_id'] ?>">
                        <div class="form-text">Pembelian Offline tidak bisa diganti saat edit. Hapus penerimaan ini dan buat baru jika salah.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row g-3" id="pemakaiFields" style="display:none;">
                <div class="col-md-6">
                    <label class="form-label">Nama Pemakai / Departemen <span class="text-danger">*</span></label>
                    <input type="text" name="source_detail" class="form-control" placeholder="Contoh: Budi (Site Proyek A) / Gudang Kantor">
                </div>
                <div class="col-md-6" id="pemakaiProjectWrapper">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="">-- Pilih Project --</option>
                        <?php foreach ($projects ?? [] as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= e($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Datang <span class="text-danger">*</span></label>
                    <input type="date" name="receipt_date" class="form-control"
                           value="<?= e($receipt['receipt_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Penerima <span class="text-danger">*</span></label>
                    <input type="text" name="receiver_name" class="form-control"
                           value="<?= e($receipt['received_by_name'] ?? '') ?>" placeholder="Contoh: Budi Santoso" required>
                    <div class="form-text">Nama orang yang secara langsung menerima barang (boleh bukan pengguna sistem).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Foto Barang</label>
                    <input type="file" name="photo_goods" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                    <?php if ($isEdit && !empty($receipt['photo_goods'])): ?>
                        <div class="form-text">
                            Foto saat ini: <a href="<?= BASE_URL ?>/<?= e($receipt['photo_goods']) ?>" target="_blank">lihat foto</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Upload Invoice <span class="text-muted small">(opsional, PDF/JPG/PNG)</span></label>
                    <input type="file" name="invoice_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <?php if ($isEdit && !empty($receipt['invoice_file'])): ?>
                        <div class="form-text">
                            Invoice saat ini: <a href="<?= e(fileUrl($receipt['invoice_file'])) ?>" target="_blank">lihat file</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">No. Surat Jalan</label>
                    <input type="text" name="document_number" class="form-control" placeholder="Opsional">
                </div>
                <div class="col-12">
                    <label class="form-label">Upload Foto Surat Jalan (bisa lebih dari 1)</label>
                    <input type="file" name="delivery_documents[]" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple>
                    <?php if ($isEdit && !empty($documents)): ?>
                        <div class="form-text">
                            Dokumen tersimpan:
                            <?php foreach ($documents as $doc): ?>
                                <a href="<?= BASE_URL ?>/<?= e($doc['file_path']) ?>" target="_blank" class="me-2">
                                    <i class="bi bi-file-earmark"></i> <?= e($doc['document_number'] ?? 'lihat') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" value="<?= e($receipt['notes'] ?? '') ?>" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="poItemsCard" <?= (!$isEdit && $receiptType !== 'purchase_order') ? 'style="display:none;"' : '' ?>>
        <div class="card-body">
            <h6 class="mb-3">Item Barang &amp; Qty Diterima</h6>
            <div id="poItemsWrapper">
                <?php if (empty($poItems)): ?>
                    <p class="text-muted small" id="poItemsEmptyMsg">Pilih Purchase Order terlebih dahulu untuk menampilkan daftar item.</p>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle <?= empty($poItems) ? 'd-none' : '' ?>" id="poItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Barang (PO)</th>
                                <th>Satuan (PO)</th>
                                <th class="text-end">Qty PO</th>
                                <th class="text-end">Sudah Diterima</th>
                                <th class="text-end">Sisa</th>
                                <th style="width: 140px;">Qty Diterima Sekarang</th>
                            </tr>
                        </thead>
                        <tbody id="poItemsBody">
                            <?php foreach ($poItems as $index => $poItem): ?>
                                <?php include ROOT_PATH . '/app/views/goods_receipt/_item_row.php'; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="offlineItemsCard" <?= (!$isEdit && $receiptType !== 'offline_purchase') ? 'style="display:none;"' : '' ?>>
        <div class="card-body">
            <h6 class="mb-3">Item Barang &amp; Qty Diterima</h6>
            <div id="offlineItemsWrapper">
                <?php if (empty($offlineItems)): ?>
                    <p class="text-muted small" id="offlineItemsEmptyMsg">Pilih Pembelian Offline terlebih dahulu untuk menampilkan daftar item.</p>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle <?= empty($offlineItems) ? 'd-none' : '' ?>" id="offlineItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Barang</th>
                                <th>Satuan</th>
                                <th class="text-end">Qty Dibeli</th>
                                <th class="text-end">Sudah Diterima</th>
                                <th class="text-end">Sisa</th>
                                <th style="width: 140px;">Qty Diterima Sekarang</th>
                            </tr>
                        </thead>
                        <tbody id="offlineItemsBody">
                            <?php foreach ($offlineItems as $index => $offlineItem): ?>
                                <?php include ROOT_PATH . '/app/views/goods_receipt/_offline_item_row.php'; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="mismatchItemsCard" <?= (!$isEdit && !in_array($receiptType, ['purchase_order', 'offline_purchase'], true)) ? 'style="display:none;"' : '' ?>>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0">Barang Tidak Sesuai <span class="text-muted small fw-normal">(opsional)</span></h6>
                <button type="button" id="btnAddMismatchItem" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-plus-circle"></i> Tambah Barang
                </button>
            </div>
            <p class="text-muted small mb-3">
                Jangan diisi di tabel item di atas. Kalau ada barang yang datang tapi
                sama sekali tidak sesuai/tidak tercantum di PO atau Pembelian Offline ini
                (mis. pesan "Kabel NYY" tapi yang datang "Kabel NYM"), tambahkan sebagai baris
                terpisah di sini -- otomatis ditandai
                <span class="badge bg-dark">Barang Lain</span> untuk ditindaklanjuti di modul Validasi.
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle <?= empty($mismatchItems) ? 'd-none' : '' ?>" id="mismatchItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Barang</th>
                            <th style="width: 120px;">Satuan</th>
                            <th style="width: 120px;">Qty</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="mismatchItemsBody">
                        <?php foreach ($mismatchItems ?? [] as $mi): ?>
                            <tr class="mismatch-item-row">
                                <td><input type="text" name="mismatch_item_name[]" class="form-control form-control-sm" value="<?= e($mi['item_name']) ?>"></td>
                                <td>
                                    <select name="mismatch_unit[]" class="form-select form-select-sm">
                                        <option value="">-- Satuan --</option>
                                        <?php $mismatchUnitMatched = false; ?>
                                        <?php foreach ($units as $u): ?>
                                            <?php $isSel = mb_strtolower($u['unit_name']) === mb_strtolower($mi['unit'] ?? ''); if ($isSel) $mismatchUnitMatched = true; ?>
                                            <option value="<?= e($u['unit_name']) ?>" <?= $isSel ? 'selected' : '' ?>><?= e($u['unit_name']) ?></option>
                                        <?php endforeach; ?>
                                        <?php if (!$mismatchUnitMatched && !empty($mi['unit'])): ?>
                                            <option value="<?= e($mi['unit']) ?>" selected><?= e($mi['unit']) ?> (lama)</option>
                                        <?php endif; ?>
                                    </select>
                                </td>
                                <td><input type="number" name="mismatch_qty[]" class="form-control form-control-sm" min="0" step="0.01" value="<?= e($mi['qty_received']) ?>"></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-mismatch-row" title="Hapus baris">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="pemakaiItemsCard" style="display:none;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Item Barang &amp; Qty</h6>
                <button type="button" id="btnAddPemakaiItem" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Tambah Baris
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="pemakaiItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Barang</th>
                            <th style="width: 120px;">Satuan</th>
                            <th style="width: 120px;">Qty</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="pemakaiItemsBody">
                        <tr class="pemakai-item-row">
                            <td><input type="text" name="pemakai_item_name[]" class="form-control form-control-sm" placeholder="Nama barang"></td>
                            <td>
                                <select name="pemakai_unit[]" class="form-select form-select-sm">
                                    <option value="">-- Satuan --</option>
                                    <?php foreach ($units as $u): ?>
                                        <option value="<?= e($u['unit_name']) ?>"><?= e($u['unit_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input type="number" name="pemakai_qty[]" class="form-control form-control-sm" min="0" step="0.01" placeholder="0"></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-pemakai-row" title="Hapus baris">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Simpan
        </button>
        <a href="<?= BASE_URL ?>/goods_receipt" class="btn btn-light border">Batal</a>
    </div>
</form>

<script>
(function () {
    // Tambah/hapus baris "Barang Tidak Sesuai PO" -- berlaku di form create maupun edit
    const mismatchTable = document.getElementById('mismatchItemsTable');
    const mismatchBody = document.getElementById('mismatchItemsBody');
    const btnAddMismatchItem = document.getElementById('btnAddMismatchItem');
    const unitOptionsHtml = '<option value="">-- Satuan --</option>' +
        <?= json_encode($units) ?>.map(function (u) {
            return '<option value="' + u.unit_name.replace(/"/g, '&quot;') + '">' + u.unit_name + '</option>';
        }).join('');

    function addMismatchRow() {
        const row = document.createElement('tr');
        row.className = 'mismatch-item-row';
        row.innerHTML =
            '<td><input type="text" name="mismatch_item_name[]" class="form-control form-control-sm"></td>' +
            '<td><select name="mismatch_unit[]" class="form-select form-select-sm">' + unitOptionsHtml + '</select></td>' +
            '<td><input type="number" name="mismatch_qty[]" class="form-control form-control-sm" min="0" step="0.01"></td>' +
            '<td><button type="button" class="btn btn-sm btn-outline-danger btn-remove-mismatch-row" title="Hapus baris"><i class="bi bi-trash"></i></button></td>';
        mismatchBody.appendChild(row);
        mismatchTable.classList.remove('d-none');
    }

    if (btnAddMismatchItem) {
        btnAddMismatchItem.addEventListener('click', addMismatchRow);
    }
    if (mismatchBody) {
        mismatchBody.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-remove-mismatch-row');
            if (!btn) return;
            btn.closest('.mismatch-item-row').remove();
            if (!mismatchBody.querySelector('.mismatch-item-row')) {
                mismatchTable.classList.add('d-none');
            }
        });
    }
})();
</script>

<?php if (!$isEdit): ?>
<script>
(function () {
    // Toggle tampilan alur PO vs Pembelian Offline vs Pemakai + wajib/tidaknya field terkait
    const typeSelect = document.getElementById('receiptTypeSelect');
    const poFields = document.getElementById('poFields');
    const offlinePurchaseFields = document.getElementById('offlinePurchaseFields');
    const pemakaiFields = document.getElementById('pemakaiFields');
    const stockScopeWrapper = document.getElementById('stockScopeWrapper');
    const poItemsCard = document.getElementById('poItemsCard');
    const offlineItemsCard = document.getElementById('offlineItemsCard');
    const mismatchItemsCard = document.getElementById('mismatchItemsCard');
    const pemakaiItemsCard = document.getElementById('pemakaiItemsCard');
    const poSelectEl = document.getElementById('poSelect');
    const offlinePurchaseSelectEl = document.getElementById('offlinePurchaseSelect');

    function applyReceiptType() {
        const type = typeSelect.value;
        const isPo = type === 'purchase_order';
        const isOffline = type === 'offline_purchase';
        const isPemakai = type === 'pemakai';

        poFields.style.display = isPo ? '' : 'none';
        offlinePurchaseFields.style.display = isOffline ? '' : 'none';
        pemakaiFields.style.display = isPemakai ? '' : 'none';
        stockScopeWrapper.style.display = isPemakai ? '' : 'none';
        poItemsCard.style.display = isPo ? '' : 'none';
        offlineItemsCard.style.display = isOffline ? '' : 'none';
        mismatchItemsCard.style.display = (isPo || isOffline) ? '' : 'none';
        pemakaiItemsCard.style.display = isPemakai ? '' : 'none';
        if (poSelectEl) poSelectEl.required = isPo;
        if (offlinePurchaseSelectEl) offlinePurchaseSelectEl.required = isOffline;
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', applyReceiptType);
        applyReceiptType();
    }

    // Tambah/hapus baris item Pemakai (client-side saja, tidak perlu AJAX)
    const pemakaiBody = document.getElementById('pemakaiItemsBody');
    const btnAddPemakaiItem = document.getElementById('btnAddPemakaiItem');
    if (btnAddPemakaiItem) {
        btnAddPemakaiItem.addEventListener('click', function () {
            const row = pemakaiBody.querySelector('.pemakai-item-row').cloneNode(true);
            row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
            row.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
            pemakaiBody.appendChild(row);
        });
    }
    if (pemakaiBody) {
        pemakaiBody.addEventListener('click', function (e) {
            const btn = e.target.closest('.btn-remove-pemakai-row');
            if (!btn) return;
            const rows = pemakaiBody.querySelectorAll('.pemakai-item-row');
            if (rows.length <= 1) {
                alert('Minimal harus ada 1 baris item.');
                return;
            }
            btn.closest('.pemakai-item-row').remove();
        });
    }
})();
</script>
<script>
(function () {
    const poSelect = document.getElementById('poSelect');
    const poItemsTable = document.getElementById('poItemsTable');
    const poItemsBody = document.getElementById('poItemsBody');
    const emptyMsg = document.getElementById('poItemsEmptyMsg');

    // AJAX: setiap kali user pilih PO, muat daftar item + sisa qty dari server
    poSelect.addEventListener('change', function () {
        const poId = this.value;
        poItemsBody.innerHTML = '';

        if (!poId) {
            poItemsTable.classList.add('d-none');
            if (emptyMsg) emptyMsg.classList.remove('d-none');
            return;
        }

        const url = '<?= BASE_URL ?>/index.php?module=goods_receipt&action=ajaxPoItems&po_id=' + poId;
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                poItemsBody.innerHTML = data.html;
                if (data.count > 0) {
                    poItemsTable.classList.remove('d-none');
                    if (emptyMsg) emptyMsg.classList.add('d-none');
                } else {
                    poItemsTable.classList.add('d-none');
                    if (emptyMsg) {
                        emptyMsg.textContent = 'PO ini tidak memiliki item.';
                        emptyMsg.classList.remove('d-none');
                    }
                }
            })
            .catch(function () {
                alert('Gagal memuat item PO. Silakan coba lagi.');
            });
    });
})();
</script>
<script>
(function () {
    const offlinePurchaseSelect = document.getElementById('offlinePurchaseSelect');
    const offlineItemsTable = document.getElementById('offlineItemsTable');
    const offlineItemsBody = document.getElementById('offlineItemsBody');
    const emptyMsg = document.getElementById('offlineItemsEmptyMsg');

    // AJAX: setiap kali user pilih Pembelian Offline, muat daftar item + sisa qty dari server
    offlinePurchaseSelect.addEventListener('change', function () {
        const offlinePurchaseId = this.value;
        offlineItemsBody.innerHTML = '';

        if (!offlinePurchaseId) {
            offlineItemsTable.classList.add('d-none');
            if (emptyMsg) emptyMsg.classList.remove('d-none');
            return;
        }

        const url = '<?= BASE_URL ?>/index.php?module=goods_receipt&action=ajaxOfflinePurchaseItems&offline_purchase_id=' + offlinePurchaseId;
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                offlineItemsBody.innerHTML = data.html;
                if (data.count > 0) {
                    offlineItemsTable.classList.remove('d-none');
                    if (emptyMsg) emptyMsg.classList.add('d-none');
                } else {
                    offlineItemsTable.classList.add('d-none');
                    if (emptyMsg) {
                        emptyMsg.textContent = 'Pembelian offline ini tidak memiliki item.';
                        emptyMsg.classList.remove('d-none');
                    }
                }
            })
            .catch(function () {
                alert('Gagal memuat item Pembelian Offline. Silakan coba lagi.');
            });
    });
})();
</script>
<?php endif; ?>
