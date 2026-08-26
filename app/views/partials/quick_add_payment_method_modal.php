<?php
/**
 * Modal quick-add Metode Pembayaran. Dipakai dari form Pembayaran supaya
 * metode baru bisa dibuat tanpa keluar dari alur tambah pembayaran.
 */
?>
<div class="modal fade" id="modalQuickAddPaymentMethod" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="formQuickAddPaymentMethod">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-credit-card-2-front"></i> Tambah Metode Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="mb-1">
                        <label class="form-label">Nama Metode <span class="text-danger">*</span></label>
                        <input type="text" name="method_name" class="form-control" placeholder="mis. QRIS, Kartu Kredit" required>
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
    initQuickAdd({
        modalId: 'modalQuickAddPaymentMethod',
        formId: 'formQuickAddPaymentMethod',
        selectEl: 'payment_method_id',
        endpoint: '<?= BASE_URL ?>/index.php?module=payment_method&action=quickStore',
    });
});
</script>
