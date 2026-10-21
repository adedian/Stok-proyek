<?php
$id = (int) $order['id'];
$fq = fn($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',') ?: '0';
$fqIn = fn($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',') ?: '0'; // untuk nilai input (koma desimal)
$openLines = array_values(array_filter($items, fn($it) => (float) $it['remaining_qty'] > SalesOrder::EPS));
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Proses Pemenuhan Order <?= e($order['order_number']) ?></h4>
        <small class="text-muted"><?= e($order['client_name']) ?> &middot; PO Customer <strong><?= e($order['po_number']) ?></strong> (<?= e(formatTanggal($order['po_date'])) ?>)</small>
    </div>
    <a href="<?= BASE_URL ?>/sales_order/detail/<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Detail</a>
</div>

<div class="alert alert-info small">
    <i class="bi bi-info-circle"></i> Halaman ini menampilkan <strong>stok terkini</strong> dari database. Memproses pemenuhan akan membuat dokumen
    <strong>Pengeluaran Barang</strong> (stok berkurang saat itu juga). Isi jumlah sesuai stok yang benar-benar tersedia &mdash; sisa pesanan tetap tercatat
    dan bisa diproses nanti tanpa membuat order baru. Invoice &amp; Surat Jalan diterbitkan terpisah setelah ini.
</div>

<?php if (!$openLines): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle"></i> Seluruh barang pada order ini telah terpenuhi.</div>
<?php else: ?>
<form method="POST" action="<?= BASE_URL ?>/index.php?module=sales_order&action=processStore" id="soProcessForm" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal Pengeluaran <span class="text-danger">*</span></label>
                    <input type="date" name="fulfillment_date" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">PIC Pengeluaran</label>
                    <input type="text" name="pic_name" class="form-control" maxlength="150" value="<?= e(currentUserName()) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" placeholder="Opsional">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-2">Barang yang Belum Terpenuhi</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Barang</th>
                            <th class="text-end">Dipesan</th>
                            <th class="text-end">Terpenuhi</th>
                            <th class="text-end">Sisa Pesanan</th>
                            <th class="text-end">Stok Tersedia</th>
                            <th style="min-width:230px">Sumber Stok</th>
                            <th style="width:130px">Jumlah Diproses</th>
                        </tr>
                    </thead>
                    <tbody id="soProcBody">
                        <?php foreach ($openLines as $it):
                            $lid = (int) $it['id'];
                            $rem = (float) $it['remaining_qty'];
                            $stock = (float) $it['stock_qty'];
                            $suggest = min($rem, $stock);
                            $bk = $buckets[$lid] ?? [];
                        ?>
                            <tr class="so-proc-row" data-remaining="<?= e($rem) ?>" data-stock="<?= e($stock) ?>" data-unit="<?= e($it['unit']) ?>" data-name="<?= e($it['item_name']) ?>">
                                <td data-label="Barang">
                                    <input type="hidden" name="line_id[]" value="<?= $lid ?>">
                                    <div class="fw-semibold"><?= e($it['item_name']) ?></div>
                                    <div class="small text-muted"><?= e($it['item_code']) ?> &middot; <?= e($it['unit']) ?></div>
                                </td>
                                <td data-label="Dipesan" class="text-end"><?= e($fq($it['qty'])) ?></td>
                                <td data-label="Terpenuhi" class="text-end"><?= e($fq($it['fulfilled_qty'])) ?></td>
                                <td data-label="Sisa Pesanan" class="text-end fw-semibold"><?= e($fq($rem)) ?></td>
                                <td data-label="Stok Tersedia" class="text-end <?= $stock + SalesOrder::EPS < $rem ? 'text-danger fw-semibold' : 'text-success' ?>">
                                    <?= e($fq($stock)) ?>
                                    <?php if ($stock + SalesOrder::EPS < $rem): ?><div class="small fw-normal">kurang <?= e($fq($rem - $stock)) ?></div><?php endif; ?>
                                </td>
                                <td data-label="Sumber Stok">
                                    <?php if ($bk): ?>
                                        <select name="source[]" class="form-select form-select-sm so-source" data-no-search>
                                            <option value="0" data-qty="<?= e($stock) ?>">Otomatis (Stok Kantor dulu, lalu terbanyak)</option>
                                            <?php foreach ($bk as $b): ?>
                                                <option value="<?= (int) $b['id'] ?>" data-qty="<?= e($b['qty_available']) ?>">
                                                    <?= $b['stock_scope'] === 'kantor' ? 'Stok Kantor' : e($b['project_name'] ?: 'Stok Proyek') ?> &mdash; <?= e($fq($b['qty_available'])) ?> <?= e($b['unit']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="hidden" name="source[]" value="0">
                                        <span class="text-danger small"><i class="bi bi-x-circle"></i> Stok kosong &mdash; belum bisa diproses</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Jumlah Diproses">
                                    <input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end so-proc-qty"
                                           value="<?= $suggest > SalesOrder::EPS ? e($fqIn($suggest)) : '' ?>" <?= $bk ? '' : 'readonly' ?> placeholder="0">
                                    <div class="small mt-1 so-proc-hint"></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-2">Jumlah otomatis diisi sebesar yang bisa dipenuhi saat ini (terkecil antara sisa pesanan dan stok). Ubah bila perlu; kosongkan / 0 untuk melewati sebuah barang.</div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary" id="soProcessBtn"><i class="bi bi-box-arrow-up"></i> Proses Pemenuhan</button>
        <a href="<?= BASE_URL ?>/sales_order/detail/<?= $id ?>" class="btn btn-light border">Batal</a>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('soProcessForm');
    var body = document.getElementById('soProcBody');
    function num(v) {
        v = String(v || '').trim();
        if (v === '') { return 0; }
        if (v.indexOf(',') !== -1) { v = v.replace(/\./g, '').replace(',', '.'); } else { v = v.replace(/,/g, ''); }
        var n = parseFloat(v); return isNaN(n) ? 0 : n;
    }
    function fq(n) { return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }); }

    function check(tr) {
        var qty = num(tr.querySelector('.so-proc-qty').value);
        var rem = parseFloat(tr.dataset.remaining), unit = tr.dataset.unit;
        var sel = tr.querySelector('.so-source');
        var cap = sel ? parseFloat(sel.options[sel.selectedIndex].dataset.qty) : 0;
        var hint = tr.querySelector('.so-proc-hint'), bad = false, msg = '';
        if (qty > 0) {
            if (qty > rem + 0.004) { bad = true; msg = 'Melebihi sisa pesanan (' + fq(rem) + ').'; }
            else if (qty > cap + 0.004) { bad = true; msg = 'Melebihi stok pada sumber ini (' + fq(cap) + ').'; }
            else { msg = 'Sisa setelah ini: ' + fq(rem - qty) + ' ' + unit; }
        }
        hint.className = 'small mt-1 so-proc-hint ' + (bad ? 'text-danger' : 'text-muted');
        hint.textContent = msg;
        tr.querySelector('.so-proc-qty').classList.toggle('is-invalid', bad);
        return !bad;
    }
    body.addEventListener('input', function (e) { if (e.target.classList.contains('so-proc-qty')) { check(e.target.closest('tr')); } });
    body.addEventListener('change', function (e) { if (e.target.classList.contains('so-source')) { check(e.target.closest('tr')); } });
    body.querySelectorAll('.so-proc-row').forEach(check);

    var submitted = false;
    form.addEventListener('submit', function (e) {
        if (submitted) { e.preventDefault(); return; }   // klik ganda
        var any = false, ok = true;
        body.querySelectorAll('.so-proc-row').forEach(function (tr) {
            if (num(tr.querySelector('.so-proc-qty').value) > 0) { any = true; }
            if (!check(tr)) { ok = false; }
        });
        if (!any) { e.preventDefault(); alert('Isi jumlah pemenuhan minimal untuk satu barang.'); return; }
        if (!ok) { e.preventDefault(); alert('Periksa kembali jumlah yang melebihi sisa pesanan atau stok.'); return; }
        e.preventDefault();
        confirmAction('Proses pemenuhan sekarang? Stok akan langsung berkurang dan dokumen Pengeluaran Barang dibuat.', 'Ya, proses').then(function (okc) {
            if (okc && !submitted) {
                submitted = true;
                var btn = document.getElementById('soProcessBtn');
                btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...';
                form.submit();
            }
        });
    });
})();
</script>
<?php endif; ?>
