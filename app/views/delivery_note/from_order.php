<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Buat Surat Jalan dari Order Penjualan</h4>
        <small class="text-muted">Pilih Order Penjualan, sistem mengisi customer &amp; barang otomatis. Stok baru berkurang saat Surat Jalan disimpan.</small>
    </div>
    <a href="<?= BASE_URL ?>/stock_out" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=delivery_note&action=storeFromOrder" id="dnOrderForm" novalidate>
    <?= csrfField() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <input type="hidden" name="sales_order_id" id="dnOrderId" value="">

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label" for="dnOrderSearch">Sumber Order Penjualan <span class="text-danger">*</span></label>
            <div class="position-relative">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="dnOrderSearch" class="form-control" autocomplete="off"
                           placeholder="Cari No. Order, No. PO Customer, atau nama customer...">
                    <button type="button" class="btn btn-outline-secondary d-none" id="dnOrderClear" title="Ganti order"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="list-group position-absolute w-100 shadow d-none" id="dnOrderResults" style="z-index:1050; max-height:340px; overflow-y:auto;"></div>
            </div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="dnIncludeDone">
                <label class="form-check-label small" for="dnIncludeDone">Tampilkan juga order yang sudah selesai dikirim (hanya untuk dilihat, tidak bisa dikirim lagi)</label>
            </div>
            <div class="form-text">Hanya Order Penjualan aktif yang masih punya sisa pesanan yang ditampilkan (maks. 20 hasil, ketik untuk mempersempit).</div>
        </div>
    </div>

    <div id="dnLoading" class="text-center text-muted py-4 d-none">
        <span class="spinner-border spinner-border-sm"></span> Mengambil data order...
    </div>
    <div id="dnError" class="alert alert-danger d-none"></div>

    <div id="dnOrderBody" class="d-none">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div class="card-section-title mb-0">Data Order &amp; Customer</div>
                    <span class="badge" id="dnShipBadge"></span>
                </div>
                <div class="row g-3 small">
                    <div class="col-md-3"><div class="text-muted">No. Order Penjualan</div><div class="fw-semibold" id="infoOrderNo"></div></div>
                    <div class="col-md-3"><div class="text-muted">Tanggal Order</div><div id="infoOrderDate"></div></div>
                    <div class="col-md-3"><div class="text-muted">No. PO Customer</div><div class="fw-semibold" id="infoPoNo"></div></div>
                    <div class="col-md-3"><div class="text-muted">Tanggal PO Customer</div><div id="infoPoDate"></div></div>
                    <div class="col-md-3"><div class="text-muted">Customer</div><div class="fw-semibold" id="infoClient"></div></div>
                    <div class="col-md-6"><div class="text-muted">Alamat</div><div id="infoAddress" style="white-space:pre-line"></div></div>
                    <div class="col-md-3"><div class="text-muted">Telepon</div><div id="infoPhone"></div></div>
                    <div class="col-12 d-none" id="infoNotesWrap"><div class="text-muted">Catatan Order</div><div id="infoNotes" style="white-space:pre-line"></div></div>
                </div>
                <div class="alert alert-warning small mt-3 mb-0 d-none" id="dnUngrouped"></div>
                <div class="alert alert-secondary small mt-3 mb-0 d-none" id="dnBlocked"></div>
            </div>
        </div>

        <div id="dnShipPart">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="card-section-title mb-2">Barang yang Dikirim</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle entry-cards mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:44px">No.</th>
                                    <th>Kode Barang</th>
                                    <th>Nama Barang</th>
                                    <th class="text-end">Qty Order</th>
                                    <th class="text-end">Sudah Dikirim</th>
                                    <th class="text-end">Sisa Pesanan</th>
                                    <th class="text-end">Stok Tersedia</th>
                                    <th style="width:150px">Qty Dikirim</th>
                                </tr>
                            </thead>
                            <tbody id="dnItemsBody"></tbody>
                        </table>
                    </div>
                    <div class="form-text mt-2">Qty Dikirim otomatis diisi sebesar yang bisa dikirim sekarang (terkecil antara sisa pesanan dan stok). Ubah bila perlu; kosongkan / 0 untuk melewati sebuah barang. Sisa pesanan dapat dikirim di Surat Jalan berikutnya.</div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">No. Surat Jalan <span class="text-danger">*</span></label>
                            <input type="text" name="delivery_number" class="form-control" maxlength="50" required
                                   autocomplete="off" placeholder="mis. 001/SJ.HME/X/2026">
                            <div class="form-text">Ketik sendiri, harus unik (maks. 50 karakter).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal Surat Jalan <span class="text-danger">*</span></label>
                            <input type="date" name="delivery_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
                            <div class="form-text">Tanggal ini juga menjadi tanggal Pengeluaran Barang.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Tujuan <span class="text-muted small">(otomatis nama customer, bisa diubah)</span></label>
                            <input type="text" name="destination_name" id="dnDestName" class="form-control" maxlength="200">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Kota <span class="text-muted small">(untuk baris "Kota, Tanggal" di penutup Surat Jalan)</span></label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">No. Kendaraan</label>
                            <input type="text" name="vehicle_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama Driver</label>
                            <input type="text" name="driver_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pengirim</label>
                            <input type="text" name="sender_name" class="form-control" value="<?= e(currentUserName()) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Penerima <span class="text-muted small">(diisi manual saat serah terima)</span></label>
                            <input type="text" name="recipient_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanda Tangan HME</label>
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

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary" id="dnSubmitBtn"><i class="bi bi-save"></i> Simpan &amp; Cetak Surat Jalan</button>
                <a href="<?= BASE_URL ?>/stock_out" class="btn btn-light border">Batal</a>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var AJAX = '<?= BASE_URL ?>/index.php?module=delivery_note&action=';
    var PRESET = <?= (int) $presetOrderId ?>;
    var form = document.getElementById('dnOrderForm');
    var search = document.getElementById('dnOrderSearch');
    var results = document.getElementById('dnOrderResults');
    var clearBtn = document.getElementById('dnOrderClear');
    var idInput = document.getElementById('dnOrderId');
    var includeDone = document.getElementById('dnIncludeDone');
    var loading = document.getElementById('dnLoading');
    var errBox = document.getElementById('dnError');
    var body = document.getElementById('dnOrderBody');
    var shipPart = document.getElementById('dnShipPart');
    var itemsBody = document.getElementById('dnItemsBody');
    var submitBtn = document.getElementById('dnSubmitBtn');
    var submitted = false, searchTimer = null, searchSeq = 0, loadSeq = 0, hasOrder = false;

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function fq(n) { return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 }); }
    function fqIn(n) { return Number(n || 0).toLocaleString('id-ID', { maximumFractionDigits: 2, useGrouping: false }); }
    function num(v) {
        v = String(v || '').trim();
        if (v === '') { return 0; }
        if (v.indexOf(',') !== -1) { v = v.replace(/\./g, '').replace(',', '.'); } else { v = v.replace(/,/g, ''); }
        var n = parseFloat(v); return isNaN(n) ? 0 : n;
    }
    function getJson(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) {
            return r.json().then(function (j) { if (!r.ok) { throw new Error(j.error || 'Gagal memuat data.'); } return j; });
        });
    }

    // ---------- Dropdown pencarian order ----------
    function showResults(list) {
        if (!list.length) {
            results.innerHTML = '<div class="list-group-item text-muted small">Tidak ada Order Penjualan yang cocok.</div>';
        } else {
            results.innerHTML = list.map(function (o) {
                return '<button type="button" class="list-group-item list-group-item-action" data-id="' + o.id + '" data-label="' +
                    esc(o.order_number + ' | PO Customer: ' + o.po_number + ' | ' + o.client_name) + '">' +
                    '<div class="d-flex justify-content-between gap-2"><span class="fw-semibold">' + esc(o.order_number) + '</span>' +
                    (o.done ? '<span class="badge bg-success">Selesai dikirim</span>'
                            : (o.shipped > 0 ? '<span class="badge bg-warning text-dark">Dikirim sebagian</span>' : '<span class="badge bg-light text-muted border">Belum dikirim</span>')) + '</div>' +
                    '<div class="small text-muted">PO Customer: ' + esc(o.po_number) + ' &middot; ' + esc(o.client_name) + '</div>' +
                    '<div class="small text-muted">' + esc(o.order_date) + (o.done ? '' : ' &middot; sisa ' + fq(o.remaining)) + '</div></button>';
            }).join('');
        }
        results.classList.remove('d-none');
    }
    function runSearch() {
        var seq = ++searchSeq;
        results.innerHTML = '<div class="list-group-item small text-muted"><span class="spinner-border spinner-border-sm"></span> Mencari...</div>';
        results.classList.remove('d-none');
        getJson(AJAX + 'ajaxOrders&q=' + encodeURIComponent(search.value.trim()) + (includeDone.checked ? '&include_done=1' : ''))
            .then(function (j) { if (seq === searchSeq) { showResults(j.orders || []); } })
            .catch(function (e) { if (seq === searchSeq) { results.innerHTML = '<div class="list-group-item text-danger small">' + esc(e.message) + '</div>'; } });
    }
    search.addEventListener('focus', function () { if (!hasOrder) { runSearch(); } });
    search.addEventListener('input', function () {
        if (hasOrder) { return; }
        clearTimeout(searchTimer); searchTimer = setTimeout(runSearch, 250);
    });
    includeDone.addEventListener('change', function () { if (!hasOrder) { runSearch(); } });
    results.addEventListener('click', function (e) {
        var b = e.target.closest('[data-id]'); if (!b) { return; }
        pick(parseInt(b.dataset.id, 10), b.dataset.label);
    });
    document.addEventListener('click', function (e) {
        if (!results.contains(e.target) && e.target !== search) { results.classList.add('d-none'); }
    });
    clearBtn.addEventListener('click', function () { reset(); search.focus(); });

    function reset() {
        hasOrder = false; loadSeq++;
        idInput.value = ''; search.value = ''; search.readOnly = false;
        clearBtn.classList.add('d-none');
        body.classList.add('d-none'); itemsBody.innerHTML = '';
        errBox.classList.add('d-none'); loading.classList.add('d-none');
    }

    // ---------- Muat order terpilih ----------
    function pick(id, label) {
        // Ganti order = bersihkan seluruh detail lama supaya data dua order tidak tercampur.
        reset();
        hasOrder = true;
        idInput.value = id; search.value = label || ''; search.readOnly = true;
        clearBtn.classList.remove('d-none');
        results.classList.add('d-none');
        loading.classList.remove('d-none');
        var seq = ++loadSeq;
        getJson(AJAX + 'ajaxOrder&id=' + id).then(function (j) {
            if (seq !== loadSeq) { return; }
            loading.classList.add('d-none');
            render(j);
        }).catch(function (e) {
            if (seq !== loadSeq) { return; }
            loading.classList.add('d-none');
            errBox.textContent = e.message; errBox.classList.remove('d-none');
            idInput.value = '';
        });
    }

    function render(j) {
        var o = j.order;
        if (!search.value) { search.value = o.order_number + ' | PO Customer: ' + o.po_number + ' | ' + o.client_name; }
        document.getElementById('infoOrderNo').textContent = o.order_number;
        document.getElementById('infoOrderDate').textContent = o.order_date;
        document.getElementById('infoPoNo').textContent = o.po_number;
        document.getElementById('infoPoDate').textContent = o.po_date;
        document.getElementById('infoClient').textContent = o.client_name;
        document.getElementById('infoAddress').textContent = o.address || '-';
        document.getElementById('infoPhone').textContent = o.phone || '-';
        document.getElementById('infoNotes').textContent = o.notes;
        document.getElementById('infoNotesWrap').classList.toggle('d-none', !o.notes);
        var sb = document.getElementById('dnShipBadge');
        sb.className = 'badge bg-' + o.ship_badge; sb.textContent = 'Pengiriman: ' + o.ship_label;
        document.getElementById('dnDestName').value = o.client_name;

        var ung = document.getElementById('dnUngrouped');
        if (o.ungrouped_rows > 0) {
            ung.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Ada <strong>' + o.ungrouped_rows + ' baris pengeluaran</strong> dari order ini yang sudah dikeluarkan lewat halaman Proses tetapi belum masuk Surat Jalan. ' +
                'Barang itu sudah dihitung pada kolom "Sudah Dikirim". Terbitkan Surat Jalannya dari <a href="<?= BASE_URL ?>/sales_order/detail/' + o.id + '">detail order</a>.';
            ung.classList.remove('d-none');
        } else { ung.classList.add('d-none'); }

        var blocked = document.getElementById('dnBlocked');
        if (j.blocked) { blocked.textContent = j.blocked; blocked.classList.remove('d-none'); shipPart.classList.add('d-none'); }
        else { blocked.classList.add('d-none'); shipPart.classList.remove('d-none'); }

        itemsBody.innerHTML = j.items.map(function (it, i) {
            var rem = it.remaining, canSend = rem > 0.004 && it.max_send > 0.004;
            var lbl = (it.item_code ? it.item_code : '-');
            var qtyCell;
            if (rem <= 0.004) { qtyCell = '<span class="badge bg-success">Terkirim penuh</span>'; }
            else if (!canSend) { qtyCell = '<input type="hidden" name="line_id[]" value="' + it.id + '"><input type="text" name="qty[]" class="form-control form-control-sm text-end" value="" readonly placeholder="0"><div class="small mt-1 text-danger dn-hint">Stok kosong &mdash; belum bisa dikirim.</div>'; }
            else {
                qtyCell = '<input type="hidden" name="line_id[]" value="' + it.id + '"><input type="text" inputmode="decimal" name="qty[]" class="form-control form-control-sm text-end dn-qty" value="' + fqIn(it.max_send) + '" placeholder="0"><div class="small mt-1 dn-hint"></div>';
            }
            return '<tr class="dn-row" data-remaining="' + rem + '" data-stock="' + it.stock + '" data-max="' + it.max_send + '" data-unit="' + esc(it.unit) + '">' +
                '<td data-label="No.">' + (i + 1) + '</td>' +
                '<td data-label="Kode Barang">' + esc(lbl) + '</td>' +
                '<td data-label="Nama Barang"><div class="fw-semibold">' + esc(it.item_name) + '</div>' + (it.specification ? '<div class="small text-muted">' + esc(it.specification) + '</div>' : '') + '</td>' +
                '<td data-label="Qty Order" class="text-end">' + fq(it.ordered) + ' ' + esc(it.unit) + '</td>' +
                '<td data-label="Sudah Dikirim" class="text-end">' + fq(it.shipped) + '</td>' +
                '<td data-label="Sisa Pesanan" class="text-end fw-semibold">' + fq(rem) + '</td>' +
                '<td data-label="Stok Tersedia" class="text-end ' + (rem > 0.004 && it.stock + 0.004 < rem ? 'text-danger fw-semibold' : 'text-success') + '">' + fq(it.stock) + '</td>' +
                '<td data-label="Qty Dikirim">' + qtyCell + '</td></tr>';
        }).join('');
        body.classList.remove('d-none');
        itemsBody.querySelectorAll('.dn-row').forEach(check);
        if (typeof window.applyResponsiveTables === 'function') { window.applyResponsiveTables(); }
        submitBtn.disabled = !!j.blocked;
    }

    function check(tr) {
        var inp = tr.querySelector('.dn-qty'); if (!inp) { return true; }
        var qty = num(inp.value), rem = parseFloat(tr.dataset.remaining), stock = parseFloat(tr.dataset.stock),
            max = parseFloat(tr.dataset.max), unit = tr.dataset.unit, hint = tr.querySelector('.dn-hint'), bad = false, msg = '';
        if (qty < 0) { bad = true; msg = 'Qty tidak boleh negatif.'; }
        else if (qty > rem + 0.004) { bad = true; msg = 'Melebihi sisa pesanan (' + fq(rem) + ' ' + unit + ').'; }
        else if (qty > stock + 0.004) {
            bad = true;
            msg = 'Stok tersedia hanya ' + fq(stock) + ' ' + unit + ', sedangkan sisa pesanan ' + fq(rem) + ' ' + unit + '. Jumlah pengiriman maksimal saat ini adalah ' + fq(max) + ' ' + unit + '.';
        } else if (qty > 0) { msg = 'Sisa pesanan setelah ini: ' + fq(rem - qty) + ' ' + unit; }
        else if (stock + 0.004 < rem) { msg = 'Stok tersedia hanya ' + fq(stock) + ' ' + unit + ', sisa pesanan ' + fq(rem) + '. Maksimal saat ini ' + fq(max) + ' ' + unit + '.'; }
        hint.className = 'small mt-1 dn-hint ' + (bad ? 'text-danger' : 'text-muted');
        hint.textContent = msg;
        inp.classList.toggle('is-invalid', bad);
        return !bad;
    }
    itemsBody.addEventListener('input', function (e) { if (e.target.classList.contains('dn-qty')) { check(e.target.closest('tr')); } });

    // ---------- Simpan ----------
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (submitted) { return; }                       // klik ganda
        if (!idInput.value) { alert('Pilih Order Penjualan terlebih dahulu.'); search.focus(); return; }
        var no = form.querySelector('[name="delivery_number"]');
        if (!no.value.trim()) { alert('No. Surat Jalan wajib diisi.'); no.focus(); return; }
        var any = false, ok = true;
        itemsBody.querySelectorAll('.dn-row').forEach(function (tr) {
            var inp = tr.querySelector('.dn-qty'); if (!inp) { return; }
            if (num(inp.value) > 0) { any = true; }
            if (!check(tr)) { ok = false; }
        });
        if (!any) { alert('Isi Qty Dikirim minimal untuk satu barang.'); return; }
        if (!ok) { alert('Periksa kembali Qty Dikirim yang melebihi sisa pesanan atau stok.'); return; }
        confirmAction('Simpan Surat Jalan sekarang? Stok akan langsung berkurang dan dokumen Pengeluaran Barang dibuat.', 'Ya, simpan').then(function (okc) {
            if (!okc || submitted) { return; }
            submitted = true;
            submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Menyimpan...';
            form.submit();
        });
    });

    if (PRESET > 0) { pick(PRESET, ''); }
})();
</script>
