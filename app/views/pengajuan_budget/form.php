<?php
/**
 * Form Pengajuan Budget (Permintaan Otorisasi). Baris dinamis; subtotal per Rekening Sumber
 * & Grand Total dihitung live (hanya tampilan -- server menghitung & memvalidasi ulang).
 * Variabel: $mode, $pb, $items, $posted, $number, $banks, $jenisList, $categories, $tujuanList, $canSubmit, $poList, $canViewPo
 * "Ambil Data dari PO": ringkasan PO dimuat lewat AJAX (poInfo, baca-saja) lalu ditambahkan sebagai baris;
 * tiap baris menyimpan purchase_order_id[] (hidden). PO asli tidak pernah diubah.
 */
$isEdit = $mode === 'edit';
$action = $isEdit ? 'update' : 'store';
$rows = $items ?: [['source_bank_id' => '', 'jenis_transaksi' => '', 'tujuan' => '', 'amount' => '', 'keterangan' => '', 'category_id' => '', 'project_id' => '', 'tujuan_id' => '']];
$dateVal = $posted['pengajuan_date'] ?? ($pb['pengajuan_date'] ?? date('Y-m-d'));
$notesVal = $posted['notes'] ?? ($pb['notes'] ?? '');
$backUrl = BASE_URL . '/pengajuan_budget' . ($isEdit ? '/detail/' . (int) $pb['id'] : '');
$fmtAmt = function ($v) {
    if ($v === '' || $v === null) {
        return '';
    }
    return rtrim(rtrim(number_format((float) $v, 2, '.', ','), '0'), '.');
};
$bankSelect = function (string $sel) use ($banks) {
    $o = '<option value="">-- Pilih --</option>';
    foreach ($banks as $b) {
        $label = MasterBank::reportLabel($b) . ($b['is_active'] ? '' : ' (nonaktif)');
        $o .= '<option value="' . (int) $b['id'] . '"' . ((string) $sel === (string) $b['id'] ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $o;
};
$jenisSelect = function (string $sel) use ($jenisList) {
    $o = '<option value="">-- Pilih --</option>';
    foreach ($jenisList as $j) {
        $o .= '<option value="' . e($j) . '"' . ($sel === $j ? ' selected' : '') . '>' . e($j) . '</option>';
    }
    return $o;
};
$catSelect = function (string $sel) use ($categories) {
    $o = '<option value="">-- Tanpa Kategori --</option>';
    foreach ($categories as $c) {
        $o .= '<option value="' . (int) $c['id'] . '"' . ((string) $sel === (string) $c['id'] ? ' selected' : '') . '>' . e($c['category_name']) . '</option>';
    }
    return $o;
};
$projSelect = function (string $sel) use ($projects) {
    $o = '<option value="">-- Tanpa Project --</option>';
    foreach ($projects as $p) {
        $o .= '<option value="' . (int) $p['id'] . '"' . ((string) $sel === (string) $p['id'] ? ' selected' : '') . '>' . e($p['project_name']) . '</option>';
    }
    return $o;
};
$tujuanSelect = function (array $it) use ($tujuanList) {
    $tid = (int) ($it['tujuan_id'] ?? 0);
    $manual = $tid === 0 && trim((string) ($it['tujuan'] ?? '')) !== '';
    $o = '<option value="">-- Pilih Rekening Tujuan --</option>';
    foreach ($tujuanList as $t) {
        $o .= '<option value="' . (int) $t['id'] . '"' . ($tid === (int) $t['id'] ? ' selected' : '') . '>' . e(MasterRekeningTujuan::label($t)) . '</option>';
    }
    $o .= '<option value="manual"' . ($manual ? ' selected' : '') . '>&#9998; Ketik manual...</option>';
    return [$o, $manual];
};
$renderRow = function (array $it) use ($tujuanSelect, $bankSelect, $jenisSelect, $catSelect, $projSelect, $fmtAmt) {
    ob_start(); ?>
    <tr class="pb-row">
        <td data-label="No" class="pb-no text-muted text-center"></td>
        <td data-label="Rekening Sumber"><select name="source_bank_id[]" class="form-select form-select-sm pb-bank"><?= $bankSelect((string) ($it['source_bank_id'] ?? '')) ?></select></td>
        <td data-label="Jenis Transaksi"><select name="jenis_transaksi[]" class="form-select form-select-sm"><?= $jenisSelect((string) ($it['jenis_transaksi'] ?? '')) ?></select></td>
        <?php [$tujuanOpts, $tujuanManual] = $tujuanSelect($it); ?>
        <td data-label="Rekening Tujuan">
            <select name="tujuan_id[]" class="form-select form-select-sm pb-tujuan"><?= $tujuanOpts ?></select>
            <input type="text" name="tujuan[]" class="form-control form-control-sm mt-1 pb-tujuan-manual<?= $tujuanManual ? '' : ' d-none' ?>" maxlength="255" autocomplete="off" value="<?= e($tujuanManual ? ($it['tujuan'] ?? '') : '') ?>" placeholder="Nama - Bank No. Rek">
        </td>
        <td data-label="Jumlah (Rp)"><input type="text" name="amount[]" class="form-control form-control-sm text-end currency-input pb-amount" inputmode="decimal" placeholder="0" value="<?= e($fmtAmt($it['amount'] ?? '')) ?>"></td>
        <td data-label="Keterangan">
            <textarea name="keterangan[]" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Keterangan pembayaran"><?= e($it['keterangan'] ?? '') ?></textarea>
            <?php $rowPo = (int) ($it['purchase_order_id'] ?? 0); ?>
            <input type="hidden" name="purchase_order_id[]" class="pb-po-id" value="<?= $rowPo ?: '' ?>">
            <div class="pb-po-badge small mt-1<?= $rowPo ? '' : ' d-none' ?>"><span class="badge bg-info-subtle text-info-emphasis border"><i class="bi bi-link-45deg"></i> Dari PO <span class="pb-po-no"><?= e($it['po_number'] ?? '') ?></span></span> <button type="button" class="btn btn-link btn-sm p-0 text-danger align-baseline pb-po-unlink" title="Lepas tautan PO (data baris tidak berubah)">lepas</button></div>
        </td>
        <td data-label="Kategori"><select name="category_id[]" class="form-select form-select-sm"><?= $catSelect((string) ($it['category_id'] ?? '')) ?></select></td>
        <td data-label="Project"><select name="project_id[]" class="form-select form-select-sm"><?= $projSelect((string) ($it['project_id'] ?? '')) ?></select></td>
        <td class="text-center cell-remove"><button type="button" class="btn btn-sm btn-outline-danger pb-remove" title="Hapus baris"><i class="bi bi-x-lg"></i></button></td>
    </tr>
    <?php
    return ob_get_clean();
};
?>
<style>
    /* Desktop/tablet: kolom punya lebar tetap supaya tidak menumpuk; tabel scroll horizontal bila layar sempit. HP memakai tampilan kartu (entry-cards). */
    @media (min-width: 768px) {
        #pbTable { min-width: 1640px; table-layout: fixed; }
        #pbTable td { vertical-align: top; }
    }
</style>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Buat' ?> Pengajuan Budget</h4>
        <small class="text-muted">No. Pengajuan: <strong><?= e($number) ?></strong> <?= $isEdit ? '' : '(otomatis; nomor resmi dibuat saat disimpan)' ?></small>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=pengajuan_budget&action=<?= $action ?>" id="pbForm" novalidate>
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $pb['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-3">Informasi Pengajuan</div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="pengajuan_date" <?= !$isEdit ? BackDate::minAttr('pengajuan_budget') : '' ?> class="form-control" required value="<?= e($dateVal) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pengaju</label>
                    <input type="text" class="form-control" value="<?= e($isEdit ? ($pb['created_by_name'] ?? '') : currentUserName()) ?>" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <input type="text" class="form-control" value="<?= e(PengajuanBudget::statusLabel($pb['status'] ?? PengajuanBudget::DRAFT)) ?>" readonly>
                </div>
                <div class="col-12">
                    <label class="form-label">Catatan Umum</label>
                    <textarea name="notes" class="form-control" rows="2" maxlength="2000" placeholder="Opsional"><?= e($notesVal) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-section-title mb-0">Daftar Pembayaran</div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="pbAddRow" data-inline-add="#pbItemsBody"><i class="bi bi-plus-circle"></i> Tambah Baris</button>
            </div>
            <div class="border rounded p-2 mb-3 bg-light-subtle" id="pbPoPicker">
                <label class="form-label small fw-semibold mb-1" for="pbPoSelect"><i class="bi bi-link-45deg"></i> Ambil Data dari PO <span class="text-muted fw-normal">(opsional &mdash; data PO tidak diubah)</span></label>
                <select id="pbPoSelect" class="form-select form-select-sm">
                    <option value="">-- Cari No. PO / Supplier / Project --</option>
                    <?php foreach ($poList as $po): ?>
                        <option value="<?= (int) $po['id'] ?>"><?= e($po['po_number'] . ' | ' . $po['supplier_name'] . ' | ' . ($po['project_name'] ?: '-') . ' | ' . ($po['currency'] === 'IDR' ? formatRupiah($po['total_amount']) : $po['currency'] . ' ' . number_format((float) $po['total_amount'], 2)) . ' | ' . formatTanggal($po['po_date'])) ?></option>
                    <?php endforeach; ?>
                </select>
                <div id="pbPoSummary" class="d-none mt-2"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle entry-cards mb-0" id="pbTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px">No</th>
                            <th style="width:200px">Rekening Sumber <span class="text-danger">*</span></th>
                            <th style="width:150px">Jenis Transaksi <span class="text-danger">*</span></th>
                            <th style="width:240px">Rekening Tujuan <span class="text-danger">*</span>
                            </th>
                            <th style="width:160px" class="text-end">Jumlah Pembayaran <span class="text-danger">*</span></th>
                            <th style="width:280px">Keterangan</th>
                            <th style="width:170px">Kategori</th>
                            <th style="width:190px">Project</th>
                            <th style="width:90px"></th>
                        </tr>
                    </thead>
                    <tbody id="pbItemsBody">
                        <?php foreach ($rows as $it): echo $renderRow($it); endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-text mt-2">Rekening Sumber diambil dari Master Bank. Pengajuan hanya mencatat <strong>rencana</strong> pembayaran &mdash; saldo Bank tidak berubah dan tidak ada transaksi Bank Keluar yang dibuat otomatis.</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="card-section-title mb-2">Subtotal per Rekening Sumber</div>
            <div id="pbSubtotals" class="table-responsive"></div>
            <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                <strong>TOTAL KESELURUHAN</strong>
                <strong class="fs-5" id="pbGrand">Rp 0</strong>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan sebagai Draft</button>
        <?php if ($canSubmit): ?>
            <button type="submit" name="submit_after" value="1" class="btn btn-success"><i class="bi bi-send"></i> Simpan &amp; Ajukan</button>
        <?php endif; ?>
        <a href="<?= e($backUrl) ?>" class="btn btn-light border">Batal</a>
    </div>
</form>

<template id="pbRowTemplate"><?= $renderRow(['source_bank_id' => '', 'jenis_transaksi' => '', 'tujuan' => '', 'amount' => '', 'keterangan' => '', 'category_id' => '', 'project_id' => '', 'tujuan_id' => '']) ?></template>


<script>
(function () {
    const body = document.getElementById('pbItemsBody');
    const tpl = document.getElementById('pbRowTemplate');
    const form = document.getElementById('pbForm');

    // Sama dengan parseCurrencyInput() di server: koma = ribuan, titik = desimal.
    function num(v) {
        v = String(v || '').replace(/,/g, '').trim();
        const n = parseFloat(v);
        return isNaN(n) ? 0 : n;
    }
    function rp(n) {
        return 'Rp ' + n.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }

    function recalc() {
        const groups = {};
        const order = [];
        let grand = 0;
        body.querySelectorAll('.pb-row').forEach(function (tr, i) {
            tr.querySelector('.pb-no').textContent = i + 1;
            const sel = tr.querySelector('.pb-bank');
            const amt = num(tr.querySelector('.pb-amount').value);
            if (!sel.value || amt <= 0) { return; }
            const label = sel.options[sel.selectedIndex].text;
            if (!groups[sel.value]) { groups[sel.value] = { label: label, n: 0, total: 0 }; order.push(sel.value); }
            groups[sel.value].n++;
            groups[sel.value].total += amt;
            grand += amt;
        });
        let html = '';
        if (!order.length) {
            html = '<div class="text-muted small">Belum ada baris dengan rekening sumber &amp; nominal.</div>';
        } else {
            html = '<table class="table table-sm mb-0"><tbody>';
            order.forEach(function (k) {
                html += '<tr><td>' + esc(groups[k].label) + ' <span class="text-muted small">(' + groups[k].n + ' baris)</span></td><td class="text-end fw-semibold">' + rp(groups[k].total) + '</td></tr>';
            });
            html += '</tbody></table>';
        }
        document.getElementById('pbSubtotals').innerHTML = html;
        document.getElementById('pbGrand').textContent = rp(grand);
    }

    // Rekening Tujuan: pilih dari master atau "Ketik manual..." (menampilkan kolom teks).
    function syncTujuan(sel) {
        const inp = sel.closest('td').querySelector('.pb-tujuan-manual');
        const manual = sel.value === 'manual';
        inp.classList.toggle('d-none', !manual);
        if (manual) { inp.focus(); } else { inp.value = ''; }
    }
    body.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('pb-tujuan')) { syncTujuan(e.target); }
    });

    // Quick-add Master Rekening Tujuan: opsi baru ditambahkan ke SEMUA baris + template, dan dipilih di baris aktif.
    let lastTujuanSelect = null;
    body.addEventListener('focusin', function (e) {
        if (e.target.closest && e.target.closest('td') && e.target.closest('td').querySelector('.pb-tujuan')) {
            lastTujuanSelect = e.target.closest('td').querySelector('.pb-tujuan');
        }
    });
    const tForm = document.getElementById('pbTujuanForm');
    if (tForm) {
        tForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            const err = document.getElementById('pbTujuanErr');
            err.classList.add('d-none');
            const btn = document.getElementById('pbTujuanSave');
            btn.disabled = true;
            fetch('<?= BASE_URL ?>/index.php?module=master_rekening_tujuan&action=quickStore', { method: 'POST', body: new FormData(tForm), credentials: 'same-origin' })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    btn.disabled = false;
                    if (!res.ok) { err.textContent = (res.j.errors || ['Gagal menyimpan.']).join(' '); err.classList.remove('d-none'); return; }
                    const addOpt = function (sel, select) {
                        const o = document.createElement('option');
                        o.value = res.j.id; o.textContent = res.j.label;
                        const manualOpt = sel.querySelector('option[value="manual"]');
                        sel.insertBefore(o, manualOpt);
                        if (select) { sel.value = String(res.j.id); }
                    };
                    const target = lastTujuanSelect && body.contains(lastTujuanSelect) ? lastTujuanSelect : body.querySelector('.pb-tujuan');
                    body.querySelectorAll('.pb-tujuan').forEach(function (sel) {
                        addOpt(sel, sel === target);
                        if (window.resyncSearchableSelect) { window.resyncSearchableSelect(sel); }
                    });
                    tpl.content.querySelectorAll('.pb-tujuan').forEach(function (sel) { addOpt(sel, false); });
                    if (target) { syncTujuan(target); }
                    tForm.reset();
                    const m = bootstrap.Modal.getInstance(document.getElementById('pbTujuanModal'));
                    if (m) { m.hide(); }
                })
                .catch(function () { btn.disabled = false; err.textContent = 'Gagal menyimpan (jaringan).'; err.classList.remove('d-none'); });
        });
    }

    document.getElementById('pbAddRow').addEventListener('click', function () {
        body.appendChild(tpl.content.cloneNode(true));
        recalc();
        const last = body.querySelector('.pb-row:last-child select');
        if (last) { last.focus(); }
    });
    body.addEventListener('click', function (e) {
        const btn = e.target.closest('.pb-remove');
        if (!btn) { return; }
        if (body.querySelectorAll('.pb-row').length <= 1) {
            body.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
            body.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; if (window.resyncSearchableSelect) { window.resyncSearchableSelect(s); } });
            body.querySelectorAll('.pb-tujuan-manual').forEach(function (i) { i.classList.add('d-none'); });
            body.querySelectorAll('.pb-po-badge').forEach(function (i) { i.classList.add('d-none'); });
        } else {
            btn.closest('.pb-row').remove();
        }
        recalc();
    });
    body.addEventListener('input', recalc);
    body.addEventListener('change', recalc);

    form.addEventListener('submit', function (e) {
        let msg = '';
        if (!form.pengajuan_date.value) { msg = 'Tanggal wajib diisi.'; }
        let filled = 0;
        body.querySelectorAll('.pb-row').forEach(function (tr, i) {
            const f = tr.querySelectorAll('select, input[type=text], textarea');
            const bank = tr.querySelector('.pb-bank').value;
            const jenis = tr.querySelector('[name="jenis_transaksi[]"]').value;
            const tsel = tr.querySelector('.pb-tujuan').value;
            const tujuan = tsel === 'manual' ? tr.querySelector('[name="tujuan[]"]').value.trim() : tsel;
            const amt = num(tr.querySelector('.pb-amount').value);
            const ket = tr.querySelector('[name="keterangan[]"]').value.trim();
            if (!bank && !jenis && !tujuan && !amt && !ket) { return; }
            filled++;
            if (!msg) {
                if (!bank) { msg = 'Baris ' + (i + 1) + ': Rekening Sumber wajib dipilih.'; }
                else if (!jenis) { msg = 'Baris ' + (i + 1) + ': Jenis Transaksi wajib dipilih.'; }
                else if (!tujuan) { msg = 'Baris ' + (i + 1) + ': Rekening Tujuan wajib diisi.'; }
                else if (amt <= 0) { msg = 'Baris ' + (i + 1) + ': Jumlah harus lebih dari 0.'; }
            }
        });
        if (!filled && !msg) { msg = 'Minimal harus ada 1 baris pembayaran.'; }
        if (msg) { e.preventDefault(); alert(msg); }
    });


    // ---- Ambil Data dari PO (baca-saja; hasilnya hanya mengisi BARIS BARU / baris kosong, tak menimpa input) ----
    const poSel = document.getElementById('pbPoSelect');
    const poBox = document.getElementById('pbPoSummary');
    const EXCLUDE_ID = <?= $isEdit ? (int) $pb['id'] : 0 ?>;
    let currentPo = null, canViewPo = false;

    function money(n, cur) {
        return (cur && cur !== 'IDR' ? cur + ' ' : 'Rp ') + Number(n).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }
    function renderPo(po) {
        const idr = po.currency === 'IDR';
        const head = canViewPo
            ? '<a href="<?= BASE_URL ?>/purchase_order/detail/' + po.id + '" target="_blank" rel="noopener">' + esc(po.po_number) + '</a>'
            : esc(po.po_number);
        let h = '<div class="card card-body py-2 px-3 small">';
        h += '<div class="d-flex flex-wrap gap-3"><div><span class="text-muted">No. PO</span><br><strong>' + head + '</strong></div>'
            + '<div><span class="text-muted">Tanggal</span><br>' + esc(po.po_date) + '</div>'
            + '<div><span class="text-muted">Supplier</span><br>' + esc(po.supplier_name) + '</div>'
            + '<div><span class="text-muted">Project</span><br>' + esc(po.project_name || '-') + '</div>'
            + '<div><span class="text-muted">Kategori Biaya</span><br>' + esc(po.cost_category_name || '-') + '</div>'
            + '<div><span class="text-muted">Status PO</span><br>' + esc(po.status) + '</div>'
            + '<div><span class="text-muted">Total PO</span><br><strong>' + money(po.total_amount, po.currency) + '</strong></div></div>';
        if (po.items && po.items.length) {
            h += '<div class="table-responsive mt-2"><table class="table table-sm table-bordered mb-1"><thead class="table-light"><tr><th>Item</th><th class="text-end">Qty</th><th>Sat.</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
            po.items.forEach(function (it) {
                h += '<tr><td>' + esc(it.item_name) + '</td><td class="text-end">' + Number(it.qty_order).toLocaleString('en-US') + '</td><td>' + esc(it.unit) + '</td><td class="text-end">' + money(it.price, po.currency) + '</td><td class="text-end">' + money(it.subtotal, po.currency) + '</td></tr>';
            });
            (po.extra_costs || []).forEach(function (c) {
                h += '<tr><td colspan="4" class="text-end">' + esc(c.cost_name) + '</td><td class="text-end">' + money(c.amount, po.currency) + '</td></tr>';
            });
            h += '</tbody></table></div>';
        }
        if (idr) {
            h += '<div class="mb-1">Sudah diajukan di pengajuan lain: <strong>' + money(po.proposed_total, 'IDR') + '</strong>'
                + (po.proposed_numbers.length ? ' <span class="text-muted">(' + esc(po.proposed_numbers.join(', ')) + ')</span>' : '')
                + ' &middot; Sisa yang belum diajukan: <strong class="text-success">' + money(po.remaining, 'IDR') + '</strong></div>';
        } else {
            h += '<div class="mb-1 text-warning-emphasis">PO bermata uang ' + esc(po.currency) + ' &mdash; isi Jumlah Pembayaran dalam Rupiah secara manual.</div>';
        }
        h += '<div><button type="button" class="btn btn-sm btn-primary" id="pbPoAdd"><i class="bi bi-plus-circle"></i> Tambahkan ke Daftar Pembayaran</button></div></div>';
        poBox.innerHTML = h;
        poBox.classList.remove('d-none');
        document.getElementById('pbPoAdd').addEventListener('click', function () { addPoRow(currentPo); });
    }
    function loadPo() {
        currentPo = null;
        poBox.classList.add('d-none');
        if (!poSel.value) { return; }
        poBox.innerHTML = '<div class="text-muted small">Memuat data PO...</div>';
        poBox.classList.remove('d-none');
        fetch('<?= BASE_URL ?>/index.php?module=pengajuan_budget&action=poInfo&id=' + encodeURIComponent(poSel.value) + '&exclude=' + EXCLUDE_ID, { credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (!res.ok) { poBox.innerHTML = '<div class="text-danger small">' + esc(res.j.error || 'Gagal memuat PO.') + '</div>'; return; }
                currentPo = res.j.po; canViewPo = !!res.j.can_view_po;
                renderPo(currentPo);
            })
            .catch(function () { poBox.innerHTML = '<div class="text-danger small">Gagal memuat PO (jaringan).</div>'; });
    }
    poSel.addEventListener('change', loadPo);

    function rowIsEmpty(tr) {
        return !tr.querySelector('.pb-bank').value
            && !tr.querySelector('[name="jenis_transaksi[]"]').value
            && !tr.querySelector('.pb-tujuan').value
            && !tr.querySelector('.pb-amount').value.trim()
            && !tr.querySelector('[name="keterangan[]"]').value.trim()
            && !tr.querySelector('.pb-po-id').value;
    }
    function setSelect(sel, value) {
        if (!sel || value === null || value === undefined || value === '') { return; }
        if (Array.prototype.some.call(sel.options, function (o) { return o.value === String(value); })) {
            sel.value = String(value);
            if (window.resyncSearchableSelect) { window.resyncSearchableSelect(sel); }
        }
    }
    function doAddPoRow(po) {
        // Baris kosong dipakai dulu; kalau tak ada, baris baru ditambahkan -- data yang sudah diketik TIDAK ditimpa.
        let tr = Array.prototype.find.call(body.querySelectorAll('.pb-row'), rowIsEmpty);
        if (!tr) {
            body.appendChild(tpl.content.cloneNode(true));
            tr = body.querySelector('.pb-row:last-child');
        }
        tr.querySelector('.pb-po-id').value = po.id;
        tr.querySelector('.pb-po-no').textContent = po.po_number;
        tr.querySelector('.pb-po-badge').classList.remove('d-none');
        if (po.currency === 'IDR' && Number(po.remaining) > 0) {
            const a = tr.querySelector('.pb-amount');
            a.value = Number(po.remaining).toLocaleString('en-US', { maximumFractionDigits: 2 });
            a.dispatchEvent(new Event('input', { bubbles: true }));
        }
        const ket = tr.querySelector('[name="keterangan[]"]');
        if (!ket.value.trim()) { ket.value = 'Pembayaran PO ' + po.po_number + ' - ' + po.supplier_name; }
        setSelect(tr.querySelector('[name="project_id[]"]'), po.project_id);
        setSelect(tr.querySelector('[name="category_id[]"]'), po.cost_category_id);
        // Rekening Tujuan: otomatis hanya bila TEPAT 1 master yang namanya memuat nama supplier.
        const ts = tr.querySelector('.pb-tujuan');
        if (!ts.value) {
            const nm = String(po.supplier_name || '').toLowerCase().trim();
            const hits = nm ? Array.prototype.filter.call(ts.options, function (o) { return o.value && o.value !== 'manual' && o.textContent.toLowerCase().indexOf(nm) !== -1; }) : [];
            if (hits.length === 1) { setSelect(ts, hits[0].value); syncTujuan(ts); }
        }
        recalc();
        tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const bank = tr.querySelector('.pb-bank');
        if (bank) { bank.focus(); }
    }
    function addPoRow(po) {
        if (!po) { return; }
        const dup = Array.prototype.some.call(body.querySelectorAll('.pb-po-id'), function (i) { return i.value === String(po.id); });
        if (!dup) { doAddPoRow(po); return; }
        const msg = 'PO ' + po.po_number + ' sudah ada di salah satu baris pengajuan ini. Tambahkan baris lagi untuk PO yang sama (mis. termin berikutnya)?';
        if (typeof confirmAction === 'function') {
            confirmAction(msg, 'Ya, tambahkan').then(function (ok) { if (ok) { doAddPoRow(po); } });
        } else if (window.confirm(msg)) { doAddPoRow(po); }
    }
    body.addEventListener('click', function (e) {
        const b = e.target.closest('.pb-po-unlink');
        if (!b) { return; }
        const tr = b.closest('.pb-row');
        tr.querySelector('.pb-po-id').value = '';
        tr.querySelector('.pb-po-badge').classList.add('d-none');
    });

    recalc();
})();
</script>
