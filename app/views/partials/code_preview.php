<?php
/**
 * Partial: pilih Prefix + preview kode otomatis di form Tambah {Entity}.
 * Wajib dari pemanggil:
 *   $codePrefixes   array baris code_configs untuk entity ini (bisa kosong)
 *   $codeMasterCode string (mis. 'ITM')
 *   $codeEntityType (mis. 'supplier' / 'item_stok_proyek')
 *   $codeEntityLabel (mis. 'Supplier')
 * Opsional:
 *   $codePrefixFieldId  id unik utk <select> (default 'codePrefix')
 *   $codePrefixInline   true = render ringkas dalam 1 baris (dipakai form Barang)
 *
 * Format kode: PREFIX.NOMOR.MASTERCODE
 */
$codePrefixes    = $codePrefixes ?? [];
$codeMasterCode  = $codeMasterCode ?? '';
$fieldId         = $codePrefixFieldId ?? 'codePrefix';
?>
<?php if (empty($codePrefixes)): ?>
    <div class="alert alert-warning py-2 mb-0">
        <i class="bi bi-exclamation-triangle"></i>
        Belum ada prefix kode <?= e($codeEntityLabel) ?>.
        Tambahkan dulu di
        <a href="<?= BASE_URL ?>/master_kode/group?type=<?= e($codeEntityType) ?>" class="alert-link">Master Kode &raquo; <?= e($codeEntityLabel) ?></a>.
    </div>
<?php else: ?>
    <div class="row g-2 align-items-end">
        <div class="col-sm-4">
            <label class="form-label small mb-1">Prefix Kode <span class="text-danger">*</span></label>
            <select name="code_prefix" id="<?= e($fieldId) ?>" class="form-select form-select-sm js-cp-prefix"
                    data-master="<?= e($codeMasterCode) ?>" required>
                <?php foreach ($codePrefixes as $cfg): ?>
                    <option value="<?= e($cfg['prefix']) ?>"
                            data-digit="<?= (int) $cfg['digit_length'] ?>"
                            data-next="<?= (int) $cfg['next_number'] ?>"
                            data-master="<?= e($cfg['master_code'] ?? $codeMasterCode) ?>">
                        <?= e($cfg['prefix']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-8">
            <label class="form-label small mb-1">Kode Barang <span class="text-muted">(otomatis)</span></label>
            <input type="text" class="form-control form-control-sm fw-bold js-cp-out" value="" readonly>
        </div>
    </div>
    <div class="form-text">Nomor urut otomatis per prefix. Master Code: <strong><?= e($codeMasterCode ?: '-') ?></strong> (atur di Master Kode).</div>
<?php endif; ?>

<?php if (empty($GLOBALS['__codePreviewJsDone'])): $GLOBALS['__codePreviewJsDone'] = true; ?>
<script>
(function () {
    function pad(n, len) { n = String(n); while (n.length < len) n = '0' + n; return n; }
    function refresh(sel) {
        const wrap = sel.closest('.row');
        const out = wrap ? wrap.querySelector('.js-cp-out') : null;
        if (!out) return;
        const opt = sel.options[sel.selectedIndex];
        if (!opt) { out.value = ''; return; }
        const digit = parseInt(opt.dataset.digit || '4', 10);
        const next = parseInt(opt.dataset.next || '1', 10);
        const master = opt.dataset.master || sel.dataset.master || '';
        out.value = sel.value + '.' + pad(next, digit) + (master ? '.' + master : '');
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-cp-prefix')) refresh(e.target);
    });
    document.querySelectorAll('.js-cp-prefix').forEach(refresh);
})();
</script>
<?php endif; ?>
