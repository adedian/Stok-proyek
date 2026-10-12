<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Tambah Stok Opname</h4>
        <small class="text-muted">No. Opname: <?= e($opnameNumber) ?></small>
    </div>
    <a href="<?= BASE_URL ?>/inventory/opnameIndex" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=inventory&action=opnameStore" id="opnameForm">
    <?= csrfField() ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Jenis Stok <span class="text-danger">*</span></label>
                    <select name="stock_type" id="stockTypeSelect" class="form-select">
                        <?php foreach ($stockTypeLabels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $selectedStockType === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Sama dengan "Jenis Stok" di Master Data &raquo; Barang.</div>
                </div>
                <div class="col-md-6" id="projectFieldWrapper">
                    <label class="form-label">Project</label>
                    <div class="input-group">
                        <select name="project_id" id="projectSelect" class="form-select">
                            <option value="">-- Semua Project + Kantor --</option>
                            <?php foreach ($projects as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= (string) $selectedProjectId === (string) $p['id'] ? 'selected' : '' ?>>
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
                    <div class="form-text">Opsional. Kosongkan untuk menghitung semua barang jenis stok ini (lintas project + kantor).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tanggal Opname <span class="text-danger">*</span></label>
                    <input type="date" name="opname_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="mb-3">Hitung Fisik Barang</h6>
            <div id="itemsContainer">
                <?php if (empty($items)): ?>
                    <p class="text-muted small mb-0" id="emptyNotice">Belum ada data stok barang untuk jenis stok ini.</p>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle entry-cards" id="itemsTable" style="<?= empty($items) ? 'display:none;' : '' ?>">
                        <thead class="table-light">
                            <tr>
                                <th>Barang</th>
                                <th class="text-end">Stok Sistem</th>
                                <th class="text-end" style="width: 180px;">Stok Fisik (Hitung Ulang)</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <?= e($item['item_name']) ?> <span class="text-muted small">(<?= e($item['unit']) ?>)</span>
                                        <input type="hidden" name="inventory_id[]" value="<?= (int) $item['id'] ?>">
                                    </td>
                                    <td class="text-end"><?= number_format((float) $item['qty_available'], 2, ',', '.') ?></td>
                                    <td>
                                        <input type="text" inputmode="decimal" name="qty_actual[]" class="form-control form-control-sm text-end"
                                               value="<?= e((string) $item['qty_available']) ?>" required>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Simpan sebagai Draft
        </button>
        <a href="<?= BASE_URL ?>/inventory/opnameIndex" class="btn btn-light border">Batal</a>
    </div>
</form>

<?php if (canQuickAdd('project')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_project_modal.php'; ?>
<?php endif; ?>

<script>
(function () {
    const stockTypeSelect = document.getElementById('stockTypeSelect');
    const projectSelect = document.getElementById('projectSelect');
    const itemsBody = document.getElementById('itemsBody');
    const itemsTable = document.getElementById('itemsTable');
    const itemsContainer = document.getElementById('itemsContainer');
    // Pesan kosong/gagal selalu tersedia (sebelumnya hanya ada kalau halaman dibuka tanpa barang).
    let emptyNotice = document.getElementById('emptyNotice');
    if (!emptyNotice) {
        emptyNotice = document.createElement('p');
        emptyNotice.id = 'emptyNotice';
        emptyNotice.className = 'text-muted small mb-0';
        emptyNotice.style.display = 'none';
        itemsContainer.insertBefore(emptyNotice, itemsContainer.firstChild);
    }
    let requestSeq = 0; // balasan AJAX lama diabaikan kalau user sudah mengganti pilihan lagi

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function showNotice(msg) {
        itemsBody.innerHTML = '';
        itemsTable.style.display = 'none';
        emptyNotice.textContent = msg;
        emptyNotice.style.display = '';
    }

    function loadItems() {
        const seq = ++requestSeq;
        const stockType = stockTypeSelect.value;
        const projectId = projectSelect.value;

        const url = '<?= BASE_URL ?>/index.php?module=inventory&action=ajaxItemsByProject'
            + '&stock_type=' + encodeURIComponent(stockType)
            + '&project_id=' + encodeURIComponent(projectId);

        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (seq !== requestSeq) { return; }
                if (!data.items || data.items.length === 0) {
                    showNotice('Belum ada data stok barang untuk jenis stok ini.');
                    return;
                }
                emptyNotice.style.display = 'none';
                itemsBody.innerHTML = '';
                data.items.forEach(function (item) {
                    const tr = document.createElement('tr');
                    tr.innerHTML =
                        '<td>' + esc(item.item_name) + ' <span class="text-muted small">(' + esc(item.unit) + ')</span>'
                        + '<input type="hidden" name="inventory_id[]" value="' + parseInt(item.id, 10) + '"></td>'
                        + '<td class="text-end">' + parseFloat(item.qty_available).toLocaleString('id-ID') + '</td>'
                        + '<td><input type="text" inputmode="decimal" name="qty_actual[]" class="form-control form-control-sm text-end" '
                        + 'value="' + esc(item.qty_available) + '" required></td>';
                    itemsBody.appendChild(tr);
                });
                itemsTable.style.display = '';
            })
            .catch(function () {
                if (seq === requestSeq) { showNotice('Gagal memuat daftar barang.'); }
            });
    }

    stockTypeSelect.addEventListener('change', loadItems);
    projectSelect.addEventListener('change', loadItems);
})();
</script>
