<?php
$isEdit = $mode === 'edit';
$action = $isEdit ? 'update' : 'store';
$unitNames = array_map(fn($u) => $u['unit_name'], $units);
// Baris item awal: dari DB (edit / gagal validasi) atau 1 baris kosong.
$rows = $items ?: [['item_name' => '', 'description' => '', 'qty' => '', 'unit_name' => '', 'estimated_unit_price' => '', 'notes' => '']];
$canSubmit = can('request_budget', 'submit');

function rbUnitOptions(array $unitNames, ?string $selected): string
{
    $html = '<option value="">-- Satuan --</option>';
    $list = $unitNames;
    if ($selected !== null && $selected !== '' && !in_array($selected, $list, true)) {
        $list[] = $selected; // pertahankan satuan lama yang sudah tidak ada di master
    }
    foreach ($list as $n) {
        $html .= '<option value="' . e($n) . '"' . ($n === $selected ? ' selected' : '') . '>' . e($n) . '</option>';
    }
    return $html;
}
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Request Budget</h4>
        <small class="text-muted">No. Request: <strong><?= e($number) ?></strong> <?= $isEdit ? '' : '(otomatis)' ?></small>
    </div>
    <a href="<?= BASE_URL ?>/request_budget<?= $isEdit ? '/detail/' . (int) $rb['id'] : '' ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php if (empty($projects)): ?>
    <div class="alert alert-warning">
        Anda belum di-assign ke project mana pun, jadi belum bisa membuat Request Budget.
        Minta Super Admin menambahkan Anda lewat <strong>Project &raquo; Akses</strong>.
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=request_budget&action=<?= $action ?>" id="rbForm" novalidate>
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $rb['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-3">Informasi Pengajuan</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Pengajuan <span class="text-danger">*</span></label>
                    <input type="date" name="request_date" class="form-control" required
                           value="<?= e($rb['request_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pengaju</label>
                    <input type="text" class="form-control" disabled
                           value="<?= e($isEdit ? $rb['requester_name'] : currentUserName()) ?>">
                    <div class="form-text">Otomatis dari akun yang login.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Project <span class="text-danger">*</span></label>
                    <select name="project_id" class="form-select" required>
                        <option value="">-- Pilih Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) ($rb['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                        <?php endforeach; ?>
                        <?php // Edit: project lama yang (sudah) di luar akses tetap tampil agar tidak hilang diam-diam ?>
                        <?php if ($isEdit && !in_array((int) $rb['project_id'], array_map(fn($p) => (int) $p['id'], $projects), true)): ?>
                            <option value="<?= (int) $rb['project_id'] ?>" selected><?= e($rb['project_name']) ?></option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Hanya project yang di-assign ke Anda.</div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Keperluan <span class="text-danger">*</span></label>
                    <input type="text" name="purpose" class="form-control" maxlength="200" required
                           value="<?= e($rb['purpose'] ?? '') ?>" placeholder="mis. Operasional Proyek Peihai 2">
                    <div class="form-text">Dipakai sebagai judul cetak: &ldquo;REQUEST BUDGET &hellip;&rdquo;.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Periode</label>
                    <input type="text" name="period_label" class="form-control" maxlength="100"
                           value="<?= e($rb['period_label'] ?? '') ?>" placeholder="mis. Week 4 Agustus (opsional)">
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Opsional"><?= e($rb['description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-section-title mb-0">Detail Budget</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="rbAddRow"><i class="bi bi-plus-circle"></i> Tambah Item</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0" id="rbItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">No</th>
                            <th>Nama Barang / Kebutuhan <span class="text-danger">*</span></th>
                            <th>Spesifikasi / Deskripsi</th>
                            <th style="width:100px">Qty <span class="text-danger">*</span></th>
                            <th style="width:120px">Satuan</th>
                            <th style="width:150px">Estimasi Harga Satuan</th>
                            <th style="width:150px" class="text-end">Estimasi Total</th>
                            <th>Keterangan</th>
                            <th style="width:44px"></th>
                        </tr>
                    </thead>
                    <tbody id="rbItemsBody">
                        <?php foreach ($rows as $it): ?>
                            <tr class="rb-row">
                                <td data-label="No" class="rb-no text-muted"></td>
                                <td data-label="Barang / Kebutuhan"><input type="text" name="item_name[]" class="form-control form-control-sm" maxlength="200" value="<?= e($it['item_name']) ?>"></td>
                                <td data-label="Spesifikasi"><input type="text" name="item_description[]" class="form-control form-control-sm" maxlength="500" value="<?= e($it['description'] ?? '') ?>"></td>
                                <td data-label="Qty"><input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end rb-qty" value="<?= $it['qty'] === '' ? '' : e(rtrim(rtrim(number_format((float) $it['qty'], 2, ',', ''), '0'), ',')) ?>"></td>
                                <td data-label="Satuan"><select name="unit_name[]" class="form-select form-select-sm"><?= rbUnitOptions($unitNames, $it['unit_name'] ?? null) ?></select></td>
                                <td data-label="Harga Satuan"><input type="text" inputmode="decimal" name="price[]" class="form-control form-control-sm text-end currency-input rb-price" value="<?= $it['estimated_unit_price'] === '' ? '' : e(number_format((float) $it['estimated_unit_price'], 2, '.', '')) ?>"></td>
                                <td data-label="Estimasi Total" class="text-end fw-semibold rb-line">Rp 0</td>
                                <td data-label="Keterangan"><input type="text" name="item_notes[]" class="form-control form-control-sm" maxlength="500" value="<?= e($it['notes'] ?? '') ?>"></td>
                                <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger rb-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="6" class="text-end fw-bold">TOTAL REQUEST BUDGET</td>
                            <td class="text-end fw-bold" id="rbTotal">Rp 0</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="form-text mt-2">Estimasi Total = Qty &times; Harga Satuan. Total dihitung ulang oleh server saat disimpan.</div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary" <?= empty($projects) ? 'disabled' : '' ?>><i class="bi bi-save"></i> Simpan sebagai Draft</button>
        <?php if ($canSubmit): ?>
            <button type="submit" name="submit_after" value="1" class="btn btn-success" <?= empty($projects) ? 'disabled' : '' ?>><i class="bi bi-send"></i> Simpan &amp; Ajukan</button>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/request_budget<?= $isEdit ? '/detail/' . (int) $rb['id'] : '' ?>" class="btn btn-light border">Batal</a>
    </div>
</form>

<template id="rbRowTemplate">
    <tr class="rb-row">
        <td data-label="No" class="rb-no text-muted"></td>
        <td data-label="Barang / Kebutuhan"><input type="text" name="item_name[]" class="form-control form-control-sm" maxlength="200"></td>
        <td data-label="Spesifikasi"><input type="text" name="item_description[]" class="form-control form-control-sm" maxlength="500"></td>
        <td data-label="Qty"><input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end rb-qty"></td>
        <td data-label="Satuan"><select name="unit_name[]" class="form-select form-select-sm"><?= rbUnitOptions($unitNames, null) ?></select></td>
        <td data-label="Harga Satuan"><input type="text" inputmode="decimal" name="price[]" class="form-control form-control-sm text-end currency-input rb-price"></td>
        <td data-label="Estimasi Total" class="text-end fw-semibold rb-line">Rp 0</td>
        <td data-label="Keterangan"><input type="text" name="item_notes[]" class="form-control form-control-sm" maxlength="500"></td>
        <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger rb-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<script>
(function () {
    const body = document.getElementById('rbItemsBody');
    const totalEl = document.getElementById('rbTotal');
    const tpl = document.getElementById('rbRowTemplate');

    // Sama dengan parseQtyInput()/parseCurrencyInput() di server -- ini HANYA tampilan,
    // angka final selalu dihitung ulang oleh server.
    function num(v, isQty) {
        v = String(v || '').trim();
        if (v === '') { return 0; }
        if (isQty && v.indexOf(',') !== -1) { v = v.replace(/\./g, '').replace(',', '.'); }
        else { v = v.replace(/,/g, ''); }
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }

    function recalc() {
        let total = 0;
        body.querySelectorAll('.rb-row').forEach(function (tr, i) {
            tr.querySelector('.rb-no').textContent = i + 1;
            const line = Math.round(num(tr.querySelector('.rb-qty').value, true) * num(tr.querySelector('.rb-price').value, false) * 100) / 100;
            tr.querySelector('.rb-line').textContent = 'Rp ' + line.toLocaleString('id-ID', { maximumFractionDigits: 2 });
            total += line;
        });
        totalEl.textContent = 'Rp ' + total.toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    document.getElementById('rbAddRow').addEventListener('click', function () {
        body.appendChild(tpl.content.cloneNode(true));
        recalc();
    });
    body.addEventListener('input', function (e) {
        if (e.target.matches('.rb-qty, .rb-price')) { recalc(); }
    });
    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.rb-remove');
        if (!btn) { return; }
        if (body.querySelectorAll('.rb-row').length <= 1) {
            body.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        } else {
            btn.closest('.rb-row').remove();
        }
        recalc();
    });

    // Validasi sisi klien (server tetap memvalidasi ulang semuanya).
    document.getElementById('rbForm').addEventListener('submit', function (e) {
        const f = this;
        let msg = '';
        if (!f.request_date.value) { msg = 'Tanggal pengajuan wajib diisi.'; }
        else if (!f.project_id.value) { msg = 'Project wajib dipilih.'; }
        else if (!f.purpose.value.trim()) { msg = 'Keperluan wajib diisi.'; }
        else {
            let filled = 0;
            body.querySelectorAll('.rb-row').forEach(function (tr, i) {
                const name = tr.querySelector('[name="item_name[]"]').value.trim();
                const qty = num(tr.querySelector('.rb-qty').value, true);
                const price = num(tr.querySelector('.rb-price').value, false);
                if (name || qty > 0 || price > 0) {
                    filled++;
                    if (!name) { msg = msg || 'Item #' + (i + 1) + ': nama barang/kebutuhan wajib diisi.'; }
                    if (qty <= 0) { msg = msg || 'Item #' + (i + 1) + ': qty harus lebih dari 0.'; }
                }
            });
            if (!filled && !msg) { msg = 'Minimal harus ada 1 item kebutuhan.'; }
        }
        if (msg) {
            e.preventDefault();
            alert(msg);
        }
    });

    recalc();
})();
</script>
