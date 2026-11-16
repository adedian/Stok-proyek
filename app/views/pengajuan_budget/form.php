<?php
/**
 * Form Pengajuan Budget (Permintaan Otorisasi). Baris dinamis; subtotal per Rekening Sumber
 * & Grand Total dihitung live (hanya tampilan -- server menghitung & memvalidasi ulang).
 * Variabel: $mode, $pb, $items, $posted, $number, $banks, $jenisList, $categories, $tujuanList, $canSubmit
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
        <td data-label="Keterangan"><textarea name="keterangan[]" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Keterangan pembayaran"><?= e($it['keterangan'] ?? '') ?></textarea></td>
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
                    <input type="date" name="pengajuan_date" class="form-control" required value="<?= e($dateVal) ?>">
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

    recalc();
})();
</script>
