<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? 'update' : 'store';
$backUrl = BASE_URL . '/sales_order' . ($isEdit ? '/detail/' . (int) $order['id'] : '');
$isDraft = $isEdit && ($order['status'] ?? '') === SalesOrder::DRAFT;
$rows = $items ?: [[
    'id' => 0, 'item_id' => '', 'qty' => '', 'unit_price' => '', 'discount_percent' => 0,
    'fulfilled_qty' => 0, 'invoiced_qty' => 0,
]];
$fmtQty = fn($q) => $q === '' ? '' : rtrim(rtrim(number_format((float) $q, 2, ',', ''), '0'), ',');
$fmtPrice = fn($p) => $p === '' ? '' : number_format((float) $p, 2, '.', ',');
$catalogById = [];
foreach ($itemCatalog as $ic) {
    $catalogById[(int) $ic['id']] = $ic;
}
$lockedClient = $isEdit && !empty($isProcessed);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Order Penjualan</h4>
        <small class="text-muted">No. Order: <strong><?= e($orderNumber) ?></strong> <?= $isEdit ? '' : '(otomatis, dibuat saat disimpan)' ?></small>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<?php if ($isEdit && !empty($isProcessed)): ?>
    <div class="alert alert-warning small">
        <i class="bi bi-lock-fill"></i> Order ini sudah diproses Accounting (ada pemenuhan atau invoice). Hanya Super Admin yang bisa mengubahnya, dan
        barang yang sudah dikeluarkan/ditagih tidak bisa dihapus, diganti, atau diturunkan qty-nya di bawah jumlah tersebut. Perubahan dicatat di Riwayat.
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=sales_order&action=<?= $action ?>" id="soForm" novalidate>
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $order['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-3">Informasi Customer &amp; Dokumen</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Order Penjualan <span class="text-danger">*</span></label>
                    <input type="date" name="order_date" class="form-control" required value="<?= e($order['order_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nama Customer <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="client_id" id="so_client_id" class="form-select" required <?= $lockedClient ? 'disabled' : '' ?>>
                            <option value="">-- Pilih Customer --</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                        data-address="<?= e($c['address'] ?? '') ?>" data-phone="<?= e($c['phone'] ?? '') ?>"
                                        <?= (int) ($order['client_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['client_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (canQuickAdd('client') && !$lockedClient): ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddClient" title="Tambah Customer Cepat">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if ($lockedClient): ?>
                        <input type="hidden" name="client_id" value="<?= (int) $order['client_id'] ?>">
                        <div class="form-text">Customer terkunci karena order sudah diproses.</div>
                    <?php else: ?>
                        <div class="form-text">Memakai Master Client yang sudah ada.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">PIC Purchase (Pembuat)</label>
                    <input type="text" class="form-control" readonly value="<?= e($isEdit ? ($order['created_by_name'] ?? '-') : currentUserName()) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nomor PO Customer <span class="text-danger">*</span></label>
                    <input type="text" name="po_number" class="form-control" maxlength="100" required autocomplete="off"
                           value="<?= e($order['po_number'] ?? '') ?>" placeholder="nomor PO dari customer">
                    <div class="form-text">Nomor PO milik customer &mdash; berbeda dari No. Order internal.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal PO Customer <span class="text-danger">*</span></label>
                    <input type="date" name="po_date" class="form-control" required value="<?= e($order['po_date'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Telepon Customer</label>
                    <input type="text" name="customer_phone" id="so_phone" class="form-control" maxlength="30" value="<?= e($order['customer_phone'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">PPN (%)</label>
                    <input type="number" name="ppn_percent" id="so_ppn" class="form-control" min="0" max="100" step="0.01"
                           value="<?= e($order['ppn_percent'] ?? $defaultPpn) ?>">
                    <div class="form-text">Isi 0 bila tanpa PPN.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alamat Customer</label>
                    <textarea name="customer_address" id="so_address" class="form-control" rows="2"
                              placeholder="terisi otomatis dari Master Client; boleh diubah untuk alamat kirim"><?= e($order['customer_address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Catatan Pesanan</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Opsional"><?= e($order['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-section-title mb-0">Barang yang Dipesan</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="soAddRow" data-inline-add="#soItemsBody"><i class="bi bi-plus-circle"></i> Tambah Barang</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:280px">Barang <span class="text-danger">*</span></th>
                            <th style="width:80px">Satuan</th>
                            <th style="width:110px">Jumlah <span class="text-danger">*</span></th>
                            <th style="width:150px">Harga Satuan</th>
                            <th style="width:90px">Diskon (%)</th>
                            <th style="width:150px" class="text-end">Subtotal</th>
                            <th style="width:90px"></th>
                        </tr>
                    </thead>
                    <tbody id="soItemsBody">
                        <?php foreach ($rows as $it):
                            $itemId = (int) ($it['item_id'] ?? 0);
                            $cat = $catalogById[$itemId] ?? null;
                            $touched = (float) ($it['fulfilled_qty'] ?? 0) > 0.004 || (float) ($it['invoiced_qty'] ?? 0) > 0.004;
                            $stock = $cat ? (float) $cat['stock_qty'] : 0;
                        ?>
                            <tr class="so-row" data-stock="<?= e($stock) ?>" data-fulfilled="<?= e($it['fulfilled_qty'] ?? 0) ?>" data-touched="<?= $touched ? 1 : 0 ?>">
                                <td data-label="Barang">
                                    <input type="hidden" name="line_id[]" value="<?= (int) ($it['id'] ?? 0) ?>">
                                    <select name="item_id[]" class="form-select form-select-sm so-item" <?= $touched ? 'disabled' : '' ?>>
                                        <option value="">-- Pilih dari Master Barang --</option>
                                        <?php foreach ($itemCatalog as $ic): ?>
                                            <option value="<?= (int) $ic['id'] ?>" <?= $itemId === (int) $ic['id'] ? 'selected' : '' ?>><?= e($ic['item_code'] . ' - ' . $ic['item_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($touched): ?>
                                        <input type="hidden" name="item_id[]" value="<?= $itemId ?>">
                                    <?php endif; ?>
                                    <div class="small text-muted so-spec mt-1"><?= $cat ? e(preg_replace('/\s+/', ' ', trim((string) $cat['specification']))) : '' ?></div>
                                    <div class="small so-stock mt-1"></div>
                                    <?php if ($touched): ?>
                                        <div class="small text-primary mt-1"><i class="bi bi-lock-fill"></i> Terpenuhi <?= e($fmtQty($it['fulfilled_qty'])) ?> &middot; Ditagih <?= e($fmtQty($it['invoiced_qty'])) ?> &mdash; barang tidak bisa diganti/dihapus</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Satuan" class="so-unit text-muted"><?= $cat ? e($cat['unit_name']) : (e($it['unit'] ?? '') ?: '-') ?></td>
                                <td data-label="Jumlah"><input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end so-qty" value="<?= e($fmtQty($it['qty'] ?? '')) ?>"></td>
                                <td data-label="Harga Satuan"><input type="text" inputmode="numeric" name="unit_price[]" class="form-control form-control-sm text-end currency-input so-price" value="<?= e($fmtPrice($it['unit_price'] ?? '')) ?>" placeholder="0"></td>
                                <td data-label="Diskon (%)"><input type="number" name="discount_percent[]" class="form-control form-control-sm text-end so-disc" min="0" max="100" step="0.01" value="<?= e((float) ($it['discount_percent'] ?? 0) ?: '') ?>" placeholder="0"></td>
                                <td data-label="Subtotal" class="text-end so-subtotal">Rp 0,00</td>
                                <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger so-remove" title="Hapus baris" <?= $touched ? 'disabled' : '' ?>><i class="bi bi-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="5" class="text-end">Subtotal</td><td class="text-end" id="soSumSubtotal">Rp 0,00</td><td></td></tr>
                        <tr><td colspan="5" class="text-end">PPN <span id="soPpnLabel"></span></td><td class="text-end" id="soSumPpn">Rp 0,00</td><td></td></tr>
                        <tr class="table-light"><td colspan="5" class="text-end fw-bold">Total Keseluruhan</td><td class="text-end fw-bold" id="soSumTotal">Rp 0,00</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
            <div id="soStockAlerts" class="mt-3"></div>
            <div class="form-text mt-2">
                Order tetap bisa disimpan walau stok kurang atau kosong. Menyimpan order <strong>tidak</strong> mengurangi stok &mdash; stok baru berkurang saat
                Accounting memproses pengeluaran barang.
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Simpan Order' ?></button>
        <?php if (!$isEdit): ?>
            <button type="submit" name="save_mode" value="draft" class="btn btn-outline-secondary" title="Draft belum muncul untuk diproses Accounting"><i class="bi bi-file-earmark"></i> Simpan sebagai Draft</button>
        <?php endif; ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-light border">Batal</a>
    </div>
</form>

<template id="soRowTemplate">
    <tr class="so-row" data-stock="0" data-fulfilled="0" data-touched="0">
        <td data-label="Barang">
            <input type="hidden" name="line_id[]" value="0">
            <select name="item_id[]" class="form-select form-select-sm so-item">
                <option value="">-- Pilih dari Master Barang --</option>
                <?php foreach ($itemCatalog as $ic): ?>
                    <option value="<?= (int) $ic['id'] ?>"><?= e($ic['item_code'] . ' - ' . $ic['item_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="small text-muted so-spec mt-1"></div>
            <div class="small so-stock mt-1"></div>
        </td>
        <td data-label="Satuan" class="so-unit text-muted">-</td>
        <td data-label="Jumlah"><input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end so-qty"></td>
        <td data-label="Harga Satuan"><input type="text" inputmode="numeric" name="unit_price[]" class="form-control form-control-sm text-end currency-input so-price" placeholder="0"></td>
        <td data-label="Diskon (%)"><input type="number" name="discount_percent[]" class="form-control form-control-sm text-end so-disc" min="0" max="100" step="0.01" placeholder="0"></td>
        <td data-label="Subtotal" class="text-end so-subtotal">Rp 0,00</td>
        <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger so-remove" title="Hapus baris"><i class="bi bi-trash"></i></button></td>
    </tr>
</template>

<?php if (canQuickAdd('client') && !$lockedClient): ?>
    <?php $quickAddClientTargetId = 'so_client_id'; ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_client_modal.php'; ?>
<?php endif; ?>

<script>
(function () {
    var body = document.getElementById('soItemsBody');
    var tpl = document.getElementById('soRowTemplate');
    var form = document.getElementById('soForm');
    var stockUrl = <?= json_encode(BASE_URL . '/index.php?module=sales_order&action=ajaxStock&item_id=') ?>;
    // Data awal barang dari katalog (spesifikasi, satuan, stok) -- stok DIPERBARUI dari server (AJAX) saat barang dipilih.
    var catalog = <?= json_encode(array_map(fn($c) => [
        'unit' => $c['unit_name'], 'spec' => preg_replace('/\s+/', ' ', trim((string) $c['specification'])), 'stock' => (float) $c['stock_qty'], 'name' => $c['item_name'],
    ], $catalogById), JSON_UNESCAPED_UNICODE) ?>;

    // Sama dengan parseQtyInput() / parseCurrencyInput() di server -- hanya untuk tampilan; server menghitung ulang.
    function numQty(v) {
        v = String(v || '').trim();
        if (v === '') { return 0; }
        if (v.indexOf(',') !== -1) { v = v.replace(/\./g, '').replace(',', '.'); } else { v = v.replace(/,/g, ''); }
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }
    function numPrice(v) {
        v = String(v || '').replace(/,/g, '');
        var n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }
    function rp(n) {
        return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fq(n) {
        return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }
    function esc(s) {
        var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
    }

    function recalc() {
        var subtotal = 0, alerts = [];
        body.querySelectorAll('.so-row').forEach(function (tr) {
            var qty = numQty(tr.querySelector('.so-qty').value);
            var price = numPrice(tr.querySelector('.so-price').value);
            var disc = Math.max(0, Math.min(100, parseFloat(tr.querySelector('.so-disc').value) || 0));
            var net = Math.round(price * (1 - disc / 100) * 100) / 100;
            var sub = Math.round(qty * net * 100) / 100;
            tr.querySelector('.so-subtotal').textContent = rp(sub);
            subtotal += sub;

            // Peringatan stok: sisa pesanan (qty - sudah terpenuhi) vs stok tersedia SAAT INI.
            var hint = tr.querySelector('.so-stock');
            var sel = tr.querySelector('.so-item');
            var id = sel.value || (tr.querySelector('input[name="item_id[]"][type=hidden]') || {}).value;
            if (!id) { hint.textContent = ''; return; }
            var stock = parseFloat(tr.dataset.stock) || 0;
            var fulfilled = parseFloat(tr.dataset.fulfilled) || 0;
            var unit = tr.querySelector('.so-unit').textContent.trim();
            var need = Math.max(qty - fulfilled, 0);
            var name = (catalog[id] || {}).name || sel.options[sel.selectedIndex].textContent;
            var html = 'Stok tersedia: <strong>' + fq(stock) + '</strong> ' + esc(unit);
            if (qty > 0 && need > 0 && stock + 0.004 < need) {
                var short = need - stock;
                html += ' &middot; <span class="text-danger fw-semibold">Kekurangan ' + fq(short) + ' ' + esc(unit) + '</span>';
                alerts.push(stock <= 0.004
                    ? 'Stok ' + name + ' belum tersedia. Pesanan tetap tersimpan dan dapat diproses setelah stok tersedia.'
                    : 'Stok barang tidak mencukupi. ' + name + ' dipesan ' + fq(need) + ' ' + unit + '. Stok tersedia ' + fq(stock) + ' ' + unit + '. Kekurangan ' + fq(short) + ' ' + unit + '.');
            } else if (qty > 0) {
                html += ' &middot; <span class="text-success">cukup</span>';
            }
            hint.innerHTML = html;
        });
        var ppn = Math.max(0, Math.min(100, parseFloat(document.getElementById('so_ppn').value) || 0));
        var ppnAmt = Math.round(subtotal * ppn) / 100;
        document.getElementById('soSumSubtotal').textContent = rp(subtotal);
        document.getElementById('soPpnLabel').textContent = '(' + ppn + '%)';
        document.getElementById('soSumPpn').textContent = rp(ppnAmt);
        document.getElementById('soSumTotal').textContent = rp(subtotal + ppnAmt);
        document.getElementById('soStockAlerts').innerHTML = alerts.map(function (a) {
            return '<div class="alert alert-warning py-2 mb-2 small"><i class="bi bi-exclamation-triangle"></i> ' + esc(a) + '</div>';
        }).join('');
    }

    function applyItem(tr, info) {
        tr.dataset.stock = info.stock;
        tr.querySelector('.so-unit').textContent = info.unit || '-';
        tr.querySelector('.so-spec').textContent = info.spec || '';
        recalc();
    }

    body.addEventListener('change', function (e) {
        if (!e.target.classList.contains('so-item')) { return; }
        var tr = e.target.closest('.so-row');
        var id = e.target.value;
        if (!id) { applyItem(tr, { stock: 0, unit: '', spec: '' }); return; }
        var c = catalog[id] || { stock: 0, unit: '', spec: '' };
        applyItem(tr, c); // tampilkan dulu dari katalog...
        fetch(stockUrl + encodeURIComponent(id), { credentials: 'same-origin' })   // ...lalu ganti dengan stok terkini dari database
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (j && !j.error && tr.querySelector('.so-item').value === id) {
                    applyItem(tr, { stock: j.stock_qty, unit: j.unit, spec: (j.specification || '').replace(/\s+/g, ' ').trim() });
                }
            }).catch(function () {});
    });
    body.addEventListener('input', function (e) {
        if (e.target.matches('.so-qty, .so-price, .so-disc')) { recalc(); }
    });
    document.getElementById('so_ppn').addEventListener('input', recalc);

    body.addEventListener('click', function (e) {
        var btn = e.target.closest('.so-remove');
        if (!btn || btn.disabled) { return; }
        var rows = body.querySelectorAll('.so-row');
        if (rows.length <= 1) {
            var tr = rows[0];
            tr.querySelectorAll('input[type=text], input[type=number]').forEach(function (i) { i.value = ''; });
            var s = tr.querySelector('.so-item'); s.value = ''; s.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            btn.closest('.so-row').remove();
        }
        recalc();
    });

    document.getElementById('soAddRow').addEventListener('click', function () {
        body.appendChild(tpl.content.cloneNode(true));
        recalc();
    });

    // Customer dipilih -> isi Alamat & Telepon dari Master Client (hanya bila field masih kosong / belum diubah manual).
    var clientSel = document.getElementById('so_client_id');
    var addr = document.getElementById('so_address'), phone = document.getElementById('so_phone');
    var addrTouched = addr.value.trim() !== '', phoneTouched = phone.value.trim() !== '';
    addr.addEventListener('input', function () { addrTouched = true; });
    phone.addEventListener('input', function () { phoneTouched = true; });
    clientSel.addEventListener('change', function () {
        var opt = clientSel.options[clientSel.selectedIndex];
        if (!opt || !opt.value) { return; }
        if (!addrTouched) { addr.value = opt.dataset.address || ''; }
        if (!phoneTouched) { phone.value = opt.dataset.phone || ''; }
    });

    form.addEventListener('submit', function (e) {
        var msg = '';
        if (!form.order_date.value) { msg = 'Tanggal Order Penjualan wajib diisi.'; }
        else if (!clientSel.value && !form.querySelector('input[name="client_id"][type=hidden]')) { msg = 'Customer wajib dipilih.'; }
        else if (!form.po_number.value.trim()) { msg = 'Nomor PO Customer wajib diisi.'; }
        else if (!form.po_date.value) { msg = 'Tanggal PO Customer wajib diisi.'; }
        else {
            var filled = 0;
            body.querySelectorAll('.so-row').forEach(function (tr, i) {
                var id = tr.querySelector('.so-item').value || (tr.querySelector('input[name="item_id[]"][type=hidden]') || {}).value;
                var qty = numQty(tr.querySelector('.so-qty').value);
                if (id || qty > 0) {
                    filled++;
                    if (!id) { msg = msg || 'Barang #' + (i + 1) + ': pilih barang dari Master Barang.'; }
                    if (qty <= 0) { msg = msg || 'Barang #' + (i + 1) + ': jumlah pesanan harus lebih dari 0.'; }
                }
            });
            if (!filled && !msg) { msg = 'Minimal 1 barang wajib diisi.'; }
        }
        if (msg) { e.preventDefault(); alert(msg); return; }
        // Cegah klik ganda: tombol dinonaktifkan sesaat setelah submit pertama.
        form.querySelectorAll('button[type=submit]').forEach(function (b) { setTimeout(function () { b.disabled = true; }, 0); });
    });

    // Kembali via tombol Back (bfcache): aktifkan lagi tombol simpan yang sempat dinonaktifkan.
    window.addEventListener('pageshow', function () {
        form.querySelectorAll('button[type=submit]').forEach(function (b) { b.disabled = false; });
    });

    recalc();
})();
</script>
