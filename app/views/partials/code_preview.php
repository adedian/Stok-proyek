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
 *   $codePrefixHideAdd  true = sembunyikan tombol "+" quick-add prefix (dipakai di
 *                        modal quick-add Barang, yang tidak memuat modal prefix-nya)
 *   $codePrefixCanAdd   override siapa yang boleh lihat tombol "+" (default: yang
 *                        boleh 'master_kode'.'edit'). Modal quick-add Barang
 *                        melonggarkan ini ke pengguna 'item'.'quick_add'.
 *
 * Format kode: PREFIX.NOMOR.MASTERCODE
 */
$codePrefixes    = $codePrefixes ?? [];
$codeMasterCode  = $codeMasterCode ?? '';
$fieldId         = $codePrefixFieldId ?? 'codePrefix';
$codePrefixHideAdd = $codePrefixHideAdd ?? false;
$codePrefixCanAdd  = $codePrefixCanAdd ?? (function_exists('can') && can('master_kode', 'edit'));
// Kelompok ber-'master_code_options' (Inventory Kantor): kode akhir dipilih pengguna lewat dropdown
// kedua (name=master_code, wajib) dan menggantikan master code tetap pada pratinjau.
require_once ROOT_PATH . '/app/models/CodeConfig.php';
$__cpMcOptions = !empty($codeEntityType) ? (new CodeConfig())->masterCodeOptions($codeEntityType) : [];
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
        <div class="<?= $__cpMcOptions ? 'col-sm-3' : 'col-sm-4' ?>">
            <label class="form-label small mb-1">Prefix Kode <span class="text-danger">*</span></label>
            <div class="input-group input-group-sm">
                <select name="code_prefix" id="<?= e($fieldId) ?>" class="form-select form-select-sm js-cp-prefix"
                        data-master="<?= e($codeMasterCode) ?>" required>
                    <?php foreach ($codePrefixes as $cfg): ?>
                        <option value="<?= e($cfg['prefix']) ?>"
                                data-digit="<?= (int) $cfg['digit_length'] ?>"
                                data-next="<?= (int) $cfg['next_number'] ?>"
                                <?php if ($__cpMcOptions): ?>data-suffix-next="<?= e(json_encode((new CodeConfig())->suffixNextNumbers($cfg + ['entity_type' => $codeEntityType]))) ?>"<?php endif; ?>
                                data-master="<?= e($cfg['master_code'] ?? $codeMasterCode) ?>">
                            <?= e($cfg['prefix']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$codePrefixHideAdd && $codePrefixCanAdd): ?>
                    <button type="button" class="btn btn-outline-secondary js-cp-add-prefix"
                            data-entity-type="<?= e($codeEntityType) ?>"
                            data-master="<?= e($codeMasterCode) ?>"
                            title="Tambah prefix baru">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($__cpMcOptions): ?>
        <div class="col-sm-4">
            <label class="form-label small mb-1">Kode Akhir <span class="text-danger">*</span></label>
            <select name="master_code" id="<?= e($fieldId) ?>_master" class="form-select form-select-sm js-cp-master" required>
                <option value="">-- Pilih Kode Akhir --</option>
                <?php foreach ($__cpMcOptions as $mcOpt): ?>
                    <option value="<?= e($mcOpt) ?>"><?= e($mcOpt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="<?= $__cpMcOptions ? 'col-sm-5' : 'col-sm-8' ?>">
            <label class="form-label small mb-1">Kode <?= e(preg_replace('/\s*[-\x{2013}].*$/u', '', $codeEntityLabel)) ?> <span class="text-muted">(otomatis)</span></label>
            <input type="text" class="form-control form-control-sm fw-bold js-cp-out" value="" readonly>
        </div>
    </div>
    <?php if ($__cpMcOptions): ?>
        <div class="form-text">Nomor urut otomatis per prefix. Kode Akhir dipilih per barang: <strong><?= e(implode(' / ', $__cpMcOptions)) ?></strong>.</div>
    <?php else: ?>
        <div class="form-text">Nomor urut otomatis per prefix. Master Code: <strong><?= e($codeMasterCode ?: '-') ?></strong> (atur di Master Kode).</div>
    <?php endif; ?>
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
        let next = parseInt(opt.dataset.next || '1', 10);
        const mSel = wrap.querySelector('.js-cp-master');
        // Dropdown Kode Akhir ada -> pratinjau menunggu pilihan pengguna (kosong = belum dipilih).
        if (mSel && !mSel.value) { out.value = ''; return; }
        const master = mSel ? mSel.value : (opt.dataset.master || sel.dataset.master || '');
        // Nomor urut TERPISAH per Kode Akhir (INV.HME / INV.EXGF / INV.EXSS).
        if (mSel && opt.dataset.suffixNext) {
            try { next = parseInt(JSON.parse(opt.dataset.suffixNext)[master] || '1', 10); } catch (err) {}
        }
        out.value = sel.value + '.' + pad(next, digit) + (master ? '.' + master : '');
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('js-cp-prefix')) refresh(e.target);
        if (e.target.classList.contains('js-cp-master')) {
            const p = e.target.closest('.row').querySelector('.js-cp-prefix');
            if (p) refresh(p);
        }
    });
    // Skrip ini dimuat sekali, tepat setelah partial PERTAMA -- di halaman dengan beberapa
    // partial (form Barang: 1 kartu per Jenis Stok) kartu lainnya belum ada di DOM saat itu,
    // jadi pratinjau awalnya kosong sampai prefix diganti manual. Tunggu DOM selesai dimuat.
    function initAll() { document.querySelectorAll('.js-cp-prefix').forEach(refresh); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
    else initAll();
})();
</script>
<?php endif; ?>
