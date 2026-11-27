<?php
/**
 * Tarik dari Invoice Keluar -- pilih invoice final ber-PPN yang belum ditarik, lalu jadikan faktur PPN Keluaran.
 * Variabel: $f (tahun, masa), $years, $invoices, $pulledCount
 */
$nf = fn($v) => number_format((float) $v, 2, '.', ',');
$periodLabel = $f['masa'] > 0 ? RekapPpn::monthName($f['masa']) . ' ' . $f['tahun'] : 'Seluruh Tahun ' . $f['tahun'];
$backUrl = BASE_URL . '/rekap_ppn?' . http_build_query(['tab' => 'keluaran', 'tahun' => $f['tahun'], 'masa' => $f['masa']]);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Tarik dari Invoice Keluar</h4>
        <small class="text-muted">Invoice <strong>final</strong> ber-PPN periode <strong><?= e($periodLabel) ?></strong> yang belum ada di PPN Keluaran. Invoice sumbernya tidak diubah.</small>
    </div>
    <a href="<?= e($backUrl) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="rekap_ppn">
            <input type="hidden" name="action" value="tarikInvoice">
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Tahun (tgl invoice)</label>
                <select name="tahun" class="form-select form-select-sm">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= (int) $y ?>" <?= (int) $y === $f['tahun'] ? 'selected' : '' ?>><?= (int) $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Masa</label>
                <select name="masa" class="form-select form-select-sm">
                    <option value="0" <?= $f['masa'] === 0 ? 'selected' : '' ?>>Semua masa</option>
                    <?php foreach (RekapPpn::MONTHS as $num => $name): ?>
                        <option value="<?= $num ?>" <?= $f['masa'] === $num ? 'selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> Tampilkan</button>
            </div>
            <?php if ($pulledCount > 0): ?>
                <div class="col-12 col-md-5 small text-muted"><i class="bi bi-check2-circle"></i> <?= (int) $pulledCount ?> invoice periode ini sudah pernah ditarik (tidak ditampilkan).</div>
            <?php endif; ?>
        </form>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=rekap_ppn&action=prosesTarik" id="pullForm" data-no-draft>
    <?= csrfField() ?>
    <input type="hidden" name="tahun" value="<?= (int) $f['tahun'] ?>">
    <input type="hidden" name="masa" value="<?= (int) $f['masa'] ?>">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px"><input type="checkbox" class="form-check-input" id="pullAll" title="Pilih semua"></th>
                            <th>Tanggal</th>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>NPWP (master)</th>
                            <th>Faktur Pajak</th>
                            <th class="text-end">DPP</th>
                            <th class="text-end">PPN</th>
                            <th class="text-end">Total</th>
                            <th>Hasil tarik</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$invoices): ?>
                            <tr><td colspan="10" class="p-0">
                                <div class="empty-state">
                                    <i class="bi bi-cloud-download empty-icon"></i>
                                    <div class="empty-title">Tidak ada Invoice Keluar yang bisa ditarik pada periode ini.</div>
                                    <div class="small text-muted">Syarat: jenis Invoice (bukan Proforma), status Final, dan ber-PPN.</div>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                        <?php foreach ($invoices as $inv):
                            $complete = trim((string) $inv['tax_invoice_number']) !== '' && $inv['client_npwp'] !== ''; ?>
                            <tr>
                                <td data-label="Pilih"><input type="checkbox" class="form-check-input pull-cb" name="ids[]" value="<?= (int) $inv['id'] ?>"></td>
                                <td data-label="Tanggal" class="text-nowrap"><?= e(formatTanggal($inv['invoice_date'])) ?></td>
                                <td data-label="Invoice" class="text-nowrap"><?= e($inv['invoice_number']) ?></td>
                                <td data-label="Customer"><?= e($inv['client_name']) ?></td>
                                <td data-label="NPWP (master)" class="text-nowrap"><?= $inv['client_npwp'] !== '' ? e($inv['client_npwp']) : '<span class="text-danger small">belum ada</span>' ?></td>
                                <td data-label="Faktur Pajak" class="text-nowrap"><?= trim((string) $inv['tax_invoice_number']) !== '' ? e($inv['tax_invoice_number']) : '<span class="text-muted">-</span>' ?></td>
                                <td data-label="DPP" class="text-end text-nowrap"><?= e($nf($inv['dpp_amount'])) ?></td>
                                <td data-label="PPN" class="text-end text-nowrap"><?= e($nf($inv['ppn_amount'])) ?></td>
                                <td data-label="Total" class="text-end text-nowrap"><?= e($nf($inv['total_amount'])) ?></td>
                                <td data-label="Hasil tarik"><span class="badge bg-<?= $complete ? 'success' : 'warning text-dark' ?>"><?= $complete ? 'Lengkap' : 'Draft' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if ($invoices): ?>
        <div class="d-flex gap-2 align-items-center flex-wrap mt-3">
            <button type="submit" class="btn btn-primary" id="pullBtn" disabled><i class="bi bi-cloud-download"></i> Tarik yang dipilih (<span id="pullCount">0</span>)</button>
            <a href="<?= e($backUrl) ?>" class="btn btn-light border">Batal</a>
        </div>
        <div class="form-text mt-2">
            Tiap barang invoice menjadi satu baris; nilai ditagih (DPP + PPN, sudah memperhitungkan DP/termin) menjadi harga satuan termasuk PPN.
            Hasil tarik berstatus <strong>Lengkap</strong> bila invoice punya No. Faktur Pajak dan customer punya NPWP di master; selain itu <strong>Draft</strong>
            (lengkapi lewat Edit). Maksimal 100 invoice sekali tarik.
        </div>
    <?php endif; ?>
</form>
<script>
(function () {
    const boxes = Array.from(document.querySelectorAll('.pull-cb'));
    const all = document.getElementById('pullAll');
    const btn = document.getElementById('pullBtn');
    if (!btn) { return; }
    function sync() {
        const n = boxes.filter(function (b) { return b.checked; }).length;
        document.getElementById('pullCount').textContent = n;
        btn.disabled = n === 0 || n > 100;
        all.checked = n > 0 && n === boxes.length;
    }
    all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); sync(); });
    boxes.forEach(function (b) { b.addEventListener('change', sync); });
    sync();
})();
</script>
