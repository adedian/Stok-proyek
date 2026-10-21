<?php
$id = (int) $order['id'];
$fq = fn($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',') ?: '0';
$fqIn = fn($n) => rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',') ?: '0';
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Terbitkan Invoice Keluar</h4>
        <small class="text-muted">Dari Order <strong><?= e($order['order_number']) ?></strong> &middot; <?= e($order['client_name']) ?> &middot; PO Customer <strong><?= e($order['po_number']) ?></strong></small>
    </div>
    <a href="<?= BASE_URL ?>/sales_order/detail/<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali ke Detail</a>
</div>

<div class="alert alert-info small">
    <i class="bi bi-info-circle"></i> Invoice hanya bisa menagih barang yang <strong>sudah dikeluarkan</strong> dan belum ditagih. Harga mengikuti Order Penjualan
    (sudah termasuk diskon). Penerbitan invoice <strong>tidak</strong> mengurangi stok. Satu order boleh punya beberapa invoice (penagihan bertahap).
    PO Customer dicantumkan sebagai rujukan kontrak pada dokumen invoice.
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=sales_order&action=invoiceStore" id="soInvoiceForm" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">No. Invoice <span class="text-danger">*</span></label>
                    <input type="text" name="invoice_number" class="form-control" maxlength="50" required autocomplete="off" placeholder="mis. 001/INV.HME/X/2026">
                    <div class="form-text">Ketik sendiri, harus unik (maks. 50 karakter).</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Invoice <span class="text-danger">*</span></label>
                    <input type="date" name="invoice_date" id="soInvDate" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tempo <span class="text-muted small">(hari)</span></label>
                    <input type="number" name="tempo" id="soInvTempo" class="form-control" min="0" step="1" placeholder="mis. 30">
                </div>
                <div class="col-md-3">
                    <label class="form-label">PPN (%)</label>
                    <input type="number" name="ppn_percent" id="soInvPpn" class="form-control" min="0" max="100" step="0.01" value="<?= e($order['ppn_percent']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jenis Invoice</label>
                    <select name="invoice_type" class="form-select" data-no-search>
                        <option value="project">Project (INV.HME)</option>
                        <option value="lampu">Lampu (FKT.HME)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jenis Dokumen</label>
                    <select name="document_kind" class="form-select" data-no-search>
                        <option value="invoice">Invoice</option>
                        <option value="proforma">Proforma Invoice</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Project <span class="text-muted small">(opsional)</span></label>
                    <select name="project_id" class="form-select">
                        <option value="">-- Tanpa Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= e($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Faktur Pajak <span class="text-muted small">(opsional)</span></label>
                    <input type="text" name="tax_invoice_number" class="form-control" maxlength="50">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-2">Barang yang Ditagih</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Barang</th>
                            <th class="text-end">Dipesan</th>
                            <th class="text-end">Sudah Dikeluarkan</th>
                            <th class="text-end">Sudah Ditagih</th>
                            <th class="text-end">Dapat Ditagih</th>
                            <th class="text-end">Harga (net)</th>
                            <th style="width:130px">Jumlah Ditagih</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="soInvBody">
                        <?php foreach ($items as $it): $inv = (float) $it['invoiceable_qty']; ?>
                            <tr class="so-inv-row" data-max="<?= e($inv) ?>" data-price="<?= e($it['net_price']) ?>">
                                <td data-label="Barang">
                                    <input type="hidden" name="line_id[]" value="<?= (int) $it['id'] ?>">
                                    <div class="fw-semibold"><?= e($it['item_name']) ?></div>
                                    <div class="small text-muted"><?= e($it['item_code']) ?> &middot; <?= e($it['unit']) ?></div>
                                </td>
                                <td data-label="Dipesan" class="text-end"><?= e($fq($it['qty'])) ?></td>
                                <td data-label="Sudah Dikeluarkan" class="text-end"><?= e($fq($it['fulfilled_qty'])) ?></td>
                                <td data-label="Sudah Ditagih" class="text-end"><?= e($fq($it['invoiced_qty'])) ?></td>
                                <td data-label="Dapat Ditagih" class="text-end fw-semibold"><?= e($fq($inv)) ?></td>
                                <td data-label="Harga (net)" class="text-end"><?= e(formatRupiah($it['net_price'])) ?></td>
                                <td data-label="Jumlah Ditagih">
                                    <input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end so-inv-qty" value="<?= e($fqIn($inv)) ?>">
                                    <div class="small text-danger so-inv-hint"></div>
                                </td>
                                <td data-label="Subtotal" class="text-end so-inv-sub">Rp 0,00</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light"><td colspan="7" class="text-end fw-bold">Jumlah</td><td class="text-end fw-bold" id="soInvSum">Rp 0,00</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <div class="card-section-title mb-0">Termin Tagihan</div>
                    <div class="small text-muted">Total persentase maks. 100%. Default 1 termin 100% (pelunasan).</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="soAddTerm"><i class="bi bi-plus-lg"></i> Tambah Termin</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0">
                    <thead class="table-light">
                        <tr><th style="width:130px">Persentase</th><th>Label</th><th class="text-end">Nominal</th><th class="text-end">PPN</th><th class="text-end">Total</th><th style="width:50px"></th></tr>
                    </thead>
                    <tbody id="soTermBody">
                        <tr class="so-term-row">
                            <td data-label="Persentase"><input type="number" name="term_percentage[]" class="form-control form-control-sm so-term-pct" min="0.01" max="100" step="0.01" value="100"></td>
                            <td data-label="Label"><input type="text" name="term_label[]" class="form-control form-control-sm" value="Pelunasan"></td>
                            <td data-label="Nominal" class="text-end so-term-amt">Rp 0,00</td>
                            <td data-label="PPN" class="text-end so-term-ppn">Rp 0,00</td>
                            <td data-label="Total" class="text-end fw-semibold so-term-tot">Rp 0,00</td>
                            <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger so-term-del"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="table-light"><td colspan="2" class="text-end fw-bold">Total Keseluruhan</td>
                            <td class="text-end fw-bold" id="soTermSumAmt">Rp 0,00</td><td class="text-end fw-bold" id="soTermSumPpn">Rp 0,00</td><td class="text-end fw-bold" id="soTermSumTot">Rp 0,00</td><td></td></tr>
                    </tfoot>
                </table>
            </div>
            <div id="soTermWarn" class="small mt-2"></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tanda Tangan</label>
                    <select name="signature_id" class="form-select">
                        <option value="">-- Tanpa Tanda Tangan Gambar --</option>
                        <?php foreach ($signatures as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> &middot; <?= e($s['position']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="soInvBtn"><i class="bi bi-receipt"></i> Terbitkan Invoice</button>
        <a href="<?= BASE_URL ?>/sales_order/detail/<?= $id ?>" class="btn btn-light border">Batal</a>
    </div>
</form>

<script>
(function () {
    var form = document.getElementById('soInvoiceForm');
    var body = document.getElementById('soInvBody');
    var termBody = document.getElementById('soTermBody');
    function num(v) {
        v = String(v || '').trim(); if (v === '') { return 0; }
        if (v.indexOf(',') !== -1) { v = v.replace(/\./g, '').replace(',', '.'); } else { v = v.replace(/,/g, ''); }
        var n = parseFloat(v); return isNaN(n) ? 0 : n;
    }
    function rp(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fq(n) { return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }); }

    function recalc() {
        var subtotal = 0, ok = true;
        body.querySelectorAll('.so-inv-row').forEach(function (tr) {
            var qty = num(tr.querySelector('.so-inv-qty').value), max = parseFloat(tr.dataset.max), price = parseFloat(tr.dataset.price);
            var sub = Math.round(qty * price * 100) / 100;
            tr.querySelector('.so-inv-sub').textContent = rp(sub);
            subtotal += sub;
            var bad = qty > max + 0.004 || qty < 0;
            tr.querySelector('.so-inv-hint').textContent = bad ? 'Melebihi yang dapat ditagih (' + fq(max) + ').' : '';
            tr.querySelector('.so-inv-qty').classList.toggle('is-invalid', bad);
            if (bad) { ok = false; }
        });
        document.getElementById('soInvSum').textContent = rp(subtotal);
        var ppn = parseFloat(document.getElementById('soInvPpn').value) || 0;
        var sa = 0, sp = 0, st = 0, pct = 0;
        termBody.querySelectorAll('.so-term-row').forEach(function (tr) {
            var p = parseFloat(tr.querySelector('.so-term-pct').value) || 0;
            var amt = Math.round(subtotal * p) / 100, pp = Math.round(amt * ppn) / 100, tot = Math.round((amt + pp) * 100) / 100;
            tr.querySelector('.so-term-amt').textContent = rp(amt);
            tr.querySelector('.so-term-ppn').textContent = rp(pp);
            tr.querySelector('.so-term-tot').textContent = rp(tot);
            sa += amt; sp += pp; st += tot; pct += p;
        });
        document.getElementById('soTermSumAmt').textContent = rp(sa);
        document.getElementById('soTermSumPpn').textContent = rp(sp);
        document.getElementById('soTermSumTot').textContent = rp(st);
        var w = document.getElementById('soTermWarn'); pct = Math.round(pct * 100) / 100;
        if (pct > 100.01) { w.className = 'small mt-2 text-danger fw-semibold'; w.textContent = 'Total persentase termin ' + pct + '% -- melebihi 100%.'; ok = false; }
        else if (pct < 99.99) { w.className = 'small mt-2 text-warning-emphasis fw-semibold'; w.textContent = 'Total persentase termin baru ' + pct + '% (belum 100%).'; }
        else { w.className = 'small mt-2 text-success'; w.textContent = 'Total persentase termin 100%.'; }
        return ok;
    }
    body.addEventListener('input', recalc);
    termBody.addEventListener('input', recalc);
    document.getElementById('soInvPpn').addEventListener('input', recalc);
    termBody.addEventListener('click', function (e) {
        var b = e.target.closest('.so-term-del'); if (!b) { return; }
        if (termBody.querySelectorAll('.so-term-row').length <= 1) { alert('Minimal harus ada 1 baris termin.'); return; }
        b.closest('.so-term-row').remove(); recalc();
    });
    document.getElementById('soAddTerm').addEventListener('click', function () {
        var tr = document.createElement('tr'); tr.className = 'so-term-row';
        tr.innerHTML = '<td data-label="Persentase"><input type="number" name="term_percentage[]" class="form-control form-control-sm so-term-pct" min="0.01" max="100" step="0.01"></td>' +
            '<td data-label="Label"><input type="text" name="term_label[]" class="form-control form-control-sm" placeholder="mis. Termin 2"></td>' +
            '<td data-label="Nominal" class="text-end so-term-amt">Rp 0,00</td><td data-label="PPN" class="text-end so-term-ppn">Rp 0,00</td>' +
            '<td data-label="Total" class="text-end fw-semibold so-term-tot">Rp 0,00</td>' +
            '<td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger so-term-del"><i class="bi bi-trash"></i></button></td>';
        termBody.appendChild(tr); recalc();
    });

    var submitted = false;
    form.addEventListener('submit', function (e) {
        if (submitted) { e.preventDefault(); return; }
        var any = false;
        body.querySelectorAll('.so-inv-qty').forEach(function (i) { if (num(i.value) > 0) { any = true; } });
        if (!form.invoice_number.value.trim()) { e.preventDefault(); alert('No. Invoice wajib diisi.'); return; }
        if (!form.invoice_date.value) { e.preventDefault(); alert('Tanggal invoice wajib diisi.'); return; }
        if (!any) { e.preventDefault(); alert('Isi jumlah tagihan minimal untuk satu barang.'); return; }
        if (!recalc()) { e.preventDefault(); alert('Periksa kembali jumlah tagihan / persentase termin.'); return; }
        submitted = true;
        var btn = document.getElementById('soInvBtn'); btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menerbitkan...';
    });
    window.addEventListener('pageshow', function () { submitted = false; var b = document.getElementById('soInvBtn'); b.disabled = false; b.innerHTML = '<i class="bi bi-receipt"></i> Terbitkan Invoice'; });
    recalc();
})();
</script>
