<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? 'update' : 'store';
// Baris barang awal: dari DB (edit / gagal validasi) atau 1 baris kosong.
$rows = $items ?: [['item_name' => '', 'qty' => '']];
$canSubmit = can('request_po', 'submit');
$backUrl = BASE_URL . '/request_po' . ($isEdit ? '/detail/' . (int) $rp['id'] : '');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Request PO</h4>
        <small class="text-muted">No. Request PO: <strong><?= e($number) ?></strong> <?= $isEdit ? '' : '(otomatis)' ?></small>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=request_po&action=<?= $action ?>" id="rpoForm" novalidate>
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $rp['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-3">Informasi Request</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Request <span class="text-danger">*</span></label>
                    <input type="date" name="request_date" class="form-control" required
                           value="<?= e($rp['request_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Requester (Yang Meminta) <span class="text-danger">*</span></label>
                    <input type="text" name="requester_name" class="form-control" maxlength="100" required
                           value="<?= e($rp['requester_name'] ?? currentUserName()) ?>" placeholder="nama yang meminta PO">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <select name="project_id" class="form-select" required>
                        <option value="">-- Pilih Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) ($rp['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                        <?php endforeach; ?>
                        <?php // Edit: project lama yang sudah tidak aktif tetap tampil agar tidak hilang diam-diam ?>
                        <?php if ($isEdit && !in_array((int) $rp['project_id'], array_map(fn($p) => (int) $p['id'], $projects), true)): ?>
                            <option value="<?= (int) $rp['project_id'] ?>" selected><?= e($rp['project_name']) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan / Keperluan <span class="text-danger">*</span></label>
                    <input type="text" name="purpose" class="form-control" maxlength="200" required
                           value="<?= e($rp['purpose'] ?? '') ?>" placeholder="mis. Kebutuhan material panel project">
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Opsional"><?= e($rp['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-section-title mb-0">Daftar Barang</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="rpoAddRow"><i class="bi bi-plus-circle"></i> Tambah Barang</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Nama Barang <span class="text-danger">*</span></th>
                            <th style="width:160px">Qty <span class="text-danger">*</span></th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="rpoItemsBody">
                        <?php foreach ($rows as $it): ?>
                            <tr class="rpo-row">
                                <td data-label="No" class="rpo-no text-muted"></td>
                                <td data-label="Nama Barang"><input type="text" name="item_name[]" class="form-control form-control-sm" maxlength="200" value="<?= e($it['item_name']) ?>"></td>
                                <td data-label="Qty"><input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end rpo-qty" value="<?= $it['qty'] === '' ? '' : e(rtrim(rtrim(number_format((float) $it['qty'], 2, ',', ''), '0'), ',')) ?>"></td>
                                <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger rpo-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-2">Hanya nama barang dan qty &mdash; harga, vendor, dan data PO ditangani terpisah oleh Purchase.</div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan sebagai Draft</button>
        <?php if ($canSubmit): ?>
            <button type="submit" name="submit_after" value="1" class="btn btn-success"><i class="bi bi-send"></i> Simpan &amp; Submit</button>
        <?php endif; ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-light border">Batal</a>
    </div>
</form>

<template id="rpoRowTemplate">
    <tr class="rpo-row">
        <td data-label="No" class="rpo-no text-muted"></td>
        <td data-label="Nama Barang"><input type="text" name="item_name[]" class="form-control form-control-sm" maxlength="200"></td>
        <td data-label="Qty"><input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end rpo-qty"></td>
        <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger rpo-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<script>
(function () {
    const body = document.getElementById('rpoItemsBody');
    const tpl = document.getElementById('rpoRowTemplate');

    // Sama dengan parseQtyInput() di server -- hanya untuk validasi tampilan; server memvalidasi ulang.
    function num(v) {
        v = String(v || '').trim();
        if (v === '') { return 0; }
        if (v.indexOf(',') !== -1) { v = v.replace(/\./g, '').replace(',', '.'); }
        else { v = v.replace(/,/g, ''); }
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function renumber() {
        body.querySelectorAll('.rpo-row').forEach(function (tr, i) { tr.querySelector('.rpo-no').textContent = i + 1; });
    }

    document.getElementById('rpoAddRow').addEventListener('click', function () {
        body.appendChild(tpl.content.cloneNode(true));
        renumber();
        const last = body.querySelector('.rpo-row:last-child input');
        if (last) { last.focus(); }
    });
    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.rpo-remove');
        if (!btn) { return; }
        if (body.querySelectorAll('.rpo-row').length <= 1) {
            body.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        } else {
            btn.closest('.rpo-row').remove();
        }
        renumber();
    });

    document.getElementById('rpoForm').addEventListener('submit', function (e) {
        const f = this;
        let msg = '';
        if (!f.request_date.value) { msg = 'Tanggal request wajib diisi.'; }
        else if (!f.requester_name.value.trim()) { msg = 'Requester wajib diisi.'; }
        else if (!f.project_id.value) { msg = 'Project wajib dipilih.'; }
        else if (!f.purpose.value.trim()) { msg = 'Keterangan / Keperluan wajib diisi.'; }
        else {
            let filled = 0;
            body.querySelectorAll('.rpo-row').forEach(function (tr, i) {
                const name = tr.querySelector('[name="item_name[]"]').value.trim();
                const qty = num(tr.querySelector('.rpo-qty').value);
                if (name || qty > 0) {
                    filled++;
                    if (!name) { msg = msg || 'Barang #' + (i + 1) + ': nama barang wajib diisi.'; }
                    if (qty <= 0) { msg = msg || 'Barang #' + (i + 1) + ': qty harus lebih dari 0.'; }
                }
            });
            if (!filled && !msg) { msg = 'Minimal harus ada 1 barang.'; }
        }
        if (msg) {
            e.preventDefault();
            alert(msg);
        }
    });

    renumber();
})();
</script>
