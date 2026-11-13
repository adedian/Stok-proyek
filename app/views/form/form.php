<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';

// Qty tampil gaya Indonesia (koma desimal, tanpa nol di belakang): 28 / 0,5.
$fmtQty = fn($v) => ($v === null || $v === '') ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
// Nominal gaya field .currency-input: koma ribuan, titik desimal.
$fmtMoney = fn($v) => ($v === null || $v === '' || (float) $v == 0.0) ? '' : number_format((float) $v, 2, '.', ',');

$parties = [
    'to'   => ['title' => 'Kepada',   'icon' => 'bi-box-arrow-in-down'],
    'from' => ['title' => 'Pengirim', 'icon' => 'bi-box-arrow-up'],
];

// Baris awal rincian: yang tersimpan, atau 3 baris kosong untuk form baru.
$orderItems = !empty($d['items']) && $type === 'order_form' ? $d['items'] : array_fill(0, 3, ['qty' => '', 'description' => '', 'price' => '']);
$bonItems   = !empty($d['items']) && $type === 'bon_sementara' ? $d['items'] : array_fill(0, 3, ['description' => '', 'amount' => '']);
$today = date('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Form</h4>
        <small class="text-muted">Pilih jenis form, isian di bawah menyesuaikan jenis yang dipilih</small>
    </div>
    <a href="<?= BASE_URL ?>/form" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=form&action=<?= $actionUrl ?>" id="formDoc">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label" for="formType">Jenis Form <span class="text-danger">*</span></label>
            <?php if ($isEdit): ?>
                <input type="text" class="form-control" value="<?= e($types[$type]) ?>" disabled>
                <div class="form-text">Jenis form tidak bisa diubah setelah dibuat.</div>
                <input type="hidden" id="formType" value="<?= e($type) ?>">
            <?php else: ?>
                <select name="form_type" id="formType" class="form-select" required>
                    <?php foreach ($types as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $type === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <!-- ================= PENGIRIMAN DOKUMEN ================= -->
    <fieldset class="fm-section" data-type="pengiriman">
        <div class="row g-3 mb-3">
            <?php foreach ($parties as $p => $cfg): ?>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white fw-semibold"><i class="bi <?= $cfg['icon'] ?>"></i> <?= e($cfg['title']) ?></div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="<?= $p ?>_name">Nama <span class="text-danger">*</span></label>
                                    <input type="text" id="<?= $p ?>_name" name="<?= $p ?>_name" class="form-control" maxlength="150" required
                                           value="<?= e($d[$p . '_name'] ?? '') ?>" data-preview="<?= $p ?>-name">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="<?= $p ?>_company">Nama Perusahaan</label>
                                    <input type="text" id="<?= $p ?>_company" name="<?= $p ?>_company" class="form-control" maxlength="150"
                                           value="<?= e($d[$p . '_company'] ?? '') ?>" data-preview="<?= $p ?>-company">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="<?= $p ?>_address">Alamat <span class="text-danger">*</span></label>
                                    <input type="text" id="<?= $p ?>_address" name="<?= $p ?>_address" class="form-control" maxlength="255" required
                                           value="<?= e($d[$p . '_address'] ?? '') ?>" data-preview="<?= $p ?>-address">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="<?= $p ?>_city">Kelurahan / Kota</label>
                                    <input type="text" id="<?= $p ?>_city" name="<?= $p ?>_city" class="form-control" maxlength="150"
                                           value="<?= e($d[$p . '_city'] ?? '') ?>" data-preview="<?= $p ?>-city">
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="<?= $p ?>_postal_code">Kode Pos</label>
                                    <input type="text" id="<?= $p ?>_postal_code" name="<?= $p ?>_postal_code" class="form-control" maxlength="20" inputmode="numeric"
                                           value="<?= e($d[$p . '_postal_code'] ?? '') ?>" data-preview="<?= $p ?>-postal">
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="<?= $p ?>_phone">No. Telp</label>
                                    <input type="text" id="<?= $p ?>_phone" name="<?= $p ?>_phone" class="form-control" maxlength="50" inputmode="tel"
                                           value="<?= e($d[$p . '_phone'] ?? '') ?>" data-preview="<?= $p ?>-phone">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-eye"></i> Pratinjau Cetak</div>
            <div class="card-body">
                <div class="sl-form-preview">
                    <?php foreach ($parties as $p => $cfg): ?>
                        <div class="sl-box">
                            <div class="sl-title"><?= e($cfg['title']) ?> :</div>
                            <div class="sl-name-main" data-out="<?= $p ?>-name"></div>
                            <div class="sl-name-company" data-out="<?= $p ?>-company"></div>
                            <div data-out="<?= $p ?>-address"></div>
                            <div data-out="<?= $p ?>-city"></div>
                            <div data-out="<?= $p ?>-postal" data-prefix="Kode Pos "></div>
                            <div data-out="<?= $p ?>-phone" data-prefix="No. Telp "></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </fieldset>

    <!-- ================= KWITANSI ================= -->
    <fieldset class="fm-section" data-type="kwitansi">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt"></i> Kwitansi</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="received_from">Sudah terima dari <span class="text-danger">*</span></label>
                        <input type="text" id="received_from" name="received_from" class="form-control" maxlength="200" required
                               value="<?= e($d['received_from'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="kw_amount">Jumlah Uang (Rp) <span class="text-danger">*</span></label>
                        <input type="text" id="kw_amount" name="amount" class="form-control currency-input" inputmode="decimal" required
                               value="<?= e($fmtMoney($d['amount'] ?? '')) ?>">
                        <div class="form-text">"Banyaknya uang" (terbilang) terisi otomatis dari jumlah ini saat dicetak.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="kw_purpose">Untuk pembayaran <span class="text-danger">*</span></label>
                        <textarea id="kw_purpose" name="purpose" class="form-control" rows="2" maxlength="500" required><?= e($d['purpose'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="kw_city">Kota</label>
                        <input type="text" id="kw_city" name="city" class="form-control" maxlength="100" value="<?= e($d['city'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="kw_date">Tanggal</label>
                        <input type="date" id="kw_date" name="form_date" class="form-control" value="<?= e($d['form_date'] ?? $today) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="signer_name">Nama Penandatangan</label>
                        <input type="text" id="signer_name" name="signer_name" class="form-control" maxlength="150" value="<?= e($d['signer_name'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </fieldset>

    <!-- ================= ORDER FORM ================= -->
    <fieldset class="fm-section" data-type="order_form">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-card-checklist"></i> Order Form</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="of_date">Date</label>
                        <input type="date" id="of_date" name="order_date" class="form-control" value="<?= e($d['order_date'] ?? $today) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="order_number">Order Number</label>
                        <input type="text" id="order_number" name="order_number" class="form-control" maxlength="50" value="<?= e($d['order_number'] ?? '') ?>">
                    </div>
                    <div class="col-md-4"></div>
                    <div class="col-md-4">
                        <label class="form-label" for="of_name">Name <span class="text-danger">*</span></label>
                        <input type="text" id="of_name" name="name" class="form-control" maxlength="150" required value="<?= e($d['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="of_phone">Phone</label>
                        <input type="text" id="of_phone" name="phone" class="form-control" maxlength="50" inputmode="tel" value="<?= e($d['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="of_email">Email</label>
                        <input type="text" id="of_email" name="email" class="form-control" maxlength="150" inputmode="email" value="<?= e($d['email'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="of_address">Address</label>
                        <textarea id="of_address" name="address" class="form-control" rows="2" maxlength="500"><?= e($d['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="of_payment">Payment</label>
                        <select id="of_payment" name="payment" class="form-select">
                            <option value="">-</option>
                            <option value="cash" <?= ($d['payment'] ?? '') === 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="transfer" <?= ($d['payment'] ?? '') === 'transfer' ? 'selected' : '' ?>>Transfer</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="of_notes">Notes</label>
                        <textarea id="of_notes" name="notes" class="form-control" rows="2" maxlength="500"><?= e($d['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-list-ol"></i> Daftar Barang</span>
                <button type="button" class="btn btn-sm btn-outline-primary js-fm-add" data-target="#ofRows"><i class="bi bi-plus-lg"></i> Tambah Baris</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 entry-cards">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 110px;">Qty</th>
                                <th>Description</th>
                                <th style="width: 190px;">Price</th>
                                <th class="text-end" style="width: 170px;">Amount</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="ofRows" data-template="#ofRowTpl">
                            <?php foreach ($orderItems as $it): ?>
                                <tr class="fm-row">
                                    <td data-label="Qty"><input type="text" name="item_qty[]" class="form-control form-control-sm fm-qty" inputmode="decimal" value="<?= e($fmtQty($it['qty'])) ?>"></td>
                                    <td data-label="Description"><input type="text" name="item_description[]" class="form-control form-control-sm" maxlength="255" value="<?= e($it['description']) ?>"></td>
                                    <td data-label="Price"><input type="text" name="item_price[]" class="form-control form-control-sm currency-input fm-price" inputmode="decimal" value="<?= e($fmtMoney($it['price'])) ?>"></td>
                                    <td data-label="Amount" class="text-end fm-amount">-</td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger js-fm-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end">Sub Total</td>
                                <td class="text-end fw-semibold" id="ofSubtotal">0.00</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end">
                                    Tax (%)
                                    <input type="text" name="tax_percent" id="ofTaxPercent" class="form-control form-control-sm d-inline-block text-end" style="width: 90px;"
                                           inputmode="decimal" value="<?= e($fmtQty($d['tax_percent'] ?? '')) ?>">
                                </td>
                                <td class="text-end" id="ofTax">0.00</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end fw-bold">Total</td>
                                <td class="text-end fw-bold" id="ofTotal">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <template id="ofRowTpl">
            <tr class="fm-row">
                <td data-label="Qty"><input type="text" name="item_qty[]" class="form-control form-control-sm fm-qty" inputmode="decimal"></td>
                <td data-label="Description"><input type="text" name="item_description[]" class="form-control form-control-sm" maxlength="255"></td>
                <td data-label="Price"><input type="text" name="item_price[]" class="form-control form-control-sm currency-input fm-price" inputmode="decimal"></td>
                <td data-label="Amount" class="text-end fm-amount">-</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger js-fm-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
            </tr>
        </template>
    </fieldset>

    <!-- ================= PENGAJUAN BON SEMENTARA ================= -->
    <fieldset class="fm-section" data-type="bon_sementara">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin"></i> Pengajuan Bon Sementara</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="bon_name">Nama <span class="text-danger">*</span></label>
                        <input type="text" id="bon_name" name="name" class="form-control" maxlength="150" required value="<?= e($d['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="bon_position">Jabatan</label>
                        <input type="text" id="bon_position" name="position" class="form-control" maxlength="150" value="<?= e($d['position'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="bon_purpose">Keperluan <span class="text-danger">*</span></label>
                        <input type="text" id="bon_purpose" name="purpose" class="form-control" maxlength="255" required value="<?= e($d['purpose'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="bon_date">Tgl. Pengajuan</label>
                        <input type="date" id="bon_date" name="request_date" class="form-control" value="<?= e($d['request_date'] ?? $today) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="bon_city">Kota</label>
                        <input type="text" id="bon_city" name="city" class="form-control" maxlength="100" value="<?= e($d['city'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-list-ol"></i> Estimasi Rincian Pengeluaran</span>
                <button type="button" class="btn btn-sm btn-outline-primary js-fm-add" data-target="#bonRows"><i class="bi bi-plus-lg"></i> Tambah Baris</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 entry-cards">
                        <thead class="table-light">
                            <tr>
                                <th>Rincian</th>
                                <th style="width: 220px;">Total (Rp)</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="bonRows" data-template="#bonRowTpl">
                            <?php foreach ($bonItems as $it): ?>
                                <tr class="fm-row">
                                    <td data-label="Rincian"><textarea name="item_description[]" class="form-control form-control-sm" rows="2" maxlength="500"><?= e($it['description']) ?></textarea></td>
                                    <td data-label="Total (Rp)"><input type="text" name="item_amount[]" class="form-control form-control-sm currency-input fm-bon-amount" inputmode="decimal" value="<?= e($fmtMoney($it['amount'])) ?>"></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger js-fm-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="text-end fw-bold">Total Pengeluaran (Bon Sementara)</td>
                                <td class="fw-bold" id="bonTotal">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <template id="bonRowTpl">
            <tr class="fm-row">
                <td data-label="Rincian"><textarea name="item_description[]" class="form-control form-control-sm" rows="2" maxlength="500"></textarea></td>
                <td data-label="Total (Rp)"><input type="text" name="item_amount[]" class="form-control form-control-sm currency-input fm-bon-amount" inputmode="decimal"></td>
                <td><button type="button" class="btn btn-sm btn-outline-danger js-fm-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
            </tr>
        </template>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pen"></i> Nama di Kolom Tanda Tangan <span class="text-muted fw-normal">(opsional)</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="approved_by">Disetujui</label>
                        <input type="text" id="approved_by" name="approved_by" class="form-control" maxlength="150" value="<?= e($d['approved_by'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="known_by">Mengetahui</label>
                        <input type="text" id="known_by" name="known_by" class="form-control" maxlength="150" value="<?= e($d['known_by'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="made_by">Dibuat</label>
                        <input type="text" id="made_by" name="made_by" class="form-control" maxlength="150" value="<?= e($d['made_by'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </fieldset>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan &amp; Cetak</button>
        <a href="<?= BASE_URL ?>/form" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>

<style>
    .fm-section { border: 0; padding: 0; margin: 0; min-width: 0; }
    .fm-section[disabled] { display: none; }
    .sl-form-preview { display: flex; flex-wrap: wrap; gap: 14px; }
    .sl-form-preview .sl-box {
        flex: 1 1 260px; max-width: 400px; border: 1px solid #212529; border-radius: 0;
        padding: 14px 20px; font-size: 15px; line-height: 1.55; background: #fff; color: #212529;
    }
    .sl-form-preview .sl-title { font-weight: 700; }
    .sl-form-preview .sl-name-main { font-weight: 700; font-size: 18px; text-decoration: underline; }
    .sl-form-preview .sl-name-company { font-weight: 700; font-size: 16px; }
    .sl-form-preview .sl-box > div:empty { display: none; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var typeEl = document.getElementById('formType');
    var sections = document.querySelectorAll('.fm-section');

    // Hanya section jenis terpilih yang aktif: fieldset disabled -> tidak ikut terkirim & tidak kena "required".
    function applyType() {
        sections.forEach(function (s) { s.disabled = s.dataset.type !== typeEl.value; });
    }
    typeEl.addEventListener('change', applyType);
    applyType();

    // ---- pratinjau Pengiriman ----
    document.querySelectorAll('#formDoc [data-preview]').forEach(function (input) {
        function render() {
            var out = document.querySelector('[data-out="' + input.dataset.preview + '"]');
            if (!out) return;
            var v = input.value.trim();
            out.textContent = v === '' ? '' : (out.dataset.prefix || '') + v;
        }
        render();
        input.addEventListener('input', render);
    });

    // ---- baris dinamis (Order Form & Bon Sementara) ----
    function money(n) { return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function num(s) { return parseFloat(String(s).replace(/,/g, '')) || 0; }            // nominal: koma ribuan, titik desimal
    function qty(s) { return parseFloat(String(s).replace(/\./g, '').replace(',', '.')) || 0; } // qty: koma desimal

    function recalc() {
        var sub = 0;
        document.querySelectorAll('#ofRows .fm-row').forEach(function (tr) {
            var a = Math.round(qty(tr.querySelector('.fm-qty').value) * num(tr.querySelector('.fm-price').value) * 100) / 100;
            tr.querySelector('.fm-amount').textContent = a ? money(a) : '-';
            sub += a;
        });
        var tax = Math.round(sub * qty(document.getElementById('ofTaxPercent').value)) / 100;
        document.getElementById('ofSubtotal').textContent = money(sub);
        document.getElementById('ofTax').textContent = money(tax);
        document.getElementById('ofTotal').textContent = money(sub + tax);

        var bon = 0;
        document.querySelectorAll('.fm-bon-amount').forEach(function (i) { bon += num(i.value); });
        document.getElementById('bonTotal').textContent = money(bon);
    }

    document.querySelectorAll('.js-fm-add').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var body = document.querySelector(btn.dataset.target);
            if (body.querySelectorAll('.fm-row').length >= <?= 30 ?>) { return; }
            var tpl = document.querySelector(body.dataset.template);
            body.appendChild(tpl.content.cloneNode(true));
            var rows = body.querySelectorAll('.fm-row');
            var first = rows[rows.length - 1].querySelector('input, textarea');
            if (first) first.focus();
        });
    });

    document.getElementById('formDoc').addEventListener('click', function (e) {
        var rm = e.target.closest('.js-fm-remove');
        if (!rm) return;
        var body = rm.closest('tbody');
        if (body.querySelectorAll('.fm-row').length > 1) {
            rm.closest('.fm-row').remove();
        } else {
            rm.closest('.fm-row').querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
        }
        recalc();
    });
    document.getElementById('formDoc').addEventListener('input', recalc);
    recalc();
});
</script>
