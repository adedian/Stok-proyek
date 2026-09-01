<?php
/**
 * Modal quick-add Prefix Kode. Dipakai dari form Tambah Barang -- tombol "+"
 * di samping dropdown "Prefix Kode" (lihat code_preview.php). Karena form Barang
 * punya beberapa kartu prefix (satu per Jenis Stok), target <select> + entity_type
 * di-resolve dinamis lewat window.__quickAddPrefixTarget saat tombol diklik.
 * Butuh permission 'master_kode'.'edit'.
 */
?>
<div class="modal fade" id="modalQuickAddPrefix" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="formQuickAddPrefix">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-tag"></i> Tambah Prefix Kode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <input type="hidden" name="entity_type" id="quickAddPrefixEntityType" value="">
                    <div class="mb-2">
                        <label class="form-label">Prefix <span class="text-danger">*</span></label>
                        <input type="text" name="prefix" class="form-control text-uppercase"
                               placeholder="mis. ME, EL" maxlength="10" required
                               style="text-transform:uppercase">
                        <div class="form-text">Huruf/angka saja, tanpa spasi atau simbol.</div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label">Panjang Nomor Urut</label>
                        <input type="number" name="digit_length" class="form-control" value="4" min="1" max="10">
                        <div class="form-text">Jumlah digit angka, mis. 4 &rarr; 0001.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('modalQuickAddPrefix');
    if (!modalEl) return;

    document.querySelectorAll('.js-cp-add-prefix').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('.input-group');
            window.__quickAddPrefixTarget = group ? group.querySelector('select.js-cp-prefix') : null;
            document.getElementById('quickAddPrefixEntityType').value = btn.dataset.entityType || '';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    });

    initQuickAdd({
        modalId: 'modalQuickAddPrefix',
        formId: 'formQuickAddPrefix',
        selectEl: function () { return window.__quickAddPrefixTarget || null; },
        endpoint: '<?= BASE_URL ?>/index.php?module=master_kode&action=quickAddPrefix',
        extraFill: function (opt, data) {
            opt.dataset.digit = data.digit_length || 4;
            opt.dataset.next = data.next_number || 1;
            opt.dataset.master = data.master_code || '';
        },
    });
});
</script>
