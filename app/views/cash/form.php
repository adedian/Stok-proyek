<?php
/** @var string $mode @var array|null $cash @var array $items @var array $categories @var array $picOptions
 *  @var array $projects @var array $units */
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$val = static fn(string $k, $d = '') => e($cash[$k] ?? $d);
$curPic = $cash['pic'] ?? '';
// Partial baris memakai $cashCategories / $units / $projects.
$cashCategories = $categories;
?>
<style>
/* Rincian item Kas: di layar sempit (<768px) tiap baris jadi kartu bertumpuk,
   bukan tabel yang meluber ke samping. Hanya menyasar #itemTable form ini --
   tidak menyentuh CSS/tabel modul lain. */
@media (max-width: 767.98px) {
    .rincian-head { align-items: flex-start !important; }
    .rincian-head > #btnAddItem { flex: 1 1 100%; }

    #itemTable { border: 0; }
    #itemTable thead { display: none; }
    #itemTable tbody, #itemTable tfoot,
    #itemTable tr.item-row, #itemTable tfoot tr { display: block; width: 100%; }

    #itemTable tr.item-row {
        position: relative;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        padding: .6rem .75rem .75rem;
        margin-bottom: .625rem;
        background: #fff;
    }
    #itemTable tr.item-row > td {
        display: block;
        width: 100% !important;
        border: 0;
        padding: .4rem 0 0;
        text-align: left !important;
    }
    #itemTable tr.item-row > td::before {
        content: attr(data-label);
        display: block;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .15rem;
    }
    /* Kolom stok yang tidak berlaku untuk kategori baris ini (Project/Supplier/
       Satuan pada baris non-stok) -- sembunyikan seluruh selnya di mobile
       supaya kartu tidak penuh label "-". Di desktop sel tetap ada demi
       kesejajaran kolom tabel. */
    #itemTable tr.item-row > td.cell-na { display: none; }

    #itemTable tr.item-row > td.subtotal-cell { font-weight: 600; }
    #itemTable tr.item-row > td.cell-remove { padding-top: .6rem; }
    #itemTable tr.item-row > td.cell-remove::before { content: none; }
    #itemTable tr.item-row > td.cell-remove .btn-remove-row { width: 100%; }

    #itemTable tfoot td { display: block; border: 0; padding: 0; text-align: left !important; }
    #itemTable tfoot td:empty { display: none; }
    #itemTable tfoot tr {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        border-top: 2px solid #dee2e6;
        margin-top: .25rem;
        padding-top: .6rem;
    }
}
</style>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Kas</h4>
    <a href="<?= BASE_URL ?>/cash" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=<?= $actionUrl ?>" id="cashForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $cash['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" class="form-control"
                           value="<?= $val('trx_date', date('Y-m-d')) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">PIC <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select name="pic" id="cash_pic" class="form-select" required>
                            <option value="">-- Pilih PIC --</option>
                            <?php foreach ($picOptions as $p): ?>
                                <option value="<?= e($p) ?>" <?= $curPic === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalQuickAddPic" title="Tambah PIC Cepat">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    <div class="form-text">Sumber: Master Data &rarr; PIC Kas.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">No Bukti <span class="text-danger">*</span></label>
                    <input type="text" name="no_bukti" class="form-control" value="<?= $val('no_bukti') ?>"
                           placeholder="mis. KB-001" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Mutasi <span class="text-danger">*</span></label>
                    <select name="mutasi" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <option value="masuk"  <?= ($cash['mutasi'] ?? '') === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                        <option value="keluar" <?= ($cash['mutasi'] ?? '') === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2 rincian-head">
                <h6 class="mb-0">Rincian</h6>
                <button type="button" id="btnAddItem" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Tambah Baris
                </button>
            </div>
            <p class="text-muted small mb-2">
                Kategori, Barang, Project, dan Supplier dipilih per baris. Baris ber-kategori <strong>Material Projek</strong>,
                <strong>Inventory Kantor</strong>, atau <strong>Inventory Teknik</strong> otomatis menambah stok
                saat disimpan &mdash; <strong>wajib pilih Barang</strong> dari master (stok dicatat atas nama Barang itu &amp;
                nyambung ke Laporan Stok Barang), Satuan wajib, Project wajib untuk Material Projek / Inventory Teknik.
                Baris <strong>Biaya Operasional</strong> tidak menyentuh stok.
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="itemTable">
                    <thead class="table-light">
                        <tr>
                            <th>Uraian</th>
                            <th>Kategori</th>
                            <th>Barang</th>
                            <th>Project</th>
                            <th>Supplier</th>
                            <th>Satuan</th>
                            <th>Qty</th>
                            <th>Harga Satuan (Rp)</th>
                            <th class="text-end">Jumlah</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="itemTableBody">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <?php include ROOT_PATH . '/app/views/cash/_item_row.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php $item = null; include ROOT_PATH . '/app/views/cash/_item_row.php'; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8" class="text-end fw-bold">Total Nominal</td>
                            <td class="text-end fw-bold" id="grandTotal">Rp 0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/cash" class="btn btn-light border">Batal</a>
    </div>
</form>

<?php require ROOT_PATH . '/app/views/partials/quick_add_pic_modal.php'; ?>
<?php if (canQuickAdd('item')): ?>
    <?php $itemCategories = $itemCategories ?? []; ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_item_modal.php'; ?>
<?php endif; ?>

<script>
(function () {
    const tableBody = document.getElementById('itemTableBody');
    const btnAddItem = document.getElementById('btnAddItem');
    const grandTotalEl = document.getElementById('grandTotal');
    let rowIndex = tableBody.querySelectorAll('.item-row').length;

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
    function recalcAll() {
        let total = 0;
        tableBody.querySelectorAll('.item-row').forEach(function (row) { total += recalcRow(row); });
        grandTotalEl.textContent = formatRupiah(total);
    }

    // Aktifkan/matikan satu field stok + placeholder "—" pendampingnya.
    function setField(field, na, on, req) {
        if (!field) return;
        field.disabled = !on;
        field.required = !!(on && req);
        field.classList.toggle('d-none', !on);
        if (!on) field.value = '';
        if (na) na.classList.toggle('d-none', on);
        const cell = field.closest('td');
        if (cell) cell.classList.toggle('cell-na', !on);
    }

    // Barang picker (select + tombol "+") pakai wrapper .barang-wrap -- tombol
    // ikut sembunyi. Nilai id disalin ke hidden .barang-id-input.
    function setBarang(row, on) {
        const wrap = row.querySelector('.barang-wrap');
        const sel = row.querySelector('.barang-select');
        const na = row.querySelector('.barang-na');
        const idInput = row.querySelector('.barang-id-input');
        if (!wrap || !sel) return;
        wrap.classList.toggle('d-none', !on);
        if (na) na.classList.toggle('d-none', on);
        const cell = wrap.closest('td');
        if (cell) cell.classList.toggle('cell-na', !on);
        sel.disabled = !on;
        sel.required = on;
        if (!on) { sel.value = ''; if (idInput) idInput.value = ''; }
    }

    // Kategori baris menentukan kolom stok mana yang aktif:
    //  affects_stock -> Barang (wajib) + Satuan (wajib) + Supplier (opsional)
    //  scope 'proyek' -> Project (wajib)
    function applyRowCategory(row) {
        const sel = row.querySelector('.category-select');
        const opt = sel.options[sel.selectedIndex];
        const isStock = !!(opt && opt.dataset.affectsStock === '1');
        const isProyek = isStock && opt.dataset.scope === 'proyek';
        setBarang(row, isStock);
        setField(row.querySelector('.unit-select'),     row.querySelector('.unit-na'), isStock, true);
        setField(row.querySelector('.supplier-input'),  row.querySelector('.sup-na'),  isStock, false);
        setField(row.querySelector('.project-select'),  row.querySelector('.proj-na'), isProyek, true);
        row.classList.toggle('stock-row', isStock);
        const hint = row.querySelector('.cat-hint');
        if (hint) hint.classList.toggle('d-none', !isStock);
    }

    // Barang dipilih -> simpan id ke hidden, prefill Satuan bila masih kosong.
    function onBarangChange(row) {
        const sel = row.querySelector('.barang-select');
        const opt = sel.options[sel.selectedIndex];
        const idInput = row.querySelector('.barang-id-input');
        if (idInput) idInput.value = opt && opt.value ? opt.value : '';
        const unitSel = row.querySelector('.unit-select');
        if (opt && opt.dataset.unit && unitSel && !unitSel.value) {
            const match = Array.prototype.find.call(unitSel.options, function (o) { return o.value === opt.dataset.unit; });
            if (match) unitSel.value = opt.dataset.unit;
        }
    }

    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('qty-input') || e.target.classList.contains('price-input')) {
            recalcAll();
        }
    });
    tableBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('category-select')) {
            applyRowCategory(e.target.closest('.item-row'));
        } else if (e.target.classList.contains('barang-select')) {
            onBarangChange(e.target.closest('.item-row'));
        }
    });
    tableBody.addEventListener('click', function (e) {
        const quickAddBtn = e.target.closest('.btn-quick-add-item');
        if (quickAddBtn) {
            window.__quickAddItemTarget = quickAddBtn.closest('.item-row').querySelector('.barang-select');
            const modalEl = document.getElementById('modalQuickAddItem');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        const removeBtn = e.target.closest('.btn-remove-row');
        if (removeBtn) {
            if (tableBody.querySelectorAll('.item-row').length <= 1) {
                alert('Minimal harus ada 1 baris rincian.');
                return;
            }
            removeBtn.closest('.item-row').remove();
            recalcAll();
        }
    });
    btnAddItem.addEventListener('click', function () {
        rowIndex++;
        fetch('<?= BASE_URL ?>/index.php?module=cash&action=ajaxItemRow&index=' + rowIndex)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                tableBody.insertAdjacentHTML('beforeend', data.html);
                const newRow = tableBody.querySelector('.item-row:last-child');
                if (newRow) applyRowCategory(newRow);
                recalcAll();
            })
            .catch(function () { alert('Gagal menambahkan baris. Silakan coba lagi.'); });
    });

    tableBody.querySelectorAll('.item-row').forEach(applyRowCategory);
    recalcAll();
})();
</script>
