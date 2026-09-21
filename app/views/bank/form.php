<?php
/** @var string $mode @var array|null $row @var array $items @var array $banks @var array $projects
 *  @var array $picOptions @var array $rekeningOptions @var string $noBuktiPreview */
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$val = static fn(string $k, $d = '') => e($row[$k] ?? $d);
$items = $items ?? [];
$picOptions = $picOptions ?? [];
$rekeningOptions = $rekeningOptions ?? [];
$noBuktiPreview = $noBuktiPreview ?? ($row['no_bukti'] ?? '');
$curPic = $row['pic'] ?? '';
?>
<style>
/* Rincian Bank: di layar sempit (<768px) tiap baris jadi kartu bertumpuk,
   sama pola dengan Rincian Kas -- hanya menyasar #bankItemTable form ini. */
@media (max-width: 767.98px) {
    .rincian-head { align-items: flex-start !important; }
    .rincian-head > #btnAddBankItem { flex: 1 1 100%; }

    #bankItemTable { border: 0; }
    #bankItemTable thead { display: none; }
    #bankItemTable tbody, #bankItemTable tfoot,
    #bankItemTable tr.item-row, #bankItemTable tfoot tr { display: block; width: 100%; }

    #bankItemTable tr.item-row {
        position: relative;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        padding: .6rem .75rem .75rem;
        margin-bottom: .625rem;
        background: #fff;
    }
    #bankItemTable tr.item-row > td {
        display: block;
        width: 100% !important;
        border: 0;
        padding: .4rem 0 0;
        text-align: left !important;
    }
    #bankItemTable tr.item-row > td::before {
        content: attr(data-label);
        display: block;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .15rem;
    }
    #bankItemTable tr.item-row > td.cell-remove { padding-top: .6rem; }
    #bankItemTable tr.item-row > td.cell-remove::before { content: none; }
    #bankItemTable tr.item-row > td.cell-remove .btn-remove-row { width: 100%; }

    #bankItemTable tfoot td { display: block; border: 0; padding: 0; text-align: left !important; }
    #bankItemTable tfoot td:empty { display: none; }
    #bankItemTable tfoot tr {
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
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Transaksi Bank</h4>
    <a href="<?= BASE_URL ?>/cash" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=bank&action=<?= $actionUrl ?>" id="bankForm">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" class="form-control" value="<?= $val('trx_date', date('Y-m-d')) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">PIC</label>
                    <select name="pic" class="form-select">
                        <option value="">-- Tanpa PIC --</option>
                        <?php foreach ($picOptions as $p): ?>
                            <option value="<?= e($p) ?>" <?= $curPic === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Opsional. Sumber: Master Data &rarr; PIC Kas.</div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">No Bukti</label>
                    <input type="text" class="form-control bg-light" value="<?= e($noBuktiPreview) ?>"
                           placeholder="<?= $isEdit ? '' : 'otomatis saat disimpan' ?>" readonly>
                    <div class="form-text">
                        <?php if ($isEdit): ?>
                            Nomor tidak berubah saat transaksi diedit.
                        <?php else: ?>
                            Dibuat otomatis (prefix <strong>BK</strong>).
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Mutasi <span class="text-danger">*</span></label>
                    <select name="mutasi" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <option value="masuk" <?= ($row['mutasi'] ?? '') === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                        <option value="keluar" <?= ($row['mutasi'] ?? '') === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Bank <span class="text-danger">*</span></label>
                    <select name="bank_id" class="form-select" required>
                        <option value="">-- Pilih Bank --</option>
                        <?php foreach ($banks as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= (string) ($row['bank_id'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>>
                                <?= e($b['bank_name']) ?> (<?= e(strtoupper($b['jenis'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="">-- Tanpa Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (string) ($row['project_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Rekening</label>
                    <select name="rekening_id" class="form-select">
                        <option value="">-- Tanpa Rekening --</option>
                        <?php foreach ($rekeningOptions as $r): ?>
                            <option value="<?= (int) $r['id'] ?>" <?= (string) ($row['rekening_id'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['nama_rekening']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Opsional. Sumber: Master Data &rarr; Master Rekening.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2 rincian-head">
                <h6 class="mb-0">Rincian</h6>
                <button type="button" id="btnAddBankItem" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-circle"></i> Tambah Baris
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle" id="bankItemTable">
                    <thead class="table-light">
                        <tr>
                            <th>Uraian</th>
                            <th>Nominal (Rp)</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="bankItemTableBody">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $item): ?>
                                <?php include ROOT_PATH . '/app/views/bank/_item_row.php'; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php $item = null; include ROOT_PATH . '/app/views/bank/_item_row.php'; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="text-end fw-bold">Total Nominal</td>
                            <td class="fw-bold" id="bankGrandTotal">Rp 0.00</td>
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

<script>
(function () {
    const tableBody = document.getElementById('bankItemTableBody');
    const btnAddItem = document.getElementById('btnAddBankItem');
    const grandTotalEl = document.getElementById('bankGrandTotal');

    function formatRupiah(num) {
        return 'Rp ' + Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function recalcAll() {
        let total = 0;
        tableBody.querySelectorAll('.item-row').forEach(function (row) {
            const raw = (row.querySelector('.amount-input').value || '').replace(/,/g, '');
            total += parseFloat(raw) || 0;
        });
        grandTotalEl.textContent = formatRupiah(total);
    }

    tableBody.addEventListener('input', function (e) {
        if (e.target.classList.contains('amount-input')) {
            recalcAll();
        }
    });
    tableBody.addEventListener('click', function (e) {
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
        const tpl = document.createElement('tbody');
        tpl.innerHTML =
            '<tr class="item-row">' +
                '<td data-label="Uraian"><input type="text" name="item_uraian[]" class="form-control form-control-sm uraian-input" required></td>' +
                '<td data-label="Nominal (Rp)" style="width: 220px;"><input type="text" name="item_amount[]" class="form-control form-control-sm amount-input currency-input" inputmode="numeric" placeholder="0" required></td>' +
                '<td class="text-center cell-remove" style="width: 44px;"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus baris"><i class="bi bi-trash"></i><span class="d-md-none ms-1">Hapus baris</span></button></td>' +
            '</tr>';
        tableBody.appendChild(tpl.firstElementChild);
        recalcAll();
    });

    recalcAll();
})();
</script>
